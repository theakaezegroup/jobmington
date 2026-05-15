<?php
/**
 * JOBMINGTON - Paystack Webhook Handler
 * Receives and processes Paystack webhook events
 * 
 * Configure webhook URL in Paystack Dashboard:
 * https://dashboard.paystack.com/#/settings/developer
 * 
 * Webhook URL: https://yourdomain.com/jobmington/api/webhooks/paystack.php
 */

define('JOBMINGTON', true);
require_once __DIR__ . '/../../config/env.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/paystack.php';
require_once __DIR__ . '/../../includes/seeds.php';

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

// Get the raw POST body
$payload = file_get_contents('php://input');

// Get the signature from headers
$signature = $_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] ?? '';

// Log webhook receipt
error_log("Paystack Webhook received: " . substr($payload, 0, 500));

// Validate webhook signature
try {
    if (!Paystack::validateWebhook($payload, $signature)) {
        error_log("Paystack Webhook: Invalid signature");
        http_response_code(401);
        exit('Invalid signature');
    }
} catch (Exception $e) {
    error_log("Paystack Webhook signature validation error: " . $e->getMessage());
    http_response_code(500);
    exit('Signature validation error');
}

// Parse the payload
$event = json_decode($payload, true);

if (!$event || !isset($event['event'])) {
    error_log("Paystack Webhook: Invalid payload");
    http_response_code(400);
    exit('Invalid payload');
}

$eventType = $event['event'];
$data = $event['data'] ?? [];

error_log("Paystack Webhook Event: {$eventType}");

$pdo = db();

try {
    switch ($eventType) {
        case 'charge.success':
            handleChargeSuccess($pdo, $data);
            break;
            
        case 'charge.failed':
            handleChargeFailed($pdo, $data);
            break;
            
        case 'transfer.success':
            handleTransferSuccess($pdo, $data);
            break;
            
        case 'transfer.failed':
            handleTransferFailed($pdo, $data);
            break;
            
        case 'subscription.create':
            handleSubscriptionCreate($pdo, $data);
            break;
            
        case 'subscription.disable':
            handleSubscriptionDisable($pdo, $data);
            break;
            
        case 'invoice.create':
        case 'invoice.update':
            handleInvoice($pdo, $data, $eventType);
            break;
            
        default:
            error_log("Paystack Webhook: Unhandled event type: {$eventType}");
    }
    
    // Return 200 OK to acknowledge receipt
    http_response_code(200);
    echo json_encode(['status' => 'success', 'message' => 'Webhook processed']);
    
} catch (Exception $e) {
    error_log("Paystack Webhook Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

/**
 * Handle successful charge
 */
function handleChargeSuccess($pdo, $data) {
    $reference = $data['reference'] ?? '';
    $amount = ($data['amount'] ?? 0) / 100; // Convert from kobo
    $metadata = $data['metadata'] ?? [];
    
    if (empty($reference)) {
        throw new Exception('Missing reference in charge.success');
    }
    
    // Check if we have this transaction
    $stmt = $pdo->prepare("SELECT * FROM transactions WHERE txn_ref = ?");
    $stmt->execute([$reference]);
    $transaction = $stmt->fetch();
    
    if (!$transaction) {
        error_log("Paystack Webhook: Transaction not found: {$reference}");
        return; // Not our transaction, ignore
    }
    
    // Already processed?
    if ($transaction['status'] === 'completed') {
        error_log("Paystack Webhook: Transaction already completed: {$reference}");
        return;
    }
    
    $userId = $transaction['user_id'];
    $credits = $transaction['credits'];
    
    $pdo->beginTransaction();
    
    try {
        // Update transaction status
        $stmt = $pdo->prepare("
            UPDATE transactions 
            SET status = 'completed',
                paystack_ref = ?,
                paystack_data = ?,
                completed_at = NOW()
            WHERE txn_ref = ?
        ");
        $stmt->execute([
            $data['id'] ?? null,
            json_encode($data),
            $reference
        ]);
        
        // Credit wallet
        $stmt = $pdo->prepare("
            UPDATE wallets 
            SET balance = balance + ?,
                lifetime_earned = lifetime_earned + ?,
                updated_at = NOW()
            WHERE user_id = ?
        ");
        $result = $stmt->execute([$credits, $credits, $userId]);
        
        // If no wallet exists, create one
        if ($stmt->rowCount() === 0) {
            $stmt = $pdo->prepare("
                INSERT INTO wallets (user_id, balance, lifetime_earned, created_at)
                VALUES (?, ?, ?, NOW())
            ");
            $stmt->execute([$userId, $credits, $credits]);
        }
        
        // Record seed transaction
        $stmt = $pdo->prepare("
            INSERT INTO seed_transactions (user_id, type, amount, source, description, reference, created_at)
            VALUES (?, 'purchase', ?, 'purchase', ?, ?, NOW())
        ");
        $stmt->execute([
            $userId,
            $credits,
            'Purchased ' . number_format($credits) . ' Seeds via Paystack',
            $reference
        ]);
        
        $pdo->commit();
        
        error_log("Paystack Webhook: Successfully credited {$credits} seeds to user {$userId}");
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Handle failed charge
 */
function handleChargeFailed($pdo, $data) {
    $reference = $data['reference'] ?? '';
    
    if (empty($reference)) return;
    
    $stmt = $pdo->prepare("
        UPDATE transactions 
        SET status = 'failed',
            paystack_data = ?,
            completed_at = NOW()
        WHERE txn_ref = ? AND status = 'pending'
    ");
    $stmt->execute([json_encode($data), $reference]);
    
    error_log("Paystack Webhook: Charge failed for reference: {$reference}");
}

/**
 * Handle successful transfer (for payouts)
 */
function handleTransferSuccess($pdo, $data) {
    $reference = $data['reference'] ?? '';
    
    // Log transfer success
    error_log("Paystack Webhook: Transfer success - Reference: {$reference}");
    
    // Update any payout records if applicable
    // This would be used for paying out to users
}

/**
 * Handle failed transfer
 */
function handleTransferFailed($pdo, $data) {
    $reference = $data['reference'] ?? '';
    error_log("Paystack Webhook: Transfer failed - Reference: {$reference}");
}

/**
 * Handle subscription creation
 */
function handleSubscriptionCreate($pdo, $data) {
    $subscriptionCode = $data['subscription_code'] ?? '';
    $customerEmail = $data['customer']['email'] ?? '';
    $planCode = $data['plan']['plan_code'] ?? '';
    
    error_log("Paystack Webhook: Subscription created - {$subscriptionCode} for {$customerEmail}");
    
    // Update user subscription status if needed
}

/**
 * Handle subscription disable/cancel
 */
function handleSubscriptionDisable($pdo, $data) {
    $subscriptionCode = $data['subscription_code'] ?? '';
    error_log("Paystack Webhook: Subscription disabled - {$subscriptionCode}");
}

/**
 * Handle invoice events
 */
function handleInvoice($pdo, $data, $eventType) {
    $invoiceCode = $data['invoice_code'] ?? '';
    error_log("Paystack Webhook: Invoice event {$eventType} - {$invoiceCode}");
}
