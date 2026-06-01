<?php
/**
 * JOBMINGTON - Admin Command Center
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

function admin_scalar(PDO $pdo, string $sql, $fallback = 0) {
    try {
        $value = $pdo->query($sql)->fetchColumn();
        return $value === false ? $fallback : $value;
    } catch (Throwable $e) {
        error_log('Admin dashboard scalar error: ' . $e->getMessage());
        return $fallback;
    }
}

function admin_rows(PDO $pdo, string $sql): array {
    try {
        return $pdo->query($sql)->fetchAll();
    } catch (Throwable $e) {
        error_log('Admin dashboard rows error: ' . $e->getMessage());
        return [];
    }
}

function admin_money($amount): string {
    return '$' . number_format((float) $amount, 2);
}

function admin_bytes($bytes): string {
    if ($bytes === false || $bytes === null) return 'Unavailable';
    $bytes = (float) $bytes;
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $power = $bytes > 0 ? min((int) floor(log($bytes, 1024)), count($units) - 1) : 0;
    return number_format($bytes / (1024 ** $power), $power === 0 ? 0 : 1) . ' ' . $units[$power];
}

function admin_snippet(?string $value, int $length = 90): string {
    $value = trim((string) $value);
    if ($value === '') return 'No details';
    return strlen($value) > $length ? substr($value, 0, $length - 3) . '...' : $value;
}

function admin_when(?string $datetime): string {
    if (empty($datetime)) return 'Never';
    return formatDateTime($datetime, 'M d, Y h:i A');
}

function admin_status_class(?string $status): string {
    $status = strtolower((string) $status);
    if (in_array($status, ['completed', 'active', 'published', 'hired', 'verified'], true)) {
        return 'bg-emerald-500/15 text-emerald-300 border-emerald-500/30';
    }
    if (in_array($status, ['pending', 'reviewed', 'shortlisted', 'interview'], true)) {
        return 'bg-amber-500/15 text-amber-300 border-amber-500/30';
    }
    if (in_array($status, ['failed', 'abandoned', 'rejected', 'inactive', 'expired', 'locked'], true)) {
        return 'bg-rose-500/15 text-rose-300 border-rose-500/30';
    }
    return 'bg-slate-500/15 text-slate-300 border-slate-500/30';
}

function admin_tone_class(string $tone): string {
    switch ($tone) {
        case 'good':
            return 'border-emerald-500/30 bg-emerald-500/10 text-emerald-300';
        case 'warning':
            return 'border-amber-500/30 bg-amber-500/10 text-amber-300';
        case 'danger':
            return 'border-rose-500/30 bg-rose-500/10 text-rose-300';
        case 'blue':
            return 'border-blue-500/30 bg-blue-500/10 text-blue-300';
        default:
            return 'border-white/10 bg-white/5 text-slate-300';
    }
}

function admin_bar_class(string $tone): string {
    switch ($tone) {
        case 'good':
            return 'bg-emerald-400';
        case 'warning':
            return 'bg-amber-400';
        case 'danger':
            return 'bg-rose-400';
        case 'blue':
            return 'bg-blue-400';
        default:
            return 'bg-slate-400';
    }
}

$dbOnline = false;
$dbLatencyMs = null;
$started = microtime(true);
try {
    $pdo->query('SELECT 1');
    $dbOnline = true;
    $dbLatencyMs = (int) round((microtime(true) - $started) * 1000);
} catch (Throwable $e) {
    error_log('Admin dashboard database health error: ' . $e->getMessage());
}

$totalUsers = (int) admin_scalar($pdo, 'SELECT COUNT(*) FROM users');
$newUsersToday = (int) admin_scalar($pdo, 'SELECT COUNT(*) FROM users WHERE created_at >= CURDATE()');
$newUsersWeek = (int) admin_scalar($pdo, 'SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)');
$activeUsers = (int) admin_scalar($pdo, 'SELECT COUNT(*) FROM users WHERE is_active = 1');
$lockedUsers = (int) admin_scalar($pdo, 'SELECT COUNT(*) FROM users WHERE locked_until IS NOT NULL AND locked_until > NOW()');

$totalCompanies = (int) admin_scalar($pdo, 'SELECT COUNT(*) FROM companies');
$unverifiedCompanies = (int) admin_scalar($pdo, 'SELECT COUNT(*) FROM companies WHERE COALESCE(is_verified, 0) = 0');

$totalJobs = (int) admin_scalar($pdo, 'SELECT COUNT(*) FROM jobs');
$liveJobs = (int) admin_scalar($pdo, 'SELECT COUNT(*) FROM jobs WHERE is_active = 1 AND (expires_at IS NULL OR expires_at >= CURDATE())');
$inactiveJobs = (int) admin_scalar($pdo, 'SELECT COUNT(*) FROM jobs WHERE COALESCE(is_active, 0) = 0');
$expiredJobs = (int) admin_scalar($pdo, 'SELECT COUNT(*) FROM jobs WHERE expires_at IS NOT NULL AND expires_at < CURDATE()');
$newJobsWeek = (int) admin_scalar($pdo, 'SELECT COUNT(*) FROM jobs WHERE posted_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)');
$jobViewsWeek = (int) admin_scalar($pdo, 'SELECT COALESCE(SUM(views), 0) FROM jobs WHERE posted_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)');

$totalApplications = (int) admin_scalar($pdo, 'SELECT COUNT(*) FROM job_applications');
$pendingApplications = (int) admin_scalar($pdo, "SELECT COUNT(*) FROM job_applications WHERE status = 'pending'");
$applicationsToday = (int) admin_scalar($pdo, 'SELECT COUNT(*) FROM job_applications WHERE applied_at >= CURDATE()');
$applicationsWeek = (int) admin_scalar($pdo, 'SELECT COUNT(*) FROM job_applications WHERE applied_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)');

$completedRevenue = (float) admin_scalar($pdo, "SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE status = 'completed'");
$monthRevenue = (float) admin_scalar($pdo, "SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE status = 'completed' AND created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')");
$totalTransactions = (int) admin_scalar($pdo, 'SELECT COUNT(*) FROM transactions');
$failedPaymentsDay = (int) admin_scalar($pdo, "SELECT COUNT(*) FROM transactions WHERE status IN ('failed', 'abandoned') AND created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)");
$pendingPayments = (int) admin_scalar($pdo, "SELECT COUNT(*) FROM transactions WHERE status = 'pending'");

$publishedCourses = (int) admin_scalar($pdo, 'SELECT COUNT(*) FROM courses WHERE is_published = 1');
$totalCourses = (int) admin_scalar($pdo, 'SELECT COUNT(*) FROM courses');
$courseEnrollments = (int) admin_scalar($pdo, 'SELECT COUNT(*) FROM course_enrollments');
$totalCertificates = (int) admin_scalar($pdo, 'SELECT COUNT(*) FROM certificates');

$publicPassports = (int) admin_scalar($pdo, 'SELECT COUNT(*) FROM talent_passports WHERE is_public = 1');
$totalPassports = (int) admin_scalar($pdo, 'SELECT COUNT(*) FROM talent_passports');
$unprocessedWebhooks = (int) admin_scalar($pdo, 'SELECT COUNT(*) FROM webhook_logs WHERE COALESCE(processed, 0) = 0 OR error_message IS NOT NULL');
$rateLimitsHour = (int) admin_scalar($pdo, 'SELECT COUNT(*) FROM rate_limits WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)');
$failedLoginsDay = (int) admin_scalar($pdo, 'SELECT COUNT(*) FROM failed_logins WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)');
$notificationsUnread = (int) admin_scalar($pdo, 'SELECT COUNT(*) FROM notifications WHERE is_read = 0');

$recentUsers = admin_rows($pdo, "
    SELECT user_id, full_name, email, user_type, is_active, is_verified, created_at
    FROM users
    ORDER BY created_at DESC
    LIMIT 6
");

$recentJobs = admin_rows($pdo, "
    SELECT j.job_id, j.title, j.is_active, j.is_featured, j.views, j.applications_count, j.posted_at, j.expires_at,
           c.name AS company_name
    FROM jobs j
    LEFT JOIN companies c ON j.company_id = c.company_id
    ORDER BY j.posted_at DESC
    LIMIT 6
");

$recentApplications = admin_rows($pdo, "
    SELECT ja.application_id, ja.status, ja.applied_at, u.full_name, u.email, j.title, c.name AS company_name
    FROM job_applications ja
    JOIN users u ON ja.user_id = u.user_id
    JOIN jobs j ON ja.job_id = j.job_id
    LEFT JOIN companies c ON j.company_id = c.company_id
    ORDER BY ja.applied_at DESC
    LIMIT 6
");

$recentPayments = admin_rows($pdo, "
    SELECT t.id, t.plan, t.amount, t.status, t.created_at, u.full_name, u.email
    FROM transactions t
    LEFT JOIN users u ON t.user_id = u.user_id
    ORDER BY t.created_at DESC
    LIMIT 6
");

$recentActivity = admin_rows($pdo, "
    SELECT al.action, al.details, al.ip_address, al.created_at, u.full_name, u.email
    FROM activity_logs al
    LEFT JOIN users u ON al.user_id = u.user_id
    ORDER BY al.created_at DESC
    LIMIT 8
");

$latestWebhooks = admin_rows($pdo, "
    SELECT provider, event_type, reference, processed, error_message, created_at
    FROM webhook_logs
    ORDER BY created_at DESC
    LIMIT 5
");

$rootPath = realpath(__DIR__ . '/..') ?: __DIR__;
$diskTotal = @disk_total_space($rootPath);
$diskFree = @disk_free_space($rootPath);
$diskUsedPercent = ($diskTotal && $diskFree) ? (int) round((1 - ($diskFree / $diskTotal)) * 100) : 0;
$appDebug = strtolower((string) getenv('APP_DEBUG')) === 'true';
$displayErrors = in_array(strtolower((string) ini_get('display_errors')), ['1', 'on', 'yes', 'true'], true);
$phpErrorLog = ini_get('error_log') ?: '';
$phpErrorLogReadable = $phpErrorLog !== '' && is_readable($phpErrorLog);
$phpErrorLogSize = $phpErrorLogReadable ? @filesize($phpErrorLog) : null;

$attentionItems = [
    ['label' => 'Pending applications', 'count' => $pendingApplications, 'detail' => 'Need employer/admin review', 'href' => '/jobmington/employer/applications.php', 'tone' => $pendingApplications > 0 ? 'warning' : 'good'],
    ['label' => 'Unverified companies', 'count' => $unverifiedCompanies, 'detail' => 'Employer profiles awaiting trust checks', 'href' => '/jobmington/admin/users.php', 'tone' => $unverifiedCompanies > 0 ? 'warning' : 'good'],
    ['label' => 'Expired jobs', 'count' => $expiredJobs, 'detail' => 'Old listings to clean or renew', 'href' => '/jobmington/admin/jobs.php', 'tone' => $expiredJobs > 0 ? 'warning' : 'good'],
    ['label' => 'Failed payments today', 'count' => $failedPaymentsDay, 'detail' => 'Failed or abandoned transactions', 'href' => '/jobmington/payments/', 'tone' => $failedPaymentsDay > 0 ? 'danger' : 'good'],
    ['label' => 'Webhook issues', 'count' => $unprocessedWebhooks, 'detail' => 'Unprocessed or errored webhook logs', 'href' => '/jobmington/admin/settings.php', 'tone' => $unprocessedWebhooks > 0 ? 'danger' : 'good'],
    ['label' => 'Locked users', 'count' => $lockedUsers, 'detail' => 'Accounts currently under login lockout', 'href' => '/jobmington/admin/users.php', 'tone' => $lockedUsers > 0 ? 'warning' : 'good'],
];

$kpis = [
    ['label' => 'Users', 'value' => number_format($totalUsers), 'detail' => number_format($newUsersToday) . ' today / ' . number_format($newUsersWeek) . ' this week', 'icon' => 'fa-users', 'tone' => 'blue', 'href' => '/jobmington/admin/users.php'],
    ['label' => 'Live jobs', 'value' => number_format($liveJobs), 'detail' => number_format($newJobsWeek) . ' posted this week / ' . number_format($inactiveJobs) . ' inactive', 'icon' => 'fa-briefcase', 'tone' => 'good', 'href' => '/jobmington/admin/jobs.php'],
    ['label' => 'Applications', 'value' => number_format($totalApplications), 'detail' => number_format($applicationsToday) . ' today / ' . number_format($pendingApplications) . ' pending', 'icon' => 'fa-file-signature', 'tone' => 'warning', 'href' => '/jobmington/employer/applications.php'],
    ['label' => 'Revenue', 'value' => admin_money($completedRevenue), 'detail' => admin_money($monthRevenue) . ' this month / ' . number_format($totalTransactions) . ' transactions', 'icon' => 'fa-credit-card', 'tone' => 'good', 'href' => '/jobmington/payments/'],
    ['label' => 'Employers', 'value' => number_format($totalCompanies), 'detail' => number_format($unverifiedCompanies) . ' unverified company profiles', 'icon' => 'fa-building', 'tone' => 'blue', 'href' => '/jobmington/admin/users.php'],
    ['label' => 'Learning', 'value' => number_format($publishedCourses) . '/' . number_format($totalCourses), 'detail' => number_format($courseEnrollments) . ' enrollments / ' . number_format($totalCertificates) . ' certificates', 'icon' => 'fa-graduation-cap', 'tone' => 'neutral', 'href' => '/jobmington/admin/courses.php'],
    ['label' => 'Talent passports', 'value' => number_format($publicPassports) . '/' . number_format($totalPassports), 'detail' => 'Public passports available to employers', 'icon' => 'fa-id-badge', 'tone' => 'blue', 'href' => '/jobmington/wallet/passport/'],
    ['label' => 'Platform signals', 'value' => number_format($notificationsUnread), 'detail' => number_format($failedLoginsDay) . ' failed logins / ' . number_format($rateLimitsHour) . ' rate records', 'icon' => 'fa-bell', 'tone' => $failedLoginsDay > 0 ? 'warning' : 'neutral', 'href' => '/jobmington/admin/settings.php'],
];

$healthItems = [
    ['label' => 'Database', 'value' => $dbOnline ? 'Online' : 'Down', 'detail' => $dbOnline ? $dbLatencyMs . ' ms response' : 'Connection failed', 'tone' => $dbOnline ? 'good' : 'danger', 'percent' => $dbOnline ? 100 : 6],
    ['label' => 'Debug exposure', 'value' => ($appDebug || $displayErrors) ? 'Enabled' : 'Off', 'detail' => ($appDebug || $displayErrors) ? 'Disable before production traffic' : 'Production-safe display setting', 'tone' => ($appDebug || $displayErrors) ? 'warning' : 'good', 'percent' => ($appDebug || $displayErrors) ? 72 : 100],
    ['label' => 'Disk usage', 'value' => $diskTotal ? $diskUsedPercent . '% used' : 'Unavailable', 'detail' => $diskTotal ? admin_bytes($diskFree) . ' free of ' . admin_bytes($diskTotal) : $rootPath, 'tone' => $diskUsedPercent > 85 ? 'danger' : ($diskUsedPercent > 70 ? 'warning' : 'good'), 'percent' => $diskTotal ? max(6, min(100, $diskUsedPercent)) : 6],
    ['label' => 'Payment webhooks', 'value' => number_format($unprocessedWebhooks), 'detail' => 'Unprocessed or errored records', 'tone' => $unprocessedWebhooks > 0 ? 'danger' : 'good', 'percent' => $unprocessedWebhooks > 0 ? 62 : 100],
    ['label' => 'PHP error log', 'value' => $phpErrorLogReadable ? admin_bytes($phpErrorLogSize) : 'Not readable', 'detail' => $phpErrorLog !== '' ? $phpErrorLog : 'No explicit error_log path', 'tone' => $phpErrorLogReadable ? 'neutral' : 'warning', 'percent' => $phpErrorLogReadable ? 85 : 46],
    ['label' => 'Server time', 'value' => date('H:i:s'), 'detail' => date('D, M d, Y T'), 'tone' => 'neutral', 'percent' => 100],
];

$managementLinks = [
    ['label' => 'Operations', 'href' => '/jobmington/admin/operations.php', 'icon' => 'fa-heartbeat', 'detail' => 'Launch checks, cron, payments'],
    ['label' => 'State icons', 'href' => '/jobmington/admin/illustration-states.php', 'icon' => 'fa-icons', 'detail' => 'Preview notification states'],
    ['label' => 'Users', 'href' => '/jobmington/admin/users.php', 'icon' => 'fa-users', 'detail' => 'Accounts, roles, verification'],
    ['label' => 'Jobs', 'href' => '/jobmington/admin/jobs.php', 'icon' => 'fa-briefcase', 'detail' => 'Moderate listings and status'],
    ['label' => 'Post job', 'href' => '/jobmington/employer/post-job.php', 'icon' => 'fa-plus', 'detail' => 'Create a listing quickly'],
    ['label' => 'Countries', 'href' => '/jobmington/admin/countries.php', 'icon' => 'fa-globe-africa', 'detail' => 'Markets, currencies, coverage'],
    ['label' => 'Categories', 'href' => '/jobmington/admin/categories.php', 'icon' => 'fa-layer-group', 'detail' => 'Job and course grouping'],
    ['label' => 'Courses', 'href' => '/jobmington/admin/courses.php', 'icon' => 'fa-graduation-cap', 'detail' => 'Learning catalog'],
    ['label' => 'Modules', 'href' => '/jobmington/admin/modules.php', 'icon' => 'fa-book-open', 'detail' => 'Course modules'],
    ['label' => 'Quizzes', 'href' => '/jobmington/admin/quizzes.php', 'icon' => 'fa-tasks', 'detail' => 'Assessments'],
    ['label' => 'Certificates', 'href' => '/jobmington/admin/certificates.php', 'icon' => 'fa-certificate', 'detail' => 'Issued credentials'],
    ['label' => 'Badges', 'href' => '/jobmington/admin/badges.php', 'icon' => 'fa-medal', 'detail' => 'Seeds and achievements'],
    ['label' => 'Blog', 'href' => '/jobmington/admin/blog.php', 'icon' => 'fa-pen', 'detail' => 'Content publishing'],
    ['label' => 'Forum', 'href' => '/jobmington/admin/forum.php', 'icon' => 'fa-comments', 'detail' => 'Community moderation'],
    ['label' => 'Settings', 'href' => '/jobmington/admin/settings.php', 'icon' => 'fa-sliders-h', 'detail' => 'Platform configuration'],
];

$pageTitle = 'Admin Command Center - ' . SITE_NAME;

if (class_exists('HeaderConfig')) {
    HeaderConfig::getInstance()->setTitle($pageTitle);
}

$pillTone = static function (?string $status): string {
    $s = strtolower((string) $status);
    if (in_array($s, ['completed', 'active', 'published', 'hired', 'verified', 'sent'], true)) return 'good';
    if (in_array($s, ['pending', 'reviewed', 'shortlisted', 'interview', 'draft'], true)) return 'warning';
    if (in_array($s, ['failed', 'abandoned', 'rejected', 'inactive', 'expired', 'locked'], true)) return 'danger';
    return 'neutral';
};

require_once __DIR__ . '/../includes/header.php';
?>

<div class="ja-pagehead">
    <div>
        <h1>Command Center</h1>
        <p>Live overview of users, jobs, revenue, and platform health.</p>
    </div>
    <span class="ja-statuschip">
        <span class="ja-dot <?= $dbOnline ? '' : 'down' ?>"></span>
        <?= $dbOnline ? 'Database online · ' . (int) $dbLatencyMs . ' ms' : 'Database unreachable' ?>
    </span>
</div>

<!-- KPIs -->
<div class="ja-kpis">
    <?php foreach ($kpis as $kpi): ?>
        <a class="ja-kpi tone-<?= e($kpi['tone']) ?>" href="<?= e($kpi['href']) ?>">
            <div class="ja-kpi-top">
                <span class="ja-kpi-val"><?= e($kpi['value']) ?></span>
                <span class="ja-kpi-ico"><i class="fas <?= e($kpi['icon']) ?>"></i></span>
            </div>
            <div class="ja-kpi-lab"><?= e($kpi['label']) ?></div>
            <div class="ja-kpi-det"><?= e($kpi['detail']) ?></div>
        </a>
    <?php endforeach; ?>
</div>

<!-- Attention + Health -->
<div class="ja-grid-2">
    <div class="ja-card">
        <div class="ja-card-head"><h2>Needs attention</h2></div>
        <div class="ja-card-body">
            <div class="ja-attention">
                <?php foreach ($attentionItems as $item): ?>
                    <a class="ja-att tone-<?= e($item['tone']) ?>" href="<?= e($item['href']) ?>">
                        <span class="ja-att-count"><?= number_format((int) $item['count']) ?></span>
                        <span>
                            <span class="ja-att-lab"><?= e($item['label']) ?></span>
                            <span class="ja-att-det"><?= e($item['detail']) ?></span>
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <div class="ja-card">
        <div class="ja-card-head"><h2>Operations health</h2></div>
        <div class="ja-card-body">
            <?php foreach ($healthItems as $h): ?>
                <div class="ja-health-row">
                    <div class="ja-health-top">
                        <span class="ja-health-lab"><?= e($h['label']) ?></span>
                        <span class="ja-health-val ja-tone-<?= e($h['tone']) ?>"><?= e($h['value']) ?></span>
                    </div>
                    <div class="ja-bar"><span class="tone-<?= e($h['tone']) ?>" style="width: <?= (int) $h['percent'] ?>%"></span></div>
                    <div class="ja-health-det"><?= e($h['detail']) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Quick links -->
<div class="ja-quick">
    <?php foreach ($managementLinks as $link): ?>
        <a class="ja-tile" href="<?= e($link['href']) ?>">
            <i class="fas <?= e($link['icon']) ?>"></i>
            <span><b><?= e($link['label']) ?></b><span><?= e($link['detail']) ?></span></span>
        </a>
    <?php endforeach; ?>
</div>

<!-- Recent activity feeds -->
<div class="ja-grid-2">
    <div class="ja-card">
        <div class="ja-card-head"><h2>New users</h2><a href="/jobmington/admin/users.php">Manage</a></div>
        <div class="ja-card-body">
            <table class="ja-feed">
                <?php if (empty($recentUsers)): ?>
                    <tr><td class="ja-feed-empty">No users yet.</td></tr>
                <?php else: foreach ($recentUsers as $u): ?>
                    <tr>
                        <td>
                            <div><?= e($u['full_name'] ?: 'Unnamed') ?></div>
                            <div class="sub"><?= e($u['email']) ?></div>
                        </td>
                        <td style="text-align:right;">
                            <span class="ja-pill <?= $u['is_active'] ? 'good' : 'danger' ?>"><?= e($u['user_type']) ?></span>
                            <div class="sub"><?= e(admin_when($u['created_at'])) ?></div>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </table>
        </div>
    </div>

    <div class="ja-card">
        <div class="ja-card-head"><h2>Recent jobs</h2><a href="/jobmington/admin/jobs.php">Manage</a></div>
        <div class="ja-card-body">
            <table class="ja-feed">
                <?php if (empty($recentJobs)): ?>
                    <tr><td class="ja-feed-empty">No jobs yet.</td></tr>
                <?php else: foreach ($recentJobs as $j): ?>
                    <tr>
                        <td>
                            <div><?= e($j['title']) ?></div>
                            <div class="sub"><?= e($j['company_name'] ?: 'Unknown company') ?> · <?= (int) $j['views'] ?> views · <?= (int) $j['applications_count'] ?> apps</div>
                        </td>
                        <td style="text-align:right;">
                            <span class="ja-pill <?= $j['is_active'] ? 'good' : 'neutral' ?>"><?= $j['is_active'] ? 'Live' : 'Off' ?></span>
                            <div class="sub"><?= e(admin_when($j['posted_at'])) ?></div>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </table>
        </div>
    </div>

    <div class="ja-card">
        <div class="ja-card-head"><h2>Recent applications</h2><a href="/jobmington/employer/applications.php">Review</a></div>
        <div class="ja-card-body">
            <table class="ja-feed">
                <?php if (empty($recentApplications)): ?>
                    <tr><td class="ja-feed-empty">No applications yet.</td></tr>
                <?php else: foreach ($recentApplications as $a): ?>
                    <tr>
                        <td>
                            <div><?= e($a['full_name'] ?: $a['email']) ?></div>
                            <div class="sub"><?= e($a['title']) ?><?= $a['company_name'] ? ' · ' . e($a['company_name']) : '' ?></div>
                        </td>
                        <td style="text-align:right;">
                            <span class="ja-pill <?= $pillTone($a['status']) ?>"><?= e($a['status']) ?></span>
                            <div class="sub"><?= e(admin_when($a['applied_at'])) ?></div>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </table>
        </div>
    </div>

    <div class="ja-card">
        <div class="ja-card-head"><h2>Recent payments</h2><a href="/jobmington/payments/">View</a></div>
        <div class="ja-card-body">
            <table class="ja-feed">
                <?php if (empty($recentPayments)): ?>
                    <tr><td class="ja-feed-empty">No transactions yet.</td></tr>
                <?php else: foreach ($recentPayments as $p): ?>
                    <tr>
                        <td>
                            <div><?= e($p['full_name'] ?: ($p['email'] ?: 'Unknown')) ?></div>
                            <div class="sub"><?= e($p['plan'] ?: 'Payment') ?></div>
                        </td>
                        <td style="text-align:right;">
                            <div><strong><?= e(admin_money($p['amount'])) ?></strong></div>
                            <span class="ja-pill <?= $pillTone($p['status']) ?>"><?= e($p['status']) ?></span>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </table>
        </div>
    </div>
</div>

<!-- Activity + webhooks -->
<div class="ja-grid-2">
    <div class="ja-card">
        <div class="ja-card-head"><h2>Activity log</h2></div>
        <div class="ja-card-body">
            <table class="ja-feed">
                <?php if (empty($recentActivity)): ?>
                    <tr><td class="ja-feed-empty">No activity recorded.</td></tr>
                <?php else: foreach ($recentActivity as $act): ?>
                    <tr>
                        <td>
                            <div><?= e($act['action']) ?></div>
                            <div class="sub"><?= e($act['full_name'] ?: ($act['email'] ?: 'System')) ?> · <?= e(admin_snippet($act['details'], 60)) ?></div>
                        </td>
                        <td style="text-align:right;white-space:nowrap;"><span class="sub"><?= e(admin_when($act['created_at'])) ?></span></td>
                    </tr>
                <?php endforeach; endif; ?>
            </table>
        </div>
    </div>

    <div class="ja-card">
        <div class="ja-card-head"><h2>Recent webhooks</h2><a href="/jobmington/admin/settings.php">Settings</a></div>
        <div class="ja-card-body">
            <table class="ja-feed">
                <?php if (empty($latestWebhooks)): ?>
                    <tr><td class="ja-feed-empty">No webhook activity.</td></tr>
                <?php else: foreach ($latestWebhooks as $w): ?>
                    <tr>
                        <td>
                            <div><?= e($w['provider'] ?: 'webhook') ?> · <?= e($w['event_type'] ?: '—') ?></div>
                            <div class="sub"><?= e($w['reference'] ?: '') ?></div>
                        </td>
                        <td style="text-align:right;">
                            <?php $whBad = empty($w['processed']) || !empty($w['error_message']); ?>
                            <span class="ja-pill <?= $whBad ? 'danger' : 'good' ?>"><?= $whBad ? 'Issue' : 'OK' ?></span>
                            <div class="sub"><?= e(admin_when($w['created_at'])) ?></div>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
