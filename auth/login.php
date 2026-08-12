<?php
/**
 * JOBMINGTON - Minimal Sign In
 */

define('JOBMINGTON', true);
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';

Session::start();

$redirectTo = jm_safe_redirect_path(Security::clean(get('redirect', '')));
$hasSafeRedirect = $redirectTo !== '';

// "Not you?" — drop the remembered name and any persistent login on this device.
if (get('forget', '') !== '') {
    require_once __DIR__ . '/../includes/remember.php';
    jm_forget_visitor();
    jm_remember_revoke(db());
    redirect('/jobmington/auth/login.php' . ($hasSafeRedirect ? '?redirect=' . urlencode($redirectTo) : ''));
}

require_once __DIR__ . '/../includes/navigation.php';   // jm_login_dashboard_for

if (Session::isLoggedIn()) {
    if ($hasSafeRedirect) {
        redirect($redirectTo);
    }
    redirect(jm_login_dashboard_for($_SESSION['user_type'] ?? USER_TYPE_SEEKER));
}

$pdo = db();
$error = '';
if (($_GET['error'] ?? '') === 'account_disabled') {
    $error = 'This account has been deactivated. Get in touch if you think that is a mistake.';
}
$emailValue = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = cleanPost('email');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);
    $postedRedirect = jm_safe_redirect_path(Security::clean(post('redirect', '')));
    $redirectTo = $postedRedirect !== '' ? $postedRedirect : $redirectTo;
    $hasSafeRedirect = $redirectTo !== '';
    $emailValue = $email;

    if ($email === '' || $password === '') {
        $error = 'Enter your email and password.';
    } else {
        $stmt = $pdo->prepare("
            SELECT user_id, password_hash, user_type, first_name, last_name, full_name, profile_image,
                   is_verified, is_active, failed_login_attempts, locked_until
            FROM users
            WHERE email = ?
            LIMIT 1
        ");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && $user['locked_until'] && strtotime($user['locked_until']) > time()) {
            $minutesLeft = ceil((strtotime($user['locked_until']) - time()) / 60);
            $error = "Account locked. Try again in {$minutesLeft} minutes.";
        } elseif ($user && !(int) $user['is_active'] && password_verify($password, $user['password_hash'])) {
            // Checked only after the password verifies, so account state is never
            // revealed to someone guessing at addresses.
            $error = 'This account has been deactivated. Get in touch if you think that is a mistake.';
        } elseif ($user && password_verify($password, $user['password_hash'])) {
            $pdo->prepare("UPDATE users SET failed_login_attempts = 0, locked_until = NULL, last_login = NOW() WHERE user_id = ?")->execute([$user['user_id']]);

            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['user_type'] = $user['user_type'];
            $_SESSION['full_name'] = $user['full_name'] ?: trim($user['first_name'] . ' ' . $user['last_name']);
            $_SESSION['email'] = $email;
            $_SESSION['profile_image'] = $user['profile_image'];
            $_SESSION['is_verified'] = (bool) $user['is_verified'];
            $_SESSION['login_time'] = time();

            jm_log_activity((int) $user['user_id'], 'login', $remember ? 'with remember me' : null);

            // Greet them by name next time even if the token expires or is cleared.
            jm_remember_visitor((string) $_SESSION['full_name']);

            if ($remember) {
                require_once __DIR__ . '/../includes/remember.php';
                jm_remember_issue($pdo, (int) $user['user_id']);
            }
            // The old remember_token cookie was written but never validated, so it
            // did nothing. Clear any leftovers rather than leave them on devices.
            if (!empty($_COOKIE['remember_token']) && !headers_sent()) {
                setcookie('remember_token', '', time() - 3600, '/');
            }

            /* Block unverified users — send to verification page */
            if (!$_SESSION['is_verified'] && $user['user_type'] !== USER_TYPE_ADMIN) {
                // Park the destination so verification can hand them back to it
                // instead of the detour swallowing where they were going.
                if ($hasSafeRedirect) {
                    $_SESSION['post_auth_redirect'] = $redirectTo;
                }
                redirect('/jobmington/auth/verify-email.php');
            }

            unset($_SESSION['auth_context'], $_SESSION['auth_context_for']);   // consumed; must not linger
            if ($hasSafeRedirect) {
                redirect($redirectTo);
            }
            redirect(jm_login_dashboard_for($user['user_type']));
        } else {
            if ($user) {
                $attempts = ($user['failed_login_attempts'] ?? 0) + 1;
                // Admin-configurable, was the literal 5 in three places below.
                require_once __DIR__ . '/../includes/maintenance.php';
                $maxAttempts = max(3, min(20, (int) jm_setting('max_login_attempts', 5)));
                $lockout = $attempts >= $maxAttempts ? date('Y-m-d H:i:s', strtotime('+15 minutes')) : null;
                $pdo->prepare("UPDATE users SET failed_login_attempts = ?, locked_until = ? WHERE user_id = ?")->execute([$attempts, $lockout, $user['user_id']]);
                $error = $attempts >= $maxAttempts ? 'Too many failed attempts. Account locked for 15 minutes.' : 'Invalid credentials. Please try again.';
                jm_log_activity((int) $user['user_id'], $attempts >= $maxAttempts ? 'login_locked' : 'login_failed', 'attempt ' . $attempts);
            } else {
                usleep(random_int(100000, 300000));
                $error = 'Invalid credentials. Please try again.';
                jm_log_activity(null, 'login_unknown_email', $email);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In | Jobmington</title>
    <link rel="preload" as="font" type="font/ttf" href="/jobmington/assets/fonts/FuturaCyrillicDemi.ttf" crossorigin>
    <link rel="preload" as="font" type="font/ttf" href="/jobmington/assets/fonts/FuturaCyrillicBook.ttf" crossorigin>
    <link rel="stylesheet" href="/jobmington/assets/css/minimal-jobmington.css?v=brand-21">
</head>
<body class="jm-minimal">
    <div class="jm-shell">
        <header class="jm-header">
            <a href="/jobmington/" class="jm-logo">
                <img src="/jobmington/assets/images/badge.png?v=logo-8" alt="">
                <span>Jobmington</span>
            </a>
            <?php require_once __DIR__ . '/../includes/navigation.php'; jm_auth_nav('register'); ?>
        </header>

        <section class="jm-hero">
            <div>
                <?php
                    /* A gate always supplies ?redirect=; a nav link never does. Only
                       show the note when this request actually came from a gate, and
                       drop a stale one otherwise, so someone who just clicked "Sign in"
                       is not told they are mid-way through something they abandoned. */
                    $ctxFor  = (string) ($_SESSION['auth_context_for'] ?? '');
                    $ctxHere = $hasSafeRedirect && $ctxFor !== ''
                        && jm_norm_target($ctxFor) === jm_norm_target($redirectTo);
                    // Not shown unless it matches; deliberately not cleared here, so
                    // an unrelated visit (a footer link, another tab) cannot destroy a
                    // note the visitor still needs when they return to the real link.
                    $authCtx = $ctxHere ? trim((string) ($_SESSION['auth_context'] ?? '')) : '';
                ?>
                <?php $visitor = jm_returning_visitor(); ?>
                <p class="jm-kicker">Sign in</p>
                <?php /* "Welcome back" only when this device has signed in before.
                          Never assumed from a gate arrival, since whoever clicked
                          through may have no account at all. */ ?>
                <h1><?php
                    if ($visitor !== '')      { echo 'Welcome back, ' . e($visitor) . '.'; }
                    elseif ($hasSafeRedirect) { echo 'Sign in to continue.'; }
                    else                      { echo 'Sign in.'; }
                ?></h1>
                <p><?php
                    if ($authCtx !== '') {
                        echo e($authCtx);
                    } elseif ($hasSafeRedirect) {
                        echo 'Sign in below, or create a free account if you are new — either way we will bring you straight back.';
                    } else {
                        echo 'Sign in to apply, save jobs, manage listings, or continue posting a role.';
                    }
                ?></p>
                <?php if ($visitor !== ''): ?>
                    <p style="margin:10px 0 0;font-size:13px;color:#94a3b8;">
                        Not <?= e($visitor) ?>?
                        <a style="color:#0640a3;font-weight:600;" href="/jobmington/auth/login.php?forget=1<?= $hasSafeRedirect ? '&amp;redirect=' . urlencode($redirectTo) : '' ?>">Use a different account</a>
                    </p>
                <?php endif; ?>
            </div>

            <form method="POST" class="jm-panel">
                <?php if ($error): ?>
                    <div class="jm-alert"><?= e($error) ?></div>
                <?php endif; ?>

                <?php if ($hasSafeRedirect): ?>
                    <input type="hidden" name="redirect" value="<?= e($redirectTo) ?>">
                <?php endif; ?>

                <div class="jm-field">
                    <label for="email">Email</label>
                    <input class="jm-input" id="email" type="email" name="email" value="<?= e($emailValue) ?>" autocomplete="email" required>
                </div>

                <div class="jm-field" style="margin-top:16px;">
                    <label for="password">Password</label>
                    <input class="jm-input" id="password" type="password" name="password" autocomplete="current-password" required>
                </div>

                <div class="jm-form-actions" style="justify-content:space-between;">
                    <label style="display:flex;align-items:center;gap:8px;color:var(--jm-muted);font-size:14px;">
                        <input type="checkbox" name="remember">
                        Remember me
                    </label>
                    <a class="jm-muted-link" href="/jobmington/auth/forgot-password.php">Forgot password?</a>
                </div>

                <div class="jm-form-actions">
                    <button class="jm-button" type="submit">Sign in</button>
                    <a class="jm-button secondary" href="/jobmington/auth/register.php<?= $hasSafeRedirect ? '?redirect=' . urlencode($redirectTo) : '' ?>">Create account</a>
                </div>
            </form>
        </section>

        <?php jm_minimal_footer(); ?>
    </div>
</body>
</html>
