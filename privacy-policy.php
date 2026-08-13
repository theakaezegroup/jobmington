<?php
define('JOBMINGTON', true);
require_once __DIR__ . '/config/env.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/functions.php';

Session::start();

$sections = [
    ['Information we collect', 'We collect account details, profile information, job activity, applications, employer listings, device information, and support messages needed to run Jobmington.'],
    ['How we use information', 'We use information to provide job search, applications, employer tools, account security, support, and service improvements.'],
    ['Sharing', 'We share application information with employers when you apply. We do not sell your personal information.'],
    ['Security', 'We use reasonable technical and organizational safeguards to protect accounts and platform data.'],
    ['Your choices', 'You can update account information, manage communications, or contact us about privacy requests.'],
    ['Contact', 'For privacy questions, contact privacy@jobmington.com or use the contact page.'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Privacy Policy | Jobmington</title>
    <link rel="preload" as="font" type="font/ttf" href="/jobmington/assets/fonts/FuturaCyrillicDemi.ttf" crossorigin>
    <link rel="preload" as="font" type="font/ttf" href="/jobmington/assets/fonts/FuturaCyrillicBook.ttf" crossorigin>
    <link rel="stylesheet" href="/jobmington/assets/css/minimal-jobmington.css?v=brand-28">
</head>
<body class="jm-minimal">
    <div class="jm-shell">
        <header class="jm-header">
            <a href="/jobmington/" class="jm-logo"><img src="/jobmington/assets/images/badge.png?v=logo-8" alt=""><span>Jobmington</span></a>
            <?php require_once __DIR__ . '/includes/navigation.php'; jm_site_nav(); ?>
        </header>

        <section class="jm-hero">
            <div>
                <p class="jm-kicker">Privacy</p>
                <h1>Privacy Policy.</h1>
                <p>Last updated: January 2026. This page explains how Jobmington handles personal information.</p>
            </div>
        </section>

        <section class="jm-section">
            <div class="jm-job-list">
                <?php foreach ($sections as [$title, $copy]): ?>
                    <div class="jm-job-row" style="display:block;">
                        <strong><?= e($title) ?></strong>
                        <p style="margin:10px 0 0;color:var(--jm-muted);"><?= e($copy) ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <?php jm_minimal_footer(); ?>
    </div>
</body>
</html>
