<?php
/**
 * Who is here right now.
 *
 * last_login only says when someone last signed in, which for anybody using
 * "remember me" can be weeks ago while they are reading a page this second.
 * Presence needs a heartbeat, so this is stamped on each request rather than
 * at sign-in.
 *
 * Two columns on users rather than a sessions table: there is exactly one
 * current answer per person, it is overwritten constantly, and a table would
 * mean rows to prune forever for a question that only ever looks at the last
 * few minutes.
 */
return function (PDO $pdo): void {
    $has = static function (string $column) use ($pdo): bool {
        return (bool) $pdo->query("SHOW COLUMNS FROM users LIKE " . $pdo->quote($column))->fetchAll();
    };

    if (!$has('last_seen_at')) {
        $pdo->exec("ALTER TABLE users ADD COLUMN last_seen_at DATETIME DEFAULT NULL");
    }
    // Where they were when we last heard from them, so the admin sees what
    // people are doing and not just a count.
    if (!$has('last_seen_route')) {
        $pdo->exec("ALTER TABLE users ADD COLUMN last_seen_route VARCHAR(190) DEFAULT NULL");
    }

    $hasIndex = $pdo->query("SHOW INDEX FROM users WHERE Key_name = 'idx_users_last_seen'")->fetchAll();
    if (!$hasIndex) {
        $pdo->exec("ALTER TABLE users ADD KEY idx_users_last_seen (last_seen_at)");
    }
};
