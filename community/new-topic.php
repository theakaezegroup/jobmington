<?php
/**
 * JOBMINGTON - The Room - Share Your Insights
 * Version 17.0: Cyber-Obsidian Form Interface
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

// Fetch categories for the selector
$stmt = $pdo->query("SELECT id, name FROM forum_categories ORDER BY name");
$categories = $stmt->fetchAll();

// Handle Post Submission
if (isPost()) {
    if (!Security::verifyCSRF()) {
        Session::flash('error', 'Security check failed. Please try again.');
        redirect('/jobmington/community/new-topic.php');
    }

    if (!Session::isLoggedIn()) {
        Session::flash('error', 'Unauthorised Access. Please Log in.');
        Session::requireLogin('Sign in or create a free account to start a discussion. We will bring you straight back here.');
    }

    $title = Security::clean(post('title', ''));
    $content = Security::clean(post('content', ''));
    $categoryId = (int) post('category_id', 0);

    if (strlen($title) < 5) {
        Session::flash('error', 'Title too short. Please provide more detail.');
        redirect('/jobmington/community/new-topic.php');
    }

    try {
        jm_log_activity($userId, 'forum_topic', $title);
        $stmt = $pdo->prepare("INSERT INTO forum_topics (category_id, user_id, title, content, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([$categoryId ?: null, Session::userId(), $title, $content]);
        $topicId = $pdo->lastInsertId();

        Session::flash('success', 'Your post has been shared in The Room!');
        redirect('/jobmington/community/topic.php?id=' . $topicId);
    } catch (Exception $e) {
        Session::flash('error', 'Failed to post. Please try again.');
        redirect('/jobmington/community/new-topic.php');
    }
}

$pageTitle = 'New topic - ' . SITE_NAME;
$activeAIPage = 'learn';
require_once __DIR__ . '/../includes/ai-header.php';
?>
<style>
.jm-nt { max-width:680px; margin:0 auto; padding:36px 20px 72px; }
.jm-nt h1 { font-size:clamp(26px,4vw,38px); font-weight:800; letter-spacing:-.02em; color:#061426; margin:0 0 8px; }
.jm-nt p.sub { font-size:15px; color:#53667f; margin:0 0 24px; }
.jm-nt-card { background:#fff; border:1px solid #e4eaf3; border-radius:14px; padding:24px; }
.jm-nt-field { margin-bottom:16px; }
.jm-nt-field label { display:block; font-size:13px; font-weight:700; color:#5b6b82; margin-bottom:6px; }
.jm-nt-field input, .jm-nt-field select, .jm-nt-field textarea { width:100%; box-sizing:border-box; border:1px solid #d8e4f4; border-radius:10px; padding:11px 14px; font:inherit; font-size:14px; background:#fbfdff; }
.jm-nt-field textarea { min-height:160px; resize:vertical; line-height:1.6; }
.jm-nt-field input:focus, .jm-nt-field select:focus, .jm-nt-field textarea:focus { outline:none; border-color:#0640a3; box-shadow:0 0 0 3px rgba(6,64,163,.08); }
.jm-nt-btn { background:#0640a3; color:#fff; border:0; border-radius:10px; padding:13px 24px; font-weight:800; font-size:14px; cursor:pointer; }
.jm-nt-login { background:#fff; border:1px solid #e4eaf3; border-radius:14px; padding:40px 24px; text-align:center; color:#53667f; }
.jm-nt-login a { color:#0640a3; font-weight:700; text-decoration:none; }
</style>

<div class="jm-nt">
    <?= jm_breadcrumbs([['label' => 'Community', 'url' => '/jobmington/community/'], ['label' => 'New topic']]) ?>
    <h1>Start a discussion.</h1>
    <p class="sub">Ask a question or share something with the community.</p>

    <?php if (!Session::isLoggedIn()): ?>
        <div class="jm-nt-login"><a href="/jobmington/auth/login.php?redirect=<?= urlencode('/jobmington/community/new-topic.php') ?>">Sign in</a> to post a topic.</div>
    <?php else: ?>
        <form class="jm-nt-card" method="post">
            <?= Security::csrfField() ?>
            <div class="jm-nt-field">
                <label>Category</label>
                <select name="category_id">
                    <option value="">General</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="jm-nt-field">
                <label>Title</label>
                <input type="text" name="title" placeholder="A clear, specific title" required>
            </div>
            <div class="jm-nt-field">
                <label>Your message</label>
                <textarea name="content" placeholder="Share the details…" required></textarea>
            </div>
            <button class="jm-nt-btn" type="submit">Post topic</button>
        </form>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/ai-footer.php'; ?>

