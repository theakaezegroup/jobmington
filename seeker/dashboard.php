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

if (Session::isEmployer() && !Session::isAdmin()) {
    redirect('/jobmington/employer/dashboard.php');
}

$pdo = db();
$userId = Session::userId();

if (!function_exists('jm_dashboard_scalar')) {
    function jm_dashboard_scalar(PDO $pdo, string $sql, array $params = []): int {
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return (int) $stmt->fetchColumn();
        } catch (Throwable $e) {
            return 0;
        }
    }
}

if (!function_exists('jm_dashboard_rows')) {
    function jm_dashboard_rows(PDO $pdo, string $sql, array $params = []): array {
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (Throwable $e) {
            return [];
        }
    }
}

$stmt = $pdo->prepare("
    SELECT u.*, cv.headline, cv.summary
    FROM users u
    LEFT JOIN cv_profiles cv ON u.user_id = cv.user_id
    WHERE u.user_id = ?
    LIMIT 1
");
$stmt->execute([$userId]);
$user = $stmt->fetch() ?: [];

$displayName = trim($user['full_name'] ?? '');
if ($displayName === '') {
    $displayName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
}
if ($displayName === '') {
    $displayName = 'there';
}
$firstName = explode(' ', $displayName)[0];

$stats = [
    'Applications' => jm_dashboard_scalar($pdo, "SELECT COUNT(*) FROM job_applications WHERE user_id = ?", [$userId]),
    'Pending' => jm_dashboard_scalar($pdo, "SELECT COUNT(*) FROM job_applications WHERE user_id = ? AND status = 'pending'", [$userId]),
    'Interviews' => jm_dashboard_scalar($pdo, "SELECT COUNT(*) FROM job_applications WHERE user_id = ? AND status = 'interview'", [$userId]),
    'Saved' => jm_dashboard_scalar($pdo, "SELECT COUNT(*) FROM saved_jobs WHERE user_id = ?", [$userId]),
];

$recentApplications = jm_dashboard_rows($pdo, "
    SELECT ja.*, j.title AS job_title, j.city, c.name AS country_name,
           co.name AS company_name
    FROM job_applications ja
    JOIN jobs j ON ja.job_id = j.job_id
    LEFT JOIN countries c ON j.country_id = c.country_id
    JOIN companies co ON j.company_id = co.company_id
    WHERE ja.user_id = ?
    ORDER BY ja.applied_at DESC
    LIMIT 5
", [$userId]);

$recommendedJobs = jm_dashboard_rows($pdo, "
    SELECT j.*, c.name AS country_name, c.currency_symbol,
           co.name AS company_name
    FROM jobs j
    LEFT JOIN countries c ON j.country_id = c.country_id
    JOIN companies co ON j.company_id = co.company_id
    WHERE j.is_active = 1
      AND (j.expires_at IS NULL OR j.expires_at >= CURDATE())
      AND j.job_id NOT IN (SELECT job_id FROM job_applications WHERE user_id = ?)
    ORDER BY j.is_featured DESC, j.posted_at DESC
    LIMIT 5
", [$userId]);

$matchedJobs = [];
try {
    require_once __DIR__ . '/../ai/JobMatcher.php';
    $matcher = new JobMatcher($pdo);
    $matchedJobs = $matcher->getTopMatches($userId, 4);
} catch (Throwable $e) {
    $matchedJobs = [];
}

$profileFields = [
    !empty($displayName) && $displayName !== 'there',
    !empty($user['email'] ?? ''),
    !empty($user['phone'] ?? ''),
    !empty($user['headline'] ?? ''),
    !empty($user['summary'] ?? ''),
];
$profileScore = (int) round((array_sum($profileFields) / count($profileFields)) * 100);
$pageTitle = 'Dashboard | ' . SITE_NAME;
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?></title>
    <link rel="stylesheet" href="/jobmington/assets/css/minimal-jobmington.css?v=brand-10">
    <style>
        .jm-dashboard-top {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 260px;
            gap: 32px;
            align-items: end;
            padding-bottom: 48px;
            border-bottom: 1px solid var(--jm-line);
        }
        .jm-stats {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 16px;
            margin-top: 30px;
        }
        .jm-stat {
            border: 1px solid var(--jm-line);
            padding: 20px;
            background: #fff;
        }
        .jm-stat strong {
            display: block;
            color: var(--jm-ink);
            font-size: 32px;
            line-height: 1;
            margin-bottom: 8px;
        }
        .jm-stat span {
            color: var(--jm-muted);
            font-size: 14px;
            font-weight: 600;
        }
        .jm-dashboard-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.2fr) minmax(300px, 0.8fr);
            gap: 36px;
        }
        .jm-pill-row {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 14px;
        }
        @media (max-width: 900px) {
            .jm-dashboard-top,
            .jm-dashboard-grid,
            .jm-stats {
                grid-template-columns: 1fr;
            }
        }
    </style>
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
                <a href="/jobmington/seeker/applications.php">Applications</a>
                <a href="/jobmington/jobs/saved.php">Saved jobs</a>
                <a href="/jobmington/seeker/profile.php">Profile</a>
                <a class="jm-button secondary" href="/jobmington/auth/logout.php">Sign out</a>
            </nav>
        </header>

        <section class="jm-dashboard-top">
            <div>
                <p class="jm-kicker">Dashboard</p>
                <h1 style="margin:0;color:var(--jm-ink);font-size:52px;line-height:1.05;font-weight:600;">Welcome back, <?= e($firstName) ?>.</h1>
                <p style="max-width:680px;margin:22px 0 0;color:var(--jm-muted);">Track your applications, saved roles, and matched jobs from one quiet workspace.</p>
            </div>
            <aside class="jm-panel">
                <h2><?= $profileScore ?>% profile</h2>
                <p>Complete your profile and keep a polished CV ready for applications.</p>
                <div class="jm-form-actions">
                    <a class="jm-button" href="/jobmington/cv-builder/">Open CV Builder</a>
                    <a class="jm-button secondary" href="/jobmington/seeker/profile.php">Update profile</a>
                </div>
            </aside>
        </section>

        <section class="jm-section">
            <div class="jm-stats">
                <?php foreach ($stats as $label => $value): ?>
                    <div class="jm-stat">
                        <strong><?= number_format((int) $value) ?></strong>
                        <span><?= e($label) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="jm-section">
            <div class="jm-dashboard-grid">
                <div>
                    <div class="jm-section-head">
                        <div>
                            <h2>Matched jobs</h2>
                            <p>Roles ranked against your profile details.</p>
                        </div>
                        <a class="jm-muted-link" href="/jobmington/jobs/">Browse all</a>
                    </div>

                    <?php if (empty($matchedJobs)): ?>
                        <?= jm_empty_state_card('profile_incomplete', [
                            'actions' => '<a class="jm-button" href="/jobmington/seeker/profile.php">Update profile</a>',
                        ]) ?>
                    <?php else: ?>
                        <div class="jm-job-list">
                            <?php foreach ($matchedJobs as $job): ?>
                                <a class="jm-job-row" href="/jobmington/jobs/view.php?id=<?= (int) $job['job_id'] ?>">
                                    <strong>
                                        <?= e($job['title'] ?? 'Open role') ?>
                                        <small style="display:block;color:var(--jm-muted);font-weight:400;margin-top:4px;">
                                            <?= e($job['company'] ?? 'Company') ?> / <?= e($job['location'] ?? 'Remote') ?>
                                        </small>
                                    </strong>
                                    <span><?= (int) ($job['score'] ?? 0) ?>% match</span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <aside>
                    <div class="jm-panel">
                        <h2>Recent applications</h2>
                        <?php if (empty($recentApplications)): ?>
                            <p>No applications yet.</p>
                            <a class="jm-muted-link" href="/jobmington/jobs/">Find your first role</a>
                        <?php else: ?>
                            <div class="jm-stack">
                                <?php foreach ($recentApplications as $application): ?>
                                    <div>
                                        <strong><?= e($application['job_title'] ?? 'Role') ?></strong>
                                        <p style="margin:4px 0;color:var(--jm-muted);"><?= e($application['company_name'] ?? 'Company') ?></p>
                                        <span class="jm-badge"><?= e(ucfirst($application['status'] ?? 'pending')) ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="jm-form-actions">
                                <a class="jm-button secondary" href="/jobmington/seeker/applications.php">View all</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </aside>
            </div>
        </section>

        <section class="jm-section">
            <div class="jm-section-head">
                <div>
                    <h2>Recommended jobs</h2>
                    <p>Fresh roles you have not applied to yet.</p>
                </div>
            </div>

            <?php if (empty($recommendedJobs)): ?>
                <?= jm_empty_state_card('no_jobs_found', [
                    'actions' => '<a class="jm-button" href="/jobmington/jobs/">Browse jobs</a>',
                ]) ?>
            <?php else: ?>
                <div class="jm-job-list">
                    <?php foreach ($recommendedJobs as $job): ?>
                        <?php
                            $salary = (!empty($job['salary_min']) || !empty($job['salary_max']))
                                ? formatSalaryRange($job['salary_min'], $job['salary_max'], $job['currency_symbol'] ?? null)
                                : 'Salary not listed';
                        ?>
                        <a class="jm-job-row" href="/jobmington/jobs/view.php?id=<?= (int) $job['job_id'] ?>">
                            <strong>
                                <?= e($job['title'] ?? 'Open role') ?>
                                <small style="display:block;color:var(--jm-muted);font-weight:400;margin-top:4px;">
                                    <?= e($job['company_name'] ?? 'Company') ?> / <?= e($job['country_name'] ?? 'Remote') ?>
                                </small>
                            </strong>
                            <span><?= e($salary) ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <?php jm_minimal_footer(); ?>
    </div>
</body>
</html>
