<?php
/**
 * Community reactions.
 *
 * Three kinds rather than a single like, because in a career forum the useful
 * question is not whether a post was popular but what it was worth:
 *   helpful  taught me something
 *   worked   I tried this and it worked
 *   same     this happens to me too
 *
 * Only 'worked' pays Seeds, so the economy rewards outcomes rather than
 * opinions. The unique key allows one of each kind per person per post, which
 * is the main defence against a reaction being farmed.
 */
return function (PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS forum_reactions (
        reaction_id INT AUTO_INCREMENT PRIMARY KEY,
        target_type ENUM('topic','reply') NOT NULL,
        target_id   INT NOT NULL,
        user_id     INT NOT NULL,
        kind        ENUM('helpful','worked','same') NOT NULL,
        created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_reaction (target_type, target_id, user_id, kind),
        KEY idx_target (target_type, target_id),
        KEY idx_user (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Editorial stamp, separate from crowd reactions. One per thread, enforced
    // in code so a later change of mind simply moves it.
    $has = $pdo->query("SHOW COLUMNS FROM forum_replies LIKE 'is_verified_answer'")->fetchAll();
    if (!$has) {
        $pdo->exec("ALTER TABLE forum_replies ADD COLUMN is_verified_answer TINYINT(1) NOT NULL DEFAULT 0");
    }

    // Seeds rate for the one reaction that pays. Modest on purpose: it should
    // recognise a genuinely useful answer, not become an income stream.
    $exists = $pdo->prepare("SELECT COUNT(*) FROM seed_rates WHERE action = ?");
    $exists->execute(['forum_worked_for_me']);
    if (!(int) $exists->fetchColumn()) {
        $pdo->prepare("INSERT INTO seed_rates (action, seeds_amount, type, description, is_active)
                       VALUES (?, ?, 'earn', ?, 1)")
            ->execute(['forum_worked_for_me', 5.00, 'Someone confirmed your advice worked for them']);
    }
};
