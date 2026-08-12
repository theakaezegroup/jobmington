<?php
define('JOBMINGTON', true);
require_once __DIR__ . '/config/env.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/monetization.php';

Session::start();

$tab        = Security::clean($_GET['tab'] ?? 'employer');
$tab        = in_array($tab, ['employer', 'seeker']) ? $tab : 'employer';
$isLoggedIn = Session::isLoggedIn();
$isEmployer = Session::isEmployer();

$pageTitle = 'Pricing | ' . SITE_NAME;
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?></title>
    <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/minimal-jobmington.css?v=brand-18">
    <style>
    /* ── Animations ─────────────────────────────────────────────── */
    @keyframes fadeUp {
        from { opacity:0; transform:translateY(22px); }
        to   { opacity:1; transform:translateY(0); }
    }
    @keyframes fadeIn {
        from { opacity:0; }
        to   { opacity:1; }
    }
    @keyframes checkDraw {
        from { stroke-dashoffset: 24; }
        to   { stroke-dashoffset: 0; }
    }
    .jm-p-reveal   { animation: fadeUp .5s cubic-bezier(.22,.68,0,1.2) both; }
    .jm-p-reveal-1 { animation-delay:.05s; }
    .jm-p-reveal-2 { animation-delay:.12s; }
    .jm-p-reveal-3 { animation-delay:.19s; }
    .jm-p-reveal-4 { animation-delay:.26s; }

    /* ── Page chrome ─────────────────────────────────────────────── */
    .jm-p-hero {
        text-align:center;
        padding:54px 0 44px;
        animation: fadeUp .55s cubic-bezier(.22,.68,0,1.2) both;
    }
    .jm-p-hero .jm-kicker {
        font-size:13px; letter-spacing:.08em; text-transform:uppercase;
        font-weight:800; margin-bottom:14px;
    }
    .jm-p-hero h1 {
        font-size:clamp(34px,5vw,54px); line-height:1.06;
        margin:0 0 16px; color:var(--jm-ink);
    }
    .jm-p-hero p {
        color:var(--jm-muted); font-size:17px; margin:0; line-height:1.7;
    }

    /* ── Tab switcher ────────────────────────────────────────────── */
    .jm-p-tabs {
        display:flex; justify-content:center; margin:0 0 52px;
        gap:0; position:relative;
        animation: fadeIn .4s ease both .15s;
    }
    .jm-p-tab-wrap {
        background:#f0f4fb; border:1px solid var(--jm-line);
        border-radius:10px; padding:4px; display:flex; gap:4px;
    }
    .jm-p-tab {
        position:relative; padding:10px 28px;
        border:none; background:transparent;
        color:var(--jm-muted); font:inherit; font-size:14px;
        font-weight:700; cursor:pointer; border-radius:8px;
        transition:color .2s ease; z-index:1;
    }
    .jm-p-tab.active {
        color:var(--jm-ink);
        background:#ffffff;
        box-shadow:0 1px 4px rgba(6,20,38,.1);
    }

    /* ── Section panels ──────────────────────────────────────────── */
    .jm-p-panel { display:none; }
    .jm-p-panel.active { display:block; }

    /* ── Price card grid ─────────────────────────────────────────── */
    .jm-p-grid {
        display:grid;
        grid-template-columns:repeat(auto-fit, minmax(260px,1fr));
        gap:20px; align-items:stretch;
        margin-bottom:36px;
    }
    .jm-p-card {
        position:relative; display:flex; flex-direction:column;
        border:1px solid var(--jm-line); border-radius:12px;
        background:#ffffff; padding:32px 28px;
        transition:transform .22s ease, border-color .22s ease, box-shadow .22s ease;
        cursor:default;
    }
    .jm-p-card:hover {
        transform:translateY(-4px);
        border-color:#b6cceb;
        box-shadow:0 12px 32px rgba(6,64,163,.08);
    }
    .jm-p-card.featured {
        border-color:var(--jm-blue);
        background:linear-gradient(160deg,#ffffff 0%,#f4f8ff 100%);
    }
    .jm-p-card.featured:hover {
        border-color:var(--jm-blue-dark);
        box-shadow:0 16px 40px rgba(6,64,163,.14);
    }
    .jm-p-badge {
        position:absolute; top:-13px; left:50%; transform:translateX(-50%);
        background:var(--jm-blue); color:#fff;
        font-size:11px; font-weight:800; letter-spacing:.06em;
        padding:4px 14px; border-radius:99px; white-space:nowrap;
        text-transform:uppercase;
    }
    .jm-p-badge.tone-orange {
        background:var(--jm-orange); color:var(--jm-ink);
    }
    .jm-p-plan-name {
        font-size:12px; font-weight:800; text-transform:uppercase;
        letter-spacing:.08em; color:var(--jm-blue); margin:0 0 10px;
    }
    .jm-p-amount {
        display:flex; align-items:baseline; gap:4px; margin:0 0 4px;
    }
    .jm-p-amount strong {
        font-size:clamp(30px,4vw,42px); font-weight:800;
        color:var(--jm-ink); line-height:1;
    }
    .jm-p-amount span {
        color:var(--jm-muted); font-size:14px;
    }
    .jm-p-usd {
        font-size:12px; color:var(--jm-muted); margin:0 0 14px; display:block;
    }
    .jm-p-desc {
        font-size:14px; color:var(--jm-muted); line-height:1.65;
        margin:0 0 22px; flex:0;
    }
    .jm-p-feats {
        list-style:none; padding:0; margin:0 0 28px;
        display:grid; gap:10px; flex:1;
        border-top:1px solid var(--jm-line); padding-top:20px;
    }
    .jm-p-feats li {
        display:flex; align-items:flex-start; gap:9px;
        font-size:14px; color:var(--jm-ink); line-height:1.5;
    }
    .jm-p-feats li .jm-p-check {
        flex:0 0 auto; width:18px; height:18px; margin-top:1px;
    }
    .jm-p-feats li .jm-p-check circle { fill:#ecfdf8; }
    .jm-p-feats li .jm-p-check path {
        stroke:#0f766e; stroke-width:2; stroke-linecap:round;
        stroke-linejoin:round; fill:none;
        stroke-dasharray:24; stroke-dashoffset:24;
        transition:stroke-dashoffset .4s ease;
    }
    .jm-p-card:hover .jm-p-check path { stroke-dashoffset:0; }
    .jm-p-feats li.dim { color:var(--jm-muted); }
    .jm-p-feats li.dim .jm-p-check circle { fill:#f1f5f9; }
    .jm-p-feats li.dim .jm-p-check path { stroke:#cbd5e1; }
    .jm-p-cta { margin-top:auto; }
    .jm-p-cta .jm-button { width:100%; justify-content:center; min-height:46px; font-size:14px; }

    /* ── Single-post feature ─────────────────────────────────────── */
    .jm-p-solo {
        border:1px solid var(--jm-line); border-radius:12px;
        background:#ffffff; padding:28px 32px;
        display:grid; grid-template-columns:1fr auto;
        gap:24px; align-items:center;
        margin-bottom:40px;
        transition:border-color .2s, box-shadow .2s;
    }
    .jm-p-solo:hover {
        border-color:#b6cceb;
        box-shadow:0 8px 28px rgba(6,64,163,.07);
    }
    .jm-p-solo-price strong {
        display:block; font-size:36px; font-weight:800;
        color:var(--jm-ink); line-height:1;
    }
    .jm-p-solo-price small {
        font-size:12px; color:var(--jm-muted);
    }

    /* ── Add-on pill ─────────────────────────────────────────────── */
    .jm-p-addon {
        display:flex; align-items:center; gap:14px; flex-wrap:wrap;
        border:1px dashed #c5d5ea; border-radius:10px;
        padding:18px 24px; background:var(--jm-warm);
        margin-bottom:44px;
        transition:border-color .2s, background .2s;
    }
    .jm-p-addon:hover {
        border-color:var(--jm-orange); background:#fff3dc;
    }
    .jm-p-addon-icon {
        flex:0 0 40px; width:40px; height:40px;
        background:var(--jm-orange); border-radius:8px;
        display:grid; place-items:center; font-size:20px;
    }
    .jm-p-addon-body { flex:1; min-width:0; }
    .jm-p-addon-body strong { display:block; font-size:15px; color:var(--jm-ink); }
    .jm-p-addon-body span { font-size:13px; color:#9a5a00; display:block; margin-top:2px; }
    .jm-p-addon-price { font-size:22px; font-weight:800; color:#b45309; white-space:nowrap; }

    /* ── Section headings ────────────────────────────────────────── */
    .jm-p-section-label {
        font-size:11px; font-weight:800; text-transform:uppercase;
        letter-spacing:.1em; color:var(--jm-muted);
        margin:0 0 8px; display:block;
    }
    .jm-p-section-h {
        font-size:26px; font-weight:700; color:var(--jm-ink);
        margin:0 0 6px;
    }
    .jm-p-section-sub {
        font-size:15px; color:var(--jm-muted); margin:0 0 28px;
    }

    /* ── Tool table ──────────────────────────────────────────────── */
    .jm-p-tool-table {
        width:100%; border-collapse:collapse;
        font-size:14px; margin-bottom:36px;
    }
    .jm-p-tool-table thead th {
        background:var(--jm-soft); color:var(--jm-muted);
        font-size:11px; font-weight:800; text-transform:uppercase;
        letter-spacing:.07em; padding:10px 16px; text-align:left;
    }
    .jm-p-tool-table thead th:first-child { border-radius:8px 0 0 0; }
    .jm-p-tool-table thead th:last-child  { border-radius:0 8px 0 0; }
    .jm-p-tool-table tbody tr {
        border-bottom:1px solid var(--jm-line);
        transition:background .15s;
    }
    .jm-p-tool-table tbody tr:hover { background:var(--jm-soft); }
    .jm-p-tool-table tbody td { padding:12px 16px; color:var(--jm-ink); }
    .jm-p-tool-table .tone-green { color:#0f766e; font-weight:700; }
    .jm-p-tool-table .tone-free  { color:var(--jm-blue); font-weight:700; }
    .jm-p-beta { display:inline-block; margin-left:7px; padding:2px 8px; border-radius:99px; background:#fdf0d5; color:#8a5a00; font-size:10px; font-weight:800; text-transform:uppercase; letter-spacing:.06em; vertical-align:middle; }

    /* ── Credit pack selector ────────────────────────────────────── */
    .jm-p-packs {
        display:grid;
        grid-template-columns:repeat(auto-fit,minmax(200px,1fr));
        gap:16px; margin-bottom:28px;
    }
    .jm-p-pack {
        border:2px solid var(--jm-line); border-radius:10px;
        padding:22px 20px; cursor:pointer;
        transition:border-color .2s, background .2s, transform .2s;
        position:relative;
    }
    .jm-p-pack:hover {
        border-color:#9ab8e0; background:var(--jm-soft);
        transform:translateY(-2px);
    }
    .jm-p-pack.best {
        border-color:var(--jm-orange);
        background:linear-gradient(135deg,#fff8ec,#fff);
    }
    .jm-p-pack strong { display:block; font-size:15px; color:var(--jm-ink); margin-bottom:6px; }
    .jm-p-pack-price { font-size:24px; font-weight:800; color:var(--jm-blue); }
    .jm-p-pack-credits { font-size:13px; color:var(--jm-muted); margin-top:4px; }
    .jm-p-pack-save {
        display:inline-block; margin-top:8px;
        background:#dcfce7; color:#166534;
        font-size:11px; font-weight:800;
        padding:2px 9px; border-radius:99px;
    }

    /* ── Bundle card ─────────────────────────────────────────────── */
    .jm-p-bundle {
        display:flex; align-items:center; justify-content:space-between;
        gap:24px; flex-wrap:wrap;
        border:1px solid var(--jm-line); border-radius:12px;
        padding:28px 32px; background:#ffffff;
        margin-bottom:28px;
        transition:border-color .2s, box-shadow .2s;
    }
    .jm-p-bundle:hover {
        border-color:#b6cceb;
        box-shadow:0 8px 28px rgba(6,64,163,.07);
    }
    .jm-p-bundle-tag {
        display:inline-flex; align-items:center; gap:4px;
        background:var(--jm-warm); border:1px solid #f3d4a3;
        color:#9a5a00; font-size:11px; font-weight:800;
        padding:3px 10px; border-radius:99px; margin-bottom:8px;
    }
    .jm-p-bundle-items {
        display:flex; gap:10px; flex-wrap:wrap; margin-top:10px;
    }
    .jm-p-bundle-items span {
        background:var(--jm-soft); border:1px solid var(--jm-line);
        color:var(--jm-ink); font-size:12px; font-weight:700;
        padding:4px 12px; border-radius:99px;
    }
    .jm-p-bundle-price strong {
        display:block; font-size:38px; font-weight:800;
        color:var(--jm-ink); line-height:1; white-space:nowrap;
    }
    .jm-p-bundle-price small { font-size:12px; color:var(--jm-muted); }

    /* ── FAQ ─────────────────────────────────────────────────────── */
    .jm-p-faq { max-width:680px; }
    .jm-p-faq h3 { font-size:22px; margin:0 0 18px; color:var(--jm-ink); }
    .jm-p-faq details {
        border-bottom:1px solid var(--jm-line); padding:16px 0;
    }
    .jm-p-faq summary {
        font-weight:700; font-size:15px; cursor:pointer;
        list-style:none; display:flex; align-items:center;
        justify-content:space-between; gap:12px; color:var(--jm-ink);
    }
    .jm-p-faq summary::-webkit-details-marker { display:none; }
    .jm-p-faq summary::after {
        content:''; flex:0 0 20px; height:20px;
        background:var(--jm-soft); border:1px solid var(--jm-line);
        border-radius:999px;
        background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='10' viewBox='0 0 10 10'%3E%3Cpath d='M2 4l3 3 3-3' stroke='%2353667f' stroke-width='1.5' fill='none' stroke-linecap='round'/%3E%3C/svg%3E");
        background-repeat:no-repeat; background-position:center;
        transition:transform .2s ease;
    }
    details[open] summary::after { transform:rotate(180deg); }
    .jm-p-faq details p {
        margin:12px 0 4px; color:var(--jm-muted); font-size:14px; line-height:1.7;
    }

    /* ── Social proof bar ────────────────────────────────────────── */
    .jm-p-proof {
        display:flex; align-items:center; justify-content:center;
        gap:32px; flex-wrap:wrap;
        border:1px solid var(--jm-line); border-radius:10px;
        background:var(--jm-soft); padding:18px 24px;
        margin-bottom:52px;
        animation: fadeUp .5s ease both .3s;
    }
    .jm-p-proof-item {
        display:flex; align-items:center; gap:8px;
        font-size:14px; font-weight:600; color:var(--jm-muted);
    }
    .jm-p-proof-item strong { color:var(--jm-ink); font-size:16px; font-weight:800; }
    .jm-p-proof-sep { width:1px; height:28px; background:var(--jm-line); }

    /* ── Balance badge ───────────────────────────────────────────── */
    .jm-p-balance {
        display:inline-flex; align-items:center; gap:10px;
        background:var(--jm-soft); border:1px solid var(--jm-line);
        border-radius:8px; padding:10px 16px;
        font-size:14px; font-weight:600; color:var(--jm-muted);
        margin-bottom:28px;
    }
    .jm-p-balance strong { color:var(--jm-ink); }
    .jm-p-premium-pill {
        background:var(--jm-green); color:#fff;
        font-size:11px; font-weight:800; padding:2px 9px; border-radius:99px;
    }

    /* ── Upsell strip ────────────────────────────────────────────── */
    .jm-p-upsell {
        display:flex; align-items:center; justify-content:space-between;
        gap:20px; flex-wrap:wrap;
        border:1px solid var(--jm-line); border-radius:10px;
        padding:20px 24px; background:#ffffff;
        margin-bottom:32px;
    }
    .jm-p-upsell p { margin:0; font-size:14px; color:var(--jm-muted); }
    .jm-p-upsell strong { display:block; font-size:16px; color:var(--jm-ink); margin-bottom:3px; }

    /* ── Mobile ──────────────────────────────────────────────────── */
    @media (max-width:640px) {
        .jm-p-solo { grid-template-columns:1fr; }
        .jm-p-solo-price { text-align:center; }
        .jm-p-bundle { flex-direction:column; align-items:flex-start; }
        .jm-p-proof { gap:18px; }
        .jm-p-proof-sep { display:none; }
    }
    </style>
</head>
<body class="jm-minimal">
<div class="jm-shell">

    <!-- Header -->
    <header class="jm-header">
        <a class="jm-logo" href="<?= SITE_URL ?>/"><img src="<?= ASSETS_URL ?>/images/badge.png?v=logo-8" alt=""><span>Jobmington</span></a>
            <?php require_once __DIR__ . '/includes/navigation.php'; jm_site_nav('/pricing'); ?>
    </header>

    <!-- Hero -->
    <div class="jm-p-hero">
        <p class="jm-kicker">Pricing</p>
        <h1>Transparent, Africa&#8209;first pricing.</h1>
        <p>All prices in Naira (₦). International cards also accepted.</p>
    </div>

    <!-- Social proof -->
    <div class="jm-p-proof">
        <div class="jm-p-proof-item"><strong><?= jm_format_ngn(PRICE_SEEKER_PREMIUM_MONTHLY) ?></strong>/mo for seekers</div>
        <div class="jm-p-proof-sep"></div>
        <div class="jm-p-proof-item"><strong>₦30,000</strong> per job post</div>
        <div class="jm-p-proof-sep"></div>
        <div class="jm-p-proof-item">Credits <strong>never expire</strong></div>
        <div class="jm-p-proof-sep"></div>
        <div class="jm-p-proof-item">Powered by <strong>Paystack</strong></div>
    </div>

    <!-- Tabs -->
    <div class="jm-p-tabs">
        <div class="jm-p-tab-wrap">
            <button class="jm-p-tab <?= $tab === 'employer' ? 'active' : '' ?>" onclick="switchTab('employer', this)">For Employers</button>
            <button class="jm-p-tab <?= $tab === 'seeker'   ? 'active' : '' ?>" onclick="switchTab('seeker', this)">For Job Seekers</button>
        </div>
    </div>

    <!-- ════════════════════ EMPLOYER ════════════════════ -->
    <div class="jm-p-panel <?= $tab === 'employer' ? 'active' : '' ?>" id="tab-employer">

        <span class="jm-p-section-label">Single listing</span>
        <h2 class="jm-p-section-h">Post one job. Pay once.</h2>
        <p class="jm-p-section-sub">No subscription needed. Post when you're ready.</p>

        <div class="jm-p-solo jm-p-reveal jm-p-reveal-1">
            <div>
                <strong style="font-size:18px;color:var(--jm-ink);display:block;margin-bottom:6px;">Standard Job Post</strong>
                <p style="margin:0 0 14px;font-size:14px;color:var(--jm-muted);">Your listing is live for 30 days with full applicant tracking and company branding.</p>
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <?php foreach(['30-day listing','Full applicant dashboard','Company branding','Unlimited applications'] as $f): ?>
                    <span class="jm-job-tag tone-blue"><?= e($f) ?></span>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="jm-p-solo-price">
                <strong><?= jm_format_ngn(PRICE_EMPLOYER_SINGLE_POST) ?></strong>
                <small>≈ <?= jm_ngn_to_usd(PRICE_EMPLOYER_SINGLE_POST) ?> USD</small>
                <div style="margin-top:14px;">
                    <a class="jm-button" href="<?= SITE_URL ?>/employer/post-job.php">Post a job</a>
                </div>
            </div>
        </div>

        <!-- Featured add-on -->
        <div class="jm-p-addon jm-p-reveal jm-p-reveal-2">
            <div class="jm-p-addon-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--jm-ink)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg></div>
            <div class="jm-p-addon-body">
                <strong>Featured Job Boost</strong>
                <span>Pin your listing at the top of search results with a Featured badge. Add to any post or plan.</span>
            </div>
            <div class="jm-p-addon-price"><?= jm_format_ngn(PRICE_EMPLOYER_FEATURED_ADDON) ?> <span style="font-size:13px;font-weight:400;color:#9a5a00;">/ job</span></div>
        </div>

        <!-- Plans -->
        <span class="jm-p-section-label">Monthly plans</span>
        <h2 class="jm-p-section-h">Hire at scale, subscribe and save.</h2>
        <p class="jm-p-section-sub">One subscription, multiple active roles.</p>

        <div class="jm-p-grid">
            <?php $i = 0; foreach (jm_employer_plans() as $plan): $isFeatured = $plan['id'] === 'pro'; $i++; ?>
            <div class="jm-p-card <?= $isFeatured ? 'featured' : '' ?> jm-p-reveal jm-p-reveal-<?= $i + 2 ?>">
                <?php if ($plan['badge']): ?><span class="jm-p-badge"><?= e($plan['badge']) ?></span><?php endif; ?>
                <p class="jm-p-plan-name"><?= e($plan['name']) ?></p>
                <div class="jm-p-amount">
                    <strong><?= jm_format_ngn($plan['price_monthly']) ?></strong>
                    <span>/month</span>
                </div>
                <span class="jm-p-usd">≈ <?= jm_ngn_to_usd($plan['price_monthly']) ?> USD</span>
                <p class="jm-p-desc"><?= e($plan['description']) ?></p>
                <ul class="jm-p-feats">
                    <?php foreach ($plan['features'] as $feat): ?>
                    <li>
                        <svg class="jm-p-check" viewBox="0 0 18 18"><circle cx="9" cy="9" r="9"/><path d="M5.5 9l2.5 2.5 4.5-4.5" stroke-dasharray="24" stroke-dashoffset="24"/></svg>
                        <?= e($feat) ?>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <div class="jm-p-cta">
                    <a class="jm-button <?= !$isFeatured ? 'secondary' : '' ?>"
                       href="<?= SITE_URL ?>/employer/post-job.php?plan=<?= e($plan['id']) ?>">
                        Get <?= e($plan['name']) ?>
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="jm-p-faq">
            <h3>Employer FAQ</h3>
            <details><summary>Can I pay in USD?</summary><p>Yes. Paystack accepts international cards. Prices shown in Naira — USD equivalent at checkout: Single post ≈ <?= jm_ngn_to_usd(PRICE_EMPLOYER_SINGLE_POST) ?>, Basic ≈ <?= jm_ngn_to_usd(PRICE_EMPLOYER_BASIC_MONTHLY) ?>/mo, Pro ≈ <?= jm_ngn_to_usd(PRICE_EMPLOYER_PRO_MONTHLY) ?>/mo.</p></details>
            <details><summary>What does Featured mean?</summary><p>Featured jobs are pinned at the top of search results and displayed in dedicated "Featured" sections across the site, giving your listing significantly more impressions.</p></details>
            <details><summary>Can I cancel a monthly plan?</summary><p>Yes, cancel any time. Your active listings stay live until the end of the billing cycle.</p></details>
            <details><summary>How many applications can I receive?</summary><p>Unlimited on all plans.</p></details>
        </div>
    </div>

    <!-- ════════════════════ SEEKER ════════════════════ -->
    <div class="jm-p-panel <?= $tab === 'seeker' ? 'active' : '' ?>" id="tab-seeker">

        <span class="jm-p-section-label">Premium membership</span>
        <h2 class="jm-p-section-h">Land your next role faster.</h2>
        <p class="jm-p-section-sub">Free to browse and apply. Premium unlocks AI tools that give you a real edge.</p>

        <!-- Plan cards: Free + Monthly + Annual -->
        <div class="jm-p-grid" style="grid-template-columns:repeat(auto-fit,minmax(240px,1fr));">

            <!-- Free -->
            <div class="jm-p-card jm-p-reveal jm-p-reveal-1">
                <p class="jm-p-plan-name">Free</p>
                <div class="jm-p-amount"><strong>₦0</strong><span>forever</span></div>
                <span class="jm-p-usd">&nbsp;</span>
                <p class="jm-p-desc">Everything you need to start your job search.</p>
                <ul class="jm-p-feats">
                    <?php foreach(['Browse all job listings','Apply to unlimited jobs','Basic CV builder','AI tool previews (score only)'] as $f): ?>
                    <li><svg class="jm-p-check" viewBox="0 0 18 18"><circle cx="9" cy="9" r="9"/><path d="M5.5 9l2.5 2.5 4.5-4.5" stroke-dasharray="24" stroke-dashoffset="24"/></svg><?= e($f) ?></li>
                    <?php endforeach; ?>
                    <?php foreach(['PDF/Word CV download','Unlimited AI tools','Priority applications','Early job alerts'] as $f): ?>
                    <li class="dim"><svg class="jm-p-check" viewBox="0 0 18 18"><circle cx="9" cy="9" r="9"/><path d="M5.5 9l2.5 2.5 4.5-4.5" stroke-dasharray="24" stroke-dashoffset="24"/></svg><?= e($f) ?></li>
                    <?php endforeach; ?>
                </ul>
                <div class="jm-p-cta"><a class="jm-button secondary" href="<?= SITE_URL ?>/auth/register.php">Sign up free</a></div>
            </div>

            <?php foreach (jm_seeker_plans() as $planId => $plan): $isAnnual = $planId === 'annual'; ?>
            <div class="jm-p-card featured jm-p-reveal jm-p-reveal-<?= $isAnnual ? 3 : 2 ?>">
                <?php if ($plan['badge']): ?><span class="jm-p-badge <?= $isAnnual ? 'tone-orange' : '' ?>"><?= e($plan['badge']) ?></span><?php endif; ?>
                <p class="jm-p-plan-name"><?= e($plan['name']) ?></p>
                <div class="jm-p-amount">
                    <strong><?= jm_format_ngn($plan['price']) ?></strong>
                    <span>/<?= $isAnnual ? 'year' : 'month' ?></span>
                </div>
                <?php if ($isAnnual): ?><span class="jm-p-usd" style="color:var(--jm-green);font-weight:700;">= <?= jm_format_ngn(PRICE_SEEKER_PREMIUM_MONTHLY) ?>/mo — 2 months free</span><?php else: ?><span class="jm-p-usd"><?= jm_format_ngn_with_usd($plan['price']) ?></span><?php endif; ?>
                <p class="jm-p-desc"><?= e($plan['description']) ?></p>
                <ul class="jm-p-feats">
                    <?php foreach ($plan['features'] as $feat): ?>
                    <li><svg class="jm-p-check" viewBox="0 0 18 18"><circle cx="9" cy="9" r="9"/><path d="M5.5 9l2.5 2.5 4.5-4.5" stroke-dasharray="24" stroke-dashoffset="24"/></svg><?= e($feat) ?></li>
                    <?php endforeach; ?>
                </ul>
                <div class="jm-p-cta">
                    <a class="jm-button" href="<?= SITE_URL ?>/payments/seeker-premium.php?plan=<?= e($planId) ?>">Get Premium</a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Tool table -->
        <span class="jm-p-section-label" style="margin-top:16px;">AI tools</span>
        <h2 class="jm-p-section-h">What does each tool cost?</h2>
        <p class="jm-p-section-sub">Premium includes everything. Credits let you pay per use — they never expire.</p>

        <table class="jm-p-tool-table">
            <thead>
                <tr>
                    <th>Tool</th>
                    <th>With Premium</th>
                    <th>Pay-per-use</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach (jm_ai_tools_listed() as $tool): $isBeta = ($tool['status'] ?? 'on') === 'beta'; ?>
                <tr>
                    <td>
                        <strong><?= e($tool['name']) ?></strong>
                        <?php if ($isBeta): ?><span class="jm-p-beta">Beta</span><?php endif; ?>
                        <br><span style="font-size:12px;color:var(--jm-muted);"><?= e($tool['description']) ?></span>
                    </td>
                    <td class="tone-green">✓ Included</td>
                    <td><?php
                        // A tool in beta is free to the people invited into it, so
                        // quoting a price here would be asking for money we do not
                        // take yet.
                        if ($isBeta) {
                            echo '<span class="tone-free">Free while in beta</span>';
                        } elseif ($tool['is_free']) {
                            echo '<span class="tone-free">Free</span>';
                        } else {
                            echo e(jm_format_ngn($tool['ngn_price'])) . ' (' . $tool['credit_cost'] . ' credit' . ($tool['credit_cost'] > 1 ? 's' : '') . ')';
                        }
                    ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Credits section -->
        <span class="jm-p-section-label">Credits</span>
        <h2 class="jm-p-section-h">Prefer to pay per use?</h2>
        <p class="jm-p-section-sub">Buy a pack and use credits on any tool, any time. They never expire.</p>

        <div class="jm-p-packs">
            <?php foreach (jm_credit_packs() as $pack): ?>
            <div class="jm-p-pack <?= $pack['id'] === 'pack_5' ? 'best' : '' ?> jm-p-reveal">
                <?php if ($pack['badge']): ?><span class="jm-p-badge" style="top:-11px;"><?= e($pack['badge']) ?></span><?php endif; ?>
                <strong><?= e($pack['name']) ?></strong>
                <div class="jm-p-pack-price"><?= jm_format_ngn($pack['price']) ?></div>
                <div class="jm-p-pack-credits"><?= $pack['credits'] ?> credit<?= $pack['credits'] > 1 ? 's' : '' ?></div>
                <?php if ($pack['savings'] > 0): ?><span class="jm-p-pack-save">Save <?= jm_format_ngn($pack['savings']) ?></span><?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <a class="jm-button secondary" href="<?= SITE_URL ?>/payments/credits.php">Buy credits</a>

        <!-- Bundle -->
        <?php foreach (jm_bundles() as $bundle): ?>
        <div style="margin-top:40px;">
            <span class="jm-p-section-label">Bundle</span>
            <h2 class="jm-p-section-h">Everything in one shot.</h2>
            <p class="jm-p-section-sub">For when you're applying to a specific role and want to go all in.</p>
            <div class="jm-p-bundle">
                <div>
                    <span class="jm-p-bundle-tag"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg> <?= e($bundle['badge']) ?></span>
                    <strong style="font-size:18px;display:block;color:var(--jm-ink);"><?= e($bundle['name']) ?></strong>
                    <p style="margin:4px 0 10px;font-size:14px;color:var(--jm-muted);"><?= e($bundle['description']) ?></p>
                    <div class="jm-p-bundle-items">
                        <?php foreach ($bundle['includes'] as $item): ?><span><?= e($item) ?></span><?php endforeach; ?>
                    </div>
                </div>
                <div class="jm-p-bundle-price">
                    <strong><?= jm_format_ngn($bundle['price']) ?></strong>
                    <small>one-off payment</small>
                    <div style="margin-top:14px;">
                        <a class="jm-button" href="<?= SITE_URL ?>/payments/credits.php?bundle=job_toolkit">Buy bundle</a>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>

        <div class="jm-p-faq" style="margin-top:40px;">
            <h3>Seeker FAQ</h3>
            <details><summary>Do credits expire?</summary><p>No. Credits never expire. Buy them at any time and use them whenever you need.</p></details>
            <details><summary>Can I cancel Premium?</summary><p>Yes. Cancel before your next billing date and you won't be charged again. You keep Premium access until the end of the period you paid for.</p></details>
            <details><summary>What is priority application?</summary><p>Premium members' applications appear higher in employer dashboards, giving you more visibility especially for competitive roles.</p></details>
            <details><summary>What are early job alerts?</summary><p>Premium members receive new job notifications 24 hours before they're visible to free users — giving you a head start on every application.</p></details>
        </div>
    </div>

    <?php jm_minimal_footer(); ?>
</div><!-- /shell -->

<script>
function switchTab(name, btn) {
    document.querySelectorAll('.jm-p-tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.jm-p-panel').forEach(p => p.classList.remove('active'));
    btn.classList.add('active');
    const panel = document.getElementById('tab-' + name);
    panel.classList.add('active');
    // Re-trigger entry animations
    panel.querySelectorAll('.jm-p-reveal').forEach((el, i) => {
        el.style.animation = 'none';
        el.offsetHeight; // reflow
        el.style.animation = '';
    });
    history.replaceState(null, '', '?tab=' + name);
}

// Intersection Observer for scroll-triggered animation
const observer = new IntersectionObserver(entries => {
    entries.forEach(e => {
        if (e.isIntersecting) {
            e.target.style.opacity = '1';
            e.target.style.transform = 'translateY(0)';
        }
    });
}, { threshold: 0.12 });

document.querySelectorAll('.jm-p-card, .jm-p-solo, .jm-p-bundle, .jm-p-pack').forEach(el => {
    observer.observe(el);
});
</script>
</body>
</html>
