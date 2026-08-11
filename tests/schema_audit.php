<?php
/**
 * Every INSERT and UPDATE in the codebase, checked against the real columns.
 *
 * Written after a profile save failed silently: the code named a column the
 * table did not have, the statement threw, and a catch swallowed it. Guessing
 * one column at a time kept missing the others, so this reads the live schema
 * and compares. Returns a list of [file, kind, table, column] mismatches.
 */

if (!defined('JOBMINGTON')) {
    http_response_code(403);
    exit('Not a standalone script');
}

function jm_schema_audit(PDO $pdo, string $root): array
{
    $schema = [];
    foreach ($pdo->query("SHOW TABLES") as $row) {
        $table = array_values($row)[0];
        foreach ($pdo->query("SHOW COLUMNS FROM `{$table}`") as $col) {
            $schema[$table][] = $col['Field'];
        }
    }

    // Migrations are excluded: they legitimately name columns that do not
    // exist yet, which is the entire point of them.
    $skip = ['/vendor/', '/node_modules/', '/database/migrations/', '/.git/', '/backups/'];
    $issues = [];
    $seen = [];

    $record = static function (string $rel, string $kind, string $table, string $col) use (&$issues, &$seen): void {
        $key = "{$rel}|{$kind}|{$table}|{$col}";
        if (isset($seen[$key])) {
            return;
        }
        $seen[$key] = true;
        $issues[] = [$rel, $kind, $table, $col];
    };

    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
    foreach ($files as $file) {
        if (!$file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }
        $path = str_replace(DIRECTORY_SEPARATOR, '/', $file->getPathname());
        foreach ($skip as $fragment) {
            if (strpos($path, $fragment) !== false) {
                continue 2;
            }
        }

        $src = file_get_contents($path);
        $rel = str_replace($root . '/', '', $path);

        // UPDATE <table> SET a = ?, b = ?
        if (preg_match_all('/UPDATE\s+`?([a-z_]+)`?\s+SET\s+(.*?)(?:WHERE|$)/is', $src, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $hit) {
                $table = $hit[1];
                if (!isset($schema[$table])) {
                    continue;
                }
                preg_match_all('/([a-z_]+)\s*=\s*[?\'"a-zA-Z0-9_()]/i', $hit[2], $cols);
                foreach (array_unique($cols[1]) as $col) {
                    if (in_array(strtoupper($col), ['NOW', 'NULL', 'SET', 'COALESCE'], true)) {
                        continue;
                    }
                    if (!in_array($col, $schema[$table], true)) {
                        $record($rel, 'UPDATE', $table, $col);
                    }
                }
            }
        }

        // INSERT INTO <table> (a, b, c)
        if (preg_match_all('/INSERT\s+(?:IGNORE\s+)?INTO\s+`?([a-z_]+)`?\s*\(([^)]*)\)/is', $src, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $hit) {
                $table = $hit[1];
                if (!isset($schema[$table])) {
                    continue;
                }
                foreach (explode(',', $hit[2]) as $raw) {
                    $col = trim(str_replace(['`', "\n", "\r"], '', $raw));
                    if ($col === '' || !preg_match('/^[a-z_]+$/i', $col)) {
                        continue;
                    }
                    if (!in_array($col, $schema[$table], true)) {
                        $record($rel, 'INSERT', $table, $col);
                    }
                }
            }
        }
    }

    usort($issues, static fn($a, $b) => [$a[0], $a[3]] <=> [$b[0], $b[3]]);

    return $issues;
}
