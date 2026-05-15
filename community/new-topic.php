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
        redirect('/jobmington/auth/login.php');
    }

    $title = Security::clean(post('title', ''));
    $content = Security::clean(post('content', ''));
    $categoryId = (int) post('category_id', 0);

    if (strlen($title) < 5) {
        Session::flash('error', 'Title too short. Please provide more detail.');
        redirect('/jobmington/community/new-topic.php');
    }

    try {
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

$pageTitle = 'The Room | Share Your Insights';
require_once __DIR__ . '/../includes/header.php';
?>

<style>
    /* --- SYSTEM CORE --- */
    html, body { 
        background: #030303; 
        background-image: linear-gradient(180deg, #030303 0%, #0a0a0a 50%, #0f0f0f 100%);
        background-attachment: fixed;
        color: #ffffff; 
    }

    /* Aurora Glows - subtle white/gray */
    .post-bg {
        position: fixed;
        inset: 0; z-index: -1;
        background: radial-gradient(circle at 50% 0%, rgba(255, 255, 255, 0.02) 0%, transparent 50%);
    }

    /* --- GLASS TERMINAL --- */
    .glass-terminal {
        background: rgba(10, 10, 10, 0.8);
        backdrop-filter: blur(30px);
        border: 1px solid rgba(255, 255, 255, 0.04);
        border-radius: 2.5rem;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.7);
    }

    /* --- CONSOLE INPUTS --- */
    .console-input {
        background: rgba(255, 255, 255, 0.02);
        border: 1px solid rgba(255, 255, 255, 0.08);
        border-radius: 1rem;
        color: white;
        padding: 1rem 1.5rem;
        transition: all 0.3s ease;
    }
    .console-input:focus {
        border-color: rgba(255, 255, 255, 0.15);
        background: rgba(255, 255, 255, 0.04);
        box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.04);
        outline: none;
    }

    .label-mini {
        font-size: 10px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.2em;
        color: #64748b;
        margin-bottom: 0.75rem;
        display: block;
    }

    /* --- ACTION BUTTON --- */
    .btn-post {
        background: #f4f4f5;
        color: #050608; 
        border-radius: 1.25rem; 
        font-weight: 900;
        padding: 1.25rem; 
        transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        text-transform: uppercase; 
        letter-spacing: 0.1em;
    }
    .btn-post:hover {
        transform: scale(1.02);
        box-shadow: 0 15px 30px rgba(0, 0, 0, 0.4);
    }

    /* Status HUD */
    .hud-card {
        background: rgba(255, 255, 255, 0.015);
        border: 1px solid rgba(255, 255, 255, 0.04);
        border-radius: 1.5rem;
        padding: 1.5rem;
    }
</style>

<div class="post-bg"></div>

<main class="max-w-5xl mx-auto px-4 pt-20 pb-12">
    <div class="grid lg:grid-cols-12 gap-8">
        
        <div class="lg:col-span-8">
            <div class="glass-terminal p-6 md:p-8 relative overflow-hidden">
                
                <header class="mb-6">
                    <h1 class="text-3xl font-heading font-black text-white tracking-tighter mb-1">POST TO <span class="text-zinc-400">THE ROOM</span></h1>
                    <p class="text-sm text-slate-400 font-light italic">Share career updates, questions, and insights with professionals in The Room.</p>
                </header>

                <?php if (Session::hasFlash()): ?>
                    <div class="mb-4 p-3 rounded-lg bg-red-500/10 border border-red-500/20 text-red-400 text-xs font-bold uppercase tracking-widest">
                        <?php foreach (Session::getFlash('error') as $m): ?> <?= e($m) ?> <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" class="space-y-4">
                    <?= Security::csrfField() ?>
                    
                    <div class="grid md:grid-cols-2 gap-4">
                        <div class="space-y-1.5">
                            <label class="label-mini">Category</label>
                            <select name="category_id" class="console-input w-full appearance-none text-sm">
                                <option value="">General Discussion</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= e($cat['id']) ?>"><?= e($cat['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="space-y-1.5">
                            <label class="label-mini">Post Title</label>
                            <input type="text" name="title" placeholder="Clear, professional subject..." required class="console-input w-full text-sm" />
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <label class="label-mini">Post Content</label>
                        <textarea name="content" rows="7" placeholder="Share your career insight, ask a question, or start a professional discussion..." required class="console-input w-full resize-none chat-scroll text-sm"></textarea>
                    </div>

                    <div class="pt-2">
                        <button class="btn-post w-full flex items-center justify-center gap-2 py-3 text-sm" type="submit">
                            <i class="fas fa-paper-plane"></i> Share in The Room
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <aside class="lg:col-span-4 space-y-4">
            <div class="hud-card">
                <span class="label-mini mb-3 text-xs">Rewards</span>
                <div class="flex items-center gap-3 mb-3">
                    <div class="w-10 h-10 rounded-lg bg-yellow-500/10 flex items-center justify-center text-yellow-500 text-sm">
                        <i class="fas fa-coins"></i>
                    </div>
                    <div>
                        <h4 class="text-white font-bold text-xs">Earn Seeds</h4>
                        <p class="text-[9px] text-slate-500 uppercase font-black">For Valuable Posts</p>
                    </div>
                </div>
                <p class="text-xs text-slate-400 leading-relaxed italic">
                    Helpful posts can earn you <strong>Seeds</strong> (reward points) in your Jobmington wallet. Share value and support other professionals.
                </p>
            </div>

            <div class="hud-card">
                <span class="label-mini mb-3 text-xs">Community Guidelines</span>
                <ul class="space-y-2">
                    <li class="flex items-start gap-2">
                        <div class="w-4 h-4 rounded bg-white/5 flex items-center justify-center text-white/60 text-[8px] font-bold flex-shrink-0 mt-0.5">1</div>
                        <p class="text-xs text-slate-300">Keep it professional. No spam.</p>
                    </li>
                    <li class="flex items-start gap-2">
                        <div class="w-4 h-4 rounded bg-white/5 flex items-center justify-center text-white/60 text-[8px] font-bold flex-shrink-0 mt-0.5">2</div>
                        <p class="text-xs text-slate-300">Use descriptive titles.</p>
                    </li>
                    <li class="flex items-start gap-2">
                        <div class="w-4 h-4 rounded bg-white/5 flex items-center justify-center text-white/60 text-[8px] font-bold flex-shrink-0 mt-0.5">3</div>
                        <p class="text-xs text-slate-300">Link to relevant resources if helpful.</p>
                    </li>
                </ul>
            </div>
        </aside>

    </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>