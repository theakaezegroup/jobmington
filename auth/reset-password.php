<?php
define('JOBMINGTON', true);
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/mailer.php';

Session::start();

$token   = trim((string) ($_GET['token'] ?? ''));
$message = '';
$errors  = [];
$invalid = false;
$user    = null;

if ($token === '') {
    redirect('/jobmington/auth/forgot-password.php');
}

$pdo  = db();
$stmt = $pdo->prepare("
    SELECT user_id, email, full_name
    FROM users
    WHERE reset_token = ? AND reset_expires > NOW() AND is_active = 1
    LIMIT 1
");
$stmt->execute([$token]);
$user = $stmt->fetch();

if (!$user) {
    $invalid = true;
}

if (!$invalid && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::verifyCSRF()) {
        $errors[] = 'Please refresh the page and try again.';
    } else {
        $password = $_POST['password'] ?? '';
        $confirm  = $_POST['confirm_password'] ?? '';
        $errors   = array_merge($errors, Security::validatePasswordStrength($password));

        if ($password !== $confirm) {
            $errors[] = 'Passwords do not match.';
        }

        if (empty($errors)) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE users SET password_hash = ?, reset_token = NULL, reset_expires = NULL WHERE user_id = ?")
                ->execute([$hash, (int) $user['user_id']]);

            Mailer::sendPasswordChanged($user['email'], $user['full_name']);

            Security::regenerateCSRF();
            $message = 'Your password has been reset. You can sign in now.';
            $user    = null;
        }
    }
}

$pageTitle = 'Reset Password | ' . SITE_NAME;
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?></title>
    <link rel="stylesheet" href="/jobmington/assets/css/minimal-jobmington.css?v=brand-10">
</head>
<body class="jm-minimal">
    <div class="jm-shell">
        <header class="jm-header">
            <a class="jm-logo" href="/jobmington/">
                <img src="/jobmington/assets/images/badge.png?v=logo-7" alt="">
                <span>Jobmington</span>
            </a>
            <nav class="jm-nav" aria-label="Main navigation">
                <a href="/jobmington/jobs/">Find jobs</a>
                <a href="/jobmington/employer/">Employers</a>
                <a href="/jobmington/auth/login.php">Sign in</a>
                <a class="jm-button secondary" href="/jobmington/employer/post-job.php">Post a job</a>
            </nav>
        </header>

        <section class="jm-section" style="padding-top:0;">
            <div class="jm-grid-2">
                <div class="jm-section-head" style="display:block;margin-bottom:0;">
                    <p class="jm-kicker">Account security</p>
                    <h1>Choose a new password</h1>
                    <p>Use at least 8 characters, one uppercase letter, and one number.</p>
                </div>

                <div class="jm-panel">
                    <?php if ($invalid): ?>
                        <div class="jm-alert">This reset link has expired or already been used.</div>
                        <div class="jm-form-actions" style="margin-top:16px;">
                            <a class="jm-button" href="/jobmington/auth/forgot-password.php">Request a new link</a>
                            <a class="jm-button secondary" href="/jobmington/auth/login.php">Sign in</a>
                        </div>

                    <?php elseif ($message): ?>
                        <div class="jm-success"><?= e($message) ?></div>
                        <a class="jm-button" href="/jobmington/auth/login.php" style="margin-top:16px;">Sign in</a>

                    <?php else: ?>
                        <?php if (!empty($errors)): ?>
                            <div class="jm-alert">
                                <?php foreach ($errors as $err): ?>
                                    <div><?= e($err) ?></div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <form method="post" action="/jobmington/auth/reset-password.php?token=<?= rawurlencode($token) ?>">
                            <?= Security::csrfField() ?>
                            <div class="jm-stack">
                                <div class="jm-field">
                                    <label for="password">New password</label>
                                    <input class="jm-input" id="password" type="password" name="password" required>
                                </div>
                                <div class="jm-field">
                                    <label for="confirm_password">Confirm password</label>
                                    <input class="jm-input" id="confirm_password" type="password" name="confirm_password" required>
                                </div>
                            </div>
                            <div class="jm-form-actions">
                                <button class="jm-button" type="submit">Set password</button>
                                <a class="jm-button secondary" href="/jobmington/auth/login.php">Cancel</a>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <?php jm_minimal_footer(); ?>
    </div>
</body>
</html>
