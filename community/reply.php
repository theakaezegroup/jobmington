<?php
define('JOBMINGTON', true);
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';

Session::start();
$pdo = db();

if (!Session::isLoggedIn()) {
    Session::flash('error', 'Please sign in to post a reply.');
    Session::requireLogin('Sign in or create a free account to reply. We will bring you straight back here.');
}

if (!isPost()) {
    redirect('/jobmington/community/index.php');
}

if (!Security::verifyCSRF()) {
    Session::flash('error', 'Security check failed. Please try again.');
    redirect('/jobmington/community/index.php');
}

$topicId = (int) post('topic_id', 0);
$content = Security::clean(post('content', ''));
$userId = Session::userId();

if ($topicId <= 0) {
    redirect('/jobmington/community/index.php');
}

if ($content === '' || strlen($content) < 5) {
    Session::flash('error', 'Your reply is too short. Please provide more detail.');
    redirect('/jobmington/community/topic.php?id=' . $topicId);
}

try {
    $pdo->beginTransaction();

    jm_log_activity($userId, 'forum_reply', 'topic ' . $topicId);
    $stmt = $pdo->prepare("INSERT INTO forum_replies (topic_id, user_id, content, created_at) VALUES (?, ?, ?, NOW())");
    $stmt->execute([$topicId, $userId, $content]);

    $stmt = $pdo->prepare("
        INSERT INTO wallets (user_id, balance, lifetime_earned, lifetime_spent)
        VALUES (?, 1, 1, 0)
        ON DUPLICATE KEY UPDATE balance = balance + 1, lifetime_earned = lifetime_earned + 1
    ");
    $stmt->execute([$userId]);

    $balance = getSeeds($userId);
    $stmt = $pdo->prepare("
        INSERT INTO seed_transactions (user_id, type, amount, balance_after, source, reference_id, description, created_at)
        VALUES (?, 'earn', 1, ?, 'community_reply', ?, 'Community reply reward', NOW())
    ");
    $stmt->execute([$userId, $balance, $topicId]);

    $pdo->commit();

    Session::flash('success', 'Your reply has been posted. You earned 1 Seed.');
    redirect('/jobmington/community/topic.php?id=' . $topicId);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Community reply error: ' . $e->getMessage());
    Session::flash('error', 'Unable to post your reply. Please try again.');
    redirect('/jobmington/community/topic.php?id=' . $topicId);
}
