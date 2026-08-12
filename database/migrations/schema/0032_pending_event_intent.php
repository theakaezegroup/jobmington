<?php
/**
 * The event someone was registering for when they were asked to make an account.
 *
 * It was held in the session only. That works while the whole journey happens
 * in one browser, and a verification link very often breaks that: the account
 * is made on a laptop and the email is opened on a phone. On the phone there is
 * no session, so the intent was gone, the person was verified and dropped on a
 * dashboard, and the event they came for was never registered and never
 * mentioned. On the account instead, so it survives the trip through the inbox.
 *
 * Nullable and cleared once consumed: a row with a value in it means "this
 * person still has an unfinished registration", which is exactly the question
 * verification needs to ask.
 */

return function (PDO $pdo) {
    $exists = $pdo->query("SHOW COLUMNS FROM users LIKE 'pending_event_id'")->fetch();

    if (!$exists) {
        $pdo->exec("ALTER TABLE users ADD COLUMN pending_event_id INT NULL DEFAULT NULL AFTER activation_token");
        echo "  users.pending_event_id added\n";
    } else {
        echo "  users.pending_event_id already present\n";
    }
};
