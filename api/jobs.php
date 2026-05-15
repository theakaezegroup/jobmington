<?php
require_once 'config.php';

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        // Get all jobs or filter
        $country = $_GET['country'] ?? null;
        $keyword = $_GET['keyword'] ?? null;
        
        $sql = "SELECT j.*, c.name as country_name, c.region 
                FROM jobs j 
                JOIN countries c ON j.country_id = c.country_id 
                WHERE j.is_active = 1";
        
        if ($country) {
            $sql .= " AND c.name = :country";
        }
        if ($keyword) {
            $sql .= " AND (j.title LIKE :keyword OR j.description LIKE :keyword)";
        }
        
        $sql .= " ORDER BY j.posted_at DESC";
        
        $stmt = $pdo->prepare($sql);
        
        if ($country) $stmt->bindValue(':country', $country);
        if ($keyword) $stmt->bindValue(':keyword', "%$keyword%");
        
        $stmt->execute();
        $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            "success" => true,
            "count" => count($jobs),
            "data" => $jobs
        ]);
        break;
        
    default:
        echo json_encode(["error" => "Method not allowed"]);
        break;
}
?>