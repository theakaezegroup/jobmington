<?php
define('JOBMINGTON', true);
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/_job_helpers.php';

Session::start();
$pdo = db();

$q = trim(Security::clean(get('q', '')));
$category = trim(Security::clean(get('category', '')));
$countryId = (int) get('country_id', 0);
$type = trim(Security::clean(get('type', '')));
$page = max(1, (int) get('page', 1));
$perPage = JOBS_PER_PAGE;

$where = ["j.is_active = 1", "(j.expires_at IS NULL OR j.expires_at >= CURDATE())"];
$params = [];

if ($q !== '') {
    $where[] = "(j.title LIKE ? OR j.description LIKE ? OR j.requirements LIKE ? OR co.name LIKE ?)";
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like, $like);
}

if ($category !== '') {
    if (ctype_digit($category)) {
        $where[] = "j.category_id = ?";
        $params[] = (int) $category;
    } else {
        $where[] = "jc.slug = ?";
        $params[] = $category;
    }
}

if ($countryId > 0) {
    $where[] = "j.country_id = ?";
    $params[] = $countryId;
}

if ($type !== '' && in_array($type, JOB_TYPES, true)) {
    $where[] = "j.job_type = ?";
    $params[] = $type;
}

$whereSql = implode(' AND ', $where);

$countStmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM jobs j
    JOIN companies co ON j.company_id = co.company_id
    LEFT JOIN job_categories jc ON j.category_id = jc.category_id
    WHERE {$whereSql}
");
$countStmt->execute($params);
$totalJobs = (int) $countStmt->fetchColumn();
$pagination = paginate($totalJobs, $perPage, $page);

$stmt = $pdo->prepare("
    SELECT j.*, co.name AS company_name, co.logo AS company_logo,
           c.name AS country_name, c.currency_symbol, jc.name AS category_name
    FROM jobs j
    JOIN companies co ON j.company_id = co.company_id
    LEFT JOIN countries c ON j.country_id = c.country_id
    LEFT JOIN job_categories jc ON j.category_id = jc.category_id
    WHERE {$whereSql}
    ORDER BY j.is_featured DESC, j.posted_at DESC
    LIMIT ? OFFSET ?
");
$executeParams = array_merge($params, [$pagination['per_page'], $pagination['offset']]);
$stmt->execute($executeParams);
$jobs = $stmt->fetchAll();

$countries = $pdo->query("SELECT country_id, name FROM countries WHERE is_active = 1 ORDER BY name")->fetchAll();
$categories = $pdo->query("SELECT category_id, name, slug FROM job_categories ORDER BY name")->fetchAll();
$pageTitle = 'Find Jobs | ' . SITE_NAME;

jm_jobs_header($pageTitle, 'jobs');
?>

<section class="jm-section" style="padding-top:0;">
    <div class="jm-section-head">
        <div>
            <p class="jm-kicker">Find jobs</p>
            <h1><?= $q !== '' ? 'Search results.' : 'Open roles.' ?></h1>
            <p><?= number_format($totalJobs) ?> active <?= $totalJobs === 1 ? 'job' : 'jobs' ?><?= $q !== '' ? ' for "' . e($q) . '"' : '' ?>.</p>
        </div>
        <a class="jm-button secondary" href="/jobmington/employer/post-job.php">Post a job</a>
    </div>

    <form class="jm-filter-grid" method="get" action="/jobmington/jobs/">
        <div class="jm-field">
            <label for="q">Keyword</label>
            <input class="jm-input" id="q" type="search" name="q" value="<?= e($q) ?>" placeholder="Title, skill, or company">
        </div>
        <div class="jm-field">
            <label for="category">Category</label>
            <select class="jm-select" id="category" name="category">
                <option value="">All categories</option>
                <?php foreach ($categories as $cat): ?>
                    <?php $catValue = $cat['slug'] ?: (string) $cat['category_id']; ?>
                    <option value="<?= e($catValue) ?>" <?= $category === $catValue ? 'selected' : '' ?>><?= e($cat['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="jm-field">
            <label for="country_id">Country</label>
            <select class="jm-select" id="country_id" name="country_id">
                <option value="">All</option>
                <?php foreach ($countries as $country): ?>
                    <option value="<?= (int) $country['country_id'] ?>" <?= $countryId === (int) $country['country_id'] ? 'selected' : '' ?>><?= e($country['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="jm-field">
            <label for="type">Type</label>
            <select class="jm-select" id="type" name="type">
                <option value="">Any</option>
                <?php foreach (JOB_TYPES as $jobType): ?>
                    <option value="<?= e($jobType) ?>" <?= $type === $jobType ? 'selected' : '' ?>><?= e($jobType) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button class="jm-button" type="submit">Filter</button>
    </form>

    <?php if (empty($jobs)): ?>
        <?= jm_empty_state_card('no_jobs_found', [
            'actions' => '<a class="jm-button" href="/jobmington/jobs/">Clear filters</a>',
        ]) ?>
    <?php else: ?>
        <div class="jm-job-list">
            <?php foreach ($jobs as $job): ?>
                <a class="jm-job-row" href="/jobmington/jobs/view.php?id=<?= (int) $job['job_id'] ?>">
                    <strong>
                        <?= e($job['title']) ?>
                        <small class="jm-job-subtitle">
                            <?= e($job['company_name']) ?> / <?= e(jm_job_location($job)) ?>
                        </small>
                        <small class="jm-tag-row compact">
                            <?php foreach (jm_job_tags($job, 4) as $tag): ?>
                                <span class="jm-job-tag <?= e('tone-' . $tag['tone']) ?>"><?= e($tag['label']) ?></span>
                            <?php endforeach; ?>
                        </small>
                    </strong>
                    <span><?= e(jm_job_salary($job)) ?><br><?= e($job['job_type']) ?> / <?= e(timeAgo($job['posted_at'])) ?></span>
                </a>
            <?php endforeach; ?>
        </div>

        <div class="jm-form-actions" style="justify-content:center;margin-top:34px;">
            <?php
                $query = $_GET;
                unset($query['page']);
                $base = '/jobmington/jobs/?' . http_build_query($query);
                $join = str_ends_with($base, '?') ? '' : '&';
            ?>
            <?php if ($pagination['has_previous']): ?>
                <a class="jm-button secondary" href="<?= e($base . $join . 'page=' . $pagination['previous_page']) ?>">Previous</a>
            <?php endif; ?>
            <?php if ($pagination['has_next']): ?>
                <a class="jm-button secondary" href="<?= e($base . $join . 'page=' . $pagination['next_page']) ?>">Next</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</section>

<?php jm_jobs_footer('/jobmington/employer/post-job.php', 'Post a job'); ?>
