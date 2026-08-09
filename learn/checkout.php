<?php
require_once __DIR__ . '/_disabled.php';

/**
 * Course Checkout Page
 * Pay with Naira (Paystack) or Seeds
 */

define('JOBMINGTON', true);
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/seeds.php';

Session::start();
$pdo = db();
$userId = Session::userId();

// Must be logged in
if (!Session::isLoggedIn()) {
    $_SESSION['redirect_after_login'] = currentUrl();
    Session::requireLogin('Sign in or create a free account to complete your enrolment.');
}

$courseId = (int) ($_GET['course'] ?? 0);
$method = $_GET['method'] ?? 'naira'; // naira or seeds

if (!$courseId) {
    redirect('/jobmington/learn');
}

// Fetch course
$stmt = $pdo->prepare("SELECT * FROM courses WHERE course_id = ?");
$stmt->execute([$courseId]);
$course = $stmt->fetch();

if (!$course) {
    redirect('/jobmington/learn');
}

// If course is free, just redirect to course page
if ($course['is_free']) {
    redirect('/jobmington/learn/course.php?id=' . $courseId);
}

// Check if already purchased
$stmt = $pdo->prepare("SELECT * FROM course_purchases WHERE user_id = ? AND course_id = ?");
$stmt->execute([$userId, $courseId]);
if ($stmt->fetch()) {
    $_SESSION['success'] = 'You already have access to this course!';
    redirect('/jobmington/learn/course.php?id=' . $courseId);
}

// Get user's seed balance
$seedBalance = getSeeds((int) $userId);

$hasEnoughSeeds = $seedBalance >= ($course['seed_price'] ?? 0);

// Credit price (explicit, or derived from seed price at the bridge rate)
$creditPrice = (int) ($course['credit_price'] ?? 0);
if ($creditPrice <= 0 && (int) ($course['seed_price'] ?? 0) > 0) {
    $creditPrice = (int) ceil((int) $course['seed_price'] / SEEDS_PER_CREDIT);
}
$wallet         = jm_wallet_summary((int) $userId);
$creditBalance  = (int) $wallet['credits'];
$hasEnoughCredits = $creditPrice > 0 && $creditBalance >= $creditPrice;

// Handle Credits payment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pay_with_credits'])) {
    if (!Security::verifyCSRF()) {
        $_SESSION['error'] = 'Invalid request. Please try again.';
        redirect('/jobmington/learn/checkout.php?course=' . $courseId . '&method=credits');
    }
    if ($creditPrice <= 0 || !$hasEnoughCredits) {
        $_SESSION['error'] = 'Insufficient credits for this course.';
        redirect('/jobmington/learn/checkout.php?course=' . $courseId . '&method=credits');
    }
    $pay = jm_pay_with_credits((int) $userId, $creditPrice, 'course_unlock', 'Course: ' . $course['title'], $courseId);
    if (!$pay['success']) {
        $_SESSION['error'] = $pay['message'];
        redirect('/jobmington/learn/checkout.php?course=' . $courseId . '&method=credits');
    }
    try {
        $pdo->prepare("INSERT INTO course_purchases (user_id, course_id, amount, payment_method, transaction_ref) VALUES (?, ?, ?, 'credits', ?)")
            ->execute([$userId, $courseId, $creditPrice, 'CREDITS-' . time() . '-' . $userId]);
        $pdo->prepare("INSERT IGNORE INTO course_enrollments (user_id, course_id, started_at) VALUES (?, ?, NOW())")->execute([$userId, $courseId]);
        $pdo->prepare("UPDATE courses SET enrollment_count = enrollment_count + 1 WHERE course_id = ?")->execute([$courseId]);
    } catch (Throwable $e) { /* purchase recorded via wallet; enrollment best-effort */ }
    $_SESSION['success'] = 'Course unlocked! You spent ' . $creditPrice . ' credit' . ($creditPrice > 1 ? 's' : '') . '.';
    redirect('/jobmington/learn/course.php?id=' . $courseId);
}

// Handle Seeds payment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pay_with_seeds'])) {
    if (!Security::verifyCSRF()) {
        $_SESSION['error'] = 'Invalid request. Please try again.';
        redirect('/jobmington/learn/checkout.php?course=' . $courseId . '&method=seeds');
    }
    
    if (!$hasEnoughSeeds) {
        $_SESSION['error'] = 'Insufficient seeds balance. You need ' . number_format($course['seed_price']) . ' seeds.';
        redirect('/jobmington/learn/checkout.php?course=' . $courseId . '&method=seeds');
    }
    
    try {
        $pdo->beginTransaction();
        
        // Deduct seeds from wallet
        deductSeeds((int) $userId, (float) $course['seed_price'], 'Course purchase: ' . $course['title']);
        
        // Record purchase
        $stmt = $pdo->prepare("
            INSERT INTO course_purchases (user_id, course_id, amount, payment_method, transaction_ref)
            VALUES (?, ?, ?, 'seeds', ?)
        ");
        $stmt->execute([$userId, $courseId, $course['seed_price'], 'SEEDS-' . time() . '-' . $userId]);
        
        // Auto-enroll user
        $stmt = $pdo->prepare("
            INSERT IGNORE INTO course_enrollments (user_id, course_id, started_at)
            VALUES (?, ?, NOW())
        ");
        $stmt->execute([$userId, $courseId]);
        
        // Update course enrollment count
        $pdo->prepare("UPDATE courses SET enrollment_count = enrollment_count + 1 WHERE course_id = ?")->execute([$courseId]);
        
        // Record transaction in wallet history (if table exists)
        try {
            $stmt = $pdo->prepare("
                INSERT INTO wallet_transactions (user_id, type, amount, currency, description, reference, status, created_at)
                VALUES (?, 'debit', ?, 'seeds', ?, ?, 'completed', NOW())
            ");
            $stmt->execute([
                $userId,
                $course['seed_price'],
                'Course purchase: ' . $course['title'],
                'SEEDS-' . time() . '-' . $userId
            ]);
        } catch (Exception $e) {
            // Table may not exist, ignore
        }
        
        $pdo->commit();
        
        $_SESSION['success'] = ' Course unlocked! You spent ' . number_format($course['seed_price']) . ' seeds.';
        redirect('/jobmington/learn/course.php?id=' . $courseId);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = 'Payment failed. Please try again.';
        redirect('/jobmington/learn/checkout.php?course=' . $courseId . '&method=seeds');
    }
}

// Handle Naira payment initiation (Paystack)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pay_with_naira'])) {
    if (!Security::verifyCSRF()) {
        $_SESSION['error'] = 'Invalid request. Please try again.';
        redirect('/jobmington/learn/checkout.php?course=' . $courseId);
    }
    
    // Get user email
    $stmt = $pdo->prepare("SELECT email FROM users WHERE user_id = ?");
    $stmt->execute([$userId]);
    $userEmail = $stmt->fetchColumn();
    
    // Create Paystack payment
    $reference = 'COURSE-' . $courseId . '-' . $userId . '-' . time();
    $amountKobo = $course['price'] * 100; // Convert to kobo
    
    $paystackSecretKey = $_ENV['PAYSTACK_SECRET_KEY'] ?? '';
    
    $data = [
        'email' => $userEmail,
        'amount' => $amountKobo,
        'reference' => $reference,
        'callback_url' => SITE_URL . '/learn/verify-purchase.php?course=' . $courseId,
        'metadata' => [
            'course_id' => $courseId,
            'user_id' => $userId,
            'purchase_type' => 'course'
        ]
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://api.paystack.co/transaction/initialize');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $paystackSecretKey,
        'Content-Type: application/json'
    ]);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    $result = json_decode($response, true);
    
    if ($result && $result['status'] && isset($result['data']['authorization_url'])) {
        // Store reference in session for verification
        $_SESSION['pending_course_purchase'] = [
            'course_id' => $courseId,
            'reference' => $reference,
            'amount' => $course['price']
        ];
        
        // Redirect to Paystack
        redirect($result['data']['authorization_url']);
    } else {
        $_SESSION['error'] = 'Payment initialization failed. Please try again.';
        redirect('/jobmington/learn/checkout.php?course=' . $courseId);
    }
}

// Get provider icon
function getProviderIcon($name) {
    $lower = strtolower($name);
    if (strpos($lower, 'google') !== false) return 'fab fa-google text-blue-500';
    if (strpos($lower, 'hubspot') !== false) return 'fas fa-hubspot text-orange-500';
    if (strpos($lower, 'youtube') !== false) return 'fab fa-youtube text-red-500';
    return 'fas fa-chalkboard-teacher text-primary';
}

$pageTitle = 'Course Checkout | ' . SITE_NAME;
require_once __DIR__ . '/../includes/header.php';
?>

<style>
    .checkout-card {
        background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
    }
    .payment-option {
        transition: all 0.3s ease;
    }
    .payment-option:hover {
        transform: translateY(-2px);
    }
    .payment-option.selected {
        ring: 2px;
        ring-color: #f59e0b;
    }
</style>

<main class="min-h-screen bg-gradient-to-br from-slate-100 to-slate-200 py-8 md:py-12">
    <div class="max-w-4xl mx-auto px-4">
        
        <!-- Back Button -->
        <a href="/jobmington/learn/course.php?id=<?= $courseId ?>" 
           class="inline-flex items-center gap-2 text-slate-600 hover:text-primary mb-6 transition">
            <i class="fas fa-arrow-left"></i> Back to course
        </a>
        
        <div class="grid lg:grid-cols-5 gap-8">
            
            <!-- Course Summary -->
            <div class="lg:col-span-2">
                <div class="checkout-card rounded-2xl p-6 text-white sticky top-24">
                    <div class="text-xs uppercase tracking-wider text-slate-400 mb-4">Unlocking</div>
                    
                    <!-- Course Image -->
                    <?php if ($course['thumbnail'] && strpos($course['thumbnail'], 'http') === 0): ?>
                    <img src="<?= e($course['thumbnail']) ?>" 
                         alt="<?= e($course['title']) ?>" 
                         class="w-full aspect-video object-cover rounded-xl mb-4">
                    <?php endif; ?>
                    
                    <h2 class="text-xl font-bold mb-2"><?= e($course['title']) ?></h2>
                    
                    <div class="flex items-center gap-2 text-slate-400 text-sm mb-4">
                        <i class="<?= getProviderIcon($course['instructor_name']) ?>"></i>
                        <span><?= e($course['instructor_name']) ?></span>
                    </div>
                    
                    <div class="border-t border-slate-600 pt-4 mt-4">
                        <div class="flex justify-between items-center mb-2">
                            <span class="text-slate-400">Price</span>
                            <span class="text-xl font-bold">₦<?= number_format($course['price']) ?></span>
                        </div>
                        <?php if ($course['seed_price'] > 0): ?>
                        <div class="flex justify-between items-center text-green-400">
                            <span>Or with Seeds</span>
                            <span class="font-bold"><?= number_format($course['seed_price']) ?> <i class="fas fa-seedling"></i></span>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="mt-6 pt-4 border-t border-slate-600">
                        <div class="flex items-center gap-2 text-green-400 text-sm">
                            <i class="fas fa-infinity"></i>
                            <span>Lifetime access</span>
                        </div>
                        <?php if ($course['has_certificate']): ?>
                        <div class="flex items-center gap-2 text-amber-400 text-sm mt-2">
                            <i class="fas fa-certificate"></i>
                            <span>Certificate included</span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Payment Options -->
            <div class="lg:col-span-3 space-y-6">
                
                <h1 class="text-2xl font-bold text-slate-900">Choose Payment Method</h1>
                
                <!-- Seeds Payment -->
                <?php if ($course['seed_price'] > 0): ?>
                <div class="bg-white rounded-2xl p-6 shadow-sm payment-option <?= $method === 'seeds' ? 'ring-2 ring-green-500' : '' ?>">
                    <div class="flex items-start gap-4">
                        <div class="w-14 h-14 bg-gradient-to-br from-green-500 to-emerald-600 rounded-xl flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-seedling text-white text-2xl"></i>
                        </div>
                        <div class="flex-1">
                            <h3 class="text-lg font-bold text-slate-900">Pay with Seeds</h3>
                            <p class="text-slate-500 text-sm mb-4">Use your Jobmington seeds balance</p>
                            
                            <div class="bg-slate-50 rounded-xl p-4 mb-4">
                                <div class="flex justify-between items-center">
                                    <span class="text-slate-600">Your balance</span>
                                    <span class="font-bold text-slate-900"><?= number_format($seedBalance) ?> <i class="fas fa-seedling text-green-500"></i></span>
                                </div>
                                <div class="flex justify-between items-center mt-2">
                                    <span class="text-slate-600">Course cost</span>
                                    <span class="font-bold text-slate-900">−<?= number_format($course['seed_price']) ?> <i class="fas fa-seedling text-green-500"></i></span>
                                </div>
                                <div class="border-t border-slate-200 mt-3 pt-3 flex justify-between items-center">
                                    <span class="font-medium text-slate-700">After purchase</span>
                                    <span class="font-bold <?= $hasEnoughSeeds ? 'text-green-600' : 'text-red-600' ?>">
                                        <?= number_format($seedBalance - $course['seed_price']) ?> <i class="fas fa-seedling"></i>
                                    </span>
                                </div>
                            </div>
                            
                            <?php if ($hasEnoughSeeds): ?>
                            <form method="POST">
                                <?= csrf_field() ?>
                                <button type="submit" name="pay_with_seeds" 
                                        class="w-full bg-gradient-to-r from-green-500 to-emerald-600 hover:from-green-400 hover:to-emerald-500 text-white font-bold py-4 rounded-xl transition-all shadow-lg">
                                    <i class="fas fa-check-circle mr-2"></i>
                                    Pay <?= number_format($course['seed_price']) ?> Seeds
                                </button>
                            </form>
                            <?php else: ?>
                            <div class="bg-red-50 border border-red-200 rounded-xl p-4 text-center">
                                <i class="fas fa-exclamation-circle text-red-500 text-xl mb-2"></i>
                                <p class="text-red-700 font-medium">Insufficient seeds</p>
                                <p class="text-red-600 text-sm">You need <?= number_format($course['seed_price'] - $seedBalance) ?> more seeds</p>
                                <a href="/jobmington/wallet/?topup=1"
                                   class="inline-block mt-3 text-sm text-primary hover:underline font-medium">
                                    <i class="fas fa-plus-circle mr-1"></i> Get more seeds
                                </a>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Credits Payment -->
                <?php if ($creditPrice > 0): ?>
                <div class="bg-white rounded-2xl p-6 shadow-sm payment-option <?= $method === 'credits' ? 'ring-2 ring-blue-500' : '' ?>">
                    <div class="flex items-start gap-4">
                        <div class="w-14 h-14 bg-gradient-to-br from-blue-600 to-indigo-700 rounded-xl flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-coins text-white text-2xl"></i>
                        </div>
                        <div class="flex-1">
                            <h3 class="text-lg font-bold text-slate-900">Pay with Credits</h3>
                            <p class="text-slate-500 text-sm mb-4">Use your Jobmington tool credits</p>
                            <div class="bg-slate-50 rounded-xl p-4 mb-4">
                                <div class="flex justify-between items-center">
                                    <span class="text-slate-600">Your credits</span>
                                    <span class="font-bold text-slate-900"><?= number_format($creditBalance) ?> <i class="fas fa-coins text-blue-500"></i></span>
                                </div>
                                <div class="flex justify-between items-center mt-2">
                                    <span class="text-slate-600">Course cost</span>
                                    <span class="font-bold text-slate-900">−<?= number_format($creditPrice) ?> <i class="fas fa-coins text-blue-500"></i></span>
                                </div>
                            </div>
                            <?php if ($hasEnoughCredits): ?>
                            <form method="POST">
                                <?= csrf_field() ?>
                                <button type="submit" name="pay_with_credits"
                                        class="w-full bg-gradient-to-r from-blue-600 to-indigo-700 hover:from-blue-500 hover:to-indigo-600 text-white font-bold py-4 rounded-xl transition-all shadow-lg">
                                    <i class="fas fa-check-circle mr-2"></i>
                                    Pay <?= number_format($creditPrice) ?> Credit<?= $creditPrice > 1 ? 's' : '' ?>
                                </button>
                            </form>
                            <?php else: ?>
                            <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 text-center">
                                <p class="text-blue-700 font-medium">Need <?= number_format($creditPrice - $creditBalance) ?> more credits</p>
                                <a href="/jobmington/wallet/?topup=1" class="inline-block mt-2 text-sm text-primary hover:underline font-medium">
                                    <i class="fas fa-plus-circle mr-1"></i> Get or convert credits
                                </a>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Divider -->
                <?php if ($course['seed_price'] > 0 || $creditPrice > 0): ?>
                <div class="flex items-center gap-4">
                    <div class="flex-1 h-px bg-slate-300"></div>
                    <span class="text-slate-400 text-sm font-medium">OR</span>
                    <div class="flex-1 h-px bg-slate-300"></div>
                </div>
                <?php endif; ?>
                
                <!-- Naira Payment -->
                <div class="bg-white rounded-2xl p-6 shadow-sm payment-option <?= $method === 'naira' ? 'ring-2 ring-amber-500' : '' ?>">
                    <div class="flex items-start gap-4">
                        <div class="w-14 h-14 bg-gradient-to-br from-amber-500 to-orange-600 rounded-xl flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-credit-card text-white text-2xl"></i>
                        </div>
                        <div class="flex-1">
                            <h3 class="text-lg font-bold text-slate-900">Pay with Card</h3>
                            <p class="text-slate-500 text-sm mb-4">Secure payment via Paystack</p>
                            
                            <div class="bg-slate-50 rounded-xl p-4 mb-4">
                                <div class="flex justify-between items-center">
                                    <span class="text-slate-600">Amount</span>
                                    <span class="text-2xl font-bold text-slate-900">₦<?= number_format($course['price']) ?></span>
                                </div>
                            </div>
                            
                            <form method="POST">
                                <?= csrf_field() ?>
                                <button type="submit" name="pay_with_naira" 
                                        class="w-full bg-gradient-to-r from-amber-500 to-orange-600 hover:from-amber-400 hover:to-orange-500 text-slate-900 font-bold py-4 rounded-xl transition-all shadow-lg">
                                    <i class="fas fa-lock mr-2"></i>
                                    Pay ₦<?= number_format($course['price']) ?>
                                </button>
                            </form>
                            
                            <!-- Payment Methods Icons -->
                            <div class="flex items-center justify-center gap-4 mt-4 text-slate-400">
                                <i class="fab fa-cc-visa text-2xl"></i>
                                <i class="fab fa-cc-mastercard text-2xl"></i>
                                <span class="text-xs">Secured by Paystack</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Security Note -->
                <div class="text-center text-slate-500 text-sm">
                    <i class="fas fa-shield-alt text-green-500 mr-1"></i>
                    Your payment is secure and encrypted
                </div>
                
            </div>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
