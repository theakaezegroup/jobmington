<?php
/**
 * Email suppression list. Any address here is excluded from marketing
 * campaign sends (transactional emails are exempt).
 */
return function (PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS email_unsubscribes (
            id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            email      VARCHAR(255) NOT NULL,
            reason     VARCHAR(100) NOT NULL DEFAULT 'user_unsubscribed',
            created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_email (email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
};
