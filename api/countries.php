<?php
require_once 'config.php';

$stmt = $pdo->query("SELECT * FROM countries ORDER BY region, name");
$countries = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    "success" => true,
    "data" => $countries
]);
?>