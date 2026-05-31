<?php
/**
 * JOBMINGTON - Scheduled Backup Runner
 *
 * Examples:
 * php cron/run_backup.php
 * php cron/run_backup.php --include-uploads
 * php cron/run_backup.php --keep-days=30
 */

define('JOBMINGTON', true);
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/backup.php';

$args = $argv ?? [];
$includeUploads = in_array('--include-uploads', $args, true);
$keepDays = 14;

foreach ($args as $arg) {
    if (str_starts_with($arg, '--keep-days=')) {
        $keepDays = max(1, (int) substr($arg, strlen('--keep-days=')));
    }
}

try {
    $result = jm_backup_create(db(), [
        'include_uploads' => $includeUploads,
        'keep_days' => $keepDays,
    ]);

    echo '[' . date('c') . '] Backup complete: ';
    echo ($result['manifest_name'] ?? 'manifest unavailable') . ', ';
    echo jm_backup_format_bytes((int) ($result['total_size'] ?? 0)) . PHP_EOL;
    exit(0);
} catch (Throwable $e) {
    error_log('Scheduled backup failed: ' . $e->getMessage());
    fwrite(STDERR, '[' . date('c') . '] Backup failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
