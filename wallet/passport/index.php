<?php
/**
 * JOBMINGTON - Talent Passport View
 * 
 * Displays user's talent passport with verification status,
 * endorsements, and upgrade options.
 */

define('JOBMINGTON', true);

require_once __DIR__ . '/../../config/env.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';

Session::start();
$pdo = db();

// Check if viewing own passport or someone else's
$passportNumber = $_GET['id'] ?? null;
$isOwner = false;
$passport = null;
$user = null;

if ($passportNumber) {
    // Viewing a specific passport
    $stmt = $pdo->prepare("
        SELECT tp.*, u.user_id, u.full_name, u.profile_image, u.email, u.created_at as member_since,
               cv.headline, cv.summary, cv.city
        FROM talent_passports tp
        JOIN users u ON tp.user_id = u.user_id
        LEFT JOIN cv_profiles cv ON u.user_id = cv.user_id
        WHERE tp.passport_number = ? AND tp.is_public = 1
    ");
    $stmt->execute([$passportNumber]);
    $passport = $stmt->fetch();
    
    if ($passport && Session::isLoggedIn() && Session::get('user_id') == $passport['user_id']) {
        $isOwner = true;
    }
} elseif (Session::isLoggedIn()) {
    // Viewing own passport
    $userId = Session::get('user_id');
    $ownStmt = "
        SELECT tp.*, u.user_id, u.full_name, u.profile_image, u.email, u.created_at as member_since,
               cv.headline, cv.summary, cv.city
        FROM talent_passports tp
        JOIN users u ON tp.user_id = u.user_id
        LEFT JOIN cv_profiles cv ON u.user_id = cv.user_id
        WHERE tp.user_id = ?
    ";
    $stmt = $pdo->prepare($ownStmt);
    $stmt->execute([$userId]);
    $passport = $stmt->fetch();

    // First visit: create (claim) the passport for this user.
    if (!$passport) {
        try {
            for ($try = 0; $try < 5; $try++) {
                $passportNumber = 'JM' . str_pad((string) $userId, 6, '0', STR_PAD_LEFT) . strtoupper(bin2hex(random_bytes(2)));
                $chk = $pdo->prepare("SELECT 1 FROM talent_passports WHERE passport_number = ? LIMIT 1");
                $chk->execute([$passportNumber]);
                if (!$chk->fetchColumn()) {
                    break;
                }
            }
            $pdo->prepare("
                INSERT INTO talent_passports (user_id, passport_number, first_featured_at, last_featured_at)
                VALUES (?, ?, NOW(), NOW())
            ")->execute([$userId, $passportNumber]);

            $stmt = $pdo->prepare($ownStmt);
            $stmt->execute([$userId]);
            $passport = $stmt->fetch();
        } catch (Throwable $e) {
            error_log('Passport creation failed: ' . $e->getMessage());
        }
    }
    $isOwner = true;
}

// Get verifications if passport exists
$verifications = [];
$endorsements = [];
$viewStats = [];

if ($passport) {
    // Get verifications
    $stmt = $pdo->prepare("
        SELECT * FROM passport_verifications 
        WHERE passport_id = ? AND status = 'verified'
        ORDER BY verified_at DESC
    ");
    $stmt->execute([$passport['passport_id']]);
    $verifications = $stmt->fetchAll();
    
    // Get endorsements
    $stmt = $pdo->prepare("
        SELECT * FROM passport_endorsements 
        WHERE passport_id = ? AND is_verified = 1 AND is_public = 1
        ORDER BY created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$passport['passport_id']]);
    $endorsements = $stmt->fetchAll();
    
    // Get view stats (for owner only)
    if ($isOwner) {
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_views,
                COUNT(DISTINCT viewer_company_id) as unique_companies,
                COUNT(CASE WHEN viewed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as week_views
            FROM passport_views
            WHERE passport_id = ?
        ");
        $stmt->execute([$passport['passport_id']]);
        $viewStats = $stmt->fetch();
    }
    
    // Log view if not owner
    if (!$isOwner) {
        $stmt = $pdo->prepare("
            INSERT INTO passport_views (passport_id, viewer_user_id, source, ip_address)
            VALUES (?, ?, 'direct', ?)
        ");
        $stmt->execute([
            $passport['passport_id'],
            Session::isLoggedIn() ? Session::get('user_id') : null,
            $_SERVER['REMOTE_ADDR'] ?? null
        ]);
    }
}

// Get pricing for upgrades
$pricing = [];
$stmt = $pdo->query("SELECT * FROM passport_pricing WHERE is_active = 1");
$pricing = $stmt->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_ASSOC);

$pageTitle = $passport ? e($passport['full_name']) . "'s Talent Passport" : "Talent Passport";
require_once __DIR__ . '/../../includes/header.php';

// Level configuration
$levels = [
    'rising' => ['icon' => '', 'color' => 'green', 'name' => 'Rising', 'next' => 'verified', 'points_needed' => 100],
    'verified' => ['icon' => '', 'color' => 'blue', 'name' => 'Verified', 'next' => 'expert', 'points_needed' => 300],
    'expert' => ['icon' => '', 'color' => 'purple', 'name' => 'Expert', 'next' => 'elite', 'points_needed' => 1000],
    'elite' => ['icon' => '', 'color' => 'amber', 'name' => 'Elite', 'next' => null, 'points_needed' => null]
];
?>

<style>
    .passport-hero {
        background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0f172a 100%);
        border-bottom: 1px solid rgba(255,255,255,0.05);
    }
    .passport-card-lg {
        background: #0f172a;
        border: 1px solid rgba(255,255,255,0.1);
        border-radius: 24px;
        box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5);
    }
    .level-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 12px;
        border-radius: 9999px;
        font-size: 12px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }
    .level-rising { background: rgba(34, 197, 94, 0.1); color: #22c55e; border: 1px solid rgba(34, 197, 94, 0.2); }
    .level-verified { background: rgba(59, 130, 246, 0.1); color: #3b82f6; border: 1px solid rgba(59, 130, 246, 0.2); }
    .level-expert { background: rgba(168, 85, 247, 0.1); color: #a855f7; border: 1px solid rgba(168, 85, 247, 0.2); }
    .level-elite { background: rgba(245, 158, 11, 0.1); color: #f59e0b; border: 1px solid rgba(245, 158, 11, 0.2); }
    
    .verification-card {
        background: rgba(255,255,255,0.02);
        border: 1px solid rgba(255,255,255,0.05);
        border-radius: 12px;
        padding: 16px;
        transition: all 0.2s;
    }
    .verification-card:hover {
        background: rgba(255,255,255,0.05);
        border-color: rgba(255,255,255,0.1);
    }
    .stat-card {
        background: rgba(255,255,255,0.02);
        border: 1px solid rgba(255,255,255,0.05);
        border-radius: 16px;
        padding: 20px;
        text-align: center;
    }
    .progress-ring {
        width: 120px;
        height: 120px;
    }
    .endorsement-card {
        background: linear-gradient(135deg, rgba(255,255,255,0.03), rgba(255,255,255,0.01));
        border: 1px solid rgba(255,255,255,0.05);
        border-radius: 16px;
        padding: 20px;
    }
</style>

<?php if (!$passport): ?>
<!-- No Passport State -->
<section class="min-h-[60vh] flex items-center justify-center bg-slate-950">
    <div class="text-center max-w-lg px-4">
        <div class="w-24 h-24 mx-auto mb-6 rounded-full bg-slate-800 flex items-center justify-center">
            <i class="fas fa-passport text-4xl text-slate-600"></i>
        </div>
        <h1 class="text-3xl font-bold text-white mb-4">No Talent Passport Yet</h1>
        <p class="text-slate-400 mb-8">
            Talent Passports are awarded to professionals who get featured on the Jobmington homepage. 
            Complete your profile, earn certificates, and stay active to increase your chances!
        </p>
        <div class="flex flex-col sm:flex-row gap-4 justify-center">
            <a href="<?= SITE_URL ?>/seeker/dashboard.php" class="px-6 py-3 bg-white text-black rounded-xl font-bold hover:bg-slate-100 transition">
                Complete Your Profile
            </a>
            <a href="<?= SITE_URL ?>/jobs/" class="px-6 py-3 bg-white/10 text-white rounded-xl font-bold hover:bg-white/20 transition border border-white/10">
                Browse Jobs
            </a>
        </div>
    </div>
</section>

<?php else: ?>
<!-- Passport Display -->
<section class="passport-hero py-12">
    <div class="max-w-6xl mx-auto px-4">
        
        <!-- Back Link -->
        <a href="<?= SITE_URL ?>/wallet/" class="inline-flex items-center gap-2 text-slate-400 hover:text-white text-sm mb-8 transition">
            <i class="fas fa-arrow-left"></i> Back to Wallet
        </a>
        
        <div class="grid lg:grid-cols-3 gap-8">
            
            <!-- Left: Passport Card -->
            <div class="lg:col-span-1">
                <div class="passport-card-lg p-8 sticky top-24">
                    <!-- Header -->
                    <div class="flex justify-between items-start mb-6">
                        <div>
                            <span class="text-[10px] font-bold text-slate-500 uppercase tracking-widest block">Talent Passport</span>
                            <span class="text-cyan-400 font-mono text-sm"><?= e($passport['passport_number']) ?></span>
                        </div>
                        <span class="level-badge level-<?= $passport['level'] ?>">
                            <?= $levels[$passport['level']]['icon'] ?> <?= $levels[$passport['level']]['name'] ?>
                        </span>
                    </div>
                    
                    <!-- Avatar -->
                    <div class="text-center mb-6">
                        <div class="relative inline-block">
                            <img src="<?= profileImage($passport['profile_image']) ?>" 
                                 alt="<?= e($passport['full_name']) ?>"
                                 class="w-32 h-32 rounded-full object-cover border-4 border-slate-700">
                            <?php if ($passport['level'] === 'elite'): ?>
                            <div class="absolute -top-2 -right-2 w-10 h-10 bg-amber-500 rounded-full flex items-center justify-center text-xl">
                                
                            </div>
                            <?php endif; ?>
                        </div>
                        <h2 class="text-xl font-bold text-white mt-4"><?= e($passport['full_name']) ?></h2>
                        <?php if ($passport['headline']): ?>
                        <p class="text-slate-400 text-sm mt-1"><?= e($passport['headline']) ?></p>
                        <?php endif; ?>
                        <?php if ($passport['city']): ?>
                        <p class="text-slate-500 text-xs mt-1"><i class="fas fa-map-marker-alt mr-1"></i><?= e($passport['city']) ?></p>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Stats Grid -->
                    <div class="grid grid-cols-2 gap-3 mb-6">
                        <div class="bg-white/5 rounded-lg p-3 text-center">
                            <span class="text-2xl font-bold text-white block"><?= $passport['times_featured'] ?></span>
                            <span class="text-[10px] text-slate-500 uppercase">Featured</span>
                        </div>
                        <div class="bg-white/5 rounded-lg p-3 text-center">
                            <span class="text-2xl font-bold text-white block"><?= $passport['skills_verified'] ?></span>
                            <span class="text-[10px] text-slate-500 uppercase">Verified</span>
                        </div>
                        <div class="bg-white/5 rounded-lg p-3 text-center">
                            <span class="text-2xl font-bold text-white block"><?= $passport['endorsements_count'] ?></span>
                            <span class="text-[10px] text-slate-500 uppercase">Endorsed</span>
                        </div>
                        <div class="bg-white/5 rounded-lg p-3 text-center">
                            <span class="text-2xl font-bold text-white block"><?= $passport['successful_hires'] ?></span>
                            <span class="text-[10px] text-slate-500 uppercase">Hired</span>
                        </div>
                    </div>
                    
                    <!-- Footer -->
                    <div class="border-t border-white/5 pt-4 flex justify-between items-center">
                        <div class="text-[9px] text-slate-500 uppercase">
                            Member since <?= date('M Y', strtotime($passport['member_since'])) ?>
                        </div>
                        <i class="fas fa-qrcode text-2xl text-slate-600"></i>
                    </div>
                    
                    <?php if ($isOwner): ?>
                    <!-- Owner Actions -->
                    <div class="mt-6 space-y-2">
                        <button class="w-full py-3 rounded-xl bg-white text-black font-bold text-sm hover:bg-slate-100 transition" onclick="sharePassport()">
                            <i class="fas fa-share-alt mr-2"></i> Share Passport
                        </button>
                        <button class="w-full py-3 rounded-xl bg-white/10 text-white font-bold text-sm hover:bg-white/20 transition border border-white/10" onclick="exportPassport()">
                            <i class="fas fa-download mr-2"></i> Export as PDF (50 Seeds)
                        </button>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Right: Details -->
            <div class="lg:col-span-2 space-y-8">
                
                <?php if ($isOwner && $viewStats): ?>
                <!-- Analytics (Owner Only) -->
                <div class="bg-gradient-to-r from-blue-500/10 to-cyan-500/10 border border-blue-500/20 rounded-2xl p-6">
                    <h3 class="text-lg font-bold text-white mb-4"><i class="fas fa-chart-line mr-2 text-blue-400"></i> Passport Analytics</h3>
                    <div class="grid grid-cols-3 gap-4">
                        <div class="text-center">
                            <span class="text-3xl font-bold text-white block"><?= number_format($viewStats['total_views']) ?></span>
                            <span class="text-xs text-slate-400">Total Views</span>
                        </div>
                        <div class="text-center">
                            <span class="text-3xl font-bold text-white block"><?= number_format($viewStats['unique_companies']) ?></span>
                            <span class="text-xs text-slate-400">Companies</span>
                        </div>
                        <div class="text-center">
                            <span class="text-3xl font-bold text-white block"><?= number_format($viewStats['week_views']) ?></span>
                            <span class="text-xs text-slate-400">This Week</span>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Level Progress -->
                <?php if ($isOwner && $levels[$passport['level']]['next']): ?>
                <div class="bg-slate-900/50 border border-white/5 rounded-2xl p-6">
                    <h3 class="text-lg font-bold text-white mb-4">
                        <i class="fas fa-level-up-alt mr-2 text-amber-400"></i> Level Progress
                    </h3>
                    <div class="flex items-center gap-6">
                        <div class="text-center">
                            <span class="text-4xl"><?= $levels[$passport['level']]['icon'] ?></span>
                            <span class="text-xs text-slate-400 block mt-1"><?= $levels[$passport['level']]['name'] ?></span>
                        </div>
                        <div class="flex-1">
                            <div class="flex justify-between text-xs mb-2">
                                <span class="text-slate-400"><?= $passport['level_points'] ?> points</span>
                                <span class="text-slate-400"><?= $levels[$passport['level']]['points_needed'] ?> needed</span>
                            </div>
                            <div class="h-3 bg-slate-800 rounded-full overflow-hidden">
                                <?php $progress = min(100, ($passport['level_points'] / $levels[$passport['level']]['points_needed']) * 100); ?>
                                <div class="h-full bg-gradient-to-r from-amber-500 to-orange-500 rounded-full transition-all" style="width: <?= $progress ?>%"></div>
                            </div>
                        </div>
                        <div class="text-center">
                            <span class="text-4xl"><?= $levels[$levels[$passport['level']]['next']]['icon'] ?></span>
                            <span class="text-xs text-slate-400 block mt-1"><?= $levels[$levels[$passport['level']]['next']]['name'] ?></span>
                        </div>
                    </div>
                    <p class="text-xs text-slate-500 mt-4">
                        Earn points by getting featured, verifying skills, receiving endorsements, and getting hired through Jobmington.
                    </p>
                </div>
                <?php endif; ?>
                
                <!-- Verified Skills -->
                <div class="bg-slate-900/50 border border-white/5 rounded-2xl p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-bold text-white">
                            <i class="fas fa-check-circle mr-2 text-emerald-400"></i> Verified Credentials
                        </h3>
                        <?php if ($isOwner): ?>
                        <a href="<?= SITE_URL ?>/wallet/passport/verify.php" class="text-sm text-amber-400 hover:text-amber-300">
                            + Add Verification
                        </a>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (empty($verifications)): ?>
                    <div class="text-center py-8 text-slate-500">
                        <i class="fas fa-certificate text-4xl mb-3 opacity-50"></i>
                        <p>No verified credentials yet</p>
                        <?php if ($isOwner): ?>
                        <a href="<?= SITE_URL ?>/wallet/passport/verify.php" class="text-amber-400 text-sm mt-2 inline-block hover:underline">
                            Verify your first skill for 100 Seeds →
                        </a>
                        <?php endif; ?>
                    </div>
                    <?php else: ?>
                    <div class="grid gap-3">
                        <?php foreach ($verifications as $v): ?>
                        <div class="verification-card flex items-center gap-4">
                            <div class="w-10 h-10 rounded-lg bg-emerald-500/20 flex items-center justify-center">
                                <i class="fas fa-<?= $v['verification_type'] === 'skill' ? 'code' : ($v['verification_type'] === 'certificate' ? 'award' : 'briefcase') ?> text-emerald-400"></i>
                            </div>
                            <div class="flex-1">
                                <span class="text-white font-medium block"><?= e($v['reference_name']) ?></span>
                                <span class="text-xs text-slate-500">Verified by <?= ucfirst($v['verified_by']) ?> • <?= timeAgo($v['verified_at']) ?></span>
                            </div>
                            <i class="fas fa-check-circle text-emerald-400"></i>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Endorsements -->
                <div class="bg-slate-900/50 border border-white/5 rounded-2xl p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-bold text-white">
                            <i class="fas fa-quote-left mr-2 text-blue-400"></i> Endorsements
                        </h3>
                        <?php if ($isOwner): ?>
                        <a href="<?= SITE_URL ?>/wallet/passport/request-endorsement.php" class="text-sm text-amber-400 hover:text-amber-300">
                            Request Endorsement
                        </a>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (empty($endorsements)): ?>
                    <div class="text-center py-8 text-slate-500">
                        <i class="fas fa-user-check text-4xl mb-3 opacity-50"></i>
                        <p>No endorsements yet</p>
                        <?php if ($isOwner): ?>
                        <p class="text-xs mt-2">Request endorsements from past employers or colleagues</p>
                        <?php endif; ?>
                    </div>
                    <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($endorsements as $e): ?>
                        <div class="endorsement-card">
                            <div class="flex items-start gap-3 mb-3">
                                <div class="w-10 h-10 rounded-full bg-slate-700 flex items-center justify-center text-white font-bold">
                                    <?= strtoupper(substr($e['endorser_name'], 0, 1)) ?>
                                </div>
                                <div>
                                    <span class="text-white font-medium block"><?= e($e['endorser_name']) ?></span>
                                    <span class="text-xs text-slate-500"><?= e($e['endorser_title']) ?> • <?= e($e['relationship']) ?></span>
                                </div>
                                <?php if ($e['rating']): ?>
                                <div class="ml-auto text-amber-400 text-sm">
                                    <?= str_repeat('', $e['rating']) ?><?= str_repeat('', 5 - $e['rating']) ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            <p class="text-slate-300 text-sm italic">"<?= e($e['endorsement_text']) ?>"</p>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
                
                <?php if (!$isOwner && Session::isLoggedIn() && Session::isEmployer()): ?>
                <!-- Employer Contact CTA -->
                <div class="bg-gradient-to-r from-amber-500/20 to-orange-500/20 border border-amber-500/30 rounded-2xl p-6 text-center">
                    <h3 class="text-xl font-bold text-white mb-2">Interested in this talent?</h3>
                    <p class="text-slate-300 mb-4">Contact <?= e($passport['full_name']) ?> directly through Jobmington</p>
                    <button class="px-8 py-3 bg-amber-500 text-black rounded-xl font-bold hover:bg-amber-400 transition" onclick="contactTalent(<?= $passport['passport_id'] ?>)">
                        <i class="fas fa-envelope mr-2"></i> Contact ($5 or subscription)
                    </button>
                </div>
                <?php endif; ?>
                
            </div>
        </div>
    </div>
</section>

<script>
function sharePassport() {
    const url = '<?= SITE_URL ?>/wallet/passport/?id=<?= $passport['passport_number'] ?>';
    if (navigator.share) {
        navigator.share({
            title: '<?= e($passport['full_name']) ?>\'s Talent Passport',
            text: 'Check out my verified Talent Passport on Jobmington',
            url: url
        });
    } else {
        navigator.clipboard.writeText(url);
        alert('Passport link copied to clipboard!');
    }
}

function exportPassport() {
    if (confirm('Export your passport as a verified PDF?\n\nCost: 50 Seeds')) {
        window.location.href = '<?= SITE_URL ?>/wallet/passport/export.php';
    }
}

function contactTalent(passportId) {
    window.location.href = '<?= SITE_URL ?>/employer/contact-talent.php?passport=' + passportId;
}
</script>

<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
