<?php
/**
 * JOBMINGTON - Talent Access Payment Callback
 * 
 * Handle Paystack callback for talent pool subscriptions.
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

$reference = $_GET['reference'] ?? '';
$trxref = $_GET['trxref'] ?? '';

if (!$reference && !$trxref) {
    header('Location: ' . SITE_URL . '/employer/talent-pool.php?error=no_reference');
    exit;
}

$ref = $reference ?: $trxref;

// Verify with Paystack
$paystackKey = getenv('PAYSTACK_SECRET_KEY');

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => 'https://api.paystack.co/transaction/verify/' . urlencode($ref),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $paystackKey
    ]
]);

$response = curl_exec($ch);
curl_close($ch);

$result = json_decode($response, true);

if (!$result || !$result['status'] || $result['data']['status'] !== 'success') {
    header('Location: ' . SITE_URL . '/payments/failed.php?ref=' . urlencode($ref));
    exit;
}

// Get stored purchase info
$purchaseInfo = Session::get('talent_access_purchase');
if (!$purchaseInfo) {
    header('Location: ' . SITE_URL . '/employer/talent-pool.php?error=session_expired');
    exit;
}

$userId = Session::userId();

$stmt = $pdo->prepare("SELECT * FROM companies WHERE user_id = ? ORDER BY company_id ASC LIMIT 1");
$stmt->execute([$userId]);
$company = $stmt->fetch();

if (!$userId || !$company) {
    header('Location: ' . SITE_URL . '/auth/login.php?redirect=' . urlencode('/payments/talent-access-callback.php?reference=' . $ref));
    exit;
}

$companyId = (int) $company['company_id'];

try {
    $pdo->beginTransaction();
    
    // Update transaction
    $stmt = $pdo->prepare("
        UPDATE transactions
        SET status = 'completed',
            paystack_ref = ?,
            paystack_data = ?,
            completed_at = NOW()
        WHERE id = ? AND user_id = ?
    ");
    $stmt->execute([$ref, json_encode($result['data'] ?? []), $purchaseInfo['transaction_id'], $userId]);
    
    if ($purchaseInfo['type'] === 'single') {
        // Single contact - add 1 contact to existing or create minimal access
        $stmt = $pdo->prepare("
            SELECT access_id FROM employer_talent_access 
            WHERE company_id = ? AND user_id = ? AND status = 'active' AND expires_at > NOW()
            LIMIT 1
        ");
        $stmt->execute([$companyId, $userId]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            // Add to existing
            $stmt = $pdo->prepare("
                UPDATE employer_talent_access 
                SET contacts_allowed = contacts_allowed + 1
                WHERE access_id = ?
            ");
            $stmt->execute([$existing['access_id']]);
        } else {
            // Create minimal access
            $stmt = $pdo->prepare("
                INSERT INTO employer_talent_access
                (company_id, user_id, access_tier, subscription_type, price_paid, currency, contacts_allowed, contacts_used,
                 exports_allowed, exports_used, can_access_rising, can_access_verified, can_access_expert, can_access_elite,
                 status, starts_at, expires_at, created_at)
                VALUES (?, ?, 'basic', 'paypercontact', ?, 'USD', 1, 0, 0, 0, 1, 0, 0, 0, 'active', NOW(), DATE_ADD(NOW(), INTERVAL 30 DAY), NOW())
            ");
            $stmt->execute([$companyId, $userId, $purchaseInfo['amount'] ?? 5]);
        }
    } else {
        // Subscription plan
        $plan = $purchaseInfo['plan_details'];
        
        // Determine access tier and permissions
        $tier = 'basic';
        $canVerified = 0;
        $canExpert = 0;
        $canElite = 0;
        
        $planName = strtolower($plan['item_name']);
        if (strpos($planName, 'vip') !== false) {
            $tier = 'vip';
            $canVerified = 1;
            $canExpert = 1;
            $canElite = 1;
        } elseif (strpos($planName, 'premium') !== false) {
            $tier = 'premium';
            $canVerified = 1;
            $canExpert = 1;
        } elseif (strpos($planName, 'basic') !== false) {
            $tier = 'basic';
            $canVerified = 1;
        }
        
        // Deactivate old access
        $stmt = $pdo->prepare("
            UPDATE employer_talent_access
            SET status = 'cancelled'
            WHERE company_id = ? AND user_id = ? AND status = 'active'
        ");
        $stmt->execute([$companyId, $userId]);
        
        // Create new access
        $stmt = $pdo->prepare("
            INSERT INTO employer_talent_access
            (company_id, user_id, access_tier, contacts_allowed, contacts_used, exports_allowed, exports_used, 
             can_access_rising, can_access_verified, can_access_expert, can_access_elite, subscription_type, price_paid,
             currency, starts_at, expires_at, status, created_at)
            VALUES (?, ?, ?, ?, 0, ?, 0, 1, ?, ?, ?, ?, ?, 'USD', NOW(), DATE_ADD(NOW(), INTERVAL ? DAY), 'active', NOW())
        ");
        $stmt->execute([
            $companyId, 
            $userId, 
            $tier,
            $plan['includes_contacts'],
            $plan['includes_exports'] ?? 0,
            $canVerified,
            $canExpert,
            $canElite,
            ((int) ($plan['duration_days'] ?? 30) >= 365 ? 'yearly' : 'monthly'),
            $purchaseInfo['amount'] ?? ($plan['price_cash'] ?? 0),
            $plan['duration_days']
        ]);
    }
    
    // Clear session
    Session::remove('talent_access_purchase');
    
    $pdo->commit();

    // Advertising conversion, in the currency Paystack charged.
    require_once __DIR__ . '/../includes/pixel.php';
    jm_pixel_track('Purchase', [
        'value'        => round((float) ($purchaseInfo['amount'] ?? ($plan['price_cash'] ?? 0)), 2),
        'currency'     => 'NGN',
        'content_name' => 'talent_access',
    ]);

    // Redirect to success
    header('Location: ' . SITE_URL . '/employer/talent-pool.php?success=1');
    exit;
    
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Talent access callback error: " . $e->getMessage());
    header('Location: ' . SITE_URL . '/employer/talent-pool.php?error=processing_failed');
    exit;
}
