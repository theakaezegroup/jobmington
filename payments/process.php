<?php
/**
 * JOBMINGTON - Payment Processing
 * Integrates with Paystack API for real payment processing
 */

define('JOBMINGTON', true);
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/paystack.php';

Session::start();
if (!Session::isLoggedIn()) redirect('/jobmington/auth/login.php');

$pdo = db();
$userId = Session::userId();

// Get user data
$stmt = $pdo->prepare("SELECT email, full_name FROM users WHERE user_id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    Session::flash('error', 'User not found');
    redirect('/jobmington/payments/');
}

// Validate POST
$plan = Security::clean(post('plan', ''));
$amount = (float)post('amount', 0);
$credits = (int)post('credits', 0);
$method = Security::clean(post('payment_method', 'paystack'));

if (!$plan || $amount <= 0) {
    Session::flash('error', 'Invalid payment data');
    redirect('/jobmington/payments/');
}

// Generate unique reference
$txnRef = Paystack::generateReference('JMT');

try {
    $pdo->beginTransaction();
    
    // Log transaction attempt
    $stmt = $pdo->prepare("
        INSERT INTO transactions (user_id, plan, amount, credits, txn_ref, method, status, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())
    ");
    $stmt->execute([$userId, $plan, $amount, $credits, $txnRef, $method]);
    $transactionId = $pdo->lastInsertId();
    
    $pdo->commit();
    
    // Initialize Paystack transaction
    $metadata = [
        'user_id' => $userId,
        'plan' => $plan,
        'credits' => $credits,
        'transaction_id' => $transactionId,
        'custom_fields' => [
            ['display_name' => 'Plan', 'variable_name' => 'plan', 'value' => $plan],
            ['display_name' => 'Seeds', 'variable_name' => 'seeds', 'value' => $credits . ' Seeds']
        ]
    ];
    
    $response = Paystack::initializeTransaction(
        $user['email'],
        Paystack::toKobo($amount),
        $txnRef,
        $metadata
    );
    
    if ($response['status'] && isset($response['data']['authorization_url'])) {
        // Redirect to Paystack checkout
        header('Location: ' . $response['data']['authorization_url']);
        exit;
    } else {
        throw new Exception($response['message'] ?? 'Failed to initialize payment');
    }
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Payment initialization error: " . $e->getMessage());
    
    // Check if it's a config error
    if (strpos($e->getMessage(), 'not configured') !== false) {
        Session::flash('error', 'Payment gateway not configured. Please contact support.');
    } else {
        Session::flash('error', 'Payment initialization failed: ' . $e->getMessage());
    }
    
    redirect('/jobmington/payments/');
}
