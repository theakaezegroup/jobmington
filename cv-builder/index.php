<?php
define('JOBMINGTON', true);
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/_cv_helpers.php';

Session::start();
Session::requireLogin();

$pdo = db();
$stmt = $pdo->prepare("SELECT * FROM cv_profiles WHERE user_id = ? ORDER BY updated_at DESC, created_at DESC");
$stmt->execute([Session::userId()]);
$resumes = $stmt->fetchAll();

function jm_cv_count_map(PDO $pdo, string $table, array $cvIds): array {
    $allowed = ['cv_experience', 'cv_education', 'cv_skills', 'cv_certifications', 'cv_languages', 'cv_projects'];
    if (empty($cvIds) || !in_array($table, $allowed, true)) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($cvIds), '?'));
    try {
        $stmt = $pdo->prepare("SELECT cv_id, COUNT(*) AS total FROM {$table} WHERE cv_id IN ({$placeholders}) GROUP BY cv_id");
        $stmt->execute($cvIds);
        $rows = $stmt->fetchAll();
    } catch (Throwable $e) {
        return [];
    }

    $map = [];
    foreach ($rows as $row) {
        $map[(int) $row['cv_id']] = (int) $row['total'];
    }
    return $map;
}

function jm_cv_completion(array $cv, array $cvCounts): int {
    $id = (int) $cv['cv_id'];
    $summary = trim((string) ($cv['summary'] ?? ''));
    if (str_starts_with($summary, 'Save failed:')) {
        $summary = '';
    }

    $core = count(array_filter([
        $cv['full_name'] ?? '',
        $cv['email'] ?? '',
        $cv['headline'] ?? '',
        $summary,
    ]));

    $sections = 0;
    foreach (['experience', 'education', 'skills'] as $section) {
        $sections += !empty($cvCounts[$section][$id]) ? 1 : 0;
    }

    return min(100, (int) round((($core + $sections) / 7) * 100));
}

function jm_cv_missing_items(array $cv, array $cvCounts): array {
    $id = (int) $cv['cv_id'];
    $summary = trim((string) ($cv['summary'] ?? ''));
    if (str_starts_with($summary, 'Save failed:')) {
        $summary = '';
    }

    $items = [];
    if (empty($cv['full_name'])) $items[] = 'name';
    if (empty($cv['headline'])) $items[] = 'headline';
    if ($summary === '') $items[] = 'summary';
    if (empty($cvCounts['experience'][$id])) $items[] = 'experience';
    if (empty($cvCounts['skills'][$id])) $items[] = 'skills';

    return $items;
}

function jm_cv_human_list(array $items): string {
    $items = array_values(array_filter($items));
    if (empty($items)) {
        return '';
    }

    if (count($items) === 1) {
        return $items[0];
    }

    $last = array_pop($items);
    return implode(', ', $items) . ' and ' . $last;
}

function jm_cv_next_action(array $missing): string {
    if (in_array('name', $missing, true)) {
        return 'Add the candidate name';
    }
    if (in_array('headline', $missing, true)) {
        return 'Write the target headline';
    }
    if (in_array('summary', $missing, true)) {
        return 'Shape the opening summary';
    }
    if (in_array('experience', $missing, true)) {
        return 'Add recent experience';
    }
    if (in_array('skills', $missing, true)) {
        return 'Add role-matching skills';
    }

    return 'Review wording and export';
}

function jm_cv_status(array $cv, array $cvCounts): array {
    $completion = jm_cv_completion($cv, $cvCounts);
    $missing = jm_cv_missing_items($cv, $cvCounts);
    $missingText = jm_cv_human_list(array_slice($missing, 0, 2));

    if ($completion >= 86 && empty($missing)) {
        return [
            'label' => 'Ready to export',
            'tone' => 'ready',
            'detail' => 'Core profile sections are in place.',
            'action' => 'Export or tailor this version',
        ];
    }

    if ($completion >= 72) {
        return [
            'label' => 'Review next',
            'tone' => 'review',
            'detail' => $missingText ? 'Tighten ' . $missingText . ' before sending.' : 'Polish wording and template choice before export.',
            'action' => jm_cv_next_action($missing),
        ];
    }

    if ($completion >= 35) {
        return [
            'label' => 'Needs polish',
            'tone' => 'progress',
            'detail' => $missingText ? 'Add ' . $missingText . ' next.' : 'Add more proof before export.',
            'action' => jm_cv_next_action($missing),
        ];
    }

    return [
        'label' => 'Draft setup',
        'tone' => 'draft',
        'detail' => $missingText ? 'Start with ' . $missingText . '.' : 'Build the first sections.',
        'action' => jm_cv_next_action($missing),
    ];
}

function jm_cv_section_summary(int $cvId, array $cvCounts): array {
    $labels = [
        'experience' => ['experience', 'experience'],
        'education' => ['education', 'education'],
        'skills' => ['skill', 'skills'],
        'projects' => ['project', 'projects'],
    ];

    $summary = [];
    foreach ($labels as $key => [$single, $plural]) {
        $count = (int) ($cvCounts[$key][$cvId] ?? 0);
        if ($count > 0) {
            $summary[] = $count . ' ' . ($count === 1 ? $single : $plural);
        }
    }

    return $summary ?: ['No sections yet'];
}

$cvIds = array_map(static fn($cv) => (int) $cv['cv_id'], $resumes);
$cvCounts = [
    'experience' => jm_cv_count_map($pdo, 'cv_experience', $cvIds),
    'education' => jm_cv_count_map($pdo, 'cv_education', $cvIds),
    'skills' => jm_cv_count_map($pdo, 'cv_skills', $cvIds),
    'certifications' => jm_cv_count_map($pdo, 'cv_certifications', $cvIds),
    'languages' => jm_cv_count_map($pdo, 'cv_languages', $cvIds),
    'projects' => jm_cv_count_map($pdo, 'cv_projects', $cvIds),
];

$latestCv = $resumes[0] ?? null;
$readyCount = 0;
foreach ($resumes as $cv) {
    if (jm_cv_status($cv, $cvCounts)['tone'] === 'ready') {
        $readyCount++;
    }
}

$activeCv = $latestCv;
$activeTemplate = $activeCv ? jm_cv_template($activeCv['template'] ?? 'obsidian') : null;
$activeId = $activeCv ? (int) $activeCv['cv_id'] : 0;
$activeStatus = $activeCv ? jm_cv_status($activeCv, $cvCounts) : null;
$activeSections = $activeCv ? jm_cv_section_summary($activeId, $cvCounts) : [];

$pageTitle = 'CV Builder | ' . SITE_NAME;
jm_cv_header($pageTitle, 'cv');

// Pre-calculate completion for all CVs
$completionMap = [];
foreach ($resumes as $cv) {
    $completionMap[(int)$cv['cv_id']] = jm_cv_completion($cv, $cvCounts);
}
$activeCompletion = $activeCv ? ($completionMap[$activeId] ?? 0) : 0;
?>

<style>
@keyframes jmFadeUp { from{opacity:0;transform:translateY(16px)} to{opacity:1;transform:translateY(0)} }
@keyframes jmFadeIn { from{opacity:0} to{opacity:1} }

/* ── Page hero ──────────────────────────────────────────────────── */
.jm-cvb-hero {
    display:flex; align-items:center; justify-content:space-between;
    gap:24px; flex-wrap:wrap;
    padding:40px 0 32px;
    border-bottom:1px solid var(--jm-line);
    margin-bottom:36px;
    animation: jmFadeUp .5s ease both;
}
.jm-cvb-hero h1 { font-size:clamp(26px,3.5vw,38px); margin:0 0 8px; color:var(--jm-ink); }
.jm-cvb-hero p  { color:var(--jm-muted); font-size:15px; margin:0; max-width:520px; line-height:1.65; }
.jm-cvb-hero-actions { display:flex; gap:10px; flex-wrap:wrap; }

/* ── Workbench ──────────────────────────────────────────────────── */
.jm-cvb-bench {
    display:grid;
    grid-template-columns:minmax(0,1fr) 300px;
    gap:24px; align-items:start;
    margin-bottom:48px;
    animation: jmFadeUp .5s ease both .08s;
}
@media(max-width:860px){ .jm-cvb-bench{ grid-template-columns:1fr; } }

/* ── Focus card ─────────────────────────────────────────────────── */
.jm-cvb-focus {
    border:1px solid var(--jm-line); border-radius:14px;
    background:#ffffff; overflow:hidden; display:grid;
    grid-template-columns:220px minmax(0,1fr);
}
@media(max-width:640px){ .jm-cvb-focus{ grid-template-columns:1fr; } }
.jm-cvb-preview-col {
    background:#f6f9fd; border-right:1px solid var(--jm-line);
    padding:24px; display:flex; flex-direction:column;
    align-items:center; justify-content:center; gap:18px;
}
.jm-cvb-completion-ring {
    position:relative; width:100px; height:100px;
}
.jm-cvb-ring-svg { width:100px; height:100px; transform:rotate(-90deg); }
.jm-cvb-ring-track { fill:none; stroke:var(--jm-line); stroke-width:8; }
.jm-cvb-ring-fill  {
    fill:none; stroke:var(--jm-blue); stroke-width:8;
    stroke-linecap:round; stroke-dasharray:283;
    transition:stroke-dashoffset 1s cubic-bezier(.22,.68,0,1.1);
}
.jm-cvb-ring-fill.ready  { stroke:var(--jm-green); }
.jm-cvb-ring-fill.review { stroke:var(--jm-orange); }
.jm-cvb-ring-fill.draft  { stroke:#94a3b8; }
.jm-cvb-ring-num {
    position:absolute; inset:0; display:flex; flex-direction:column;
    align-items:center; justify-content:center;
    font-size:22px; font-weight:800; color:var(--jm-ink); line-height:1;
}
.jm-cvb-ring-num small { font-size:10px; font-weight:700; color:var(--jm-muted); margin-top:2px; }

.jm-cvb-focus-body {
    padding:28px; display:flex; flex-direction:column; gap:16px;
}
.jm-cvb-active-badge {
    display:inline-flex; align-items:center; gap:5px;
    background:#eef5ff; border:1px solid #c9dcf5;
    color:var(--jm-blue); font-size:11px; font-weight:800;
    padding:3px 10px; border-radius:99px; text-transform:uppercase; letter-spacing:.07em;
    width:fit-content;
}
.jm-cvb-active-badge::before {
    content:''; width:6px; height:6px; border-radius:999px;
    background:var(--jm-blue); animation:jmPulse 2s ease infinite;
}
@keyframes jmPulse { 0%,100%{opacity:1} 50%{opacity:.35} }
.jm-cvb-focus-body h2 { font-size:22px; color:var(--jm-ink); margin:0; }
.jm-cvb-focus-body > p { font-size:14px; color:var(--jm-muted); margin:0; line-height:1.65; }
.jm-cvb-focus-actions { display:flex; gap:10px; flex-wrap:wrap; margin-top:4px; }
.jm-cvb-focus-actions .jm-button { min-height:40px; font-size:13px; }

/* ── AI tools strip ─────────────────────────────────────────────── */
.jm-cvb-ai-strip {
    display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-top:4px;
    padding-top:16px; border-top:1px solid var(--jm-line);
}
.jm-cvb-ai-card {
    border:1px solid var(--jm-line); border-radius:10px;
    padding:14px 16px; text-decoration:none; display:flex;
    flex-direction:column; gap:4px; background:#ffffff;
    transition:border-color .18s, background .18s, transform .18s;
}
.jm-cvb-ai-card:hover { border-color:var(--jm-blue); background:var(--jm-soft); transform:translateY(-2px); }
.jm-cvb-ai-card-label {
    font-size:10px; font-weight:800; text-transform:uppercase;
    letter-spacing:.08em; color:var(--jm-blue);
}
.jm-cvb-ai-card.tone-orange .jm-cvb-ai-card-label { color:#9a5a00; }
.jm-cvb-ai-card strong { font-size:13px; color:var(--jm-ink); }
.jm-cvb-ai-card span   { font-size:12px; color:var(--jm-muted); }

/* ── Sidebar ────────────────────────────────────────────────────── */
.jm-cvb-sidebar { display:grid; gap:14px; }

.jm-cvb-next {
    border:1px solid var(--jm-line); border-radius:12px;
    background:#ffffff; padding:20px;
    border-left:3px solid var(--jm-blue);
}
.jm-cvb-next.tone-ready  { border-left-color:var(--jm-green);  background:#f0fdf9; }
.jm-cvb-next.tone-review { border-left-color:var(--jm-orange); background:#fffcf7; }
.jm-cvb-next.tone-draft  { border-left-color:#94a3b8; }
.jm-cvb-next-label { font-size:10px; font-weight:800; text-transform:uppercase; letter-spacing:.09em; color:var(--jm-muted); display:block; margin-bottom:6px; }
.jm-cvb-next strong { font-size:15px; color:var(--jm-ink); display:block; margin-bottom:4px; }
.jm-cvb-next p { font-size:13px; color:var(--jm-muted); margin:0 0 14px; line-height:1.6; }
.jm-cvb-next .jm-button { width:100%; justify-content:center; min-height:40px; font-size:13px; }

.jm-cvb-stats {
    display:grid; grid-template-columns:1fr 1fr; gap:10px;
}
.jm-cvb-stat {
    border:1px solid var(--jm-line); border-radius:10px;
    background:#ffffff; padding:14px;
}
.jm-cvb-stat b    { display:block; font-size:26px; font-weight:800; color:var(--jm-ink); line-height:1; }
.jm-cvb-stat span { display:block; font-size:12px; color:var(--jm-muted); margin-top:4px; }

.jm-cvb-import {
    border:1px solid var(--jm-line); border-radius:12px;
    background:#ffffff; overflow:hidden;
}
.jm-cvb-import-label {
    font-size:11px; font-weight:800; text-transform:uppercase;
    letter-spacing:.09em; color:var(--jm-muted);
    padding:10px 16px; background:var(--jm-soft);
    border-bottom:1px solid var(--jm-line);
    display:block;
}
.jm-cvb-import-btn {
    display:flex; align-items:center; gap:12px;
    padding:14px 16px; cursor:pointer;
    border-bottom:1px solid var(--jm-line);
    transition:background .15s;
    width:100%;
}
.jm-cvb-import-btn:last-child { border-bottom:none; }
.jm-cvb-import-btn:hover { background:var(--jm-soft); }
.jm-cvb-import-icon {
    flex-shrink:0; width:34px; height:34px; border-radius:8px;
    display:grid; place-items:center; color:var(--jm-blue);
    background:#eef5ff; border:1px solid #c9dcf5;
}
.jm-cvb-import-copy strong { display:block; font-size:13px; color:var(--jm-ink); }
.jm-cvb-import-copy span   { display:block; font-size:12px; color:var(--jm-muted); margin-top:2px; }

/* ── Library ────────────────────────────────────────────────────── */
.jm-cvb-library { animation: jmFadeUp .5s ease both .16s; }
.jm-cvb-library-head {
    display:flex; align-items:flex-end; justify-content:space-between;
    gap:16px; margin-bottom:20px;
}
.jm-cvb-library-head h2 { font-size:22px; color:var(--jm-ink); margin:0 0 4px; }
.jm-cvb-library-head p  { font-size:14px; color:var(--jm-muted); margin:0; }

.jm-cvb-row {
    display:grid;
    grid-template-columns:auto minmax(0,1fr) auto auto auto;
    gap:16px; align-items:center;
    padding:16px 20px;
    border:1px solid var(--jm-line); border-radius:10px;
    background:#ffffff; margin-bottom:10px;
    transition:border-color .18s, box-shadow .18s;
}
.jm-cvb-row:hover {
    border-color:#b6cceb;
    box-shadow:0 4px 16px rgba(6,64,163,.06);
}
@media(max-width:860px){
    .jm-cvb-row { grid-template-columns:auto minmax(0,1fr); grid-template-rows:auto auto; }
    .jm-cvb-row-completion, .jm-cvb-row-status, .jm-cvb-row-actions { grid-column:1/-1; }
}

/* Completion mini-ring */
.jm-cvb-mini-ring { position:relative; width:48px; height:48px; flex-shrink:0; }
.jm-cvb-mini-ring svg { width:48px; height:48px; transform:rotate(-90deg); }
.jm-cvb-mini-ring .track { fill:none; stroke:var(--jm-line); stroke-width:4; }
.jm-cvb-mini-ring .fill  { fill:none; stroke:var(--jm-blue); stroke-width:4; stroke-linecap:round; stroke-dasharray:134; }
.jm-cvb-mini-ring .fill.ready  { stroke:var(--jm-green); }
.jm-cvb-mini-ring .fill.review { stroke:var(--jm-orange); }
.jm-cvb-mini-ring .fill.draft  { stroke:#94a3b8; }
.jm-cvb-mini-num {
    position:absolute; inset:0; display:flex; align-items:center; justify-content:center;
    font-size:11px; font-weight:800; color:var(--jm-ink);
}

.jm-cvb-row-info h3 { font-size:15px; font-weight:700; color:var(--jm-ink); margin:0 0 3px; }
.jm-cvb-row-info p  { font-size:13px; color:var(--jm-muted); margin:0; }
.jm-cvb-row-tags    { display:flex; flex-wrap:wrap; gap:6px; margin-top:6px; }

.jm-cvb-row-status {
    text-align:center; min-width:90px;
}
.jm-cvb-row-status strong { display:block; font-size:12px; font-weight:800; color:var(--jm-ink); }
.jm-cvb-row-status small  { font-size:11px; color:var(--jm-muted); }

.jm-cvb-row-actions { display:flex; gap:8px; align-items:center; flex-wrap:wrap; justify-content:flex-end; }
.jm-cvb-row-actions .jm-button { min-height:36px; font-size:12px; padding:0 12px; }

/* ── Empty state ────────────────────────────────────────────────── */
.jm-cvb-empty {
    text-align:center; padding:60px 24px;
    border:1px dashed var(--jm-line); border-radius:14px;
    margin-bottom:48px; animation: jmFadeIn .5s ease both;
}
.jm-cvb-empty-icon {
    width:56px; height:56px; border-radius:14px;
    background:var(--jm-soft); border:1px solid var(--jm-line);
    display:grid; place-items:center; margin:0 auto 18px;
    color:var(--jm-blue);
}
.jm-cvb-empty h2 { font-size:22px; color:var(--jm-ink); margin:0 0 8px; }
.jm-cvb-empty p  { font-size:15px; color:var(--jm-muted); margin:0 0 24px; max-width:420px; margin-left:auto; margin-right:auto; line-height:1.7; }
.jm-cvb-empty-actions { display:flex; gap:12px; justify-content:center; flex-wrap:wrap; }
</style>

<section class="jm-section jm-cv-page" style="padding-top:0;border-bottom:none;">

    <!-- Hero -->
    <div class="jm-cvb-hero">
        <div>
            <p class="jm-kicker" style="font-size:12px;letter-spacing:.1em;text-transform:uppercase;font-weight:800;margin-bottom:10px;">CV Studio</p>
            <h1>Build a CV for every role.</h1>
            <p>Create multiple versions, track completion, and export when the profile is ready to carry an application.</p>
        </div>
        <div class="jm-cvb-hero-actions">
            <a class="jm-button" href="/jobmington/cv-builder/create.php">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="margin-right:5px;"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                New CV
            </a>
            <a class="jm-button secondary" href="/jobmington/cv-builder/templates.php">Templates</a>
        </div>
    </div>

    <?php if ($activeCv): ?>
    <!-- Workbench -->
    <div class="jm-cvb-bench">

        <!-- Focus card -->
        <article class="jm-cvb-focus">
            <div class="jm-cvb-preview-col">
                <!-- Completion ring -->
                <div class="jm-cvb-completion-ring">
                    <?php
                    $ringOffset = 283 - (283 * $activeCompletion / 100);
                    $ringTone   = $activeStatus['tone'];
                    ?>
                    <svg class="jm-cvb-ring-svg" viewBox="0 0 100 100">
                        <circle class="jm-cvb-ring-track" cx="50" cy="50" r="45"/>
                        <circle class="jm-cvb-ring-fill <?= e($ringTone) ?>"
                                cx="50" cy="50" r="45"
                                style="stroke-dashoffset:<?= round($ringOffset, 1) ?>"/>
                    </svg>
                    <div class="jm-cvb-ring-num">
                        <?= $activeCompletion ?>
                        <small>%</small>
                    </div>
                </div>
                <?php jm_cv_template_preview($activeTemplate); ?>
            </div>

            <div class="jm-cvb-focus-body">
                <span class="jm-cvb-active-badge">Active CV</span>
                <h2><?= e($activeCv['title'] ?: 'My CV') ?></h2>
                <p><?= e($activeCv['headline'] ?: 'Add a headline that matches the role you want next.') ?></p>

                <div class="jm-tag-row compact">
                    <span class="jm-job-tag <?= e('tone-' . $activeTemplate['tone']) ?>"><?= e($activeTemplate['name']) ?></span>
                    <span class="jm-job-tag">Updated <?= e(timeAgo($activeCv['updated_at'] ?? $activeCv['created_at'])) ?></span>
                    <span class="jm-job-tag"><?= e(jm_cv_human_list($activeSections)) ?></span>
                </div>

                <div class="jm-cv-status-panel <?= e('tone-' . $activeStatus['tone']) ?>">
                    <span>Profile status</span>
                    <strong><?= e($activeStatus['label']) ?></strong>
                    <p><?= e($activeStatus['detail']) ?></p>
                </div>

                <div class="jm-cvb-focus-actions">
                    <a class="jm-button" href="/jobmington/cv-builder/editor-complete.php?id=<?= $activeId ?>">Open editor</a>
                    <a class="jm-button secondary" href="/jobmington/cv-builder/export.php?id=<?= $activeId ?>">Export PDF</a>
                    <a class="jm-button secondary" href="/jobmington/cv-builder/templates.php?cv_id=<?= $activeId ?>">Change template</a>
                </div>

                <!-- AI tools -->
                <div class="jm-cvb-ai-strip">
                    <a class="jm-cvb-ai-card" href="/jobmington/ai/roast.php">
                        <span class="jm-cvb-ai-card-label">AI Roast</span>
                        <strong>Get your ATS score</strong>
                        <span>Find issues before recruiters do</span>
                    </a>
                    <a class="jm-cvb-ai-card tone-orange" href="/jobmington/ai/andika.php">
                        <span class="jm-cvb-ai-card-label" style="color:#9a5a00;">Andika AI</span>
                        <strong>Improve with AI</strong>
                        <span>Cover letters, interview prep</span>
                    </a>
                </div>
            </div>
        </article>

        <!-- Sidebar -->
        <aside class="jm-cvb-sidebar">

            <!-- Next action -->
            <div class="jm-cvb-next tone-<?= e($activeStatus['tone']) ?>">
                <span class="jm-cvb-next-label">Next best move</span>
                <strong><?= e($activeStatus['action']) ?></strong>
                <p><?= e($activeStatus['detail']) ?></p>
                <a class="jm-button" href="/jobmington/cv-builder/editor-complete.php?id=<?= $activeId ?>">Continue editing</a>
            </div>

            <!-- Stats -->
            <div class="jm-cvb-stats">
                <div class="jm-cvb-stat">
                    <b><?= count($resumes) ?></b>
                    <span>CV version<?= count($resumes) !== 1 ? 's' : '' ?></span>
                </div>
                <div class="jm-cvb-stat">
                    <b><?= $readyCount ?></b>
                    <span>Ready to send</span>
                </div>
            </div>

            <!-- Import -->
            <div class="jm-cvb-import">
                <span class="jm-cvb-import-label">Import</span>
                <label class="jm-cvb-import-btn" for="importFile">
                    <span class="jm-cvb-import-icon">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                    </span>
                    <div class="jm-cvb-import-copy">
                        <strong>Import resume</strong>
                        <span>PDF or DOCX → editable sections</span>
                    </div>
                    <input id="importFile" type="file" accept=".pdf,.docx" hidden onchange="importDocument(this)">
                </label>
                <label class="jm-cvb-import-btn" for="linkedinFile">
                    <span class="jm-cvb-import-icon" style="background:#f0f9ff;border-color:#bae6fd;color:#0369a1;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-2-2 2 2 0 0 0-2 2v7h-4v-7a6 6 0 0 1 6-6zM2 9h4v12H2z"/><circle cx="4" cy="4" r="2"/></svg>
                    </span>
                    <div class="jm-cvb-import-copy">
                        <strong>LinkedIn import</strong>
                        <span>Use a LinkedIn export ZIP</span>
                    </div>
                    <input id="linkedinFile" type="file" accept=".zip" hidden onchange="importDocument(this)">
                </label>
            </div>

        </aside>
    </div>

    <?php else: ?>

    <!-- Empty state -->
    <div class="jm-cvb-empty">
        <div class="jm-cvb-empty-icon">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><line x1="9" y1="15" x2="15" y2="15"/></svg>
        </div>
        <h2>No CVs yet</h2>
        <p>Build your first CV from scratch, or import an existing PDF or DOCX to start with what you already have.</p>
        <div class="jm-cvb-empty-actions">
            <a class="jm-button" href="/jobmington/cv-builder/create.php">Create from scratch</a>
            <label class="jm-button secondary" for="importFile" style="cursor:pointer;">
                Import existing CV
                <input id="importFile" type="file" accept=".pdf,.docx" hidden onchange="importDocument(this)">
            </label>
        </div>
    </div>

    <?php endif; ?>

    <!-- Library -->
    <?php if (!empty($resumes)): ?>
    <div class="jm-cvb-library">
        <div class="jm-cvb-library-head">
            <div>
                <p class="jm-kicker" style="font-size:11px;letter-spacing:.1em;text-transform:uppercase;font-weight:800;margin-bottom:6px;">All versions</p>
                <h2>Your CV library</h2>
                <p>Every version in one place. Edit, export, or duplicate for a new role.</p>
            </div>
            <a class="jm-button secondary" href="/jobmington/cv-builder/create.php" style="white-space:nowrap;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="margin-right:5px;"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                New version
            </a>
        </div>

        <div>
            <?php foreach ($resumes as $cv):
                $cvId       = (int) $cv['cv_id'];
                $template   = jm_cv_template($cv['template'] ?? 'obsidian');
                $status     = jm_cv_status($cv, $cvCounts);
                $completion = $completionMap[$cvId] ?? 0;
                $sections   = jm_cv_section_summary($cvId, $cvCounts);
                $miniOffset = 134 - (134 * $completion / 100);
            ?>
            <article class="jm-cvb-row">
                <!-- Mini completion ring -->
                <div class="jm-cvb-mini-ring">
                    <svg viewBox="0 0 48 48">
                        <circle class="track" cx="24" cy="24" r="21.5"/>
                        <circle class="fill <?= e($status['tone']) ?>" cx="24" cy="24" r="21.5"
                                style="stroke-dashoffset:<?= round($miniOffset, 1) ?>"/>
                    </svg>
                    <div class="jm-cvb-mini-num"><?= $completion ?>%</div>
                </div>

                <!-- Info -->
                <div class="jm-cvb-row-info">
                    <h3><?= e($cv['title'] ?: 'My CV') ?></h3>
                    <p><?= e($cv['headline'] ?: $status['detail']) ?></p>
                    <div class="jm-cvb-row-tags">
                        <span class="jm-job-tag <?= e('tone-' . $template['tone']) ?>"><?= e($template['name']) ?></span>
                        <?php foreach (array_slice($sections, 0, 3) as $s): ?>
                            <span class="jm-job-tag"><?= e($s) ?></span>
                        <?php endforeach; ?>
                        <span class="jm-job-tag">Updated <?= e(timeAgo($cv['updated_at'] ?? $cv['created_at'])) ?></span>
                    </div>
                </div>

                <!-- Status -->
                <div class="jm-cvb-row-status">
                    <strong><?= e($status['label']) ?></strong>
                    <small><?= e($status['detail']) ?></small>
                </div>

                <!-- Actions -->
                <div class="jm-cvb-row-actions">
                    <a class="jm-button" href="/jobmington/cv-builder/editor-complete.php?id=<?= $cvId ?>">Edit</a>
                    <a class="jm-button secondary" href="/jobmington/cv-builder/export.php?id=<?= $cvId ?>">Export</a>
                    <a class="jm-button secondary" href="/jobmington/ai/roast.php">Roast</a>
                    <button class="jm-cv-danger-button compact" type="button"
                            onclick="confirmDeleteCV(<?= $cvId ?>, <?= e(json_encode($cv['title'] ?: 'My CV')) ?>)">Delete</button>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

</section>

<div class="jm-cv-toast" id="cvToast" role="status" aria-live="polite"></div>

<div class="jm-cv-modal" id="deleteModal" aria-hidden="true">
    <div class="jm-cv-modal-card" role="dialog" aria-modal="true" aria-labelledby="deleteModalTitle">
        <span class="jm-cv-modal-mark">Delete</span>
        <h2 id="deleteModalTitle">Remove this CV?</h2>
        <p id="deleteModalMessage">This only happens after you confirm. The CV and its sections will be removed from your workspace.</p>
        <div class="jm-form-actions">
            <button class="jm-cv-danger-button solid" type="button" id="deleteConfirm">Delete CV</button>
            <button class="jm-button secondary" type="button" onclick="closeDeleteModal()">Cancel</button>
        </div>
    </div>
</div>

<script>
let pendingDeleteCvId = null;

function showCvToast(message, type = 'success') {
    const toast = document.getElementById('cvToast');
    toast.textContent = message;
    toast.className = `jm-cv-toast show ${type}`;
    setTimeout(() => toast.classList.remove('show'), 3200);
}

function importDocument(input) {
    if (!input.files || !input.files[0]) return;

    const card = input.closest('.jm-cv-import-lane, .jm-cv-action-card');
    const title = card.querySelector('strong');
    const original = title.textContent;
    const formData = new FormData();
    formData.append('document', input.files[0]);
    title.textContent = 'Importing...';
    card.style.pointerEvents = 'none';

    fetch('/jobmington/cv-builder/import.php', { method: 'POST', body: formData })
        .then(async response => {
            const text = await response.text();
            let data = {};
            try {
                data = text ? JSON.parse(text) : {};
            } catch (error) {
                throw new Error('Import returned an unreadable server response. Please try a smaller PDF or DOCX.');
            }

            if (!response.ok) {
                throw new Error(data.message || 'Import failed.');
            }

            return data;
        })
        .then(data => {
            if (data.success && data.redirect) {
                window.location.href = data.redirect;
                return;
            }
            if (data.success) {
                window.location.reload();
                return;
            }
            showCvToast(data.message || 'Import failed.', 'error');
            title.textContent = original;
            card.style.pointerEvents = '';
        })
        .catch(error => {
            showCvToast(error.message || 'Import failed.', 'error');
            title.textContent = original;
            card.style.pointerEvents = '';
        });
}

function confirmDeleteCV(cvId, title) {
    pendingDeleteCvId = cvId;
    document.getElementById('deleteModalMessage').textContent = `Delete "${title}" and all saved sections? This cannot be undone.`;
    document.getElementById('deleteModal').classList.add('active');
    document.getElementById('deleteModal').setAttribute('aria-hidden', 'false');
}

function closeDeleteModal() {
    pendingDeleteCvId = null;
    document.getElementById('deleteModal').classList.remove('active');
    document.getElementById('deleteModal').setAttribute('aria-hidden', 'true');
    const button = document.getElementById('deleteConfirm');
    button.textContent = 'Delete CV';
    button.disabled = false;
}

function deleteCV(cvId) {
    fetch('/jobmington/cv-builder/delete.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ cv_id: cvId })
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                window.location.reload();
                return;
            }
            closeDeleteModal();
            showCvToast(data.message || 'Could not delete CV.', 'error');
        })
        .catch(error => {
            closeDeleteModal();
            showCvToast(error.message || 'Could not delete CV.', 'error');
        });
}

document.getElementById('deleteConfirm').addEventListener('click', function () {
    if (!pendingDeleteCvId) return;
    this.textContent = 'Deleting...';
    this.disabled = true;
    deleteCV(pendingDeleteCvId);
});

document.getElementById('deleteModal').addEventListener('click', function (event) {
    if (event.target === this) closeDeleteModal();
});
</script>

<?php jm_cv_footer(); ?>
