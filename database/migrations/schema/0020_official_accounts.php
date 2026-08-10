<?php
/**
 * Mark accounts that speak for Jobmington itself, so the community can show a
 * verified tick beside them. A flag rather than a hardcoded id, so staff or
 * partner accounts can be marked later without touching templates.
 */
return function (PDO $pdo): void {
    $has = $pdo->query("SHOW COLUMNS FROM users LIKE 'is_official'")->fetchAll();
    if (!$has) {
        $pdo->exec("ALTER TABLE users ADD COLUMN is_official TINYINT(1) NOT NULL DEFAULT 0 AFTER user_type");
    }
    // The brand account: employer-type, named Jobmington.
    $pdo->exec("UPDATE users SET is_official = 1 WHERE full_name = 'Jobmington' AND user_type = 'employer'");
};
