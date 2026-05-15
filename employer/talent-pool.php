<?php
define('JOBMINGTON', true);
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/_employer_helpers.php';

Session::start();
Session::requireRole(USER_TYPE_EMPLOYER);

$pdo = db();
$userId = Session::userId();
$company = jm_employer_company_or_redirect($pdo, $userId);

$search = Security::clean($_GET['q'] ?? '');
$location = Security::clean($_GET['location'] ?? '');
$level = Security::clean($_GET['level'] ?? '');
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 12;

$where = ["u.user_type = 'seeker'", 'u.is_active = 1'];
$params = [];

if ($search !== '') {
    $where[] = '(u.full_name LIKE ? OR u.headline LIKE ? OR cv.headline LIKE ? OR cv.summary LIKE ?)';
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like, $like);
}

if ($location !== '') {
    $where[] = '(u.city LIKE ? OR cv.city LIKE ?)';
    $like = '%' . $location . '%';
    array_push($params, $like, $like);
}

if ($level !== '') {
    $where[] = 'tp.level = ?';
    $params[] = $level;
}

$whereClause = implode(' AND ', $where);

$stmt = $pdo->prepare("
    SELECT COUNT(DISTINCT u.user_id)
    FROM users u
    LEFT JOIN cv_profiles cv ON u.user_id = cv.user_id
    LEFT JOIN talent_passports tp ON u.user_id = tp.user_id AND tp.is_public = 1
    WHERE {$whereClause}
");
$stmt->execute($params);
$totalTalents = (int) $stmt->fetchColumn();
$pagination = paginate($totalTalents, $perPage, $page);

$stmt = $pdo->prepare("
    SELECT u.user_id, u.full_name, u.email, u.phone, u.profile_image, u.headline, u.bio, u.city AS user_city,
           cv.headline AS cv_headline, cv.summary AS cv_summary, cv.city AS cv_city,
           tp.level AS passport_level, tp.level_points,
           (SELECT COUNT(*) FROM job_applications ja WHERE ja.user_id = u.user_id) AS application_count
    FROM users u
    LEFT JOIN cv_profiles cv ON u.user_id = cv.user_id
    LEFT JOIN talent_passports tp ON u.user_id = tp.user_id AND tp.is_public = 1
    WHERE {$whereClause}
    GROUP BY u.user_id
    ORDER BY COALESCE(tp.level_points, 0) DESC, u.created_at DESC
    LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}
");
$stmt->execute($params);
$talents = $stmt->fetchAll();

$levels = $pdo->query("
    SELECT DISTINCT level
    FROM talent_passports
    WHERE is_public = 1 AND level IS NOT NULL AND level != ''
    ORDER BY level
")->fetchAll(PDO::FETCH_COLUMN);

$pageTitle = 'Talent Pool | ' . SITE_NAME;
jm_employer_header($pageTitle, 'talent');
?>

<section class="jm-section" style="padding-top:0;">
    <div class="jm-section-head">
        <div>
            <p class="jm-kicker">Talent</p>
            <h1>Browse job seekers.</h1>
            <p>Find people who may be a fit for <?= e($company['name']) ?> roles.</p>
        </div>
        <a class="jm-button" href="/jobmington/employer/post-job.php">Post a job</a>
    </div>

    <form method="get" class="jm-filter-grid" style="grid-template-columns:minmax(0,1fr) 200px 180px auto;">
        <div class="jm-field">
            <label for="q">Search talent</label>
            <input class="jm-input" id="q" name="q" value="<?= e($search) ?>" placeholder="Name, role, skill, or summary">
        </div>
        <div class="jm-field">
            <label for="location">Location</label>
            <input class="jm-input" id="location" name="location" value="<?= e($location) ?>" placeholder="Lagos, Abuja, Remote">
        </div>
        <div class="jm-field">
            <label for="level">Passport</label>
            <select class="jm-select" id="level" name="level">
                <option value="">All levels</option>
                <?php foreach ($levels as $passportLevel): ?>
                    <option value="<?= e($passportLevel) ?>" <?= $level === $passportLevel ? 'selected' : '' ?>><?= e(ucfirst($passportLevel)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button class="jm-button" type="submit">Search</button>
    </form>

    <?php if (empty($talents)): ?>
        <div class="jm-empty">
            <h2>No talent found</h2>
            <p>Try a broader role, skill, or location search.</p>
            <a class="jm-button secondary" href="/jobmington/employer/talent-pool.php">Clear filters</a>
        </div>
    <?php else: ?>
        <div class="jm-grid-3">
            <?php foreach ($talents as $talent): ?>
                <article class="jm-card">
                    <div style="display:flex;gap:14px;align-items:center;margin-bottom:16px;">
                        <img src="<?= e(profileImage($talent['profile_image'] ?? null)) ?>" alt="" style="width:54px;height:54px;object-fit:cover;border:1px solid var(--jm-line);">
                        <div>
                            <h3 style="margin-bottom:2px;"><?= e($talent['full_name'] ?: 'Job seeker') ?></h3>
                            <p style="margin:0;"><?= e($talent['cv_headline'] ?: $talent['headline'] ?: 'Open to work') ?></p>
                        </div>
                    </div>
                    <p><?= e(excerpt($talent['cv_summary'] ?: $talent['bio'] ?: 'No summary yet.', 150)) ?></p>
                    <div class="jm-inline-actions">
                        <span class="jm-badge"><?= e($talent['cv_city'] ?: $talent['user_city'] ?: 'Location open') ?></span>
                        <?php if (!empty($talent['passport_level'])): ?><span class="jm-badge"><?= e(ucfirst($talent['passport_level'])) ?></span><?php endif; ?>
                    </div>
                    <div class="jm-form-actions">
                        <a class="jm-muted-link" href="mailto:<?= e($talent['email']) ?>">Contact</a>
                        <span class="jm-small"><?= number_format((int) $talent['application_count']) ?> application<?= (int) $talent['application_count'] === 1 ? '' : 's' ?></span>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>

        <div class="jm-form-actions" style="justify-content:center;">
            <?php if ($pagination['has_previous']): ?>
                <a class="jm-button secondary" href="/jobmington/employer/talent-pool.php?<?= http_build_query(array_filter(['q' => $search, 'location' => $location, 'level' => $level, 'page' => $pagination['previous_page']])) ?>">Previous</a>
            <?php endif; ?>
            <?php if ($pagination['has_next']): ?>
                <a class="jm-button secondary" href="/jobmington/employer/talent-pool.php?<?= http_build_query(array_filter(['q' => $search, 'location' => $location, 'level' => $level, 'page' => $pagination['next_page']])) ?>">Next</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</section>

<?php jm_employer_footer('/jobmington/employer/dashboard.php', 'Employer dashboard'); ?>
