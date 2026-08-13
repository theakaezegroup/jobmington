<?php
define('JOBMINGTON', true);
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';

Session::start();
Session::requireLogin();

$pdo = db();
$userId = Session::userId();

if (($_GET['action'] ?? '') === 'mark_all_read') {
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
    $stmt->execute([$userId]);
    redirect('/jobmington/seeker/notifications.php');
}

if (($_GET['action'] ?? '') === 'clear_read' && Security::verifyCSRF($_GET['token'] ?? '')) {
    $stmt = $pdo->prepare("DELETE FROM notifications WHERE user_id = ? AND is_read = 1");
    $stmt->execute([$userId]);
    Security::regenerateCSRF();
    redirect('/jobmington/seeker/notifications.php');
}

$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 15;
$stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ?");
$stmt->execute([$userId]);
$total = (int) $stmt->fetchColumn();
$pagination = paginate($total, $perPage, $page);

$stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?");
$stmt->execute([$userId, $pagination['per_page'], $pagination['offset']]);
$notifications = $stmt->fetchAll();
$hadUnreadNotifications = false;
foreach ($notifications as $notification) {
    if (empty($notification['is_read'])) {
        $hadUnreadNotifications = true;
        break;
    }
}

if (!empty($notifications)) {
    $ids = array_column($notifications, 'notification_id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE notification_id IN ({$placeholders}) AND user_id = ?");
    $stmt->execute([...$ids, $userId]);
}

$stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
$stmt->execute([$userId]);
$unreadCount = (int) $stmt->fetchColumn();
$pageTitle = 'Notifications | ' . SITE_NAME;
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?></title>
    <link rel="preload" as="font" type="font/ttf" href="/jobmington/assets/fonts/FuturaCyrillicDemi.ttf" crossorigin>
    <link rel="preload" as="font" type="font/ttf" href="/jobmington/assets/fonts/FuturaCyrillicBook.ttf" crossorigin>
    <link rel="stylesheet" href="/jobmington/assets/css/minimal-jobmington.css?v=brand-28">
</head>
<body class="jm-minimal">
    <div class="jm-shell">
        <header class="jm-header">
            <a class="jm-logo" href="/jobmington/"><img src="/jobmington/assets/images/badge.png?v=logo-8" alt=""><span>Jobmington</span></a>
            <?php require_once __DIR__ . '/../includes/navigation.php'; jm_workspace_nav(['dashboard' => ['/jobmington/seeker/dashboard.php', 'Dashboard'], 'applications' => ['/jobmington/seeker/applications.php', 'Applications'], 'saved' => ['/jobmington/jobs/saved.php', 'Saved jobs'], 'profile' => ['/jobmington/seeker/profile.php', 'Profile']], 'dashboard'); ?>
        </header>

        <section class="jm-section" style="padding-top:0;">
            <div class="jm-section-head">
                <div>
                    <p class="jm-kicker">Notifications</p>
                    <h1>Updates for your account.</h1>
                    <p><?= number_format($unreadCount) ?> unread <?= $unreadCount === 1 ? 'notification' : 'notifications' ?>.</p>
                </div>
                <div class="jm-form-actions">
                    <a class="jm-button secondary" href="/jobmington/seeker/notifications.php?action=mark_all_read">Mark all read</a>
                    <a class="jm-button secondary" href="/jobmington/seeker/notifications.php?action=clear_read&token=<?= e(csrf_token()) ?>">Clear read</a>
                </div>
            </div>

            <?php if ($hadUnreadNotifications || $unreadCount > 0): ?>
                <?= jm_notification_state_card('new_notification', [
                    'compact' => true,
                    'inline' => true,
                    'aria_live' => 'polite',
                ]) ?>
            <?php endif; ?>

            <?php if (empty($notifications)): ?>
                <?= jm_empty_state_card('all_good') ?>
            <?php else: ?>
                <div class="jm-job-list">
                    <?php foreach ($notifications as $notification): ?>
                        <a class="jm-job-row" href="<?= e($notification['link'] ?: '#') ?>">
                            <strong>
                                <?= e($notification['title']) ?>
                                <?php if (!empty($notification['message'])): ?>
                                    <small style="display:block;color:var(--jm-muted);font-weight:400;margin-top:4px;"><?= e($notification['message']) ?></small>
                                <?php endif; ?>
                            </strong>
                            <span><?= e(timeAgo($notification['created_at'])) ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <?php jm_minimal_footer(); ?>
    </div>
</body>
</html>
