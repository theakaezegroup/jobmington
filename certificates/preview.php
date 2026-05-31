<?php
/**
 * JOBMINGTON - Certificate Template Preview
 * Preview page for founders/admins to review certificate design
 */

define('JOBMINGTON', true);
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/constants.php';

// Sample data for preview
$certificate = [
    'certificate_id' => 'PREV-' . date('Y') . '-001',
    'user_name' => 'Alexandra Johnson',
    'course_title' => 'Advanced Digital Marketing Strategy',
    'issued_date' => date('Y-m-d'),
    'verification_code' => 'JBM-' . strtoupper(substr(md5(time()), 0, 8))
];

$verifyUrl = SITE_URL . '/certificates/view.php?code=' . $certificate['verification_code'];
$qrCodeUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=120x120&data=' . urlencode($verifyUrl);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificate Preview - <?= SITE_NAME ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;500;600;700&family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;1,400&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Futura Cyrillic Demi';
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        /* Preview Controls */
        .preview-controls {
            max-width: 1200px;
            margin: 0 auto 30px;
            padding: 20px;
            background: rgba(255,255,255,0.1);
            backdrop-filter: blur(10px);
            border-radius: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .preview-controls h1 {
            color: #d4af37;
            font-family: 'Futura Cyrillic Demi';
            font-size: 1.5rem;
        }
        
        .preview-controls .badge {
            background: linear-gradient(135deg, #d4af37, #f4d03f);
            color: #0a1628;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.85rem;
        }
        
        .btn-group {
            display: flex;
            gap: 10px;
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-family: 'Futura Cyrillic Demi';
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #d4af37, #f4d03f);
            color: #0a1628;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(212, 175, 55, 0.4);
        }
        
        .btn-secondary {
            background: rgba(255,255,255,0.1);
            color: #fff;
            border: 1px solid rgba(212, 175, 55, 0.5);
        }
        
        .btn-secondary:hover {
            background: rgba(212, 175, 55, 0.2);
        }
        
        /* Certificate Container */
        .certificate-wrapper {
            display: flex;
            justify-content: center;
            padding: 20px;
        }
        
        .certificate {
            width: 297mm;
            height: 210mm;
            background: linear-gradient(145deg, #fffef9 0%, #faf8f0 50%, #f5f0e1 100%);
            position: relative;
            box-shadow: 
                0 25px 80px rgba(0,0,0,0.4),
                0 0 0 1px rgba(212, 175, 55, 0.3),
                inset 0 0 100px rgba(212, 175, 55, 0.05);
            overflow: hidden;
        }
        
        /* Decorative Border System */
        .border-outer {
            position: absolute;
            inset: 8mm;
            border: 3px solid #d4af37;
            pointer-events: none;
        }
        
        .border-middle {
            position: absolute;
            inset: 12mm;
            border: 1px solid #b8962e;
            pointer-events: none;
        }
        
        .border-inner {
            position: absolute;
            inset: 15mm;
            border: 2px solid #d4af37;
            pointer-events: none;
        }
        
        /* Corner Ornaments */
        .corner-ornament {
            position: absolute;
            width: 35mm;
            height: 35mm;
            pointer-events: none;
        }
        
        .corner-ornament svg {
            width: 100%;
            height: 100%;
            fill: #d4af37;
            opacity: 0.7;
        }
        
        .corner-tl { top: 5mm; left: 5mm; }
        .corner-tr { top: 5mm; right: 5mm; transform: rotate(90deg); }
        .corner-bl { bottom: 5mm; left: 5mm; transform: rotate(-90deg); }
        .corner-br { bottom: 5mm; right: 5mm; transform: rotate(180deg); }
        
        /* Main Content */
        .certificate-content {
            position: absolute;
            inset: 20mm;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: space-between;
            text-align: center;
            padding: 10mm 15mm;
        }
        
        /* Header Section */
        .cert-header {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 3mm;
        }
        
        .logo-section {
            display: flex;
            align-items: center;
            gap: 4mm;
            margin-bottom: 2mm;
        }
        
        .logo-icon {
            width: 15mm;
            height: 15mm;
        }
        
        .company-name {
            font-family: 'Futura Cyrillic Demi';
            font-size: 28pt;
            font-weight: 700;
            color: #0a1628;
            letter-spacing: 8px;
            text-transform: uppercase;
        }
        
        .cert-title {
            font-family: 'Futura Cyrillic Demi';
            font-size: 42pt;
            font-weight: 600;
            color: #d4af37;
            letter-spacing: 12px;
            text-transform: uppercase;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.1);
            margin-top: 2mm;
        }
        
        .cert-subtitle {
            font-size: 14pt;
            color: #5a5a5a;
            letter-spacing: 4px;
            text-transform: uppercase;
            margin-top: 1mm;
        }
        
        /* Decorative Line */
        .decorative-line {
            width: 80mm;
            height: 2px;
            background: linear-gradient(90deg, transparent, #d4af37, transparent);
            margin: 3mm 0;
        }
        
        /* Body Section */
        .cert-body {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 5mm;
        }
        
        .presented-to {
            font-size: 13pt;
            color: #666;
            letter-spacing: 3px;
            text-transform: uppercase;
        }
        
        .recipient-name {
            font-family: 'Futura Cyrillic Demi';
            font-size: 36pt;
            font-weight: 600;
            color: #0a1628;
            border-bottom: 2px solid #d4af37;
            padding-bottom: 3mm;
            min-width: 120mm;
        }
        
        .completion-text {
            font-size: 13pt;
            color: #555;
            line-height: 1.8;
            max-width: 180mm;
        }
        
        .course-name {
            font-family: 'Futura Cyrillic Demi';
            font-size: 22pt;
            font-weight: 600;
            color: #0a1628;
            margin-top: 2mm;
            padding: 3mm 8mm;
            background: linear-gradient(135deg, rgba(212, 175, 55, 0.1), rgba(212, 175, 55, 0.05));
            border-left: 3px solid #d4af37;
            border-right: 3px solid #d4af37;
        }
        
        /* Footer Section */
        .cert-footer {
            width: 100%;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            padding-top: 5mm;
        }
        
        .qr-section {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 2mm;
        }
        
        .qr-code {
            width: 25mm;
            height: 25mm;
            padding: 2mm;
            background: white;
            border: 1px solid #d4af37;
            border-radius: 2mm;
        }
        
        .qr-code img {
            width: 100%;
            height: 100%;
        }
        
        .verify-text {
            font-size: 8pt;
            color: #888;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .cert-id {
            font-size: 7pt;
            color: #999;
            font-family: 'Futura Cyrillic Demi';
        }
        
        .signature-section {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 2mm;
        }
        
        .signature-line {
            width: 50mm;
            border-bottom: 1px solid #333;
            height: 8mm;
            display: flex;
            align-items: flex-end;
            justify-content: center;
        }
        
        .signature-text {
            font-family: 'Futura Cyrillic Demi';
            font-size: 14pt;
            color: #333;
        }
        
        .signature-title {
            font-size: 9pt;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 2px;
        }
        
        .signature-name {
            font-size: 10pt;
            color: #333;
            font-weight: 600;
        }
        
        .seal-section {
            position: relative;
        }
        
        .golden-seal {
            width: 30mm;
            height: 30mm;
        }
        
        .seal-date {
            font-size: 8pt;
            color: #888;
            margin-top: 1mm;
            text-align: center;
        }
        
        /* Gold Flourish */
        .flourish {
            position: absolute;
            width: 60mm;
            height: 8mm;
            left: 50%;
            transform: translateX(-50%);
            opacity: 0.6;
        }
        
        .flourish-top {
            top: 45mm;
        }
        
        .flourish-bottom {
            bottom: 45mm;
        }
        
        /* Watermark */
        .watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-family: 'Futura Cyrillic Demi';
            font-size: 120pt;
            color: rgba(212, 175, 55, 0.03);
            letter-spacing: 20px;
            white-space: nowrap;
            pointer-events: none;
            z-index: 0;
        }
        
        /* Responsive */
        @media screen and (max-width: 1200px) {
            .certificate {
                width: 100%;
                height: auto;
                aspect-ratio: 297 / 210;
            }
        }
        
        @media print {
            body {
                background: white;
                padding: 0;
            }
            
            .preview-controls {
                display: none;
            }
            
            .certificate-wrapper {
                padding: 0;
            }
            
            .certificate {
                box-shadow: none;
                width: 297mm;
                height: 210mm;
            }
        }
    </style>
</head>
<body>
    <!-- Preview Controls -->
    <div class="preview-controls">
        <div>
            <h1> Certificate Template Preview</h1>
            <span class="badge">FOUNDER PREVIEW MODE</span>
        </div>
        <div class="btn-group">
            <button onclick="window.print()" class="btn btn-primary">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2"/>
                    <rect x="6" y="14" width="12" height="8"/>
                </svg>
                Print / Save as PDF
            </button>
            <a href="/jobmington/certificates/" class="btn btn-secondary">← Back to Certificates</a>
        </div>
    </div>
    
    <!-- Certificate -->
    <div class="certificate-wrapper">
        <div class="certificate">
            <!-- Watermark -->
            <div class="watermark">JOBMINGTON</div>
            
            <!-- Decorative Borders -->
            <div class="border-outer"></div>
            <div class="border-middle"></div>
            <div class="border-inner"></div>
            
            <!-- Corner Ornaments -->
            <div class="corner-ornament corner-tl">
                <svg viewBox="0 0 100 100">
                    <path d="M0,0 L100,0 L100,15 C60,15 15,60 15,100 L0,100 Z M25,0 L25,5 C45,5 5,45 5,25 L0,25 L0,0 Z"/>
                    <circle cx="20" cy="20" r="5"/>
                    <path d="M35,0 Q35,35 0,35" fill="none" stroke="#d4af37" stroke-width="2"/>
                </svg>
            </div>
            <div class="corner-ornament corner-tr">
                <svg viewBox="0 0 100 100">
                    <path d="M0,0 L100,0 L100,15 C60,15 15,60 15,100 L0,100 Z M25,0 L25,5 C45,5 5,45 5,25 L0,25 L0,0 Z"/>
                    <circle cx="20" cy="20" r="5"/>
                    <path d="M35,0 Q35,35 0,35" fill="none" stroke="#d4af37" stroke-width="2"/>
                </svg>
            </div>
            <div class="corner-ornament corner-bl">
                <svg viewBox="0 0 100 100">
                    <path d="M0,0 L100,0 L100,15 C60,15 15,60 15,100 L0,100 Z M25,0 L25,5 C45,5 5,45 5,25 L0,25 L0,0 Z"/>
                    <circle cx="20" cy="20" r="5"/>
                    <path d="M35,0 Q35,35 0,35" fill="none" stroke="#d4af37" stroke-width="2"/>
                </svg>
            </div>
            <div class="corner-ornament corner-br">
                <svg viewBox="0 0 100 100">
                    <path d="M0,0 L100,0 L100,15 C60,15 15,60 15,100 L0,100 Z M25,0 L25,5 C45,5 5,45 5,25 L0,25 L0,0 Z"/>
                    <circle cx="20" cy="20" r="5"/>
                    <path d="M35,0 Q35,35 0,35" fill="none" stroke="#d4af37" stroke-width="2"/>
                </svg>
            </div>
            
            <!-- Top Flourish -->
            <svg class="flourish flourish-top" viewBox="0 0 200 30">
                <path d="M0,15 Q25,5 50,15 T100,15 T150,15 T200,15" fill="none" stroke="#d4af37" stroke-width="1.5"/>
                <circle cx="100" cy="15" r="4" fill="#d4af37"/>
                <circle cx="60" cy="15" r="2" fill="#d4af37"/>
                <circle cx="140" cy="15" r="2" fill="#d4af37"/>
            </svg>
            
            <!-- Bottom Flourish -->
            <svg class="flourish flourish-bottom" viewBox="0 0 200 30">
                <path d="M0,15 Q25,25 50,15 T100,15 T150,15 T200,15" fill="none" stroke="#d4af37" stroke-width="1.5"/>
                <circle cx="100" cy="15" r="4" fill="#d4af37"/>
                <circle cx="60" cy="15" r="2" fill="#d4af37"/>
                <circle cx="140" cy="15" r="2" fill="#d4af37"/>
            </svg>
            
            <!-- Main Content -->
            <div class="certificate-content">
                <!-- Header -->
                <div class="cert-header">
                    <div class="logo-section">
                        <svg class="logo-icon" viewBox="0 0 100 100">
                            <defs>
                                <linearGradient id="logoGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                                    <stop offset="0%" style="stop-color:#d4af37"/>
                                    <stop offset="100%" style="stop-color:#f4d03f"/>
                                </linearGradient>
                            </defs>
                            <circle cx="50" cy="50" r="45" fill="none" stroke="url(#logoGrad)" stroke-width="4"/>
                            <text x="50" y="62" text-anchor="middle" font-family="Cinzel" font-size="40" font-weight="bold" fill="#0a1628">J</text>
                        </svg>
                        <span class="company-name"><?= SITE_NAME ?></span>
                    </div>
                    <h1 class="cert-title">Certificate</h1>
                    <p class="cert-subtitle">of Completion</p>
                    <div class="decorative-line"></div>
                </div>
                
                <!-- Body -->
                <div class="cert-body">
                    <p class="presented-to">This is to certify that</p>
                    <h2 class="recipient-name"><?= htmlspecialchars($certificate['user_name']) ?></h2>
                    <p class="completion-text">
                        has successfully completed all requirements and demonstrated<br>
                        proficiency in the following program of study:
                    </p>
                    <h3 class="course-name"><?= htmlspecialchars($certificate['course_title']) ?></h3>
                </div>
                
                <!-- Footer -->
                <div class="cert-footer">
                    <!-- QR Code -->
                    <div class="qr-section">
                        <div class="qr-code">
                            <img src="<?= $qrCodeUrl ?>" alt="Verification QR Code">
                        </div>
                        <span class="verify-text">Scan to Verify</span>
                        <span class="cert-id"><?= htmlspecialchars($certificate['verification_code']) ?></span>
                    </div>
                    
                    <!-- Signature -->
                    <div class="signature-section">
                        <div class="signature-line">
                            <span class="signature-text">Director</span>
                        </div>
                        <p class="signature-title">Director of Learning</p>
                        <p class="signature-name"><?= SITE_NAME ?></p>
                    </div>
                    
                    <!-- Seal -->
                    <div class="seal-section">
                        <svg class="golden-seal" viewBox="0 0 100 100">
                            <defs>
                                <linearGradient id="sealGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                                    <stop offset="0%" style="stop-color:#d4af37"/>
                                    <stop offset="50%" style="stop-color:#f4d03f"/>
                                    <stop offset="100%" style="stop-color:#d4af37"/>
                                </linearGradient>
                                <filter id="shadow">
                                    <feDropShadow dx="2" dy="2" stdDeviation="3" flood-opacity="0.3"/>
                                </filter>
                            </defs>
                            <!-- Outer ring with notches -->
                            <circle cx="50" cy="50" r="48" fill="url(#sealGrad)" filter="url(#shadow)"/>
                            <circle cx="50" cy="50" r="42" fill="#fffef9"/>
                            <circle cx="50" cy="50" r="38" fill="url(#sealGrad)"/>
                            <circle cx="50" cy="50" r="32" fill="#fffef9"/>
                            <!-- Checkmark -->
                            <path d="M35,50 L45,60 L65,35" fill="none" stroke="#d4af37" stroke-width="5" stroke-linecap="round" stroke-linejoin="round"/>
                            <!-- Year -->
                            <text x="50" y="78" text-anchor="middle" font-family="Cinzel" font-size="10" fill="#d4af37"><?= date('Y') ?></text>
                        </svg>
                        <p class="seal-date"><?= date('F j, Y', strtotime($certificate['issued_date'])) ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
