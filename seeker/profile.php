<?php
define('JOBMINGTON', true);
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';

Session::start();
Session::requireLogin('Sign in to view your profile.');

$pdo = db();
$userId = Session::userId();
$errors = [];
$success = '';

function jm_profile_user(PDO $pdo, int $userId): array {
    $stmt = $pdo->prepare("
        SELECT u.*, cv.cv_id, cv.headline AS cv_headline, cv.summary AS cv_summary
        FROM users u
        LEFT JOIN cv_profiles cv ON u.user_id = cv.user_id
        WHERE u.user_id = ?
        LIMIT 1
    ");
    $stmt->execute([$userId]);
    return $stmt->fetch() ?: [];
}

$user = jm_profile_user($pdo, $userId);
if (!$user) {
    Session::destroy();
    Session::requireLogin('Sign in to view your profile.');
}

$countries = $pdo->query("SELECT * FROM countries WHERE is_active = 1 ORDER BY name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::verifyCSRF()) {
        $errors[] = 'Please refresh the page and try again.';
    } else {
        $fullName = Security::clean($_POST['full_name'] ?? '');
        $phone = Security::clean($_POST['phone'] ?? '');
        $headline = Security::clean($_POST['headline'] ?? '');
        $bio = Security::clean($_POST['bio'] ?? '');
        $city = Security::clean($_POST['city'] ?? '');
        $countryId = (int) ($_POST['country_id'] ?? 0);
        $profileImage = $user['profile_image'] ?? null;

        if ($fullName === '') {
            $errors[] = 'Enter your full name.';
        }
        if (strlen($headline) > 200) {
            $errors[] = 'Keep the headline under 200 characters.';
        }

        if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] !== UPLOAD_ERR_NO_FILE) {
            $upload = Security::uploadFile(
                $_FILES['profile_image'],
                UPLOADS_PATH . '/avatars',
                ['image/jpeg', 'image/png', 'image/webp'],
                MAX_AVATAR_SIZE
            );

            if ($upload['success']) {
                if (!empty($profileImage) && $profileImage !== 'assets/images/default-avatar.png') {
                    deleteUpload('avatars/' . $profileImage);
                }
                $profileImage = $upload['filename'];
            } else {
                $errors = array_merge($errors, $upload['errors']);
            }
        }

        if (empty($errors)) {
            try {
                $pdo->beginTransaction();
                [$firstName, $lastName] = array_pad(explode(' ', $fullName, 2), 2, '');

                $stmt = $pdo->prepare("
                    UPDATE users
                    SET first_name = ?, last_name = ?, full_name = ?, phone = ?, headline = ?, bio = ?, city = ?, country_id = ?, profile_image = ?, updated_at = NOW()
                    WHERE user_id = ?
                ");
                $stmt->execute([
                    $firstName,
                    $lastName,
                    $fullName,
                    $phone ?: null,
                    $headline ?: null,
                    $bio ?: null,
                    $city ?: null,
                    $countryId ?: null,
                    $profileImage,
                    $userId
                ]);

                if (!empty($user['cv_id'])) {
                    $stmt = $pdo->prepare("UPDATE cv_profiles SET full_name = ?, phone = ?, city = ?, headline = ?, summary = ?, updated_at = NOW() WHERE cv_id = ?");
                    $stmt->execute([$fullName, $phone ?: null, $city ?: null, $headline ?: null, $bio ?: null, $user['cv_id']]);
                } else {
                    $stmt = $pdo->prepare("INSERT INTO cv_profiles (user_id, full_name, email, phone, city, headline, summary) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$userId, $fullName, $user['email'] ?? null, $phone ?: null, $city ?: null, $headline ?: null, $bio ?: null]);
                }

                $_SESSION['full_name'] = $fullName;
                $_SESSION['profile_image'] = $profileImage;
                $pdo->commit();
                Security::regenerateCSRF();
                $success = 'Profile saved.';
                $user = jm_profile_user($pdo, $userId);
            } catch (Throwable $e) {
                $pdo->rollBack();
                $errors[] = 'Profile could not be saved.';
            }
        }
    }
}

$profileFields = [
    !empty($user['full_name']),
    !empty($user['email']),
    !empty($user['phone']),
    !empty($user['city']),
    !empty($user['headline'] ?? $user['cv_headline'] ?? ''),
    !empty($user['bio'] ?? $user['cv_summary'] ?? ''),
];
$completion = (int) round((array_sum($profileFields) / count($profileFields)) * 100);
$headlineValue = $user['headline'] ?: ($user['cv_headline'] ?? '');
$bioValue = $user['bio'] ?: ($user['cv_summary'] ?? '');
$pageTitle = 'Profile | ' . SITE_NAME;
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?></title>
    <link rel="stylesheet" href="/jobmington/assets/css/minimal-jobmington.css?v=brand-15">
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
                    <p class="jm-kicker">Profile</p>
                    <h1>Your public details.</h1>
                    <p>Keep this simple and useful for employers reviewing your application.</p>
                </div>
                <a class="jm-muted-link" href="/jobmington/seeker/dashboard.php">Back to dashboard</a>
            </div>

            <?php if ($success): ?><div class="jm-success"><?= e($success) ?></div><?php endif; ?>
            <?php if (!empty($errors)): ?>
                <div class="jm-alert"><?php foreach ($errors as $error): ?><div><?= e($error) ?></div><?php endforeach; ?></div>
            <?php endif; ?>

            <form method="post" enctype="multipart/form-data" class="jm-grid-2">
                <?= Security::csrfField() ?>
                <aside class="jm-panel">
                    <img src="<?= e(profileImage($user['profile_image'] ?? null)) ?>" alt="" style="width:92px;height:92px;object-fit:cover;border:1px solid var(--jm-line);margin-bottom:18px;">
                    <h2><?= e($user['full_name'] ?: 'Your name') ?></h2>
                    <p><?= e($headlineValue ?: 'Add a headline so employers quickly understand your focus.') ?></p>
                    <span class="jm-badge"><?= $completion ?>% complete</span>
                    <div class="jm-field" style="margin-top:22px;">
                        <label for="profile_image">Profile photo</label>
                        <input class="jm-file" id="profile_image" type="file" name="profile_image" accept="image/jpeg,image/png,image/webp">
                    </div>
                </aside>

                <main class="jm-panel">
                    <div class="jm-form-grid">
                        <div class="jm-field">
                            <label for="full_name">Full name</label>
                            <input class="jm-input" id="full_name" name="full_name" value="<?= e($user['full_name'] ?? '') ?>" required>
                        </div>
                        <div class="jm-field">
                            <label for="phone">Phone</label>
                            <input class="jm-input" id="phone" name="phone" value="<?= e($user['phone'] ?? '') ?>">
                        </div>
                        <div class="jm-field">
                            <label for="country_id">Country</label>
                            <select class="jm-select" id="country_id" name="country_id">
                                <option value="">Select country</option>
                                <?php foreach ($countries as $country): ?>
                                    <option value="<?= (int) $country['country_id'] ?>" <?= (int) ($user['country_id'] ?? 0) === (int) $country['country_id'] ? 'selected' : '' ?>>
                                        <?= e($country['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="jm-field">
                            <label for="city">City</label>
                            <input class="jm-input" id="city" name="city" value="<?= e($user['city'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="jm-field" style="margin-top:18px;">
                        <label for="headline">Headline</label>
                        <input class="jm-input" id="headline" name="headline" value="<?= e($headlineValue) ?>" placeholder="Product designer, Laravel developer, sales lead...">
                    </div>

                    <div class="jm-field" style="margin-top:18px;">
                        <label for="bio">About</label>
                        <textarea class="jm-textarea" id="bio" name="bio" placeholder="A short, plain-language summary of what you do."><?= e($bioValue) ?></textarea>
                    </div>

                    <div class="jm-form-actions">
                        <button class="jm-button" type="submit">Save profile</button>
                        <a class="jm-button secondary" href="/jobmington/seeker/dashboard.php">Cancel</a>
                    </div>
                </main>
            </form>
        </section>

        <?php jm_minimal_footer(); ?>
    </div>
</body>
</html>
