<?php
/**
 * JOBMINGTON - Blog post (public)
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

$slug = Security::clean(get('slug', ''));
if (empty($slug)) redirect('/jobmington/blog/');

$stmt = $pdo->prepare("SELECT bp.*, u.full_name, bc.name AS cat_name, bc.slug AS cat_slug
    FROM blog_posts bp
    LEFT JOIN users u ON bp.author_id = u.user_id
    LEFT JOIN blog_categories bc ON bp.category_id = bc.id
    WHERE bp.slug = ? AND bp.is_published = 1 LIMIT 1");
$stmt->execute([$slug]);
$post = $stmt->fetch();

if (!$post) {
    http_response_code(404);
    $pageTitle = 'Post not found - ' . SITE_NAME;
    $activeAIPage = 'learn';
    require_once __DIR__ . '/../includes/ai-header.php';
    echo '<div style="max-width:680px;margin:80px auto;text-align:center;padding:0 20px;"><h1 style="color:#061426;">Post not found</h1><p style="color:#53667f;"><a href="/jobmington/blog/" style="color:#0640a3;">Back to blog</a></p></div>';
    require_once __DIR__ . '/../includes/ai-footer.php';
    exit;
}

try { $pdo->prepare("UPDATE blog_posts SET views = views + 1 WHERE post_id = ?")->execute([$post['post_id']]); } catch (Throwable $e) {}

// More posts
$more = [];
try {
    $m = $pdo->prepare("SELECT title, slug FROM blog_posts WHERE is_published = 1 AND post_id <> ? ORDER BY COALESCE(published_at, created_at) DESC LIMIT 4");
    $m->execute([$post['post_id']]);
    $more = $m->fetchAll();
} catch (Throwable $e) {}

$pageTitle = $post['title'] . ' - ' . SITE_NAME;
$activeAIPage = 'learn';
require_once __DIR__ . '/../includes/ai-header.php';
?>
<style>
.jm-post { max-width:760px; margin:0 auto; padding:36px 20px 72px; }
.jm-post-tag { font-size:11px; font-weight:800; text-transform:uppercase; letter-spacing:.08em; color:#0640a3; }
.jm-post h1 { font-size:clamp(28px,4.5vw,42px); font-weight:800; letter-spacing:-.02em; color:#061426; line-height:1.15; margin:10px 0 14px; }
.jm-post-byline { font-size:13px; color:#94a3b8; margin-bottom:24px; padding-bottom:24px; border-bottom:1px solid #e4eaf3; }
.jm-post-hero { aspect-ratio:16/8; border-radius:16px; overflow:hidden; margin-bottom:28px; background:linear-gradient(135deg,#eef5ff,#f7faff); }
.jm-post-hero img { width:100%; height:100%; object-fit:cover; }
.jm-post-body { font-size:17px; line-height:1.8; color:#1f2d3d; }
.jm-post-body p { margin:0 0 1.4em; }
.jm-post-body h2 { font-size:24px; font-weight:800; color:#061426; margin:1.6em 0 .6em; }
.jm-post-body h3 { font-size:19px; font-weight:800; color:#061426; margin:1.4em 0 .5em; }
.jm-post-body a { color:#0640a3; text-decoration:underline; text-underline-offset:3px; }
.jm-post-body img { max-width:100%; border-radius:12px; }
.jm-post-body ul, .jm-post-body ol { margin:0 0 1.4em; padding-left:1.4em; }
.jm-post-body li { margin-bottom:.5em; }
.jm-post-more { margin-top:40px; padding-top:28px; border-top:1px solid #e4eaf3; }
.jm-post-more h3 { font-size:13px; font-weight:800; text-transform:uppercase; letter-spacing:.07em; color:#5b6b82; margin:0 0 14px; }
.jm-post-more a { display:block; padding:12px 0; border-bottom:1px solid #f0f4f9; color:#061426; font-weight:600; text-decoration:none; font-size:15px; }
.jm-post-more a:hover { color:#0640a3; }
</style>

<div class="jm-post">
    <?= jm_breadcrumbs([['label' => 'Blog', 'url' => '/jobmington/blog/'], ['label' => $post['title']]]) ?>

    <span class="jm-post-tag"><?= e($post['cat_name'] ?: 'Article') ?></span>
    <h1><?= e($post['title']) ?></h1>
    <div class="jm-post-byline">
        By <strong><?= e($post['full_name'] ?: 'Jobmington') ?></strong>
        &middot; <?= e(formatDate($post['published_at'] ?: $post['created_at'])) ?>
        &middot; <?= (int)$post['views'] ?> views
    </div>

    <?php if (!empty($post['featured_image'])): ?>
        <div class="jm-post-hero"><img src="<?= e($post['featured_image']) ?>" alt="<?= e($post['title']) ?>"></div>
    <?php endif; ?>

    <div class="jm-post-body"><?php
        $content = preg_replace('#<(script|style|iframe)\b[^>]*>.*?</\1>#is', '', (string) $post['content']);
        $isPlain = strip_tags($content) === $content;
        if (Session::isLoggedIn()) {
            echo $isPlain ? nl2br(e($content)) : $content;
        } else {
            // Public, crawlable teaser + members-only wall for the rest.
            echo $isPlain ? nl2br(e(strip_tags(jm_content_teaser($content, 2)))) : jm_content_teaser($content, 2);
            echo jm_signin_wall(
                'Keep reading this article',
                'It is free — create an account or sign in to read the full post and unlock courses, ebooks, and events.',
                '/jobmington/blog/post.php?slug=' . $post['slug']
            );
        }
    ?></div>

    <?php if (!empty($more)): ?>
        <div class="jm-post-more">
            <h3>More from the blog</h3>
            <?php foreach ($more as $m): ?>
                <a href="/jobmington/blog/post.php?slug=<?= e($m['slug']) ?>"><?= e($m['title']) ?></a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/ai-footer.php'; ?>
