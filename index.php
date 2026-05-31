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

        <!-- ── Identity bar ─────────────────────────────────────────── -->
        <div class="jm-identity-bar">
            <div class="jm-identity-bar-item">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 8v4l3 3"/></svg>
                <span>Remote-first</span>
            </div>
            <div class="jm-identity-bar-item">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2L2 7l10 5 10-5-10-5M2 17l10 5 10-5M2 12l10 5 10-5"/></svg>
                <span><strong>AI-powered</strong> career tools</span>
            </div>
            <div class="jm-identity-bar-item">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                <span>Built for <strong>Africa</strong></span>
            </div>
            <div class="jm-identity-bar-item">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                <span>Earn in <strong>USD</strong></span>
            </div>
        </div>

        <!-- ── Hero ──────────────────────────────────────────────────── -->
        <section class="jm-hero jm-home-hero" style="grid-template-columns:minmax(0,1.1fr) minmax(300px,420px);padding:60px 0 72px;border-bottom:1px solid var(--jm-line);">
            <div class="jm-hero-copy">
                <p class="jm-kicker" style="font-size:12px;letter-spacing:.1em;text-transform:uppercase;font-weight:800;margin-bottom:20px;">Africa's career platform</p>
                <h1 class="jm-hero-headline">Africa's talent.<br>The world's <span class="jm-signature-word">opportunities.</span></h1>
                <p class="jm-hero-sub">Find remote jobs that pay in dollars, unlock AI tools to sharpen your applications, and build a career without borders — from anywhere in Africa.</p>

                <div class="jm-hero-actions">
                    <?php if ($isLoggedIn): ?>
                        <a class="jm-button" href="<?= e($dashboardUrl) ?>">Go to dashboard</a>
                        <a class="jm-button secondary" href="/jobmington/jobs/">Browse jobs</a>
                    <?php else: ?>
                        <a class="jm-button" href="/jobmington/auth/register.php">Create free account</a>
                        <a class="jm-button secondary" href="/jobmington/jobs/">Browse jobs</a>
                    <?php endif; ?>
                </div>

                <!-- Search -->
                <form class="jm-home-search" action="/jobmington/jobs/search.php" method="get">
                    <label class="jm-sr-only" for="home-q">Job title, skill, or company</label>
                    <input class="jm-input" id="home-q" type="search" name="q" placeholder="Job title, skill, or keyword…">
                    <select class="jm-select" name="type">
                        <option value="">Any type</option>
                        <?php foreach (JOB_TYPES as $type): ?>
                            <option value="<?= e($type) ?>"><?= e($type) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button class="jm-home-search-btn" type="submit" aria-label="Search jobs">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    </button>
                </form>

                <div class="jm-popular-searches">
                    <span>Popular</span>
                    <?php foreach ($popularSearches as [$label, $url]): ?>
                        <a href="<?= e($url) ?>"><?= e($label) ?></a>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Stats panel -->
            <div class="jm-hero-panel">
                <div class="jm-stat-board">
                    <!-- Hero stat: open jobs -->
                    <div class="jm-stat-board-top">
                        <span class="jm-stat-live">Live now</span>
                        <strong class="jm-stat-big-num"><?= number_format((int)($stats['Open jobs'] ?? 0)) ?></strong>
                        <span class="jm-stat-big-label">open roles across Africa</span>
                    </div>
                    <!-- Supporting stats -->
                    <div class="jm-stat-row">
                        <div class="jm-stat-cell">
                            <strong><?= number_format((int)($stats['Hiring companies'] ?? 0)) ?></strong>
                            <span>Companies</span>
                        </div>
                        <div class="jm-stat-cell">
                            <strong><?= number_format((int)($stats['Remote roles'] ?? 0)) ?></strong>
                            <span>Remote</span>
                        </div>
                        <div class="jm-stat-cell">
                            <strong><?= number_format((int)($stats['Categories'] ?? 0)) ?></strong>
                            <span>Categories</span>
                        </div>
                    </div>
                    <!-- Employer CTA -->
                    <a class="jm-stat-board-cta" href="/jobmington/employer/post-job.php">
                        <div class="jm-stat-board-cta-copy">
                            <strong>Post a job</strong>
                            <span>From <?= jm_format_ngn(PRICE_EMPLOYER_SINGLE_POST) ?></span>
                        </div>
                        <div class="jm-stat-board-cta-arrow">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                        </div>
                    </a>
                </div>
            </div>
        </section>

        <!-- ── AI Tools ───────────────────────────────────────────────── -->
        <section class="jm-hp-section">
            <div class="jm-hp-head">
                <div>
                    <span class="jm-hp-kicker">Andika career intelligence</span>
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
                     'title' => 'Your AI career coach.',
                     'desc'  => 'Ask anything — cover letter help, interview prep, salary advice. Powered by Llama 3.2.',
                     'cta'   => 'Open Andika'],
                    ['href' => '/jobmington/cv-builder/', 'tone' => 'green', 'label' => 'CV Optimiser',
                     'title' => 'Know your ATS score before employers do.',
                     'desc'  => 'Get a detailed scan, missing keywords, and rewrite suggestions that actually improve your chances.',
                     'cta'   => 'Analyse CV'],
                    ['href' => '/jobmington/ai/roast.php', 'tone' => 'orange', 'label' => 'CV Roast',
                     'title' => 'Honest feedback. No filter.',
                     'desc'  => 'Direct score, weak-point breakdown, and a sharper summary — before a recruiter sees it.',
                     'cta'   => 'Roast my CV'],
                ];
                foreach ($homeAiTools as $tool): ?>
                <a class="jm-ai-tool-card tone-<?= e($tool['tone']) ?>" href="<?= e($tool['href']) ?>">
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
        <section class="jm-hp-section">
            <div class="jm-home-jobs-layout">
                <div>
                    <div class="jm-hp-head">
                        <div>
                            <span class="jm-hp-kicker">Live listings</span>
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
                    <span class="jm-hp-kicker">Who it's for</span>
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

        <!-- ── Premium upsell strip ───────────────────────────────────── -->
        <section class="jm-hp-section">
            <div class="jm-premium-strip">
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
    </div>
</body>
</html>
