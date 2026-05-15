<?php
/**
 * FPDF helper for Jobmington
 * Lightweight helpers to create and output PDFs consistently.
 *
 * PHP 8.0+
 */

declare(strict_types=1);

// Ensure the FPDF library is available
if (!file_exists(__DIR__ . '/fpdf.php')) {
    throw new RuntimeException('FPDF library not found. Please install it in "libs/fpdf/"');
}

require_once __DIR__ . '/fpdf.php';

/**
 * Create a new FPDF instance with sensible defaults.
 *
 * @param string $title
 * @param string $author
 * @return FPDF
 */
function fpdf_create(string $title = '', string $author = 'Jobmington'): FPDF
{
    $pdf = new FPDF();
    if ($title !== '') {
        $pdf->SetTitle($title);
    }
    if ($author !== '') {
        $pdf->SetAuthor($author);
    }

    // Defaults
    $pdf->SetAutoPageBreak(true, 15);
    $pdf->AddPage();
    $pdf->SetFont('Arial', '', 12);

    return $pdf;
}

/**
 * Output the PDF to browser or force download.
 *
 * @param FPDF $pdf
 * @param string $filename
 * @param bool $download
 * @return void
 */
function fpdf_output(FPDF $pdf, string $filename = 'document.pdf', bool $download = false): void
{
    $mode = $download ? 'D' : 'I';
    $pdf->Output($mode, $filename);
}
