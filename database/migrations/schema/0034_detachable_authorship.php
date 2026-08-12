<?php
/**
 * Let a record outlive the person who made it.
 *
 * Deleting an account has to remove the person without gutting the site
 * around them. A forum topic that a dozen people replied to, a published blog
 * post, a live job listing and the payment history behind a month's revenue
 * all have to survive the account going away, pointing at nobody.
 *
 * They could not: every one of these columns was NOT NULL, so detaching was
 * impossible and the only options were to destroy the content or refuse the
 * deletion. Nullable now, which is the honest shape anyway. A blog post whose
 * author has left genuinely has no author.
 *
 * Types are read back from the schema rather than assumed, so an unsigned or
 * bigint column keeps what it is and only loses NOT NULL.
 */

return function (PDO $pdo) {
    $targets = [
        'blog_posts'             => 'author_id',
        'course_reviews'         => 'user_id',
        'transactions'           => 'user_id',
        'course_purchases'       => 'user_id',
        'ebook_purchases'        => 'user_id',
        'subscriptions'          => 'user_id',
        'seeker_subscriptions'   => 'user_id',
        'employer_subscriptions' => 'user_id',
        'seed_transactions'      => 'user_id',
    ];

    $database = $pdo->query('SELECT DATABASE()')->fetchColumn();

    foreach ($targets as $table => $column) {
        $stmt = $pdo->prepare("
            SELECT COLUMN_TYPE, IS_NULLABLE
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?
        ");
        $stmt->execute([$database, $table, $column]);
        $info = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$info) {
            echo "  {$table}.{$column} not present, skipped\n";
            continue;
        }
        if ($info['IS_NULLABLE'] === 'YES') {
            echo "  {$table}.{$column} already nullable\n";
            continue;
        }

        $pdo->exec("ALTER TABLE `{$table}` MODIFY `{$column}` {$info['COLUMN_TYPE']} NULL DEFAULT NULL");
        echo "  {$table}.{$column} now nullable\n";
    }
};
