<?php
/**
 * JOBMINGTON - CV File Extractor
 * Accepts PDF or DOCX upload, extracts plain text, detects Jobmington-built CVs.
 * Works on Windows (XAMPP dev) and Linux (production VPS).
 */

define('JOBMINGTON', true);
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/tools.php';

header('Content-Type: application/json');
Session::start();

if (!Session::isLoggedIn()) {
    jsonError('Please log in.', 401);
}
// Both the builder and the optimizer extract CVs; either one is enough.
jm_require_tool_api('cv_builder', 'cv_roast');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('POST required.', 405);
}
if (empty($_FILES['cv_file']) || $_FILES['cv_file']['error'] !== UPLOAD_ERR_OK) {
    $errCode = $_FILES['cv_file']['error'] ?? -1;
    jsonError('Upload failed. ' . (
        $errCode === UPLOAD_ERR_INI_SIZE || $errCode === UPLOAD_ERR_FORM_SIZE
            ? 'File exceeds the 5 MB limit.'
            : 'Error code: ' . $errCode
    ));
}

$file    = $_FILES['cv_file'];
$maxSize = 5 * 1024 * 1024;

if ($file['size'] > $maxSize) {
    jsonError('File too large. Maximum size is 5 MB.');
}

// ── Determine file type by extension + MIME ───────────────────────
$ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime  = $finfo->file($file['tmp_name']);

$isPdf  = ($ext === 'pdf' || $mime === 'application/pdf');
$isDocx = ($ext === 'docx' || in_array($mime, [
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/zip',
    'application/x-zip-compressed',
]));

// Reject anything that's neither
if (!$isPdf && !$isDocx) {
    jsonError('Only PDF and DOCX files are accepted. Detected: ' . $mime);
}
// Reject if extension and MIME completely disagree
if ($ext === 'pdf' && $isPdf === false) {
    jsonError('File appears corrupt — the content does not match a PDF.');
}

// ── Extract text ──────────────────────────────────────────────────
$text    = '';
$method  = 'unknown';

if ($isPdf) {
    [$text, $method] = jm_extract_pdf($file['tmp_name']);
} else {
    [$text, $method] = jm_extract_docx($file['tmp_name']);
}

// ── Validate extracted text quality ──────────────────────────────
$text    = trim($text);
$cleaned = preg_replace('/\s+/', ' ', $text);
$words   = array_filter(explode(' ', $cleaned), fn($w) => strlen($w) >= 3);

if (count($words) < 20) {
    if ($isPdf) {
        jsonError(
            'This PDF appears to be image-based (a scan or screenshot). ' .
            'We can only read text-based PDFs. ' .
            'Please export your CV from Word, Google Docs, or our CV Builder as a PDF and try again.'
        );
    } else {
        jsonError('Could not read this DOCX. Make sure the file is not password-protected or corrupted.');
    }
}

// ── Detect Jobmington CV ──────────────────────────────────────────
$isJobmington = jm_detect_jobmington($text, $file['tmp_name'], $isPdf);

// Trim to keep the AI prompt manageable
$trimmedText = mb_substr($text, 0, 6000);

jsonSuccess([
    'text'          => $trimmedText,
    'is_jobmington' => $isJobmington,
    'filename'      => basename($file['name']),
    'word_count'    => count($words),
    'method'        => $method,
]);

// ─────────────────────────────────────────────────────────────────
// EXTRACTION FUNCTIONS
// ─────────────────────────────────────────────────────────────────

function jm_extract_pdf(string $path): array {
    $isWin = (PHP_OS_FAMILY === 'Windows');
    $null  = $isWin ? '2>nul' : '2>/dev/null';

    if (jm_shell_available()) {
        // On Linux: use which. On Windows: only use pdftotext if it's the real poppler binary
        // (skip Git's broken pdftotext.exe stub)
        if ($isWin) {
            $bin = trim((string) @shell_exec("where pdftotext {$null}"));
            $bin = strtok((string)$bin, "\n");
            $bin = trim((string)$bin);
            // Reject Git's broken stub
            if ($bin && stripos($bin, 'git') !== false) {
                $bin = '';
            }
        } else {
            $bin = trim((string) @shell_exec("which pdftotext {$null}"));
        }

        if ($bin && file_exists($bin)) {
            $safe   = escapeshellarg($path);
            $result = (string) @shell_exec("{$bin} -layout {$safe} - {$null}");
            if (strlen(trim($result)) > 50) {
                return [trim($result), 'pdftotext'];
            }
        }
    }

    // 2. ZipArchive fallback — some PDF generators use zip-based format (rare)
    // 3. Raw PDF stream parser — handles most text-based PDFs
    $text = jm_raw_pdf_extract($path);
    if (!empty(trim($text))) {
        return [trim($text), 'raw-stream'];
    }

    return ['', 'failed'];
}

function jm_extract_docx(string $path): array {
    if (!class_exists('ZipArchive')) {
        return ['', 'zip-unavailable'];
    }

    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        return ['', 'zip-open-failed'];
    }

    $xml = $zip->getFromName('word/document.xml');
    $zip->close();

    if ($xml === false || empty($xml)) {
        return ['', 'docx-no-document-xml'];
    }

    // Insert newlines before paragraph markers
    $xml  = preg_replace('/<w:p[ >]/', "\n<w:p>", $xml);
    $xml  = preg_replace('/<w:br[^>]*\/>/', "\n", $xml);
    $text = strip_tags($xml);
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace('/[ \t]+/', ' ', $text);
    $text = preg_replace('/\n{3,}/', "\n\n", $text);

    return [trim($text), 'docx-xml'];
}

function jm_raw_pdf_extract(string $path): string {
    $raw = @file_get_contents($path);
    if ($raw === false || strlen($raw) < 100) return '';

    $text = '';

    // Step 1: Decompress FlateDecode streams first, then look for BT...ET blocks inside
    $positions = [];
    $offset = 0;
    while (($pos = strpos($raw, 'stream', $offset)) !== false) {
        $start = strpos($raw, "\n", $pos);
        if ($start === false) break;
        $start++;
        $end = strpos($raw, 'endstream', $start);
        if ($end === false) break;
        $chunk  = substr($raw, $start, $end - $start);
        $chunk  = rtrim($chunk, "\r\n");

        // Check if this stream object has FlateDecode filter
        $objBefore = substr($raw, max(0, $pos - 400), 400);
        $isFlate   = (stripos($objBefore, '/FlateDecode') !== false || stripos($objBefore, '/Fl ') !== false);
        $isImage   = (stripos($objBefore, '/DCTDecode') !== false || stripos($objBefore, '/CCITTFax') !== false
                   || stripos($objBefore, '/JBIG2') !== false || stripos($objBefore, '/Subtype /Image') !== false);

        if (!$isImage) {
            $decoded = false;
            if ($isFlate || strlen($chunk) > 200) {
                // Try all common decompressions
                if ($decoded === false && strlen($chunk) >= 2) {
                    $decoded = @gzuncompress($chunk);
                }
                if ($decoded === false) {
                    $decoded = @gzinflate($chunk);
                }
                if ($decoded === false && strlen($chunk) >= 2) {
                    // Try skipping zlib header (some tools omit it)
                    $decoded = @gzinflate(substr($chunk, 2));
                }
            }
            $src = $decoded !== false ? $decoded : ($isFlate ? '' : $chunk);

            // Extract text operators from decompressed content
            if (!empty($src)) {
                // BT...ET blocks
                if (preg_match_all('/BT\s+(.*?)\s+ET/s', $src, $blocks)) {
                    foreach ($blocks[1] as $block) {
                        // Tj: single string
                        if (preg_match_all('/\(((?:[^()\\\\]|\\\\.)*)\)\s*Tj/s', $block, $m)) {
                            foreach ($m[1] as $s) {
                                $d = jm_pdf_unescape($s);
                                if (strlen(trim($d)) >= 1) $text .= $d . ' ';
                            }
                        }
                        // TJ: array
                        if (preg_match_all('/\[(.*?)\]\s*TJ/s', $block, $m)) {
                            foreach ($m[1] as $arr) {
                                if (preg_match_all('/\(((?:[^()\\\\]|\\\\.)*)\)/', $arr, $parts)) {
                                    foreach ($parts[1] as $part) {
                                        $text .= jm_pdf_unescape($part);
                                    }
                                }
                            }
                            $text .= ' ';
                        }
                        // " operator (move and show)
                        if (preg_match_all('/\(((?:[^()\\\\]|\\\\.)*)\)\s*"/s', $block, $m)) {
                            foreach ($m[1] as $s) { $text .= jm_pdf_unescape($s) . ' '; }
                        }
                    }
                }

                // If no BT...ET found but content looks like readable text, extract runs
                if (!$text && !$isImage) {
                    $readable = preg_replace('/[^\x20-\x7E\n]/', ' ', $src);
                    if (preg_match_all('/[A-Za-z][A-Za-z0-9 ,.\-]{10,}/', $readable, $runs)) {
                        foreach ($runs[0] as $run) {
                            $run = trim($run);
                            $alpha = strlen(preg_replace('/[^a-zA-Z ]/', '', $run));
                            if ($alpha / max(strlen($run), 1) > 0.6) {
                                $text .= $run . ' ';
                            }
                        }
                    }
                }
            }
        }
        $offset = $end + 9;
    }

    // Step 2: Also try uncompressed BT...ET in the raw body (for uncompressed PDFs)
    if (strlen($text) < 100) {
        if (preg_match_all('/BT\s+(.*?)\s+ET/s', $raw, $blocks)) {
            foreach ($blocks[1] as $block) {
                if (preg_match_all('/\(((?:[^()\\\\]|\\\\.)*)\)\s*Tj/s', $block, $m)) {
                    foreach ($m[1] as $s) {
                        $d = jm_pdf_unescape($s);
                        if (strlen(trim($d)) >= 1) $text .= $d . ' ';
                    }
                }
            }
        }
    }

    $text = preg_replace('/[^\x20-\x7E\x0A\x0D]/', ' ', $text);
    $text = preg_replace('/\s+/', ' ', $text);
    return trim($text);
}

function jm_pdf_unescape(string $s): string {
    static $map = [
        '\\n' => ' ', '\\r' => ' ', '\\t' => ' ',
        '\\\\' => '\\', '\\(' => '(', '\\)' => ')',
    ];
    return strtr($s, $map);
}

function jm_shell_available(): bool {
    if (!function_exists('shell_exec')) return false;
    $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));
    return !in_array('shell_exec', $disabled);
}

function jm_detect_jobmington(string $text, string $path, bool $isPdf): bool {
    // Check extracted text
    if (stripos($text, 'JOBMINGTON_CV_BUILDER_EXPORT') !== false) return true;
    if (stripos($text, 'jobmington.com') !== false) return true;
    if (stripos($text, 'Created with Jobmington') !== false) return true;
    if (stripos($text, 'Jobmington CV Builder') !== false) return true;

    // Check raw bytes too (catches HTML comments in PDF)
    $raw = @file_get_contents($path);
    if ($raw !== false && stripos($raw, 'JOBMINGTON_CV_BUILDER_EXPORT') !== false) return true;

    return false;
}
