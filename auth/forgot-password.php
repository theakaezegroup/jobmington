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

$message = '';
$error   = '';
$email   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::verifyCSRF()) {
        $error = 'Please refresh the page and try again.';
    } else {
        $email = Security::clean($_POST['email'] ?? '');
        if (!Security::validateEmail($email)) {
            $error = 'Enter a valid email address.';
        } else {
            $pdo = db();

            $stmt = $pdo->prepare("SELECT user_id, full_name FROM users WHERE email = ? AND is_active = 1 LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user) {
                $token   = bin2hex(random_bytes(32));
                $expires = date('Y-m-d H:i:s', time() + 3600);
                $pdo->prepare("UPDATE users SET reset_token = ?, reset_expires = ? WHERE user_id = ?")
                    ->execute([$token, $expires, (int) $user['user_id']]);
                Mailer::sendPasswordReset($email, $user['full_name'], SITE_URL . '/auth/reset-password.php?token=' . $token);
            }

            $message = 'If that email exists, a password reset link has been sent.';
            Security::regenerateCSRF();
        }
    }
}

$pageTitle = 'Forgot Password | ' . SITE_NAME;
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?></title>
    <link rel="preload" as="font" type="font/ttf" href="/jobmington/assets/fonts/FuturaCyrillicDemi.ttf" crossorigin>
    <link rel="preload" as="font" type="font/ttf" href="/jobmington/assets/fonts/FuturaCyrillicBook.ttf" crossorigin>
    <link rel="stylesheet" href="/jobmington/assets/css/minimal-jobmington.css?v=brand-27">
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
            <div class="jm-grid-2">
                <div class="jm-section-head" style="display:block;margin-bottom:0;">
                    <p class="jm-kicker">Password help</p>
                    <h1>Reset your password</h1>
                    <p>Enter your account email and we will send a reset link if the account exists.</p>
                </div>

                <div class="jm-panel">
                    <?php if ($message): ?>
                        <div class="jm-success"><?= e($message) ?></div>
                        <a class="jm-button" href="/jobmington/auth/login.php" style="margin-top:16px;">Back to sign in</a>
                    <?php else: ?>
                        <?php if ($error): ?>
                            <div class="jm-alert"><?= e($error) ?></div>
                        <?php endif; ?>

                        <form method="post" action="/jobmington/auth/forgot-password.php">
                            <?= Security::csrfField() ?>
                            <div class="jm-field">
                                <label for="email">Email address</label>
                                <input class="jm-input" id="email" type="email" name="email" value="<?= e($email) ?>" required>
                            </div>
                            <div class="jm-form-actions">
                                <button class="jm-button" type="submit">Send reset link</button>
                                <a class="jm-button secondary" href="/jobmington/auth/login.php">Back to sign in</a>
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
