<?php
/**
 * Blog + Community Forum tables. The front-ends (blog/*, community/*) and admin
 * (admin/blog, admin/forum) already exist; this creates the backing tables in
 * production (they were only on the dev DB) and seeds default categories.
 */
return function (PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS blog_categories (
            id INT(11) NOT NULL AUTO_INCREMENT,
            name VARCHAR(100) NOT NULL,
            slug VARCHAR(100) DEFAULT NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS blog_posts (
            post_id INT(11) NOT NULL AUTO_INCREMENT,
            category_id INT(11) NOT NULL,
            author_id INT(11) NOT NULL,
            title VARCHAR(255) NOT NULL,
            slug VARCHAR(255) NOT NULL,
            excerpt TEXT DEFAULT NULL,
            content LONGTEXT NOT NULL,
            featured_image VARCHAR(255) DEFAULT NULL,
            views INT(11) DEFAULT 0,
            is_published TINYINT(1) DEFAULT 1,
            published_at DATETIME DEFAULT NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (post_id),
            KEY category_id (category_id),
            KEY author_id (author_id),
            KEY slug (slug)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS forum_categories (
            id INT(11) NOT NULL AUTO_INCREMENT,
            name VARCHAR(100) NOT NULL,
            description TEXT DEFAULT NULL,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS forum_topics (
            topic_id INT(11) NOT NULL AUTO_INCREMENT,
            category_id INT(11) DEFAULT NULL,
            user_id INT(11) DEFAULT NULL,
            title VARCHAR(255) DEFAULT NULL,
            content TEXT DEFAULT NULL,
            views INT(11) DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (topic_id),
            KEY category_id (category_id),
            KEY user_id (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS forum_replies (
            reply_id INT(11) NOT NULL AUTO_INCREMENT,
            topic_id INT(11) DEFAULT NULL,
            user_id INT(11) DEFAULT NULL,
            content TEXT DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (reply_id),
            KEY topic_id (topic_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS forum_likes (
            id INT(11) NOT NULL AUTO_INCREMENT,
            topic_id INT(11) DEFAULT NULL,
            reply_id INT(11) DEFAULT NULL,
            user_id INT(11) NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_topic_like (topic_id, user_id),
            UNIQUE KEY unique_reply_like (reply_id, user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    if ((int) $pdo->query("SELECT COUNT(*) FROM blog_categories")->fetchColumn() === 0) {
        $pdo->exec("
            INSERT INTO blog_categories (name, slug) VALUES
            ('Career Advice', 'career-advice'),
            ('Remote Work', 'remote-work'),
            ('Tech & Skills', 'tech-skills'),
            ('Job Search', 'job-search'),
            ('Company News', 'company-news')
        ");
    }

    if ((int) $pdo->query("SELECT COUNT(*) FROM forum_categories")->fetchColumn() === 0) {
        $pdo->exec("
            INSERT INTO forum_categories (name, description) VALUES
            ('General', 'Introductions and general discussion'),
            ('Job Search', 'Tips, leads, and questions about finding work'),
            ('Remote Work', 'Working remotely across Africa and beyond'),
            ('Skills & Learning', 'Courses, certifications, and upskilling'),
            ('Career Growth', 'Promotions, salary, and switching paths'),
            ('Feedback', 'Ideas and feedback for Jobmington')
        ");
    }
};
