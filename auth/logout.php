<?php
/**
 * JOBMINGTON - Logout
 */

define('JOBMINGTON', true);
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/session.php';

Session::start();
Session::destroy();

// Redirect to public home
header('Location: /jobmington/');
exit;