<?php
/**
 * JOBMINGTON - Persistent login ("remember me").
 *
 * Selector/validator scheme:
 *   cookie  = "<selector>:<validator>"
 *   database stores the selector in the clear and only a SHA-256 of the validator
 *
 * The selector is a public lookup key, so a single indexed read finds the row
 * without comparing secrets across rows. The validator never touches storage in
 * a usable form, so a leaked database cannot be replayed as a login.
 *
 * The validator is rotated on every use. If a selector is found but its
 * validator does not match, the cookie is a stale copy or a theft, so every
 * token for that user is revoked.
 */
if (!defined('JOBMINGTON')) { exit; }

const JM_REMEMBER_COOKIE = 'jm_remember';
const JM_REMEMBER_DAYS   = 30;

/** Write the cookie holding selector:validator. */
function jm_remember_set_cookie(string $value, int $expires): void {
    if (headers_sent()) { return; }
    setcookie(JM_REMEMBER_COOKIE, $value, [
        'expires'  => $expires,
        'path'     => '/',
        'secure'   => !empty($_SERVER['HTTPS']),
        'httponly' => true,   // never needs to be readable from JavaScript
        'samesite' => 'Lax',
    ]);
}

/** Issue a fresh persistent-login token for a user. */
function jm_remember_issue(PDO $pdo, int $userId): void {
    try {
        $selector  = bin2hex(random_bytes(16));
        $validator = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', time() + (86400 * JM_REMEMBER_DAYS));

        $pdo->prepare("
            INSERT INTO auth_tokens (user_id, selector, validator_hash, expires_at, user_agent)
            VALUES (?, ?, ?, ?, ?)
        ")->execute([
            $userId,
            $selector,
            hash('sha256', $validator),
            $expiresAt,
            substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
        ]);

        jm_remember_set_cookie($selector . ':' . $validator, time() + (86400 * JM_REMEMBER_DAYS));
    } catch (Throwable $e) {
        error_log('remember-me issue failed: ' . $e->getMessage());
    }
}

/** Revoke the current device's token, or every token for the user. */
function jm_remember_revoke(PDO $pdo, ?int $userId = null, bool $allDevices = false): void {
    try {
        if ($allDevices && $userId !== null) {
            $pdo->prepare("DELETE FROM auth_tokens WHERE user_id = ?")->execute([$userId]);
        } else {
            $raw = (string) ($_COOKIE[JM_REMEMBER_COOKIE] ?? '');
            if (strpos($raw, ':') !== false) {
                [$selector] = explode(':', $raw, 2);
                $pdo->prepare("DELETE FROM auth_tokens WHERE selector = ?")->execute([$selector]);
            }
        }
    } catch (Throwable $e) {
        error_log('remember-me revoke failed: ' . $e->getMessage());
    }

    unset($_COOKIE[JM_REMEMBER_COOKIE]);
    jm_remember_set_cookie('', time() - 3600);
}

/**
 * Try to restore a session from the cookie. Returns true if the user was
 * signed in. Rotates the validator on success so a captured cookie is only
 * ever usable once.
 */
function jm_remember_attempt(PDO $pdo): bool {
    $raw = (string) ($_COOKIE[JM_REMEMBER_COOKIE] ?? '');
    if ($raw === '' || strpos($raw, ':') === false) { return false; }

    [$selector, $validator] = explode(':', $raw, 2);
    if (strlen($selector) !== 32 || $validator === '') {
        jm_remember_revoke($pdo);
        return false;
    }

    try {
        $stmt = $pdo->prepare("
            SELECT t.token_id, t.user_id, t.validator_hash, t.expires_at,
                   u.user_type, u.full_name, u.first_name, u.last_name, u.email,
                   u.profile_image, u.is_verified, u.is_active
            FROM auth_tokens t
            JOIN users u ON u.user_id = t.user_id
            WHERE t.selector = ?
            LIMIT 1
        ");
        $stmt->execute([$selector]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            jm_remember_revoke($pdo);
            return false;
        }

        // Expired token: drop it and fall back to normal sign-in.
        if (strtotime($row['expires_at']) < time()) {
            $pdo->prepare("DELETE FROM auth_tokens WHERE token_id = ?")->execute([$row['token_id']]);
            jm_remember_revoke($pdo);
            return false;
        }

        // Constant-time comparison; a mismatch means a stale or stolen cookie,
        // so every token for that user is revoked rather than just this one.
        if (!hash_equals((string) $row['validator_hash'], hash('sha256', $validator))) {
            $pdo->prepare("DELETE FROM auth_tokens WHERE user_id = ?")->execute([(int) $row['user_id']]);
            jm_remember_revoke($pdo);
            error_log('remember-me validator mismatch for user ' . (int) $row['user_id'] . '; all tokens revoked');
            return false;
        }

        // Mirror the gates enforced by a normal sign-in.
        if (!$row['is_active'] || (!$row['is_verified'] && $row['user_type'] !== USER_TYPE_ADMIN)) {
            jm_remember_revoke($pdo);
            return false;
        }

        // Rotate: same selector, new secret, so a captured cookie dies on use.
        $newValidator = bin2hex(random_bytes(32));
        $newExpiry    = time() + (86400 * JM_REMEMBER_DAYS);
        $pdo->prepare("
            UPDATE auth_tokens
            SET validator_hash = ?, expires_at = ?, last_used_at = NOW()
            WHERE token_id = ?
        ")->execute([
            hash('sha256', $newValidator),
            date('Y-m-d H:i:s', $newExpiry),
            $row['token_id'],
        ]);
        jm_remember_set_cookie($selector . ':' . $newValidator, $newExpiry);

        // New session id: a persistent cookie must not resurrect a fixated session.
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }

        $_SESSION['user_id']       = (int) $row['user_id'];
        $_SESSION['user_type']     = $row['user_type'];
        $_SESSION['full_name']     = $row['full_name'] ?: trim($row['first_name'] . ' ' . $row['last_name']);
        $_SESSION['email']         = $row['email'];
        $_SESSION['profile_image'] = $row['profile_image'];
        $_SESSION['is_verified']   = (bool) $row['is_verified'];
        $_SESSION['login_time']    = time();
        $_SESSION['via_remember']  = true;   // signed in from a cookie, not a password

        $pdo->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?")->execute([(int) $row['user_id']]);

        return true;
    } catch (Throwable $e) {
        error_log('remember-me attempt failed: ' . $e->getMessage());
        return false;
    }
}

/** Housekeeping: drop expired rows. Safe to call occasionally. */
function jm_remember_prune(PDO $pdo): void {
    try {
        $pdo->exec("DELETE FROM auth_tokens WHERE expires_at < NOW()");
    } catch (Throwable $e) {
        error_log('remember-me prune failed: ' . $e->getMessage());
    }
}
