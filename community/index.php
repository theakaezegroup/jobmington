<?php
/**
 * JOBMINGTON - Community forum (public)
 */
define('JOBMINGTON', true);
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/learn_nav.php';

Session::start();
$pdo = db();

$filterCat = (int) get('category', 0);
$categories = [];
$feed = [];
try {
    $categories = $pdo->query("
        SELECT fc.*, (SELECT COUNT(*) FROM forum_topics WHERE category_id = fc.id) AS topic_count
        FROM forum_categories fc ORDER BY fc.name ASC
    ")->fetchAll();

    $where = "";
    $params = [];
    if ($filterCat > 0) { $where = "WHERE ft.category_id = ?"; $params[] = $filterCat; }

    $sql = "SELECT ft.*, u.full_name, u.profile_image, fc.name AS category_name,
                   (SELECT COUNT(*) FROM forum_replies WHERE topic_id = ft.topic_id) AS replies
            FROM forum_topics ft
            LEFT JOIN users u ON ft.user_id = u.user_id
            LEFT JOIN forum_categories fc ON ft.category_id = fc.id
            $where
            ORDER BY ft.created_at DESC LIMIT 30";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $feed = $stmt->fetchAll();
} catch (Throwable $e) { $feed = []; }

$pageTitle = 'Community - ' . SITE_NAME;
$activeAIPage = 'learn';
require_once __DIR__ . '/../includes/ai-header.php';
?>
<style>
.jm-forum { max-width:1100px; margin:0 auto; padding:36px 20px 72px; }
.jm-forum-head { display:flex; align-items:flex-start; justify-content:space-between; gap:16px; flex-wrap:wrap; margin-bottom:24px; }
.jm-forum-head h1 { font-size:clamp(30px,5vw,46px); font-weight:800; letter-spacing:-.02em; color:#061426; margin:0 0 12px; }
.jm-forum-head p { font-size:17px; color:#53667f; margin:0; max-width:560px; line-height:1.6; }
.jm-forum-new { display:inline-flex; align-items:center; gap:8px; background:#0640a3; color:#fff; border-radius:10px; padding:12px 20px; font-weight:800; font-size:14px; text-decoration:none; white-space:nowrap; }
.jm-forum-new:hover { background:#052f78; }
.jm-forum-grid { display:grid; grid-template-columns:1fr 280px; gap:24px; align-items:start; }
@media(max-width:820px){ .jm-forum-grid{grid-template-columns:1fr;} }
.jm-topic { display:flex; gap:14px; padding:16px 18px; background:#fff; border:1px solid #e4eaf3; border-radius:12px; margin-bottom:10px; text-decoration:none; transition:box-shadow .14s,transform .14s; }
.jm-topic:hover { box-shadow:0 8px 22px rgba(6,20,38,.08); transform:translateY(-1px); }
.jm-topic-av { width:42px; height:42px; border-radius:50%; flex-shrink:0; object-fit:cover; background:#eef5ff; display:grid; place-items:center; color:#0640a3; font-weight:800; font-size:16px; }
.jm-topic-main { flex:1; min-width:0; }
.jm-topic-cat { font-size:10px; font-weight:800; text-transform:uppercase; letter-spacing:.06em; color:#0640a3; }
.jm-topic-title { font-size:16px; font-weight:700; color:#061426; line-height:1.3; margin:2px 0 4px; }
.jm-topic-snippet { font-size:13px; color:#53667f; line-height:1.5; overflow:hidden; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; }
.jm-topic-meta { display:flex; gap:14px; font-size:12px; color:#94a3b8; margin-top:8px; }
.jm-forum-side h3 { font-size:12px; font-weight:800; text-transform:uppercase; letter-spacing:.07em; color:#5b6b82; margin:0 0 12px; }
.jm-forum-cat { display:flex; align-items:center; justify-content:space-between; padding:10px 14px; background:#fff; border:1px solid #e4eaf3; border-radius:10px; margin-bottom:8px; text-decoration:none; font-size:14px; font-weight:600; color:#061426; transition:border-color .14s; }
.jm-forum-cat:hover { border-color:#c8d8ef; color:#0640a3; }
.jm-forum-cat.active { background:#0640a3; border-color:#0640a3; color:#fff; }
.jm-forum-cat .cnt { font-size:12px; color:#94a3b8; font-weight:700; }
.jm-forum-cat.active .cnt { color:rgba(255,255,255,.8); }
.jm-forum-empty { text-align:center; color:#94a3b8; padding:50px 20px; background:#fff; border:1px solid #e4eaf3; border-radius:14px; }
</style>

<div class="jm-forum">
    <?= jm_breadcrumbs([['label' => 'Community']]) ?>
    <?= jm_learn_nav('forum') ?>

    <div class="jm-forum-head">
        <div>
            <h1>Community.</h1>
            <p>Ask questions, share wins, and connect with other African talent.</p>
        </div>
        <a class="jm-forum-new" href="/jobmington/community/new-topic.php">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            New topic
        </a>
    </div>

    <div class="jm-forum-grid">
        <div>
            <?php if (empty($feed)): ?>
                <div class="jm-forum-empty">No discussions yet. <a href="/jobmington/community/new-topic.php" style="color:#0640a3;">Start the first one.</a></div>
            <?php else: foreach ($feed as $t): ?>
                <a class="jm-topic" href="/jobmington/community/topic.php?id=<?= (int)$t['topic_id'] ?>">
                    <?php $img = !empty($t['profile_image']) ? (function_exists('profileImage') ? profileImage($t['profile_image']) : $t['profile_image']) : ''; ?>
                    <?php if ($img): ?>
                        <img class="jm-topic-av" src="<?= e($img) ?>" alt="">
                    <?php else: ?>
                        <span class="jm-topic-av"><?= e(strtoupper(substr($t['full_name'] ?: 'J', 0, 1))) ?></span>
                    <?php endif; ?>
                    <div class="jm-topic-main">
                        <?php if ($t['category_name']): ?><span class="jm-topic-cat"><?= e($t['category_name']) ?></span><?php endif; ?>
                        <div class="jm-topic-title"><?= e($t['title']) ?></div>
                        <div class="jm-topic-snippet"><?= e(excerpt($t['content'], 140)) ?></div>
                        <div class="jm-topic-meta">
                            <span><?= e($t['full_name'] ?: 'Member') ?></span>
                            <span><?= (int)$t['replies'] ?> repl<?= (int)$t['replies'] === 1 ? 'y' : 'ies' ?></span>
                            <span><?= (int)$t['views'] ?> views</span>
                            <span><?= e(timeAgo($t['created_at'])) ?></span>
                        </div>
                    </div>
                </a>
            <?php endforeach; endif; ?>
        </div>

        <aside class="jm-forum-side">
            <h3>Categories</h3>
            <a class="jm-forum-cat <?= $filterCat === 0 ? 'active' : '' ?>" href="/jobmington/community/">All topics</a>
            <?php foreach ($categories as $c): ?>
                <a class="jm-forum-cat <?= $filterCat === (int)$c['id'] ? 'active' : '' ?>" href="/jobmington/community/?category=<?= (int)$c['id'] ?>">
                    <span><?= e($c['name']) ?></span><span class="cnt"><?= (int)$c['topic_count'] ?></span>
                </a>
            <?php endforeach; ?>
        </aside>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/ai-footer.php'; ?>
