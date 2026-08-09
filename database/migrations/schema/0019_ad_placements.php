<?php
/**
 * Ads can now sit in more than one place, so each row records where it belongs.
 * Existing rows are header banners, which is the default.
 */
return function (PDO $pdo): void {
    $cols = $pdo->query("SHOW COLUMNS FROM header_ads LIKE 'placement'")->fetchAll();
    if (!$cols) {
        $pdo->exec("ALTER TABLE header_ads
            ADD COLUMN placement VARCHAR(20) NOT NULL DEFAULT 'header' AFTER name,
            ADD KEY idx_placement (placement)");
    }
};
