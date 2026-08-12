<?php
/**
 * JOBMINGTON - Logout
 */

define('JOBMINGTON', true);
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/remember.php';

Session::start();

// Revoke this device's persistent login before the session goes away, or the
// next request would silently sign the user straight back in.
$userId = Session::isLoggedIn() ? (int) Session::userId() : null;
jm_log_activity($userId, 'logout');
jm_remember_revoke(db(), $userId);

// Signing out is an explicit "I am done here", which on a shared device also
// means do not greet the next person by this person's name.
jm_forget_visitor();

Session::destroy();

// Redirect to public home
header('Location: /jobmington/');
exit;
