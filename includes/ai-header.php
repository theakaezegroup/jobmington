<?php
/**
 * Clean AI product shell for Andika and CV Roast.
 */

$aiPageTitle = $pageTitle ?? SITE_NAME;
$activeAIPage = $activeAIPage ?? '';
$isLoggedIn = class_exists('Session') && Session::isLoggedIn();
$dashboardUrl = class_exists('Session') && Session::isAdmin()
    ? '/jobmington/admin/'
    : ((class_exists('Session') && Session::isEmployer()) ? '/jobmington/employer/dashboard.php' : '/jobmington/seeker/dashboard.php');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0640a3">
    <link rel="manifest" href="/jobmington/manifest.json?v=brand-10">
    <link rel="apple-touch-icon" href="/jobmington/assets/images/pwa-icon-192.png?v=brand-10">
    <title><?= e($aiPageTitle) ?></title>
    <link rel="stylesheet" href="/jobmington/assets/css/minimal-jobmington.css?v=brand-12">
    <script>
        window.tailwind = window.tailwind || {};
        window.tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Futura Cyrillic Demi']
                    }
                }
            }
        };
    </script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="jm-minimal jm-ai-page">
    <div class="jm-shell">
        <header class="jm-header">
            <a class="jm-logo" href="/jobmington/">
                <img src="/jobmington/assets/images/badge.png?v=logo-7" alt="">
                <span>Jobmington</span>
            </a>
            <button class="jm-mobile-nav-toggle" type="button" aria-label="Open menu" aria-expanded="false" aria-controls="jm-ai-nav">
                <span></span>
            </button>
            <nav class="jm-nav" id="jm-ai-nav" aria-label="Main navigation">
                <a href="/jobmington/jobs/">Find jobs</a>
                <a href="/jobmington/cv-builder/">CV Builder</a>
                <a class="<?= $activeAIPage === 'andika' ? 'active' : '' ?>" href="/jobmington/ai/andika.php">Andika AI</a>
                <a href="/jobmington/employer/">Employers</a>
                <a href="/jobmington/pricing.php">Pricing</a>
                <?php if ($isLoggedIn): ?>
                    <a href="<?= e($dashboardUrl) ?>">Dashboard</a>
                    <a class="jm-button secondary" href="/jobmington/auth/logout.php">Sign out</a>
                <?php else: ?>
                    <a href="/jobmington/auth/login.php">Sign in</a>
                    <a class="jm-button secondary" href="/jobmington/auth/register.php">Create account</a>
                <?php endif; ?>
            </nav>
        </header>
    </div>
    <main class="jm-ai-main">
