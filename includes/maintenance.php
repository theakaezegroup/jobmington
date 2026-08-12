<?php
/**
 * JOBMINGTON - site settings, and the maintenance gate.
 *
 * The maintenance toggle on admin/settings.php was decoration: the save
 * handler was a stub that reported success and wrote nothing, and no code
 * anywhere read the value. Turning it on did exactly nothing.
 *
 * The gate runs from Session::start(), which is the one call every page makes
 * before it emits anything and the point at which we know who is asking.
 */

if (!defined('JOBMINGTON')) {
    http_response_code(403);
    exit('Forbidden');
}

// The settings live in the database, so this file depends on db(). Declared
// here rather than trusting each caller: pricing.php does not load the
// database layer, and a guard that quietly skips when db() is missing is a
// maintenance mode with holes in it.
require_once __DIR__ . '/../config/database.php';

/**
 * Every stored setting, read once per request.
 */
function jm_settings(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    $cache = [];
    try {
        // No LIMIT. The certificate branding rows already filled the old
        // twenty-row window, so anything added later was invisible.
        foreach (db()->query("SELECT setting_key, setting_value FROM settings") as $row) {
            $cache[$row['setting_key']] = $row['setting_value'];
        }
    } catch (Throwable $e) {
        error_log('jm_settings: ' . $e->getMessage());
    }

    return $cache;
}

function jm_setting(string $key, $default = null)
{
    $all = jm_settings();
    return array_key_exists($key, $all) && $all[$key] !== null ? $all[$key] : $default;
}

/**
 * Write a setting. Returns false rather than throwing, so a failed save is
 * reported to the admin instead of white-screening the settings page.
 */
function jm_setting_save(string $key, ?string $value): bool
{
    try {
        db()->prepare("
            INSERT INTO settings (setting_key, setting_value, updated_at)
            VALUES (?, ?, NOW())
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()
        ")->execute([$key, $value]);
        return true;
    } catch (Throwable $e) {
        error_log('jm_setting_save(' . $key . '): ' . $e->getMessage());
        return false;
    }
}

function jm_maintenance_on(): bool
{
    return (string) jm_setting('maintenance_mode', '0') === '1';
}

/**
 * Paths that stay reachable while the site is down.
 *
 * Login has to work or turning maintenance off would mean editing the
 * database: an admin who is signed out could not sign in to reach the switch.
 * Assets are allowed because the maintenance page itself needs its font and
 * logo, and the cron and webhook endpoints keep running because the world
 * outside does not stop for a deploy.
 */
function jm_maintenance_allows(string $path): bool
{
    $allowed = [
        '/admin',
        '/auth/login',
        '/auth/logout',
        '/assets',
        '/uploads',
        '/api/webhooks',
        '/unsubscribe',
        '/payments',
        '/learn/verify-purchase',
        '/favicon.ico',
        '/service-worker.js',
        '/robots.txt',
    ];

    foreach ($allowed as $prefix) {
        if (stripos($path, $prefix) === 0) {
            return true;
        }
    }

    return false;
}

/**
 * Show the maintenance page and stop, unless this request is exempt.
 *
 * Answers 503 with Retry-After rather than 200: a 200 tells search engines the
 * thin page is the real one and invites them to drop the ranked pages behind
 * it, which is a slow and expensive mistake to undo.
 */
function jm_maintenance_guard(): void
{
    if (PHP_SAPI === 'cli') {
        return;
    }

    $isAdmin = !empty($_SESSION['user_id']) && ($_SESSION['user_type'] ?? '') === USER_TYPE_ADMIN;

    // An admin can see the holding page without taking the site down, so the
    // copy can be checked before anyone else reads it.
    if ($isAdmin && isset($_GET['preview_maintenance'])) {
        jm_maintenance_render(200);
    }

    if (!jm_maintenance_on()) {
        return;
    }

    // Admins keep working, otherwise the person who turned it on is locked out
    // alongside everyone else.
    if ($isAdmin) {
        return;
    }

    $path = strtok((string) ($_SERVER['REQUEST_URI'] ?? '/'), '?');
    $path = preg_replace('#^/jobmington#', '', $path) ?: '/';

    if (jm_maintenance_allows($path)) {
        return;
    }

    jm_maintenance_render(503);
}

/**
 * Render the holding page and stop.
 *
 * 503 with Retry-After for the real thing, rather than 200: a 200 tells search
 * engines this thin page is the real one and invites them to drop the ranked
 * pages behind it, which is slow and expensive to undo. The admin preview
 * answers 200 because nothing is actually down.
 */
function jm_maintenance_render(int $status): void
{
    http_response_code($status);
    if ($status === 503) {
        header('Retry-After: 3600');
    }
    header('Cache-Control: no-store, no-cache, must-revalidate');

    $jmMaintenanceMessage = (string) jm_setting('maintenance_message', 'We are making Jobmington better. Back shortly.');
    $jmMaintenanceBack    = (string) jm_setting('maintenance_back', '');

    require __DIR__ . '/../errors/maintenance.php';
    exit;
}
