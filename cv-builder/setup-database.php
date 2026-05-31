<?php
/**
 * JOBMINGTON - CV Database Setup
 * Run this to add all necessary columns and tables for the world-class CV builder
 */

define('JOBMINGTON', true);
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/database.php';

$pdo = db();
$results = [];

// 1. Add missing columns to cv_profiles
$columnsToAdd = [
    'headline' => "VARCHAR(255) DEFAULT NULL",
    'linkedin_url' => "VARCHAR(500) DEFAULT NULL",
    'portfolio_url' => "VARCHAR(500) DEFAULT NULL", 
    'github_url' => "VARCHAR(500) DEFAULT NULL",
    'location' => "VARCHAR(255) DEFAULT NULL"
];

foreach ($columnsToAdd as $column => $definition) {
    try {
        $pdo->exec("ALTER TABLE cv_profiles ADD COLUMN $column $definition");
        $results[] = " Added column '$column' to cv_profiles";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            $results[] = "• Column '$column' already exists in cv_profiles";
        } else {
            $results[] = " Error adding '$column': " . $e->getMessage();
        }
    }
}

// 2. Add missing columns to cv_education
$eduColumns = [
    'field_of_study' => "VARCHAR(255) DEFAULT NULL",
    'grade' => "VARCHAR(100) DEFAULT NULL",
    'location' => "VARCHAR(255) DEFAULT NULL"
];

foreach ($eduColumns as $column => $definition) {
    try {
        $pdo->exec("ALTER TABLE cv_education ADD COLUMN $column $definition");
        $results[] = " Added column '$column' to cv_education";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            $results[] = "• Column '$column' already exists in cv_education";
        } else {
            $results[] = " Error adding '$column': " . $e->getMessage();
        }
    }
}

// 3. Add missing columns to cv_experience
$expColumns = [
    'location' => "VARCHAR(255) DEFAULT NULL"
];

foreach ($expColumns as $column => $definition) {
    try {
        $pdo->exec("ALTER TABLE cv_experience ADD COLUMN $column $definition");
        $results[] = " Added column '$column' to cv_experience";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            $results[] = "• Column '$column' already exists in cv_experience";
        } else {
            $results[] = " Error adding '$column': " . $e->getMessage();
        }
    }
}

// 4. Create cv_certifications table
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS cv_certifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            cv_id INT NOT NULL,
            name VARCHAR(255) NOT NULL,
            issuing_organization VARCHAR(255),
            issue_date DATE,
            expiry_date DATE,
            credential_id VARCHAR(255),
            credential_url VARCHAR(500),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_cv_id (cv_id),
            FOREIGN KEY (cv_id) REFERENCES cv_profiles(cv_id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $results[] = " Created/verified cv_certifications table";
} catch (PDOException $e) {
    $results[] = " cv_certifications: " . $e->getMessage();
}

// 5. Create cv_languages table
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS cv_languages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            cv_id INT NOT NULL,
            language VARCHAR(100) NOT NULL,
            proficiency ENUM('basic', 'conversational', 'professional', 'fluent', 'native') DEFAULT 'professional',
            sort_order INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_cv_id (cv_id),
            FOREIGN KEY (cv_id) REFERENCES cv_profiles(cv_id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $results[] = " Created/verified cv_languages table";
} catch (PDOException $e) {
    $results[] = " cv_languages: " . $e->getMessage();
}

// 6. Create cv_projects table
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS cv_projects (
            id INT AUTO_INCREMENT PRIMARY KEY,
            cv_id INT NOT NULL,
            name VARCHAR(255) NOT NULL,
            role VARCHAR(255),
            url VARCHAR(500),
            technologies TEXT,
            description TEXT,
            start_date DATE,
            end_date DATE,
            is_current TINYINT(1) DEFAULT 0,
            sort_order INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_cv_id (cv_id),
            FOREIGN KEY (cv_id) REFERENCES cv_profiles(cv_id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $results[] = " Created/verified cv_projects table";
} catch (PDOException $e) {
    $results[] = " cv_projects: " . $e->getMessage();
}

// 7. Create cv_awards table
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS cv_awards (
            id INT AUTO_INCREMENT PRIMARY KEY,
            cv_id INT NOT NULL,
            title VARCHAR(255) NOT NULL,
            issuer VARCHAR(255),
            date_received DATE,
            description TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_cv_id (cv_id),
            FOREIGN KEY (cv_id) REFERENCES cv_profiles(cv_id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $results[] = " Created/verified cv_awards table";
} catch (PDOException $e) {
    $results[] = " cv_awards: " . $e->getMessage();
}

// 8. Create cv_volunteer table
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS cv_volunteer (
            id INT AUTO_INCREMENT PRIMARY KEY,
            cv_id INT NOT NULL,
            organization VARCHAR(255) NOT NULL,
            role VARCHAR(255),
            cause VARCHAR(255),
            start_date DATE,
            end_date DATE,
            is_current TINYINT(1) DEFAULT 0,
            description TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_cv_id (cv_id),
            FOREIGN KEY (cv_id) REFERENCES cv_profiles(cv_id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $results[] = " Created/verified cv_volunteer table";
} catch (PDOException $e) {
    $results[] = " cv_volunteer: " . $e->getMessage();
}

// 9. Create cv_references table
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS cv_references (
            id INT AUTO_INCREMENT PRIMARY KEY,
            cv_id INT NOT NULL,
            name VARCHAR(255) NOT NULL,
            title VARCHAR(255),
            company VARCHAR(255),
            email VARCHAR(255),
            phone VARCHAR(50),
            relationship VARCHAR(255),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_cv_id (cv_id),
            FOREIGN KEY (cv_id) REFERENCES cv_profiles(cv_id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $results[] = " Created/verified cv_references table";
} catch (PDOException $e) {
    $results[] = " cv_references: " . $e->getMessage();
}

// Output HTML results
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CV Builder Database Setup</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Futura Cyrillic Demi';
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            min-height: 100vh;
            padding: 40px 20px;
            color: #f1f5f9;
        }
        .container {
            max-width: 700px;
            margin: 0 auto;
            background: rgba(15, 23, 42, 0.9);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 16px;
            padding: 40px;
            backdrop-filter: blur(10px);
        }
        h1 {
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .subtitle {
            color: #94a3b8;
            margin-bottom: 30px;
        }
        .results {
            background: rgba(0,0,0,0.3);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 30px;
        }
        .result-item {
            padding: 10px 0;
            border-bottom: 1px solid rgba(255,255,255,0.05);
            font-family: 'Futura Cyrillic Demi';
            font-size: 0.875rem;
        }
        .result-item:last-child { border-bottom: none; }
        .result-item.success { color: #22c55e; }
        .result-item.info { color: #94a3b8; }
        .result-item.error { color: #ef4444; }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 14px 28px;
            background: #0077b5;
            color: white;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s;
        }
        .btn:hover {
            background: #005885;
            transform: translateY(-2px);
        }
        .success-banner {
            background: rgba(34, 197, 94, 0.1);
            border: 1px solid rgba(34, 197, 94, 0.3);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 30px;
            text-align: center;
        }
        .success-banner h2 {
            color: #22c55e;
            margin-bottom: 8px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>
            <svg width="28" height="28" fill="currentColor" viewBox="0 0 20 20" style="color: #0077b5;">
                <path fill-rule="evenodd" d="M6 2a2 2 0 00-2 2v12a2 2 0 002 2h8a2 2 0 002-2V7.414A2 2 0 0015.414 6L12 2.586A2 2 0 0010.586 2H6zm5 6a1 1 0 10-2 0v2H7a1 1 0 100 2h2v2a1 1 0 102 0v-2h2a1 1 0 100-2h-2V8z" clip-rule="evenodd"/>
            </svg>
            CV Builder Database Setup
        </h1>
        <p class="subtitle">Setting up tables for world-class CV building</p>
        
        <div class="success-banner">
            <h2> Setup Complete</h2>
            <p>Your CV builder database is now ready with all sections.</p>
        </div>
        
        <div class="results">
            <?php foreach ($results as $result): ?>
            <div class="result-item <?= strpos($result, '') === 0 ? 'success' : (strpos($result, '•') === 0 ? 'info' : 'error') ?>">
                <?= htmlspecialchars($result) ?>
            </div>
            <?php endforeach; ?>
        </div>
        
        <a href="<?= defined('SITE_URL') ? SITE_URL : '' ?>/cv-builder/" class="btn">
            → Go to CV Builder
        </a>
    </div>
</body>
</html>
