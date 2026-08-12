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
require_once __DIR__ . '/../employer/_employer_helpers.php';

Session::start();
Session::requireRole(USER_TYPE_EMPLOYER);

$pdo = db();
$userId = (int) Session::userId();
$company = jm_employer_company_or_redirect($pdo, $userId);
$ref = Security::clean($_GET['ref'] ?? '');

if ($ref === '') {
    Session::flash('error', 'Payment reference not found.');
    redirect('/jobmington/employer/manage-jobs.php');
}

$stmt = $pdo->prepare("SELECT email, full_name FROM users WHERE user_id = ? LIMIT 1");
$stmt->execute([$userId]);
$user = $stmt->fetch();

$stmt = $pdo->prepare("SELECT * FROM transactions WHERE txn_ref = ? AND user_id = ? LIMIT 1");
$stmt->execute([$ref, $userId]);
$transaction = $stmt->fetch();

if (!$transaction || !$user) {
    Session::flash('error', 'Payment record not found.');
    redirect('/jobmington/employer/manage-jobs.php');
}

[$jobId, $packageId] = jm_parse_job_payment_plan((string) $transaction['plan']);
$package = jm_job_posting_package($packageId);

$stmt = $pdo->prepare("
    SELECT j.*, c.name AS company_name
    FROM jobs j
    JOIN companies c ON j.company_id = c.company_id
    WHERE j.job_id = ? AND c.user_id = ?
    LIMIT 1
");
$stmt->execute([$jobId, $userId]);
$job = $stmt->fetch();

if (!$job) {
    Session::flash('error', 'Job listing not found.');
    redirect('/jobmington/employer/manage-jobs.php');
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::verifyCSRF()) {
        $error = 'Please refresh the page and try again.';
    } elseif ($transaction['status'] === 'completed') {
        redirect('/jobmington/employer/manage-jobs.php?payment=success');
    } else {
        try {
            if ($transaction['status'] !== 'pending') {
                $newRef = jm_job_payment_reference();
                $stmt = $pdo->prepare("
                    INSERT INTO transactions (user_id, plan, amount, credits, txn_ref, method, status, created_at)
                    VALUES (?, ?, ?, 0, ?, 'paystack', 'pending', NOW())
                ");
                $stmt->execute([
                    $userId,
                    jm_job_payment_plan_value($jobId, $package['id'], $package['name']),
                    (float) $package['price'],
                    $newRef,
                ]);
                redirect('/jobmington/payments/job-posting.php?ref=' . urlencode($newRef));
            }

            $metadata = [
                'user_id' => $userId,
                'job_id' => $jobId,
                'package_id' => $package['id'],
                'transaction_id' => (int) $transaction['id'],
                'custom_fields' => [
                    ['display_name' => 'Job', 'variable_name' => 'job', 'value' => $job['title']],
                    ['display_name' => 'Package', 'variable_name' => 'package', 'value' => $package['name']],
                ],
            ];

            $response = Paystack::initializeTransaction(
                $user['email'],
                Paystack::toKobo((float) $transaction['amount']),
                $ref,
                $metadata,
                SITE_URL . '/payments/job-posting-callback.php'
            );

            if (!empty($response['status']) && !empty($response['data']['authorization_url'])) {
                header('Location: ' . $response['data']['authorization_url']);
                exit;
            }

            throw new Exception($response['message'] ?? 'Unable to initialize payment.');
        } catch (Throwable $e) {
            error_log('Job posting payment error: ' . $e->getMessage());
            $error = strpos($e->getMessage(), 'not configured') !== false
                ? 'Payment gateway is not configured yet. Add your Paystack keys before accepting paid job posts.'
                : 'Payment could not start. Please try again.';
        }
    }
}

$pageTitle = 'Complete Job Payment | ' . SITE_NAME;
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?></title>
    <link rel="preload" as="font" type="font/ttf" href="/jobmington/assets/fonts/FuturaCyrillicDemi.ttf" crossorigin>
    <link rel="preload" as="font" type="font/ttf" href="/jobmington/assets/fonts/FuturaCyrillicBook.ttf" crossorigin>
    <link rel="stylesheet" href="/jobmington/assets/css/minimal-jobmington.css?v=brand-22">
</head>
<body class="jm-minimal">
    <div class="jm-shell">
        <header class="jm-header">
            <a class="jm-logo" href="/jobmington/"><img src="/jobmington/assets/images/badge.png?v=logo-8" alt=""><span>Jobmington</span></a>
            <?php require_once __DIR__ . '/../includes/navigation.php'; jm_workspace_nav([], ''); ?>
        </header>

        <section class="jm-section" style="padding-top:0;">
            <div class="jm-section-head">
                <div>
                    <p class="jm-kicker">Job payment</p>
                    <h1>Complete payment to publish.</h1>
                    <p>Your listing is saved. Paid plans go live after Paystack confirms payment.</p>
                </div>
            </div>

            <?php if ($error): ?><div class="jm-alert"><?= e($error) ?></div><?php endif; ?>

            <div class="jm-grid-2">
                <article class="jm-panel">
                    <h2><?= e($job['title']) ?></h2>
                    <div class="jm-key-list">
                        <div><span>Company</span><strong><?= e($job['company_name']) ?></strong></div>
                        <div><span>Package</span><strong><?= e($package['name']) ?></strong></div>
                        <div><span>Amount</span><strong><?= e(jm_money_ngn((float) $transaction['amount'])) ?></strong></div>
                        <div><span>Status</span><strong><?= e(ucfirst((string) $transaction['status'])) ?></strong></div>
                        <div><span>Reference</span><strong><?= e($transaction['txn_ref']) ?></strong></div>
                    </div>
                </article>

                <aside class="jm-panel">
                    <?php if ($transaction['status'] === 'completed'): ?>
                        <h2>Payment complete</h2>
                        <p>This job can now run with the selected visibility package.</p>
                        <a class="jm-button" href="/jobmington/employer/manage-jobs.php">Manage jobs</a>
                    <?php else: ?>
                        <h2>Pay securely</h2>
                        <p>Paystack will handle card, bank, transfer, and supported local payment options. Jobmington only publishes the paid listing after verification.</p>
                        <ul class="jm-price-list">
                            <?php foreach ($package['features'] as $feature): ?><li><?= e($feature) ?></li><?php endforeach; ?>
                        </ul>
                        <form method="post" class="jm-form-actions">
                            <?= Security::csrfField() ?>
                            <button class="jm-button" type="submit"><?= $transaction['status'] === 'pending' ? 'Pay with Paystack' : 'Try payment again' ?></button>
                            <a class="jm-button secondary" href="/jobmington/employer/manage-jobs.php">Later</a>
                        </form>
                    <?php endif; ?>
                </aside>
            </div>
        </section>

        <?php jm_minimal_footer(); ?>
    </div>
</body>
</html>
