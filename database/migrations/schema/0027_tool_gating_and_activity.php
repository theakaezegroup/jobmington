<?php
/**
 * Admin-controlled access to every tool, and an activity log that exists.
 *
 * tool_flags is the global switch per tool, tool_grants is the per-person
 * exception used while a tool sits in beta.
 *
 * activity_logs is not a redesign: Security::logActivity has always inserted
 * into a table by that name and the table was never created, so all eleven
 * call sites have been throwing since the day they were written. The columns
 * match what that method already sends, plus route and method, which are the
 * two things missing when you try to reconstruct what someone did.
 */
return function (PDO $pdo): void {

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS tool_flags (
            tool_key   VARCHAR(50) NOT NULL PRIMARY KEY,
            status     ENUM('off','beta','on') NOT NULL DEFAULT 'on',
            note       VARCHAR(255) DEFAULT NULL,
            updated_by INT DEFAULT NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS tool_grants (
            grant_id   INT AUTO_INCREMENT PRIMARY KEY,
            tool_key   VARCHAR(50) NOT NULL,
            user_id    INT NOT NULL,
            granted_by INT DEFAULT NULL,
            note       VARCHAR(255) DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_tool_user (tool_key, user_id),
            KEY idx_grant_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS activity_logs (
            log_id     BIGINT AUTO_INCREMENT PRIMARY KEY,
            user_id    INT DEFAULT NULL,
            action     VARCHAR(60) NOT NULL,
            details    VARCHAR(500) DEFAULT NULL,
            route      VARCHAR(255) DEFAULT NULL,
            method     VARCHAR(10) DEFAULT NULL,
            ip_address VARCHAR(45) DEFAULT NULL,
            user_agent VARCHAR(255) DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_activity_user (user_id, created_at),
            KEY idx_activity_action (action, created_at),
            KEY idx_activity_time (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // Seed every known tool as 'on', which is exactly how the site behaves
    // today. Deploying this must not lock anyone out mid-session; turning a
    // tool down to beta or off is then one click in the admin.
    require_once __DIR__ . '/../../../includes/tools.php';

    $insert = $pdo->prepare("INSERT IGNORE INTO tool_flags (tool_key, status) VALUES (?, 'on')");
    foreach (array_keys(jm_tools()) as $key) {
        $insert->execute([$key]);
    }
};
