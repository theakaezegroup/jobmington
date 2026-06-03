<?php
/**
 * Course completion certificates. Issued by certificates/generate.php on course
 * completion; verified publicly via /verify?code= using cert_code.
 *
 * Self-heals legacy tables that used verification_code instead of cert_code.
 */
return function (PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS certificates (
            certificate_id INT PRIMARY KEY AUTO_INCREMENT,
            cert_code VARCHAR(40) NULL UNIQUE,
            user_id INT NOT NULL,
            course_id INT NOT NULL,
            issued_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user (user_id),
            INDEX idx_course (course_id),
            INDEX idx_cert_code (cert_code)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // Self-heal: ensure cert_code exists, and don't let a legacy NOT NULL
    // verification_code block new inserts that only set cert_code.
    jm_mig_add_column($pdo, 'certificates', 'cert_code', 'VARCHAR(40) NULL');

    if (jm_mig_has_column($pdo, 'certificates', 'verification_code')) {
        // Backfill cert_code from the old column, then relax its NOT NULL.
        $pdo->exec("UPDATE certificates SET cert_code = verification_code
                    WHERE (cert_code IS NULL OR cert_code = '') AND verification_code IS NOT NULL");
        try { $pdo->exec("ALTER TABLE certificates MODIFY verification_code VARCHAR(64) NULL"); } catch (Throwable $e) {}
    }
};
