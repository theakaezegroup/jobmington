<?php
/**
 * Track which reminders each registration has had.
 *
 * Columns rather than a separate table: there are at most two reminders per
 * registration, and a NULL timestamp is the whole state. It also lets the cron
 * claim a row atomically, which is what stops a slow run or an overlapping one
 * from mailing the same person twice.
 */
return function (PDO $pdo): void {
    foreach (['reminder_24h_at', 'reminder_1h_at'] as $col) {
        $has = $pdo->query("SHOW COLUMNS FROM event_registrations LIKE '{$col}'")->fetchAll();
        if (!$has) {
            $pdo->exec("ALTER TABLE event_registrations ADD COLUMN {$col} DATETIME DEFAULT NULL");
        }
    }
    $idx = $pdo->query("SHOW INDEX FROM event_registrations WHERE Key_name = 'idx_reminders'")->fetchAll();
    if (!$idx) {
        $pdo->exec("ALTER TABLE event_registrations ADD KEY idx_reminders (event_id, reminder_24h_at, reminder_1h_at)");
    }
};
