<?php
/**
 * Add the columns the application already writes to.
 *
 * A schema audit of every INSERT and UPDATE against the live database found
 * writers naming columns that do not exist. Some were renames handled in code;
 * these are the ones that are genuinely absent, so the feature cannot work
 * without them.
 *
 * Every change is additive with a default, so it is safe against the 30k jobs
 * and 11k companies already in place: no data is moved and nothing is dropped.
 *
 * Deliberately excluded: seed_transactions.reference, used only by the Paystack
 * webhook, which is not wired up yet.
 */
return function (PDO $pdo): void {

    $addColumn = static function (PDO $pdo, string $table, string $column, string $definition): void {
        $exists = $pdo->query("SHOW COLUMNS FROM `{$table}` LIKE " . $pdo->quote($column))->fetchAll();
        if (!$exists) {
            $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
        }
    };

    /* ── jobs ──────────────────────────────────────────────────────────
       deadline and application_url are not here: they map onto the existing
       expires_at and apply_link, and are handled in code. */
    $addColumn($pdo, 'jobs', 'slug',              'VARCHAR(255) DEFAULT NULL');
    $addColumn($pdo, 'jobs', 'benefits',          'TEXT DEFAULT NULL');
    $addColumn($pdo, 'jobs', 'experience_level',  "VARCHAR(50) DEFAULT NULL");
    $addColumn($pdo, 'jobs', 'salary_currency',   "VARCHAR(10) DEFAULT NULL");
    $addColumn($pdo, 'jobs', 'show_salary',       'TINYINT(1) NOT NULL DEFAULT 1');
    $addColumn($pdo, 'jobs', 'application_email', 'VARCHAR(255) DEFAULT NULL');

    // Non-unique: 30k rows already exist with no slug, and scraped listings can
    // repeat a title. Uniqueness would fail the migration outright.
    $hasSlugIdx = $pdo->query("SHOW INDEX FROM jobs WHERE Key_name = 'idx_job_slug'")->fetchAll();
    if (!$hasSlugIdx) {
        $pdo->exec("ALTER TABLE jobs ADD KEY idx_job_slug (slug)");
    }

    /* ── companies ─────────────────────────────────────────────────────
       company_size and city map onto the existing size and location. */
    $addColumn($pdo, 'companies', 'address',     'VARCHAR(255) DEFAULT NULL');
    $addColumn($pdo, 'companies', 'country_id',  'INT DEFAULT NULL');
    $addColumn($pdo, 'companies', 'cover_image', 'VARCHAR(500) DEFAULT NULL');
    $addColumn($pdo, 'companies', 'updated_at',  'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');

    /* ── quizzes ───────────────────────────────────────────────────────── */
    $addColumn($pdo, 'quizzes', 'description', 'TEXT DEFAULT NULL');
    $addColumn($pdo, 'quizzes', 'time_limit',  'INT DEFAULT NULL');
    $addColumn($pdo, 'quizzes', 'created_at',  'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP');

    /* ── passport endorsements ─────────────────────────────────────────── */
    $addColumn($pdo, 'passport_endorsements', 'work_duration', 'VARCHAR(100) DEFAULT NULL');
};
