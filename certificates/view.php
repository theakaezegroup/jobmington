<?php
/**
 * JOBMINGTON - View Certificate (premium, on-brand)
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
$certCode = Security::clean(get('code', ''));
if (empty($certCode)) redirect('/jobmington/certificates/');

$stmt = $pdo->prepare("
    SELECT cert.*, c.title AS course_title, u.full_name, u.email
    FROM certificates cert
    JOIN courses c ON cert.course_id = c.course_id
    LEFT JOIN users u ON cert.user_id = u.user_id
    WHERE cert.cert_code = ? LIMIT 1
");
$stmt->execute([$certCode]);
$cert = $stmt->fetch();

if (!$cert) {
    http_response_code(404);
    $pageTitle = 'Certificate not found - ' . SITE_NAME;
    $activeAIPage = 'learn';
    require_once __DIR__ . '/../includes/ai-header.php';
    echo '<div style="max-width:620px;margin:80px auto;text-align:center;padding:0 20px;"><h1 style="color:#061426;">Certificate not found</h1><p style="color:#53667f;">This certificate ID could not be verified. <a href="/jobmington/verify" style="color:#0640a3;">Verify a certificate</a></p></div>';
    require_once __DIR__ . '/../includes/ai-footer.php';
    exit;
}

$isOwner   = Session::isLoggedIn() && (int) Session::userId() === (int) $cert['user_id'];
$isPremium = !empty($cert['is_premium']);

$verifyUrl = SITE_URL . '/verify?code=' . urlencode($cert['cert_code']);
$pageTitle = 'Certificate — ' . $cert['course_title'] . ' - ' . SITE_NAME;
$activeAIPage = 'learn';
require_once __DIR__ . '/../includes/ai-header.php';
?>
<style>
.jm-certpage { max-width:1040px; margin:0 auto; padding:30px 20px 72px; }
.jm-certpage-head { text-align:center; margin-bottom:24px; }
.jm-certpage-head h1 { font-size:clamp(22px,3.5vw,30px); font-weight:800; color:#061426; margin:0 0 6px; }
.jm-certpage-head p { font-size:14px; color:#53667f; margin:0; }
.jm-certpage-verified { display:inline-flex; align-items:center; gap:7px; background:#e6f5f1; color:#0a6454; font-size:12.5px; font-weight:800; padding:6px 13px; border-radius:99px; margin-top:12px; }
.jm-certpage-actions { display:flex; gap:10px; justify-content:center; flex-wrap:wrap; margin-top:26px; }
.jm-cp-btn { display:inline-flex; align-items:center; gap:8px; border-radius:10px; padding:12px 22px; font-weight:800; font-size:14px; text-decoration:none; cursor:pointer; border:1px solid #d8e4f4; }
.jm-cp-btn.primary { background:#0640a3; color:#fff; border-color:#0640a3; } .jm-cp-btn.primary:hover { background:#052f78; }
.jm-cp-btn.ghost { background:#fff; color:#0640a3; }
.jm-cp-btn.ghost:hover { background:#eef5ff; }
</style>

<div class="jm-certpage">
    <?= jm_breadcrumbs([['label' => 'Certificate']]) ?>
    <div class="jm-certpage-head">
        <h1><?= e($cert['full_name'] ?: 'Learner') ?>’s certificate</h1>
        <p><?= e($cert['course_title']) ?></p>
        <div><span class="jm-certpage-verified">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><polyline points="9 12 11 14 15 10"/></svg>
            Authentic &amp; verified
        </span>
        <?php if ($isPremium): ?>
        <span class="jm-certpage-verified" style="background:#eef3ff;color:#0640a3;margin-left:8px;">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15 9 22 9 16.5 13.5 18.5 21 12 16.5 5.5 21 7.5 13.5 2 9 9 9"/></svg>
            Premium
        </span>
        <?php endif; ?>
        </div>
    </div>

    <?php require __DIR__ . '/_cert_template.php'; ?>

    <div class="jm-certpage-actions">
        <a class="jm-cp-btn primary" href="/jobmington/certificates/download.php?code=<?= e($cert['cert_code']) ?>" target="_blank">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
            Download / Print
        </a>
        <a class="jm-cp-btn ghost" href="<?= e($verifyUrl) ?>" target="_blank">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
            Verify page
        </a>
        <button class="jm-cp-btn ghost" type="button" onclick="navigator.clipboard.writeText('<?= e($verifyUrl) ?>').then(function(){if(window.JM&&JM.toast)JM.toast('Verification link copied','success');else alert('Link copied');})">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.5.5l3-3a5 5 0 0 0-7-7l-1.5 1.5"/><path d="M14 11a5 5 0 0 0-7.5-.5l-3 3a5 5 0 0 0 7 7l1.5-1.5"/></svg>
            Share link
        </button>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/ai-footer.php'; ?>
