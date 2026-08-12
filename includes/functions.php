<?php
/**
 * JOBMINGTON - Core System Functions
 * Architecture: Enterprise / Heavy Duty
 * Status: Collision-Proof (Safe for integration with security.php)
 */

// Prevent direct access
if (!defined('JOBMINGTON')) {
    die('Direct access not permitted');
}

/* ==========================================================================
   1. SECURITY & SANITIZATION (Collision-Proofed)
   ========================================================================== */

/**
 * Recursively sanitize input data
 * Checked: Only defines if not already in security.php
 */
if (!function_exists('clean')) {
    function clean($data) {
        if (is_array($data)) {
            return array_map('clean', $data);
        }
        return htmlspecialchars(trim($data ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

/**
 * Securely get cleaned POST data
 * Checked: Only defines if not already in security.php
 */
if (!function_exists('cleanPost')) {
    function cleanPost($key, $default = '') {
        if (isset($_POST[$key])) {
            return clean($_POST[$key]);
        }
        return $default;
    }
}

/**
 * Output Safe HTML (Short Echo)
 * Checked: Only defines if not already in security.php
 */
if (!function_exists('e')) {
    function e($string) {
        return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
    }
}

/**
 * Convert ISO country code to a compact country label
 * @param string $code Two-letter ISO country code (e.g., 'ng', 'us')
 * @return string Uppercase country code or empty string
 */
if (!function_exists('getCountryFlag')) {
    function getCountryFlag($code) {
        if (empty($code) || strlen($code) !== 2) return '';
        return strtoupper($code);
    }
}

/* ==========================================================================
   2. SYSTEM UTILITIES
   ========================================================================== */

/**
 * Redirect to URL
 */
if (!function_exists('redirect')) {
    function redirect(string $url): void {
        if (!headers_sent()) {
            header("Location: {$url}");
            exit;
        } else {
            echo "<script>window.location.href='{$url}';</script>";
            exit;
        }
    }
}

/**
 * Redirect back to previous page
 */
if (!function_exists('redirectBack')) {
    function redirectBack(): void {
        $referer = $_SERVER['HTTP_REFERER'] ?? '/';
        redirect($referer);
    }
}

/**
 * Author identity used across the community.
 *
 * Falls back to the brand badge rather than a letter tile, and shows a verified
 * tick for accounts flagged is_official so the platform voice is distinguishable
 * from a member with the same display name.
 */
if (!function_exists('jm_author_avatar')) {
    function jm_author_avatar(array $u, string $class = 'jm-topic-av'): string {
        $img = !empty($u['profile_image']) && function_exists('profileImage')
            ? profileImage($u['profile_image'])
            : (!empty($u['profile_image']) ? $u['profile_image'] : asset('images/badge.png?v=logo-8'));
        return '<img class="' . e($class) . '" src="' . e($img) . '" alt="">';
    }
}

if (!function_exists('jm_verified_tick')) {
    function jm_verified_tick(array $u, int $size = 14): string {
        if (empty($u['is_official'])) { return ''; }
        return '<img src="' . e(asset('images/badges/verified-blue.svg')) . '"'
             . ' width="' . $size . '" height="' . $size . '"'
             . ' style="display:inline-block;vertical-align:-2px;margin-left:4px;flex:0 0 auto;"'
             . ' alt="Verified" title="Official Jobmington account">';
    }
}

/**
 * Remember a signed-in visitor's first name so the sign-in page can greet them
 * by name on their next visit.
 *
 * Stores a display name and nothing else -- no user id, no email, nothing that
 * could identify an account or authenticate anyone. It is a greeting, not a
 * credential, so it is never trusted for anything but rendering a name.
 */
if (!function_exists('jm_remember_visitor')) {
    function jm_remember_visitor(string $fullName): void {
        if (headers_sent()) { return; }
        $first = jm_clean_display_name(explode(' ', trim($fullName))[0] ?? '');
        if ($first === '') { return; }

        setcookie('jm_visitor', $first, [
            'expires'  => time() + (86400 * 90),
            'path'     => '/',
            'secure'   => !empty($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}

if (!function_exists('jm_forget_visitor')) {
    function jm_forget_visitor(): void {
        unset($_COOKIE['jm_visitor']);
        if (headers_sent()) { return; }
        setcookie('jm_visitor', '', [
            'expires'  => time() - 3600,
            'path'     => '/',
            'secure'   => !empty($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}

/** First name of a previously signed-in visitor, or '' if this device has none. */
if (!function_exists('jm_returning_visitor')) {
    function jm_returning_visitor(): string {
        return jm_clean_display_name((string) ($_COOKIE['jm_visitor'] ?? ''));
    }
}

/** Letters, marks, spaces, apostrophes and hyphens only; capped at 32 chars. */
if (!function_exists('jm_clean_display_name')) {
    function jm_clean_display_name(string $name): string {
        $name = trim(preg_replace('/[^\p{L}\p{M}\'\- ]/u', '', $name) ?? '');
        // preg rather than mb_substr: mbstring is not installed on the server.
        return preg_match('/^.{0,32}/u', $name, $m) ? $m[0] : '';
    }
}

/**
 * Normalise a return target for comparison.
 *
 * The same destination can arrive spelled differently (/seeker/profile.php vs
 * /seeker/profile, with or without a trailing slash), so an auth note bound to
 * one spelling must still match the other.
 */
if (!function_exists('jm_norm_target')) {
    function jm_norm_target(string $target): string {
        $target = explode('#', $target)[0];
        $target = preg_replace('#\.php(?=$|\?)#', '', $target);
        return rtrim($target, '/');
    }
}

/**
 * Validate a post-authentication return target.
 *
 * The previous test was str_starts_with($target, '/jobmington/'), which only
 * matched the legacy prefix. Real production paths (/events/..., /wallet/...)
 * failed it, so every return target built from REQUEST_URI -- including the one
 * in Session::requireLogin(), used by 33 pages -- was silently discarded and the
 * user was dumped on the dashboard instead of where they were going.
 *
 * Accepts any same-site absolute path. Rejects anything that could leave the
 * site: absolute URLs, scheme-relative //host, backslash variants, and CR/LF
 * header injection.
 */
if (!function_exists('jm_safe_redirect_path')) {
    function jm_safe_redirect_path(string $target, string $fallback = ''): string {
        // Strip every control character, not just CR/LF: browsers remove raw tabs
        // and newlines while parsing a URL, so "/<TAB>/evil.com" would otherwise
        // collapse to "//evil.com" and leave the site.
        $target = trim(preg_replace('/[\x00-\x1F\x7F]/', '', $target));

        if ($target === '' || $target[0] !== '/') {
            return $fallback;
        }
        // Both //evil.com and /\evil.com resolve off-site in browsers.
        if (isset($target[1]) && ($target[1] === '/' || $target[1] === '\\')) {
            return $fallback;
        }
        // Guard against /javascript:... and similar scheme-ish targets.
        if (preg_match('#^/+[a-z][a-z0-9+.\-]*:#i', $target)) {
            return $fallback;
        }
        return $target;
    }
}

/**
 * Get current URL
 */
function currentUrl(): string {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    return $protocol . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
}

/**
 * Get base URL
 */
function baseUrl(string $path = ''): string {
    $root = defined('SITE_URL') ? SITE_URL : '/jobmington';
    return $root . '/' . ltrim($path, '/');
}

/**
 * Get asset URL
 */
function asset(string $path): string {
    $root = defined('ASSETS_URL') ? ASSETS_URL : '/jobmington/assets';
    return $root . '/' . ltrim($path, '/');
}

/**
 * Get upload URL
 */
function upload(string $path): string {
    $root = defined('UPLOADS_URL') ? UPLOADS_URL : '/jobmington/uploads';
    return $root . '/' . ltrim($path, '/');
}

/**
 * Render an accessible breadcrumb trail. Self-contained: it inlines its own CSS
 * once per request, so it renders correctly on any page regardless of which
 * header/stylesheet version is cached.
 *
 * @param array $crumbs  Ordered list of ['label' => ..., 'url' => ...]. The last
 *                       item is the current page (rendered as plain text even if
 *                       it has a url). 'Home' is prepended automatically.
 */
function jm_breadcrumbs(array $crumbs, bool $withHome = true): string {
    if (empty($crumbs)) {
        return '';
    }
    if ($withHome) {
        array_unshift($crumbs, ['label' => 'Home', 'url' => '/jobmington/']);
    }

    static $stylePrinted = false;
    $style = '';
    if (!$stylePrinted) {
        $stylePrinted = true;
        $style = '<style>'
            . '.jm-breadcrumb{margin:0 0 22px;}'
            . '.jm-breadcrumb ol{display:flex;flex-wrap:wrap;align-items:center;gap:7px;list-style:none;margin:0;padding:0;font-size:13px;line-height:1.4;}'
            . '.jm-breadcrumb li{display:inline-flex;align-items:center;gap:7px;}'
            . '.jm-breadcrumb li+li::before{content:"";width:6px;height:6px;border-top:1.6px solid #94a3b8;border-right:1.6px solid #94a3b8;transform:rotate(45deg);display:inline-block;}'
            . '.jm-breadcrumb a{color:#53667f;text-decoration:none;transition:color .15s;font-weight:600;}'
            . '.jm-breadcrumb a:hover{color:#0640a3;}'
            . '.jm-breadcrumb .current{color:#061426;font-weight:700;max-width:46ch;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}'
            . '</style>';
    }

    $count = count($crumbs);
    $items = '';
    foreach (array_values($crumbs) as $i => $crumb) {
        $label  = e((string) ($crumb['label'] ?? ''));
        $url    = (string) ($crumb['url'] ?? '');
        $isLast = ($i === $count - 1);
        $pos    = $i + 1;

        $inner = (!$isLast && $url !== '')
            ? '<a itemprop="item" href="' . e($url) . '"><span itemprop="name">' . $label . '</span></a>'
            : '<span class="current" itemprop="name" aria-current="page">' . $label . '</span>';

        $items .= '<li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">'
            . $inner
            . '<meta itemprop="position" content="' . $pos . '"></li>';
    }

    return $style
        . '<nav class="jm-breadcrumb" aria-label="Breadcrumb">'
        . '<ol itemscope itemtype="https://schema.org/BreadcrumbList">' . $items . '</ol>'
        . '</nav>';
}

/**
 * Return the first N block-level chunks of HTML content as a teaser. Falls back
 * to a word-bounded character cut for plain-text content. Used for the public,
 * crawlable preview of gated content (e.g. blog posts).
 */
function jm_content_teaser(string $html, int $paragraphs = 2, int $charFallback = 360): string {
    if (preg_match_all('#<(p|h2|h3|ul|ol|blockquote)\b[^>]*>.*?</\1>#is', $html, $m) && !empty($m[0])) {
        return implode('', array_slice($m[0], 0, $paragraphs));
    }
    $text = trim(preg_replace('/\s+/', ' ', strip_tags($html)));
    if (function_exists('mb_strlen') ? mb_strlen($text) <= $charFallback : strlen($text) <= $charFallback) {
        return '<p>' . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . '</p>';
    }
    $cut  = mb_substr($text, 0, $charFallback);
    $sp   = mb_strrpos($cut, ' ');
    if ($sp) $cut = mb_substr($cut, 0, $sp);
    return '<p>' . htmlspecialchars($cut, ENT_QUOTES, 'UTF-8') . '&hellip;</p>';
}

/**
 * A members-only sign-in wall. Self-contained (prints CSS once). Use after a
 * teaser to gate the rest of the content while keeping the page crawlable.
 */
function jm_signin_wall(string $heading = 'This is for members', string $sub = 'Create a free account or sign in to keep reading.', string $redirect = ''): string {
    static $stylePrinted = false;
    $style = '';
    if (!$stylePrinted) {
        $stylePrinted = true;
        $style = '<style>'
            . '.jm-wall{position:relative;margin-top:-90px;padding-top:90px;}'
            . '.jm-wall-fade{position:absolute;top:0;left:0;right:0;height:90px;background:linear-gradient(to bottom,rgba(255,255,255,0),#ffffff);pointer-events:none;}'
            . '.jm-wall-card{border:1px solid #e4eaf3;border-radius:16px;background:#fff;box-shadow:0 12px 30px rgba(6,20,38,.08);padding:30px 26px;text-align:center;max-width:520px;margin:0 auto;}'
            . '.jm-wall-ico{width:46px;height:46px;border-radius:12px;background:#eef5ff;color:#0640a3;display:grid;place-items:center;margin:0 auto 14px;}'
            . '.jm-wall-card h3{font-size:20px;font-weight:800;color:#061426;margin:0 0 8px;}'
            . '.jm-wall-card p{font-size:14.5px;color:#53667f;margin:0 0 20px;line-height:1.6;}'
            . '.jm-wall-actions{display:flex;gap:10px;justify-content:center;flex-wrap:wrap;}'
            . '.jm-wall-btn{display:inline-flex;align-items:center;justify-content:center;border-radius:10px;padding:12px 22px;font-weight:800;font-size:14px;text-decoration:none;}'
            . '.jm-wall-btn.primary{background:#0640a3;color:#fff;}.jm-wall-btn.primary:hover{background:#052f78;}'
            . '.jm-wall-btn.ghost{background:#fff;color:#0640a3;border:1px solid #d8e4f4;}'
            . '</style>';
    }
    $r = $redirect !== '' ? '?redirect=' . urlencode($redirect) : '';
    return $style
        . '<div class="jm-wall"><div class="jm-wall-fade"></div><div class="jm-wall-card">'
        . '<div class="jm-wall-ico"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg></div>'
        . '<h3>' . htmlspecialchars($heading, ENT_QUOTES, 'UTF-8') . '</h3>'
        . '<p>' . htmlspecialchars($sub, ENT_QUOTES, 'UTF-8') . '</p>'
        . '<div class="jm-wall-actions">'
        . '<a class="jm-wall-btn primary" href="/jobmington/auth/register.php' . $r . '">Create free account</a>'
        . '<a class="jm-wall-btn ghost" href="/jobmington/auth/login.php' . $r . '">Sign in</a>'
        . '</div></div></div>';
}

/* ==========================================================================
   3. FORMATTING & DISPLAY
   ========================================================================== */

/**
 * Format date
 */
function formatDate(?string $date, string $format = 'M d, Y'): string {
    if (empty($date)) return '';
    return date($format, strtotime($date));
}

/**
 * Format datetime
 */
function formatDateTime(?string $datetime, string $format = 'M d, Y h:i A'): string {
    if (empty($datetime)) return '';
    return date($format, strtotime($datetime));
}

/**
 * Time ago format
 */
function timeAgo(string $datetime): string {
    $timestamp = strtotime($datetime);
    $diff = time() - $timestamp;
    
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) {
        $mins = floor($diff / 60);
        return $mins . ' minute' . ($mins > 1 ? 's' : '') . ' ago';
    }
    if ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    }
    if ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    }
    if ($diff < 2592000) {
        $weeks = floor($diff / 604800);
        return $weeks . ' week' . ($weeks > 1 ? 's' : '') . ' ago';
    }
    if ($diff < 31536000) {
        $months = floor($diff / 2592000);
        return $months . ' month' . ($months > 1 ? 's' : '') . ' ago';
    }
    $years = floor($diff / 31536000);
    return $years . ' year' . ($years > 1 ? 's' : '') . ' ago';
}

/**
 * Format currency
 */
function formatCurrency(float $amount, ?string $symbol = null): string {
    $symbol = $symbol ?? (class_exists('LocationDetector') ? LocationDetector::getCurrencySymbol() : '$');
    return $symbol . number_format($amount, 0);
}

/**
 * Format salary range
 */
function formatSalaryRange(?float $min, ?float $max, ?string $symbol = null): string {
    if ($min === null && $max === null) return 'Negotiable';
    
    $symbol = $symbol ?? (class_exists('LocationDetector') ? LocationDetector::getCurrencySymbol() : '$');
    
    if ($min !== null && $max !== null) {
        return $symbol . number_format($min, 0) . ' - ' . $symbol . number_format($max, 0);
    } elseif ($min !== null) {
        return 'From ' . $symbol . number_format($min, 0);
    } else {
        return 'Up to ' . $symbol . number_format($max, 0);
    }
}

/**
 * Truncate text
 */
function truncate(string $text, int $length = 100, string $suffix = '...'): string {
    if (strlen($text) <= $length) return $text;
    return substr($text, 0, $length - strlen($suffix)) . $suffix;
}

/**
 * Strip HTML and truncate
 */
function excerpt(?string $html, int $length = 150): string {
    if (empty($html)) return '';
    $text = strip_tags($html);
    return truncate($text, $length);
}

/* ==========================================================================
   4. DATA HELPERS
   ========================================================================== */

function pluralize(int $count, string $singular, ?string $plural = null): string {
    $plural = $plural ?? $singular . 's';
    return $count === 1 ? $singular : $plural;
}

function formatCount(int $count, string $singular, ?string $plural = null): string {
    return number_format($count) . ' ' . pluralize($count, $singular, $plural);
}

function getInitials(string $name): string {
    $words = explode(' ', trim($name));
    $initials = '';
    foreach ($words as $word) {
        if (!empty($word)) $initials .= strtoupper($word[0]);
    }
    return substr($initials, 0, 2);
}

function stringToColor(string $string): string {
    $hash = md5($string);
    return '#' . substr($hash, 0, 6);
}

/* ==========================================================================
   5. REQUEST HANDLING
   ========================================================================== */

if (!function_exists('isPost')) {
    function isPost(): bool {
        return $_SERVER['REQUEST_METHOD'] === 'POST';
    }
}

if (!function_exists('isGet')) {
    function isGet(): bool {
        return $_SERVER['REQUEST_METHOD'] === 'GET';
    }
}

if (!function_exists('post')) {
    function post(string $key, $default = null) {
        return $_POST[$key] ?? $default;
    }
}

if (!function_exists('get')) {
    function get(string $key, $default = null) {
        return $_GET[$key] ?? $default;
    }
}

if (!function_exists('jm_setting_get')) {
    /**
     * Read a value from the key/value settings table (cached per request).
     */
    function jm_setting_get(string $key, $default = null) {
        static $cache = null;
        if ($cache === null) {
            $cache = [];
            try {
                foreach (db()->query("SELECT setting_key, setting_value FROM settings") as $row) {
                    $cache[$row['setting_key']] = $row['setting_value'];
                }
            } catch (Throwable $e) {
                // settings table missing — fall back to defaults
            }
        }
        $v = $cache[$key] ?? null;
        return ($v === null || $v === '') ? $default : $v;
    }
}

if (!function_exists('jm_setting_set')) {
    /**
     * Upsert a settings value. Pass null/'' to clear.
     */
    function jm_setting_set(string $key, $value): bool {
        try {
            $stmt = db()->prepare(
                "INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = CURRENT_TIMESTAMP"
            );
            return $stmt->execute([$key, ($value === '' ? null : $value)]);
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('jsonResponse')) {
    function jsonResponse(array $data, int $statusCode = 200): void {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
}

function jsonSuccess($data = null, string $message = 'Success'): void {
    jsonResponse(['success' => true, 'message' => $message, 'data' => $data]);
}

function jsonError(string $message, int $statusCode = 400, array $errors = []): void {
    jsonResponse(['success' => false, 'message' => $message, 'errors' => $errors], $statusCode);
}

/* ==========================================================================
   6. FILE & NOTIFICATION LOGIC
   ========================================================================== */

function profileImage(?string $image): string {
    if (empty($image)) return asset('images/badge.png');
    if (strpos($image, 'http') === 0) return $image;
    // Handle legacy 'assets/' paths - return as asset instead
    if (strpos($image, 'assets/') === 0) return '/' . ltrim(str_replace('assets/', 'jobmington/assets/', $image), '/');
    // Handle default avatar string
    if ($image === 'assets/images/default-avatar.png' || $image === 'default-avatar.png') {
        return asset('images/badge.png');
    }
    return upload('avatars/' . $image);
}

function companyLogo(?string $logo): string {
    if (empty($logo)) return asset('images/default-company.png');
    if (strpos($logo, 'http') === 0) return $logo;
    return upload($logo);
}

function uploadExists(string $path): bool {
    return file_exists(UPLOADS_PATH . '/' . $path);
}

if (!function_exists('getSeeds')) {
    function getSeeds(int $userId): float {
        $pdo = db();

        $stmt = $pdo->prepare("SELECT balance FROM wallets WHERE user_id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $balance = $stmt->fetchColumn();

        if ($balance === false) {
            $pdo->prepare("INSERT IGNORE INTO wallets (user_id, balance, lifetime_earned, lifetime_spent) VALUES (?, 0, 0, 0)")
                ->execute([$userId]);
            return 0.0;
        }

        return (float) $balance;
    }
}

if (!function_exists('deductSeeds')) {
    function deductSeeds(int $userId, float $amount, string $description = 'Seeds spent'): bool {
        if ($amount <= 0) {
            return true;
        }

        $pdo = db();
        $ownsTransaction = !$pdo->inTransaction();

        try {
            if ($ownsTransaction) {
                $pdo->beginTransaction();
            }

            $stmt = $pdo->prepare("
                UPDATE wallets
                SET balance = balance - ?, lifetime_spent = lifetime_spent + ?
                WHERE user_id = ? AND balance >= ?
            ");
            $stmt->execute([$amount, $amount, $userId, $amount]);

            if ($stmt->rowCount() === 0) {
                throw new RuntimeException('Insufficient Seeds balance.');
            }

            $balance = getSeeds($userId);
            $stmt = $pdo->prepare("
                INSERT INTO seed_transactions (user_id, type, amount, balance_after, source, description, created_at)
                VALUES (?, 'spend', ?, ?, 'passport', ?, NOW())
            ");
            $stmt->execute([$userId, $amount, $balance, $description]);

            if ($ownsTransaction) {
                $pdo->commit();
            }

            return true;
        } catch (Throwable $e) {
            if ($ownsTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}

function getUnreadNotificationCount(?int $userId = null): int {
    $userId = $userId ?? Session::userId();
    if (!$userId) return 0;
    
    $pdo = db();
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$userId]);
    return (int) $stmt->fetchColumn();
}

if (!function_exists('sendNotification')) {
    function sendNotification(int $userId, string $type, string $title, string $message = '', ?string $link = null): bool {
        $pdo = db();

        try {
            $stmt = $pdo->prepare("
                INSERT INTO notifications (user_id, type, title, message, link, is_read, created_at)
                VALUES (?, ?, ?, ?, ?, 0, NOW())
            ");
            return $stmt->execute([$userId, $type, $title, $message, $link]);
        } catch (Throwable $e) {
            error_log('Notification error: ' . $e->getMessage());
            return false;
        }
    }
}

function renderPagination(array $pagination, string $baseUrl): string {
    if ($pagination['total_pages'] <= 1) return '';
    $separator = str_contains($baseUrl, '?') ? '&' : '?';
    
    $html = '<nav class="flex justify-center mt-8"><ul class="flex flex-wrap justify-center gap-2">';
    
    // Previous
    if ($pagination['has_previous']) {
        $prevUrl = $baseUrl . $separator . 'page=' . $pagination['previous_page'];
        $html .= '<li><a href="' . e($prevUrl) . '" class="px-4 py-2 rounded-lg font-bold transition hover:translate-y-[-2px]" style="background: white; border: 2px solid var(--color-ink); color: var(--color-ink);"><i class="fas fa-chevron-left"></i></a></li>';
    }
    
    // Page numbers
    for ($i = 1; $i <= $pagination['total_pages']; $i++) {
        if ($i === 1 || $i === $pagination['total_pages'] || 
           ($i >= $pagination['current_page'] - 2 && $i <= $pagination['current_page'] + 2)) {
            
            $pageUrl = $baseUrl . $separator . 'page=' . $i;
            if ($i === $pagination['current_page']) {
                // Active page - Royal Blue background
                $html .= '<li><a href="' . e($pageUrl) . '" class="px-4 py-2 rounded-lg font-bold transition" style="background: var(--color-primary); color: white; border: 2px solid var(--color-ink); box-shadow: 2px 2px 0px 0px var(--color-ink);">' . $i . '</a></li>';
            } else {
                // Inactive pages - White background with Navy border
                $html .= '<li><a href="' . e($pageUrl) . '" class="px-4 py-2 rounded-lg font-bold transition hover:translate-y-[-2px]" style="background: white; border: 2px solid var(--color-ink); color: var(--color-ink);">' . $i . '</a></li>';
            }
        } elseif ($i === 2 || $i === $pagination['total_pages'] - 1) {
            $html .= '<li><span class="px-2 py-2 font-bold" style="color: var(--color-ink); opacity: 0.5;">...</span></li>';
        }
    }
    
    // Next
    if ($pagination['has_next']) {
        $nextUrl = $baseUrl . $separator . 'page=' . $pagination['next_page'];
        $html .= '<li><a href="' . e($nextUrl) . '" class="px-4 py-2 rounded-lg font-bold transition hover:translate-y-[-2px]" style="background: white; border: 2px solid var(--color-ink); color: var(--color-ink);"><i class="fas fa-chevron-right"></i></a></li>';
    }
    
    $html .= '</ul></nav>';
    return $html;

}
/**
 * Calculate Pagination Logic
 * (The missing piece for jobs/index.php)
 */
if (!function_exists('paginate')) {
    function paginate(int $total, int $perPage, int $currentPage = 1): array {
        $totalPages = ceil($total / $perPage);
        $currentPage = max(1, min($currentPage, $totalPages));
        $offset = ($currentPage - 1) * $perPage;
        
        return [
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $currentPage,
            'total_pages' => $totalPages,
            'offset' => $offset,
            'has_previous' => $currentPage > 1,
            'has_next' => $currentPage < $totalPages,
            'previous_page' => max(1, $currentPage - 1),
            'next_page' => min($totalPages, $currentPage + 1)
        ];
    }
}

/**
 * Delete upload file safely
 */
if (!function_exists('deleteUpload')) {
    function deleteUpload(string $path): bool {
        $fullPath = UPLOADS_PATH . '/' . ltrim($path, '/');
        if (file_exists($fullPath) && is_file($fullPath)) {
            return unlink($fullPath);
        }
        return false;
    }
}

if (!function_exists('jm_minimal_footer')) {
    function jm_minimal_footer(): void {
        $year = date('Y');
        ?>
        <footer class="jm-footer" aria-label="Site footer">
            <div class="jm-footer-inner">
                <div class="jm-footer-brand">
                    <a class="jm-logo" href="/jobmington/">
                        <img src="/jobmington/assets/images/badge.png?v=logo-8" alt="">
                        <span>Jobmington</span>
                    </a>
                    <p>Simple hiring for African talent. Find jobs, apply quickly, and manage hiring without the noise.</p>
                </div>

                <nav class="jm-footer-links" aria-label="Job seeker links">
                    <h2>Job Seekers</h2>
                    <a href="/jobmington/jobs/">Find jobs</a>
                    <a href="/jobmington/cv-builder/">CV Builder</a>
                    <a href="/jobmington/jobs/search.php">Search jobs</a>
                    <a href="/jobmington/jobs/?type=Remote">Remote jobs</a>
                    <a href="/jobmington/auth/register.php">Create account</a>
                </nav>

                <nav class="jm-footer-links" aria-label="Employer links">
                    <h2>Employers</h2>
                    <a href="/jobmington/employer/">Hire talent</a>
                    <a href="/jobmington/employer/post-job.php">Post a job</a>
                    <a href="/jobmington/employer/dashboard.php">Employer dashboard</a>
                    <a href="/jobmington/pricing.php">Pricing</a>
                </nav>

                <nav class="jm-footer-links" aria-label="Learn links">
                    <h2>Learn</h2>
                    <a href="/jobmington/learn/">Online courses</a>
                    <a href="/jobmington/ebooks/">Free ebooks &amp; guides</a>
                    <a href="/jobmington/events/">Events &amp; webinars</a>
                    <a href="/jobmington/blog/">Career blog</a>
                    <a href="/jobmington/community/">Community forum</a>
                    <a href="/jobmington/tools/">Career tools</a>
                </nav>

                <nav class="jm-footer-links" aria-label="Company links">
                    <h2>Company</h2>
                    <a href="/jobmington/contact.php">Contact</a>
                    <a href="/jobmington/faq.php">FAQ</a>
                    <a href="/jobmington/privacy-policy.php">Privacy policy</a>
                    <a href="/jobmington/terms-of-service.php">Terms of service</a>
                </nav>

                <nav class="jm-footer-links" aria-label="Popular job searches">
                    <h2>Popular Searches</h2>
                    <a href="/jobmington/jobs/search.php?q=developer">Developer jobs</a>
                    <a href="/jobmington/jobs/search.php?q=marketing">Marketing jobs</a>
                    <a href="/jobmington/jobs/search.php?q=designer">Design jobs</a>
                    <a href="/jobmington/jobs/search.php?q=operations">Operations jobs</a>
                </nav>
            </div>

            <div class="jm-footer-bottom">
                <span>&copy; <?= $year ?> Jobmington</span>
                <span>Lagos, Nigeria</span>
            </div>
        </footer>
        <script>
        (() => {
            const body = document.body;
            const headers = document.querySelectorAll('.jm-header');
            const pathBase = window.location.pathname.toLowerCase().startsWith('/jobmington/') ? '/jobmington' : '';

            if (!document.querySelector('link[rel="manifest"]')) {
                const manifest = document.createElement('link');
                manifest.rel = 'manifest';
                manifest.href = `${pathBase}/manifest.json?v=brand-10`;
                document.head.appendChild(manifest);
            }

            if (!document.querySelector('link[rel="apple-touch-icon"]')) {
                const icon = document.createElement('link');
                icon.rel = 'apple-touch-icon';
                icon.href = `${pathBase}/assets/images/pwa-icon-192.png?v=brand-10`;
                document.head.appendChild(icon);
            }

            if (!document.querySelector('link[rel="icon"]')) {
                const favicon = document.createElement('link');
                favicon.rel = 'icon';
                favicon.type = 'image/png';
                favicon.href = `${pathBase}/assets/images/favicon.png?v=fav-1`;
                document.head.appendChild(favicon);
            }

            let nav = null;
            let toggle = null;

            headers.forEach((header, index) => {
                const headerNav = header.querySelector('.jm-nav');
                if (!headerNav) return;

                headerNav.id = headerNav.id || `jm-mobile-nav-${index + 1}`;
                let button = header.querySelector('.jm-mobile-nav-toggle');

                if (!button) {
                    button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'jm-mobile-nav-toggle';
                    button.innerHTML = '<span></span>';
                    header.insertBefore(button, headerNav);
                }

                button.setAttribute('aria-label', 'Open menu');
                button.setAttribute('aria-expanded', 'false');
                button.setAttribute('aria-controls', headerNav.id);

                if (!nav) {
                    nav = headerNav;
                    toggle = button;
                }
            });

            if (!nav || !toggle) return;

            const backdrop = document.createElement('div');
            backdrop.className = 'jm-nav-backdrop';
            backdrop.setAttribute('aria-hidden', 'true');
            body.appendChild(backdrop);
            body.classList.add('jm-mobile-nav-ready');

            const setOpen = (open) => {
                body.classList.toggle('jm-nav-open', open);
                toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
                toggle.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
            };

            toggle.addEventListener('click', () => setOpen(!body.classList.contains('jm-nav-open')));
            backdrop.addEventListener('click', () => setOpen(false));
            nav.querySelectorAll('a').forEach((link) => link.addEventListener('click', () => setOpen(false)));
            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') setOpen(false);
            });

            if ('serviceWorker' in navigator) {
                window.addEventListener('load', () => {
                    navigator.serviceWorker.register(`${pathBase}/service-worker.js?v=brand-16`).catch(() => {});
                });
            }
        })();
        </script>
        <?php
        require_once __DIR__ . '/feedback.php';
    }
}

/**
 * Shorthand for the activity trail. Same thing as Security::logActivity, named
 * to match the other jm_ helpers so call sites read consistently.
 */
if (!function_exists('jm_log_activity')) {
    function jm_log_activity(?int $userId, string $action, ?string $details = null): void {
        Security::logActivity($userId, $action, $details);
    }
}

/**
 * Record that someone looked at a piece of content.
 *
 * Deduplicated per person per item per hour, so a reader who refreshes or
 * comes back to a half-finished article does not appear thirty times. Signed
 * out readers are deduplicated on IP for the same reason.
 *
 * Never throws: a page must not fail because its analytics did.
 */
if (!function_exists('jm_record_view')) {
    function jm_record_view(string $contentType, int $contentId): void {
        if ($contentId <= 0) {
            return;
        }

        try {
            $pdo = db();
            $userId = Session::isLoggedIn() ? (int) Session::userId() : null;
            $ip = Security::getClientIP();

            $recent = $pdo->prepare("
                SELECT view_id FROM content_views
                WHERE content_type = ? AND content_id = ?
                  AND (" . ($userId ? "user_id = ?" : "user_id IS NULL AND ip_address = ?") . ")
                  AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
                LIMIT 1
            ");
            $recent->execute([$contentType, $contentId, $userId ?: $ip]);
            if ($recent->fetchColumn()) {
                return;
            }

            $pdo->prepare("
                INSERT INTO content_views (content_type, content_id, user_id, ip_address, created_at)
                VALUES (?, ?, ?, ?, NOW())
            ")->execute([$contentType, $contentId, $userId, $ip]);
        } catch (Throwable $e) {
            error_log('jm_record_view(' . $contentType . '): ' . $e->getMessage());
        }
    }
}

require_once __DIR__ . '/illustration-states.php';
?>
