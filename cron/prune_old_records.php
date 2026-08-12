<?php
/**
 * JOBMINGTON - retention.
 *
 * Four tables grow with traffic and nothing ever removed a row from them:
 * activity_logs, content_views, email_queue and notifications. At today's
 * volumes that is invisible. Left alone it is a table nobody can query and a
 * backup nobody can restore in a hurry, which is a problem best solved before
 * there is data in it rather than after.
 *
 * Deliberately conservative. Nothing here removes something a person can still
 * see or that anyone would reasonably audit:
 *
 *   activity_logs   18 months. It is the audit trail; a year plus change
 *                   covers any dispute worth having.
 *   content_views   6 months. The public counters are separate columns, so
 *                   totals survive; only the per-view rows behind them go.
 *   email_queue     30 days for sent or failed rows. Pending is never touched.
 *   notifications   90 days, read only. An unread one stays until it is read,
 *                   however old.
 *   broadcast_reads follows its broadcast, so nothing to do here.
 *
 * Deletes in batches so a big first run cannot hold a lock long enough to be
 * noticed by anybody using the site.
 *
 *   php cron/prune_old_records.php            # do it
 *   php cron/prune_old_records.php --dry-run  # say what it would do
 */

define('JOBMINGTON', true);

require __DIR__ . '/../config/env.php';
require __DIR__ . '/../config/constants.php';
require __DIR__ . '/../config/database.php';

$opts   = getopt('', ['dry-run']);
$dryRun = isset($opts['dry-run']);
$pdo    = db();

function jm_prune_log(string $line): void
{
    echo '[' . date('Y-m-d H:i:s') . '] ' . $line . PHP_EOL;
}

/** @var array<int, array{0:string,1:string,2:string}> table, where clause, human description */
$rules = [
    ['activity_logs', "created_at < DATE_SUB(NOW(), INTERVAL 18 MONTH)", 'activity older than 18 months'],
    ['content_views', "created_at < DATE_SUB(NOW(), INTERVAL 6 MONTH)",  'views older than 6 months'],
    ['email_queue',   "status IN ('sent','failed') AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)", 'sent or failed email older than 30 days'],
    ['notifications', "is_read = 1 AND created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)", 'read notifications older than 90 days'],
];

jm_prune_log('Retention sweep' . ($dryRun ? ' (dry run)' : ''));

$total = 0;

foreach ($rules as [$table, $where, $describe]) {
    try {
        $count = (int) $pdo->query("SELECT COUNT(*) FROM `{$table}` WHERE {$where}")->fetchColumn();

        if ($count === 0) {
            jm_prune_log(sprintf('  %-16s nothing to remove', $table));
            continue;
        }

        if ($dryRun) {
            jm_prune_log(sprintf('  %-16s would remove %s (%s)', $table, number_format($count), $describe));
            continue;
        }

        // Batched, so the first run on a table that has been growing for a
        // year does not lock it for however long that takes.
        $removed = 0;
        do {
            $stmt = $pdo->prepare("DELETE FROM `{$table}` WHERE {$where} LIMIT 1000");
            $stmt->execute();
            $batch = $stmt->rowCount();
            $removed += $batch;
            if ($batch === 1000) {
                usleep(200000);   // breathe between batches
            }
        } while ($batch === 1000);

        $total += $removed;
        jm_prune_log(sprintf('  %-16s removed %s (%s)', $table, number_format($removed), $describe));
    } catch (Throwable $e) {
        // One table having a bad day must not stop the others.
        jm_prune_log(sprintf('  %-16s FAILED: %s', $table, $e->getMessage()));
    }
}

// A read marker outlives its broadcast otherwise.
try {
    if (!$dryRun) {
        $orphans = $pdo->exec("
            DELETE r FROM broadcast_reads r
            LEFT JOIN broadcasts b ON b.broadcast_id = r.broadcast_id
            WHERE b.broadcast_id IS NULL
        ");
        if ($orphans) {
            jm_prune_log(sprintf('  %-16s removed %s orphaned read markers', 'broadcast_reads', number_format((int) $orphans)));
        }
    }
} catch (Throwable $e) {
    jm_prune_log('  broadcast_reads  FAILED: ' . $e->getMessage());
}

jm_prune_log('Done. ' . number_format($total) . ' rows removed.');
exit(0);
