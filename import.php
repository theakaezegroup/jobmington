<?php
// import.php (TUNED FOR YOUR NEW DATA)
define('JOBMINGTON', true);
require_once __DIR__ . '/config/env.php';
require_once __DIR__ . '/config/database.php';

$pdo = db();
$jsonFile = 'jobs.json'; 

echo "<div style='font-family: monospace; padding: 20px; background: #f4f4f4;'>";
echo "<h2> Importing Real Nigerian Data...</h2>";

// 1. GET ADMIN USER (To own the companies)
$user = $pdo->query("SELECT user_id FROM users LIMIT 1")->fetch();
if (!$user) die(" STOP: Please register a user on your site first!");
$userId = $user['user_id'];

// 2. ENSURE NIGERIA EXISTS
$country = $pdo->query("SELECT country_id FROM countries WHERE name = 'Nigeria' LIMIT 1")->fetch();
if ($country) {
    $countryId = $country['country_id'];
} else {
    $pdo->exec("INSERT INTO countries (name, iso_code, currency_symbol, is_active) VALUES ('Nigeria', 'NG', '₦', 1)");
    $countryId = $pdo->lastInsertId();
}

// 3. ENSURE ENGINEERING CATEGORY EXISTS
$cat = $pdo->query("SELECT category_id FROM job_categories LIMIT 1")->fetch();
$catId = $cat ? $cat['category_id'] : 1;

// 4. LOAD JSON
if (!file_exists($jsonFile)) die(" Error: jobs.json not found.");
$jsonData = file_get_contents($jsonFile);
$jobs = json_decode($jsonData, true);

if (!$jobs) die(" Error: JSON format is invalid.");

echo "info: Found " . count($jobs) . " jobs in file.<br><hr>";

foreach ($jobs as $job) {
    // --- SMART DATA MAPPING ---
    $title = $job['title'] ?? 'Software Engineer';
    $companyName = $job['company_name'] ?? 'Tech Company';
    $location = $job['location'] ?? 'Nigeria';
    $description = $job['description'] ?? 'See application link for details.';
    
    // FIX: Extract link from 'apply_options' or fallback to 'share_link'
    $applyLink = '#';
    if (!empty($job['apply_options'][0]['link'])) {
        $applyLink = $job['apply_options'][0]['link'];
    } elseif (!empty($job['share_link'])) {
        $applyLink = $job['share_link'];
    }

    // A. HANDLE COMPANY
    $stmt = $pdo->prepare("SELECT company_id FROM companies WHERE name = ?");
    $stmt->execute([$companyName]);
    $co = $stmt->fetch();

    if ($co) {
        $coId = $co['company_id'];
    } else {
        // Create Company
        $stmt = $pdo->prepare("INSERT INTO companies (user_id, name, created_at) VALUES (?, ?, NOW())");
        $stmt->execute([$userId, $companyName]);
        $coId = $pdo->lastInsertId();
    }

    // B. INSERT JOB
    // We use IGNORE to skip if exact job exists, or just catch exception
    $sql = "INSERT INTO jobs (company_id, title, description, country_id, category_id, is_active, posted_at, apply_link) 
            VALUES (?, ?, ?, ?, ?, 1, NOW(), ?)";
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$coId, $title, $description, $countryId, $catId, $applyLink]);
        echo " Imported: <strong>$title</strong> at $companyName<br>";
    } catch (PDOException $e) {
        // Likely a duplicate or data issue, safe to skip for now
        echo " Skipped: $title <span style='color:gray;font-size:0.8em'>(" . $e->getMessage() . ")</span><br>";
    }
}

echo "<hr><h3> IMPORT COMPLETE.</h3>";
echo "<a href='index.php' style='background: #000; color: #fff; padding: 10px 20px; text-decoration: none; border-radius: 4px;'>View Live Site &rarr;</a>";
echo "</div>";
?>