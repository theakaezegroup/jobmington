<?php
/**
 * The table behind seeker/settings.php, which is missing on production.
 *
 * That page reads user_settings on load, unguarded, and creates a row when
 * there is not one. The table does not exist on the server, so every signed-in
 * job seeker who opens Settings gets a 500 rather than their notification and
 * privacy preferences. It exists on older copies of this database, so the code
 * was written against something real and the table was simply never carried
 * across.
 *
 * Shape taken from the copy the page was written against, so the four
 * statements in seeker/settings.php work unchanged.
 *
 * Found by the account-deletion audit: user_settings holds personal data and
 * has to go when an account is deleted, and checking that list against the
 * live schema is what surfaced that the table was not there at all.
 */

return function (PDO $pdo) {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS user_settings (
            id                 INT AUTO_INCREMENT PRIMARY KEY,
            user_id            INT NOT NULL,
            email_job_alerts   TINYINT(1) DEFAULT 1,
            email_applications TINYINT(1) DEFAULT 1,
            email_messages     TINYINT(1) DEFAULT 1,
            email_newsletter   TINYINT(1) DEFAULT 1,
            profile_visibility ENUM('public','employers','private') DEFAULT 'public',
            show_email         TINYINT(1) DEFAULT 0,
            show_phone         TINYINT(1) DEFAULT 0,
            created_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_user_settings_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    echo "  user_settings ready\n";
};
