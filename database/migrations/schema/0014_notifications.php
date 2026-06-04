<?php
/**
 * In-app notifications. sendNotification() writes here; the header bell + the
 * seeker notifications page read from it. The table was missing in production,
 * so notifications were being silently dropped.
 */
return function (PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS notifications (
            notification_id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            type VARCHAR(50) DEFAULT 'general',
            title VARCHAR(255) NOT NULL,
            message TEXT DEFAULT NULL,
            link VARCHAR(500) DEFAULT NULL,
            is_read TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_user_read (user_id, is_read),
            KEY idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // Make sure saved_jobs.saved_at has a default so the bookmark INSERT (which
    // only sets user_id, job_id) never fails on a NOT NULL column.
    if (jm_mig_has_table($pdo, 'saved_jobs') && jm_mig_has_column($pdo, 'saved_jobs', 'saved_at')) {
        try { $pdo->exec("ALTER TABLE saved_jobs MODIFY saved_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP"); } catch (Throwable $e) {}
    }
};
