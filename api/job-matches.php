<?php
/**
 * JOBMINGTON - Job Matches API
 * Returns personalized job matches for logged-in user
 */

define('JOBMINGTON', true);
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../ai/JobMatcher.php';

header('Content-Type: application/json');

Session::start();

// Require login
if (!Session::isLoggedIn()) {
    jsonError('Please log in to view job matches', 401);
}

$userId = Session::userId();

try {
    $pdo = db();
    $matcher = new JobMatcher($pdo);
    
    // Get requested limit
    $limit = min(50, max(5, (int) get('limit', 20)));
    
    // Get matches
    $matches = $matcher->getTopMatches($userId, $limit);
    
    // Check if user has complete profile
    $stmt = $pdo->prepare("
        SELECT cv.cv_id,
               (SELECT COUNT(*) FROM cv_skills WHERE cv_id = cv.cv_id) as skill_count
        FROM cv_profiles cv
        WHERE cv.user_id = ?
        LIMIT 1
    ");
    $stmt->execute([$userId]);
    $cvInfo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $profileComplete = $cvInfo && $cvInfo['skill_count'] >= 3;
    
    jsonSuccess([
        'matches' => $matches,
        'count' => count($matches),
        'profile_complete' => $profileComplete,
        'tip' => !$profileComplete ? 'Add more skills to your CV for better matches!' : null
    ]);
    
} catch (Exception $e) {
    error_log('Job Matches API Error: ' . $e->getMessage());
    jsonError('Could not fetch job matches. Please try again.');
}
