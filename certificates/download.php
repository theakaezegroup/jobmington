<?php
/**
 * JOBMINGTON - Certificate download (print-optimised, A4 landscape -> PDF)
 */
define('JOBMINGTON', true);
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';

Session::start();
$pdo = db();
$certCode = Security::clean(get('code', ''));
if (empty($certCode)) redirect('/jobmington/certificates/');

$stmt = $pdo->prepare("
    SELECT cert.*, c.title AS course_title, u.full_name, u.email
    FROM certificates cert
    JOIN courses c ON cert.course_id = c.course_id
    LEFT JOIN users u ON cert.user_id = u.user_id
    WHERE cert.cert_code = ? LIMIT 1
");
$stmt->execute([$certCode]);
$cert = $stmt->fetch();
if (!$cert) { http_response_code(404); exit('Certificate not found.'); }
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Certificate — <?= e($cert['course_title']) ?> — <?= e($cert['full_name'] ?: 'Jobmington') ?></title>
<style>
    * { box-sizing:border-box; }
    body { margin:0; background:#0b1b33; font-family:"Futura Cyrillic Demi", Arial, sans-serif; padding:34px 16px 90px; }
    .jm-dl-bar {
        position:fixed; top:0; left:0; right:0; z-index:20;
        display:flex; align-items:center; justify-content:center; gap:10px;
        padding:12px; background:rgba(11,27,51,.92);
    }
    .jm-dl-bar a, .jm-dl-bar button {
        display:inline-flex; align-items:center; gap:8px; border-radius:9px; padding:10px 18px;
        font:inherit; font-size:13.5px; font-weight:800; text-decoration:none; cursor:pointer; border:0;
    }
    .jm-dl-print { background:#fff; color:#0640a3; }
    .jm-dl-print:hover { background:#eef5ff; }
    .jm-dl-back { background:rgba(255,255,255,.12); color:#fff; }
    .jm-dl-stage { max-width:1000px; margin:46px auto 0; }
    @media print {
        @page { size:A4 landscape; margin:0; }
        html, body { margin:0; padding:0; background:#fff; width:297mm; height:210mm; overflow:hidden; }
        .jm-dl-bar { display:none; }
        .jm-dl-stage { margin:0; padding:0; max-width:none; }
        .jm-cert-stage { max-width:none !important; width:297mm !important; }
        .jm-cert {
            width:297mm !important; height:210mm !important;
            aspect-ratio:auto !important; border:0 !important;
            -webkit-print-color-adjust:exact; print-color-adjust:exact;
            break-inside:avoid; page-break-inside:avoid;
        }
    }
</style>
</head>
<body>
    <div class="jm-dl-bar">
        <button class="jm-dl-print" type="button" onclick="window.print()">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
            Print / Save as PDF
        </button>
        <a class="jm-dl-back" href="/jobmington/certificates/view.php?code=<?= e($cert['cert_code']) ?>">Back</a>
    </div>
    <div class="jm-dl-stage">
        <?php require __DIR__ . '/_cert_template.php'; ?>
    </div>
</body>
</html>
