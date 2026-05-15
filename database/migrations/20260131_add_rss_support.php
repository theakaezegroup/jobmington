<?php
/**
 * Migration: Add RSS Feed Support to Jobs
 * Adds guid, source, and original_location columns
 */

define('JOBMINGTON', true);
require_once __DIR__ . '/../../config/env.php';
require_once __DIR__ . '/../../config/database.php';

$pdo = db();

try {
    echo " Altering 'jobs' table for RSS support...\n";
    
    $pdo->exec("
        ALTER TABLE jobs 
        ADD COLUMN guid VARCHAR(255) DEFAULT NULL AFTER job_id,
        ADD COLUMN source VARCHAR(50) DEFAULT 'Jobmington' AFTER guid,
        ADD COLUMN original_location VARCHAR(255) DEFAULT NULL AFTER city,
        ADD UNIQUE INDEX idx_job_guid (guid)
    ");
    
    echo " Success: Jobs table updated.\n";
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "ℹ Note: Columns already exist.\n";
    } else {
        echo " Error: " . $e->getMessage() . "\n";
    }
}
