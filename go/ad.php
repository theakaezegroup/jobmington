<?php
/**
 * JOBMINGTON - Header ad click-through.
 *
 * Counts the click, then forwards to the advertiser. The destination comes from
 * the database, never from the query string, so this cannot be used as an open
 * redirect: the only thing a visitor controls is which ad id to look up.
 */
define('JOBMINGTON', true);
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$adId = (int) ($_GET['id'] ?? 0);
$fallback = '/jobmington/';

if ($adId > 0) {
    try {
        $pdo  = db();
        $stmt = $pdo->prepare("SELECT click_url FROM header_ads WHERE ad_id = ? LIMIT 1");
        $stmt->execute([$adId]);
        $url = (string) ($stmt->fetchColumn() ?: '');

        if ($url !== '' && preg_match('#^https?://#i', $url) && filter_var($url, FILTER_VALIDATE_URL)) {
            $pdo->prepare("UPDATE header_ads SET clicks = clicks + 1 WHERE ad_id = ?")->execute([$adId]);
            header('Location: ' . $url, true, 302);
            exit;
        }
    } catch (Throwable $e) {
        error_log('Header ad click failed for id ' . $adId . ': ' . $e->getMessage());
    }
}

header('Location: ' . $fallback, true, 302);
exit;
