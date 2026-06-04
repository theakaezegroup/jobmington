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
    if ($action === 'list') {
        $stmt = $pdo->prepare("SELECT notification_id, type, title, message, link, is_read, created_at
                               FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 12");
        $stmt->execute([$userId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $items = array_map(static function ($n) {
            return [
                'id'      => (int) $n['notification_id'],
                'type'    => (string) $n['type'],
                'title'   => (string) $n['title'],
                'message' => (string) ($n['message'] ?? ''),
                'link'    => (string) ($n['link'] ?? ''),
                'is_read' => (int) $n['is_read'],
                'ago'     => jm_notif_time_ago($n['created_at']),
            ];
        }, $rows);

        $unread = (int) $pdo->query("SELECT COUNT(*) FROM notifications WHERE user_id = $userId AND is_read = 0")->fetchColumn();

        jsonSuccess(['items' => $items, 'unread' => $unread]);
    }

    if ($action === 'read') {
        $id = (int) ($_POST['id'] ?? $_GET['id'] ?? 0);
        if ($id > 0) {
            $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE notification_id = ? AND user_id = ?")->execute([$id, $userId]);
        }
        $unread = (int) $pdo->query("SELECT COUNT(*) FROM notifications WHERE user_id = $userId AND is_read = 0")->fetchColumn();
        jsonSuccess(['unread' => $unread]);
    }

    if ($action === 'read_all') {
        $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0")->execute([$userId]);
        jsonSuccess(['unread' => 0]);
    }

    jsonError('Unknown action.');
} catch (Throwable $e) {
    error_log('Notifications API error: ' . $e->getMessage());
    jsonError('Could not load notifications.', 500);
}
