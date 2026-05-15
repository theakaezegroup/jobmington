<?php
/**
 * JOBMINGTON - Data Uplink (AJAX Save Handler)
 * Function: Receives JSON payload from CV Editor and updates DB
 */

define('JOBMINGTON', true);
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/session.php';

// JSON Response Helper
function jsonResponse($success, $message, $data = []) {
    header('Content-Type: application/json');
    echo json_encode(['success' => $success, 'message' => $message, 'data' => $data]);
    exit;
}

Session::start();
if (!Session::isLoggedIn()) jsonResponse(false, 'Session expired. Please sign in again.');

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(false, 'Invalid Signal Type');

// Get JSON Input
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) jsonResponse(false, 'Corrupted Data Packet');

$pdo = db();
$userId = Session::userId();
$cvId = (int)($input['cv_id'] ?? 0);

// Validate Ownership
if ($cvId > 0) {
    $stmt = $pdo->prepare("SELECT cv_id FROM cv_profiles WHERE cv_id = ? AND user_id = ?");
    $stmt->execute([$cvId, $userId]);
    if (!$stmt->fetch()) jsonResponse(false, 'Access Denied: Artifact mismatch');
} else {
    // Create new if ID is 0
    $stmt = $pdo->prepare("INSERT INTO cv_profiles (user_id, title, template, created_at, updated_at) VALUES (?, 'New CV', 'professional', NOW(), NOW())");
    $stmt->execute([$userId]);
    $cvId = $pdo->lastInsertId();
}

try {
    $pdo->beginTransaction();

    // 1. Update Main Profile
    $stmt = $pdo->prepare("
        UPDATE cv_profiles SET 
            full_name = ?, email = ?, phone = ?, city = ?, 
            headline = ?, summary = ?, updated_at = NOW()
        WHERE cv_id = ? AND user_id = ?
    ");
    $stmt->execute([
        Security::clean($input['personal']['full_name'] ?? ''),
        Security::clean($input['personal']['email'] ?? ''),
        Security::clean($input['personal']['phone'] ?? ''),
        Security::clean($input['personal']['city'] ?? ''),
        Security::clean($input['personal']['headline'] ?? ''),
        Security::clean($input['personal']['summary'] ?? ''),
        $cvId, $userId
    ]);

    // 2. Update Experience (Wipe and Rewrite Strategy for simplicity in Editor)
    $pdo->prepare("DELETE FROM cv_experience WHERE cv_id = ?")->execute([$cvId]);
    if (!empty($input['experience'])) {
        $stmtExp = $pdo->prepare("INSERT INTO cv_experience (cv_id, job_title, company, start_date, end_date, is_current, description) VALUES (?, ?, ?, ?, ?, ?, ?)");
        foreach ($input['experience'] as $exp) {
            $startDate = !empty($exp['start_date']) ? $exp['start_date'] . '-01' : null;
            $endDate = !empty($exp['end_date']) ? $exp['end_date'] . '-01' : null;
            $stmtExp->execute([
                $cvId,
                Security::clean($exp['job_title'] ?? ''),
                Security::clean($exp['company'] ?? ''),
                $startDate,
                $endDate,
                !empty($exp['is_current']) ? 1 : 0,
                Security::clean($exp['description'] ?? '')
            ]);
        }
    }

    // 3. Update Education
    $pdo->prepare("DELETE FROM cv_education WHERE cv_id = ?")->execute([$cvId]);
    if (!empty($input['education'])) {
        $stmtEdu = $pdo->prepare("INSERT INTO cv_education (cv_id, degree, institution, start_date, end_date, description) VALUES (?, ?, ?, ?, ?, ?)");
        foreach ($input['education'] as $edu) {
            $startDate = !empty($edu['start_date']) ? $edu['start_date'] . '-01' : null;
            $endDate = !empty($edu['end_date']) ? $edu['end_date'] . '-01' : null;
            $stmtEdu->execute([
                $cvId,
                Security::clean($edu['degree'] ?? ''),
                Security::clean($edu['school'] ?? ''),
                $startDate,
                $endDate,
                Security::clean($edu['field_of_study'] ?? '')
            ]);
        }
    }

    // 4. Update Skills
    $pdo->prepare("DELETE FROM cv_skills WHERE cv_id = ?")->execute([$cvId]);
    if (!empty($input['skills'])) {
        $stmtSkill = $pdo->prepare("INSERT INTO cv_skills (cv_id, skill_name, proficiency) VALUES (?, ?, ?)");
        foreach ($input['skills'] as $skill) {
            $stmtSkill->execute([
                $cvId,
                Security::clean($skill['name'] ?? ''),
                'intermediate'
            ]);
        }
    }

    $pdo->commit();
    jsonResponse(true, 'Data Uplink Successful', ['cv_id' => $cvId]);

} catch (Exception $e) {
    $pdo->rollBack();
    error_log("CV Save Error: " . $e->getMessage() . " | File: " . $e->getFile() . " | Line: " . $e->getLine());
    jsonResponse(false, 'Save failed: ' . $e->getMessage());
}
?>