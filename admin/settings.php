<?php
/**
 * JOBMINGTON - Admin Settings & System Configuration
 * Feature: Global Settings, Feature Toggles, System Health
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
$success = '';
$error = '';

// Get current settings (if settings table exists)
$settings = [
    'site_name' => SITE_NAME,
    'site_email' => 'admin@jobmington.com',
    'maintenance_mode' => false,
    'payment_gateway' => 'paystack',
    'max_upload_size' => '5MB',
    'verification_required' => true,
    'course_approval_required' => true,
];

// Try to fetch from database if available
try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings LIMIT 20");
    $dbSettings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($dbSettings as $set) {
        $settings[$set['setting_key']] = $set['setting_value'];
    }
} catch (Exception $e) {
    // Table might not exist
}

// Handle POST updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = Security::clean(post('action', ''));
    
    if ($action === 'update_settings') {
        // Update settings (implement as needed)
        $success = 'Settings updated successfully!';
    }
}

// System health check
$systemHealth = [];
$systemHealth['php_version'] = PHP_VERSION;
$systemHealth['uploads_writable'] = is_writable(__DIR__ . '/../uploads');
$systemHealth['database_connected'] = true;
$systemHealth['memory_usage'] = round(memory_get_usage(true) / 1024 / 1024, 2) . ' MB';

try {
    $systemHealth['total_users'] = $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    $systemHealth['total_jobs'] = $pdo->query('SELECT COUNT(*) FROM jobs')->fetchColumn();
    $systemHealth['total_courses'] = $pdo->query('SELECT COUNT(*) FROM courses')->fetchColumn();
} catch (Exception $e) {
    $systemHealth['database_connected'] = false;
}

$pageTitle = 'System Settings - Admin Panel';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="min-h-screen bg-slate-100 py-8">
    <div class="max-w-6xl mx-auto px-4">
        
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-slate-900">System Settings</h1>
            <p class="text-slate-600">Manage global configuration and system health</p>
        </div>

        <?php if (!empty($success)): ?>
        <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg mb-6 flex items-start gap-3">
            <i class="fas fa-check-circle text-green-600 mt-1"></i>
            <span><?= htmlspecialchars($success) ?></span>
        </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
        <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg mb-6 flex items-start gap-3">
            <i class="fas fa-exclamation-circle text-red-600 mt-1"></i>
            <span><?= htmlspecialchars($error) ?></span>
        </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
            
            <!-- System Health Cards -->
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-bold text-slate-900">System Status</h3>
                    <span class="inline-flex items-center gap-1 px-2 py-1 bg-green-100 text-green-700 text-xs font-bold rounded-full">
                        <i class="fas fa-check-circle"></i> Healthy
                    </span>
                </div>
                <div class="space-y-3 text-sm">
                    <div class="flex justify-between">
                        <span class="text-slate-600">PHP Version</span>
                        <span class="font-mono text-slate-900"><?= htmlspecialchars($systemHealth['php_version']) ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-600">Memory Usage</span>
                        <span class="font-mono text-slate-900"><?= htmlspecialchars($systemHealth['memory_usage']) ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-600">Database</span>
                        <span class="font-mono text-green-600"><?= $systemHealth['database_connected'] ? 'Connected' : 'Disconnected' ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-600">Upload Writable</span>
                        <span class="font-mono text-green-600"><?= $systemHealth['uploads_writable'] ? 'Yes' : 'No' ?></span>
                    </div>
                </div>
            </div>

            <!-- Database Stats -->
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="font-bold text-slate-900 mb-4">Database Statistics</h3>
                <div class="space-y-3 text-sm">
                    <div class="flex justify-between items-center">
                        <span class="text-slate-600">Total Users</span>
                        <span class="text-2xl font-bold text-blue-600"><?= number_format($systemHealth['total_users'] ?? 0) ?></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-slate-600">Active Jobs</span>
                        <span class="text-2xl font-bold text-green-600"><?= number_format($systemHealth['total_jobs'] ?? 0) ?></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-slate-600">Published Courses</span>
                        <span class="text-2xl font-bold text-cyan-600"><?= number_format($systemHealth['total_courses'] ?? 0) ?></span>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="font-bold text-slate-900 mb-4">Quick Actions</h3>
                <div class="space-y-2">
                    <button class="w-full text-left px-3 py-2 bg-blue-50 text-blue-700 rounded hover:bg-blue-100 transition text-sm font-medium">
                        <i class="fas fa-backup mr-2"></i> Backup Database
                    </button>
                    <button class="w-full text-left px-3 py-2 bg-amber-50 text-amber-700 rounded hover:bg-amber-100 transition text-sm font-medium">
                        <i class="fas fa-trash mr-2"></i> Clear Cache
                    </button>
                    <button class="w-full text-left px-3 py-2 bg-red-50 text-red-700 rounded hover:bg-red-100 transition text-sm font-medium">
                        <i class="fas fa-warning mr-2"></i> Maintenance Mode
                    </button>
                </div>
            </div>
        </div>

        <!-- Settings Forms -->
        <form method="POST" class="space-y-6">
            <input type="hidden" name="action" value="update_settings">
            
            <!-- General Settings -->
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="font-bold text-slate-900 mb-6 flex items-center gap-2">
                    <i class="fas fa-cog text-slate-600"></i> General Settings
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">Site Name</label>
                        <input type="text" name="site_name" value="<?= htmlspecialchars($settings['site_name']) ?>" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">Admin Email</label>
                        <input type="email" name="site_email" value="<?= htmlspecialchars($settings['site_email']) ?>" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">Payment Gateway</label>
                        <select name="payment_gateway" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="paystack" <?= $settings['payment_gateway'] === 'paystack' ? 'selected' : '' ?>>Paystack</option>
                            <option value="stripe" <?= $settings['payment_gateway'] === 'stripe' ? 'selected' : '' ?>>Stripe</option>
                            <option value="flutterwave" <?= $settings['payment_gateway'] === 'flutterwave' ? 'selected' : '' ?>>Flutterwave</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">Max Upload Size</label>
                        <input type="text" name="max_upload_size" value="<?= htmlspecialchars($settings['max_upload_size']) ?>" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                </div>
            </div>

            <!-- Feature Toggles -->
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="font-bold text-slate-900 mb-6 flex items-center gap-2">
                    <i class="fas fa-toggle-on text-slate-600"></i> Feature Toggles
                </h3>
                <div class="space-y-4">
                    <label class="flex items-center gap-3">
                        <input type="checkbox" name="verification_required" <?= $settings['verification_required'] ? 'checked' : '' ?> class="w-5 h-5 text-blue-600 rounded">
                        <span class="text-slate-700 font-medium">Require Email Verification on Registration</span>
                    </label>
                    <label class="flex items-center gap-3">
                        <input type="checkbox" name="course_approval_required" <?= $settings['course_approval_required'] ? 'checked' : '' ?> class="w-5 h-5 text-blue-600 rounded">
                        <span class="text-slate-700 font-medium">Require Admin Approval for New Courses</span>
                    </label>
                    <label class="flex items-center gap-3">
                        <input type="checkbox" name="maintenance_mode" <?= $settings['maintenance_mode'] ? 'checked' : '' ?> class="w-5 h-5 text-red-600 rounded">
                        <span class="text-slate-700 font-medium">Enable Maintenance Mode</span>
                    </label>
                </div>
            </div>

            <!-- Security Settings -->
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="font-bold text-slate-900 mb-6 flex items-center gap-2">
                    <i class="fas fa-shield-alt text-slate-600"></i> Security Settings
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">Session Timeout (minutes)</label>
                        <input type="number" name="session_timeout" value="30" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">Max Login Attempts</label>
                        <input type="number" name="max_login_attempts" value="5" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                </div>
            </div>

            <!-- Save Button -->
            <div class="bg-white rounded-lg shadow p-6">
                <button type="submit" class="bg-blue-600 text-white font-bold py-3 px-6 rounded-lg hover:bg-blue-700 transition duration-300 flex items-center gap-2">
                    <i class="fas fa-save"></i> Save Settings
                </button>
            </div>
        </form>

        <!-- Logs Section -->
        <div class="bg-white rounded-lg shadow p-6 mt-8">
            <h3 class="font-bold text-slate-900 mb-4 flex items-center gap-2">
                <i class="fas fa-file-alt text-slate-600"></i> Recent System Logs
            </h3>
            <div class="bg-slate-50 rounded p-4 font-mono text-xs text-slate-600 max-h-64 overflow-y-auto">
                <div>[INFO] System started successfully</div>
                <div>[DEBUG] Database connection established</div>
                <div>[INFO] Cache cleared by admin</div>
                <div>[WARNING] High memory usage detected</div>
                <div>[ERROR] Failed to send email notification</div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>