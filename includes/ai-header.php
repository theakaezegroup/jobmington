<?php
/**
 * Clean AI product shell for Andika and CV Roast.
 */

$aiPageTitle = $pageTitle ?? SITE_NAME;
$activeAIPage = $activeAIPage ?? '';

/*
 * Link previews (WhatsApp, LinkedIn, X, Slack, iMessage).
 *
 * Pages may set $pageDescription / $pageImage / $pageCanonical / $pageType
 * before including this header. Anything not set falls back to the site
 * defaults, so every page that uses this shell previews with the brand cover
 * rather than as a bare link.
 *
 * $pageImage may be a site-relative path; scrapers require an absolute URL,
 * so it is resolved here rather than at every call site.
 */
$jmShareDesc = trim((string) ($pageDescription
    ?? "Jobmington — Africa's career platform. Find remote roles that pay in dollars, local jobs near you, and AI tools that get you hired."));

$jmShareImage = trim((string) ($pageImage ?? ''));
$jmShareDefault = ($jmShareImage === '');
if ($jmShareDefault) {
    $jmShareImage = SITE_URL . '/assets/images/og-cover.png?v=brand-15';
} elseif (!preg_match('#^https?://#i', $jmShareImage)) {
    // Stored paths may carry a legacy /jobmington prefix. Production serves from
    // the root, so keeping it produced jobmington.com/jobmington/... which only
    // resolves via a redirect -- and scrapers routinely refuse to follow one for
    // an image. Strip it before prefixing; SITE_URL re-adds it where it belongs.
    $jmShareImage = preg_replace('#^/?jobmington/#', '', ltrim($jmShareImage, '/'));
    $jmShareImage = SITE_URL . '/' . ltrim($jmShareImage, '/');
}

$jmShareUrl  = trim((string) ($pageCanonical ?? (SITE_URL . ($_SERVER['REQUEST_URI'] ?? '/'))));
$jmShareType = (string) ($pageType ?? 'website');
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
    <meta name="description" content="<?= e($jmShareDesc) ?>">
    <link rel="canonical" href="<?= e($jmShareUrl) ?>">

    <meta property="og:type" content="<?= e($jmShareType) ?>">
    <meta property="og:site_name" content="<?= e(SITE_NAME) ?>">
    <meta property="og:title" content="<?= e($aiPageTitle) ?>">
    <meta property="og:description" content="<?= e($jmShareDesc) ?>">
    <meta property="og:url" content="<?= e($jmShareUrl) ?>">
    <meta property="og:image" content="<?= e($jmShareImage) ?>">
    <?php if ($jmShareDefault): /* only the bundled cover has known dimensions */ ?>
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <?php endif; ?>
    <meta property="og:image:alt" content="<?= e($aiPageTitle) ?>">
    <meta property="og:locale" content="en_US">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= e($aiPageTitle) ?>">
    <meta name="twitter:description" content="<?= e($jmShareDesc) ?>">
    <meta name="twitter:image" content="<?= e($jmShareImage) ?>">

    <link rel="preload" as="font" type="font/ttf" href="/jobmington/assets/fonts/FuturaCyrillicDemi.ttf" crossorigin>
    <link rel="preload" as="font" type="font/ttf" href="/jobmington/assets/fonts/FuturaCyrillicBook.ttf" crossorigin>
    <link rel="stylesheet" href="/jobmington/assets/css/minimal-jobmington.css?v=brand-18">
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
<script>document.body.classList.add('jm-mobile-nav-ready');</script>
<?php require_once __DIR__ . '/header_ads.php'; jm_header_ad_bar(); ?>
    <div class="jm-shell">
        <header class="jm-header">
            <a class="jm-logo" href="/jobmington/">
                <img src="/jobmington/assets/images/badge.png?v=logo-8" alt="">
                <span>Jobmington</span>
            </a>
            <button class="jm-mobile-nav-toggle" type="button" aria-label="Open menu" aria-expanded="false" aria-controls="jm-ai-nav">
                <span></span>
            </button>
            <nav class="jm-nav" id="jm-ai-nav" aria-label="Main navigation">
                <?php
                /* Rendered from the shared list rather than typed out here. The
                   hand-written copy had drifted: it was missing Home and Post a
                   Job, said "Find jobs" where the other header said "Find Jobs",
                   and sent Employers somewhere else entirely. */
                require_once __DIR__ . '/navigation.php';
                $jmAiActive = ['tools' => '/tools/', 'learn' => '/learn/', 'andika' => '/ai/andika.php'];
                foreach (Navigation::getCompactItems() as $item):
                    // Same matcher as everywhere else, with the page's own hint
                    // taking precedence where it sets one.
                    $isActive = jm_nav_is_active($item['url'], $jmAiActive[$activeAIPage] ?? '');
                ?>
                    <a class="<?= $isActive ? 'active' : '' ?>" href="<?= e($item['url']) ?>"><?= e($item['label']) ?></a>
                <?php endforeach; ?>
                <?php if ($isLoggedIn): ?>
                    <a href="<?= e($dashboardUrl) ?>">Dashboard</a>
                    <?php require_once __DIR__ . '/notification_bell.php'; jm_notification_bell(); ?>
                    <a class="jm-button secondary" href="/jobmington/auth/logout.php">Sign out</a>
                <?php else: ?>
                    <a href="/jobmington/auth/login.php">Sign in</a>
                    <a class="jm-button secondary" href="/jobmington/auth/register.php">Create account</a>
                <?php endif; ?>
            </nav>
        </header>
    </div>
    <main class="jm-ai-main">
