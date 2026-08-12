<?php
/**
 * JOBMINGTON - Blog Category View
 * Aesthetic: Cyber-Obsidian (High Tech Visuals)
 * Content: Professional Career Advice (Standard Language)
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

$slug = Security::clean(get('slug', ''));
if (empty($slug)) redirect(SITE_URL . '/blog/');

try {
    // 1. Fetch Category Info
    $stmt = $pdo->prepare("SELECT * FROM blog_categories WHERE slug = ?");
    $stmt->execute([$slug]);
    $category = $stmt->fetch();

    if (!$category) { Session::flash('error', 'Category not found.'); redirect(SITE_URL . '/blog/'); }

    // 2. Pagination
    $page = max(1, (int) get('page', 1));
    $perPage = 10;

    // 3. Fetch Posts in Category
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM blog_posts WHERE category_id = ? AND is_published = 1 AND published_at <= NOW()");
    $countStmt->execute([$category['id']]);
    $totalPosts = $countStmt->fetchColumn();

    $pagination = paginate($totalPosts, $perPage, $page);

    $stmt = $pdo->prepare("
        SELECT bp.*, u.full_name as author_name, u.profile_image as author_image
        FROM blog_posts bp
        LEFT JOIN users u ON bp.author_id = u.user_id
        WHERE bp.category_id = ? AND bp.is_published = 1 AND bp.published_at <= NOW()
        ORDER BY bp.published_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([$category['id'], $perPage, $pagination['offset']]);
    $posts = $stmt->fetchAll();

    // 4. Fetch All Categories (For Sidebar)
    $allCats = $pdo->query("SELECT * FROM blog_categories ORDER BY name")->fetchAll();
} catch (Throwable $e) {
    error_log('Blog category unavailable: ' . $e->getMessage());
    Session::flash('info', 'Career articles are being prepared.');
    redirect(SITE_URL . '/blog/');
}

$pageTitle = e($category['name']) . ' | Career Articles';
require_once __DIR__ . '/../includes/header.php';
?>

<style>
    /* --- THEME: CYBER OBSIDIAN --- */
    body { background-color: #020617; color: #f8fafc; font-family: 'Futura Cyrillic Demi'; }

    /* Header */
    .cat-header {
        position: relative; padding: 3rem 0; border-bottom: 1px solid rgba(255,255,255,0.1);
        background: radial-gradient(circle at 50% 0%, rgba(37, 99, 235, 0.1), transparent 70%);
    }

    /* Card Design */
    .story-card {
        background: rgba(30, 41, 59, 0.4); 
        border: 1px solid rgba(255,255,255,0.05);
        border-radius: 1rem; overflow: hidden; transition: 0.3s;
        display: flex; flex-direction: column; height: 100%;
        backdrop-filter: blur(10px);
    }
    .story-card:hover { 
        transform: translateY(-5px); 
        border-color: rgba(59, 130, 246, 0.4); 
        box-shadow: 0 10px 30px -10px rgba(59, 130, 246, 0.2); 
    }
    .story-img { height: 180px; width: 100%; object-fit: cover; border-bottom: 1px solid rgba(255,255,255,0.05); }

    /* Inputs */
    .glass-input {
        background: rgba(0, 0, 0, 0.3); border: 1px solid rgba(255, 255, 255, 0.1);
        color: white; transition: 0.3s;
    }
    .glass-input:focus {
        border-color: #3b82f6; outline: none; box-shadow: 0 0 15px rgba(59, 130, 246, 0.2);
    }
</style>

<div class="cat-header">
    <div class="max-w-[1400px] mx-auto px-4 md:px-8">
        <div class="flex items-center gap-2 text-sm text-slate-400 mb-2">
            <a href="/jobmington/blog" class="hover:text-white transition">Blog</a>
            <i class="fas fa-chevron-right text-[10px]"></i>
            <span class="text-blue-400 font-bold">Category</span>
        </div>
        <h1 class="text-3xl md:text-4xl font-black text-white tracking-tight">
            <?= e($category['name']) ?>
        </h1>
        <p class="text-slate-400 mt-2 text-lg">
            <?= $totalPosts ?> articles found in this topic.
        </p>
    </div>
</div>

<div class="max-w-[1400px] mx-auto px-4 md:px-8 py-12">
    <div class="flex flex-col lg:flex-row gap-12">
        
        <div class="lg:w-3/4">
            <?php if (empty($posts)): ?>
                <div class="p-12 text-center border border-dashed border-slate-700 rounded-xl bg-white/5">
                    <i class="far fa-folder-open text-4xl text-slate-600 mb-4"></i>
                    <h3 class="text-xl font-bold text-white">No articles yet</h3>
                    <p class="text-slate-400 mt-2">Check back later for updates in this category.</p>
                    <a href="/jobmington/blog" class="inline-block mt-6 text-blue-400 hover:text-white font-bold text-sm">
                        Browse all topics &rarr;
                    </a>
                </div>
            <?php else: ?>
                <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php foreach ($posts as $post): ?>
                    <article class="story-card group">
                        <a href="/jobmington/blog/post.php?slug=<?= e($post['slug']) ?>" class="overflow-hidden relative block">
                            <?php if ($post['featured_image']): ?>
                                <img src="<?= upload('blog-images/' . $post['featured_image']) ?>" class="story-img group-hover:scale-110 transition duration-500">
                            <?php else: ?>
                                <div class="story-img bg-slate-800 flex items-center justify-center"><i class="fas fa-pen-nib text-4xl text-slate-700"></i></div>
                            <?php endif; ?>
                        </a>
                        <div class="p-5 flex flex-col flex-1">
                            <div class="text-xs text-slate-500 mb-2 font-bold uppercase tracking-wider flex items-center gap-2">
                                <i class="far fa-calendar-alt"></i> <?= formatDate($post['published_at']) ?>
                            </div>
                            <h3 class="text-lg font-bold text-white mb-3 leading-snug group-hover:text-blue-400 transition">
                                <a href="/jobmington/blog/post.php?slug=<?= e($post['slug']) ?>"><?= e($post['title']) ?></a>
                            </h3>
                            <p class="text-sm text-slate-400 line-clamp-3 mb-4 flex-1">
                                <?= excerpt($post['excerpt'] ?: $post['content'], 100) ?>
                            </p>
                            <div class="flex items-center gap-3 pt-4 border-t border-white/5">
                                <img src="<?= profileImage($post['author_image']) ?>" class="w-6 h-6 rounded-full object-cover">
                                <span class="text-xs text-slate-300 font-bold">By <?= e($post['author_name'] ?: 'Jobmington') ?></span>
                            </div>
                        </div>
                    </article>
                    <?php endforeach; ?>
                </div>

                <div class="mt-12 flex justify-center">
                    <div class="bg-white/5 p-1 rounded-xl border border-white/10">
                        <?= renderPagination($pagination, '/jobmington/blog/category.php?slug=' . urlencode($slug)) ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <aside class="lg:w-1/4 space-y-8">
            
            <div class="bg-white/5 p-6 rounded-2xl border border-white/10 backdrop-blur-sm">
                <h4 class="text-xs font-bold text-white uppercase tracking-widest mb-4 border-b border-white/10 pb-2">Search Articles</h4>
                <form action="/jobmington/blog/search.php" method="GET" class="relative">
                    <input type="text" name="q" placeholder="Type to search..." class="glass-input w-full rounded-lg py-3 px-4 text-sm">
                    <button type="submit" class="absolute right-3 top-3 text-slate-500 hover:text-white"><i class="fas fa-search"></i></button>
                </form>
            </div>

            <div class="bg-white/5 p-6 rounded-2xl border border-white/10 backdrop-blur-sm">
                <h4 class="text-xs font-bold text-white uppercase tracking-widest mb-4 border-b border-white/10 pb-2">Browse Topics</h4>
                <div class="space-y-1">
                    <a href="/jobmington/blog" class="flex items-center justify-between p-2 rounded hover:bg-white/5 text-slate-400 hover:text-white transition group">
                        <span class="text-sm">All Articles</span>
                    </a>
                    <?php foreach ($allCats as $cat): ?>
                    <a href="/jobmington/blog/category.php?slug=<?= e($cat['slug']) ?>" class="flex items-center justify-between p-2 rounded hover:bg-white/5 <?= $cat['id'] == $category['id'] ? 'bg-white/10 text-white font-bold' : 'text-slate-400 hover:text-white' ?> transition group">
                        <span class="text-sm"><?= e($cat['name']) ?></span>
                        <?php if ($cat['id'] == $category['id']): ?>
                            <i class="fas fa-check text-[10px] text-blue-500"></i>
                        <?php else: ?>
                            <i class="fas fa-chevron-right text-[10px] opacity-0 group-hover:opacity-100 text-blue-500"></i>
                        <?php endif; ?>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="bg-gradient-to-br from-blue-900/40 to-slate-900/40 p-6 rounded-2xl border border-white/10 text-center relative overflow-hidden">
                <div class="absolute -top-10 -right-10 w-20 h-20 bg-blue-500 blur-3xl opacity-20 rounded-full"></div>
                
                <i class="fas fa-bell text-3xl text-white mb-4 relative z-10"></i>
                <h4 class="font-bold text-white mb-2 relative z-10">Job Alerts</h4>
                <p class="text-xs text-slate-300 mb-4 relative z-10">Never miss a new job opportunity or career tip.</p>
                
                <form action="/jobmington/subscribe.php" method="POST" class="relative z-10">
                    <input type="email" name="email" class="glass-input w-full rounded-lg py-2 px-3 text-xs text-center mb-3" placeholder="you@example.com" required>
                    <button class="w-full bg-white text-black font-bold text-xs uppercase py-3 rounded hover:bg-blue-50 transition shadow-lg">
                        Subscribe
                    </button>
                </form>
            </div>

        </aside>

    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
