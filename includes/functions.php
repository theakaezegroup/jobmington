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
                        <img src="/jobmington/assets/images/badge.png?v=logo-7" alt="">
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
                    navigator.serviceWorker.register(`${pathBase}/service-worker.js?v=brand-10`).catch(() => {});
                });
            }
        })();
        </script>
        <?php
    }
}

require_once __DIR__ . '/illustration-states.php';
?>
