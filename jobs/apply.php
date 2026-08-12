<?php
define('JOBMINGTON', true);
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/_job_helpers.php';
require_once __DIR__ . '/../includes/mailer.php';

Session::start();
Session::requireVerified();
$pdo = db();
$jobId = (int) get('id', 0);

if ($jobId <= 0) {
    redirect('/jobmington/jobs/');
}

$job = jm_job_find($pdo, $jobId);
if (!$job) {
    redirect('/jobmington/jobs/');
}

$externalUrl = $job['application_url'] ?: $job['apply_link'];
$errors = [];
$success = '';
$existingApplication = null;

if ($externalUrl) {
    $pageTitle = 'Apply for ' . $job['title'] . ' | ' . SITE_NAME;
    jm_jobs_header($pageTitle, 'jobs');
    ?>
    <section class="jm-hero">
        <div>
            <p class="jm-kicker">External application</p>
            <h1><?= e($job['title']) ?></h1>
            <p><?= e($job['company_name']) ?> receives applications for this role on their own site.</p>
            <div class="jm-form-actions">
                <a class="jm-button" href="<?= e($externalUrl) ?>" target="_blank" rel="noopener">Continue to application</a>
                <a class="jm-button secondary" href="/jobmington/jobs/view.php?id=<?= (int) $jobId ?>">Back to job</a>
            </div>
        </div>
        <aside class="jm-panel">
            <h2>Job summary</h2>
            <div class="jm-key-list">
                <div><span>Company</span><strong><?= e($job['company_name']) ?></strong></div>
                <div><span>Location</span><strong><?= e(jm_job_location($job)) ?></strong></div>
                <div><span>Type</span><strong><?= e($job['job_type']) ?></strong></div>
            </div>
        </aside>
    </section>
    <?php
    jm_jobs_footer('/jobmington/jobs/view.php?id=' . $jobId, 'Job details');
    exit;
}

if (!Session::isLoggedIn()) {
    redirect('/jobmington/auth/login.php?redirect=' . urlencode('/jobmington/jobs/apply.php?id=' . $jobId));
}

if (Session::isEmployer() && !Session::isAdmin()) {
    redirect('/jobmington/errors/403.php');
}

$userId = Session::userId();
$stmt = $pdo->prepare("SELECT * FROM job_applications WHERE user_id = ? AND job_id = ? LIMIT 1");
$stmt->execute([$userId, $jobId]);
$existingApplication = $stmt->fetch();
$applyCoach = jm_job_match_coach($pdo, $job, (int) $userId, true);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$existingApplication) {
    if (!Security::verifyCSRF()) {
        $errors[] = 'Please refresh the page and try again.';
    } else {
        $coverLetter = trim($_POST['cover_letter'] ?? '');
        $cvPath = null;

        if (!empty($_FILES['cv']['name'])) {
            $upload = Security::uploadFile(
                $_FILES['cv'],
                UPLOADS_PATH . '/resumes',
                [
                    'application/pdf',
                    'application/msword',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                ],
                MAX_CV_SIZE
            );

            if ($upload['success']) {
                $cvPath = 'resumes/' . $upload['filename'];
            } else {
                $errors = array_merge($errors, $upload['errors']);
            }
        }

        if (empty($errors)) {
            $stmt = $pdo->prepare("
                INSERT INTO job_applications (job_id, user_id, cover_letter, cv_path, status, applied_at)
                VALUES (?, ?, ?, ?, 'pending', NOW())
            ");
            $stmt->execute([$jobId, $userId, $coverLetter ?: null, $cvPath]);
            $applicationId = (int) $pdo->lastInsertId();
            jm_log_activity($userId, 'job_apply', $job['title'] ?? ('job ' . $jobId));

            $pdo->prepare("UPDATE jobs SET applications_count = COALESCE(applications_count, 0) + 1 WHERE job_id = ?")->execute([$jobId]);

            // Reward the application with Seeds.
            try {
                require_once __DIR__ . '/../includes/seeds.php';
                awardSeeds($userId, 'job_apply', $applicationId);
            } catch (Throwable $e) {
                error_log('Job-apply seed award failed: ' . $e->getMessage());
            }

            if (!empty($job['employer_user_id'])) {
                sendNotification(
                    (int) $job['employer_user_id'],
                    'application',
                    'New application received',
                    'A candidate applied for ' . $job['title'] . '.',
                    '/jobmington/employer/applications.php?job=' . $jobId
                );
            }

            /* ── Emails ─────────────────────────────────────────── */
            $jobUrl = SITE_URL . '/jobs/view?id=' . $jobId;

            Mailer::sendApplicationConfirmation(
                Session::get('email'),
                Session::get('full_name'),
                $job['title'],
                $job['company_name'],
                $jobUrl
            );

            if (!empty($job['employer_user_id'])) {
                $empStmt = $pdo->prepare("SELECT email FROM users WHERE user_id = ? LIMIT 1");
                $empStmt->execute([(int) $job['employer_user_id']]);
                $empRow = $empStmt->fetch();
                if ($empRow) {
                    Mailer::sendNewApplicationAlert(
                        $empRow['email'],
                        $job['company_name'],
                        $job['title'],
                        Session::get('full_name'),
                        SITE_URL . '/employer/applications?job=' . $jobId
                    );
                }
            }

            Security::regenerateCSRF();
            redirect('/jobmington/seeker/applications.php?submitted=1');
        }
    }
}

$pageTitle = 'Apply for ' . $job['title'] . ' | ' . SITE_NAME;
jm_jobs_header($pageTitle, 'jobs');
?>

<section class="jm-section" style="padding-top:0;">
    <div class="jm-section-head">
        <div>
            <p class="jm-kicker">Apply</p>
            <h1><?= e($job['title']) ?></h1>
            <p><?= e($job['company_name']) ?> / <?= e(jm_job_location($job)) ?></p>
        </div>
        <a class="jm-button secondary" href="/jobmington/jobs/view.php?id=<?= (int) $jobId ?>">Job details</a>
    </div>

    <?php if ($existingApplication): ?>
        <?= jm_notification_state_card('application_submitted', [
            'actions' => '<a class="jm-button" href="/jobmington/seeker/applications.php">View applications</a>',
        ]) ?>
    <?php else: ?>
        <?php if (!empty($errors)): ?>
            <div class="jm-alert"><?php foreach ($errors as $error): ?><div><?= e($error) ?></div><?php endforeach; ?></div>
        <?php endif; ?>

        <form class="jm-grid-2" method="post" enctype="multipart/form-data">
            <?= Security::csrfField() ?>
            <main class="jm-panel">
                <h2>Cover note</h2>
                <p>Keep it short and specific. Your profile details will carry the rest.</p>
                <?php if (!empty($applyCoach['cover_note'])): ?>
                    <div class="jm-match-note" style="margin:16px 0;">
                        <span>Opening line</span>
                        <p><?= e($applyCoach['cover_note']) ?></p>
                    </div>
                <?php endif; ?>
                <div class="jm-field">
                    <label for="cover_letter">Message to employer</label>
                    <textarea class="jm-textarea" id="cover_letter" name="cover_letter" placeholder="<?= e($applyCoach['cover_note'] ?: 'Why are you a good fit for this role?') ?>"></textarea>
                </div>
                <div class="jm-form-actions">
                    <button class="jm-button" type="submit">Submit application</button>
                </div>
            </main>

            <aside class="jm-panel">
                <h2>Attach CV</h2>
                <p>PDF, DOC, or DOCX. Maximum <?= e(Security::formatBytes(MAX_CV_SIZE)) ?>.</p>
                <div class="jm-field">
                    <label for="cv">CV file</label>
                    <input class="jm-file" id="cv" type="file" name="cv" accept=".pdf,.doc,.docx">
                </div>

                <div class="jm-section" style="padding:28px 0 0;margin-top:28px;border-bottom:0;">
                    <h2>Role summary</h2>
                    <div class="jm-key-list">
                        <div><span>Company</span><strong><?= e($job['company_name']) ?></strong></div>
                        <div><span>Type</span><strong><?= e($job['job_type']) ?></strong></div>
                        <div><span>Salary</span><strong><?= e(jm_job_salary($job)) ?></strong></div>
                    </div>
                </div>
            </aside>
        </form>
    <?php endif; ?>
</section>

<?php jm_jobs_footer('/jobmington/jobs/', 'Browse jobs'); ?>
