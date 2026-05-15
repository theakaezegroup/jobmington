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
$companyId = (int) $company['company_id'];
$applicationId = (int) ($_GET['id'] ?? 0);

if ($applicationId <= 0) {
    redirect('/jobmington/employer/applications.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::verifyCSRF()) {
        Session::flash('error', 'Please refresh the page and try again.');
    } else {
        $status = $_POST['status'] ?? '';
        if (in_array($status, APPLICATION_STATUSES, true)) {
            $stmt = $pdo->prepare("
                UPDATE job_applications ja
                JOIN jobs j ON ja.job_id = j.job_id
                SET ja.status = ?, ja.updated_at = NOW()
                WHERE ja.application_id = ? AND j.company_id = ?
            ");
            $stmt->execute([$status, $applicationId, $companyId]);
            Session::flash('success', 'Application status updated.');
            Security::regenerateCSRF();
        }
    }

    redirect('/jobmington/employer/view-applicant.php?id=' . $applicationId);
}

$stmt = $pdo->prepare("
    SELECT ja.*, j.title AS job_title, j.job_id, j.job_type, j.city AS job_city,
           u.user_id, u.full_name, u.email, u.phone, u.profile_image, u.headline, u.bio, u.city AS user_city,
           cv.cv_id, cv.title AS cv_title, cv.headline AS cv_headline, cv.summary AS cv_summary, cv.city AS cv_city
    FROM job_applications ja
    JOIN jobs j ON ja.job_id = j.job_id
    LEFT JOIN users u ON ja.user_id = u.user_id
    LEFT JOIN cv_profiles cv ON u.user_id = cv.user_id
    WHERE ja.application_id = ? AND j.company_id = ?
    LIMIT 1
");
$stmt->execute([$applicationId, $companyId]);
$application = $stmt->fetch();

if (!$application) {
    Session::flash('error', 'Application not found.');
    redirect('/jobmington/employer/applications.php');
}

$cvUrl = '';
if (!empty($application['cv_path'])) {
    $cvUrl = upload(strpos($application['cv_path'], '/') !== false ? $application['cv_path'] : 'resumes/' . $application['cv_path']);
}

$pageTitle = 'Applicant | ' . SITE_NAME;
jm_employer_header($pageTitle, 'applications');
?>

<section class="jm-section" style="padding-top:0;">
    <div class="jm-section-head">
        <div>
            <p class="jm-kicker">Applicant</p>
            <h1><?= e($application['full_name'] ?: 'Candidate') ?></h1>
            <p>Applied for <?= e($application['job_title']) ?> on <?= e(formatDate($application['applied_at'])) ?>.</p>
        </div>
        <div class="jm-form-actions">
            <a class="jm-button secondary" href="/jobmington/employer/applications.php">Applications</a>
            <a class="jm-button secondary" href="/jobmington/jobs/view.php?id=<?= (int) $application['job_id'] ?>">Job page</a>
        </div>
    </div>

    <?php jm_employer_flash(); ?>

    <div class="jm-grid-2">
        <main class="jm-panel">
            <div style="display:flex;gap:18px;align-items:center;margin-bottom:24px;">
                <img src="<?= e(profileImage($application['profile_image'] ?? null)) ?>" alt="" style="width:72px;height:72px;object-fit:cover;border:1px solid var(--jm-line);">
                <div>
                    <h2 style="margin-bottom:4px;"><?= e($application['full_name'] ?: 'Candidate') ?></h2>
                    <p style="margin:0;"><?= e($application['headline'] ?: $application['cv_headline'] ?: 'Job seeker') ?></p>
                </div>
            </div>

            <div class="jm-key-list">
                <div><span>Email</span><strong><?= e($application['email'] ?: 'Not available') ?></strong></div>
                <div><span>Phone</span><strong><?= e($application['phone'] ?: 'Not available') ?></strong></div>
                <div><span>Location</span><strong><?= e($application['cv_city'] ?: $application['user_city'] ?: 'Not provided') ?></strong></div>
                <div><span>Status</span><strong><?= e(jm_employer_status_text($application['status'] ?? 'pending')) ?></strong></div>
                <div><span>Role</span><strong><?= e($application['job_title']) ?></strong></div>
            </div>

            <h2 style="margin-top:32px;">Cover note</h2>
            <?php if (!empty($application['cover_letter'])): ?>
                <p style="white-space:pre-line;"><?= e($application['cover_letter']) ?></p>
            <?php else: ?>
                <p>No cover note was submitted.</p>
            <?php endif; ?>

            <h2 style="margin-top:32px;">Profile summary</h2>
            <p style="white-space:pre-line;"><?= e($application['cv_summary'] ?: $application['bio'] ?: 'No profile summary yet.') ?></p>
        </main>

        <aside class="jm-panel">
            <h2>Hiring action</h2>
            <p>Move this candidate through your process. Job seekers will see the status in their application history.</p>
            <form method="post" class="jm-stack">
                <?= Security::csrfField() ?>
                <div class="jm-field">
                    <label for="status">Status</label>
                    <select class="jm-select" id="status" name="status">
                        <?php foreach (APPLICATION_STATUSES as $status): ?>
                            <option value="<?= e($status) ?>" <?= ($application['status'] ?? 'pending') === $status ? 'selected' : '' ?>><?= e(jm_employer_status_text($status)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button class="jm-button" type="submit">Save status</button>
            </form>

            <div class="jm-section" style="padding:28px 0 0;margin-top:28px;">
                <h2>Files</h2>
                <?php if ($cvUrl): ?>
                    <a class="jm-button secondary" href="<?= e($cvUrl) ?>" target="_blank" rel="noopener">Open CV</a>
                <?php else: ?>
                    <p>No CV file was attached.</p>
                <?php endif; ?>
            </div>

            <div class="jm-section" style="padding:28px 0 0;margin-top:28px;border-bottom:0;">
                <h2>Job</h2>
                <p><?= e($application['job_title']) ?></p>
                <p class="jm-small"><?= e($application['job_city'] ?: 'Remote') ?> / <?= e($application['job_type'] ?: 'Job') ?></p>
                <a class="jm-muted-link" href="/jobmington/employer/applications.php?job=<?= (int) $application['job_id'] ?>">View all applications for this job</a>
            </div>
        </aside>
    </div>
</section>

<?php jm_employer_footer('/jobmington/employer/applications.php', 'Applications'); ?>
