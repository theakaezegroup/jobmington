<?php
/**
 * Courses / Learn / Quiz system. The front-end (learn/*) and admin (admin/courses)
 * already exist; this creates the backing tables in production (they were only
 * present on the dev DB). Schemas mirror the dev database exactly.
 */
return function (PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS course_categories (
            id INT(11) NOT NULL AUTO_INCREMENT,
            name VARCHAR(100) NOT NULL,
            slug VARCHAR(100) NOT NULL,
            icon VARCHAR(50) DEFAULT 'fa-book',
            description TEXT DEFAULT NULL,
            is_active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS courses (
            course_id INT(11) NOT NULL AUTO_INCREMENT,
            category_id INT(11) DEFAULT NULL,
            title VARCHAR(255) NOT NULL,
            slug VARCHAR(255) NOT NULL,
            description TEXT DEFAULT NULL,
            short_description VARCHAR(500) DEFAULT NULL,
            thumbnail VARCHAR(500) DEFAULT NULL,
            instructor_name VARCHAR(100) DEFAULT NULL,
            instructor_bio TEXT DEFAULT NULL,
            course_type ENUM('video','article','certification','full-course','guide') DEFAULT 'video',
            external_url VARCHAR(500) DEFAULT NULL,
            is_external TINYINT(1) DEFAULT 0,
            duration_hours DECIMAL(5,1) DEFAULT 0.0,
            difficulty ENUM('beginner','intermediate','advanced') DEFAULT 'beginner',
            is_free TINYINT(1) DEFAULT 1,
            price DECIMAL(10,2) DEFAULT 0.00,
            seed_price INT(11) DEFAULT 0,
            enrollment_count INT(11) DEFAULT 0,
            completion_count INT(11) DEFAULT 0,
            rating_avg DECIMAL(2,1) DEFAULT 0.0,
            rating_count INT(11) DEFAULT 0,
            has_certificate TINYINT(1) DEFAULT 0,
            certificate_provider VARCHAR(100) DEFAULT NULL,
            is_published TINYINT(1) DEFAULT 1,
            is_featured TINYINT(1) DEFAULT 0,
            tags VARCHAR(500) DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (course_id),
            UNIQUE KEY slug (slug),
            KEY idx_category (category_id),
            KEY idx_published (is_published),
            KEY idx_featured (is_featured),
            KEY idx_external (is_external)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS course_modules (
            module_id INT(11) NOT NULL AUTO_INCREMENT,
            course_id INT(11) NOT NULL,
            title VARCHAR(255) NOT NULL,
            description TEXT DEFAULT NULL,
            content TEXT DEFAULT NULL,
            video_url VARCHAR(500) DEFAULT NULL,
            duration_minutes INT(11) DEFAULT 0,
            sort_order INT(11) DEFAULT 0,
            is_free_preview TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (module_id),
            KEY idx_course (course_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS quizzes (
            quiz_id INT(11) NOT NULL AUTO_INCREMENT,
            course_id INT(11) NOT NULL,
            title VARCHAR(200) DEFAULT NULL,
            passing_score INT(11) DEFAULT 70,
            PRIMARY KEY (quiz_id),
            KEY course_id (course_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS quiz_questions (
            question_id INT(11) NOT NULL AUTO_INCREMENT,
            quiz_id INT(11) NOT NULL,
            question TEXT DEFAULT NULL,
            option_a VARCHAR(255) DEFAULT NULL,
            option_b VARCHAR(255) DEFAULT NULL,
            option_c VARCHAR(255) DEFAULT NULL,
            option_d VARCHAR(255) DEFAULT NULL,
            correct_option CHAR(1) DEFAULT NULL,
            PRIMARY KEY (question_id),
            KEY quiz_id (quiz_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS course_enrollments (
            enrollment_id INT(11) NOT NULL AUTO_INCREMENT,
            user_id INT(11) NOT NULL,
            course_id INT(11) NOT NULL,
            progress INT(11) DEFAULT 0,
            started_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            completed_at TIMESTAMP NULL DEFAULT NULL,
            last_accessed TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (enrollment_id),
            UNIQUE KEY unique_enrollment (user_id, course_id),
            KEY idx_user (user_id),
            KEY idx_course (course_id),
            KEY idx_progress (progress)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS module_progress (
            id INT(11) NOT NULL AUTO_INCREMENT,
            user_id INT(11) NOT NULL,
            module_id INT(11) NOT NULL,
            is_completed TINYINT(1) DEFAULT 0,
            completed_at TIMESTAMP NULL DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY unique_progress (user_id, module_id),
            KEY idx_user (user_id),
            KEY idx_module (module_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS course_purchases (
            purchase_id INT(11) NOT NULL AUTO_INCREMENT,
            user_id INT(11) NOT NULL,
            course_id INT(11) NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            payment_method ENUM('naira','seeds') NOT NULL,
            transaction_ref VARCHAR(100) DEFAULT NULL,
            purchased_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (purchase_id),
            UNIQUE KEY unique_purchase (user_id, course_id),
            KEY idx_user (user_id),
            KEY idx_course (course_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS course_reviews (
            review_id INT(11) NOT NULL AUTO_INCREMENT,
            user_id INT(11) NOT NULL,
            course_id INT(11) NOT NULL,
            rating TINYINT(4) NOT NULL,
            review TEXT DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (review_id),
            UNIQUE KEY unique_review (user_id, course_id),
            KEY idx_course (course_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // Seed default categories (only when empty) so admin course creation has options.
    if ((int) $pdo->query("SELECT COUNT(*) FROM course_categories")->fetchColumn() === 0) {
        $pdo->exec("
            INSERT INTO course_categories (name, slug, icon, description) VALUES
            ('Technology', 'technology', 'fa-laptop-code', 'Software, data, and digital skills'),
            ('Design', 'design', 'fa-pen-nib', 'UI/UX, graphics, and product design'),
            ('Business', 'business', 'fa-briefcase', 'Entrepreneurship, management, and strategy'),
            ('Marketing', 'marketing', 'fa-bullhorn', 'Growth, content, and social media'),
            ('Career Skills', 'career-skills', 'fa-user-graduate', 'CVs, interviews, and workplace skills'),
            ('Finance', 'finance', 'fa-coins', 'Personal finance, accounting, and fintech')
        ");
    }
};
