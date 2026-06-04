<?php
/**
 * Monetization wiring for content: ebook pricing + purchases,
 * course credit price, premium certificate flag.
 */
return function (PDO $pdo): void {
    // Ebooks: seed/credit pricing
    jm_mig_add_column($pdo, 'ebooks', 'seed_price',   "INT NOT NULL DEFAULT 0");
    jm_mig_add_column($pdo, 'ebooks', 'credit_price', "INT NOT NULL DEFAULT 0");

    // Ebook purchases (unlock record)
    $pdo->exec("CREATE TABLE IF NOT EXISTS ebook_purchases (
        purchase_id INT AUTO_INCREMENT PRIMARY KEY,
        user_id  INT NOT NULL,
        ebook_id INT NOT NULL,
        method   VARCHAR(20) NOT NULL DEFAULT 'seeds',
        amount   INT NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_user_ebook (user_id, ebook_id),
        KEY idx_user (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Courses: credit price alongside existing seed_price
    jm_mig_add_column($pdo, 'courses', 'credit_price', "INT NOT NULL DEFAULT 0");

    // Certificates: premium upgrade flag
    jm_mig_add_column($pdo, 'certificates', 'is_premium', "TINYINT(1) NOT NULL DEFAULT 0");
};
