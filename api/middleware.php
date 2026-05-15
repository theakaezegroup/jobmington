<?php
if (!defined('JOBMINGTON')) {
    require_once __DIR__ . '/config.php';
}

function api_require_login(): int {
    Session::start();
    if (!Session::isLoggedIn()) {
        jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
    }
    return (int) Session::userId();
}

function api_require_role(string $role): int {
    $userId = api_require_login();
    if (Session::userType() !== $role && !Session::isAdmin()) {
        jsonResponse(['success' => false, 'message' => 'Forbidden'], 403);
    }
    return $userId;
}

function api_input(): array {
    $raw = file_get_contents('php://input');
    $json = $raw ? json_decode($raw, true) : null;
    return is_array($json) ? $json : $_POST;
}
