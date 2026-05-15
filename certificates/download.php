<?php
/**
 * JOBMINGTON - Premium Certificate Download
 * World-Class Professional Certificate Generator
 */

define('JOBMINGTON', true);
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';

Session::start();
Session::requireLogin();

$pdo = db();
$certCode = Security::clean(get('code', ''));
$userId = Session::userId();

if (empty($certCode)) {
    redirect('/jobmington/certificates');
}

// Get Certificate (must belong to current user)
$stmt = $pdo->prepare("
    SELECT cert.*, c.title as course_title, c.description as course_desc, u.full_name
    FROM certificates cert
    JOIN courses c ON cert.course_id = c.course_id
    JOIN users u ON cert.user_id = u.user_id
    WHERE cert.cert_code = ? AND cert.user_id = ?
");
$stmt->execute([$certCode, $userId]);
$cert = $stmt->fetch();

if (!$cert) {
    Session::flash('error', 'Certificate not found or access denied.');
    redirect('/jobmington/certificates');
}

// Generate premium HTML certificate
generatePremiumCertificate($cert);

function generatePremiumCertificate($cert) {
    $verifyUrl = SITE_URL . '/verify?code=' . $cert['cert_code'];
    $issueDate = date('F j, Y', strtotime($cert['issued_at']));
    $year = date('Y', strtotime($cert['issued_at']));
    $qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size=80x80&data=" . urlencode($verifyUrl);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificate - <?= e($cert['cert_code']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;500;600;700&family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;1,400&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        @page { size: A4 landscape; margin: 0; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Cormorant Garamond', Georgia, serif;
            background: linear-gradient(135deg, #0a1628 0%, #1a1a2e 50%, #16213e 100%);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .controls {
            margin-bottom: 24px;
            display: flex;
            gap: 12px;
        }
        
        .controls button {
            padding: 14px 28px;
            font-size: 14px;
            font-weight: 600;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s;
            font-family: 'Inter', sans-serif;
        }
        
        .btn-print {
            background: linear-gradient(135deg, #fbbf24, #d97706);
            color: #000;
            box-shadow: 0 4px 20px rgba(251,191,36,0.3);
        }
        
        .btn-back {
            background: rgba(255,255,255,0.08);
            color: #fff;
            border: 1px solid rgba(255,255,255,0.15) !important;
            backdrop-filter: blur(10px);
        }
        
        .btn-print:hover { transform: translateY(-3px); box-shadow: 0 8px 30px rgba(251,191,36,0.5); }
        .btn-back:hover { background: rgba(255,255,255,0.15); }
        
        .certificate-wrapper {
            width: 297mm;
            height: 210mm;
            background: linear-gradient(135deg, #fffef5 0%, #fff 50%, #fffef8 100%);
            position: relative;
            overflow: hidden;
            box-shadow: 0 30px 100px rgba(0,0,0,0.6), 0 0 0 1px rgba(212,175,55,0.3);
        }
        
        /* Elegant gold border system */
        .border-outer {
            position: absolute;
            inset: 6mm;
            border: 2px solid #0a1628;
        }
        
        .border-gold {
            position: absolute;
            inset: 8mm;
            border: 1px solid #d4af37;
        }
        
        .border-inner {
            position: absolute;
            inset: 10mm;
            border: 1px solid rgba(212, 175, 55, 0.3);
        }
        
        /* Corner ornaments - enhanced */
        .corner {
            position: absolute;
            width: 70px;
            height: 70px;
        }
        .corner-tl { top: 12mm; left: 12mm; }
        .corner-tr { top: 12mm; right: 12mm; transform: scaleX(-1); }
        .corner-bl { bottom: 12mm; left: 12mm; transform: scaleY(-1); }
        .corner-br { bottom: 12mm; right: 12mm; transform: scale(-1); }
        
        .corner svg { width: 100%; height: 100%; }
        
        /* Subtle pattern overlay */
        .pattern-overlay {
            position: absolute;
            inset: 0;
            background-image: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23d4af37' fill-opacity='0.03'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
            pointer-events: none;
        }
        
        /* Watermark */
        .watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-family: 'Cinzel', serif;
            font-size: 160px;
            font-weight: 700;
            color: rgba(212, 175, 55, 0.04);
            letter-spacing: 15px;
            white-space: nowrap;
            pointer-events: none;
        }
        
        /* Content */
        .content {
            position: relative;
            height: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 22mm 35mm 28mm;
            text-align: center;
            z-index: 1;
        }
        
        /* Brand header */
        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 12px;
        }
        
        .brand-logo {
            width: 42px;
            height: 42px;
            background: linear-gradient(135deg, #0a1628, #1e3a5f);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 12px rgba(10,22,40,0.3);
        }
        
        .brand-logo span {
            font-family: 'Cinzel', serif;
            font-size: 20px;
            font-weight: 700;
            color: #d4af37;
        }
        
        .brand-text {
            font-family: 'Cinzel', serif;
            font-size: 16px;
            font-weight: 600;
            color: #0a1628;
            letter-spacing: 4px;
        }
        
        /* Flourish divider */
        .flourish {
            width: 220px;
            height: 24px;
            margin: 8px 0;
        }
        
        /* Title section */
        .cert-label {
            font-family: 'Cinzel', serif;
            font-size: 11px;
            font-weight: 500;
            letter-spacing: 6px;
            color: #888;
            text-transform: uppercase;
            margin-bottom: 2px;
        }
        
        .cert-title {
            font-family: 'Cinzel', serif;
            font-size: 48px;
            font-weight: 700;
            color: #0a1628;
            letter-spacing: 6px;
            text-transform: uppercase;
            margin-bottom: 0;
            text-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        
        .cert-subtitle {
            font-family: 'Cinzel', serif;
            font-size: 15px;
            color: #d4af37;
            letter-spacing: 5px;
            text-transform: uppercase;
            margin-bottom: 16px;
        }
        
        /* Recipient */
        .presented-to {
            font-size: 13px;
            color: #777;
            font-style: italic;
            margin-bottom: 6px;
        }
        
        .recipient-name {
            font-family: 'Cinzel', serif;
            font-size: 42px;
            font-weight: 600;
            color: #0a1628;
            padding: 0 30px 8px;
            position: relative;
            display: inline-block;
            margin-bottom: 6px;
        }
        
        .recipient-name::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 5%;
            right: 5%;
            height: 2px;
            background: linear-gradient(90deg, transparent, #d4af37, transparent);
        }
        
        /* Course */
        .completion-text {
            font-size: 13px;
            color: #777;
            margin: 14px 0 8px;
            font-style: italic;
        }
        
        .course-title {
            font-family: 'Cinzel', serif;
            font-size: 20px;
            font-weight: 500;
            color: #0a1628;
            max-width: 580px;
            line-height: 1.5;
            margin-bottom: 18px;
        }
        
        /* Gold line */
        .gold-line {
            width: 100px;
            height: 1px;
            background: linear-gradient(90deg, transparent, #d4af37, transparent);
            margin: 0 auto 16px;
        }
        
        /* Details */
        .details-row {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 60px;
        }
        
        .detail-item { text-align: center; }
        
        .detail-label {
            font-size: 9px;
            color: #999;
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-bottom: 3px;
        }
        
        .detail-value {
            font-family: 'Cinzel', serif;
            font-size: 12px;
            font-weight: 500;
            color: #333;
        }
        
        /* Seal */
        .seal-container {
            position: absolute;
            bottom: 22mm;
            right: 32mm;
        }
        
        .seal {
            width: 85px;
            height: 85px;
            background: linear-gradient(135deg, #d4af37 0%, #c5a028 40%, #e8c547 60%, #d4af37 100%);
            border-radius: 50%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            box-shadow: 
                0 6px 20px rgba(212, 175, 55, 0.4),
                inset 0 2px 4px rgba(255,255,255,0.4),
                inset 0 -3px 6px rgba(0,0,0,0.15);
            position: relative;
        }
        
        .seal::before {
            content: '';
            position: absolute;
            inset: 5px;
            border-radius: 50%;
            border: 2px solid rgba(255,255,255,0.35);
        }
        
        .seal::after {
            content: '';
            position: absolute;
            inset: 9px;
            border-radius: 50%;
            border: 1px dashed rgba(255,255,255,0.25);
        }
        
        .seal-icon {
            font-size: 22px;
            color: #fff;
            text-shadow: 0 2px 3px rgba(0,0,0,0.2);
            margin-bottom: 1px;
        }
        
        .seal-text {
            font-family: 'Cinzel', serif;
            font-size: 7px;
            color: #fff;
            text-transform: uppercase;
            letter-spacing: 1px;
            text-shadow: 0 1px 2px rgba(0,0,0,0.2);
        }
        
        /* QR */
        .qr-container {
            position: absolute;
            bottom: 22mm;
            left: 32mm;
            text-align: center;
        }
        
        .qr-code {
            width: 55px;
            height: 55px;
            padding: 3px;
            background: #fff;
            border: 1px solid #e0e0e0;
            border-radius: 4px;
        }
        
        .qr-label {
            font-size: 7px;
            color: #aaa;
            margin-top: 4px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        /* Signature */
        .signature-area {
            position: absolute;
            bottom: 24mm;
            left: 50%;
            transform: translateX(-50%);
            text-align: center;
            width: 160px;
        }
        
        .signature-line {
            width: 100%;
            height: 1px;
            background: #333;
            margin-bottom: 5px;
        }
        
        .signature-name {
            font-family: 'Cinzel', serif;
            font-size: 11px;
            font-weight: 600;
            color: #333;
        }
        
        .signature-title {
            font-size: 9px;
            color: #777;
        }
        
        /* Ribbon decorations */
        .ribbon {
            position: absolute;
            bottom: 18mm;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            gap: 8px;
        }
        
        .ribbon-strand {
            width: 3px;
            height: 25px;
            border-radius: 0 0 2px 2px;
        }
        
        .ribbon-strand:nth-child(1) { background: #0a1628; }
        .ribbon-strand:nth-child(2) { background: #d4af37; }
        .ribbon-strand:nth-child(3) { background: #0a1628; }
        
        @media print {
            body { background: #fff; padding: 0; min-height: auto; }
            .controls { display: none !important; }
            .certificate-wrapper { box-shadow: none; }
        }
    </style>
</head>
<body>
    <div class="controls">
        <button class="btn-back" onclick="window.location.href='/jobmington/certificates'">← Back to Certificates</button>
        <button class="btn-print" onclick="window.print()"> Print / Save as PDF</button>
    </div>
    
    <div class="certificate-wrapper">
        <div class="pattern-overlay"></div>
        <div class="border-outer"></div>
        <div class="border-gold"></div>
        <div class="border-inner"></div>
        
        <!-- Corner ornaments -->
        <div class="corner corner-tl">
            <svg viewBox="0 0 100 100">
                <path d="M0,0 L40,0 L40,5 L5,5 L5,40 L0,40 Z" fill="#0a1628"/>
                <path d="M0,0 L25,0 L25,2 L2,2 L2,25 L0,25 Z" fill="#d4af37"/>
                <circle cx="12" cy="12" r="3" fill="#d4af37" opacity="0.5"/>
            </svg>
        </div>
        <div class="corner corner-tr">
            <svg viewBox="0 0 100 100">
                <path d="M0,0 L40,0 L40,5 L5,5 L5,40 L0,40 Z" fill="#0a1628"/>
                <path d="M0,0 L25,0 L25,2 L2,2 L2,25 L0,25 Z" fill="#d4af37"/>
                <circle cx="12" cy="12" r="3" fill="#d4af37" opacity="0.5"/>
            </svg>
        </div>
        <div class="corner corner-bl">
            <svg viewBox="0 0 100 100">
                <path d="M0,0 L40,0 L40,5 L5,5 L5,40 L0,40 Z" fill="#0a1628"/>
                <path d="M0,0 L25,0 L25,2 L2,2 L2,25 L0,25 Z" fill="#d4af37"/>
                <circle cx="12" cy="12" r="3" fill="#d4af37" opacity="0.5"/>
            </svg>
        </div>
        <div class="corner corner-br">
            <svg viewBox="0 0 100 100">
                <path d="M0,0 L40,0 L40,5 L5,5 L5,40 L0,40 Z" fill="#0a1628"/>
                <path d="M0,0 L25,0 L25,2 L2,2 L2,25 L0,25 Z" fill="#d4af37"/>
                <circle cx="12" cy="12" r="3" fill="#d4af37" opacity="0.5"/>
            </svg>
        </div>
        
        <div class="watermark">JOBMINGTON</div>
        
        <div class="content">
            <div class="brand">
                <div class="brand-logo"><span>J</span></div>
                <span class="brand-text">JOBMINGTON</span>
            </div>
            
            <div class="flourish">
                <svg viewBox="0 0 220 24" fill="none">
                    <path d="M0,12 Q55,2 110,12 Q165,22 220,12" stroke="#d4af37" stroke-width="0.5"/>
                    <path d="M40,12 Q75,6 110,12 Q145,18 180,12" stroke="#d4af37" stroke-width="0.5"/>
                    <circle cx="110" cy="12" r="4" fill="#d4af37"/>
                    <circle cx="90" cy="12" r="2" fill="#d4af37" opacity="0.6"/>
                    <circle cx="130" cy="12" r="2" fill="#d4af37" opacity="0.6"/>
                    <circle cx="70" cy="12" r="1" fill="#d4af37" opacity="0.4"/>
                    <circle cx="150" cy="12" r="1" fill="#d4af37" opacity="0.4"/>
                </svg>
            </div>
            
            <div class="cert-label">Jobmington</div>
            <h1 class="cert-title">Certificate</h1>
            <div class="cert-subtitle">of Achievement</div>
            
            <p class="presented-to">This certificate is proudly presented to</p>
            <h2 class="recipient-name"><?= e($cert['full_name']) ?></h2>
            
            <p class="completion-text">for successfully completing the professional development course</p>
            <h3 class="course-title">"<?= e($cert['course_title']) ?>"</h3>
            
            <div class="gold-line"></div>
            
            <div class="details-row">
                <div class="detail-item">
                    <div class="detail-label">Issue Date</div>
                    <div class="detail-value"><?= $issueDate ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Certificate ID</div>
                    <div class="detail-value"><?= e($cert['cert_code']) ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Credential</div>
                    <div class="detail-value">Lifetime Valid</div>
                </div>
            </div>
        </div>
        
        <div class="qr-container">
            <img src="<?= $qrUrl ?>" alt="Verify" class="qr-code">
            <div class="qr-label">Scan to Verify</div>
        </div>
        
        <div class="signature-area">
            <div class="signature-line"></div>
            <div class="signature-name">Director of Learning</div>
            <div class="signature-title">Jobmington</div>
        </div>
        
        <div class="seal-container">
            <div class="seal">
                <div class="seal-icon"></div>
                <div class="seal-text">Verified</div>
                <div class="seal-text"><?= $year ?></div>
            </div>
        </div>
    </div>
</body>
</html>
<?php
}
            
