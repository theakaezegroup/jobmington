<?php
/**
 * JOBMINGTON - Passport Verification Center
 * 
 * Users can verify skills, link certificates, and request endorsements
 * to upgrade their Talent Passport level.
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

// Must be logged in
if (!Session::isLoggedIn()) {
    header('Location: ' . SITE_URL . '/auth/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

$userId = Session::get('user_id');
$userSeeds = getSeeds($userId);

// Get user's passport
$stmt = $pdo->prepare("SELECT * FROM talent_passports WHERE user_id = ?");
$stmt->execute([$userId]);
$passport = $stmt->fetch();

if (!$passport) {
    // Redirect to passport info page
    header('Location: ' . SITE_URL . '/wallet/passport/?no_passport=1');
    exit;
}

// Get existing verifications
$stmt = $pdo->prepare("
    SELECT * FROM passport_verifications 
    WHERE passport_id = ?
    ORDER BY created_at DESC
");
$stmt->execute([$passport['passport_id']]);
$verifications = $stmt->fetchAll();

// Get user's certificates
$stmt = $pdo->prepare("
    SELECT c.*, cr.title AS course_name, c.verification_code AS certificate_number
    FROM certificates c
    JOIN courses cr ON c.course_id = cr.course_id
    WHERE c.user_id = ?
    ORDER BY c.issued_at DESC
");
$stmt->execute([$userId]);
$certificates = $stmt->fetchAll();

// Get endorsements
$stmt = $pdo->prepare("
    SELECT pe.*, u.full_name, u.profile_image
    FROM passport_endorsements pe
    LEFT JOIN users u ON pe.endorser_user_id = u.user_id
    WHERE pe.passport_id = ?
    ORDER BY pe.created_at DESC
");
$stmt->execute([$passport['passport_id']]);
$endorsements = $stmt->fetchAll();

// Get pricing
$stmt = $pdo->prepare("SELECT * FROM passport_pricing WHERE item_type = 'verification' AND is_active = 1");
$stmt->execute();
$pricing = [
    'skill_verify' => ['price_seeds' => 100],
    'cert_link' => ['price_seeds' => 50],
    'ext_cert' => ['price_seeds' => 75],
];
foreach ($stmt->fetchAll() as $p) {
    $key = match (strtolower($p['item_name'])) {
        'skill verification' => 'skill_verify',
        'certificate link' => 'cert_link',
        'external certificate' => 'ext_cert',
        default => null,
    };

    if ($key) {
        $pricing[$key] = $p;
    }
}

// Calculate level progress
$verifiedCount = count(array_filter($verifications, fn($v) => $v['status'] === 'verified'));
$endorsementCount = count(array_filter($endorsements, fn($e) => $e['is_verified']));
$certificateCount = count(array_filter($verifications, fn($v) => $v['verification_type'] === 'certificate' && $v['status'] === 'verified'));

$levelProgress = [
    'verified' => ['need' => 3, 'have' => $verifiedCount, 'desc' => '3 verified skills'],
    'expert' => ['need' => 2, 'have' => $endorsementCount, 'desc' => '2 employer endorsements'],
    'elite' => ['need' => 5, 'have' => $passport['times_featured'], 'desc' => 'Featured 5+ times']
];

$pageTitle = 'Verify Your Passport';
require_once __DIR__ . '/../../includes/header.php';

$levelConfig = [
    'rising' => ['icon' => '', 'color' => 'emerald', 'name' => 'Rising'],
    'verified' => ['icon' => '', 'color' => 'blue', 'name' => 'Verified'],
    'expert' => ['icon' => '', 'color' => 'purple', 'name' => 'Expert'],
    'elite' => ['icon' => '', 'color' => 'amber', 'name' => 'Elite']
];
$currentLevel = $levelConfig[$passport['level']];
?>

<style>
    .verify-card {
        background: rgba(255,255,255,0.02);
        border: 1px solid rgba(255,255,255,0.05);
        border-radius: 16px;
        transition: all 0.3s;
    }
    .verify-card:hover {
        border-color: rgba(255,255,255,0.15);
        background: rgba(255,255,255,0.03);
    }
    .verify-card.done {
        border-color: rgba(34, 197, 94, 0.3);
        background: rgba(34, 197, 94, 0.05);
    }
    .skill-pill {
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 12px;
        background: rgba(255,255,255,0.05);
        border: 1px solid rgba(255,255,255,0.1);
        cursor: pointer;
        transition: all 0.2s;
    }
    .skill-pill:hover {
        background: rgba(251, 191, 36, 0.1);
        border-color: rgba(251, 191, 36, 0.3);
    }
    .skill-pill.selected {
        background: rgba(251, 191, 36, 0.2);
        border-color: rgba(251, 191, 36, 0.5);
        color: #fbbf24;
    }
</style>

<div class="min-h-screen bg-slate-950">
    <div class="max-w-4xl mx-auto px-4 py-8">
        
        <!-- Header -->
        <div class="mb-8">
            <a href="<?= SITE_URL ?>/wallet/passport/" class="text-slate-400 hover:text-white text-xs uppercase tracking-wider mb-2 inline-block">
                <i class="fas fa-arrow-left mr-1"></i> My Passport
            </a>
            <h1 class="text-3xl font-bold text-white">Verification Center</h1>
            <p class="text-slate-400 mt-1">Verify skills, link certificates, and earn endorsements to level up your passport</p>
        </div>
        
        <!-- Current Status -->
        <div class="grid md:grid-cols-3 gap-4 mb-8">
            <!-- Level Card -->
            <div class="bg-slate-900/50 border border-white/5 rounded-2xl p-5">
                <div class="flex items-center gap-3 mb-3">
                    <span class="text-3xl"><?= $currentLevel['icon'] ?></span>
                    <div>
                        <span class="text-white font-bold text-lg block"><?= $currentLevel['name'] ?></span>
                        <span class="text-xs text-slate-500">Current Level</span>
                    </div>
                </div>
                <div class="text-xs font-mono text-slate-500"><?= e($passport['passport_number']) ?></div>
            </div>
            
            <!-- Seeds Balance -->
            <div class="bg-slate-900/50 border border-white/5 rounded-2xl p-5">
                <div class="flex items-center gap-3 mb-3">
                    <div class="w-12 h-12 rounded-full bg-amber-500/10 flex items-center justify-center">
                        <span class="text-xl"></span>
                    </div>
                    <div>
                        <span class="text-white font-bold text-lg block"><?= number_format($userSeeds) ?></span>
                        <span class="text-xs text-slate-500">Seeds Balance</span>
                    </div>
                </div>
                <a href="<?= SITE_URL ?>/wallet/" class="text-amber-400 text-xs hover:underline">
                    Get more Seeds <i class="fas fa-arrow-right ml-1"></i>
                </a>
            </div>
            
            <!-- Verified Count -->
            <div class="bg-slate-900/50 border border-white/5 rounded-2xl p-5">
                <div class="flex items-center gap-3 mb-3">
                    <div class="w-12 h-12 rounded-full bg-emerald-500/10 flex items-center justify-center">
                        <i class="fas fa-check text-emerald-400 text-xl"></i>
                    </div>
                    <div>
                        <span class="text-white font-bold text-lg block"><?= $verifiedCount ?></span>
                        <span class="text-xs text-slate-500">Verified Credentials</span>
                    </div>
                </div>
                <span class="text-emerald-400 text-xs"><?= $endorsementCount ?> endorsements</span>
            </div>
        </div>
        
        <!-- Level Progress -->
        <div class="bg-slate-900/50 border border-white/5 rounded-2xl p-5 mb-8">
            <h2 class="text-white font-bold mb-4">Level Progress</h2>
            <div class="grid md:grid-cols-3 gap-4">
                <?php foreach (['verified', 'expert', 'elite'] as $targetLevel): ?>
                <?php 
                $prog = $levelProgress[$targetLevel];
                $complete = $prog['have'] >= $prog['need'];
                $lvl = $levelConfig[$targetLevel];
                ?>
                <div class="p-4 rounded-xl <?= $complete ? 'bg-' . $lvl['color'] . '-500/10 border border-' . $lvl['color'] . '-500/20' : 'bg-white/5' ?>">
                    <div class="flex items-center gap-2 mb-2">
                        <span><?= $lvl['icon'] ?></span>
                        <span class="text-white font-bold"><?= $lvl['name'] ?></span>
                        <?php if ($complete): ?>
                        <i class="fas fa-check-circle text-<?= $lvl['color'] ?>-400 ml-auto"></i>
                        <?php endif; ?>
                    </div>
                    <p class="text-slate-400 text-xs mb-2"><?= $prog['desc'] ?></p>
                    <div class="h-2 bg-white/10 rounded-full overflow-hidden">
                        <div class="h-full bg-<?= $lvl['color'] ?>-500 rounded-full transition-all" 
                             style="width: <?= min(100, ($prog['have'] / $prog['need']) * 100) ?>%"></div>
                    </div>
                    <div class="text-xs text-slate-500 mt-1"><?= min($prog['have'], $prog['need']) ?>/<?= $prog['need'] ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Verification Options -->
        <h2 class="text-white font-bold text-xl mb-4">Verify Your Skills</h2>
        
        <div class="grid md:grid-cols-2 gap-4 mb-8">
            <!-- AI Skill Verification -->
            <div class="verify-card p-6">
                <div class="flex items-start justify-between mb-4">
                    <div class="w-14 h-14 rounded-2xl bg-blue-500/10 flex items-center justify-center">
                        <i class="fas fa-robot text-2xl text-blue-400"></i>
                    </div>
                    <span class="px-3 py-1 rounded-full bg-amber-500/10 text-amber-400 text-xs font-bold">
                        <span class="mr-1"></span> <?= $pricing['skill_verify']['price_seeds'] ?? 100 ?>
                    </span>
                </div>
                <h3 class="text-white font-bold text-lg mb-2">AI Skill Assessment</h3>
                <p class="text-slate-400 text-sm mb-4">Take a quick AI-powered assessment to verify your expertise in any skill.</p>
                <button onclick="openSkillVerify()" class="w-full py-3 rounded-xl bg-blue-500 text-white font-bold text-sm hover:bg-blue-400 transition">
                    <i class="fas fa-play mr-2"></i> Start Assessment
                </button>
            </div>
            
            <!-- Link Certificate -->
            <div class="verify-card p-6">
                <div class="flex items-start justify-between mb-4">
                    <div class="w-14 h-14 rounded-2xl bg-purple-500/10 flex items-center justify-center">
                        <i class="fas fa-certificate text-2xl text-purple-400"></i>
                    </div>
                    <span class="px-3 py-1 rounded-full bg-amber-500/10 text-amber-400 text-xs font-bold">
                        <span class="mr-1"></span> <?= $pricing['cert_link']['price_seeds'] ?? 50 ?>
                    </span>
                </div>
                <h3 class="text-white font-bold text-lg mb-2">Link Certificate</h3>
                <p class="text-slate-400 text-sm mb-4">Connect your Jobmington course certificates to auto-verify skills.</p>
                <?php if (count($certificates) > 0): ?>
                <button onclick="openCertLink()" class="w-full py-3 rounded-xl bg-purple-500 text-white font-bold text-sm hover:bg-purple-400 transition">
                    <i class="fas fa-link mr-2"></i> Link Certificate (<?= count($certificates) ?> available)
                </button>
                <?php else: ?>
                <a href="<?= SITE_URL ?>/seeker/profile.php" class="block w-full py-3 rounded-xl bg-slate-700 text-slate-300 font-bold text-sm text-center hover:bg-slate-600 transition">
                    <i class="fas fa-user mr-2"></i> Complete Profile First
                </a>
                <?php endif; ?>
            </div>
            
            <!-- Request Endorsement -->
            <div class="verify-card p-6">
                <div class="flex items-start justify-between mb-4">
                    <div class="w-14 h-14 rounded-2xl bg-emerald-500/10 flex items-center justify-center">
                        <i class="fas fa-handshake text-2xl text-emerald-400"></i>
                    </div>
                    <span class="px-3 py-1 rounded-full bg-slate-700 text-slate-400 text-xs font-bold">
                        FREE
                    </span>
                </div>
                <h3 class="text-white font-bold text-lg mb-2">Request Endorsement</h3>
                <p class="text-slate-400 text-sm mb-4">Ask previous employers or colleagues to vouch for your skills.</p>
                <button onclick="openEndorsementRequest()" class="w-full py-3 rounded-xl bg-emerald-500 text-white font-bold text-sm hover:bg-emerald-400 transition">
                    <i class="fas fa-envelope mr-2"></i> Request Endorsement
                </button>
            </div>
            
            <!-- External Certificate -->
            <div class="verify-card p-6">
                <div class="flex items-start justify-between mb-4">
                    <div class="w-14 h-14 rounded-2xl bg-orange-500/10 flex items-center justify-center">
                        <i class="fas fa-external-link-alt text-2xl text-orange-400"></i>
                    </div>
                    <span class="px-3 py-1 rounded-full bg-amber-500/10 text-amber-400 text-xs font-bold">
                        <span class="mr-1"></span> <?= $pricing['ext_cert']['price_seeds'] ?? 75 ?>
                    </span>
                </div>
                <h3 class="text-white font-bold text-lg mb-2">External Certificate</h3>
                <p class="text-slate-400 text-sm mb-4">Submit certificates from other platforms (Coursera, Udemy, etc.) for review.</p>
                <button onclick="openExternalCert()" class="w-full py-3 rounded-xl bg-orange-500 text-black font-bold text-sm hover:bg-orange-400 transition">
                    <i class="fas fa-upload mr-2"></i> Submit Certificate
                </button>
            </div>
        </div>
        
        <!-- Existing Verifications -->
        <?php if (count($verifications) > 0): ?>
        <h2 class="text-white font-bold text-xl mb-4">Your Verified Credentials</h2>
        <div class="space-y-3 mb-8">
            <?php foreach ($verifications as $v): ?>
            <div class="verify-card <?= $v['status'] === 'verified' ? 'done' : '' ?> p-4 flex items-center gap-4">
                <div class="w-10 h-10 rounded-xl flex items-center justify-center <?php
                    echo match($v['verification_type']) {
                        'skill' => 'bg-blue-500/10 text-blue-400',
                        'certificate' => 'bg-purple-500/10 text-purple-400',
                        'employment' => 'bg-orange-500/10 text-orange-400',
                        'education' => 'bg-emerald-500/10 text-emerald-400',
                        default => 'bg-slate-500/10 text-slate-400'
                    };
                ?>">
                    <i class="fas <?php
                        echo match($v['verification_type']) {
                            'skill' => 'fa-robot',
                            'certificate' => 'fa-certificate',
                            'employment' => 'fa-briefcase',
                            'education' => 'fa-graduation-cap',
                            default => 'fa-check'
                        };
                    ?>"></i>
                </div>
                <div class="flex-1">
                    <span class="text-white font-bold"><?= e($v['reference_name']) ?></span>
                    <span class="text-slate-500 text-xs block">
                        <?= ucfirst(str_replace('_', ' ', $v['verification_type'])) ?> • 
                        <?= date('M j, Y', strtotime($v['created_at'])) ?>
                    </span>
                </div>
                <?php if ($v['status'] === 'verified'): ?>
                <span class="px-3 py-1 rounded-full bg-emerald-500/10 text-emerald-400 text-xs font-bold">
                    <i class="fas fa-check mr-1"></i> Verified
                </span>
                <?php elseif ($v['status'] === 'pending'): ?>
                <span class="px-3 py-1 rounded-full bg-amber-500/10 text-amber-400 text-xs font-bold">
                    <i class="fas fa-clock mr-1"></i> Pending
                </span>
                <?php else: ?>
                <span class="px-3 py-1 rounded-full bg-red-500/10 text-red-400 text-xs font-bold">
                    <i class="fas fa-times mr-1"></i> Failed
                </span>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
        <!-- Endorsements -->
        <?php if (count($endorsements) > 0): ?>
        <h2 class="text-white font-bold text-xl mb-4">Endorsements</h2>
        <div class="space-y-3 mb-8">
            <?php foreach ($endorsements as $e): ?>
            <div class="verify-card <?= $e['is_verified'] ? 'done' : '' ?> p-4">
                <div class="flex items-center gap-3 mb-2">
                    <?php if ($e['endorser_user_id']): ?>
                    <img src="<?= profileImage($e['profile_image']) ?>" class="w-10 h-10 rounded-full">
                    <div class="flex-1">
                        <span class="text-white font-bold"><?= e($e['full_name']) ?></span>
                        <span class="text-slate-500 text-xs block"><?= e($e['endorser_title']) ?></span>
                    </div>
                    <?php else: ?>
                    <div class="w-10 h-10 rounded-full bg-slate-700 flex items-center justify-center">
                        <i class="fas fa-user text-slate-500"></i>
                    </div>
                    <div class="flex-1">
                        <span class="text-white font-bold"><?= e($e['endorser_name']) ?></span>
                        <span class="text-slate-500 text-xs block"><?= e($e['endorser_title']) ?><?= !empty($e['worked_together_at']) ? ' at ' . e($e['worked_together_at']) : '' ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($e['is_verified']): ?>
                    <span class="px-2 py-1 rounded bg-emerald-500/10 text-emerald-400 text-[10px] font-bold">VERIFIED</span>
                    <?php else: ?>
                    <span class="px-2 py-1 rounded bg-amber-500/10 text-amber-400 text-[10px] font-bold">PENDING</span>
                    <?php endif; ?>
                </div>
                <p class="text-slate-400 text-sm">"<?= e(substr($e['endorsement_text'], 0, 200)) ?><?= strlen($e['endorsement_text']) > 200 ? '...' : '' ?>"</p>
                <div class="flex gap-2 mt-2">
                    <?php foreach (json_decode($e['skills_endorsed'] ?: '[]', true) as $skill): ?>
                    <span class="px-2 py-0.5 rounded bg-white/5 text-slate-400 text-[10px]"><?= e($skill) ?></span>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
    </div>
</div>

<!-- AI Skill Verification Modal -->
<dialog id="skillVerifyModal" class="bg-transparent backdrop:bg-black/90 backdrop:backdrop-blur-sm">
    <div class="bg-slate-900 border border-white/10 rounded-2xl w-full max-w-lg overflow-hidden">
        <div class="p-6 border-b border-white/5">
            <h2 class="text-xl font-bold text-white">AI Skill Assessment</h2>
            <p class="text-slate-400 text-sm">Select a skill to verify through our AI assessment</p>
        </div>
        
        <form id="skillVerifyForm" class="p-6">
            <div class="mb-4">
                <label class="text-sm text-slate-400 block mb-2">Select Skill</label>
                <div id="skillOptions" class="flex flex-wrap gap-2">
                    <?php 
                    $commonSkills = ['JavaScript', 'Python', 'React', 'Node.js', 'PHP', 'SQL', 'Excel', 'Marketing', 'Design', 'Writing', 'Leadership', 'Project Management'];
                    foreach ($commonSkills as $skill):
                    ?>
                    <span class="skill-pill" data-skill="<?= $skill ?>"><?= $skill ?></span>
                    <?php endforeach; ?>
                </div>
                <input type="hidden" name="skill" id="selectedSkill" required>
            </div>
            
            <div class="mb-4">
                <label class="text-sm text-slate-400 block mb-2">Or enter custom skill</label>
                <input type="text" name="custom_skill" placeholder="e.g. Data Analysis" 
                       class="w-full px-4 py-2 rounded-lg bg-white/5 border border-white/10 text-white focus:border-blue-500/50 focus:outline-none">
            </div>
            
            <div class="p-4 rounded-lg bg-amber-500/10 border border-amber-500/20 mb-6">
                <div class="flex items-center gap-2 text-amber-400 text-sm">
                    <span></span>
                    <span class="font-bold"><?= $pricing['skill_verify']['price_seeds'] ?? 100 ?> Seeds will be deducted</span>
                </div>
                <p class="text-slate-400 text-xs mt-1">You have <?= number_format($userSeeds) ?> Seeds available</p>
            </div>
            
            <div class="flex gap-3">
                <button type="button" onclick="document.getElementById('skillVerifyModal').close()" class="flex-1 py-3 rounded-xl bg-white/5 text-white font-bold hover:bg-white/10 transition">
                    Cancel
                </button>
                <button type="submit" class="flex-1 py-3 rounded-xl bg-blue-500 text-white font-bold hover:bg-blue-400 transition">
                    <i class="fas fa-play mr-2"></i> Start Assessment
                </button>
            </div>
        </form>
    </div>
</dialog>

<!-- Certificate Link Modal -->
<dialog id="certLinkModal" class="bg-transparent backdrop:bg-black/90 backdrop:backdrop-blur-sm">
    <div class="bg-slate-900 border border-white/10 rounded-2xl w-full max-w-lg overflow-hidden">
        <div class="p-6 border-b border-white/5">
            <h2 class="text-xl font-bold text-white">Link Certificate</h2>
            <p class="text-slate-400 text-sm">Connect a Jobmington certificate to your passport</p>
        </div>
        
        <form id="certLinkForm" class="p-6">
            <div class="mb-6 space-y-3">
                <?php foreach ($certificates as $cert): ?>
                <label class="verify-card p-4 flex items-center gap-3 cursor-pointer">
                    <input type="radio" name="certificate_id" value="<?= $cert['certificate_id'] ?>" class="hidden peer">
                    <div class="w-5 h-5 rounded-full border-2 border-slate-600 peer-checked:border-purple-500 peer-checked:bg-purple-500 flex items-center justify-center">
                        <i class="fas fa-check text-[10px] text-white hidden peer-checked:block"></i>
                    </div>
                    <div class="flex-1">
                        <span class="text-white font-bold block"><?= e($cert['course_name']) ?></span>
                        <span class="text-slate-500 text-xs"><?= date('M j, Y', strtotime($cert['issued_at'])) ?></span>
                    </div>
                    <span class="px-2 py-1 rounded bg-purple-500/10 text-purple-400 text-[10px] font-bold">
                        <?= $cert['certificate_number'] ?>
                    </span>
                </label>
                <?php endforeach; ?>
            </div>
            
            <div class="p-4 rounded-lg bg-amber-500/10 border border-amber-500/20 mb-6">
                <div class="flex items-center gap-2 text-amber-400 text-sm">
                    <span></span>
                    <span class="font-bold"><?= $pricing['cert_link']['price_seeds'] ?? 50 ?> Seeds will be deducted</span>
                </div>
            </div>
            
            <div class="flex gap-3">
                <button type="button" onclick="document.getElementById('certLinkModal').close()" class="flex-1 py-3 rounded-xl bg-white/5 text-white font-bold hover:bg-white/10 transition">
                    Cancel
                </button>
                <button type="submit" class="flex-1 py-3 rounded-xl bg-purple-500 text-white font-bold hover:bg-purple-400 transition">
                    <i class="fas fa-link mr-2"></i> Link Certificate
                </button>
            </div>
        </form>
    </div>
</dialog>

<!-- Endorsement Request Modal -->
<dialog id="endorsementModal" class="bg-transparent backdrop:bg-black/90 backdrop:backdrop-blur-sm">
    <div class="bg-slate-900 border border-white/10 rounded-2xl w-full max-w-lg overflow-hidden">
        <div class="p-6 border-b border-white/5">
            <h2 class="text-xl font-bold text-white">Request Endorsement</h2>
            <p class="text-slate-400 text-sm">Ask a colleague or employer to endorse your skills</p>
        </div>
        
        <form id="endorsementForm" class="p-6">
            <div class="grid grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="text-sm text-slate-400 block mb-2">Their Name</label>
                    <input type="text" name="endorser_name" required placeholder="John Smith" 
                           class="w-full px-4 py-2 rounded-lg bg-white/5 border border-white/10 text-white focus:border-emerald-500/50 focus:outline-none">
                </div>
                <div>
                    <label class="text-sm text-slate-400 block mb-2">Their Email</label>
                    <input type="email" name="endorser_email" required placeholder="john@company.com" 
                           class="w-full px-4 py-2 rounded-lg bg-white/5 border border-white/10 text-white focus:border-emerald-500/50 focus:outline-none">
                </div>
            </div>
            
            <div class="grid grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="text-sm text-slate-400 block mb-2">Their Title</label>
                    <input type="text" name="endorser_title" required placeholder="Engineering Manager" 
                           class="w-full px-4 py-2 rounded-lg bg-white/5 border border-white/10 text-white focus:border-emerald-500/50 focus:outline-none">
                </div>
                <div>
                    <label class="text-sm text-slate-400 block mb-2">Company</label>
                    <input type="text" name="endorser_company" required placeholder="TechCorp Inc" 
                           class="w-full px-4 py-2 rounded-lg bg-white/5 border border-white/10 text-white focus:border-emerald-500/50 focus:outline-none">
                </div>
            </div>
            
            <div class="mb-6">
                <label class="text-sm text-slate-400 block mb-2">Personal Message (optional)</label>
                <textarea name="message" rows="3" placeholder="Hi John, we worked together at..." 
                          class="w-full px-4 py-2 rounded-lg bg-white/5 border border-white/10 text-white focus:border-emerald-500/50 focus:outline-none resize-none"></textarea>
            </div>
            
            <div class="flex gap-3">
                <button type="button" onclick="document.getElementById('endorsementModal').close()" class="flex-1 py-3 rounded-xl bg-white/5 text-white font-bold hover:bg-white/10 transition">
                    Cancel
                </button>
                <button type="submit" class="flex-1 py-3 rounded-xl bg-emerald-500 text-white font-bold hover:bg-emerald-400 transition">
                    <i class="fas fa-envelope mr-2"></i> Send Request
                </button>
            </div>
        </form>
    </div>
</dialog>

<!-- External Certificate Modal -->
<dialog id="extCertModal" class="bg-transparent backdrop:bg-black/90 backdrop:backdrop-blur-sm">
    <div class="bg-slate-900 border border-white/10 rounded-2xl w-full max-w-lg overflow-hidden">
        <div class="p-6 border-b border-white/5">
            <h2 class="text-xl font-bold text-white">Submit External Certificate</h2>
            <p class="text-slate-400 text-sm">Add certificates from other learning platforms</p>
        </div>
        
        <form id="extCertForm" enctype="multipart/form-data" class="p-6">
            <div class="mb-4">
                <label class="text-sm text-slate-400 block mb-2">Certificate Name</label>
                <input type="text" name="cert_name" required placeholder="Google Analytics Certification" 
                       class="w-full px-4 py-2 rounded-lg bg-white/5 border border-white/10 text-white focus:border-orange-500/50 focus:outline-none">
            </div>
            
            <div class="mb-4">
                <label class="text-sm text-slate-400 block mb-2">Issuing Platform</label>
                <select name="platform" required class="w-full px-4 py-2 rounded-lg bg-white/5 border border-white/10 text-white focus:border-orange-500/50 focus:outline-none">
                    <option value="">Select platform...</option>
                    <option value="coursera">Coursera</option>
                    <option value="udemy">Udemy</option>
                    <option value="linkedin">LinkedIn Learning</option>
                    <option value="google">Google</option>
                    <option value="aws">AWS</option>
                    <option value="microsoft">Microsoft</option>
                    <option value="hubspot">HubSpot</option>
                    <option value="other">Other</option>
                </select>
            </div>
            
            <div class="mb-4">
                <label class="text-sm text-slate-400 block mb-2">Verification URL</label>
                <input type="url" name="cert_url" required placeholder="https://coursera.org/verify/..." 
                       class="w-full px-4 py-2 rounded-lg bg-white/5 border border-white/10 text-white focus:border-orange-500/50 focus:outline-none">
            </div>
            
            <div class="mb-6">
                <label class="text-sm text-slate-400 block mb-2">Upload Certificate (optional)</label>
                <input type="file" name="cert_file" accept=".pdf,.jpg,.jpeg,.png" 
                       class="w-full px-4 py-2 rounded-lg bg-white/5 border border-white/10 text-white file:mr-4 file:py-2 file:px-4 file:rounded file:border-0 file:bg-orange-500 file:text-black file:font-bold file:cursor-pointer">
            </div>
            
            <div class="p-4 rounded-lg bg-amber-500/10 border border-amber-500/20 mb-6">
                <div class="flex items-center gap-2 text-amber-400 text-sm">
                    <span></span>
                    <span class="font-bold"><?= $pricing['ext_cert']['price_seeds'] ?? 75 ?> Seeds will be deducted</span>
                </div>
                <p class="text-slate-400 text-xs mt-1">Review may take 24-48 hours</p>
            </div>
            
            <div class="flex gap-3">
                <button type="button" onclick="document.getElementById('extCertModal').close()" class="flex-1 py-3 rounded-xl bg-white/5 text-white font-bold hover:bg-white/10 transition">
                    Cancel
                </button>
                <button type="submit" class="flex-1 py-3 rounded-xl bg-orange-500 text-black font-bold hover:bg-orange-400 transition">
                    <i class="fas fa-upload mr-2"></i> Submit
                </button>
            </div>
        </form>
    </div>
</dialog>

<script>
// Skill selection
document.querySelectorAll('.skill-pill').forEach(pill => {
    pill.addEventListener('click', () => {
        document.querySelectorAll('.skill-pill').forEach(p => p.classList.remove('selected'));
        pill.classList.add('selected');
        document.getElementById('selectedSkill').value = pill.dataset.skill;
    });
});

// Modal openers
function openSkillVerify() {
    document.getElementById('skillVerifyModal').showModal();
}

function openCertLink() {
    document.getElementById('certLinkModal').showModal();
}

function openEndorsementRequest() {
    document.getElementById('endorsementModal').showModal();
}

function openExternalCert() {
    document.getElementById('extCertModal').showModal();
}

// Form handlers
document.getElementById('skillVerifyForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const skill = document.getElementById('selectedSkill').value || this.custom_skill.value;
    if (!skill) {
        alert('Please select or enter a skill');
        return;
    }
    
    // Redirect to assessment page
    window.location.href = '<?= SITE_URL ?>/wallet/passport/assessment.php?skill=' + encodeURIComponent(skill);
});

document.getElementById('certLinkForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    formData.append('action', 'link_certificate');
    
    fetch('<?= SITE_URL ?>/api/passport/verify.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            alert(' Certificate linked successfully!');
            location.reload();
        } else {
            alert(' ' + data.message);
        }
    });
});

document.getElementById('endorsementForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    formData.append('action', 'request_endorsement');
    
    fetch('<?= SITE_URL ?>/api/passport/verify.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            alert(' Endorsement request sent!');
            document.getElementById('endorsementModal').close();
            location.reload();
        } else {
            alert(' ' + data.message);
        }
    });
});

document.getElementById('extCertForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    formData.append('action', 'external_cert');
    
    fetch('<?= SITE_URL ?>/api/passport/verify.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            alert(' Certificate submitted for review!');
            document.getElementById('extCertModal').close();
            location.reload();
        } else {
            alert(' ' + data.message);
        }
    });
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
