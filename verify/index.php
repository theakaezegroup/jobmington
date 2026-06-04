<?php
/**
 * JOBMINGTON - Public certificate verification (scan QR or enter ID)
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

$certCode = Security::clean(get('code', ''));
$certificate = null;
$checked = false;

if ($certCode !== '') {
    $checked = true;
    try {
        $stmt = $pdo->prepare("
            SELECT cert.cert_code, cert.issued_at, c.title AS course_title, u.full_name
            FROM certificates cert
            JOIN courses c ON cert.course_id = c.course_id
            LEFT JOIN users u ON cert.user_id = u.user_id
            WHERE cert.cert_code = ? LIMIT 1
        ");
        $stmt->execute([$certCode]);
        $certificate = $stmt->fetch() ?: null;
    } catch (Throwable $e) {
        error_log('Certificate verify error: ' . $e->getMessage());
    }
}

$pageTitle = 'Verify a Certificate - ' . SITE_NAME;
$activeAIPage = 'learn';
require_once __DIR__ . '/../includes/ai-header.php';
?>
<style>
.jm-vf { max-width:640px; margin:0 auto; padding:40px 20px 72px; }
.jm-vf-head { text-align:center; margin-bottom:28px; }
.jm-vf-head h1 { font-size:clamp(26px,4vw,36px); font-weight:800; letter-spacing:-.02em; color:#061426; margin:0 0 10px; }
.jm-vf-head p { font-size:15px; color:#53667f; margin:0; }
.jm-vf-form { display:flex; gap:10px; margin-bottom:26px; }
.jm-vf-form input { flex:1; border:1px solid #d8e4f4; border-radius:10px; padding:13px 15px; font:inherit; font-size:14px; background:#fbfdff; }
.jm-vf-form input:focus { outline:none; border-color:#0640a3; box-shadow:0 0 0 3px rgba(6,64,163,.08); }
.jm-vf-form button { background:#0640a3; color:#fff; border:0; border-radius:10px; padding:0 24px; font-weight:800; font-size:14px; cursor:pointer; }
.jm-vf-result { border:1px solid #e4eaf3; border-radius:16px; background:#fff; overflow:hidden; box-shadow:0 8px 24px rgba(6,20,38,.06); }
.jm-vf-banner { padding:24px; text-align:center; color:#fff; }
.jm-vf-banner.ok { background:linear-gradient(135deg,#0f766e,#0a5d56); }
.jm-vf-banner.no { background:linear-gradient(135deg,#b42318,#8a1a12); }
.jm-vf-banner .ic { width:54px; height:54px; border-radius:50%; background:rgba(255,255,255,.18); display:grid; place-items:center; margin:0 auto 12px; }
.jm-vf-banner h2 { font-size:22px; font-weight:800; margin:0 0 4px; }
.jm-vf-banner p { font-size:13.5px; margin:0; opacity:.92; }
.jm-vf-body { padding:24px; }
.jm-vf-row { display:flex; justify-content:space-between; gap:14px; padding:11px 0; border-bottom:1px solid #f0f4f9; font-size:14px; }
.jm-vf-row:last-child { border-bottom:none; }
.jm-vf-row .k { color:#94a3b8; font-weight:600; }
.jm-vf-row .v { color:#061426; font-weight:700; text-align:right; }
.jm-vf-cta { margin-top:18px; }
.jm-vf-cta a { display:block; text-align:center; background:#0640a3; color:#fff; border-radius:10px; padding:12px; font-weight:800; font-size:14px; text-decoration:none; }
.jm-vf-hint { text-align:center; color:#94a3b8; font-size:13px; margin-top:18px; }
</style>

<div class="jm-vf">
    <?= jm_breadcrumbs([['label' => 'Verify certificate']]) ?>
    <div class="jm-vf-head">
        <h1>Verify a certificate</h1>
        <p>Scan the QR on a Jobmington certificate, or enter its ID below.</p>
    </div>

    <form class="jm-vf-form" method="get">
        <input type="text" name="code" value="<?= e($certCode) ?>" placeholder="e.g. JMT-2026-AB12CD34" autofocus>
        <button type="submit">Verify</button>
    </form>

    <?php if ($checked && $certificate): ?>
        <div class="jm-vf-result">
            <div class="jm-vf-banner ok">
                <div class="ic"><svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><polyline points="9 12 11 14 15 10"/></svg></div>
                <h2>Authentic certificate</h2>
                <p>This certificate is genuine and was issued by Jobmington.</p>
            </div>
            <div class="jm-vf-body">
                <div class="jm-vf-row"><span class="k">Recipient</span><span class="v"><?= e($certificate['full_name'] ?: 'Jobmington Learner') ?></span></div>
                <div class="jm-vf-row"><span class="k">Course</span><span class="v"><?= e($certificate['course_title']) ?></span></div>
                <div class="jm-vf-row"><span class="k">Issued</span><span class="v"><?= e(formatDate($certificate['issued_at'], 'F j, Y')) ?></span></div>
                <div class="jm-vf-row"><span class="k">Certificate ID</span><span class="v" style="font-family:monospace;"><?= e($certificate['cert_code']) ?></span></div>
                <div class="jm-vf-cta"><a href="/jobmington/certificates/view.php?code=<?= e($certificate['cert_code']) ?>">View certificate</a></div>
            </div>
        </div>
    <?php elseif ($checked): ?>
        <div class="jm-vf-result">
            <div class="jm-vf-banner no">
                <div class="ic"><svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg></div>
                <h2>Certificate not found</h2>
                <p>No certificate matches the ID <strong><?= e($certCode) ?></strong>.</p>
            </div>
            <div class="jm-vf-body" style="text-align:center;color:#53667f;font-size:14px;">
                Double-check the ID exactly as it appears on the certificate, including the dashes.
            </div>
        </div>
    <?php else: ?>
        <p class="jm-vf-hint">Every Jobmington certificate carries a unique ID and a scannable QR code that links here.</p>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/ai-footer.php'; ?>
