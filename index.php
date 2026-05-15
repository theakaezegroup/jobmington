<?php
define('JOBMINGTON', true);
require_once __DIR__ . '/config/env.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/functions.php';

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
    <meta name="description" content="Jobmington is a simple African job board for finding jobs, posting roles, and managing applications without the noise.">
    <link rel="canonical" href="/jobmington/">
    <title><?= e($pageTitle) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Satisfy&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/jobmington/assets/css/minimal-jobmington.css?v=home-heroo-1">
</head>
<body class="jm-minimal jm-home-page">
    <div class="jm-shell">
        <header class="jm-header">
            <a class="jm-logo" href="/jobmington/">
                <img src="/jobmington/assets/images/badge.png" alt="">
                <span>Jobmington</span>
            </a>
            <nav class="jm-nav" aria-label="Main navigation">
                <a href="/jobmington/jobs/">Find jobs</a>
                <a href="/jobmington/cv-builder/">CV Builder</a>
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

        <section class="jm-strip-carousel" aria-label="Jobmington updates" data-strip-slider>
            <div class="jm-strip-banner">
                <div class="jm-strip-window">
                    <div class="jm-strip-track" data-strip-track>
                        <article class="jm-strip-item is-active">
                            <span class="jm-strip-icon" aria-hidden="true">
                                <svg viewBox="0 0 48 48" role="presentation" focusable="false">
                                    <path d="M16 8h12l8 8v24H16z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                                    <path d="M28 8v9h8M21 24h10M21 30h8" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                    <path d="M12 18l2.2 4.4L19 24l-4.8 1.6L12 30l-2.2-4.4L5 24l4.8-1.6z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
                                </svg>
                            </span>
                            <p><strong>For job seekers</strong> Skip the long forms. Build a clean CV, complete your profile, and apply faster.</p>
                            <a href="/jobmington/cv-builder/">Start now</a>
                        </article>
                        <article class="jm-strip-item">
                            <span class="jm-strip-icon" aria-hidden="true">
                                <svg viewBox="0 0 48 48" role="presentation" focusable="false">
                                    <path d="M9 39h30M14 39V14h20v25" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                    <path d="M18 19h4M26 19h4M18 25h4M26 25h4M18 31h4M26 31h4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                    <path d="M24 9c3 0 5.5 2.5 5.5 5.5H18.5C18.5 11.5 21 9 24 9Z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
                                    <path d="M35 12l2-3 2 3 3 2-3 2-2 3-2-3-3-2z" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/>
                                </svg>
                            </span>
                            <p><strong>For employers</strong> Post a role, choose visibility, and track applications from one workspace.</p>
                            <a href="/jobmington/employer/post-job.php">Post a job</a>
                        </article>
                        <article class="jm-strip-item">
                            <span class="jm-strip-icon" aria-hidden="true">
                                <svg viewBox="0 0 48 48" role="presentation" focusable="false">
                                    <path d="M24 38c7.7 0 14-6.3 14-14S31.7 10 24 10 10 16.3 10 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                    <path d="M10 24c0 7.7 6.3 14 14 14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-dasharray="2 5"/>
                                    <path d="M18 24l4 4 8-9" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
                                    <path d="M38 8l1.6 3.4L43 13l-3.4 1.6L38 18l-1.6-3.4L33 13l3.4-1.6z" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/>
                                </svg>
                            </span>
                            <p><strong>Fresh roles</strong> New jobs are flowing in through curated feeds while the platform grows.</p>
                            <a href="/jobmington/jobs/">Browse jobs</a>
                        </article>
                    </div>
                </div>
                <div class="jm-strip-controls" aria-label="Choose update">
                    <button class="jm-strip-arrow jm-strip-prev" type="button" aria-label="Previous update" data-strip-prev></button>
                    <div class="jm-strip-dots">
                        <button class="is-active" type="button" aria-label="Show job seeker update" aria-current="true" data-strip-dot></button>
                        <button type="button" aria-label="Show employer update" data-strip-dot></button>
                        <button type="button" aria-label="Show fresh jobs update" data-strip-dot></button>
                    </div>
                    <button class="jm-strip-arrow jm-strip-next" type="button" aria-label="Next update" data-strip-next></button>
                </div>
            </div>
        </section>

        <section class="jm-hero jm-home-hero">
            <div class="jm-hero-copy">
                <p class="jm-kicker">African talent marketplace</p>
                <h1>Find work that <span class="jm-signature-word">fits</span>. Hire talent that moves.</h1>
                <p>Jobmington brings active roles, direct applications, and simple hiring tools into one calm workspace for seekers and employers.</p>

                <form class="jm-search jm-hero-search" action="/jobmington/jobs/search.php" method="get">
                    <div class="jm-hero-search-main">
                        <label class="jm-sr-only" for="home-q">Job title, skill, or company</label>
                        <input class="jm-input" id="home-q" type="search" name="q" placeholder="Job title, skill, or company">
                    </div>

                    <div class="jm-hero-search-filters">
                        <label class="jm-filter-control" for="home-country">
                            <span>Country</span>
                            <select class="jm-select" id="home-country" name="country_id">
                                <option value="">All countries</option>
                                <?php foreach ($countries as $country): ?>
                                    <option value="<?= (int) $country['country_id'] ?>"><?= e($country['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>

                        <label class="jm-filter-control" for="home-type">
                            <span>Type</span>
                            <select class="jm-select" id="home-type" name="type">
                                <option value="">Any type</option>
                                <?php foreach (JOB_TYPES as $type): ?>
                                    <option value="<?= e($type) ?>"><?= e($type) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>

                        <button class="jm-button" type="submit">Find jobs</button>
                    </div>
                </form>

                <div class="jm-home-actions">
                    <a class="jm-button secondary" href="/jobmington/auth/register.php">Create profile</a>
                    <a class="jm-muted-link" href="/jobmington/employer/post-job.php">Post a job</a>
                </div>

                <div class="jm-search-links" aria-label="Popular job searches">
                    <span>Popular searches</span>
                    <?php foreach ($popularSearches as [$label, $url]): ?>
                        <a href="<?= e($url) ?>"><?= e($label) ?></a>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="jm-hero-media">
                <img src="https://images.unsplash.com/photo-1758611972515-23399a8786df?auto=format&fit=crop&w=1200&q=80" alt="Woman working on a laptop in a modern office">
            </div>
        </section>

        <section class="jm-section jm-home-stats">
            <div class="jm-stat-grid">
                <?php foreach ($stats as $label => $value): ?>
                    <?php $statMeta = $statCards[$label] ?? ['tone' => 'blue', 'icon' => '']; ?>
                    <div class="jm-stat jm-home-stat jm-home-stat-<?= e($statMeta['tone']) ?>">
                        <span class="jm-home-stat-icon"><?= $statMeta['icon'] ?></span>
                        <span class="jm-home-stat-copy">
                            <strong><?= number_format((int) $value) ?></strong>
                            <span class="jm-small"><?= e($label) ?></span>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="jm-section jm-pathways">
            <div class="jm-pathway-grid">
                <a class="jm-pathway-card" href="/jobmington/jobs/">
                    <span class="jm-pathway-image">
                        <img src="https://images.unsplash.com/photo-1758874385393-3ef15b394a86?auto=format&fit=crop&w=900&q=80" alt="Professional writing notes while working on a laptop" loading="lazy">
                    </span>
                    <span class="jm-pathway-label">For job seekers</span>
                    <h2>Search less. Apply better.</h2>
                    <p>Find roles by skill, country, category, and work style, then apply without repeating your details each time.</p>
                </a>
                <a class="jm-pathway-card accent" href="/jobmington/employer/">
                    <span class="jm-pathway-image">
                        <img src="https://images.unsplash.com/photo-1758520144437-f068ecaf0d83?auto=format&fit=crop&w=900&q=80" alt="Recruiter interviewing a candidate at an office desk" loading="lazy">
                    </span>
                    <span class="jm-pathway-label">For employers</span>
                    <h2>Post clearly. Review faster.</h2>
                    <p>Publish roles, collect applications, and move candidates through hiring from one focused dashboard.</p>
                </a>
            </div>
        </section>

        <section class="jm-section jm-photo-story" aria-label="Job search and hiring moments">
            <div class="jm-section-head">
                <div>
                    <p class="jm-kicker">The journey</p>
                    <h2>From search to start.</h2>
                    <p>Real moments from the path between finding a role and moving work forward.</p>
                </div>
            </div>
            <div class="jm-slider" data-jm-slider>
                <div class="jm-slider-frame" tabindex="0" aria-label="Career journey carousel">
                    <div class="jm-image-slider" aria-label="Career journey image slides" aria-live="polite" data-slider-track>
                        <figure class="jm-photo-slide">
                            <img src="https://images.unsplash.com/photo-1775567291821-a0edcff11b72?auto=format&fit=crop&w=1200&q=80" alt="Team presenting in a modern meeting room" loading="lazy">
                            <figcaption><strong>Discover</strong><span>Start with roles that fit your work style.</span></figcaption>
                        </figure>
                        <figure class="jm-photo-slide">
                            <img src="https://images.unsplash.com/photo-1698047682091-782b1e5c6536?auto=format&fit=crop&w=1200&q=80" alt="Candidate shaking hands across an interview table" loading="lazy">
                            <figcaption><strong>Connect</strong><span>Turn applications into meaningful conversations.</span></figcaption>
                        </figure>
                        <figure class="jm-photo-slide">
                            <img src="https://images.unsplash.com/photo-1758691737568-a1572060ce5a?auto=format&fit=crop&w=1200&q=80" alt="Team having an office planning conversation" loading="lazy">
                            <figcaption><strong>Align</strong><span>Keep hiring conversations tied to the role.</span></figcaption>
                        </figure>
                        <figure class="jm-photo-slide">
                            <img src="https://images.unsplash.com/photo-1522202176988-66273c2fd55f?auto=format&fit=crop&w=1200&q=80" alt="Colleagues laughing together during a work session" loading="lazy">
                            <figcaption><strong>Culture</strong><span>See the people behind each opportunity.</span></figcaption>
                        </figure>
                        <figure class="jm-photo-slide">
                            <img src="https://images.unsplash.com/photo-1515378791036-0648a3ef77b2?auto=format&fit=crop&w=1200&q=80" alt="Person working on a laptop at a desk" loading="lazy">
                            <figcaption><strong>Remote</strong><span>Compare flexible roles without extra noise.</span></figcaption>
                        </figure>
                        <figure class="jm-photo-slide">
                            <img src="https://images.unsplash.com/photo-1516534775068-ba3e7458af70?auto=format&fit=crop&w=1200&q=80" alt="Professional focused at a desktop workspace" loading="lazy">
                            <figcaption><strong>Start</strong><span>Move from search to the work itself.</span></figcaption>
                        </figure>
                    </div>
                </div>
                <div class="jm-slider-controls">
                    <button class="jm-slider-button" type="button" data-slider-prev aria-label="Previous slide">&larr;</button>
                    <div class="jm-slider-dots" data-slider-dots aria-label="Slide controls"></div>
                    <button class="jm-slider-button" type="button" data-slider-next aria-label="Next slide">&rarr;</button>
                </div>
            </div>
        </section>

        <section class="jm-section jm-workflow-section">
            <div class="jm-workflow-band">
                <div class="jm-section-head jm-workflow-head">
                    <div>
                        <p class="jm-kicker">How it works</p>
                        <h2>One flow for both sides.</h2>
                        <p>Jobmington keeps the work focused: search, apply, review, and hire.</p>
                    </div>
                </div>

                <div class="jm-workflow-layout">
                    <div class="jm-workflow-visual">
                        <img src="https://images.unsplash.com/photo-1556761175-b413da4baf72?auto=format&fit=crop&w=900&q=80" alt="Professionals reviewing hiring work around a laptop" loading="lazy">
                        <div class="jm-workflow-caption">
                            <strong>Search / Apply / Review / Hire</strong>
                            <span>A simple path, without turning the homepage into a control room.</span>
                        </div>
                    </div>

                    <div class="jm-workflow">
                        <div class="jm-workflow-column">
                            <h3>For job seekers</h3>
                            <?php foreach ($jobSeekerSteps as $index => [$title, $description]): ?>
                                <div class="jm-step">
                                    <span><?= $index + 1 ?></span>
                                    <div>
                                        <strong><?= e($title) ?></strong>
                                        <p><?= e($description) ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="jm-workflow-column accent">
                            <h3>For employers</h3>
                            <?php foreach ($employerSteps as $index => [$title, $description]): ?>
                                <div class="jm-step">
                                    <span><?= $index + 1 ?></span>
                                    <div>
                                        <strong><?= e($title) ?></strong>
                                        <p><?= e($description) ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="jm-section jm-roles-section">
            <div class="jm-roles-layout">
                <div class="jm-roles-intro">
                    <div>
                        <p class="jm-kicker">Active roles</p>
                        <h2>Roles hiring now.</h2>
                        <p>Open listings currently accepting applications through Jobmington.</p>
                        <a class="jm-muted-link" href="/jobmington/jobs/">Browse all</a>
                    </div>
                    <div class="jm-roles-photo">
                        <img src="https://images.unsplash.com/photo-1497366754035-f200968a6e72?auto=format&fit=crop&w=900&q=80" alt="Modern workspace where open roles are being reviewed" loading="lazy">
                    </div>
                </div>

                <div class="jm-roles-list-wrap">
                    <?php if (empty($featuredJobs)): ?>
                        <div class="jm-empty">
                            <h2>No jobs yet</h2>
                            <p>Employer listings will appear here when they are published.</p>
                            <a class="jm-button" href="/jobmington/employer/post-job.php">Post the first job</a>
                        </div>
                    <?php else: ?>
                        <div class="jm-job-list">
                            <?php foreach ($featuredJobs as $job): ?>
                                <?php
                                    $location = trim(($job['city'] ?? '') . (($job['city'] ?? '') && ($job['country_name'] ?? '') ? ', ' : '') . ($job['country_name'] ?? ''));
                                    $salary = !empty($job['show_salary'])
                                        ? formatSalaryRange($job['salary_min'] !== null ? (float) $job['salary_min'] : null, $job['salary_max'] !== null ? (float) $job['salary_max'] : null, $job['currency_symbol'] ?? null)
                                        : 'Salary not listed';
                                    $jobMeta = array_filter([$job['job_type'] ?? '', $job['category_name'] ?? '']);
                                    $jobMetaLabel = !empty($jobMeta) ? implode(' / ', $jobMeta) : 'Active listing';
                                ?>
                                <a class="jm-job-row" href="/jobmington/jobs/view.php?id=<?= (int) $job['job_id'] ?>">
                                    <strong>
                                        <?= e($job['title']) ?>
                                        <small class="jm-job-subtitle">
                                            <?= e($job['company_name']) ?> / <?= e($location !== '' ? $location : 'Remote') ?>
                                        </small>
                                    </strong>
                                    <span><?= e($salary) ?><br><?= e($jobMetaLabel) ?></span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <?php if (!empty($categories)): ?>
        <section class="jm-section jm-category-section">
            <div class="jm-section-head">
                <div>
                    <h2>Explore by category.</h2>
                    <p>Jump into areas with active hiring.</p>
                </div>
            </div>
            <div class="jm-grid-3 jm-category-grid">
                <?php foreach ($categories as $categoryIndex => $category): ?>
                    <?php $categoryImage = $categoryImages[$categoryIndex % count($categoryImages)]; ?>
                    <a class="jm-card jm-home-category" href="/jobmington/jobs/?category=<?= e($category['slug'] ?: $category['category_id']) ?>">
                        <span class="jm-category-image">
                            <img src="<?= e($categoryImage['src']) ?>" alt="<?= e($categoryImage['alt']) ?>" loading="lazy">
                        </span>
                        <span class="jm-category-body">
                            <span class="jm-category-label">Explore roles</span>
                            <h2><?= e($category['name']) ?></h2>
                            <p><?= number_format((int) $category['live_jobs']) ?> active <?= ((int) $category['live_jobs']) === 1 ? 'job' : 'jobs' ?></p>
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <script>
            (() => {
                const strip = document.querySelector('[data-strip-slider]');
                if (!strip) return;

                const track = strip.querySelector('[data-strip-track]');
                const slides = Array.from(strip.querySelectorAll('.jm-strip-item'));
                const dots = Array.from(strip.querySelectorAll('[data-strip-dot]'));
                const prev = strip.querySelector('[data-strip-prev]');
                const next = strip.querySelector('[data-strip-next]');
                const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
                let index = 0;
                let timer = null;

                if (!track || slides.length < 2) return;

                const render = () => {
                    track.style.transform = `translate3d(${-index * 100}%, 0, 0)`;
                    slides.forEach((slide, slideIndex) => {
                        slide.classList.toggle('is-active', slideIndex === index);
                    });
                    dots.forEach((dot, dotIndex) => {
                        dot.classList.toggle('is-active', dotIndex === index);
                        dot.setAttribute('aria-current', dotIndex === index ? 'true' : 'false');
                    });
                };

                const goTo = (nextIndex) => {
                    index = (nextIndex + slides.length) % slides.length;
                    render();
                };

                const stopAuto = () => {
                    if (timer) window.clearInterval(timer);
                    timer = null;
                };

                const startAuto = () => {
                    if (reducedMotion || timer) return;
                    timer = window.setInterval(() => goTo(index + 1), 6500);
                };

                prev?.addEventListener('click', () => {
                    stopAuto();
                    goTo(index - 1);
                    startAuto();
                });
                next?.addEventListener('click', () => {
                    stopAuto();
                    goTo(index + 1);
                    startAuto();
                });
                dots.forEach((dot, dotIndex) => {
                    dot.addEventListener('click', () => {
                        stopAuto();
                        goTo(dotIndex);
                        startAuto();
                    });
                });

                strip.addEventListener('mouseenter', stopAuto);
                strip.addEventListener('mouseleave', startAuto);
                strip.addEventListener('focusin', stopAuto);
                strip.addEventListener('focusout', (event) => {
                    if (!strip.contains(event.relatedTarget)) startAuto();
                });
                strip.addEventListener('keydown', (event) => {
                    if (event.key === 'ArrowLeft') {
                        event.preventDefault();
                        stopAuto();
                        goTo(index - 1);
                        startAuto();
                    }
                    if (event.key === 'ArrowRight') {
                        event.preventDefault();
                        stopAuto();
                        goTo(index + 1);
                        startAuto();
                    }
                });

                render();
                startAuto();
            })();

            (() => {
                const slider = document.querySelector('[data-jm-slider]');
                if (!slider) return;

                const frame = slider.querySelector('.jm-slider-frame');
                const track = slider.querySelector('[data-slider-track]');
                const originalSlides = Array.from(slider.querySelectorAll('.jm-photo-slide'));
                const prev = slider.querySelector('[data-slider-prev]');
                const next = slider.querySelector('[data-slider-next]');
                const dotsWrap = slider.querySelector('[data-slider-dots]');
                const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
                let index = 0;
                let position = 1;
                let timer = null;
                let currentOffset = 0;
                let startX = 0;
                let dragging = false;

                if (originalSlides.length < 2) return;

                const firstClone = originalSlides[0].cloneNode(true);
                const lastClone = originalSlides[originalSlides.length - 1].cloneNode(true);
                [firstClone, lastClone].forEach((clone) => {
                    clone.dataset.clone = 'true';
                    clone.setAttribute('aria-hidden', 'true');
                });
                track.insertBefore(lastClone, originalSlides[0]);
                track.appendChild(firstClone);

                const slides = Array.from(track.querySelectorAll('.jm-photo-slide'));
                const lastRealPosition = originalSlides.length;
                const firstClonePosition = originalSlides.length + 1;

                const dots = originalSlides.map((_, dotIndex) => {
                    const dot = document.createElement('button');
                    dot.className = 'jm-slider-dot';
                    dot.type = 'button';
                    dot.setAttribute('aria-label', `Go to slide ${dotIndex + 1}`);
                    dot.addEventListener('click', () => goTo(dotIndex));
                    dotsWrap.appendChild(dot);
                    return dot;
                });

                const getGap = () => {
                    const styles = window.getComputedStyle(track);
                    return parseFloat(styles.columnGap || styles.gap) || 0;
                };

                const getOffset = (slidePosition) => {
                    const active = slides[slidePosition];
                    const slideWidth = active.getBoundingClientRect().width;
                    const gap = getGap();
                    return ((frame.clientWidth - slideWidth) / 2) - (slidePosition * (slideWidth + gap));
                };

                const setSlideState = () => {
                    slides.forEach((slide, slideIndex) => {
                        const distance = Math.abs(slideIndex - position);
                        const isActive = slideIndex === position;
                        const isNeighbor = distance === 1;
                        const direction = Math.max(-2, Math.min(2, slideIndex - position));
                        slide.classList.toggle('is-active', isActive);
                        slide.classList.toggle('is-neighbor', isNeighbor);
                        slide.style.setProperty('--slide-tilt', `${direction * -1.8}deg`);
                        slide.style.setProperty('--image-shift', `${direction * -9}px`);
                        slide.setAttribute('aria-hidden', isActive && slide.dataset.clone !== 'true' ? 'false' : 'true');
                    });

                    dots.forEach((dot, dotIndex) => {
                        const isActive = dotIndex === index;
                        dot.classList.toggle('is-active', isActive);
                        dot.setAttribute('aria-current', isActive ? 'true' : 'false');
                    });
                };

                const render = (animate = true) => {
                    currentOffset = getOffset(position);
                    track.style.transition = animate ? '' : 'none';
                    track.style.transform = `translate3d(${currentOffset}px, 0, 0)`;
                    setSlideState();

                    if (!animate) {
                        window.requestAnimationFrame(() => {
                            track.style.transition = '';
                        });
                    }
                };

                const stopAuto = () => {
                    if (timer) window.clearInterval(timer);
                    timer = null;
                };

                const startAuto = () => {
                    if (reducedMotion || timer) return;
                    timer = window.setInterval(() => goTo(index + 1, false), 5200);
                };

                const restartAuto = () => {
                    stopAuto();
                    startAuto();
                };

                const snapClonePosition = () => {
                    if (position === 0) {
                        position = lastRealPosition;
                        render(false);
                    }
                    if (position === firstClonePosition) {
                        position = 1;
                        render(false);
                    }
                };

                function goTo(nextIndex, resetAuto = true) {
                    index = (nextIndex + originalSlides.length) % originalSlides.length;
                    position = nextIndex + 1;
                    render(true);
                    if (reducedMotion) snapClonePosition();
                    if (resetAuto) restartAuto();
                }

                track.addEventListener('transitionend', (event) => {
                    if (event.propertyName !== 'transform') return;
                    snapClonePosition();
                });

                prev.addEventListener('click', () => goTo(index - 1));
                next.addEventListener('click', () => goTo(index + 1));
                slider.addEventListener('mouseenter', stopAuto);
                slider.addEventListener('mouseleave', startAuto);
                slider.addEventListener('focusin', stopAuto);
                slider.addEventListener('focusout', (event) => {
                    if (!slider.contains(event.relatedTarget)) startAuto();
                });
                slider.addEventListener('keydown', (event) => {
                    if (event.key === 'ArrowLeft') {
                        event.preventDefault();
                        goTo(index - 1);
                    }
                    if (event.key === 'ArrowRight') {
                        event.preventDefault();
                        goTo(index + 1);
                    }
                });

                frame.addEventListener('pointerdown', (event) => {
                    if (event.button !== 0) return;
                    dragging = true;
                    startX = event.clientX;
                    stopAuto();
                    if (frame.setPointerCapture) frame.setPointerCapture(event.pointerId);
                    track.style.transition = 'none';
                });

                frame.addEventListener('pointermove', (event) => {
                    if (!dragging) return;
                    const delta = event.clientX - startX;
                    track.style.transform = `translate3d(${currentOffset + delta}px, 0, 0)`;
                });

                const endDrag = (event) => {
                    if (!dragging) return;
                    dragging = false;
                    const delta = event.clientX - startX;
                    const threshold = Math.min(92, Math.max(52, frame.clientWidth * 0.12));
                    track.style.transition = '';
                    if (Math.abs(delta) > threshold) {
                        goTo(index + (delta < 0 ? 1 : -1));
                    } else {
                        render(true);
                        startAuto();
                    }
                };

                frame.addEventListener('pointerup', endDrag);
                frame.addEventListener('pointercancel', endDrag);
                window.addEventListener('resize', () => render(false));
                document.addEventListener('visibilitychange', () => {
                    if (document.hidden) stopAuto();
                    else startAuto();
                });

                render(false);
                startAuto();
            })();
        </script>

        <?php jm_minimal_footer(); ?>
    </div>
</body>
</html>
