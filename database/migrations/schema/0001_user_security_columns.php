<?php
/**
 * Login lockout / activity columns on `users`.
 * Previously created at runtime by jm_login_ensure_lockout_columns()
 * and jm_admin_users_ensure_columns().
 */
return function (PDO $pdo): void {
    jm_mig_add_column($pdo, 'users', 'failed_login_attempts', 'INT NOT NULL DEFAULT 0');
    jm_mig_add_column($pdo, 'users', 'locked_until',          'DATETIME DEFAULT NULL');
    jm_mig_add_column($pdo, 'users', 'last_login',            'DATETIME DEFAULT NULL');
};
