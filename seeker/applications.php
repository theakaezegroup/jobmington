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
$statusFilter = Security::clean($_GET['status'] ?? '');
$showSubmittedState = ($_GET['submitted'] ?? '') === '1';
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 10;

$where = ['ja.user_id = ?'];
$params = [$userId];
if ($statusFilter !== '' && in_array($statusFilter, APPLICATION_STATUSES, true)) {
    $where[] = 'ja.status = ?';
    $params[] = $statusFilter;
}
$whereClause = implode(' AND ', $where);

$stmt = $pdo->prepare("SELECT COUNT(*) FROM job_applications ja WHERE {$whereClause}");
$stmt->execute($params);
$totalApplications = (int) $stmt->fetchColumn();
$pagination = paginate($totalApplications, $perPage, $page);

$stmt = $pdo->prepare("
    SELECT ja.*, j.title AS job_title, j.city, j.job_type, j.job_id,
           c.name AS country_name, c.currency_symbol,
           co.name AS company_name
    FROM job_applications ja
    JOIN jobs j ON ja.job_id = j.job_id
    LEFT JOIN countries c ON j.country_id = c.country_id
    JOIN companies co ON j.company_id = co.company_id
    WHERE {$whereClause}
    ORDER BY ja.applied_at DESC
    LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}
");
$stmt->execute($params);
$applications = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT status, COUNT(*) AS count FROM job_applications WHERE user_id = ? GROUP BY status");
$stmt->execute([$userId]);
$statusCounts = [];
while ($row = $stmt->fetch()) {
    $statusCounts[$row['status']] = (int) $row['count'];
}

$pageTitle = 'Applications | ' . SITE_NAME;
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?></title>
    <link rel="stylesheet" href="/jobmington/assets/css/minimal-jobmington.css?v=brand-15">
    <style>
        .jm-tabs { display:flex; flex-wrap:wrap; gap:10px; margin-bottom:30px; }
        .jm-tabs a { border:1px solid var(--jm-line); color:var(--jm-ink); padding:8px 12px; text-decoration:none; font-size:14px; font-weight:600; }
        .jm-tabs a.active { border-color:var(--jm-blue); color:#fff; background:var(--jm-blue); }
    </style>
</head>
<body class="jm-minimal">
    <div class="jm-shell">
        <header class="jm-header">
            <a class="jm-logo" href="/jobmington/"><img src="/jobmington/assets/images/badge.png?v=logo-7" alt=""><span>Jobmington</span></a>
            <nav class="jm-nav" aria-label="Main navigation">
                <a href="/jobmington/jobs/">Find jobs</a>
                <a href="/jobmington/cv-builder/">CV Builder</a>
                <a href="/jobmington/seeker/dashboard.php">Dashboard</a>
                <a href="/jobmington/seeker/applications.php">Applications</a>
                <a href="/jobmington/jobs/saved.php">Saved jobs</a>
                <a href="/jobmington/seeker/profile.php">Profile</a>
                <a class="jm-button secondary" href="/jobmington/auth/logout.php">Sign out</a>
            </nav>
        </header>

        <section class="jm-section" style="padding-top:0;">
            <div class="jm-section-head">
                <div>
                    <p class="jm-kicker">Applications</p>
                    <h1>Your application history.</h1>
                    <p>Track where you applied and what changed.</p>
                </div>
                <a class="jm-button" href="/jobmington/jobs/">Find jobs</a>
            </div>

            <nav class="jm-tabs" aria-label="Application status">
                <a class="<?= $statusFilter === '' ? 'active' : '' ?>" href="/jobmington/seeker/applications.php">All <span>(<?= number_format($totalApplications) ?>)</span></a>
                <?php foreach (APPLICATION_STATUSES as $status): ?>
                    <a class="<?= $statusFilter === $status ? 'active' : '' ?>" href="/jobmington/seeker/applications.php?status=<?= e($status) ?>">
                        <?= e(ucfirst($status)) ?> <span>(<?= number_format($statusCounts[$status] ?? 0) ?>)</span>
                    </a>
                <?php endforeach; ?>
            </nav>

            <?php if ($showSubmittedState): ?>
                <?= jm_notification_state_card('application_submitted', [
                    'compact' => true,
                    'inline' => true,
                    'aria_live' => 'polite',
                ]) ?>
            <?php endif; ?>

            <?php if (empty($applications)): ?>
                <?php if ($statusFilter): ?>
                    <div class="jm-empty">
                        <h2>No <?= e($statusFilter) ?> applications</h2>
                        <p>Try another status or browse all applications.</p>
                        <a class="jm-button" href="/jobmington/seeker/applications.php">View all</a>
                    </div>
                <?php else: ?>
                    <?= jm_empty_state_card('no_applications_yet', [
                        'actions' => '<a class="jm-button" href="/jobmington/jobs/">Browse jobs</a>',
                    ]) ?>
                <?php endif; ?>
            <?php else: ?>
                <div class="jm-job-list">
                    <?php foreach ($applications as $application): ?>
                        <a class="jm-job-row" href="/jobmington/jobs/view.php?id=<?= (int) $application['job_id'] ?>">
                            <strong>
                                <?= e($application['job_title']) ?>
                                <small style="display:block;color:var(--jm-muted);font-weight:400;margin-top:4px;">
                                    <?= e($application['company_name']) ?> / <?= e($application['country_name'] ?? 'Remote') ?>
                                </small>
                            </strong>
                            <span>
                                <?= e(ucfirst($application['status'] ?? 'pending')) ?><br>
                                Applied <?= e(formatDate($application['applied_at'], 'M d, Y')) ?>
                            </span>
                        </a>
                    <?php endforeach; ?>
                </div>

                <div class="jm-form-actions" style="justify-content:center;">
                    <?php if ($pagination['has_previous']): ?>
                        <a class="jm-button secondary" href="/jobmington/seeker/applications.php?<?= http_build_query(array_filter(['status' => $statusFilter, 'page' => $pagination['previous_page']])) ?>">Previous</a>
                    <?php endif; ?>
                    <?php if ($pagination['has_next']): ?>
                        <a class="jm-button secondary" href="/jobmington/seeker/applications.php?<?= http_build_query(array_filter(['status' => $statusFilter, 'page' => $pagination['next_page']])) ?>">Next</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </section>

        <?php jm_minimal_footer(); ?>
    </div>
</body>
</html>
