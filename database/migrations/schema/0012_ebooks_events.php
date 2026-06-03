<?php
/**
 * Ebooks library + Events/Webinars system.
 *  - ebooks: downloadable resources (free or paid).
 *  - events: webinars / workshops / meetups (online or in-person).
 *  - event_registrations: who signed up for an event.
 */
return function (PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS ebooks (
            ebook_id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            slug VARCHAR(255) NOT NULL UNIQUE,
            author VARCHAR(150) DEFAULT NULL,
            description TEXT DEFAULT NULL,
            category VARCHAR(100) DEFAULT NULL,
            cover_image VARCHAR(500) DEFAULT NULL,
            file_path VARCHAR(500) DEFAULT NULL,
            pages INT DEFAULT 0,
            is_free TINYINT(1) DEFAULT 1,
            price DECIMAL(10,2) DEFAULT 0.00,
            seed_price INT DEFAULT 0,
            download_count INT DEFAULT 0,
            is_published TINYINT(1) DEFAULT 1,
            is_featured TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_published (is_published),
            KEY idx_featured (is_featured)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS events (
            event_id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            slug VARCHAR(255) NOT NULL UNIQUE,
            description TEXT DEFAULT NULL,
            event_type ENUM('webinar','workshop','meetup','conference') DEFAULT 'webinar',
            cover_image VARCHAR(500) DEFAULT NULL,
            host_name VARCHAR(150) DEFAULT NULL,
            host_bio TEXT DEFAULT NULL,
            starts_at DATETIME NOT NULL,
            ends_at DATETIME DEFAULT NULL,
            timezone VARCHAR(64) DEFAULT 'Africa/Lagos',
            is_online TINYINT(1) DEFAULT 1,
            location VARCHAR(255) DEFAULT NULL,
            meeting_url VARCHAR(500) DEFAULT NULL,
            capacity INT DEFAULT 0,
            registration_count INT DEFAULT 0,
            is_free TINYINT(1) DEFAULT 1,
            price DECIMAL(10,2) DEFAULT 0.00,
            is_published TINYINT(1) DEFAULT 1,
            is_featured TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_published (is_published),
            KEY idx_starts (starts_at),
            KEY idx_featured (is_featured)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS event_registrations (
            registration_id INT AUTO_INCREMENT PRIMARY KEY,
            event_id INT NOT NULL,
            user_id INT DEFAULT NULL,
            name VARCHAR(150) DEFAULT NULL,
            email VARCHAR(255) DEFAULT NULL,
            registered_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_registration (event_id, user_id),
            KEY idx_event (event_id),
            KEY idx_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
};
