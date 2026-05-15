<?php
/**
 * JOBMINGTON - CV Delete Handler
 * Handles CV deletion with ownership verification
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

if (!$cvId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing CV ID']);
    exit;
}

$pdo = db();
$userId = Session::userId();

// Verify ownership
$stmt = $pdo->prepare("SELECT cv_id FROM cv_profiles WHERE cv_id = ? AND user_id = ?");
$stmt->execute([$cvId, $userId]);
if (!$stmt->fetch()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

try {
    $pdo->beginTransaction();
    
    // Delete related data first
    $pdo->prepare("DELETE FROM cv_experience WHERE cv_id = ?")->execute([$cvId]);
    $pdo->prepare("DELETE FROM cv_education WHERE cv_id = ?")->execute([$cvId]);
    $pdo->prepare("DELETE FROM cv_skills WHERE cv_id = ?")->execute([$cvId]);
    
    // Delete CV profile
    $stmt = $pdo->prepare("DELETE FROM cv_profiles WHERE cv_id = ? AND user_id = ?");
    $stmt->execute([$cvId, $userId]);
    
    $pdo->commit();
    
    echo json_encode(['success' => true, 'message' => 'CV deleted successfully']);
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
