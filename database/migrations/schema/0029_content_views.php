<?php
/**
 * Who looked at what, not just how many did.
 *
 * blog_posts.views and forum_topics.views are bare counters: they tell you a
 * topic was opened 300 times and nothing about by whom, which is the wrong
 * half of the question once you have real users. Those counters stay, because
 * the public pages display them and recomputing a total from this table on
 * every page view would be wasteful.
 *
 * One table for every content type rather than a views table per thing. The
 * question is identical in each case and the alternative is five near-identical
 * tables that drift apart.
 *
 * Signed-out views are kept with a null user_id: they still count towards the
 * total, they just have nobody attached.
 */
return function (PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS content_views (
            view_id      BIGINT AUTO_INCREMENT PRIMARY KEY,
            content_type VARCHAR(30) NOT NULL,
            content_id   INT NOT NULL,
            user_id      INT DEFAULT NULL,
            ip_address   VARCHAR(45) DEFAULT NULL,
            created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_view_content (content_type, content_id, created_at),
            KEY idx_view_user (user_id, created_at),
            KEY idx_view_time (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
};
