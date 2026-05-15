<?php
/**
 * JOBMINGTON - Admin Users
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

// Fetch users (recent 50)
try {
    $stmt = $pdo->query("SELECT user_id, full_name, email, user_type, created_at FROM users ORDER BY created_at DESC LIMIT 50");
    $users = $stmt->fetchAll();
} catch (Exception $e) {
    $users = [];
}

$pageTitle = 'Manage Users - ' . SITE_NAME;
require_once __DIR__ . '/../includes/header.php';
?>

<div class="bg-gray-100 min-h-screen py-8">
    <div class="max-w-6xl mx-auto px-4">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-heading font-bold">Users</h1>
            <a href="/jobmington/admin/users.php?create=1" class="bg-primary px-4 py-2 text-white rounded-lg">Add User</a>
        </div>

        <div class="bg-white rounded-lg shadow-md overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Role</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Joined</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-100">
                    <?php if (empty($users)): ?>
                        <tr><td colspan="5" class="px-6 py-4 text-gray-500">No users yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($users as $u): ?>
                        <tr>
                            <td class="px-6 py-4"><?= e($u['user_id']) ?></td>
                            <td class="px-6 py-4"><?= e($u['full_name']) ?></td>
                            <td class="px-6 py-4"><?= e($u['email']) ?></td>
                            <td class="px-6 py-4"><?= e(ucfirst($u['user_type'])) ?></td>
                            <td class="px-6 py-4"><?= e(formatDateTime($u['created_at'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php';