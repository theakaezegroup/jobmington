<?php
define('JOBMINGTON', true);
require_once __DIR__ . '/config/env.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/functions.php';

Session::start();

$sections = [
    ['Acceptance', 'By using Jobmington, you agree to these terms and any policies linked from the service.'],
    ['Accounts', 'You are responsible for keeping your account information accurate and your password secure.'],
    ['Job listings', 'Employers are responsible for posting accurate, lawful, and non-misleading job listings.'],
    ['Applications', 'Job seekers are responsible for the information they submit to employers through Jobmington.'],
    ['Prohibited use', 'Do not misuse the service, scrape it aggressively, post harmful content, or violate applicable laws.'],
    ['Service changes', 'We may update, suspend, or change features as the product evolves.'],
    ['Contact', 'For questions about these terms, contact legal@jobmington.com or use the contact page.'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Terms of Service | Jobmington</title>
    <link rel="preload" as="font" type="font/ttf" href="/jobmington/assets/fonts/FuturaCyrillicDemi.ttf" crossorigin>
    <link rel="preload" as="font" type="font/ttf" href="/jobmington/assets/fonts/FuturaCyrillicBook.ttf" crossorigin>
    <link rel="stylesheet" href="/jobmington/assets/css/minimal-jobmington.css?v=brand-20">
</head>
<body class="jm-minimal">
    <div class="jm-shell">
        <header class="jm-header">
            <a href="/jobmington/" class="jm-logo"><img src="/jobmington/assets/images/badge.png?v=logo-8" alt=""><span>Jobmington</span></a>
            <?php require_once __DIR__ . '/includes/navigation.php'; jm_site_nav(); ?>
        </header>

        <section class="jm-hero">
            <div>
                <p class="jm-kicker">Terms</p>
                <h1>Terms of Service.</h1>
                <p>Last updated: January 2026. These terms govern your use of Jobmington.</p>
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
