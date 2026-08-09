<?php
/**
 * JOBMINGTON - Payment Failed Page
 * Feature: Error Handling & Retry Options
 */

define('JOBMINGTON', true);
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';

Session::start();
if (!Session::isLoggedIn()) Session::requireLogin('Sign in to review this payment.');

$pdo = db();
$userId = Session::userId();
$reason = Security::clean(get('reason', 'unknown'));

// Get wallet data
$user = ['seed_balance' => getSeeds($userId)];

$pageTitle = 'Payment Failed - ' . SITE_NAME;
require_once __DIR__ . '/../includes/header.php';
?>

<div class="min-h-screen bg-gradient-to-br from-red-50 to-rose-50 py-16">
    <div class="max-w-2xl mx-auto px-4">
        
        <!-- Error Card -->
        <div class="bg-white rounded-2xl shadow-xl overflow-hidden">
            
            <!-- Error Header -->
            <div class="bg-gradient-to-r from-red-500 to-rose-600 text-white px-8 py-12 text-center">
                <div class="inline-flex items-center justify-center w-20 h-20 bg-white/20 rounded-full mb-4">
                    <i class="fas fa-exclamation-circle text-white text-5xl"></i>
                </div>
                <h1 class="text-3xl font-bold">Payment Failed</h1>
                <p class="text-red-100 mt-2">We encountered an issue processing your payment</p>
            </div>

            <div class="p-8">
                <?php if ($reason === 'insufficient'): ?>
                    <?= jm_illustration_state_card('insufficient_balance', [
                        'compact' => true,
                        'class' => 'mb-8',
                    ]) ?>
                <?php endif; ?>
                
                <!-- Error Details -->
                <div class="bg-red-50 border-l-4 border-red-500 rounded-lg p-6 mb-8">
                    <h3 class="font-bold text-red-900 mb-2">What Happened?</h3>
                    <p class="text-red-800 mb-4">
                        <?php
                        $reasons = [
                            'declined' => 'Your card was declined by the bank. Please check your card details and try again.',
                            'insufficient' => 'Insufficient funds in your account.',
                            'expired' => 'Your card has expired. Please use a valid card.',
                            'timeout' => 'The payment gateway did not respond in time. Please try again.',
                            'cancelled' => 'You cancelled the payment process.',
                            'unknown' => 'An unexpected error occurred. Please contact support.'
                        ];
                        echo htmlspecialchars($reasons[$reason] ?? $reasons['unknown']);
                        ?>
                    </p>
                </div>

                <!-- Current Balance -->
                <div class="bg-blue-50 border-l-4 border-blue-500 rounded-lg p-6 mb-8">
                    <h4 class="font-bold text-blue-900 mb-2 flex items-center gap-2">
                        <i class="fas fa-wallet"></i> Your Current Balance
                    </h4>
                    <p class="text-blue-800">
                        <span class="text-2xl font-bold text-blue-600"><?= number_format($user['seed_balance'] ?? 0, 0) ?></span>
                        <span class="ml-2">Credits Available</span>
                    </p>
                </div>

                <!-- Troubleshooting -->
                <div class="bg-slate-50 rounded-xl p-6 mb-8">
                    <h3 class="font-bold text-slate-900 mb-4">How to Resolve</h3>
                    <div class="space-y-3">
                        <div class="flex items-start gap-4">
                            <i class="fas fa-check-circle text-green-600 mt-1"></i>
                            <div>
                                <p class="font-medium text-slate-900">Verify your card details</p>
                                <p class="text-sm text-slate-600">Ensure card number, expiry, and CVV are correct</p>
                            </div>
                        </div>
                        <div class="flex items-start gap-4">
                            <i class="fas fa-check-circle text-green-600 mt-1"></i>
                            <div>
                                <p class="font-medium text-slate-900">Contact your bank</p>
                                <p class="text-sm text-slate-600">Ask if there are any restrictions on online payments</p>
                            </div>
                        </div>
                        <div class="flex items-start gap-4">
                            <i class="fas fa-check-circle text-green-600 mt-1"></i>
                            <div>
                                <p class="font-medium text-slate-900">Try a different payment method</p>
                                <p class="text-sm text-slate-600">Use bank transfer, USSD, or another card</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="grid grid-cols-2 gap-4">
                    <a href="/jobmington/payments/" class="block text-center bg-slate-800 text-white font-bold py-3 px-6 rounded-xl hover:bg-slate-700 transition duration-300">
                        <i class="fas fa-redo mr-2"></i>Try Again
                    </a>
                    <a href="/jobmington/seeker/settings.php" class="block text-center bg-slate-200 text-slate-900 font-bold py-3 px-6 rounded-xl hover:bg-slate-300 transition duration-300">
                        <i class="fas fa-cog mr-2"></i>Account Settings
                    </a>
                </div>

                <!-- Support -->
                <div class="mt-8 text-center text-slate-600 text-sm border-t border-slate-200 pt-6">
                    <p>Need help? <a href="/jobmington/community/" class="text-slate-800 font-bold hover:underline">Contact Support</a> or visit our community forum.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
