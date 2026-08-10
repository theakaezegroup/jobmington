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
<style>
/* ── Dashboard (Namecheap-style) ─────────────────────────────────── */

/* Page header */
.nc-header {
    display: flex; align-items: flex-start; justify-content: space-between;
    gap: 16px; flex-wrap: wrap; margin-bottom: 28px;
    padding-bottom: 20px; border-bottom: 1px solid #e4eaf3;
}
.nc-header-left h1 { margin: 0 0 4px; font-size: 22px; font-weight: 800; letter-spacing: -.03em; color: #0b1b33; }
.nc-header-left p  { margin: 0; font-size: 13px; color: #5b6b82; }
.nc-header-right   { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.nc-chip {
    display: inline-flex; align-items: center; gap: 7px;
    padding: 6px 13px; border-radius: 8px; font-size: 12px; font-weight: 700;
    background: #fff; border: 1px solid #e4eaf3;
    box-shadow: 0 1px 3px rgba(11,27,51,.05); color: #0b1b33; white-space: nowrap;
}
.nc-dot { width: 6px; height: 6px; border-radius: 50%; flex-shrink: 0; }
.nc-dot.up   { background: #0f766e; }
.nc-dot.down { background: #dc2626; }
.nc-chip-time { font-size: 12px; font-weight: 600; color: #94a3b8; }

/* Primary KPI cards */
.nc-stats { display: grid; grid-template-columns: repeat(4, minmax(0,1fr)); gap: 14px; margin-bottom: 14px; }
.nc-stat {
    background: #fff; border: 1px solid #e4eaf3; border-radius: 10px;
    padding: 18px 18px 16px; text-decoration: none; display: block;
    border-left: 4px solid #e4eaf3;
    box-shadow: 0 1px 4px rgba(11,27,51,.04);
    transition: box-shadow .15s, transform .15s;
}
.nc-stat:hover { box-shadow: 0 6px 20px rgba(11,27,51,.09); transform: translateY(-1px); }
.nc-stat.s-blue   { border-left-color: #0640a3; }
.nc-stat.s-green  { border-left-color: #0f766e; }
.nc-stat.s-amber  { border-left-color: #d97706; }
.nc-stat.s-violet { border-left-color: #7c3aed; }
.nc-stat-top { display: flex; align-items: flex-start; justify-content: space-between; gap: 8px; margin-bottom: 8px; }
.nc-stat-ico { width: 34px; height: 34px; border-radius: 8px; display: grid; place-items: center; font-size: 13px; flex-shrink: 0; }
.s-blue .nc-stat-ico   { background: #eef4ff; color: #0640a3; }
.s-green .nc-stat-ico  { background: #e6f5f1; color: #0f766e; }
.s-amber .nc-stat-ico  { background: #fef5e7; color: #d97706; }
.s-violet .nc-stat-ico { background: #f3f0ff; color: #7c3aed; }
.nc-stat-badge { font-size: 10.5px; font-weight: 700; padding: 2px 7px; border-radius: 5px; background: #f0f4f9; color: #5b6b82; white-space: nowrap; }
.nc-stat-num { font-size: 28px; font-weight: 800; letter-spacing: -.04em; line-height: 1; color: #0b1b33; }
.nc-stat-lbl { font-size: 11.5px; font-weight: 700; color: #5b6b82; margin-top: 5px; text-transform: uppercase; letter-spacing: .06em; }
.nc-stat-sub { font-size: 11px; color: #94a3b8; margin-top: 3px; line-height: 1.5; }

/* Secondary stats strip */
.nc-stats2 { display: grid; grid-template-columns: repeat(4, minmax(0,1fr)); gap: 14px; margin-bottom: 24px; }
.nc-stat2 {
    background: #fff; border: 1px solid #e4eaf3; border-radius: 10px;
    padding: 12px 14px; text-decoration: none; display: flex; align-items: center; gap: 12px;
    box-shadow: 0 1px 3px rgba(11,27,51,.03);
    transition: box-shadow .15s, transform .15s;
}
.nc-stat2:hover { box-shadow: 0 5px 16px rgba(11,27,51,.07); transform: translateY(-1px); }
.nc-stat2-ico { width: 34px; height: 34px; border-radius: 8px; flex-shrink: 0; display: grid; place-items: center; font-size: 13px; background: #f0f4f9; color: #5b6b82; }
.nc-stat2-num { font-size: 20px; font-weight: 800; letter-spacing: -.03em; line-height: 1; color: #0b1b33; }
.nc-stat2-lbl { font-size: 11.5px; font-weight: 700; color: #5b6b82; margin-top: 1px; }
.nc-stat2-sub { font-size: 10.5px; color: #94a3b8; margin-top: 1px; }

/* Two-column middle section */
.nc-mid { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; margin-bottom: 24px; }

/* Card shell */
.nc-card { background: #fff; border: 1px solid #e4eaf3; border-radius: 10px; overflow: hidden; box-shadow: 0 1px 3px rgba(11,27,51,.04); }
.nc-card-head { display: flex; align-items: center; gap: 10px; padding: 12px 16px; border-bottom: 1px solid #f0f4f9; background: #fafbfd; }
.nc-card-head-ico { width: 26px; height: 26px; border-radius: 6px; display: grid; place-items: center; font-size: 11px; flex-shrink: 0; }
.nc-card-head-ico.amber { background: #fef5e7; color: #d97706; }
.nc-card-head-ico.blue  { background: #eef4ff; color: #0640a3; }
.nc-card-head h3 { margin: 0; font-size: 12px; font-weight: 800; color: #0b1b33; text-transform: uppercase; letter-spacing: .08em; }
.nc-card-head a { margin-left: auto; font-size: 11px; font-weight: 700; color: #0640a3; text-decoration: none; padding: 3px 9px; background: #eef4ff; border-radius: 5px; }
.nc-card-head a:hover { background: #d9e8ff; }

/* Attention items */
.nc-att-list { padding: 8px 12px 10px; display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
.nc-att-item {
    display: flex; align-items: flex-start; gap: 10px;
    padding: 11px 12px 11px 14px; border-radius: 8px;
    border: 1px solid #edf2f9; text-decoration: none;
    transition: box-shadow .12s, transform .12s;
}
.nc-att-item:hover { box-shadow: 0 4px 12px rgba(11,27,51,.07); transform: translateY(-1px); }
.nc-att-item.t-ok   { border-left: 3px solid #0f766e; }
.nc-att-item.t-warn { border-left: 3px solid #d97706; }
.nc-att-item.t-bad  { border-left: 3px solid #dc2626; }
.nc-att-n   { font-size: 22px; font-weight: 800; letter-spacing: -.04em; line-height: 1; color: #0b1b33; min-width: 26px; }
.nc-att-lbl { font-size: 12px; font-weight: 700; color: #0b1b33; line-height: 1.3; display: block; }
.nc-att-sub { font-size: 10.5px; color: #94a3b8; margin-top: 2px; display: block; }

/* Health bars */
.nc-health-list { padding: 4px 16px 8px; }
.nc-health-row { padding: 9px 0; border-bottom: 1px solid #f3f6fa; }
.nc-health-row:last-child { border-bottom: none; }
.nc-health-top { display: flex; align-items: center; justify-content: space-between; gap: 8px; margin-bottom: 5px; }
.nc-health-lbl { font-size: 12px; font-weight: 700; color: #0b1b33; }
.nc-health-val { font-size: 11px; font-weight: 800; padding: 2px 7px; border-radius: 4px; }
.nc-health-val.v-ok   { background: #e6f5f1; color: #0a6454; }
.nc-health-val.v-warn { background: #fef5e7; color: #8c5200; }
.nc-health-val.v-bad  { background: #fdecea; color: #991b1b; }
.nc-health-val.v-mute { background: #f0f3f8; color: #475569; }
.nc-hbar { height: 4px; border-radius: 999px; background: #edf2f7; overflow: hidden; margin-bottom: 3px; }
.nc-hbar span { display: block; height: 100%; border-radius: 999px; transition: width .5s ease; }
.nc-hbar span.ok   { background: #0f766e; }
.nc-hbar span.warn { background: #d97706; }
.nc-hbar span.bad  { background: #dc2626; }
.nc-hbar span.mute { background: #94a3b8; }
.nc-health-det { font-size: 10px; color: #94a3b8; }

/* Section header */
.nc-sec-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px; }
.nc-sec-head h2 { margin: 0; font-size: 12px; font-weight: 800; color: #5b6b82; text-transform: uppercase; letter-spacing: .09em; }

/* Module tiles — Namecheap product-grid style */
.nc-modules { display: grid; grid-template-columns: repeat(auto-fill, minmax(155px, 1fr)); gap: 10px; margin-bottom: 24px; }
.nc-module {
    display: flex; flex-direction: column;
    padding: 16px 14px 14px; background: #fff;
    border: 1px solid #e4eaf3; border-radius: 10px; text-decoration: none;
    box-shadow: 0 1px 3px rgba(11,27,51,.04);
    transition: box-shadow .15s, transform .15s, border-color .15s;
}
.nc-module:hover { box-shadow: 0 8px 22px rgba(11,27,51,.09); transform: translateY(-2px); border-color: #c8d8ef; }
.nc-module-ico { width: 38px; height: 38px; border-radius: 9px; display: grid; place-items: center; font-size: 15px; margin-bottom: 10px; background: #eef4ff; color: #0640a3; flex-shrink: 0; }
.nc-module b     { font-size: 13px; font-weight: 800; color: #0b1b33; display: block; line-height: 1.3; }
.nc-module small { font-size: 11px; color: #94a3b8; margin-top: 3px; display: block; line-height: 1.4; }

/* Feed cards */
.nc-feeds { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; margin-bottom: 24px; }
.nc-feed-card { background: #fff; border: 1px solid #e4eaf3; border-radius: 10px; overflow: hidden; box-shadow: 0 1px 3px rgba(11,27,51,.04); }
.nc-feed-head { display: flex; align-items: center; justify-content: space-between; padding: 12px 16px; border-bottom: 1px solid #f0f4f9; background: #fafbfd; }
.nc-feed-head h3 { margin: 0; font-size: 12px; font-weight: 800; color: #0b1b33; text-transform: uppercase; letter-spacing: .08em; }
.nc-feed-head a { font-size: 11.5px; font-weight: 700; color: #0640a3; text-decoration: none; padding: 3px 9px; background: #eef4ff; border-radius: 5px; }
.nc-feed-head a:hover { background: #d9e8ff; }
.nc-feed-table { width: 100%; border-collapse: collapse; }
.nc-feed-table td { padding: 10px 16px; border-bottom: 1px solid #f3f6fa; vertical-align: middle; }
.nc-feed-table tr:last-child td { border-bottom: none; }
.nc-feed-table tr:hover td { background: #fafbfd; }
.nc-row-main { font-size: 13px; font-weight: 600; color: #0b1b33; }
.nc-row-sub  { font-size: 11px; color: #94a3b8; margin-top: 2px; }
.nc-right { text-align: right; white-space: nowrap; }
.nc-empty { padding: 24px 16px; text-align: center; color: #94a3b8; font-size: 13px; }

/* Pills */
.nc-pill { display: inline-flex; align-items: center; padding: 2px 7px; border-radius: 5px; font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: .06em; white-space: nowrap; }
.nc-pill.ok   { background: #e6f5f1; color: #0a6454; }
.nc-pill.warn { background: #fef5e7; color: #8c5200; }
.nc-pill.bad  { background: #fdecea; color: #991b1b; }
.nc-pill.mute { background: #f0f3f8; color: #475569; }

@media (max-width: 1100px) {
    .nc-stats, .nc-stats2 { grid-template-columns: repeat(2, minmax(0,1fr)); }
    .nc-mid, .nc-feeds { grid-template-columns: 1fr; }
    .nc-att-list { grid-template-columns: 1fr; }
}
@media (max-width: 640px) {
    .nc-stats, .nc-stats2 { grid-template-columns: 1fr; }
    .nc-stat-num { font-size: 24px; }
    .nc-modules { grid-template-columns: repeat(2, minmax(0,1fr)); }
}
</style>

<?php
$htone = static fn(string $t): string => match($t) { 'good' => 'ok', 'warning' => 'warn', 'danger' => 'bad', default => 'mute' };
$atone = static fn(string $t): string => match($t) { 'warning' => 't-warn', 'danger' => 't-bad', default => 't-ok' };
$ftone = static function (?string $s): string {
    $s = strtolower((string) $s);
    if (in_array($s, ['completed','active','published','hired','verified','sent','live'], true)) return 'ok';
    if (in_array($s, ['pending','reviewed','shortlisted','interview','draft'], true)) return 'warn';
    if (in_array($s, ['failed','abandoned','rejected','inactive','expired','locked','off'], true)) return 'bad';
    return 'mute';
};
?>

<!-- Page header -->
<div class="nc-header">
    <div class="nc-header-left">
        <h1>Dashboard</h1>
        <p>Live platform overview &mdash; <?= e(date('l, d F Y')) ?></p>
    </div>
    <div class="nc-header-right">
        <span class="nc-chip">
            <span class="nc-dot <?= $dbOnline ? 'up' : 'down' ?>"></span>
            <?= $dbOnline ? 'DB online &middot; ' . (int)$dbLatencyMs . 'ms' : 'DB unreachable' ?>
        </span>
        <span class="nc-chip-time"><?= e(date('H:i')) ?> <?= e(date('T')) ?></span>
    </div>
</div>

<!-- Primary stats -->
<div class="nc-stats">
    <?php
    $pstats = [
        ['num' => number_format($totalUsers),        'lbl' => 'Total users',  'sub' => $newUsersToday . ' today &middot; ' . $newUsersWeek . ' this week', 'icon' => 'fa-users',         'cls' => 's-blue',   'badge' => '+' . $newUsersToday . ' today', 'href' => '/jobmington/admin/users.php'],
        ['num' => number_format($liveJobs),          'lbl' => 'Live jobs',    'sub' => $newJobsWeek . ' posted this week',                                  'icon' => 'fa-briefcase',     'cls' => 's-green',  'badge' => $newJobsWeek . ' this week',     'href' => '/jobmington/admin/jobs.php'],
        ['num' => number_format($totalApplications), 'lbl' => 'Applications', 'sub' => $applicationsToday . ' today &middot; ' . $pendingApplications . ' pending', 'icon' => 'fa-file-signature', 'cls' => 's-amber', 'badge' => $applicationsToday . ' today', 'href' => '/jobmington/employer/applications.php'],
        ['num' => admin_money($completedRevenue),    'lbl' => 'Revenue',      'sub' => admin_money($monthRevenue) . ' this month',                          'icon' => 'fa-dollar-sign',   'cls' => 's-violet', 'badge' => $totalTransactions . ' txns',    'href' => '/jobmington/payments/'],
    ];
    foreach ($pstats as $s): ?>
        <a class="nc-stat <?= e($s['cls']) ?>" href="<?= e($s['href']) ?>">
            <div class="nc-stat-top">
                <span class="nc-stat-ico"><i class="fas <?= e($s['icon']) ?>"></i></span>
                <span class="nc-stat-badge"><?= $s['badge'] ?></span>
            </div>
            <div class="nc-stat-num"><?= $s['num'] ?></div>
            <div class="nc-stat-lbl"><?= e($s['lbl']) ?></div>
            <div class="nc-stat-sub"><?= $s['sub'] ?></div>
        </a>
    <?php endforeach; ?>
</div>

<!-- Secondary stats -->
<div class="nc-stats2">
    <?php
    $sstats = [
        ['num' => number_format($totalCompanies),            'lbl' => 'Employers', 'sub' => $unverifiedCompanies . ' unverified',                              'icon' => 'fa-building',      'href' => '/jobmington/admin/users.php'],
        ['num' => $publishedCourses . '/' . $totalCourses,  'lbl' => 'Courses',   'sub' => $courseEnrollments . ' enrolled &middot; ' . $totalCertificates . ' certs', 'icon' => 'fa-graduation-cap', 'href' => '/jobmington/admin/courses.php'],
        ['num' => $publicPassports . '/' . $totalPassports, 'lbl' => 'Passports', 'sub' => 'Public profiles',                                                'icon' => 'fa-id-badge',      'href' => '/jobmington/wallet/passport/'],
        ['num' => number_format($notificationsUnread),       'lbl' => 'Signals',   'sub' => $failedLoginsDay . ' failed logins',                               'icon' => 'fa-bell',          'href' => '/jobmington/admin/settings.php'],
    ];
    foreach ($sstats as $s): ?>
        <a class="nc-stat2" href="<?= e($s['href']) ?>">
            <span class="nc-stat2-ico"><i class="fas <?= e($s['icon']) ?>"></i></span>
            <span>
                <div class="nc-stat2-num"><?= e($s['num']) ?></div>
                <div class="nc-stat2-lbl"><?= e($s['lbl']) ?></div>
                <div class="nc-stat2-sub"><?= $s['sub'] ?></div>
            </span>
        </a>
    <?php endforeach; ?>
</div>

<!-- Needs attention + System health -->
<div class="nc-mid">
    <div class="nc-card">
        <div class="nc-card-head">
            <span class="nc-card-head-ico amber"><i class="fas fa-triangle-exclamation"></i></span>
            <h3>Needs attention</h3>
        </div>
        <div class="nc-att-list">
            <?php foreach ($attentionItems as $item): ?>
                <a class="nc-att-item <?= $atone($item['tone']) ?>" href="<?= e($item['href']) ?>">
                    <span class="nc-att-n"><?= number_format((int)$item['count']) ?></span>
                    <span>
                        <span class="nc-att-lbl"><?= e($item['label']) ?></span>
                        <span class="nc-att-sub"><?= e($item['detail']) ?></span>
                    </span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="nc-card">
        <div class="nc-card-head">
            <span class="nc-card-head-ico blue"><i class="fas fa-heartbeat"></i></span>
            <h3>System health</h3>
        </div>
        <div class="nc-health-list">
            <?php foreach ($healthItems as $h):
                $ht = $htone($h['tone']); ?>
                <div class="nc-health-row">
                    <div class="nc-health-top">
                        <span class="nc-health-lbl"><?= e($h['label']) ?></span>
                        <span class="nc-health-val v-<?= $ht ?>"><?= e($h['value']) ?></span>
                    </div>
                    <div class="nc-hbar"><span class="<?= $ht ?>" style="width:<?= (int)$h['percent'] ?>%"></span></div>
                    <div class="nc-health-det"><?= e($h['detail']) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Quick access -->
<div class="nc-sec-head"><h2>Quick access</h2></div>
<div class="nc-modules">
    <?php foreach ($managementLinks as $link): ?>
        <a class="nc-module" href="<?= e($link['href']) ?>">
            <span class="nc-module-ico"><i class="fas <?= e($link['icon']) ?>"></i></span>
            <b><?= e($link['label']) ?></b>
            <small><?= e($link['detail']) ?></small>
        </a>
    <?php endforeach; ?>
</div>

<!-- Feeds: Users + Jobs + Applications + Payments -->
<div class="nc-feeds">
    <div class="nc-feed-card">
        <div class="nc-feed-head"><h3>New users</h3><a href="/jobmington/admin/users.php">Manage &rarr;</a></div>
        <?php if (empty($recentUsers)): ?>
            <div class="nc-empty">No users yet.</div>
        <?php else: ?>
        <div class="jm-tablewrap"><table class="nc-feed-table">
            <?php foreach ($recentUsers as $u): ?>
            <tr>
                <td>
                    <div class="nc-row-main"><?= e($u['full_name'] ?: 'Unnamed') ?></div>
                    <div class="nc-row-sub"><?= e($u['email']) ?></div>
                </td>
                <td class="nc-right">
                    <span class="nc-pill <?= $u['is_active'] ? 'ok' : 'bad' ?>"><?= e($u['user_type']) ?></span>
                    <div class="nc-row-sub"><?= e(admin_when($u['created_at'])) ?></div>
                </td>
            </tr>
            <?php endforeach; ?>
        </table></div>
        <?php endif; ?>
    </div>

    <div class="nc-feed-card">
        <div class="nc-feed-head"><h3>Recent jobs</h3><a href="/jobmington/admin/jobs.php">Manage &rarr;</a></div>
        <?php if (empty($recentJobs)): ?>
            <div class="nc-empty">No jobs yet.</div>
        <?php else: ?>
        <div class="jm-tablewrap"><table class="nc-feed-table">
            <?php foreach ($recentJobs as $j): ?>
            <tr>
                <td>
                    <div class="nc-row-main"><?= e($j['title']) ?></div>
                    <div class="nc-row-sub"><?= e($j['company_name'] ?: 'Unknown') ?> &middot; <?= (int)$j['views'] ?> views &middot; <?= (int)$j['applications_count'] ?> apps</div>
                </td>
                <td class="nc-right">
                    <span class="nc-pill <?= $j['is_active'] ? 'ok' : 'mute' ?>"><?= $j['is_active'] ? 'Live' : 'Off' ?></span>
                    <div class="nc-row-sub"><?= e(admin_when($j['posted_at'])) ?></div>
                </td>
            </tr>
            <?php endforeach; ?>
        </table></div>
        <?php endif; ?>
    </div>

    <div class="nc-feed-card">
        <div class="nc-feed-head"><h3>Recent applications</h3><a href="/jobmington/employer/applications.php">Review &rarr;</a></div>
        <?php if (empty($recentApplications)): ?>
            <div class="nc-empty">No applications yet.</div>
        <?php else: ?>
        <div class="jm-tablewrap"><table class="nc-feed-table">
            <?php foreach ($recentApplications as $a): ?>
            <tr>
                <td>
                    <div class="nc-row-main"><?= e($a['full_name'] ?: $a['email']) ?></div>
                    <div class="nc-row-sub"><?= e($a['title']) ?><?= $a['company_name'] ? ' &middot; ' . e($a['company_name']) : '' ?></div>
                </td>
                <td class="nc-right">
                    <span class="nc-pill <?= $ftone($a['status']) ?>"><?= e($a['status']) ?></span>
                    <div class="nc-row-sub"><?= e(admin_when($a['applied_at'])) ?></div>
                </td>
            </tr>
            <?php endforeach; ?>
        </table></div>
        <?php endif; ?>
    </div>

    <div class="nc-feed-card">
        <div class="nc-feed-head"><h3>Recent payments</h3><a href="/jobmington/payments/">View &rarr;</a></div>
        <?php if (empty($recentPayments)): ?>
            <div class="nc-empty">No transactions yet.</div>
        <?php else: ?>
        <div class="jm-tablewrap"><table class="nc-feed-table">
            <?php foreach ($recentPayments as $p): ?>
            <tr>
                <td>
                    <div class="nc-row-main"><?= e($p['full_name'] ?: ($p['email'] ?: 'Unknown')) ?></div>
                    <div class="nc-row-sub"><?= e($p['plan'] ?: 'Payment') ?></div>
                </td>
                <td class="nc-right">
                    <div class="nc-row-main"><?= e(admin_money($p['amount'])) ?></div>
                    <span class="nc-pill <?= $ftone($p['status']) ?>"><?= e($p['status']) ?></span>
                </td>
            </tr>
            <?php endforeach; ?>
        </table></div>
        <?php endif; ?>
    </div>
</div>

<!-- Activity log + Webhooks -->
<div class="nc-feeds">
    <div class="nc-feed-card">
        <div class="nc-feed-head"><h3>Activity log</h3></div>
        <?php if (empty($recentActivity)): ?>
            <div class="nc-empty">No activity recorded.</div>
        <?php else: ?>
        <div class="jm-tablewrap"><table class="nc-feed-table">
            <?php foreach ($recentActivity as $act): ?>
            <tr>
                <td>
                    <div class="nc-row-main"><?= e($act['action']) ?></div>
                    <div class="nc-row-sub"><?= e($act['full_name'] ?: ($act['email'] ?: 'System')) ?> &middot; <?= e(admin_snippet($act['details'], 55)) ?></div>
                </td>
                <td class="nc-right">
                    <div class="nc-row-sub"><?= e(admin_when($act['created_at'])) ?></div>
                </td>
            </tr>
            <?php endforeach; ?>
        </table></div>
        <?php endif; ?>
    </div>

    <div class="nc-feed-card">
        <div class="nc-feed-head"><h3>Webhooks</h3><a href="/jobmington/admin/settings.php">Settings &rarr;</a></div>
        <?php if (empty($latestWebhooks)): ?>
            <div class="nc-empty">No webhook activity.</div>
        <?php else: ?>
        <div class="jm-tablewrap"><table class="nc-feed-table">
            <?php foreach ($latestWebhooks as $w):
                $bad = empty($w['processed']) || !empty($w['error_message']); ?>
            <tr>
                <td>
                    <div class="nc-row-main"><?= e($w['provider'] ?: 'webhook') ?> &middot; <?= e($w['event_type'] ?: '—') ?></div>
                    <div class="nc-row-sub"><?= e($w['reference'] ?: '') ?></div>
                </td>
                <td class="nc-right">
                    <span class="nc-pill <?= $bad ? 'bad' : 'ok' ?>"><?= $bad ? 'Issue' : 'OK' ?></span>
                    <div class="nc-row-sub"><?= e(admin_when($w['created_at'])) ?></div>
                </td>
            </tr>
            <?php endforeach; ?>
        </table></div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
