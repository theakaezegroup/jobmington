<?php
/**
 * Jobmington - Email queue worker
 *
 * Delivers pending emails (campaigns, job-match alerts) enqueued in
 * email_queue. Intended for cron on the VPS, every ~2 minutes. Crontab line:
 *   (every 2 min)  cd /var/www/jobmington && /usr/bin/php cron/process_email_queue.php >> logs/email-queue.log 2>&1
 *
 * Options:
 *   --limit=N   max emails to send this run (default 60)
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

define('JOBMINGTON', true);
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/email_queue.php';

date_default_timezone_set(getenv('APP_TIMEZONE') ?: 'Africa/Lagos');

$options = getopt('', ['limit::']);
$limit   = max(1, min(500, (int) ($options['limit'] ?? 60)));

$pdo = db();

$pending = (int) $pdo->query("SELECT COUNT(*) FROM email_queue WHERE status='pending' AND attempts < 3")->fetchColumn();

if ($pending === 0) {
    // Still reconcile in case a campaign finished on the previous run.
    jm_reconcile_sending_campaigns($pdo);
    echo '[' . date('Y-m-d H:i:s') . "] queue empty\n";
    exit(0);
}

$result = jm_process_email_queue($pdo, $limit);

echo '[' . date('Y-m-d H:i:s') . "] processed={$result['processed']} sent={$result['sent']} failed={$result['failed']} (pending_before={$pending})\n";
