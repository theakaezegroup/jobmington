<?php
/**
 * Async email queue. Campaign and job-match-alert emails are enqueued here
 * and delivered by cron/process_email_queue.php so web requests never block
 * on bulk sending.
 */
return function (PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS email_queue (
            id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            campaign_id INT UNSIGNED  DEFAULT NULL,
            source      VARCHAR(30)   NOT NULL DEFAULT 'campaign',
            to_email    VARCHAR(255)  NOT NULL,
            to_name     VARCHAR(255)  NOT NULL DEFAULT '',
            subject     VARCHAR(255)  NOT NULL,
            body_html   MEDIUMTEXT    NOT NULL,
            status      ENUM('pending','sent','failed') NOT NULL DEFAULT 'pending',
            attempts    TINYINT UNSIGNED NOT NULL DEFAULT 0,
            error       VARCHAR(255)  DEFAULT NULL,
            created_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
            sent_at     DATETIME      DEFAULT NULL,
            KEY idx_status (status, attempts),
            KEY idx_campaign (campaign_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
};
