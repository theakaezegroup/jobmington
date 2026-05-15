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

Session::start();
Session::requireRole(USER_TYPE_EMPLOYER);

$pdo = db();
$userId = Session::userId();
$company = jm_employer_company_or_redirect($pdo, $userId);
$companyId = (int) $company['company_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::verifyCSRF()) {
        Session::flash('error', 'Please refresh the page and try again.');
        redirect('/jobmington/employer/manage-jobs.php');
    }

    $action = $_POST['action'] ?? '';
    $jobId = (int) ($_POST['job_id'] ?? 0);

    $stmt = $pdo->prepare("SELECT job_id, title FROM jobs WHERE job_id = ? AND company_id = ? LIMIT 1");
    $stmt->execute([$jobId, $companyId]);
    $job = $stmt->fetch();

    if ($job) {
        $paymentStmt = $pdo->prepare("
            SELECT txn_ref, status
            FROM transactions
            WHERE user_id = ?
              AND amount > 0
              AND plan LIKE ?
            ORDER BY id DESC
            LIMIT 1
        ");
        $paymentStmt->execute([$userId, '%job:' . $jobId . '%']);
        $payment = $paymentStmt->fetch();
        $paymentBlocked = $payment && $payment['status'] !== 'completed';

        if ($action === 'activate' || $action === 'deactivate') {
            if ($action === 'activate' && $paymentBlocked) {
                Session::flash('error', 'Complete payment before opening this paid listing.');
                if (!empty($payment['txn_ref']) && $payment['status'] === 'pending') {
                    redirect('/jobmington/payments/job-posting.php?ref=' . urlencode($payment['txn_ref']));
                }
                redirect('/jobmington/employer/manage-jobs.php');
            }
            $pdo->prepare("UPDATE jobs SET is_active = ? WHERE job_id = ? AND company_id = ?")
                ->execute([$action === 'activate' ? 1 : 0, $jobId, $companyId]);
            Session::flash('success', $action === 'activate' ? 'Job opened.' : 'Job closed.');
        } elseif ($action === 'feature' || $action === 'unfeature') {
            if ($action === 'feature' && $paymentBlocked) {
                Session::flash('error', 'Complete payment before featuring this paid listing.');
                if (!empty($payment['txn_ref']) && $payment['status'] === 'pending') {
                    redirect('/jobmington/payments/job-posting.php?ref=' . urlencode($payment['txn_ref']));
                }
                redirect('/jobmington/employer/manage-jobs.php');
            }
            $pdo->prepare("UPDATE jobs SET is_featured = ? WHERE job_id = ? AND company_id = ?")
                ->execute([$action === 'feature' ? 1 : 0, $jobId, $companyId]);
            Session::flash('success', $action === 'feature' ? 'Job featured.' : 'Job unfeatured.');
        } elseif ($action === 'delete') {
            $pdo->prepare("DELETE FROM job_applications WHERE job_id = ?")->execute([$jobId]);
            $pdo->prepare("DELETE FROM saved_jobs WHERE job_id = ?")->execute([$jobId]);
            $pdo->prepare("DELETE FROM jobs WHERE job_id = ? AND company_id = ?")->execute([$jobId, $companyId]);
            Session::flash('success', 'Job deleted.');
        }

        try {
            Security::logActivity($userId, 'job_' . $action, $job['title']);
        } catch (Throwable $e) {
            error_log($e->getMessage());
        }
    }

    Security::regenerateCSRF();
    redirect('/jobmington/employer/manage-jobs.php');
}

$filter = Security::clean($_GET['filter'] ?? 'all');
$search = Security::clean($_GET['q'] ?? '');
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 10;

$where = ['j.company_id = ?'];
$params = [$companyId];

if ($filter === 'active') {
    $where[] = 'j.is_active = 1';
} elseif ($filter === 'inactive') {
    $where[] = 'j.is_active = 0';
} elseif ($filter === 'featured') {
    $where[] = 'j.is_featured = 1';
} elseif ($filter === 'expired') {
    $where[] = "((j.deadline IS NOT NULL AND j.deadline < CURDATE()) OR (j.expires_at IS NOT NULL AND j.expires_at < CURDATE()))";
}

if ($search !== '') {
    $where[] = '(j.title LIKE ? OR j.city LIKE ? OR jc.name LIKE ?)';
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like);
}

$whereClause = implode(' AND ', $where);

$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM jobs j
    LEFT JOIN job_categories jc ON j.category_id = jc.category_id
    WHERE {$whereClause}
");
$stmt->execute($params);
$totalJobs = (int) $stmt->fetchColumn();
$pagination = paginate($totalJobs, $perPage, $page);

$stmt = $pdo->prepare("
    SELECT j.*, c.name AS country_name, c.currency_symbol, jc.name AS category_name,
           COUNT(ja.application_id) AS application_total,
           (
               SELECT t.status
               FROM transactions t
               WHERE t.user_id = ?
                 AND t.amount > 0
                 AND t.plan LIKE CONCAT('%job:', j.job_id, '%')
               ORDER BY t.id DESC
               LIMIT 1
           ) AS payment_status,
           (
               SELECT t.txn_ref
               FROM transactions t
               WHERE t.user_id = ?
                 AND t.amount > 0
                 AND t.plan LIKE CONCAT('%job:', j.job_id, '%')
               ORDER BY t.id DESC
               LIMIT 1
           ) AS payment_ref
    FROM jobs j
    LEFT JOIN countries c ON j.country_id = c.country_id
    LEFT JOIN job_categories jc ON j.category_id = jc.category_id
    LEFT JOIN job_applications ja ON j.job_id = ja.job_id
    WHERE {$whereClause}
    GROUP BY j.job_id
    ORDER BY j.posted_at DESC
    LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}
");
$stmt->execute(array_merge([$userId, $userId], $params));
$jobs = $stmt->fetchAll();

$stmt = $pdo->prepare("
    SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) AS active,
        SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) AS inactive,
        SUM(CASE WHEN is_featured = 1 THEN 1 ELSE 0 END) AS featured
    FROM jobs
    WHERE company_id = ?
");
$stmt->execute([$companyId]);
$stats = $stmt->fetch() ?: [];

$pageTitle = 'Jobs | ' . SITE_NAME;
jm_employer_header($pageTitle, 'jobs');
?>

<section class="jm-section" style="padding-top:0;">
    <div class="jm-section-head">
        <div>
            <p class="jm-kicker">Jobs</p>
            <h1>Manage your job listings.</h1>
            <p><?= e($company['name']) ?> roles, applications, status, and public visibility in one place.</p>
        </div>
        <a class="jm-button" href="/jobmington/employer/post-job.php">Post a job</a>
    </div>

    <?php jm_employer_flash(); ?>

    <?php if (($_GET['payment'] ?? '') === 'success'): ?>
        <?= jm_notification_state_card('job_activated', [
            'compact' => true,
            'inline' => true,
            'aria_live' => 'polite',
        ]) ?>
    <?php elseif (($_GET['posted'] ?? '') === '1'): ?>
        <?= jm_notification_state_card('job_posted_successfully', [
            'compact' => true,
            'inline' => true,
            'aria_live' => 'polite',
        ]) ?>
    <?php endif; ?>

    <div class="jm-stat-grid">
        <a class="jm-stat" href="/jobmington/employer/manage-jobs.php" style="text-decoration:none;">
            <strong><?= number_format((int) ($stats['total'] ?? 0)) ?></strong>
            <span class="jm-small">Total jobs</span>
        </a>
        <a class="jm-stat" href="/jobmington/employer/manage-jobs.php?filter=active" style="text-decoration:none;">
            <strong><?= number_format((int) ($stats['active'] ?? 0)) ?></strong>
            <span class="jm-small">Active</span>
        </a>
        <a class="jm-stat" href="/jobmington/employer/manage-jobs.php?filter=inactive" style="text-decoration:none;">
            <strong><?= number_format((int) ($stats['inactive'] ?? 0)) ?></strong>
            <span class="jm-small">Closed</span>
        </a>
        <a class="jm-stat" href="/jobmington/employer/manage-jobs.php?filter=featured" style="text-decoration:none;">
            <strong><?= number_format((int) ($stats['featured'] ?? 0)) ?></strong>
            <span class="jm-small">Featured</span>
        </a>
    </div>
</section>

<section class="jm-section">
    <nav class="jm-tabs" aria-label="Job filters">
        <?php foreach (['all' => 'All', 'active' => 'Active', 'inactive' => 'Closed', 'featured' => 'Featured', 'expired' => 'Expired'] as $key => $label): ?>
            <a class="<?= $filter === $key ? 'active' : '' ?>" href="/jobmington/employer/manage-jobs.php?<?= http_build_query(array_filter(['filter' => $key === 'all' ? null : $key, 'q' => $search])) ?>"><?= e($label) ?></a>
        <?php endforeach; ?>
    </nav>

    <form method="get" class="jm-filter-grid" style="grid-template-columns:minmax(0,1fr) 170px auto;">
        <div class="jm-field">
            <label for="q">Search jobs</label>
            <input class="jm-input" id="q" name="q" value="<?= e($search) ?>" placeholder="Title, city, or category">
        </div>
        <div class="jm-field">
            <label for="filter">Status</label>
            <select class="jm-select" id="filter" name="filter">
                <?php foreach (['all' => 'All jobs', 'active' => 'Active', 'inactive' => 'Closed', 'featured' => 'Featured', 'expired' => 'Expired'] as $key => $label): ?>
                    <option value="<?= e($key) ?>" <?= $filter === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button class="jm-button" type="submit">Search</button>
    </form>

    <?php if (empty($jobs)): ?>
        <?= jm_empty_state_card('no_jobs_found', [
            'actions' => '<a class="jm-button" href="/jobmington/employer/post-job.php">Post a job</a>',
        ]) ?>
    <?php else: ?>
        <table class="jm-table">
            <thead>
                <tr>
                    <th>Job</th>
                    <th>Status</th>
                    <th>Applications</th>
                    <th>Views</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($jobs as $job): ?>
                    <?php $paymentPending = !empty($job['payment_status']) && $job['payment_status'] !== 'completed'; ?>
                    <tr>
                        <td>
                            <strong><?= e($job['title']) ?></strong>
                            <div class="jm-small">
                                <?= e($job['city'] ?: ($job['country_name'] ?? 'Remote')) ?>
                                <?php if (!empty($job['job_type'])): ?> / <?= e($job['job_type']) ?><?php endif; ?>
                                <?php if (!empty($job['category_name'])): ?> / <?= e($job['category_name']) ?><?php endif; ?>
                            </div>
                            <div class="jm-small">Posted <?= e(formatDate($job['posted_at'])) ?></div>
                        </td>
                        <td>
                            <span class="jm-badge"><?= $paymentPending ? 'Payment pending' : ($job['is_active'] ? 'Active' : 'Closed') ?></span>
                            <?php if ($job['is_featured']): ?><span class="jm-badge">Featured</span><?php endif; ?>
                        </td>
                        <td><a class="jm-muted-link" href="/jobmington/employer/applications.php?job=<?= (int) $job['job_id'] ?>"><?= number_format((int) $job['application_total']) ?></a></td>
                        <td><?= number_format((int) ($job['views'] ?? 0)) ?></td>
                        <td>
                            <div class="jm-inline-actions">
                                <a class="jm-muted-link" href="/jobmington/jobs/view.php?id=<?= (int) $job['job_id'] ?>">View</a>
                                <a class="jm-muted-link" href="/jobmington/employer/edit-job.php?id=<?= (int) $job['job_id'] ?>">Edit</a>
                                <?php if ($paymentPending && !empty($job['payment_ref']) && $job['payment_status'] === 'pending'): ?>
                                    <a class="jm-muted-link" href="/jobmington/payments/job-posting.php?ref=<?= urlencode($job['payment_ref']) ?>">Pay now</a>
                                <?php else: ?>
                                    <form method="post">
                                        <?= Security::csrfField() ?>
                                        <input type="hidden" name="job_id" value="<?= (int) $job['job_id'] ?>">
                                        <input type="hidden" name="action" value="<?= $job['is_active'] ? 'deactivate' : 'activate' ?>">
                                        <button class="jm-muted-link" style="border:0;background:none;padding:0;cursor:pointer;" type="submit"><?= $job['is_active'] ? 'Close' : 'Open' ?></button>
                                    </form>
                                    <form method="post">
                                        <?= Security::csrfField() ?>
                                        <input type="hidden" name="job_id" value="<?= (int) $job['job_id'] ?>">
                                        <input type="hidden" name="action" value="<?= $job['is_featured'] ? 'unfeature' : 'feature' ?>">
                                        <button class="jm-muted-link" style="border:0;background:none;padding:0;cursor:pointer;" type="submit"><?= $job['is_featured'] ? 'Unfeature' : 'Feature' ?></button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="jm-form-actions" style="justify-content:center;">
            <?php if ($pagination['has_previous']): ?>
                <a class="jm-button secondary" href="/jobmington/employer/manage-jobs.php?<?= http_build_query(array_filter(['filter' => $filter === 'all' ? null : $filter, 'q' => $search, 'page' => $pagination['previous_page']])) ?>">Previous</a>
            <?php endif; ?>
            <?php if ($pagination['has_next']): ?>
                <a class="jm-button secondary" href="/jobmington/employer/manage-jobs.php?<?= http_build_query(array_filter(['filter' => $filter === 'all' ? null : $filter, 'q' => $search, 'page' => $pagination['next_page']])) ?>">Next</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</section>

<?php jm_employer_footer('/jobmington/employer/dashboard.php', 'Employer dashboard'); ?>
