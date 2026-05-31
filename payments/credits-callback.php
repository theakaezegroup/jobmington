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

Session::start();
Session::requireLogin();

$pdo    = db();
$userId = (int) Session::userId();
$ref    = Security::clean($_GET['reference'] ?? $_GET['trxref'] ?? '');
$tool   = Security::clean($_GET['tool'] ?? '');

if ($ref === '') {
    Session::flash('error', 'Payment reference missing.');
    redirect(SITE_URL . '/payments/credits.php');
}

try {
    $response = Paystack::verifyTransaction($ref);
    $data     = $response['data'] ?? [];
    $status   = $data['status'] ?? 'failed';

    $stmt = $pdo->prepare("SELECT * FROM transactions WHERE txn_ref = ? AND user_id = ? LIMIT 1");
    $stmt->execute([$ref, $userId]);
    $txn = $stmt->fetch();

    if (!$txn) {
        Session::flash('error', 'Transaction not found.');
        redirect(SITE_URL . '/payments/credits.php');
    }

    if ($status === 'success' && $txn['status'] !== 'completed') {
        $credits = (int) $txn['credits'];

        $pdo->beginTransaction();
        try {
            $pdo->prepare("
                UPDATE transactions
                SET status = 'completed', paystack_ref = ?, paystack_data = ?, completed_at = NOW()
                WHERE txn_ref = ?
            ")->execute([$data['id'] ?? null, json_encode($data), $ref]);

            jm_seeker_add_credits($pdo, $userId, $credits, $ref);

            $pdo->commit();

            $msg = "Payment successful! {$credits} credit" . ($credits !== 1 ? 's' : '') . " added to your account.";
            Session::flash('success', $msg);

            if ($tool) {
                redirect(SITE_URL . '/ai/andika.php?tool=' . urlencode($tool));
            }
            redirect(SITE_URL . '/seeker/dashboard.php?credits_added=1');
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    } elseif ($txn['status'] === 'completed') {
        redirect(SITE_URL . '/seeker/dashboard.php?credits_added=1');
    } else {
        $pdo->prepare("
            UPDATE transactions SET status = 'failed', completed_at = NOW() WHERE txn_ref = ?
        ")->execute([$ref]);
        Session::flash('error', 'Payment failed. Please try again.');
        redirect(SITE_URL . '/payments/credits.php');
    }
} catch (Throwable $e) {
    error_log('Credits callback error: ' . $e->getMessage());
    Session::flash('error', 'Could not verify payment. Contact support if you were charged.');
    redirect(SITE_URL . '/payments/credits.php');
}
