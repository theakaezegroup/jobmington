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
$inactive  = false;
$resendMsg = '';
$resendErr = '';

/*
 * Resend.
 *
 * Without this the page was a dead end: an unverified account cannot sign in
 * (login.php bounces it straight back here), and this page only offered "Sign
 * in", which looped. The copy already told people to request a new link, but
 * nothing existed to request it with.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'resend') {
    if (!Security::verifyCSRF()) {
        $resendErr = 'Security check failed. Please try again.';
    } elseif (!Session::isLoggedIn()) {
        $resendErr = 'Sign in first so we know which address to send to.';
    } else {
        $last = (int) ($_SESSION['verify_resend_at'] ?? 0);
        if (time() - $last < 120) {
            $wait = 120 - (time() - $last);
            $resendErr = "Just sent one. Try again in {$wait} seconds.";
        } else {
            try {
                $pdo  = db();
                $stmt = $pdo->prepare("SELECT user_id, full_name, email, is_verified FROM users WHERE user_id = ? LIMIT 1");
                $stmt->execute([(int) Session::userId()]);
                $me = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$me) {
                    $resendErr = 'We could not find that account.';
                } elseif ((int) $me['is_verified'] === 1) {
                    $verified  = true;   // already done elsewhere; do not send again
                    $_SESSION['is_verified'] = true;
                } else {
                    // Fresh token each time, so an older link cannot be reused.
                    $newToken = bin2hex(random_bytes(32));
                    $pdo->prepare("UPDATE users SET activation_token = ? WHERE user_id = ?")
                        ->execute([$newToken, (int) $me['user_id']]);

                    require_once __DIR__ . '/../includes/mailer.php';
                    $sent = Mailer::sendVerificationEmail($me['email'], $me['full_name'], $newToken);

                    $_SESSION['verify_resend_at'] = time();
                    $resendMsg = $sent
                        ? 'Sent. Check ' . $me['email'] . ', including your spam folder.'
                        : '';
                    $resendErr = $sent ? '' : 'We could not send it just now. Please try again shortly.';
                }
                Security::regenerateCSRF();
            } catch (Throwable $e) {
                error_log('Verification resend failed: ' . $e->getMessage());
                $resendErr = 'Something went wrong. Please try again.';
            }
        }
    }
}

// Someone already signed in but unverified is the person who needs the resend.
$pendingEmail = '';
if (Session::isLoggedIn() && empty($_SESSION['is_verified'])) {
    $pendingEmail = (string) Session::get('email');
}

if ($token !== '') {
    $pdo  = db();
    /*
     * is_active is checked in PHP rather than in the WHERE clause, so a
     * deactivated account can be told apart from a bad token. Filtering it out
     * in SQL made both look identical, and the page then blamed the link. That
     * sends people round in circles requesting new links, since every new link
     * fails for the same reason the last one did.
     */
    $stmt = $pdo->prepare("
        SELECT user_id, full_name, email, is_active, is_verified
        FROM users
        WHERE activation_token = ?
        LIMIT 1
    ");
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if ($user && (int) $user['is_active'] !== 1) {
        $inactive = true;
        $user = null;   // nothing further should treat this as verifiable
    }

    if ($user) {
        $alreadyDone = (int) $user['is_verified'] === 1;

        if (!$alreadyDone) {
            // The token is deliberately kept. Clearing it is what made the
            // second visit indistinguishable from a forged one.
            $pdo->prepare("
                UPDATE users SET is_verified = 1 WHERE user_id = ?
            ")->execute([$user['user_id']]);
        }

        /* update session if this is the same user */
        if (Session::isLoggedIn() && Session::userId() === (int) $user['user_id']) {
            $_SESSION['is_verified'] = true;
        }

        if (!$alreadyDone) {
            sendNotification(
                (int) $user['user_id'],
                'account',
                'Your email is verified',
                'Everything on Jobmington is open to you now.',
                '/seeker/dashboard.php'
            );
        }

        // Seeds and the badge are first-time only. They used to rely on the
        // token being wiped to stop them repeating; the token now survives, so
        // the condition is stated rather than implied.
        if (!$alreadyDone) {
            try {
                require_once __DIR__ . '/../includes/seeds.php';
                awardEmailVerificationBonus((int) $user['user_id']);
            } catch (Throwable $e) {
                error_log('Email-verify seed bonus failed: ' . $e->getMessage());
            }
        }

        if (!$alreadyDone) {
            try {
                require_once __DIR__ . '/../includes/badges.php';
                awardBadge((int) $user['user_id'], 'verified-email');
            } catch (Throwable $e) {
                error_log('Email-verify badge failed: ' . $e->getMessage());
            }
        }

        $verified = true;

        $isThisPerson = Session::isLoggedIn() && Session::userId() === (int) $user['user_id'];

        /*
         * Plenty of people only came here to attend an event, and were made to
         * create an account on the way. Finish that registration and hand them
         * a note about it, rather than dropping them on a dashboard that says
         * nothing about the one thing they came to do.
         *
         * Only for a signed-in request. A link fetched by a mail scanner has no
         * session, and settling the intent there would leave the note somewhere
         * nobody reads; the intent stays put and the next sign-in collects it.
         */
        if ($isThisPerson) {
            require_once __DIR__ . '/../includes/event_registration.php';
            if (jm_settle_pending_event((int) $user['user_id'])) {
                unset($_SESSION['post_auth_redirect'], $_SESSION['auth_context'], $_SESSION['auth_context_for']);
                redirect(Session::isEmployer() ? '/jobmington/employer/dashboard.php' : '/jobmington/seeker/dashboard.php');
            }
        }

        // Verification interrupted something else (applying for a job, say).
        // Hand the user back to it rather than ending the journey here.
        $pending = jm_safe_redirect_path((string) ($_SESSION['post_auth_redirect'] ?? ''));
        if ($pending !== '' && $isThisPerson) {
            unset($_SESSION['post_auth_redirect'], $_SESSION['auth_context'], $_SESSION['auth_context_for']);
            redirect($pending);
        }
    } elseif (!$inactive) {
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
    <link rel="stylesheet" href="/jobmington/assets/css/minimal-jobmington.css?v=brand-25">
</head>
<body class="jm-minimal">
    <div class="jm-shell">
        <header class="jm-header">
            <a class="jm-logo" href="/jobmington/">
                <img src="/jobmington/assets/images/badge.png?v=logo-8" alt="">
                <span>Jobmington</span>
            </a>
            <?php require_once __DIR__ . '/../includes/navigation.php'; jm_auth_nav('login'); ?>
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

            <?php elseif ($inactive): ?>
                <div class="jm-panel" style="max-width:480px;margin:60px auto;text-align:center;padding:48px 40px;">
                    <div style="width:64px;height:64px;border-radius:50%;background:#fff8ee;display:flex;align-items:center;justify-content:center;margin:0 auto 24px;">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#f59f22" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="4.9" y1="4.9" x2="19.1" y2="19.1"/></svg>
                    </div>
                    <h1 style="font-size:24px;font-weight:800;color:var(--jm-ink);margin:0 0 12px;">This account is not active.</h1>
                    <p style="color:var(--jm-muted);margin:0 0 28px;line-height:1.7;">Your link is fine. The account it belongs to has been deactivated, so it cannot be verified. Requesting another link will not help. Get in touch and we can sort it out.</p>
                    <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap;">
                        <a class="jm-button" href="/jobmington/contact.php">Contact us</a>
                        <a class="jm-button secondary" href="/jobmington/">Back to home</a>
                    </div>
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
                    <p style="color:var(--jm-muted);margin:0 0 20px;line-height:1.7;">
                        We sent a verification link<?= $pendingEmail !== '' ? ' to <strong style="color:var(--jm-ink);">' . e($pendingEmail) . '</strong>' : ' to your email' ?>.
                        Click it to confirm your address and unlock jobs and AI tools.
                    </p>

                    <?php if ($resendMsg): ?>
                        <p style="background:#e6f5f1;color:#0a6454;border-radius:8px;padding:11px 14px;font-size:13px;font-weight:600;margin:0 0 18px;"><?= e($resendMsg) ?></p>
                    <?php endif; ?>
                    <?php if ($resendErr): ?>
                        <p style="background:#fdecea;color:#991b1b;border-radius:8px;padding:11px 14px;font-size:13px;font-weight:600;margin:0 0 18px;"><?= e($resendErr) ?></p>
                    <?php endif; ?>

                    <?php if ($pendingEmail !== ''): ?>
                        <p style="font-size:13px;color:var(--jm-muted);margin:0 0 16px;">Not there? Check spam first, then send it again.</p>
                        <form method="post" style="margin:0 0 20px;">
                            <?= Security::csrfField() ?>
                            <input type="hidden" name="action" value="resend">
                            <button class="jm-button" type="submit">Resend verification email</button>
                        </form>
                        <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap;">
                            <a class="jm-button secondary" href="/jobmington/auth/logout.php">Use a different account</a>
                            <a class="jm-button secondary" href="/jobmington/">Back to home</a>
                        </div>
                    <?php else: ?>
                        <p style="font-size:13px;color:var(--jm-muted);margin:0 0 28px;">Didn't get it? Check your spam folder, then sign in to send a new link.</p>
                        <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap;">
                            <a class="jm-button" href="/jobmington/auth/login.php">Sign in</a>
                            <a class="jm-button secondary" href="/jobmington/">Back to home</a>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </section>

        <?php jm_minimal_footer(); ?>
    </div>
</body>
</html>
