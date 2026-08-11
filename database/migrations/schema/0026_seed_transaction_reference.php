<?php
/**
 * The Paystack payment reference on a seed purchase.
 *
 * Not the same thing as the existing reference_id, which is an internal int
 * foreign key. This is the string Paystack sends back, such as JM_a1b2c3.
 *
 * Unique on purpose. Paystack retries a webhook it did not get a 200 for, and
 * without this a retry credits the buyer twice. Existing rows are all NULL and
 * MySQL allows repeated NULLs in a unique index, so the 30k rows already there
 * are unaffected.
 */
return function (PDO $pdo): void {
    $has = $pdo->query("SHOW COLUMNS FROM seed_transactions LIKE 'reference'")->fetchAll();
    if (!$has) {
        $pdo->exec("ALTER TABLE seed_transactions ADD COLUMN reference VARCHAR(100) DEFAULT NULL");
    }

    $hasIndex = $pdo->query("SHOW INDEX FROM seed_transactions WHERE Key_name = 'uniq_seed_reference'")->fetchAll();
    if (!$hasIndex) {
        $pdo->exec("ALTER TABLE seed_transactions ADD UNIQUE KEY uniq_seed_reference (reference)");
    }
};
