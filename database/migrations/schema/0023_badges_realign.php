<?php
/**
 * Realign the badge catalogue with what can actually be earned.
 *
 * verify-email.php awarded the type 'verified', which was never in the
 * catalogue, so awardBadge() rejected it and renderBadge() returned an empty
 * string for the rows that predate that check. Five users held a badge that
 * could neither be granted nor displayed.
 */
return function (PDO $pdo): void {
    $has = $pdo->query("SHOW TABLES LIKE 'user_badges'")->fetchAll();
    if (!$has) { return; }

    // Anyone holding the phantom type earned it by verifying their email.
    $pdo->prepare("UPDATE user_badges SET badge_type = 'verified-email' WHERE badge_type = 'verified'")->execute();

    // A person cannot hold the same badge twice; older rows may not have said so.
    $idx = $pdo->query("SHOW INDEX FROM user_badges WHERE Key_name = 'uniq_user_badge'")->fetchAll();
    if (!$idx) {
        $pdo->exec("DELETE b1 FROM user_badges b1
                    JOIN user_badges b2
                      ON b1.user_id = b2.user_id AND b1.badge_type = b2.badge_type AND b1.id < b2.id");
        $pdo->exec("ALTER TABLE user_badges ADD UNIQUE KEY uniq_user_badge (user_id, badge_type)");
    }
};
