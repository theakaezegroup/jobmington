<?php
// upgrade_db.php
// Adds the missing 'apply_link' column to the jobs table

define('JOBMINGTON', true);
require_once __DIR__ . '/config/env.php';
require_once __DIR__ . '/config/database.php';

$pdo = db();

echo "<div style='font-family: monospace; padding: 20px;'>";
echo "<h2> Upgrading Database...</h2>";

try {
    // Check if column exists first
    $stmt = $pdo->query("SHOW COLUMNS FROM jobs LIKE 'apply_link'");
    
    if ($stmt->fetch()) {
        echo " Column 'apply_link' already exists. You are good.<br>";
    } else {
        // Create the column
        $pdo->exec("ALTER TABLE jobs ADD COLUMN apply_link VARCHAR(255) NULL AFTER description");
        echo " Success: Added 'apply_link' column to 'jobs' table.<br>";
    }
} catch (PDOException $e) {
    echo " Error: " . $e->getMessage();
}

echo "<hr><h3>Now run <a href='import.php'>import.php</a> again!</h3>";
echo "</div>";
?>