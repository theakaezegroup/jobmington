<?php
/**
 * Persistent login ("remember me") tokens.
 *
 * Split selector/validator design: the selector is a public lookup key, the
 * validator is a secret stored only as a SHA-256 hash. A stolen database
 * therefore cannot be replayed as a login, and lookup does not need to compare
 * secrets across rows.
 */
return function (PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS auth_tokens (
        token_id       INT AUTO_INCREMENT PRIMARY KEY,
        user_id        INT NOT NULL,
        selector       CHAR(32) NOT NULL,
        validator_hash CHAR(64) NOT NULL,
        expires_at     DATETIME NOT NULL,
        created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        last_used_at   DATETIME DEFAULT NULL,
        user_agent     VARCHAR(255) DEFAULT NULL,
        UNIQUE KEY uniq_selector (selector),
        KEY idx_user (user_id),
        KEY idx_expires (expires_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
};
