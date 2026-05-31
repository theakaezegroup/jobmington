<?php
if (!defined('JOBMINGTON')) {
    die('Direct access not permitted');
}

/**
 * Returns the active seeker subscription row, or null.
 */
function jm_seeker_subscription(PDO $pdo, int $userId): ?array {
    $stmt = $pdo->prepare("
        SELECT * FROM seeker_subscriptions
        WHERE user_id = ? AND status = 'active' AND expires_at > NOW()
        ORDER BY expires_at DESC
        LIMIT 1
    ");
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * True if the user has an active premium subscription.
 */
function jm_seeker_is_premium(PDO $pdo, int $userId): bool {
    return jm_seeker_subscription($pdo, $userId) !== null;
}

/**
 * Returns the user's current tool credit balance.
 */
function jm_seeker_credit_balance(PDO $pdo, int $userId): int {
    $stmt = $pdo->prepare("SELECT tool_credits FROM wallets WHERE user_id = ? LIMIT 1");
    $stmt->execute([$userId]);
    return (int) ($stmt->fetchColumn() ?: 0);
}

/**
 * True if the user can use a given tool (premium OR has enough credits).
 */
function jm_seeker_can_use_tool(PDO $pdo, int $userId, string $toolId): bool {
    if (jm_seeker_is_premium($pdo, $userId)) {
        return true;
    }
    $tool = jm_ai_tool($toolId);
    if (empty($tool) || ($tool['is_free'] ?? false)) {
        return true;
    }
    return jm_seeker_credit_balance($pdo, $userId) >= ($tool['credit_cost'] ?? 1);
}

/**
 * Deduct credits for a tool use. Returns true on success.
 * Logs usage to tool_usage_log.
 */
function jm_seeker_spend_credit(PDO $pdo, int $userId, string $toolId): bool {
    $tool = jm_ai_tool($toolId);
    if (empty($tool)) {
        return false;
    }

    $isPremium = jm_seeker_is_premium($pdo, $userId);
    $cost      = (int) ($tool['credit_cost'] ?? 1);
    $source    = 'credit';

    if ($isPremium) {
        $source = 'premium';
        $cost   = 0;
    } elseif ($tool['is_free'] ?? false) {
        $source = 'free';
        $cost   = 0;
    } else {
        // Deduct from wallet
        $stmt = $pdo->prepare("
            UPDATE wallets SET tool_credits = tool_credits - ?
            WHERE user_id = ? AND tool_credits >= ?
        ");
        $stmt->execute([$cost, $userId, $cost]);
        if ($stmt->rowCount() === 0) {
            return false; // insufficient credits
        }
    }

    $stmt = $pdo->prepare("
        INSERT INTO tool_usage_log (user_id, tool, credits_used, source, created_at)
        VALUES (?, ?, ?, ?, NOW())
    ");
    $stmt->execute([$userId, $toolId, $cost, $source]);

    return true;
}

/**
 * Add tool credits to a user's wallet. Logs a transaction.
 */
function jm_seeker_add_credits(PDO $pdo, int $userId, int $credits, string $txnRef): void {
    // Upsert wallet
    $pdo->prepare("
        INSERT INTO wallets (user_id, tool_credits, created_at)
        VALUES (?, ?, NOW())
        ON DUPLICATE KEY UPDATE tool_credits = tool_credits + ?
    ")->execute([$userId, $credits, $credits]);
}

/**
 * Activate a seeker subscription after successful payment.
 */
function jm_activate_seeker_subscription(
    PDO    $pdo,
    int    $userId,
    string $plan,
    float  $amountNgn,
    string $txnRef,
    string $paystackSubCode = '',
    string $paystackCustomerCode = ''
): void {
    $intervalDays = $plan === 'annual' ? 365 : 31;
    $expiresAt    = date('Y-m-d H:i:s', strtotime("+{$intervalDays} days"));

    // Cancel any existing active subscriptions first
    $pdo->prepare("
        UPDATE seeker_subscriptions SET status = 'cancelled' WHERE user_id = ? AND status = 'active'
    ")->execute([$userId]);

    $pdo->prepare("
        INSERT INTO seeker_subscriptions
            (user_id, plan, status, amount_ngn, txn_ref, paystack_sub_code, paystack_customer_code, starts_at, expires_at)
        VALUES (?, ?, 'active', ?, ?, ?, ?, NOW(), ?)
    ")->execute([$userId, $plan, $amountNgn, $txnRef, $paystackSubCode, $paystackCustomerCode, $expiresAt]);
}

/**
 * Render an inline paywall banner for a tool page.
 * Call this at the top of any AI tool that requires premium/credits.
 * Returns true if the user is allowed through, false if blocked.
 */
function jm_tool_paywall_check(PDO $pdo, int $userId, string $toolId, bool $redirect = true): bool {
    $tool = jm_ai_tool($toolId);
    if (empty($tool) || ($tool['is_free'] ?? false)) {
        return true;
    }
    if (jm_seeker_can_use_tool($pdo, $userId, $toolId)) {
        return true;
    }
    if ($redirect) {
        $enc = urlencode($toolId);
        header('Location: ' . SITE_URL . '/payments/credits.php?tool=' . $enc . '&paywall=1');
        exit;
    }
    return false;
}

/**
 * Returns HTML for a paywall upsell modal/banner (inline, no redirect).
 */
function jm_paywall_banner(string $toolId, int $creditBalance): string {
    $tool       = jm_ai_tool($toolId);
    $toolName   = $tool['name'] ?? 'this tool';
    $creditCost = $tool['credit_cost'] ?? 1;
    $premPrice  = jm_format_ngn(PRICE_SEEKER_PREMIUM_MONTHLY);
    $credPrice  = jm_format_ngn(PRICE_CREDITS_SINGLE * $creditCost);

    ob_start(); ?>
    <div class="jm-paywall-banner" role="alert">
        <div class="jm-paywall-inner">
            <div class="jm-paywall-icon">🔒</div>
            <div class="jm-paywall-body">
                <h3>Unlock <?= htmlspecialchars($toolName) ?></h3>
                <p>You need <?= $creditCost ?> credit<?= $creditCost > 1 ? 's' : '' ?> to use this tool.
                   Your balance: <strong><?= $creditBalance ?></strong> credit<?= $creditBalance !== 1 ? 's' : '' ?>.</p>
                <div class="jm-paywall-actions">
                    <a class="jm-button" href="<?= SITE_URL ?>/payments/seeker-premium.php">
                        Go Premium — <?= $premPrice ?>/mo
                    </a>
                    <a class="jm-button secondary" href="<?= SITE_URL ?>/payments/credits.php?tool=<?= urlencode($toolId) ?>">
                        Buy credits from <?= $credPrice ?>
                    </a>
                </div>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
