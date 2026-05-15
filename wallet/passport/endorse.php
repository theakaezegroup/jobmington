<?php
/**
 * JOBMINGTON - Endorsement Submission
 * 
 * External page for endorsers to submit endorsements via token.
 */

define('JOBMINGTON', true);

require_once __DIR__ . '/../../config/env.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../includes/functions.php';

$pdo = db();

$token = trim($_GET['token'] ?? '');

if (!$token || strlen($token) !== 64) {
    header('Location: ' . SITE_URL . '/?error=invalid_token');
    exit;
}

// Get endorsement request
$stmt = $pdo->prepare("
    SELECT pe.*, tp.user_id, tp.passport_number, tp.level, u.full_name, u.profile_image
    FROM passport_endorsements pe
    JOIN talent_passports tp ON pe.passport_id = tp.passport_id
    JOIN users u ON tp.user_id = u.user_id
    WHERE pe.token = ? AND pe.is_verified = 0
");
$stmt->execute([$token]);
$endorsement = $stmt->fetch();

if (!$endorsement) {
    header('Location: ' . SITE_URL . '/?error=invalid_or_used_token');
    exit;
}

// Check if expired (30 days)
$created = strtotime($endorsement['created_at']);
if (time() - $created > 30 * 24 * 60 * 60) {
    header('Location: ' . SITE_URL . '/?error=token_expired');
    exit;
}

$error = '';
$success = false;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $endorsementText = trim($_POST['endorsement'] ?? '');
    $skills = array_filter(array_map('trim', explode(',', $_POST['skills'] ?? '')));
    $relationship = trim($_POST['relationship'] ?? '');
    $duration = trim($_POST['duration'] ?? '');
    
    if (strlen($endorsementText) < 50) {
        $error = 'Endorsement must be at least 50 characters';
    } elseif (empty($skills)) {
        $error = 'Please specify at least one skill you\'re endorsing';
    } elseif (!$relationship) {
        $error = 'Please specify your relationship';
    } else {
        try {
            $pdo->beginTransaction();
            
            // Update endorsement
            $stmt = $pdo->prepare("
                UPDATE passport_endorsements 
                SET endorsement_text = ?,
                    skills_endorsed = ?,
                    relationship = ?,
                    work_duration = ?,
                    is_verified = 1,
                    verified_at = NOW()
                WHERE endorsement_id = ?
            ");
            $stmt->execute([
                $endorsementText,
                json_encode($skills),
                $relationship,
                $duration,
                $endorsement['endorsement_id']
            ]);
            
            // Check if passport should be upgraded
            // Count endorsements
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM passport_endorsements 
                WHERE passport_id = ? AND is_verified = 1
            ");
            $stmt->execute([$endorsement['passport_id']]);
            $endorsementCount = $stmt->fetchColumn();
            
            // Get current level
            $stmt = $pdo->prepare("SELECT level FROM talent_passports WHERE passport_id = ?");
            $stmt->execute([$endorsement['passport_id']]);
            $currentLevel = $stmt->fetchColumn();
            
            // Upgrade to expert if 2+ endorsements and currently verified or lower
            if ($endorsementCount >= 2 && in_array($currentLevel, ['rising', 'verified'])) {
                $stmt = $pdo->prepare("
                    UPDATE talent_passports 
                    SET level = 'expert', updated_at = NOW()
                    WHERE passport_id = ?
                ");
                $stmt->execute([$endorsement['passport_id']]);
                
                // Notify user
                $stmt = $pdo->prepare("
                    INSERT INTO notifications (user_id, type, title, message, link, created_at)
                    VALUES (?, 'passport_upgrade', ' Upgraded to Expert!', 'Your Talent Passport has been upgraded to Expert level based on employer endorsements.', '/wallet/passport/', NOW())
                ");
                $stmt->execute([$endorsement['user_id']]);
            }
            
            // Notify user of new endorsement
            $stmt = $pdo->prepare("
                INSERT INTO notifications (user_id, type, title, message, link, created_at)
                VALUES (?, 'endorsement_received', ?, ?, '/wallet/passport/', NOW())
            ");
            $stmt->execute([
                $endorsement['user_id'],
                "New endorsement from {$endorsement['endorser_name']}",
                substr($endorsementText, 0, 100) . '...'
            ]);
            
            $pdo->commit();
            $success = true;
            
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Endorsement submission error: " . $e->getMessage());
            $error = 'Failed to submit endorsement. Please try again.';
        }
    }
}

$levelConfig = [
    'rising' => ['icon' => '', 'name' => 'Rising'],
    'verified' => ['icon' => '', 'name' => 'Verified'],
    'expert' => ['icon' => '', 'name' => 'Expert'],
    'elite' => ['icon' => '', 'name' => 'Elite']
];
$level = $levelConfig[$endorsement['level']];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Write Endorsement - Jobmington</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-slate-950 min-h-screen">
    
    <div class="max-w-lg mx-auto px-4 py-12">
        
        <!-- Logo -->
        <div class="text-center mb-8">
            <a href="<?= SITE_URL ?>">
                <img src="<?= SITE_URL ?>/assets/images/logo.png" alt="Jobmington" class="h-8 mx-auto">
            </a>
        </div>
        
        <?php if ($success): ?>
        <!-- Success State -->
        <div class="bg-slate-900 border border-white/10 rounded-2xl p-8 text-center">
            <div class="w-20 h-20 mx-auto mb-6 rounded-full bg-emerald-500/20 flex items-center justify-center">
                <i class="fas fa-check text-3xl text-emerald-400"></i>
            </div>
            <h1 class="text-2xl font-bold text-white mb-2">Thank You!</h1>
            <p class="text-slate-400 mb-6">Your endorsement has been submitted and is now visible on <?= e($endorsement['full_name']) ?>'s Talent Passport.</p>
            <a href="<?= SITE_URL ?>" class="inline-block px-6 py-3 rounded-xl bg-amber-500 text-black font-bold hover:bg-amber-400 transition">
                Explore Jobmington
            </a>
        </div>
        <?php else: ?>
        <!-- Endorsement Form -->
        <div class="bg-slate-900 border border-white/10 rounded-2xl overflow-hidden">
            
            <!-- Header -->
            <div class="p-6 border-b border-white/5 text-center">
                <h1 class="text-xl font-bold text-white mb-1">Write Endorsement</h1>
                <p class="text-slate-400 text-sm">for <?= e($endorsement['full_name']) ?>'s Talent Passport</p>
            </div>
            
            <!-- User Card -->
            <div class="p-6 bg-slate-800/50 border-b border-white/5">
                <div class="flex items-center gap-4">
                    <img src="<?= profileImage($endorsement['profile_image']) ?>" 
                         alt="<?= e($endorsement['full_name']) ?>"
                         class="w-16 h-16 rounded-full border-2 border-slate-600">
                    <div>
                        <span class="text-white font-bold text-lg block"><?= e($endorsement['full_name']) ?></span>
                        <span class="text-slate-400 text-sm"><?= $level['icon'] ?> <?= $level['name'] ?> Talent Passport</span>
                    </div>
                </div>
            </div>
            
            <!-- Form -->
            <form method="POST" class="p-6">
                
                <?php if ($error): ?>
                <div class="mb-6 p-4 rounded-lg bg-red-500/10 border border-red-500/20 text-red-400 text-sm">
                    <i class="fas fa-exclamation-circle mr-2"></i><?= e($error) ?>
                </div>
                <?php endif; ?>
                
                <div class="mb-5">
                    <label class="text-sm text-slate-400 block mb-2">Your Relationship *</label>
                    <select name="relationship" required class="w-full px-4 py-3 rounded-lg bg-white/5 border border-white/10 text-white focus:border-amber-500/50 focus:outline-none">
                        <option value="">Select...</option>
                        <option value="manager">Direct Manager</option>
                        <option value="colleague">Colleague</option>
                        <option value="client">Client</option>
                        <option value="mentor">Mentor</option>
                        <option value="report">Direct Report</option>
                        <option value="other">Other Professional</option>
                    </select>
                </div>
                
                <div class="mb-5">
                    <label class="text-sm text-slate-400 block mb-2">How long did you work together? *</label>
                    <select name="duration" required class="w-full px-4 py-3 rounded-lg bg-white/5 border border-white/10 text-white focus:border-amber-500/50 focus:outline-none">
                        <option value="">Select...</option>
                        <option value="<6mo">Less than 6 months</option>
                        <option value="6mo-1yr">6 months - 1 year</option>
                        <option value="1-2yr">1-2 years</option>
                        <option value="2-5yr">2-5 years</option>
                        <option value="5yr+">5+ years</option>
                    </select>
                </div>
                
                <div class="mb-5">
                    <label class="text-sm text-slate-400 block mb-2">Skills you're endorsing * <span class="text-slate-600">(comma separated)</span></label>
                    <input type="text" name="skills" required placeholder="e.g. Leadership, Python, Project Management" 
                           class="w-full px-4 py-3 rounded-lg bg-white/5 border border-white/10 text-white focus:border-amber-500/50 focus:outline-none">
                </div>
                
                <div class="mb-6">
                    <label class="text-sm text-slate-400 block mb-2">Your Endorsement * <span class="text-slate-600">(min 50 characters)</span></label>
                    <textarea name="endorsement" rows="5" required minlength="50" 
                              placeholder="Write about your experience working with <?= e($endorsement['full_name']) ?>. What made them stand out? What skills did they demonstrate?"
                              class="w-full px-4 py-3 rounded-lg bg-white/5 border border-white/10 text-white focus:border-amber-500/50 focus:outline-none resize-none"></textarea>
                    <p class="text-xs text-slate-500 mt-1">This endorsement will be publicly visible on their Talent Passport</p>
                </div>
                
                <button type="submit" class="w-full py-4 rounded-xl bg-amber-500 text-black font-bold text-lg hover:bg-amber-400 transition">
                    <i class="fas fa-check mr-2"></i> Submit Endorsement
                </button>
                
            </form>
            
        </div>
        
        <!-- Info -->
        <p class="text-center text-slate-500 text-xs mt-6">
            <i class="fas fa-shield-alt mr-1"></i> Your email will not be shared publicly
        </p>
        <?php endif; ?>
        
    </div>
    
</body>
</html>
