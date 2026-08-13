<?php
define('JOBMINGTON', true);
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';

Session::start();
Session::requireRole(USER_TYPE_EMPLOYER);

$pdo = db();
$userId = Session::userId();

$stmt = $pdo->prepare("SELECT * FROM companies WHERE user_id = ? ORDER BY company_id ASC LIMIT 1");
$stmt->execute([$userId]);
$company = $stmt->fetch();
$companyId = $company['company_id'] ?? null;

$stats = ['jobs' => 0, 'active' => 0, 'applications' => 0, 'views' => 0];
$recentApplications = [];
$recentJobs = [];

if ($companyId) {
    $stmt = $pdo->prepare("
        SELECT
            COUNT(*) AS jobs,
            SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) AS active,
            COALESCE(SUM(views), 0) AS views
        FROM jobs
        WHERE company_id = ?
    ");
    $stmt->execute([$companyId]);
    $row = $stmt->fetch() ?: [];
    $stats['jobs'] = (int) ($row['jobs'] ?? 0);
    $stats['active'] = (int) ($row['active'] ?? 0);
    $stats['views'] = (int) ($row['views'] ?? 0);

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM job_applications ja
        JOIN jobs j ON ja.job_id = j.job_id
        WHERE j.company_id = ?
    ");
    $stmt->execute([$companyId]);
    $stats['applications'] = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT ja.*, j.title AS job_title, j.job_id,
               u.full_name, u.email, u.phone
        FROM job_applications ja
        JOIN jobs j ON ja.job_id = j.job_id
        LEFT JOIN users u ON ja.user_id = u.user_id
        WHERE j.company_id = ?
        ORDER BY ja.applied_at DESC
        LIMIT 6
    ");
    $stmt->execute([$companyId]);
    $recentApplications = $stmt->fetchAll();

    $stmt = $pdo->prepare("
        SELECT j.*, c.name AS country_name
        FROM jobs j
        LEFT JOIN countries c ON j.country_id = c.country_id
        WHERE j.company_id = ?
        ORDER BY j.posted_at DESC
        LIMIT 6
    ");
    $stmt->execute([$companyId]);
    $recentJobs = $stmt->fetchAll();
}

$pageTitle = 'Employer Dashboard | ' . SITE_NAME;
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?></title>
    <link rel="preload" as="font" type="font/ttf" href="/jobmington/assets/fonts/FuturaCyrillicDemi.ttf" crossorigin>
    <link rel="preload" as="font" type="font/ttf" href="/jobmington/assets/fonts/FuturaCyrillicBook.ttf" crossorigin>
    <link rel="stylesheet" href="/jobmington/assets/css/minimal-jobmington.css?v=brand-28">
    <style>
        .jm-stats { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:16px; }
        .jm-stat { border:1px solid var(--jm-line); padding:20px; background:#fff; }
        .jm-stat strong { display:block; font-size:32px; line-height:1; margin-bottom:8px; }
        .jm-employer-grid { display:grid; grid-template-columns:minmax(0,1fr) 360px; gap:36px; }
        @media (max-width:900px){ .jm-stats,.jm-employer-grid{grid-template-columns:1fr;} }
    </style>
</head>
<body class="jm-minimal">
    <div class="jm-shell">
        <header class="jm-header">
            <a class="jm-logo" href="/jobmington/"><img src="/jobmington/assets/images/badge.png?v=logo-8" alt=""><span>Jobmington</span></a>
            <?php require_once __DIR__ . '/../includes/navigation.php'; jm_workspace_nav(['dashboard' => ['/jobmington/employer/dashboard.php', 'Dashboard'], 'company' => ['/jobmington/employer/company-profile.php', 'Company'], 'jobs' => ['/jobmington/employer/manage-jobs.php', 'Jobs'], 'applications' => ['/jobmington/employer/applications.php', 'Applications'], 'talent' => ['/jobmington/employer/talent-pool.php', 'Talent'], 'pricing' => ['/jobmington/pricing.php', 'Pricing']], 'dashboard'); ?>
        </header>

        <?php jm_flash_render();   /* one-shot note from whatever redirected here */ ?>

        <section class="jm-section" style="padding-top:0;">
            <div class="jm-section-head">
                <div>
                    <p class="jm-kicker">Employer</p>
                    <h1><?= $company ? e($company['name']) : 'Set up your company.' ?></h1>
                    <p><?= $company ? 'Manage roles, applications, and hiring activity from one place.' : 'Create your company profile before posting and managing roles.' ?></p>
                </div>
                <div class="jm-form-actions">
                    <a class="jm-button" href="/jobmington/employer/post-job.php">Post a job</a>
                    <a class="jm-button secondary" href="/jobmington/employer/company-profile.php<?= $company ? '' : '?setup=1' ?>">Company profile</a>
                </div>
            </div>

            <?php if (!$company): ?>
                <div class="jm-empty">
                    <h2>Company profile required</h2>
                    <p>Your employer account needs a company profile so job seekers know who they are applying to.</p>
                    <a class="jm-button" href="/jobmington/employer/company-profile.php?setup=1">Set up company</a>
                </div>
            <?php else: ?>
                <div class="jm-stats">
                    <div class="jm-stat"><strong><?= number_format($stats['jobs']) ?></strong><span class="jm-small">Total jobs</span></div>
                    <div class="jm-stat"><strong><?= number_format($stats['active']) ?></strong><span class="jm-small">Active jobs</span></div>
                    <div class="jm-stat"><strong><?= number_format($stats['applications']) ?></strong><span class="jm-small">Applications</span></div>
                    <div class="jm-stat"><strong><?= number_format($stats['views']) ?></strong><span class="jm-small">Job views</span></div>
                </div>
            <?php endif; ?>
        </section>

        <?php if ($company): ?>
            <section class="jm-section">
                <div class="jm-employer-grid">
                    <main>
                        <div class="jm-section-head">
                            <div>
                                <h2>Recent applications</h2>
                                <p>Latest candidates across your roles.</p>
                            </div>
                            <a class="jm-muted-link" href="/jobmington/employer/applications.php">View all</a>
                        </div>
                        <?php if (empty($recentApplications)): ?>
                            <div class="jm-empty"><h2>No applications yet</h2><p>Applications will appear here when candidates apply.</p></div>
                        <?php else: ?>
                            <div class="jm-job-list">
                                <?php foreach ($recentApplications as $application): ?>
                                    <a class="jm-job-row" href="/jobmington/employer/view-applicant.php?id=<?= (int) $application['application_id'] ?>">
                                        <strong>
                                            <?= e($application['full_name'] ?: $application['email'] ?: 'Applicant') ?>
                                            <small style="display:block;color:var(--jm-muted);font-weight:400;margin-top:4px;"><?= e($application['job_title']) ?></small>
                                        </strong>
                                        <span><?= e(ucfirst($application['status'] ?? 'pending')) ?><br><?= e(timeAgo($application['applied_at'])) ?></span>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </main>

                    <aside class="jm-panel">
                        <h2>Recent jobs</h2>
                        <?php if (empty($recentJobs)): ?>
                            <p>No jobs posted yet.</p>
                            <a class="jm-muted-link" href="/jobmington/employer/post-job.php">Post your first job</a>
                        <?php else: ?>
                            <div class="jm-stack">
                                <?php foreach ($recentJobs as $job): ?>
                                    <div>
                                        <strong><?= e($job['title']) ?></strong>
                                        <p style="margin:4px 0;color:var(--jm-muted);"><?= e($job['country_name'] ?? 'Remote') ?> / <?= e($job['job_type'] ?? 'Job') ?></p>
                                        <a class="jm-muted-link" href="/jobmington/employer/edit-job.php?id=<?= (int) $job['job_id'] ?>">Edit</a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </aside>
                </div>
            </section>
        <?php endif; ?>

        <?php jm_minimal_footer(); ?>
    </div>
</body>
</html>
