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
require_once __DIR__ . '/../includes/tools.php';

header('Content-Type: application/json');

Session::start();
if (!Session::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}


// The tool gate, after the sign-in check: a signed-out caller should be told
// they are signed out, not that a tool they cannot even see is locked.
jm_require_tool_api('cv_builder');
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

    /*
     * Every child table, not the three this knew about when it was written.
     *
     * The CV builder gained projects, certifications, languages, awards,
     * volunteering and references later, and nothing taught this about them, so
     * deleting a CV left six tables of rows pointing at a cv_id that no longer
     * existed. Nobody saw it because those rows are only ever read by cv_id,
     * so they simply became invisible and permanent.
     *
     * Guarded by a table check, because a database that has not run the CV
     * migration yet should still be able to delete a CV.
     */
    $children = [
        'cv_experience', 'cv_education', 'cv_skills',
        'cv_projects', 'cv_certifications', 'cv_languages',
        'cv_awards', 'cv_volunteer', 'cv_references',
    ];

    foreach ($children as $table) {
        $exists = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES
                                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
        $exists->execute([$table]);
        if ((int) $exists->fetchColumn() === 0) {
            continue;
        }
        $pdo->prepare("DELETE FROM `{$table}` WHERE cv_id = ?")->execute([$cvId]);
    }

    $stmt = $pdo->prepare("DELETE FROM cv_profiles WHERE cv_id = ? AND user_id = ?");
    $stmt->execute([$cvId, $userId]);

    $pdo->commit();

    echo json_encode(['success' => true, 'message' => 'CV deleted successfully']);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    // The message goes to the log, not to the browser: a database error text
    // tells a stranger about the schema and helps the person reading it not at all.
    error_log('CV delete failed for cv ' . $cvId . ': ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'We could not delete that CV. Please try again.']);
}
