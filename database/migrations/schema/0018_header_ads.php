<?php
/**
 * Sponsor banners shown in a strip above the site header.
 *
 * Image + click-through only: nothing stored here is ever rendered as markup,
 * so a banner can never inject code into every page of the site.
 */
return function (PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS header_ads (
        ad_id       INT AUTO_INCREMENT PRIMARY KEY,
        name        VARCHAR(150) NOT NULL,
        image_path  VARCHAR(500) NOT NULL,
        image_alt   VARCHAR(200) DEFAULT NULL,
        click_url   VARCHAR(500) DEFAULT NULL,
        bg_color    VARCHAR(9) DEFAULT NULL,
        starts_at   DATETIME DEFAULT NULL,
        ends_at     DATETIME DEFAULT NULL,
        priority    INT NOT NULL DEFAULT 0,
        is_active   TINYINT(1) NOT NULL DEFAULT 1,
        impressions INT NOT NULL DEFAULT 0,
        clicks      INT NOT NULL DEFAULT 0,
        created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        KEY idx_live (is_active, starts_at, ends_at),
        KEY idx_priority (priority)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
};
