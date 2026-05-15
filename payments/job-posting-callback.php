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

Session::start();
$pdo = db();
$reference = Security::clean($_GET['reference'] ?? $_GET['trxref'] ?? '');

if ($reference === '') {
    Session::flash('error', 'Invalid payment reference.');
    redirect('/jobmington/payments/failed.php');
}

try {
    $response = Paystack::verifyTransaction($reference);
    if (empty($response['status'])) {
        throw new Exception($response['message'] ?? 'Payment verification failed.');
    }

    $data = $response['data'] ?? [];
    $status = $data['status'] ?? 'failed';

    $stmt = $pdo->prepare("SELECT * FROM transactions WHERE txn_ref = ? LIMIT 1");
    $stmt->execute([$reference]);
    $transaction = $stmt->fetch();

    if (!$transaction) {
        throw new Exception('Transaction not found.');
    }

    [$jobId, $packageId] = jm_parse_job_payment_plan((string) $transaction['plan']);
    $package = jm_job_posting_package($packageId);

    if ($transaction['status'] === 'completed') {
        Session::flash('success', 'Payment already processed.');
        redirect('/jobmington/employer/manage-jobs.php?payment=success');
    }

    if ($status === 'success') {
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("
                UPDATE transactions
                SET status = 'completed',
                    paystack_ref = ?,
                    paystack_data = ?,
                    completed_at = NOW()
                WHERE txn_ref = ?
            ");
            $stmt->execute([
                $data['id'] ?? $reference,
                json_encode($data),
                $reference,
            ]);

            $stmt = $pdo->prepare("
                UPDATE jobs j
                JOIN companies c ON j.company_id = c.company_id
                SET j.is_active = 1,
                    j.is_featured = ?,
                    j.expires_at = DATE_ADD(CURDATE(), INTERVAL ? DAY)
                WHERE j.job_id = ? AND c.user_id = ?
            ");
            $stmt->execute([
                !empty($package['featured']) ? 1 : 0,
                (int) $package['duration_days'],
                $jobId,
                (int) $transaction['user_id'],
            ]);

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        try {
            Security::logActivity((int) $transaction['user_id'], 'job_payment_success', $transaction['plan']);
        } catch (Throwable $e) {
            error_log($e->getMessage());
        }

        Session::flash('success', 'Payment successful. Your job is now live.');
        redirect('/jobmington/employer/manage-jobs.php?payment=success');
    }

    $safeStatus = in_array($status, ['failed', 'abandoned', 'refunded'], true) ? $status : 'failed';
    $stmt = $pdo->prepare("
        UPDATE transactions
        SET status = ?,
            paystack_data = ?
        WHERE txn_ref = ?
    ");
    $stmt->execute([$safeStatus, json_encode($data), $reference]);

    Session::flash('error', 'Payment was not completed. You can try again.');
    redirect('/jobmington/payments/job-posting.php?ref=' . urlencode($reference));
} catch (Throwable $e) {
    error_log('Job posting payment callback error: ' . $e->getMessage());
    Session::flash('error', 'Payment verification failed.');
    redirect('/jobmington/payments/failed.php?ref=' . urlencode($reference));
}
