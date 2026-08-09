<?php
define('JOBMINGTON', true);
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';

Session::start();

$token     = trim((string) ($_GET['token'] ?? ''));
$verified  = false;
$invalid   = false;

if ($token !== '') {
    $pdo  = db();
    $stmt = $pdo->prepare("
        SELECT user_id, full_name, email
        FROM users
        WHERE activation_token = ? AND is_verified = 0 AND is_active = 1
        LIMIT 1
    ");
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if ($user) {
        $pdo->prepare("
            UPDATE users SET is_verified = 1, activation_token = NULL WHERE user_id = ?
        ")->execute([$user['user_id']]);

        /* update session if this is the same user */
        if (Session::isLoggedIn() && Session::userId() === (int) $user['user_id']) {
            $_SESSION['is_verified'] = true;
        }

        // Reward email verification with Seeds (once — awardEmailVerificationBonus
        // is safe to call, but guard against repeat verifies via the token reset).
        try {
            require_once __DIR__ . '/../includes/seeds.php';
            awardEmailVerificationBonus((int) $user['user_id']);
        } catch (Throwable $e) {
            error_log('Email-verify seed bonus failed: ' . $e->getMessage());
        }

        // Award the Verified badge.
        try {
            require_once __DIR__ . '/../includes/badges.php';
            awardBadge((int) $user['user_id'], 'verified');
        } catch (Throwable $e) {
            error_log('Email-verify badge failed: ' . $e->getMessage());
        }

        $verified = true;

        // Verification interrupted something (registering for an event, applying
        // for a job). Hand the user back to it rather than ending the journey here.
        $pending = jm_safe_redirect_path((string) ($_SESSION['post_auth_redirect'] ?? ''));
        if ($pending !== '' && Session::isLoggedIn() && Session::userId() === (int) $user['user_id']) {
            unset($_SESSION['post_auth_redirect'], $_SESSION['auth_context'], $_SESSION['auth_context_for']);
            redirect($pending);
        }
    } else {
        $invalid = true;
    }
}

$pageTitle = 'Verify Email | ' . SITE_NAME;
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?></title>
    <link rel="preload" as="font" type="font/ttf" href="/jobmington/assets/fonts/FuturaCyrillicDemi.ttf" crossorigin>
    <link rel="preload" as="font" type="font/ttf" href="/jobmington/assets/fonts/FuturaCyrillicBook.ttf" crossorigin>
    <link rel="stylesheet" href="/jobmington/assets/css/minimal-jobmington.css?v=brand-15">
</head>
<body class="jm-minimal">
    <div class="jm-shell">
        <header class="jm-header">
            <a class="jm-logo" href="/jobmington/">
                <img src="/jobmington/assets/images/badge.png?v=logo-8" alt="">
                <span>Jobmington</span>
            </a>
            <nav class="jm-nav" aria-label="Main navigation">
                <a href="/jobmington/jobs/">Find jobs</a>
                <a href="/jobmington/cv-builder/">CV Builder</a>
                <a href="/jobmington/employer/">Employers</a>
                <a class="jm-button secondary" href="/jobmington/auth/login.php">Sign in</a>
            </nav>
        </header>

        <section class="jm-section" style="padding-top:0;">
            <?php if ($verified): ?>
                <div class="jm-panel" style="max-width:480px;margin:60px auto;text-align:center;padding:48px 40px;">
                    <div style="width:64px;height:64px;border-radius:50%;background:#eef5ff;display:flex;align-items:center;justify-content:center;margin:0 auto 24px;">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#0640a3" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                    </div>
                    <h1 style="font-size:24px;font-weight:800;color:var(--jm-ink);margin:0 0 12px;">Email verified.</h1>
                    <p style="color:var(--jm-muted);margin:0 0 28px;line-height:1.7;">Your account is confirmed. You can now sign in and access all jobs and AI tools on Jobmington.</p>
                    <a class="jm-button" href="/jobmington/auth/login.php">Sign in</a>
                </div>

            <?php elseif ($invalid): ?>
                <div class="jm-panel" style="max-width:480px;margin:60px auto;text-align:center;padding:48px 40px;">
                    <div style="width:64px;height:64px;border-radius:50%;background:#fff8ee;display:flex;align-items:center;justify-content:center;margin:0 auto 24px;">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#f59f22" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    </div>
                    <h1 style="font-size:24px;font-weight:800;color:var(--jm-ink);margin:0 0 12px;">Link expired or invalid.</h1>
                    <p style="color:var(--jm-muted);margin:0 0 28px;line-height:1.7;">This verification link has already been used or has expired. If you need a new link, sign in and we can resend it.</p>
                    <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap;">
                        <a class="jm-button" href="/jobmington/auth/login.php">Sign in</a>
                        <a class="jm-button secondary" href="/jobmington/auth/register.php">Create account</a>
                    </div>
                </div>

            <?php else: ?>
                <div class="jm-panel" style="max-width:480px;margin:60px auto;text-align:center;padding:48px 40px;">
                    <div style="width:64px;height:64px;border-radius:50%;background:#eef5ff;display:flex;align-items:center;justify-content:center;margin:0 auto 24px;">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#0640a3" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                    </div>
                    <h1 style="font-size:24px;font-weight:800;color:var(--jm-ink);margin:0 0 12px;">Check your inbox.</h1>
                    <p style="color:var(--jm-muted);margin:0 0 28px;line-height:1.7;">We sent a verification link to your email. Click it to confirm your address and unlock access to jobs and AI tools.</p>
                    <p style="font-size:13px;color:var(--jm-muted);margin:0 0 28px;">Didn't get it? Check your spam folder, or sign in and request a new link.</p>
                    <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap;">
                        <a class="jm-button" href="/jobmington/auth/login.php">Sign in</a>
                        <a class="jm-button secondary" href="/jobmington/">Back to home</a>
                    </div>
                </div>
            <?php endif; ?>
        </section>

        <?php jm_minimal_footer(); ?>
    </div>
</body>
</html>
