<?php
define('JOBMINGTON', true);
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';

Session::start();
Session::requireLogin('Sign in to manage your settings.');

$pdo = db();
$userId = Session::userId();
$errors = [];
$success = '';

$stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ? LIMIT 1");
$stmt->execute([$userId]);
$user = $stmt->fetch();
if (!$user) {
    Session::destroy();
    Session::requireLogin('Sign in to manage your settings.');
}

$stmt = $pdo->prepare("SELECT * FROM user_settings WHERE user_id = ? LIMIT 1");
$stmt->execute([$userId]);
$settings = $stmt->fetch();
if (!$settings) {
    $stmt = $pdo->prepare("INSERT INTO user_settings (user_id, email_job_alerts, email_applications, email_messages, email_newsletter, profile_visibility, show_email, show_phone) VALUES (?, 1, 1, 1, 1, 'public', 0, 0)");
    $stmt->execute([$userId]);
    $settings = [
        'email_job_alerts' => 1,
        'email_applications' => 1,
        'email_messages' => 1,
        'email_newsletter' => 1,
        'profile_visibility' => 'public',
        'show_email' => 0,
        'show_phone' => 0,
    ];
}

$activeTab = $_GET['tab'] ?? 'account';
$activeTab = in_array($activeTab, ['account', 'password', 'notifications', 'privacy', 'data'], true) ? $activeTab : 'account';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::verifyCSRF()) {
        $errors[] = 'Please refresh the page and try again.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'update_account') {
            $activeTab = 'account';
            $email = Security::clean($_POST['email'] ?? '');
            $phone = Security::clean($_POST['phone'] ?? '');

            if (!Security::validateEmail($email)) {
                $errors[] = 'Enter a valid email address.';
            } else {
                $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ? LIMIT 1");
                $stmt->execute([$email, $userId]);
                if ($stmt->fetch()) {
                    $errors[] = 'That email is already in use.';
                }
            }

            if (empty($errors)) {
                $stmt = $pdo->prepare("UPDATE users SET email = ?, phone = ? WHERE user_id = ?");
                $stmt->execute([$email, $phone ?: null, $userId]);
                $_SESSION['email'] = $email;
                $user['email'] = $email;
                $user['phone'] = $phone;
                $success = 'Account updated.';
            }
        }

        if ($action === 'change_password') {
            $activeTab = 'password';
            $current = $_POST['current_password'] ?? '';
            $new = $_POST['new_password'] ?? '';
            $confirm = $_POST['confirm_password'] ?? '';

            if (!Security::verifyPassword($current, $user['password_hash'])) {
                $errors[] = 'Current password is incorrect.';
            }
            $errors = array_merge($errors, Security::validatePasswordStrength($new));
            if ($new !== $confirm) {
                $errors[] = 'Passwords do not match.';
            }

            if (empty($errors)) {
                $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
                $stmt->execute([Security::hashPassword($new), $userId]);
                $success = 'Password changed.';
            }
        }

        if ($action === 'update_notifications') {
            $activeTab = 'notifications';
            $settings['email_job_alerts'] = isset($_POST['email_job_alerts']) ? 1 : 0;
            $settings['email_applications'] = isset($_POST['email_applications']) ? 1 : 0;
            $settings['email_messages'] = isset($_POST['email_messages']) ? 1 : 0;
            $settings['email_newsletter'] = isset($_POST['email_newsletter']) ? 1 : 0;

            $stmt = $pdo->prepare("UPDATE user_settings SET email_job_alerts = ?, email_applications = ?, email_messages = ?, email_newsletter = ? WHERE user_id = ?");
            $stmt->execute([$settings['email_job_alerts'], $settings['email_applications'], $settings['email_messages'], $settings['email_newsletter'], $userId]);
            $success = 'Notification preferences saved.';
        }

        if ($action === 'update_privacy') {
            $activeTab = 'privacy';
            $visibility = $_POST['profile_visibility'] ?? 'public';
            $visibility = in_array($visibility, ['public', 'employers', 'private'], true) ? $visibility : 'public';
            $settings['profile_visibility'] = $visibility;
            $settings['show_email'] = isset($_POST['show_email']) ? 1 : 0;
            $settings['show_phone'] = isset($_POST['show_phone']) ? 1 : 0;

            $stmt = $pdo->prepare("UPDATE user_settings SET profile_visibility = ?, show_email = ?, show_phone = ? WHERE user_id = ?");
            $stmt->execute([$settings['profile_visibility'], $settings['show_email'], $settings['show_phone'], $userId]);
            $success = 'Privacy settings saved.';
        }

        if ($action === 'download_data') {
            header('Content-Type: application/json');
            header('Content-Disposition: attachment; filename="jobmington-data.json"');
            echo json_encode(['user' => $user, 'settings' => $settings], JSON_PRETTY_PRINT);
            exit;
        }

        Security::regenerateCSRF();
    }
}

$pageTitle = 'Settings | ' . SITE_NAME;
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
    <style>
        .jm-settings-grid { display:grid; grid-template-columns:220px minmax(0,1fr); gap:36px; }
        .jm-settings-nav { display:grid; gap:10px; align-content:start; }
        .jm-settings-nav a { color:var(--jm-ink); text-decoration:none; padding:10px 0; border-bottom:1px solid var(--jm-line); font-weight:600; }
        .jm-settings-nav a.active { color:var(--jm-blue); }
        .jm-check-row { display:flex; align-items:flex-start; justify-content:space-between; gap:20px; padding:16px 0; border-bottom:1px solid var(--jm-line); }
        .jm-check-row input { width:20px; height:20px; }
        @media (max-width:900px){ .jm-settings-grid{grid-template-columns:1fr;} }
    </style>
</head>
<body class="jm-minimal">
    <div class="jm-shell">
        <header class="jm-header">
            <a class="jm-logo" href="/jobmington/"><img src="/jobmington/assets/images/badge.png?v=logo-8" alt=""><span>Jobmington</span></a>
            <nav class="jm-nav" aria-label="Main navigation">
                <a href="/jobmington/jobs/">Find jobs</a>
                <a href="/jobmington/cv-builder/">CV Builder</a>
                <a href="/jobmington/seeker/dashboard.php">Dashboard</a>
                <a href="/jobmington/seeker/applications.php">Applications</a>
                <a href="/jobmington/jobs/saved.php">Saved jobs</a>
                <a href="/jobmington/seeker/profile.php">Profile</a>
                <a class="jm-button secondary" href="/jobmington/auth/logout.php">Sign out</a>
            </nav>
        </header>

        <section class="jm-section" style="padding-top:0;">
            <div class="jm-section-head">
                <div>
                    <p class="jm-kicker">Settings</p>
                    <h1>Account preferences.</h1>
                    <p>Manage your login, email preferences, and profile visibility.</p>
                </div>
            </div>

            <?php if ($success): ?><div class="jm-success"><?= e($success) ?></div><?php endif; ?>
            <?php if (!empty($errors)): ?><div class="jm-alert"><?php foreach ($errors as $error): ?><div><?= e($error) ?></div><?php endforeach; ?></div><?php endif; ?>

            <div class="jm-settings-grid">
                <aside class="jm-settings-nav">
                    <?php foreach (['account' => 'Account', 'password' => 'Password', 'notifications' => 'Notifications', 'privacy' => 'Privacy', 'data' => 'Data'] as $tab => $label): ?>
                        <a class="<?= $activeTab === $tab ? 'active' : '' ?>" href="/jobmington/seeker/settings.php?tab=<?= e($tab) ?>"><?= e($label) ?></a>
                    <?php endforeach; ?>
                </aside>

                <main class="jm-panel">
                    <?php if ($activeTab === 'account'): ?>
                        <form method="post">
                            <?= Security::csrfField() ?>
                            <input type="hidden" name="action" value="update_account">
                            <div class="jm-form-grid">
                                <div class="jm-field"><label>Email</label><input class="jm-input" type="email" name="email" value="<?= e($user['email']) ?>" required></div>
                                <div class="jm-field"><label>Phone</label><input class="jm-input" name="phone" value="<?= e($user['phone'] ?? '') ?>"></div>
                            </div>
                            <div class="jm-form-actions"><button class="jm-button" type="submit">Save account</button></div>
                        </form>
                    <?php endif; ?>

                    <?php if ($activeTab === 'password'): ?>
                        <form method="post">
                            <?= Security::csrfField() ?>
                            <input type="hidden" name="action" value="change_password">
                            <div class="jm-stack">
                                <div class="jm-field"><label>Current password</label><input class="jm-input" type="password" name="current_password" required></div>
                                <div class="jm-field"><label>New password</label><input class="jm-input" type="password" name="new_password" required></div>
                                <div class="jm-field"><label>Confirm new password</label><input class="jm-input" type="password" name="confirm_password" required></div>
                            </div>
                            <div class="jm-form-actions"><button class="jm-button" type="submit">Change password</button></div>
                        </form>
                    <?php endif; ?>

                    <?php if ($activeTab === 'notifications'): ?>
                        <form method="post">
                            <?= Security::csrfField() ?>
                            <input type="hidden" name="action" value="update_notifications">
                            <?php
                                $checks = [
                                    'email_job_alerts' => ['Job alerts', 'New jobs related to your profile.'],
                                    'email_applications' => ['Application updates', 'Status updates from employers.'],
                                    'email_messages' => ['Messages', 'Employer and account messages.'],
                                    'email_newsletter' => ['Newsletter', 'Product updates and career notes.'],
                                ];
                            ?>
                            <?php foreach ($checks as $key => [$title, $desc]): ?>
                                <label class="jm-check-row">
                                    <span><strong><?= e($title) ?></strong><br><span class="jm-small"><?= e($desc) ?></span></span>
                                    <input type="checkbox" name="<?= e($key) ?>" <?= !empty($settings[$key]) ? 'checked' : '' ?>>
                                </label>
                            <?php endforeach; ?>
                            <div class="jm-form-actions"><button class="jm-button" type="submit">Save notifications</button></div>
                        </form>
                    <?php endif; ?>

                    <?php if ($activeTab === 'privacy'): ?>
                        <form method="post">
                            <?= Security::csrfField() ?>
                            <input type="hidden" name="action" value="update_privacy">
                            <div class="jm-field">
                                <label>Profile visibility</label>
                                <select class="jm-select" name="profile_visibility">
                                    <?php foreach (['public' => 'Public', 'employers' => 'Employers only', 'private' => 'Private'] as $value => $label): ?>
                                        <option value="<?= e($value) ?>" <?= ($settings['profile_visibility'] ?? '') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <label class="jm-check-row"><span><strong>Show email</strong><br><span class="jm-small">Allow approved viewers to see your email.</span></span><input type="checkbox" name="show_email" <?= !empty($settings['show_email']) ? 'checked' : '' ?>></label>
                            <label class="jm-check-row"><span><strong>Show phone</strong><br><span class="jm-small">Allow approved viewers to see your phone number.</span></span><input type="checkbox" name="show_phone" <?= !empty($settings['show_phone']) ? 'checked' : '' ?>></label>
                            <div class="jm-form-actions"><button class="jm-button" type="submit">Save privacy</button></div>
                        </form>
                    <?php endif; ?>

                    <?php if ($activeTab === 'data'): ?>
                        <h2>Download your data</h2>
                        <p>Your export includes your account and current settings.</p>
                        <form method="post">
                            <?= Security::csrfField() ?>
                            <input type="hidden" name="action" value="download_data">
                            <button class="jm-button" type="submit">Download JSON</button>
                        </form>
                    <?php endif; ?>
                </main>
            </div>
        </section>

        <?php jm_minimal_footer(); ?>
    </div>
</body>
</html>
