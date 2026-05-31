<?php
define('JOBMINGTON', true);
require_once __DIR__ . '/config/env.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/monetization.php';

Session::start();
$pdo = db();

$stats = [
    'Open jobs' => 0,
    'Hiring companies' => 0,
    'Remote roles' => 0,
    'Categories' => 0,
];

try {
    $stats['Open jobs'] = (int) $pdo->query("SELECT COUNT(*) FROM jobs WHERE is_active = 1 AND (expires_at IS NULL OR expires_at >= CURDATE())")->fetchColumn();
    $stats['Hiring companies'] = (int) $pdo->query("SELECT COUNT(DISTINCT company_id) FROM jobs WHERE is_active = 1 AND (expires_at IS NULL OR expires_at >= CURDATE())")->fetchColumn();
    $stats['Remote roles'] = (int) $pdo->query("SELECT COUNT(*) FROM jobs WHERE is_active = 1 AND job_type = 'Remote' AND (expires_at IS NULL OR expires_at >= CURDATE())")->fetchColumn();
    $stats['Categories'] = (int) $pdo->query("SELECT COUNT(DISTINCT category_id) FROM jobs WHERE is_active = 1 AND category_id IS NOT NULL AND (expires_at IS NULL OR expires_at >= CURDATE())")->fetchColumn();
} catch (Throwable $e) {
    $stats = ['Open jobs' => 0, 'Hiring companies' => 0, 'Remote roles' => 0, 'Categories' => 0];
}

$featuredJobs = [];
try {
    $stmt = $pdo->query("
        SELECT j.*, co.name AS company_name, c.name AS country_name, c.currency_symbol, jc.name AS category_name
        FROM jobs j
        JOIN companies co ON j.company_id = co.company_id
        LEFT JOIN countries c ON j.country_id = c.country_id
        LEFT JOIN job_categories jc ON j.category_id = jc.category_id
        WHERE j.is_active = 1
          AND (j.expires_at IS NULL OR j.expires_at >= CURDATE())
        ORDER BY j.is_featured DESC, j.posted_at DESC
        LIMIT 6
    ");
    $featuredJobs = $stmt->fetchAll();
} catch (Throwable $e) {
    $featuredJobs = [];
}

$countries = [];
try {
    $countries = $pdo->query("
        SELECT DISTINCT c.country_id, c.name
        FROM countries c
        JOIN jobs j ON c.country_id = j.country_id
        WHERE c.is_active = 1
          AND j.is_active = 1
          AND (j.expires_at IS NULL OR j.expires_at >= CURDATE())
        ORDER BY c.name
    ")->fetchAll();
} catch (Throwable $e) {
    $countries = [];
}

$categories = [];
try {
    $categories = $pdo->query("
        SELECT jc.category_id, jc.name, jc.slug, COUNT(j.job_id) AS live_jobs
        FROM job_categories jc
        LEFT JOIN jobs j ON jc.category_id = j.category_id
            AND j.is_active = 1
            AND (j.expires_at IS NULL OR j.expires_at >= CURDATE())
        GROUP BY jc.category_id, jc.name, jc.slug
        HAVING live_jobs > 0
        ORDER BY live_jobs DESC, jc.name ASC
        LIMIT 6
    ")->fetchAll();
} catch (Throwable $e) {
    $categories = [];
}

$tickerSponsors = [
    ["name"=>"Flutterwave",  "logo"=>"flutterwave.png"],
    ["name"=>"Paystack",     "logo"=>"paystack.png"],
    ["name"=>"MTN",          "logo"=>"mtn.svg"],
    ["name"=>"GTBank",       "logo"=>"gtbank.svg"],
    ["name"=>"Jumia",        "logo"=>"jumia.png"],
    ["name"=>"Equity Bank",  "logo"=>"equity.png"],
    ["name"=>"Access Bank",  "logo"=>"accessbank.png"],
    ["name"=>"Interswitch",  "logo"=>"interswitch.svg"],
    ["name"=>"Zenith Bank",  "logo"=>"zenith.svg"],
    ["name"=>"Moniepoint",   "logo"=>"moniepoint.svg"],
    ["name"=>"Andela",       "logo"=>"andela.svg"],
    ["name"=>"PiggyVest",    "logo"=>"piggyvest.svg"],
];

$popularSearches = [
    ['Remote jobs', '/jobmington/jobs/search.php?type=Remote'],
    ['Developer jobs', '/jobmington/jobs/search.php?q=developer'],
    ['Internships', '/jobmington/jobs/search.php?type=Internship'],
    ['Marketing jobs', '/jobmington/jobs/search.php?q=marketing'],
];

$statCards = [
    'Open jobs' => [
        'tone' => 'blue',
        'icon' => '<svg viewBox="0 0 32 32" aria-hidden="true"><path d="M11 10V8.5A3.5 3.5 0 0 1 14.5 5h3A3.5 3.5 0 0 1 21 8.5V10"/><rect x="6" y="10" width="20" height="16" rx="3"/><path d="M6 16h20M14 16v2h4v-2"/></svg>',
    ],
    'Hiring companies' => [
        'tone' => 'orange',
        'icon' => '<svg viewBox="0 0 32 32" aria-hidden="true"><path d="M7 26h18M10 26V8h9v18M19 14h5v12"/><path d="M13 12h2M13 17h2M13 22h2M21 18h1M21 22h1"/></svg>',
    ],
    'Remote roles' => [
        'tone' => 'green',
        'icon' => '<svg viewBox="0 0 32 32" aria-hidden="true"><circle cx="16" cy="11" r="4"/><path d="M8.5 26a7.5 7.5 0 0 1 15 0"/></svg>',
    ],
    'Categories' => [
        'tone' => 'purple',
        'icon' => '<svg viewBox="0 0 32 32" aria-hidden="true"><rect x="7" y="7" width="6" height="6" rx="1.5"/><rect x="19" y="7" width="6" height="6" rx="1.5"/><rect x="7" y="19" width="6" height="6" rx="1.5"/><rect x="19" y="19" width="6" height="6" rx="1.5"/></svg>',
    ],
];

$categoryImages = [
    ['src' => 'https://images.unsplash.com/photo-1497366811353-6870744d04b2?auto=format&fit=crop&w=700&q=80', 'alt' => 'Bright office workspace with desks and chairs'],
    ['src' => 'https://images.unsplash.com/photo-1517245386807-bb43f82c33c4?auto=format&fit=crop&w=700&q=80', 'alt' => 'Team planning ideas with notes on a glass wall'],
    ['src' => 'https://images.unsplash.com/photo-1551836022-d5d88e9218df?auto=format&fit=crop&w=700&q=80', 'alt' => 'Professionals reviewing business work at a table'],
    ['src' => 'https://images.unsplash.com/photo-1519389950473-47ba0277781c?auto=format&fit=crop&w=700&q=80', 'alt' => 'People collaborating with laptops at a shared table'],
    ['src' => 'https://images.unsplash.com/photo-1552664730-d307ca884978?auto=format&fit=crop&w=700&q=80', 'alt' => 'Team presenting ideas during a work session'],
    ['src' => 'https://images.unsplash.com/photo-1521737604893-d14cc237f11d?auto=format&fit=crop&w=700&q=80', 'alt' => 'Colleagues working together around a laptop'],
];

$jobSeekerSteps = [
    ['Search jobs', 'Use filters to find roles that match your skills, location, and work style.'],
    ['Apply quickly', 'Send your profile and cover note without repeating the same details everywhere.'],
    ['Track progress', 'Keep applications, saved jobs, and employer updates in one simple place.'],
];

$employerSteps = [
    ['Post a role', 'Create a company profile and publish clear job openings in minutes.'],
    ['Review candidates', 'See applications, CVs, cover notes, and candidate status from one dashboard.'],
    ['Move hiring forward', 'Shortlist, interview, reject, or hire without losing the trail.'],
];

$aiHighlights = [
    [
        'label' => 'Andika AI',
        'title' => 'Ask for career help in plain language.',
        'description' => 'Draft cover notes, prepare for interviews, and get practical career guidance tuned to your market.',
        'href' => '/jobmington/ai/andika.php',
        'image' => '/jobmington/assets/images/features/andika_ai.png',
        'tone' => 'blue',
    ],
    [
        'label' => 'AI Matching',
        'title' => 'See the roles that fit your profile.',
        'description' => 'Jobmington compares your CV skills, headline, location, and experience against live roles.',
        'href' => '/jobmington/ai/andika.php#job-matches-widget',
        'image' => '/jobmington/assets/images/features/job_matching.png',
        'tone' => 'green',
    ],
    [
        'label' => 'CV Roast',
        'title' => 'Fix weak CV points before recruiters see them.',
        'description' => 'Get a direct CV score, missing keywords, rewrite guidance, and a sharper summary.',
        'href' => '/jobmington/ai/roast.php',
        'image' => '/jobmington/assets/images/features/cv_review.png',
        'tone' => 'orange',
    ],
];

$isLoggedIn = Session::isLoggedIn();
$dashboardUrl = Session::isAdmin()
    ? '/jobmington/admin/'
    : (Session::isEmployer() ? '/jobmington/employer/dashboard.php' : '/jobmington/seeker/dashboard.php');
$pageTitle = SITE_NAME . ' | Simple hiring for African talent';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Jobmington — Africa's career platform. Find remote jobs, unlock AI tools, and build a career that pays in dollars.">
    <link rel="canonical" href="/">
    <link rel="manifest" href="/jobmington/manifest.json?v=clean-urls">
    <link rel="apple-touch-icon" href="/jobmington/assets/images/pwa-icon-192.png?v=brand-10">
    <meta name="theme-color" content="#0640a3">
    <title><?= e($pageTitle) ?></title>
    <link rel="stylesheet" href="/jobmington/assets/css/minimal-jobmington.css?v=brand-10">
    <style>
    /* ── Homepage overrides ───────────────────────────────────────── */
    @keyframes jmFadeUp { from{opacity:0;transform:translateY(20px)} to{opacity:1;transform:translateY(0)} }
    @keyframes jmFadeIn { from{opacity:0} to{opacity:1} }

    /* Hero */
    .jm-hero-headline {
        font-size: clamp(40px, 6vw, 72px);
        line-height: 1.02;
        font-weight: 800;
        letter-spacing: -0.02em;
        color: var(--jm-ink);
        margin: 0 0 22px;
        animation: jmFadeUp .6s cubic-bezier(.22,.68,0,1.1) both;
    }
    .jm-hero-sub {
        font-size: 18px;
        color: var(--jm-muted);
        max-width: 560px;
        line-height: 1.7;
        margin: 0 0 32px;
        animation: jmFadeUp .6s cubic-bezier(.22,.68,0,1.1) both .1s;
    }
    .jm-hero-actions {
        display: flex;
        align-items: center;
        gap: 14px;
        flex-wrap: wrap;
        margin-bottom: 36px;
        animation: jmFadeUp .6s ease both .15s;
    }
    .jm-hero-actions .jm-button { min-height: 50px; padding: 0 28px; font-size: 15px; }
    .jm-hero-actions .jm-button.secondary { min-height: 50px; }

    /* Stat panel (hero right side) */
    .jm-hero-panel {
        animation: jmFadeUp .7s ease both .2s;
    }
    .jm-stat-board {
        border: 1px solid var(--jm-line);
        border-radius: 14px;
        background: #ffffff;
        overflow: hidden;
    }
    .jm-stat-board-top {
        padding: 28px 28px 24px;
        border-bottom: 1px solid var(--jm-line);
    }
    .jm-stat-live {
        display: inline-flex; align-items: center; gap: 7px;
        font-size: 11px; font-weight: 800; text-transform: uppercase;
        letter-spacing: .09em; color: var(--jm-muted);
        margin-bottom: 20px;
    }
    .jm-stat-live::before {
        content: ''; width: 7px; height: 7px; border-radius: 999px;
        background: var(--jm-green); flex-shrink: 0;
        animation: jmPulse 2s ease infinite;
    }
    @keyframes jmPulse {
        0%, 100% { opacity: 1; }
        50% { opacity: .4; }
    }
    .jm-stat-big-num {
        font-size: clamp(56px, 7vw, 80px);
        font-weight: 800; line-height: 1;
        color: var(--jm-ink); letter-spacing: -0.03em;
        display: block;
    }
    .jm-stat-big-label {
        display: block; font-size: 15px; font-weight: 600;
        color: var(--jm-muted); margin-top: 8px;
    }
    .jm-stat-row {
        display: grid; grid-template-columns: repeat(3, 1fr);
        border-bottom: 1px solid var(--jm-line);
    }
    .jm-stat-cell {
        padding: 18px 20px;
        border-right: 1px solid var(--jm-line);
    }
    .jm-stat-cell:last-child { border-right: none; }
    .jm-stat-cell strong {
        display: block; font-size: 24px; font-weight: 800;
        color: var(--jm-ink); line-height: 1;
    }
    .jm-stat-cell span {
        display: block; font-size: 11px; font-weight: 700;
        color: var(--jm-muted); margin-top: 5px;
        text-transform: uppercase; letter-spacing: .05em;
    }
    .jm-stat-board-cta {
        display: flex; align-items: center; justify-content: space-between;
        padding: 18px 24px; text-decoration: none;
        background: var(--jm-soft);
        transition: background .15s;
        gap: 12px;
    }
    .jm-stat-board-cta:hover { background: #e8f0fe; }
    .jm-stat-board-cta-copy strong {
        display: block; font-size: 15px; font-weight: 700; color: var(--jm-ink);
    }
    .jm-stat-board-cta-copy span {
        font-size: 12px; color: var(--jm-muted); display: block; margin-top: 2px;
    }
    .jm-stat-board-cta-arrow {
        width: 34px; height: 34px; border-radius: 8px;
        background: var(--jm-blue); display: grid; place-items: center;
        flex-shrink: 0; transition: background .15s;
    }
    .jm-stat-board-cta:hover .jm-stat-board-cta-arrow { background: var(--jm-blue-dark); }
    .jm-stat-board-cta-arrow svg { stroke: #fff; width: 16px; height: 16px; }

    /* Search bar */
    .jm-home-search {
        display: grid;
        grid-template-columns: 1fr 160px 46px;
        gap: 8px;
        background: #ffffff;
        border: 1px solid var(--jm-line);
        border-radius: 10px;
        padding: 8px;
        max-width: 600px;
        box-shadow: 0 4px 20px rgba(6,20,38,.06);
        animation: jmFadeUp .6s ease both .2s;
    }
    .jm-home-search .jm-input, .jm-home-search .jm-select {
        border: none; background: transparent;
        box-shadow: none; min-height: 40px; padding: 0 10px;
    }
    .jm-home-search .jm-input:focus, .jm-home-search .jm-select:focus { box-shadow: none; }
    .jm-home-search-btn {
        background: var(--jm-blue); border: none; border-radius: 7px;
        color: #fff; cursor: pointer; display: grid; place-items: center;
        min-height: 40px; transition: background .15s;
    }
    .jm-home-search-btn:hover { background: var(--jm-blue-dark); }
    .jm-home-search-btn svg { width: 18px; height: 18px; stroke: #fff; }

    /* Popular searches */
    .jm-popular-searches {
        display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
        margin-top: 16px; animation: jmFadeIn .6s ease both .3s;
    }
    .jm-popular-searches span { font-size: 12px; font-weight: 800; color: var(--jm-muted); text-transform: uppercase; letter-spacing: .06em; }
    .jm-popular-searches a {
        font-size: 13px; font-weight: 700; color: var(--jm-blue);
        border: 1px solid var(--jm-line); border-radius: 99px;
        padding: 5px 14px; text-decoration: none; background: #fff;
        transition: border-color .15s, background .15s;
    }
    .jm-popular-searches a:hover { border-color: var(--jm-blue); background: #eef5ff; }

    /* Identity bar */
    .jm-identity-bar {
        display: flex; align-items: center; gap: 0;
        border-top: 1px solid var(--jm-line);
        border-bottom: 1px solid var(--jm-line);
        overflow: hidden;
        margin: 0;
    }
    .jm-identity-bar-item {
        flex: 1; padding: 16px 20px;
        border-right: 1px solid var(--jm-line);
        display: flex; align-items: center; gap: 10px;
        font-size: 13px; font-weight: 600; color: var(--jm-muted);
        white-space: nowrap;
    }
    .jm-identity-bar-item:last-child { border-right: none; }
    .jm-identity-bar-item svg { color: var(--jm-blue); flex-shrink: 0; }
    .jm-identity-bar-item strong { color: var(--jm-ink); }

    /* AI tools section */
    .jm-ai-tools-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 18px;
        margin-top: 28px;
    }
    .jm-ai-tool-card {
        border: 1px solid var(--jm-line);
        border-radius: 12px;
        padding: 26px;
        background: #ffffff;
        text-decoration: none;
        display: flex; flex-direction: column; gap: 12px;
        transition: border-color .2s, box-shadow .2s, transform .2s;
        position: relative; overflow: hidden;
    }
    .jm-ai-tool-card::before {
        content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px;
        background: var(--jm-blue);
    }
    .jm-ai-tool-card.tone-green::before  { background: var(--jm-green); }
    .jm-ai-tool-card.tone-orange::before { background: var(--jm-orange); }
    .jm-ai-tool-card:hover {
        border-color: #b6cceb;
        box-shadow: 0 12px 32px rgba(6,64,163,.08);
        transform: translateY(-3px);
    }
    .jm-ai-tool-label {
        font-size: 11px; font-weight: 800; text-transform: uppercase;
        letter-spacing: .08em; color: var(--jm-blue);
    }
    .jm-ai-tool-card.tone-green  .jm-ai-tool-label { color: var(--jm-green); }
    .jm-ai-tool-card.tone-orange .jm-ai-tool-label { color: #9a5a00; }
    .jm-ai-tool-card h3 { margin: 0; font-size: 18px; color: var(--jm-ink); line-height: 1.3; }
    .jm-ai-tool-card p  { margin: 0; font-size: 14px; color: var(--jm-muted); line-height: 1.65; flex: 1; }
    .jm-ai-tool-footer {
        display: flex; align-items: center; justify-content: space-between;
        padding-top: 14px; border-top: 1px solid var(--jm-line);
        font-size: 12px; font-weight: 700;
    }
    .jm-ai-premium-badge {
        background: var(--jm-blue); color: #fff;
        font-size: 10px; font-weight: 800; text-transform: uppercase;
        letter-spacing: .06em; padding: 3px 9px; border-radius: 99px;
    }
    .jm-ai-tool-arrow {
        color: var(--jm-blue); font-size: 18px; transition: transform .2s;
    }
    .jm-ai-tool-card:hover .jm-ai-tool-arrow { transform: translateX(4px); }

    /* Jobs section */
    .jm-home-jobs-layout {
        display: grid;
        grid-template-columns: minmax(0,1fr) 280px;
        gap: 40px; align-items: start;
    }
    @media(max-width:860px){ .jm-home-jobs-layout{ grid-template-columns:1fr; } }
    .jm-home-jobs-sidebar {
        border: 1px solid var(--jm-line); border-radius: 12px;
        background: linear-gradient(160deg,#f4f8ff,#fff);
        padding: 26px; position: sticky; top: 20px;
    }
    .jm-home-jobs-sidebar h3 { margin: 0 0 6px; font-size: 18px; color: var(--jm-ink); }
    .jm-home-jobs-sidebar p  { margin: 0 0 18px; font-size: 14px; color: var(--jm-muted); }

    /* Pathways */
    .jm-pathways-2 {
        display: grid;
        grid-template-columns: repeat(2, minmax(0,1fr));
        gap: 20px;
    }
    @media(max-width:640px){ .jm-pathways-2{ grid-template-columns:1fr; } }
    .jm-pathway-2 {
        border: 1px solid var(--jm-line); border-radius: 12px;
        padding: 36px 32px; text-decoration: none; display: block;
        transition: border-color .2s, box-shadow .2s, transform .2s;
        background: #ffffff;
    }
    .jm-pathway-2:hover {
        border-color: #b6cceb;
        box-shadow: 0 12px 32px rgba(6,64,163,.08);
        transform: translateY(-3px);
    }
    .jm-pathway-2.accent { background: var(--jm-ink); border-color: var(--jm-ink); }
    .jm-pathway-2.accent h2,
    .jm-pathway-2.accent p   { color: rgba(255,255,255,.85); }
    .jm-pathway-2.accent h2  { color: #ffffff; }
    .jm-pathway-2.accent .jm-pathway-tag { background:rgba(255,255,255,.12); color:#fff; border-color:rgba(255,255,255,.2); }
    .jm-pathway-tag {
        display: inline-block; font-size: 11px; font-weight: 800;
        text-transform: uppercase; letter-spacing: .08em;
        color: var(--jm-blue); background: #eef5ff;
        border: 1px solid #c9dcf5; border-radius: 99px;
        padding: 3px 10px; margin-bottom: 18px;
    }
    .jm-pathway-2 h2 { font-size: clamp(22px,3vw,30px); color: var(--jm-ink); margin: 0 0 12px; line-height: 1.15; }
    .jm-pathway-2 p  { font-size: 15px; color: var(--jm-muted); margin: 0 0 24px; line-height: 1.65; }
    .jm-pathway-2 .jm-path-link {
        font-size: 14px; font-weight: 700; color: var(--jm-blue);
        display: inline-flex; align-items: center; gap: 6px;
    }
    .jm-pathway-2.accent .jm-path-link { color: rgba(255,255,255,.8); }

    /* Premium upsell strip */
    .jm-premium-strip {
        background: var(--jm-ink);
        border-radius: 14px;
        padding: 44px 48px;
        display: grid;
        grid-template-columns: 1fr auto;
        gap: 32px; align-items: center;
        margin: 0;
    }
    @media(max-width:640px){ .jm-premium-strip{ grid-template-columns:1fr; padding:32px 24px; } }
    .jm-premium-strip h2 { font-size: clamp(22px,3vw,32px); color: #fff; margin: 0 0 10px; line-height: 1.15; }
    .jm-premium-strip p  { font-size: 15px; color: rgba(255,255,255,.7); margin: 0; }
    .jm-premium-strip-price {
        font-size: 13px; color: rgba(255,255,255,.5);
        display: block; margin-top: 4px;
    }
    .jm-premium-strip-actions { display: flex; gap: 12px; flex-wrap: wrap; }
    .jm-premium-strip .jm-button {
        background: var(--jm-orange); border-color: var(--jm-orange);
        color: var(--jm-ink) !important; font-weight: 800;
        min-height: 48px; padding: 0 28px;
    }
    .jm-premium-strip .jm-button:hover { background: #e8920f; border-color: #e8920f; }
    .jm-premium-strip .jm-button.secondary {
        background: transparent; border-color: rgba(255,255,255,.25);
        color: rgba(255,255,255,.8) !important;
    }
    .jm-premium-strip .jm-button.secondary:hover {
        background: rgba(255,255,255,.08); border-color: rgba(255,255,255,.4);
    }

    /* Categories */
    .jm-cat-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(160px,1fr));
        gap: 12px; margin-top: 24px;
    }
    .jm-cat-chip {
        border: 1px solid var(--jm-line); border-radius: 10px;
        padding: 16px 18px; text-decoration: none; display: block;
        background: #ffffff; transition: border-color .18s, background .18s, transform .18s;
    }
    .jm-cat-chip:hover { border-color: var(--jm-blue); background: #eef5ff; transform: translateY(-2px); }
    .jm-cat-chip strong { display: block; font-size: 15px; color: var(--jm-ink); margin-bottom: 4px; }
    .jm-cat-chip span { font-size: 12px; color: var(--jm-muted); font-weight: 600; }

    /* Section spacing */
    .jm-hp-section {
        padding: 72px 0;
        border-bottom: 1px solid var(--jm-line);
    }
    .jm-hp-section:last-of-type { border-bottom: none; }
    .jm-hp-kicker {
        font-size: 11px; font-weight: 800; text-transform: uppercase;
        letter-spacing: .1em; color: var(--jm-blue);
        display: block; margin-bottom: 10px;
    }
    .jm-hp-h {
        font-size: clamp(26px,3.5vw,40px); font-weight: 800;
        color: var(--jm-ink); margin: 0 0 14px; line-height: 1.1;
    }
    .jm-hp-sub {
        font-size: 16px; color: var(--jm-muted); margin: 0;
        max-width: 560px; line-height: 1.7;
    }
    .jm-hp-head {
        display: flex; align-items: flex-end; justify-content: space-between;
        gap: 20px; flex-wrap: wrap; margin-bottom: 32px;
    }
    .jm-hp-head-link {
        font-size: 14px; font-weight: 700; color: var(--jm-blue);
        text-decoration: none; white-space: nowrap;
        display: inline-flex; align-items: center; gap: 5px;
    }
    .jm-hp-head-link:hover { text-decoration: underline; text-underline-offset: 3px; }

    /* Responsive hero */
    @media(max-width:960px) {
        .jm-home-hero { grid-template-columns: 1fr !important; }
        .jm-stat-board { max-width: 520px; }
    }

    /* ── Hero (full-bleed, Jobmington palette) ──────────────────── */
    .jm-hero2-section {
        width: 100vw;
        margin-left: calc(-50vw + 50%);
        padding: 48px 0 0;
        overflow: hidden;
        position: relative;
        background:
            radial-gradient(ellipse at 25% 70%, rgba(245,159,34,.02) 0%, transparent 40%),
            radial-gradient(ellipse at 75% 15%, rgba(6,64,163,.02) 0%, transparent 35%);
    }
    @media(min-width:960px) { .jm-hero2-section { padding-top: 96px; } }
    .jm-hero2-inner {
        max-width: 1172px;
        margin: 0 auto;
        padding: 0 24px;
    }
    .jm-hero2-grid {
        display: grid; grid-template-columns: 1fr 1fr;
        gap: 48px; align-items: center;
    }
    @media(max-width:959px) { .jm-hero2-grid { grid-template-columns: 1fr; } }

    /* Headline */
    .jm-hero2-h1 { font-weight: 800; color: var(--jm-ink); line-height: 1.1; margin: 0 0 24px; }
    .jm-hero2-h1-big { display: block; font-size: clamp(32px,4vw,48px); }
    .jm-hero2-h1-sm  { display: block; font-size: clamp(22px,2.8vw,32px); margin-top: 8px; }
    .jm-hero2-h1-accent {
        color: var(--jm-orange);
        position: relative;
        display: inline-block;
    }
    .jm-hero2-h1-accent::after {
        content: '';
        position: absolute;
        inset: -8px -12px;
        background: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 220 60' preserveAspectRatio='none'%3E%3Cpath d='M8 30 C10 10 28 4 60 3 C95 2 148 2 182 7 C204 11 214 20 212 31 C210 43 196 52 164 55 C128 58 78 58 46 55 C18 52 5 44 8 30Z' fill='none' stroke='%23f59f22' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E") no-repeat center;
        background-size: 100% 100%;
        pointer-events: none;
    }
    .jm-hero2-para {
        font-size: 17px; color: var(--jm-muted); line-height: 1.7;
        margin: 0 0 32px; max-width: 480px;
    }

    /* CTA */
    .jm-hero2-cta-row { margin-bottom: 40px; }
    .jm-hero2-cta-btn {
        display: inline-flex; align-items: center; gap: 8px;
        padding: 16px 28px; border-radius: 10px;
        background: var(--jm-blue); color: #fff !important;
        font-size: 15px; font-weight: 800; text-decoration: none;
        box-shadow: 0 2px 10px rgba(6,64,163,.15);
        transition: background .15s, box-shadow .15s;
    }
    .jm-hero2-cta-btn:hover {
        background: var(--jm-blue-dark);
        box-shadow: 0 4px 16px rgba(6,64,163,.2);
    }

    /* Stats row */
    .jm-hero2-stats { padding-bottom: 24px; border-bottom: 1px solid var(--jm-line); }
    .jm-hero2-stats-row { display: flex; align-items: center; gap: 16px; flex-wrap: wrap; }
    .jm-hero2-stat-group { display: flex; align-items: center; gap: 10px; }
    .jm-hero2-avatars { display: flex; }
    .jm-hero2-avatars img {
        width: 28px; height: 28px; border-radius: 50%;
        border: 2px solid white; object-fit: cover;
        margin-left: -8px; box-shadow: 0 0 0 2px rgba(6,64,163,.12);
    }
    .jm-hero2-avatars img:first-child { margin-left: 0; }
    .jm-hero2-stat-num { font-size: 16px; font-weight: 800; color: var(--jm-ink); margin: 0; line-height: 1.2; }
    .jm-hero2-stat-lbl { font-size: 11px; color: var(--jm-muted); margin: 0; line-height: 1.2; }
    .jm-hero2-stat-icon {
        width: 36px; height: 36px; border-radius: 50%;
        display: flex; align-items: center; justify-content: center; flex-shrink: 0;
    }
    .jm-hero2-stat-icon.blue  { background: var(--jm-soft); }
    .jm-hero2-stat-icon.green { background: var(--jm-soft); }
    .jm-hero2-stat-icon.blue  svg { stroke: var(--jm-blue); }
    .jm-hero2-stat-icon.green svg { stroke: var(--jm-blue); }
    .jm-hero2-divider { width: 1px; height: 40px; background: var(--jm-line); flex-shrink: 0; }

    /* Right collage */
    .jm-hero2-right { position: relative; }
    .jm-hc-wrap { display: flex; flex-direction: column; gap: 16px; position: relative; }
    @media(min-width:960px) { .jm-hc-wrap { height: 550px; } }

    .jm-hc-main {
        border-radius: 16px; overflow: hidden;
        box-shadow: 0 24px 56px rgba(0,0,0,.18); border: 2px solid white;
    }
    @media(max-width:959px) {
        .jm-hc-main { height: 260px; }
    }
    @media(min-width:960px) {
        .jm-hc-main {
            position: absolute; left: 50%; top: 50%;
            transform: translate(-50%,-50%); width: 280px; height: 340px;
        }
    }
    .jm-hc-main img { width: 100%; height: 100%; object-fit: cover; display: block; }

    .jm-hc-tl, .jm-hc-tr, .jm-hc-br { display: none; }
    @media(min-width:960px) {
        .jm-hc-tl {
            display: block; position: absolute;
            top: 0; left: 0; width: 160px; height: 200px;
            border-radius: 12px; overflow: hidden;
            box-shadow: 0 8px 28px rgba(0,0,0,.14); border: 1px solid rgba(255,255,255,.7);
        }
        .jm-hc-tr {
            display: block; position: absolute;
            top: 0; right: 0; width: 140px; height: 180px;
            border-radius: 12px; overflow: hidden;
            box-shadow: 0 8px 28px rgba(0,0,0,.14); border: 1px solid rgba(255,255,255,.7);
        }
        .jm-hc-br {
            display: block; position: absolute;
            bottom: 0; right: 0; width: 150px; height: 190px;
            border-radius: 12px; overflow: hidden;
            box-shadow: 0 8px 28px rgba(0,0,0,.14); border: 1px solid rgba(255,255,255,.6);
        }
    }
    .jm-hc-tl img, .jm-hc-tr img, .jm-hc-br img { width: 100%; height: 100%; object-fit: cover; display: block; }

    /* Floating cards */
    .jm-hc-card {
        position: absolute; z-index: 10;
        background: var(--jm-white); border: 1px solid var(--jm-line);
        border-radius: 12px; padding: 12px;
        box-shadow: 0 8px 32px rgba(0,0,0,.12);
        animation: jmFadeIn .6s ease both .5s;
    }
    .jm-hc-card-top {
        top: -10px; left: 50%;
        animation: jmFloatC 5s ease-in-out infinite;
    }
    .jm-hc-card-right {
        right: -16px; top: 200px;
        animation: jmFloat 5s ease-in-out infinite 1.2s;
    }
    .jm-hc-card-left {
        left: -16px; bottom: 180px;
        animation: jmFloat 5s ease-in-out infinite 2.4s;
    }
    @media(max-width:959px) {
        .jm-hc-card-top, .jm-hc-card-right, .jm-hc-card-left { display: none; }
    }
    @keyframes jmFloat {
        0%, 100% { transform: translateY(0px); }
        50%       { transform: translateY(-10px); }
    }
    @keyframes jmFloatC {
        0%, 100% { transform: translateX(-50%) translateY(0px); }
        50%       { transform: translateX(-50%) translateY(-10px); }
    }
    /* Match score ring card */
    .jm-hc-ring-wrap { display: flex; align-items: center; gap: 12px; }
    .jm-hc-ring { position: relative; width: 48px; height: 48px; flex-shrink: 0; }
    .jm-hc-ring svg { transform: rotate(-90deg); width: 48px; height: 48px; }
    .jm-hc-ring-lbl {
        position: absolute; inset: 0; display: flex;
        align-items: center; justify-content: center;
        font-size: 10px; font-weight: 800; color: var(--jm-ink);
    }
    .jm-hc-score-kicker { font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: .06em; color: var(--jm-muted); margin: 0; }
    .jm-hc-score-val    { font-weight: 800; font-size: 13px; color: var(--jm-blue); margin: 0; }

    /* CV checklist */
    .jm-hc-checklist { display: flex; flex-direction: column; gap: 6px; }
    .jm-hc-check-row { display: flex; align-items: center; gap: 8px; font-size: 13px; color: var(--jm-ink); }

    /* Company ticker */
    .jm-hero2-ticker-section { padding: 32px 0 0; margin-top: 40px; }
    .jm-hero2-ticker-label { font-size: 13px; color: var(--jm-muted); text-align: center; margin: 0 0 28px; }
    .jm-hero2-ticker-wrap { position: relative; overflow: hidden; }
    .jm-hero2-ticker-fade-l,
    .jm-hero2-ticker-fade-r {
        position: absolute; top: 0; bottom: 0; z-index: 10; width: 80px; pointer-events: none;
    }
    .jm-hero2-ticker-fade-l { left: 0; background: linear-gradient(to right, var(--jm-white) 60%, transparent); }
    .jm-hero2-ticker-fade-r { right: 0; background: linear-gradient(to left, var(--jm-white) 60%, transparent); }
    .jm-hero2-ticker-track {
        display: flex; align-items: center;
        animation: jmScrollTicker 35s linear infinite;
        width: max-content;
    }
    .jm-hero2-ticker-set { display: flex; align-items: center; gap: 40px; padding: 0 20px; flex-shrink: 0; }
    .jm-hero2-ticker-item {
        font-size: 14px; font-weight: 600; color: var(--jm-muted);
        white-space: nowrap; opacity: .7; padding: 8px 0;
        transition: opacity .2s;
    }
    .jm-hero2-ticker-item:hover { opacity: 1; }
    @keyframes jmScrollTicker {
        from { transform: translateX(0); }
        to   { transform: translateX(-50%); }
    }

    /* Ticker logos */
    .jm-ticker-logo-wrap {
        display: inline-flex; align-items: center;
        flex-shrink: 0; text-decoration: none;
        opacity: .5; filter: grayscale(1);
        transition: opacity .25s, filter .25s;
    }
    .jm-ticker-logo-wrap:hover { opacity: 1; filter: grayscale(0); }
    .jm-ticker-logo {
        height: 40px; width: auto; max-width: 130px;
        object-fit: contain; display: block;
    }

    /* ── Testimonials ─────────────────────────────────────────── */
    .jm-testimonials-section { padding: 80px 0; overflow: hidden; }
    .jm-testimonials-contents {
        max-width: 1172px; margin: 0 auto 56px; padding: 0 24px;
        text-align: center;
    }
    .jm-testimonials-eyebrow {
        font-size: 11px; font-weight: 800; text-transform: uppercase;
        letter-spacing: .12em; color: var(--jm-blue); margin: 0 0 12px;
    }
    .jm-testimonials-title {
        font-size: clamp(28px,3.5vw,40px); font-weight: 800;
        color: var(--jm-ink); margin: 0 0 28px; line-height: 1.1;
    }
    .jm-testimonials-cta-row { display: flex; justify-content: center; }

    /* Marquee */
    .jm-testimonials-list-container {
        position: relative; overflow: hidden;
    }
    .jm-testimonials-list-container::before,
    .jm-testimonials-list-container::after {
        content: ''; position: absolute; top: 0; bottom: 0;
        z-index: 10; width: 120px; pointer-events: none;
    }
    .jm-testimonials-list-container::before { left: 0; background: linear-gradient(to right, var(--jm-white), transparent); }
    .jm-testimonials-list-container::after  { right: 0; background: linear-gradient(to left,  var(--jm-white), transparent); }
    .jm-testimonials-list {
        display: flex; gap: 20px; padding: 8px 20px 20px;
        animation: jmMarqueeTestimonials 45s linear infinite;
        width: max-content;
    }
    @keyframes jmMarqueeTestimonials {
        from { transform: translateX(0); }
        to   { transform: translateX(-50%); }
    }
    .jm-testimonials-list { animation-duration: 80s; }

    /* Card */
    .jm-testimonial-card {
        width: 340px; flex-shrink: 0;
        background: var(--jm-white);
        border: 1px solid var(--jm-line);
        border-radius: 14px; padding: 28px;
        display: flex; flex-direction: column; gap: 14px;
        margin: 0;
    }
    .jm-testimonial-text {
        font-size: 15px; color: var(--jm-ink);
        line-height: 1.65; flex: 1; margin: 0;
    }
    .jm-testimonial-footer {
        display: flex; align-items: center;
        justify-content: space-between; gap: 12px;
        padding-top: 16px; border-top: 1px solid var(--jm-line);
        margin-top: auto;
    }
    .jm-testimonial-cite {
        font-size: 13px; font-weight: 700; color: var(--jm-ink);
        font-style: normal; line-height: 1.35;
    }
    .jm-testimonial-cite span {
        display: block; font-weight: 500; color: var(--jm-muted); font-size: 12px; margin-top: 2px;
    }
    .jm-testimonial-avatar {
        width: 44px; height: 44px; border-radius: 50%;
        object-fit: cover; flex-shrink: 0;
        border: 2px solid var(--jm-line);
    }

    /* ── Section header consistency ─────────────────────────────── */
    .jm-hp-kicker {
        display: inline-flex !important; align-items: center; gap: 10px;
    }
    .jm-hp-kicker::before {
        content: ''; display: block; flex-shrink: 0;
        width: 20px; height: 2.5px; background: var(--jm-orange);
        border-radius: 2px;
    }
    .jm-hp-h { font-size: clamp(30px,4vw,44px) !important; }

    /* Jobs sidebar accent */
    .jm-home-jobs-sidebar { border-top: 3px solid var(--jm-orange) !important; background: var(--jm-white) !important; }

    /* Premium strip full-bleed */
    .jm-strip-section { padding: 0 !important; border: none !important; }
    .jm-strip-section .jm-premium-strip {
        margin-left: calc(-50vw + 50%) !important;
        width: 100vw !important;
        border-radius: 0 !important;
        padding: 80px max(24px, calc(50vw - 586px)) !important;
    }

    /* Testimonials section – full-bleed soft bg */
    .jm-testimonials-section {
        background: var(--jm-soft);
        margin-left: calc(-50vw + 50%);
        width: 100vw;
    }
    .jm-testimonials-list-container::before { background: linear-gradient(to right, var(--jm-soft), transparent) !important; }
    .jm-testimonials-list-container::after  { background: linear-gradient(to left,  var(--jm-soft), transparent) !important; }
    .jm-testimonial-card { background: var(--jm-white) !important; }

    /* AI tool cards – icon-first, no thin top stripe */
    .jm-ai-tool-card::before { display: none; }
    .jm-ai-tool-card { padding: 30px 28px; }
    .jm-ai-tool-icon {
        width: 48px; height: 48px; border-radius: 12px;
        display: flex; align-items: center; justify-content: center;
        flex-shrink: 0; background: rgba(6,64,163,.08);
    }
    .jm-ai-tool-card.tone-green  .jm-ai-tool-icon { background: rgba(16,185,129,.1); }
    .jm-ai-tool-card.tone-orange .jm-ai-tool-icon { background: rgba(245,159,34,.12); }
    .jm-ai-tool-icon svg {
        width: 22px; height: 22px;
        stroke: var(--jm-blue); fill: none;
        stroke-linecap: round; stroke-linejoin: round; stroke-width: 2;
    }
    .jm-ai-tool-card.tone-green  .jm-ai-tool-icon svg { stroke: var(--jm-green); }
    .jm-ai-tool-card.tone-orange .jm-ai-tool-icon svg { stroke: var(--jm-orange); }
    .jm-ai-tool-card h3 { font-size: 20px !important; }

    /* Jobs section – soft background for visual rhythm */
    .jm-jobs-section { position: relative; isolation: isolate; }
    .jm-jobs-section::before {
        content: ''; position: absolute; inset: 0;
        left: calc(-50vw + 50%); width: 100vw;
        background: var(--jm-soft); z-index: -1;
    }

    /* Antigravity canvas – premium strip */
    .jm-premium-strip { position: relative; overflow: hidden; }
    .jm-premium-strip > div { position: relative; z-index: 1; }
    #jm-premium-canvas {
        position: absolute; inset: 0;
        width: 100%; height: 100%;
        pointer-events: none; display: block;
    }
    </style>
</head>
<body class="jm-minimal jm-home-page">
    <div class="jm-shell">
        <header class="jm-header">
            <a class="jm-logo" href="/jobmington/">
                <img src="/jobmington/assets/images/badge.png?v=logo-7" alt="">
                <span>Jobmington</span>
            </a>
            <button class="jm-mobile-nav-toggle" type="button" aria-label="Open menu" aria-expanded="false" aria-controls="jm-home-nav">
                <span></span>
            </button>
            <nav class="jm-nav" id="jm-home-nav" aria-label="Main navigation">
                <a href="/jobmington/jobs/">Find jobs</a>
                <a href="/jobmington/cv-builder/">CV Builder</a>
                <a href="/jobmington/ai/andika.php">Andika AI</a>
                <a href="/jobmington/employer/">Employers</a>
                <a href="/jobmington/pricing.php">Pricing</a>
                <?php if ($isLoggedIn): ?>
                    <a href="<?= e($dashboardUrl) ?>">Dashboard</a>
                    <a class="jm-button secondary" href="/jobmington/auth/logout.php">Sign out</a>
                <?php else: ?>
                    <a href="/jobmington/auth/login.php">Sign in</a>
                    <a class="jm-button secondary" href="/jobmington/auth/register.php">Create account</a>
                <?php endif; ?>
            </nav>
        </header>

        <!-- ── Hero ──────────────────────────────────────────────────── -->
        <section class="jm-hero2-section">
            <div class="jm-hero2-inner">
            <div class="jm-hero2-grid">

                <!-- LEFT -->
                <div class="jm-hero2-left">

                    <h1 class="jm-hero2-h1">
                        <span class="jm-hero2-h1-big">Africa's talent. Global demand.</span>
                        <span class="jm-hero2-h1-sm">Remote jobs and AI tools built to get you <span class="jm-hero2-h1-accent">hired.</span></span>
                    </h1>

                    <p class="jm-hero2-para">We give African professionals the tools to understand why they're being filtered out, sharpen their positioning, and get in front of the right opportunities — from anywhere on the continent.</p>

                    <div class="jm-hero2-cta-row">
                        <?php if ($isLoggedIn): ?>
                            <a href="<?= e($dashboardUrl) ?>" class="jm-hero2-cta-btn">
                                Go to Dashboard
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                            </a>
                        <?php else: ?>
                            <a href="/jobmington/auth/register.php" class="jm-hero2-cta-btn">
                                Get Matched Now
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                            </a>
                        <?php endif; ?>
                    </div>

                    <div class="jm-hero2-stats">
                        <div class="jm-hero2-stats-row">
                            <div class="jm-hero2-stat-group">
                                <div class="jm-hero2-avatars" aria-hidden="true">
                                    <img src="/jobmington/assets/images/testimonials/testimonial_1.jpg" alt="">
                                    <img src="/jobmington/assets/images/testimonials/testimonial_2.jpg" alt="">
                                    <img src="/jobmington/assets/images/testimonials/testimonial_3.jpg" alt="">
                                    <img src="https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&crop=faces&w=56&q=80" alt="">
                                </div>
                                <div>
                                    <p class="jm-hero2-stat-num"><?= number_format((int)($stats['Open jobs'] ?? 0)) ?>+</p>
                                    <p class="jm-hero2-stat-lbl">Open Roles</p>
                                </div>
                            </div>
                            <div class="jm-hero2-divider"></div>
                            <div class="jm-hero2-stat-group">
                                <div class="jm-hero2-stat-icon blue">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M16 20V4a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/><rect height="14" width="20" x="2" y="6" rx="2"/></svg>
                                </div>
                                <div>
                                    <p class="jm-hero2-stat-num"><?= number_format((int)($stats['Remote roles'] ?? 0)) ?>+</p>
                                    <p class="jm-hero2-stat-lbl">Remote Jobs</p>
                                </div>
                            </div>
                            <div class="jm-hero2-divider"></div>
                            <div class="jm-hero2-stat-group">
                                <div class="jm-hero2-stat-icon green">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 22V4a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v18Z"/><path d="M6 12H4a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h2"/><path d="M18 9h2a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2h-2"/><path d="M10 6h4"/><path d="M10 10h4"/><path d="M10 14h4"/><path d="M10 18h4"/></svg>
                                </div>
                                <div>
                                    <p class="jm-hero2-stat-num"><?= number_format((int)($stats['Hiring companies'] ?? 0)) ?>+</p>
                                    <p class="jm-hero2-stat-lbl">Companies</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- RIGHT: Photo collage -->
                <div class="jm-hero2-right">
                    <div class="jm-hc-wrap">
                        <div class="jm-hc-main">
                            <img src="/jobmington/assets/images/hero2.jpg" alt="Professional working on career">
                        </div>
                        <div class="jm-hc-tl">
                            <img src="https://images.unsplash.com/photo-1739300293504-234817eead52?auto=format&fit=crop&crop=faces&w=320&q=80" alt="Professional reviewing documents">
                        </div>
                        <div class="jm-hc-tr">
                            <img src="https://images.unsplash.com/photo-1522529599102-193c0d76b5b6?auto=format&fit=crop&crop=faces&w=280&q=80" alt="Professional on tablet">
                        </div>
                        <div class="jm-hc-br">
                            <img src="https://images.unsplash.com/photo-1653669486397-b802144ae64a?auto=format&fit=crop&crop=faces&w=300&q=80" alt="Professional on laptop">
                        </div>

                        <!-- Card: ATS Score (top, centred) -->
                        <div class="jm-hc-card jm-hc-card-top">
                            <div class="jm-hc-ring-wrap">
                                <div class="jm-hc-ring">
                                    <svg width="48" height="48" style="transform:rotate(-90deg)">
                                        <circle cx="24" cy="24" r="20" fill="none" stroke="#d8e4f4" stroke-width="3"/>
                                        <circle cx="24" cy="24" r="20" fill="none" stroke="#0640a3" stroke-width="3" stroke-dasharray="125" stroke-dashoffset="22" stroke-linecap="round"/>
                                    </svg>
                                    <span class="jm-hc-ring-lbl">84%</span>
                                </div>
                                <div>
                                    <p class="jm-hc-score-kicker">ATS Score</p>
                                    <p class="jm-hc-score-val">Strong Match</p>
                                </div>
                            </div>
                        </div>

                        <!-- Card: Live job match (right) -->
                        <div class="jm-hc-card jm-hc-card-right">
                            <div style="display:flex;align-items:center;gap:12px;">
                                <div style="width:36px;height:36px;border-radius:50%;background:#f7faff;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#0640a3" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M16 20V4a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/><rect height="14" width="20" x="2" y="6" rx="2"/></svg>
                                </div>
                                <div>
                                    <p style="font-size:11px;color:var(--jm-muted);margin:0 0 2px;">New Match</p>
                                    <p style="font-size:13px;font-weight:700;color:var(--jm-ink);margin:0 0 2px;">Product Designer</p>
                                    <p style="font-size:11px;margin:0;">Flutterwave · Remote &nbsp;<strong style="color:var(--jm-orange);">₦380k/mo</strong></p>
                                </div>
                            </div>
                        </div>

                        <!-- Card: Platform feature checklist (left) -->
                        <div class="jm-hc-card jm-hc-card-left">
                            <div class="jm-hc-checklist">
                                <div class="jm-hc-check-row">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#0640a3" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21.801 10A10 10 0 1 1 17 3.335"/><path d="m9 11 3 3L22 4"/></svg>
                                    <span>ATS Score: <strong>91%</strong></span>
                                </div>
                                <div class="jm-hc-check-row">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#0640a3" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21.801 10A10 10 0 1 1 17 3.335"/><path d="m9 11 3 3L22 4"/></svg>
                                    <span>CV Roasted</span>
                                </div>
                                <div class="jm-hc-check-row">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#0640a3" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21.801 10A10 10 0 1 1 17 3.335"/><path d="m9 11 3 3L22 4"/></svg>
                                    <span><strong style="color:var(--jm-orange);">12</strong> Job Matches</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            </div><!-- /jm-hero2-inner (grid) -->
            <div class="jm-hero2-inner">
            <!-- Company ticker -->
            <div class="jm-hero2-ticker-section">
                <p class="jm-hero2-ticker-label">Trusted by professionals at Africa's top companies</p>
                <div class="jm-hero2-ticker-wrap">
                    <div class="jm-hero2-ticker-fade-l"></div>
                    <div class="jm-hero2-ticker-fade-r"></div>
                    <div class="jm-hero2-ticker-track">
                        <?php foreach ([1,2] as $pass): ?>
                        <div class="jm-hero2-ticker-set" <?= $pass === 2 ? 'aria-hidden="true"' : '' ?>>
                            <?php foreach ($tickerSponsors as $sp): ?>
                                <a href="/jobmington/jobs/search.php?q=<?= urlencode($sp['name']) ?>" class="jm-ticker-logo-wrap" title="<?= e($sp['name']) ?>">
                                    <img src="/jobmington/assets/images/sponsors/<?= e($sp['logo']) ?>"
                                         alt="<?= e($sp['name']) ?>" class="jm-ticker-logo">
                                </a>
                            <?php endforeach; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            </div><!-- /jm-hero2-inner (ticker) -->
        </section>

        <!-- ── AI Tools ───────────────────────────────────────────────── -->
        <section class="jm-hp-section">
            <div class="jm-hp-head">
                <div>
                    <span class="jm-hp-kicker">AI Career Tools</span>
                    <h2 class="jm-hp-h">AI that does the heavy lifting.</h2>
                    <p class="jm-hp-sub">Premium AI tools built for the African job seeker — CV scoring, cover letters, interview coaching, and real job matching.</p>
                </div>
                <a class="jm-hp-head-link" href="/jobmington/pricing.php?tab=seeker">
                    See pricing
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                </a>
            </div>
            <div class="jm-ai-tools-grid">
                <?php
                $homeAiTools = [
                    ['href' => '/jobmington/ai/andika.php', 'tone' => 'blue', 'label' => 'Andika AI',
                     'icon'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>',
                     'title' => 'Your AI career coach.',
                     'desc'  => 'Ask anything — cover letter help, interview prep, salary advice. Powered by Llama 3.2.',
                     'cta'   => 'Open Andika'],
                    ['href' => '/jobmington/cv-builder/', 'tone' => 'green', 'label' => 'CV Optimiser',
                     'icon'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>',
                     'title' => 'Know your ATS score before employers do.',
                     'desc'  => 'Get a detailed scan, missing keywords, and rewrite suggestions that actually improve your chances.',
                     'cta'   => 'Analyse CV'],
                    ['href' => '/jobmington/ai/roast.php', 'tone' => 'orange', 'label' => 'CV Roast',
                     'icon'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>',
                     'title' => 'Honest feedback. No filter.',
                     'desc'  => 'Direct score, weak-point breakdown, and a sharper summary — before a recruiter sees it.',
                     'cta'   => 'Roast my CV'],
                ];
                foreach ($homeAiTools as $tool): ?>
                <a class="jm-ai-tool-card tone-<?= e($tool['tone']) ?>" href="<?= e($tool['href']) ?>">
                    <div class="jm-ai-tool-icon"><?= $tool['icon'] ?></div>
                    <div>
                        <span class="jm-ai-tool-label"><?= e($tool['label']) ?></span>
                        <h3><?= e($tool['title']) ?></h3>
                        <p><?= e($tool['desc']) ?></p>
                    </div>
                    <div class="jm-ai-tool-footer">
                        <span class="jm-ai-premium-badge">Premium</span>
                        <span class="jm-ai-tool-arrow">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                        </span>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- ── Active jobs ────────────────────────────────────────────── -->
        <section class="jm-hp-section jm-jobs-section">
            <div class="jm-home-jobs-layout">
                <div>
                    <div class="jm-hp-head">
                        <div>
                            <span class="jm-hp-kicker">Live Across Africa</span>
                            <h2 class="jm-hp-h">Roles hiring right now.</h2>
                        </div>
                        <a class="jm-hp-head-link" href="/jobmington/jobs/">
                            All jobs
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                        </a>
                    </div>
                    <?php if (empty($featuredJobs)): ?>
                        <div class="jm-panel" style="text-align:center;padding:48px;">
                            <p style="color:var(--jm-muted);margin:0 0 16px;">No listings yet. Be the first to post a role.</p>
                            <a class="jm-button" href="/jobmington/employer/post-job.php">Post the first job</a>
                        </div>
                    <?php else: ?>
                        <div class="jm-job-list">
                            <?php foreach ($featuredJobs as $job):
                                $location = trim(($job['city'] ?? '') . (($job['city'] ?? '') && ($job['country_name'] ?? '') ? ', ' : '') . ($job['country_name'] ?? ''));
                                $salary = !empty($job['show_salary'])
                                    ? formatSalaryRange($job['salary_min'] !== null ? (float)$job['salary_min'] : null, $job['salary_max'] !== null ? (float)$job['salary_max'] : null, $job['currency_symbol'] ?? null)
                                    : null;
                                $jobMeta = array_filter([$job['job_type'] ?? '', $job['category_name'] ?? '']);
                            ?>
                            <a class="jm-job-row" href="/jobmington/jobs/view.php?id=<?= (int)$job['job_id'] ?>">
                                <div>
                                    <strong><?= e($job['title']) ?></strong>
                                    <small class="jm-job-subtitle"><?= e($job['company_name']) ?> · <?= e($location ?: 'Remote') ?></small>
                                    <div class="jm-tag-row compact" style="margin-top:8px;">
                                        <?php foreach ($jobMeta as $tag): ?>
                                            <span class="jm-job-tag tone-blue"><?= e($tag) ?></span>
                                        <?php endforeach; ?>
                                        <?php if (!empty($job['is_featured'])): ?>
                                            <span class="jm-job-tag tone-orange">Featured</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <span><?= $salary ? e($salary) : '<span style="color:var(--jm-muted);font-size:13px;">Salary not listed</span>' ?></span>
                            </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Sidebar -->
                <aside class="jm-home-jobs-sidebar">
                    <h3>Employers</h3>
                    <p>Post a role and start receiving applications from African talent today.</p>
                    <div style="display:grid;gap:10px;margin-bottom:18px;">
                        <div style="display:flex;align-items:center;justify-content:space-between;font-size:14px;">
                            <span style="color:var(--jm-muted);">Single post</span>
                            <strong><?= jm_format_ngn(PRICE_EMPLOYER_SINGLE_POST) ?></strong>
                        </div>
                        <div style="display:flex;align-items:center;justify-content:space-between;font-size:14px;">
                            <span style="color:var(--jm-muted);">Basic plan</span>
                            <strong><?= jm_format_ngn(PRICE_EMPLOYER_BASIC_MONTHLY) ?>/mo</strong>
                        </div>
                        <div style="display:flex;align-items:center;justify-content:space-between;font-size:14px;">
                            <span style="color:var(--jm-muted);">Pro plan</span>
                            <strong><?= jm_format_ngn(PRICE_EMPLOYER_PRO_MONTHLY) ?>/mo</strong>
                        </div>
                    </div>
                    <a class="jm-button" href="/jobmington/employer/post-job.php" style="width:100%;justify-content:center;">Post a job</a>
                    <a href="/jobmington/pricing.php" style="display:block;text-align:center;margin-top:10px;font-size:13px;color:var(--jm-blue);text-decoration:none;font-weight:700;">See all plans →</a>
                </aside>
            </div>
        </section>

        <!-- ── Seeker / Employer pathways ─────────────────────────────── -->
        <section class="jm-hp-section">
            <div class="jm-hp-head" style="margin-bottom:28px;">
                <div>
                    <span class="jm-hp-kicker">Built for Both Sides</span>
                    <h2 class="jm-hp-h">Built for both sides of the hire.</h2>
                </div>
            </div>
            <div class="jm-pathways-2">
                <a class="jm-pathway-2" href="/jobmington/jobs/">
                    <span class="jm-pathway-tag">Job seekers</span>
                    <h2>Search less.<br>Land more.</h2>
                    <p>Browse roles by skill, country, and type. Apply without repeating yourself. Track every application in one place.</p>
                    <span class="jm-path-link">Find your role <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></span>
                </a>
                <a class="jm-pathway-2 accent" href="/jobmington/employer/">
                    <span class="jm-pathway-tag">Employers</span>
                    <h2>Post clearly.<br>Review faster.</h2>
                    <p>Publish roles, collect applications, and move candidates through hiring from one focused dashboard. No complexity.</p>
                    <span class="jm-path-link">Start hiring <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></span>
                </a>
            </div>
        </section>


        <!-- ── Testimonials ─────────────────────────────────────────── -->
        <section class="jm-testimonials-section">
            <div class="jm-testimonials-contents">
                <p class="jm-testimonials-eyebrow">Don't take our word for it.</p>
                <h2 class="jm-testimonials-title">Hired across Africa.</h2>
                <div class="jm-testimonials-cta-row">
                    <a class="jm-button" href="/jobmington/auth/register.php">Get started free</a>
                </div>
            </div>
            <div class="jm-testimonials-list-container">
                <div class="jm-testimonials-list">
            <blockquote class="jm-testimonial-card">
                <svg width="22" height="16" viewBox="0 0 22 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M21.3184 15.7266L12.2291 15.7266L12.2292 6.78143L15.2452 0.810937L19.7896 0.810938L16.8154 6.76061L21.3184 6.76102L21.3184 15.7266Z" fill="#f59f22"/><path d="M0.342431 15.7266L0.342432 6.7406L3.37879 0.810937L7.90291 0.810938L4.88683 6.76102L9.36955 6.76102L9.43164 6.82227L9.43164 15.7266L0.342431 15.7266Z" fill="#f59f22"/></svg>
                <p class="jm-testimonial-text">CV Roast showed me exactly why I was not getting callbacks. Two weeks after fixing my profile, I had three interviews lined up. I now work remotely for a London fintech.</p>
                <footer class="jm-testimonial-footer">
                    <cite class="jm-testimonial-cite">Chidi Okafor<span>Product Manager, Lagos</span></cite>
                    <img src="/jobmington/assets/images/testimonials/testimonial_2.jpg" alt="" class="jm-testimonial-avatar" width="44" height="44" loading="lazy">
                </footer>
            </blockquote>
            <blockquote class="jm-testimonial-card">
                <svg width="22" height="16" viewBox="0 0 22 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M21.3184 15.7266L12.2291 15.7266L12.2292 6.78143L15.2452 0.810937L19.7896 0.810938L16.8154 6.76061L21.3184 6.76102L21.3184 15.7266Z" fill="#f59f22"/><path d="M0.342431 15.7266L0.342432 6.7406L3.37879 0.810937L7.90291 0.810938L4.88683 6.76102L9.36955 6.76102L9.43164 6.82227L9.43164 15.7266L0.342431 15.7266Z" fill="#f59f22"/></svg>
                <p class="jm-testimonial-text">We posted a senior developer role on Thursday. By Friday evening we had twelve strong applications. The quality of talent on Jobmington genuinely surprised us.</p>
                <footer class="jm-testimonial-footer">
                    <cite class="jm-testimonial-cite">Amina Hassan<span>Head of People, Nairobi</span></cite>
                    <img src="/jobmington/assets/images/testimonials/testimonial_1.jpg" alt="" class="jm-testimonial-avatar" width="44" height="44" loading="lazy">
                </footer>
            </blockquote>
            <blockquote class="jm-testimonial-card">
                <svg width="22" height="16" viewBox="0 0 22 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M21.3184 15.7266L12.2291 15.7266L12.2292 6.78143L15.2452 0.810937L19.7896 0.810938L16.8154 6.76061L21.3184 6.76102L21.3184 15.7266Z" fill="#f59f22"/><path d="M0.342431 15.7266L0.342432 6.7406L3.37879 0.810937L7.90291 0.810938L4.88683 6.76102L9.36955 6.76102L9.43164 6.82227L9.43164 15.7266L0.342431 15.7266Z" fill="#f59f22"/></svg>
                <p class="jm-testimonial-text">Andika AI helped me write a cover letter for a remote role in under twenty minutes. I got the offer. Now I am earning in dollars from Accra — I never thought it was this simple.</p>
                <footer class="jm-testimonial-footer">
                    <cite class="jm-testimonial-cite">Ama Asante<span>Frontend Engineer, Accra</span></cite>
                    <img src="/jobmington/assets/images/testimonials/testimonial_3.jpg" alt="" class="jm-testimonial-avatar" width="44" height="44" loading="lazy">
                </footer>
            </blockquote>
            <blockquote class="jm-testimonial-card">
                <svg width="22" height="16" viewBox="0 0 22 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M21.3184 15.7266L12.2291 15.7266L12.2292 6.78143L15.2452 0.810937L19.7896 0.810938L16.8154 6.76061L21.3184 6.76102L21.3184 15.7266Z" fill="#f59f22"/><path d="M0.342431 15.7266L0.342432 6.7406L3.37879 0.810937L7.90291 0.810938L4.88683 6.76102L9.36955 6.76102L9.43164 6.82227L9.43164 15.7266L0.342431 15.7266Z" fill="#f59f22"/></svg>
                <p class="jm-testimonial-text">The AI matching flagged three roles I would never have searched for myself. One was a perfect fit. I now work with a London product studio fully remotely from Dakar.</p>
                <footer class="jm-testimonial-footer">
                    <cite class="jm-testimonial-cite">Fatima Diallo<span>UX Designer, Dakar</span></cite>
                    <img src="/jobmington/assets/images/testimonials/testimonial_4.jpg" alt="" class="jm-testimonial-avatar" width="44" height="44" loading="lazy">
                </footer>
            </blockquote>
            <blockquote class="jm-testimonial-card">
                <svg width="22" height="16" viewBox="0 0 22 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M21.3184 15.7266L12.2291 15.7266L12.2292 6.78143L15.2452 0.810937L19.7896 0.810938L16.8154 6.76061L21.3184 6.76102L21.3184 15.7266Z" fill="#f59f22"/><path d="M0.342431 15.7266L0.342432 6.7406L3.37879 0.810937L7.90291 0.810938L4.88683 6.76102L9.36955 6.76102L9.43164 6.82227L9.43164 15.7266L0.342431 15.7266Z" fill="#f59f22"/></svg>
                <p class="jm-testimonial-text">My ATS score was 41 when I joined. CV Optimiser showed me exactly what was missing. Three weeks later it hit 87 and interview calls started coming in consistently.</p>
                <footer class="jm-testimonial-footer">
                    <cite class="jm-testimonial-cite">Emeka Nwachukwu<span>Data Analyst, Abuja</span></cite>
                    <img src="/jobmington/assets/images/testimonials/testimonial_5.jpg" alt="" class="jm-testimonial-avatar" width="44" height="44" loading="lazy">
                </footer>
            </blockquote>
            <blockquote class="jm-testimonial-card">
                <svg width="22" height="16" viewBox="0 0 22 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M21.3184 15.7266L12.2291 15.7266L12.2292 6.78143L15.2452 0.810937L19.7896 0.810938L16.8154 6.76061L21.3184 6.76102L21.3184 15.7266Z" fill="#f59f22"/><path d="M0.342431 15.7266L0.342432 6.7406L3.37879 0.810937L7.90291 0.810938L4.88683 6.76102L9.36955 6.76102L9.43164 6.82227L9.43164 15.7266L0.342431 15.7266Z" fill="#f59f22"/></svg>
                <p class="jm-testimonial-text">We hired two engineers through Jobmington inside a fortnight. Applications were detailed and candidates came prepared. Best hire rate we have had on any platform yet.</p>
                <footer class="jm-testimonial-footer">
                    <cite class="jm-testimonial-cite">Zainab Mensah<span>Talent Lead, Kumasi</span></cite>
                    <img src="/jobmington/assets/images/testimonials/testimonial_6.jpg" alt="" class="jm-testimonial-avatar" width="44" height="44" loading="lazy">
                </footer>
            </blockquote>
            <blockquote class="jm-testimonial-card" aria-hidden="true">
                <svg width="22" height="16" viewBox="0 0 22 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M21.3184 15.7266L12.2291 15.7266L12.2292 6.78143L15.2452 0.810937L19.7896 0.810938L16.8154 6.76061L21.3184 6.76102L21.3184 15.7266Z" fill="#f59f22"/><path d="M0.342431 15.7266L0.342432 6.7406L3.37879 0.810937L7.90291 0.810938L4.88683 6.76102L9.36955 6.76102L9.43164 6.82227L9.43164 15.7266L0.342431 15.7266Z" fill="#f59f22"/></svg>
                <p class="jm-testimonial-text">CV Roast showed me exactly why I was not getting callbacks. Two weeks after fixing my profile, I had three interviews lined up. I now work remotely for a London fintech.</p>
                <footer class="jm-testimonial-footer">
                    <cite class="jm-testimonial-cite">Chidi Okafor<span>Product Manager, Lagos</span></cite>
                    <img src="/jobmington/assets/images/testimonials/testimonial_2.jpg" alt="" class="jm-testimonial-avatar" width="44" height="44" loading="lazy">
                </footer>
            </blockquote>
            <blockquote class="jm-testimonial-card" aria-hidden="true">
                <svg width="22" height="16" viewBox="0 0 22 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M21.3184 15.7266L12.2291 15.7266L12.2292 6.78143L15.2452 0.810937L19.7896 0.810938L16.8154 6.76061L21.3184 6.76102L21.3184 15.7266Z" fill="#f59f22"/><path d="M0.342431 15.7266L0.342432 6.7406L3.37879 0.810937L7.90291 0.810938L4.88683 6.76102L9.36955 6.76102L9.43164 6.82227L9.43164 15.7266L0.342431 15.7266Z" fill="#f59f22"/></svg>
                <p class="jm-testimonial-text">We posted a senior developer role on Thursday. By Friday evening we had twelve strong applications. The quality of talent on Jobmington genuinely surprised us.</p>
                <footer class="jm-testimonial-footer">
                    <cite class="jm-testimonial-cite">Amina Hassan<span>Head of People, Nairobi</span></cite>
                    <img src="/jobmington/assets/images/testimonials/testimonial_1.jpg" alt="" class="jm-testimonial-avatar" width="44" height="44" loading="lazy">
                </footer>
            </blockquote>
            <blockquote class="jm-testimonial-card" aria-hidden="true">
                <svg width="22" height="16" viewBox="0 0 22 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M21.3184 15.7266L12.2291 15.7266L12.2292 6.78143L15.2452 0.810937L19.7896 0.810938L16.8154 6.76061L21.3184 6.76102L21.3184 15.7266Z" fill="#f59f22"/><path d="M0.342431 15.7266L0.342432 6.7406L3.37879 0.810937L7.90291 0.810938L4.88683 6.76102L9.36955 6.76102L9.43164 6.82227L9.43164 15.7266L0.342431 15.7266Z" fill="#f59f22"/></svg>
                <p class="jm-testimonial-text">Andika AI helped me write a cover letter for a remote role in under twenty minutes. I got the offer. Now I am earning in dollars from Accra — I never thought it was this simple.</p>
                <footer class="jm-testimonial-footer">
                    <cite class="jm-testimonial-cite">Ama Asante<span>Frontend Engineer, Accra</span></cite>
                    <img src="/jobmington/assets/images/testimonials/testimonial_3.jpg" alt="" class="jm-testimonial-avatar" width="44" height="44" loading="lazy">
                </footer>
            </blockquote>
            <blockquote class="jm-testimonial-card" aria-hidden="true">
                <svg width="22" height="16" viewBox="0 0 22 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M21.3184 15.7266L12.2291 15.7266L12.2292 6.78143L15.2452 0.810937L19.7896 0.810938L16.8154 6.76061L21.3184 6.76102L21.3184 15.7266Z" fill="#f59f22"/><path d="M0.342431 15.7266L0.342432 6.7406L3.37879 0.810937L7.90291 0.810938L4.88683 6.76102L9.36955 6.76102L9.43164 6.82227L9.43164 15.7266L0.342431 15.7266Z" fill="#f59f22"/></svg>
                <p class="jm-testimonial-text">The AI matching flagged three roles I would never have searched for myself. One was a perfect fit. I now work with a London product studio fully remotely from Dakar.</p>
                <footer class="jm-testimonial-footer">
                    <cite class="jm-testimonial-cite">Fatima Diallo<span>UX Designer, Dakar</span></cite>
                    <img src="/jobmington/assets/images/testimonials/testimonial_4.jpg" alt="" class="jm-testimonial-avatar" width="44" height="44" loading="lazy">
                </footer>
            </blockquote>
            <blockquote class="jm-testimonial-card" aria-hidden="true">
                <svg width="22" height="16" viewBox="0 0 22 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M21.3184 15.7266L12.2291 15.7266L12.2292 6.78143L15.2452 0.810937L19.7896 0.810938L16.8154 6.76061L21.3184 6.76102L21.3184 15.7266Z" fill="#f59f22"/><path d="M0.342431 15.7266L0.342432 6.7406L3.37879 0.810937L7.90291 0.810938L4.88683 6.76102L9.36955 6.76102L9.43164 6.82227L9.43164 15.7266L0.342431 15.7266Z" fill="#f59f22"/></svg>
                <p class="jm-testimonial-text">My ATS score was 41 when I joined. CV Optimiser showed me exactly what was missing. Three weeks later it hit 87 and interview calls started coming in consistently.</p>
                <footer class="jm-testimonial-footer">
                    <cite class="jm-testimonial-cite">Emeka Nwachukwu<span>Data Analyst, Abuja</span></cite>
                    <img src="/jobmington/assets/images/testimonials/testimonial_5.jpg" alt="" class="jm-testimonial-avatar" width="44" height="44" loading="lazy">
                </footer>
            </blockquote>
            <blockquote class="jm-testimonial-card" aria-hidden="true">
                <svg width="22" height="16" viewBox="0 0 22 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M21.3184 15.7266L12.2291 15.7266L12.2292 6.78143L15.2452 0.810937L19.7896 0.810938L16.8154 6.76061L21.3184 6.76102L21.3184 15.7266Z" fill="#f59f22"/><path d="M0.342431 15.7266L0.342432 6.7406L3.37879 0.810937L7.90291 0.810938L4.88683 6.76102L9.36955 6.76102L9.43164 6.82227L9.43164 15.7266L0.342431 15.7266Z" fill="#f59f22"/></svg>
                <p class="jm-testimonial-text">We hired two engineers through Jobmington inside a fortnight. Applications were detailed and candidates came prepared. Best hire rate we have had on any platform yet.</p>
                <footer class="jm-testimonial-footer">
                    <cite class="jm-testimonial-cite">Zainab Mensah<span>Talent Lead, Kumasi</span></cite>
                    <img src="/jobmington/assets/images/testimonials/testimonial_6.jpg" alt="" class="jm-testimonial-avatar" width="44" height="44" loading="lazy">
                </footer>
            </blockquote>
                </div>
            </div>
        </section>

        <!-- ── Premium upsell strip ───────────────────────────────────── -->
        <section class="jm-hp-section">
            <div class="jm-premium-strip">
                <canvas id="jm-premium-canvas" aria-hidden="true"></canvas>
                <div>
                    <h2>Unlock the AI tools.<br>Stand out from the first application.</h2>
                    <p>CV optimisation, cover letter generation, interview prep, and job matching — all in one Premium membership.</p>
                    <span class="jm-premium-strip-price">From <?= jm_format_ngn(PRICE_SEEKER_PREMIUM_MONTHLY) ?>/month · Credits from <?= jm_format_ngn(PRICE_CREDITS_SINGLE) ?></span>
                </div>
                <div class="jm-premium-strip-actions">
                    <a class="jm-button" href="/jobmington/payments/seeker-premium.php">Go Premium</a>
                    <a class="jm-button secondary" href="/jobmington/pricing.php?tab=seeker">See what's included</a>
                </div>
            </div>
        </section>

        <!-- ── Categories ────────────────────────────────────────────── -->
        <?php if (!empty($categories)): ?>
        <section class="jm-hp-section">
            <div class="jm-hp-head">
                <div>
                    <span class="jm-hp-kicker">Explore</span>
                    <h2 class="jm-hp-h">Browse by category.</h2>
                </div>
                <a class="jm-hp-head-link" href="/jobmington/jobs/">All categories <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></a>
            </div>
            <div class="jm-cat-grid">
                <?php foreach ($categories as $cat): ?>
                <a class="jm-cat-chip" href="/jobmington/jobs/?category=<?= e($cat['slug'] ?: $cat['category_id']) ?>">
                    <strong><?= e($cat['name']) ?></strong>
                    <span><?= number_format((int)$cat['live_jobs']) ?> active <?= (int)$cat['live_jobs'] === 1 ? 'job' : 'jobs' ?></span>
                </a>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <?php jm_minimal_footer(); ?>
<script>
(function () {
    'use strict';
    const canvas = document.getElementById('jm-premium-canvas');
    if (!canvas) return;
    const ctx = canvas.getContext('2d');

    /* — config mirroring the Antigravity component props — */
    const COLOR        = '#b5bbc4';
    const COUNT        = 600;
    const WAVE_SPEED   = 1.1;
    const WAVE_AMP     = 5;
    const PSIZE        = 1.2;   /* particleSize (scaled up from 0.5 for canvas) */
    const LERP         = 0.06;
    const FIELD        = 70;    /* fieldStrength radius */
    const DEPTH        = 2.6;   /* depthFactor — capsule length multiplier */
    const PULSE_SPEED  = 3;
    const ROT_SPEED    = 0.3;

    let W = 0, H = 0, t = 0, raf = null;
    const mouse = { x: -9999, y: -9999 };
    let particles = [];

    function resize() {
        const strip = canvas.parentElement;
        W = canvas.width  = strip.offsetWidth;
        H = canvas.height = strip.offsetHeight;
        spawnParticles();
    }

    function spawnParticles() {
        particles = [];
        for (let i = 0; i < COUNT; i++) {
            const x = Math.random() * W;
            const y = Math.random() * H;
            particles.push({
                ox: x, oy: y, x, y,
                depth:    0.25 + Math.random() * 0.75,
                phase:    Math.random() * Math.PI * 2,
                rotAngle: Math.random() * Math.PI,
                rotDir:   Math.random() < 0.5 ? 1 : -1,
            });
        }
    }

    /* draw a capsule (elongated pill) centred at origin */
    function capsule(r, len) {
        if (len <= r * 2) { ctx.arc(0, 0, r, 0, Math.PI * 2); return; }
        const h = len / 2;
        ctx.moveTo(-h + r, -r);
        ctx.lineTo( h - r, -r);
        ctx.arc( h - r, 0, r, -Math.PI / 2,  Math.PI / 2);
        ctx.lineTo(-h + r,  r);
        ctx.arc(-h + r, 0, r,  Math.PI / 2,  Math.PI * 1.5);
        ctx.closePath();
    }

    function frame() {
        ctx.clearRect(0, 0, W, H);
        t += 0.016;

        for (const p of particles) {
            /* wave drift around home position */
            const wx = Math.sin(t * WAVE_SPEED       + p.ox * 0.009 + p.phase) * WAVE_AMP * p.depth;
            const wy = Math.cos(t * WAVE_SPEED * 0.8 + p.oy * 0.009 + p.phase) * WAVE_AMP * p.depth;
            let tx = p.ox + wx;
            let ty = p.oy + wy;

            /* mouse antigravity repulsion */
            const dx   = p.x - mouse.x;
            const dy   = p.y - mouse.y;
            const dist = Math.hypot(dx, dy) || 1;
            if (dist < FIELD) {
                const force = Math.pow((FIELD - dist) / FIELD, 1.5) * FIELD * 0.9;
                tx += (dx / dist) * force;
                ty += (dy / dist) * force;
            }

            /* lerp toward target */
            p.x += (tx - p.x) * LERP;
            p.y += (ty - p.y) * LERP;

            /* size: depth × pulse */
            const pulse = 1 + 0.2 * Math.sin(t * PULSE_SPEED + p.phase);
            const r     = Math.max(PSIZE * p.depth * pulse, 0.4);
            const len   = r * DEPTH;

            /* slow individual rotation */
            p.rotAngle += ROT_SPEED * 0.008 * p.depth * p.rotDir;

            ctx.save();
            ctx.translate(p.x, p.y);
            ctx.rotate(p.rotAngle);
            ctx.globalAlpha = 0.12 + p.depth * 0.45;
            ctx.fillStyle   = COLOR;
            ctx.beginPath();
            capsule(r, len);
            ctx.fill();
            ctx.restore();
        }

        raf = requestAnimationFrame(frame);
    }

    /* mouse tracking against the visible canvas area */
    const section = canvas.closest('section') || document.body;
    section.addEventListener('mousemove', e => {
        const rect = canvas.getBoundingClientRect();
        mouse.x = e.clientX - rect.left;
        mouse.y = e.clientY - rect.top;
    });
    section.addEventListener('mouseleave', () => { mouse.x = -9999; mouse.y = -9999; });

    /* pause animation when scrolled out of view */
    new IntersectionObserver(([entry]) => {
        if (entry.isIntersecting) { if (!raf) raf = requestAnimationFrame(frame); }
        else { cancelAnimationFrame(raf); raf = null; }
    }).observe(canvas);

    window.addEventListener('resize', resize);
    resize();
    raf = requestAnimationFrame(frame);
})();
</script>
    </div>
</body>
</html>
