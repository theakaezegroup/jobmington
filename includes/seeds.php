<?php
/**
 * Seeds Currency Helper Functions
 * Core functions for managing the Seeds wallet system
 */

/**
 * Ensure the Seeds tables and default commercial model exist.
 *
 * Production deploys can skip standalone migrations, so the helper keeps the
 * wallet model alive whenever a Seeds-backed feature is used.
 */
function ensureSeedsSchema(): void {
    static $ready = false;

    if ($ready) {
        return;
    }

    $pdo = db();

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS wallets (
            wallet_id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL UNIQUE,
            balance DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            lifetime_earned DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            lifetime_spent DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            is_locked TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_user_id (user_id),
            INDEX idx_balance (balance)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS seed_transactions (
            transaction_id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            type ENUM('earn', 'spend', 'bonus', 'refund', 'purchase', 'transfer_in', 'transfer_out') NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            balance_after DECIMAL(12,2) NOT NULL,
            source VARCHAR(50) NOT NULL,
            reference_id INT DEFAULT NULL,
            description VARCHAR(255),
            metadata JSON DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user_id (user_id),
            INDEX idx_type (type),
            INDEX idx_source (source),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS seed_rates (
            rate_id INT AUTO_INCREMENT PRIMARY KEY,
            action VARCHAR(50) NOT NULL UNIQUE,
            seeds_amount DECIMAL(10,2) NOT NULL,
            type ENUM('earn', 'spend') NOT NULL,
            description VARCHAR(255),
            is_active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_action (action)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS seed_packages (
            package_id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            seeds_amount INT NOT NULL,
            price_ngn DECIMAL(10,2) NOT NULL,
            bonus_seeds INT DEFAULT 0,
            is_featured TINYINT(1) DEFAULT 0,
            is_active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $columnExists = static function (string $table, string $column) use ($pdo): bool {
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND COLUMN_NAME = ?
        ");
        $stmt->execute([$table, $column]);
        return (int) $stmt->fetchColumn() > 0;
    };

    $ensureColumn = static function (string $table, string $column, string $sql) use ($columnExists, $pdo): void {
        if (!$columnExists($table, $column)) {
            $pdo->exec($sql);
        }
    };

    $ensureColumn('wallets', 'lifetime_spent', "ALTER TABLE wallets ADD COLUMN lifetime_spent DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER lifetime_earned");
    $ensureColumn('wallets', 'is_locked', "ALTER TABLE wallets ADD COLUMN is_locked TINYINT(1) DEFAULT 0 AFTER lifetime_spent");
    $ensureColumn('wallets', 'created_at', "ALTER TABLE wallets ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER is_locked");
    $ensureColumn('wallets', 'updated_at', "ALTER TABLE wallets ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at");
    $ensureColumn('seed_transactions', 'reference_id', "ALTER TABLE seed_transactions ADD COLUMN reference_id INT DEFAULT NULL AFTER source");
    $ensureColumn('seed_transactions', 'metadata', "ALTER TABLE seed_transactions ADD COLUMN metadata JSON DEFAULT NULL AFTER description");
    $ensureColumn('seed_rates', 'is_active', "ALTER TABLE seed_rates ADD COLUMN is_active TINYINT(1) DEFAULT 1 AFTER description");
    $ensureColumn('seed_packages', 'is_featured', "ALTER TABLE seed_packages ADD COLUMN is_featured TINYINT(1) DEFAULT 0 AFTER bonus_seeds");
    $ensureColumn('seed_packages', 'is_active', "ALTER TABLE seed_packages ADD COLUMN is_active TINYINT(1) DEFAULT 1 AFTER is_featured");

    $rates = [
        ['signup_bonus', 100, 'earn', 'Welcome bonus for new users'],
        ['email_verify', 50, 'earn', 'Verify your email address'],
        ['profile_complete', 100, 'earn', 'Complete your profile (80%+)'],
        ['course_enroll', 10, 'earn', 'Enroll in a course'],
        ['course_complete', 50, 'earn', 'Complete a course'],
        ['quiz_pass', 25, 'earn', 'Pass a quiz (70%+)'],
        ['certificate_earn', 100, 'earn', 'Earn a certificate'],
        ['job_apply', 5, 'earn', 'Apply for a job'],
        ['forum_post', 2, 'earn', 'Create a forum post'],
        ['forum_reply', 1, 'earn', 'Reply to a forum topic'],
        ['forum_helpful', 10, 'earn', 'Post marked as helpful'],
        ['cv_create', 25, 'earn', 'Create your first CV'],
        ['daily_login', 5, 'earn', 'Daily login bonus'],
        ['referral_signup', 200, 'earn', 'Refer a friend who signs up'],
        ['interview_scheduled', 50, 'earn', 'Get an interview scheduled'],
        ['ai_chat_basic', 5, 'spend', 'Basic AI chat message'],
        ['ai_chat_premium', 15, 'spend', 'Premium AI analysis'],
        ['cv_roast', 50, 'spend', 'AI CV roast/review'],
        ['cv_optimize', 100, 'spend', 'AI CV optimization'],
        ['cover_letter', 75, 'spend', 'AI cover letter generation'],
        ['job_boost', 200, 'spend', 'Boost job application visibility'],
        ['profile_boost', 150, 'spend', 'Feature profile for 7 days'],
        ['unlock_premium_course', 500, 'spend', 'Unlock a premium course'],
        ['skill_assessment', 100, 'spend', 'Take a skill assessment'],
        ['interview_practice', 100, 'spend', 'AI interview practice session'],
        ['salary_guide', 0, 'spend', 'Salary insights (free)'],
        ['career_roadmap', 75, 'spend', 'AI career roadmap planning'],
    ];

    $rateExists = $pdo->prepare("SELECT rate_id FROM seed_rates WHERE action = ? LIMIT 1");
    $rateInsert = $pdo->prepare("
        INSERT INTO seed_rates (action, seeds_amount, type, description, is_active)
        VALUES (?, ?, ?, ?, 1)
    ");
    $rateUpdate = $pdo->prepare("
        UPDATE seed_rates
        SET seeds_amount = ?, type = ?, description = ?, is_active = 1
        WHERE rate_id = ?
    ");
    foreach ($rates as $rate) {
        $rateExists->execute([$rate[0]]);
        $rateId = $rateExists->fetchColumn();
        if ($rateId) {
            $rateUpdate->execute([$rate[1], $rate[2], $rate[3], $rateId]);
        } else {
            $rateInsert->execute($rate);
        }
    }

    $packages = [
        ['Starter Pack', 500, 1000, 0, 0],
        ['Growth Pack', 1500, 2500, 150, 0],
        ['Pro Pack', 3500, 5000, 500, 1],
        ['Elite Pack', 8000, 10000, 1500, 0],
        ['Hustler Pack', 20000, 20000, 5000, 0],
    ];

    $packageExists = $pdo->prepare("SELECT package_id FROM seed_packages WHERE name = ? LIMIT 1");
    $packageInsert = $pdo->prepare("
        INSERT INTO seed_packages (name, seeds_amount, price_ngn, bonus_seeds, is_featured, is_active)
        VALUES (?, ?, ?, ?, ?, 1)
    ");
    foreach ($packages as $package) {
        $packageExists->execute([$package[0]]);
        if (!$packageExists->fetchColumn()) {
            $packageInsert->execute($package);
        }
    }

    try {
        $pdo->exec("
            INSERT IGNORE INTO wallets (user_id, balance, lifetime_earned, lifetime_spent)
            SELECT user_id, 100, 100, 0 FROM users
        ");
    } catch (Throwable $e) {
        error_log('Seeds wallet bootstrap skipped: ' . $e->getMessage());
    }

    $ready = true;
}

/**
 * Get user's wallet balance
 * 
 * @param int $userId
 * @return float
 */
function getSeedBalance(int $userId): float {
    ensureSeedsSchema();
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
    ensureSeedsSchema();
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
    ensureSeedsSchema();
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
    ensureSeedsSchema();
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
    ensureSeedsSchema();
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
    ensureSeedsSchema();
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
 * Get a configured spend cost while preserving a code fallback for new installs.
 */
function getActionCostWithFallback(string $action, float $fallbackAmount = 0): float {
    $configuredAmount = getActionCost($action);

    if ($configuredAmount > 0 || $fallbackAmount <= 0) {
        return $configuredAmount;
    }

    return $fallbackAmount;
}

/**
 * Get user's recent transactions
 * 
 * @param int $userId
 * @param int $limit
 * @return array
 */
function getRecentTransactions(int $userId, int $limit = 10): array {
    ensureSeedsSchema();
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
    ensureSeedsSchema();
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
    ensureSeedsSchema();
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
    ensureSeedsSchema();
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
    ensureSeedsSchema();
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
    ensureSeedsSchema();
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
