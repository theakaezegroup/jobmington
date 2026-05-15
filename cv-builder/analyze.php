<?php
/**
 * JOBMINGTON - CV Analysis Handler
 * Runs Andika AI optimization analysis on CV
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

$input = json_decode(file_get_contents('php://input'), true);
$cvId = (int)($input['cv_id'] ?? 0);
$jobDescription = $input['job_description'] ?? '';

if (!$cvId || !$jobDescription) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing CV ID or job description']);
    exit;
}

$pdo = db();
$userId = Session::userId();

// Verify CV ownership
$stmt = $pdo->prepare("SELECT cv_id FROM cv_profiles WHERE cv_id = ? AND user_id = ?");
$stmt->execute([$cvId, $userId]);
if (!$stmt->fetch()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

// Mock analysis - in production, call Andika AI API
$keywords = preg_split('/\s+/', $jobDescription);
$matchedKeywords = array_slice(array_unique($keywords), 0, 5);

echo json_encode([
    'success' => true,
    'message' => 'Analysis complete',
    'data' => [
        'atsScore' => 78,
        'scoreChange' => '+5',
        'matchedKeywords' => $matchedKeywords,
        'recommendations' => [
            'Add more quantifiable achievements',
            'Include industry-specific keywords',
            'Enhance professional summary',
            'Highlight leadership experience'
        ],
        'status' => 'optimized'
    ]
]);
