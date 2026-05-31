<?php
/**
 * JOBMINGTON - Site Constants
 * Define all site-wide constants here
 */

// Prevent direct access
if (!defined('JOBMINGTON')) {
    die('Direct access not permitted');
}

// Site Information
define('SITE_NAME', 'Jobmington');
define('SITE_TAGLINE', 'Preparing Africa\'s Workforce for the Future');
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
if ($host === 'localhost' || $host === '127.0.0.1' || strpos($host, 'jobmington.local') !== false) {
    define('SITE_URL', $protocol . '://' . $host . '/jobmington');
} else {
    define('SITE_URL', $protocol . '://' . $host);
}
define('SITE_EMAIL', 'hello@jobmington.com');
define('SITE_PHONE', '+234 800 000 0000');

// Branding Colors
define('COLOR_PRIMARY', '#0d47a1');
define('COLOR_SECONDARY', '#ff9800');
define('COLOR_SUCCESS', '#10b981');
define('COLOR_DANGER', '#ef4444');
define('COLOR_WARNING', '#f59e0b');

// Paths
define('ROOT_PATH', dirname(__DIR__));
define('UPLOADS_PATH', ROOT_PATH . '/uploads');
define('LIBS_PATH', ROOT_PATH . '/libs');
define('ASSETS_URL', SITE_URL . '/assets');
define('UPLOADS_URL', SITE_URL . '/uploads');

// Upload Limits
define('MAX_AVATAR_SIZE', 2 * 1024 * 1024); // 2MB
define('MAX_CV_SIZE', 5 * 1024 * 1024); // 5MB
define('MAX_IMAGE_SIZE', 5 * 1024 * 1024); // 5MB

// Pagination
define('JOBS_PER_PAGE', 12);
define('COURSES_PER_PAGE', 9);
define('BLOG_PER_PAGE', 10);
define('FORUM_PER_PAGE', 20);

// Session Settings
define('SESSION_LIFETIME', 1800); // 30 minutes
define('REMEMBER_ME_LIFETIME', 30 * 24 * 60 * 60); // 30 days

// Security
define('CSRF_TOKEN_NAME', 'csrf_token');
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 900); // 15 minutes

// API Rate Limiting
define('API_RATE_LIMIT', 100); // requests
define('API_RATE_WINDOW', 3600); // per hour

// Certificate Settings
define('CERT_PREFIX', 'JMT');
define('CERT_PASSING_SCORE', 70);

// Andika AI
define('ANDIKA_MODEL', 'meta-llama/llama-3.2-3b-instruct:free');
define('ANDIKA_MAX_TOKENS', 1024);

// Default Country
define('DEFAULT_COUNTRY_CODE', 'ng');
define('DEFAULT_COUNTRY_NAME', 'Nigeria');

// User Types
define('USER_TYPE_SEEKER', 'seeker');
define('USER_TYPE_EMPLOYER', 'employer');
define('USER_TYPE_ADMIN', 'admin');

// Job Types
define('JOB_TYPES', ['Full-time', 'Part-time', 'Contract', 'Remote', 'Hybrid', 'Internship']);

// Experience Levels
define('EXPERIENCE_LEVELS', ['Entry', 'Mid', 'Senior', 'Executive']);

// Badge Levels
define('BADGE_LEVELS', ['bronze', 'silver', 'gold', 'platinum']);

// Application Statuses
define('APPLICATION_STATUSES', ['pending', 'reviewed', 'shortlisted', 'interview', 'rejected', 'hired']);

// Course Difficulty
define('DIFFICULTY_LEVELS', ['Beginner', 'Intermediate', 'Advanced']);
?>