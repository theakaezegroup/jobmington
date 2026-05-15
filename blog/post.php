<?php
define('JOBMINGTON', true);
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/constants.php'; // <--- THIS WAS MISSING
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';

Session::start();
$pdo = db();

$slug = Security::clean(get('slug', ''));
if (empty($slug)) redirect('/jobmington/blog');

$stmt = $pdo->prepare("SELECT bp.*, u.full_name, bc.name as cat_name, bc.slug as cat_slug FROM blog_posts bp LEFT JOIN users u ON bp.author_id = u.user_id LEFT JOIN blog_categories bc ON bp.category_id = bc.id WHERE bp.slug = ?");
$stmt->execute([$slug]);
$post = $stmt->fetch();

if (!$post) { header("HTTP/1.0 404 Not Found"); exit('Data corrupted or missing.'); }

$pdo->prepare("UPDATE blog_posts SET views = views + 1 WHERE post_id = ?")->execute([$post['post_id']]);

// SIDEBAR DATA (With Safety Shield)
$jobs = [];
try {
    $jobs = $pdo->query("SELECT id as job_id, title as job_title, company_name FROM jobs WHERE status = 'active' ORDER BY created_at DESC LIMIT 3")->fetchAll();
} catch (Exception $e) {
    try {
        $jobs = $pdo->query("SELECT job_id, job_title, company_name FROM jobs WHERE status = 'active' ORDER BY created_at DESC LIMIT 3")->fetchAll();
    } catch (Exception $x) {
        $jobs = []; 
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<style>
    body { background-color: #020617; color: #cbd5e1; font-family: 'Inter', sans-serif; }
    .glass-panel { background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(12px); border: 1px solid rgba(255,255,255,0.05); border-radius: 1rem; overflow: hidden; }
    .prose-cyber { color: #cbd5e1; line-height: 1.8; font-size: 1.125rem; }
    .prose-cyber h2 { color: white; font-weight: 800; font-size: 1.8rem; margin: 2rem 0 1rem; border-left: 4px solid #a855f7; padding-left: 1rem; }
    .prose-cyber p { margin-bottom: 1.5rem; }
    .prose-cyber a { color: #3b82f6; text-decoration: underline; text-underline-offset: 4px; }
    
    .cta-break {
        background: linear-gradient(90deg, rgba(59, 130, 246, 0.1), rgba(168, 85, 247, 0.1));
        border: 1px solid rgba(255,255,255,0.1); padding: 1.5rem; border-radius: 0.5rem; margin: 2rem 0;
        display: flex; align-items: center; justify-content: space-between;
    }
</style>

<div class="min-h-screen py-10">
    <div class="max-w-[1400px] mx-auto px-4">
        <div class="text-xs text-slate-500 mb-6 font-mono uppercase tracking-widest">
            <a href="/jobmington/blog" class="hover:text-white">HUB</a> // <?= e($post['cat_name']) ?> // ID: <?= $post['post_id'] ?>
        </div>

        <div class="flex flex-col lg:flex-row gap-10">
            <div class="lg:w-3/4">
                <article class="glass-panel">
                    <?php if ($post['featured_image']): ?>
                        <div class="h-[400px] w-full overflow-hidden border-b border-white/5 relative">
                            <img src="<?= upload('blog-images/' . $post['featured_image']) ?>" class="w-full h-full object-cover">
                            <div class="absolute inset-0 bg-gradient-to-t from-[#0f172a] to-transparent"></div>
                        </div>
                    <?php endif; ?>

                    <div class="p-8 md:p-12">
                        <h1 class="text-4xl md:text-5xl font-black text-white mb-6 leading-tight"><?= e($post['title']) ?></h1>
                        <div class="flex items-center gap-4 text-sm text-slate-400 font-mono mb-10 border-b border-white/5 pb-6">
                            <span><i class="fas fa-user text-slate-400"></i> <?= e($post['full_name']) ?></span>
                            <span><i class="fas fa-clock text-blue-500"></i> <?= formatDate($post['published_at']) ?></span>
                            <span><i class="fas fa-eye text-green-500"></i> <?= $post['views'] ?> Reads</span>
                        </div>
                        <div class="prose-cyber">
                            <?= $post['content'] ?>
                            <div class="cta-break">
                                <div>
                                    <h4 class="text-white font-bold">Need a better CV?</h4>
                                    <p class="text-sm text-slate-400 m-0">Use our Obsidian CV Architect to build an ATS-proof resume.</p>
                                </div>
                                <a href="/jobmington/cv-builder" class="bg-blue-600 hover:bg-blue-500 text-white text-xs font-bold px-4 py-2 rounded uppercase tracking-wide transition">Build Now</a>
                            </div>
                        </div>
                    </div>
                </article>
            </div>

            <aside class="lg:w-1/4 space-y-6">
                <div class="p-6 rounded-2xl bg-gradient-to-br from-amber-900/20 to-slate-900/20 border border-amber-500/30 text-center">
                    <div class="w-12 h-12 bg-amber-500/20 rounded-full flex items-center justify-center mx-auto mb-3 text-amber-400"><i class="fas fa-robot text-2xl"></i></div>
                    <h4 class="font-bold text-white">Andika AI – Career Assistant</h4>
                    <p class="text-xs text-slate-400 mb-4">"I can help you summarize this article or find related jobs."</p>
                    <a href="/jobmington/ai/andika.php" class="w-full block border border-amber-500 text-amber-400 hover:bg-amber-500 hover:text-black text-xs font-bold py-2 rounded uppercase transition text-center">Chat Now</a>
                </div>
                
                <div class="glass-panel p-5">
                    <h4 class="text-xs font-bold text-white uppercase tracking-widest mb-4 border-b border-white/10 pb-2">Related Targets</h4>
                    <div class="space-y-4">
                        <?php if(!empty($jobs)): foreach($jobs as $job): ?>
                        <a href="/jobmington/jobs/view.php?id=<?= $job['job_id'] ?? 0 ?>" class="block group">
                            <div class="text-sm font-bold text-white group-hover:text-blue-400 transition truncate"><?= e($job['job_title']) ?></div>
                            <div class="text-xs text-slate-500"><?= e($job['company_name']) ?></div>
                        </a>
                        <?php endforeach; else: ?>
                            <div class="text-xs text-slate-500">Scanning...</div>
                        <?php endif; ?>
                    </div>
                </div>
            </aside>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>