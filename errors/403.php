<?php
define('JOBMINGTON', true);
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';

Session::start();
http_response_code(403);

$dashboardHref = '/jobmington/auth/login.php';
$dashboardLabel = 'Sign in';
if (Session::isLoggedIn()) {
    $dashboardHref = '/jobmington/seeker/dashboard.php';
    $dashboardLabel = 'Go to dashboard';
    if (Session::isAdmin()) {
        $dashboardHref = '/jobmington/admin/';
    } elseif (Session::isEmployer()) {
        $dashboardHref = '/jobmington/employer/dashboard.php';
    }
}

$actions = '<a class="jm-button" href="' . e($dashboardHref) . '">' . e($dashboardLabel) . '</a>';
$actions .= '<a class="jm-button secondary" href="/jobmington/">Back home</a>';
$pageTitle = 'Access Restricted | ' . SITE_NAME;
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?></title>
    <link rel="stylesheet" href="/jobmington/assets/css/minimal-jobmington.css?v=state-icons-1">
</head>
<body class="jm-minimal">
    <div class="jm-shell">
        <header class="jm-header">
            <a class="jm-logo" href="/jobmington/">
                <img src="/jobmington/assets/images/badge.png" alt="">
                <span>Jobmington</span>
            </a>
            <nav class="jm-nav" aria-label="Main navigation">
                <a href="/jobmington/jobs/">Find jobs</a>
                <a href="/jobmington/pricing.php">Pricing</a>
                <?php if (Session::isLoggedIn()): ?>
                    <a class="jm-button secondary" href="/jobmington/auth/logout.php">Sign out</a>
                <?php else: ?>
                    <a href="/jobmington/auth/login.php">Sign in</a>
                    <a class="jm-button secondary" href="/jobmington/auth/register.php">Create account</a>
                <?php endif; ?>
            </nav>
        </header>

        <section class="jm-section" style="padding-top:0;">
            <?= jm_empty_state_card('access_restricted', [
                'title_tag' => 'h1',
                'actions' => $actions,
            ]) ?>
        </section>
    </div>
</body>
</html>
