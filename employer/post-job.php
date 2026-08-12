<?php
define('JOBMINGTON', true);
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/monetization.php';
require_once __DIR__ . '/_employer_helpers.php';
require_once __DIR__ . '/../includes/mailer.php';

Session::start();
$pdo = db();

$isLoggedIn = Session::isLoggedIn();
$isEmployer = $isLoggedIn && Session::isEmployer();
$userId = Session::userId();
$company = $isEmployer ? jm_employer_company($pdo, (int) $userId) : null;
$categories = $pdo->query("SELECT * FROM job_categories ORDER BY name")->fetchAll();
$countries = $pdo->query("SELECT * FROM countries WHERE is_active = 1 ORDER BY name")->fetchAll();
$packages = jm_job_posting_packages();
$selectedPackageId = $_GET['package'] ?? 'starter';
if (!isset($packages[$selectedPackageId])) {
    $selectedPackageId = 'starter';
}
$postJobPath = '/jobmington/employer/post-job.php' . ($selectedPackageId !== 'starter' ? '?package=' . urlencode($selectedPackageId) : '');
$errors = [];

$form = [
    'title' => '',
    'category_id' => '',
    'description' => '',
    'requirements' => '',
    'benefits' => '',
    'job_type' => 'Full-time',
    'experience_level' => 'Entry',
    'salary_min' => '',
    'salary_max' => '',
    'salary_currency' => 'NGN',
    'show_salary' => 1,
    'country_id' => '',
    'city' => '',
    'application_email' => $isLoggedIn ? (Session::user()['email'] ?? '') : '',
    'application_url' => '',
    'deadline' => '',
    'posting_package' => $selectedPackageId,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$isLoggedIn) {
        redirect('/jobmington/auth/login.php?redirect=' . urlencode($postJobPath));
    }
    if (!$isEmployer) {
        redirect('/jobmington/errors/403.php');
    }
    if (!$company) {
        redirect('/jobmington/employer/company-profile.php?setup=1');
    }
    if (!Security::verifyCSRF()) {
        $errors[] = 'Please refresh the page and try again.';
    } else {
        $form['title'] = Security::clean($_POST['title'] ?? '');
        $form['category_id'] = (int) ($_POST['category_id'] ?? 0);
        $form['description'] = trim($_POST['description'] ?? '');
        $form['requirements'] = trim($_POST['requirements'] ?? '');
        $form['benefits'] = trim($_POST['benefits'] ?? '');
        $form['job_type'] = $_POST['job_type'] ?? 'Full-time';
        $form['experience_level'] = $_POST['experience_level'] ?? 'Entry';
        $form['salary_min'] = $_POST['salary_min'] !== '' ? (float) $_POST['salary_min'] : null;
        $form['salary_max'] = $_POST['salary_max'] !== '' ? (float) $_POST['salary_max'] : null;
        $form['salary_currency'] = strtoupper(Security::clean($_POST['salary_currency'] ?? 'NGN'));
        $form['show_salary'] = isset($_POST['show_salary']) ? 1 : 0;
        $form['country_id'] = (int) ($_POST['country_id'] ?? 0);
        $form['city'] = Security::clean($_POST['city'] ?? '');
        $form['application_email'] = Security::clean($_POST['application_email'] ?? '');
        $form['application_url'] = Security::clean($_POST['application_url'] ?? '');
        $form['deadline'] = Security::clean($_POST['deadline'] ?? '');
        $form['posting_package'] = Security::clean($_POST['posting_package'] ?? 'starter');
        $package = jm_job_posting_package($form['posting_package']);
        $form['posting_package'] = $package['id'];

        if ($form['title'] === '') {
            $errors[] = 'Job title is required.';
        }
        if ($form['description'] === '') {
            $errors[] = 'Job description is required.';
        }
        if ($form['country_id'] <= 0) {
            $errors[] = 'Select a country.';
        }
        if (!in_array($form['job_type'], JOB_TYPES, true)) {
            $errors[] = 'Choose a valid job type.';
        }
        if (!in_array($form['experience_level'], EXPERIENCE_LEVELS, true)) {
            $errors[] = 'Choose a valid experience level.';
        }
        if ($form['application_email'] !== '' && !Security::validateEmail($form['application_email'])) {
            $errors[] = 'Enter a valid application email.';
        }
        if ($form['application_url'] !== '' && !filter_var($form['application_url'], FILTER_VALIDATE_URL)) {
            $errors[] = 'Enter a valid application URL.';
        }
        if ($form['salary_min'] !== null && $form['salary_max'] !== null && $form['salary_min'] > $form['salary_max']) {
            $errors[] = 'Minimum salary cannot be greater than maximum salary.';
        }
        if ($form['deadline'] !== '' && strtotime($form['deadline']) < strtotime('today')) {
            $errors[] = 'Deadline cannot be in the past.';
        }

        if (empty($errors)) {
            $isPaidPackage = ((float) $package['price']) > 0;
            $expiresAt = $form['deadline'] ?: date('Y-m-d', strtotime('+' . (int) $package['duration_days'] . ' days'));
            $slug = jm_employer_unique_slug($pdo, 'jobs', 'slug', $form['title'], null, 'job_id');
            $stmt = $pdo->prepare("
                INSERT INTO jobs (
                    company_id, category_id, title, slug, description, apply_link, requirements, benefits,
                    job_type, experience_level, salary_min, salary_max, salary_currency, show_salary,
                    country_id, city, application_email, expires_at,
                    is_featured, is_active, posted_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                (int) $company['company_id'],
                $form['category_id'] ?: null,
                $form['title'],
                $slug,
                $form['description'],
                $form['application_url'] ?: null,
                $form['requirements'] ?: null,
                $form['benefits'] ?: null,
                $form['job_type'],
                $form['experience_level'],
                $form['salary_min'],
                $form['salary_max'],
                $form['salary_currency'] ?: 'NGN',
                $form['show_salary'],
                $form['country_id'],
                $form['city'] ?: null,
                $form['application_email'] ?: null,
                $expiresAt,
                (!$isPaidPackage && $package['featured']) ? 1 : 0,
                $isPaidPackage ? 0 : 1,
            ]);
            $jobId = (int) $pdo->lastInsertId();

            try {
                Security::logActivity((int) $userId, 'job_created', $form['title']);
            } catch (Throwable $e) {
                error_log($e->getMessage());
            }

            Security::regenerateCSRF();
            if ($isPaidPackage) {
                $txnRef = jm_job_payment_reference();
                $stmt = $pdo->prepare("
                    INSERT INTO transactions (user_id, plan, amount, credits, txn_ref, method, status, created_at)
                    VALUES (?, ?, ?, 0, ?, 'paystack', 'pending', NOW())
                ");
                $stmt->execute([
                    (int) $userId,
                    jm_job_payment_plan_value($jobId, $package['id'], $package['name']),
                    (float) $package['price'],
                    $txnRef,
                ]);

                Session::flash('success', 'Job saved. Complete payment to publish it.');
                redirect('/jobmington/payments/job-posting.php?ref=' . urlencode($txnRef));
            }

            Mailer::sendJobPostingConfirmed(
                Session::get('email'),
                $company['name'],
                $form['title'],
                SITE_URL . '/jobs/view?id=' . $jobId
            );

            Session::flash('success', 'Job published.');
            redirect('/jobmington/employer/manage-jobs.php?posted=1');
        }
    }
}

$pageTitle = 'Post a Job | ' . SITE_NAME;
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?></title>
    <link rel="preload" as="font" type="font/ttf" href="/jobmington/assets/fonts/FuturaCyrillicDemi.ttf" crossorigin>
    <link rel="preload" as="font" type="font/ttf" href="/jobmington/assets/fonts/FuturaCyrillicBook.ttf" crossorigin>
    <link rel="stylesheet" href="/jobmington/assets/css/minimal-jobmington.css?v=brand-17">
</head>
<body class="jm-minimal">
    <div class="jm-shell">
        <header class="jm-header">
            <a class="jm-logo" href="/jobmington/"><img src="/jobmington/assets/images/badge.png?v=logo-8" alt=""><span>Jobmington</span></a>
            <?php
            /* This nav was hand-written with four links and decided what to
               show from $isEmployer rather than from whether anyone was signed
               in, so a signed-in seeker was shown "Sign in" and offered to
               create an account they already had. It renders the shared list
               now, with the signed-in state answered once. */
            require_once __DIR__ . '/../includes/navigation.php';
            jm_site_nav('/employer/', 'Primary');
            ?>
        </header>

        <section class="jm-section" style="padding-top:0;">
            <div class="jm-section-head">
                <div>
                    <p class="jm-kicker">Post a job</p>
                    <h1>Publish a clear role.</h1>
                    <p><?= $company ? e($company['name']) . ' jobs are attached to your company profile.' : 'Sign in as an employer and complete your company profile before posting.' ?></p>
                </div>
                <?php if ($isEmployer): ?><a class="jm-muted-link" href="/jobmington/employer/manage-jobs.php">Manage jobs</a><?php endif; ?>
            </div>

            <?php if (!$isLoggedIn): ?>
                <div class="jm-grid-2">
                    <div class="jm-card">
                        <h2>Sign in to post</h2>
                        <p>Use your employer account to publish and manage applications.</p>
                        <a class="jm-button" href="/jobmington/auth/login.php?redirect=<?= urlencode($postJobPath) ?>">Sign in</a>
                    </div>
                    <div class="jm-card">
                        <h2>Create employer account</h2>
                        <p>Set up your company profile, then publish your first role.</p>
                        <a class="jm-button secondary" href="/jobmington/auth/register.php?type=employer">Create account</a>
                    </div>
                </div>
            <?php elseif (!$isEmployer): ?>
                <?= jm_empty_state_card('access_restricted', [
                    'actions' => '<a class="jm-button" href="/jobmington/auth/register.php?type=employer">Create employer account</a>',
                ]) ?>
            <?php elseif (!$company): ?>
                <?= jm_empty_state_card('profile_incomplete', [
                    'actions' => '<a class="jm-button" href="/jobmington/employer/company-profile.php?setup=1">Set up company</a>',
                ]) ?>
            <?php else: ?>
                <?php if (!empty($errors)): ?>
                    <div class="jm-alert"><?php foreach ($errors as $error): ?><div><?= e($error) ?></div><?php endforeach; ?></div>
                <?php endif; ?>

                <form method="post" class="jm-grid-2">
                    <?= Security::csrfField() ?>
                    <main class="jm-panel">
                        <h2>Role details</h2>
                        <div class="jm-form-grid">
                            <div class="jm-field"><label for="title">Job title</label><input class="jm-input" id="title" name="title" value="<?= e($form['title']) ?>" required></div>
                            <div class="jm-field">
                                <label for="category_id">Category</label>
                                <select class="jm-select" id="category_id" name="category_id">
                                    <option value="">Select category</option>
                                    <?php foreach ($categories as $category): ?><option value="<?= (int) $category['category_id'] ?>" <?= (int) $form['category_id'] === (int) $category['category_id'] ? 'selected' : '' ?>><?= e($category['name']) ?></option><?php endforeach; ?>
                                </select>
                            </div>
                            <div class="jm-field">
                                <label for="job_type">Job type</label>
                                <select class="jm-select" id="job_type" name="job_type" required>
                                    <?php foreach (JOB_TYPES as $type): ?><option value="<?= e($type) ?>" <?= $form['job_type'] === $type ? 'selected' : '' ?>><?= e($type) ?></option><?php endforeach; ?>
                                </select>
                            </div>
                            <div class="jm-field">
                                <label for="experience_level">Experience</label>
                                <select class="jm-select" id="experience_level" name="experience_level" required>
                                    <?php foreach (EXPERIENCE_LEVELS as $level): ?><option value="<?= e($level) ?>" <?= $form['experience_level'] === $level ? 'selected' : '' ?>><?= e($level) ?></option><?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="jm-field" style="margin-top:18px;"><label for="description">Description</label><textarea class="jm-textarea" id="description" name="description" required><?= e($form['description']) ?></textarea></div>
                        <div class="jm-field" style="margin-top:18px;"><label for="requirements">Requirements</label><textarea class="jm-textarea" id="requirements" name="requirements"><?= e($form['requirements']) ?></textarea></div>
                        <div class="jm-field" style="margin-top:18px;"><label for="benefits">Benefits</label><textarea class="jm-textarea" id="benefits" name="benefits"><?= e($form['benefits']) ?></textarea></div>
                    </main>

                    <aside class="jm-panel">
                        <h2>Location and apply</h2>
                        <div class="jm-stack">
                            <div class="jm-field">
                                <label for="country_id">Country</label>
                                <select class="jm-select" id="country_id" name="country_id" required>
                                    <option value="">Select country</option>
                                    <?php foreach ($countries as $country): ?><option value="<?= (int) $country['country_id'] ?>" <?= (int) $form['country_id'] === (int) $country['country_id'] ? 'selected' : '' ?>><?= e($country['name']) ?></option><?php endforeach; ?>
                                </select>
                            </div>
                            <div class="jm-field"><label for="city">City</label><input class="jm-input" id="city" name="city" value="<?= e($form['city']) ?>" placeholder="Lagos, Remote, Nairobi"></div>
                            <div class="jm-field"><label for="application_email">Application email</label><input class="jm-input" id="application_email" type="email" name="application_email" value="<?= e($form['application_email']) ?>"></div>
                            <div class="jm-field"><label for="application_url">External application URL</label><input class="jm-input" id="application_url" type="url" name="application_url" value="<?= e($form['application_url']) ?>" placeholder="Leave empty for Jobmington applications"></div>
                            <div class="jm-field"><label for="deadline">Deadline</label><input class="jm-input" id="deadline" type="date" name="deadline" value="<?= e($form['deadline']) ?>"></div>
                        </div>

                        <h2 style="margin-top:28px;">Salary</h2>
                        <div class="jm-form-grid">
                            <div class="jm-field"><label for="salary_min">Minimum</label><input class="jm-input" id="salary_min" type="number" name="salary_min" value="<?= e((string) $form['salary_min']) ?>"></div>
                            <div class="jm-field"><label for="salary_max">Maximum</label><input class="jm-input" id="salary_max" type="number" name="salary_max" value="<?= e((string) $form['salary_max']) ?>"></div>
                        </div>
                        <div class="jm-field" style="margin-top:18px;"><label for="salary_currency">Currency</label><input class="jm-input" id="salary_currency" name="salary_currency" value="<?= e($form['salary_currency']) ?>" maxlength="10"></div>
                        <label style="display:block;margin-top:18px;"><input type="checkbox" name="show_salary" <?= $form['show_salary'] ? 'checked' : '' ?>> Show salary on the job page</label>

                        <h2 style="margin-top:28px;">Promotion</h2>
                        <div class="jm-package-options">
                            <?php foreach ($packages as $option): ?>
                                <label class="jm-package-choice">
                                    <input type="radio" name="posting_package" value="<?= e($option['id']) ?>" <?= $form['posting_package'] === $option['id'] ? 'checked' : '' ?>>
                                    <span class="jm-package-choice-body">
                                        <span>
                                            <strong><?= e($option['name']) ?></strong>
                                            <em><?= e($option['badge']) ?></em>
                                        </span>
                                        <b><?= e(jm_money_ngn((float) $option['price'])) ?></b>
                                        <small><?= e($option['description']) ?></small>
                                        <ul class="jm-package-feature-list">
                                            <?php foreach ($option['features'] as $feature): ?>
                                                <li><?= e($feature) ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                        <?php if ((float) $option['price'] > 0): ?>
                                            <small class="jm-package-note">Saved first. Published after payment succeeds.</small>
                                        <?php endif; ?>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        </div>

                        <div class="jm-form-actions">
                            <button class="jm-button" type="submit">Continue</button>
                            <a class="jm-button secondary" href="/jobmington/employer/dashboard.php">Cancel</a>
                        </div>
                    </aside>
                </form>
            <?php endif; ?>
        </section>

        <?php jm_minimal_footer(); ?>
    </div>
</body>
</html>
