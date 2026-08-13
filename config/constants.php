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
if (!empty(getenv('APP_URL'))) {
    define('SITE_URL', rtrim(getenv('APP_URL'), '/'));
} else {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    if ($host === 'localhost' || $host === '127.0.0.1' || strpos($host, 'jobmington.local') !== false) {
        define('SITE_URL', $protocol . '://' . $host . '/jobmington');
    } else {
        define('SITE_URL', $protocol . '://' . $host);
    }
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

/*
 * ── Monetization ──────────────────────────────────────────────────────────────
 *
 * Every plan has two set prices, and both of them are deliberate.
 *
 *   PRICE_*      Naira. What Paystack actually charges, because Paystack takes
 *                Naira. Kept round so no Nigerian is ever repriced by a
 *                currency market moving overnight.
 *
 *   USD_PRICE_*  Dollars. What the page leads with, everywhere, because
 *                Jobmington is an African platform and a page that opens in
 *                Naira says otherwise to a Kenyan before they read a word.
 *
 * They are set separately rather than one derived from the other, so neither
 * drifts. Every other currency a visitor might see is converted from the
 * dollar price at the day's rate and labelled approximate, which is what it is.
 *
 * Change one and consider the other: they are a pair, and at today's rate of
 * about 1,360 they line up. They will drift apart as the Naira moves, which is
 * a pricing decision for a person to make rather than something to automate.
 */
define('PRICE_EMPLOYER_SINGLE_POST',      (int)(getenv('PRICE_EMPLOYER_SINGLE_POST')      ?: 30000));  // ₦30,000
define('PRICE_EMPLOYER_FEATURED_ADDON',   (int)(getenv('PRICE_EMPLOYER_FEATURED_ADDON')   ?: 3000));   // ₦3,000
define('PRICE_EMPLOYER_BASIC_MONTHLY',    (int)(getenv('PRICE_EMPLOYER_BASIC_MONTHLY')    ?: 7500));   // ₦7,500/mo
define('PRICE_EMPLOYER_PRO_MONTHLY',      (int)(getenv('PRICE_EMPLOYER_PRO_MONTHLY')      ?: 15000));  // ₦15,000/mo
define('EMPLOYER_BASIC_POST_LIMIT',       (int)(getenv('EMPLOYER_BASIC_POST_LIMIT')       ?: 3));

// ── Monetization: Seeker ──────────────────────────────────────────────────────
define('PRICE_SEEKER_PREMIUM_MONTHLY',    (int)(getenv('PRICE_SEEKER_PREMIUM_MONTHLY')    ?: 3000));   // ₦3,000/mo
define('PRICE_SEEKER_PREMIUM_ANNUAL',     (int)(getenv('PRICE_SEEKER_PREMIUM_ANNUAL')     ?: 30000));  // ₦30,000/yr (2 months free)

// ── Monetization: Credit Packs ────────────────────────────────────────────────
define('PRICE_CREDITS_SINGLE',            (int)(getenv('PRICE_CREDITS_SINGLE')            ?: 500));    // ₦500 × 1
define('PRICE_CREDITS_PACK_5',            (int)(getenv('PRICE_CREDITS_PACK_5')            ?: 2000));   // ₦2,000 × 5
define('PRICE_CREDITS_PACK_10',           (int)(getenv('PRICE_CREDITS_PACK_10')           ?: 3000));   // ₦3,000 × 10

// ── The dollar price of each plan. This is the figure the page leads with. ────
define('USD_PRICE_EMPLOYER_SINGLE_POST',    (float)(getenv('USD_PRICE_EMPLOYER_SINGLE_POST')    ?: 22));
define('USD_PRICE_EMPLOYER_FEATURED_ADDON', (float)(getenv('USD_PRICE_EMPLOYER_FEATURED_ADDON') ?: 2));
define('USD_PRICE_EMPLOYER_BASIC_MONTHLY',  (float)(getenv('USD_PRICE_EMPLOYER_BASIC_MONTHLY')  ?: 5.5));
define('USD_PRICE_EMPLOYER_PRO_MONTHLY',    (float)(getenv('USD_PRICE_EMPLOYER_PRO_MONTHLY')    ?: 11));
define('USD_PRICE_SEEKER_PREMIUM_MONTHLY',  (float)(getenv('USD_PRICE_SEEKER_PREMIUM_MONTHLY')  ?: 2));
define('USD_PRICE_SEEKER_PREMIUM_ANNUAL',   (float)(getenv('USD_PRICE_SEEKER_PREMIUM_ANNUAL')   ?: 22));
define('USD_PRICE_CREDITS_SINGLE',          (float)(getenv('USD_PRICE_CREDITS_SINGLE')          ?: 0.5));
define('USD_PRICE_CREDITS_PACK_5',          (float)(getenv('USD_PRICE_CREDITS_PACK_5')          ?: 1.5));
define('USD_PRICE_CREDITS_PACK_10',         (float)(getenv('USD_PRICE_CREDITS_PACK_10')         ?: 2));
define('USD_PRICE_BUNDLE_JOB_TOOLKIT',      (float)(getenv('USD_PRICE_BUNDLE_JOB_TOOLKIT')      ?: 1));

// ── Economy: Seeds <-> Credits bridge ─────────────────────────────────────────
// Seeds are the free, earned engagement currency. Credits are the paid currency
// for premium AI tools. Users can redeem earned Seeds into Credits at this rate.
define('SEEDS_PER_CREDIT',                (int)(getenv('SEEDS_PER_CREDIT')                ?: 100));   // 100 Seeds = 1 Credit (Seeds -> Credits)
// Reverse bridge (Credits -> Seeds) carries a spread to discourage round-trip gaming.
define('SEEDS_PER_CREDIT_REVERSE',        (int)(getenv('SEEDS_PER_CREDIT_REVERSE')        ?: 80));    // 1 Credit = 80 Seeds

// ── Monetization: premium content (Seeds / Credits) ───────────────────────────
// Certificate issuance for FREE courses (paid courses include the certificate).
define('PRICE_CERT_CREDITS',              (int)(getenv('PRICE_CERT_CREDITS')              ?: 2));     // 2 Credits
define('PRICE_CERT_SEEDS',                (int)(getenv('PRICE_CERT_SEEDS')                ?: 150));   // or 150 Seeds
// (legacy aliases kept for any older references)
define('PRICE_PREMIUM_CERT_CREDITS',      (int)(getenv('PRICE_PREMIUM_CERT_CREDITS')      ?: 2));
define('PRICE_PREMIUM_CERT_SEEDS',        (int)(getenv('PRICE_PREMIUM_CERT_SEEDS')        ?: 200));
// Talent Passport visibility boost (7 days)
define('PRICE_PASSPORT_BOOST_SEEDS',      (int)(getenv('PRICE_PASSPORT_BOOST_SEEDS')      ?: 200));   // 200 Seeds / week

// ── Monetization: Per-Tool (credits) ─────────────────────────────────────────
define('TOOL_COST_CV_OPTIMIZER',          (int)(getenv('TOOL_COST_CV_OPTIMIZER')          ?: 1));
define('TOOL_COST_COVER_LETTER',          (int)(getenv('TOOL_COST_COVER_LETTER')          ?: 1));
define('TOOL_COST_COLD_PITCH',            (int)(getenv('TOOL_COST_COLD_PITCH')            ?: 1));
define('TOOL_COST_INTERVIEW_PREP',        (int)(getenv('TOOL_COST_INTERVIEW_PREP')        ?: 2));  // ₦800 equiv
define('TOOL_COST_SKILLS_GAP_REPORT',     (int)(getenv('TOOL_COST_SKILLS_GAP_REPORT')     ?: 1));  // ₦300 equiv

// ── Monetization: Bundles ─────────────────────────────────────────────────────
define('PRICE_BUNDLE_JOB_TOOLKIT',        (int)(getenv('PRICE_BUNDLE_JOB_TOOLKIT')        ?: 1500));   // ₦1,500

// ── Exchange rate (NGN per 1 USD) — update via env var as rate changes ────────
// CBN mid-market rate, 2026: ~1,600 NGN/USD
define('NGN_USD_RATE', (int)(getenv('NGN_USD_RATE') ?: 1600));

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