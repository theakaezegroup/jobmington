<?php
/**
 * JOBMINGTON - Talent Passport View (clean, on-brand)
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

$passportNumber = $_GET['id'] ?? null;
$isOwner = false;
$passport = null;

$selectCols = "tp.*, u.user_id, u.full_name, u.profile_image, u.email, u.created_at as member_since,
               cv.headline, cv.summary, cv.city";

if ($passportNumber) {
    $stmt = $pdo->prepare("SELECT $selectCols FROM talent_passports tp
        JOIN users u ON tp.user_id = u.user_id
        LEFT JOIN cv_profiles cv ON u.user_id = cv.user_id
        WHERE tp.passport_number = ? AND tp.is_public = 1");
    $stmt->execute([$passportNumber]);
    $passport = $stmt->fetch();
    if ($passport && Session::isLoggedIn() && Session::get('user_id') == $passport['user_id']) $isOwner = true;
} elseif (Session::isLoggedIn()) {
    $userId = (int) Session::get('user_id');
    $ownStmt = "SELECT $selectCols FROM talent_passports tp
        JOIN users u ON tp.user_id = u.user_id
        LEFT JOIN cv_profiles cv ON u.user_id = cv.user_id
        WHERE tp.user_id = ?";
    $stmt = $pdo->prepare($ownStmt);
    $stmt->execute([$userId]);
    $passport = $stmt->fetch();

    if (!$passport) {
        try {
            for ($try = 0; $try < 5; $try++) {
                $passportNumber = 'JM' . str_pad((string) $userId, 6, '0', STR_PAD_LEFT) . strtoupper(bin2hex(random_bytes(2)));
                $chk = $pdo->prepare("SELECT 1 FROM talent_passports WHERE passport_number = ? LIMIT 1");
                $chk->execute([$passportNumber]);
                if (!$chk->fetchColumn()) break;
            }
            $pdo->prepare("INSERT INTO talent_passports (user_id, passport_number, first_featured_at, last_featured_at)
                VALUES (?, ?, NOW(), NOW())")->execute([$userId, $passportNumber]);
            $stmt = $pdo->prepare($ownStmt);
            $stmt->execute([$userId]);
            $passport = $stmt->fetch();
        } catch (Throwable $e) {
            error_log('Passport creation failed: ' . $e->getMessage());
        }
    }
    $isOwner = true;
}

$verifications = $endorsements = []; $viewStats = null;
if ($passport) {
    $stmt = $pdo->prepare("SELECT * FROM passport_verifications WHERE passport_id = ? AND status = 'verified' ORDER BY verified_at DESC");
    $stmt->execute([$passport['passport_id']]);
    $verifications = $stmt->fetchAll();

    $stmt = $pdo->prepare("SELECT * FROM passport_endorsements WHERE passport_id = ? AND is_verified = 1 AND is_public = 1 ORDER BY created_at DESC LIMIT 6");
    $stmt->execute([$passport['passport_id']]);
    $endorsements = $stmt->fetchAll();

    if ($isOwner) {
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) total_views, COUNT(DISTINCT viewer_company_id) unique_companies,
                COUNT(CASE WHEN viewed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) week_views
                FROM passport_views WHERE passport_id = ?");
            $stmt->execute([$passport['passport_id']]);
            $viewStats = $stmt->fetch();
        } catch (Throwable $e) {}
    }
    if (!$isOwner) {
        try {
            $pdo->prepare("INSERT INTO passport_views (passport_id, viewer_user_id, source, ip_address) VALUES (?, ?, 'direct', ?)")
                ->execute([$passport['passport_id'], Session::isLoggedIn() ? Session::get('user_id') : null, $_SERVER['REMOTE_ADDR'] ?? null]);
        } catch (Throwable $e) {}
    }
}

$levels = [
    'rising'   => ['name' => 'Rising',   'color' => '#0a6454', 'bg' => '#e6f5f1', 'next' => 'verified', 'points' => 100],
    'verified' => ['name' => 'Verified', 'color' => '#0640a3', 'bg' => '#eef3ff', 'next' => 'expert',   'points' => 300],
    'expert'   => ['name' => 'Expert',   'color' => '#5b3fc0', 'bg' => '#efeaff', 'next' => 'elite',    'points' => 1000],
    'elite'    => ['name' => 'Elite',    'color' => '#b9821a', 'bg' => '#fdf3e0', 'next' => null,       'points' => null],
];
$lvl = $passport['level'] ?? 'rising';
if (!isset($levels[$lvl])) $lvl = 'rising';

$pageTitle = $passport ? e($passport['full_name']) . "'s Talent Passport" : 'Talent Passport';
require_once __DIR__ . '/../../includes/header.php';

$shieldSvg = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>';
?>
<style>
.jm-pp { max-width:1040px; margin:0 auto; padding:24px 20px 72px; }
.jm-pp-back { display:inline-flex; align-items:center; gap:7px; font-size:13px; font-weight:700; color:#53667f; text-decoration:none; margin-bottom:18px; }
.jm-pp-back:hover { color:#0640a3; }
.jm-pp-grid { display:grid; grid-template-columns:340px 1fr; gap:22px; align-items:start; }
@media (max-width:840px){ .jm-pp-grid { grid-template-columns:1fr; } }

.jm-pp-card { background:#fff; border:1px solid #e8edf5; border-radius:18px; padding:24px; text-align:center; position:sticky; top:96px; }
.jm-pp-card .top { display:flex; align-items:center; justify-content:space-between; text-align:left; margin-bottom:18px; }
.jm-pp-card .top small { font-size:10px; font-weight:800; letter-spacing:.14em; text-transform:uppercase; color:#94a3b8; display:block; }
.jm-pp-card .top code { font-family:"Courier New",monospace; font-weight:800; color:#0640a3; font-size:13px; }
.jm-pp-badge { display:inline-flex; align-items:center; gap:5px; padding:5px 11px; border-radius:99px; font-size:11px; font-weight:800; text-transform:uppercase; letter-spacing:.05em; }
.jm-pp-av { width:120px; height:120px; border-radius:50%; object-fit:cover; border:4px solid #eef3fb; margin:0 auto; display:block; }
.jm-pp-name { font-size:19px; font-weight:800; color:#061426; margin:14px 0 2px; }
.jm-pp-head { font-size:13px; color:#53667f; margin:0; }
.jm-pp-loc { font-size:12px; color:#94a3b8; margin:4px 0 0; }
.jm-pp-stats { display:grid; grid-template-columns:1fr 1fr; gap:10px; margin:18px 0; }
.jm-pp-stat { background:#f7faff; border:1px solid #eef3fb; border-radius:11px; padding:11px; }
.jm-pp-stat b { display:block; font-size:20px; font-weight:800; color:#061426; }
.jm-pp-stat span { font-size:10px; color:#7c8aa0; text-transform:uppercase; letter-spacing:.06em; }
.jm-pp-actions { display:flex; flex-direction:column; gap:8px; margin-top:6px; }
.jm-pp-btn { display:inline-flex; align-items:center; justify-content:center; gap:7px; border-radius:10px; padding:11px; font-size:13px; font-weight:800; text-decoration:none; cursor:pointer; border:1px solid #d8e4f4; }
.jm-pp-btn.primary { background:#0640a3; color:#fff; border-color:#0640a3; } .jm-pp-btn.primary:hover { background:#052f78; }
.jm-pp-btn.ghost { background:#fff; color:#0640a3; } .jm-pp-btn.ghost:hover { background:#eef5ff; }
.jm-pp-since { font-size:11px; color:#94a3b8; margin-top:14px; }

.jm-pp-panel { background:#fff; border:1px solid #e8edf5; border-radius:16px; padding:20px; margin-bottom:16px; }
.jm-pp-panel h3 { font-size:15px; font-weight:800; color:#061426; margin:0 0 14px; display:flex; align-items:center; justify-content:space-between; }
.jm-pp-panel h3 a { font-size:12.5px; font-weight:700; color:#0640a3; text-decoration:none; }
.jm-pp-analytics { display:grid; grid-template-columns:repeat(3,1fr); gap:12px; }
.jm-pp-an { text-align:center; } .jm-pp-an b { display:block; font-size:24px; font-weight:800; color:#0640a3; } .jm-pp-an span { font-size:11.5px; color:#7c8aa0; }
.jm-pp-prog { display:flex; align-items:center; gap:14px; }
.jm-pp-prog .lab { font-size:12px; font-weight:800; text-transform:uppercase; letter-spacing:.05em; }
.jm-pp-bar { flex:1; height:10px; background:#eef3fb; border-radius:99px; overflow:hidden; }
.jm-pp-bar span { display:block; height:100%; background:linear-gradient(90deg,#0640a3,#3f6fc0); }
.jm-pp-item { display:flex; align-items:center; gap:12px; padding:11px 0; border-bottom:1px solid #f1f5fb; }
.jm-pp-item:last-child { border-bottom:0; }
.jm-pp-item .ic { width:36px; height:36px; border-radius:10px; background:#e6f5f1; color:#0a6454; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.jm-pp-item b { display:block; font-size:13.5px; color:#061426; font-weight:700; } .jm-pp-item span { font-size:11.5px; color:#94a3b8; }
.jm-pp-endo { background:#f7faff; border:1px solid #eef3fb; border-radius:12px; padding:14px; margin-bottom:10px; }
.jm-pp-endo .who { display:flex; align-items:center; gap:10px; margin-bottom:8px; }
.jm-pp-endo .ava { width:38px; height:38px; border-radius:50%; background:#0640a3; color:#fff; display:flex; align-items:center; justify-content:center; font-weight:800; }
.jm-pp-endo .who b { font-size:13.5px; color:#061426; } .jm-pp-endo .who span { font-size:11.5px; color:#94a3b8; }
.jm-pp-endo p { font-size:13px; color:#3a4a5f; font-style:italic; margin:0; }
.jm-pp-empty { text-align:center; padding:26px 10px; color:#94a3b8; font-size:13px; }
.jm-pp-blank { max-width:560px; margin:60px auto; text-align:center; }
.jm-pp-blank .ic { width:84px; height:84px; border-radius:50%; background:#eef3ff; color:#0640a3; display:flex; align-items:center; justify-content:center; margin:0 auto 18px; }
.jm-pp-blank h1 { font-size:24px; font-weight:800; color:#061426; margin:0 0 10px; }
.jm-pp-blank p { font-size:14px; color:#53667f; margin:0 auto 22px; }
</style>

<div class="jm-pp">
<?php if (!$passport): ?>
    <div class="jm-pp-blank">
        <div class="ic"><svg width="38" height="38" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="M9 12l2 2 4-4"/></svg></div>
        <h1>No Talent Passport yet</h1>
        <p>Sign in to claim your verified Talent Passport — showcase your skills, certificates and endorsements to employers.</p>
        <a class="jm-pp-btn primary" style="max-width:220px;margin:0 auto;" href="/jobmington/auth/login.php?redirect=<?= urlencode('/wallet/passport/') ?>">Sign in</a>
    </div>
<?php else: $L = $levels[$lvl]; ?>
    <a class="jm-pp-back" href="/jobmington/wallet/"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M19 12H5M11 6l-6 6 6 6"/></svg> Back to wallet</a>

    <div class="jm-pp-grid">
        <!-- Card -->
        <div class="jm-pp-card">
            <div class="top">
                <div><small>Talent Passport</small><code><?= e($passport['passport_number']) ?></code></div>
                <span class="jm-pp-badge" style="background:<?= $L['bg'] ?>;color:<?= $L['color'] ?>;"><?= $shieldSvg ?> <?= $L['name'] ?></span>
            </div>
            <img class="jm-pp-av" src="<?= profileImage($passport['profile_image']) ?>" alt="<?= e($passport['full_name']) ?>">
            <div class="jm-pp-name"><?= e($passport['full_name']) ?></div>
            <?php if (!empty($passport['headline'])): ?><p class="jm-pp-head"><?= e($passport['headline']) ?></p><?php endif; ?>
            <?php if (!empty($passport['city'])): ?><p class="jm-pp-loc"><?= e($passport['city']) ?></p><?php endif; ?>

            <div class="jm-pp-stats">
                <div class="jm-pp-stat"><b><?= (int)$passport['times_featured'] ?></b><span>Featured</span></div>
                <div class="jm-pp-stat"><b><?= (int)$passport['skills_verified'] ?></b><span>Verified</span></div>
                <div class="jm-pp-stat"><b><?= (int)$passport['endorsements_count'] ?></b><span>Endorsed</span></div>
                <div class="jm-pp-stat"><b><?= (int)$passport['successful_hires'] ?></b><span>Hired</span></div>
            </div>

            <?php if ($isOwner): ?>
            <div class="jm-pp-actions">
                <a class="jm-pp-btn primary" href="/jobmington/wallet/passport/verify.php"><?= $shieldSvg ?> Verify a skill</a>
                <a class="jm-pp-btn ghost" href="/jobmington/wallet/passport/boost.php"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M4.5 16.5 3 21l4.5-1.5"/><path d="M14 6l4 4"/><path d="M21 3s-3 1-6 4-6 9-6 9 6-3 9-6 3-7 3-7z"/></svg> Boost visibility</a>
                <button class="jm-pp-btn ghost" type="button" onclick="jmSharePassport()"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.6" y1="13.5" x2="15.4" y2="17.5"/><line x1="15.4" y1="6.5" x2="8.6" y2="10.5"/></svg> Share passport</button>
            </div>
            <?php endif; ?>
            <div class="jm-pp-since">Member since <?= !empty($passport['member_since']) ? date('M Y', strtotime($passport['member_since'])) : '—' ?></div>
        </div>

        <!-- Details -->
        <div>
            <?php if ($isOwner && $viewStats): ?>
            <div class="jm-pp-panel">
                <h3>Passport analytics</h3>
                <div class="jm-pp-analytics">
                    <div class="jm-pp-an"><b><?= number_format((int)$viewStats['total_views']) ?></b><span>Total views</span></div>
                    <div class="jm-pp-an"><b><?= number_format((int)$viewStats['unique_companies']) ?></b><span>Companies</span></div>
                    <div class="jm-pp-an"><b><?= number_format((int)$viewStats['week_views']) ?></b><span>This week</span></div>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($isOwner && $L['next']): $pts = (int)$passport['level_points']; $need = (int)$L['points']; $prog = $need ? min(100, $pts / $need * 100) : 0; ?>
            <div class="jm-pp-panel">
                <h3>Level progress</h3>
                <div class="jm-pp-prog">
                    <span class="lab" style="color:<?= $L['color'] ?>;"><?= $L['name'] ?></span>
                    <div class="jm-pp-bar"><span style="width:<?= $prog ?>%;"></span></div>
                    <span class="lab" style="color:<?= $levels[$L['next']]['color'] ?>;"><?= $levels[$L['next']]['name'] ?></span>
                </div>
                <p style="font-size:12px;color:#7c8aa0;margin:12px 0 0;"><?= $pts ?> / <?= $need ?> points — earn more by getting featured, verifying skills, receiving endorsements, and getting hired.</p>
            </div>
            <?php endif; ?>

            <div class="jm-pp-panel">
                <h3>Verified credentials <?php if ($isOwner): ?><a href="/jobmington/wallet/passport/verify.php">+ Add</a><?php endif; ?></h3>
                <?php if (empty($verifications)): ?>
                    <div class="jm-pp-empty">No verified credentials yet.<?php if ($isOwner): ?><br><a href="/jobmington/wallet/passport/verify.php" style="color:#0640a3;">Verify your first skill →</a><?php endif; ?></div>
                <?php else: foreach ($verifications as $v): ?>
                    <div class="jm-pp-item">
                        <span class="ic"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></span>
                        <div><b><?= e($v['reference_name']) ?></b><span>Verified by <?= e(ucfirst((string)$v['verified_by'])) ?> &middot; <?= e(timeAgo($v['verified_at'])) ?></span></div>
                    </div>
                <?php endforeach; endif; ?>
            </div>

            <div class="jm-pp-panel">
                <h3>Endorsements</h3>
                <?php if (empty($endorsements)): ?>
                    <div class="jm-pp-empty">No endorsements yet.<?php if ($isOwner): ?><br>Share your passport so colleagues can endorse you.<?php endif; ?></div>
                <?php else: foreach ($endorsements as $en): ?>
                    <div class="jm-pp-endo">
                        <div class="who">
                            <div class="ava"><?= strtoupper(substr((string)$en['endorser_name'], 0, 1)) ?></div>
                            <div><b><?= e($en['endorser_name']) ?></b><span><?= e($en['endorser_title']) ?><?= !empty($en['relationship']) ? ' &middot; ' . e($en['relationship']) : '' ?></span></div>
                        </div>
                        <p>&ldquo;<?= e($en['endorsement_text']) ?>&rdquo;</p>
                    </div>
                <?php endforeach; endif; ?>
            </div>

            <?php if (!$isOwner && Session::isLoggedIn() && Session::isEmployer()): ?>
            <div class="jm-pp-panel" style="background:#f7faff;text-align:center;">
                <h3 style="justify-content:center;">Interested in this talent?</h3>
                <p style="font-size:13px;color:#53667f;margin:0 0 14px;">Reach <?= e($passport['full_name']) ?> directly through Jobmington.</p>
                <a class="jm-pp-btn primary" style="max-width:240px;margin:0 auto;" href="/jobmington/employer/contact-talent.php?passport=<?= (int)$passport['passport_id'] ?>">Contact talent</a>
            </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>
</div>

<?php if ($passport): ?>
<script>
function jmSharePassport(){
    var url = '<?= SITE_URL ?>/wallet/passport/?id=<?= e($passport['passport_number']) ?>';
    if (navigator.share) { navigator.share({ title: 'My Talent Passport', text: 'My verified Talent Passport on Jobmington', url: url }); }
    else { navigator.clipboard.writeText(url).then(function(){ if(window.JM&&JM.toast)JM.toast('Passport link copied','success'); else alert('Link copied'); }); }
}
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
