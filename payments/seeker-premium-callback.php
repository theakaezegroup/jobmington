<?php
define('JOBMINGTON', true);
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/paystack.php';
require_once __DIR__ . '/../includes/monetization.php';
require_once __DIR__ . '/../includes/seeker_premium.php';
require_once __DIR__ . '/../includes/mailer.php';

Session::start();
Session::requireLogin();

$pdo    = db();
$userId = (int) Session::userId();
$ref    = Security::clean($_GET['reference'] ?? $_GET['trxref'] ?? '');

if ($ref === '') {
    Session::flash('error', 'Payment reference missing.');
    redirect(SITE_URL . '/payments/seeker-premium.php');
}

try {
    $response = Paystack::verifyTransaction($ref);
    $data     = $response['data'] ?? [];
    $status   = $data['status'] ?? 'failed';

    // Fetch local transaction
    $stmt = $pdo->prepare("SELECT * FROM transactions WHERE txn_ref = ? AND user_id = ? LIMIT 1");
    $stmt->execute([$ref, $userId]);
    $txn = $stmt->fetch();

    if (!$txn) {
        Session::flash('error', 'Transaction record not found.');
        redirect(SITE_URL . '/payments/seeker-premium.php');
    }

    if ($status === 'success' && $txn['status'] !== 'completed') {
        $amountNgn = (float) $txn['amount'];
        $plan      = strtolower(str_contains($txn['plan'], 'annual') ? 'annual' : 'monthly');

        $pdo->beginTransaction();
        try {
            // Mark transaction complete
            $pdo->prepare("
                UPDATE transactions
                SET status = 'completed', paystack_ref = ?, paystack_data = ?, completed_at = NOW()
                WHERE txn_ref = ?
            ")->execute([$data['id'] ?? null, json_encode($data), $ref]);

            // Activate subscription
            jm_activate_seeker_subscription(
                $pdo, $userId, $plan, $amountNgn, $ref,
                $data['subscription'] ?? '', $data['customer']['customer_code'] ?? ''
            );

            $pdo->commit();

            $userRow = $pdo->prepare("SELECT email, full_name FROM users WHERE user_id = ? LIMIT 1");
            $userRow->execute([$userId]);
            $userRow = $userRow->fetch();
            if ($userRow) {
                $planLabel  = $plan === 'annual' ? 'Premium Annual' : 'Premium Monthly';
                $amountStr  = '₦' . number_format($amountNgn, 0);
                Mailer::sendPaymentReceipt($userRow['email'], $userRow['full_name'], $planLabel, $amountStr);
            }

            Session::flash('success', 'Welcome to Premium! All AI tools are now unlocked.');
            redirect(SITE_URL . '/seeker/dashboard.php?upgraded=1');
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    } elseif ($txn['status'] === 'completed') {
        redirect(SITE_URL . '/seeker/dashboard.php?upgraded=1');
    } else {
        // Mark failed
        $pdo->prepare("
            UPDATE transactions SET status = 'failed', completed_at = NOW() WHERE txn_ref = ?
        ")->execute([$ref]);

        Session::flash('error', 'Payment was not successful. Please try again.');
        redirect(SITE_URL . '/payments/seeker-premium.php');
    }
} catch (Throwable $e) {
    error_log('Seeker premium callback error: ' . $e->getMessage());
    Session::flash('error', 'Could not verify payment. Contact support if you were charged.');
    redirect(SITE_URL . '/payments/seeker-premium.php');
}
