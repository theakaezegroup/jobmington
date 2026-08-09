<?php
/**
 * JOBMINGTON - Payment Success Page
 * Feature: Transaction Confirmation & Credit Distribution
 */

define('JOBMINGTON', true);
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';

Session::start();
if (!Session::isLoggedIn()) Session::requireLogin('Sign in to view your payment receipt.');

$pdo = db();
$userId = Session::userId();
$txnRef = Security::clean(get('ref', ''));

// Verify transaction
$transaction = null;
if ($txnRef) {
    $stmt = $pdo->prepare("SELECT * FROM transactions WHERE txn_ref = ? AND user_id = ?");
    $stmt->execute([$txnRef, $userId]);
    $transaction = $stmt->fetch();
}

// If transaction found and not yet credited, update it
if ($transaction && $transaction['status'] === 'pending') {
    try {
        $pdo->beginTransaction();
        
        // Mark transaction as successful
        $stmt = $pdo->prepare("UPDATE transactions SET status = 'completed', completed_at = NOW() WHERE txn_ref = ?");
        $stmt->execute([$txnRef]);
        
        // Add credits to user wallet
        $stmt = $pdo->prepare("
            INSERT INTO wallets (user_id, balance, lifetime_earned, lifetime_spent)
            VALUES (?, ?, ?, 0)
            ON DUPLICATE KEY UPDATE
                balance = balance + VALUES(balance),
                lifetime_earned = lifetime_earned + VALUES(balance)
        ");
        $stmt->execute([$userId, $transaction['credits'], $transaction['credits']]);

        $balance = getSeeds($userId);
        $stmt = $pdo->prepare("
            INSERT INTO seed_transactions (user_id, type, amount, balance_after, source, reference_id, description, created_at)
            VALUES (?, 'purchase', ?, ?, 'payment', ?, ?, NOW())
        ");
        $stmt->execute([
            $userId,
            $transaction['credits'],
            $balance,
            $transaction['id'],
            'Purchased ' . number_format((int) $transaction['credits']) . ' Seeds'
        ]);
        
        // Log activity
        Security::logActivity($userId, 'payment_received', 'Received ' . $transaction['credits'] . ' credits');
        
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Payment completion error: " . $e->getMessage());
    }
}

// Get updated wallet info
$user = ['seed_balance' => getSeeds($userId)];

$pageTitle = 'Payment Successful - ' . SITE_NAME;
require_once __DIR__ . '/../includes/header.php';
?>

<div class="min-h-screen bg-gradient-to-br from-green-50 to-emerald-50 py-16">
    <div class="max-w-2xl mx-auto px-4">
        
        <!-- Success Card -->
        <div class="bg-white rounded-2xl shadow-xl overflow-hidden">
            
            <!-- Success Header -->
            <div class="bg-white px-8 py-10 text-center">
                <?= jm_illustration_state_card('payment_successful', [
                    'compact' => true,
                    'class' => 'border-0',
                ]) ?>
            </div>

            <div class="p-8">
                
                <!-- Transaction Details -->
                <?php if ($transaction): ?>
                <div class="bg-slate-50 rounded-xl p-6 mb-8">
                    <h3 class="font-bold text-slate-900 mb-4 flex items-center gap-2">
                        <i class="fas fa-receipt"></i> Transaction Details
                    </h3>
                    <div class="space-y-3">
                        <div class="flex justify-between items-center pb-3 border-b border-slate-200">
                            <span class="text-slate-600">Plan</span>
                            <span class="font-bold text-slate-900"><?= htmlspecialchars($transaction['plan']) ?></span>
                        </div>
                        <div class="flex justify-between items-center pb-3 border-b border-slate-200">
                            <span class="text-slate-600">Amount Paid</span>
                            <span class="font-bold text-slate-900"><?= number_format($transaction['amount'], 0) ?> NGN</span>
                        </div>
                        <div class="flex justify-between items-center pb-3 border-b border-slate-200">
                            <span class="text-slate-600">Credits Added</span>
                            <span class="font-bold text-green-600"><?= number_format($transaction['credits'], 0) ?> Credits</span>
                        </div>
                        <div class="flex justify-between items-center pb-3 border-b border-slate-200">
                            <span class="text-slate-600">Reference</span>
                            <span class="text-xs font-mono text-slate-500"><?= htmlspecialchars($transaction['txn_ref']) ?></span>
                        </div>
                        <div class="flex justify-between items-center pt-3">
                            <span class="text-slate-600">Status</span>
                            <span class="inline-flex items-center gap-2 px-3 py-1 bg-green-100 text-green-700 rounded-full text-sm font-bold">
                                <i class="fas fa-check-circle"></i> Completed
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Wallet Update -->
                <div class="bg-blue-50 border-l-4 border-blue-500 rounded-lg p-6 mb-8">
                    <h4 class="font-bold text-blue-900 mb-2 flex items-center gap-2">
                        <i class="fas fa-wallet"></i> Your Wallet Updated
                    </h4>
                    <p class="text-blue-800">
                        <span class="text-2xl font-bold text-blue-600"><?= number_format($user['seed_balance'] ?? 0, 0) ?></span>
                        <span class="ml-2">Credits Available</span>
                    </p>
                </div>
                <?php else: ?>
                <div class="bg-amber-50 border-l-4 border-amber-500 rounded-lg p-6 mb-8">
                    <p class="text-amber-900 font-medium">Transaction details could not be retrieved. Please check your email for confirmation.</p>
                </div>
                <?php endif; ?>

                <!-- Next Steps -->
                <div class="bg-slate-50 rounded-xl p-6 mb-8">
                    <h3 class="font-bold text-slate-900 mb-4">What's Next?</h3>
                    <div class="space-y-3">
                        <div class="flex items-start gap-4">
                            <div class="flex-shrink-0 w-8 h-8 bg-green-100 rounded-full flex items-center justify-center text-green-600 font-bold">1</div>
                            <div>
                                <p class="font-medium text-slate-900">Explore Courses</p>
                                <p class="text-sm text-slate-600">Browse our premium course library</p>
                            </div>
                        </div>
                        <div class="flex items-start gap-4">
                            <div class="flex-shrink-0 w-8 h-8 bg-green-100 rounded-full flex items-center justify-center text-green-600 font-bold">2</div>
                            <div>
                                <p class="font-medium text-slate-900">Earn Certificates</p>
                                <p class="text-sm text-slate-600">Complete courses and get verified certificates</p>
                            </div>
                        </div>
                        <div class="flex items-start gap-4">
                            <div class="flex-shrink-0 w-8 h-8 bg-green-100 rounded-full flex items-center justify-center text-green-600 font-bold">3</div>
                            <div>
                                <p class="font-medium text-slate-900">Grow Your Career</p>
                                <p class="text-sm text-slate-600">Stand out to top employers globally</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="grid grid-cols-2 gap-4">
                    <a href="/jobmington/jobs/" class="block text-center bg-slate-800 text-white font-bold py-3 px-6 rounded-xl hover:bg-slate-700 transition duration-300">
                        <i class="fas fa-briefcase mr-2"></i>Browse Jobs
                    </a>
                    <a href="/jobmington/wallet/" class="block text-center bg-slate-200 text-slate-900 font-bold py-3 px-6 rounded-xl hover:bg-slate-300 transition duration-300">
                        <i class="fas fa-wallet mr-2"></i>My Wallet
                    </a>
                </div>

                <!-- Email Confirmation -->
                <div class="mt-8 text-center text-slate-600 text-sm border-t border-slate-200 pt-6">
                    <p><i class="fas fa-envelope text-slate-600"></i> A confirmation email has been sent to your registered email address.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
