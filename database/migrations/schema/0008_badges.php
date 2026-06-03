<?php
/**
 * Badges system.
 *  - badges:      admin-managed catalog (name, description, icon).
 *  - user_badges: badges held by users. Supports BOTH models the code uses:
 *      * badge_type (string) — verification badges via includes/badges.php
 *      * badge_id (catalog FK) — achievement gallery via seeker/badges.php
 *
 * Self-heals legacy tables (the older standalone migration referenced users(id)
 * via mysqli and only had one of the two columns).
 */
return function (PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS badges (
            badge_id INT PRIMARY KEY AUTO_INCREMENT,
            name VARCHAR(100) NOT NULL,
            description TEXT NULL,
            icon VARCHAR(100) NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    jm_mig_add_column($pdo, 'badges', 'is_active', 'TINYINT(1) NOT NULL DEFAULT 1');
    jm_mig_add_column($pdo, 'badges', 'created_at', 'DATETIME DEFAULT CURRENT_TIMESTAMP');

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS user_badges (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            badge_type VARCHAR(50) NULL,
            badge_id INT NULL,
            earned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            awarded_by INT DEFAULT NULL,
            notes TEXT NULL,
            INDEX idx_user_id (user_id),
            INDEX idx_badge_type (badge_type),
            INDEX idx_badge_id (badge_id),
            INDEX idx_earned_at (earned_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    // Make sure both column families exist (legacy tables only had one).
    jm_mig_add_column($pdo, 'user_badges', 'badge_type', 'VARCHAR(50) NULL');
    jm_mig_add_column($pdo, 'user_badges', 'badge_id', 'INT NULL');
    jm_mig_add_column($pdo, 'user_badges', 'is_active', 'TINYINT(1) NOT NULL DEFAULT 1');
    jm_mig_add_column($pdo, 'user_badges', 'awarded_by', 'INT DEFAULT NULL');
    jm_mig_add_column($pdo, 'user_badges', 'notes', 'TEXT NULL');
    // Legacy tables made badge_type/badge_id NOT NULL — relax so either model works.
    try { $pdo->exec("ALTER TABLE user_badges MODIFY badge_id INT NULL"); } catch (Throwable $e) {}
    try { $pdo->exec("ALTER TABLE user_badges MODIFY badge_type VARCHAR(50) NULL"); } catch (Throwable $e) {}

    // Seed a small default catalog (only when empty).
    if ((int) $pdo->query("SELECT COUNT(*) FROM badges")->fetchColumn() === 0) {
        $pdo->exec("
            INSERT INTO badges (name, description, icon) VALUES
            ('Verified', 'Email-verified Jobmington member', 'fa-circle-check'),
            ('Certified', 'Earned at least one course certificate', 'fa-certificate'),
            ('Top Applicant', 'Highly active job seeker', 'fa-bolt'),
            ('Mentor', 'Recognised community contributor', 'fa-hands-helping')
        ");
    }

    // Backfill the verification badge for already-verified users (idempotent).
    $pdo->exec("
        INSERT INTO user_badges (user_id, badge_type, earned_at, notes)
        SELECT u.user_id, 'verified', NOW(), 'Auto-awarded: email verified'
        FROM users u
        WHERE u.is_verified = 1
          AND NOT EXISTS (
              SELECT 1 FROM user_badges ub
              WHERE ub.user_id = u.user_id AND ub.badge_type = 'verified'
          )
    ");
};
