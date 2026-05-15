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

final class Navigation {
    public static function getMainItems(): array {
        return [
            ['url' => '/jobmington/', 'label' => 'Home', 'icon' => 'home'],
            ['url' => '/jobmington/jobs', 'label' => 'Find Jobs', 'icon' => 'briefcase'],
            ['url' => '/jobmington/cv-builder/', 'label' => 'CV Builder', 'icon' => 'document'],
            ['url' => '/jobmington/employer/post-job.php', 'label' => 'Post a Job', 'icon' => 'plus'],
            ['url' => '/jobmington/employer/dashboard.php', 'label' => 'Employers', 'icon' => 'users'],
        ];
    }

    public static function getIcon(string $name, string $class = 'w-5 h-5'): string {
        $icons = [
            'home' => '<path d="M3 10.5L12 3l9 7.5"/><path d="M5 10v10h14V10"/><path d="M9 20v-6h6v6"/>',
            'briefcase' => '<path d="M16 7V5a2 2 0 00-2-2h-4a2 2 0 00-2 2v2"/><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M12 12v.01"/>',
            'plus' => '<path d="M12 5v14"/><path d="M5 12h14"/>',
            'graduation' => '<path d="M22 10l-10-5L2 10l10 5 10-5z"/><path d="M6 12v5c0 2 3 3 6 3s6-1 6-3v-5"/><path d="M22 10v6"/>',
            'users' => '<path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/>',
            'document' => '<path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><line x1="10" y1="9" x2="8" y2="9"/>',
            'blog' => '<path d="M4 19.5A2.5 2.5 0 016.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 014 19.5v-15A2.5 2.5 0 016.5 2z"/><line x1="8" y1="6" x2="16" y2="6"/><line x1="8" y1="10" x2="16" y2="10"/><line x1="8" y1="14" x2="12" y2="14"/>',
        ];
        return sprintf('<svg class="%s" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">%s</svg>', $class, $icons[$name] ?? $icons['briefcase']);
    }
}

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
    } catch (Throwable $e) {}
}

// Fallback insurance
if (empty($locationData['flagUrl'])) {
    $locationData['flagUrl'] = 'https://flagcdn.com/w80/ng.png';
}

$navItems = Navigation::getMainItems();
$currentUri = $_SERVER['REQUEST_URI'] ?? '/';

ob_start();
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#030303">
    <title><?= htmlspecialchars($headerConfig->title) ?></title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Premium Design System -->
    <link rel="stylesheet" href="/jobmington/assets/css/premium-design-system.css">
    <link rel="stylesheet" href="/jobmington/assets/css/minimal-jobmington.css?v=state-icons-1">
    
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
                    fontFamily: { sans: ['Inter', '-apple-system', 'BlinkMacSystemFont', 'sans-serif'] },
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
            font-family: 'Inter', sans-serif;
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
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; 
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
            font-family: 'Inter', sans-serif;
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
            font-family: 'Inter', monospace;
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
    </style>
</head>
<body class="antialiased" style="background: var(--color-canvas); color: var(--color-ink);">

    <!-- PRELOADER -->
    <div id="preloader">
        <div class="preloader-content">
            <div class="preloader-brand">
                <img src="/jobmington/assets/images/badge.png" alt="Badge" class="preloader-badge">
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
                        <img src="/jobmington/assets/images/badge.png" class="relative z-10 w-8 h-8 object-contain drop-shadow-sm">
                    </div>
                    <div class="hidden sm:flex flex-col">
                        <span class="font-bold tracking-tight text-xl jm-brand-text" style="font-family: 'Futura Cyrillic Demi', sans-serif; letter-spacing: -0.025em; color: #101828;">
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
