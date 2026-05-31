<?php
/**
 * JOBMINGTON - Jobmington Wallet
 * Displays Seeds balance, certificates, and profile verification status
 */

define('JOBMINGTON', true);
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/seeds.php';

Session::start();

$pdo = db();
$userId = Session::userId();

// Get real wallet data
$wallet = null;
$seedBalance = "0.00";
$lifetimeEarned = 0;
$lifetimeSpent = 0;
$transactions = [];
$packages = [];

if ($userId) {
    $wallet = getWallet($userId);
    if ($wallet) {
        $seedBalance = number_format($wallet['balance'], 2);
        $lifetimeEarned = $wallet['lifetime_earned'];
        $lifetimeSpent = $wallet['lifetime_spent'];
    }
    $transactions = getRecentTransactions($userId, 10);
    
    // Award daily login bonus
    awardDailyLoginBonus($userId);
}

// Get seed packages for purchase
$packages = getSeedPackages();

$userName = "Guest"; 
$cardNumber = "XXXX XXXX XXXX XXXX";
if (Session::isLoggedIn()) {
    $userName = explode(' ', $_SESSION['full_name'] ?? 'Chief')[0];
    $cardNumber = "8829 4001 6002 " . str_pad($userId ?? rand(1000, 9999), 4, '0', STR_PAD_LEFT);
}

$pageTitle = 'Jobmington Wallet | Seeds & Certificates';
require_once __DIR__ . '/../includes/header.php';
?>

<style>
    /* --- 1. THE OBSIDIAN ENGINE --- */
    :root {
        --bg-deep: #02040a;
        --glass-surface: rgba(255, 255, 255, 0.03);
        --glass-border: rgba(255, 255, 255, 0.08);
        --neon-gold: #fbbf24;
        --neon-secondary: #3b82f6;
        --neon-cyan: #06b6d4;
    }

    body {
        background-color: var(--bg-deep);
        background-image: 
            radial-gradient(circle at 15% 50%, rgba(59, 130, 246, 0.08), transparent 25%),
            radial-gradient(circle at 85% 30%, rgba(251, 191, 36, 0.05), transparent 25%);
        font-family: 'Futura Cyrillic Demi';
        color: #f8fafc;
        overflow-x: hidden;
    }

    /* Noise Texture Overlay */
    .noise-overlay {
        position: fixed; inset: 0; z-index: -1; opacity: 0.03; pointer-events: none;
        background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noiseFilter'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.65' numOctaves='3' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noiseFilter)'/%3E%3C/svg%3E");
    }

    /* --- 2. THE BLACK CARD (3D TILT) --- */
    .card-perspective { perspective: 1000px; }
    
    .the-black-card {
        background: linear-gradient(135deg, #1a1c23 0%, #0f1115 100%);
        border-radius: 1.5rem;
        position: relative;
        overflow: hidden;
        border: 1px solid rgba(255,255,255,0.1);
        box-shadow: 
            0 25px 50px -12px rgba(0, 0, 0, 0.8),
            inset 0 0 0 1px rgba(255,255,255,0.05);
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }

    .the-black-card:hover {
        transform: rotateY(5deg) rotateX(5deg) translateY(-5px);
        box-shadow: 
            20px 20px 60px rgba(0,0,0,0.9),
            inset 0 0 0 1px rgba(251, 191, 36, 0.2);
    }

    /* Holographic Shine */
    .the-black-card::before {
        content: ''; position: absolute; top: 0; left: -150%; width: 100%; height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255,255,255,0.05), transparent);
        transform: skewX(-25deg);
        animation: shine 6s infinite ease-in-out;
    }

    @keyframes shine { 0% { left: -150%; } 20% { left: 150%; } 100% { left: 150%; } }

    /* The Chip */
    .smart-chip {
        width: 45px; height: 35px;
        background: linear-gradient(135deg, #fbbf24 0%, #b45309 100%);
        border-radius: 6px;
        position: relative;
    }
    .smart-chip::after {
        content: ''; position: absolute; inset: 0;
        background-image: url("data:image/svg+xml,%3Csvg width='40' height='40' viewBox='0 0 40 40' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M0 15h40M0 25h40M15 0v40M25 0v40' stroke='rgba(0,0,0,0.3)' stroke-width='1'/%3E%3C/svg%3E");
        border-radius: 6px;
    }

    /* --- 3. HUD ELEMENTS --- */
    .hud-panel {
        background: var(--glass-surface);
        backdrop-filter: blur(12px);
        border: 1px solid var(--glass-border);
        border-radius: 1.5rem;
    }

    .stat-pill {
        background: rgba(0,0,0,0.3);
        border: 1px solid rgba(255,255,255,0.05);
        border-radius: 12px;
        transition: 0.2s;
    }
    .stat-pill:hover { border-color: var(--neon-secondary); background: rgba(59, 130, 246, 0.05); }

    /* --- 4. LEDGER TERMINAL --- */
    .ledger-row {
        display: flex; align-items: center; justify-content: space-between;
        padding: 8px 0;
        border-bottom: 1px solid rgba(255,255,255,0.03);
        font-family: 'Futura Cyrillic Demi';
        font-size: 0.8rem;
    }
    .ledger-row:last-child { border-bottom: none; }
    
    .status-dot {
        height: 6px; width: 6px; border-radius: 50%;
        box-shadow: 0 0 8px currentColor;
    }

    /* Sparkline Chart (CSS Only) */
    .sparkline {
        display: flex; align-items: flex-end; gap: 2px; height: 40px; width: 100px;
    }
    .spark-bar { width: 100%; background: var(--neon-gold); opacity: 0.2; border-radius: 2px; animation: chart-grow 1s ease-out forwards; }
    @keyframes chart-grow { from { height: 0; } }

</style>

<div class="noise-overlay"></div>

<main class="max-w-5xl mx-auto px-4 pt-20 pb-8">
    
    <div class="flex items-center justify-between mb-5 opacity-80">
        <div class=\"flex items-center gap-3\">
            <a href="/jobmington/seeker/dashboard.php" class="text-slate-400 hover:text-white transition"><i class="fas fa-arrow-left mr-2"></i></a>
            <div class="h-8 w-1 bg-gradient-to-b from-yellow-400 to-transparent rounded-full"></div>
            <div>
                <h1 class="text-xl font-bold tracking-tight text-white">Jobmington Wallet</h1>
                <p class="text-[10px] font-mono text-slate-400 uppercase tracking-widest">Seeds & Certificates</p>
            </div>
        </div>
        <div class="hidden md:flex items-center gap-6 text-[10px] font-mono text-slate-500 uppercase tracking-wider">
            <span><i class="fas fa-wifi mr-2 text-emerald-500"></i>Connected</span>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-5">
        
        <div class="lg:col-span-7 space-y-4">
            
            <div class="card-perspective">
                <div class="the-black-card p-6 h-[200px] flex flex-col justify-between">
                    <div class="flex justify-between items-start">
                        <div class="smart-chip"></div>
                        <i class="fas fa-wifi text-white/20 rotate-90 text-2xl"></i>
                    </div>
                    
                    <div class="text-center">
                        <span class="text-slate-500 text-[10px] uppercase tracking-[0.4em] block mb-1">Available Balance</span>
                        <h2 class="text-4xl font-black text-white tracking-tight drop-shadow-[0_0_15px_rgba(255,255,255,0.3)] flex items-center justify-center gap-2">
                            <?= $seedBalance ?>
                            <span class="seed-icon-premium">
                                <svg viewBox="0 0 24 24" width="28" height="28">
                                    <defs>
                                        <linearGradient id="seedGold" x1="0%" y1="0%" x2="100%" y2="100%">
                                            <stop offset="0%" stop-color="#fde047"/>
                                            <stop offset="50%" stop-color="#fbbf24"/>
                                            <stop offset="100%" stop-color="#d97706"/>
                                        </linearGradient>
                                        <filter id="seedGlow">
                                            <feGaussianBlur stdDeviation="1" result="blur"/>
                                            <feMerge><feMergeNode in="blur"/><feMergeNode in="SourceGraphic"/></feMerge>
                                        </filter>
                                    </defs>
                                    <path filter="url(#seedGlow)" fill="url(#seedGold)" d="M12 2C8 2 4 6 4 12c0 4 2 8 8 10 6-2 8-6 8-10 0-6-4-10-8-10zm0 2c1.5 0 3 1 4 3-1-0.5-2.5-0.5-4 0.5-1.5-1-3-1-4-0.5 1-2 2.5-3 4-3zm-3 5c0.8 0 1.5 0.3 2 1 0.5-0.7 1.2-1 2-1s1.5 0.3 2 1c-0.5 2-1.5 4-4 6-2.5-2-3.5-4-4-6 0.5-0.7 1.2-1 2-1z"/>
                                </svg>
                            </span>
                        </h2>
                    </div>

                    <div class="flex justify-between items-end font-mono text-slate-400 text-xs">
                        <div>
                            <p class="text-[9px] uppercase text-slate-600 mb-1">Card Holder</p>
                            <p class="text-white tracking-widest uppercase"><?= $userName ?></p>
                        </div>
                        <div class="text-right">
                            <p class="tracking-widest opacity-50"><?= $cardNumber ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-4 gap-3">
                <button onclick="document.getElementById('topupModal').showModal()" class="hud-panel p-3 text-center group hover:border-yellow-500/30 transition">
                    <div class="h-8 w-8 mx-auto rounded-full bg-yellow-500/10 flex items-center justify-center text-yellow-500 mb-2 group-hover:scale-110 transition">
                        <i class="fas fa-plus text-sm"></i>
                    </div>
                    <span class="block text-white font-bold text-xs">Top Up</span>
                </button>
                <a href="/jobmington/jobs/" class="hud-panel p-3 text-center group hover:border-emerald-500/30 transition">
                    <div class="h-8 w-8 mx-auto rounded-full bg-emerald-500/10 flex items-center justify-center text-emerald-500 mb-2 group-hover:scale-110 transition">
                        <i class="fas fa-briefcase text-sm"></i>
                    </div>
                    <span class="block text-white font-bold text-xs">Jobs</span>
                </a>
                <div class="stat-pill p-3 text-center">
                    <span class="text-emerald-400 text-sm font-bold block"><?= number_format($lifetimeEarned, 0) ?></span>
                    <span class="text-[9px] text-slate-500 uppercase">Earned</span>
                </div>
                <div class="stat-pill p-3 text-center">
                    <span class="text-amber-400 text-sm font-bold block"><?= number_format($lifetimeSpent, 0) ?></span>
                    <span class="text-[9px] text-slate-500 uppercase">Spent</span>
                </div>
            </div>

            <div class="hud-panel p-4">
                <div class="flex justify-between items-center mb-3">
                    <div>
                        <h3 class="text-white font-bold text-xs">Seed Velocity</h3>
                        <p class="text-[9px] text-slate-500">7-Day Performance</p>
                    </div>
                    <span class="text-emerald-400 text-[10px] font-bold font-mono">+12.4% <i class="fas fa-level-up-alt"></i></span>
                </div>
                <div class="sparkline w-full" style="height: 32px;">
                    <div class="spark-bar" style="height: 40%"></div>
                    <div class="spark-bar" style="height: 60%"></div>
                    <div class="spark-bar" style="height: 45%"></div>
                    <div class="spark-bar" style="height: 80%"></div>
                    <div class="spark-bar" style="height: 55%"></div>
                    <div class="spark-bar" style="height: 90%; opacity: 0.8; background: #fbbf24;"></div>
                    <div class="spark-bar" style="height: 70%"></div>
                    <div class="spark-bar" style="height: 85%"></div>
                </div>
            </div>
        </div>

        <div class="lg:col-span-5">
            <div class="hud-panel h-full p-6 flex flex-col relative overflow-hidden">
                
                <div class="flex justify-between items-center mb-3">
                    <span class="text-[10px] font-bold text-slate-300 uppercase tracking-widest">Transactions</span>
                    <span class="text-[8px] font-mono text-slate-600">LIVE</span>
                </div>

                <div class="space-y-0 flex-1 overflow-y-auto overflow-x-hidden max-h-[320px] pl-1 pr-3">
                    <?php if (empty($transactions)): ?>
                    <div class="text-center py-8 text-slate-500">
                        <i class="fas fa-inbox text-3xl mb-3 opacity-30"></i>
                        <p class="text-xs">No transactions yet</p>
                        <p class="text-[10px] mt-1">Earn Seeds by completing courses and activities!</p>
                    </div>
                    <?php else: ?>
                    <?php foreach ($transactions as $tx): 
                        $isEarn = in_array($tx['type'], ['earn', 'bonus', 'refund', 'purchase', 'transfer_in']);
                        $iconClass = match($tx['source']) {
                            'signup_bonus', 'daily_login' => 'fa-gift',
                            'email_verify' => 'fa-envelope-circle-check',
                            'course_complete', 'course_enroll' => 'fa-graduation-cap',
                            'quiz_pass' => 'fa-check-circle',
                            'certificate_earn' => 'fa-certificate',
                            'job_apply' => 'fa-paper-plane',
                            'forum_post', 'forum_reply' => 'fa-comments',
                            'cv_create' => 'fa-file-lines',
                            'ai_chat_basic', 'ai_chat_premium' => 'fa-brain',
                            'cv_roast', 'cv_optimize' => 'fa-wand-magic-sparkles',
                            'purchase' => 'fa-shopping-cart',
                            'referral_signup' => 'fa-user-plus',
                            default => $isEarn ? 'fa-arrow-down' : 'fa-arrow-up'
                        };
                        $colorClass = $isEarn ? 'emerald' : 'amber';
                    ?>
                    <div class="ledger-row group hover:bg-white/[0.02] transition px-1 -mx-1 rounded">
                        <div class="flex items-center gap-1.5 min-w-0 flex-1">
                            <div class="h-5 w-5 shrink-0 rounded bg-<?= $colorClass ?>-500/10 flex items-center justify-center text-<?= $colorClass ?>-400 text-[8px] border border-<?= $colorClass ?>-500/20">
                                <i class="fas <?= $iconClass ?>"></i>
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="text-white text-[10px] font-medium truncate"><?= e($tx['description']) ?></div>
                                <div class="text-[8px] text-slate-500 truncate"><?= ucfirst(str_replace('_', ' ', $tx['source'])) ?></div>
                            </div>
                        </div>
                        <div class="text-right shrink-0 ml-1">
                            <div class="text-<?= $isEarn ? 'emerald' : 'slate' ?>-<?= $isEarn ? '400' : '300' ?> text-[10px] font-bold whitespace-nowrap">
                                <?= $isEarn ? '+' : '-' ?><?= number_format($tx['amount'], 2) ?>
                            </div>
                            <div class="text-[8px] text-slate-600 whitespace-nowrap"><?= formatDate($tx['created_at'], 'M j, g:i') ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div class="mt-auto pt-3">
                    
                    <a href="/jobmington/wallet/history.php" class="block w-full py-2 border border-white/5 bg-white/[0.02] text-[10px] font-mono text-slate-400 uppercase hover:bg-white/5 hover:text-white transition rounded-lg text-center">
                        View Full History
                    </a>
                </div>
            </div>
        </div>

    </div>
    
    <!-- Talent Passport Section -->
    <?php
    // Get user's talent passport
    $passport = null;
    if ($userId) {
        $stmt = $pdo->prepare("
            SELECT tp.*, 
                   (SELECT COUNT(*) FROM passport_views WHERE passport_id = tp.passport_id) as total_views
            FROM talent_passports tp 
            WHERE tp.user_id = ?
        ");
        $stmt->execute([$userId]);
        $passport = $stmt->fetch();
    }
    
    $levelConfig = [
        'rising' => ['icon' => '', 'color' => 'green', 'name' => 'Rising'],
        'verified' => ['icon' => '', 'color' => 'blue', 'name' => 'Verified'],
        'expert' => ['icon' => '', 'color' => 'purple', 'name' => 'Expert'],
        'elite' => ['icon' => '', 'color' => 'amber', 'name' => 'Elite']
    ];
    ?>
    
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-4 mt-6">
        <div class="lg:col-span-12">
            <div class="hud-panel p-6">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-3">
                        <div class="h-10 w-10 rounded-xl bg-gradient-to-br from-cyan-500/20 to-blue-500/20 flex items-center justify-center border border-cyan-500/20">
                            <i class="fas fa-passport text-cyan-400"></i>
                        </div>
                        <div>
                            <h3 class="text-white font-bold">Talent Passport</h3>
                            <p class="text-[10px] text-slate-500 uppercase tracking-wider">Verified Credential NFT</p>
                        </div>
                    </div>
                    <?php if ($passport): ?>
                    <a href="<?= SITE_URL ?>/wallet/passport/" class="px-4 py-2 rounded-lg bg-white/5 border border-white/10 text-white text-xs font-bold hover:bg-white/10 transition">
                        View Passport →
                    </a>
                    <?php endif; ?>
                </div>
                
                <?php if ($passport): ?>
                <!-- Has Passport -->
                <div class="grid md:grid-cols-2 gap-4">
                    <div class="bg-gradient-to-br from-slate-800/50 to-slate-900/50 rounded-2xl p-5 border border-white/5">
                        <div class="flex items-start justify-between mb-4">
                            <div>
                                <span class="text-[9px] text-slate-500 uppercase tracking-widest block mb-1">Passport Number</span>
                                <span class="text-cyan-400 font-mono text-lg"><?= e($passport['passport_number']) ?></span>
                            </div>
                            <span class="px-3 py-1 rounded-full text-[10px] font-bold uppercase bg-<?= $levelConfig[$passport['level']]['color'] ?>-500/10 text-<?= $levelConfig[$passport['level']]['color'] ?>-400 border border-<?= $levelConfig[$passport['level']]['color'] ?>-500/20">
                                <?= $levelConfig[$passport['level']]['icon'] ?> <?= $levelConfig[$passport['level']]['name'] ?>
                            </span>
                        </div>
                        <div class="grid grid-cols-4 gap-2 text-center">
                            <div>
                                <span class="text-white font-bold text-lg block"><?= $passport['times_featured'] ?></span>
                                <span class="text-[8px] text-slate-500 uppercase">Featured</span>
                            </div>
                            <div>
                                <span class="text-white font-bold text-lg block"><?= $passport['skills_verified'] ?></span>
                                <span class="text-[8px] text-slate-500 uppercase">Verified</span>
                            </div>
                            <div>
                                <span class="text-white font-bold text-lg block"><?= $passport['endorsements_count'] ?></span>
                                <span class="text-[8px] text-slate-500 uppercase">Endorsed</span>
                            </div>
                            <div>
                                <span class="text-white font-bold text-lg block"><?= number_format($passport['total_views'] ?? 0) ?></span>
                                <span class="text-[8px] text-slate-500 uppercase">Views</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="space-y-3">
                        <a href="<?= SITE_URL ?>/wallet/passport/verify.php" class="flex items-center gap-4 p-4 rounded-xl bg-white/[0.02] border border-white/5 hover:bg-white/[0.05] hover:border-emerald-500/30 transition group">
                            <div class="h-10 w-10 rounded-lg bg-emerald-500/10 flex items-center justify-center text-emerald-400 group-hover:scale-110 transition">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <div class="flex-1">
                                <span class="text-white font-bold text-sm block">Verify Skills</span>
                                <span class="text-[10px] text-slate-500">Add verified credentials • 100 Seeds</span>
                            </div>
                            <i class="fas fa-chevron-right text-slate-600 group-hover:text-white transition"></i>
                        </a>
                        <a href="<?= SITE_URL ?>/wallet/passport/boost.php" class="flex items-center gap-4 p-4 rounded-xl bg-white/[0.02] border border-white/5 hover:bg-white/[0.05] hover:border-amber-500/30 transition group">
                            <div class="h-10 w-10 rounded-lg bg-amber-500/10 flex items-center justify-center text-amber-400 group-hover:scale-110 transition">
                                <i class="fas fa-rocket"></i>
                            </div>
                            <div class="flex-1">
                                <span class="text-white font-bold text-sm block">Boost Visibility</span>
                                <span class="text-[10px] text-slate-500">3x employer visibility • 200 Seeds/week</span>
                            </div>
                            <i class="fas fa-chevron-right text-slate-600 group-hover:text-white transition"></i>
                        </a>
                    </div>
                </div>
                
                <?php else: ?>
                <!-- No Passport Yet -->
                <div class="text-center py-8">
                    <div class="w-20 h-20 mx-auto mb-4 rounded-full bg-slate-800/50 flex items-center justify-center">
                        <i class="fas fa-passport text-3xl text-slate-600"></i>
                    </div>
                    <h4 class="text-white font-bold text-lg mb-2">No Talent Passport Yet</h4>
                    <p class="text-slate-400 text-sm max-w-md mx-auto mb-4">
                        Get featured on the Jobmington homepage to receive your Talent Passport — a verified credential that showcases your professional achievements to employers.
                    </p>
                    <div class="flex flex-wrap justify-center gap-2 text-[10px]">
                        <span class="px-3 py-1.5 rounded-full bg-white/5 text-slate-400 border border-white/10">
                            <i class="fas fa-check mr-1 text-emerald-400"></i> Complete Profile
                        </span>
                        <span class="px-3 py-1.5 rounded-full bg-white/5 text-slate-400 border border-white/10">
                            <i class="fas fa-check mr-1 text-emerald-400"></i> Earn Certificates
                        </span>
                        <span class="px-3 py-1.5 rounded-full bg-white/5 text-slate-400 border border-white/10">
                            <i class="fas fa-check mr-1 text-emerald-400"></i> Stay Active
                        </span>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<!-- Top Up Modal - Premium Dynamic Version -->
<dialog id="topupModal" class="bg-transparent backdrop:bg-black/90 backdrop:backdrop-blur-md p-4">
    <div class="topup-modal-inner bg-gradient-to-br from-slate-900 via-slate-900 to-slate-800 border border-white/10 rounded-3xl w-full max-w-md overflow-hidden shadow-2xl shadow-yellow-500/10">
        <!-- Animated Header -->
        <div class="relative overflow-hidden bg-gradient-to-r from-yellow-500/20 via-amber-500/10 to-yellow-500/20 p-6 border-b border-white/5">
            <div class="absolute inset-0 bg-[url('data:image/svg+xml,%3Csvg width=\"60\" height=\"60\" viewBox=\"0 0 60 60\" xmlns=\"http://www.w3.org/2000/svg\"%3E%3Cg fill=\"none\" fill-rule=\"evenodd\"%3E%3Cg fill=\"%23fbbf24\" fill-opacity=\"0.05\"%3E%3Cpath d=\"M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z\"/%3E%3C/g%3E%3C/g%3E%3C/svg%3E')] opacity-50"></div>
            <div class="absolute -top-10 -right-10 w-32 h-32 bg-yellow-500/20 rounded-full blur-3xl animate-pulse"></div>
            <div class="absolute -bottom-10 -left-10 w-24 h-24 bg-amber-500/20 rounded-full blur-2xl animate-pulse" style="animation-delay: 1s;"></div>
            
            <div class="relative flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="seed-icon-modal h-12 w-12 rounded-2xl bg-gradient-to-br from-yellow-400 to-amber-600 flex items-center justify-center shadow-lg shadow-yellow-500/30">
                        <svg viewBox="0 0 24 24" width="24" height="24" class="drop-shadow-lg">
                            <defs>
                                <linearGradient id="seedWhite" x1="0%" y1="0%" x2="100%" y2="100%">
                                    <stop offset="0%" stop-color="#ffffff"/>
                                    <stop offset="100%" stop-color="#fef3c7"/>
                                </linearGradient>
                            </defs>
                            <path fill="url(#seedWhite)" d="M12 2C8 2 4 6 4 12c0 4 2 8 8 10 6-2 8-6 8-10 0-6-4-10-8-10zm0 2c1.5 0 3 1 4 3-1-0.5-2.5-0.5-4 0.5-1.5-1-3-1-4-0.5 1-2 2.5-3 4-3zm-3 5c0.8 0 1.5 0.3 2 1 0.5-0.7 1.2-1 2-1s1.5 0.3 2 1c-0.5 2-1.5 4-4 6-2.5-2-3.5-4-4-6 0.5-0.7 1.2-1 2-1z"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-white font-bold text-lg">Power Up</h3>
                        <p class="text-yellow-500/80 text-xs font-medium">Get Seeds Instantly</p>
                    </div>
                </div>
                <button onclick="document.getElementById('topupModal').close()" class="h-8 w-8 rounded-full bg-white/5 hover:bg-white/10 flex items-center justify-center text-slate-400 hover:text-white transition hover:rotate-90 duration-300">
                    <i class="fas fa-times text-sm"></i>
                </button>
            </div>
        </div>
        
        <!-- Package Cards -->
        <form id="topupForm" method="POST" action="/jobmington/payments/checkout.php">
            <input type="hidden" name="plan" id="selectedPlan" value="">
            <input type="hidden" name="amount" id="selectedAmount" value="">
            <input type="hidden" name="credits" id="selectedCredits" value="">
        </form>
        
        <div class="p-5 space-y-3">
            <?php 
            $packageIcons = ['fa-bolt', 'fa-fire', 'fa-gem', 'fa-crown', 'fa-rocket'];
            $packageColors = ['blue', 'orange', 'purple', 'yellow', 'emerald'];
            $i = 0;
            foreach ($packages as $pkg): 
                $icon = $packageIcons[$i % count($packageIcons)];
                $color = $packageColors[$i % count($packageColors)];
                $totalSeeds = $pkg['seeds_amount'] + $pkg['bonus_seeds'];
                $i++;
            ?>
            <div class="package-card group relative rounded-2xl border transition-all duration-300 cursor-pointer overflow-hidden <?= $pkg['is_featured'] ? 'border-yellow-500/50 bg-gradient-to-r from-yellow-500/10 via-amber-500/5 to-yellow-500/10 hover:border-yellow-400 hover:shadow-lg hover:shadow-yellow-500/20' : 'border-white/10 bg-white/[0.02] hover:border-white/30 hover:bg-white/[0.04]' ?>"
                 onclick="selectPackage('<?= e($pkg['name']) ?>', <?= $pkg['price_ngn'] ?>, <?= $totalSeeds ?>)"
                 data-plan="<?= e($pkg['name']) ?>"
                 data-amount="<?= $pkg['price_ngn'] ?>"
                 data-credits="<?= $totalSeeds ?>">
                <?php if ($pkg['is_featured']): ?>
                <div class="absolute -right-8 top-3 rotate-45 bg-gradient-to-r from-yellow-500 to-amber-500 text-black text-[8px] font-black uppercase px-8 py-0.5 shadow-md">Best</div>
                <?php endif; ?>
                
                <div class="p-4 flex items-center gap-4">
                    <div class="h-11 w-11 rounded-xl bg-<?= $color ?>-500/15 border border-<?= $color ?>-500/20 flex items-center justify-center text-<?= $color ?>-400 group-hover:scale-110 transition-transform duration-300">
                        <i class="fas <?= $icon ?>"></i>
                    </div>
                    <div class="flex-1">
                        <h4 class="text-white font-bold text-sm group-hover:text-yellow-400 transition-colors"><?= e($pkg['name']) ?></h4>
                        <div class="flex items-center gap-2 mt-0.5">
                            <span class="text-slate-300 text-xs font-mono"><?= number_format($pkg['seeds_amount']) ?></span>
                            <svg viewBox="0 0 24 24" width="12" height="12" class="text-yellow-500"><path fill="currentColor" d="M12 2C8 2 4 6 4 12c0 4 2 8 8 10 6-2 8-6 8-10 0-6-4-10-8-10z"/></svg>
                            <?php if ($pkg['bonus_seeds'] > 0): ?>
                            <span class="text-emerald-400 text-[10px] font-bold bg-emerald-500/10 px-1.5 py-0.5 rounded">+<?= number_format($pkg['bonus_seeds']) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="text-right">
                        <span class="text-white font-bold block">₦<?= number_format($pkg['price_ngn']) ?></span>
                        <span class="text-[10px] text-slate-500 group-hover:text-yellow-500 transition-colors">Select →</span>
                    </div>
                </div>
                
                <!-- Hover Glow Effect -->
                <div class="absolute inset-0 bg-gradient-to-r from-<?= $color ?>-500/0 via-<?= $color ?>-500/5 to-<?= $color ?>-500/0 opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none"></div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Earn Free Section -->
        <div class="mx-5 mb-5 p-4 rounded-2xl bg-gradient-to-br from-emerald-500/10 to-teal-500/5 border border-emerald-500/20">
            <div class="flex items-center gap-2 mb-3">
                <div class="h-6 w-6 rounded-lg bg-emerald-500/20 flex items-center justify-center">
                    <i class="fas fa-seedling text-emerald-400 text-xs"></i>
                </div>
                <span class="text-emerald-400 font-bold text-xs uppercase tracking-wide">Earn Free Seeds</span>
            </div>
            <div class="grid grid-cols-2 gap-2 text-[10px]">
                <div class="flex items-center gap-2 text-slate-400">
                    <i class="fas fa-graduation-cap text-blue-400 w-3"></i>
                    <span>Courses <span class="text-emerald-400 font-bold">+50</span></span>
                </div>
                <div class="flex items-center gap-2 text-slate-400">
                    <i class="fas fa-check-circle text-green-400 w-3"></i>
                    <span>Quizzes <span class="text-emerald-400 font-bold">+25</span></span>
                </div>
                <div class="flex items-center gap-2 text-slate-400">
                    <i class="fas fa-certificate text-yellow-400 w-3"></i>
                    <span>Certificates <span class="text-emerald-400 font-bold">+100</span></span>
                </div>
                <div class="flex items-center gap-2 text-slate-400">
                    <i class="fas fa-calendar-check text-purple-400 w-3"></i>
                    <span>Daily Login <span class="text-emerald-400 font-bold">+5</span></span>
                </div>
            </div>
        </div>
    </div>
</dialog>

<style>
    @keyframes scan { 0% { top: 0; opacity: 0; } 50% { opacity: 0.5; } 100% { top: 100%; opacity: 0; } }
    
    /* Premium Seed Icon */
    .seed-icon-premium {
        display: inline-flex;
        filter: drop-shadow(0 0 8px rgba(251, 191, 36, 0.5));
        animation: seedPulse 2s ease-in-out infinite;
    }
    @keyframes seedPulse {
        0%, 100% { filter: drop-shadow(0 0 8px rgba(251, 191, 36, 0.5)); transform: scale(1); }
        50% { filter: drop-shadow(0 0 15px rgba(251, 191, 36, 0.8)); transform: scale(1.05); }
    }
    
    /* Modal Animations */
    #topupModal {
        animation: modalFadeIn 0.3s ease-out;
    }
    #topupModal::backdrop {
        animation: backdropFade 0.3s ease-out;
    }
    @keyframes modalFadeIn {
        from { opacity: 0; transform: scale(0.95) translateY(10px); }
        to { opacity: 1; transform: scale(1) translateY(0); }
    }
    @keyframes backdropFade {
        from { opacity: 0; }
        to { opacity: 1; }
    }
    
    .topup-modal-inner {
        animation: slideUp 0.4s cubic-bezier(0.16, 1, 0.3, 1);
    }
    @keyframes slideUp {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    .seed-icon-modal {
        animation: iconBounce 0.6s cubic-bezier(0.34, 1.56, 0.64, 1) 0.2s both;
    }
    @keyframes iconBounce {
        from { opacity: 0; transform: scale(0) rotate(-10deg); }
        to { opacity: 1; transform: scale(1) rotate(0); }
    }
    
    .package-card {
        animation: cardSlideIn 0.4s ease-out backwards;
    }
    .package-card:nth-child(1) { animation-delay: 0.1s; }
    .package-card:nth-child(2) { animation-delay: 0.15s; }
    .package-card:nth-child(3) { animation-delay: 0.2s; }
    .package-card:nth-child(4) { animation-delay: 0.25s; }
    .package-card:nth-child(5) { animation-delay: 0.3s; }
    @keyframes cardSlideIn {
        from { opacity: 0; transform: translateX(-10px); }
        to { opacity: 1; transform: translateX(0); }
    }
    
    .package-card.selected {
        border-color: #fbbf24 !important;
        box-shadow: 0 0 20px rgba(251, 191, 36, 0.3);
    }
</style>

<script>
function selectPackage(plan, amount, credits) {
    // Update form values
    document.getElementById('selectedPlan').value = plan;
    document.getElementById('selectedAmount').value = amount;
    document.getElementById('selectedCredits').value = credits;
    
    // Visual feedback - highlight selected
    document.querySelectorAll('.package-card').forEach(card => {
        card.classList.remove('selected');
    });
    event.currentTarget.classList.add('selected');
    
    // Submit form after brief delay for visual feedback
    setTimeout(() => {
        document.getElementById('topupForm').submit();
    }, 200);
}

document.addEventListener('DOMContentLoaded', () => {
    const params = new URLSearchParams(window.location.search);
    if (params.get('topup') === '1') {
        document.getElementById('topupModal')?.showModal();
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
