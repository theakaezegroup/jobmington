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
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?></title>
    <link rel="stylesheet" href="/jobmington/assets/css/minimal-jobmington.css?v=brand-10">
    <style>
        :root {
            --admin-bg: #f5f8fc;
            --admin-ink: #061426;
            --admin-muted: #53667f;
            --admin-line: #d8e4f4;
            --admin-soft: #ffffff;
            --admin-blue: #0640a3;
            --admin-green: #0f766e;
            --admin-amber: #b66b00;
            --admin-red: #b42318;
        }

        body.jm-admin {
            margin: 0;
            background: var(--admin-bg);
            color: var(--admin-ink);
            font-family: 'Futura Cyrillic Demi';
            font-size: 15px;
            line-height: 1.55;
        }

        .admin-shell {
            width: min(100%, 1360px);
            margin: 0 auto;
            padding: 22px;
        }

        .admin-topbar,
        .admin-hero,
        .admin-panel,
        .admin-card,
        .admin-list,
        .admin-table-wrap {
            border: 1px solid var(--admin-line);
            border-radius: 8px;
            background: var(--admin-soft);
        }

        .admin-topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            padding: 14px 16px;
            margin-bottom: 18px;
        }

        .admin-brand {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            color: var(--admin-ink);
            font-weight: 700;
            text-decoration: none;
        }

        .admin-brand img {
            width: 34px;
            height: 34px;
            object-fit: contain;
        }

        .admin-nav {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
        }

        .admin-nav a,
        .admin-action {
            display: inline-flex;
            align-items: center;
            min-height: 38px;
            padding: 0 12px;
            border: 1px solid var(--admin-line);
            border-radius: 6px;
            color: var(--admin-ink);
            background: #ffffff;
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
        }

        .admin-nav a.primary,
        .admin-action.primary {
            border-color: var(--admin-blue);
            color: #ffffff;
            background: var(--admin-blue);
        }

        .admin-hero {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 20px;
            align-items: end;
            padding: 28px;
            margin-bottom: 18px;
            background: linear-gradient(135deg, #ffffff 0%, #eef7f3 100%);
        }

        .admin-kicker {
            margin: 0 0 8px;
            color: var(--admin-green);
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 0.16em;
            text-transform: uppercase;
        }

        .admin-hero h1,
        .admin-panel h2 {
            margin: 0;
            color: var(--admin-ink);
            line-height: 1.12;
        }

        .admin-hero h1 {
            font-size: clamp(32px, 4vw, 48px);
        }

        .admin-hero p,
        .admin-panel-head p,
        .admin-card p,
        .admin-list p,
        .admin-meta {
            color: var(--admin-muted);
        }

        .admin-hero p {
            max-width: 720px;
            margin: 10px 0 0;
            font-size: 16px;
        }

        .admin-status {
            min-width: 220px;
            padding: 16px;
            border: 1px solid #c7ded4;
            border-radius: 8px;
            background: #ffffff;
        }

        .admin-status strong {
            display: block;
            font-size: 28px;
            line-height: 1;
        }

        .admin-grid {
            display: grid;
            gap: 14px;
        }

        .admin-kpi-grid {
            grid-template-columns: repeat(4, minmax(0, 1fr));
            margin-bottom: 18px;
        }

        .admin-card {
            display: block;
            padding: 18px;
            color: inherit;
            text-decoration: none;
        }

        .admin-card:hover,
        .admin-list a:hover,
        .admin-management a:hover {
            border-color: var(--admin-blue);
        }

        .admin-card-top {
            display: flex;
            align-items: start;
            justify-content: space-between;
            gap: 12px;
        }

        .admin-card b {
            display: block;
            color: var(--admin-ink);
            font-size: 30px;
            line-height: 1;
            margin-top: 8px;
        }

        .admin-card span.label,
        .admin-table th {
            color: var(--admin-muted);
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .admin-mark {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            border-radius: 8px;
            color: var(--admin-blue);
            background: #edf4ff;
            font-weight: 800;
        }

        .admin-main-grid {
            grid-template-columns: minmax(0, 1.45fr) minmax(320px, 0.7fr);
            align-items: start;
            margin-bottom: 18px;
        }

        .admin-panel {
            padding: 20px;
        }

        .admin-panel-head {
            display: flex;
            align-items: start;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 16px;
        }

        .admin-panel-head h2 {
            font-size: 22px;
        }

        .admin-panel-head p {
            margin: 4px 0 0;
        }

        .admin-attention-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .admin-attention {
            display: block;
            padding: 14px;
            border: 1px solid var(--admin-line);
            border-radius: 8px;
            color: inherit;
            text-decoration: none;
        }

        .admin-attention-top {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            color: var(--admin-ink);
            font-weight: 800;
        }

        .admin-attention-count {
            font-size: 24px;
            line-height: 1;
        }

        .admin-health {
            display: grid;
            gap: 14px;
        }

        .admin-health-row {
            display: grid;
            gap: 7px;
        }

        .admin-health-top {
            display: flex;
            justify-content: space-between;
            gap: 12px;
        }

        .admin-bar {
            height: 7px;
            overflow: hidden;
            border-radius: 999px;
            background: #eaf0f8;
        }

        .admin-bar span {
            display: block;
            height: 100%;
            border-radius: inherit;
            background: var(--admin-blue);
        }

        .tone-good { color: var(--admin-green); }
        .tone-warning { color: var(--admin-amber); }
        .tone-danger { color: var(--admin-red); }
        .tone-blue { color: var(--admin-blue); }

        .admin-pill {
            display: inline-flex;
            align-items: center;
            min-height: 26px;
            padding: 0 9px;
            border: 1px solid var(--admin-line);
            border-radius: 999px;
            color: var(--admin-muted);
            background: #ffffff;
            font-size: 12px;
            font-weight: 800;
            white-space: nowrap;
        }

        .admin-pill.completed,
        .admin-pill.active,
        .admin-pill.published,
        .admin-pill.hired,
        .admin-pill.verified {
            border-color: #acd8ce;
            color: var(--admin-green);
            background: #effaf6;
        }

        .admin-pill.pending,
        .admin-pill.reviewed,
        .admin-pill.shortlisted,
        .admin-pill.interview {
            border-color: #f0d49d;
            color: var(--admin-amber);
            background: #fff8ec;
        }

        .admin-pill.failed,
        .admin-pill.abandoned,
        .admin-pill.rejected,
        .admin-pill.inactive,
        .admin-pill.expired,
        .admin-pill.locked {
            border-color: #f4b8b2;
            color: var(--admin-red);
            background: #fff4f2;
        }

        .admin-management {
            grid-template-columns: repeat(4, minmax(0, 1fr));
        }

        .admin-management a {
            display: grid;
            gap: 4px;
            min-height: 86px;
            padding: 14px;
            border: 1px solid var(--admin-line);
            border-radius: 8px;
            color: inherit;
            background: #ffffff;
            text-decoration: none;
        }

        .admin-management strong,
        .admin-list strong {
            color: var(--admin-ink);
        }

        .admin-two-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
            margin-top: 18px;
        }

        .admin-three-grid {
            grid-template-columns: repeat(3, minmax(0, 1fr));
            margin-top: 18px;
        }

        .admin-list {
            overflow: hidden;
        }

        .admin-list-head {
            padding: 18px 18px 14px;
            border-bottom: 1px solid var(--admin-line);
        }

        .admin-list-head h2 {
            margin: 0;
            font-size: 21px;
        }

        .admin-list-head p {
            margin: 4px 0 0;
        }

        .admin-list-row,
        .admin-list a {
            display: block;
            padding: 15px 18px;
            border-bottom: 1px solid var(--admin-line);
            color: inherit;
            text-decoration: none;
        }

        .admin-list-row:last-child,
        .admin-list a:last-child {
            border-bottom: 0;
        }

        .admin-row-top {
            display: flex;
            align-items: start;
            justify-content: space-between;
            gap: 12px;
        }

        .admin-empty {
            padding: 18px;
            color: var(--admin-muted);
        }

        .admin-table-wrap {
            overflow-x: auto;
            margin-top: 18px;
        }

        .admin-table {
            width: 100%;
            border-collapse: collapse;
        }

        .admin-table th,
        .admin-table td {
            padding: 13px 16px;
            border-bottom: 1px solid var(--admin-line);
            text-align: left;
            vertical-align: top;
        }

        .admin-table td {
            color: var(--admin-muted);
        }

        .admin-table strong {
            color: var(--admin-ink);
        }

        @media (max-width: 1000px) {
            .admin-kpi-grid,
            .admin-main-grid,
            .admin-management,
            .admin-two-grid,
            .admin-three-grid {
                grid-template-columns: 1fr;
            }

            .admin-hero {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 640px) {
            .admin-shell {
                padding: 14px;
            }

            .admin-topbar,
            .admin-panel-head,
            .admin-row-top {
                align-items: flex-start;
                flex-direction: column;
            }

            .admin-nav {
                width: 100%;
            }

            .admin-nav a {
                flex: 1 1 auto;
                justify-content: center;
            }

            .admin-hero,
            .admin-panel {
                padding: 18px;
            }

            .admin-attention-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body class="jm-admin">
    <div class="admin-shell">
        <header class="admin-topbar">
            <a class="admin-brand" href="/jobmington/admin/">
                <img src="/jobmington/assets/images/badge.png?v=logo-7" alt="">
                <span>Jobmington Admin</span>
            </a>
            <nav class="admin-nav" aria-label="Admin navigation">
                <a class="primary" href="/jobmington/admin/">Dashboard</a>
                <a href="/jobmington/admin/operations.php">Operations</a>
                <a href="/jobmington/admin/jobs.php">Jobs</a>
                <a href="/jobmington/admin/users.php">Users</a>
                <a href="/jobmington/">View site</a>
                <a href="/jobmington/auth/logout.php">Sign out</a>
            </nav>
        </header>

        <section class="admin-hero">
            <div>
                <p class="admin-kicker">Platform control</p>
                <h1>Admin Command Center</h1>
                <p>Monitor operations, spot issues, and jump into the tools that keep Jobmington running.</p>
            </div>
            <div class="admin-status">
                <span class="admin-meta">Database</span>
                <strong class="<?= $dbOnline ? 'tone-good' : 'tone-danger' ?>"><?= $dbOnline ? 'Online' : 'Down' ?></strong>
                <span class="admin-meta"><?= $dbOnline ? e((string) $dbLatencyMs) . ' ms response' : 'Connection failed' ?></span>
            </div>
        </section>

        <div class="admin-grid admin-kpi-grid">
            <?php foreach ($kpis as $index => $kpi): ?>
                <a class="admin-card" href="<?= e($kpi['href']) ?>">
                    <div class="admin-card-top">
                        <div>
                            <span class="label"><?= e($kpi['label']) ?></span>
                            <b><?= e($kpi['value']) ?></b>
                        </div>
                        <span class="admin-mark"><?= $index + 1 ?></span>
                    </div>
                    <p><?= e($kpi['detail']) ?></p>
                </a>
            <?php endforeach; ?>
        </div>

        <div class="admin-grid admin-main-grid">
            <section class="admin-panel">
                <div class="admin-panel-head">
                    <div>
                        <h2>Needs Attention</h2>
                        <p>Queues that deserve a quick look before they become noise.</p>
                    </div>
                    <span class="admin-pill active">Live checks</span>
                </div>
                <div class="admin-grid admin-attention-grid">
                    <?php foreach ($attentionItems as $item): ?>
                        <a class="admin-attention" href="<?= e($item['href']) ?>">
                            <div class="admin-attention-top">
                                <span><?= e($item['label']) ?></span>
                                <span class="admin-attention-count tone-<?= e($item['tone']) ?>"><?= number_format((int) $item['count']) ?></span>
                            </div>
                            <p class="admin-meta"><?= e($item['detail']) ?></p>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="admin-panel">
                <div class="admin-panel-head">
                    <div>
                        <h2>Operations Health</h2>
                        <p>Server, payments, and safety checks.</p>
                    </div>
                </div>
                <div class="admin-health">
                    <?php foreach ($healthItems as $item): ?>
                        <div class="admin-health-row">
                            <div class="admin-health-top">
                                <strong><?= e($item['label']) ?></strong>
                                <span class="admin-pill <?= e(strtolower((string) $item['value'])) ?>"><?= e($item['value']) ?></span>
                            </div>
                            <div class="admin-bar"><span style="width: <?= (int) $item['percent'] ?>%"></span></div>
                            <span class="admin-meta"><?= e($item['detail']) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        </div>

        <section class="admin-panel">
            <div class="admin-panel-head">
                <div>
                    <h2>Manage Platform</h2>
                    <p>Fast routes into the parts you will touch most often.</p>
                </div>
            </div>
            <div class="admin-grid admin-management">
                <?php foreach ($managementLinks as $link): ?>
                    <a href="<?= e($link['href']) ?>">
                        <strong><?= e($link['label']) ?></strong>
                        <span class="admin-meta"><?= e($link['detail']) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>

        <div class="admin-grid admin-two-grid">
            <section class="admin-list">
                <div class="admin-list-head">
                    <h2>Recent Jobs</h2>
                    <p>Newest listings and current publishing state.</p>
                </div>
                <?php if (empty($recentJobs)): ?>
                    <p class="admin-empty">No jobs found.</p>
                <?php else: ?>
                    <?php foreach ($recentJobs as $job): ?>
                        <?php
                            $jobStatus = ((int) $job['is_active'] === 1) ? 'active' : 'inactive';
                            if (!empty($job['expires_at']) && strtotime($job['expires_at']) < strtotime(date('Y-m-d'))) {
                                $jobStatus = 'expired';
                            }
                        ?>
                        <a href="/jobmington/jobs/view.php?id=<?= (int) $job['job_id'] ?>">
                            <div class="admin-row-top">
                                <div>
                                    <strong><?= e($job['title']) ?></strong>
                                    <p class="admin-meta"><?= e($job['company_name'] ?: 'Unknown company') ?> / <?= e(admin_when($job['posted_at'])) ?></p>
                                </div>
                                <span class="admin-pill <?= e($jobStatus) ?>"><?= e(ucfirst($jobStatus)) ?></span>
                            </div>
                            <span class="admin-meta"><?= number_format((int) $job['views']) ?> views / <?= number_format((int) $job['applications_count']) ?> recorded applications</span>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </section>

            <section class="admin-list">
                <div class="admin-list-head">
                    <h2>Recent Applications</h2>
                    <p>Latest candidate movement across roles.</p>
                </div>
                <?php if (empty($recentApplications)): ?>
                    <p class="admin-empty">No applications yet.</p>
                <?php else: ?>
                    <?php foreach ($recentApplications as $application): ?>
                        <div class="admin-list-row">
                            <div class="admin-row-top">
                                <div>
                                    <strong><?= e($application['full_name'] ?: $application['email']) ?></strong>
                                    <p class="admin-meta"><?= e($application['title']) ?> / <?= e($application['company_name'] ?: 'Unknown company') ?></p>
                                </div>
                                <span class="admin-pill <?= e((string) $application['status']) ?>"><?= e(ucfirst((string) $application['status'])) ?></span>
                            </div>
                            <span class="admin-meta"><?= e(admin_when($application['applied_at'])) ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </section>
        </div>

        <div class="admin-grid admin-three-grid">
            <section class="admin-list">
                <div class="admin-list-head">
                    <h2>New Users</h2>
                    <p><?= number_format($activeUsers) ?> active accounts.</p>
                </div>
                <?php if (empty($recentUsers)): ?>
                    <p class="admin-empty">No users yet.</p>
                <?php else: ?>
                    <?php foreach ($recentUsers as $user): ?>
                        <div class="admin-list-row">
                            <div class="admin-row-top">
                                <div>
                                    <strong><?= e($user['full_name'] ?: $user['email']) ?></strong>
                                    <p class="admin-meta"><?= e($user['email']) ?></p>
                                </div>
                                <span class="admin-pill <?= ((int) $user['is_active'] === 1) ? 'active' : 'inactive' ?>"><?= e(ucfirst((string) $user['user_type'])) ?></span>
                            </div>
                            <span class="admin-meta"><?= e(admin_when($user['created_at'])) ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </section>

            <section class="admin-list">
                <div class="admin-list-head">
                    <h2>Payments</h2>
                    <p><?= number_format($pendingPayments) ?> pending payments.</p>
                </div>
                <?php if (empty($recentPayments)): ?>
                    <p class="admin-empty">No payments yet.</p>
                <?php else: ?>
                    <?php foreach ($recentPayments as $payment): ?>
                        <div class="admin-list-row">
                            <div class="admin-row-top">
                                <div>
                                    <strong><?= e(admin_money($payment['amount'])) ?></strong>
                                    <p class="admin-meta"><?= e($payment['plan']) ?> / <?= e($payment['full_name'] ?: $payment['email'] ?: 'Unknown user') ?></p>
                                </div>
                                <span class="admin-pill <?= e((string) $payment['status']) ?>"><?= e(ucfirst((string) $payment['status'])) ?></span>
                            </div>
                            <span class="admin-meta"><?= e(admin_when($payment['created_at'])) ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </section>

            <section class="admin-list">
                <div class="admin-list-head">
                    <h2>Activity Log</h2>
                    <p>Latest tracked system actions.</p>
                </div>
                <?php if (empty($recentActivity)): ?>
                    <p class="admin-empty">No activity recorded yet.</p>
                <?php else: ?>
                    <?php foreach ($recentActivity as $activity): ?>
                        <div class="admin-list-row">
                            <strong><?= e($activity['action']) ?></strong>
                            <p class="admin-meta"><?= e(admin_snippet($activity['details'])) ?></p>
                            <span class="admin-meta"><?= e($activity['full_name'] ?: $activity['email'] ?: 'System') ?> / <?= e(admin_when($activity['created_at'])) ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </section>
        </div>

        <?php if (!empty($latestWebhooks)): ?>
            <section class="admin-table-wrap">
                <div class="admin-list-head">
                    <h2>Recent Webhooks</h2>
                    <p>Payment callbacks and processing notes.</p>
                </div>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Provider</th>
                            <th>Event</th>
                            <th>Reference</th>
                            <th>Status</th>
                            <th>Created</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($latestWebhooks as $webhook): ?>
                            <?php $webhookStatus = ((int) $webhook['processed'] === 1 && empty($webhook['error_message'])) ? 'completed' : 'failed'; ?>
                            <tr>
                                <td><?= e($webhook['provider'] ?: 'unknown') ?></td>
                                <td><strong><?= e($webhook['event_type'] ?: 'unknown') ?></strong></td>
                                <td><?= e($webhook['reference'] ?: 'none') ?></td>
                                <td><span class="admin-pill <?= e($webhookStatus) ?>"><?= e($webhookStatus === 'completed' ? 'Processed' : 'Needs review') ?></span></td>
                                <td><?= e(admin_when($webhook['created_at'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </section>
        <?php endif; ?>
    </div>
</body>
</html>
