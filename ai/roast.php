<?php
define('JOBMINGTON', true);
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/monetization.php';
require_once __DIR__ . '/../includes/seeker_premium.php';

Session::start();
Session::requireLogin();

$pdo         = db();
$userId      = (int) Session::userId();
$isPremium   = jm_seeker_is_premium($pdo, $userId);
$userCredits = jm_seeker_credit_balance($pdo, $userId);
$isLoggedIn  = true;
$toolCost    = TOOL_COST_CV_OPTIMIZER;
$pageTitle   = 'CV Roast | Jobmington';
$activeAIPage = 'roast';

// Load user's saved CVs
$savedCvs = [];
try {
    $stmt = $pdo->prepare("
        SELECT cv_id, full_name, headline,
               (SELECT COUNT(*) FROM cv_skills WHERE cv_id = cp.cv_id) AS skill_count,
               (SELECT COUNT(*) FROM cv_experience WHERE cv_id = cp.cv_id) AS exp_count,
               updated_at
        FROM cv_profiles cp
        WHERE user_id = ?
        ORDER BY updated_at DESC
    ");
    $stmt->execute([$userId]);
    $savedCvs = $stmt->fetchAll();
} catch (Throwable $e) { $savedCvs = []; }

$hasCv       = !empty($savedCvs);
$primaryCv   = $savedCvs[0] ?? null;

require_once __DIR__ . '/../includes/ai-header.php';
?>

<script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>

<style>
/* ── Layout ──────────────────────────────────────────────────────── */
@keyframes jmFadeUp { from{opacity:0;transform:translateY(16px)} to{opacity:1;transform:translateY(0)} }
@keyframes jmRingFill { from{stroke-dashoffset:314} to{stroke-dashoffset:var(--target-offset)} }
@keyframes jmPulse { 0%,100%{opacity:1} 50%{opacity:.4} }

.jm-roast-wrap {
    max-width: 1160px;
    margin: 0 auto;
    padding: 32px 24px 64px;
}

/* ── Page hero ────────────────────────────────────────────────────── */
.jm-roast-hero {
    margin-bottom: 36px;
    animation: jmFadeUp .5s ease both;
}
.jm-roast-hero h1 {
    font-size: clamp(28px, 4vw, 44px);
    font-weight: 800;
    color: var(--jm-ink);
    margin: 0 0 10px;
    line-height: 1.05;
}
.jm-roast-hero p {
    font-size: 16px;
    color: var(--jm-muted);
    margin: 0;
    max-width: 540px;
    line-height: 1.7;
}

/* ── Grid ─────────────────────────────────────────────────────────── */
.jm-roast-grid {
    display: grid;
    grid-template-columns: 360px minmax(0, 1fr);
    gap: 24px;
    align-items: start;
}
@media (max-width: 860px) {
    .jm-roast-grid { grid-template-columns: 1fr; }
}

/* ── Left panel ───────────────────────────────────────────────────── */
.jm-roast-aside {
    display: grid;
    gap: 16px;
    animation: jmFadeUp .5s ease both .1s;
}

.jm-roast-input-card {
    border: 1px solid var(--jm-line);
    border-radius: 12px;
    background: #ffffff;
    padding: 26px;
}
.jm-roast-input-card h2 {
    font-size: 17px;
    font-weight: 800;
    color: var(--jm-ink);
    margin: 0 0 6px;
}
.jm-roast-input-card p {
    font-size: 13px;
    color: var(--jm-muted);
    margin: 0 0 16px;
    line-height: 1.6;
}
.jm-roast-textarea {
    width: 100%;
    min-height: 100px;
    resize: vertical;
    border: 1px solid #b8c8df;
    border-radius: 8px;
    background: var(--jm-soft);
    color: var(--jm-ink);
    padding: 12px 14px;
    font: inherit;
    font-size: 14px;
    outline: none;
    transition: border-color .15s, box-shadow .15s;
    box-sizing: border-box;
}
.jm-roast-textarea:focus {
    border-color: var(--jm-blue);
    box-shadow: 0 0 0 3px rgba(6,64,163,.08);
    background: #ffffff;
}
.jm-roast-textarea::placeholder { color: #94a3b8; }

.btn-analyze {
    width: 100%;
    justify-content: center;
    margin-top: 12px;
    min-height: 46px;
    font-size: 14px;
    font-weight: 700;
    gap: 8px;
}
.btn-analyze:disabled { opacity: .6; cursor: not-allowed; }

/* Credit badge */
.jm-roast-credit {
    display: flex;
    align-items: center;
    justify-content: space-between;
    border: 1px solid var(--jm-line);
    border-radius: 8px;
    background: var(--jm-soft);
    padding: 12px 16px;
    font-size: 13px;
}
.jm-roast-credit-label { color: var(--jm-muted); font-weight: 600; }
.jm-roast-credit-val   { color: var(--jm-ink);  font-weight: 800; }
.jm-roast-premium-pill {
    background: var(--jm-green);
    color: #fff;
    font-size: 10px;
    font-weight: 800;
    padding: 2px 9px;
    border-radius: 99px;
    text-transform: uppercase;
    letter-spacing: .06em;
}

/* Score ring */
.jm-roast-score {
    border: 1px solid var(--jm-line);
    border-radius: 12px;
    background: #ffffff;
    padding: 28px 24px;
    text-align: center;
    display: none;
}
.jm-roast-score.visible { display: block; animation: jmFadeUp .4s ease both; }
.jm-score-ring-wrap {
    position: relative;
    width: 130px;
    height: 130px;
    margin: 0 auto 16px;
}
.jm-score-svg {
    width: 130px;
    height: 130px;
    transform: rotate(-90deg);
}
.jm-score-track { fill: none; stroke: var(--jm-line); stroke-width: 9; }
.jm-score-fill  {
    fill: none;
    stroke: var(--jm-blue);
    stroke-width: 9;
    stroke-linecap: round;
    stroke-dasharray: 314;
    stroke-dashoffset: 314;
    transition: stroke-dashoffset 1s cubic-bezier(.22,.68,0,1.1), stroke .4s;
}
.jm-score-fill.strong  { stroke: var(--jm-green); }
.jm-score-fill.weak    { stroke: #ef4444; }
.jm-score-fill.mid     { stroke: var(--jm-orange); }
.jm-score-num {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 32px;
    font-weight: 800;
    color: var(--jm-ink);
    line-height: 1;
}
.jm-score-status {
    font-size: 14px;
    font-weight: 700;
    color: var(--jm-muted);
    display: block;
    margin-bottom: 4px;
}
.jm-score-msg {
    font-size: 13px;
    color: var(--jm-muted);
    line-height: 1.55;
}

/* ── Right: Report card ───────────────────────────────────────────── */
.jm-roast-report {
    border: 1px solid var(--jm-line);
    border-radius: 12px;
    background: #ffffff;
    overflow: hidden;
    position: relative;
    min-height: 480px;
    display: flex;
    flex-direction: column;
    animation: jmFadeUp .5s ease both .15s;
}
.jm-roast-report-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16px 24px;
    border-bottom: 1px solid var(--jm-line);
    background: var(--jm-soft);
}
.jm-roast-report-head span {
    font-size: 11px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .09em;
    color: var(--jm-muted);
}
.jm-roast-lock-status {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: 11px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .07em;
    color: var(--jm-muted);
}
.jm-roast-lock-status svg { width: 13px; height: 13px; }
.jm-roast-lock-status.unlocked { color: var(--jm-green); }

.jm-roast-report-body {
    padding: 28px;
    flex: 1;
}

/* ── Empty state ─────────────────────────────────────────────────── */
.jm-roast-empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    min-height: 320px;
    text-align: center;
    gap: 14px;
}
.jm-roast-empty-icon {
    width: 52px;
    height: 52px;
    border-radius: 12px;
    background: var(--jm-soft);
    border: 1px solid var(--jm-line);
    display: grid;
    place-items: center;
    color: var(--jm-blue);
}
.jm-roast-empty h3 { font-size: 17px; color: var(--jm-ink); margin: 0 0 4px; }
.jm-roast-empty p  { font-size: 14px; color: var(--jm-muted); margin: 0; max-width: 360px; line-height: 1.6; }

/* ── Paywall ─────────────────────────────────────────────────────── */
.jm-roast-paywall {
    padding: 0 28px 28px;
}
.jm-roast-paywall.hidden { display: none; }
.jm-roast-paywall-inner {
    border: 1px solid var(--jm-line);
    border-top: 3px solid var(--jm-orange);
    border-radius: 10px;
    padding: 24px;
    background: #fffcf7;
    text-align: center;
}
.jm-roast-paywall-inner h3 {
    font-size: 17px;
    font-weight: 800;
    color: var(--jm-ink);
    margin: 12px 0 6px;
}
.jm-roast-paywall-inner p {
    font-size: 13px;
    color: var(--jm-muted);
    margin: 0 0 18px;
    line-height: 1.6;
}
.jm-roast-paywall-lock {
    width: 40px;
    height: 40px;
    border-radius: 8px;
    background: #fff3e0;
    border: 1px solid #f3d4a3;
    display: grid;
    place-items: center;
    margin: 0 auto;
    color: #9a5a00;
}
.btn-unlock {
    justify-content: center;
    width: 100%;
    min-height: 46px;
    background: var(--jm-orange);
    border-color: var(--jm-orange);
    color: var(--jm-ink) !important;
    font-weight: 800;
    font-size: 14px;
    gap: 8px;
}
.btn-unlock:hover { background: #e8920f; border-color: #e8920f; }
.btn-unlock:disabled { opacity: .6; cursor: not-allowed; }
.jm-roast-balance {
    display: block;
    font-size: 12px;
    color: var(--jm-muted);
    margin-top: 10px;
}

/* ── Report content ──────────────────────────────────────────────── */
.jm-roast-summary {
    font-size: 14px;
    color: var(--jm-muted);
    line-height: 1.7;
    margin-bottom: 24px;
    padding-bottom: 20px;
    border-bottom: 1px solid var(--jm-line);
}
.jm-roast-section { margin-bottom: 24px; }
.jm-roast-section-label {
    font-size: 11px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .09em;
    display: flex;
    align-items: center;
    gap: 7px;
    margin-bottom: 14px;
}
.jm-roast-section-label.green { color: var(--jm-green); }
.jm-roast-section-label.red   { color: #b42318; }
.jm-roast-section-label.blue  { color: var(--jm-blue); }
.jm-roast-section-label svg { width: 14px; height: 14px; }

.jm-roast-issue {
    border: 1px solid var(--jm-line);
    border-left: 3px solid #ef4444;
    border-radius: 0 8px 8px 0;
    padding: 14px 16px;
    margin-bottom: 10px;
    background: #fff9f9;
}
.jm-roast-issue-title {
    font-size: 14px;
    font-weight: 700;
    color: #b42318;
    margin-bottom: 4px;
}
.jm-roast-issue-critique {
    font-size: 13px;
    color: var(--jm-muted);
    margin-bottom: 8px;
    line-height: 1.55;
}
.jm-roast-issue-fix {
    font-size: 13px;
    color: var(--jm-green);
    font-weight: 600;
    line-height: 1.55;
    display: flex;
    gap: 6px;
    align-items: flex-start;
}
.jm-roast-issue-fix svg { flex-shrink: 0; margin-top: 2px; }

.jm-roast-strength {
    display: flex;
    align-items: flex-start;
    gap: 8px;
    font-size: 13px;
    color: var(--jm-ink);
    padding: 6px 0;
    border-bottom: 1px solid var(--jm-line);
    line-height: 1.5;
}
.jm-roast-strength:last-child { border-bottom: none; }
.jm-roast-strength svg { flex-shrink: 0; color: var(--jm-green); margin-top: 2px; }

.jm-roast-optimized {
    border: 1px solid #bde9df;
    border-radius: 8px;
    background: #f0fdf9;
    padding: 18px;
    margin-top: 4px;
}
.jm-roast-optimized-label {
    font-size: 11px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .08em;
    color: var(--jm-green);
    margin-bottom: 8px;
    display: block;
}
.jm-roast-optimized p {
    font-size: 14px;
    color: var(--jm-ink);
    margin: 0;
    line-height: 1.7;
}

.jm-roast-keywords {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 4px;
}
.jm-roast-keywords span {
    background: var(--jm-warm);
    border: 1px solid #f3d4a3;
    color: #9a5a00;
    font-size: 12px;
    font-weight: 700;
    padding: 4px 12px;
    border-radius: 99px;
}

.jm-roast-next-steps {
    display: grid;
    gap: 8px;
    margin-top: 4px;
}
.jm-roast-step {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    font-size: 13px;
    color: var(--jm-ink);
    line-height: 1.55;
}
.jm-roast-step-num {
    flex-shrink: 0;
    width: 22px;
    height: 22px;
    border-radius: 999px;
    background: #eef5ff;
    border: 1px solid #c9dcf5;
    color: var(--jm-blue);
    font-size: 11px;
    font-weight: 800;
    display: grid;
    place-items: center;
    margin-top: 1px;
}

.jm-roast-cta {
    display: flex;
    gap: 10px;
    margin-top: 24px;
    padding-top: 20px;
    border-top: 1px solid var(--jm-line);
}
.jm-roast-cta .jm-button { flex: 1; justify-content: center; }

/* ── CV source card ───────────────────────────────────────────────── */
.jm-roast-cv-card {
    border: 1px solid var(--jm-line);
    border-radius: 12px;
    background: #ffffff;
    overflow: hidden;
}
.jm-roast-cv-label {
    padding: 10px 16px;
    background: var(--jm-soft);
    border-bottom: 1px solid var(--jm-line);
    font-size: 11px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .09em;
    color: var(--jm-muted);
    display: flex;
    align-items: center;
    gap: 6px;
}
.jm-roast-cv-label svg { color: var(--jm-blue); }
.jm-roast-cv-row {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 14px 16px;
    cursor: pointer;
    border-bottom: 1px solid var(--jm-line);
    transition: background .15s;
}
.jm-roast-cv-row:last-child { border-bottom: none; }
.jm-roast-cv-row:hover { background: var(--jm-soft); }
.jm-roast-cv-row input[type=radio] { display:none; }
.jm-roast-cv-radio {
    width: 18px; height: 18px; border-radius: 999px;
    border: 2px solid var(--jm-line); flex-shrink: 0;
    transition: border-color .15s, background .15s;
    display: grid; place-items: center;
}
.jm-roast-cv-row.selected .jm-roast-cv-radio {
    border-color: var(--jm-blue); background: var(--jm-blue);
}
.jm-roast-cv-radio::after {
    content: ''; width: 7px; height: 7px; border-radius: 999px;
    background: #fff; opacity: 0; transition: opacity .15s;
}
.jm-roast-cv-row.selected .jm-roast-cv-radio::after { opacity: 1; }
.jm-roast-cv-info { flex: 1; min-width: 0; }
.jm-roast-cv-name {
    font-size: 14px; font-weight: 700; color: var(--jm-ink);
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.jm-roast-cv-meta { font-size: 12px; color: var(--jm-muted); margin-top: 2px; }

/* ── Source tabs ──────────────────────────────────────────────────── */
.jm-roast-src-tab {
    flex: 1; display: flex; align-items: center; justify-content: center; gap: 6px;
    padding: 11px 14px; border: none; background: transparent;
    font: inherit; font-size: 12px; font-weight: 700; color: var(--jm-muted);
    cursor: pointer; border-bottom: 2px solid transparent;
    transition: color .15s, border-color .15s;
}
.jm-roast-src-tab.active { color: var(--jm-blue); border-bottom-color: var(--jm-blue); }
.jm-roast-src-panel { padding: 16px; }
.jm-roast-src-panel.hidden { display: none; }

/* ── Drop zone ─────────────────────────────────────────────────────── */
.jm-roast-dropzone {
    border: 2px dashed #b8c8df;
    border-radius: 10px;
    padding: 28px 20px;
    text-align: center;
    cursor: pointer;
    transition: border-color .15s, background .15s;
    background: var(--jm-soft);
}
.jm-roast-dropzone:hover,
.jm-roast-dropzone.dragging {
    border-color: var(--jm-blue);
    background: #eef5ff;
}
.jm-roast-dz-idle { display:flex; flex-direction:column; align-items:center; gap:8px; }
.jm-roast-dz-idle strong { font-size:14px; color:var(--jm-ink); }
.jm-roast-dz-idle span   { font-size:12px; color:var(--jm-muted); }
.jm-roast-dz-file { display:flex; align-items:center; gap:10px; justify-content:center; }
.jm-roast-dz-file span   { font-size:13px; font-weight:700; color:var(--jm-ink); flex:1; text-align:left; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.hidden { display:none !important; }

/* No CV state */
.jm-roast-no-cv {
    border: 1px dashed var(--jm-line);
    border-radius: 12px;
    padding: 24px;
    text-align: center;
}
.jm-roast-no-cv p { font-size: 14px; color: var(--jm-muted); margin: 0 0 14px; }
.jm-roast-no-cv .jm-button { justify-content: center; }
</style>

<div class="jm-roast-wrap">

    <?= jm_breadcrumbs([['label' => 'Tools', 'url' => '/jobmington/tools/'], ['label' => 'Resume Optimizer']]) ?>

    <!-- Hero -->
    <div class="jm-roast-hero">
        <p class="jm-kicker" style="font-size:12px;letter-spacing:.1em;text-transform:uppercase;font-weight:800;margin-bottom:10px;">AI-Powered</p>
        <h1>CV Roast.</h1>
        <p>Get a real ATS score, specific fixes, and an optimised summary — before a recruiter sees your CV.</p>
    </div>

    <div class="jm-roast-grid">

        <!-- Left: Input + Score -->
        <aside class="jm-roast-aside">

            <!-- Source tabs -->
            <div class="jm-roast-cv-card">
                <div class="jm-roast-cv-label" style="gap:0;padding:0;">
                    <button class="jm-roast-src-tab active" id="tab-upload" onclick="Roast.switchSource('upload')">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                        Upload CV
                    </button>
                    <?php if ($hasCv): ?>
                    <button class="jm-roast-src-tab" id="tab-saved" onclick="Roast.switchSource('saved')">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                        Jobmington CV
                    </button>
                    <?php endif; ?>
                </div>

                <!-- Upload source -->
                <div id="src-upload" class="jm-roast-src-panel">
                    <div class="jm-roast-dropzone" id="drop-zone"
                         onclick="document.getElementById('cv-file-input').click()"
                         ondragover="event.preventDefault();this.classList.add('dragging')"
                         ondragleave="this.classList.remove('dragging')"
                         ondrop="Roast.handleDrop(event)">
                        <input type="file" id="cv-file-input" hidden accept=".pdf,.docx"
                               onchange="Roast.handleFile(this.files[0])">
                        <div class="jm-roast-dz-idle" id="dz-idle">
                            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="color:var(--jm-blue)"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                            <strong>Drop your CV here</strong>
                            <span>PDF or DOCX · max 5 MB</span>
                        </div>
                        <div class="jm-roast-dz-file hidden" id="dz-file">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="var(--jm-green)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                            <span id="dz-filename">No file selected</span>
                            <button type="button" onclick="Roast.clearFile(event)" style="background:none;border:none;color:var(--jm-muted);cursor:pointer;font-size:12px;font-weight:700;padding:0;">Remove</button>
                        </div>
                    </div>
                    <!-- Jobmington verified badge (shown when detected) -->
                    <div id="jm-verified-badge" class="hidden" style="display:flex;align-items:center;gap:8px;padding:10px 14px;background:#f0fdf9;border:1px solid #bde9df;border-radius:8px;margin-top:10px;font-size:13px;font-weight:700;color:var(--jm-green);">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                        Jobmington CV detected — ATS-optimised
                    </div>
                </div>

                <!-- Saved CV source -->
                <?php if ($hasCv): ?>
                <div id="src-saved" class="jm-roast-src-panel hidden">
                    <?php foreach ($savedCvs as $i => $cv): ?>
                    <label class="jm-roast-cv-row <?= $i === 0 ? 'selected' : '' ?>"
                           onclick="Roast.selectCv(<?= (int)$cv['cv_id'] ?>, this)">
                        <input type="radio" name="cv_id" value="<?= (int)$cv['cv_id'] ?>" <?= $i === 0 ? 'checked' : '' ?>>
                        <div class="jm-roast-cv-radio"></div>
                        <div class="jm-roast-cv-info">
                            <div class="jm-roast-cv-name"><?= e($cv['full_name'] ?: 'Untitled CV') ?></div>
                            <div class="jm-roast-cv-meta">
                                <?= (int)$cv['skill_count'] ?> skill<?= $cv['skill_count'] != 1 ? 's' : '' ?>
                                · <?= (int)$cv['exp_count'] ?> role<?= $cv['exp_count'] != 1 ? 's' : '' ?>
                                · <?= date('d M Y', strtotime($cv['updated_at'])) ?>
                            </div>
                        </div>
                    </label>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Target role -->
            <div class="jm-roast-input-card">
                <h2>Target role <span style="font-weight:400;font-size:13px;color:var(--jm-muted);">(optional)</span></h2>
                <p>Paste a job description or describe the role — powers keyword matching.</p>
                <textarea id="target-role" class="jm-roast-textarea"
                    placeholder="e.g. Product Manager in Lagos, fintech, stakeholder management, SQL, roadmap ownership…"></textarea>
                <button class="jm-button btn-analyze" onclick="Roast.run()">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    Roast my CV
                </button>
            </div>

            <!-- Credit info -->
            <div class="jm-roast-credit">
                <span class="jm-roast-credit-label">Access</span>
                <?php if ($isPremium): ?>
                    <span class="jm-roast-premium-pill">Premium — unlimited</span>
                <?php else: ?>
                    <span class="jm-roast-credit-val"><?= $userCredits ?> credit<?= $userCredits !== 1 ? 's' : '' ?> · <?= jm_format_ngn(PRICE_CREDITS_SINGLE) ?>/use</span>
                <?php endif; ?>
            </div>

            <!-- Score ring -->
            <div class="jm-roast-score" id="score-panel">
                <div class="jm-score-ring-wrap">
                    <svg class="jm-score-svg" viewBox="0 0 120 120">
                        <circle class="jm-score-track" cx="60" cy="60" r="50"/>
                        <circle class="jm-score-fill" id="score-fill" cx="60" cy="60" r="50"/>
                    </svg>
                    <div class="jm-score-num" id="score-num">0</div>
                </div>
                <span class="jm-score-status" id="score-status">Analysing…</span>
                <p class="jm-score-msg" id="score-msg"></p>
            </div>

            <?php if (!$isPremium): ?>
            <div style="text-align:center;">
                <a href="<?= SITE_URL ?>/payments/seeker-premium.php" style="font-size:13px;font-weight:700;color:var(--jm-blue);text-decoration:none;">Go Premium for unlimited access →</a>
            </div>
            <?php endif; ?>

        </aside>

        <!-- Right: Report card -->
        <main class="jm-roast-report">

            <div class="jm-roast-report-head">
                <span>Analysis report</span>
                <span class="jm-roast-lock-status" id="lock-status">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                    Locked
                </span>
            </div>

            <div class="jm-roast-report-body" id="report-content">
                <!-- Empty state -->
                <div class="jm-roast-empty" id="empty-state">
                    <div class="jm-roast-empty-icon">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                    </div>
                    <div>
                        <h3>No report yet</h3>
                        <p>Enter your target role and click "Roast my CV" to get your personalised ATS score and fix list.</p>
                    </div>
                </div>
                <!-- Report renders here -->
                <div id="report-output" style="display:none;"></div>
            </div>

            <!-- Paywall -->
            <div class="jm-roast-paywall hidden" id="paywall">
                <div class="jm-roast-paywall-inner">
                    <div class="jm-roast-paywall-lock">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                    </div>
                    <h3>Unlock the full report</h3>
                    <p>See every issue, the exact fix, and an optimised summary you can copy straight into your CV.</p>
                    <button class="jm-button btn-unlock" onclick="Roast.unlock()">
                        <?php if ($isPremium): ?>
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                            Unlock — Premium
                        <?php else: ?>
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                            Use <?= $toolCost ?> credit<?= $toolCost > 1 ? 's' : '' ?>
                        <?php endif; ?>
                    </button>
                    <span class="jm-roast-balance" id="balance-display">
                        <?php if ($isPremium): ?>
                            Premium — unlimited access
                        <?php else: ?>
                            Balance: <?= number_format($userCredits) ?> credit<?= $userCredits !== 1 ? 's' : '' ?>
                        <?php endif; ?>
                    </span>
                </div>
            </div>

        </main>
    </div>
</div>

<script>
const JOBMINGTON_SITE_URL = <?= json_encode(SITE_URL) ?>;
let selectedCvId    = <?= (int)($primaryCv['cv_id'] ?? 0) ?>;
let currentSource   = 'upload'; // 'upload' | 'saved'
let uploadedCvText  = '';
let uploadedIsJobmington = false;

const Roast = {

    switchSource: (src) => {
        currentSource = src;
        document.querySelectorAll('.jm-roast-src-tab').forEach(t => t.classList.remove('active'));
        document.getElementById('tab-' + src)?.classList.add('active');
        document.querySelectorAll('.jm-roast-src-panel').forEach(p => p.classList.add('hidden'));
        document.getElementById('src-' + src)?.classList.remove('hidden');
    },

    selectCv: (cvId, rowEl) => {
        selectedCvId = cvId;
        document.querySelectorAll('.jm-roast-cv-row').forEach(r => r.classList.remove('selected'));
        rowEl.classList.add('selected');
    },

    handleDrop: (e) => {
        e.preventDefault();
        document.getElementById('drop-zone').classList.remove('dragging');
        const file = e.dataTransfer.files[0];
        if (file) Roast.handleFile(file);
    },

    handleFile: async (file) => {
        if (!file) return;
        const allowed = ['application/pdf', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
        if (!allowed.includes(file.type) && !file.name.match(/\.(pdf|docx)$/i)) {
            JM.toast('Only PDF and DOCX files are accepted.', 'error'); return;
        }
        if (file.size > 5 * 1024 * 1024) {
            JM.toast('File is too large. Maximum size is 5 MB.', 'error'); return;
        }

        // Show uploading state
        const dz = document.getElementById('drop-zone');
        dz.classList.add('dragging');
        document.getElementById('dz-idle').classList.add('hidden');
        document.getElementById('dz-file').classList.remove('hidden');
        document.getElementById('dz-filename').textContent = 'Extracting text…';

        const formData = new FormData();
        formData.append('cv_file', file);

        try {
            const res  = await fetch(`${JOBMINGTON_SITE_URL}/api/cv-extract.php`, { method: 'POST', body: formData });
            const data = await res.json();

            dz.classList.remove('dragging');

            if (data.success) {
                uploadedCvText       = data.data.text;
                uploadedIsJobmington = data.data.is_jobmington;
                document.getElementById('dz-filename').textContent = data.data.filename;

                const badge = document.getElementById('jm-verified-badge');
                if (data.data.is_jobmington) {
                    badge.style.display = 'flex';
                    badge.classList.remove('hidden');
                } else {
                    badge.style.display = 'none';
                }
            } else {
                Roast.clearFile(null);
                JM.toast(data.message || 'Could not read file.', 'error');
            }
        } catch (err) {
            dz.classList.remove('dragging');
            Roast.clearFile(null);
            JM.toast('Upload failed. Please try again.', 'error');
        }
    },

    clearFile: (e) => {
        if (e) e.stopPropagation();
        uploadedCvText = '';
        uploadedIsJobmington = false;
        document.getElementById('cv-file-input').value = '';
        document.getElementById('dz-idle').classList.remove('hidden');
        document.getElementById('dz-file').classList.add('hidden');
        document.getElementById('jm-verified-badge').style.display = 'none';
    },

    // Single entry point - routes to upload or saved depending on active tab
    run: () => {
        if (currentSource === 'upload') {
            if (!uploadedCvText) {
                JM.toast('Please upload a CV file first.', 'warning'); return;
            }
            Roast.analyzeUpload();
        } else {
            Roast.analyzeSavedCv(false);
        }
    },

    analyzeUpload: async () => {
        const isPremium      = <?= $isPremium ? 'true' : 'false' ?>;
        const currentBalance = <?= $userCredits ?>;
        const cost           = <?= $toolCost ?>;

        if (!isPremium && currentBalance < cost) {
            JM.toast(`You need ${cost} credit${cost > 1 ? 's' : ''} but only have ${currentBalance}.`, 'error', 'Insufficient credits');
            setTimeout(() => window.location.href = `${JOBMINGTON_SITE_URL}/payments/credits.php?tool=cv_optimizer&paywall=1`, 2000);
            return;
        }

        const btn = document.querySelector('.btn-analyze');
        const orig = btn ? btn.innerHTML : '';
        if (btn) { btn.innerHTML = `<svg class="jm-spin" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M21 12a9 9 0 1 1-6.22-8.56"/></svg> Analysing…`; btn.disabled = true; }

        try {
            const targetRole = document.getElementById('target-role')?.value || '';
            const res  = await fetch(`${JOBMINGTON_SITE_URL}/api/cv-roast.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    cv_text: uploadedCvText,
                    is_jobmington: uploadedIsJobmington,
                    target_role: targetRole,
                    job_description: targetRole,
                    charge: true,
                }),
            });
            const data = await res.json();
            if (btn) { btn.innerHTML = orig; btn.disabled = false; }

            if (data.success) {
                Roast.renderReport(data.data.report, data.data.balance, data.data.is_jobmington);
                const msg = data.data.premium ? 'Premium — unlimited' : `−${data.data.cost} credit${data.data.cost !== 1 ? 's' : ''}`;
                JM.toast(`Report ready. ${msg}`, 'success', 'Done');
            } else {
                if (data.error === 'insufficient_credits') {
                    JM.toast(data.message, 'error');
                    setTimeout(() => window.location.href = data.buy || `${JOBMINGTON_SITE_URL}/payments/credits.php`, 2000);
                } else {
                    JM.toast(data.message || 'Something went wrong.', 'error');
                }
            }
        } catch (err) {
            if (btn) { btn.innerHTML = orig; btn.disabled = false; }
            JM.toast('Connection error. Please try again.', 'error');
        }
    },

    celebrate: () => {
        const colors = ['#0640a3', '#f59f22', '#0f766e'];
        confetti({ particleCount: 100, spread: 70, origin: { y: 0.6 }, colors, zIndex: 9999 });
        setTimeout(() => confetti({ particleCount: 50, angle: 60,  spread: 55, origin: { x: 0 }, colors, zIndex: 9999 }), 200);
        setTimeout(() => confetti({ particleCount: 50, angle: 120, spread: 55, origin: { x: 1 }, colors, zIndex: 9999 }), 400);
    },

    setScore: (score) => {
        const fill    = document.getElementById('score-fill');
        const numEl   = document.getElementById('score-num');
        const circ    = 314;
        const offset  = circ - (circ * score / 100);
        const panel   = document.getElementById('score-panel');

        panel.classList.add('visible');
        numEl.textContent = score;

        fill.classList.remove('strong', 'mid', 'weak');
        if (score >= 80)      fill.classList.add('strong');
        else if (score >= 60) fill.classList.add('mid');
        else                  fill.classList.add('weak');

        fill.style.strokeDashoffset = offset;

        const statusEl = document.getElementById('score-status');
        if (score >= 80)      { statusEl.textContent = 'Strong'; statusEl.style.color = 'var(--jm-green)'; }
        else if (score >= 60) { statusEl.textContent = 'Promising'; statusEl.style.color = 'var(--jm-orange)'; }
        else                  { statusEl.textContent = 'Needs work'; statusEl.style.color = '#b42318'; }
    },

    e: (val) => {
        const d = document.createElement('div');
        d.textContent = val == null ? '' : String(val);
        return d.innerHTML;
    },

    renderReport: (report, balance, isJobmington = false) => {
        const score    = Number(report.score || 0);
        const issues   = Array.isArray(report.issues)        ? report.issues        : [];
        const strengths= Array.isArray(report.strengths)     ? report.strengths     : [];
        const keywords = Array.isArray(report.missing_keywords)? report.missing_keywords: [];
        const steps    = Array.isArray(report.next_steps)    ? report.next_steps    : [];

        Roast.setScore(score);
        document.getElementById('score-msg').textContent = report.summary || '';

        // Lock status
        const lockEl = document.getElementById('lock-status');
        lockEl.classList.add('unlocked');
        const verifiedHtml = isJobmington
            ? `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="13" height="13"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg> Jobmington Verified`
            : `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a4 4 0 0 1 8 0v4"/></svg> Unlocked`;
        lockEl.innerHTML = verifiedHtml;

        document.getElementById('empty-state').style.display  = 'none';
        document.getElementById('report-output').style.display = 'block';
        document.getElementById('paywall').classList.add('hidden');

        const svgCheck = `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>`;
        const svgArrow = `<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>`;

        let html = `<p class="jm-roast-summary">${Roast.e(report.summary || '')}</p>`;

        if (strengths.length) {
            html += `
            <div class="jm-roast-section">
                <div class="jm-roast-section-label green">
                    ${svgCheck} What's working
                </div>
                ${strengths.map(s => `<div class="jm-roast-strength">${svgCheck} ${Roast.e(s)}</div>`).join('')}
            </div>`;
        }

        if (issues.length) {
            html += `
            <div class="jm-roast-section">
                <div class="jm-roast-section-label red">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    Issues to fix
                </div>
                ${issues.map(i => `
                <div class="jm-roast-issue">
                    <div class="jm-roast-issue-title">${Roast.e(i.title || '')}</div>
                    <div class="jm-roast-issue-critique">${Roast.e(i.critique || '')}</div>
                    <div class="jm-roast-issue-fix">${svgCheck} ${Roast.e(i.fix || '')}</div>
                </div>`).join('')}
            </div>`;
        }

        if (report.optimized_summary) {
            html += `
            <div class="jm-roast-section">
                <div class="jm-roast-section-label green">${svgCheck} Optimised summary</div>
                <div class="jm-roast-optimized">
                    <p>${Roast.e(report.optimized_summary)}</p>
                </div>
            </div>`;
        }

        if (keywords.length) {
            html += `
            <div class="jm-roast-section">
                <div class="jm-roast-section-label blue">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>
                    Missing keywords
                </div>
                <div class="jm-roast-keywords">${keywords.map(k => `<span>${Roast.e(k)}</span>`).join('')}</div>
            </div>`;
        }

        if (steps.length) {
            html += `
            <div class="jm-roast-section">
                <div class="jm-roast-section-label blue">
                    ${svgArrow} Next moves
                </div>
                <div class="jm-roast-next-steps">
                    ${steps.map((s, i) => `<div class="jm-roast-step"><span class="jm-roast-step-num">${i + 1}</span>${Roast.e(s)}</div>`).join('')}
                </div>
            </div>`;
        }

        html += `
        <div class="jm-roast-cta">
            <a class="jm-button" href="${JOBMINGTON_SITE_URL}/cv-builder/">Fix my CV</a>
            <a class="jm-button secondary" href="${JOBMINGTON_SITE_URL}/jobs/">Browse jobs</a>
        </div>`;

        document.getElementById('report-output').innerHTML = html;

        // Update balance display
        const isPrem = <?= $isPremium ? 'true' : 'false' ?>;
        const balEl  = document.getElementById('balance-display');
        if (balEl && balance !== undefined) {
            balEl.textContent = isPrem ? 'Premium — unlimited access' : `Balance: ${Number(balance).toLocaleString()} credit${balance !== 1 ? 's' : ''}`;
        }

        if (score >= 90) Roast.celebrate();
    },

    analyzeSavedCv: async (fromUnlock = false) => {
        const isPremium      = <?= $isPremium ? 'true' : 'false' ?>;
        const currentBalance = <?= $userCredits ?>;
        const cost           = <?= $toolCost ?>;

        if (!isPremium && currentBalance < cost) {
            JM.toast(`You need ${cost} credit${cost > 1 ? 's' : ''} but only have ${currentBalance}.`, 'error', 'Insufficient credits');
            setTimeout(() => window.location.href = `${JOBMINGTON_SITE_URL}/payments/credits.php?tool=cv_optimizer&paywall=1`, 2000);
            return;
        }

        const btn = fromUnlock
            ? document.querySelector('.btn-unlock')
            : document.querySelector('.btn-analyze');
        const originalHtml = btn ? btn.innerHTML : '';
        if (btn) {
            btn.innerHTML = `<svg class="jm-spin" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M21 12a9 9 0 1 1-6.22-8.56"/></svg> Analysing…`;
            btn.disabled = true;
        }

        try {
            const targetRole = document.getElementById('target-role')?.value || '';
            const res  = await fetch(`${JOBMINGTON_SITE_URL}/api/cv-roast.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ cv_id: selectedCvId, target_role: targetRole, job_description: targetRole, charge: true }),
            });
            const data = await res.json();

            if (data.success) {
                Roast.renderReport(data.data.report, data.data.balance, data.data.is_jobmington);
                const msg = data.data.premium ? 'Premium — unlimited' : `−${data.data.cost} credit${data.data.cost !== 1 ? 's' : ''}`;
                JM.toast(`Report ready. ${msg}`, 'success', 'Done');
            } else {
                if (btn) { btn.innerHTML = originalHtml; btn.disabled = false; }
                if (data.error === 'insufficient_credits') {
                    JM.toast(data.message, 'error', 'Insufficient credits');
                    setTimeout(() => window.location.href = data.buy || `${JOBMINGTON_SITE_URL}/payments/credits.php`, 2000);
                } else {
                    JM.toast(data.message || 'Something went wrong.', 'error');
                }
            }
        } catch (err) {
            if (btn) { btn.innerHTML = originalHtml; btn.disabled = false; }
            JM.toast('Connection error. Please try again.', 'error');
        }
    },

    unlock: async () => { await Roast.analyzeSavedCv(true); }
};

// Spinner animation
const style = document.createElement('style');
style.textContent = `
  @keyframes jmSpin { to { transform: rotate(360deg); } }
  .jm-spin { animation: jmSpin .8s linear infinite; }
`;
document.head.appendChild(style);
</script>

<?php require_once __DIR__ . '/../includes/ai-footer.php'; ?>
