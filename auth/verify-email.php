<?php
define('JOBMINGTON', true);
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';

Session::start();
$hasToken = isset($_GET['token']) && trim((string) $_GET['token']) !== '';

$pageTitle = 'Verify Email | ' . SITE_NAME;
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
                <a href="/jobmington/employer/">Employers</a>
                <a href="/jobmington/auth/login.php">Sign in</a>
                <a class="jm-button secondary" href="/jobmington/employer/post-job.php">Post a job</a>
            </nav>
        </header>

        <section class="jm-section" style="padding-top:0;">
            <?php if ($hasToken): ?>
                <?= jm_illustration_state_card('account_verified', [
                    'title_tag' => 'h1',
                    'actions' => '<a class="jm-button" href="/jobmington/auth/login.php">Sign in</a>',
                    'aria_live' => 'polite',
                ]) ?>
            <?php else: ?>
                <div class="jm-grid-2">
                    <div class="jm-section-head" style="display:block;margin-bottom:0;">
                        <p class="jm-kicker">Email verification</p>
                        <h1>Check your inbox</h1>
                        <p>Follow the verification link we sent to your email address to complete your account setup.</p>
                    </div>
                    <div class="jm-panel">
                        <h2>Already verified?</h2>
                        <p>Sign in and continue with your Jobmington account.</p>
                        <div class="jm-form-actions">
                            <a class="jm-button" href="/jobmington/auth/login.php">Sign in</a>
                            <a class="jm-button secondary" href="/jobmington/auth/register.php">Create account</a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </section>

        <?php jm_minimal_footer(); ?>
    </div>
</body>
</html>
