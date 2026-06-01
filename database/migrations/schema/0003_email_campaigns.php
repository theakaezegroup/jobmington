<?php
/**
 * Email Outreach campaigns table.
 * Previously created at runtime inside admin/email-campaigns.php.
 * Includes columns added later: poster_url, custom_emails, started_at.
 */
return function (PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS email_campaigns (
            id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            subject         VARCHAR(255)  NOT NULL,
            preview_text    VARCHAR(255)  NOT NULL DEFAULT '',
            body_html       TEXT          NOT NULL,
            poster_url      VARCHAR(500)  DEFAULT NULL,
            custom_emails   TEXT          DEFAULT NULL,
            segment         VARCHAR(50)   NOT NULL DEFAULT 'all',
            recipient_count INT UNSIGNED  NOT NULL DEFAULT 0,
            sent_count      INT UNSIGNED  NOT NULL DEFAULT 0,
            failed_count    INT UNSIGNED  NOT NULL DEFAULT 0,
            skipped_count   INT UNSIGNED  NOT NULL DEFAULT 0,
            status          ENUM('draft','sending','sent','failed') NOT NULL DEFAULT 'draft',
            started_at      DATETIME      DEFAULT NULL,
            sent_at         DATETIME      DEFAULT NULL,
            created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
            created_by      INT UNSIGNED  DEFAULT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // Bring older tables up to the current shape.
    jm_mig_add_column($pdo, 'email_campaigns', 'poster_url',    'VARCHAR(500) DEFAULT NULL AFTER body_html');
    jm_mig_add_column($pdo, 'email_campaigns', 'custom_emails', 'TEXT DEFAULT NULL AFTER poster_url');
    jm_mig_add_column($pdo, 'email_campaigns', 'skipped_count', 'INT UNSIGNED NOT NULL DEFAULT 0 AFTER failed_count');
    jm_mig_add_column($pdo, 'email_campaigns', 'started_at',    'DATETIME DEFAULT NULL AFTER status');
};
