<?php
require_once __DIR__ . '/config.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method !== 'GET') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

$code = trim($_GET['code'] ?? '');

try {
    if ($code !== '') {
        $stmt = $pdo->prepare("
            SELECT cert.certificate_id, cert.verification_code, cert.issued_at, cert.pdf_path,
                   u.full_name, c.title AS course_title
            FROM certificates cert
            JOIN users u ON cert.user_id = u.user_id
            JOIN courses c ON cert.course_id = c.course_id
            WHERE cert.verification_code = ?
            LIMIT 1
        ");
        $stmt->execute([$code]);
        $certificate = $stmt->fetch();

        if (!$certificate) {
            jsonResponse(['success' => false, 'message' => 'Certificate not found'], 404);
        }

        jsonResponse(['success' => true, 'data' => $certificate]);
    }

    Session::start();
    if (!Session::isLoggedIn()) {
        jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
    }

    $stmt = $pdo->prepare("
        SELECT cert.certificate_id, cert.verification_code, cert.issued_at, cert.pdf_path,
               c.title AS course_title
        FROM certificates cert
        JOIN courses c ON cert.course_id = c.course_id
        WHERE cert.user_id = ?
        ORDER BY cert.issued_at DESC
    ");
    $stmt->execute([Session::userId()]);

    jsonResponse(['success' => true, 'count' => $stmt->rowCount(), 'data' => $stmt->fetchAll()]);
} catch (Throwable $e) {
    jsonResponse(['success' => false, 'message' => 'Unable to load certificates'], 500);
}
