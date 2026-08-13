<?php
/**
 * The CV builder's own tables, moved into the migration system where they
 * belong.
 *
 * They were created by cv-builder/setup-database.php, a script that lived in a
 * public directory, answered 200 to anybody on the internet, and ran ALTER
 * TABLE and CREATE TABLE with no authentication of any kind. Not an admin
 * check, not a signed-in check, not even the JOBMINGTON constant guard every
 * other file in that folder has. Anyone who found the URL could make the site
 * attempt schema changes on the production database, repeatedly.
 *
 * The schema it made is fine and is already live, so nothing here changes a
 * working database. This exists so the schema has an owner that is not a public
 * URL, and so a fresh install gets it without anyone visiting a page to make
 * tables. The script is deleted in the same commit.
 *
 * Definitions are taken from production rather than retyped, so this is what is
 * actually there.
 */

return function (PDO $pdo) {
    $tables = [
        'cv_projects' => "
            id int(11) NOT NULL AUTO_INCREMENT,
            cv_id int(11) NOT NULL,
            name varchar(200) NOT NULL,
            role varchar(150) DEFAULT NULL,
            url varchar(500) DEFAULT NULL,
            start_date date DEFAULT NULL,
            end_date date DEFAULT NULL,
            is_current tinyint(1) DEFAULT 0,
            description text DEFAULT NULL,
            technologies text DEFAULT NULL,
            sort_order int(11) DEFAULT 0,
            PRIMARY KEY (id), KEY idx_cv_id (cv_id)",

        'cv_certifications' => "
            id int(11) NOT NULL AUTO_INCREMENT,
            cv_id int(11) NOT NULL,
            name varchar(200) NOT NULL,
            issuing_organization varchar(200) DEFAULT NULL,
            issue_date date DEFAULT NULL,
            expiry_date date DEFAULT NULL,
            credential_id varchar(100) DEFAULT NULL,
            credential_url varchar(500) DEFAULT NULL,
            sort_order int(11) DEFAULT 0,
            PRIMARY KEY (id), KEY idx_cv_id (cv_id)",

        'cv_languages' => "
            id int(11) NOT NULL AUTO_INCREMENT,
            cv_id int(11) NOT NULL,
            language varchar(50) NOT NULL,
            proficiency enum('basic','conversational','professional','fluent','native') DEFAULT 'professional',
            sort_order int(11) DEFAULT 0,
            PRIMARY KEY (id), KEY idx_cv_id (cv_id)",

        'cv_awards' => "
            id int(11) NOT NULL AUTO_INCREMENT,
            cv_id int(11) NOT NULL,
            title varchar(200) NOT NULL,
            issuer varchar(200) DEFAULT NULL,
            date_received date DEFAULT NULL,
            description text DEFAULT NULL,
            sort_order int(11) DEFAULT 0,
            PRIMARY KEY (id), KEY idx_cv_id (cv_id)",

        'cv_volunteer' => "
            id int(11) NOT NULL AUTO_INCREMENT,
            cv_id int(11) NOT NULL,
            role varchar(200) NOT NULL,
            organization varchar(200) NOT NULL,
            cause varchar(100) DEFAULT NULL,
            start_date date DEFAULT NULL,
            end_date date DEFAULT NULL,
            is_current tinyint(1) DEFAULT 0,
            description text DEFAULT NULL,
            sort_order int(11) DEFAULT 0,
            PRIMARY KEY (id), KEY idx_cv_id (cv_id)",

        'cv_references' => "
            id int(11) NOT NULL AUTO_INCREMENT,
            cv_id int(11) NOT NULL,
            name varchar(150) NOT NULL,
            job_title varchar(150) DEFAULT NULL,
            company varchar(150) DEFAULT NULL,
            email varchar(150) DEFAULT NULL,
            phone varchar(50) DEFAULT NULL,
            relationship varchar(100) DEFAULT NULL,
            sort_order int(11) DEFAULT 0,
            PRIMARY KEY (id), KEY idx_cv_id (cv_id)",
    ];

    $made = 0;
    foreach ($tables as $name => $definition) {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `{$name}` ({$definition})
                    ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
        $made++;
    }
    echo "  {$made} CV tables confirmed\n";

    // The columns the same script added to cv_profiles.
    $existing = [];
    foreach ($pdo->query('SHOW COLUMNS FROM cv_profiles') as $row) {
        $existing[] = $row['Field'];
    }

    $columns = [
        'headline'      => 'VARCHAR(255) DEFAULT NULL',
        'linkedin_url'  => 'VARCHAR(500) DEFAULT NULL',
        'portfolio_url' => 'VARCHAR(500) DEFAULT NULL',
        'github_url'    => 'VARCHAR(500) DEFAULT NULL',
        'location'      => 'VARCHAR(255) DEFAULT NULL',
    ];

    foreach ($columns as $column => $definition) {
        if (!in_array($column, $existing, true)) {
            $pdo->exec("ALTER TABLE cv_profiles ADD COLUMN `{$column}` {$definition}");
            echo "  cv_profiles.{$column} added\n";
        }
    }
};
