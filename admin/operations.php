<?php
define('JOBMINGTON', true);
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/backup.php';

Session::start();
Session::requireAdmin();

$pdo = db();
$rootPath = ROOT_PATH;
$logDir = $rootPath . '/logs';

if (isPost() && post('action') === 'run_backup') {
    if (!Security::verifyCSRF()) {
        Session::flash('error', 'Invalid security token. Please try again.');
        redirect('/jobmington/admin/operations.php#backups');
    }

    try {
        $result = jm_backup_create($pdo, [
            'include_uploads' => post('include_uploads', '0') === '1',
            'keep_days' => (int) post('keep_days', 14),
        ]);
        Session::flash('success', 'Backup complete: ' . jm_backup_format_bytes((int) ($result['total_size'] ?? 0)) . ' created.');
    } catch (Throwable $e) {
        error_log('Manual backup failed: ' . $e->getMessage());
        Session::flash('error', 'Backup failed: ' . $e->getMessage());
    }

    redirect('/jobmington/admin/operations.php#backups');
}

if (isPost() && post('action') === 'refresh_storage') {
    if (!Security::verifyCSRF()) {
        Session::flash('error', 'Invalid security token. Please try again.');
        redirect('/jobmington/admin/operations.php#storage');
    }

    $storageCachePath = $logDir . '/operations-storage.json';
    if (is_file($storageCachePath)) {
        @unlink($storageCachePath);
    }
    Session::flash('success', 'VPS storage scan refreshed.');
    redirect('/jobmington/admin/operations.php#storage');
}

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

function jm_ops_database_footprints(PDO $pdo): array {
    try {
        $stmt = $pdo->query("
            SELECT table_schema, COALESCE(SUM(data_length + index_length), 0) AS total_size
            FROM information_schema.tables
            WHERE table_schema NOT IN ('information_schema', 'mysql', 'performance_schema', 'sys')
            GROUP BY table_schema
            ORDER BY total_size DESC
        ");
        return array_map(static function (array $row): array {
            return [
                'name' => (string) $row['table_schema'],
                'size' => (int) $row['total_size'],
            ];
        }, $stmt->fetchAll(PDO::FETCH_ASSOC));
    } catch (Throwable $e) {
        error_log('Operations database footprint error: ' . $e->getMessage());
        return [];
    }
}

function jm_ops_monitored_app_definitions(): array {
    return [
        [
            'name' => 'Jobmington',
            'slug' => 'jobmington',
            'candidates' => [ROOT_PATH, '/var/www/jobmington'],
            'note' => 'Current admin portal app.',
        ],
        [
            'name' => 'TechCocoon',
            'slug' => 'techcocoon',
            'candidates' => ['/var/www/techcocoon', '/srv/techcocoon', '/opt/techcocoon', '/root/techcocoon'],
            'note' => 'Not found on this VPS disk. If it is on another Linode, its disk is separate.',
        ],
        [
            'name' => 'GlaucomaOne',
            'slug' => 'glaucomaone',
            'candidates' => ['/var/www/glaucomaone', '/srv/glaucomaone', '/opt/glaucomaone', '/root/glaucomaone', '/var/www/glaucoma-one', '/srv/glaucoma-one', '/opt/glaucoma-one', '/root/glaucoma-one'],
            'note' => 'Not found on this VPS disk. Add the folder to this VPS to measure it here.',
        ],
    ];
}

function jm_ops_storage_footprints(PDO $pdo, string $logDir, int $ttlSeconds = 900): array {
    $cacheFile = $logDir . '/operations-storage.json';
    if (is_readable($cacheFile) && (time() - (filemtime($cacheFile) ?: 0)) < $ttlSeconds) {
        $cached = json_decode((string) file_get_contents($cacheFile), true);
        if (is_array($cached) && isset($cached['app_rows'])) {
            $cached['from_cache'] = true;
            return $cached;
        }
    }

    $webroot = dirname(ROOT_PATH);
    $items = [];
    if (is_dir($webroot) && is_readable($webroot)) {
        foreach (new DirectoryIterator($webroot) as $entry) {
            if ($entry->isDot() || !$entry->isDir() || $entry->isLink()) {
                continue;
            }
            $path = $entry->getPathname();
            $items[] = [
                'name' => $entry->getFilename(),
                'path' => $path,
                'size' => jm_backup_directory_size($path),
                'modified_at' => $entry->getMTime(),
                'is_current_app' => realpath($path) === realpath(ROOT_PATH),
                'is_backup_store' => realpath($path) === realpath(jm_backup_dir()),
            ];
        }
    }

    usort($items, static fn ($a, $b) => (int) ($b['size'] ?? 0) <=> (int) ($a['size'] ?? 0));
    $itemsByRealPath = [];
    foreach ($items as $item) {
        $realPath = realpath((string) $item['path']);
        if ($realPath) {
            $itemsByRealPath[$realPath] = $item;
        }
    }

    $appRows = [];
    $matchedPaths = [];
    foreach (jm_ops_monitored_app_definitions() as $app) {
        $match = null;
        foreach ($app['candidates'] as $candidate) {
            $realCandidate = realpath((string) $candidate);
            if ($realCandidate && isset($itemsByRealPath[$realCandidate])) {
                $match = $itemsByRealPath[$realCandidate];
                break;
            }
        }

        if ($match) {
            $realPath = realpath((string) $match['path']);
            if ($realPath) {
                $matchedPaths[$realPath] = true;
            }
            $match['display_name'] = $app['name'];
            $match['slug'] = $app['slug'];
            $match['status'] = 'measured';
            $match['note'] = $app['note'];
            $match['is_named_app'] = true;
            $appRows[] = $match;
            continue;
        }

        $appRows[] = [
            'display_name' => $app['name'],
            'name' => $app['slug'],
            'slug' => $app['slug'],
            'path' => implode(' or ', $app['candidates']),
            'size' => 0,
            'modified_at' => null,
            'status' => 'not_found',
            'note' => $app['note'],
            'is_named_app' => true,
            'is_current_app' => false,
            'is_backup_store' => false,
        ];
    }

    foreach ($items as $item) {
        $realPath = realpath((string) $item['path']);
        if ($realPath && isset($matchedPaths[$realPath])) {
            continue;
        }
        $item['display_name'] = $item['name'];
        $item['status'] = 'measured';
        $item['note'] = 'Other folder on this VPS.';
        $item['is_named_app'] = false;
        $appRows[] = $item;
    }

    $payload = [
        'created_at' => date('c'),
        'webroot' => $webroot,
        'items' => $items,
        'app_rows' => $appRows,
        'total_tracked_size' => array_sum(array_map(static fn ($item) => (int) ($item['size'] ?? 0), $items)),
        'visible_databases' => jm_ops_database_footprints($pdo),
        'from_cache' => false,
    ];

    if (PHP_SAPI !== 'cli' && is_dir($logDir) && is_writable($logDir)) {
        @file_put_contents($cacheFile, json_encode($payload, JSON_PRETTY_PRINT));
    }

    return $payload;
}

$scraperLog = $logDir . '/job-scraper.log';
$backupCronLog = $logDir . '/backup-cron.log';
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
$backupStatus = jm_backup_status($pdo);
$backupList = jm_backup_list(10);
$backupLatestTime = $backupStatus['latest_time'] ? date('M d, Y h:i A', $backupStatus['latest_time']) : 'Never';
$backupDailyCron = '0 2 * * * cd /var/www/jobmington && /usr/bin/php cron/run_backup.php >> logs/backup-cron.log 2>&1';
$backupWeeklyCron = '30 2 * * 0 cd /var/www/jobmington && /usr/bin/php cron/run_backup.php --include-uploads >> logs/backup-cron.log 2>&1';
$storageFootprints = jm_ops_storage_footprints($pdo, $logDir);
$storageScannedAt = !empty($storageFootprints['created_at']) ? strtotime((string) $storageFootprints['created_at']) : null;
$vpsUsedBytes = max(0, (int) ($backupStatus['disk_total'] ?? 0) - (int) ($backupStatus['disk_free'] ?? 0));
$trackedWebBytes = (int) ($storageFootprints['total_tracked_size'] ?? 0);
$trackedShareOfUsed = $vpsUsedBytes > 0 ? round(($trackedWebBytes / $vpsUsedBytes) * 100, 1) : 0;
$visibleDatabaseBytes = array_sum(array_map(static fn ($item) => (int) ($item['size'] ?? 0), $storageFootprints['visible_databases'] ?? []));

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
    [
        'label' => 'Backup directory',
        'tone' => ($backupStatus['exists'] && $backupStatus['writable']) ? 'good' : 'danger',
        'detail' => ($backupStatus['exists'] && $backupStatus['writable']) ? 'Writable: ' . $backupStatus['dir'] : 'Create/chown: ' . $backupStatus['dir'],
    ],
    [
        'label' => 'Backup freshness',
        'tone' => $backupStatus['is_stale'] ? 'warning' : 'good',
        'detail' => 'Last successful backup: ' . $backupLatestTime,
    ],
    [
        'label' => 'VPS disk space',
        'tone' => $backupStatus['disk_used_percent'] > 85 ? 'danger' : ($backupStatus['disk_used_percent'] > 70 ? 'warning' : 'good'),
        'detail' => $backupStatus['disk_used_percent'] . '% used / ' . jm_backup_format_bytes($backupStatus['disk_free']) . ' free.',
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
require_once __DIR__ . '/../includes/header.php';
?>
<!-- This page used to build its own document, with its own header and a
     five-link nav that matched nothing else in the admin. It uses the
     shared chrome now. The minimal stylesheet comes along because the
     markup below depends on its section and panel classes; its global
     rules are scoped to body.jm-minimal, which this page no longer is, so
     they cannot fight the admin shell. -->
<link rel="stylesheet" href="/jobmington/assets/css/minimal-jobmington.css?v=brand-21">
    <style>
        .jm-backup-grid {
            display: grid;
            gap: 14px;
            grid-template-columns: repeat(4, minmax(0, 1fr));
        }
        .jm-backup-card {
            background: #ffffff;
            border: 1px solid var(--jm-line);
            border-radius: 8px;
            padding: 16px;
        }
        .jm-backup-card span {
            color: var(--jm-muted);
            display: block;
            font-size: 12px;
        }
        .jm-backup-card strong {
            color: var(--jm-ink);
            display: block;
            font-size: 24px;
            line-height: 1.1;
            margin: 7px 0;
        }
        .jm-backup-actions {
            align-items: center;
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
        }
        .jm-backup-actions label {
            align-items: center;
            color: var(--jm-muted);
            display: inline-flex;
            font-size: 13px;
            gap: 8px;
        }
        .jm-backup-table {
            border-collapse: collapse;
            min-width: 820px;
            width: 100%;
        }
        .jm-backup-table--narrow { min-width: 520px; }
        .jm-backup-table th,
        .jm-backup-table td {
            border-bottom: 1px solid var(--jm-line);
            padding: 12px;
            text-align: left;
            vertical-align: top;
        }
        .jm-backup-table th {
            color: var(--jm-muted);
            font-size: 11px;
            text-transform: uppercase;
        }
        .jm-backup-file-links {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        .jm-backup-file-links a {
            border: 1px solid #cfe0f7;
            border-radius: 999px;
            color: var(--jm-blue);
            font-size: 12px;
            padding: 6px 9px;
            text-decoration: none;
        }
        .jm-storage-meter {
            background: #edf2f7;
            border-radius: 999px;
            height: 8px;
            margin-top: 8px;
            overflow: hidden;
        }
        .jm-storage-fill {
            background: var(--jm-blue);
            border-radius: inherit;
            display: block;
            height: 100%;
            min-width: 4px;
        }
        .jm-storage-meta {
            color: var(--jm-muted);
            display: flex;
            flex-wrap: wrap;
            font-size: 12px;
            gap: 12px;
            margin-top: 10px;
        }
        .jm-storage-tag {
            background: #eef6ff;
            border-radius: 999px;
            color: var(--jm-blue);
            display: inline-block;
            font-size: 11px;
            margin-left: 6px;
            padding: 3px 7px;
        }
        .jm-flash {
            border: 1px solid var(--jm-line);
            border-radius: 8px;
            margin: 0 0 14px;
            padding: 12px 14px;
        }
        .jm-flash.success {
            background: #effaf5;
            border-color: #bfe9d9;
            color: #0f766e;
        }
        .jm-flash.error {
            background: #fff5f5;
            border-color: #ffc9c9;
            color: #b42318;
        }
        @media (max-width: 900px) {
            .jm-backup-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }
        @media (max-width: 560px) {
            .jm-backup-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
<div class="jm-shell">

        <?php foreach (Session::getFlash('success') as $message): ?>
            <div class="jm-flash success"><?= e($message) ?></div>
        <?php endforeach; ?>
        <?php foreach (Session::getFlash('error') as $message): ?>
            <div class="jm-flash error"><?= e($message) ?></div>
        <?php endforeach; ?>

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

        <section class="jm-section" id="backups">
            <div class="jm-section-head">
                <div>
                    <p class="jm-kicker">VPS and backups</p>
                    <h2>Space, database, uploads, and recovery.</h2>
                    <p>Manual backups can be downloaded by admins. Automatic backups run from cron using the commands below.</p>
                </div>
                <form method="POST" class="jm-backup-actions">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="run_backup">
                    <input type="hidden" name="keep_days" value="14">
                    <label><input type="checkbox" name="include_uploads" value="1"> Include uploads archive</label>
                    <button class="jm-button" type="submit">Run backup now</button>
                </form>
            </div>

            <div class="jm-backup-grid">
                <article class="jm-backup-card">
                    <span>VPS disk</span>
                    <strong><?= e((string) $backupStatus['disk_used_percent']) ?>% used</strong>
                    <span><?= e(jm_backup_format_bytes($backupStatus['disk_free'])) ?> free of <?= e(jm_backup_format_bytes($backupStatus['disk_total'])) ?></span>
                </article>
                <article class="jm-backup-card">
                    <span>Database size</span>
                    <strong><?= e(jm_backup_format_bytes($backupStatus['database_size'])) ?></strong>
                    <span>Included in every backup</span>
                </article>
                <article class="jm-backup-card">
                    <span>Uploads size</span>
                    <strong><?= e(jm_backup_format_bytes($backupStatus['uploads_size'])) ?></strong>
                    <span><?= $backupStatus['zip_available'] ? 'Can be zipped into manual/full backups' : 'ZipArchive missing on server' ?></span>
                </article>
                <article class="jm-backup-card">
                    <span>Backup storage</span>
                    <strong><?= e(jm_backup_format_bytes($backupStatus['backup_size'])) ?></strong>
                    <span><?= e($backupStatus['dir']) ?></span>
                </article>
            </div>

            <div class="jm-grid-2" id="storage" style="margin-top:16px;">
                <article class="jm-panel" style="overflow-x:auto;">
                    <div class="jm-section-head" style="margin-bottom:10px;">
                        <div>
                            <h2>App footprints</h2>
                            <p>Scans named apps plus first-level folders in <?= e((string) ($storageFootprints['webroot'] ?? dirname(ROOT_PATH))) ?>. Apps hosted on another VPS have separate disk space and show as not found here.</p>
                        </div>
                        <form method="POST">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="refresh_storage">
                            <button class="jm-button secondary" type="submit">Refresh scan</button>
                        </form>
                    </div>
                    <div class="jm-storage-meta">
                        <span>Tracked web folders: <?= e(jm_backup_format_bytes($trackedWebBytes)) ?></span>
                        <span>Share of used VPS disk: <?= e((string) $trackedShareOfUsed) ?>%</span>
                        <span>Scanned: <?= e($storageScannedAt ? date('M d, Y h:i A', $storageScannedAt) : 'Just now') ?><?= !empty($storageFootprints['from_cache']) ? ' (cached)' : '' ?></span>
                    </div>
                    <table class="jm-backup-table jm-stacktable" style="margin-top:12px;">
                        <thead>
                            <tr>
                                <th>App / folder</th>
                                <th>Status</th>
                                <th>Size</th>
                                <th>Share</th>
                                <th>Modified</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($storageFootprints['app_rows'])): ?>
                                <tr><td colspan="5">No readable app folders found.</td></tr>
                            <?php else: ?>
                                <?php foreach ($storageFootprints['app_rows'] as $item): ?>
                                    <?php
                                        $share = $trackedWebBytes > 0 ? round(((int) $item['size'] / $trackedWebBytes) * 100, 1) : 0;
                                        $barWidth = $share > 0 ? max(2, min(100, $share)) : 0;
                                        $isMeasured = ($item['status'] ?? '') === 'measured';
                                    ?>
                                    <tr>
                                        <td>
                                            <strong><?= e((string) ($item['display_name'] ?? $item['name'])) ?></strong>
                                            <?php if (!empty($item['is_named_app'])): ?><span class="jm-storage-tag">Named app</span><?php endif; ?>
                                            <?php if (!empty($item['is_current_app'])): ?><span class="jm-storage-tag">Jobmington</span><?php endif; ?>
                                            <?php if (!empty($item['is_backup_store'])): ?><span class="jm-storage-tag">Backups</span><?php endif; ?>
                                            <div class="jm-small"><?= e((string) $item['path']) ?></div>
                                            <?php if (!empty($item['note'])): ?><div class="jm-small"><?= e((string) $item['note']) ?></div><?php endif; ?>
                                        </td>
                                        <td><?= $isMeasured ? 'Measured on this VPS' : 'Not found on this VPS' ?></td>
                                        <td><?= $isMeasured ? e(jm_backup_format_bytes($item['size'] ?? 0)) : '-' ?></td>
                                        <td>
                                            <?= $isMeasured ? e((string) $share) . '%' : '-' ?>
                                            <?php if ($isMeasured): ?>
                                                <div class="jm-storage-meter"><span class="jm-storage-fill" style="width: <?= e((string) $barWidth) ?>%;"></span></div>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= e($isMeasured && !empty($item['modified_at']) ? date('M d, Y h:i A', (int) $item['modified_at']) : '-') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </article>

                <article class="jm-panel" style="overflow-x:auto;">
                    <h2>Database footprints</h2>
                    <p class="jm-small">This shows the database schemas the Jobmington database user can see. Other apps may use schemas hidden from this user, but their space is still included in the VPS disk total.</p>
                    <div class="jm-storage-meta">
                        <span>Visible DB total: <?= e(jm_backup_format_bytes($visibleDatabaseBytes)) ?></span>
                        <span>Current Jobmington DB: <?= e(jm_backup_format_bytes($backupStatus['database_size'])) ?></span>
                    </div>
                    <table class="jm-backup-table jm-backup-table--narrow jm-stacktable" style="margin-top:12px;">
                        <thead>
                            <tr>
                                <th>Schema</th>
                                <th>Size</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($storageFootprints['visible_databases'])): ?>
                                <tr><td colspan="2">No database schema sizes available.</td></tr>
                            <?php else: ?>
                                <?php foreach ($storageFootprints['visible_databases'] as $database): ?>
                                    <tr>
                                        <td><?= e((string) $database['name']) ?></td>
                                        <td><?= e(jm_backup_format_bytes($database['size'] ?? 0)) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </article>
            </div>

            <div class="jm-grid-2" style="margin-top:16px;">
                <article class="jm-panel">
                    <h2>Automatic schedule</h2>
                    <p class="jm-small">Daily database backup:</p>
                    <pre class="jm-code-block"><?= e($backupDailyCron) ?></pre>
                    <p class="jm-small" style="margin-top:12px;">Weekly full backup with uploads:</p>
                    <pre class="jm-code-block"><?= e($backupWeeklyCron) ?></pre>
                </article>

                <article class="jm-panel">
                    <h2>Recovery notes</h2>
                    <div class="jm-key-list">
                        <div><span>Last backup</span><strong><?= e($backupLatestTime) ?></strong></div>
                        <div><span>Directory writable</span><strong><?= $backupStatus['writable'] ? 'Yes' : 'No' ?></strong></div>
                        <div><span>Retention</span><strong>14 days</strong></div>
                        <div><span>Downloads</span><strong>Admin only</strong></div>
                    </div>
                </article>
            </div>

            <div class="jm-panel" style="margin-top:16px; overflow-x:auto;">
                <h2>Available backups</h2>
                <table class="jm-backup-table jm-stacktable">
                    <thead>
                        <tr>
                            <th>Created</th>
                            <th>Type</th>
                            <th>Size</th>
                            <th>Files</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$backupList): ?>
                            <tr><td colspan="4">No backups have been created yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($backupList as $backup): ?>
                                <?php
                                    $files = array_values(array_filter($backup['files'] ?? [], static fn ($file) => !empty($file['name'])));
                                    $types = implode(', ', array_map(static fn ($file) => ucfirst((string) $file['type']), $files));
                                ?>
                                <tr>
                                    <td><?= e(!empty($backup['created_at']) ? date('M d, Y h:i A', strtotime((string) $backup['created_at'])) : 'Unknown') ?></td>
                                    <td><?= e($types ?: 'Manifest') ?></td>
                                    <td><?= e(jm_backup_format_bytes($backup['total_size'] ?? 0)) ?></td>
                                    <td>
                                        <div class="jm-backup-file-links">
                                            <?php foreach ($files as $file): ?>
                                                <a href="/jobmington/admin/backup-download.php?file=<?= urlencode((string) $file['name']) ?>">
                                                    <?= e(ucfirst((string) $file['type'])) ?> <?= e(jm_backup_format_bytes($file['size'] ?? 0)) ?>
                                                </a>
                                            <?php endforeach; ?>
                                            <?php if (!empty($backup['manifest_name'])): ?>
                                                <a href="/jobmington/admin/backup-download.php?file=<?= urlencode((string) $backup['manifest_name']) ?>">Manifest</a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="jm-panel" style="margin-top:16px;">
                <h2>Backup cron log</h2>
                <pre class="jm-log-box"><?php
                    $backupTail = jm_ops_tail($backupCronLog, 8);
                    echo e($backupTail ? implode("\n", $backupTail) : 'No automatic backup log yet.');
                ?></pre>
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

    </div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
