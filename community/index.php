<?php
/**
 * JOBMINGTON - The Room
 * Professional networking feed where users share insights and connect.
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

// --- DATA LOGIC (ROBUST & FIXED) ---

// 1. Fetch Categories
// Fixed: Sorts by Name to avoid 'order_index' error
$stmt = $pdo->query("
    SELECT fc.*, 
    (SELECT COUNT(*) FROM forum_topics WHERE category_id = fc.id) as topic_count 
    FROM forum_categories fc 
    ORDER BY fc.name ASC
");
$categories = $stmt->fetchAll();

// 2. Fetch Topics
// Fixed: Removed 'color_hex' to avoid DB error
$sql = "SELECT ft.*, u.full_name, u.profile_image, fc.name as category_name,
       (SELECT COUNT(*) FROM forum_replies WHERE topic_id = ft.topic_id) as replies
       FROM forum_topics ft
       JOIN users u ON ft.user_id = u.user_id
       LEFT JOIN forum_categories fc ON ft.category_id = fc.id
       ORDER BY ft.created_at DESC LIMIT 15";

$stmt = $pdo->prepare($sql);
$stmt->execute();
$feed = $stmt->fetchAll();

// 3. COLOR ENGINE (No DB changes needed)
// Assigns neutral/semantic colors - minimal purple (5%)
function getCategoryColor($id) {
    $palette = [
        '#a78bfa', // Soft purple - ONLY accent (5% use)
        '#22c55e', // Green (growth)
        '#3b82f6', // Blue (general)
        '#ef4444', // Red (hot)
        '#eab308'  // Yellow (trending)
    ];
    return $palette[$id % count($palette)];
}

$pageTitle = 'The Room | Share Your Insights';
require_once __DIR__ . '/../includes/header.php';
?>

<style>
    /* --- 1. THE PLATINUM ENGINE --- */
    :root {
        --bg-app: #030303;
        --bg-panel: rgba(10, 10, 10, 0.8);
        --bg-surface: #0f0f0f;
        --text-main: #ffffff;
        --text-muted: #a3a3a3;
        --border-glass: rgba(255, 255, 255, 0.06);
        --accent-glow: rgba(255,255,255,0.03);
        --hero-gradient: linear-gradient(to bottom, #ffffff, #737373);
        --accent: #ffffff;
        --seed-gold: #eab308;
        --trend-up: #22c55e;
    }

    [data-theme="light"] {
        --bg-app: #f5f5f5;
        --bg-panel: rgba(255, 255, 255, 0.9);
        --bg-surface: #ffffff;
        --text-main: #171717;
        --text-muted: #737373;
        --border-glass: rgba(0, 0, 0, 0.06);
        --accent-glow: rgba(0,0,0,0.02);
        --hero-gradient: linear-gradient(to bottom, #171717, #525252);
    }

    /* --- 2. PHYSICS & AMBIENCE --- */
    html, body { 
        background: var(--bg-app); 
        background-image: linear-gradient(180deg, #030303 0%, #0a0a0a 50%, #0f0f0f 100%);
        background-attachment: fixed;
        font-family: 'Inter', sans-serif; 
        color: var(--text-main); 
        transition: 0.3s; 
        overflow-x: hidden; 
    }
    
    .ambient-glow { 
        position: fixed; top: -20%; right: -10%; width: 70%; height: 70%; 
        background: radial-gradient(circle, rgba(255, 255, 255, 0.02) 0%, transparent 60%); 
        filter: blur(140px); z-index: -1; pointer-events: none; 
    }

    /* --- 3. COMPONENT: GLASS CARDS --- */
    .room-card {
        background: var(--bg-panel);
        border: 1px solid var(--border-glass);
        backdrop-filter: blur(24px);
        border-radius: 20px;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        overflow: hidden;
    }
    .room-card:hover {
        transform: translateY(-4px);
        border-color: rgba(255, 255, 255, 0.1);
        box-shadow: 0 20px 40px -10px rgba(0,0,0,0.4);
    }

    /* --- 4. COMPONENT: DATA ROWS --- */
    .topic-row {
        display: flex; gap: 16px; padding: 20px;
        border-bottom: 1px solid var(--border-glass);
        position: relative; transition: 0.2s;
    }
    .topic-row:last-child { border-bottom: none; }
    .topic-row:hover { background: var(--accent-glow); }
    
    /* --- 5. TYPOGRAPHY & BADGES --- */
    .section-header {
        font-size: 0.7rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.15em;
        color: var(--text-muted); margin-bottom: 16px; display: flex; align-items: center; gap: 8px; opacity: 0.8;
    }
    
    .category-badge {
        font-size: 0.65rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em;
        padding: 4px 10px; border-radius: 6px; border: 1px solid transparent;
    }

    .seed-pill {
        background: rgba(251, 191, 36, 0.1); border: 1px solid rgba(251, 191, 36, 0.2);
        color: var(--seed-gold); font-size: 0.7rem; font-weight: 700; padding: 4px 12px; border-radius: 12px;
        display: inline-flex; align-items: center; gap: 6px;
    }

    /* --- 6. UTILITIES --- */
    .search-input {
        background: var(--bg-panel); border: 1px solid var(--border-glass); color: var(--text-main);
        padding: 14px 20px; border-radius: 14px; width: 100%; outline: none; transition: 0.3s; font-size: 0.95rem;
    }
    .search-input:focus { border-color: rgba(255,255,255,0.15); box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.04); }
    
    .btn-platinum {
        background: var(--text-main); color: var(--bg-app);
        padding: 12px 24px; border-radius: 12px; font-weight: 800; font-size: 0.9rem;
        border: none; cursor: pointer; transition: 0.2s; display: inline-flex; align-items: center; gap: 8px;
    }
    .btn-platinum:hover { transform: translateY(-2px); box-shadow: 0 10px 20px -5px rgba(0,0,0,0.2); }
</style>

<div class="ambient-glow"></div>

<div class="max-w-7xl mx-auto px-6 py-12">

    <header class="flex flex-col md:flex-row justify-between items-end mb-12">
        <div>
            <div class="flex items-center gap-3 mb-4">
                <span class="relative flex h-2 w-2">
                  <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                  <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                </span>
                <span class="text-[10px] font-bold text-emerald-500 uppercase tracking-widest">Network Live </span>
            </div>
            
            <h1 style="font-size: 3.5rem; font-weight: 900; background: var(--hero-gradient); -webkit-background-clip: text; -webkit-text-fill-color: transparent; line-height: 1.1; letter-spacing: -0.03em;">
            The Room 
            </h1>
            <p style="color: var(--text-muted); font-size: 1.1rem; margin-top: 8px;">
                Connect. Share your experience. Own the network.
            </p>
        </div>
        
        <div class="flex gap-4 mt-6 md:mt-0 items-center">
            <div class="relative group">
                <input type="text" class="search-input w-72" placeholder="Search...">
                <i class="fas fa-search absolute right-5 top-4 text-slate-500"></i>
            </div>
            <a href="new-topic.php" class="btn-platinum">
                <i class="fas fa-plus"></i> <span class="hidden md:inline">Create post</span>
            </a>
        </div>
    </header>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
        
        <aside class="lg:col-span-3">
            <div class="section-header"><i class="fas fa-layer-group"></i> Categories</div>
            <div class="room-card overflow-hidden">
                <div class="flex flex-col">
                    <?php if (!empty($categories)): ?>
                        <?php foreach ($categories as $cat): 
                             $catColor = getCategoryColor($cat['id']);
                        ?>
                        <a href="?cat=<?= $cat['id'] ?>" class="flex items-center justify-between p-4 hover:bg-[var(--accent-glow)] transition border-b border-[var(--border-glass)] last:border-0 group">
                            <div class="flex items-center gap-3">
                                <span class="w-2 h-2 rounded-full transition-all group-hover:scale-125" style="background-color: <?= $catColor ?>; box-shadow: 0 0 10px <?= $catColor ?>;"></span>
                                <span class="text-sm font-bold opacity-90 group-hover:text-[var(--text-main)] transition"><?= e($cat['name']) ?></span>
                            </div>
                            <span class="text-xs font-mono text-[var(--text-muted)] group-hover:text-[var(--text-main)]"><?= $cat['topic_count'] ?></span>
                        </a>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="p-4 text-xs text-[var(--text-muted)]">No channels active.</div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="mt-8 room-card p-6 border-l-4 border-l-white/20">
                <h4 class="text-xs font-black uppercase mb-2 tracking-widest text-white/60">Community Guidelines</h4>
                <p class="text-xs text-[var(--text-muted)] leading-relaxed">
                    Helpful posts earn <strong>Seeds</strong>. 
                    <br>Keep it professional and respectful.
                    <br>Focus on value for others.
                </p>
            </div>
        </aside>

        <section class="lg:col-span-6">
            <div class="flex items-center justify-between mb-4">
                <div class="section-header mb-0"><i class="fas fa-stream"></i> Live Feed</div>
                <div class="flex gap-4 text-xs font-bold text-[var(--text-muted)]">
                    <a href="?sort=new" class="text-[var(--text-main)] border-b-2 border-[var(--text-main)] pb-1">Newest</a>
                    <a href="?sort=top" class="hover:text-[var(--text-main)] transition">Top Rated</a>
                </div>
            </div>

            <div class="room-card">
                <?php if (!empty($feed)): ?>
                    <?php foreach ($feed as $topic): 
                        $catColor = getCategoryColor($topic['category_id']);
                    ?>
                    <div class="topic-row">
                        <div class="flex-shrink-0 pt-1">
                            <img src="<?= profileImage($topic['profile_image']) ?>" class="w-12 h-12 rounded-2xl object-cover border border-[var(--border-glass)]">
                        </div>
                        
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 mb-1.5">
                                <span class="category-badge" style="color:<?= $catColor ?>; background:<?= $catColor ?>15; border-color:<?= $catColor ?>30">
                                    <?= e($topic['category_name']) ?>
                                </span>
                                <span class="text-[10px] text-[var(--text-muted)] font-mono tracking-wide">• <?= timeAgo($topic['created_at']) ?></span>
                            </div>
                            
                            <h3 class="text-lg font-bold truncate pr-4 mb-1">
                                <a href="topic.php?id=<?= $topic['topic_id'] ?>" class="hover:text-white/80 transition">
                                    <?= e($topic['title']) ?>
                                </a>
                            </h3>
                            
                            <p class="text-xs text-[var(--text-muted)] line-clamp-1 mb-3">
                                Architect: <span class="text-[var(--text-main)] font-semibold"><?= e($topic['full_name']) ?></span>
                            </p>

                            <div class="flex items-center justify-between pt-2 border-t border-[var(--border-glass)]">
                                <div class="flex items-center gap-5 text-xs text-[var(--text-muted)] font-bold">
                                    <span class="flex items-center gap-1.5 hover:text-[var(--text-main)] transition"><i class="far fa-comment-alt"></i> <?= $topic['replies'] ?></span>
                                    <span class="flex items-center gap-1.5 hover:text-[var(--text-main)] transition"><i class="far fa-eye"></i> <?= $topic['views'] ?? 0 ?></span>
                                </div>
                                
                                <?php if($topic['replies'] > 10): ?>
                                <span class="seed-pill">
                                    <i class="fas fa-seedling"></i> 50 Bounty
                                </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="p-12 text-center text-[var(--text-muted)]">
                        <i class="fas fa-wind text-4xl mb-4 opacity-30"></i>
                        <p class="text-sm font-bold">Silence on the network.</p>
                        <p class="text-xs mt-1">Break the ice.</p>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <aside class="lg:col-span-3">
            <div class="section-header"><i class="fas fa-crown"></i> Top Contributors</div>
            
            <div class="room-card p-6 mb-6">
                <div class="flex items-center justify-between mb-5">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg bg-yellow-500/10 text-yellow-500 flex items-center justify-center font-black text-xs border border-yellow-500/20 shadow-[0_0_15px_rgba(234,179,8,0.2)]">1</div>
                        <div>
                            <div class="text-sm font-bold">Lekan_Dev</div>
                            <div class="text-[10px] text-[var(--text-muted)] font-mono">12k Seeds</div>
                        </div>
                    </div>
                    <i class="fas fa-trophy text-yellow-500/50"></i>
                </div>
                
                <div class="flex items-center justify-between mb-5">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg bg-slate-500/10 text-slate-400 flex items-center justify-center font-black text-xs border border-slate-500/20">2</div>
                        <div>
                            <div class="text-sm font-bold">Amaka_PM</div>
                            <div class="text-[10px] text-[var(--text-muted)] font-mono">8.5k Seeds</div>
                        </div>
                    </div>
                </div>
                
                <button class="w-full mt-2 py-3 text-xs font-bold text-[var(--text-muted)] hover:text-[var(--text-main)] hover:bg-[var(--accent-glow)] transition border border-[var(--border-glass)] rounded-xl">
                    View Full Leaderboard
                </button>
            </div>

            <div class="room-card p-6">
                <span class="text-[10px] font-bold text-[var(--text-muted)] uppercase tracking-widest mb-3 block">Daily Volume</span>
                <div class="flex justify-between items-end mb-2">
                    <span class="text-3xl font-black text-[var(--text-main)] tracking-tighter">142</span>
                    <span class="text-xs text-emerald-400 font-bold mb-1.5"><i class="fas fa-arrow-up"></i> 12%</span>
                </div>
                <div class="w-full h-1.5 bg-[var(--border-glass)] rounded-full overflow-hidden">
                    <div class="h-full bg-gradient-to-r from-emerald-500 to-teal-400 w-[65%] shadow-[0_0_10px_rgba(16,185,129,0.5)]"></div>
                </div>
            </div>
        </aside>

    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>