<?php
define('JOBMINGTON', true);
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';

Session::start();
Session::requireLogin();

if (Session::isEmployer() && !Session::isAdmin()) {
    redirect('/jobmington/employer/company-profile.php');
}

if (Session::isAdmin()) {
    redirect('/jobmington/admin/users.php');
}

redirect('/jobmington/seeker/profile.php');
