<?php
/**
 * More than one unfinished event registration per person.
 *
 * users.pending_event_id held exactly one, so a visitor who clicked Register
 * on two events before creating an account had the first one silently
 * overwritten by the second. They were never told, and the first event simply
 * never happened for them.
 *
 * A row per intent instead. The primary key on (user_id, event_id) makes
 * parking the same intent twice a no-op, which is what a person clicking
 * Register again before signing up should cost.
 *
 * The old column is copied across and dropped rather than left in place: two
 * places holding the same answer is how they end up disagreeing.
 */

return function (PDO $pdo) {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS pending_event_registrations (
            user_id    INT NOT NULL,
            event_id   INT NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (user_id, event_id),
            KEY idx_pending_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "  pending_event_registrations ready\n";

    $hasColumn = $pdo->query("SHOW COLUMNS FROM users LIKE 'pending_event_id'")->fetch();

    if ($hasColumn) {
        // Anyone mid-journey right now keeps their place.
        $moved = $pdo->exec("
            INSERT IGNORE INTO pending_event_registrations (user_id, event_id)
            SELECT user_id, pending_event_id
            FROM users
            WHERE pending_event_id IS NOT NULL AND pending_event_id > 0
        ");
        echo "  carried over " . (int) $moved . " unfinished registration(s)\n";

        $pdo->exec("ALTER TABLE users DROP COLUMN pending_event_id");
        echo "  users.pending_event_id dropped\n";
    } else {
        echo "  users.pending_event_id already gone\n";
    }
};
