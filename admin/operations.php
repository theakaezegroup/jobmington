<?php
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

function jm_ops_scalar(PDO $pdo, string $sql, array $params = [], $fallback = 0) {
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $value = $stmt->fetchColumn();
        return $value === false ? $fallback : $value;
    } catch (Throwable $e) {
        error_log('Operations scalar error: ' . $e->getMessage());
        return $fallback;
    }
}

function jm_ops_tail(string $path, int $lines = 10): array {
    if (!is_readable($path)) {
        return [];
    }
    $rows = @file($path, FILE_IGNORE_NEW_LINES);
    if (!$rows) {
        return [];
    }
    return array_slice($rows, -$lines);
}

function jm_ops_env_configured(string $key): bool {
    $value = trim((string) getenv($key));
    return $value !== '' && !str_contains($value, 'xxxxxxxx');
}

function jm_ops_status_label(string $tone): string {
    return $tone === 'good' ? 'Ready' : ($tone === 'warning' ? 'Needs attention' : 'Blocked');
}

$rootPath = ROOT_PATH;
$logDir = $rootPath . '/logs';
$scraperLog = $logDir . '/job-scraper.log';
$scraperStatusPath = $logDir . '/job-scraper-status.json';
$scraperStatus = [];
if (is_readable($scraperStatusPath)) {
    $decoded = json_decode((string) file_get_contents($scraperStatusPath), true);
    $scraperStatus = is_array($decoded) ? $decoded : [];
}

$lastScraperTime = !empty($scraperStatus['finished_at']) ? strtotime((string) $scraperStatus['finished_at']) : null;
$scraperFresh = $lastScraperTime && $lastScraperTime >= strtotime('-6 hours');

$appEnv = strtolower((string) (getenv('APP_ENV') ?: 'development'));
$appDebug = strtolower((string) (getenv('APP_DEBUG') ?: 'true')) === 'true';
$paystackSecret = trim((string) getenv('PAYSTACK_SECRET_KEY'));
$paystackPublic = trim((string) getenv('PAYSTACK_PUBLIC_KEY'));
$paystackLive = str_starts_with($paystackSecret, 'sk_live_') && str_starts_with($paystackPublic, 'pk_live_');
$webhookUrl = trim((string) getenv('PAYSTACK_WEBHOOK_URL'));
$callbackUrl = trim((string) getenv('PAYSTACK_CALLBACK_URL'));

$liveJobs = (int) jm_ops_scalar($pdo, "SELECT COUNT(*) FROM jobs WHERE is_active = 1 AND (expires_at IS NULL OR expires_at >= CURDATE())");
$newJobsWeek = (int) jm_ops_scalar($pdo, "SELECT COUNT(*) FROM jobs WHERE posted_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
$pendingPaidJobs = (int) jm_ops_scalar($pdo, "
    SELECT COUNT(*)
    FROM jobs j
    WHERE COALESCE(j.is_active, 0) = 0
      AND EXISTS (
          SELECT 1 FROM transactions t
          WHERE t.plan LIKE CONCAT('%job:', j.job_id, '%')
            AND t.status = 'pending'
      )
");
$pendingPayments = (int) jm_ops_scalar($pdo, "SELECT COUNT(*) FROM transactions WHERE status = 'pending'");
$failedPaymentsDay = (int) jm_ops_scalar($pdo, "SELECT COUNT(*) FROM transactions WHERE status IN ('failed', 'abandoned') AND created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)");
$pendingApplications = (int) jm_ops_scalar($pdo, "SELECT COUNT(*) FROM job_applications WHERE status = 'pending'");
$oldPendingApplications = (int) jm_ops_scalar($pdo, "SELECT COUNT(*) FROM job_applications WHERE status = 'pending' AND applied_at < DATE_SUB(NOW(), INTERVAL 7 DAY)");

$checks = [
    [
        'label' => 'Production mode',
        'tone' => ($appEnv === 'production' && !$appDebug) ? 'good' : 'warning',
        'detail' => $appEnv === 'production' && !$appDebug ? 'Production settings are active.' : 'Set APP_ENV=production and APP_DEBUG=false on the VPS.',
    ],
    [
        'label' => 'Paystack live payments',
        'tone' => $paystackLive ? 'good' : 'danger',
        'detail' => $paystackLive ? 'Live Paystack keys are present.' : 'Add live Paystack keys before accepting paid listings.',
    ],
    [
        'label' => 'Payment return URLs',
        'tone' => (str_starts_with($callbackUrl, 'https://') && str_starts_with($webhookUrl, 'https://')) ? 'good' : 'warning',
        'detail' => 'Use HTTPS callback and webhook URLs on Linode.',
    ],
    [
        'label' => 'Uploads writable',
        'tone' => is_dir(UPLOADS_PATH) && is_writable(UPLOADS_PATH) ? 'good' : 'danger',
        'detail' => is_writable(UPLOADS_PATH) ? 'CV and media uploads can be saved.' : 'Create uploads/ and make it writable by the web server.',
    ],
    [
        'label' => 'Logs writable',
        'tone' => is_dir($logDir) && is_writable($logDir) ? 'good' : 'danger',
        'detail' => is_writable($logDir) ? 'Scraper and cron logs can be written.' : 'Create logs/ and make it writable by the web server.',
    ],
    [
        'label' => 'PHP extensions',
        'tone' => (extension_loaded('curl') && extension_loaded('openssl') && extension_loaded('fileinfo') && extension_loaded('pdo_mysql')) ? 'good' : 'danger',
        'detail' => 'Required: curl, openssl, fileinfo, pdo_mysql.',
    ],
    [
        'label' => 'Job scraper',
        'tone' => $scraperFresh ? 'good' : 'warning',
        'detail' => $lastScraperTime ? 'Last run: ' . date('M d, Y h:i A', $lastScraperTime) : 'No scraper status file yet. Run the scraper once after deploy.',
    ],
    [
        'label' => 'Payment queue',
        'tone' => $failedPaymentsDay > 0 ? 'danger' : ($pendingPayments > 0 ? 'warning' : 'good'),
        'detail' => number_format($pendingPayments) . ' pending / ' . number_format($failedPaymentsDay) . ' failed today.',
    ],
    [
        'label' => 'Applications',
        'tone' => $oldPendingApplications > 0 ? 'warning' : 'good',
        'detail' => number_format($pendingApplications) . ' pending, ' . number_format($oldPendingApplications) . ' older than 7 days.',
    ],
];

$smokePaths = [
    ['Home', '/jobmington/'],
    ['Jobs', '/jobmington/jobs/'],
    ['CV Builder', '/jobmington/cv-builder/'],
    ['Pricing', '/jobmington/pricing.php'],
    ['Post a job', '/jobmington/employer/post-job.php'],
    ['Admin jobs', '/jobmington/admin/jobs.php'],
];

$cronCommand = '*/45 * * * * cd /var/www/jobmington && /usr/bin/php cron/run_job_scrapers.php --limit=80 >> logs/job-scraper-cron.log 2>&1';
$pageTitle = 'Operations | ' . SITE_NAME;
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?></title>
    <link rel="stylesheet" href="/jobmington/assets/css/minimal-jobmington.css?v=ship-1">
</head>
<body class="jm-minimal">
    <div class="jm-shell">
        <header class="jm-header">
            <a class="jm-logo" href="/jobmington/admin/"><img src="/jobmington/assets/images/badge.png" alt=""><span>Jobmington</span></a>
            <nav class="jm-nav" aria-label="Admin navigation">
                <a href="/jobmington/admin/">Dashboard</a>
                <a href="/jobmington/admin/jobs.php">Jobs</a>
                <a href="/jobmington/admin/users.php">Users</a>
                <a class="active" href="/jobmington/admin/operations.php">Operations</a>
                <a href="/jobmington/">View site</a>
            </nav>
        </header>

        <section class="jm-section" style="padding-top:0;">
            <div class="jm-section-head">
                <div>
                    <p class="jm-kicker">Operations</p>
                    <h1>Launch readiness.</h1>
                    <p>Payments, scraping, applications, and VPS checks in one place.</p>
                </div>
                <a class="jm-button secondary" href="/jobmington/admin/">Admin dashboard</a>
            </div>

            <div class="jm-stat-grid">
                <div class="jm-stat"><strong><?= number_format($liveJobs) ?></strong><span>Live jobs</span></div>
                <div class="jm-stat"><strong><?= number_format($newJobsWeek) ?></strong><span>New this week</span></div>
                <div class="jm-stat"><strong><?= number_format($pendingPaidJobs) ?></strong><span>Awaiting payment</span></div>
                <div class="jm-stat"><strong><?= number_format($pendingApplications) ?></strong><span>Pending applications</span></div>
            </div>
        </section>

        <section class="jm-section">
            <div class="jm-section-head">
                <div>
                    <h2>Ship checks.</h2>
                    <p>Fix blocked items before pointing real users at the VPS.</p>
                </div>
            </div>
            <div class="jm-ops-checks">
                <?php foreach ($checks as $check): ?>
                    <div class="jm-ops-check <?= e('tone-' . $check['tone']) ?>">
                        <span><?= e(jm_ops_status_label($check['tone'])) ?></span>
                        <strong><?= e($check['label']) ?></strong>
                        <p><?= e($check['detail']) ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="jm-section">
            <div class="jm-grid-2">
                <article class="jm-panel">
                    <h2>Scraper</h2>
                    <div class="jm-key-list">
                        <div><span>Last run</span><strong><?= $lastScraperTime ? e(date('M d, Y h:i A', $lastScraperTime)) : 'Not yet' ?></strong></div>
                        <div><span>Inserted</span><strong><?= e((string) ($scraperStatus['totals']['inserted'] ?? 0)) ?></strong></div>
                        <div><span>Duplicates</span><strong><?= e((string) ($scraperStatus['totals']['duplicate'] ?? 0)) ?></strong></div>
                        <div><span>Failed</span><strong><?= e((string) ($scraperStatus['totals']['failed'] ?? 0)) ?></strong></div>
                    </div>
                    <p class="jm-small" style="margin-top:16px;">Linode cron:</p>
                    <pre class="jm-code-block"><?= e($cronCommand) ?></pre>
                </article>

                <article class="jm-panel">
                    <h2>Smoke test paths</h2>
                    <div class="jm-ops-links">
                        <?php foreach ($smokePaths as [$label, $href]): ?>
                            <a href="<?= e($href) ?>"><?= e($label) ?><span><?= e($href) ?></span></a>
                        <?php endforeach; ?>
                    </div>
                </article>
            </div>
        </section>

        <section class="jm-section">
            <div class="jm-section-head">
                <div>
                    <h2>Recent scraper log.</h2>
                    <p>The last lines written by the automated job importer.</p>
                </div>
            </div>
            <pre class="jm-log-box"><?php
                $tail = jm_ops_tail($scraperLog, 14);
                echo e($tail ? implode("\n", $tail) : 'No scraper log yet.');
            ?></pre>
        </section>

        <?php jm_minimal_footer(); ?>
    </div>
</body>
</html>
