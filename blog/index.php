<?php
/**
 * JOBMINGTON - The Command Center (Blog Index)
 * Aesthetic: Deep Space / Cyber-Obsidian
 * Strategy: Aggressively showcase platform tools (CV, AI, Jobs)
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

$page = max(1, (int) get('page', 1));
$perPage = 10;
$featured = null;
$posts = [];
$pagination = paginate(0, $perPage, $page);
$blogUnavailable = false;

try {
    // 1. Featured Intel
    $stmt = $pdo->query("SELECT bp.*, u.full_name, bc.name as cat_name, bc.slug as cat_slug FROM blog_posts bp LEFT JOIN users u ON bp.author_id = u.user_id LEFT JOIN blog_categories bc ON bp.category_id = bc.id WHERE bp.is_published = 1 ORDER BY bp.published_at DESC LIMIT 1");
    $featured = $stmt->fetch();
    $featuredId = $featured['post_id'] ?? 0;

    // 2. Grid Intel
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM blog_posts WHERE is_published = 1 AND post_id != ?");
    $countStmt->execute([$featuredId]);
    $pagination = paginate($countStmt->fetchColumn(), $perPage, $page);

    $stmt = $pdo->prepare("SELECT bp.*, u.full_name, bc.name as cat_name FROM blog_posts bp LEFT JOIN users u ON bp.author_id = u.user_id LEFT JOIN blog_categories bc ON bp.category_id = bc.id WHERE bp.is_published = 1 AND bp.post_id != ? ORDER BY bp.published_at DESC LIMIT ? OFFSET ?");
    $stmt->execute([$featuredId, $perPage, $pagination['offset']]);
    $posts = $stmt->fetchAll();
} catch (Throwable $e) {
    $blogUnavailable = true;
    error_log('Blog index unavailable: ' . $e->getMessage());
}

// 3. SHOWCASE DATA (With Safety Shield)
$jobs = [];
try {
    $jobs = $pdo->query("
        SELECT j.job_id, j.title AS job_title, COALESCE(c.name, 'Jobmington employer') AS company_name
        FROM jobs j
        LEFT JOIN companies c ON j.company_id = c.company_id
        WHERE j.is_active = 1
          AND (j.expires_at IS NULL OR j.expires_at >= CURDATE())
        ORDER BY j.posted_at DESC, j.job_id DESC
        LIMIT 3
    ")->fetchAll();
} catch (Exception $e) {
    error_log("Sidebar Widget Error: " . $e->getMessage());
}
$pageTitle = 'Blog & Resources | ' . SITE_NAME;
require_once __DIR__ . '/../includes/header.php';
?>

<style>
    /* --- BLOG THEME --- */
    body { 
        background-color: #0f172a; 
        color: #e2e8f0; 
        font-family: 'Futura Cyrillic Demi';
        background-image: 
            radial-gradient(circle at 10% 20%, rgba(59, 130, 246, 0.03), transparent 40%),
            radial-gradient(circle at 90% 80%, rgba(245, 158, 11, 0.03), transparent 40%);
    }
    
    /* The Glass Stack */
    .glass-card {
        background: rgba(30, 41, 59, 0.5); 
        border: 1px solid rgba(255,255,255,0.08);
        backdrop-filter: blur(16px); 
        border-radius: 16px; 
        overflow: hidden;
        transition: all 0.3s ease;
    }
    .glass-card:hover {
        border-color: rgba(245, 158, 11, 0.4);
        box-shadow: 0 10px 40px -10px rgba(0, 0, 0, 0.3);
        transform: translateY(-4px);
    }
    
    /* Accent colors */
    .neon-border { position: relative; }
    .neon-border::after {
        content: ''; position: absolute; bottom: 0; left: 0; width: 0%; height: 2px;
        background: linear-gradient(90deg, #f59e0b, #3b82f6); transition: width 0.3s;
    }
    .glass-card:hover .neon-border::after { width: 100%; }

    /* The Showcase Widgets */
    .tool-widget {
        background: linear-gradient(145deg, rgba(255,255,255,0.05) 0%, rgba(255,255,255,0.01) 100%);
        border: 1px solid rgba(255,255,255,0.1); padding: 1.5rem; border-radius: 1rem;
        position: relative; overflow: hidden; margin-bottom: 1.5rem;
    }
    .tool-icon {
        width: 40px; height: 40px; border-radius: 8px; display: flex; align-items: center; justify-content: center;
        font-size: 1.2rem; margin-bottom: 1rem;
        background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.1);
    }
    
    /* Pulsing AI Orb */
    .ai-orb {
        width: 12px; height: 12px; background: #22c55e; border-radius: 50%;
        box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.7);
        animation: pulse-green 2s infinite;
    }
    @keyframes pulse-green {
        0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.7); }
        70% { transform: scale(1); box-shadow: 0 0 0 10px rgba(34, 197, 94, 0); }
        100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(34, 197, 94, 0); }
    }
</style>

<div class="min-h-screen py-12">
    <div class="max-w-[1400px] mx-auto px-4 md:px-8">
        
        <div class="flex items-end justify-between mb-12 border-b border-white/10 pb-6">
            <div>
                <h1 class="text-4xl md:text-5xl font-black text-white tracking-tighter mb-2">Blog & <span class="text-transparent bg-clip-text bg-gradient-to-r from-amber-400 to-amber-500">Resources</span></h1>
                <p class="text-slate-400 text-sm">Career tips, industry insights, and platform updates</p>
            </div>
            <form action="/jobmington/blog/search.php" class="hidden md:block relative w-64">
                <input type="text" name="q" placeholder="Search articles..." class="w-full bg-white/5 border border-white/10 rounded-full py-2 px-4 text-sm text-white focus:border-amber-500 outline-none transition">
                <i class="fas fa-search absolute right-4 top-3 text-slate-500"></i>
            </form>
        </div>

        <div class="flex flex-col lg:flex-row gap-10">
            
            <div class="lg:w-3/4">
                
                <?php if ($featured && $page === 1): ?>
                <a href="/jobmington/blog/post.php?slug=<?= e($featured['slug']) ?>" class="block glass-card h-[450px] relative mb-12 group">
                    <?php if ($featured['featured_image']): ?>
                        <img src="<?= upload('blog-images/' . $featured['featured_image']) ?>" class="w-full h-full object-cover opacity-80 group-hover:scale-105 transition duration-700">
                    <?php else: ?>
                        <div class="w-full h-full bg-gradient-to-tr from-slate-800 to-slate-900"></div>
                    <?php endif; ?>
                    
                    <div class="absolute inset-0 bg-gradient-to-t from-slate-900 via-slate-900/40 to-transparent flex flex-col justify-end p-10">
                        <span class="inline-flex items-center gap-2 px-3 py-1 bg-amber-500/20 border border-amber-500/50 text-amber-300 text-xs font-bold uppercase tracking-widest rounded mb-4 backdrop-blur-md w-fit">
                            <i class="fas fa-star text-[10px]"></i> Featured
                        </span>
                        <h2 class="text-3xl md:text-4xl font-black text-white mb-4 leading-tight group-hover:text-amber-300 transition">
                            <?= e($featured['title']) ?>
                        </h2>
                        <div class="flex items-center gap-4 text-sm text-slate-300">
                            <span><?= e($featured['cat_name']) ?></span>
                            <span>&bull;</span>
                            <span><?= formatDate($featured['published_at']) ?></span>
                        </div>
                    </div>
                </a>
                <?php endif; ?>

                <?php if ($blogUnavailable || (!$featured && empty($posts))): ?>
                <div class="glass-card p-10 text-center mb-12">
                    <div class="text-4xl mb-4">JM</div>
                    <h2 class="text-2xl md:text-3xl font-black text-white mb-3">Career articles are being prepared</h2>
                    <p class="text-slate-400 max-w-xl mx-auto">The blog library is not published yet. You can still explore live jobs, build your CV, and use Andika AI while the resource center is being set up.</p>
                    <div class="mt-6 flex flex-col sm:flex-row justify-center gap-3">
                        <a href="/jobmington/jobs/" class="px-5 py-3 rounded-xl bg-amber-500 text-black font-black">Browse jobs</a>
                        <a href="/jobmington/ai/andika.php" class="px-5 py-3 rounded-xl bg-white/10 text-white font-black border border-white/10">Ask Andika</a>
                    </div>
                </div>
                <?php endif; ?>

                <div class="grid md:grid-cols-2 gap-6">
                    <?php foreach ($posts as $post): ?>
                    <div class="glass-card flex flex-col group neon-border">
                        <a href="/jobmington/blog/post.php?slug=<?= e($post['slug']) ?>" class="h-56 overflow-hidden relative">
                            <?php if ($post['featured_image']): ?>
                                <img src="<?= upload('blog-images/' . $post['featured_image']) ?>" class="w-full h-full object-cover group-hover:scale-110 transition duration-500">
                            <?php else: ?>
                                <div class="w-full h-full bg-slate-800 flex items-center justify-center"><i class="fas fa-file-code text-4xl text-slate-600"></i></div>
                            <?php endif; ?>
                            <div class="absolute top-4 right-4 bg-black/60 backdrop-blur border border-white/10 px-2 py-1 rounded text-xs font-bold text-white uppercase">
                                <?= e($post['cat_name']) ?>
                            </div>
                        </a>
                        <div class="p-6 flex flex-col flex-1">
                            <h3 class="text-xl font-bold text-white mb-3 leading-tight group-hover:text-blue-400 transition">
                                <a href="/jobmington/blog/post.php?slug=<?= e($post['slug']) ?>"><?= e($post['title']) ?></a>
                            </h3>
                            <p class="text-slate-400 text-sm line-clamp-2 mb-4 flex-1">
                                <?= excerpt($post['excerpt'] ?: $post['content'], 100) ?>
                            </p>
                            <div class="pt-4 border-t border-white/5 flex justify-between items-center text-xs text-slate-500">
                                <span><?= formatDate($post['published_at']) ?></span>
                                <span class="group-hover:text-amber-400 transition">Read More &rarr;</span>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="mt-12 flex justify-center">
                    <div class="glass-card inline-block p-2">
                        <?= renderPagination($pagination, '/jobmington/blog/index.php') ?>
                    </div>
                </div>
            </div>

            <aside class="lg:w-1/4 space-y-6">
                
                <div class="tool-widget group cursor-pointer hover:border-amber-500/50 transition" onclick="location.href='/jobmington/ai/andika.php'">
                    <div class="flex justify-between items-start">
                        <div class="tool-icon text-amber-400"><i class="fas fa-robot"></i></div>
                        <div class="ai-orb"></div>
                    </div>
                    <h4 class="font-bold text-white text-lg mb-1">Andika AI</h4>
                    <p class="text-slate-400 text-xs mb-3">Get career advice, refine your CV, or find the right opportunities.</p>
                    <span class="text-amber-400 text-xs font-bold uppercase tracking-wider group-hover:underline">Start Chat &rarr;</span>
                </div>

                <div class="tool-widget group cursor-pointer hover:border-blue-500/50 transition" onclick="location.href='/jobmington/cv-builder'">
                    <div class="tool-icon text-blue-400"><i class="fas fa-file-contract"></i></div>
                    <h4 class="font-bold text-white text-lg mb-1">CV Builder</h4>
                    <p class="text-slate-400 text-xs mb-3">Build an ATS-friendly resume with professional templates.</p>
                    <div class="w-full bg-white/10 h-1 rounded-full overflow-hidden mb-3">
                        <div class="bg-blue-500 h-full w-3/4"></div>
                    </div>
                    <span class="text-blue-400 text-xs font-bold uppercase tracking-wider group-hover:underline">Build Now &rarr;</span>
                </div>

                <div class="glass-card p-5">
                    <div class="flex items-center justify-between mb-4 border-b border-white/10 pb-2">
                        <h4 class="text-xs font-bold text-white uppercase tracking-widest">Latest Jobs</h4>
                        <div class="flex gap-1">
                            <span class="w-2 h-2 rounded-full bg-green-500 animate-pulse"></span>
                        </div>
                    </div>
                    
                    <div class="space-y-4">
                        <?php if(!empty($jobs)): foreach($jobs as $job): ?>
                        <a href="/jobmington/jobs/view.php?id=<?= $job['job_id'] ?>" class="block group">
                            <div class="text-sm font-bold text-white group-hover:text-amber-400 transition truncate"><?= e($job['job_title']) ?></div>
                            <div class="text-xs text-slate-500"><?= e($job['company_name']) ?></div>
                        </a>
                        <?php endforeach; else: ?>
                            <div class="text-xs text-slate-500">No jobs available right now</div>
                        <?php endif; ?>
                    </div>
                    
                    <a href="/jobmington/jobs" class="block mt-4 text-center text-xs font-bold bg-white/5 hover:bg-white/10 py-2 rounded text-slate-300 transition">View All Jobs</a>
                </div>

                <div class="tool-widget text-center">
                    <i class="fas fa-envelope text-3xl text-amber-400 mb-3 block"></i>
                    <h4 class="font-bold text-white mb-2">Stay Updated</h4>
                    <p class="text-xs text-slate-400 mb-4">Get career tips and job alerts delivered to your inbox.</p>
                    <form action="/jobmington/subscribe.php" method="POST">
                        <input type="email" class="w-full bg-black/40 border border-white/10 rounded py-2 px-3 text-xs text-white text-center mb-2 focus:border-amber-400 outline-none" placeholder="Enter Email">
                        <button class="w-full bg-amber-500 text-black font-bold text-xs uppercase py-2 rounded hover:bg-amber-400 transition">Subscribe</button>
                    </form>
                </div>

            </aside>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
