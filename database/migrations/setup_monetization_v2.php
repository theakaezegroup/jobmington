<?php
/**
 * JOBMINGTON - Monetization v2 Migration
 * Adds seeker_subscriptions, employer_subscriptions, tool_usage_log
 * and extends the transactions table with type + currency columns.
 *
 * Run once:  php database/migrations/setup_monetization_v2.php
 */

define('JOBMINGTON', true);
require_once __DIR__ . '/../../config/env.php';
require_once __DIR__ . '/../../config/database.php';

$pdo = db();
echo "=== Jobmington Monetization v2 Migration ===\n\n";

$steps = [];

// 1. Extend transactions table
$steps[] = ['Extending transactions table', function () use ($pdo) {
    $cols = array_column($pdo->query("SHOW COLUMNS FROM transactions")->fetchAll(), 'Field');

    if (!in_array('type', $cols)) {
        $pdo->exec("ALTER TABLE transactions ADD COLUMN type VARCHAR(50) NOT NULL DEFAULT 'seeds_purchase' AFTER plan");
        echo "  + Added transactions.type\n";
    }
    if (!in_array('currency_code', $cols)) {
        $pdo->exec("ALTER TABLE transactions ADD COLUMN currency_code CHAR(3) NOT NULL DEFAULT 'NGN' AFTER amount");
        echo "  + Added transactions.currency_code\n";
    }
    if (!in_array('ngn_equivalent', $cols)) {
        $pdo->exec("ALTER TABLE transactions ADD COLUMN ngn_equivalent DECIMAL(12,2) DEFAULT NULL AFTER currency_code");
        echo "  + Added transactions.ngn_equivalent\n";
    }
    // Back-fill existing rows
    $pdo->exec("UPDATE transactions SET type = 'seeds_purchase' WHERE type = 'seeds_purchase'");
}];

// 2. seeker_subscriptions
$steps[] = ['Creating seeker_subscriptions table', function () use ($pdo) {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS seeker_subscriptions (
            id                       INT AUTO_INCREMENT PRIMARY KEY,
            user_id                  INT NOT NULL,
            plan                     ENUM('monthly','annual') NOT NULL DEFAULT 'monthly',
            status                   ENUM('active','cancelled','expired','pending') NOT NULL DEFAULT 'pending',
            amount_ngn               DECIMAL(12,2) NOT NULL,
            txn_ref                  VARCHAR(100) NOT NULL,
            paystack_sub_code        VARCHAR(100) DEFAULT NULL,
            paystack_customer_code   VARCHAR(100) DEFAULT NULL,
            paystack_email_token     VARCHAR(200) DEFAULT NULL,
            starts_at                DATETIME DEFAULT NULL,
            expires_at               DATETIME DEFAULT NULL,
            created_at               TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at               TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_user   (user_id),
            INDEX idx_status (status),
            INDEX idx_expiry (expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}];

// 3. employer_subscriptions
$steps[] = ['Creating employer_subscriptions table', function () use ($pdo) {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS employer_subscriptions (
            id                       INT AUTO_INCREMENT PRIMARY KEY,
            user_id                  INT NOT NULL,
            plan                     ENUM('basic','pro') NOT NULL DEFAULT 'basic',
            status                   ENUM('active','cancelled','expired','pending') NOT NULL DEFAULT 'pending',
            amount_ngn               DECIMAL(12,2) NOT NULL,
            txn_ref                  VARCHAR(100) NOT NULL,
            paystack_sub_code        VARCHAR(100) DEFAULT NULL,
            paystack_customer_code   VARCHAR(100) DEFAULT NULL,
            paystack_email_token     VARCHAR(200) DEFAULT NULL,
            job_posts_used           INT NOT NULL DEFAULT 0,
            job_posts_limit          INT NOT NULL DEFAULT 3,
            starts_at                DATETIME DEFAULT NULL,
            expires_at               DATETIME DEFAULT NULL,
            created_at               TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at               TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_user   (user_id),
            INDEX idx_status (status),
            INDEX idx_expiry (expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}];

// 4. tool_credits column on wallets
$steps[] = ['Adding tool_credits to wallets', function () use ($pdo) {
    $cols = array_column($pdo->query("SHOW COLUMNS FROM wallets")->fetchAll(), 'Field');
    if (!in_array('tool_credits', $cols)) {
        $pdo->exec("ALTER TABLE wallets ADD COLUMN tool_credits INT NOT NULL DEFAULT 0 AFTER balance");
        echo "  + Added wallets.tool_credits\n";
    }
}];

// 5. tool_usage_log
$steps[] = ['Creating tool_usage_log table', function () use ($pdo) {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS tool_usage_log (
            id           INT AUTO_INCREMENT PRIMARY KEY,
            user_id      INT NOT NULL,
            tool         VARCHAR(50) NOT NULL,
            credits_used INT NOT NULL DEFAULT 1,
            source       ENUM('premium','credit','free') NOT NULL DEFAULT 'credit',
            created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user (user_id),
            INDEX idx_tool (tool)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}];

// 6. Ensure wallets exist for all users (safe upsert)
$steps[] = ['Ensuring wallets for all users', function () use ($pdo) {
    $pdo->exec("
        INSERT IGNORE INTO wallets (user_id, balance, tool_credits, lifetime_earned, created_at)
        SELECT user_id, 0, 0, 0, NOW() FROM users
    ");
}];

// Run all steps
foreach ($steps as [$label, $fn]) {
    echo "Running: {$label}...\n";
    try {
        $fn();
        echo "  ✓ Done\n";
    } catch (Throwable $e) {
        echo "  ✗ Error: " . $e->getMessage() . "\n";
    }
}

echo "\n=== Migration complete ===\n";
echo "Next: Add Paystack plan codes to .env\n";
echo "  PAYSTACK_PLAN_SEEKER_MONTHLY=PLN_xxx\n";
echo "  PAYSTACK_PLAN_SEEKER_ANNUAL=PLN_xxx\n";
echo "  PAYSTACK_PLAN_EMPLOYER_BASIC=PLN_xxx\n";
echo "  PAYSTACK_PLAN_EMPLOYER_PRO=PLN_xxx\n";
