<?php
define('JOBMINGTON', true);
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';

Session::start();
Session::requireLogin();

$pdo = db();
$userId = Session::userId();

if (isset($_GET['action']) && $_GET['action'] === 'toggle' && Security::isAjax()) {
    $jobId = (int) get('job_id', 0);
    if ($jobId <= 0) {
        jsonError('Invalid job.');
    }

    $stmt = $pdo->prepare("SELECT id FROM saved_jobs WHERE user_id = ? AND job_id = ? LIMIT 1");
    $stmt->execute([$userId, $jobId]);

    if ($stmt->fetch()) {
        $pdo->prepare("DELETE FROM saved_jobs WHERE user_id = ? AND job_id = ?")->execute([$userId, $jobId]);
        jsonSuccess(['saved' => false], 'Job removed from saved jobs.');
    } else {
        $pdo->prepare("INSERT INTO saved_jobs (user_id, job_id) VALUES (?, ?)")->execute([$userId, $jobId]);
        jsonSuccess(['saved' => true], 'Job saved.');
    }
}

if (isset($_GET['remove'])) {
    $jobId = (int) get('remove');
    if ($jobId > 0 && Security::verifyCSRF(get('token'))) {
        $pdo->prepare("DELETE FROM saved_jobs WHERE user_id = ? AND job_id = ?")->execute([$userId, $jobId]);
        Session::flash('success', 'Job removed from saved jobs.');
        Security::regenerateCSRF();
    }
    redirect('/jobmington/jobs/saved.php');
}

$page = max(1, (int) get('page', 1));
$perPage = 10;

$stmt = $pdo->prepare("SELECT COUNT(*) FROM saved_jobs WHERE user_id = ?");
$stmt->execute([$userId]);
$totalSaved = (int) $stmt->fetchColumn();
$pagination = paginate($totalSaved, $perPage, $page);

$stmt = $pdo->prepare("
    SELECT j.*, c.name AS country_name, c.currency_symbol,
           co.name AS company_name, co.logo AS company_logo,
           sj.saved_at
    FROM saved_jobs sj
    JOIN jobs j ON sj.job_id = j.job_id
    LEFT JOIN countries c ON j.country_id = c.country_id
    JOIN companies co ON j.company_id = co.company_id
    WHERE sj.user_id = ?
    ORDER BY sj.saved_at DESC
    LIMIT ? OFFSET ?
");
$stmt->execute([$userId, $pagination['per_page'], $pagination['offset']]);
$savedJobs = $stmt->fetchAll();

$flashes = Session::getFlash();
$pageTitle = 'Saved Jobs | ' . SITE_NAME;
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?></title>
    <link rel="stylesheet" href="/jobmington/assets/css/minimal-jobmington.css?v=brand-10">
</head>
<body class="jm-minimal">
    <div class="jm-shell">
        <header class="jm-header">
            <a class="jm-logo" href="/jobmington/">
                <img src="/jobmington/assets/images/badge.png?v=logo-7" alt="">
                <span>Jobmington</span>
            </a>
            <nav class="jm-nav" aria-label="Main navigation">
                <a href="/jobmington/jobs/">Find jobs</a>
                <a href="/jobmington/cv-builder/">CV Builder</a>
                <a href="/jobmington/employer/">Employers</a>
                <a href="/jobmington/seeker/dashboard.php">Dashboard</a>
                <a class="jm-button secondary" href="/jobmington/auth/logout.php">Sign out</a>
            </nav>
        </header>

        <section class="jm-section" style="padding-top:0;">
            <div class="jm-section-head">
                <div>
                    <p class="jm-kicker">Saved jobs</p>
                    <h1>Your shortlist.</h1>
                    <p><?= number_format($totalSaved) ?> saved <?= $totalSaved === 1 ? 'job' : 'jobs' ?>.</p>
                </div>
                <a class="jm-button" href="/jobmington/jobs/">Find more jobs</a>
            </div>

            <?php foreach ($flashes as $type => $messages): ?>
                <?php foreach ($messages as $message): ?>
                    <div class="<?= $type === 'success' ? 'jm-success' : 'jm-alert' ?>"><?= e($message) ?></div>
                <?php endforeach; ?>
            <?php endforeach; ?>

            <?php if (empty($savedJobs)): ?>
                <div class="jm-empty">
                    <h2>No saved jobs yet</h2>
                    <p>Save roles from the job detail page and they will appear here.</p>
                    <a class="jm-button" href="/jobmington/jobs/">Browse jobs</a>
                </div>
            <?php else: ?>
                <div class="jm-job-list">
                    <?php foreach ($savedJobs as $job): ?>
                        <?php
                            $salary = (!empty($job['salary_min']) || !empty($job['salary_max']))
                                ? formatSalaryRange($job['salary_min'], $job['salary_max'], $job['currency_symbol'] ?? null)
                                : 'Salary not listed';
                        ?>
                        <div class="jm-job-row" style="align-items:center;">
                            <a href="/jobmington/jobs/view.php?id=<?= (int) $job['job_id'] ?>" style="color:inherit;text-decoration:none;">
                                <strong>
                                    <?= e($job['title']) ?>
                                    <small style="display:block;color:var(--jm-muted);font-weight:400;margin-top:4px;">
                                        <?= e($job['company_name']) ?> / <?= e($job['country_name'] ?? 'Remote') ?>
                                    </small>
                                </strong>
                            </a>
                            <span>
                                <?= e($salary) ?><br>
                                <a class="jm-muted-link" href="/jobmington/jobs/saved.php?remove=<?= (int) $job['job_id'] ?>&token=<?= e(csrf_token()) ?>">Remove</a>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="jm-form-actions" style="justify-content:center;margin-top:34px;">
                    <?php if ($pagination['has_previous']): ?>
                        <a class="jm-button secondary" href="/jobmington/jobs/saved.php?page=<?= (int) $pagination['previous_page'] ?>">Previous</a>
                    <?php endif; ?>
                    <?php if ($pagination['has_next']): ?>
                        <a class="jm-button secondary" href="/jobmington/jobs/saved.php?page=<?= (int) $pagination['next_page'] ?>">Next</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </section>

        <?php jm_minimal_footer(); ?>
    </div>
</body>
</html>
