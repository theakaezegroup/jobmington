<?php
/**
 * JOBMINGTON - Admin Backup Download
 */

define('JOBMINGTON', true);
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/backup.php';

Session::start();
Session::requireAdmin();

try {
    $file = (string) get('file', '');
    $path = jm_backup_resolve_file($file);
    $name = basename($path);

    $type = 'application/octet-stream';
    if (str_ends_with($name, '.sql')) {
        $type = 'application/sql';
    } elseif (str_ends_with($name, '.zip')) {
        $type = 'application/zip';
    } elseif (str_ends_with($name, '.json')) {
        $type = 'application/json';
    }

    header('Content-Type: ' . $type);
    header('Content-Length: ' . filesize($path));
    header('Content-Disposition: attachment; filename="' . $name . '"');
    header('X-Content-Type-Options: nosniff');
    readfile($path);
    exit;
} catch (Throwable $e) {
    http_response_code(404);
    echo 'Backup file unavailable.';
}
