<?php
/**
 * JOBMINGTON - Ebook download (counts + serves the file)
 */
define('JOBMINGTON', true);
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/seeds.php';

Session::start();
$pdo = db();

$slug = trim((string) get('slug', ''));
$stmt = $pdo->prepare("SELECT * FROM ebooks WHERE slug = ? AND is_published = 1 LIMIT 1");
$stmt->execute([$slug]);
$ebook = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$ebook || empty($ebook['file_path'])) {
    http_response_code(404);
    redirect('/jobmington/ebooks/');
}

// Ebook downloads are members-only (the listing + detail pages stay public for SEO).
if (!Session::isLoggedIn()) {
    redirect('/jobmington/auth/login.php?redirect=' . urlencode('/jobmington/ebooks/view.php?slug=' . $ebook['slug']));
}

// Premium ebooks require a purchase (Seeds / Credits / Naira) before download.
if (!jm_ebook_has_access($pdo, (int) Session::userId(), $ebook)) {
    redirect('/jobmington/ebooks/view.php?slug=' . $ebook['slug'] . '&unlock=1');
}

// Resolve the file path on disk (file_path is stored as a web path under /jobmington/uploads/...).
$relative = preg_replace('#^/jobmington/#', '', $ebook['file_path']);
$diskPath = dirname(__DIR__) . '/' . ltrim($relative, '/');

if (!is_file($diskPath)) {
    http_response_code(404);
    redirect('/jobmington/ebooks/view.php?slug=' . $ebook['slug']);
}

// Count the download.
try {
    $pdo->prepare("UPDATE ebooks SET download_count = download_count + 1 WHERE ebook_id = ?")->execute([(int) $ebook['ebook_id']]);
} catch (Throwable $e) {}

$ext = strtolower(pathinfo($diskPath, PATHINFO_EXTENSION));
$mime = ['pdf' => 'application/pdf', 'epub' => 'application/epub+zip', 'zip' => 'application/zip'][$ext] ?? 'application/octet-stream';
$downloadName = preg_replace('/[^a-zA-Z0-9._-]+/', '-', $ebook['title']) . '.' . $ext;

header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . $downloadName . '"');
header('Content-Length: ' . filesize($diskPath));
header('Cache-Control: private');
readfile($diskPath);
exit;
