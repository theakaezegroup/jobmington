<?php
/**
 * Fix: Remove duplicate countries from database
 * Keeps only the first record for each iso_code
 */

define('JOBMINGTON', true);
require_once dirname(__DIR__, 2) . '/config/env.php';
require_once dirname(__DIR__, 2) . '/config/database.php';

$pdo = db();

echo "<!DOCTYPE html><html><head><title>Country Duplicates Cleanup</title></head><body>";
echo "<div style='font-family: system-ui; max-width: 600px; margin: 40px auto; padding: 30px; background: #f8fafc; border: 2px solid #001733; border-radius: 16px; box-shadow: 8px 8px 0px 0px #001733;'>";
echo "<h2 style='margin:0 0 20px 0; color: #001733;'> Country Duplicates Cleanup</h2><hr style='border-color: #001733; margin-bottom: 20px;'>";

// Step 1: Check for duplicates
echo "<h4 style='color: #001733;'>Step 1: Checking for duplicates...</h4>";
$stmt = $pdo->query("
    SELECT iso_code, COUNT(*) as cnt 
    FROM countries 
    GROUP BY iso_code 
    HAVING COUNT(*) > 1
");
$duplicates = $stmt->fetchAll();

if (empty($duplicates)) {
    echo "<p style='color: #22c55e;'> No duplicates found! Database is clean.</p>";
} else {
    echo "<p style='color: #f97316;'>Found " . count($duplicates) . " countries with duplicates:</p>";
    echo "<ul>";
    foreach ($duplicates as $d) {
        echo "<li><strong>{$d['iso_code']}</strong> appears {$d['cnt']} times</li>";
    }
    echo "</ul>";
    
    // Step 2: Delete duplicates, keeping the one with the lowest country_id
    echo "<h4 style='color: #001733;'>Step 2: Removing duplicates...</h4>";
    
    $deleted = 0;
    foreach ($duplicates as $d) {
        // Get the minimum ID for this iso_code (the one we want to keep)
        $stmt = $pdo->prepare("SELECT MIN(country_id) as keep_id FROM countries WHERE iso_code = ?");
        $stmt->execute([$d['iso_code']]);
        $keepId = $stmt->fetch()['keep_id'];
        
        // Delete all others
        $stmt = $pdo->prepare("DELETE FROM countries WHERE iso_code = ? AND country_id != ?");
        $stmt->execute([$d['iso_code'], $keepId]);
        $deleted += $stmt->rowCount();
        
        echo "<p> Cleaned <strong>{$d['iso_code']}</strong> - kept ID {$keepId}</p>";
    }
    
    echo "<p style='color: #22c55e;'> Deleted {$deleted} duplicate records.</p>";
}

// Step 3: Ensure unique constraint exists
echo "<h4 style='color: #001733;'>Step 3: Adding unique constraint...</h4>";
try {
    // First check if index exists
    $stmt = $pdo->query("SHOW INDEX FROM countries WHERE Key_name = 'unique_iso_code'");
    $exists = $stmt->fetch();
    
    if (!$exists) {
        $pdo->exec("ALTER TABLE countries ADD UNIQUE INDEX unique_iso_code (iso_code)");
        echo "<p style='color: #22c55e;'> Added unique constraint on iso_code.</p>";
    } else {
        echo "<p style='color: #22c55e;'> Unique constraint already exists.</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: #f97316;'> Could not add constraint: " . $e->getMessage() . "</p>";
}

// Step 4: Verify final count
echo "<h4 style='color: #001733;'>Step 4: Final verification...</h4>";
$stmt = $pdo->query("SELECT COUNT(*) as total FROM countries");
$total = $stmt->fetch()['total'];

$stmt = $pdo->query("
    SELECT region, COUNT(*) as cnt 
    FROM countries 
    WHERE is_active = 1 
    AND region IN ('West Africa', 'East Africa', 'North Africa', 'Southern Africa', 'Central Africa')
    GROUP BY region 
    ORDER BY 
        CASE 
            WHEN region = 'West Africa' THEN 1
            WHEN region = 'East Africa' THEN 2
            WHEN region = 'North Africa' THEN 3
            WHEN region = 'Central Africa' THEN 4
            WHEN region = 'Southern Africa' THEN 5
        END
");
$regions = $stmt->fetchAll();

echo "<p><strong>Total countries:</strong> {$total}</p>";
echo "<p><strong>African countries by region:</strong></p>";
echo "<ul>";
foreach ($regions as $r) {
    echo "<li>{$r['region']}: {$r['cnt']} countries</li>";
}
echo "</ul>";

// Check Nigeria specifically
$ng = $pdo->query("SELECT * FROM countries WHERE iso_code = 'ng'")->fetch();
echo "<p style='padding: 10px; background: #dcfce7; border-radius: 8px;'>";
echo " <strong>Nigeria:</strong> " . ($ng ? "Active ({$ng['currency_symbol']} {$ng['currency_code']})" : "NOT FOUND!");
echo "</p>";

echo "<hr style='border-color: #001733; margin: 20px 0;'>";
echo "<p style='text-align: center; font-size: 1.2em;'> <strong>Cleanup Complete!</strong><br><small>Refresh the country modal to see the fix.</small></p>";
echo "</div></body></html>";
?>
