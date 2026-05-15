<?php
/**
 * JOBMINGTON - Payment System Migration
 * Creates/updates transactions table for Paystack integration
 * 
 * Run this file once: php database/migrations/setup_payments.php
 */

define('JOBMINGTON', true);
require_once __DIR__ . '/../../config/env.php';
require_once __DIR__ . '/../../config/database.php';

$pdo = db();

echo "=== Jobmington Payment System Setup ===\n\n";

try {
    // 1. Create transactions table if not exists
    echo "Creating transactions table...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS transactions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            plan VARCHAR(100) NOT NULL,
            amount DECIMAL(12,2) NOT NULL,
            credits INT DEFAULT 0,
            txn_ref VARCHAR(100) UNIQUE NOT NULL,
            method VARCHAR(50) DEFAULT 'paystack',
            status ENUM('pending', 'completed', 'failed', 'abandoned', 'refunded') DEFAULT 'pending',
            paystack_ref VARCHAR(100) NULL,
            paystack_data JSON NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            completed_at TIMESTAMP NULL,
            INDEX idx_user (user_id),
            INDEX idx_ref (txn_ref),
            INDEX idx_status (status),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo " Transactions table ready\n";
    
    // 2. Add Paystack columns if they don't exist
    echo "\nChecking for Paystack columns...\n";
    
    $columns = $pdo->query("SHOW COLUMNS FROM transactions")->fetchAll(PDO::FETCH_COLUMN);
    
    if (!in_array('paystack_ref', $columns)) {
        $pdo->exec("ALTER TABLE transactions ADD COLUMN paystack_ref VARCHAR(100) NULL AFTER status");
        echo " Added paystack_ref column\n";
    }
    
    if (!in_array('paystack_data', $columns)) {
        $pdo->exec("ALTER TABLE transactions ADD COLUMN paystack_data JSON NULL AFTER paystack_ref");
        echo " Added paystack_data column\n";
    }
    
    // 3. Create payment_methods table for saved cards
    echo "\nCreating payment_methods table...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS payment_methods (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            authorization_code VARCHAR(100) NOT NULL,
            card_type VARCHAR(50),
            last4 VARCHAR(4),
            exp_month VARCHAR(2),
            exp_year VARCHAR(4),
            bank VARCHAR(100),
            channel VARCHAR(50),
            signature VARCHAR(100),
            reusable BOOLEAN DEFAULT TRUE,
            is_default BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user (user_id),
            INDEX idx_auth_code (authorization_code)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo " Payment methods table ready\n";
    
    // 4. Create subscriptions table
    echo "\nCreating subscriptions table...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS subscriptions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            plan_code VARCHAR(100) NOT NULL,
            subscription_code VARCHAR(100) NOT NULL,
            email_token VARCHAR(100),
            status ENUM('active', 'non-renewing', 'attention', 'completed', 'cancelled') DEFAULT 'active',
            amount DECIMAL(12,2) NOT NULL,
            next_payment_date DATE NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_user (user_id),
            INDEX idx_sub_code (subscription_code),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo " Subscriptions table ready\n";
    
    // 5. Create webhook_logs table for debugging
    echo "\nCreating webhook_logs table...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS webhook_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            provider VARCHAR(50) DEFAULT 'paystack',
            event_type VARCHAR(100),
            reference VARCHAR(100),
            payload JSON,
            processed BOOLEAN DEFAULT FALSE,
            error_message TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_event (event_type),
            INDEX idx_ref (reference),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo " Webhook logs table ready\n";
    
    echo "\n=== Payment System Setup Complete! ===\n";
    echo "\nNext steps:\n";
    echo "1. Get your Paystack API keys from: https://dashboard.paystack.com/#/settings/developers\n";
    echo "2. Update config/env.php with your keys:\n";
    echo "   - PAYSTACK_PUBLIC_KEY=pk_test_xxx\n";
    echo "   - PAYSTACK_SECRET_KEY=sk_test_xxx\n";
    echo "3. Configure webhook URL in Paystack dashboard:\n";
    echo "   - URL: https://yourdomain.com/jobmington/api/webhooks/paystack.php\n";
    echo "4. Test with Paystack test cards:\n";
    echo "   - Success: 4084 0840 8408 4081 (any future date, any CVV)\n";
    echo "   - Decline: 4084 0840 8408 4085\n";
    echo "\n";
    
} catch (PDOException $e) {
    echo " Error: " . $e->getMessage() . "\n";
    exit(1);
}
