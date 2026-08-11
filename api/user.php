<?php
require_once __DIR__ . '/config.php';

Session::start();

if (!Session::isLoggedIn()) {
    jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$userId = Session::userId();

if ($method === 'GET') {
    // headline, bio and city live on cv_profiles, not users. Selecting them
    // here made every GET a 500.
    $stmt = $pdo->prepare("
        SELECT u.user_id, u.first_name, u.last_name, u.full_name, u.email, u.phone, u.user_type,
               u.profile_image, u.country_id, u.is_verified, u.created_at,
               cv.headline, cv.summary AS bio, cv.location AS city
        FROM users u
        LEFT JOIN cv_profiles cv ON u.user_id = cv.user_id
        WHERE u.user_id = ?
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

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            UPDATE users
            SET first_name = ?, last_name = ?, full_name = ?, phone = ?
            WHERE user_id = ?
        ");
        $stmt->execute([$firstName, $lastName, $fullName, $phone ?: null, $userId]);

        // The rest belongs to cv_profiles, same split seeker/profile.php uses.
        $cv = $pdo->prepare("SELECT cv_id, email FROM cv_profiles WHERE user_id = ? LIMIT 1");
        $cv->execute([$userId]);
        $row = $cv->fetch();

        if ($row) {
            $stmt = $pdo->prepare("UPDATE cv_profiles SET full_name = ?, phone = ?, location = ?, headline = ?, summary = ?, updated_at = NOW() WHERE cv_id = ?");
            $stmt->execute([$fullName, $phone ?: null, $city ?: null, $headline ?: null, $bio ?: null, $row['cv_id']]);
        } else {
            $email = $pdo->prepare("SELECT email FROM users WHERE user_id = ?");
            $email->execute([$userId]);
            $stmt = $pdo->prepare("INSERT INTO cv_profiles (user_id, full_name, email, phone, location, headline, summary) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$userId, $fullName, $email->fetchColumn() ?: null, $phone ?: null, $city ?: null, $headline ?: null, $bio ?: null]);
        }

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('api/user.php profile update: ' . $e->getMessage());
        jsonResponse(['success' => false, 'message' => 'Profile could not be saved'], 500);
    }

    $_SESSION['full_name'] = $fullName;
    jsonResponse(['success' => true, 'message' => 'Profile updated']);
}

jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
