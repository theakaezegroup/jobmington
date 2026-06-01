<?php
/**
 * JOBMINGTON - Async Email Queue
 *
 * Bulk email (campaigns, job-match alerts) is enqueued here and delivered by
 * cron/process_email_queue.php so web requests never block on sending.
 *
 * Schema: see database/migrations/schema/0005_email_queue.php
 */

if (!defined('JOBMINGTON')) {
    die('Direct access not permitted');
}

require_once __DIR__ . '/mailer.php';

if (!function_exists('jm_enqueue_email')) {
    /**
     * Add one fully-rendered email to the queue.
     */
    function jm_enqueue_email(PDO $pdo, string $toEmail, string $toName, string $subject, string $bodyHtml, string $source = 'campaign', ?int $campaignId = null): bool {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO email_queue (campaign_id, source, to_email, to_name, subject, body_html)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$campaignId, $source, trim($toEmail), $toName, $subject, $bodyHtml]);
            return true;
        } catch (Throwable $e) {
            error_log('jm_enqueue_email error: ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('jm_process_email_queue')) {
    /**
     * Send up to $limit pending emails. Failed rows are retried up to 3 times,
     * then marked failed. Campaign counters are updated as rows resolve.
     *
     * @return array{processed:int,sent:int,failed:int}
     */
    function jm_process_email_queue(PDO $pdo, int $limit = 50): array {
        $sent = 0; $failed = 0; $processed = 0;

        $stmt = $pdo->prepare("
            SELECT id, campaign_id, to_email, to_name, subject, body_html, attempts
            FROM email_queue
            WHERE status = 'pending' AND attempts < 3
            ORDER BY id ASC
            LIMIT {$limit}
        ");
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $mailer = new Mailer();

        foreach ($rows as $row) {
            $processed++;
            $id         = (int) $row['id'];
            $campaignId = $row['campaign_id'] !== null ? (int) $row['campaign_id'] : null;
            $to         = trim($row['to_email']);

            // Honour the suppression list at send time too (it may have changed).
            if (Mailer::isSuppressed($to)) {
                $pdo->prepare("UPDATE email_queue SET status='sent', error='skipped:suppressed', sent_at=NOW() WHERE id=?")->execute([$id]);
                continue;
            }

            $ok = false;
            try {
                $ok = $mailer->send($to, $row['subject'], $row['body_html']);
            } catch (Throwable $e) {
                error_log('email_queue send error (row ' . $id . '): ' . $e->getMessage());
                $ok = false;
            }

            if ($ok) {
                $pdo->prepare("UPDATE email_queue SET status='sent', sent_at=NOW() WHERE id=?")->execute([$id]);
                if ($campaignId) {
                    $pdo->prepare("UPDATE email_campaigns SET sent_count = sent_count + 1 WHERE id=?")->execute([$campaignId]);
                }
                $sent++;
            } else {
                $attempts = (int) $row['attempts'] + 1;
                if ($attempts >= 3) {
                    $pdo->prepare("UPDATE email_queue SET status='failed', attempts=?, error='send failed' WHERE id=?")->execute([$attempts, $id]);
                    if ($campaignId) {
                        $pdo->prepare("UPDATE email_campaigns SET failed_count = failed_count + 1 WHERE id=?")->execute([$campaignId]);
                    }
                    $failed++;
                } else {
                    // leave pending for retry on a later run
                    $pdo->prepare("UPDATE email_queue SET attempts=? WHERE id=?")->execute([$attempts, $id]);
                }
            }
        }

        jm_reconcile_sending_campaigns($pdo);

        return ['processed' => $processed, 'sent' => $sent, 'failed' => $failed];
    }
}

if (!function_exists('jm_reconcile_sending_campaigns')) {
    /**
     * Finalise any campaign in 'sending' that has no pending queue rows left.
     * This replaces time-based stuck detection — a campaign is done when its
     * queue is drained, regardless of how long it took.
     */
    function jm_reconcile_sending_campaigns(PDO $pdo): void {
        try {
            $ids = $pdo->query("SELECT id FROM email_campaigns WHERE status = 'sending'")->fetchAll(PDO::FETCH_COLUMN) ?: [];
            foreach ($ids as $id) {
                $id = (int) $id;
                $pending = (int) $pdo->query("SELECT COUNT(*) FROM email_queue WHERE campaign_id = {$id} AND status = 'pending'")->fetchColumn();
                if ($pending > 0) {
                    continue;
                }
                $row = $pdo->query("SELECT sent_count, failed_count FROM email_campaigns WHERE id = {$id}")->fetch(PDO::FETCH_ASSOC);
                $finalStatus = ((int) ($row['sent_count'] ?? 0)) > 0 ? 'sent' : (((int) ($row['failed_count'] ?? 0)) > 0 ? 'failed' : 'sent');
                $pdo->prepare("UPDATE email_campaigns SET status=?, sent_at=COALESCE(sent_at, NOW()) WHERE id=?")->execute([$finalStatus, $id]);
            }
        } catch (Throwable $e) {
            error_log('jm_reconcile_sending_campaigns error: ' . $e->getMessage());
        }
    }
}
