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

$redirectTo = Security::clean(get('redirect', ''));
$hasSafeRedirect = str_starts_with($redirectTo, '/jobmington/');

function jm_login_dashboard_for(string $userType): string {
    if ($userType === USER_TYPE_ADMIN) {
        return '/jobmington/admin/';
    }
    if ($userType === USER_TYPE_EMPLOYER) {
        return '/jobmington/employer/dashboard.php';
    }
    return '/jobmington/seeker/dashboard.php';
}

if (Session::isLoggedIn()) {
    if ($hasSafeRedirect) {
        redirect($redirectTo);
    }
    redirect(jm_login_dashboard_for($_SESSION['user_type'] ?? USER_TYPE_SEEKER));
}

$pdo = db();
$error = '';
$emailValue = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = cleanPost('email');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);
    $postedRedirect = Security::clean(post('redirect', ''));
    $redirectTo = str_starts_with($postedRedirect, '/jobmington/') ? $postedRedirect : $redirectTo;
    $hasSafeRedirect = str_starts_with($redirectTo, '/jobmington/');
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
        } elseif ($user && password_verify($password, $user['password_hash'])) {
            $pdo->prepare("UPDATE users SET failed_login_attempts = 0, locked_until = NULL, last_login = NOW() WHERE user_id = ?")->execute([$user['user_id']]);

            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['user_type'] = $user['user_type'];
            $_SESSION['full_name'] = $user['full_name'] ?: trim($user['first_name'] . ' ' . $user['last_name']);
            $_SESSION['email'] = $email;
            $_SESSION['profile_image'] = $user['profile_image'];
            $_SESSION['is_verified'] = (bool) $user['is_verified'];
            $_SESSION['login_time'] = time();

            if ($remember) {
                $token = bin2hex(random_bytes(32));
                $expiry = time() + (86400 * 30);
                setcookie('remember_token', $token, $expiry, '/', '', isset($_SERVER['HTTPS']), true);
            }

            /* Block unverified users — send to verification page */
            if (!$_SESSION['is_verified'] && $user['user_type'] !== USER_TYPE_ADMIN) {
                redirect('/jobmington/auth/verify-email.php');
            }

            if ($hasSafeRedirect) {
                redirect($redirectTo);
            }
            redirect(jm_login_dashboard_for($user['user_type']));
        } else {
            if ($user) {
                $attempts = ($user['failed_login_attempts'] ?? 0) + 1;
                $lockout = $attempts >= 5 ? date('Y-m-d H:i:s', strtotime('+15 minutes')) : null;
                $pdo->prepare("UPDATE users SET failed_login_attempts = ?, locked_until = ? WHERE user_id = ?")->execute([$attempts, $lockout, $user['user_id']]);
                $error = $attempts >= 5 ? 'Too many failed attempts. Account locked for 15 minutes.' : 'Invalid credentials. Please try again.';
            } else {
                usleep(random_int(100000, 300000));
                $error = 'Invalid credentials. Please try again.';
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
    <link rel="stylesheet" href="/jobmington/assets/css/minimal-jobmington.css?v=brand-15">
</head>
<body class="jm-minimal">
    <div class="jm-shell">
        <header class="jm-header">
            <a href="/jobmington/" class="jm-logo">
                <img src="/jobmington/assets/images/badge.png?v=logo-7" alt="">
                <span>Jobmington</span>
            </a>
            <nav class="jm-nav" aria-label="Main navigation">
                <a href="/jobmington/jobs/">Find jobs</a>
                <a href="/jobmington/cv-builder/">CV Builder</a>
                <a href="/jobmington/employer/">Employers</a>
                <a class="jm-button secondary" href="/jobmington/auth/register.php">Create account</a>
            </nav>
        </header>

        <section class="jm-hero">
            <div>
                <p class="jm-kicker">Sign in</p>
                <h1>Welcome back.</h1>
                <p>Sign in to apply, save jobs, manage listings, or continue posting a role.</p>
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
