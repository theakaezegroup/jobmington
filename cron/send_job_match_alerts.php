<?php
/**
 * Jobmington - Job-match alert emails
 *
 * For each verified seeker, finds new active jobs in their country and queues
 * a "N new roles match your profile" email. Delivery is handled asynchronously
 * by the email queue worker.
 *
 * SAFE BY DEFAULT: does nothing unless JOB_MATCH_ALERTS_ENABLED=true (or --force).
 * Use --dry-run to preview who would be emailed without queuing anything.
 *
 * Cron (after enabling): 0 7 * * * cd /var/www/jobmington && /usr/bin/php cron/send_job_match_alerts.php >> logs/match-alerts.log 2>&1
 *
 * Options:
 *   --dry-run     compute + print, do not queue or stamp users
 *   --force       run even if JOB_MATCH_ALERTS_ENABLED is not 'true'
 *   --days=N      job recency window in days (default 1)
 *   --limit=N     max seekers to process this run (default 500)
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

define('JOBMINGTON', true);
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/email_queue.php';

date_default_timezone_set(getenv('APP_TIMEZONE') ?: 'Africa/Lagos');

$opts    = getopt('', ['dry-run', 'force', 'days::', 'limit::']);
$dryRun  = isset($opts['dry-run']);
$force   = isset($opts['force']);
$days    = max(1, min(30, (int) ($opts['days'] ?? 1)));
$limit   = max(1, min(5000, (int) ($opts['limit'] ?? 500)));
$enabled = strtolower((string) getenv('JOB_MATCH_ALERTS_ENABLED')) === 'true';

function ma_log(string $m): void { echo '[' . date('Y-m-d H:i:s') . '] ' . $m . "\n"; }

if (!$enabled && !$force && !$dryRun) {
    ma_log('Disabled (JOB_MATCH_ALERTS_ENABLED != true). Set it to enable, or use --dry-run / --force.');
    exit(0);
}

$pdo = db();

// Short-circuit: any new jobs at all in the window?
$newJobs = (int) $pdo->query("
    SELECT COUNT(*) FROM jobs
    WHERE is_active = 1
      AND posted_at >= (NOW() - INTERVAL {$days} DAY)
      AND (expires_at IS NULL OR expires_at >= CURDATE())
")->fetchColumn();

if ($newJobs === 0) {
    ma_log("No new jobs in the last {$days} day(s) — nothing to alert.");
    exit(0);
}

$seekers = $pdo->prepare("
    SELECT user_id, full_name, email, country_id
    FROM users
    WHERE user_type = 'seeker'
      AND is_active = 1
      AND is_verified = 1
      AND country_id IS NOT NULL
      AND email IS NOT NULL AND email <> ''
      AND (last_job_match_alert IS NULL OR last_job_match_alert < (NOW() - INTERVAL 3 DAY))
    LIMIT {$limit}
");
$seekers->execute();
$seekers = $seekers->fetchAll(PDO::FETCH_ASSOC) ?: [];

$matchStmt = $pdo->prepare("
    SELECT j.title, co.name AS company
    FROM jobs j
    JOIN companies co ON j.company_id = co.company_id
    WHERE j.is_active = 1
      AND j.country_id = ?
      AND j.posted_at >= (NOW() - INTERVAL {$days} DAY)
      AND (j.expires_at IS NULL OR j.expires_at >= CURDATE())
    ORDER BY j.posted_at DESC
    LIMIT 25
");
$stampStmt = $pdo->prepare("UPDATE users SET last_job_match_alert = NOW() WHERE user_id = ?");

$queued = 0; $skippedSuppressed = 0; $noMatch = 0;

foreach ($seekers as $seeker) {
    $email = trim($seeker['email']);

    if (Mailer::isSuppressed($email)) { $skippedSuppressed++; continue; }

    $matchStmt->execute([(int) $seeker['country_id']]);
    $jobs = $matchStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    if (count($jobs) === 0) { $noMatch++; continue; }

    $count = count($jobs);
    $composed = Mailer::composeJobMatchAlert($seeker['full_name'] ?: 'there', $count, $jobs);

    if ($dryRun) {
        ma_log("WOULD QUEUE -> {$email} ({$count} matches)");
        $queued++;
        continue;
    }

    if (jm_enqueue_email($pdo, $email, $seeker['full_name'] ?: 'there', $composed['subject'], $composed['content'], 'match_alert', null)) {
        $stampStmt->execute([(int) $seeker['user_id']]);
        $queued++;
    }
}

ma_log(($dryRun ? '[DRY RUN] ' : '') . "Done. seekers=" . count($seekers) . " queued={$queued} no_match={$noMatch} suppressed={$skippedSuppressed} (window={$days}d)");
