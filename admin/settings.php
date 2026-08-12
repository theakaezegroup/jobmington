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
require_once __DIR__ . '/../includes/maintenance.php';

Session::start();
Session::requireAdmin();

$pdo = db();
$success = '';
$error = '';

/*
 * Only settings that actually change behaviour appear here.
 *
 * The save handler used to be the comment "implement as needed" followed by a
 * success message, so every control on this page reported that it had saved
 * and wrote nothing. Several of them had nothing behind them to save to
 * either: a payment gateway picker offering Stripe and Flutterwave when only
 * Paystack is built, a max upload size that PHP and the upload code decide, a
 * course approval toggle with no approval workflow. Those are gone rather than
 * left as switches that do nothing.
 */
$definitions = [
    'maintenance_mode'      => ['type' => 'bool', 'default' => '0'],
    'maintenance_message'   => ['type' => 'text', 'default' => 'We are making Jobmington better. Back shortly.'],
    'maintenance_back'      => ['type' => 'text', 'default' => ''],
    'verification_required' => ['type' => 'bool', 'default' => '1'],
    'max_login_attempts'    => ['type' => 'int',  'default' => '5',  'min' => 3,  'max' => 20],
    'session_timeout'       => ['type' => 'int',  'default' => '30', 'min' => 10, 'max' => 1440],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && Security::clean(post('action', '')) === 'update_settings') {
    if (!Security::verifyCSRF()) {
        $error = 'Please refresh the page and try again.';
    } else {
        $saved = 0;
        foreach ($definitions as $key => $def) {
            if ($def['type'] === 'bool') {
                $value = isset($_POST[$key]) ? '1' : '0';
            } elseif ($def['type'] === 'int') {
                $value = (string) min($def['max'], max($def['min'], (int) ($_POST[$key] ?? $def['default'])));
            } else {
                $value = trim(Security::clean($_POST[$key] ?? ''));
            }

            if (jm_setting_save($key, $value)) {
                $saved++;
            }
        }

        if ($saved === count($definitions)) {
            $wasOn = jm_maintenance_on();
            jm_log_activity((int) Session::userId(), 'admin_settings_saved',
                'maintenance ' . (isset($_POST['maintenance_mode']) ? 'on' : 'off'));
            $success = 'Settings saved.';
        } else {
            $error = 'Some settings could not be saved. Check the error log.';
        }
    }
}

// Read back after writing, so the form always shows what is actually stored.
$settings = [];
foreach ($definitions as $key => $def) {
    $settings[$key] = (string) jm_setting($key, $def['default']);
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
                <div class="flex items-center justify-between mb-4 flex-wrap gap-2">
                    <h3 class="font-bold text-slate-900">System Status</h3>
                    <span class="inline-flex items-center gap-1 px-2 py-1 bg-green-100 text-green-700 text-xs font-bold rounded-full">
                        <i class="fas fa-check-circle"></i> Healthy
                    </span>
                </div>
                <div class="space-y-3 text-sm">
                    <div class="flex justify-between flex-wrap gap-2">
                        <span class="text-slate-600">PHP Version</span>
                        <span class="font-mono text-slate-900"><?= htmlspecialchars($systemHealth['php_version']) ?></span>
                    </div>
                    <div class="flex justify-between flex-wrap gap-2">
                        <span class="text-slate-600">Memory Usage</span>
                        <span class="font-mono text-slate-900"><?= htmlspecialchars($systemHealth['memory_usage']) ?></span>
                    </div>
                    <div class="flex justify-between flex-wrap gap-2">
                        <span class="text-slate-600">Database</span>
                        <span class="font-mono text-green-600"><?= $systemHealth['database_connected'] ? 'Connected' : 'Disconnected' ?></span>
                    </div>
                    <div class="flex justify-between flex-wrap gap-2">
                        <span class="text-slate-600">Upload Writable</span>
                        <span class="font-mono text-green-600"><?= $systemHealth['uploads_writable'] ? 'Yes' : 'No' ?></span>
                    </div>
                </div>
            </div>

            <!-- Database Stats -->
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="font-bold text-slate-900 mb-4">Database Statistics</h3>
                <div class="space-y-3 text-sm">
                    <div class="flex justify-between items-center flex-wrap gap-2">
                        <span class="text-slate-600">Total Users</span>
                        <span class="text-2xl font-bold text-blue-600"><?= number_format($systemHealth['total_users'] ?? 0) ?></span>
                    </div>
                    <div class="flex justify-between items-center flex-wrap gap-2">
                        <span class="text-slate-600">Active Jobs</span>
                        <span class="text-2xl font-bold text-green-600"><?= number_format($systemHealth['total_jobs'] ?? 0) ?></span>
                    </div>
                    <div class="flex justify-between items-center flex-wrap gap-2">
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

        <form method="POST" class="space-y-6">
            <?= Security::csrfField() ?>
            <input type="hidden" name="action" value="update_settings">

            <!-- Maintenance -->
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="font-bold text-slate-900 mb-2">Maintenance mode</h3>
                <p class="text-sm text-slate-500 mb-5">
                    Visitors see a holding page and the site answers 503, which tells search engines
                    to come back rather than to drop your pages. You and anyone else signed in as an
                    admin keep full access, and the sign-in page stays open so you can always get back in.
                </p>

                <label class="flex items-center gap-3 mb-5">
                    <input type="checkbox" name="maintenance_mode" value="1" <?= $settings['maintenance_mode'] === '1' ? 'checked' : '' ?> class="w-5 h-5 text-red-600 rounded">
                    <span class="text-slate-700 font-medium">Take the site offline for visitors</span>
                </label>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">Message on the holding page</label>
                        <input type="text" name="maintenance_message" maxlength="160" value="<?= htmlspecialchars($settings['maintenance_message']) ?>"
                               class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">Expected back <span class="text-slate-400 font-normal">(optional)</span></label>
                        <input type="text" name="maintenance_back" maxlength="60" placeholder="e.g. 6pm WAT" value="<?= htmlspecialchars($settings['maintenance_back']) ?>"
                               class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                </div>

                <p class="text-sm mt-5">
                    <a href="/jobmington/?preview_maintenance=1" target="_blank" class="text-blue-700 font-bold">Preview the holding page</a>
                </p>
            </div>

            <!-- Accounts -->
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="font-bold text-slate-900 mb-2">Accounts</h3>
                <p class="text-sm text-slate-500 mb-5">These take effect on the next sign-in attempt.</p>

                <label class="flex items-center gap-3 mb-5">
                    <input type="checkbox" name="verification_required" value="1" <?= $settings['verification_required'] === '1' ? 'checked' : '' ?> class="w-5 h-5 text-blue-600 rounded">
                    <span class="text-slate-700 font-medium">Require people to verify their email before using the site</span>
                </label>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">Failed sign-ins before lockout</label>
                        <input type="number" name="max_login_attempts" min="3" max="20" value="<?= htmlspecialchars($settings['max_login_attempts']) ?>"
                               class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <p class="text-xs text-slate-400 mt-1">Between 3 and 20. The lockout lasts 15 minutes.</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">Session timeout (minutes)</label>
                        <input type="number" name="session_timeout" min="10" max="1440" value="<?= htmlspecialchars($settings['session_timeout']) ?>"
                               class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <p class="text-xs text-slate-400 mt-1">How long an idle session survives. Remember me is unaffected.</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <button type="submit" class="bg-blue-600 text-white font-bold py-3 px-6 rounded-lg hover:bg-blue-700 transition duration-300">
                    Save settings
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