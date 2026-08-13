<?php
define('JOBMINGTON', true);
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';

Session::start();

if (Session::isEmployer()) {
    redirect('/jobmington/employer/dashboard.php');
}

$pageTitle = 'Employers | ' . SITE_NAME;
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Post jobs on Jobmington, manage applications, and hire African talent from one simple employer workspace.">
    <title><?= e($pageTitle) ?></title>
    <link rel="preload" as="font" type="font/ttf" href="/jobmington/assets/fonts/FuturaCyrillicDemi.ttf" crossorigin>
    <link rel="preload" as="font" type="font/ttf" href="/jobmington/assets/fonts/FuturaCyrillicBook.ttf" crossorigin>
    <link rel="stylesheet" href="/jobmington/assets/css/minimal-jobmington.css?v=brand-27">
</head>
<body class="jm-minimal">
    <div class="jm-shell">
        <header class="jm-header">
            <a class="jm-logo" href="/jobmington/"><img src="/jobmington/assets/images/badge.png?v=logo-8" alt=""><span>Jobmington</span></a>
            <?php require_once __DIR__ . '/../includes/navigation.php'; jm_site_nav(); ?>
        </header>

        <section class="jm-hero">
            <div>
                <p class="jm-kicker">Employers</p>
                <h1>Post roles and manage candidates in one place.</h1>
                <p>Jobmington connects your company profile, job posts, candidate applications, and talent discovery so hiring work stays organized.</p>
                <div class="jm-form-actions">
                    <a class="jm-button" href="/jobmington/employer/post-job.php">Post a job</a>
                    <a class="jm-button secondary" href="/jobmington/auth/register.php?type=employer">Create employer account</a>
                </div>
            </div>
            <aside class="jm-panel">
                <h2>Hiring flow</h2>
                <div class="jm-key-list">
                    <div><span>1</span><strong>Create company profile</strong></div>
                    <div><span>2</span><strong>Publish a job</strong></div>
                    <div><span>3</span><strong>Review applications</strong></div>
                    <div><span>4</span><strong>Update candidate status</strong></div>
                </div>
            </aside>
        </section>

        <section class="jm-section">
            <div class="jm-grid-3">
                <article class="jm-card">
                    <h2>Company profile</h2>
                    <p>Show candidates who you are before they apply.</p>
                </article>
                <article class="jm-card">
                    <h2>Application tracking</h2>
                    <p>Keep pending, reviewed, shortlisted, interview, and hired stages tidy.</p>
                </article>
                <article class="jm-card">
                    <h2>Talent pool</h2>
                    <p>Review available talent passports when your hiring plan grows.</p>
                </article>
            </div>
        </section>

        <?php jm_minimal_footer(); ?>
    </div>
</body>
</html>
