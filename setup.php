<?php
// setup.php
// Creates the missing Country and Category so jobs can be imported
define('JOBMINGTON', true);
require_once __DIR__ . '/config/env.php';
require_once __DIR__ . '/config/database.php';

$pdo = db();

echo "<h2> Setting up Foundation Data...</h2>";

// 1. Create Country (Nigeria)
// adjusting column names based on your likely schema: name, iso_code, currency_symbol
try {
    $stmt = $pdo->prepare("INSERT INTO countries (country_id, name, iso_code, currency_symbol, is_active) VALUES (1, 'Nigeria', 'NG', '₦', 1)");
    $stmt->execute();
    echo " Created Country: Nigeria (ID 1)<br>";
} catch (PDOException $e) {
    echo "ℹ Country (ID 1) already exists.<br>";
}

// 2. Create Category (Engineering)
try {
    $stmt = $pdo->prepare("INSERT INTO job_categories (category_id, name, icon) VALUES (1, 'Engineering', 'fa-code')");
    $stmt->execute();
    echo " Created Category: Engineering (ID 1)<br>";
} catch (PDOException $e) {
    echo "ℹ Category (ID 1) already exists.<br>";
}

echo "<hr><h3>Now try running <a href='import.php'>import.php</a> again!</h3>";
?>