<?php
/**
 * JOBMINGTON - CV Preview
 * Redirects to the export/preview page
 */

define('JOBMINGTON', true);
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/tools.php';

Session::start();
Session::requireLogin();
jm_require_tool('cv_builder');

$cvId = (int)($_GET['id'] ?? 0);

if ($cvId > 0) {
    header('Location: ' . SITE_URL . '/cv-builder/export-complete.php?id=' . $cvId);
} else {
    header('Location: ' . SITE_URL . '/cv-builder/');
}
exit;
