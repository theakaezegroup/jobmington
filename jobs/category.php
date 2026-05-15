<?php
define('JOBMINGTON', true);
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';

Session::start();

$slug = Security::clean(get('slug', ''));
if ($slug === '') {
    redirect('/jobmington/jobs/');
}

redirect('/jobmington/jobs/?category=' . urlencode($slug));
