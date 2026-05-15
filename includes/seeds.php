<?php
/**
 * Seeds Currency Helper Functions
 * Core functions for managing the Seeds wallet system
 */

/**
 * Get user's wallet balance
 * 
 * @param int $userId
 * @return float
 */
function getSeedBalance(int $userId): float {
    $pdo = db();
    
    try {
        $stmt = $pdo->prepare("SELECT balance FROM wallets WHERE user_id = ?");
        $stmt->execute([$userId]);
        $result = $stmt->fetch();
        
        if ($result) {
            return (float) $result['balance'];
        }
        
        // Create wallet if doesn't exist
        createWallet($userId);
        return 0.00;
    } catch (Exception $e) {
        return 0.00;
    }
}

/**
 * Get user's full wallet info
 * 
 * @param int $userId
 * @return array|null
 */
function getWallet(int $userId): ?array {
    $pdo = db();
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM wallets WHERE user_id = ?");
        $stmt->execute([$userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Create a wallet for a user
 * 
 * @param int $userId
 * @param float $initialBalance
 * @return bool
 */
function createWallet(int $userId, float $initialBalance = 0): bool {
    $pdo = db();
    
    try {
        $stmt = $pdo->prepare("INSERT IGNORE INTO wallets (user_id, balance, lifetime_earned) VALUES (?, ?, ?)");
        return $stmt->execute([$userId, $initialBalance, $initialBalance]);
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Get the seed rate for a specific action
 * 
 * @param string $action
 * @return array|null
 */
function getSeedRate(string $action): ?array {
    $pdo = db();
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM seed_rates WHERE action = ? AND is_active = 1");
        $stmt->execute([$action]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Award seeds to a user (EARN)
 * 
 * @param int $userId
 * @param string $action The action type (e.g., 'course_complete', 'quiz_pass')
 * @param int|null $referenceId Optional ID of related record
 * @param string|null $description Optional custom description
 * @param float|null $customAmount Optional custom amount (overrides rate)
 * @return array ['success' => bool, 'amount' => float, 'balance' => float, 'message' => string]
 */
function awardSeeds(int $userId, string $action, ?int $referenceId = null, ?string $description = null, ?float $customAmount = null): array {
    $pdo = db();
    
    try {
        // Get the rate for this action
        $rate = getSeedRate($action);
        $amount = $customAmount ?? ($rate ? (float)$rate['seeds_amount'] : 0);
        
        if ($amount <= 0) {
            return ['success' => false, 'amount' => 0, 'balance' => getSeedBalance($userId), 'message' => 'Invalid action or amount'];
        }
        
        $desc = $description ?? ($rate ? $rate['description'] : "Earned seeds for {$action}");
        
        $pdo->beginTransaction();
        
        // Update wallet balance
        $stmt = $pdo->prepare("
            INSERT INTO wallets (user_id, balance, lifetime_earned) 
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                balance = balance + VALUES(balance),
                lifetime_earned = lifetime_earned + VALUES(balance)
        ");
        $stmt->execute([$userId, $amount, $amount]);
        
        // Get new balance
        $newBalance = getSeedBalance($userId);
        
        // Record transaction
        $stmt = $pdo->prepare("
            INSERT INTO seed_transactions (user_id, type, amount, balance_after, source, reference_id, description)
            VALUES (?, 'earn', ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$userId, $amount, $newBalance, $action, $referenceId, $desc]);
        
        $pdo->commit();
        
        return [
            'success' => true,
            'amount' => $amount,
            'balance' => $newBalance,
            'message' => "+{$amount} Seeds earned!"
        ];
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return ['success' => false, 'amount' => 0, 'balance' => 0, 'message' => 'Transaction failed: ' . $e->getMessage()];
    }
}

/**
 * Deduct seeds from a user (SPEND)
 * 
 * @param int $userId
 * @param string $action The action type (e.g., 'ai_chat_basic', 'cv_roast')
 * @param int|null $referenceId Optional ID of related record
 * @param string|null $description Optional custom description
 * @param float|null $customAmount Optional custom amount (overrides rate)
 * @return array ['success' => bool, 'amount' => float, 'balance' => float, 'message' => string]
 */
function spendSeeds(int $userId, string $action, ?int $referenceId = null, ?string $description = null, ?float $customAmount = null): array {
    $pdo = db();
    
    try {
        // Get the rate for this action
        $rate = getSeedRate($action);
        $amount = $customAmount ?? ($rate ? (float)$rate['seeds_amount'] : 0);
        
        if ($amount <= 0) {
            return ['success' => false, 'amount' => 0, 'balance' => getSeedBalance($userId), 'message' => 'Invalid action or amount'];
        }
        
        // Check if user has enough balance
        $currentBalance = getSeedBalance($userId);
        if ($currentBalance < $amount) {
            return [
                'success' => false,
                'amount' => 0,
                'balance' => $currentBalance,
                'message' => "Insufficient Seeds. You need {$amount} but have {$currentBalance}."
            ];
        }
        
        $desc = $description ?? ($rate ? $rate['description'] : "Spent seeds on {$action}");
        
        $pdo->beginTransaction();
        
        // Deduct from wallet
        $stmt = $pdo->prepare("
            UPDATE wallets 
            SET balance = balance - ?, 
                lifetime_spent = lifetime_spent + ?
            WHERE user_id = ? AND balance >= ?
        ");
        $stmt->execute([$amount, $amount, $userId, $amount]);
        
        if ($stmt->rowCount() === 0) {
            $pdo->rollBack();
            return ['success' => false, 'amount' => 0, 'balance' => $currentBalance, 'message' => 'Insufficient balance'];
        }
        
        // Get new balance
        $newBalance = getSeedBalance($userId);
        
        // Record transaction
        $stmt = $pdo->prepare("
            INSERT INTO seed_transactions (user_id, type, amount, balance_after, source, reference_id, description)
            VALUES (?, 'spend', ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$userId, $amount, $newBalance, $action, $referenceId, $desc]);
        
        $pdo->commit();
        
        return [
            'success' => true,
            'amount' => $amount,
            'balance' => $newBalance,
            'message' => "-{$amount} Seeds spent"
        ];
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return ['success' => false, 'amount' => 0, 'balance' => 0, 'message' => 'Transaction failed: ' . $e->getMessage()];
    }
}

/**
 * Check if user can afford an action
 * 
 * @param int $userId
 * @param string $action
 * @return bool
 */
function canAfford(int $userId, string $action): bool {
    $rate = getSeedRate($action);
    if (!$rate) return true; // Free if no rate defined
    
    $balance = getSeedBalance($userId);
    return $balance >= (float)$rate['seeds_amount'];
}

/**
 * Get the cost of an action
 * 
 * @param string $action
 * @return float
 */
function getActionCost(string $action): float {
    $rate = getSeedRate($action);
    return $rate ? (float)$rate['seeds_amount'] : 0;
}

/**
 * Get user's recent transactions
 * 
 * @param int $userId
 * @param int $limit
 * @return array
 */
function getRecentTransactions(int $userId, int $limit = 10): array {
    $pdo = db();
    
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM seed_transactions 
            WHERE user_id = ? 
            ORDER BY created_at DESC 
            LIMIT ?
        ");
        $stmt->execute([$userId, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Get transaction history with filters
 * 
 * @param int $userId
 * @param array $filters ['type' => 'earn|spend', 'source' => 'action_name', 'from' => 'date', 'to' => 'date']
 * @param int $limit
 * @param int $offset
 * @return array
 */
function getTransactionHistory(int $userId, array $filters = [], int $limit = 20, int $offset = 0): array {
    $pdo = db();
    
    try {
        $where = ["user_id = ?"];
        $params = [$userId];
        
        if (!empty($filters['type'])) {
            $where[] = "type = ?";
            $params[] = $filters['type'];
        }
        
        if (!empty($filters['source'])) {
            $where[] = "source = ?";
            $params[] = $filters['source'];
        }
        
        if (!empty($filters['from'])) {
            $where[] = "created_at >= ?";
            $params[] = $filters['from'];
        }
        
        if (!empty($filters['to'])) {
            $where[] = "created_at <= ?";
            $params[] = $filters['to'];
        }
        
        $whereClause = implode(' AND ', $where);
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $pdo->prepare("
            SELECT * FROM seed_transactions 
            WHERE {$whereClause}
            ORDER BY created_at DESC 
            LIMIT ? OFFSET ?
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Get all seed packages
 * 
 * @param bool $activeOnly
 * @return array
 */
function getSeedPackages(bool $activeOnly = true): array {
    $pdo = db();
    
    try {
        $where = $activeOnly ? "WHERE is_active = 1" : "";
        $stmt = $pdo->query("SELECT * FROM seed_packages {$where} ORDER BY price_ngn ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Purchase a seed package
 * 
 * @param int $userId
 * @param int $packageId
 * @param string $paymentRef
 * @return array
 */
function purchaseSeedPackage(int $userId, int $packageId, string $paymentRef): array {
    $pdo = db();
    
    try {
        // Get package
        $stmt = $pdo->prepare("SELECT * FROM seed_packages WHERE package_id = ? AND is_active = 1");
        $stmt->execute([$packageId]);
        $package = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$package) {
            return ['success' => false, 'message' => 'Package not found'];
        }
        
        $totalSeeds = $package['seeds_amount'] + $package['bonus_seeds'];
        
        // Award seeds
        $result = awardSeeds(
            $userId, 
            'purchase', 
            $packageId, 
            "Purchased {$package['name']} (+{$package['bonus_seeds']} bonus)",
            $totalSeeds
        );
        
        if ($result['success']) {
            // Record payment reference in metadata
            $stmt = $pdo->prepare("
                UPDATE seed_transactions 
                SET metadata = ? 
                WHERE user_id = ? AND source = 'purchase' AND reference_id = ?
                ORDER BY created_at DESC LIMIT 1
            ");
            $stmt->execute([json_encode(['payment_ref' => $paymentRef, 'package' => $package['name']]), $userId, $packageId]);
        }
        
        return $result;
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Purchase failed: ' . $e->getMessage()];
    }
}

/**
 * Award signup bonus to new user
 * 
 * @param int $userId
 * @return array
 */
function awardSignupBonus(int $userId): array {
    return awardSeeds($userId, 'signup_bonus', null, 'Welcome to Jobmington! Here\'s your starter Seeds.');
}

/**
 * Award email verification bonus
 * 
 * @param int $userId
 * @return array
 */
function awardEmailVerificationBonus(int $userId): array {
    return awardSeeds($userId, 'email_verify', null, 'Email verified successfully!');
}

/**
 * Award daily login bonus (once per day)
 * 
 * @param int $userId
 * @return array
 */
function awardDailyLoginBonus(int $userId): array {
    $pdo = db();
    
    try {
        // Check if already awarded today
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM seed_transactions 
            WHERE user_id = ? AND source = 'daily_login' 
            AND DATE(created_at) = CURDATE()
        ");
        $stmt->execute([$userId]);
        
        if ($stmt->fetchColumn() > 0) {
            return ['success' => false, 'message' => 'Daily bonus already claimed today', 'balance' => getSeedBalance($userId)];
        }
        
        return awardSeeds($userId, 'daily_login', null, 'Daily login bonus!');
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Failed to check daily bonus'];
    }
}

/**
 * Get leaderboard of top seed earners
 * 
 * @param int $limit
 * @return array
 */
function getSeedLeaderboard(int $limit = 10): array {
    $pdo = db();
    
    try {
        $stmt = $pdo->prepare("
            SELECT w.*, u.full_name, u.profile_image
            FROM wallets w
            JOIN users u ON w.user_id = u.user_id
            WHERE w.lifetime_earned > 0
            ORDER BY w.lifetime_earned DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Format seeds amount for display
 * 
 * @param float $amount
 * @return string
 */
function formatSeeds(float $amount): string {
    if ($amount >= 1000000) {
        return number_format($amount / 1000000, 1) . 'M';
    }
    if ($amount >= 1000) {
        return number_format($amount / 1000, 1) . 'K';
    }
    return number_format($amount, 0);
}
