<?php
/**
 * JOBMINGTON - CV Export Tool
 * Download CV in multiple formats (PDF, DOCX)
 */

define('JOBMINGTON', true);
require_once __DIR__ . '/config/env.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/libs/fpdf/fpdf.php';

Session::start();
Session::requireLogin();

$userId = Session::userId();
$format = $_GET['format'] ?? 'pdf'; // pdf or docx

try {
    $db = Database::getInstance();
    
    // Get user data
    $stmt = $db->prepare("SELECT * FROM users WHERE user_id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    // Get CV profile
    $stmt = $db->prepare("SELECT * FROM cv_profiles WHERE user_id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $cv = $stmt->fetch();
    
    if (!$user) {
        throw new Exception("User not found");
    }
    
    // Generate PDF
    if ($format === 'pdf') {
        generatePDF($user, $cv);
    } else {
        generateDOCX($user, $cv);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    die("Error generating CV: " . $e->getMessage());
}

function generatePDF($user, $cv) {
    $pdf = new FPDF();
    $pdf->AddPage();
    $pdf->SetFont('Arial', 'B', 16);
    
    // Header
    $pdf->SetTextColor(59, 130, 246);
    $pdf->Cell(0, 10, $user['full_name'], 0, 1, 'C');
    
    $pdf->SetFont('Arial', '', 10);
    $pdf->SetTextColor(100, 100, 100);
    $pdf->Cell(0, 5, $user['email'] . ' | ' . ($user['phone'] ?? 'N/A'), 0, 1, 'C');
    
    // Sections
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Ln(5);
    
    if ($cv && $cv['headline']) {
        $pdf->Cell(0, 5, 'PROFESSIONAL SUMMARY', 0, 1);
        $pdf->SetFont('Arial', '', 10);
        $pdf->MultiCell(0, 5, $cv['headline']);
        $pdf->Ln(2);
    }
    
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 5, 'CONTACT INFORMATION', 0, 1);
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(0, 5, 'Email: ' . $user['email'], 0, 1);
    $pdf->Cell(0, 5, 'Phone: ' . ($user['phone'] ?? 'Not provided'), 0, 1);
    $pdf->Cell(0, 5, 'Location: ' . ($user['city'] ?? 'Not specified'), 0, 1);
    
    // Output
    $filename = 'CV_' . preg_replace('/[^a-zA-Z0-9]/', '_', $user['full_name']) . '_' . date('Y-m-d') . '.pdf';
    $pdf->Output('D', $filename);
}

function generateDOCX($user, $cv) {
    header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    header('Content-Disposition: attachment; filename="CV_' . preg_replace('/[^a-zA-Z0-9]/', '_', $user['full_name']) . '.docx"');
    
    // Basic DOCX generation (simplified XML-based)
    $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
    $xml .= '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">';
    $xml .= '<w:body>';
    $xml .= '<w:p><w:r><w:t>' . htmlspecialchars($user['full_name']) . '</w:t></w:r></w:p>';
    $xml .= '<w:p><w:r><w:t>' . $user['email'] . '</w:t></w:r></w:p>';
    $xml .= '</w:body>';
    $xml .= '</w:document>';
    
    echo $xml;
}
