<?php
/**
 * JOBMINGTON - View Topic
 * Read discussion and reply
 */

define('JOBMINGTON', true);
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/forum_reactions.php';
require_once __DIR__ . '/../includes/badges.php';
require_once __DIR__ . '/../includes/learn_nav.php';

Session::start();

$pdo = db();
$topicId = (int) get('id', 0);

if ($topicId <= 0) {
    redirect('/jobmington/community');
}

// Get Topic
$stmt = $pdo->prepare("
    SELECT ft.*, u.full_name, u.profile_image, u.user_type, u.is_official, fc.name as category_name,
           (SELECT COUNT(*) FROM forum_likes WHERE topic_id = ft.topic_id) as likes
    FROM forum_topics ft
    JOIN users u ON ft.user_id = u.user_id
    LEFT JOIN forum_categories fc ON ft.category_id = fc.id
    WHERE ft.topic_id = ?
");
$stmt->execute([$topicId]);
$topic = $stmt->fetch();

if (!$topic) {
    Session::flash('error', 'Topic not found.');
    redirect('/jobmington/community');
}

// Increment Views
$pdo->prepare("UPDATE forum_topics SET views = views + 1 WHERE topic_id = ?")->execute([$topicId]);
jm_record_view('forum_topic', $topicId);

// React. Runs before the reply handler, which would otherwise treat this
// submission as an empty reply. Redirect after so a refresh does not re-toggle.
if (isPost() && Security::verifyCSRF() && !empty($_POST['react'])) {
    Session::requireLogin('Sign in or create a free account to react to posts.');
    $parts = explode(':', (string) $_POST['react']);
    if (count($parts) === 3) {
        [$rType, $rId, $rKind] = $parts;
        [$ok, $note] = jm_reaction_toggle($pdo, $rType, (int) $rId, (int) Session::userId(), $rKind);
        if (!$ok) { Session::flash('error', $note); }
    }
    Security::regenerateCSRF();
    redirect('/jobmington/community/topic.php?id=' . $topicId);
}

// Post Reply
if (isPost() && Security::verifyCSRF()) {
    Session::requireLogin();
    
    $content = Security::clean(post('content', ''));
    
    if (empty($content)) {
        Session::flash('error', 'Reply cannot be empty.');
    } else {
        $stmt = $pdo->prepare("INSERT INTO forum_replies (topic_id, user_id, content, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$topicId, Session::userId(), $content]);
        
        // Notify topic owner if not self
        if ($topic['user_id'] != Session::userId()) {
            sendNotification($topic['user_id'], 'forum_reply', 
                'New reply to: ' . truncate($topic['title'], 30), 
                Session::get('full_name') . ' replied to your discussion.',
                '/community/topic.php?id=' . $topicId
            );
        }
        
        Session::flash('success', 'Reply posted!');
        redirect('/jobmington/community/topic.php?id=' . $topicId);
    }
}

// Handle Like Action via AJAX
if (isPost() && post('action') === 'like') {
    header('Content-Type: application/json');
    
    if (!Session::isLoggedIn()) {
        echo json_encode(['success' => false, 'message' => 'Please log in to like']);
        exit;
    }
    
    $likeType = post('type', 'topic'); // 'topic' or 'reply'
    $targetId = (int) post('target_id', 0);
    $userId = Session::userId();
    
    try {
        if ($likeType === 'topic') {
            // Check if already liked
            $check = $pdo->prepare("SELECT id FROM forum_likes WHERE topic_id = ? AND user_id = ?");
            $check->execute([$targetId, $userId]);
            
            if ($check->fetch()) {
                // Unlike
                $pdo->prepare("DELETE FROM forum_likes WHERE topic_id = ? AND user_id = ?")->execute([$targetId, $userId]);
                $liked = false;
            } else {
                // Like
                $pdo->prepare("INSERT INTO forum_likes (topic_id, user_id) VALUES (?, ?)")->execute([$targetId, $userId]);
                $liked = true;
            }
            
            // Get new count
            $countStmt = $pdo->prepare("SELECT COUNT(*) FROM forum_likes WHERE topic_id = ?");
            $countStmt->execute([$targetId]);
            $count = $countStmt->fetchColumn();
        } else {
            // Reply like
            $check = $pdo->prepare("SELECT id FROM forum_likes WHERE reply_id = ? AND user_id = ?");
            $check->execute([$targetId, $userId]);
            
            if ($check->fetch()) {
                $pdo->prepare("DELETE FROM forum_likes WHERE reply_id = ? AND user_id = ?")->execute([$targetId, $userId]);
                $liked = false;
            } else {
                $pdo->prepare("INSERT INTO forum_likes (reply_id, user_id) VALUES (?, ?)")->execute([$targetId, $userId]);
                $liked = true;
            }
            
            $countStmt = $pdo->prepare("SELECT COUNT(*) FROM forum_likes WHERE reply_id = ?");
            $countStmt->execute([$targetId]);
            $count = $countStmt->fetchColumn();
        }
        
        echo json_encode(['success' => true, 'liked' => $liked, 'count' => (int)$count]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error processing like']);
    }
    exit;
}

// Check if current user liked the topic
$userLikedTopic = false;
if (Session::isLoggedIn()) {
    $likeCheck = $pdo->prepare("SELECT id FROM forum_likes WHERE topic_id = ? AND user_id = ?");
    $likeCheck->execute([$topicId, Session::userId()]);
    $userLikedTopic = (bool) $likeCheck->fetch();
}

// Get Replies with like counts
$stmt = $pdo->prepare("
    SELECT fr.*, u.full_name, u.profile_image, u.user_type, u.is_official,
           (SELECT COUNT(*) FROM forum_likes WHERE reply_id = fr.reply_id) as likes
    FROM forum_replies fr
    JOIN users u ON fr.user_id = u.user_id
    WHERE fr.topic_id = ?
    ORDER BY fr.created_at ASC
");
$stmt->execute([$topicId]);
$replies = $stmt->fetchAll();

$viewerId    = Session::isLoggedIn() ? (int) Session::userId() : null;
$canReact    = Session::isLoggedIn();
$topicReacts = jm_reactions_for($pdo, 'topic', [$topicId], $viewerId);

// One query for every author on the page rather than one per post.
$authorIds = array_unique(array_merge([(int) $topic['user_id']], array_map('intval', array_column($replies, 'user_id'))));
$authorBadges = [];
try {
    $ph = implode(',', array_fill(0, count($authorIds), '?'));
    $bs = $pdo->prepare("SELECT user_id, badge_type FROM user_badges WHERE user_id IN ($ph) ORDER BY earned_at ASC");
    $bs->execute($authorIds);
    foreach ($bs as $b) { $authorBadges[(int) $b['user_id']][] = $b['badge_type']; }
} catch (Throwable $e) { $authorBadges = []; }
$replyReacts = jm_reactions_for($pdo, 'reply', array_column($replies, 'reply_id'), $viewerId);

// Get user's liked reply IDs
$userLikedReplies = [];
if (Session::isLoggedIn()) {
    $likedStmt = $pdo->prepare("SELECT reply_id FROM forum_likes WHERE reply_id IS NOT NULL AND user_id = ?");
    $likedStmt->execute([Session::userId()]);
    $userLikedReplies = $likedStmt->fetchAll(PDO::FETCH_COLUMN);
}

$pageTitle = $topic['title'] . ' - ' . SITE_NAME;
$activeAIPage = 'learn';
require_once __DIR__ . '/../includes/ai-header.php';

function jm_forum_avatar(array $u): string {
    return jm_author_avatar($u, 'jm-tpc-av');
}
?>
<style>
.jm-tpc { max-width:760px; margin:0 auto; padding:36px 20px 72px; }
.jm-tpc-av { width:30px; height:30px; border-radius:6px; flex-shrink:0; object-fit:cover; display:block; }
.jm-tpc-cat { font-size:11px; font-weight:800; text-transform:uppercase; letter-spacing:.07em; color:#0640a3; }
.jm-tpc h1 { font-size:clamp(24px,4vw,34px); font-weight:800; letter-spacing:-.02em; color:#061426; line-height:1.2; margin:8px 0 14px; }
.jm-tpc-card { background:#fff; border:1px solid #e4eaf3; border-radius:14px; padding:22px; margin-bottom:24px; }
.jm-tpc-author { display:flex; align-items:center; gap:12px; margin-bottom:16px; }
.jm-tpc-author .nm { font-size:14px; font-weight:700; color:#061426; }
.jm-tpc-author .mt { font-size:12px; color:#94a3b8; }
.jm-tpc-content { font-size:16px; line-height:1.7; color:#1f2d3d; white-space:pre-wrap; }
.jm-tpc-stats { display:flex; gap:16px; font-size:12px; color:#94a3b8; margin-top:16px; padding-top:14px; border-top:1px solid #f0f4f9; }
.jm-tpc-replies-h { font-size:13px; font-weight:800; text-transform:uppercase; letter-spacing:.07em; color:#5b6b82; margin:0 0 14px; }
.jm-tpc-reply { display:flex; gap:12px; padding:16px 0; border-bottom:1px solid #f0f4f9; }
.jm-tpc-reply:last-child { border-bottom:none; }
.jm-tpc-reply-main { flex:1; min-width:0; }
.jm-tpc-reply-head { display:flex; align-items:center; gap:8px; margin-bottom:5px; }
.jm-tpc-reply-head .nm { font-size:13.5px; font-weight:700; color:#061426; }
.jm-tpc-reply-head .ag { font-size:12px; color:#94a3b8; }
.jm-tpc-reply-body { font-size:14.5px; line-height:1.6; color:#1f2d3d; white-space:pre-wrap; }
.jm-tpc-form { background:#fff; border:1px solid #e4eaf3; border-radius:14px; padding:20px; margin-top:24px; }
.jm-tpc-form textarea { width:100%; box-sizing:border-box; border:1px solid #d8e4f4; border-radius:10px; padding:12px 14px; font:inherit; font-size:14px; min-height:100px; resize:vertical; background:#fbfdff; }
.jm-tpc-form textarea:focus { outline:none; border-color:#0640a3; box-shadow:0 0 0 3px rgba(6,64,163,.08); }
.jm-tpc-form button { margin-top:12px; background:#0640a3; color:#fff; border:0; border-radius:10px; padding:12px 22px; font-weight:800; font-size:14px; cursor:pointer; }
.jm-tpc-login { background:#fff; border:1px solid #e4eaf3; border-radius:14px; padding:20px; margin-top:24px; text-align:center; color:#53667f; }
.jm-tpc-login a { color:#0640a3; font-weight:700; text-decoration:none; }
</style>

<div class="jm-tpc">
    <?= jm_breadcrumbs([['label' => 'Community', 'url' => '/jobmington/community/'], ['label' => $topic['title']]]) ?>

    <div class="jm-tpc-card">
        <?php if ($topic['category_name']): ?><span class="jm-tpc-cat"><?= e($topic['category_name']) ?></span><?php endif; ?>
        <h1><?= e($topic['title']) ?></h1>
        <div class="jm-tpc-author">
            <?= jm_forum_avatar($topic) ?>
            <div>
                <div class="nm"><?= e($topic['full_name'] ?: 'Member') ?><?= jm_verified_tick($topic, 14) ?><?= renderBadgeRow($authorBadges[(int) $topic['user_id']] ?? [], 'xs', 3) ?></div>
                <div class="mt"><?= e(timeAgo($topic['created_at'])) ?></div>
            </div>
        </div>
        <div class="jm-tpc-content"><?= e($topic['content']) ?></div>
        <?= jm_reaction_styles() ?>
        <?= jm_reaction_bar('topic', (int) $topicId, $topicReacts[(int) $topicId] ?? [], $canReact, $viewerId === (int) $topic['user_id']) ?>
        <div class="jm-tpc-stats">
            <span><?= count($replies) ?> repl<?= count($replies) === 1 ? 'y' : 'ies' ?></span>
            <span><?= (int)$topic['views'] ?> views</span>
        </div>
    </div>

    <div class="jm-tpc-replies-h"><?= count($replies) ?> repl<?= count($replies) === 1 ? 'y' : 'ies' ?></div>

    <?php if (!empty($replies)): ?>
        <div class="jm-tpc-card" style="padding:6px 22px;">
            <?php foreach ($replies as $r): ?>
                <div class="jm-tpc-reply">
                    <?= jm_forum_avatar($r) ?>
                    <div class="jm-tpc-reply-main">
                        <div class="jm-tpc-reply-head">
                            <span class="nm"><?= e($r['full_name'] ?: 'Member') ?><?= jm_verified_tick($r, 13) ?><?= renderBadgeRow($authorBadges[(int) $r['user_id']] ?? [], 'xs', 3) ?></span>
                            <span class="ag"><?= e(timeAgo($r['created_at'])) ?></span>
                        </div>
                        <?php if (!empty($r['is_verified_answer'])): ?>
                            <span class="jm-verified-answer"><img src="<?= e(asset('images/badge.png?v=logo-8')) ?>" alt="">Verified by Jobmington</span>
                        <?php endif; ?>
                        <div class="jm-tpc-reply-body"><?= e($r['content']) ?></div>
                        <?= jm_reaction_bar('reply', (int) $r['reply_id'], $replyReacts[(int) $r['reply_id']] ?? [], $canReact, $viewerId === (int) $r['user_id']) ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if (Session::isLoggedIn()): ?>
        <form class="jm-tpc-form" method="post">
            <?= Security::csrfField() ?>
            <textarea name="content" placeholder="Write a reply…" required></textarea>
            <button type="submit">Post reply</button>
        </form>
    <?php else: ?>
        <div class="jm-tpc-login"><a href="/jobmington/auth/login.php?redirect=<?= urlencode('/jobmington/community/topic.php?id=' . $topicId) ?>">Sign in</a> to join the discussion.</div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/ai-footer.php'; ?>


