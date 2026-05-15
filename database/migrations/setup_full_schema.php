<?php
/**
 * Full Database Schema Setup
 * Creates all required tables for Jobmington
 */

define('JOBMINGTON', true);
require_once __DIR__ . '/../../config/env.php';
require_once __DIR__ . '/../../config/database.php';

$pdo = db();

echo "<div style='font-family: monospace; padding: 20px; background: #1e293b; color: #f8fafc;'>";
echo "<h2> Setting Up Full Database Schema...</h2><hr>";

// --- COUNTRIES TABLE ---
$pdo->exec("
CREATE TABLE IF NOT EXISTS countries (
    country_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    iso_code VARCHAR(3) NOT NULL,
    region VARCHAR(50) DEFAULT 'Africa',
    currency_code VARCHAR(10) DEFAULT 'NGN',
    currency_symbol VARCHAR(10) DEFAULT '₦',
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");
echo " countries table ready<br>";

// --- JOB CATEGORIES TABLE ---
$pdo->exec("
CREATE TABLE IF NOT EXISTS job_categories (
    category_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL,
    icon VARCHAR(50) DEFAULT 'fa-briefcase',
    description TEXT,
    is_active TINYINT(1) DEFAULT 1,
    job_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");
echo " job_categories table ready<br>";

// --- COMPANIES TABLE ---
$pdo->exec("
CREATE TABLE IF NOT EXISTS companies (
    company_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255),
    logo VARCHAR(255),
    website VARCHAR(255),
    description TEXT,
    industry VARCHAR(100),
    size VARCHAR(50),
    location VARCHAR(255),
    is_verified TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");
echo " companies table ready<br>";

// --- JOBS TABLE (Full Schema) ---
// First check if we need to alter existing table or create new
$result = $pdo->query("SHOW TABLES LIKE 'jobs'");
if ($result->rowCount() > 0) {
    // Table exists, check for missing columns
    $columns = $pdo->query("SHOW COLUMNS FROM jobs")->fetchAll(PDO::FETCH_COLUMN);
    
    $alterations = [];
    
    if (!in_array('job_id', $columns) && in_array('id', $columns)) {
        // Rename id to job_id
        $alterations[] = "CHANGE COLUMN id job_id INT AUTO_INCREMENT";
    }
    if (!in_array('company_id', $columns)) {
        $alterations[] = "ADD COLUMN company_id INT AFTER job_id";
    }
    if (!in_array('category_id', $columns)) {
        $alterations[] = "ADD COLUMN category_id INT AFTER country_id";
    }
    if (!in_array('country_id', $columns)) {
        $alterations[] = "ADD COLUMN country_id INT DEFAULT 1 AFTER description";
    }
    if (!in_array('city', $columns)) {
        $alterations[] = "ADD COLUMN city VARCHAR(100) AFTER country_id";
    }
    if (!in_array('job_type', $columns) && !in_array('type', $columns)) {
        $alterations[] = "ADD COLUMN job_type ENUM('full-time','part-time','contract','internship','remote') DEFAULT 'full-time'";
    }
    if (!in_array('salary_min', $columns)) {
        $alterations[] = "ADD COLUMN salary_min DECIMAL(12,2)";
    }
    if (!in_array('salary_max', $columns)) {
        $alterations[] = "ADD COLUMN salary_max DECIMAL(12,2)";
    }
    if (!in_array('is_active', $columns)) {
        $alterations[] = "ADD COLUMN is_active TINYINT(1) DEFAULT 1";
    }
    if (!in_array('is_featured', $columns)) {
        $alterations[] = "ADD COLUMN is_featured TINYINT(1) DEFAULT 0";
    }
    if (!in_array('apply_link', $columns)) {
        $alterations[] = "ADD COLUMN apply_link VARCHAR(500)";
    }
    if (!in_array('expires_at', $columns)) {
        $alterations[] = "ADD COLUMN expires_at DATE";
    }
    if (!in_array('posted_at', $columns)) {
        $alterations[] = "ADD COLUMN posted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP";
    }
    if (!in_array('views', $columns)) {
        $alterations[] = "ADD COLUMN views INT DEFAULT 0";
    }
    
    if (!empty($alterations)) {
        try {
            $sql = "ALTER TABLE jobs " . implode(", ", $alterations);
            $pdo->exec($sql);
            echo " jobs table updated with missing columns<br>";
        } catch (Exception $e) {
            echo " Some alterations may have failed: " . $e->getMessage() . "<br>";
        }
    } else {
        echo " jobs table already has required columns<br>";
    }
} else {
    // Create jobs table fresh
    $pdo->exec("
    CREATE TABLE jobs (
        job_id INT AUTO_INCREMENT PRIMARY KEY,
        company_id INT NOT NULL,
        user_id INT,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        requirements TEXT,
        country_id INT DEFAULT 1,
        city VARCHAR(100),
        category_id INT,
        job_type ENUM('full-time','part-time','contract','internship','remote') DEFAULT 'full-time',
        salary_min DECIMAL(12,2),
        salary_max DECIMAL(12,2),
        is_active TINYINT(1) DEFAULT 1,
        is_featured TINYINT(1) DEFAULT 0,
        apply_link VARCHAR(500),
        expires_at DATE,
        posted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        views INT DEFAULT 0,
        INDEX idx_company (company_id),
        INDEX idx_category (category_id),
        INDEX idx_country (country_id),
        INDEX idx_active (is_active),
        INDEX idx_featured (is_featured)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo " jobs table created<br>";
}

// --- Insert Default Categories ---
$categories = [
    ['name' => 'Technology & IT', 'slug' => 'technology-it', 'icon' => 'fa-laptop-code', 'description' => 'Software development, IT support, cybersecurity, and more'],
    ['name' => 'Finance & Accounting', 'slug' => 'finance-accounting', 'icon' => 'fa-chart-line', 'description' => 'Banking, accounting, financial analysis, and auditing'],
    ['name' => 'Marketing & Sales', 'slug' => 'marketing-sales', 'icon' => 'fa-bullhorn', 'description' => 'Digital marketing, sales, brand management, and advertising'],
    ['name' => 'Healthcare', 'slug' => 'healthcare', 'icon' => 'fa-heartbeat', 'description' => 'Medical professionals, nursing, pharmacy, and health services'],
    ['name' => 'Engineering', 'slug' => 'engineering', 'icon' => 'fa-cogs', 'description' => 'Civil, mechanical, electrical, and chemical engineering'],
    ['name' => 'Education & Training', 'slug' => 'education-training', 'icon' => 'fa-graduation-cap', 'description' => 'Teaching, training, academic research, and tutoring'],
    ['name' => 'Customer Service', 'slug' => 'customer-service', 'icon' => 'fa-headset', 'description' => 'Customer support, call center, and client relations'],
    ['name' => 'Human Resources', 'slug' => 'human-resources', 'icon' => 'fa-users', 'description' => 'Recruitment, HR management, and talent acquisition'],
    ['name' => 'Design & Creative', 'slug' => 'design-creative', 'icon' => 'fa-palette', 'description' => 'Graphic design, UI/UX, video production, and content creation'],
    ['name' => 'Legal', 'slug' => 'legal', 'icon' => 'fa-balance-scale', 'description' => 'Legal counsel, paralegal, compliance, and regulatory affairs'],
    ['name' => 'Operations & Logistics', 'slug' => 'operations-logistics', 'icon' => 'fa-truck', 'description' => 'Supply chain, warehouse management, and logistics'],
    ['name' => 'Administrative', 'slug' => 'administrative', 'icon' => 'fa-folder-open', 'description' => 'Office administration, executive assistance, and data entry'],
    ['name' => 'Real Estate', 'slug' => 'real-estate', 'icon' => 'fa-building', 'description' => 'Property management, real estate agents, and construction'],
    ['name' => 'Media & Communications', 'slug' => 'media-communications', 'icon' => 'fa-newspaper', 'description' => 'Journalism, public relations, and broadcasting'],
    ['name' => 'Hospitality & Tourism', 'slug' => 'hospitality-tourism', 'icon' => 'fa-hotel', 'description' => 'Hotels, restaurants, travel, and event management'],
];

$insertStmt = $pdo->prepare("INSERT IGNORE INTO job_categories (name, slug, icon, description) VALUES (?, ?, ?, ?)");
foreach ($categories as $cat) {
    $insertStmt->execute([$cat['name'], $cat['slug'], $cat['icon'], $cat['description']]);
}
echo " " . count($categories) . " job categories inserted<br>";

// --- Insert Default Countries ---
$countries = [
    // West Africa
    ['Nigeria', 'ng', 'West Africa', 'NGN', '₦'],
    ['Ghana', 'gh', 'West Africa', 'GHS', '₵'],
    ['Senegal', 'sn', 'West Africa', 'XOF', 'CFA'],
    ['Ivory Coast', 'ci', 'West Africa', 'XOF', 'CFA'],
    ['Cameroon', 'cm', 'West Africa', 'XAF', 'FCFA'],
    // East Africa
    ['Kenya', 'ke', 'East Africa', 'KES', 'KSh'],
    ['Tanzania', 'tz', 'East Africa', 'TZS', 'TSh'],
    ['Uganda', 'ug', 'East Africa', 'UGX', 'USh'],
    ['Rwanda', 'rw', 'East Africa', 'RWF', 'FRw'],
    ['Ethiopia', 'et', 'East Africa', 'ETB', 'Br'],
    // Southern Africa
    ['South Africa', 'za', 'Southern Africa', 'ZAR', 'R'],
    ['Zimbabwe', 'zw', 'Southern Africa', 'ZWL', 'Z$'],
    ['Botswana', 'bw', 'Southern Africa', 'BWP', 'P'],
    ['Zambia', 'zm', 'Southern Africa', 'ZMW', 'ZK'],
    // North Africa
    ['Egypt', 'eg', 'North Africa', 'EGP', 'E£'],
    ['Morocco', 'ma', 'North Africa', 'MAD', 'د.م.'],
];

$insertStmt = $pdo->prepare("INSERT IGNORE INTO countries (name, iso_code, region, currency_code, currency_symbol, is_active) VALUES (?, ?, ?, ?, ?, 1)");
foreach ($countries as $c) {
    $insertStmt->execute($c);
}
echo " " . count($countries) . " countries seeded<br>";

echo "<hr><h3> Schema Setup Complete!</h3>";
echo "<p>Next: <a href='categorize_jobs.php' style='color: #60a5fa;'>Categorize Existing Jobs →</a></p>";
echo "</div>";
