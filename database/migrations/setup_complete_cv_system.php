<?php
/**
 * JOBMINGTON - Complete CV System Setup
 * Creates all tables needed for a world-class CV builder
 */

define('JOBMINGTON', true);
require_once __DIR__ . '/../../config/env.php';
require_once __DIR__ . '/../../config/database.php';

$pdo = db();

echo "<div style='font-family: system-ui, sans-serif; max-width: 800px; margin: 40px auto; padding: 20px; background: #0f172a; color: #f8fafc; border-radius: 16px;'>";
echo "<h1 style='color: #fbbf24;'> Setting Up World-Class CV System</h1><hr style='border-color: #334155;'>";

try {
    // 1. CV PROFILES (Main CV record)
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS cv_profiles (
        cv_id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        title VARCHAR(100) DEFAULT 'My CV',
        template VARCHAR(50) DEFAULT 'professional',
        full_name VARCHAR(150),
        email VARCHAR(150),
        phone VARCHAR(50),
        location VARCHAR(200),
        linkedin_url VARCHAR(255),
        portfolio_url VARCHAR(255),
        github_url VARCHAR(255),
        headline VARCHAR(255),
        summary TEXT,
        is_public TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_user_id (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    
    // Add missing columns to existing table
    $columns = $pdo->query("SHOW COLUMNS FROM cv_profiles")->fetchAll(PDO::FETCH_COLUMN);
    $alterations = [
        'location' => "ADD COLUMN location VARCHAR(200) AFTER phone",
        'linkedin_url' => "ADD COLUMN linkedin_url VARCHAR(255) AFTER location",
        'portfolio_url' => "ADD COLUMN portfolio_url VARCHAR(255) AFTER linkedin_url",
        'github_url' => "ADD COLUMN github_url VARCHAR(255) AFTER portfolio_url",
        'is_public' => "ADD COLUMN is_public TINYINT(1) DEFAULT 0 AFTER summary"
    ];
    foreach ($alterations as $col => $sql) {
        if (!in_array($col, $columns)) {
            try { $pdo->exec("ALTER TABLE cv_profiles $sql"); } catch (Exception $e) {}
        }
    }
    // Rename city to location if needed
    if (in_array('city', $columns) && !in_array('location', $columns)) {
        try { $pdo->exec("ALTER TABLE cv_profiles CHANGE city location VARCHAR(200)"); } catch (Exception $e) {}
    }
    echo " cv_profiles table ready<br>";

    // 2. WORK EXPERIENCE
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS cv_experience (
        id INT AUTO_INCREMENT PRIMARY KEY,
        cv_id INT NOT NULL,
        job_title VARCHAR(200) NOT NULL,
        company VARCHAR(200) NOT NULL,
        location VARCHAR(150),
        employment_type ENUM('full-time','part-time','contract','freelance','internship','volunteer') DEFAULT 'full-time',
        start_date DATE,
        end_date DATE,
        is_current TINYINT(1) DEFAULT 0,
        description TEXT,
        achievements TEXT,
        sort_order INT DEFAULT 0,
        INDEX idx_cv_id (cv_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    // Add missing columns
    $columns = $pdo->query("SHOW COLUMNS FROM cv_experience")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('location', $columns)) {
        try { $pdo->exec("ALTER TABLE cv_experience ADD COLUMN location VARCHAR(150) AFTER company"); } catch (Exception $e) {}
    }
    if (!in_array('employment_type', $columns)) {
        try { $pdo->exec("ALTER TABLE cv_experience ADD COLUMN employment_type ENUM('full-time','part-time','contract','freelance','internship','volunteer') DEFAULT 'full-time' AFTER location"); } catch (Exception $e) {}
    }
    if (!in_array('achievements', $columns)) {
        try { $pdo->exec("ALTER TABLE cv_experience ADD COLUMN achievements TEXT AFTER description"); } catch (Exception $e) {}
    }
    if (!in_array('sort_order', $columns)) {
        try { $pdo->exec("ALTER TABLE cv_experience ADD COLUMN sort_order INT DEFAULT 0"); } catch (Exception $e) {}
    }
    echo " cv_experience table ready<br>";

    // 3. EDUCATION
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS cv_education (
        id INT AUTO_INCREMENT PRIMARY KEY,
        cv_id INT NOT NULL,
        institution VARCHAR(200) NOT NULL,
        degree VARCHAR(150),
        field_of_study VARCHAR(150),
        location VARCHAR(150),
        start_date DATE,
        end_date DATE,
        is_current TINYINT(1) DEFAULT 0,
        grade VARCHAR(50),
        description TEXT,
        achievements TEXT,
        sort_order INT DEFAULT 0,
        INDEX idx_cv_id (cv_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    // Add missing columns
    $columns = $pdo->query("SHOW COLUMNS FROM cv_education")->fetchAll(PDO::FETCH_COLUMN);
    $eduAlterations = [
        'field_of_study' => "ADD COLUMN field_of_study VARCHAR(150) AFTER degree",
        'location' => "ADD COLUMN location VARCHAR(150) AFTER field_of_study",
        'is_current' => "ADD COLUMN is_current TINYINT(1) DEFAULT 0 AFTER end_date",
        'grade' => "ADD COLUMN grade VARCHAR(50) AFTER is_current",
        'achievements' => "ADD COLUMN achievements TEXT AFTER description",
        'sort_order' => "ADD COLUMN sort_order INT DEFAULT 0"
    ];
    foreach ($eduAlterations as $col => $sql) {
        if (!in_array($col, $columns)) {
            try { $pdo->exec("ALTER TABLE cv_education $sql"); } catch (Exception $e) {}
        }
    }
    echo " cv_education table ready<br>";

    // 4. SKILLS
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS cv_skills (
        id INT AUTO_INCREMENT PRIMARY KEY,
        cv_id INT NOT NULL,
        skill_name VARCHAR(100) NOT NULL,
        skill_category VARCHAR(50),
        proficiency_level INT DEFAULT 80,
        years_experience DECIMAL(3,1),
        sort_order INT DEFAULT 0,
        INDEX idx_cv_id (cv_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $columns = $pdo->query("SHOW COLUMNS FROM cv_skills")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('skill_category', $columns)) {
        try { $pdo->exec("ALTER TABLE cv_skills ADD COLUMN skill_category VARCHAR(50) AFTER skill_name"); } catch (Exception $e) {}
    }
    if (!in_array('proficiency_level', $columns)) {
        try { $pdo->exec("ALTER TABLE cv_skills ADD COLUMN proficiency_level INT DEFAULT 80 AFTER skill_category"); } catch (Exception $e) {}
    }
    if (!in_array('years_experience', $columns)) {
        try { $pdo->exec("ALTER TABLE cv_skills ADD COLUMN years_experience DECIMAL(3,1) AFTER proficiency_level"); } catch (Exception $e) {}
    }
    echo " cv_skills table ready<br>";

    // 5. CERTIFICATIONS
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS cv_certifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        cv_id INT NOT NULL,
        name VARCHAR(200) NOT NULL,
        issuing_organization VARCHAR(200),
        issue_date DATE,
        expiry_date DATE,
        credential_id VARCHAR(100),
        credential_url VARCHAR(500),
        sort_order INT DEFAULT 0,
        INDEX idx_cv_id (cv_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo " cv_certifications table ready<br>";

    // 6. LANGUAGES
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS cv_languages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        cv_id INT NOT NULL,
        language VARCHAR(50) NOT NULL,
        proficiency ENUM('basic','conversational','professional','fluent','native') DEFAULT 'professional',
        sort_order INT DEFAULT 0,
        INDEX idx_cv_id (cv_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo " cv_languages table ready<br>";

    // 7. PROJECTS
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS cv_projects (
        id INT AUTO_INCREMENT PRIMARY KEY,
        cv_id INT NOT NULL,
        name VARCHAR(200) NOT NULL,
        role VARCHAR(150),
        url VARCHAR(500),
        start_date DATE,
        end_date DATE,
        is_current TINYINT(1) DEFAULT 0,
        description TEXT,
        technologies TEXT,
        sort_order INT DEFAULT 0,
        INDEX idx_cv_id (cv_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo " cv_projects table ready<br>";

    // 8. AWARDS & ACHIEVEMENTS
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS cv_awards (
        id INT AUTO_INCREMENT PRIMARY KEY,
        cv_id INT NOT NULL,
        title VARCHAR(200) NOT NULL,
        issuer VARCHAR(200),
        date_received DATE,
        description TEXT,
        sort_order INT DEFAULT 0,
        INDEX idx_cv_id (cv_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo " cv_awards table ready<br>";

    // 9. VOLUNTEER EXPERIENCE
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS cv_volunteer (
        id INT AUTO_INCREMENT PRIMARY KEY,
        cv_id INT NOT NULL,
        role VARCHAR(200) NOT NULL,
        organization VARCHAR(200) NOT NULL,
        cause VARCHAR(100),
        start_date DATE,
        end_date DATE,
        is_current TINYINT(1) DEFAULT 0,
        description TEXT,
        sort_order INT DEFAULT 0,
        INDEX idx_cv_id (cv_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo " cv_volunteer table ready<br>";

    // 10. PUBLICATIONS
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS cv_publications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        cv_id INT NOT NULL,
        title VARCHAR(300) NOT NULL,
        publisher VARCHAR(200),
        publication_date DATE,
        url VARCHAR(500),
        description TEXT,
        sort_order INT DEFAULT 0,
        INDEX idx_cv_id (cv_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo " cv_publications table ready<br>";

    // 11. REFERENCES
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS cv_references (
        id INT AUTO_INCREMENT PRIMARY KEY,
        cv_id INT NOT NULL,
        name VARCHAR(150) NOT NULL,
        job_title VARCHAR(150),
        company VARCHAR(150),
        email VARCHAR(150),
        phone VARCHAR(50),
        relationship VARCHAR(100),
        sort_order INT DEFAULT 0,
        INDEX idx_cv_id (cv_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo " cv_references table ready<br>";

    // 12. CUSTOM SECTIONS (for flexibility)
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS cv_custom_sections (
        id INT AUTO_INCREMENT PRIMARY KEY,
        cv_id INT NOT NULL,
        section_title VARCHAR(100) NOT NULL,
        content TEXT,
        sort_order INT DEFAULT 0,
        INDEX idx_cv_id (cv_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo " cv_custom_sections table ready<br>";

    echo "<hr style='border-color: #334155;'>";
    echo "<h2 style='color: #22c55e;'> All CV Tables Ready!</h2>";
    echo "<p>Your CV builder now supports:</p>";
    echo "<ul style='line-height: 2;'>";
    echo "<li> Personal Information & Social Links</li>";
    echo "<li> Work Experience</li>";
    echo "<li> Education</li>";
    echo "<li> Skills (with categories & proficiency)</li>";
    echo "<li> Certifications & Licenses</li>";
    echo "<li> Languages</li>";
    echo "<li> Projects & Portfolio</li>";
    echo "<li> Awards & Achievements</li>";
    echo "<li> Volunteer Experience</li>";
    echo "<li> Publications</li>";
    echo "<li> References</li>";
    echo "<li> Custom Sections</li>";
    echo "</ul>";
    echo "<p><a href='/jobmington/cv-builder/' style='color: #fbbf24; font-weight: bold;'>→ Go to CV Builder</a></p>";

} catch (PDOException $e) {
    echo "<p style='color: #ef4444;'> Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "</div>";
