<?php
/**
 * The certificate code is split across the app: generate.php/view.php/download.php
 * use cert_code, while api/certificates.php, admin/certificates.php and
 * wallet/passport/verify.php use verification_code. Keep both working by exposing
 * verification_code as a stored column that mirrors cert_code.
 *
 * On databases that already have a real verification_code column (legacy/dev),
 * this is a no-op.
 */
return function (PDO $pdo): void {
    if (!jm_mig_has_table($pdo, 'certificates')) {
        return;
    }
    if (jm_mig_has_column($pdo, 'certificates', 'verification_code')) {
        return; // already present (legacy/dev) — leave as-is
    }

    try {
        // Preferred: a generated column that always equals cert_code.
        $pdo->exec("ALTER TABLE certificates
                    ADD COLUMN verification_code VARCHAR(40)
                    GENERATED ALWAYS AS (cert_code) STORED");
    } catch (Throwable $e) {
        // Fallback for engines without generated columns: plain column + backfill.
        jm_mig_add_column($pdo, 'certificates', 'verification_code', 'VARCHAR(40) NULL');
        try {
            $pdo->exec("UPDATE certificates SET verification_code = cert_code
                        WHERE verification_code IS NULL OR verification_code = ''");
        } catch (Throwable $e2) {
            error_log('certificates verification_code backfill failed: ' . $e2->getMessage());
        }
    }
};
