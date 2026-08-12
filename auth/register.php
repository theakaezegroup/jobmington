<?php
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

if (Session::isLoggedIn()) {
    if ($hasSafeRedirect) {
        redirect($redirectTo);
    }
    redirect(Session::isEmployer() ? '/jobmington/employer/dashboard.php' : '/jobmington/seeker/dashboard.php');
}

$pdo = db();
$requestedType = get('type', 'seeker') === USER_TYPE_EMPLOYER ? USER_TYPE_EMPLOYER : USER_TYPE_SEEKER;
$errors = [];
$form = [
    'full_name' => '',
    'email' => '',
    'phone' => '',
    'user_type' => $requestedType,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::verifyCSRF()) {
        $errors[] = 'Please refresh the page and try again.';
    } else {
        $form['full_name'] = trim(Security::clean($_POST['full_name'] ?? ''));
        $form['email'] = strtolower(trim(Security::clean($_POST['email'] ?? '')));
        $form['phone'] = trim(Security::clean($_POST['phone'] ?? ''));
        $form['user_type'] = ($_POST['user_type'] ?? USER_TYPE_SEEKER) === USER_TYPE_EMPLOYER ? USER_TYPE_EMPLOYER : USER_TYPE_SEEKER;
        $postedRedirect = jm_safe_redirect_path(Security::clean($_POST['redirect'] ?? ''));
        $redirectTo = $postedRedirect !== '' ? $postedRedirect : $redirectTo;
        $hasSafeRedirect = $redirectTo !== '';
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if ($form['full_name'] === '') {
            $errors[] = 'Enter your full name.';
        }
        if (!Security::validateEmail($form['email'])) {
            $errors[] = 'Enter a valid email address.';
        }
        foreach (Security::validatePasswordStrength($password) as $passwordError) {
            $errors[] = $passwordError;
        }
        if ($password !== $confirmPassword) {
            $errors[] = 'Passwords do not match.';
        }

        if (empty($errors)) {
            $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$form['email']]);
            if ($stmt->fetch()) {
                $errors[] = 'An account with this email already exists.';
            }
        }

        if (empty($errors)) {
            [$firstName, $lastName] = array_pad(explode(' ', $form['full_name'], 2), 2, '');
            $activationToken = Security::generateToken(32);
            $stmt = $pdo->prepare("
                INSERT INTO users (
                    first_name, last_name, full_name, email, password_hash, phone,
                    user_type, is_active, is_verified, activation_token, ip_address, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, 1, 0, ?, ?, NOW())
            ");
            $stmt->execute([
                $firstName,
                $lastName,
                $form['full_name'],
                $form['email'],
                Security::hashPassword($password),
                $form['phone'] ?: null,
                $form['user_type'],
                $activationToken,
                Security::getClientIP(),
            ]);

            $userId = (int) $pdo->lastInsertId();
            jm_log_activity($userId, 'signup', $form['user_type'] . ' - ' . $form['email']);
            sendNotification(
                $userId,
                'welcome',
                'Welcome to Jobmington, ' . explode(' ', trim($form['full_name']))[0],
                $form['user_type'] === USER_TYPE_EMPLOYER
                    ? 'Post your first role and start receiving applications.'
                    : 'Build your CV, then let the AI tools sharpen it for the roles you want.',
                $form['user_type'] === USER_TYPE_EMPLOYER ? '/employer/post-job.php' : '/cv-builder/'
            );

            // Welcome Seeds (free engagement currency).
            try {
                require_once __DIR__ . '/../includes/seeds.php';
                awardSignupBonus($userId);
            } catch (Throwable $e) {
                error_log('Signup seed bonus failed: ' . $e->getMessage());
            }

            require_once __DIR__ . '/../includes/mailer.php';
            Mailer::sendVerificationEmail($form['email'], $form['full_name'], $activationToken);

            $_SESSION['user_id'] = $userId;
            $_SESSION['user_type'] = $form['user_type'];
            $_SESSION['full_name'] = $form['full_name'];
            $_SESSION['email'] = $form['email'];
            $_SESSION['profile_image'] = null;
            $_SESSION['is_verified'] = false;
            $_SESSION['login_time'] = time();

            Security::regenerateCSRF();
            jm_remember_visitor((string) $form['full_name']);
            // Park the destination so verification hands them back to it.
            if ($hasSafeRedirect) {
                $_SESSION['post_auth_redirect'] = $redirectTo;
            }
            $_SESSION['is_new_signup'] = true;
            redirect('/jobmington/auth/verify-email.php');
        }
    }
}

$pageTitle = 'Create Account | ' . SITE_NAME;
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?></title>
    <link rel="preload" as="font" type="font/ttf" href="/jobmington/assets/fonts/FuturaCyrillicDemi.ttf" crossorigin>
    <link rel="preload" as="font" type="font/ttf" href="/jobmington/assets/fonts/FuturaCyrillicBook.ttf" crossorigin>
    <link rel="stylesheet" href="/jobmington/assets/css/minimal-jobmington.css?v=brand-19">
</head>
<body class="jm-minimal">
    <div class="jm-shell">
        <header class="jm-header">
            <a class="jm-logo" href="/jobmington/"><img src="/jobmington/assets/images/badge.png?v=logo-8" alt=""><span>Jobmington</span></a>
            <?php require_once __DIR__ . '/../includes/navigation.php'; jm_auth_nav('login'); ?>
        </header>

        <section class="jm-hero">
            <div>
                <?php
                    $ctxFor  = (string) ($_SESSION['auth_context_for'] ?? '');
                    $ctxHere = $hasSafeRedirect && $ctxFor !== ''
                        && jm_norm_target($ctxFor) === jm_norm_target($redirectTo);
                    $authCtx = $ctxHere ? trim((string) ($_SESSION['auth_context'] ?? '')) : '';
                ?>
                <p class="jm-kicker">Create account</p>
                <h1><?= $authCtx ? 'Create your account.' : 'Join Jobmington.' ?></h1>
                <p><?= $authCtx
                        ? e($authCtx)
                        : 'Create a seeker account to apply and save roles, or an employer account to set up a company and publish jobs.' ?></p>
            </div>

            <form method="post" class="jm-panel">
                <?= Security::csrfField() ?>
                <?php if ($hasSafeRedirect): ?>
                    <input type="hidden" name="redirect" value="<?= e($redirectTo) ?>">
                <?php endif; ?>
                <?php if (!empty($errors)): ?>
                    <div class="jm-alert"><?php foreach ($errors as $error): ?><div><?= e($error) ?></div><?php endforeach; ?></div>
                <?php endif; ?>

                <div class="jm-form-grid">
                    <div class="jm-field">
                        <label for="full_name">Full name</label>
                        <input class="jm-input" id="full_name" name="full_name" value="<?= e($form['full_name']) ?>" autocomplete="name" required>
                    </div>
                    <div class="jm-field">
                        <label for="email">Email</label>
                        <input class="jm-input" id="email" type="email" name="email" value="<?= e($form['email']) ?>" autocomplete="email" required>
                    </div>
                    <div class="jm-field">
                        <label for="phone">Phone</label>
                        <input class="jm-input" id="phone" name="phone" value="<?= e($form['phone']) ?>" autocomplete="tel">
                    </div>
                    <div class="jm-field">
                        <label for="user_type">Account type</label>
                        <select class="jm-select" id="user_type" name="user_type">
                            <option value="<?= USER_TYPE_SEEKER ?>" <?= $form['user_type'] === USER_TYPE_SEEKER ? 'selected' : '' ?>>Job seeker</option>
                            <option value="<?= USER_TYPE_EMPLOYER ?>" <?= $form['user_type'] === USER_TYPE_EMPLOYER ? 'selected' : '' ?>>Employer</option>
                        </select>
                    </div>
                    <div class="jm-field">
                        <label for="password">Password</label>
                        <input class="jm-input" id="password" type="password" name="password" autocomplete="new-password" required>
                    </div>
                    <div class="jm-field">
                        <label for="confirm_password">Confirm password</label>
                        <input class="jm-input" id="confirm_password" type="password" name="confirm_password" autocomplete="new-password" required>
                    </div>
                </div>

                <div class="jm-form-actions">
                    <button class="jm-button" type="submit">Create account</button>
                    <a class="jm-button secondary" href="/jobmington/auth/login.php<?= $hasSafeRedirect ? '?redirect=' . urlencode($redirectTo) : '' ?>">Sign in instead</a>
                </div>
            </form>
        </section>

        <?php jm_minimal_footer(); ?>
    </div>
</body>
</html>
