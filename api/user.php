<?php
require_once __DIR__ . '/config.php';

Session::start();

if (!Session::isLoggedIn()) {
    jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$userId = Session::userId();

if ($method === 'GET') {
    $stmt = $pdo->prepare("
        SELECT user_id, first_name, last_name, full_name, email, phone, user_type,
               profile_image, headline, bio, country_id, city, is_verified, created_at
        FROM users
        WHERE user_id = ?
        LIMIT 1
    ");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    jsonResponse(['success' => true, 'data' => $user]);
}

if ($method === 'POST' || $method === 'PUT') {
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $fullName = trim(Security::clean($input['full_name'] ?? ''));
    $phone = trim(Security::clean($input['phone'] ?? ''));
    $headline = trim(Security::clean($input['headline'] ?? ''));
    $bio = trim(Security::clean($input['bio'] ?? ''));
    $city = trim(Security::clean($input['city'] ?? ''));

    if ($fullName === '') {
        jsonResponse(['success' => false, 'message' => 'Full name is required'], 422);
    }

    [$firstName, $lastName] = array_pad(explode(' ', $fullName, 2), 2, '');
    $stmt = $pdo->prepare("
        UPDATE users
        SET first_name = ?, last_name = ?, full_name = ?, phone = ?, headline = ?, bio = ?, city = ?
        WHERE user_id = ?
    ");
    $stmt->execute([
        $firstName,
        $lastName,
        $fullName,
        $phone ?: null,
        $headline ?: null,
        $bio ?: null,
        $city ?: null,
        $userId,
    ]);

    $_SESSION['full_name'] = $fullName;
    jsonResponse(['success' => true, 'message' => 'Profile updated']);
}

jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
