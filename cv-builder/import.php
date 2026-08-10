<?php
/**
 * JOBMINGTON - CV Import Handler
 * Supports: PDF, DOCX, LinkedIn Data Export (ZIP)
 * Uses Andika AI (Gemini) to extract structured CV data
 */

define('JOBMINGTON', true);
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/session.php';

header('Content-Type: application/json');

Session::start();
if (!Session::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Check if file was uploaded
if (!isset($_FILES['document'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No file uploaded']);
    exit;
}

$file = $_FILES['document'];
$maxSize = 10 * 1024 * 1024; // 10MB for LinkedIn exports

// Validate file
if ($file['error'] !== UPLOAD_ERR_OK) {
    $errorMessages = [
        UPLOAD_ERR_INI_SIZE => 'File exceeds server limit',
        UPLOAD_ERR_FORM_SIZE => 'File exceeds form limit',
        UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
        UPLOAD_ERR_NO_FILE => 'No file was uploaded',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
    ];
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $errorMessages[$file['error']] ?? 'File upload error']);
    exit;
}

if ($file['size'] > $maxSize) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'File size exceeds 10MB limit']);
    exit;
}

// Detect file type
$extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

// Process based on file type
$extractedText = '';
$importType = 'document';

try {
    if ($extension === 'pdf') {
        $extractedText = extractTextFromPDF($file['tmp_name']);
        $importType = 'pdf';
    } elseif ($extension === 'docx') {
        $extractedText = extractTextFromDOCX($file['tmp_name']);
        $importType = 'docx';
    } elseif ($extension === 'zip') {
        $extractedText = extractTextFromLinkedInZip($file['tmp_name']);
        $importType = 'linkedin';
    } else {
        throw new Exception('Unsupported file type. Please upload PDF, DOCX, or LinkedIn ZIP export.');
    }
    
    if (empty(trim($extractedText))) {
        throw new Exception('Could not extract text from document. Please ensure the file contains readable text.');
    }
    
    // Use AI to parse the extracted text into structured CV data
    $cvData = parseWithAI($extractedText, $importType);
    
    if (!$cvData) {
        throw new Exception('Could not parse document content. Please try a different file.');
    }
    
    // Create new CV in database
    $pdo = db();
    $userId = Session::userId();
    
    // Create CV profile
    $stmt = $pdo->prepare("
        INSERT INTO cv_profiles (user_id, title, template, full_name, email, phone, location, headline, summary, created_at, updated_at)
        VALUES (?, ?, 'obsidian', ?, ?, ?, ?, ?, ?, NOW(), NOW())
    ");
    $importedName = trim((string) ($cvData['name'] ?? ''));
    if ($importedName === '') {
        $importedName = trim((string) ($_SESSION['full_name'] ?? ''));
    }

    $importedEmail = trim((string) ($cvData['email'] ?? ''));
    if ($importedEmail === '') {
        $importedEmail = trim((string) ($_SESSION['email'] ?? ''));
    }

    $titleBase = $importedName !== '' ? $importedName : trim((string) ($cvData['headline'] ?? ''));
    if ($titleBase === '') {
        $titleBase = 'Imported CV';
    }

    $stmt->execute([
        $userId,
        substr($titleBase, 0, 80) . ' - CV',
        $importedName,
        $importedEmail,
        $cvData['phone'] ?? '',
        $cvData['location'] ?? '',
        $cvData['headline'] ?? '',
        $cvData['summary'] ?? ''
    ]);
    $cvId = $pdo->lastInsertId();
    
    // Insert experiences
    if (!empty($cvData['experience'])) {
        $stmtExp = $pdo->prepare("
            INSERT INTO cv_experience (cv_id, company, job_title, start_date, end_date, is_current, description)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        foreach ($cvData['experience'] as $exp) {
            $startDate = parseDate($exp['start_date'] ?? '');
            $endDate = parseDate($exp['end_date'] ?? '');
            
            $stmtExp->execute([
                $cvId,
                $exp['company'] ?? '',
                $exp['title'] ?? '',
                $startDate,
                $endDate,
                !empty($exp['is_current']) ? 1 : 0,
                $exp['description'] ?? ''
            ]);
        }
    }
    
    // Insert education
    if (!empty($cvData['education'])) {
        $stmtEdu = $pdo->prepare("
            INSERT INTO cv_education (cv_id, institution, degree, start_date, end_date, description)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        foreach ($cvData['education'] as $edu) {
            $startDate = parseDate($edu['start_date'] ?? '');
            $endDate = parseDate($edu['end_date'] ?? '');
            
            $stmtEdu->execute([
                $cvId,
                $edu['institution'] ?? '',
                $edu['degree'] ?? '',
                $startDate,
                $endDate,
                $edu['field'] ?? ''
            ]);
        }
    }
    
    // Insert skills
    if (!empty($cvData['skills'])) {
        $stmtSkill = $pdo->prepare("
            INSERT INTO cv_skills (cv_id, skill_name)
            VALUES (?, ?)
        ");
        foreach ($cvData['skills'] as $skill) {
            if (!empty($skill) && is_string($skill)) {
                $stmtSkill->execute([$cvId, trim($skill)]);
            }
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'CV imported successfully! Redirecting to editor...',
        'cv_id' => $cvId,
        'redirect' => SITE_URL . '/cv-builder/editor-complete.php?id=' . $cvId,
        'data' => [
            'name' => $cvData['name'] ?? '',
            'experience_count' => count($cvData['experience'] ?? []),
            'education_count' => count($cvData['education'] ?? []),
            'skills_count' => count($cvData['skills'] ?? [])
        ]
    ]);
    
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

/**
 * Parse various date formats to YYYY-MM-DD
 */
function parseDate($dateStr) {
    if (empty($dateStr)) return null;
    
    $dateStr = trim($dateStr);
    
    // Already in good format
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateStr)) {
        return $dateStr;
    }
    
    // YYYY-MM format
    if (preg_match('/^(\d{4})-(\d{2})$/', $dateStr, $m)) {
        return $m[1] . '-' . $m[2] . '-01';
    }
    
    // Try common formats
    $formats = ['Y-m-d', 'Y/m/d', 'd/m/Y', 'm/d/Y', 'M Y', 'F Y', 'Y'];
    foreach ($formats as $format) {
        $date = DateTime::createFromFormat($format, $dateStr);
        if ($date) {
            return $date->format('Y-m-d');
        }
    }
    
    // Just year
    if (preg_match('/^(\d{4})$/', $dateStr, $m)) {
        return $m[1] . '-01-01';
    }
    
    return null;
}

function jm_zip_uint16(string $content, int $offset): int {
    if ($offset < 0 || $offset + 2 > strlen($content)) {
        return 0;
    }

    $value = unpack('v', substr($content, $offset, 2));
    return (int) ($value[1] ?? 0);
}

function jm_zip_uint32(string $content, int $offset): int {
    if ($offset < 0 || $offset + 4 > strlen($content)) {
        return 0;
    }

    $value = unpack('V', substr($content, $offset, 4));
    return (int) ($value[1] ?? 0);
}

function jm_zip_normalize_name(string $name): string {
    return strtolower(str_replace('\\', '/', ltrim($name, '/')));
}

function jm_zip_entry_matches(string $name, array $wantedNames): bool {
    $normalized = jm_zip_normalize_name($name);
    $basename = basename($normalized);

    foreach ($wantedNames as $wantedName) {
        $wanted = jm_zip_normalize_name($wantedName);
        if ($normalized === $wanted || $basename === basename($wanted)) {
            return true;
        }
    }

    return false;
}

function jm_zip_extract_local_entry(string $content, int $localOffset, int $compressedSize, int $method, int $flags): ?string {
    if (($flags & 1) === 1) {
        throw new Exception('This ZIP file is encrypted. Please upload an unprotected export.');
    }

    if (substr($content, $localOffset, 4) !== "PK\x03\x04") {
        return null;
    }

    $nameLength = jm_zip_uint16($content, $localOffset + 26);
    $extraLength = jm_zip_uint16($content, $localOffset + 28);
    $dataOffset = $localOffset + 30 + $nameLength + $extraLength;
    $compressed = substr($content, $dataOffset, $compressedSize);

    if ($method === 0) {
        return $compressed;
    }

    if ($method === 8) {
        $decoded = @gzinflate($compressed);
        if ($decoded !== false) {
            return $decoded;
        }
    }

    throw new Exception('Could not read the ZIP contents. Please try another file or enable PHP zip support on the server.');
}

function jm_zip_read_entry(string $filepath, array $wantedNames): ?string {
    if (class_exists('ZipArchive')) {
        $zip = new ZipArchive();
        if ($zip->open($filepath) === true) {
            foreach ($wantedNames as $wantedName) {
                $content = $zip->getFromName($wantedName);
                if ($content !== false) {
                    $zip->close();
                    return $content;
                }
            }

            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                $entryName = $stat['name'] ?? '';
                if ($entryName !== '' && jm_zip_entry_matches($entryName, $wantedNames)) {
                    $content = $zip->getFromIndex($i);
                    $zip->close();
                    return $content !== false ? $content : null;
                }
            }

            $zip->close();
        }
    }

    $content = @file_get_contents($filepath);
    if ($content === false || strlen($content) < 22) {
        return null;
    }

    $searchLength = min(strlen($content), 65557);
    $tail = substr($content, -$searchLength);
    $eocdInTail = strrpos($tail, "PK\x05\x06");
    if ($eocdInTail === false) {
        return null;
    }

    $eocdOffset = strlen($content) - $searchLength + $eocdInTail;
    $totalEntries = jm_zip_uint16($content, $eocdOffset + 10);
    $directoryOffset = jm_zip_uint32($content, $eocdOffset + 16);
    $position = $directoryOffset;

    for ($i = 0; $i < $totalEntries; $i++) {
        if (substr($content, $position, 4) !== "PK\x01\x02") {
            break;
        }

        $flags = jm_zip_uint16($content, $position + 8);
        $method = jm_zip_uint16($content, $position + 10);
        $compressedSize = jm_zip_uint32($content, $position + 20);
        $nameLength = jm_zip_uint16($content, $position + 28);
        $extraLength = jm_zip_uint16($content, $position + 30);
        $commentLength = jm_zip_uint16($content, $position + 32);
        $localOffset = jm_zip_uint32($content, $position + 42);
        $entryName = substr($content, $position + 46, $nameLength);

        if (jm_zip_entry_matches($entryName, $wantedNames)) {
            return jm_zip_extract_local_entry($content, $localOffset, $compressedSize, $method, $flags);
        }

        $position += 46 + $nameLength + $extraLength + $commentLength;
    }

    return null;
}

/**
 * Extract text from PDF using basic parsing
 */
function extractTextFromPDF($filepath) {
    $content = file_get_contents($filepath);
    $text = '';
    
    // Method 1: Try to extract text between stream/endstream
    if (preg_match_all('/stream\s*(.+?)\s*endstream/s', $content, $matches)) {
        foreach ($matches[1] as $stream) {
            // Try to decode if compressed
            $decoded = @gzuncompress($stream);
            if ($decoded) {
                $stream = $decoded;
            }
            
            // Extract text operators (Tj, TJ, ')
            if (preg_match_all('/\(([^)]+)\)\s*Tj/s', $stream, $textMatches)) {
                $text .= implode(' ', $textMatches[1]) . "\n";
            }
            if (preg_match_all('/\[(.*?)\]\s*TJ/s', $stream, $textMatches)) {
                foreach ($textMatches[1] as $tjContent) {
                    if (preg_match_all('/\(([^)]+)\)/', $tjContent, $innerMatches)) {
                        $text .= implode('', $innerMatches[1]) . ' ';
                    }
                }
                $text .= "\n";
            }
        }
    }
    
    // Method 2: Extract BT...ET blocks
    if (empty(trim($text))) {
        if (preg_match_all('/BT\s*(.+?)\s*ET/s', $content, $btMatches)) {
            foreach ($btMatches[1] as $bt) {
                if (preg_match_all('/\(([^)]+)\)/', $bt, $textMatches)) {
                    $text .= implode(' ', $textMatches[1]) . "\n";
                }
            }
        }
    }
    
    // Clean up the text
    $text = preg_replace('/[^\x20-\x7E\n]/', '', $text);
    $text = preg_replace('/\s+/', ' ', $text);
    $text = trim($text);
    
    // If still no text, extract any readable content
    if (empty($text) || strlen($text) < 50) {
        $text = preg_replace('/[^\x20-\x7E\s]/', '', $content);
        $text = preg_replace('/\s+/', ' ', $text);
        $text = substr($text, 0, 10000); // Limit
    }
    
    return $text;
}

/**
 * Extract text from DOCX (ZIP with XML inside)
 */
function extractTextFromDOCX($filepath) {
    $text = '';

    // Main document content is in word/document.xml
    $content = jm_zip_read_entry($filepath, ['word/document.xml']);
    if ($content === null) {
        throw new Exception('Could not read this DOCX file. Please upload a valid Word document.');
    }

    // Preserve paragraph breaks before falling back to namespace parsing.
    if (preg_match_all('/<w:p\b[^>]*>(.*?)<\/w:p>/s', $content, $paragraphMatches)) {
        foreach ($paragraphMatches[1] as $paragraph) {
            $paragraphText = trim(html_entity_decode(strip_tags($paragraph), ENT_QUOTES | ENT_XML1, 'UTF-8'));
            if ($paragraphText !== '') {
                $text .= $paragraphText . "\n";
            }
        }
    }

    // Parse XML and extract text
    libxml_use_internal_errors(true);
    $xml = simplexml_load_string($content);

    if ($xml && trim($text) === '') {
        // Register namespaces
        $namespaces = $xml->getNamespaces(true);
        if (isset($namespaces['w'])) {
            $xml->registerXPathNamespace('w', $namespaces['w']);
            $textNodes = $xml->xpath('//w:t');
            foreach ($textNodes as $node) {
                $text .= (string)$node . ' ';
            }
        }
    }

    // Fallback: strip XML tags
    if (empty(trim($text))) {
        $text = strip_tags($content);
    }

    libxml_clear_errors();

    return trim($text);
}

/**
 * Extract data from LinkedIn ZIP export
 */
function extractTextFromLinkedInZip($filepath) {
    $text = '';

    // Look for Profile.csv
    $profile = jm_zip_read_entry($filepath, ['Profile.csv']);
    if ($profile) {
        $lines = str_getcsv($profile, "\n");
        if (count($lines) > 1) {
            $headers = str_getcsv($lines[0]);
            $values = str_getcsv($lines[1]);
            if (count($headers) === count($values)) {
                $profileData = array_combine($headers, $values);
                $text .= "PROFILE:\n";
                $text .= "Name: " . trim(($profileData['First Name'] ?? '') . ' ' . ($profileData['Last Name'] ?? '')) . "\n";
                $text .= "Headline: " . ($profileData['Headline'] ?? '') . "\n";
                $text .= "Summary: " . ($profileData['Summary'] ?? '') . "\n";
                $text .= "Location: " . ($profileData['Geo Location'] ?? $profileData['Location'] ?? '') . "\n\n";
            }
        }
    }
    
    // Look for Email Addresses.csv
    $emails = jm_zip_read_entry($filepath, ['Email Addresses.csv']);
    if ($emails) {
        $lines = str_getcsv($emails, "\n");
        if (count($lines) > 1) {
            $values = str_getcsv($lines[1]);
            $text .= "Email: " . ($values[0] ?? '') . "\n\n";
        }
    }
    
    // Phone Numbers.csv
    $phones = jm_zip_read_entry($filepath, ['PhoneNumbers.csv', 'Phone Numbers.csv']);
    if ($phones) {
        $lines = str_getcsv($phones, "\n");
        if (count($lines) > 1) {
            $values = str_getcsv($lines[1]);
            $text .= "Phone: " . ($values[0] ?? '') . "\n\n";
        }
    }
    
    // Look for Positions.csv (work experience)
    $positions = jm_zip_read_entry($filepath, ['Positions.csv']);
    if ($positions) {
        $lines = str_getcsv($positions, "\n");
        if (count($lines) > 1) {
            $headers = str_getcsv($lines[0]);
            $text .= "WORK EXPERIENCE:\n";
            for ($i = 1; $i < count($lines); $i++) {
                if (empty(trim($lines[$i]))) continue;
                $values = str_getcsv($lines[$i]);
                if (count($values) >= count($headers)) {
                    $pos = @array_combine($headers, $values);
                    if ($pos) {
                        $text .= "- " . ($pos['Title'] ?? '') . " at " . ($pos['Company Name'] ?? '') . "\n";
                        $text .= "  Started: " . ($pos['Started On'] ?? '') . "\n";
                        $text .= "  Ended: " . ($pos['Finished On'] ?? 'Present') . "\n";
                        $text .= "  Description: " . ($pos['Description'] ?? '') . "\n\n";
                    }
                }
            }
        }
    }
    
    // Look for Education.csv
    $education = jm_zip_read_entry($filepath, ['Education.csv']);
    if ($education) {
        $lines = str_getcsv($education, "\n");
        if (count($lines) > 1) {
            $headers = str_getcsv($lines[0]);
            $text .= "EDUCATION:\n";
            for ($i = 1; $i < count($lines); $i++) {
                if (empty(trim($lines[$i]))) continue;
                $values = str_getcsv($lines[$i]);
                if (count($values) >= count($headers)) {
                    $edu = @array_combine($headers, $values);
                    if ($edu) {
                        $text .= "- " . ($edu['Degree Name'] ?? $edu['Degree'] ?? '') . " in " . ($edu['Field of Study'] ?? $edu['Field Of Study'] ?? '') . "\n";
                        $text .= "  Institution: " . ($edu['School Name'] ?? $edu['School'] ?? '') . "\n";
                        $text .= "  " . ($edu['Start Date'] ?? '') . " - " . ($edu['End Date'] ?? '') . "\n\n";
                    }
                }
            }
        }
    }
    
    // Look for Skills.csv
    $skills = jm_zip_read_entry($filepath, ['Skills.csv']);
    if ($skills) {
        $lines = str_getcsv($skills, "\n");
        if (count($lines) > 1) {
            $text .= "SKILLS:\n";
            for ($i = 1; $i < count($lines); $i++) {
                $values = str_getcsv($lines[$i]);
                if (!empty($values[0])) {
                    $text .= "- " . trim($values[0]) . "\n";
                }
            }
            $text .= "\n";
        }
    }
    
    // Look for Certifications.csv
    $certs = jm_zip_read_entry($filepath, ['Certifications.csv']);
    if ($certs) {
        $lines = str_getcsv($certs, "\n");
        if (count($lines) > 1) {
            $headers = str_getcsv($lines[0]);
            $text .= "CERTIFICATIONS:\n";
            for ($i = 1; $i < count($lines); $i++) {
                if (empty(trim($lines[$i]))) continue;
                $values = str_getcsv($lines[$i]);
                if (count($values) >= count($headers)) {
                    $cert = @array_combine($headers, $values);
                    if ($cert) {
                        $text .= "- " . ($cert['Name'] ?? '') . " from " . ($cert['Authority'] ?? '') . "\n";
                    }
                }
            }
        }
    }

    if (empty(trim($text))) {
        throw new Exception('Could not find LinkedIn data files in ZIP. Please ensure you uploaded the correct LinkedIn data export.');
    }
    
    return $text;
}

/**
 * Use Gemini AI to parse extracted text into structured CV data
 */
function parseWithAI($text, $importType) {
    $apiKey = getenv('GEMINI_API_KEY');
    
    // Truncate text if too long
    if (strlen($text) > 15000) {
        $text = substr($text, 0, 15000) . '...';
    }
    
    $prompt = <<<PROMPT
You are a CV/Resume parser. Extract structured data from the following {$importType} content and return it as valid JSON.

Extract these fields:
- name: Full name (string)
- email: Email address (string)
- phone: Phone number (string)
- location: City/Location (string)
- headline: Professional title/headline (string)
- summary: Professional summary - 2-3 sentences (string)
- experience: Array of work experiences, each with: company (string), title (string), start_date (string YYYY-MM-DD or YYYY-MM), end_date (string or null if current), is_current (boolean), description (string)
- education: Array of education entries, each with: institution (string), degree (string), field (string), start_date (string), end_date (string)
- skills: Array of skill names - strings only, not objects (array of strings)

IMPORTANT RULES:
- Return ONLY valid JSON, no markdown code blocks, no explanation
- Use empty string "" for missing text values
- Use empty array [] for missing arrays
- Use null only for end_date when position is current
- Dates should be YYYY-MM-DD or YYYY-MM format
- Skills MUST be an array of strings like ["JavaScript", "Python", "Leadership"]

Content to parse:
---
{$text}
---

Return only the JSON object:
PROMPT;

    // If no API key, use basic parsing
    if (empty($apiKey) || $apiKey === 'YOUR_GEMINI_API_KEY_HERE') {
        return basicParse($text, $importType);
    }
    
    $payload = [
        'contents' => [
            ['role' => 'user', 'parts' => [['text' => $prompt]]]
        ],
        'generationConfig' => [
            'temperature' => 0.1,
            'maxOutputTokens' => 4096,
        ]
    ];
    
    $apiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=' . $apiKey;
    
    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($curlError || $httpCode !== 200) {
        error_log("CV Import AI Error: " . ($curlError ?: "HTTP $httpCode"));
        return basicParse($text, $importType);
    }
    
    $data = json_decode($response, true);
    
    if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
        $jsonText = $data['candidates'][0]['content']['parts'][0]['text'];
        
        // Clean up the response (remove markdown code blocks if present)
        $jsonText = preg_replace('/```json\s*/i', '', $jsonText);
        $jsonText = preg_replace('/```\s*/', '', $jsonText);
        $jsonText = trim($jsonText);
        
        $parsed = json_decode($jsonText, true);
        
        if ($parsed && is_array($parsed)) {
            return $parsed;
        }
    }
    
    // Fallback to basic parsing
    return basicParse($text, $importType);
}

/**
 * Basic parsing without AI (fallback)
 */
function basicParse($text, $importType) {
    $data = [
        'name' => '',
        'email' => '',
        'phone' => '',
        'location' => '',
        'headline' => '',
        'summary' => '',
        'experience' => [],
        'education' => [],
        'skills' => []
    ];
    
    // Extract email
    if (preg_match('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $text, $matches)) {
        $data['email'] = $matches[0];
    }
    
    // Extract phone (various formats)
    if (preg_match('/(?:\+?\d{1,3}[-.\s]?)?\(?\d{2,4}\)?[-.\s]?\d{3,4}[-.\s]?\d{3,4}/', $text, $matches)) {
        $data['phone'] = $matches[0];
    }

    $lines = preg_split('/\r\n|\r|\n/', trim($text));
    $lines = array_values(array_filter(array_map(static function ($line) {
        $line = trim(preg_replace('/\s+/', ' ', $line));
        return $line;
    }, $lines)));

    $sectionWords = '/^(summary|profile|experience|employment|education|skills|certifications|projects|contact)$/i';
    foreach ($lines as $line) {
        if (strlen($line) > 80 || preg_match($sectionWords, $line) || str_contains($line, '@')) {
            continue;
        }
        if (preg_match('/\d{3,}/', $line)) {
            continue;
        }
        $data['name'] = $line;
        break;
    }

    foreach ($lines as $line) {
        if ($line === $data['name'] || str_contains($line, '@') || preg_match($sectionWords, $line)) {
            continue;
        }
        if (strlen($line) >= 8 && strlen($line) <= 140) {
            $data['headline'] = $line;
            break;
        }
    }

    if (preg_match('/(?:summary|profile)\s*:?\s*(.+?)(?:\n\s*(?:experience|employment|education|skills|projects)\b|$)/is', $text, $matches)) {
        $data['summary'] = trim(preg_replace('/\s+/', ' ', $matches[1]));
    } else {
        $summaryLines = [];
        foreach ($lines as $line) {
            if ($line === $data['name'] || $line === $data['headline'] || str_contains($line, '@') || preg_match($sectionWords, $line)) {
                continue;
            }
            if (strlen($line) > 55) {
                $summaryLines[] = $line;
            }
            if (count($summaryLines) >= 2) {
                break;
            }
        }
        $data['summary'] = trim(implode(' ', $summaryLines));
    }

    if (preg_match('/skills\s*:?\s*(.+?)(?:\n\s*(?:experience|employment|education|certifications|projects)\b|$)/is', $text, $matches)) {
        $skillsText = preg_replace('/[;|]/', ',', $matches[1]);
        $skills = preg_split('/,|\n|•|- /', $skillsText);
        $data['skills'] = array_values(array_filter(array_map('trim', $skills)));
    }

    if (empty($data['skills'])) {
        $knownSkills = ['JavaScript', 'PHP', 'Python', 'React', 'Laravel', 'MySQL', 'SQL', 'HTML', 'CSS', 'Figma', 'UX research', 'UI design', 'Project management', 'Leadership', 'Communication', 'Sales', 'Marketing', 'Data analysis', 'Excel', 'Customer service'];
        foreach ($knownSkills as $skill) {
            if (stripos($text, $skill) !== false) {
                $data['skills'][] = $skill;
            }
        }
    }

    $data['skills'] = array_values(array_unique(array_slice($data['skills'], 0, 24)));
    
    // For LinkedIn imports, extract structured data
    if ($importType === 'linkedin') {
        // Name
        if (preg_match('/Name:\s*(.+?)(?:\n|$)/i', $text, $matches)) {
            $data['name'] = trim($matches[1]);
        }
        
        // Headline
        if (preg_match('/Headline:\s*(.+?)(?:\n|$)/i', $text, $matches)) {
            $data['headline'] = trim($matches[1]);
        }
        
        // Summary
        if (preg_match('/Summary:\s*(.+?)(?:\n\n|Location:|WORK|EDUCATION|$)/is', $text, $matches)) {
            $data['summary'] = trim($matches[1]);
        }
        
        // Location
        if (preg_match('/Location:\s*(.+?)(?:\n|$)/i', $text, $matches)) {
            $data['location'] = trim($matches[1]);
        }
        
        // Work Experience
        if (preg_match('/WORK EXPERIENCE:\s*(.+?)(?:EDUCATION:|SKILLS:|CERTIFICATIONS:|$)/is', $text, $matches)) {
            $expText = $matches[1];
            if (preg_match_all('/- (.+?) at (.+?)\n\s*Started: (.+?)\n\s*Ended: (.+?)\n\s*Description: (.*?)(?=\n- |\n\n|$)/is', $expText, $expMatches, PREG_SET_ORDER)) {
                foreach ($expMatches as $exp) {
                    $data['experience'][] = [
                        'title' => trim($exp[1]),
                        'company' => trim($exp[2]),
                        'start_date' => trim($exp[3]),
                        'end_date' => trim($exp[4]) === 'Present' ? null : trim($exp[4]),
                        'is_current' => trim($exp[4]) === 'Present',
                        'description' => trim($exp[5])
                    ];
                }
            }
        }
        
        // Education
        if (preg_match('/EDUCATION:\s*(.+?)(?:SKILLS:|CERTIFICATIONS:|$)/is', $text, $matches)) {
            $eduText = $matches[1];
            if (preg_match_all('/- (.+?) in (.+?)\n\s*Institution: (.+?)\n\s*(.+?) - (.+?)(?=\n- |\n\n|$)/is', $eduText, $eduMatches, PREG_SET_ORDER)) {
                foreach ($eduMatches as $edu) {
                    $data['education'][] = [
                        'degree' => trim($edu[1]),
                        'field' => trim($edu[2]),
                        'institution' => trim($edu[3]),
                        'start_date' => trim($edu[4]),
                        'end_date' => trim($edu[5])
                    ];
                }
            }
        }
        
        // Skills
        if (preg_match('/SKILLS:\s*(.+?)(?:CERTIFICATIONS:|$)/is', $text, $matches)) {
            $skillsText = $matches[1];
            if (preg_match_all('/- (.+?)(?:\n|$)/', $skillsText, $skillMatches)) {
                $data['skills'] = array_map('trim', array_slice($skillMatches[1], 0, 20));
            }
        }
    }
    
    return $data;
}
