<?php
/**
 * Migration: Create Seeds/Wallet System Tables
 * Run this to set up the complete Seeds currency system
 */

define('JOBMINGTON', true);
require_once __DIR__ . '/../../config/env.php';
require_once __DIR__ . '/../../config/database.php';

$pdo = db();

echo "<div style='font-family: monospace; padding: 20px; background: #1e293b; color: #f8fafc;'>";
echo "<h2> Setting Up Seeds Currency System...</h2><hr>";

// --- 1. WALLETS TABLE ---
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
echo " wallets table created<br>";

// --- 2. SEED TRANSACTIONS TABLE ---
$pdo->exec("
CREATE TABLE IF NOT EXISTS seed_transactions (
    transaction_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type ENUM('earn', 'spend', 'bonus', 'refund', 'purchase', 'transfer_in', 'transfer_out') NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    balance_after DECIMAL(12,2) NOT NULL,
    source VARCHAR(50) NOT NULL COMMENT 'Source of transaction: course_complete, quiz_pass, job_apply, ai_chat, etc.',
    reference_id INT DEFAULT NULL COMMENT 'ID of related record (course_id, quiz_id, etc.)',
    description VARCHAR(255),
    metadata JSON DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_type (type),
    INDEX idx_source (source),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");
echo " seed_transactions table created<br>";

// --- 3. SEED RATES TABLE (For configuring earn/spend rates) ---
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
echo " seed_rates table created<br>";

// --- 4. SEED PACKAGES TABLE (For purchasing seeds) ---
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
echo " seed_packages table created<br>";

// --- Insert Default Seed Rates (EARN) ---
$earnRates = [
    ['action' => 'signup_bonus', 'amount' => 100, 'desc' => 'Welcome bonus for new users'],
    ['action' => 'email_verify', 'amount' => 50, 'desc' => 'Verify your email address'],
    ['action' => 'profile_complete', 'amount' => 100, 'desc' => 'Complete your profile (80%+)'],
    ['action' => 'course_enroll', 'amount' => 10, 'desc' => 'Enroll in a course'],
    ['action' => 'course_complete', 'amount' => 50, 'desc' => 'Complete a course'],
    ['action' => 'quiz_pass', 'amount' => 25, 'desc' => 'Pass a quiz (70%+)'],
    ['action' => 'certificate_earn', 'amount' => 100, 'desc' => 'Earn a certificate'],
    ['action' => 'job_apply', 'amount' => 5, 'desc' => 'Apply for a job'],
    ['action' => 'forum_post', 'amount' => 2, 'desc' => 'Create a forum post'],
    ['action' => 'forum_reply', 'amount' => 1, 'desc' => 'Reply to a forum topic'],
    ['action' => 'forum_helpful', 'amount' => 10, 'desc' => 'Post marked as helpful'],
    ['action' => 'cv_create', 'amount' => 25, 'desc' => 'Create your first CV'],
    ['action' => 'daily_login', 'amount' => 5, 'desc' => 'Daily login bonus'],
    ['action' => 'referral_signup', 'amount' => 200, 'desc' => 'Refer a friend who signs up'],
    ['action' => 'interview_scheduled', 'amount' => 50, 'desc' => 'Get an interview scheduled'],
];

$earnStmt = $pdo->prepare("INSERT IGNORE INTO seed_rates (action, seeds_amount, type, description) VALUES (?, ?, 'earn', ?)");
foreach ($earnRates as $rate) {
    $earnStmt->execute([$rate['action'], $rate['amount'], $rate['desc']]);
}
echo " " . count($earnRates) . " earn rates configured<br>";

// --- Insert Default Seed Rates (SPEND) ---
$spendRates = [
    ['action' => 'ai_chat_basic', 'amount' => 5, 'desc' => 'Basic AI chat message'],
    ['action' => 'ai_chat_premium', 'amount' => 15, 'desc' => 'Premium AI analysis'],
    ['action' => 'cv_roast', 'amount' => 50, 'desc' => 'AI CV roast/review'],
    ['action' => 'cv_optimize', 'amount' => 100, 'desc' => 'AI CV optimization'],
    ['action' => 'cover_letter', 'amount' => 75, 'desc' => 'AI cover letter generation'],
    ['action' => 'job_boost', 'amount' => 200, 'desc' => 'Boost job application visibility'],
    ['action' => 'profile_boost', 'amount' => 150, 'desc' => 'Feature profile for 7 days'],
    ['action' => 'unlock_premium_course', 'amount' => 500, 'desc' => 'Unlock a premium course'],
    ['action' => 'skill_assessment', 'amount' => 100, 'desc' => 'Take a skill assessment'],
    // Andika AI Tools
    ['action' => 'interview_practice', 'amount' => 100, 'desc' => 'AI interview practice session'],
    ['action' => 'salary_guide', 'amount' => 0, 'desc' => 'Salary insights (free)'],
    ['action' => 'career_roadmap', 'amount' => 75, 'desc' => 'AI career roadmap planning'],
];

$spendStmt = $pdo->prepare("INSERT IGNORE INTO seed_rates (action, seeds_amount, type, description) VALUES (?, ?, 'spend', ?)");
foreach ($spendRates as $rate) {
    $spendStmt->execute([$rate['action'], $rate['amount'], $rate['desc']]);
}
echo " " . count($spendRates) . " spend rates configured<br>";

// --- Insert Default Seed Packages ---
$packages = [
    ['name' => 'Starter Pack', 'seeds' => 500, 'price' => 1000, 'bonus' => 0, 'featured' => 0],
    ['name' => 'Growth Pack', 'seeds' => 1500, 'price' => 2500, 'bonus' => 150, 'featured' => 0],
    ['name' => 'Pro Pack', 'seeds' => 3500, 'price' => 5000, 'bonus' => 500, 'featured' => 1],
    ['name' => 'Elite Pack', 'seeds' => 8000, 'price' => 10000, 'bonus' => 1500, 'featured' => 0],
    ['name' => 'Hustler Pack', 'seeds' => 20000, 'price' => 20000, 'bonus' => 5000, 'featured' => 0],
];

$pkgStmt = $pdo->prepare("INSERT IGNORE INTO seed_packages (name, seeds_amount, price_ngn, bonus_seeds, is_featured) VALUES (?, ?, ?, ?, ?)");
foreach ($packages as $pkg) {
    $pkgStmt->execute([$pkg['name'], $pkg['seeds'], $pkg['price'], $pkg['bonus'], $pkg['featured']]);
}
echo " " . count($packages) . " seed packages created<br>";

// --- Create wallets for existing users ---
$pdo->exec("
INSERT IGNORE INTO wallets (user_id, balance, lifetime_earned)
SELECT user_id, 100, 100 FROM users WHERE user_id NOT IN (SELECT user_id FROM wallets)
");
$newWallets = $pdo->query("SELECT ROW_COUNT()")->fetchColumn();
echo " Created wallets for existing users (with 100 Seeds bonus)<br>";

echo "<hr><h3> Seeds Currency System Ready!</h3>";

// Display rate summary
echo "<h4> Earn Rates:</h4>";
echo "<table style='width: 100%; border-collapse: collapse; font-size: 12px;'>";
foreach ($earnRates as $rate) {
    echo "<tr><td style='padding: 4px; border-bottom: 1px solid #334155;'>{$rate['desc']}</td>";
    echo "<td style='padding: 4px; border-bottom: 1px solid #334155; text-align: right; color: #22c55e;'>+{$rate['amount']} Seeds</td></tr>";
}
echo "</table>";

echo "<h4 style='margin-top: 20px;'> Spend Rates:</h4>";
echo "<table style='width: 100%; border-collapse: collapse; font-size: 12px;'>";
foreach ($spendRates as $rate) {
    echo "<tr><td style='padding: 4px; border-bottom: 1px solid #334155;'>{$rate['desc']}</td>";
    echo "<td style='padding: 4px; border-bottom: 1px solid #334155; text-align: right; color: #f59e0b;'>-{$rate['amount']} Seeds</td></tr>";
}
echo "</table>";

echo "<br><a href='/jobmington/wallet/' style='background: #fbbf24; color: black; padding: 10px 20px; text-decoration: none; border-radius: 8px; display: inline-block; font-weight: bold;'>Open Wallet →</a>";
echo "</div>";
