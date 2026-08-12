<?php
/**
 * JOBMINGTON - My Certificates (clean, on-brand shell)
 */
define('JOBMINGTON', true);
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/tools.php';

Session::start();
Session::requireLogin();
jm_require_tool('certificates');

$pdo = db();
$userId = Session::userId();

$stmt = $pdo->prepare("
    SELECT cert.*, c.title AS course_title
    FROM certificates cert
    JOIN courses c ON cert.course_id = c.course_id
    WHERE cert.user_id = ?
    ORDER BY cert.issued_at DESC
");
$stmt->execute([$userId]);
$certificates = $stmt->fetchAll();
$fullName = Session::get('full_name') ?: 'Jobmington Learner';

$pageTitle = 'My Certificates - ' . SITE_NAME;
$activeAIPage = 'learn';
require_once __DIR__ . '/../includes/ai-header.php';
?>
<style>
.jm-certs { max-width:1040px; margin:0 auto; padding:28px 20px 72px; }
.jm-certs-head { margin-bottom:24px; }
.jm-certs-head h1 { font-size:clamp(22px,3.5vw,30px); font-weight:800; color:#061426; margin:0 0 6px; }
.jm-certs-head p { font-size:14px; color:#53667f; margin:0; max-width:600px; }

.jm-certs-stats { display:grid; grid-template-columns:repeat(3,1fr); gap:12px; margin:0 0 28px; }
.jm-certs-stat { background:#fff; border:1px solid #e8edf5; border-radius:12px; padding:18px 16px; text-align:center; }
.jm-certs-stat b { display:block; font-size:26px; font-weight:800; color:#0640a3; line-height:1; }
.jm-certs-stat span { display:block; font-size:12.5px; color:#53667f; margin-top:6px; }

.jm-certs-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(300px,1fr)); gap:18px; }
.jm-certcard { background:#fff; border:1px solid #e8edf5; border-radius:14px; overflow:hidden; transition:box-shadow .15s, transform .15s; }
.jm-certcard:hover { box-shadow:0 18px 40px -20px rgba(6,20,38,.28); transform:translateY(-2px); }
.jm-certcard-top { position:relative; padding:22px 20px; background:#f7faff; border-bottom:1px solid #eef3fb; }
.jm-certcard-kicker { font-size:10.5px; font-weight:800; letter-spacing:.22em; text-transform:uppercase; color:#0640a3; }
.jm-certcard-title { font-size:16px; font-weight:800; color:#061426; margin:8px 0 0; line-height:1.3; }
.jm-certcard-badge { position:absolute; top:16px; right:16px; display:inline-flex; align-items:center; gap:5px; background:#e6f5f1; color:#0a6454; font-size:11px; font-weight:800; padding:4px 9px; border-radius:99px; }
.jm-certcard-body { padding:18px 20px 20px; }
.jm-certcard-meta { display:flex; align-items:center; gap:7px; font-size:13px; color:#53667f; margin-bottom:14px; }
.jm-certcard-id { background:#f7faff; border:1px solid #eef3fb; border-radius:10px; padding:10px 12px; margin-bottom:16px; }
.jm-certcard-id small { display:block; font-size:11px; color:#7c8aa0; margin-bottom:2px; }
.jm-certcard-id code { font-family:"Courier New",monospace; font-weight:800; color:#0640a3; font-size:13.5px; letter-spacing:.02em; }
.jm-certcard-acts { display:flex; gap:8px; }
.jm-cc-btn { flex:1; display:inline-flex; align-items:center; justify-content:center; gap:6px; border-radius:9px; padding:10px; font-size:13px; font-weight:800; text-decoration:none; cursor:pointer; border:1px solid #d8e4f4; }
.jm-cc-btn.primary { background:#0640a3; color:#fff; border-color:#0640a3; } .jm-cc-btn.primary:hover { background:#052f78; }
.jm-cc-btn.ghost { background:#fff; color:#0640a3; } .jm-cc-btn.ghost:hover { background:#eef5ff; }
.jm-cc-btn.icon { flex:0 0 auto; width:42px; padding:10px 0; color:#53667f; }
.jm-cc-btn.icon:hover { background:#f6f8fb; }

.jm-certs-empty { background:#fff; border:1px solid #e8edf5; border-radius:16px; padding:56px 24px; text-align:center; }
.jm-certs-empty .ico { width:72px; height:72px; border-radius:50%; background:#f0f5ff; display:flex; align-items:center; justify-content:center; margin:0 auto 18px; color:#0640a3; }
.jm-certs-empty h3 { font-size:19px; font-weight:800; color:#061426; margin:0 0 8px; }
.jm-certs-empty p { font-size:14px; color:#53667f; margin:0 auto 22px; max-width:420px; }

.jm-certs-verify { margin-top:28px; background:#f7faff; border:1px solid #e8edf5; border-radius:14px; padding:22px; display:flex; align-items:center; gap:18px; flex-wrap:wrap; }
.jm-certs-verify .ico { width:52px; height:52px; border-radius:12px; background:#fff; border:1px solid #e8edf5; display:flex; align-items:center; justify-content:center; color:#0640a3; flex-shrink:0; }
.jm-certs-verify .txt { flex:1; min-width:220px; }
.jm-certs-verify h4 { font-size:15px; font-weight:800; color:#061426; margin:0 0 4px; }
.jm-certs-verify p { font-size:13px; color:#53667f; margin:0; }
.jm-certs-verify p strong { color:#0640a3; }
@media (max-width:560px){ .jm-certs-stats { grid-template-columns:repeat(3,1fr); } .jm-certs-stat b { font-size:21px; } }
</style>

<div class="jm-certs">
    <?= jm_breadcrumbs([['label' => 'Learn', 'url' => '/jobmington/learn/'], ['label' => 'My certificates']]) ?>
    <div class="jm-certs-head">
        <h1>My certificates</h1>
        <p>Your verified Jobmington credentials. Share the verification link or certificate ID with any employer.</p>
    </div>

    <?php if (empty($certificates)): ?>
        <div class="jm-certs-empty">
            <div class="ico">
                <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="6"/><path d="M15.477 12.89 17 22l-5-3-5 3 1.523-9.11"/></svg>
            </div>
            <h3>No certificates yet</h3>
            <p>Complete a Jobmington course to earn your first verified certificate. Each one comes with a scannable QR code employers can verify.</p>
            <a href="/jobmington/learn/" class="jm-cc-btn primary" style="display:inline-flex;max-width:220px;margin:0 auto;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
                Browse courses
            </a>
        </div>
    <?php else: ?>
        <div class="jm-certs-stats">
            <div class="jm-certs-stat"><b><?= count($certificates) ?></b><span>Certificates earned</span></div>
            <div class="jm-certs-stat"><b>100%</b><span>Verifiable</span></div>
            <div class="jm-certs-stat"><b>&infin;</b><span>Never expires</span></div>
        </div>

        <div class="jm-certs-grid">
            <?php foreach ($certificates as $cert): ?>
            <div class="jm-certcard">
                <div class="jm-certcard-top">
                    <span class="jm-certcard-badge">
                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                        Verified
                    </span>
                    <div class="jm-certcard-kicker">Certificate of Completion</div>
                    <h3 class="jm-certcard-title"><?= e($cert['course_title']) ?></h3>
                </div>
                <div class="jm-certcard-body">
                    <div class="jm-certcard-meta">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                        Issued <?= formatDate($cert['issued_at'], 'M j, Y') ?>
                    </div>
                    <div class="jm-certcard-id">
                        <small>Certificate ID</small>
                        <code><?= e($cert['cert_code']) ?></code>
                    </div>
                    <div class="jm-certcard-acts">
                        <a class="jm-cc-btn primary" href="/jobmington/certificates/view.php?code=<?= e($cert['cert_code']) ?>">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/></svg>
                            View
                        </a>
                        <a class="jm-cc-btn ghost" href="/jobmington/certificates/download.php?code=<?= e($cert['cert_code']) ?>">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                            Download
                        </a>
                        <button class="jm-cc-btn icon" type="button" title="Share verification link" onclick="jmShareCert('<?= e($cert['cert_code']) ?>')">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.6" y1="13.5" x2="15.4" y2="17.5"/><line x1="15.4" y1="6.5" x2="8.6" y2="10.5"/></svg>
                        </button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="jm-certs-verify">
            <div class="ico">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><polyline points="9 12 11 14 15 10"/></svg>
            </div>
            <div class="txt">
                <h4>Employer verification</h4>
                <p>Employers can confirm any certificate at <strong><?= SITE_URL ?>/verify</strong> using the Certificate ID.</p>
            </div>
            <a href="/jobmington/verify" target="_blank" class="jm-cc-btn ghost" style="max-width:200px;">Open verify page</a>
        </div>
    <?php endif; ?>
</div>

<script>
function jmShareCert(code) {
    var url = '<?= SITE_URL ?>/verify?code=' + code;
    if (navigator.share) {
        navigator.share({ title: 'My Jobmington Certificate', text: 'Verify my certificate from Jobmington', url: url });
    } else {
        navigator.clipboard.writeText(url).then(function () {
            if (window.JM && JM.toast) JM.toast('Verification link copied', 'success'); else alert('Link copied');
        });
    }
}
</script>

<?php require_once __DIR__ . '/../includes/ai-footer.php'; ?>
