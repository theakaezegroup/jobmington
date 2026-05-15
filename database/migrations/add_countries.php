<?php
/**
 * Migration: Add region column and seed African countries
 */

define('JOBMINGTON', true);
require_once dirname(__DIR__, 2) . '/config/env.php';
require_once dirname(__DIR__, 2) . '/config/database.php';

$pdo = db();

echo "<div style='font-family: system-ui; max-width: 800px; margin: 40px auto; padding: 20px; background: #0f172a; color: #e2e8f0; border-radius: 12px;'>";
echo "<h2> Adding Countries Migration</h2><hr style='border-color: #334155;'>";

// Step 1: Add region and currency_code columns if they don't exist
try {
    $pdo->exec("ALTER TABLE countries ADD COLUMN IF NOT EXISTS region VARCHAR(50) DEFAULT 'Africa'");
    echo " Added 'region' column<br>";
} catch (Exception $e) {
    echo "ℹ 'region' column already exists or error: " . $e->getMessage() . "<br>";
}

try {
    $pdo->exec("ALTER TABLE countries ADD COLUMN IF NOT EXISTS currency_code VARCHAR(10) DEFAULT 'NGN'");
    echo " Added 'currency_code' column<br>";
} catch (Exception $e) {
    echo "ℹ 'currency_code' column already exists or error: " . $e->getMessage() . "<br>";
}

// Step 2: Update existing Nigeria record
$pdo->exec("UPDATE countries SET region = 'West Africa', currency_code = 'NGN' WHERE iso_code = 'ng' OR iso_code = 'NG'");
echo " Updated existing Nigeria record<br>";

// Step 3: Seed African countries
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
$count = 0;
foreach ($countries as $c) {
    $insertStmt->execute($c);
    $count++;
}
echo " {$count} countries seeded<br>";

// Step 4: Verify
$stmt = $pdo->query("SELECT COUNT(*) as total FROM countries WHERE is_active = 1");
$result = $stmt->fetch();
echo "<hr style='border-color: #334155;'>";
echo "<h3 style='color: #22c55e;'> Done! {$result['total']} active countries in database</h3>";

// Show what we have
$stmt = $pdo->query("SELECT region, GROUP_CONCAT(name SEPARATOR ', ') as countries FROM countries WHERE is_active = 1 GROUP BY region ORDER BY region");
$regions = $stmt->fetchAll();
echo "<h4>Countries by Region:</h4>";
foreach ($regions as $r) {
    echo "<p><strong>{$r['region']}:</strong> {$r['countries']}</p>";
}

echo "</div>";
?>
