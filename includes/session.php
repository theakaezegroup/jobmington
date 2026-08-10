<?php
/**
 * JOBMINGTON - Session Management
 * Secure session handling with hijacking prevention
 */

// Prevent direct access
if (!defined('JOBMINGTON')) {
    die('Direct access not permitted');
}

class Session {

    private static $started = false;

    /**
     * Start secure session
     */
    public static function start(): void {
        if (self::$started) {
            return;
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            self::$started = true;
            if (!isset($_SESSION['_created'])) {
                $_SESSION['_created'] = time();
                $_SESSION['_user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
                $_SESSION['_ip'] = Security::getClientIP();
            }
            self::validate();
            return;
        }

        // Secure session settings
        ini_set('session.cookie_httponly', 1);
        /*
         * Lax, not Strict.
         *
         * Strict withholds the session cookie on every cross-site navigation,
         * including someone clicking a verification or password-reset link from
         * their email client. PHP then starts a fresh session and replaces the
         * cookie, so the visitor lands apparently signed out and any CSRF token
         * they already held no longer matches: "Security check failed".
         *
         * Lax still withholds the cookie on cross-site POSTs and subresource
         * requests, which are the vectors CSRF actually uses, and token checks
         * run on top of it. It is also what browsers default to when unset.
         */
        ini_set('session.cookie_samesite', 'Lax');
        ini_set('session.use_strict_mode', 1);
        ini_set('session.use_only_cookies', 1);
        ini_set('session.gc_maxlifetime', SESSION_LIFETIME);

        // Enable secure cookies only in production
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
            ini_set('session.cookie_secure', 1);
        }

        // Set session name
        session_name('JOBMINGTON_SESSION');

        // Start session
        session_start();

        self::$started = true;

        // Regenerate session ID periodically
        if (!isset($_SESSION['_created'])) {
            $_SESSION['_created'] = time();
            $_SESSION['_user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
            $_SESSION['_ip'] = Security::getClientIP();
        } elseif (time() - $_SESSION['_created'] > SESSION_LIFETIME / 2) {
            // Regenerate session ID every 15 minutes
            self::regenerate();
        }

        // Validate session
        self::validate();

        // Restore a persistent login if there is no session but a valid token.
        self::restoreFromRemember();
    }

    /**
     * Sign the visitor back in from a "remember me" cookie.
     *
     * Deliberately cheap for the common cases: it does nothing at all unless the
     * visitor is signed out AND actually presents the cookie, so guests and
     * signed-in users never pay for a query.
     */
    private static function restoreFromRemember(): void {
        if (self::isLoggedIn()) { return; }
        if (empty($_COOKIE['jm_remember'])) { return; }
        if (!function_exists('db')) { return; }   // page did not load the database layer

        try {
            require_once __DIR__ . '/remember.php';
            jm_remember_attempt(db());
        } catch (Throwable $e) {
            error_log('remember-me restore failed: ' . $e->getMessage());
        }
    }

    /**
     * Validate session against hijacking
     */
    private static function validate(): void {
        // Check user agent
        $currentAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        if (isset($_SESSION['_user_agent']) && $_SESSION['_user_agent'] !== $currentAgent) {
            self::destroy();
            header('Location: /jobmington/auth/login.php?error=session_invalid');
            exit;
        }

        // Optionally check IP (can cause issues with mobile users)
        // Uncomment if needed:
        // if (isset($_SESSION['_ip']) && $_SESSION['_ip'] !== Security::getClientIP()) {
        //     self::destroy();
        //     header('Location: /auth/login.php?error=session_invalid');
        //     exit;
        // }
    }

    /**
     * Regenerate session ID
     */
    public static function regenerate(): void {
        session_regenerate_id(true);
        $_SESSION['_created'] = time();
    }

    /**
     * Destroy session completely
     */
    public static function destroy(): void {
        $_SESSION = [];

        // Delete session cookie
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();
        self::$started = false;
    }

    /**
     * Set session value
     */
    public static function set(string $key, $value): void {
        $_SESSION[$key] = $value;
    }

    /**
     * Get session value
     */
    public static function get(string $key, $default = null) {
        return $_SESSION[$key] ?? $default;
    }

    /**
     * Check if session key exists
     */
    public static function has(string $key): bool {
        return isset($_SESSION[$key]);
    }

    /**
     * Remove session value
     */
    public static function remove(string $key): void {
        unset($_SESSION[$key]);
    }

    /**
     * Set flash message
     */
    public static function flash(string $type, string $message): void {
        $_SESSION['_flash'][$type][] = $message;
    }

    /**
     * Get and clear flash messages
     */
    public static function getFlash(?string $type = null): array {
        if ($type !== null) {
            $messages = $_SESSION['_flash'][$type] ?? [];
            unset($_SESSION['_flash'][$type]);
            return $messages;
        }

        $messages = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);
        return $messages;
    }

    /**
     * Check if flash messages exist
     */
    public static function hasFlash(?string $type = null): bool {
        if ($type !== null) {
            return !empty($_SESSION['_flash'][$type]);
        }
        return !empty($_SESSION['_flash']);
    }

    /**
     * Check if user is logged in
     */
    public static function isLoggedIn(): bool {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }

    /**
     * Get logged in user ID
     */
    public static function userId(): ?int {
        return $_SESSION['user_id'] ?? null;
    }

    /**
     * Get logged in user type
     */
    public static function userType(): ?string {
        return $_SESSION['user_type'] ?? null;
    }

    /**
     * Get logged in user data
     */
    public static function user(): ?array {
        if (!self::isLoggedIn()) {
            return null;
        }

        return [
            'user_id' => $_SESSION['user_id'],
            'full_name' => $_SESSION['full_name'] ?? '',
            'email' => $_SESSION['email'] ?? '',
            'user_type' => $_SESSION['user_type'] ?? '',
            'profile_image' => $_SESSION['profile_image'] ?? null
        ];
    }

    /**
     * Set user session after login
     */
    public static function login(array $user): void {
        // Regenerate session ID to prevent fixation
        self::regenerate();

        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['user_type'] = $user['user_type'];
        $_SESSION['profile_image'] = $user['profile_image'] ?? null;
        $_SESSION['logged_in_at'] = time();
    }

    /**
     * Require login - redirect if not logged in
     */
    /**
     * @param string $context Optional note explaining why sign-in is needed.
     *                        Shown on the auth pages so the visitor is not sent
     *                        to generic copy with no sign their click registered.
     * @param string $returnTo Override the return target. Defaults to the current
     *                        URL, which is right for GET pages; POST-only handlers
     *                        should pass the page a user can meaningfully return to.
     */
    public static function requireLogin(string $context = '', string $returnTo = ''): void {
        if (!self::isLoggedIn()) {
            $target = $returnTo !== '' ? $returnTo : ($_SERVER['REQUEST_URI'] ?? '/');
            if ($context !== '') {
                // Bound to its target so it can only ever appear for this journey.
                $_SESSION['auth_context']     = $context;
                $_SESSION['auth_context_for'] = $target;
            }
            $redirect = urlencode($target);
            header("Location: /jobmington/auth/login.php?redirect={$redirect}");
            exit;
        }
    }

    /**
     * Require specific user type
     */
    public static function requireRole(string $role): void {
        self::requireLogin();

        $userType = self::userType();

        // Admin can access everything
        if ($userType === USER_TYPE_ADMIN) {
            return;
        }

        if ($userType !== $role) {
            header('Location: /jobmington/errors/403.php');
            exit;
        }
    }

    /**
     * Require admin access
     */
    public static function requireAdmin(): void {
        self::requireLogin();

        if (self::userType() !== USER_TYPE_ADMIN && function_exists('db')) {
            try {
                $pdo = db();
                $stmt = $pdo->prepare('SELECT user_type FROM users WHERE user_id = ? AND is_active = 1 LIMIT 1');
                $stmt->execute([self::userId()]);
                $freshUserType = $stmt->fetchColumn();
                if ($freshUserType) {
                    $_SESSION['user_type'] = $freshUserType;
                }
            } catch (Throwable $e) {
                error_log('Admin role refresh error: ' . $e->getMessage());
            }
        }

        if (self::userType() !== USER_TYPE_ADMIN) {
            header('Location: /jobmington/errors/403.php');
            exit;
        }
    }

    /**
     * Check if user is admin
     */
    public static function isAdmin(): bool {
        return self::userType() === USER_TYPE_ADMIN;
    }

    /**
     * Check if user is employer
     */
    public static function isEmployer(): bool {
        return self::userType() === USER_TYPE_EMPLOYER || self::isAdmin();
    }

    /**
     * Check if user is seeker
     */
    public static function isSeeker(): bool {
        return self::userType() === USER_TYPE_SEEKER;
    }

    /**
     * Check if the logged-in user has verified their email
     */
    public static function isVerified(): bool {
        return !empty($_SESSION['is_verified']);
    }

    /**
     * Require verified email — redirect to verify page if not
     */
    public static function requireVerified(): void {
        self::requireLogin();
        if (!self::isVerified() && self::userType() !== USER_TYPE_ADMIN) {
            header('Location: /jobmington/auth/verify-email.php');
            exit;
        }
    }
}
