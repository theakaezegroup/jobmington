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
?>

<section class="jm-section jm-cv-page jm-cv-studio" style="padding-top:0;">
    <div class="jm-cv-command">
        <div>
            <p class="jm-kicker">CV Studio</p>
            <h1>Build a CV for the role in front of you.</h1>
            <p>Keep one CV in focus, move quickly between versions, and export only when the profile has enough substance to carry the application.</p>
        </div>
        <div class="jm-cv-command-actions">
            <a class="jm-button" href="/jobmington/cv-builder/create.php">Create CV</a>
            <a class="jm-button secondary" href="/jobmington/cv-builder/templates.php">Templates</a>
        </div>
    </div>

    <?php if ($activeCv): ?>
        <div class="jm-cv-workbench">
            <article class="jm-cv-focus-card">
                <div class="jm-cv-focus-preview">
                    <?php jm_cv_template_preview($activeTemplate); ?>
                </div>
                <div class="jm-cv-focus-copy">
                    <span class="jm-cv-state">Currently active</span>
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
                    <div class="jm-cv-focus-actions">
                        <a class="jm-button" href="/jobmington/cv-builder/editor-complete.php?id=<?= (int) $activeId ?>">Open editor</a>
                        <a class="jm-button secondary" href="/jobmington/cv-builder/export.php?id=<?= (int) $activeId ?>">Export</a>
                        <a class="jm-button secondary" href="/jobmington/cv-builder/templates.php?cv_id=<?= (int) $activeId ?>">Change template</a>
                    </div>
                </div>
            </article>

            <aside class="jm-cv-sideboard">
                <div class="jm-cv-next-card <?= e('tone-' . $activeStatus['tone']) ?>">
                    <span>Next best move</span>
                    <strong><?= e($activeStatus['action']) ?></strong>
                    <p><?= e($activeStatus['detail']) ?></p>
                    <a class="jm-button" href="/jobmington/cv-builder/editor-complete.php?id=<?= (int) $activeId ?>">Continue editing</a>
                </div>
                <div class="jm-cv-mini-stats">
                    <span><b><?= count($resumes) ?></b> CV versions</span>
                    <span><b><?= (int) $readyCount ?></b> ready to send</span>
                </div>
                <label class="jm-cv-import-lane" for="importFile">
                    <span>Import resume</span>
                    <strong>Turn a PDF or DOCX into editable sections.</strong>
                    <input id="importFile" type="file" accept=".pdf,.docx" hidden onchange="importDocument(this)">
                </label>
                <label class="jm-cv-import-lane" for="linkedinFile">
                    <span>LinkedIn import</span>
                    <strong>Use a LinkedIn export zip as a starting point.</strong>
                    <input id="linkedinFile" type="file" accept=".zip" hidden onchange="importDocument(this)">
                </label>
            </aside>
        </div>
    <?php else: ?>
        <div class="jm-empty">
            <h2>No CVs yet</h2>
            <p>Create your first CV or import an existing document to begin.</p>
            <a class="jm-button" href="/jobmington/cv-builder/create.php">Create CV</a>
        </div>
    <?php endif; ?>

    <?php if (!empty($resumes)): ?>
        <div class="jm-cv-library">
            <div class="jm-cv-library-head">
                <div>
                    <p class="jm-kicker">Document library</p>
                    <h2>Your CV versions, laid out clearly.</h2>
                </div>
            </div>

            <div class="jm-cv-library-list">
                <?php foreach ($resumes as $cv): ?>
                    <?php
                        $cvId = (int) $cv['cv_id'];
                        $template = jm_cv_template($cv['template'] ?? 'obsidian');
                        $missing = jm_cv_missing_items($cv, $cvCounts);
                        $status = jm_cv_status($cv, $cvCounts);
                        $sectionSummary = jm_cv_section_summary($cvId, $cvCounts);
                    ?>
                    <article class="jm-cv-library-row">
                        <div class="jm-cv-row-main">
                            <span class="jm-cv-row-template <?= e('tone-' . $template['tone']) ?>"><?= e(substr($template['name'], 0, 1)) ?></span>
                            <div>
                                <h3><?= e($cv['title'] ?: 'My CV') ?></h3>
                                <p><?= e($cv['headline'] ?: $status['detail']) ?></p>
                            </div>
                        </div>
                        <div class="jm-cv-row-sections">
                            <?php foreach ($sectionSummary as $item): ?>
                                <span><?= e($item) ?></span>
                            <?php endforeach; ?>
                        </div>
                        <div class="jm-cv-row-status <?= e('tone-' . $status['tone']) ?>">
                            <strong><?= e($status['label']) ?></strong>
                            <small><?= e($status['detail']) ?></small>
                        </div>
                        <div class="jm-cv-row-actions">
                            <a class="jm-button" href="/jobmington/cv-builder/editor-complete.php?id=<?= (int) $cvId ?>">Edit</a>
                            <a class="jm-button secondary" href="/jobmington/cv-builder/export.php?id=<?= (int) $cvId ?>">Export</a>
                            <a class="jm-button secondary" href="/jobmington/cv-builder/templates.php?cv_id=<?= (int) $cvId ?>">Template</a>
                            <button class="jm-cv-danger-button compact" type="button" onclick="confirmDeleteCV(<?= (int) $cvId ?>, <?= e(json_encode($cv['title'] ?: 'My CV')) ?>)">Delete</button>
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
