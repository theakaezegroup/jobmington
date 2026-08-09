<?php
require_once __DIR__ . '/_disabled.php';

/**
 * Course Purchase Verification
 * Handles Paystack callback after payment
 */

require_once __DIR__ . '/../includes/header.php';

if (!$userId) {
    Session::requireLogin('Sign in to confirm your purchase.');
}

$courseId = (int) ($_GET['course'] ?? 0);
$reference = $_GET['reference'] ?? '';

// Check session for pending purchase
$pending = $_SESSION['pending_course_purchase'] ?? null;

if (!$pending || $pending['course_id'] != $courseId) {
    $_SESSION['error'] = 'Invalid purchase session.';
    redirect('/jobmington/learn');
}

// Verify with Paystack
$paystackSecretKey = $_ENV['PAYSTACK_SECRET_KEY'] ?? '';

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://api.paystack.co/transaction/verify/' . $reference);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $paystackSecretKey,
]);

$response = curl_exec($ch);
curl_close($ch);

$result = json_decode($response, true);

if ($result && $result['status'] && $result['data']['status'] === 'success') {
    // Payment successful
    
    // Fetch course
    $stmt = $pdo->prepare("SELECT * FROM courses WHERE course_id = ?");
    $stmt->execute([$courseId]);
    $course = $stmt->fetch();
    
    if (!$course) {
        $_SESSION['error'] = 'Course not found.';
        redirect('/jobmington/learn');
    }
    
    // Check if already purchased (prevent duplicate)
    $stmt = $pdo->prepare("SELECT * FROM course_purchases WHERE user_id = ? AND course_id = ?");
    $stmt->execute([$userId, $courseId]);
    
    if (!$stmt->fetch()) {
        try {
            $pdo->beginTransaction();
            
            // Record purchase
            $stmt = $pdo->prepare("
                INSERT INTO course_purchases (user_id, course_id, amount, payment_method, transaction_ref)
                VALUES (?, ?, ?, 'naira', ?)
            ");
            $stmt->execute([$userId, $courseId, $pending['amount'], $reference]);
            
            // Auto-enroll user
            $stmt = $pdo->prepare("
                INSERT IGNORE INTO course_enrollments (user_id, course_id, started_at)
                VALUES (?, ?, NOW())
            ");
            $stmt->execute([$userId, $courseId]);
            
            // Update course enrollment count
            $pdo->prepare("UPDATE courses SET enrollment_count = enrollment_count + 1 WHERE course_id = ?")->execute([$courseId]);
            
            // Record in wallet transactions (if table exists)
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO wallet_transactions (user_id, type, amount, currency, description, reference, status, created_at)
                    VALUES (?, 'debit', ?, 'naira', ?, ?, 'completed', NOW())
                ");
                $stmt->execute([
                    $userId,
                    $pending['amount'],
                    'Course purchase: ' . $course['title'],
                    $reference
                ]);
            } catch (Exception $e) {
                // Table may not exist, ignore
            }
            
            $pdo->commit();
            
        } catch (Exception $e) {
            $pdo->rollBack();
            // Still continue - payment was successful
        }
    }
    
    // Clear pending session
    unset($_SESSION['pending_course_purchase']);
    
    $_SESSION['success'] = ' Payment successful! Course unlocked.';
    redirect('/jobmington/learn/course.php?id=' . $courseId);
    
} else {
    // Payment failed
    unset($_SESSION['pending_course_purchase']);
    $_SESSION['error'] = 'Payment verification failed. If you were charged, please contact support.';
    redirect('/jobmington/learn/checkout.php?course=' . $courseId);
}
