<?php
/**
 * JOBMINGTON - Admin Jobs
 */

define('JOBMINGTON', true);
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';

Session::start();
Session::requireAdmin();

$pdo = db();

// Handle POST actions (delete, toggle_active)
if (isPost()) {
    if (!Security::verifyCSRF()) {
        Session::flash('error', 'Invalid CSRF token.');
        redirect('/jobmington/admin/jobs.php');
    }

    $action = post('action', '');
    $jobId = (int) post('job_id', 0);

    if ($action === 'delete' && $jobId > 0) {
        try {
            // Remove related saved jobs and applications if present
            $stmt = $pdo->prepare("DELETE FROM saved_jobs WHERE job_id = ?");
            $stmt->execute([$jobId]);

            // If there is a job applications table, attempt to remove
            try {
                $stmt = $pdo->prepare("DELETE FROM job_applications WHERE job_id = ?");
                $stmt->execute([$jobId]);
            } catch (Exception $e) {
                // ignore if table doesn't exist
            }

            $stmt = $pdo->prepare("DELETE FROM jobs WHERE job_id = ?");
            $stmt->execute([$jobId]);

            Session::flash('success', 'Job deleted successfully.');
        } catch (Exception $e) {
            error_log('Admin delete job error: ' . $e->getMessage());
            Session::flash('error', 'Failed to delete job.');
        }

        redirect('/jobmington/admin/jobs.php');
    }

    if ($action === 'toggle_active' && $jobId > 0) {
        $value = post('value', '0') === '1' ? 1 : 0;
        try {
            $stmt = $pdo->prepare("UPDATE jobs SET is_active = ? WHERE job_id = ?");
            $stmt->execute([$value, $jobId]);
            Session::flash('success', 'Job status updated.');
        } catch (Exception $e) {
            error_log('Admin toggle job active error: ' . $e->getMessage());
            Session::flash('error', 'Failed to update job status.');
        }

        redirect('/jobmington/admin/jobs.php');
    }
}

// Fetch recent jobs
try {
    $stmt = $pdo->query("SELECT j.job_id, j.title, j.slug, j.is_active, j.is_featured, j.posted_at, c.name AS company_name
                         FROM jobs j
                         LEFT JOIN companies c ON j.company_id = c.company_id
                         ORDER BY j.posted_at DESC
                         LIMIT 200");
    $jobs = $stmt->fetchAll();
} catch (Exception $e) {
    $jobs = [];
}

$pageTitle = 'Manage Jobs - ' . SITE_NAME;
require_once __DIR__ . '/../includes/header.php';
?>

<div class="bg-gray-100 min-h-screen py-8">
    <div class="max-w-6xl mx-auto px-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
            <h1 class="text-2xl font-heading font-bold">Jobs</h1>
            <a href="/jobmington/employer/post-job.php" class="bg-primary px-4 py-2 text-white rounded-lg">Add Job</a>
        </div>

        <div class="bg-white rounded-lg shadow-md overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 jm-stacktable" data-title-col="1">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Title</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Company</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Featured</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Posted</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-100">
                    <?php if (empty($jobs)): ?>
                        <tr><td colspan="7" class="px-6 py-4 text-gray-500">No jobs found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($jobs as $j): ?>
                        <tr>
                            <td class="px-6 py-4"><?= e($j['job_id']) ?></td>
                            <td class="px-6 py-4"><?= e($j['title']) ?></td>
                            <td class="px-6 py-4"><?= e($j['company_name'] ?? '—') ?></td>
                            <td class="px-6 py-4"><?= $j['is_active'] ? '<span class="text-green-600">Active</span>' : '<span class="text-gray-500">Inactive</span>' ?></td>
                            <td class="px-6 py-4"><?= $j['is_featured'] ? '<span class="text-yellow-600">Yes</span>' : 'No' ?></td>
                            <td class="px-6 py-4"><?= e(formatDateTime($j['posted_at'])) ?></td>
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <a href="/jobmington/jobs/view.php?id=<?= e($j['job_id']) ?>" class="text-primary text-sm">View</a>
                                    <a href="/jobmington/employer/edit-job.php?id=<?= e($j['job_id']) ?>" class="text-sm">Edit</a>

                                    <form method="POST" action="/jobmington/admin/jobs.php" class="inline-block">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="toggle_active" />
                                        <input type="hidden" name="job_id" value="<?= e($j['job_id']) ?>" />
                                        <input type="hidden" name="value" value="<?= $j['is_active'] ? '0' : '1' ?>" />
                                        <button type="submit" class="text-sm text-gray-700 hover:text-primary"><?= $j['is_active'] ? 'Deactivate' : 'Activate' ?></button>
                                    </form>

                                    <form method="POST" action="/jobmington/admin/jobs.php" class="inline-block" onsubmit="return confirm('Delete this job?');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete" />
                                        <input type="hidden" name="job_id" value="<?= e($j['job_id']) ?>" />
                                        <button type="submit" class="text-sm text-red-600">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php';