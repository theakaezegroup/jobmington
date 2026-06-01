<?php
/**
 * Password reset token columns on `users`.
 * Previously created at runtime by jm_ensure_password_reset_columns().
 */
return function (PDO $pdo): void {
    jm_mig_add_column($pdo, 'users', 'reset_token',   'VARCHAR(64) NULL');
    jm_mig_add_column($pdo, 'users', 'reset_expires', 'DATETIME NULL');
};
