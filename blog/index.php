<?php
/**
 * JOBMINGTON - Blog (public listing)
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

$filterCat = Security::clean(get('category', ''));
$categories = [];
$posts = [];
$featured = null;
try {
    $categories = $pdo->query("SELECT * FROM blog_categories ORDER BY name")->fetchAll();

    $where = "WHERE bp.is_published = 1";
    $params = [];
    if ($filterCat) { $where .= " AND bc.slug = ?"; $params[] = $filterCat; }

    $sql = "SELECT bp.*, u.full_name, bc.name AS cat_name, bc.slug AS cat_slug
            FROM blog_posts bp
            LEFT JOIN users u ON bp.author_id = u.user_id
            LEFT JOIN blog_categories bc ON bp.category_id = bc.id
            $where
            ORDER BY COALESCE(bp.published_at, bp.created_at) DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $posts = $stmt->fetchAll();

    if (!$filterCat && !empty($posts)) {
        $featured = array_shift($posts);
    }
} catch (Throwable $e) { $posts = []; }

$pageTitle = 'Blog - ' . SITE_NAME;
$activeAIPage = 'learn';
require_once __DIR__ . '/../includes/ai-header.php';
?>
<style>
.jm-blog { max-width:1100px; margin:0 auto; padding:36px 20px 72px; }
.jm-blog-head h1 { font-size:clamp(30px,5vw,46px); font-weight:800; letter-spacing:-.02em; color:#061426; margin:0 0 12px; }
.jm-blog-head p { font-size:17px; color:#53667f; margin:0 0 24px; max-width:600px; line-height:1.6; }
.jm-blog-cats { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:30px; }
.jm-blog-cat { font-size:13px; font-weight:700; padding:7px 14px; border-radius:99px; border:1px solid #e4eaf3; background:#fff; color:#53667f; text-decoration:none; transition:all .14s; }
.jm-blog-cat:hover { border-color:#c8d8ef; color:#0640a3; }
.jm-blog-cat.active { background:#0640a3; border-color:#0640a3; color:#fff; }
.jm-blog-featured { display:grid; grid-template-columns:1.4fr 1fr; gap:0; background:#fff; border:1px solid #e4eaf3; border-radius:16px; overflow:hidden; margin-bottom:30px; text-decoration:none; transition:box-shadow .16s; }
.jm-blog-featured:hover { box-shadow:0 12px 30px rgba(6,20,38,.1); }
@media(max-width:760px){ .jm-blog-featured{grid-template-columns:1fr;} }
.jm-blog-featured-img { aspect-ratio:16/10; background:linear-gradient(135deg,#eef5ff,#f7faff); display:grid; place-items:center; overflow:hidden; }
.jm-blog-featured-img img { width:100%; height:100%; object-fit:cover; }
.jm-blog-featured-img svg { width:46px; height:46px; color:#9bb0c7; }
.jm-blog-featured-body { padding:28px; display:flex; flex-direction:column; justify-content:center; }
.jm-blog-tag { font-size:10px; font-weight:800; text-transform:uppercase; letter-spacing:.07em; color:#0640a3; margin-bottom:10px; }
.jm-blog-featured-body h2 { font-size:24px; font-weight:800; color:#061426; line-height:1.2; margin:0 0 10px; }
.jm-blog-featured-body p { font-size:14px; color:#53667f; line-height:1.6; margin:0 0 14px; }
.jm-blog-byline { font-size:12px; color:#94a3b8; }
.jm-blog-grid { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:18px; }
@media(max-width:860px){ .jm-blog-grid{grid-template-columns:repeat(2,minmax(0,1fr));} }
@media(max-width:560px){ .jm-blog-grid{grid-template-columns:1fr;} }
.jm-blog-card { display:flex; flex-direction:column; background:#fff; border:1px solid #e4eaf3; border-radius:14px; overflow:hidden; text-decoration:none; transition:box-shadow .16s,transform .16s; }
.jm-blog-card:hover { box-shadow:0 12px 30px rgba(6,20,38,.1); transform:translateY(-3px); }
.jm-blog-card-img { aspect-ratio:16/9; background:linear-gradient(135deg,#eef5ff,#f7faff); display:grid; place-items:center; overflow:hidden; }
.jm-blog-card-img img { width:100%; height:100%; object-fit:cover; }
.jm-blog-card-img svg { width:34px; height:34px; color:#9bb0c7; }
.jm-blog-card-body { padding:14px 16px 16px; display:flex; flex-direction:column; gap:6px; flex:1; }
.jm-blog-card-body h3 { font-size:16px; font-weight:800; color:#061426; line-height:1.3; margin:0; }
.jm-blog-card-body p { font-size:13px; color:#53667f; line-height:1.5; flex:1; margin:0; }
.jm-blog-empty { text-align:center; color:#94a3b8; padding:60px 20px; background:#fff; border:1px solid #e4eaf3; border-radius:14px; }
</style>

<div class="jm-blog">
    <?= jm_breadcrumbs([['label' => 'Blog']]) ?>
    <?= jm_learn_nav('blog') ?>

    <div class="jm-blog-head">
        <h1>The Jobmington blog.</h1>
        <p>Career advice, remote-work insight, and stories from across African talent.</p>
        <div class="jm-blog-cats">
            <a class="jm-blog-cat <?= $filterCat === '' ? 'active' : '' ?>" href="/jobmington/blog/">All</a>
            <?php foreach ($categories as $cat): ?>
                <a class="jm-blog-cat <?= $filterCat === $cat['slug'] ? 'active' : '' ?>" href="/jobmington/blog/?category=<?= e($cat['slug']) ?>"><?= e($cat['name']) ?></a>
            <?php endforeach; ?>
        </div>
    </div>

    <?php if (!$featured && empty($posts)): ?>
        <div class="jm-blog-empty">No posts published yet. Check back soon.</div>
    <?php else: ?>
        <?php if ($featured): ?>
            <a class="jm-blog-featured" href="/jobmington/blog/post.php?slug=<?= e($featured['slug']) ?>">
                <div class="jm-blog-featured-img">
                    <?php if (!empty($featured['featured_image'])): ?>
                        <img src="<?= e($featured['featured_image']) ?>" alt="<?= e($featured['title']) ?>">
                    <?php else: ?>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                    <?php endif; ?>
                </div>
                <div class="jm-blog-featured-body">
                    <span class="jm-blog-tag">Featured<?= $featured['cat_name'] ? ' &middot; ' . e($featured['cat_name']) : '' ?></span>
                    <h2><?= e($featured['title']) ?></h2>
                    <p><?= e(excerpt($featured['excerpt'] ?: $featured['content'], 160)) ?></p>
                    <span class="jm-blog-byline"><?= e($featured['full_name'] ?: 'Jobmington') ?> &middot; <?= e(formatDate($featured['published_at'] ?: $featured['created_at'])) ?></span>
                </div>
            </a>
        <?php endif; ?>

        <?php if (!empty($posts)): ?>
            <div class="jm-blog-grid">
                <?php foreach ($posts as $p): ?>
                    <a class="jm-blog-card" href="/jobmington/blog/post.php?slug=<?= e($p['slug']) ?>">
                        <div class="jm-blog-card-img">
                            <?php if (!empty($p['featured_image'])): ?>
                                <img src="<?= e($p['featured_image']) ?>" alt="<?= e($p['title']) ?>">
                            <?php else: ?>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                            <?php endif; ?>
                        </div>
                        <div class="jm-blog-card-body">
                            <span class="jm-blog-tag"><?= e($p['cat_name'] ?: 'Article') ?></span>
                            <h3><?= e($p['title']) ?></h3>
                            <p><?= e(excerpt($p['excerpt'] ?: $p['content'], 100)) ?></p>
                            <span class="jm-blog-byline"><?= e(formatDate($p['published_at'] ?: $p['created_at'])) ?> &middot; <?= (int)$p['views'] ?> views</span>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/ai-footer.php'; ?>
