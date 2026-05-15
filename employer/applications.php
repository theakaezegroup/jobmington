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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::verifyCSRF()) {
        Session::flash('error', 'Please refresh the page and try again.');
        redirect('/jobmington/employer/applications.php');
    }

    $applicationId = (int) ($_POST['application_id'] ?? 0);
    $status = $_POST['status'] ?? '';

    if ($applicationId > 0 && in_array($status, APPLICATION_STATUSES, true)) {
        $stmt = $pdo->prepare("
            SELECT ja.application_id, ja.user_id, j.title
            FROM job_applications ja
            JOIN jobs j ON ja.job_id = j.job_id
            WHERE ja.application_id = ? AND j.company_id = ?
            LIMIT 1
        ");
        $stmt->execute([$applicationId, $companyId]);
        $application = $stmt->fetch();

        if ($application) {
            $stmt = $pdo->prepare("UPDATE job_applications SET status = ?, updated_at = NOW() WHERE application_id = ?");
            $stmt->execute([$status, $applicationId]);

            if (function_exists('sendNotification')) {
                sendNotification(
                    (int) $application['user_id'],
                    'application_update',
                    'Application status updated',
                    'Your application for "' . $application['title'] . '" is now ' . jm_employer_status_text($status) . '.',
                    '/seeker/applications.php'
                );
            }

            Session::flash('success', 'Application updated.');
        }
    }

    Security::regenerateCSRF();
    redirect('/jobmington/employer/applications.php?' . http_build_query(array_filter([
        'job' => $_GET['job'] ?? null,
        'status' => $_GET['status'] ?? null,
        'q' => $_GET['q'] ?? null,
    ])));
}

$jobFilter = (int) ($_GET['job'] ?? 0);
$statusFilter = Security::clean($_GET['status'] ?? '');
$search = Security::clean($_GET['q'] ?? '');
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 20;

$where = ['j.company_id = ?'];
$params = [$companyId];

if ($jobFilter > 0) {
    $where[] = 'ja.job_id = ?';
    $params[] = $jobFilter;
}

if ($statusFilter !== '' && in_array($statusFilter, APPLICATION_STATUSES, true)) {
    $where[] = 'ja.status = ?';
    $params[] = $statusFilter;
}

if ($search !== '') {
    $where[] = '(u.full_name LIKE ? OR u.email LIKE ? OR j.title LIKE ?)';
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like);
}

$whereClause = implode(' AND ', $where);

$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM job_applications ja
    JOIN jobs j ON ja.job_id = j.job_id
    LEFT JOIN users u ON ja.user_id = u.user_id
    WHERE {$whereClause}
");
$stmt->execute($params);
$totalApplications = (int) $stmt->fetchColumn();
$pagination = paginate($totalApplications, $perPage, $page);

$stmt = $pdo->prepare("
    SELECT ja.*, j.title AS job_title, j.job_id,
           u.full_name, u.email, u.phone, u.profile_image, u.headline
    FROM job_applications ja
    JOIN jobs j ON ja.job_id = j.job_id
    LEFT JOIN users u ON ja.user_id = u.user_id
    WHERE {$whereClause}
    ORDER BY ja.applied_at DESC
    LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}
");
$stmt->execute($params);
$applications = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT job_id, title FROM jobs WHERE company_id = ? ORDER BY posted_at DESC");
$stmt->execute([$companyId]);
$companyJobs = $stmt->fetchAll();

$statusCounts = [];
$stmt = $pdo->prepare("
    SELECT ja.status, COUNT(*) AS total
    FROM job_applications ja
    JOIN jobs j ON ja.job_id = j.job_id
    WHERE j.company_id = ?
    GROUP BY ja.status
");
$stmt->execute([$companyId]);
while ($row = $stmt->fetch()) {
    $statusCounts[$row['status']] = (int) $row['total'];
}

$selectedJobTitle = '';
foreach ($companyJobs as $companyJob) {
    if ((int) $companyJob['job_id'] === $jobFilter) {
        $selectedJobTitle = $companyJob['title'];
        break;
    }
}

$pageTitle = 'Applications | ' . SITE_NAME;
jm_employer_header($pageTitle, 'applications');
?>

<section class="jm-section" style="padding-top:0;">
    <div class="jm-section-head">
        <div>
            <p class="jm-kicker">Applications</p>
            <h1><?= $selectedJobTitle ? 'Applications for ' . e($selectedJobTitle) : 'Review candidates.' ?></h1>
            <p><?= number_format($totalApplications) ?> application<?= $totalApplications === 1 ? '' : 's' ?> across <?= e($company['name']) ?> roles.</p>
        </div>
        <a class="jm-button" href="/jobmington/employer/manage-jobs.php">Manage jobs</a>
    </div>

    <?php jm_employer_flash(); ?>

    <nav class="jm-tabs" aria-label="Application status">
        <a class="<?= $statusFilter === '' ? 'active' : '' ?>" href="/jobmington/employer/applications.php?<?= http_build_query(array_filter(['job' => $jobFilter ?: null, 'q' => $search])) ?>">All <span>(<?= number_format($totalApplications) ?>)</span></a>
        <?php foreach (APPLICATION_STATUSES as $status): ?>
            <a class="<?= $statusFilter === $status ? 'active' : '' ?>" href="/jobmington/employer/applications.php?<?= http_build_query(array_filter(['job' => $jobFilter ?: null, 'q' => $search, 'status' => $status])) ?>">
                <?= e(jm_employer_status_text($status)) ?> <span>(<?= number_format($statusCounts[$status] ?? 0) ?>)</span>
            </a>
        <?php endforeach; ?>
    </nav>

    <form method="get" class="jm-filter-grid" style="grid-template-columns:minmax(0,1fr) 260px auto;">
        <div class="jm-field">
            <label for="q">Search applications</label>
            <input class="jm-input" id="q" name="q" value="<?= e($search) ?>" placeholder="Candidate, email, or job title">
        </div>
        <div class="jm-field">
            <label for="job">Job</label>
            <select class="jm-select" id="job" name="job">
                <option value="">All jobs</option>
                <?php foreach ($companyJobs as $job): ?>
                    <option value="<?= (int) $job['job_id'] ?>" <?= $jobFilter === (int) $job['job_id'] ? 'selected' : '' ?>><?= e($job['title']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <input type="hidden" name="status" value="<?= e($statusFilter) ?>">
        <button class="jm-button" type="submit">Filter</button>
    </form>

    <?php if (empty($applications)): ?>
        <div class="jm-empty">
            <h2>No applications found</h2>
            <p>New applications will appear here as candidates apply.</p>
            <a class="jm-button" href="/jobmington/employer/manage-jobs.php">View jobs</a>
        </div>
    <?php else: ?>
        <table class="jm-table">
            <thead>
                <tr>
                    <th>Candidate</th>
                    <th>Job</th>
                    <th>Status</th>
                    <th>Applied</th>
                    <th>Update</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($applications as $application): ?>
                    <tr>
                        <td>
                            <a class="jm-muted-link" href="/jobmington/employer/view-applicant.php?id=<?= (int) $application['application_id'] ?>">
                                <?= e($application['full_name'] ?: $application['email'] ?: 'Applicant') ?>
                            </a>
                            <?php if (!empty($application['headline'])): ?><div class="jm-small"><?= e($application['headline']) ?></div><?php endif; ?>
                            <div class="jm-small"><?= e($application['email'] ?? '') ?></div>
                        </td>
                        <td>
                            <a class="jm-muted-link" href="/jobmington/jobs/view.php?id=<?= (int) $application['job_id'] ?>"><?= e($application['job_title']) ?></a>
                        </td>
                        <td><span class="jm-badge"><?= e(jm_employer_status_text($application['status'] ?? 'pending')) ?></span></td>
                        <td><?= e(formatDate($application['applied_at'])) ?></td>
                        <td>
                            <form method="post" class="jm-inline-actions">
                                <?= Security::csrfField() ?>
                                <input type="hidden" name="application_id" value="<?= (int) $application['application_id'] ?>">
                                <select class="jm-select" name="status" style="min-height:38px;width:150px;">
                                    <?php foreach (APPLICATION_STATUSES as $status): ?>
                                        <option value="<?= e($status) ?>" <?= ($application['status'] ?? 'pending') === $status ? 'selected' : '' ?>><?= e(jm_employer_status_text($status)) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button class="jm-button secondary" style="min-height:38px;" type="submit">Save</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="jm-form-actions" style="justify-content:center;">
            <?php if ($pagination['has_previous']): ?>
                <a class="jm-button secondary" href="/jobmington/employer/applications.php?<?= http_build_query(array_filter(['job' => $jobFilter ?: null, 'status' => $statusFilter, 'q' => $search, 'page' => $pagination['previous_page']])) ?>">Previous</a>
            <?php endif; ?>
            <?php if ($pagination['has_next']): ?>
                <a class="jm-button secondary" href="/jobmington/employer/applications.php?<?= http_build_query(array_filter(['job' => $jobFilter ?: null, 'status' => $statusFilter, 'q' => $search, 'page' => $pagination['next_page']])) ?>">Next</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</section>

<?php jm_employer_footer('/jobmington/employer/dashboard.php', 'Employer dashboard'); ?>
