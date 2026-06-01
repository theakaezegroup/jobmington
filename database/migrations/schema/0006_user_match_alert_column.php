<?php
/**
 * Tracks when a seeker was last sent a job-match alert, so the alert cron
 * doesn't email the same person repeatedly.
 */
return function (PDO $pdo): void {
    jm_mig_add_column($pdo, 'users', 'last_job_match_alert', 'DATETIME DEFAULT NULL');
};
