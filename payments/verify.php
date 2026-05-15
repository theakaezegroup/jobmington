<?php
/**
 * JOBMINGTON - Paystack Payment Verification
 * Handles callback from Paystack after payment
 */

define('JOBMINGTON', true);
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/paystack.php';
require_once __DIR__ . '/../includes/seeds.php';

Session::start();

$pdo = db();

// Get reference from Paystack callback
$reference = Security::clean($_GET['reference'] ?? $_GET['trxref'] ?? '');

if (empty($reference)) {
    Session::flash('error', 'Invalid payment reference');
    redirect('/jobmington/payments/failed.php');
}

try {
    // Verify transaction with Paystack
    $response = Paystack::verifyTransaction($reference);
    
    if (!$response['status']) {
        throw new Exception('Verification failed: ' . ($response['message'] ?? 'Unknown error'));
    }
    
    $data = $response['data'];
    $status = $data['status']; // success, failed, abandoned
    $amount = Paystack::toNaira($data['amount']);
    $metadata = $data['metadata'] ?? [];
    
    // Get our transaction record
    $stmt = $pdo->prepare("SELECT * FROM transactions WHERE txn_ref = ?");
    $stmt->execute([$reference]);
    $transaction = $stmt->fetch();
    
    if (!$transaction) {
        throw new Exception('Transaction not found in database');
    }
    
    // Check if already processed
    if ($transaction['status'] === 'completed') {
        Session::flash('info', 'This payment has already been processed');
        redirect('/jobmington/payments/success.php?ref=' . $reference);
    }
    
    $userId = $transaction['user_id'];
    
    if ($status === 'success') {
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
                $data['id'],
                json_encode($data),
                $reference
            ]);
            
            // Credit seeds to wallet
            $credits = $transaction['credits'];
            $wallet = getWallet($userId);
            
            if ($wallet) {
                // Update existing wallet
                $stmt = $pdo->prepare("
                    UPDATE wallets 
                    SET balance = balance + ?,
                        lifetime_earned = lifetime_earned + ?,
                        updated_at = NOW()
                    WHERE user_id = ?
                ");
                $stmt->execute([$credits, $credits, $userId]);
            } else {
                // Create wallet with balance
                $stmt = $pdo->prepare("
                    INSERT INTO wallets (user_id, balance, lifetime_earned, created_at)
                    VALUES (?, ?, ?, NOW())
                ");
                $stmt->execute([$userId, $credits, $credits]);
            }

            $balanceAfter = getSeedBalance($userId);
            
            // Record seed transaction
            $stmt = $pdo->prepare("
                INSERT INTO seed_transactions (user_id, type, amount, balance_after, source, reference_id, description, created_at)
                VALUES (?, 'purchase', ?, ?, 'purchase', ?, ?, NOW())
            ");
            $stmt->execute([
                $userId,
                $credits,
                $balanceAfter,
                $transaction['id'],
                'Purchased ' . number_format($credits) . ' Seeds (' . $transaction['plan'] . ')',
            ]);
            
            // Log activity
            Security::logActivity($userId, 'payment_success', 'Payment of ₦' . number_format($amount) . ' successful. Added ' . $credits . ' Seeds.');
            
            $pdo->commit();
            
            Session::flash('success', 'Payment successful! ' . number_format($credits) . ' Seeds have been added to your wallet.');
            redirect('/jobmington/payments/success.php?ref=' . $reference);
            
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
        
    } elseif ($status === 'failed') {
        // Update transaction as failed
        $stmt = $pdo->prepare("
            UPDATE transactions 
            SET status = 'failed',
                paystack_data = ?,
                completed_at = NOW()
            WHERE txn_ref = ?
        ");
        $stmt->execute([json_encode($data), $reference]);
        
        Security::logActivity($userId, 'payment_failed', 'Payment of ₦' . number_format($amount) . ' failed.');
        
        Session::flash('error', 'Payment failed. Please try again.');
        redirect('/jobmington/payments/failed.php?ref=' . $reference);
        
    } else {
        // Abandoned or other status
        $stmt = $pdo->prepare("
            UPDATE transactions 
            SET status = ?,
                paystack_data = ?
            WHERE txn_ref = ?
        ");
        $stmt->execute([$status, json_encode($data), $reference]);
        
        Session::flash('warning', 'Payment was not completed. Please try again.');
        redirect('/jobmington/payments/?status=' . $status);
    }
    
} catch (Exception $e) {
    error_log("Payment verification error: " . $e->getMessage());
    Session::flash('error', 'Payment verification failed: ' . $e->getMessage());
    redirect('/jobmington/payments/failed.php?ref=' . $reference);
}
