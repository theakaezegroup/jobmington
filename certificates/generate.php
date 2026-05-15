<?php
/**
 * JOBMINGTON - Generate Certificate
 * Internal use: Generate certificate after quiz pass
 * Usually called from quiz.php, not accessed directly
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
$courseId = (int) get('course_id', 0);
$userId = Session::userId();

if ($courseId <= 0) {
    jsonError('Invalid course ID');
}

// Check if user has completed the course (passed quiz)
// This should normally be done in quiz.php, but this is a backup
$stmt = $pdo->prepare("
    SELECT ce.*, c.title 
    FROM course_enrollments ce
    JOIN courses c ON ce.course_id = c.course_id
    WHERE ce.user_id = ? AND ce.course_id = ? AND ce.progress >= 100
");
$stmt->execute([$userId, $courseId]);
$enrollment = $stmt->fetch();

if (!$enrollment) {
    jsonError('You must complete the course first');
}

// Check if certificate already exists
$stmt = $pdo->prepare("SELECT * FROM certificates WHERE user_id = ? AND course_id = ?");
$stmt->execute([$userId, $courseId]);
$existingCert = $stmt->fetch();

if ($existingCert) {
    jsonSuccess([
        'cert_code' => $existingCert['cert_code'],
        'message' => 'Certificate already exists'
    ]);
}

// Generate new certificate
$certCode = 'JMT-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(4)));

$stmt = $pdo->prepare("
    INSERT INTO certificates (cert_code, user_id, course_id, issued_at) 
    VALUES (?, ?, ?, NOW())
");
$stmt->execute([$certCode, $userId, $courseId]);

// Log activity
Security::logActivity($userId, 'certificate_issued', 'Certificate: ' . $certCode);

// Send notification
sendNotification($userId, 'certificate', 
    'Certificate Earned: ' . $enrollment['title'],
    'Congratulations! Your certificate is ready for download.',
    '/certificates'
);

// Attempt to generate and save the PDF to uploads/certificates/<cert_code>.pdf
$pdfSaved = false;
$generatedPdfUrl = '';
$uploadsDir = __DIR__ . '/../uploads/certificates';
if (!is_dir($uploadsDir)) {
    @mkdir($uploadsDir, 0755, true);
}
$fpdfPath = __DIR__ . '/../libs/fpdf/fpdf.php';
if (file_exists($fpdfPath)) {
    require_once $fpdfPath;

    // Get user info
    $stmt = $pdo->prepare("SELECT full_name FROM users WHERE user_id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    // Create PDF
    class GenCertificatePDF extends FPDF {
        function Header() {}
        function Footer() {}
    }

    $pdf = new GenCertificatePDF('L', 'mm', 'A4');
    $pdf->AddPage();
    $pdf->SetAutoPageBreak(false);

    // Background
    $pdf->SetFillColor(249, 250, 251);
    $pdf->Rect(0, 0, 297, 210, 'F');

    // White certificate area
    $pdf->SetFillColor(255, 255, 255);
    $pdf->Rect(15, 15, 267, 180, 'F');

    // Border
    $pdf->SetDrawColor(13, 71, 161);
    $pdf->SetLineWidth(2);
    $pdf->Rect(20, 20, 257, 170);

    // Header
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->SetTextColor(100, 100, 100);
    $pdf->SetXY(0, 35);
    $pdf->Cell(297, 10, 'CERTIFICATE OF COMPLETION', 0, 1, 'C');

    // Recipient
    $pdf->SetFont('Arial', '', 12);
    $pdf->SetXY(0, 55);
    $pdf->Cell(297, 8, 'This is to certify that', 0, 1, 'C');

    $pdf->SetFont('Arial', 'B', 28);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetXY(0, 68);
    $pdf->Cell(297, 15, utf8_decode($user['full_name'] ?? 'Participant'), 0, 1, 'C');

    // Course title
    $pdf->SetFont('Arial', '', 12);
    $pdf->SetXY(0, 92);
    $pdf->Cell(297, 8, 'has successfully completed the course', 0, 1, 'C');

    $pdf->SetFont('Arial', 'B', 18);
    $pdf->SetTextColor(13, 71, 161);
    $pdf->SetXY(0, 105);
    $courseTitle = '"' . $enrollment['title'] . '"';
    $pdf->Cell(297, 12, utf8_decode($courseTitle), 0, 1, 'C');

    // Issue date and code
    $pdf->SetFont('Arial', '', 11);
    $pdf->SetTextColor(100, 100, 100);
    $pdf->SetXY(0, 125);
    $pdf->Cell(297, 8, 'Issued on ' . date('F d, Y'), 0, 1, 'C');

    $pdf->SetFont('Arial', 'B', 10);
    $pdf->SetXY(0, 145);
    $pdf->Cell(297, 8, 'Certificate ID: ' . $certCode, 0, 1, 'C');

    // Footer brand
    $pdf->SetFont('Arial', 'B', 14);
    $pdf->SetTextColor(13, 71, 161);
    $pdf->SetXY(0, 170);
    $pdf->Cell(297, 10, SITE_NAME, 0, 1, 'C');

    // Save PDF
    $pdfPath = $uploadsDir . '/' . $certCode . '.pdf';
    try {
        $pdf->Output('F', $pdfPath);
        $pdfSaved = file_exists($pdfPath);
        if ($pdfSaved) {
            $generatedPdfUrl = '/uploads/certificates/' . $certCode . '.pdf';
        }
    } catch (Exception $e) {
        // ignore PDF generation errors for now
    }
}

if (Security::isAjax()) {
    $response = [
        'cert_code' => $certCode,
        'download_url' => '/certificates/download.php?code=' . $certCode,
        'view_url' => '/certificates/view.php?code=' . $certCode
    ];
    if ($pdfSaved) {
        $response['pdf_url'] = $generatedPdfUrl;
    }

    jsonSuccess($response, 'Certificate generated successfully!');
} else {
    Session::flash('success', 'Certificate generated! Code: ' . $certCode);
    // Redirect to download so user can get the PDF immediately
    redirect('/jobmington/certificates/download.php?code=' . $certCode);
}
?>