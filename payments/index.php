<?php
/**
 * JOBMINGTON - Payment Portal (Ultra Premium Edition)
 * Feature: Premium Course Purchases & Wallet Top-Up
 */

define('JOBMINGTON', true);
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';

Session::start();
if (!Session::isLoggedIn()) Session::requireLogin('Sign in to view your payments.');

$pdo = db();
$userId = Session::userId();

// Payment packages
$packages = [
    ['name' => 'Starter', 'amount' => 5000, 'credits' => 500, 'features' => ['5 Course Access', 'Basic Support']],
    ['name' => 'Professional', 'amount' => 15000, 'credits' => 1500, 'features' => ['Unlimited Courses', 'Priority Support', 'Certification']],
    ['name' => 'Enterprise', 'amount' => 50000, 'credits' => 5500, 'features' => ['All Premium', 'Dedicated Manager', 'API Access']],
];

$pageTitle = 'Payment Plans - ' . SITE_NAME;
require_once __DIR__ . '/../includes/header.php';
?>

<div class="min-h-screen bg-gradient-to-br from-slate-50 to-slate-100 py-16">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <!-- Header -->
        <div class="text-center mb-16">
            <h1 class="text-4xl md:text-5xl font-heading font-black text-slate-900 mb-4">Choose Your Plan</h1>
            <p class="text-xl text-slate-600">Unlock unlimited access to premium courses and certifications</p>
        </div>

        <!-- Pricing Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mb-12">
            <?php foreach ($packages as $pkg): ?>
            <div class="bg-white rounded-2xl shadow-lg overflow-hidden hover:shadow-2xl transition duration-300 flex flex-col">
                <div class="bg-gradient-to-r from-slate-800 to-slate-900 text-white px-8 py-6">
                    <h3 class="text-2xl font-bold"><?= htmlspecialchars($pkg['name']) ?></h3>
                    <div class="mt-4 flex items-baseline">
                        <span class="text-5xl font-bold"><?= number_format($pkg['amount'], 0) ?></span>
                        <span class="ml-2 text-slate-300">NGN</span>
                    </div>
                    <p class="text-sm text-slate-300 mt-2"><?= number_format($pkg['credits'], 0) ?> Credits</p>
                </div>
                
                <div class="px-8 py-8 flex-1">
                    <ul class="space-y-4">
                        <?php foreach ($pkg['features'] as $feature): ?>
                        <li class="flex items-start">
                            <i class="fas fa-check-circle text-green-500 mr-3 mt-1"></i>
                            <span class="text-slate-700"><?= htmlspecialchars($feature) ?></span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <div class="px-8 py-6 border-t border-slate-200">
                    <form method="POST" action="/jobmington/payments/checkout.php">
                        <input type="hidden" name="plan" value="<?= htmlspecialchars($pkg['name']) ?>">
                        <input type="hidden" name="amount" value="<?= (int)$pkg['amount'] ?>">
                        <input type="hidden" name="credits" value="<?= (int)$pkg['credits'] ?>">
                        <button type="submit" class="w-full bg-slate-900 text-white font-bold py-3 px-6 rounded-xl hover:bg-slate-800 transition duration-300">
                            Get <?= htmlspecialchars($pkg['name']) ?>
                        </button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Info Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mt-16">
            <div class="bg-white rounded-xl p-8 shadow-md">
                <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mb-4">
                    <i class="fas fa-shield-alt text-blue-600 text-xl"></i>
                </div>
                <h4 class="font-bold text-slate-900 mb-2">Secure Payments</h4>
                <p class="text-slate-600 text-sm">All transactions encrypted with 256-bit SSL security</p>
            </div>

            <div class="bg-white rounded-xl p-8 shadow-md">
                <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center mb-4">
                    <i class="fas fa-clock text-green-600 text-xl"></i>
                </div>
                <h4 class="font-bold text-slate-900 mb-2">Instant Access</h4>
                <p class="text-slate-600 text-sm">Credits added immediately after successful payment</p>
            </div>

            <div class="bg-white rounded-xl p-8 shadow-md">
                <div class="w-12 h-12 bg-slate-100 rounded-lg flex items-center justify-center mb-4">
                    <i class="fas fa-undo text-slate-600 text-xl"></i>
                </div>
                <h4 class="font-bold text-slate-900 mb-2">Money-Back Guarantee</h4>
                <p class="text-slate-600 text-sm">30-day refund policy with no questions asked</p>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
