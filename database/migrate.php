<?php
/**
 * JOBMINGTON - Database Migration Runner
 *
 * Ordered, idempotent schema migrations. Each migration lives in
 * database/migrations/schema/NNNN_name.php and returns a closure:
 *
 *     <?php
 *     return function (PDO $pdo): void { ... };
 *
 * Applied migrations are tracked in the `schema_migrations` table so each
 * runs exactly once. Migrations should also be internally guarded
 * (jm_mig_has_table / jm_mig_has_column) so a first run is safe on databases
 * whose columns were created by the older inline runtime checks.
 *
 * Usage (CLI only):
 *     php database/migrate.php            # apply pending migrations
 *     php database/migrate.php --status   # list applied / pending
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Migrations can only be run from the command line.');
}

define('JOBMINGTON', true);
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';

$pdo = db();

/* ── Migration helpers (available to migration closures) ─────────── */
function jm_mig_has_table(PDO $pdo, string $table): bool {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
    ");
    $stmt->execute([$table]);
    return (int) $stmt->fetchColumn() > 0;
}

function jm_mig_has_column(PDO $pdo, string $table, string $column): bool {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?
    ");
    $stmt->execute([$table, $column]);
    return (int) $stmt->fetchColumn() > 0;
}

function jm_mig_add_column(PDO $pdo, string $table, string $column, string $definition): void {
    if (jm_mig_has_table($pdo, $table) && !jm_mig_has_column($pdo, $table, $column)) {
        $pdo->exec("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
    }
}

/* ── Tracking table ──────────────────────────────────────────────── */
$pdo->exec("
    CREATE TABLE IF NOT EXISTS schema_migrations (
        version    VARCHAR(255) NOT NULL PRIMARY KEY,
        applied_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

$applied = $pdo->query("SELECT version FROM schema_migrations")->fetchAll(PDO::FETCH_COLUMN) ?: [];
$applied = array_flip($applied);

$files = glob(__DIR__ . '/migrations/schema/*.php') ?: [];
sort($files);

/* ── --status mode ───────────────────────────────────────────────── */
if (in_array('--status', $argv, true)) {
    echo "Migration status:\n";
    foreach ($files as $file) {
        $version = basename($file, '.php');
        echo (isset($applied[$version]) ? '  [x] ' : '  [ ] ') . $version . "\n";
    }
    exit(0);
}

/* ── Apply pending migrations ────────────────────────────────────── */
$ran = 0;
foreach ($files as $file) {
    $version = basename($file, '.php');
    if (isset($applied[$version])) {
        continue;
    }

    $migration = require $file;
    if (!is_callable($migration)) {
        fwrite(STDERR, "  ! {$version} did not return a callable — skipping\n");
        continue;
    }

    try {
        $migration($pdo);
        $pdo->prepare("INSERT INTO schema_migrations (version) VALUES (?)")->execute([$version]);
        echo "  [applied] {$version}\n";
        $ran++;
    } catch (Throwable $e) {
        fwrite(STDERR, "  [FAILED] {$version}: " . $e->getMessage() . "\n");
        exit(1);
    }
}

echo $ran === 0 ? "Nothing to migrate — database is up to date.\n" : "Done. Applied {$ran} migration(s).\n";
