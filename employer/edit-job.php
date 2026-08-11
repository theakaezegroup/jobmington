<?php
define('JOBMINGTON', true);
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/_employer_helpers.php';

Session::start();
Session::requireRole(USER_TYPE_EMPLOYER);

$pdo = db();
$userId = Session::userId();
$company = jm_employer_company_or_redirect($pdo, $userId);
$companyId = (int) $company['company_id'];
$jobId = (int) ($_GET['id'] ?? 0);

if ($jobId <= 0) {
    redirect('/jobmington/employer/manage-jobs.php');
}

$stmt = $pdo->prepare("
    SELECT j.*, c.name AS country_name, c.currency_symbol
    FROM jobs j
    LEFT JOIN countries c ON j.country_id = c.country_id
    WHERE j.job_id = ? AND j.company_id = ?
    LIMIT 1
");
$stmt->execute([$jobId, $companyId]);
$job = $stmt->fetch();

if (!$job) {
    Session::flash('error', 'Job not found.');
    redirect('/jobmington/employer/manage-jobs.php');
}

$categories = $pdo->query("SELECT * FROM job_categories ORDER BY name")->fetchAll();
$countries = $pdo->query("SELECT * FROM countries WHERE is_active = 1 ORDER BY name")->fetchAll();
$errors = [];

$form = [
    'title' => $job['title'] ?? '',
    'category_id' => $job['category_id'] ?? '',
    'description' => $job['description'] ?? '',
    'requirements' => $job['requirements'] ?? '',
    'benefits' => $job['benefits'] ?? '',
    'job_type' => $job['job_type'] ?? 'Full-time',
    'experience_level' => $job['experience_level'] ?? 'Entry',
    'salary_min' => $job['salary_min'] ?? '',
    'salary_max' => $job['salary_max'] ?? '',
    'salary_currency' => $job['salary_currency'] ?? 'NGN',
    'show_salary' => (int) ($job['show_salary'] ?? 0),
    'country_id' => $job['country_id'] ?? '',
    'city' => $job['city'] ?? '',
    'application_email' => $job['application_email'] ?? '',
    'application_url' => $job['apply_link'] ?? '',
    // expires_at may carry a time; the date input needs a bare Y-m-d.
    'deadline' => substr((string) ($job['expires_at'] ?? ''), 0, 10),
    'is_active' => (int) ($job['is_active'] ?? 1),
    'is_featured' => (int) ($job['is_featured'] ?? 0),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
        $form['is_active'] = isset($_POST['is_active']) ? 1 : 0;
        $form['is_featured'] = isset($_POST['is_featured']) ? 1 : 0;

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
            $slug = $form['title'] === ($job['title'] ?? '')
                ? ($job['slug'] ?: jm_employer_unique_slug($pdo, 'jobs', 'slug', $form['title'], $jobId, 'job_id'))
                : jm_employer_unique_slug($pdo, 'jobs', 'slug', $form['title'], $jobId, 'job_id');

            $stmt = $pdo->prepare("
                UPDATE jobs SET
                    category_id = ?, title = ?, slug = ?, description = ?, requirements = ?, benefits = ?,
                    job_type = ?, experience_level = ?, salary_min = ?, salary_max = ?, salary_currency = ?,
                    show_salary = ?, country_id = ?, city = ?, application_email = ?,
                    apply_link = ?, expires_at = COALESCE(?, expires_at), is_featured = ?, is_active = ?
                WHERE job_id = ? AND company_id = ?
            ");
            $stmt->execute([
                $form['category_id'] ?: null,
                $form['title'],
                $slug,
                $form['description'],
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
                $form['application_url'] ?: null,
                $form['deadline'] ?: null,
                $form['is_featured'],
                $form['is_active'],
                $jobId,
                $companyId,
            ]);

            try {
                Security::logActivity($userId, 'job_updated', $form['title']);
            } catch (Throwable $e) {
                error_log($e->getMessage());
            }

            Security::regenerateCSRF();
            Session::flash('success', 'Job saved.');
            redirect('/jobmington/employer/manage-jobs.php');
        }
    }
}

$pageTitle = 'Edit Job | ' . SITE_NAME;
jm_employer_header($pageTitle, 'jobs');
?>

<section class="jm-section" style="padding-top:0;">
    <div class="jm-section-head">
        <div>
            <p class="jm-kicker">Edit job</p>
            <h1><?= e($job['title']) ?></h1>
            <p><?= number_format((int) ($job['views'] ?? 0)) ?> views / <?= number_format((int) ($job['applications_count'] ?? 0)) ?> applications</p>
        </div>
        <div class="jm-form-actions">
            <a class="jm-button secondary" href="/jobmington/jobs/view.php?id=<?= (int) $jobId ?>">View public page</a>
            <a class="jm-button secondary" href="/jobmington/employer/manage-jobs.php">Back to jobs</a>
        </div>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="jm-alert">
            <?php foreach ($errors as $error): ?><div><?= e($error) ?></div><?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="post" class="jm-grid-2">
        <?= Security::csrfField() ?>
        <main class="jm-panel">
            <h2>Role details</h2>
            <div class="jm-form-grid">
                <div class="jm-field">
                    <label for="title">Job title</label>
                    <input class="jm-input" id="title" name="title" value="<?= e($form['title']) ?>" required>
                </div>
                <div class="jm-field">
                    <label for="category_id">Category</label>
                    <select class="jm-select" id="category_id" name="category_id">
                        <option value="">Select category</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= (int) $category['category_id'] ?>" <?= (int) $form['category_id'] === (int) $category['category_id'] ? 'selected' : '' ?>><?= e($category['name']) ?></option>
                        <?php endforeach; ?>
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

            <div class="jm-field" style="margin-top:18px;">
                <label for="description">Description</label>
                <textarea class="jm-textarea" id="description" name="description" required><?= e($form['description']) ?></textarea>
            </div>
            <div class="jm-field" style="margin-top:18px;">
                <label for="requirements">Requirements</label>
                <textarea class="jm-textarea" id="requirements" name="requirements"><?= e($form['requirements']) ?></textarea>
            </div>
            <div class="jm-field" style="margin-top:18px;">
                <label for="benefits">Benefits</label>
                <textarea class="jm-textarea" id="benefits" name="benefits"><?= e($form['benefits']) ?></textarea>
            </div>
        </main>

        <aside class="jm-panel">
            <h2>Location and apply</h2>
            <div class="jm-stack">
                <div class="jm-field">
                    <label for="country_id">Country</label>
                    <select class="jm-select" id="country_id" name="country_id" required>
                        <option value="">Select country</option>
                        <?php foreach ($countries as $country): ?>
                            <option value="<?= (int) $country['country_id'] ?>" <?= (int) $form['country_id'] === (int) $country['country_id'] ? 'selected' : '' ?>><?= e($country['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="jm-field">
                    <label for="city">City</label>
                    <input class="jm-input" id="city" name="city" value="<?= e($form['city']) ?>" placeholder="Lagos, Remote, Nairobi">
                </div>
                <div class="jm-field">
                    <label for="application_email">Application email</label>
                    <input class="jm-input" id="application_email" type="email" name="application_email" value="<?= e($form['application_email']) ?>">
                </div>
                <div class="jm-field">
                    <label for="application_url">External application URL</label>
                    <input class="jm-input" id="application_url" type="url" name="application_url" value="<?= e($form['application_url']) ?>" placeholder="Leave empty for Jobmington applications">
                </div>
                <div class="jm-field">
                    <label for="deadline">Deadline</label>
                    <input class="jm-input" id="deadline" type="date" name="deadline" value="<?= e($form['deadline']) ?>">
                </div>
            </div>

            <h2 style="margin-top:28px;">Salary</h2>
            <div class="jm-form-grid">
                <div class="jm-field">
                    <label for="salary_min">Minimum</label>
                    <input class="jm-input" id="salary_min" type="number" name="salary_min" value="<?= e((string) $form['salary_min']) ?>">
                </div>
                <div class="jm-field">
                    <label for="salary_max">Maximum</label>
                    <input class="jm-input" id="salary_max" type="number" name="salary_max" value="<?= e((string) $form['salary_max']) ?>">
                </div>
            </div>
            <div class="jm-field" style="margin-top:18px;">
                <label for="salary_currency">Currency</label>
                <input class="jm-input" id="salary_currency" name="salary_currency" value="<?= e($form['salary_currency']) ?>" maxlength="10">
            </div>

            <div class="jm-stack" style="margin-top:24px;">
                <label><input type="checkbox" name="show_salary" <?= $form['show_salary'] ? 'checked' : '' ?>> Show salary</label>
                <label><input type="checkbox" name="is_active" <?= $form['is_active'] ? 'checked' : '' ?>> Active</label>
                <label><input type="checkbox" name="is_featured" <?= $form['is_featured'] ? 'checked' : '' ?>> Featured</label>
            </div>

            <div class="jm-form-actions">
                <button class="jm-button" type="submit">Save job</button>
                <a class="jm-button secondary" href="/jobmington/employer/manage-jobs.php">Cancel</a>
            </div>
        </aside>
    </form>
</section>

<?php jm_employer_footer('/jobmington/employer/manage-jobs.php', 'Manage jobs'); ?>
