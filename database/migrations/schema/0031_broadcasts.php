<?php
/**
 * Announcements stored once instead of copied to everybody.
 *
 * jm_notify_all wrote one notifications row per member. At ten members that is
 * ten rows; at fifty thousand it is fifty thousand rows for a single sentence,
 * every time, and the bell counts unread on every poll. The table grows with
 * members times announcements, which is the shape of a problem you only notice
 * once it is expensive to undo.
 *
 * A broadcast is one row. Who has read it is one row per person who has
 * actually read it, which is a far smaller set than everybody, and only exists
 * once somebody engages.
 *
 * Personal notifications stay exactly as they are: they genuinely are per
 * person, and there is nothing to save by moving them.
 */
return function (PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS broadcasts (
            broadcast_id INT AUTO_INCREMENT PRIMARY KEY,
            type       VARCHAR(40) NOT NULL,
            title      VARCHAR(255) NOT NULL,
            message    VARCHAR(500) DEFAULT NULL,
            link       VARCHAR(255) DEFAULT NULL,
            audience   VARCHAR(20) DEFAULT NULL,   -- null means everybody, else a user_type
            created_by INT DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_broadcast_time (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // Only written when somebody reads one, so it stays proportional to
    // engagement rather than to the size of the membership.
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS broadcast_reads (
            broadcast_id INT NOT NULL,
            user_id      INT NOT NULL,
            read_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (broadcast_id, user_id),
            KEY idx_bread_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
};
