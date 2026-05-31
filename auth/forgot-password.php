<?php
define('JOBMINGTON', true);
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';

Session::start();
$message = '';
$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::verifyCSRF()) {
        $error = 'Please refresh the page and try again.';
    } else {
        $email = Security::clean($_POST['email'] ?? '');
        if (!Security::validateEmail($email)) {
            $error = 'Enter a valid email address.';
        } else {
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
                    <p class="jm-kicker">Password help</p>
                    <h1>Reset your password</h1>
                    <p>Enter your account email and we will send a reset link if the account exists.</p>
                </div>

                <div class="jm-panel">
                    <?php if ($message): ?>
                        <div class="jm-success"><?= e($message) ?></div>
                    <?php endif; ?>
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
                </div>
            </div>
        </section>

        <?php jm_minimal_footer(); ?>
    </div>
</body>
</html>
