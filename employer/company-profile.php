<?php
define('JOBMINGTON', true);
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';

Session::start();
Session::requireRole(USER_TYPE_EMPLOYER);

$pdo = db();
$userId = Session::userId();
$errors = [];
$success = '';
$isSetup = ($_GET['setup'] ?? '') === '1';

function jm_company_slug(string $name): string {
    $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $name), '-'));
    return $slug !== '' ? $slug : 'company';
}

function jm_company_unique_slug(PDO $pdo, string $name, ?int $ignoreCompanyId = null): string {
    $base = jm_company_slug($name);
    $slug = $base;
    $i = 2;

    while (true) {
        $sql = "SELECT COUNT(*) FROM companies WHERE slug = ?";
        $params = [$slug];
        if ($ignoreCompanyId !== null) {
            $sql .= " AND company_id != ?";
            $params[] = $ignoreCompanyId;
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        if ((int) $stmt->fetchColumn() === 0) {
            return $slug;
        }

        $slug = $base . '-' . $i;
        $i++;
    }
}

function jm_company_logo_url(?string $logo): string {
    if (!$logo) {
        return asset('images/default-company.png');
    }
    if (str_starts_with($logo, 'http')) {
        return $logo;
    }
    return upload(str_contains($logo, '/') ? $logo : 'company-logos/' . $logo);
}

function jm_company_fetch(PDO $pdo, int $userId): array {
    $stmt = $pdo->prepare("SELECT * FROM companies WHERE user_id = ? ORDER BY company_id ASC LIMIT 1");
    $stmt->execute([$userId]);
    return $stmt->fetch() ?: [];
}

$company = jm_company_fetch($pdo, $userId);
if (!$company) {
    $user = Session::user();
    $name = trim(($user['full_name'] ?? '') . "'s Company");
    if ($name === "'s Company") {
        $name = 'New Company';
    }
    $stmt = $pdo->prepare("INSERT INTO companies (user_id, name, slug, created_at) VALUES (?, ?, ?, NOW())");
    $stmt->execute([$userId, $name, jm_company_unique_slug($pdo, $name)]);
    $company = jm_company_fetch($pdo, $userId);
    $isSetup = true;
}

$countries = $pdo->query("SELECT * FROM countries WHERE is_active = 1 ORDER BY name")->fetchAll();
$companySizes = ['1-10', '11-50', '51-200', '201-500', '500+'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::verifyCSRF()) {
        $errors[] = 'Please refresh the page and try again.';
    } else {
        $name = Security::clean($_POST['name'] ?? '');
        $industry = Security::clean($_POST['industry'] ?? '');
        $companySize = $_POST['company_size'] ?? '';
        $website = Security::clean($_POST['website'] ?? '');
        $description = Security::clean($_POST['description'] ?? '');
        $countryId = (int) ($_POST['country_id'] ?? 0);
        $city = Security::clean($_POST['city'] ?? '');
        $address = Security::clean($_POST['address'] ?? '');
        $logo = $company['logo'] ?? null;
        $cover = $company['cover_image'] ?? null;

        if ($name === '') {
            $errors[] = 'Company name is required.';
        }
        if ($companySize !== '' && !in_array($companySize, $companySizes, true)) {
            $companySize = '';
        }
        if ($website !== '' && !filter_var($website, FILTER_VALIDATE_URL)) {
            $errors[] = 'Enter a valid website URL.';
        }

        foreach (['logo' => 'logo', 'cover_image' => 'cover'] as $field => $target) {
            if (isset($_FILES[$field]) && $_FILES[$field]['error'] !== UPLOAD_ERR_NO_FILE) {
                $upload = Security::uploadFile(
                    $_FILES[$field],
                    UPLOADS_PATH . '/company-logos',
                    ['image/jpeg', 'image/png', 'image/webp'],
                    MAX_IMAGE_SIZE
                );
                if ($upload['success']) {
                    ${$target} = 'company-logos/' . $upload['filename'];
                } else {
                    $errors = array_merge($errors, $upload['errors']);
                }
            }
        }

        if (empty($errors)) {
            $slug = jm_company_unique_slug($pdo, $name, (int) $company['company_id']);
            $stmt = $pdo->prepare("
                UPDATE companies
                SET name = ?, slug = ?, logo = ?, cover_image = ?, industry = ?, size = ?, website = ?,
                    description = ?, country_id = ?, location = ?, address = ?, updated_at = NOW()
                WHERE company_id = ? AND user_id = ?
            ");
            $stmt->execute([
                $name,
                $slug,
                $logo,
                $cover,
                $industry ?: null,
                $companySize ?: null,
                $website ?: null,
                $description ?: null,
                $countryId ?: null,
                $city ?: null,
                $address ?: null,
                $company['company_id'],
                $userId
            ]);
            Security::regenerateCSRF();
            $success = 'Company profile saved.';
            $company = jm_company_fetch($pdo, $userId);
            $isSetup = false;
        }
    }
}

$pageTitle = 'Company Profile | ' . SITE_NAME;
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?></title>
    <link rel="preload" as="font" type="font/ttf" href="/jobmington/assets/fonts/FuturaCyrillicDemi.ttf" crossorigin>
    <link rel="preload" as="font" type="font/ttf" href="/jobmington/assets/fonts/FuturaCyrillicBook.ttf" crossorigin>
    <link rel="stylesheet" href="/jobmington/assets/css/minimal-jobmington.css?v=brand-22">
</head>
<body class="jm-minimal">
    <div class="jm-shell">
        <header class="jm-header">
            <a class="jm-logo" href="/jobmington/"><img src="/jobmington/assets/images/badge.png?v=logo-8" alt=""><span>Jobmington</span></a>
            <?php require_once __DIR__ . '/../includes/navigation.php'; jm_workspace_nav(['dashboard' => ['/jobmington/employer/dashboard.php', 'Dashboard'], 'company' => ['/jobmington/employer/company-profile.php', 'Company'], 'jobs' => ['/jobmington/employer/manage-jobs.php', 'Jobs'], 'applications' => ['/jobmington/employer/applications.php', 'Applications'], 'talent' => ['/jobmington/employer/talent-pool.php', 'Talent'], 'pricing' => ['/jobmington/pricing.php', 'Pricing']], 'company'); ?>
        </header>

        <section class="jm-section" style="padding-top:0;">
            <div class="jm-section-head">
                <div>
                    <p class="jm-kicker"><?= $isSetup ? 'Setup' : 'Company' ?></p>
                    <h1><?= $isSetup ? 'Complete your company profile.' : 'Company profile.' ?></h1>
                    <p>This is the company information candidates see beside your jobs.</p>
                </div>
                <a class="jm-muted-link" href="/jobmington/employer/dashboard.php">Back to dashboard</a>
            </div>

            <?php if ($success): ?><div class="jm-success"><?= e($success) ?></div><?php endif; ?>
            <?php if (!empty($errors)): ?><div class="jm-alert"><?php foreach ($errors as $error): ?><div><?= e($error) ?></div><?php endforeach; ?></div><?php endif; ?>

            <form method="post" enctype="multipart/form-data" class="jm-grid-2">
                <?= Security::csrfField() ?>
                <aside class="jm-panel">
                    <img src="<?= e(jm_company_logo_url($company['logo'] ?? null)) ?>" alt="" style="width:82px;height:82px;object-fit:contain;border:1px solid var(--jm-line);margin-bottom:18px;">
                    <h2><?= e($company['name']) ?></h2>
                    <p><?= e($company['industry'] ?: 'Add industry, website, and a short description to help candidates understand the company.') ?></p>
                    <div class="jm-field" style="margin-top:22px;">
                        <label for="logo">Logo</label>
                        <input class="jm-file" id="logo" type="file" name="logo" accept="image/jpeg,image/png,image/webp">
                    </div>
                    <div class="jm-field" style="margin-top:18px;">
                        <label for="cover_image">Cover image</label>
                        <input class="jm-file" id="cover_image" type="file" name="cover_image" accept="image/jpeg,image/png,image/webp">
                    </div>
                </aside>

                <main class="jm-panel">
                    <div class="jm-form-grid">
                        <div class="jm-field"><label for="name">Company name</label><input class="jm-input" id="name" name="name" value="<?= e($company['name']) ?>" required></div>
                        <div class="jm-field"><label for="industry">Industry</label><input class="jm-input" id="industry" name="industry" value="<?= e($company['industry'] ?? '') ?>"></div>
                        <div class="jm-field">
                            <label for="company_size">Company size</label>
                            <select class="jm-select" id="company_size" name="company_size">
                                <option value="">Select size</option>
                                <?php foreach ($companySizes as $size): ?><option value="<?= e($size) ?>" <?= ($company['size'] ?? '') === $size ? 'selected' : '' ?>><?= e($size) ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="jm-field"><label for="website">Website</label><input class="jm-input" id="website" type="url" name="website" value="<?= e($company['website'] ?? '') ?>"></div>
                        <div class="jm-field">
                            <label for="country_id">Country</label>
                            <select class="jm-select" id="country_id" name="country_id">
                                <option value="">Select country</option>
                                <?php foreach ($countries as $country): ?>
                                    <option value="<?= (int) $country['country_id'] ?>" <?= (int) ($company['country_id'] ?? 0) === (int) $country['country_id'] ? 'selected' : '' ?>><?= e($country['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="jm-field"><label for="city">City</label><input class="jm-input" id="city" name="city" value="<?= e($company['location'] ?? '') ?>"></div>
                    </div>
                    <div class="jm-field" style="margin-top:18px;"><label for="address">Address</label><input class="jm-input" id="address" name="address" value="<?= e($company['address'] ?? '') ?>"></div>
                    <div class="jm-field" style="margin-top:18px;"><label for="description">Description</label><textarea class="jm-textarea" id="description" name="description"><?= e($company['description'] ?? '') ?></textarea></div>
                    <div class="jm-form-actions">
                        <button class="jm-button" type="submit">Save company</button>
                        <a class="jm-button secondary" href="/jobmington/employer/dashboard.php">Cancel</a>
                    </div>
                </main>
            </form>
        </section>

        <?php jm_minimal_footer(); ?>
    </div>
</body>
</html>
