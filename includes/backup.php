<?php
/**
 * JOBMINGTON - Backup Helpers
 * Creates admin-controlled database dumps and optional uploads archives.
 */

if (!defined('JOBMINGTON')) {
    die('Direct access not permitted');
}

function jm_backup_dir(): string {
    $configured = trim((string) getenv('JOBMINGTON_BACKUP_PATH'));
    if ($configured !== '') {
        return rtrim($configured, DIRECTORY_SEPARATOR);
    }

    return rtrim(dirname(ROOT_PATH), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'jobmington-backups';
}

function jm_backup_ensure_dir(): string {
    $dir = jm_backup_dir();
    if (!is_dir($dir)) {
        mkdir($dir, 0750, true);
    }
    if (!is_writable($dir)) {
        throw new RuntimeException('Backup directory is not writable: ' . $dir);
    }

    return $dir;
}

function jm_backup_format_bytes($bytes): string {
    if ($bytes === false || $bytes === null) {
        return 'Unavailable';
    }
    $bytes = max(0, (float) $bytes);
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $power = $bytes > 0 ? min((int) floor(log($bytes, 1024)), count($units) - 1) : 0;
    return number_format($bytes / (1024 ** $power), $power === 0 ? 0 : 1) . ' ' . $units[$power];
}

function jm_backup_directory_size(string $path): int {
    if (!is_dir($path)) {
        return 0;
    }

    $size = 0;
    try {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
            RecursiveIteratorIterator::CATCH_GET_CHILD
        );

        foreach ($iterator as $item) {
            if ($item->isFile() && !$item->isLink()) {
                $size += (int) $item->getSize();
            }
        }
    } catch (Throwable $e) {
        error_log('Backup directory size error: ' . $e->getMessage());
        return 0;
    }

    return $size;
}

function jm_backup_database_size(PDO $pdo): int {
    try {
        $stmt = $pdo->query("
            SELECT COALESCE(SUM(data_length + index_length), 0)
            FROM information_schema.tables
            WHERE table_schema = DATABASE()
        ");
        return (int) $stmt->fetchColumn();
    } catch (Throwable $e) {
        error_log('Backup database size error: ' . $e->getMessage());
        return 0;
    }
}

function jm_backup_quote_identifier(string $identifier): string {
    return '`' . str_replace('`', '``', $identifier) . '`';
}

function jm_backup_write_database_dump(PDO $pdo, string $dir, string $stamp): array {
    $file = $dir . DIRECTORY_SEPARATOR . "jobmington-{$stamp}-db.sql";
    $handle = fopen($file, 'wb');
    if (!$handle) {
        throw new RuntimeException('Could not create database dump file.');
    }

    fwrite($handle, "-- Jobmington database backup\n");
    fwrite($handle, "-- Created: " . date('c') . "\n\n");
    fwrite($handle, "SET FOREIGN_KEY_CHECKS=0;\n\n");

    $tables = [];
    $stmt = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'");
    foreach ($stmt->fetchAll(PDO::FETCH_NUM) as $row) {
        $tables[] = (string) $row[0];
    }

    foreach ($tables as $table) {
        $quotedTable = jm_backup_quote_identifier($table);
        $create = $pdo->query("SHOW CREATE TABLE {$quotedTable}")->fetch(PDO::FETCH_ASSOC);
        $createSql = $create['Create Table'] ?? array_values($create)[1] ?? '';

        fwrite($handle, "\n-- Table: {$table}\n");
        fwrite($handle, "DROP TABLE IF EXISTS {$quotedTable};\n");
        fwrite($handle, $createSql . ";\n\n");

        $rows = $pdo->query("SELECT * FROM {$quotedTable}", PDO::FETCH_ASSOC);
        while ($row = $rows->fetch(PDO::FETCH_ASSOC)) {
            $columns = array_map('jm_backup_quote_identifier', array_keys($row));
            $values = array_map(static function ($value) use ($pdo): string {
                return $value === null ? 'NULL' : $pdo->quote((string) $value);
            }, array_values($row));

            fwrite(
                $handle,
                "INSERT INTO {$quotedTable} (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $values) . ");\n"
            );
        }
        fwrite($handle, "\n");
    }

    fwrite($handle, "SET FOREIGN_KEY_CHECKS=1;\n");
    fclose($handle);

    return [
        'type' => 'database',
        'name' => basename($file),
        'path' => $file,
        'size' => filesize($file) ?: 0,
        'status' => 'created',
    ];
}

function jm_backup_write_uploads_archive(string $dir, string $stamp): ?array {
    if (!is_dir(UPLOADS_PATH)) {
        return [
            'type' => 'uploads',
            'name' => null,
            'path' => null,
            'size' => 0,
            'status' => 'skipped',
            'message' => 'Uploads folder does not exist.',
        ];
    }

    if (!class_exists('ZipArchive')) {
        return [
            'type' => 'uploads',
            'name' => null,
            'path' => null,
            'size' => 0,
            'status' => 'skipped',
            'message' => 'PHP ZipArchive is not installed.',
        ];
    }

    $file = $dir . DIRECTORY_SEPARATOR . "jobmington-{$stamp}-uploads.zip";
    $zip = new ZipArchive();
    if ($zip->open($file, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('Could not create uploads archive.');
    }

    $rootLength = strlen(rtrim(UPLOADS_PATH, DIRECTORY_SEPARATOR)) + 1;
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(UPLOADS_PATH, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );

    foreach ($iterator as $item) {
        if ($item->isFile() && !$item->isLink()) {
            $zip->addFile($item->getPathname(), 'uploads/' . substr($item->getPathname(), $rootLength));
        }
    }

    $zip->close();

    return [
        'type' => 'uploads',
        'name' => basename($file),
        'path' => $file,
        'size' => filesize($file) ?: 0,
        'status' => 'created',
    ];
}

function jm_backup_prune(int $keepDays = 14): int {
    $dir = jm_backup_dir();
    if (!is_dir($dir)) {
        return 0;
    }

    $deleted = 0;
    $cutoff = time() - max(1, $keepDays) * 86400;
    foreach (glob($dir . DIRECTORY_SEPARATOR . 'jobmington-*') ?: [] as $file) {
        if (is_file($file) && filemtime($file) < $cutoff) {
            unlink($file);
            $deleted++;
        }
    }

    return $deleted;
}

function jm_backup_create(PDO $pdo, array $options = []): array {
    $dir = jm_backup_ensure_dir();
    $stamp = date('Ymd-His');
    $includeUploads = (bool) ($options['include_uploads'] ?? false);
    $keepDays = (int) ($options['keep_days'] ?? 14);

    $files = [];
    $files[] = jm_backup_write_database_dump($pdo, $dir, $stamp);

    if ($includeUploads) {
        $uploads = jm_backup_write_uploads_archive($dir, $stamp);
        if ($uploads) {
            $files[] = $uploads;
        }
    }

    $deleted = jm_backup_prune($keepDays);
    $createdFiles = array_values(array_filter($files, static fn ($file) => ($file['status'] ?? '') === 'created'));
    $totalSize = array_sum(array_map(static fn ($file) => (int) ($file['size'] ?? 0), $createdFiles));

    $manifest = [
        'stamp' => $stamp,
        'created_at' => date('c'),
        'include_uploads' => $includeUploads,
        'backup_dir' => $dir,
        'files' => array_map(static function (array $file): array {
            return [
                'type' => $file['type'],
                'name' => $file['name'],
                'size' => $file['size'],
                'status' => $file['status'],
                'message' => $file['message'] ?? null,
            ];
        }, $files),
        'total_size' => $totalSize,
        'pruned_files' => $deleted,
        'status' => 'success',
    ];

    $manifestFile = $dir . DIRECTORY_SEPARATOR . "jobmington-{$stamp}-manifest.json";
    file_put_contents($manifestFile, json_encode($manifest, JSON_PRETTY_PRINT));
    $manifest['manifest_name'] = basename($manifestFile);

    return $manifest;
}

function jm_backup_list(int $limit = 12): array {
    $dir = jm_backup_dir();
    if (!is_dir($dir)) {
        return [];
    }

    $items = [];
    foreach (glob($dir . DIRECTORY_SEPARATOR . 'jobmington-*-manifest.json') ?: [] as $manifestFile) {
        $decoded = json_decode((string) file_get_contents($manifestFile), true);
        if (!is_array($decoded)) {
            continue;
        }
        $decoded['manifest_name'] = basename($manifestFile);
        $decoded['mtime'] = filemtime($manifestFile) ?: 0;
        $items[] = $decoded;
    }

    usort($items, static fn ($a, $b) => (int) ($b['mtime'] ?? 0) <=> (int) ($a['mtime'] ?? 0));
    return array_slice($items, 0, $limit);
}

function jm_backup_status(PDO $pdo): array {
    $dir = jm_backup_dir();
    $latest = jm_backup_list(1)[0] ?? null;
    $latestTime = $latest ? strtotime((string) ($latest['created_at'] ?? '')) : null;
    $diskTotal = @disk_total_space(ROOT_PATH);
    $diskFree = @disk_free_space(ROOT_PATH);
    $diskUsedPercent = ($diskTotal && $diskFree) ? (int) round((1 - ($diskFree / $diskTotal)) * 100) : 0;

    return [
        'dir' => $dir,
        'exists' => is_dir($dir),
        'writable' => is_dir($dir) && is_writable($dir),
        'latest' => $latest,
        'latest_time' => $latestTime,
        'is_stale' => !$latestTime || $latestTime < strtotime('-30 hours'),
        'backup_size' => is_dir($dir) ? jm_backup_directory_size($dir) : 0,
        'uploads_size' => jm_backup_directory_size(UPLOADS_PATH),
        'logs_size' => jm_backup_directory_size(ROOT_PATH . DIRECTORY_SEPARATOR . 'logs'),
        'database_size' => jm_backup_database_size($pdo),
        'disk_total' => $diskTotal ?: 0,
        'disk_free' => $diskFree ?: 0,
        'disk_used_percent' => $diskUsedPercent,
        'zip_available' => class_exists('ZipArchive'),
    ];
}

function jm_backup_resolve_file(string $filename): string {
    $filename = basename($filename);
    if (!preg_match('/^jobmington-\d{8}-\d{6}-(db\.sql|uploads\.zip|manifest\.json)$/', $filename)) {
        throw new RuntimeException('Invalid backup file.');
    }

    $path = jm_backup_dir() . DIRECTORY_SEPARATOR . $filename;
    if (!is_file($path) || !is_readable($path)) {
        throw new RuntimeException('Backup file not found.');
    }

    return $path;
}
