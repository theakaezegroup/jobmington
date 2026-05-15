<?php
define('JOBMINGTON', true);
require_once __DIR__ . '/config/env.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/monetization.php';

Session::start();
$packages = jm_job_posting_packages();
$pageTitle = 'Pricing | ' . SITE_NAME;
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?></title>
    <link rel="stylesheet" href="/jobmington/assets/css/minimal-jobmington.css?v=pricing-copy-1">
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
                <a href="/jobmington/cv-builder/">CV Builder</a>
                <a href="/jobmington/employer/">Employers</a>
                <a href="/jobmington/auth/login.php">Sign in</a>
                <a class="jm-button secondary" href="/jobmington/employer/post-job.php">Post a job</a>
            </nav>
        </header>

        <section class="jm-section" style="padding-top:0;">
            <div class="jm-section-head">
                <div>
                    <p class="jm-kicker">Pricing</p>
                    <h1>Post a job. Reach the right people.</h1>
                    <p>Start with a free listing, or give important roles more visibility when you need stronger applications.</p>
                </div>
            </div>

            <div class="jm-grid-3">
                <?php foreach ($packages as $package): ?>
                    <article class="jm-card jm-price-card">
                        <span class="jm-price-badge"><?= e($package['badge']) ?></span>
                        <h2><?= e($package['name']) ?></h2>
                        <p><?= e($package['description']) ?></p>
                        <h3><?= e(jm_money_ngn((float) $package['price'])) ?></h3>
                        <ul class="jm-price-list">
                            <?php foreach ($package['features'] as $feature): ?>
                                <li><?= e($feature) ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <a class="jm-button <?= $package['price'] > 0 ? '' : 'secondary' ?>" href="/jobmington/employer/post-job.php?package=<?= e($package['id']) ?>">Post with this</a>
                    </article>
                <?php endforeach; ?>
            </div>

            <div class="jm-panel" style="margin-top:24px;">
                <h2>Job seekers do not pay to apply.</h2>
                <p>Candidates can browse jobs, save roles, apply, and use the CV builder for free. Employers pay when they want more visibility for the roles that matter most.</p>
            </div>
        </section>

        <?php jm_minimal_footer(); ?>
    </div>
</body>
</html>
