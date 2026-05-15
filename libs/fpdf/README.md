# FPDF Library for Jobmington

## Installation

1. Download FPDF from: http://www.fpdf.org/
2. Extract the ZIP and copy the contents to this directory (`libs/fpdf/`).

Required files and folders:

- `fpdf.php` (main library file)
- `font/` directory with font files (`courier.php`, `helvetica.php`, `times.php`, etc.)

## Usage

Basic example:

```php
require_once __DIR__ . '/fpdf.php';

$pdf = new FPDF();
$pdf->AddPage();
$pdf->SetFont('Arial', 'B', 16);
$pdf->Cell(0, 10, 'Hello World!');
$pdf->Output();
```

## Helper

A lightweight helper (`helper.php`) is included to standardize PDF creation and output.

## Notes

- Keep the `font/` directory and `fpdf.php` together in this folder.
- Do not expose this directory directly; an `.htaccess` exists to protect PHP files in servers that support it.
