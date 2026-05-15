<?php
/**
 * CV Profiles Table Migration
 * Adds missing columns: title, template
 * Run this if you get "Unknown column 'title'" errors
 */

define('JOBMINGTON', true);
require_once __DIR__ . '/../../config/env.php';
require_once __DIR__ . '/../../config/database.php';

$pdo = db();

echo "<div style='font-family: monospace; padding: 20px; background: #1e293b; color: #f8fafc;'>";
echo "<h2> Fixing CV Profiles Table...</h2><hr>";

try {
    // Check if cv_profiles table exists
    $result = $pdo->query("SHOW TABLES LIKE 'cv_profiles'");
    
    if ($result->rowCount() === 0) {
        // Create the table from scratch
        $pdo->exec("
        CREATE TABLE cv_profiles (
            cv_id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            title VARCHAR(100) DEFAULT 'My CV',
            template VARCHAR(50) DEFAULT 'professional',
            full_name VARCHAR(100),
            email VARCHAR(100),
            phone VARCHAR(30),
            city VARCHAR(100),
            headline VARCHAR(200),
            summary TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_user_id (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        echo " cv_profiles table created<br>";
    } else {
        // Table exists - check for missing columns
        $columns = $pdo->query("SHOW COLUMNS FROM cv_profiles")->fetchAll(PDO::FETCH_COLUMN);
        echo " Existing columns: " . implode(', ', $columns) . "<br><br>";
        
        $alterations = [];
        
        if (!in_array('title', $columns)) {
            $alterations[] = "ADD COLUMN title VARCHAR(100) DEFAULT 'My CV' AFTER user_id";
        }
        if (!in_array('template', $columns)) {
            $alterations[] = "ADD COLUMN template VARCHAR(50) DEFAULT 'professional' AFTER title";
        }
        if (!in_array('full_name', $columns)) {
            $alterations[] = "ADD COLUMN full_name VARCHAR(100) AFTER template";
        }
        if (!in_array('email', $columns)) {
            $alterations[] = "ADD COLUMN email VARCHAR(100) AFTER full_name";
        }
        if (!in_array('phone', $columns)) {
            $alterations[] = "ADD COLUMN phone VARCHAR(30) AFTER email";
        }
        if (!in_array('city', $columns)) {
            $alterations[] = "ADD COLUMN city VARCHAR(100) AFTER phone";
        }
        if (!in_array('headline', $columns)) {
            $alterations[] = "ADD COLUMN headline VARCHAR(200) AFTER city";
        }
        if (!in_array('summary', $columns)) {
            $alterations[] = "ADD COLUMN summary TEXT AFTER headline";
        }
        if (!in_array('created_at', $columns)) {
            $alterations[] = "ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP";
        }
        if (!in_array('updated_at', $columns)) {
            $alterations[] = "ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP";
        }
        
        if (!empty($alterations)) {
            foreach ($alterations as $alteration) {
                try {
                    $pdo->exec("ALTER TABLE cv_profiles " . $alteration);
                    echo " Applied: " . $alteration . "<br>";
                } catch (PDOException $e) {
                    echo " Skipped (may already exist): " . $alteration . "<br>";
                }
            }
        } else {
            echo " All required columns already exist!<br>";
        }
    }
    
    // Also ensure cv_experience and cv_education tables exist
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS cv_experience (
        exp_id INT AUTO_INCREMENT PRIMARY KEY,
        cv_id INT NOT NULL,
        job_title VARCHAR(150),
        company VARCHAR(150),
        city VARCHAR(100),
        start_date DATE,
        end_date DATE,
        is_current TINYINT(1) DEFAULT 0,
        description TEXT,
        INDEX idx_cv_id (cv_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo " cv_experience table ready<br>";
    
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS cv_education (
        edu_id INT AUTO_INCREMENT PRIMARY KEY,
        cv_id INT NOT NULL,
        institution VARCHAR(200),
        degree VARCHAR(150),
        field_of_study VARCHAR(150),
        start_date DATE,
        end_date DATE,
        description TEXT,
        INDEX idx_cv_id (cv_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo " cv_education table ready<br>";
    
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS cv_skills (
        skill_id INT AUTO_INCREMENT PRIMARY KEY,
        cv_id INT NOT NULL,
        skill_name VARCHAR(100),
        proficiency ENUM('beginner', 'intermediate', 'advanced', 'expert') DEFAULT 'intermediate',
        INDEX idx_cv_id (cv_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo " cv_skills table ready<br>";
    
    echo "<hr><h3> CV Tables Fixed!</h3>";
    echo "<p><a href='/Jobmington/cv-builder/' style='color: #60a5fa;'>← Back to CV Builder</a></p>";
    
} catch (PDOException $e) {
    echo "<p style='color: #ef4444;'> Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "</div>";
