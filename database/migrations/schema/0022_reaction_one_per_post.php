<?php
/**
 * One reaction per person per post.
 *
 * The original key was (target, user, kind), which let one person pick all
 * three on the same post and count three times from a single opinion. The
 * kinds also conflict: if advice worked for you it was self-evidently helpful,
 * and "same here" is about having the problem rather than solving it.
 *
 * Withdrawing now nulls the kind instead of deleting the row, so seeds_paid
 * survives. Deleting it meant a reactor could toggle "worked" off and on and
 * pay the author again on every cycle.
 */
return function (PDO $pdo): void {
    // Collapse any duplicates before the narrower key is applied, keeping the
    // most recent choice per person per post.
    $pdo->exec("DELETE r1 FROM forum_reactions r1
                JOIN forum_reactions r2
                  ON r1.target_type = r2.target_type
                 AND r1.target_id   = r2.target_id
                 AND r1.user_id     = r2.user_id
                 AND r1.reaction_id < r2.reaction_id");

    $idx = $pdo->query("SHOW INDEX FROM forum_reactions WHERE Key_name = 'uniq_reaction'")->fetchAll();
    if ($idx) {
        $pdo->exec("ALTER TABLE forum_reactions DROP INDEX uniq_reaction");
    }

    $pdo->exec("ALTER TABLE forum_reactions
                MODIFY COLUMN kind ENUM('helpful','worked','same') NULL");

    $has = $pdo->query("SHOW COLUMNS FROM forum_reactions LIKE 'seeds_paid'")->fetchAll();
    if (!$has) {
        $pdo->exec("ALTER TABLE forum_reactions ADD COLUMN seeds_paid TINYINT(1) NOT NULL DEFAULT 0");
    }

    $one = $pdo->query("SHOW INDEX FROM forum_reactions WHERE Key_name = 'uniq_one_per_post'")->fetchAll();
    if (!$one) {
        $pdo->exec("ALTER TABLE forum_reactions
                    ADD UNIQUE KEY uniq_one_per_post (target_type, target_id, user_id)");
    }
};
