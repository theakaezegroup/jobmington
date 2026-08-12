<?php
/**
 * JOBMINGTON - Header Template v5.3 (The Crystal Refined)
 * Status: STABILIZED
 * Fixes: Modal placement, PHP syntax, Location Grid
 * Update: Super Dopper Geo Locator Design
 */

declare(strict_types=1);

// 1. SYSTEM INITIALIZATION
if (!defined('JOBMINGTON')) {
    define('JOBMINGTON', true);
    $basePath = dirname(__DIR__);
    $requiredFiles = [
        '/config/env.php', '/config/constants.php', '/config/database.php',
        '/includes/security.php', '/includes/session.php', '/includes/functions.php',
        '/includes/location_detect.php',
    ];
    foreach ($requiredFiles as $file) {
        if (is_readable($basePath . $file)) require_once $basePath . $file;
    }
    if (class_exists('Session')) Session::start();
    
    // Auto-refresh session with user data if missing (one-time migration for existing sessions)
    if (isset($_SESSION['user_id']) && !isset($_SESSION['is_verified'])) {
        global $pdo;
        if ($pdo) {
            $stmt = $pdo->prepare("SELECT full_name, first_name, last_name, profile_image, is_verified FROM users WHERE user_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $userData = $stmt->fetch();
            if ($userData) {
                $_SESSION['full_name'] = $userData['full_name'] ?: ($userData['first_name'] . ' ' . $userData['last_name']);
                $_SESSION['profile_image'] = $userData['profile_image'];
                $_SESSION['is_verified'] = (bool)$userData['is_verified'];
            }
        }
    }
}

$cspNonce = bin2hex(random_bytes(16));

// 2. CONFIGURATION CLASSES
final class HeaderConfig {
    private static ?self $instance = null;
    public string $title;
    public string $description;
    public array $structuredData = [];
    
    private function __construct() {
        $this->title = defined('SITE_NAME') ? SITE_NAME : 'Jobmington';
        $this->description = "The Digital Nation for African Talent";
        $this->structuredData[] = ['@context' => 'https://schema.org', '@type' => 'WebSite', 'name' => $this->title];
    }
    public static function getInstance(): self { return self::$instance ??= new self(); }
    public function setTitle(string $t): self { $this->title = $t; return $this; }
}

require_once __DIR__ . '/navigation.php';

// 3. LOAD DATA
$headerConfig = HeaderConfig::getInstance();
if (isset($pageTitle)) $headerConfig->setTitle($pageTitle);

$currentUser = null; $isLoggedIn = false; 
if (class_exists('Session')) {
    $currentUser = Session::user();
    $isLoggedIn = Session::isLoggedIn();
}

// LOCATION DETECTION LOGIC
$locationData = [
    'countryCode' => 'NG', 
    'countryName' => 'Nigeria', 
    'flagUrl' => 'https://flagcdn.com/w80/ng.png', 
    'supportedCountries' => []
];

if (class_exists('LocationDetector')) {
    try {
        LocationDetector::detect();
        $detectedName = LocationDetector::getName();
        $detectedCode = LocationDetector::getCode();
        $detectedFlag = LocationDetector::getFlagUrl();
        
        if (!empty($detectedName)) $locationData['countryName'] = $detectedName;
        if (!empty($detectedCode)) $locationData['countryCode'] = $detectedCode;
        if (!empty($detectedFlag)) $locationData['flagUrl'] = $detectedFlag;
        
        $locationData['supportedCountries'] = LocationDetector::getSupportedCountries();
    } catch (Throwable $e) {
        // Best effort: the fallback below covers a failed lookup, but a
        // permanently broken detector should not be invisible.
        error_log('LocationDetector: ' . $e->getMessage());
    }
}

// Fallback insurance
if (empty($locationData['flagUrl'])) {
    $locationData['flagUrl'] = 'https://flagcdn.com/w80/ng.png';
}

$navItems = Navigation::getMainItems();
$currentUri = $_SERVER['REQUEST_URI'] ?? '/';
$currentPath = parse_url($currentUri, PHP_URL_PATH) ?: $currentUri;
$isAdminArea = (bool) preg_match('~^/(?:jobmington/)?admin(?:/|$)~i', $currentPath);

if ($isAdminArea) {
    $adminNavGroups = [
        'Overview' => [
            ['href' => '/jobmington/admin/', 'label' => 'Dashboard', 'icon' => 'fa-gauge-high', 'match' => '/admin'],
        ],
        'Manage' => [
            ['href' => '/jobmington/admin/users.php', 'label' => 'Users', 'icon' => 'fa-users', 'match' => '/admin/users'],
            ['href' => '/jobmington/admin/jobs.php', 'label' => 'Jobs', 'icon' => 'fa-briefcase', 'match' => '/admin/jobs'],
            ['href' => '/jobmington/admin/operations.php', 'label' => 'Operations', 'icon' => 'fa-heartbeat', 'match' => '/admin/operations'],
            ['href' => '/jobmington/admin/tools.php', 'label' => 'Tool Access', 'icon' => 'fa-toggle-on', 'match' => '/admin/tools'],
            ['href' => '/jobmington/admin/activity.php', 'label' => 'Activity', 'icon' => 'fa-wave-square', 'match' => '/admin/activity'],
            ['href' => '/jobmington/admin/online.php', 'label' => 'Who is online', 'icon' => 'fa-signal', 'match' => '/admin/online'],
            ['href' => '/jobmington/admin/header-ads.php', 'label' => 'Header Ads', 'icon' => 'fa-rectangle-ad', 'match' => '/admin/header-ads'],
        ],
        'Learning' => [
            ['href' => '/jobmington/admin/courses.php', 'label' => 'Courses', 'icon' => 'fa-graduation-cap', 'match' => '/admin/courses'],
            ['href' => '/jobmington/admin/modules.php', 'label' => 'Modules', 'icon' => 'fa-book-open', 'match' => '/admin/modules'],
            ['href' => '/jobmington/admin/quizzes.php', 'label' => 'Quizzes', 'icon' => 'fa-tasks', 'match' => '/admin/quizzes'],
            ['href' => '/jobmington/admin/certificates.php', 'label' => 'Certificates', 'icon' => 'fa-certificate', 'match' => '/admin/certificates'],
            ['href' => '/jobmington/admin/ebooks.php', 'label' => 'Ebooks', 'icon' => 'fa-book', 'match' => '/admin/ebooks'],
            ['href' => '/jobmington/admin/events.php', 'label' => 'Events', 'icon' => 'fa-calendar-day', 'match' => '/admin/events'],
        ],
        'Engagement' => [
            ['href' => '/jobmington/admin/badges.php', 'label' => 'Badges & Seeds', 'icon' => 'fa-medal', 'match' => '/admin/badges'],
            ['href' => '/jobmington/admin/blog.php', 'label' => 'Blog', 'icon' => 'fa-pen', 'match' => '/admin/blog'],
            ['href' => '/jobmington/admin/forum.php', 'label' => 'Forum', 'icon' => 'fa-comments', 'match' => '/admin/forum'],
            ['href' => '/jobmington/admin/email-campaigns.php', 'label' => 'Outreach', 'icon' => 'fa-envelope-open-text', 'match' => '/admin/email-campaigns'],
        ],
        'System' => [
            ['href' => '/jobmington/admin/categories.php', 'label' => 'Categories', 'icon' => 'fa-layer-group', 'match' => '/admin/categories'],
            ['href' => '/jobmington/admin/countries.php', 'label' => 'Countries', 'icon' => 'fa-globe-africa', 'match' => '/admin/countries'],
            ['href' => '/jobmington/admin/certificate-branding.php', 'label' => 'Certificate', 'icon' => 'fa-award', 'match' => '/admin/certificate-branding'],
            ['href' => '/jobmington/admin/settings.php', 'label' => 'Settings', 'icon' => 'fa-sliders', 'match' => '/admin/settings'],
        ],
    ];
    $adminCurrentPath = rtrim(preg_replace('~^/jobmington~i', '', $currentPath), '/') ?: '/admin';
    $jmAdminActive = function (string $match) use ($adminCurrentPath): bool {
        $match = rtrim($match, '/');
        if ($match === '/admin') {
            return $adminCurrentPath === '/admin';
        }
        return $adminCurrentPath === $match || str_starts_with($adminCurrentPath, $match);
    };
    ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#0640a3">
    <link rel="manifest" href="/jobmington/manifest.json?v=brand-10">
    <link rel="icon" type="image/png" href="/jobmington/assets/images/favicon.png?v=fav-1">
    <link rel="shortcut icon" type="image/png" href="/jobmington/assets/images/favicon.png?v=fav-1">
    <link rel="apple-touch-icon" href="/jobmington/assets/images/pwa-icon-192.png?v=brand-10">
    <title><?= htmlspecialchars($headerConfig->title) ?></title>
    <link rel="preload" as="font" type="font/ttf" href="/jobmington/assets/fonts/FuturaCyrillicDemi.ttf" crossorigin>
    <link rel="preload" as="font" type="font/ttf" href="/jobmington/assets/fonts/FuturaCyrillicBook.ttf" crossorigin>
    <link rel="stylesheet" href="/jobmington/assets/css/minimal-jobmington.css?v=brand-17">
    <script src="https://cdn.tailwindcss.com" nonce="<?= $cspNonce ?>"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root {
            --admin-bg: #f4f7fb;
            --admin-ink: #0b1b33;
            --admin-muted: #5b6b82;
            --admin-line: #e4eaf3;
            --admin-card: #ffffff;
            --admin-blue: #0640a3;
            --admin-blue-soft: #eef4ff;
            --admin-orange: #f59f22;
            --admin-green: #0f766e;
            --admin-amber: #b66b00;
            --admin-red: #b42318;
            --admin-sidebar-w: 250px;
            /* One radius and one elevation for every surface in the panel, so
               cards read as the same material instead of each page inventing
               its own outline. */
            --admin-radius: 16px;
            --admin-radius-sm: 12px;
            --admin-shadow: 0 1px 2px rgba(11,27,51,.04), 0 10px 26px -14px rgba(11,27,51,.16);
            --admin-shadow-lift: 0 2px 6px rgba(11,27,51,.06), 0 20px 44px -20px rgba(11,27,51,.24);
        }
        body.jm-admin {
            margin: 0;
            background: var(--admin-bg);
            color: var(--admin-ink);
            font-family: "Futura Cyrillic Demi", sans-serif !important;
            font-size: 15px;
            line-height: 1.55;
            -webkit-font-smoothing: antialiased;
        }
        body.jm-admin * { box-sizing: border-box; }

        /* ── Layout ─────────────────────────────────────────────── */
        .jm-admin-layout { display: flex; min-height: 100vh; }

        .jm-admin-sidebar {
            position: fixed; top: 0; left: 0; bottom: 0;
            width: var(--admin-sidebar-w);
            background: #ffffff;
            border-right: 1px solid var(--admin-line);
            display: flex; flex-direction: column;
            z-index: 60;
            transition: transform .22s ease;
        }
        .jm-admin-sidebar-brand {
            display: flex; align-items: center; gap: 12px;
            padding: 20px 20px 18px; text-decoration: none;
            border-bottom: 1px solid var(--admin-line);
        }
        .jm-admin-sidebar-brand img { width: 36px; height: 36px; object-fit: contain; flex-shrink: 0; }
        .jm-admin-sidebar-brand-text strong { display: block; color: var(--admin-ink); font-size: 15px; font-weight: 800; letter-spacing: -.02em; line-height: 1.2; }
        .jm-admin-sidebar-brand-text small { display: block; color: var(--admin-muted); font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .12em; margin-top: 2px; }

        .jm-admin-navwrap { flex: 1; overflow-y: auto; padding: 12px 10px 10px; }
        .jm-admin-navgroup { margin-bottom: 4px; }
        .jm-admin-navgroup h6 {
            margin: 0 0 2px; padding: 6px 12px 4px;
            font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: .13em;
            color: #b0bccf;
        }
        .jm-admin-navgroup a {
            display: flex; align-items: center; gap: 10px;
            padding: 8px 12px; margin-bottom: 1px;
            border-radius: 8px;
            color: #4d6074; text-decoration: none;
            font-size: 13px; font-weight: 600;
            transition: background .1s, color .1s;
            white-space: nowrap;
        }
        .jm-admin-navgroup a i {
            width: 16px; text-align: center; font-size: 13px;
            color: #9bb0c7; flex-shrink: 0; transition: color .1s;
        }
        .jm-admin-navgroup a:hover { background: #f3f7fd; color: var(--admin-ink); }
        .jm-admin-navgroup a:hover i { color: var(--admin-blue); }
        .jm-admin-navgroup a.is-active {
            background: var(--admin-blue); color: #fff;
            font-weight: 700;
        }
        .jm-admin-navgroup a.is-active i { color: rgba(255,255,255,.85); }

        .jm-admin-navdivider { height: 1px; background: var(--admin-line); margin: 8px 12px; }

        .jm-admin-sidebar-foot { padding: 10px; border-top: 1px solid var(--admin-line); }
        .jm-admin-sidebar-foot a {
            display: flex; align-items: center; gap: 10px;
            padding: 8px 12px; border-radius: 8px;
            font-size: 13px; font-weight: 600; text-decoration: none; color: #4d6074;
            transition: background .1s, color .1s;
        }
        .jm-admin-sidebar-foot a i { width: 16px; text-align: center; font-size: 13px; color: #9bb0c7; }
        .jm-admin-sidebar-foot a:hover { background: #f3f7fd; color: var(--admin-ink); }
        .jm-admin-sidebar-foot a.logout { color: var(--admin-red); }
        .jm-admin-sidebar-foot a.logout i { color: var(--admin-red); opacity: .7; }
        .jm-admin-sidebar-foot a.logout:hover { background: #fff5f5; }

        /* ── Main column ────────────────────────────────────────── */
        .jm-admin-main { flex: 1; min-width: 0; margin-left: var(--admin-sidebar-w); display: flex; flex-direction: column; min-height: 100vh; }
        .jm-admin-topbar {
            display: none; align-items: center; gap: 12px;
            padding: 12px 16px; background: #fff; border-bottom: 1px solid var(--admin-line);
            position: sticky; top: 0; z-index: 50;
        }
        .jm-admin-burger {
            display: inline-flex; align-items: center; justify-content: center;
            width: 36px; height: 36px; border: 1px solid var(--admin-line);
            border-radius: 8px; background: #fff; color: var(--admin-ink); cursor: pointer; font-size: 15px;
        }
        .jm-admin-topbar-brand { display: inline-flex; align-items: center; gap: 9px; font-weight: 800; color: var(--admin-ink); text-decoration: none; }
        .jm-admin-topbar-brand img { width: 26px; height: 26px; }

        .jm-admin-content { flex: 1; padding: 30px 30px 48px; }

        .jm-admin-footer {
            color: var(--admin-muted); display: flex; flex-wrap: wrap;
            font-size: 11.5px; gap: 10px; justify-content: space-between;
            padding: 14px 30px 20px; border-top: 0;
        }

        .jm-admin-backdrop {
            display: none; position: fixed; inset: 0;
            background: rgba(11,27,51,.4); z-index: 55;
            backdrop-filter: blur(2px);
        }
        body.jm-admin.nav-open .jm-admin-backdrop { display: block; }

        /* ── Reusable dashboard components (.ja-*) ──────────────── */
        .ja-pagehead {
            display: flex; align-items: flex-start; justify-content: space-between;
            gap: 16px; flex-wrap: wrap; margin-bottom: 24px;
        }
        .ja-pagehead h1 {
            margin: 0 0 5px; font-size: 24px; font-weight: 800;
            letter-spacing: -.03em; color: var(--admin-ink);
        }
        .ja-pagehead p { margin: 0; font-size: 13.5px; color: var(--admin-muted); }
        .ja-statuschip {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 8px 15px; border-radius: 10px; font-size: 12.5px; font-weight: 700;
            background: #fff; border: 0;
            box-shadow: var(--admin-shadow);
            white-space: nowrap;
        }
        .ja-dot { width: 7px; height: 7px; border-radius: 50%; background: var(--admin-green); flex-shrink: 0; }
        .ja-dot.down { background: var(--admin-red); }

        /* KPI cards */
        .ja-kpis { display: grid; grid-template-columns: repeat(4, minmax(0,1fr)); gap: 16px; margin-bottom: 26px; }
        .ja-kpi {
            position: relative; display: block; text-decoration: none;
            background: var(--admin-card);
            border: 0;
            border-radius: var(--admin-radius); padding: 22px;
            overflow: hidden;
            box-shadow: var(--admin-shadow);
            transition: box-shadow .2s ease, transform .2s ease;
        }
        /* The 3px strip across the top of each tile was one more hard line.
           The tinted icon already carries the tone. */
        .ja-kpi:hover { box-shadow: var(--admin-shadow-lift); transform: translateY(-2px); }

        .ja-kpi-top { display: flex; align-items: flex-start; justify-content: space-between; gap: 10px; margin-bottom: 6px; }
        .ja-kpi-ico {
            width: 36px; height: 36px; border-radius: 9px;
            display: grid; place-items: center; font-size: 14px; flex-shrink: 0;
            background: var(--admin-blue-soft); color: var(--admin-blue);
        }
        .ja-kpi.tone-good .ja-kpi-ico    { background: #e6f5f1; color: var(--admin-green); }
        .ja-kpi.tone-warning .ja-kpi-ico { background: #fef5e7; color: var(--admin-amber); }
        .ja-kpi.tone-neutral .ja-kpi-ico { background: #f0f3f8; color: #64748b; }

        .ja-kpi-val { font-size: 32px; font-weight: 800; letter-spacing: -.04em; line-height: 1; color: var(--admin-ink); }
        .ja-kpi-lab { font-size: 12.5px; font-weight: 700; color: var(--admin-muted); margin-top: 6px; text-transform: uppercase; letter-spacing: .05em; }
        .ja-kpi-det { font-size: 11.5px; color: #94a3b8; margin-top: 4px; line-height: 1.5; }

        /* Grid layouts */
        .ja-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 26px; }
        .ja-grid-3 { display: grid; grid-template-columns: repeat(3, minmax(0,1fr)); gap: 20px; margin-bottom: 26px; }

        /* Cards */
        .ja-card {
            background: var(--admin-card); border: 0;
            border-radius: var(--admin-radius); overflow: hidden;
            box-shadow: var(--admin-shadow);
        }
        /* No grey strip and no divider: a card should read as one surface, not
           as a header glued to a body. */
        .ja-card-head {
            display: flex; align-items: center; justify-content: space-between; gap: 12px;
            padding: 20px 22px 6px; border-bottom: 0;
            background: none;
        }
        .ja-card-head h2 { margin: 0; font-size: 15px; font-weight: 800; color: var(--admin-ink); text-transform: none; letter-spacing: -.015em; }
        .ja-card-head a {
            font-size: 12px; font-weight: 700; color: var(--admin-blue);
            text-decoration: none; padding: 4px 10px;
            background: var(--admin-blue-soft); border-radius: 6px;
        }
        .ja-card-head a:hover { background: #d9e8ff; }
        .ja-card-body { padding: 6px 22px 20px; }

        /* Attention tiles */
        .ja-attention { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
        .ja-att {
            display: flex; gap: 14px; align-items: flex-start;
            padding: 14px 14px 14px 18px;
            border: 1px solid var(--admin-line); border-radius: 10px;
            text-decoration: none; background: #fff;
            transition: box-shadow .15s, transform .15s;
        }
        .ja-att:hover { box-shadow: 0 4px 14px rgba(11,27,51,.07); transform: translateY(-1px); }
        .ja-att-count { font-size: 26px; font-weight: 800; letter-spacing: -.04em; line-height: 1; color: var(--admin-ink); min-width: 32px; }
        .ja-att-lab { font-size: 13px; font-weight: 700; color: var(--admin-ink); line-height: 1.3; }
        .ja-att-det { font-size: 11.5px; color: var(--admin-muted); margin-top: 3px; line-height: 1.4; }
        .ja-att.tone-warning { border-left: 4px solid var(--admin-amber); }
        .ja-att.tone-danger  { border-left: 4px solid var(--admin-red); }
        .ja-att.tone-good    { border-left: 4px solid var(--admin-green); }

        /* Health bars */
        .ja-health-row { padding: 10px 0; border-bottom: 1px solid #f0f4f9; }
        .ja-health-row:last-child { border-bottom: none; }
        .ja-health-top { display: flex; align-items: center; justify-content: space-between; gap: 8px; margin-bottom: 6px; }
        .ja-health-lab { font-size: 12.5px; font-weight: 700; color: var(--admin-ink); }
        .ja-health-val { font-size: 12px; font-weight: 700; }
        .ja-health-det { font-size: 11px; color: var(--admin-muted); margin-top: 4px; }
        .ja-bar { height: 5px; border-radius: 999px; background: #edf2f7; overflow: hidden; }
        .ja-bar > span { display: block; height: 100%; border-radius: 999px; transition: width .4s ease; }
        .ja-bar > span.tone-good    { background: var(--admin-green); }
        .ja-bar > span.tone-warning { background: var(--admin-amber); }
        .ja-bar > span.tone-danger  { background: var(--admin-red); }
        .ja-bar > span.tone-neutral { background: #94a3b8; }
        .ja-tone-good { color: var(--admin-green); } .ja-tone-warning { color: var(--admin-amber); }
        .ja-tone-danger { color: var(--admin-red); } .ja-tone-neutral { color: #64748b; }

        /* Quick-link tiles */
        .ja-quick { display: grid; grid-template-columns: repeat(4, minmax(0,1fr)); gap: 12px; margin-bottom: 24px; }
        .ja-tile {
            display: flex; flex-direction: column; gap: 10px;
            padding: 16px; background: var(--admin-card);
            border: 1px solid var(--admin-line); border-radius: 12px;
            text-decoration: none;
            box-shadow: 0 1px 3px rgba(11,27,51,.04);
            transition: box-shadow .15s, transform .15s, border-color .15s;
        }
        .ja-tile:hover { box-shadow: 0 8px 22px rgba(11,27,51,.09); transform: translateY(-2px); border-color: #c8d8ef; }
        .ja-tile i {
            width: 40px; height: 40px; border-radius: 10px;
            display: grid; place-items: center;
            background: var(--admin-blue-soft); color: var(--admin-blue);
            font-size: 16px;
        }
        .ja-tile b { font-size: 13px; font-weight: 800; color: var(--admin-ink); display: block; }
        .ja-tile span { font-size: 11.5px; color: var(--admin-muted); line-height: 1.4; }

        /* Activity feeds */
        .ja-feed { width: 100%; border-collapse: collapse; font-size: 13px; }
        .ja-feed td { padding: 11px 8px; border-bottom: 1px solid #f0f4f9; vertical-align: middle; color: var(--admin-ink); }
        .ja-feed tr:last-child td { border-bottom: none; }
        .ja-feed tr:hover td { background: #fafbfd; }
        .ja-feed .sub { font-size: 11.5px; color: var(--admin-muted); margin-top: 2px; }
        .ja-feed-empty { padding: 28px 8px; text-align: center; color: var(--admin-muted); font-size: 13px; }

        /* Status pills */
        .ja-pill {
            display: inline-flex; align-items: center;
            padding: 3px 8px; border-radius: 6px;
            font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: .06em;
        }
        .ja-pill.good    { background: #e6f5f1; color: #0a6454; }
        .ja-pill.warning { background: #fef5e7; color: #8c5200; }
        .ja-pill.danger  { background: #fdecea; color: #991b1b; }
        .ja-pill.neutral { background: #f0f3f8; color: #475569; }

        /* ── Tailwind compatibility (legacy admin pages) ────────── */
        .jm-admin .bg-gray-100, .jm-admin .bg-slate-950, .jm-admin .bg-slate-900 { background: transparent !important; }
        .jm-admin .shadow-md, .jm-admin .shadow-xl, .jm-admin .shadow-2xl { box-shadow: none !important; }
        .jm-admin .rounded-lg, .jm-admin .rounded-xl, .jm-admin .rounded-2xl, .jm-admin .rounded-3xl { border-radius: 10px !important; }
        .jm-admin .bg-white, .jm-admin .bg-white\/5, .jm-admin .bg-slate-900\/80 { background: var(--admin-card) !important; }
        .jm-admin .text-white { color: var(--admin-ink) !important; }
        .jm-admin .text-slate-400, .jm-admin .text-slate-500, .jm-admin .text-gray-500 { color: var(--admin-muted) !important; }
        .jm-admin table { color: var(--admin-ink); }

        /* ── Responsive ─────────────────────────────────────────── */
        @media (max-width: 1100px) {
            .ja-kpis { grid-template-columns: repeat(2, minmax(0,1fr)); }
            .ja-quick { grid-template-columns: repeat(2, minmax(0,1fr)); }
            .ja-grid-3 { grid-template-columns: 1fr; }
        }
        /* Data tables scroll inside their own box so a wide table never forces
           the whole page sideways. Cells stay on one line: wrapping a 9-column
           table into a phone width makes it unreadable rather than compact. */
        .jm-tablewrap { max-width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; }
        .jm-tablewrap > table { width: 100%; }
        @media (max-width: 768px) {
            .jm-tablewrap > table { min-width: 640px; }
            .jm-tablewrap th, .jm-tablewrap td { white-space: nowrap; }
        }


        @media (max-width: 1024px) {
            .jm-admin-sidebar { transform: translateX(-100%); box-shadow: 0 0 40px rgba(11,27,51,.18); }
            body.jm-admin.nav-open .jm-admin-sidebar { transform: translateX(0); }
            .jm-admin-main { margin-left: 0; }
            .jm-admin-topbar { display: flex; }
        }
        @media (max-width: 680px) {
            .ja-kpis { grid-template-columns: 1fr; }
            .ja-grid-2 { grid-template-columns: 1fr; }
            .ja-quick { grid-template-columns: 1fr; }
            .ja-attention { grid-template-columns: 1fr; }
            .jm-admin-content { padding: 18px 14px; }
        }

        /* ── One surface for every card in the panel ────────────────
           The admin grew three visual languages: Tailwind cards with
           border-slate-200, pages with their own .jm-*-card CSS, and a few
           built for a dark theme. Each drew its own outline, which is what
           made the panel read as a pile of boxes rather than a dashboard.

           This normalises them all to the same borderless surface. It is
           deliberately scoped so it cannot touch a form control: an input
           without its border is invisible, so anything that is a field, or
           carries a field's rounded-lg without a shadow, is left alone. */
        body.jm-admin .ja-panel,
        body.jm-admin .jm-panel,
        body.jm-admin .jm-ad-card,
        body.jm-admin .jm-ev-card,
        body.jm-admin .jm-eb-card,
        body.jm-admin .jm-backup-card,
        body.jm-admin .nc-card,
        body.jm-admin .nc-feed-card,
        body.jm-admin .nc-stat,
        body.jm-admin .nc-chip,
        body.jm-admin .ec-personalise,
        body.jm-admin .ec-card,
        body.jm-admin .ec-table-wrap,
        body.jm-admin div.bg-white.rounded-xl,
        body.jm-admin div.bg-white.rounded-2xl,
        body.jm-admin div.bg-white.rounded-lg.shadow,
        body.jm-admin form.bg-white.rounded-xl,
        body.jm-admin form.bg-white.rounded-lg.shadow {
            border: 0;
            border-radius: var(--admin-radius);
            box-shadow: var(--admin-shadow);
            background: var(--admin-card);
        }


        /* The dashboard had a second card system of its own. Same surface,
           same headings, so the panel does not change language when you land
           on it. */
        body.jm-admin .nc-card-head,
        body.jm-admin .nc-feed-head {
            border-bottom: 0;
            background: none;
            padding: 18px 20px 6px;
        }
        body.jm-admin .nc-card-head h3,
        body.jm-admin .nc-feed-head h3 {
            font-size: 15px; text-transform: none; letter-spacing: -.015em;
        }
        /* The 4px coloured spine on each stat tile was another hard edge. */
        body.jm-admin .nc-stat { border-left: 0; padding: 22px; }
        body.jm-admin .nc-stat:hover,
        body.jm-admin .nc-feed-card:hover { box-shadow: var(--admin-shadow-lift); }
        body.jm-admin .jm-eb-table { border: 0; box-shadow: var(--admin-shadow); border-radius: var(--admin-radius); overflow: hidden; }

        /* Tables carried the same outline. Inside a card they need none at
           all; standing alone they become the card. */
        body.jm-admin .jm-ev-table,
        body.jm-admin .jm-ad-table,
        body.jm-admin .ec-table {
            border: 0;
            border-radius: var(--admin-radius);
            overflow: hidden;
            box-shadow: var(--admin-shadow);
        }
        body.jm-admin .ja-card .jm-ev-table,
        body.jm-admin .ja-card .jm-ad-table,
        body.jm-admin div.bg-white .jm-ev-table,
        body.jm-admin div.bg-white .jm-ad-table { box-shadow: none; border-radius: 0; }

        /* Header rows: tone, not a rule. */
        body.jm-admin .jm-ev-table th,
        body.jm-admin .jm-ad-table th,
        body.jm-admin .ec-table th,
        body.jm-admin thead.bg-slate-50 th {
            border-bottom: 0;
            background: #f7f9fd;
        }
        body.jm-admin .jm-ev-table td,
        body.jm-admin .jm-ad-table td { border-bottom: 1px solid #f2f5fa; }
        body.jm-admin .jm-ev-table tr:last-child td,
        body.jm-admin .jm-ad-table tr:last-child td { border-bottom: 0; }

        /* Form controls keep a visible edge, just a softer one. */
        body.jm-admin input:not([type=checkbox]):not([type=radio]):not([type=submit]),
        body.jm-admin select,
        body.jm-admin textarea {
            border: 1px solid #dde5f1;
            border-radius: 10px;
            background: #fff;
        }
        body.jm-admin input:focus, body.jm-admin select:focus, body.jm-admin textarea:focus {
            outline: 0; border-color: var(--admin-blue);
            box-shadow: 0 0 0 3px rgba(6,64,163,.10);
        }

        /* Buttons and chips: the same corner language as the cards. */
        body.jm-admin .rounded-lg { border-radius: 10px; }
        body.jm-admin .rounded-xl { border-radius: var(--admin-radius); }

    </style>
    <?php require_once __DIR__ . '/stacktable.php'; ?>
</head>
<body class="jm-admin">
    <div class="jm-admin-layout">
        <aside class="jm-admin-sidebar" id="jmAdminSidebar">
            <a class="jm-admin-sidebar-brand" href="/jobmington/admin/">
                <img src="/jobmington/assets/images/badge.png?v=logo-8" alt="">
                <div class="jm-admin-sidebar-brand-text">
                    <strong>Jobmington</strong>
                    <small>Admin Console</small>
                </div>
            </a>
            <nav class="jm-admin-navwrap" aria-label="Admin navigation">
                <?php $groupIdx = 0; foreach ($adminNavGroups as $groupName => $items): ?>
                    <?php if ($groupIdx > 0): ?><div class="jm-admin-navdivider"></div><?php endif; ?>
                    <div class="jm-admin-navgroup">
                        <h6><?= htmlspecialchars($groupName) ?></h6>
                        <?php foreach ($items as $item): ?>
                            <a class="<?= $jmAdminActive($item['match']) ? 'is-active' : '' ?>" href="<?= htmlspecialchars($item['href']) ?>">
                                <i class="fas <?= htmlspecialchars($item['icon']) ?>"></i>
                                <span><?= htmlspecialchars($item['label']) ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php $groupIdx++; endforeach; ?>
            </nav>
            <div class="jm-admin-sidebar-foot">
                <a href="/jobmington/"><i class="fas fa-up-right-from-square fa-fw"></i> View site</a>
                <a class="logout" href="/jobmington/auth/logout.php"><i class="fas fa-right-from-bracket fa-fw"></i> Log out</a>
            </div>
        </aside>

        <div class="jm-admin-main">
            <header class="jm-admin-topbar">
                <button class="jm-admin-burger" type="button" id="jmAdminBurger" aria-label="Toggle menu"><i class="fas fa-bars"></i></button>
                <a class="jm-admin-topbar-brand" href="/jobmington/admin/">
                    <img src="/jobmington/assets/images/badge.png?v=logo-8" alt=""> Admin
                </a>
            </header>
            <main class="jm-admin-content">
    <?php
    return;
}

ob_start();
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#030303">
    <link rel="manifest" href="/jobmington/manifest.json?v=brand-10">
    <link rel="icon" type="image/png" href="/jobmington/assets/images/favicon.png?v=fav-1">
    <link rel="shortcut icon" type="image/png" href="/jobmington/assets/images/favicon.png?v=fav-1">
    <link rel="apple-touch-icon" href="/jobmington/assets/images/pwa-icon-192.png?v=brand-10">
    <title><?= htmlspecialchars($headerConfig->title) ?></title>
    
    <!-- Premium Design System -->
    <link rel="stylesheet" href="/jobmington/assets/css/premium-design-system.css?v=brand-10">
    <script src="/jobmington/assets/js/jm-image-compress.js?v=brand-15" defer></script>
    <link rel="stylesheet" href="/jobmington/assets/css/minimal-jobmington.css?v=brand-17">
    
    <script src="https://cdn.tailwindcss.com" nonce="<?= $cspNonce ?>"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <script nonce="<?= $cspNonce ?>">
        function initTheme() {
            // Light mode only
            document.documentElement.classList.remove('dark');
        }
        initTheme();



        tailwind.config = {
            darkMode: false,
            theme: {
                extend: {
                    fontFamily: { sans: ['Futura Cyrillic Demi'] },
                    colors: { 
                        brand: { 
                            400: '#e5e5e5',
                            500: '#ffffff', 
                            600: '#d4d4d4' 
                        }, 
                        obsidian: { 
                            700: '#181818',
                            800: '#111111', 
                            900: '#0a0a0a', 
                            950: '#030303' 
                        }
                    },
                    animation: { 'spin-slow': 'spin 3s linear infinite' }
                }
            }
        }
    </script>

    <style nonce="<?= $cspNonce ?>">
        .hide-scrollbar::-webkit-scrollbar { display: none; }
        .hide-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }

        .dark input, .dark select, .dark textarea, .dark .form-input {
            background-color: #0a0a0a !important; color: #ffffff !important; border-color: rgba(255,255,255,0.08) !important;
        }
        
        /* =========================================
           GLOBAL TOAST NOTIFICATIONS
           ========================================= */
        .toast-container {
            position: fixed;
            top: 100px;
            right: 24px;
            z-index: 99999;
            display: flex;
            flex-direction: column;
            gap: 12px;
            pointer-events: none;
        }
        
        .toast {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 16px 20px;
            border-radius: 12px;
            background: rgba(30, 41, 59, 0.95);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 20px 40px -12px rgba(0, 0, 0, 0.5);
            min-width: 300px;
            max-width: 420px;
            pointer-events: auto;
            animation: toastSlideIn 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            transform-origin: top right;
            position: relative;
            overflow: hidden;
        }
        
        .toast.hiding {
            animation: toastSlideOut 0.25s ease-in forwards;
        }
        
        @keyframes toastSlideIn {
            from { opacity: 0; transform: translateX(100%) scale(0.9); }
            to { opacity: 1; transform: translateX(0) scale(1); }
        }
        
        @keyframes toastSlideOut {
            from { opacity: 1; transform: translateX(0) scale(1); }
            to { opacity: 0; transform: translateX(100%) scale(0.9); }
        }
        
        .toast-icon {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .toast-icon svg { width: 14px; height: 14px; stroke: currentColor; stroke-width: 2; fill: none; }
        
        .toast.toast-success .toast-icon { background: rgba(34, 197, 94, 0.15); color: #22c55e; }
        .toast.toast-error .toast-icon { background: rgba(239, 68, 68, 0.15); color: #ef4444; }
        .toast.toast-warning .toast-icon { background: rgba(245, 158, 11, 0.15); color: #f59e0b; }
        .toast.toast-info .toast-icon { background: rgba(59, 130, 246, 0.15); color: #3b82f6; }
        
        .toast-content { flex: 1; }
        .toast-title {
            font-size: 0.875rem;
            font-weight: 600;
            color: #ffffff;
            margin-bottom: 2px;
        }
        .toast-message {
            font-size: 0.8125rem;
            color: #94a3b8;
            line-height: 1.5;
        }
        
        .toast-close {
            background: none;
            border: none;
            color: #64748b;
            cursor: pointer;
            padding: 4px;
            margin: -4px -4px -4px 8px;
            border-radius: 6px;
            transition: all 0.15s ease;
        }
        .toast-close:hover {
            background: rgba(255, 255, 255, 0.1);
            color: #ffffff;
        }
        .toast-close svg { width: 16px; height: 16px; stroke: currentColor; stroke-width: 2; fill: none; }
        
        .toast-progress {
            position: absolute;
            bottom: 0;
            left: 0;
            height: 3px;
            border-radius: 0 0 12px 12px;
            animation: toastProgress linear forwards;
        }
        .toast.toast-success .toast-progress { background: #22c55e; }
        .toast.toast-error .toast-progress { background: #ef4444; }
        .toast.toast-warning .toast-progress { background: #f59e0b; }
        .toast.toast-info .toast-progress { background: #3b82f6; }
        
        @keyframes toastProgress {
            from { width: 100%; }
            to { width: 0%; }
        }
        
        /* =========================================
           GLOBAL MODAL DIALOGS
           ========================================= */
        .jm-modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(4px);
            -webkit-backdrop-filter: blur(4px);
            z-index: 100000;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            opacity: 0;
            visibility: hidden;
            transition: all 0.2s ease;
        }
        .jm-modal-overlay.active {
            opacity: 1;
            visibility: visible;
        }
        
        .jm-modal-dialog {
            background: linear-gradient(180deg, #1e293b 0%, #0f172a 100%);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            box-shadow: 0 25px 60px -15px rgba(0, 0, 0, 0.6);
            max-width: 400px;
            width: 100%;
            transform: scale(0.95) translateY(-10px);
            transition: transform 0.25s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .jm-modal-overlay.active .jm-modal-dialog {
            transform: scale(1) translateY(0);
        }
        
        .jm-modal-header {
            padding: 24px 24px 0 24px;
            display: flex;
            align-items: flex-start;
            gap: 16px;
        }
        
        .jm-modal-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .jm-modal-icon svg { width: 24px; height: 24px; stroke: currentColor; stroke-width: 2; fill: none; }
        
        .jm-modal-icon.confirm { background: rgba(59, 130, 246, 0.15); color: #3b82f6; }
        .jm-modal-icon.warning { background: rgba(245, 158, 11, 0.15); color: #f59e0b; }
        .jm-modal-icon.danger { background: rgba(239, 68, 68, 0.15); color: #ef4444; }
        .jm-modal-icon.success { background: rgba(34, 197, 94, 0.15); color: #22c55e; }
        
        .jm-modal-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: #ffffff;
            margin-bottom: 8px;
        }
        .jm-modal-message {
            font-size: 0.875rem;
            color: #94a3b8;
            line-height: 1.6;
        }
        
        .jm-modal-footer {
            padding: 20px 24px 24px 24px;
            display: flex;
            gap: 12px;
            justify-content: flex-end;
        }
        
        .jm-modal-btn {
            padding: 10px 20px;
            border-radius: 10px;
            font-size: 0.875rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.15s ease;
            border: none;
        }
        .jm-modal-btn-secondary {
            background: rgba(255, 255, 255, 0.08);
            color: #94a3b8;
        }
        .jm-modal-btn-secondary:hover {
            background: rgba(255, 255, 255, 0.12);
            color: #ffffff;
        }
        .jm-modal-btn-primary {
            background: #f59e0b;
            color: #000;
        }
        .jm-modal-btn-primary:hover {
            filter: brightness(1.1);
        }
        .jm-modal-btn-danger {
            background: #ef4444;
            color: white;
        }
        .jm-modal-btn-danger:hover {
            background: #dc2626;
        }
        
        @media (max-width: 480px) {
            .toast-container { right: 12px; left: 12px; }
            .toast { min-width: auto; max-width: none; }
        }
        
        .island-nav {
            position: fixed; top: 0; left: 0; right: 0; z-index: 50;
            transition: all 0.3s ease;
            border-bottom: 2px solid var(--color-ink);
            background: var(--color-primary);
            box-shadow: 4px 0px 0px 0px var(--color-ink);
            color: #ffffff; /* Global white for nav */
            text-shadow: 0 1px 2px rgba(0,0,0,0.1);
        }
        .island-nav.scrolled {
            background: var(--color-primary);
            box-shadow: 4px 4px 0px 0px var(--color-ink);
        }

        .flag-orb {
            position: relative; overflow: hidden; border-radius: 9999px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
            border: 1.5px solid rgba(255, 255, 255, 0.2);
            transform: translateZ(0); transition: all 0.15s ease;
        }
        .flag-orb:hover { transform: scale(1.05); border-color: rgba(139, 92, 246, 0.4); }
        .flag-orb::before {
            content: ''; position: absolute; top: 0; left: 0; right: 0; bottom: 50%;
            background: linear-gradient(to bottom, rgba(255,255,255,0.9) 0%, rgba(255,255,255,0.1) 100%);
            border-radius: 9999px 9999px 50% 50%; pointer-events: none; z-index: 10;
        }

        /* --- REGION SELECTOR STYLES --- */
        .geo-modal {
            background: rgb(15, 23, 42);
            backdrop-filter: blur(24px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5);
        }
        .geo-header {
            background: rgba(255, 255, 255, 0.02);
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        }
        .geo-region-title {
            font-family: 'Futura Cyrillic Demi';
            letter-spacing: 0.05em;
            text-transform: uppercase;
            font-size: 0.65rem;
            font-weight: 700;
            color: #64748b;
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .geo-region-title::after {
            content: '';
            flex: 1;
            height: 1px;
            background: rgba(255, 255, 255, 0.06);
        }
        .geo-tile {
            background: transparent;
            border: none;
            border-radius: 0.5rem;
            transition: all 0.2s ease;
            position: relative;
        }
        .geo-tile:hover {
            background: rgba(255, 255, 255, 0.05);
        }
        .geo-tile.active {
            background: rgba(255, 255, 255, 0.08);
        }
        .geo-tile.active .geo-check {
            opacity: 1;
        }
        .geo-check {
            opacity: 0;
            transition: opacity 0.2s ease;
        }

        .nav-pill { 
            position: relative; 
            font-weight: 600; 
            font-size: 0.875rem;
            color: #ffffff; 
            opacity: 0.9;
            transition: all 0.15s ease;
            letter-spacing: -0.01em;
        }
        .nav-pill:hover { 
            opacity: 1;
            color: #ffffff;
        }
        .nav-pill.active { 
            color: #ffffff; 
            opacity: 1;
            font-weight: 700; 
        }
        
        .mobile-panel { transform: translateX(100%); transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1); }
        body.menu-open .mobile-panel { transform: translateX(0); }
        body.menu-open .mobile-backdrop { opacity: 1; pointer-events: auto; }

        /* --- PRELOADER --- */
        #preloader {
            position: fixed; inset: 0; z-index: 99999; 
            background: #0f172a;
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            font-family: 'Futura Cyrillic Demi';
            transition: opacity 0.6s ease-out, visibility 0.6s;
            overflow: hidden;
        }
        /* Dynamic background orbs */
        #preloader::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -25%;
            width: 600px;
            height: 600px;
            background: radial-gradient(circle, rgba(71, 85, 105, 0.15) 0%, transparent 70%);
            border-radius: 50%;
            animation: orb-float-1 8s ease-in-out infinite;
        }
        #preloader::after {
            content: '';
            position: absolute;
            bottom: -30%;
            right: -20%;
            width: 500px;
            height: 500px;
            background: radial-gradient(circle, rgba(245, 158, 11, 0.08) 0%, transparent 70%);
            border-radius: 50%;
            animation: orb-float-2 10s ease-in-out infinite;
        }
        @keyframes orb-float-1 {
            0%, 100% { transform: translate(0, 0) scale(1); }
            50% { transform: translate(50px, 30px) scale(1.1); }
        }
        @keyframes orb-float-2 {
            0%, 100% { transform: translate(0, 0) scale(1); }
            50% { transform: translate(-40px, -20px) scale(1.15); }
        }
        .preloader-content { 
            position: relative; 
            z-index: 10; 
            display: flex; 
            flex-direction: column; 
            align-items: center; 
            text-align: center;
        }
        .preloader-brand { 
            display: flex; 
            align-items: center; 
            justify-content: center;
            gap: 14px; 
            margin-bottom: 24px; 
        }
        .preloader-badge { 
            width: 52px; 
            height: 52px; 
            object-fit: contain; 
            animation: badge-float 3s ease-in-out infinite;
            filter: drop-shadow(0 4px 12px rgba(245, 158, 11, 0.3));
        }
        @keyframes badge-float {
            0%, 100% { transform: translateY(0) rotate(0deg); }
            50% { transform: translateY(-8px) rotate(3deg); }
        }
        .preloader-name { 
            font-size: 2rem; 
            font-weight: 900; 
            letter-spacing: -0.04em; 
            color: #ffffff; 
            font-family: 'Futura Cyrillic Demi';
            text-shadow: 0 2px 20px rgba(255,255,255,0.1);
        }
        
        /* Loading Bar */
        .loader-bar-track { 
            width: 240px; 
            height: 4px; 
            background: rgba(255,255,255,0.08); 
            border-radius: 4px; 
            overflow: hidden;
            position: relative;
        }
        .loader-bar-track::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.1), transparent);
            animation: shimmer 2s infinite;
        }
        @keyframes shimmer {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }
        .loader-bar-fill { 
            width: 0%; 
            height: 100%; 
            background: linear-gradient(90deg, #f59e0b, #fbbf24, #f59e0b); 
            background-size: 200% 100%;
            animation: gradient-flow 1.5s ease infinite;
            border-radius: 4px; 
            transition: width 0.15s ease-out;
            box-shadow: 0 0 20px rgba(245, 158, 11, 0.5), 0 0 40px rgba(245, 158, 11, 0.2);
        }
        @keyframes gradient-flow {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        .loader-percent {
            font-size: 0.75rem;
            font-weight: 700;
            color: #f59e0b;
            margin-top: 12px;
            font-family: 'Futura Cyrillic Demi';
            letter-spacing: 0.05em;
        }
        .loader-text { 
            font-size: 0.625rem; 
            letter-spacing: 0.15em; 
            color: #64748b; 
            text-transform: uppercase; 
            margin-top: 8px; 
            font-weight: 600;
        }

        /* --- FLAT NAVIGATION REFRESH --- */
        .island-nav {
            background: rgba(255,255,255,0.92) !important;
            border-bottom: 1px solid #e8edf5 !important;
            box-shadow: none !important;
            color: #101828 !important;
            text-shadow: none !important;
            backdrop-filter: blur(18px);
        }
        .island-nav.scrolled {
            background: rgba(255,255,255,0.96) !important;
            box-shadow: 0 1px 0 rgba(16,24,40,0.04) !important;
        }
        .jm-brand-text,
        .jm-region-label,
        .jm-region-code {
            color: #101828 !important;
        }
        .jm-region-label {
            opacity: 0.48 !important;
        }
        .jm-nav-divider {
            background: #e8edf5 !important;
            opacity: 1 !important;
        }
        .jm-nav-shell {
            background: transparent !important;
        }
        .island-nav .nav-pill {
            color: #667085 !important;
            opacity: 1;
            font-weight: 650;
        }
        .island-nav .nav-pill:hover,
        .island-nav .nav-pill.active {
            color: #073b2f !important;
            background: #effaf6;
        }
        .jm-nav-icon,
        .jm-menu-button {
            background: #ffffff !important;
            border: 1px solid #e8edf5 !important;
            color: #101828 !important;
            box-shadow: none !important;
        }
        .jm-nav-icon:hover,
        .jm-menu-button:hover {
            background: #effaf6 !important;
        }
        .jm-auth-link {
            color: #101828 !important;
        }
        .jm-auth-cta {
            background: #073b2f !important;
            color: #ffffff !important;
            border: 0 !important;
            border-radius: 999px !important;
            box-shadow: none !important;
        }
        .jm-profile-chip {
            background: #ffffff !important;
            border: 1px solid #e8edf5 !important;
            box-shadow: none !important;
        }
        .jm-profile-chip span,
        .jm-profile-chip i {
            color: #101828 !important;
        }
        #preloader {
            display: none !important;
        }
        .flag-orb {
            box-shadow: none !important;
            border: 1px solid #e8edf5 !important;
        }
        .flag-orb::before {
            display: none !important;
        }
        #mobile-panel {
            border-left: 1px solid #e8edf5 !important;
            box-shadow: none !important;
        }

        /* --- MOBILE: BRAND-BLUE HEADER BAR --- */
        /* Breakpoint matches Tailwind `lg`, where the hamburger replaces the nav pills. */
        @media (max-width: 1023.98px) {
            .island-nav {
                background: var(--jm-blue, #0640a3) !important;
                border-bottom: 1px solid rgba(255, 255, 255, 0.16) !important;
                backdrop-filter: none !important;
                color: #ffffff !important;
            }
            .island-nav.scrolled {
                background: var(--jm-blue, #0640a3) !important;
                box-shadow: 0 2px 10px rgba(6, 64, 163, 0.24) !important;
            }
            .island-nav .jm-brand-text,
            .island-nav .jm-region-code {
                color: #ffffff !important;
            }
            .island-nav .jm-region-label {
                color: #ffffff !important;
                opacity: 0.66 !important;
            }
            .island-nav .jm-nav-divider {
                background: rgba(255, 255, 255, 0.24) !important;
            }
            .island-nav .flag-orb {
                border: 1px solid rgba(255, 255, 255, 0.38) !important;
            }
            /* badge.png has the blue tile baked in, which reads as a mismatched
               square on the blue bar. Swap to the transparent-background mark. */
            .island-nav .jm-brand-mark {
                content: url("/jobmington/assets/images/badge-mark.png?v=logo-7");
                filter: none !important;
            }
            /* Icons and the hamburger sit plainly on the blue — no chip, no border */
            .island-nav .jm-nav-icon,
            .island-nav .jm-menu-button,
            .island-nav .jm-bell-btn,
            .island-nav .jm-nav-icon:hover,
            .island-nav .jm-menu-button:hover,
            .island-nav .jm-bell-btn:hover {
                background: transparent !important;
                border: 0 !important;
                color: #ffffff !important;
            }
            /* Touch feedback without reintroducing a box */
            .island-nav .jm-nav-icon:active,
            .island-nav .jm-menu-button:active,
            .island-nav .jm-bell-btn:active {
                opacity: 0.65;
            }
            .island-nav .jm-bell-badge {
                box-shadow: 0 0 0 2px var(--jm-blue, #0640a3) !important;
            }
            .island-nav .jm-auth-link {
                color: #ffffff !important;
            }
            .island-nav .jm-auth-cta {
                background: #ffffff !important;
                color: var(--jm-blue, #0640a3) !important;
            }
            .island-nav .jm-profile-chip {
                background: transparent !important;
                border: 0 !important;
            }
            .island-nav .jm-profile-chip span,
            .island-nav .jm-profile-chip i {
                color: #ffffff !important;
            }
        }

        /* ── DESKTOP: brand blue once you start reading ────────────────
           The bar stays frosted at rest, because the blur is doing real
           work: content passing under it reads as depth, and a solid
           colour throws that away. Once you scroll it becomes brand blue,
           so the header asserts itself where it is actually being seen
           against content rather than against the top of the page.

           Translucent, not solid, so the blur survives the change.

           Everything in the bar is restyled with it. The right-hand
           cluster carries inline styles, so these need !important to win,
           and the icons set their own background on hover in inline JS,
           which an important rule still beats. Leaving any of it would put
           white tiles on blue: the mismatched square already taken off the
           logo and the hamburger.

           Desktop only. Below 1024px the bar is already blue at all times
           and has its own rules. */
        @media (min-width: 1024px) {
            .island-nav.scrolled {
                background: rgba(6, 64, 163, 0.94) !important;
                border-bottom: 1px solid rgba(255, 255, 255, 0.16) !important;
                box-shadow: 0 2px 14px rgba(6, 64, 163, 0.20) !important;
                color: #ffffff !important;
            }
            .island-nav.scrolled .jm-brand-text,
            .island-nav.scrolled .jm-region-label,
            .island-nav.scrolled .jm-region-code,
            .island-nav.scrolled .nav-pill,
            .island-nav.scrolled .jm-auth-link {
                color: #ffffff !important;
                opacity: .92;
            }
            .island-nav.scrolled .nav-pill:hover,
            .island-nav.scrolled .jm-auth-link:hover { opacity: 1; }
            /* The active pill was pale mint with green ink, which does not
               survive the change of ground. */
            .island-nav.scrolled .nav-pill.active {
                color: #ffffff !important;
                background: rgba(255, 255, 255, 0.16) !important;
                opacity: 1;
            }

            /* Icon buttons sit plainly on the blue, like the mobile ones. */
            .island-nav.scrolled .jm-nav-icon,
            .island-nav.scrolled .jm-menu-button,
            .island-nav.scrolled .jm-bell-btn {
                background: transparent !important;
                border-color: rgba(255, 255, 255, 0.30) !important;
                color: #ffffff !important;
            }
            .island-nav.scrolled .jm-nav-icon:hover,
            .island-nav.scrolled .jm-menu-button:hover,
            .island-nav.scrolled .jm-bell-btn:hover {
                background: rgba(255, 255, 255, 0.14) !important;
            }
            /* The badge ring was white to lift it off a white bar. */
            .island-nav.scrolled .jm-bell-badge { box-shadow: 0 0 0 2px #0640a3 !important; }

            /* The call to action was dark green, which fights blue. Brand
               yellow with dark ink is the pairing already used on the
               buttons in the transactional email. */
            .island-nav.scrolled .jm-auth-cta {
                background: #f59f22 !important;
                color: #061426 !important;
            }

            /* The avatar ring is an inline border, and there is no class on
               the image to hang this on, so it is matched by its shape. */
            .island-nav.scrolled img[class*="rounded-md"],
            .island-nav.scrolled .jm-profile-chip {
                border-color: rgba(255, 255, 255, 0.42) !important;
            }
        }

        /* Brand blue hairline at rest, rather than the grey one: it signs the
           header without adding weight to it. */
        @media (min-width: 1024px) {
            .island-nav:not(.scrolled) {
                border-bottom: 1px solid rgba(6, 64, 163, 0.28) !important;
            }
        }
    </style>
</head>
<body class="antialiased" style="background: var(--color-canvas); color: var(--color-ink);">

    <!-- PRELOADER -->
    <div id="preloader">
        <div class="preloader-content">
            <div class="preloader-brand">
                <img src="/jobmington/assets/images/badge.png?v=logo-8" alt="Badge" class="preloader-badge">
                <span class="preloader-name">JOBMINGTON</span>
            </div>
            <div class="loader-bar-track">
                <div class="loader-bar-fill" id="load-bar"></div>
            </div>
            <div class="loader-percent" id="load-percent">0%</div>
            <div class="loader-text" id="load-status">INITIALIZING...</div>
        </div>
    </div>

    <script>
        // Dynamic Preloader - Only on first visit
        const App = {
            hasSeenPreloader: function() {
                return localStorage.getItem('jm_preloader_seen') === 'true';
            },
            markPreloaderSeen: function() {
                localStorage.setItem('jm_preloader_seen', 'true');
            },
            hidePreloaderInstantly: function() {
                const pre = document.getElementById('preloader');
                if (pre) {
                    pre.style.display = 'none';
                }
            },
            runPreloader: function() {
                // Skip preloader if already seen
                if (this.hasSeenPreloader()) {
                    this.hidePreloaderInstantly();
                    return;
                }
                
                const bar = document.getElementById('load-bar');
                const txt = document.getElementById('load-status');
                const pre = document.getElementById('preloader');
                const percentEl = document.getElementById('load-percent');
                let width = 0;
                
                const messages = [
                    "INITIALIZING...",
                    "LOADING ASSETS...",
                    "SYNCING DATA...",
                    "PREPARING INTERFACE...",
                    "ALMOST THERE..."
                ];
                
                const interval = setInterval(() => {
                    width += Math.random() * 12 + 2;
                    if (width > 100) width = 100;
                    
                    if (bar) bar.style.width = width + '%';
                    if (percentEl) percentEl.innerText = Math.floor(width) + '%';
                    
                    // Update status text based on progress
                    const msgIndex = Math.floor((width / 100) * (messages.length - 1));
                    if (txt) txt.innerText = messages[Math.min(msgIndex, messages.length - 1)];
                    
                    if (width >= 100) {
                        clearInterval(interval);
                        if (txt) txt.innerText = "READY!";
                        if (percentEl) percentEl.innerText = "100%";
                        this.markPreloaderSeen(); // Mark as seen
                        setTimeout(() => { 
                            if (pre) { 
                                pre.style.opacity = '0'; 
                                pre.style.visibility = 'hidden'; 
                            } 
                        }, 500);
                    }
                }, 120);
            }
        };
        
        // Check immediately if preloader should be hidden
        if (App.hasSeenPreloader()) {
            document.getElementById('preloader')?.style && (document.getElementById('preloader').style.display = 'none');
        }
        
        // Start preloader when page loads
        window.addEventListener('load', function() {
            App.runPreloader();
        });
        
        // Fallback: start preloader on DOM ready if taking too long
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(() => {
                const pre = document.getElementById('preloader');
                if (pre && pre.style.visibility !== 'hidden' && pre.style.display !== 'none') {
                    App.runPreloader();
                }
            }, 100);
        });
    </script>

    <header id="main-header" class="island-nav h-[80px] flex items-center">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 w-full flex justify-between items-center">
            
            <div class="flex items-center gap-6">
                <a href="/jobmington/" class="flex items-center gap-3 group relative">
                    <div class="relative w-10 h-10 flex items-center justify-center group-hover:scale-105 transition duration-300">
                        <img src="/jobmington/assets/images/badge.png?v=logo-8" class="relative z-10 w-8 h-8 object-contain drop-shadow-sm jm-brand-mark">
                    </div>
                    <div class="hidden sm:flex flex-col">
                        <span class="font-bold tracking-tight text-xl jm-brand-text" style="font-family: 'Futura Cyrillic Demi'; letter-spacing: 0; color: #101828;">
                            Jobmington
                        </span>
                    </div>
                </a>

                <div class="w-px h-8 hidden sm:block jm-nav-divider" style="background: #e8edf5; opacity: 1;"></div>

                <button onclick="toggleCountryModal()" class="flex items-center gap-2.5 outline-none group hover:scale-105 transition duration-300">
                    <div class="flag-orb w-7 h-7">
                        <img src="<?= htmlspecialchars($locationData['flagUrl']) ?>" class="w-full h-full object-cover relative z-0">
                    </div>
                    <div class="hidden md:flex flex-col items-start">
                        <span class="text-[10px] uppercase font-bold leading-tight jm-region-label" style="color: #101828; opacity: 0.48;">Region</span>
                        <span class="text-xs font-bold leading-tight flex items-center gap-1 jm-region-code" style="color: #101828;">
                            <?= htmlspecialchars($locationData['countryCode']) ?>
                            <i class="fas fa-chevron-down text-[8px] opacity-50"></i>
                        </span>
                    </div>
                </button>
            </div>

            <nav class="hidden lg:flex items-center gap-0.5 px-2 py-1 rounded-full jm-nav-shell" style="background: transparent;">
                <?php foreach ($navItems as $item): 
                    $isActive = $item['url'] === '/jobmington/'
                        ? rtrim($currentUri, '/') === '/jobmington'
                        : strpos($currentUri, $item['url']) === 0;
                ?>
                    <a href="<?= $item['url'] ?>" class="nav-pill px-3 py-1.5 rounded-full text-sm <?= $isActive ? 'active font-semibold' : '' ?> transition">
                        <?= htmlspecialchars($item['label']) ?>
                    </a>
                <?php endforeach; ?>
                
            </nav>

            <div class="flex items-center gap-1.5 sm:gap-2 md:gap-3">
                
                <!-- Search -->
                <a href="/jobmington/jobs/search.php" class="w-9 h-9 rounded-lg flex items-center justify-center transition duration-300 jm-nav-icon" style="background: #ffffff; border: 1px solid #e8edf5; color: #101828;" onmouseover="this.style.background='#f6f8fb'" onmouseout="this.style.background='#ffffff'" title="Search Jobs">
                    <i class="fas fa-search text-sm"></i>
                </a>

                <?php if ($isLoggedIn): ?>
                <!-- Saved Jobs (Hidden on small mobile) -->
                <a href="/jobmington/jobs/saved.php" class="hidden sm:flex w-9 h-9 rounded-lg items-center justify-center transition duration-300 jm-nav-icon" style="background: #ffffff; border: 1px solid #e8edf5; color: #101828;" onmouseover="this.style.background='#f6f8fb'" onmouseout="this.style.background='#ffffff'" title="Saved Jobs">
                    <i class="fas fa-bookmark text-sm"></i>
                </a>

                <?php endif; ?>

                <?php if ($isLoggedIn): 
                    $profileImg = asset('images/badge.png');
                    if (!empty($currentUser['profile_image'])) {
                        $profileImg = function_exists('profileImage') ? profileImage($currentUser['profile_image']) : $currentUser['profile_image'];
                    }
                    $isVerified = $_SESSION['is_verified'] ?? false;
                    // Ring colors: White glow for new theme
                    $ringStyle = 'border: 1px solid #e8edf5;';
                ?>
                    <!-- Desktop Profile -->
                    <div class="hidden md:flex items-center gap-2 relative group">
                        <a href="/jobmington/seeker/profile.php" class="flex items-center gap-2 pl-1 pr-3 py-1 rounded-lg transition jm-profile-chip" style="background: #ffffff; border: 1px solid #e8edf5;">
                            <img src="<?= htmlspecialchars($profileImg) ?>" class="w-8 h-8 rounded-md object-cover" style="<?= $ringStyle ?>">
                            <span class="text-sm font-bold text-white"><?= htmlspecialchars(explode(' ', $currentUser['full_name'])[0]) ?></span>
                            <i class="fas fa-chevron-down text-xs ml-1 text-white opacity-50"></i>
                        </a>
                        <!-- Desktop Dropdown Menu -->
                        <div class="absolute top-full right-0 mt-2 w-48 rounded-xl opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 z-50" style="background: white; border: 2px solid var(--color-ink); box-shadow: 4px 4px 0px 0px var(--color-ink);">
                            <div class="py-2">
                                <a href="/jobmington/seeker/profile.php" class="flex items-center gap-3 px-4 py-2 text-sm font-medium transition" style="color: var(--color-ink);" onmouseover="this.style.background='var(--color-canvas)'" onmouseout="this.style.background='transparent'">
                                    <i class="fas fa-user w-4 text-center" style="color: var(--color-primary);"></i> Profile
                                </a>
                                <a href="/jobmington/seeker/settings.php" class="flex items-center gap-3 px-4 py-2 text-sm font-medium transition" style="color: var(--color-ink);" onmouseover="this.style.background='var(--color-canvas)'" onmouseout="this.style.background='transparent'">
                                    <i class="fas fa-cog w-4 text-center" style="color: var(--color-primary);"></i> Settings
                                </a>
                                <div class="my-2" style="border-top: 1px solid var(--color-ink); opacity: 0.2;"></div>
                                <a href="/jobmington/auth/logout.php" class="flex items-center gap-3 px-4 py-2 text-sm font-medium transition" style="color: var(--color-accent);" onmouseover="this.style.background='var(--color-canvas)'" onmouseout="this.style.background='transparent'">
                                    <i class="fas fa-sign-out-alt w-4 text-center"></i> Log Out
                                </a>
                            </div>
                        </div>
                    </div>
                    <!-- Mobile: Just the avatar with colored ring -->
                    <a href="/jobmington/seeker/profile.php" class="md:hidden">
                        <img src="<?= htmlspecialchars($profileImg) ?>" class="w-9 h-9 rounded-md object-cover transition" style="<?= $ringStyle ?>">
                    </a>
                <?php else: ?>
                    <div class="hidden sm:flex items-center gap-2">
                        <a href="/jobmington/auth/login.php" class="text-sm font-bold px-3 transition jm-auth-link" style="color: #101828;" onmouseover="this.style.opacity='0.72'" onmouseout="this.style.opacity='1'">Log in</a>
                        <a href="/jobmington/auth/register.php" class="px-5 py-2.5 rounded-lg text-sm font-bold transition hover:-translate-y-0.5 jm-auth-cta" style="background: #073b2f; color: #ffffff; border: 0; box-shadow: none;">Get Started</a>
                    </div>
                <?php endif; ?>
                
                <?php if ($isLoggedIn): ?>
                    <!-- Notifications. Last before the menu button: on a phone the
                         bell and the hamburger are the two controls you reach for,
                         so they belong next to each other rather than with the
                         bell buried between the search and the avatar. -->
                    <?php require_once __DIR__ . '/notification_bell.php'; jm_notification_bell(); ?>
                <?php endif; ?>

                <button id="mobile-toggle" class="lg:hidden w-10 h-10 flex items-center justify-center rounded-lg transition jm-menu-button" style="background: #ffffff; border: 1px solid #e8edf5; color: #101828;">
                    <i class="fas fa-bars"></i>
                </button>
            </div>
        </div>
    </header>

    <div id="countryModal" class="fixed inset-0 z-[100] hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="fixed inset-0 transition-opacity" style="background: rgba(0, 23, 51, 0.7);" onclick="toggleCountryModal()"></div>

        <div class="fixed inset-0 z-10 w-screen overflow-y-auto">
            <div class="flex min-h-full items-center justify-center p-4">
                
                <div class="relative w-full max-w-2xl transform overflow-hidden rounded-2xl text-left shadow-2xl transition-all" style="background: white; border: 3px solid var(--color-ink); box-shadow: 8px 8px 0px 0px var(--color-ink);">
                    
                    <div class="px-6 py-4 flex justify-between items-center" style="border-bottom: 2px solid var(--color-ink); background: var(--color-canvas);">
                        <div>
                            <h3 class="text-lg font-bold tracking-tight flex items-center gap-2" style="color: var(--color-ink);"><i class="fas fa-globe-africa" style="color: var(--color-primary);"></i> Select Your Region</h3>
                            <p class="text-xs mt-0.5" style="color: var(--color-ink); opacity: 0.6;">Africa-focused job listings</p>
                        </div>
                        <button type="button" class="w-8 h-8 rounded-lg flex items-center justify-center transition" style="background: white; border: 2px solid var(--color-ink); color: var(--color-ink);" onclick="toggleCountryModal()">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>

                    <div class="px-6 py-6 max-h-[65vh] overflow-y-auto">
                        <?php 
                        $globalNetwork = class_exists('LocationDetector') ? LocationDetector::getGlobalNetwork() : []; 
                        ?>

                        <?php if (empty($globalNetwork)): ?>
                            <div class="text-center py-12">
                                <div class="w-16 h-16 rounded-xl flex items-center justify-center mx-auto mb-4" style="background: var(--color-canvas); border: 2px solid var(--color-ink);">
                                    <i class="fas fa-globe-africa text-2xl" style="color: var(--color-primary);"></i>
                                </div>
                                <h3 class="text-base font-bold mb-1" style="color: var(--color-ink);">No regions available</h3>
                                <p class="text-sm" style="color: var(--color-ink); opacity: 0.6;">Please check back later.</p>
                            </div>
                        <?php else: ?>
                            <div class="space-y-6">
                                <?php foreach ($globalNetwork as $region => $countries): ?>
                                    <div>
                                        <h4 class="text-[10px] uppercase font-bold tracking-wider mb-3 flex items-center gap-2" style="color: var(--color-ink); opacity: 0.5;">
                                            <?= htmlspecialchars($region) ?>
                                            <span class="flex-1 h-px" style="background: var(--color-ink); opacity: 0.2;"></span>
                                        </h4>
                                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                                            <?php foreach ($countries as $c): 
                                                $isActive = (strtolower($c['iso_code']) === strtolower($locationData['countryCode']));
                                                $isNigeria = strtolower($c['iso_code']) === 'ng';
                                            ?>
                                                <a href="?switch_country=<?= strtolower($c['iso_code']) ?>&name=<?= urlencode($c['name']) ?>" 
                                                   class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition group"
                                                   style="background: <?= $isActive ? 'var(--color-primary)' : ($isNigeria ? 'var(--color-canvas)' : 'white') ?>; border: 2px solid var(--color-ink); <?= $isActive ? 'box-shadow: 3px 3px 0px 0px var(--color-ink);' : '' ?>">
                                                    
                                                    <img src="https://flagcdn.com/w40/<?= strtolower($c['iso_code']) ?>.png" 
                                                         class="w-6 h-auto rounded-sm" style="border: 1px solid var(--color-ink);">
                                                    
                                                    <span class="text-sm font-bold flex-1 truncate" style="color: <?= $isActive ? 'white' : 'var(--color-ink)' ?>;">
                                                        <?= htmlspecialchars($c['name']) ?>
                                                        <?php if ($isNigeria && !$isActive): ?>
                                                        <span class="text-[9px] font-bold px-1.5 py-0.5 rounded ml-1" style="background: var(--color-accent); color: white;">HOME</span>
                                                        <?php endif; ?>
                                                    </span>
                                                    
                                                    <?php if ($isActive): ?>
                                                    <i class="fas fa-check text-xs" style="color: white;"></i>
                                                    <?php endif; ?>
                                                </a>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="px-6 py-4" style="border-top: 2px solid var(--color-ink); background: var(--color-canvas);">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2 text-xs" style="color: var(--color-ink); opacity: 0.6;">
                                <i class="fas fa-info-circle"></i>
                                <span>Your region affects job listings and currency</span>
                            </div>
                            <button onclick="toggleCountryModal()" class="px-4 py-2 rounded-lg text-sm font-bold transition" style="background: var(--color-primary); color: white; border: 2px solid var(--color-ink);">Done</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="mobile-backdrop" class="fixed inset-0 bg-slate-900/60 backdrop-blur-md z-[90] opacity-0 pointer-events-none transition-opacity duration-300 mobile-backdrop"></div>
    <div id="mobile-panel" class="fixed inset-y-0 right-0 w-[280px] shadow-2xl z-[100] mobile-panel flex flex-col max-h-screen" style="background: white; border-left: 2px solid var(--color-ink);">
        <div class="p-4 flex justify-between items-center flex-shrink-0" style="border-bottom: 2px solid var(--color-ink);">
            <span class="font-heading font-bold text-lg" style="color: var(--color-ink);">Menu</span>
            <button id="mobile-close" class="w-8 h-8 flex items-center justify-center rounded-lg transition" style="background: var(--color-canvas); border: 2px solid var(--color-ink); color: var(--color-ink);"><i class="fas fa-times"></i></button>
        </div>
        <div class="p-3 space-y-1 flex-1 overflow-y-auto">
            <?php foreach ($navItems as $item): ?>
                <a href="<?= $item['url'] ?>" class="flex items-center gap-3 p-2.5 rounded-lg font-medium text-sm transition" style="color: var(--color-ink);" onmouseover="this.style.background='var(--color-canvas)'" onmouseout="this.style.background='transparent'">
                    <div class="w-5 text-center" style="color: var(--color-primary);">
                        <?= Navigation::getIcon($item['icon']) ?>
                    </div>
                    <?= htmlspecialchars($item['label']) ?>
                </a>
            <?php endforeach; ?>
            
            <?php if ($isLoggedIn): ?>
            <!-- Logged-in only mobile links -->
            <div class="mt-3 pt-3 space-y-1" style="border-top: 1px solid var(--color-ink); opacity: 0.2;">
            </div>
            <div class="space-y-1">
                <span class="text-[10px] uppercase font-bold px-3" style="color: var(--color-ink); opacity: 0.5;">Quick Access</span>
                <a href="/jobmington/jobs/saved.php" class="flex items-center gap-3 p-2.5 rounded-lg font-medium text-sm transition" style="color: var(--color-ink);" onmouseover="this.style.background='var(--color-canvas)'" onmouseout="this.style.background='transparent'">
                    <i class="fas fa-bookmark w-5 text-center" style="color: var(--color-accent);"></i> Saved Jobs
                </a>
                <a href="/jobmington/auth/logout.php" class="flex items-center gap-3 p-2.5 rounded-lg font-medium text-sm transition" style="color: var(--color-accent);" onmouseover="this.style.background='var(--color-canvas)'" onmouseout="this.style.background='transparent'">
                    <i class="fas fa-sign-out-alt w-5 text-center"></i> Log Out
                </a>
            </div>
            <?php endif; ?>
            
            <?php if (!$isLoggedIn): ?>
                <div class="mt-3 pt-3 space-y-2" style="border-top: 1px solid var(--color-ink);">
                    <a href="/jobmington/auth/login.php" class="block w-full text-center py-2.5 font-bold rounded-lg text-sm transition" style="color: var(--color-ink); border: 2px solid var(--color-ink);" onmouseover="this.style.background='var(--color-canvas)'" onmouseout="this.style.background='transparent'">Log In</a>
                    <a href="/jobmington/auth/register.php" class="block w-full text-center py-2.5 font-bold rounded-lg text-sm transition" style="background: var(--color-primary); color: white; border: 2px solid var(--color-ink); box-shadow: 3px 3px 0px 0px var(--color-ink);">Sign Up Free</a>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Mobile Menu Footer -->
        <div class="mt-auto" style="border-top: 2px solid var(--color-ink); background: var(--color-canvas);">
            <!-- Quick Actions Grid -->
            <div class="p-3 grid grid-cols-3 gap-1">
                <a href="/jobmington/jobs/" class="flex flex-col items-center justify-center p-2 rounded-lg transition-colors group" onmouseover="this.style.background='white'" onmouseout="this.style.background='transparent'">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center mb-1" style="background: white; border: 1px solid var(--color-ink);">
                        <i class="fas fa-fire text-xs" style="color: var(--color-accent);"></i>
                    </div>
                    <span class="text-[9px] font-medium" style="color: var(--color-ink);">Hot Jobs</span>
                </a>
                <a href="/jobmington/jobs/search.php" class="flex flex-col items-center justify-center p-2 rounded-lg transition-colors group" onmouseover="this.style.background='white'" onmouseout="this.style.background='transparent'">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center mb-1" style="background: white; border: 1px solid var(--color-ink);">
                        <i class="fas fa-search text-xs" style="color: var(--color-primary);"></i>
                    </div>
                    <span class="text-[9px] font-medium" style="color: var(--color-ink);">Search</span>
                </a>
                <a href="/jobmington/employer/post-job.php" class="flex flex-col items-center justify-center p-2 rounded-lg transition-colors group" onmouseover="this.style.background='white'" onmouseout="this.style.background='transparent'">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center mb-1" style="background: white; border: 1px solid var(--color-ink);">
                        <i class="fas fa-plus text-xs" style="color: var(--color-primary);"></i>
                    </div>
                    <span class="text-[9px] font-medium" style="color: var(--color-ink);">Post</span>
                </a>
            </div>
            
            <!-- Mini Footer -->
            <div class="px-4 pb-4 pt-2 text-center">
                <div class="flex items-center justify-center gap-3 mb-2">
                    <a href="https://twitter.com/jobmington" class="w-7 h-7 rounded-lg flex items-center justify-center transition-colors" style="background: white; border: 1px solid var(--color-ink); color: var(--color-ink);" onmouseover="this.style.color='var(--color-primary)'" onmouseout="this.style.color='var(--color-ink)'">
                        <i class="fab fa-twitter text-xs"></i>
                    </a>
                    <a href="https://linkedin.com/company/jobmington" class="w-7 h-7 rounded-lg flex items-center justify-center transition-colors" style="background: white; border: 1px solid var(--color-ink); color: var(--color-ink);" onmouseover="this.style.color='var(--color-primary)'" onmouseout="this.style.color='var(--color-ink)'">
                        <i class="fab fa-linkedin-in text-xs"></i>
                    </a>
                    <a href="https://instagram.com/jobmington" class="w-7 h-7 rounded-lg flex items-center justify-center transition-colors" style="background: white; border: 1px solid var(--color-ink); color: var(--color-ink);" onmouseover="this.style.color='var(--color-accent)'" onmouseout="this.style.color='var(--color-ink)'">
                        <i class="fab fa-instagram text-xs"></i>
                    </a>
                </div>
                <p class="text-[10px] font-medium" style="color: var(--color-ink); opacity: 0.5;">© <?= date('Y') ?> Jobmington · Africa's Talent Hub</p>
            </div>
        </div>
    </div>

    <main id="main-content" class="flex-1 relative z-10 pt-[80px]">

    <script nonce="<?= $cspNonce ?>">
        // Scroll Effect
        const header = document.getElementById('main-header');
        window.addEventListener('scroll', () => {
            header.classList.toggle('scrolled', window.scrollY > 20);
        });

        // Mobile Menu
        const toggle = document.getElementById('mobile-toggle');
        const close = document.getElementById('mobile-close');
        const backdrop = document.getElementById('mobile-backdrop');
        const body = document.body;

        function toggleMenu() { body.classList.toggle('menu-open'); }
        [toggle, close, backdrop].forEach(el => el?.addEventListener('click', toggleMenu));

        // Country Modal
        function toggleCountryModal() {
            const modal = document.getElementById('countryModal');
            if (modal.classList.contains('hidden')) {
                modal.classList.remove('hidden');
                document.body.style.overflow = 'hidden';
            } else {
                modal.classList.add('hidden');
                document.body.style.overflow = 'auto';
            }
        }
    </script>
