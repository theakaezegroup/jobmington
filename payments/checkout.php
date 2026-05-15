<?php
/**
 * JOBMINGTON - Payment Checkout
 * Feature: Stripe/Paystack Integration
 */

define('JOBMINGTON', true);
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';

Session::start();
if (!Session::isLoggedIn()) redirect('/jobmington/auth/login.php');

$pdo = db();
$userId = Session::userId();

// Get user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

// Validate POST data
$plan = Security::clean(post('plan', ''));
$amount = (int)post('amount', 0);
$credits = (int)post('credits', 0);

if (!$plan || $amount <= 0) {
    Session::flash('error', 'Invalid payment parameters');
    redirect('/jobmington/payments/');
}

// Generate transaction reference
$txnRef = 'JMT-' . time() . '-' . bin2hex(random_bytes(4));

$pageTitle = 'Checkout - ' . SITE_NAME;
require_once __DIR__ . '/../includes/header.php';
?>

<div class="min-h-screen bg-gradient-to-br from-slate-50 to-slate-100 py-16">
    <div class="max-w-2xl mx-auto px-4">
        
        <!-- Checkout Card -->
        <div class="bg-white rounded-2xl shadow-xl overflow-hidden">
            
            <!-- Header -->
            <div class="bg-gradient-to-r from-slate-800 to-slate-900 text-white px-8 py-8">
                <h1 class="text-3xl font-bold">Complete Purchase</h1>
                <p class="text-slate-300 mt-2">Transaction Reference: <?= htmlspecialchars($txnRef) ?></p>
            </div>

            <div class="p-8">
                
                <!-- Order Summary -->
                <div class="bg-slate-50 rounded-xl p-6 mb-8">
                    <h3 class="font-bold text-slate-900 mb-4">Order Summary</h3>
                    <div class="space-y-3">
                        <div class="flex justify-between items-center pb-3 border-b border-slate-200">
                            <span class="text-slate-600"><?= htmlspecialchars($plan) ?> Plan</span>
                            <span class="font-bold text-slate-900"><?= number_format($amount, 0) ?> NGN</span>
                        </div>
                        <div class="flex justify-between items-center pb-3 border-b border-slate-200">
                            <span class="text-slate-600">Credits</span>
                            <span class="font-bold text-slate-900"><?= number_format($credits, 0) ?> Credits</span>
                        </div>
                        <div class="flex justify-between items-center pt-3">
                            <span class="text-lg font-bold text-slate-900">Total</span>
                            <span class="text-2xl font-bold text-slate-800"><?= number_format($amount, 0) ?> NGN</span>
                        </div>
                    </div>
                </div>

                <!-- Customer Info -->
                <div class="mb-8">
                    <h3 class="font-bold text-slate-900 mb-4">Billing Information</h3>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">Full Name</label>
                            <input type="text" value="<?= htmlspecialchars($user['full_name'] ?? $user['first_name'] . ' ' . $user['last_name']) ?>" disabled class="w-full px-4 py-2 bg-slate-100 text-slate-600 rounded-lg border border-slate-200">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">Email</label>
                            <input type="email" value="<?= htmlspecialchars($user['email']) ?>" disabled class="w-full px-4 py-2 bg-slate-100 text-slate-600 rounded-lg border border-slate-200">
                        </div>
                    </div>
                </div>

                <!-- Payment Method -->
                <form method="POST" action="/jobmington/payments/process.php" class="space-y-6">
                    <input type="hidden" name="plan" value="<?= htmlspecialchars($plan) ?>">
                    <input type="hidden" name="amount" value="<?= (int)$amount ?>">
                    <input type="hidden" name="credits" value="<?= (int)$credits ?>">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">

                    <div>
                        <h3 class="font-bold text-slate-900 mb-4">Select Payment Method</h3>
                        <div class="space-y-3">
                            <label class="flex items-center p-4 border-2 border-slate-800 rounded-lg cursor-pointer bg-slate-50">
                                <input type="radio" name="payment_method" value="paystack" checked class="mr-4 w-5 h-5 text-slate-800">
                                <div>
                                    <span class="font-bold text-slate-900">Paystack</span>
                                    <p class="text-sm text-slate-600">Debit Card, Bank Transfer, USSD</p>
                                </div>
                            </label>
                            
                            <label class="flex items-center p-4 border-2 border-slate-200 rounded-lg cursor-pointer hover:border-slate-300">
                                <input type="radio" name="payment_method" value="stripe" class="mr-4 w-5 h-5 text-slate-800">
                                <div>
                                    <span class="font-bold text-slate-900">Stripe</span>
                                    <p class="text-sm text-slate-600">International Credit Cards</p>
                                </div>
                            </label>
                        </div>
                    </div>

                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                        <p class="text-sm text-blue-900">
                            <i class="fas fa-info-circle mr-2"></i>
                            Your payment is secure and encrypted. You will be redirected to the payment provider.
                        </p>
                    </div>

                    <button type="submit" class="w-full bg-gradient-to-r from-slate-800 to-slate-900 text-white font-bold py-3 px-6 rounded-xl hover:shadow-lg transition duration-300 flex items-center justify-center gap-2">
                        <i class="fas fa-lock"></i>
                        Proceed to Payment - <?= number_format($amount, 0) ?> NGN
                    </button>

                    <a href="/jobmington/payments/" class="block text-center text-slate-600 hover:text-slate-900 font-medium">
                        <i class="fas fa-arrow-left mr-2"></i>Back to Plans
                    </a>
                </form>
            </div>
        </div>

        <!-- Security Info -->
        <div class="mt-8 text-center text-slate-600 text-sm">
            <p><i class="fas fa-shield-alt text-green-500"></i> Secure Checkout Powered by Paystack & Stripe</p>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
