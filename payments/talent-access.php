<?php
/**
 * JOBMINGTON - Talent Access Payment
 * 
 * Process subscriptions for employer talent pool access.
 */

define('JOBMINGTON', true);

require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';

Session::start();
$pdo = db();

// Must be logged in as employer
if (!Session::isLoggedIn() || !Session::isEmployer()) {
    header('Location: ' . SITE_URL . '/auth/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

$userId = Session::userId();
$userName = Session::get('full_name');
$userEmail = Session::get('email');

$stmt = $pdo->prepare("SELECT * FROM companies WHERE user_id = ? ORDER BY company_id ASC LIMIT 1");
$stmt->execute([$userId]);
$company = $stmt->fetch();

if (!$company) {
    header('Location: ' . SITE_URL . '/employer/company-profile.php?setup=1');
    exit;
}

$companyId = (int) $company['company_id'];

$type = $_GET['type'] ?? 'plan';
$planId = intval($_GET['plan'] ?? 0);

$planDetails = null;
$amount = 0;
$description = '';

if ($type === 'single') {
    // Single contact purchase
    $amount = 5;
    $description = 'Single Talent Contact';
} else {
    // Subscription plan
    $stmt = $pdo->prepare("SELECT * FROM passport_pricing WHERE pricing_id = ? AND item_type = 'employer_sub' AND is_active = 1");
    $stmt->execute([$planId]);
    $planDetails = $stmt->fetch();
    
    if (!$planDetails) {
        header('Location: ' . SITE_URL . '/employer/talent-pool.php?error=invalid_plan');
        exit;
    }
    
    $amount = $planDetails['price_cash'];
    $description = $planDetails['item_name'] . ' - Talent Pool Access';
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::verifyCSRF()) {
        $error = 'Please refresh the page and try again.';
    } else {
    $paymentRef = 'JMTA-' . time() . '-' . $userId;
    
    // Create pending transaction
    $stmt = $pdo->prepare("
        INSERT INTO transactions (user_id, plan, amount, credits, txn_ref, method, status, created_at)
        VALUES (?, ?, ?, ?, ?, 'paystack', 'pending', NOW())
    ");
    $stmt->execute([$userId, $description, $amount, $planDetails['includes_contacts'] ?? 1, $paymentRef]);
    $transactionId = $pdo->lastInsertId();
    
    // Store plan info in session for callback
    Session::set('talent_access_purchase', [
        'transaction_id' => $transactionId,
        'type' => $type,
        'plan_id' => $planId,
        'plan_details' => $planDetails,
        'amount' => $amount
    ]);
    
    // Initialize Paystack
    $paystackKey = getenv('PAYSTACK_SECRET_KEY');
    $amountKobo = $amount * 100 * 1500; // Approx USD to NGN conversion
    
    $postData = [
        'email' => $userEmail,
        'amount' => $amountKobo,
        'reference' => $paymentRef,
        'callback_url' => SITE_URL . '/payments/talent-access-callback.php',
        'metadata' => [
            'user_id' => $userId,
            'company_id' => $companyId,
            'type' => $type,
            'plan_id' => $planId,
            'transaction_id' => $transactionId
        ]
    ];
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => 'https://api.paystack.co/transaction/initialize',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($postData),
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $paystackKey,
            'Content-Type: application/json'
        ]
    ]);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    $result = json_decode($response, true);
    
    if ($result && $result['status']) {
        header('Location: ' . $result['data']['authorization_url']);
        exit;
    } else {
        $error = 'Payment initialization failed';
    }
    }
}

$pageTitle = 'Purchase Talent Access';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="min-h-screen bg-slate-950 flex items-center justify-center p-4">
    <div class="w-full max-w-md">
        
        <div class="text-center mb-8">
            <a href="<?= SITE_URL ?>/employer/talent-pool.php" class="text-slate-400 hover:text-white text-sm">
                <i class="fas fa-arrow-left mr-2"></i> Back to Talent Pool
            </a>
        </div>
        
        <div class="bg-slate-900 border border-white/10 rounded-2xl overflow-hidden">
            
            <!-- Header -->
            <div class="bg-gradient-to-r from-amber-500/10 to-orange-500/10 p-6 text-center border-b border-white/5">
                <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-amber-500/20 flex items-center justify-center">
                    <i class="fas fa-users text-2xl text-amber-400"></i>
                </div>
                <h1 class="text-2xl font-bold text-white">Talent Pool Access</h1>
                <p class="text-slate-400 text-sm mt-1">Connect with verified professionals</p>
            </div>
            
            <!-- Order Summary -->
            <div class="p-6 border-b border-white/5">
                <h2 class="text-xs text-slate-500 uppercase tracking-wider mb-4">Order Summary</h2>
                
                <div class="space-y-3">
                    <div class="flex justify-between text-white">
                        <span><?= e($description) ?></span>
                        <span class="font-bold">$<?= number_format($amount) ?></span>
                    </div>
                    
                    <?php if ($planDetails): ?>
                    <div class="pt-3 border-t border-white/5">
                        <ul class="text-sm text-slate-400 space-y-2">
                            <li><i class="fas fa-check text-emerald-400 mr-2"></i><?= $planDetails['includes_contacts'] ?> talent contacts</li>
                            <?php if ($planDetails['includes_exports']): ?>
                            <li><i class="fas fa-check text-emerald-400 mr-2"></i><?= $planDetails['includes_exports'] ?> profile exports</li>
                            <?php endif; ?>
                            <li><i class="fas fa-check text-emerald-400 mr-2"><?= $planDetails['duration_days'] ?> days access</i></li>
                        </ul>
                    </div>
                    <?php elseif ($type === 'single'): ?>
                    <div class="pt-3 border-t border-white/5">
                        <p class="text-sm text-slate-400">Contact 1 verified talent directly</p>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="mt-4 pt-4 border-t border-white/5 flex justify-between text-lg font-bold">
                    <span class="text-slate-400">Total</span>
                    <span class="text-white">$<?= number_format($amount) ?></span>
                </div>
            </div>
            
            <!-- Payment Form -->
            <form method="POST" class="p-6">
                <?= Security::csrfField() ?>
                <?php if (!empty($error)): ?>
                <div class="mb-4 p-4 rounded-lg bg-red-500/10 border border-red-500/20 text-red-400 text-sm">
                    <i class="fas fa-exclamation-circle mr-2"></i><?= e($error) ?>
                </div>
                <?php endif; ?>
                
                <p class="text-slate-400 text-sm text-center mb-4">
                    Secure payment powered by <strong>Paystack</strong>
                </p>
                
                <button type="submit" class="w-full py-4 rounded-xl bg-gradient-to-r from-amber-500 to-orange-500 text-black font-bold text-lg hover:shadow-lg hover:shadow-amber-500/20 transition">
                    Pay $<?= number_format($amount) ?>
                </button>
                
                <p class="text-center text-slate-500 text-xs mt-4">
                    <i class="fas fa-lock mr-1"></i> Your payment is secure and encrypted
                </p>
            </form>
            
        </div>
        
        <!-- Trust badges -->
        <div class="mt-6 flex justify-center gap-6 text-slate-600 text-xs">
            <span><i class="fas fa-shield-alt mr-1"></i> Secure</span>
            <span><i class="fas fa-undo mr-1"></i> Refundable</span>
            <span><i class="fas fa-headset mr-1"></i> Support</span>
        </div>
        
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
