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

Session::start();

$pdo = db();
$topicId = (int) get('id', 0);

if ($topicId <= 0) {
    redirect('/jobmington/community');
}

// Get Topic
$stmt = $pdo->prepare("
    SELECT ft.*, u.full_name, u.profile_image, u.user_type, fc.name as category_name,
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
    SELECT fr.*, u.full_name, u.profile_image, u.user_type,
           (SELECT COUNT(*) FROM forum_likes WHERE reply_id = fr.reply_id) as likes
    FROM forum_replies fr
    JOIN users u ON fr.user_id = u.user_id
    WHERE fr.topic_id = ?
    ORDER BY fr.created_at ASC
");
$stmt->execute([$topicId]);
$replies = $stmt->fetchAll();

// Get user's liked reply IDs
$userLikedReplies = [];
if (Session::isLoggedIn()) {
    $likedStmt = $pdo->prepare("SELECT reply_id FROM forum_likes WHERE reply_id IS NOT NULL AND user_id = ?");
    $likedStmt->execute([Session::userId()]);
    $userLikedReplies = $likedStmt->fetchAll(PDO::FETCH_COLUMN);
}

$pageTitle = e($topic['title']) . ' - ' . SITE_NAME;
require_once __DIR__ . '/../includes/header.php';
?>

<style>
    html, body { 
        background: #030303; 
        background-image: linear-gradient(180deg, #030303 0%, #0a0a0a 50%, #0f0f0f 100%);
        background-attachment: fixed;
    }
    .topic-card {
        background: rgba(10, 10, 10, 0.8);
        backdrop-filter: blur(24px);
        border: 1px solid rgba(255, 255, 255, 0.04);
    }
</style>

<div class="min-h-screen py-8">
    <div class="max-w-5xl mx-auto px-4">
        
        <!-- Breadcrumb -->
        <div class="flex items-center gap-2 text-sm text-zinc-500 mb-6">
            <a href="/jobmington/community" class="hover:text-white transition">Community</a>
            <i class="fas fa-chevron-right text-xs"></i>
            <span class="text-zinc-400"><?= e($topic['category_name']) ?></span>
        </div>
        
        <!-- Main Topic -->
        <div class="topic-card rounded-2xl overflow-hidden mb-6">
            <div class="p-6 md:p-8">
                <h1 class="text-2xl font-bold text-white mb-4"><?= e($topic['title']) ?></h1>
                
                <div class="flex items-center gap-3 mb-6 pb-6 border-b border-white/5">
                    <img src="<?= profileImage($topic['profile_image']) ?>" class="w-10 h-10 rounded-full object-cover">
                    <div>
                        <p class="font-medium text-white text-sm">
                            <?= e($topic['full_name']) ?>
                            <?php if ($topic['user_type'] == 'admin'): ?>
                                <span class="bg-white/10 text-white/70 text-[10px] px-2 py-0.5 rounded ml-1">ADMIN</span>
                            <?php endif; ?>
                        </p>
                        <p class="text-xs text-zinc-500"><?= formatDate($topic['created_at']) ?></p>
                    </div>
                </div>
                
                <div class="prose max-w-none text-zinc-300 leading-relaxed">
                    <?= nl2br(e($topic['content'])) ?>
                </div>
                
                <!-- Topic Actions -->
                <div class="flex items-center gap-4 mt-6 pt-6 border-t border-white/5">
                    <button onclick="toggleLike('topic', <?= $topic['topic_id'] ?>, this)" 
                            class="like-btn flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-medium transition
                                   <?= $userLikedTopic ? 'bg-red-500/20 text-red-400' : 'bg-white/5 text-zinc-400 hover:bg-white/10' ?>">
                        <i class="<?= $userLikedTopic ? 'fas' : 'far' ?> fa-heart"></i>
                        <span class="like-count"><?= $topic['likes'] ?? 0 ?></span>
                    </button>
                    <span class="text-zinc-500 text-sm flex items-center gap-2">
                        <i class="far fa-eye"></i> <?= $topic['views'] ?? 0 ?> views
                    </span>
                    <span class="text-zinc-500 text-sm flex items-center gap-2">
                        <i class="far fa-comment"></i> <?= count($replies) ?> replies
                    </span>
                </div>
            </div>
        </div>
        
        <!-- Replies -->
        <div class="space-y-6">
            <h3 class="font-bold text-zinc-400 text-sm uppercase tracking-wider ml-2"><?= count($replies) ?> Replies</h3>
            
            <?php foreach ($replies as $reply): ?>
            <div class="topic-card rounded-2xl p-6" id="reply-<?= $reply['reply_id'] ?>">
                <div class="flex items-start gap-4">
                    <img src="<?= profileImage($reply['profile_image']) ?>" class="w-10 h-10 rounded-full object-cover flex-shrink-0">
                    <div class="flex-1">
                        <div class="flex justify-between items-start">
                            <div>
                                <span class="font-bold text-white text-sm">
                                    <?= e($reply['full_name']) ?>
                                    <?php if ($reply['user_type'] == 'admin'): ?>
                                        <span class="bg-white/10 text-white/70 text-[10px] px-2 py-0.5 rounded ml-1">ADMIN</span>
                                    <?php endif; ?>
                                </span>
                                <span class="text-xs text-zinc-500 ml-2"><?= timeAgo($reply['created_at']) ?></span>
                            </div>
                        </div>
                        
                        <div class="mt-2 text-zinc-400 text-sm leading-relaxed">
                            <?= nl2br(e($reply['content'])) ?>
                        </div>
                        
                        <!-- Reply Actions -->
                        <div class="flex items-center gap-3 mt-3 pt-3 border-t border-white/5">
                            <?php $isLiked = in_array($reply['reply_id'], $userLikedReplies); ?>
                            <button onclick="toggleLike('reply', <?= $reply['reply_id'] ?>, this)" 
                                    class="like-btn flex items-center gap-1.5 text-xs font-medium transition
                                           <?= $isLiked ? 'text-red-400' : 'text-zinc-500 hover:text-red-400' ?>">
                                <i class="<?= $isLiked ? 'fas' : 'far' ?> fa-heart"></i>
                                <span class="like-count"><?= $reply['likes'] ?? 0 ?></span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Reply Form -->
        <div class="mt-8 topic-card rounded-2xl p-6">
            <?php if (Session::isLoggedIn()): ?>
                <h3 class="font-bold text-white mb-4">Post a Reply</h3>
                <form method="POST">
                    <?= csrf_field() ?>
                    <div class="mb-4">
                        <textarea name="content" rows="5" class="w-full p-4 bg-white/3 border border-white/8 rounded-xl text-white placeholder-zinc-500 focus:border-white/15 focus:outline-none transition" placeholder="Share your thoughts..." required></textarea>
                    </div>
                    <div class="flex justify-end">
                        <button type="submit" class="bg-white text-black font-bold px-6 py-2.5 rounded-xl hover:bg-zinc-200 transition">
                            Post Reply
                        </button>
                    </div>
                </form>
            <?php else: ?>
                <div class="text-center py-6">
                    <p class="text-zinc-400 mb-4">You must be logged in to reply.</p>
                    <a href="/jobmington/auth/login.php?redirect=<?= urlencode($_SERVER['REQUEST_URI']) ?>" class="inline-block bg-white text-black font-bold px-6 py-2.5 rounded-xl hover:bg-zinc-200 transition">
                        Log In to Reply
                    </a>
                </div>
            <?php endif; ?>
        </div>
        
    </div>
</div>

<script>
function toggleLike(type, targetId, btn) {
    fetch(window.location.href, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=like&type=${type}&target_id=${targetId}&csrf_token=<?= Security::csrfToken() ?>`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const icon = btn.querySelector('i');
            const count = btn.querySelector('.like-count');
            count.textContent = data.count;
            
            if (data.liked) {
                icon.classList.remove('far');
                icon.classList.add('fas');
                if (type === 'topic') {
                    btn.classList.remove('bg-white/5', 'text-zinc-400');
                    btn.classList.add('bg-red-500/20', 'text-red-400');
                } else {
                    btn.classList.remove('text-zinc-500');
                    btn.classList.add('text-red-400');
                }
            } else {
                icon.classList.remove('fas');
                icon.classList.add('far');
                if (type === 'topic') {
                    btn.classList.add('bg-white/5', 'text-zinc-400');
                    btn.classList.remove('bg-red-500/20', 'text-red-400');
                } else {
                    btn.classList.add('text-zinc-500');
                    btn.classList.remove('text-red-400');
                }
            }
        } else {
            alert(data.message || 'Please log in to like');
        }
    })
    .catch(err => console.error('Like error:', err));
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>