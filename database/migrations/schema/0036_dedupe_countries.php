<?php
/**
 * One row per country.
 *
 * Sixteen countries were in the table twice, every African one, each pair
 * identical apart from the id: same name, same ISO code, same currency, same
 * region. The country filter listed each of them twice, and jobs, companies
 * and users were split across whichever id happened to be resolved first, so
 * filtering by one Nigeria silently hid everything attached to the other.
 *
 * The lowest id wins, because on every pair it is the seeded row. Anything
 * pointing at the higher id is repointed before it is deleted, so nothing is
 * orphaned.
 *
 * A unique key on the name then stops it happening again. The scraper creates
 * a country when it meets one it does not have, and without a constraint that
 * path can always race itself back into this state.
 */

return function (PDO $pdo) {
    $duplicates = $pdo->query("
        SELECT name, COUNT(*) AS n, MIN(country_id) AS keep_id
        FROM countries
        GROUP BY name
        HAVING n > 1
    ")->fetchAll(PDO::FETCH_ASSOC);

    if (!$duplicates) {
        echo "  no duplicate countries\n";
    }

    $merged = 0;
    $moved = 0;

    foreach ($duplicates as $row) {
        $keep = (int) $row['keep_id'];

        $stmt = $pdo->prepare('SELECT country_id FROM countries WHERE name = ? AND country_id <> ?');
        $stmt->execute([$row['name'], $keep]);
        $drop = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

        if (!$drop) {
            continue;
        }
        $in = implode(',', $drop);

        foreach (['jobs', 'companies', 'users'] as $table) {
            $exists = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS
                                     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
            $exists->execute([$table, 'country_id']);
            if (!(int) $exists->fetchColumn()) {
                continue;
            }

            $update = $pdo->prepare("UPDATE `{$table}` SET country_id = ? WHERE country_id IN ({$in})");
            $update->execute([$keep]);
            $moved += $update->rowCount();
        }

        $pdo->exec("DELETE FROM countries WHERE country_id IN ({$in})");
        $merged += count($drop);
        echo "  {$row['name']}: kept #{$keep}, merged " . count($drop) . "\n";
    }

    if ($merged) {
        echo "  {$merged} duplicate row(s) removed, {$moved} reference(s) repointed\n";
    }

    // Only add the guard once the table can satisfy it.
    $hasKey = $pdo->query("SHOW INDEX FROM countries WHERE Key_name = 'uniq_country_name'")->fetch();
    if (!$hasKey) {
        $stillDuplicated = $pdo->query('SELECT COUNT(*) FROM (SELECT name FROM countries GROUP BY name HAVING COUNT(*) > 1) d')->fetchColumn();
        if ((int) $stillDuplicated === 0) {
            $pdo->exec('ALTER TABLE countries ADD UNIQUE KEY uniq_country_name (name)');
            echo "  unique key added on countries.name\n";
        } else {
            echo "  names still duplicated, unique key not added\n";
        }
    } else {
        echo "  unique key already present\n";
    }
};
