<?php
/**
 * JOBMINGTON - Notifications API (list, unread count, mark read).
 */
define('JOBMINGTON', true);
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');
Session::start();

if (!Session::isLoggedIn()) {
    jsonError('Not authenticated.', 401);
}

$pdo    = db();
$userId = (int) Session::userId();
$action = $_GET['action'] ?? 'list';

function jm_notif_time_ago(string $datetime): string {
    $diff = time() - strtotime($datetime);
    if ($diff < 60)    return 'just now';
    if ($diff < 3600)  return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';
    return date('M j', strtotime($datetime));
}

try {
    /*
     * Two sources, one inbox.
     *
     * Personal notifications are per person and live in notifications.
     * Announcements are stored once in broadcasts, with a read marker per
     * person who has actually read one, so announcing to the membership does
     * not write a row per member.
     *
     * Ids are prefixed, n for a notification and b for a broadcast, because
     * the two tables have their own id sequences and would otherwise collide.
     */
    if ($action === 'list') {
        $stmt = $pdo->prepare("SELECT notification_id, type, title, message, link, is_read, created_at
                               FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 12");
        $stmt->execute([$userId]);

        $items = [];
        foreach ($stmt as $n) {
            $items[] = [
                'id'      => 'n' . (int) $n['notification_id'],
                'type'    => (string) $n['type'],
                'title'   => (string) $n['title'],
                'message' => (string) ($n['message'] ?? ''),
                'link'    => (string) ($n['link'] ?? ''),
                'is_read' => (int) $n['is_read'],
                'at'      => strtotime($n['created_at']),
                'ago'     => jm_notif_time_ago($n['created_at']),
            ];
        }

        foreach (jm_broadcasts_for($userId, 12) as $b) {
            $items[] = [
                'id'      => 'b' . (int) $b['broadcast_id'],
                'type'    => (string) $b['type'],
                'title'   => (string) $b['title'],
                'message' => (string) ($b['message'] ?? ''),
                'link'    => (string) ($b['link'] ?? ''),
                'is_read' => (int) $b['is_read'],
                'at'      => strtotime($b['created_at']),
                'ago'     => jm_notif_time_ago($b['created_at']),
            ];
        }

        usort($items, static fn($a, $b) => $b['at'] <=> $a['at']);
        $items = array_slice($items, 0, 12);

        $count = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
        $count->execute([$userId]);
        $unread = (int) $count->fetchColumn();

        // Unread announcements are the ones with no read marker, counted
        // rather than stored.
        $bcount = $pdo->prepare("
            SELECT COUNT(*)
            FROM broadcasts b
            JOIN users u ON u.user_id = ?
            LEFT JOIN broadcast_reads r ON r.broadcast_id = b.broadcast_id AND r.user_id = u.user_id
            WHERE r.user_id IS NULL
              AND (b.audience IS NULL OR b.audience = u.user_type)
              AND b.created_at >= u.created_at
        ");
        $bcount->execute([$userId]);
        $unread += (int) $bcount->fetchColumn();

        /*
         * The bell rings only for something that arrived after it started
         * watching. This used to be the highest notification id; with two
         * tables that no longer compares, so it is the newest timestamp across
         * both. Still only ever goes up.
         */
        $latest = 0;
        foreach ($items as $i) {
            $latest = max($latest, (int) $i['at']);
        }

        jsonSuccess(['items' => $items, 'unread' => $unread, 'latest' => $latest]);
    }

    if ($action === 'read') {
        $raw = (string) ($_POST['id'] ?? $_GET['id'] ?? '');
        $id  = (int) substr($raw, 1);

        if ($id > 0 && str_starts_with($raw, 'b')) {
            $pdo->prepare("INSERT IGNORE INTO broadcast_reads (broadcast_id, user_id) VALUES (?, ?)")
                ->execute([$id, $userId]);
        } elseif ($id > 0 && str_starts_with($raw, 'n')) {
            $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE notification_id = ? AND user_id = ?")
                ->execute([$id, $userId]);
        }

        jsonSuccess(['ok' => true]);
    }

    if ($action === 'read_all') {
        $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0")->execute([$userId]);

        // One marker per announcement this person can actually see, and only
        // for the ones they had not already read.
        $pdo->prepare("
            INSERT IGNORE INTO broadcast_reads (broadcast_id, user_id)
            SELECT b.broadcast_id, u.user_id
            FROM broadcasts b
            JOIN users u ON u.user_id = ?
            WHERE (b.audience IS NULL OR b.audience = u.user_type)
              AND b.created_at >= u.created_at
        ")->execute([$userId]);

        jsonSuccess(['unread' => 0]);
    }

    jsonError('Unknown action.');
} catch (Throwable $e) {
    error_log('Notifications API error: ' . $e->getMessage());
    jsonError('Could not load notifications.', 500);
}
