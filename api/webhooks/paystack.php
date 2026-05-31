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
require_once __DIR__ . '/../../includes/monetization.php';
require_once __DIR__ . '/../../includes/seeker_premium.php';

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
 * Handle successful charge — routes to the correct fulfillment based on transaction type.
 */
function handleChargeSuccess($pdo, $data) {
    $reference = $data['reference'] ?? '';
    if (empty($reference)) {
        throw new Exception('Missing reference in charge.success');
    }

    $stmt = $pdo->prepare("SELECT * FROM transactions WHERE txn_ref = ?");
    $stmt->execute([$reference]);
    $transaction = $stmt->fetch();

    if (!$transaction) {
        error_log("Paystack Webhook: Transaction not found: {$reference}");
        return;
    }
    if ($transaction['status'] === 'completed') {
        error_log("Paystack Webhook: Already completed: {$reference}");
        return;
    }

    $userId  = (int) $transaction['user_id'];
    $txnType = $transaction['type'] ?? TXN_TYPE_SEEDS_PURCHASE;

    $pdo->beginTransaction();
    try {
        // Mark transaction complete
        $pdo->prepare("
            UPDATE transactions
            SET status = 'completed', paystack_ref = ?, paystack_data = ?, completed_at = NOW()
            WHERE txn_ref = ?
        ")->execute([$data['id'] ?? null, json_encode($data), $reference]);

        switch ($txnType) {
            case TXN_TYPE_SEEKER_PREMIUM:
                $plan      = str_contains(strtolower((string) $transaction['plan']), 'annual') ? 'annual' : 'monthly';
                $amountNgn = (float) $transaction['amount'];
                jm_activate_seeker_subscription(
                    $pdo, $userId, $plan, $amountNgn, $reference,
                    $data['subscription'] ?? '',
                    $data['customer']['customer_code'] ?? ''
                );
                error_log("Webhook: Seeker premium ({$plan}) activated for user {$userId}");
                break;

            case TXN_TYPE_SEEKER_CREDITS:
            case TXN_TYPE_BUNDLE:
                $credits = (int) $transaction['credits'];
                jm_seeker_add_credits($pdo, $userId, $credits, $reference);
                error_log("Webhook: {$credits} tool credits added to user {$userId}");
                break;

            case TXN_TYPE_EMPLOYER_POST:
                // Job activation is handled by the job-posting-callback; webhook is a safety net
                $plan = (string) $transaction['plan'];
                if (preg_match('/job:(\d+)/', $plan, $m)) {
                    $jobId = (int) $m[1];
                    $pdo->prepare("
                        UPDATE jobs SET is_active = 1, status = 'active' WHERE job_id = ?
                    ")->execute([$jobId]);
                    error_log("Webhook: Job {$jobId} activated for user {$userId}");
                }
                break;

            case TXN_TYPE_SEEDS_PURCHASE:
            default:
                // Legacy Seeds wallet credit
                $credits = (int) $transaction['credits'];
                $stmt = $pdo->prepare("
                    UPDATE wallets SET balance = balance + ?, lifetime_earned = lifetime_earned + ?, updated_at = NOW()
                    WHERE user_id = ?
                ");
                $stmt->execute([$credits, $credits, $userId]);
                if ($stmt->rowCount() === 0) {
                    $pdo->prepare("INSERT INTO wallets (user_id, balance, lifetime_earned) VALUES (?, ?, ?)")
                        ->execute([$userId, $credits, $credits]);
                }
                $pdo->prepare("
                    INSERT INTO seed_transactions (user_id, type, amount, source, description, reference, created_at)
                    VALUES (?, 'purchase', ?, 'purchase', ?, ?, NOW())
                ")->execute([$userId, $credits, 'Purchased ' . number_format($credits) . ' Seeds via Paystack', $reference]);
                error_log("Webhook: {$credits} Seeds credited to user {$userId}");
                break;
        }

        $pdo->commit();
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
 * Handle subscription creation — update our records with Paystack sub code.
 */
function handleSubscriptionCreate($pdo, $data) {
    $subscriptionCode  = $data['subscription_code']           ?? '';
    $customerCode      = $data['customer']['customer_code']   ?? '';
    $emailToken        = $data['email_token']                 ?? '';
    $nextPayment       = $data['next_payment_date']           ?? null;

    if ($subscriptionCode) {
        $pdo->prepare("
            UPDATE seeker_subscriptions
            SET paystack_sub_code = ?, paystack_customer_code = ?, paystack_email_token = ?
            WHERE paystack_sub_code = '' OR paystack_sub_code IS NULL
            ORDER BY created_at DESC LIMIT 1
        ")->execute([$subscriptionCode, $customerCode, $emailToken]);

        $pdo->prepare("
            UPDATE employer_subscriptions
            SET paystack_sub_code = ?, paystack_customer_code = ?, paystack_email_token = ?
            WHERE paystack_sub_code = '' OR paystack_sub_code IS NULL
            ORDER BY created_at DESC LIMIT 1
        ")->execute([$subscriptionCode, $customerCode, $emailToken]);
    }

    error_log("Webhook: Subscription created - {$subscriptionCode}");
}

/**
 * Handle subscription disable/cancel.
 */
function handleSubscriptionDisable($pdo, $data) {
    $subscriptionCode = $data['subscription_code'] ?? '';
    if ($subscriptionCode) {
        $pdo->prepare("
            UPDATE seeker_subscriptions SET status = 'cancelled' WHERE paystack_sub_code = ?
        ")->execute([$subscriptionCode]);
        $pdo->prepare("
            UPDATE employer_subscriptions SET status = 'cancelled' WHERE paystack_sub_code = ?
        ")->execute([$subscriptionCode]);
    }
    error_log("Webhook: Subscription disabled - {$subscriptionCode}");
}

/**
 * Handle invoice events
 */
function handleInvoice($pdo, $data, $eventType) {
    $invoiceCode = $data['invoice_code'] ?? '';
    error_log("Paystack Webhook: Invoice event {$eventType} - {$invoiceCode}");
}
