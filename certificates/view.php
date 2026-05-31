<?php
/**
 * JOBMINGTON - View Certificate (Premium)
 * World-Class Public Certificate Verification Display
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

if (empty($certCode)) {
    redirect('/jobmington/certificates');
}

// Get Certificate
$stmt = $pdo->prepare("
    SELECT cert.*, c.title as course_title, c.description as course_description,
           u.full_name, u.email
    FROM certificates cert
    JOIN courses c ON cert.course_id = c.course_id
    LEFT JOIN users u ON cert.user_id = u.user_id
    WHERE cert.cert_code = ?
");
$stmt->execute([$certCode]);
$cert = $stmt->fetch();

if (!$cert) {
    Session::flash('error', 'Certificate not found.');
    redirect('/jobmington/certificates');
}

$isOwner = Session::isLoggedIn() && Session::userId() == $cert['user_id'];
$issueDate = formatDate($cert['issued_at'], 'F j, Y');
$year = date('Y', strtotime($cert['issued_at']));
$verifyUrl = SITE_URL . '/verify?code=' . $cert['cert_code'];
$qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=" . urlencode($verifyUrl);

$pageTitle = 'Certificate: ' . e($cert['course_title']) . ' - ' . SITE_NAME;
require_once __DIR__ . '/../includes/header.php';
?>

<style>
    .cert-page {
        background: linear-gradient(135deg, #0a1628 0%, #1a1a2e 50%, #16213e 100%);
        min-height: 100vh;
        padding: 40px 20px;
    }
    
    .cert-container {
        max-width: 900px;
        margin: 0 auto;
    }
    
    .back-link {
        color: rgba(255,255,255,0.6);
        font-size: 14px;
        margin-bottom: 24px;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: color 0.2s;
    }
    .back-link:hover { color: #d4af37; }
    
    .cert-card {
        background: linear-gradient(135deg, #fffef5 0%, #fff 50%, #fffef8 100%);
        border-radius: 20px;
        overflow: hidden;
        box-shadow: 0 30px 80px rgba(0,0,0,0.4), 0 0 0 1px rgba(212,175,55,0.2);
        position: relative;
    }
    
    .cert-header {
        background: linear-gradient(135deg, #0a1628 0%, #1e3a5f 100%);
        padding: 32px;
        text-align: center;
        position: relative;
        overflow: hidden;
    }
    
    .cert-header::before {
        content: '';
        position: absolute;
        inset: 0;
        background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23d4af37' fill-opacity='0.05'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
    }
    
    .verified-badge {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        background: rgba(212, 175, 55, 0.2);
        border: 1px solid rgba(212, 175, 55, 0.3);
        padding: 8px 20px;
        border-radius: 30px;
        font-size: 12px;
        font-weight: 600;
        color: #d4af37;
        letter-spacing: 1px;
        text-transform: uppercase;
        margin-bottom: 16px;
    }
    
    .cert-header h1 {
        font-family: 'Futura Cyrillic Demi';
        font-size: 28px;
        color: #fff;
        margin: 0;
        letter-spacing: 3px;
    }
    
    .cert-body {
        padding: 48px;
        text-align: center;
        position: relative;
    }
    
    /* Decorative corners */
    .cert-body::before,
    .cert-body::after {
        content: '';
        position: absolute;
        width: 60px;
        height: 60px;
        border: 2px solid #d4af37;
        opacity: 0.3;
    }
    .cert-body::before {
        top: 20px;
        left: 20px;
        border-right: none;
        border-bottom: none;
    }
    .cert-body::after {
        bottom: 20px;
        right: 20px;
        border-left: none;
        border-top: none;
    }
    
    .recipient-label {
        color: #888;
        font-size: 14px;
        font-style: italic;
        margin-bottom: 8px;
    }
    
    .recipient-name {
        font-family: 'Futura Cyrillic Demi';
        font-size: 42px;
        color: #0a1628;
        margin-bottom: 8px;
        position: relative;
        display: inline-block;
        padding: 0 20px;
    }
    
    .recipient-name::after {
        content: '';
        position: absolute;
        bottom: -4px;
        left: 0;
        right: 0;
        height: 2px;
        background: linear-gradient(90deg, transparent, #d4af37, transparent);
    }
    
    .completion-text {
        color: #666;
        font-size: 15px;
        margin: 24px 0 12px;
        font-style: italic;
    }
    
    .course-title {
        font-family: 'Futura Cyrillic Demi';
        font-size: 22px;
        color: #0a1628;
        margin-bottom: 32px;
        line-height: 1.4;
    }
    
    .gold-divider {
        width: 80px;
        height: 2px;
        background: linear-gradient(90deg, transparent, #d4af37, transparent);
        margin: 0 auto 24px;
    }
    
    .cert-details {
        display: flex;
        justify-content: center;
        gap: 48px;
        flex-wrap: wrap;
        margin-bottom: 32px;
    }
    
    .detail-box {
        text-align: center;
    }
    
    .detail-label {
        font-size: 10px;
        color: #999;
        text-transform: uppercase;
        letter-spacing: 2px;
        margin-bottom: 4px;
    }
    
    .detail-value {
        font-family: 'Futura Cyrillic Demi';
        font-size: 14px;
        color: #333;
        font-weight: 600;
    }
    
    .qr-section {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 16px;
        background: #f8f8f8;
        padding: 16px 24px;
        border-radius: 12px;
        display: inline-flex;
    }
    
    .qr-code {
        width: 70px;
        height: 70px;
        border-radius: 8px;
    }
    
    .qr-text {
        text-align: left;
    }
    
    .qr-text .cert-id {
        font-family: 'Futura Cyrillic Demi';
        font-size: 14px;
        color: #0a1628;
        font-weight: 700;
    }
    
    .qr-text .verify-link {
        font-size: 11px;
        color: #888;
    }
    
    /* Seal */
    .seal {
        position: absolute;
        bottom: 30px;
        right: 40px;
        width: 80px;
        height: 80px;
        background: linear-gradient(135deg, #d4af37 0%, #c5a028 40%, #e8c547 60%, #d4af37 100%);
        border-radius: 50%;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        box-shadow: 0 4px 15px rgba(212,175,55,0.4), inset 0 2px 4px rgba(255,255,255,0.3);
    }
    
    .seal::before {
        content: '';
        position: absolute;
        inset: 4px;
        border-radius: 50%;
        border: 2px solid rgba(255,255,255,0.3);
    }
    
    .seal-icon { font-size: 20px; color: #fff; }
    .seal-text { font-size: 8px; color: #fff; text-transform: uppercase; letter-spacing: 1px; }
    
    /* Actions */
    .cert-actions {
        background: #f8f9fa;
        padding: 24px 48px;
        display: flex;
        justify-content: center;
        gap: 16px;
        flex-wrap: wrap;
    }
    
    .cert-btn {
        padding: 14px 28px;
        border-radius: 12px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        text-decoration: none;
        border: none;
    }
    
    .btn-download {
        background: linear-gradient(135deg, #d4af37, #b8860b);
        color: #000;
        box-shadow: 0 4px 15px rgba(212,175,55,0.3);
    }
    .btn-download:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(212,175,55,0.4); }
    
    .btn-share {
        background: #0a1628;
        color: #fff;
    }
    .btn-share:hover { background: #1e3a5f; }
    
    .btn-print {
        background: #fff;
        color: #333;
        border: 1px solid #ddd;
    }
    .btn-print:hover { background: #f5f5f5; }
    
    @media (max-width: 640px) {
        .cert-body { padding: 32px 20px; }
        .recipient-name { font-size: 28px; }
        .course-title { font-size: 18px; }
        .cert-details { gap: 24px; }
        .seal { width: 60px; height: 60px; right: 20px; bottom: 20px; }
    }
</style>

<div class="cert-page">
    <div class="cert-container">
        <a href="<?= $isOwner ? '/jobmington/certificates' : '/jobmington/verify' ?>" class="back-link">
            <i class="fas fa-arrow-left"></i>
            <?= $isOwner ? 'Back to My Certificates' : 'Verify Another Certificate' ?>
        </a>
        
        <div class="cert-card">
            <div class="cert-header">
                <div class="verified-badge">
                    <i class="fas fa-check-circle"></i>
                    Verified Certificate
                </div>
                <h1>Certificate of Achievement</h1>
            </div>
            
            <div class="cert-body">
                <p class="recipient-label">This certificate is proudly awarded to</p>
                <h2 class="recipient-name"><?= e($cert['full_name'] ?: 'Anonymous User') ?></h2>
                
                <p class="completion-text">for successfully completing the professional development course</p>
                <h3 class="course-title">"<?= e($cert['course_title']) ?>"</h3>
                
                <div class="gold-divider"></div>
                
                <div class="cert-details">
                    <div class="detail-box">
                        <div class="detail-label">Issue Date</div>
                        <div class="detail-value"><?= $issueDate ?></div>
                    </div>
                    <div class="detail-box">
                        <div class="detail-label">Credential</div>
                        <div class="detail-value">Lifetime Valid</div>
                    </div>
                    <div class="detail-box">
                        <div class="detail-label">Issuer</div>
                        <div class="detail-value">Jobmington</div>
                    </div>
                </div>
                
                <div class="qr-section">
                    <img src="<?= $qrUrl ?>" alt="QR Code" class="qr-code">
                    <div class="qr-text">
                        <div class="cert-id"><?= e($cert['cert_code']) ?></div>
                        <div class="verify-link">Scan to verify authenticity</div>
                    </div>
                </div>
                
                <div class="seal">
                    <span class="seal-icon"></span>
                    <span class="seal-text">Verified</span>
                    <span class="seal-text"><?= $year ?></span>
                </div>
            </div>
            
            <div class="cert-actions">
                <?php if ($isOwner): ?>
                <a href="/jobmington/certificates/download.php?code=<?= e($cert['cert_code']) ?>" class="cert-btn btn-download">
                    <i class="fas fa-download"></i> Download Certificate
                </a>
                <?php endif; ?>
                
                <button onclick="shareCertificate()" class="cert-btn btn-share">
                    <i class="fas fa-share-alt"></i> Share
                </button>
                
                <button onclick="window.print()" class="cert-btn btn-print">
                    <i class="fas fa-print"></i> Print
                </button>
            </div>
        </div>
        
        <?php if ($cert['course_description']): ?>
        <div style="background: rgba(255,255,255,0.05); border-radius: 16px; padding: 24px; margin-top: 24px; border: 1px solid rgba(255,255,255,0.1);">
            <h4 style="color: #d4af37; font-size: 14px; margin-bottom: 12px; text-transform: uppercase; letter-spacing: 2px;">About This Course</h4>
            <p style="color: rgba(255,255,255,0.7); line-height: 1.6;"><?= e($cert['course_description']) ?></p>
            <a href="/jobmington/jobs/" style="color: #d4af37; font-size: 14px; margin-top: 12px; display: inline-block;">
                Browse Jobs <i class="fas fa-arrow-right" style="margin-left: 4px;"></i>
            </a>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
function shareCertificate() {
    const url = '<?= $verifyUrl ?>';
    const title = 'Verified Certificate - <?= addslashes(e($cert['course_title'])) ?>';
    const text = '<?= addslashes(e($cert['full_name'])) ?> has earned a verified certificate from Jobmington!';
    
    if (navigator.share) {
        navigator.share({ title, text, url });
    } else {
        navigator.clipboard.writeText(url).then(() => {
            JM.toast('Certificate link copied!', 'success');
        });
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
