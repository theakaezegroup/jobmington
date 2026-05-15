<?php
/**
 * Migration: Create user_badges table
 * Run this migration to set up the verification badges system
 */

require_once __DIR__ . '/../config/database.php';

// Create user_badges table
$sql = "
CREATE TABLE IF NOT EXISTS user_badges (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    badge_type VARCHAR(50) NOT NULL,
    earned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    awarded_by INT DEFAULT NULL COMMENT 'Admin who awarded the badge, NULL for auto-awarded',
    notes TEXT DEFAULT NULL,
    UNIQUE KEY unique_user_badge (user_id, badge_type),
    INDEX idx_user_id (user_id),
    INDEX idx_badge_type (badge_type),
    INDEX idx_earned_at (earned_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

if ($conn->query($sql)) {
    echo " user_badges table created successfully\n";
} else {
    echo " Error creating user_badges table: " . $conn->error . "\n";
}

// Add verified badge to all users with verified emails
$sql = "
INSERT IGNORE INTO user_badges (user_id, badge_type, earned_at, notes)
SELECT id, 'verified-email', email_verified_at, 'Auto-awarded on email verification'
FROM users 
WHERE email_verified_at IS NOT NULL
";

if ($conn->query($sql)) {
    $affected = $conn->affected_rows;
    echo " Awarded 'verified-email' badge to {$affected} users with verified emails\n";
} else {
    echo " Error awarding badges: " . $conn->error . "\n";
}

// Add new member badge to users joined in last 30 days
$sql = "
INSERT IGNORE INTO user_badges (user_id, badge_type, earned_at, notes)
SELECT id, 'verified-new-member', created_at, 'Auto-awarded on registration'
FROM users 
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
";

if ($conn->query($sql)) {
    $affected = $conn->affected_rows;
    echo " Awarded 'verified-new-member' badge to {$affected} new users\n";
} else {
    echo " Error awarding new member badges: " . $conn->error . "\n";
}

echo "\n Badge system migration completed!\n";
echo "\nAvailable badge types:\n";
echo "- verified-blue: Basic profile verification\n";
echo "- verified-email: Email verified\n";
echo "- verified-id: Government ID verified\n";
echo "- verified-skills: Skills certified\n";
echo "- verified-company: Business verified\n";
echo "- verified-gold: Gold membership\n";
echo "- verified-platinum: Platinum membership\n";
echo "- verified-pro: Professional status\n";
echo "- verified-elite: Elite status\n";
echo "- verified-top-rated: Top performer\n";
echo "- verified-new-member: New member\n";
