<?php
require_once __DIR__ . '/_disabled.php';

define('JOBMINGTON', true);
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';

Session::start();
$pdo = db();

$courseId = (int) get('id', 0);
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

function jm_enroll_response(bool $success, string $message, ?string $redirect = null): void {
    global $isAjax;

    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => $success, 'message' => $message, 'redirect' => $redirect]);
        exit;
    }

    Session::flash($success ? 'success' : 'error', $message);
    redirect($redirect ?: '/jobmington/learn/');
}

if ($courseId <= 0) {
    jm_enroll_response(false, 'Course not found.', '/jobmington/learn/');
}

if (!Session::isLoggedIn()) {
    $redirect = '/jobmington/auth/login.php?redirect=' . urlencode('/jobmington/learn/course.php?id=' . $courseId);
    jm_enroll_response(false, 'Please sign in to enroll.', $redirect);
}

$userId = Session::userId();

$stmt = $pdo->prepare("SELECT * FROM courses WHERE course_id = ? AND is_published = 1 LIMIT 1");
$stmt->execute([$courseId]);
$course = $stmt->fetch();

if (!$course) {
    jm_enroll_response(false, 'Course not found.', '/jobmington/learn/');
}

$courseUrl = '/jobmington/learn/course.php?id=' . $courseId;

$stmt = $pdo->prepare("SELECT enrollment_id FROM course_enrollments WHERE user_id = ? AND course_id = ? LIMIT 1");
$stmt->execute([$userId, $courseId]);
if ($stmt->fetch()) {
    jm_enroll_response(true, 'You are already enrolled.', $courseUrl);
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("INSERT INTO course_enrollments (user_id, course_id, started_at, last_accessed) VALUES (?, ?, NOW(), NOW())");
    $stmt->execute([$userId, $courseId]);

    $pdo->prepare("UPDATE courses SET enrollment_count = COALESCE(enrollment_count, 0) + 1 WHERE course_id = ?")->execute([$courseId]);

    try {
        Security::logActivity($userId, 'course_enroll', 'Enrolled in: ' . $course['title']);
        sendNotification(
            (int) $userId,
            'course',
            'You are enrolled in ' . $course['title'],
            'Pick up where you left off any time from your courses.',
            '/learn/course.php?id=' . (int) $course['course_id']
        );
    } catch (Throwable $e) {
        error_log($e->getMessage());
    }

    $pdo->commit();
    jm_enroll_response(true, 'Successfully enrolled in ' . $course['title'] . '.', $courseUrl);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Course enrollment error: ' . $e->getMessage());
    jm_enroll_response(false, 'Enrollment failed. Please try again.', $courseUrl);
}
