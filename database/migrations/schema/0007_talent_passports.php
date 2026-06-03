<?php
/**
 * Talent Passport system — verifiable, shareable talent credential.
 * Creates the passport record + verifications, endorsements, employer access,
 * contact logs, view analytics, and configurable pricing.
 */
return function (PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS talent_passports (
            passport_id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT NOT NULL UNIQUE,
            passport_number VARCHAR(20) NOT NULL UNIQUE,
            level ENUM('rising','verified','expert','elite') DEFAULT 'rising',
            level_points INT DEFAULT 0,
            times_featured INT DEFAULT 1,
            first_featured_at DATETIME NOT NULL,
            last_featured_at DATETIME NOT NULL,
            skills_verified INT DEFAULT 0,
            endorsements_count INT DEFAULT 0,
            employer_reviews INT DEFAULT 0,
            successful_hires INT DEFAULT 0,
            is_public BOOLEAN DEFAULT TRUE,
            is_claimed BOOLEAN DEFAULT FALSE,
            claimed_at DATETIME NULL,
            passport_image VARCHAR(255) NULL,
            qr_code VARCHAR(255) NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_level (level),
            INDEX idx_featured (times_featured DESC),
            INDEX idx_public (is_public, level)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS passport_verifications (
            verification_id INT PRIMARY KEY AUTO_INCREMENT,
            passport_id INT NOT NULL,
            verification_type ENUM('skill','certificate','employment','education') NOT NULL,
            reference_id INT NULL,
            reference_name VARCHAR(100) NOT NULL,
            verified_by ENUM('ai','employer','institution','peer') NOT NULL,
            verifier_id INT NULL,
            verifier_name VARCHAR(100) NULL,
            proof_type VARCHAR(50) NULL,
            proof_url VARCHAR(500) NULL,
            status ENUM('pending','verified','rejected','expired') DEFAULT 'pending',
            verified_at DATETIME NULL,
            expires_at DATETIME NULL,
            seeds_spent INT DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_type_status (verification_type, status),
            INDEX idx_passport (passport_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS passport_endorsements (
            endorsement_id INT PRIMARY KEY AUTO_INCREMENT,
            passport_id INT NOT NULL,
            endorser_type ENUM('employer','colleague','mentor','client') NOT NULL,
            endorser_user_id INT NULL,
            endorser_company_id INT NULL,
            endorser_name VARCHAR(100) NOT NULL,
            endorser_title VARCHAR(100) NULL,
            endorser_email VARCHAR(255) NULL,
            relationship VARCHAR(100) NOT NULL,
            worked_together_at VARCHAR(100) NULL,
            worked_from DATE NULL,
            worked_to DATE NULL,
            endorsement_text TEXT NOT NULL,
            skills_endorsed JSON NULL,
            rating INT NULL,
            is_verified BOOLEAN DEFAULT FALSE,
            verification_token VARCHAR(64) NULL,
            verified_at DATETIME NULL,
            is_public BOOLEAN DEFAULT TRUE,
            is_featured BOOLEAN DEFAULT FALSE,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_verified (is_verified, is_public),
            INDEX idx_passport (passport_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS employer_talent_access (
            access_id INT PRIMARY KEY AUTO_INCREMENT,
            company_id INT NOT NULL,
            user_id INT NOT NULL,
            access_tier ENUM('basic','premium','vip') DEFAULT 'basic',
            subscription_type ENUM('monthly','yearly','paypercontact') NOT NULL,
            price_paid DECIMAL(10,2) NOT NULL,
            currency VARCHAR(3) DEFAULT 'USD',
            contacts_allowed INT DEFAULT 0,
            contacts_used INT DEFAULT 0,
            exports_allowed INT DEFAULT 0,
            exports_used INT DEFAULT 0,
            can_access_rising BOOLEAN DEFAULT TRUE,
            can_access_verified BOOLEAN DEFAULT FALSE,
            can_access_expert BOOLEAN DEFAULT FALSE,
            can_access_elite BOOLEAN DEFAULT FALSE,
            status ENUM('active','expired','cancelled') DEFAULT 'active',
            starts_at DATETIME NOT NULL,
            expires_at DATETIME NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_company_status (company_id, status),
            INDEX idx_expires (expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS passport_contacts (
            contact_id INT PRIMARY KEY AUTO_INCREMENT,
            passport_id INT NOT NULL,
            employer_user_id INT NOT NULL,
            company_id INT NOT NULL,
            contact_type ENUM('view','unlock','message','export') NOT NULL,
            seeds_cost INT DEFAULT 0,
            cash_cost DECIMAL(10,2) DEFAULT 0,
            talent_responded BOOLEAN DEFAULT FALSE,
            responded_at DATETIME NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_passport (passport_id),
            INDEX idx_employer (employer_user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS passport_views (
            view_id INT PRIMARY KEY AUTO_INCREMENT,
            passport_id INT NOT NULL,
            viewer_user_id INT NULL,
            viewer_company_id INT NULL,
            source ENUM('homepage','search','profile','direct','api') NOT NULL,
            ip_address VARCHAR(45) NULL,
            user_agent VARCHAR(500) NULL,
            country_code VARCHAR(2) NULL,
            viewed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_passport_date (passport_id, viewed_at),
            INDEX idx_source (source)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS passport_pricing (
            pricing_id INT PRIMARY KEY AUTO_INCREMENT,
            item_type ENUM('verification','boost','export','employer_sub','contact') NOT NULL,
            item_name VARCHAR(100) NOT NULL,
            item_description TEXT NULL,
            price_seeds INT DEFAULT 0,
            price_cash DECIMAL(10,2) DEFAULT 0,
            currency VARCHAR(3) DEFAULT 'USD',
            duration_days INT NULL,
            includes_contacts INT NULL,
            includes_exports INT NULL,
            is_active BOOLEAN DEFAULT TRUE,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // Seed default pricing once (table has no natural unique key, so guard on empty).
    if ((int) $pdo->query("SELECT COUNT(*) FROM passport_pricing")->fetchColumn() === 0) {
        $pdo->exec("
            INSERT INTO passport_pricing (item_type, item_name, item_description, price_seeds, price_cash, duration_days, includes_contacts, includes_exports) VALUES
            ('verification', 'Skill Verification', 'AI-powered verification of a skill on your passport', 100, 0, NULL, NULL, NULL),
            ('verification', 'Certificate Link', 'Link a Jobmington certificate to your passport', 50, 0, NULL, NULL, NULL),
            ('boost', 'Weekly Visibility Boost', '3x more likely to appear in employer searches', 200, 0, 7, NULL, NULL),
            ('boost', 'Monthly Visibility Boost', '3x visibility for 30 days (save 20%)', 640, 0, 30, NULL, NULL),
            ('export', 'Verified CV Export', 'Download PDF with QR verification code', 50, 0, NULL, NULL, NULL),
            ('employer_sub', 'Basic Access', 'Access Rising talent pool, 10 contacts/month', 0, 29, 30, 10, 0),
            ('employer_sub', 'Premium Access', 'Access Verified + Expert talent, 50 contacts/month', 0, 99, 30, 50, 50),
            ('employer_sub', 'VIP Access', 'Access all talent including Elite, unlimited contacts', 0, 199, 30, 999, 200),
            ('employer_sub', 'Annual Premium', 'Premium access for 1 year (save 20%)', 0, 950, 365, 600, 600),
            ('contact', 'Single Contact', 'Unlock one talent contact (pay-per-lead)', 0, 5, NULL, 1, NULL)
        ");
    }
};
