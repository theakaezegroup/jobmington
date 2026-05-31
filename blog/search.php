<?php
/**
 * JOBMINGTON - Blog Search
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

$query = trim((string) get('q', ''));
$page = max(1, (int) get('page', 1));
$perPage = 10;
$posts = [];
$pagination = paginate(0, $perPage, $page);
$blogUnavailable = false;

if ($query !== '') {
    try {
        $like = '%' . $query . '%';
        $countStmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM blog_posts
            WHERE is_published = 1
              AND published_at <= NOW()
              AND (title LIKE ? OR excerpt LIKE ? OR content LIKE ?)
        ");
        $countStmt->execute([$like, $like, $like]);
        $pagination = paginate((int) $countStmt->fetchColumn(), $perPage, $page);

        $stmt = $pdo->prepare("
            SELECT bp.*, u.full_name, bc.name AS cat_name
            FROM blog_posts bp
            LEFT JOIN users u ON bp.author_id = u.user_id
            LEFT JOIN blog_categories bc ON bp.category_id = bc.id
            WHERE bp.is_published = 1
              AND bp.published_at <= NOW()
              AND (bp.title LIKE ? OR bp.excerpt LIKE ? OR bp.content LIKE ?)
            ORDER BY bp.published_at DESC, bp.post_id DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$like, $like, $like, $perPage, $pagination['offset']]);
        $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $blogUnavailable = true;
        error_log('Blog search unavailable: ' . $e->getMessage());
    }
}

$pageTitle = 'Search Blog | ' . SITE_NAME;
require_once __DIR__ . '/../includes/header.php';
?>

<style>
    body { background-color: #0f172a; color: #e2e8f0; font-family: 'Futura Cyrillic Demi'; }
    .blog-search-card { background: rgba(30, 41, 59, 0.55); border: 1px solid rgba(255,255,255,0.08); border-radius: 18px; padding: 22px; }
    .blog-result { display: block; border-bottom: 1px solid rgba(255,255,255,0.08); padding: 20px 0; text-decoration: none; }
    .blog-result:last-child { border-bottom: 0; }
    .blog-result h2 { color: #fff; font-size: 1.25rem; font-weight: 800; margin: 0 0 8px; }
    .blog-result:hover h2 { color: #fbbf24; }
    .blog-result p { color: #94a3b8; margin: 0; line-height: 1.6; }
    .blog-meta { color: #64748b; display: flex; flex-wrap: wrap; gap: 10px; font-size: 0.78rem; font-weight: 800; letter-spacing: 0.08em; margin-bottom: 8px; text-transform: uppercase; }
</style>

<div class="min-h-screen py-12">
    <div class="max-w-5xl mx-auto px-4 md:px-8">
        <div class="flex items-start justify-between gap-6 mb-8">
            <div>
                <a href="/jobmington/blog/" class="text-slate-400 hover:text-white text-xs font-bold uppercase tracking-widest">Blog</a>
                <h1 class="text-4xl md:text-5xl font-black text-white tracking-tighter mt-2">Search articles</h1>
                <p class="text-slate-400 mt-2">Find career guides, platform updates, and job-search advice.</p>
            </div>
        </div>

        <form action="/jobmington/blog/search.php" method="get" class="blog-search-card mb-8">
            <label class="block text-xs font-bold uppercase tracking-widest text-slate-400 mb-3" for="blog-q">Search term</label>
            <div class="flex flex-col sm:flex-row gap-3">
                <input id="blog-q" name="q" value="<?= e($query) ?>" placeholder="Search articles..." class="flex-1 rounded-xl bg-white/5 border border-white/10 px-4 py-3 text-white outline-none focus:border-amber-400">
                <button class="bg-amber-500 text-black font-black rounded-xl px-6 py-3" type="submit">Search</button>
            </div>
        </form>

        <div class="blog-search-card">
            <?php if ($blogUnavailable): ?>
                <div class="text-center py-12">
                    <h2 class="text-white text-2xl font-black mb-2">Articles are being prepared</h2>
                    <p class="text-slate-400">The blog library is not published yet. Try jobs, CV Builder, or Andika AI for now.</p>
                    <a href="/jobmington/ai/andika.php" class="inline-block mt-5 text-amber-300 font-bold">Ask Andika</a>
                </div>
            <?php elseif ($query === ''): ?>
                <div class="text-center py-12">
                    <h2 class="text-white text-2xl font-black mb-2">Start with a keyword</h2>
                    <p class="text-slate-400">Try CV, interview, remote work, hiring, or salary.</p>
                </div>
            <?php elseif (empty($posts)): ?>
                <div class="text-center py-12">
                    <h2 class="text-white text-2xl font-black mb-2">No articles found</h2>
                    <p class="text-slate-400">Try a broader search term or browse all articles.</p>
                    <a href="/jobmington/blog/" class="inline-block mt-5 text-amber-300 font-bold">Browse blog</a>
                </div>
            <?php else: ?>
                <p class="text-slate-400 text-sm mb-2"><?= number_format((int) $pagination['total']) ?> result<?= (int) $pagination['total'] === 1 ? '' : 's' ?> for <strong class="text-white"><?= e($query) ?></strong></p>
                <?php foreach ($posts as $post): ?>
                    <a class="blog-result" href="/jobmington/blog/post.php?slug=<?= e($post['slug']) ?>">
                        <div class="blog-meta">
                            <span><?= e($post['cat_name'] ?: 'Article') ?></span>
                            <span><?= formatDate($post['published_at']) ?></span>
                        </div>
                        <h2><?= e($post['title']) ?></h2>
                        <p><?= e(excerpt($post['excerpt'] ?: $post['content'], 180)) ?></p>
                    </a>
                <?php endforeach; ?>
                <div class="mt-8">
                    <?= renderPagination($pagination, '/jobmington/blog/search.php?q=' . urlencode($query)) ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
