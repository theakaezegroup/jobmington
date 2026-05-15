<?php
/**
 * Local render harness for smoke-checking Jobmington PHP pages.
 *
 * Usage:
 * php tools/render_check.php path/to/page.php "a=1&b=2" seeker
 */

$root = dirname(__DIR__);
chdir($root);

$target = $argv[1] ?? 'index.php';
$query = $argv[2] ?? '';
$sessionType = $argv[3] ?? 'guest';

if (!isset($argv[3]) && in_array($query, ['guest', 'seeker', 'employer', 'admin'], true)) {
    $sessionType = $query;
    $query = '';
}

$targetPath = realpath($root . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $target));
if (!$targetPath || !str_starts_with($targetPath, $root) || !is_file($targetPath)) {
    fwrite(STDERR, "invalid_target\n");
    exit(2);
}

parse_str($query, $_GET);
$_POST = [];
$_REQUEST = array_merge($_GET, $_POST);
$_SERVER['HTTP_HOST'] = '127.0.0.1:8000';
$_SERVER['SERVER_NAME'] = '127.0.0.1';
$_SERVER['SERVER_PORT'] = '8000';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/jobmington/' . str_replace('\\', '/', $target) . ($query ? '?' . $query : '');
$_SERVER['SCRIPT_NAME'] = '/jobmington/' . str_replace('\\', '/', $target);
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'JobmingtonRenderCheck';

$sessionDir = $root . DIRECTORY_SEPARATOR . '.agent' . DIRECTORY_SEPARATOR . 'render-sessions';
if (!is_dir($sessionDir)) {
    mkdir($sessionDir, 0777, true);
}
ini_set('session.save_path', $sessionDir);
session_name('JOBMINGTON_SESSION');

if ($sessionType !== 'guest') {
    require_once $root . '/config/env.php';

    $dsn = 'mysql:host=' . (getenv('DB_HOST') ?: 'localhost')
        . ';dbname=' . (getenv('DB_NAME') ?: 'jobmington_db')
        . ';charset=utf8mb4';
    $pdo = new PDO($dsn, getenv('DB_USER') ?: 'root', getenv('DB_PASS') ?: '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $stmt = $pdo->prepare("SELECT user_id, full_name, email, user_type, profile_image FROM users WHERE user_type = ? ORDER BY user_id LIMIT 1");
    $stmt->execute([$sessionType]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user && $sessionType === 'admin') {
        $user = $pdo->query("SELECT user_id, full_name, email, user_type, profile_image FROM users ORDER BY user_id LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        if ($user) {
            $user['user_type'] = 'admin';
        }
    }

    if (!$user) {
        echo "SKIP {$target} {$sessionType}: no_user\n";
        exit(0);
    }

    $renderSessionId = substr(str_pad('render' . preg_replace('/[^a-z0-9]/i', '', $sessionType) . $user['user_id'], 32, 'x'), 0, 32);
    session_id($renderSessionId);
    $_COOKIE[session_name()] = $renderSessionId;
    session_start();
    $_SESSION = [
        'user_id' => (int) $user['user_id'],
        'full_name' => $user['full_name'] ?? '',
        'email' => $user['email'] ?? '',
        'user_type' => $user['user_type'],
        'profile_image' => $user['profile_image'] ?? null,
        '_created' => time(),
        '_user_agent' => $_SERVER['HTTP_USER_AGENT'],
        '_ip' => $_SERVER['REMOTE_ADDR'],
    ];
} else {
    $renderSessionId = str_pad('renderguest', 32, 'x');
    session_id($renderSessionId);
    $_COOKIE[session_name()] = $renderSessionId;
    session_start();
}

$badTokens = [
    'Fatal error',
    'Parse error',
    '<b>Warning',
    'PHP Warning',
    '<b>Notice',
    'Deprecated:',
    'PDOException',
    'Uncaught',
];

if (getenv('RENDER_DEBUG') === '1') {
    fwrite(STDERR, "debug before require: status=" . session_status() . " id=" . session_id() . " session=" . json_encode($_SESSION) . "\n");
}

register_shutdown_function(function () use ($target, $badTokens) {
    $error = error_get_last();
    $body = ob_get_clean();
    $headers = headers_list();
    $location = null;

    foreach ($headers as $header) {
        if (stripos($header, 'Location:') === 0) {
            $location = trim(substr($header, strlen('Location:')));
            break;
        }
    }

    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        echo "BAD {$target}: {$error['message']} in {$error['file']}:{$error['line']}\n";
        return;
    }

    foreach ($badTokens as $token) {
        if (stripos($body, $token) !== false) {
            echo "BAD {$target}: found {$token}\n";
            return;
        }
    }

    if ($location !== null) {
        echo "REDIRECT {$target}: {$location}\n";
        return;
    }

    echo "OK {$target}: " . strlen($body) . " bytes\n";
});

ob_start();
require $targetPath;
