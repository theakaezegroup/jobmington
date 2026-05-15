<?php
/**
 * JOBMINGTON - World-Class CV Editor
 * Complete professional CV builder with all sections
 */

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
$userId = Session::userId();
$cvId = (int)($_GET['id'] ?? 0);

// Fetch CV or redirect
if ($cvId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM cv_profiles WHERE cv_id = ? AND user_id = ?");
    $stmt->execute([$cvId, $userId]);
    $cv = $stmt->fetch();
    if (!$cv) { header('Location: ' . SITE_URL . '/cv-builder/'); exit; }
} else {
    header('Location: ' . SITE_URL . '/cv-builder/'); exit;
}

// Fetch all related data
$experience = $pdo->prepare("SELECT * FROM cv_experience WHERE cv_id = ? ORDER BY is_current DESC, start_date DESC");
$experience->execute([$cvId]);
$experience = $experience->fetchAll();

$education = $pdo->prepare("SELECT * FROM cv_education WHERE cv_id = ? ORDER BY is_current DESC, start_date DESC");
$education->execute([$cvId]);
$education = $education->fetchAll();

// Fetch skills - handle missing sort_order column
try {
    $skills = $pdo->prepare("SELECT * FROM cv_skills WHERE cv_id = ? ORDER BY skill_name");
    $skills->execute([$cvId]);
    $skills = $skills->fetchAll();
} catch (Exception $e) { $skills = []; }

// Try to fetch optional tables (may not exist yet)
try {
    $certifications = $pdo->prepare("SELECT * FROM cv_certifications WHERE cv_id = ? ORDER BY issue_date DESC");
    $certifications->execute([$cvId]);
    $certifications = $certifications->fetchAll();
} catch (Exception $e) { $certifications = []; }

try {
    $languages = $pdo->prepare("SELECT * FROM cv_languages WHERE cv_id = ?");
    $languages->execute([$cvId]);
    $languages = $languages->fetchAll();
} catch (Exception $e) { $languages = []; }

try {
    $projects = $pdo->prepare("SELECT * FROM cv_projects WHERE cv_id = ? ORDER BY is_current DESC, start_date DESC");
    $projects->execute([$cvId]);
    $projects = $projects->fetchAll();
} catch (Exception $e) { $projects = []; }

$template = jm_cv_template($cv['template'] ?? 'obsidian');
$editorStats = [
    'Template' => $template['name'],
    'Experience' => count($experience),
    'Skills' => count($skills),
    'Updated' => !empty($cv['updated_at']) ? timeAgo($cv['updated_at']) : 'Now',
];

$pageTitle = 'Edit CV | ' . SITE_NAME;
jm_cv_header($pageTitle, 'cv');
?>

<style>
@media not all {
:root {
    --primary: #0077b5;
    --primary-dark: #005885;
    --accent: #fbbf24;
    --bg-dark: #0f172a;
    --bg-card: rgba(15, 23, 42, 0.9);
    --border: rgba(255,255,255,0.1);
    --text: #f1f5f9;
    --text-muted: #94a3b8;
}

body { background: var(--bg-dark); }

.editor-wrapper {
    max-width: 900px;
    margin: 0 auto;
    padding: 100px 20px 60px;
}

.editor-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    flex-wrap: wrap;
    gap: 15px;
}

.editor-title {
    font-size: 1.75rem;
    font-weight: 800;
    color: var(--text);
}

.editor-actions {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
}

.btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 24px;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.875rem;
    cursor: pointer;
    transition: all 0.2s;
    text-decoration: none;
    border: none;
}

.btn-primary {
    background: var(--primary);
    color: white;
}

.btn-primary:hover {
    background: var(--primary-dark);
    transform: translateY(-2px);
}

.btn-accent {
    background: var(--accent);
    color: var(--bg-dark);
}

.btn-accent:hover {
    background: #f59e0b;
}

.btn-outline {
    background: transparent;
    border: 2px solid var(--border);
    color: var(--text);
}

.btn-outline:hover {
    border-color: var(--primary);
    color: var(--primary);
}

/* Section Cards */
.section-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 30px;
    margin-bottom: 24px;
    backdrop-filter: blur(10px);
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
    padding-bottom: 16px;
    border-bottom: 1px solid var(--border);
}

.section-title {
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--text);
}

.section-icon {
    width: 32px;
    height: 32px;
    background: var(--primary);
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
}

.btn-add {
    background: rgba(0, 119, 181, 0.1);
    border: 2px dashed var(--primary);
    color: var(--primary);
    padding: 10px 20px;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-add:hover {
    background: var(--primary);
    color: white;
    border-style: solid;
}

/* Form Elements */
.form-row {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
    margin-bottom: 20px;
}

.form-row.three-col {
    grid-template-columns: repeat(3, 1fr);
}

@media (max-width: 640px) {
    .form-row, .form-row.three-col {
        grid-template-columns: 1fr;
    }
}

.form-group {
    margin-bottom: 0;
}

.form-group.full-width {
    grid-column: 1 / -1;
}

.form-label {
    display: block;
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--text-muted);
    margin-bottom: 8px;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.form-input, .form-select, .form-textarea {
    width: 100%;
    padding: 14px 16px;
    background: rgba(255,255,255,0.05);
    border: 1px solid var(--border);
    border-radius: 10px;
    color: var(--text);
    font-size: 0.95rem;
    transition: all 0.2s;
}

.form-input:focus, .form-select:focus, .form-textarea:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(0, 119, 181, 0.2);
}

.form-textarea {
    min-height: 120px;
    resize: vertical;
    font-family: inherit;
    line-height: 1.6;
}

.form-select {
    cursor: pointer;
}

/* Entry Items */
.entry-item {
    background: rgba(255,255,255,0.02);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 24px;
    margin-bottom: 16px;
    position: relative;
}

.entry-item:hover {
    border-color: rgba(255,255,255,0.2);
}

.entry-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 20px;
}

.entry-title {
    font-weight: 600;
    color: var(--text);
    font-size: 1rem;
}

.entry-subtitle {
    color: var(--primary);
    font-size: 0.9rem;
    margin-top: 4px;
}

.entry-actions {
    display: flex;
    gap: 8px;
}

.btn-icon {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    border: none;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
}

.btn-delete {
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
}

.btn-delete:hover {
    background: #ef4444;
    color: white;
}

/* Skills Tags */
.skills-container {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-bottom: 20px;
}

.skill-tag {
    display: flex;
    align-items: center;
    gap: 8px;
    background: rgba(0, 119, 181, 0.1);
    border: 1px solid var(--primary);
    padding: 10px 16px;
    border-radius: 25px;
    color: var(--primary);
    font-weight: 500;
}

.skill-tag .remove {
    cursor: pointer;
    opacity: 0.7;
    transition: opacity 0.2s;
}

.skill-tag .remove:hover {
    opacity: 1;
}

.skill-input-wrapper {
    display: flex;
    gap: 12px;
}

.skill-input-wrapper .form-input {
    flex: 1;
}

/* Checkbox */
.checkbox-group {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-top: 10px;
}

.checkbox-group input[type="checkbox"] {
    width: 20px;
    height: 20px;
    accent-color: var(--primary);
}

.checkbox-group label {
    color: var(--text-muted);
    font-size: 0.9rem;
}

/* Toast Notification */
.toast {
    position: fixed;
    bottom: 30px;
    right: 30px;
    padding: 16px 24px;
    border-radius: 12px;
    font-weight: 600;
    z-index: 1000;
    transform: translateY(100px);
    opacity: 0;
    transition: all 0.3s;
}

.toast.show {
    transform: translateY(0);
    opacity: 1;
}

.toast.success {
    background: #22c55e;
    color: white;
}

.toast.error {
    background: #ef4444;
    color: white;
}

/* Progress Indicator */
.save-indicator {
    position: fixed;
    top: 80px;
    right: 30px;
    background: var(--bg-card);
    border: 1px solid var(--border);
    padding: 12px 20px;
    border-radius: 10px;
    display: none;
    align-items: center;
    gap: 10px;
    color: var(--text);
    font-size: 0.875rem;
    z-index: 100;
}

.save-indicator.saving {
    display: flex;
}

.spinner {
    width: 18px;
    height: 18px;
    border: 2px solid var(--border);
    border-top-color: var(--primary);
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}
}
</style>

<style>
body.jm-minimal {
    background: linear-gradient(180deg, #ffffff 0%, #f7fbff 44%, #f3f8ff 100%);
}

.editor-wrapper {
    max-width: 1180px;
    margin: 0 auto;
    padding: 0 0 42px;
}

.editor-header {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 24px;
    margin: 0 0 28px;
    padding: 30px;
    border: 1px solid #cddbf0;
    border-radius: 8px;
    background: linear-gradient(135deg, #ffffff 0%, #f4f8ff 58%, #fff8ec 100%);
    position: relative;
    overflow: hidden;
}

.editor-header::after {
    content: "";
    position: absolute;
    right: -80px;
    top: -100px;
    width: 260px;
    height: 260px;
    border: 1px solid rgba(6, 64, 163, 0.1);
    border-radius: 50%;
    background: radial-gradient(circle, rgba(6, 64, 163, 0.06), transparent 64%);
    pointer-events: none;
}

.editor-eyebrow {
    display: inline-flex;
    margin-bottom: 12px;
    color: var(--jm-blue);
    font-size: 13px;
    font-weight: 800;
    text-decoration: none;
    text-transform: uppercase;
    letter-spacing: 0;
}

.editor-eyebrow:hover {
    color: var(--jm-blue-dark);
    text-decoration: underline;
    text-underline-offset: 4px;
}

.editor-title {
    margin: 0 0 10px;
    color: var(--jm-ink);
    font-size: 44px;
    line-height: 1.08;
    font-weight: 650;
    letter-spacing: 0;
}

.editor-subtitle {
    max-width: 580px;
    margin: 0;
    color: var(--jm-muted);
    font-size: 17px;
    line-height: 1.6;
}

.editor-header > div {
    position: relative;
    z-index: 1;
}

.editor-studio-strip {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 10px;
    margin: -12px 0 24px;
}

.editor-studio-strip span {
    display: grid;
    gap: 3px;
    min-height: 62px;
    padding: 12px;
    border: 1px solid var(--jm-line);
    border-radius: 8px;
    background: #ffffff;
    color: var(--jm-muted);
    font-size: 12px;
    font-weight: 700;
}

.editor-studio-strip b {
    color: var(--jm-ink);
    font-size: 18px;
    line-height: 1.1;
}

.editor-actions {
    display: flex;
    flex-wrap: wrap;
    justify-content: flex-end;
    gap: 10px;
}

.btn,
.btn-add {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    min-height: 42px;
    padding: 0 16px;
    border: 1px solid var(--jm-blue);
    border-radius: 6px;
    background: var(--jm-blue);
    color: #ffffff;
    font-size: 14px;
    font-weight: 650;
    text-decoration: none;
    cursor: pointer;
    transition: background 0.15s ease, border-color 0.15s ease;
}

.btn:hover,
.btn-add:hover {
    background: var(--jm-blue-dark);
    border-color: var(--jm-blue-dark);
    color: #ffffff;
    transform: none;
}

.btn i,
.btn-add i {
    display: none;
}

.btn-outline {
    background: #ffffff;
    border-color: #b8c8df;
    color: var(--jm-blue);
}

.btn-outline:hover {
    background: var(--jm-soft);
    border-color: var(--jm-blue);
    color: var(--jm-blue);
}

.btn-accent {
    background: var(--jm-orange);
    border-color: var(--jm-orange);
    color: #301900;
}

.btn-accent:hover {
    background: #e88712;
    border-color: #e88712;
    color: #301900;
}

.section-card {
    margin-bottom: 20px;
    padding: 28px;
    border: 1px solid var(--jm-line);
    border-radius: 8px;
    background: #ffffff;
    backdrop-filter: none;
    position: relative;
    overflow: hidden;
}

.section-card::before {
    content: "";
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 4px;
    background: linear-gradient(180deg, var(--jm-blue), var(--jm-green));
}

.section-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 18px;
    margin-bottom: 22px;
    padding-bottom: 16px;
    border-bottom: 1px solid var(--jm-line);
}

.section-title {
    display: flex;
    align-items: center;
    gap: 12px;
    color: var(--jm-ink);
    font-size: 22px;
    font-weight: 650;
    line-height: 1.2;
}

.section-icon {
    display: grid;
    place-items: center;
    width: 34px;
    height: 34px;
    border-radius: 8px;
    background: var(--jm-soft);
    color: var(--jm-blue);
    font-size: 0;
}

.section-icon::before {
    content: "";
    width: 12px;
    height: 12px;
    border-radius: 999px;
    background: currentColor;
}

.btn-add {
    min-height: 38px;
    border-style: solid;
    background: #ffffff;
    color: var(--jm-blue);
}

.form-row,
.form-row.three-col {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 18px;
    margin-bottom: 18px;
}

.form-row.three-col {
    grid-template-columns: repeat(3, minmax(0, 1fr));
}

.form-group.full-width {
    grid-column: 1 / -1;
}

.form-label {
    display: block;
    margin-bottom: 8px;
    color: var(--jm-muted);
    font-size: 14px;
    font-weight: 650;
    text-transform: none;
    letter-spacing: 0;
}

.form-input,
.form-select,
.form-textarea {
    width: 100%;
    min-height: 46px;
    padding: 11px 13px;
    border: 1px solid #bfd0e5;
    border-radius: 6px;
    background: #ffffff;
    color: var(--jm-ink);
    font: inherit;
    transition: border-color 0.15s ease, box-shadow 0.15s ease;
}

.form-textarea {
    min-height: 130px;
    resize: vertical;
}

.form-input:focus,
.form-select:focus,
.form-textarea:focus {
    outline: none;
    border-color: var(--jm-blue);
    box-shadow: 0 0 0 2px rgba(6, 64, 163, 0.08);
    background: #ffffff;
}

.entry-item {
    position: relative;
    margin-bottom: 16px;
    padding: 22px 62px 22px 22px;
    border: 1px solid #dce6f4;
    border-radius: 8px;
    background: linear-gradient(135deg, #ffffff, var(--jm-soft));
    transition: border-color 0.16s ease, opacity 0.16s ease, transform 0.16s ease;
}

.entry-item:last-child {
    margin-bottom: 0;
}

.entry-item:hover {
    border-color: #b6cceb;
}

.entry-item.is-removing {
    opacity: 0;
    transform: translateX(12px);
}

.btn-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    min-width: 38px;
    min-height: 34px;
    padding: 0 10px;
    border: 1px solid #f3b8b8;
    border-radius: 6px;
    background: #fff7f7;
    color: #9f1d1d;
    cursor: pointer;
}

.btn-icon i {
    display: none;
}

.btn-icon::before {
    content: "x";
    display: grid;
    place-items: center;
    width: 18px;
    height: 18px;
    border-radius: 999px;
    background: #9f1d1d;
    color: #ffffff;
    font-size: 12px;
    font-weight: 700;
}

.btn-icon::after {
    content: "Remove";
    font-size: 12px;
    font-weight: 800;
}

.btn-icon:hover {
    background: #9f1d1d;
    border-color: #9f1d1d;
    color: #ffffff;
}

.btn-icon:hover::before {
    background: #ffffff;
    color: #9f1d1d;
}

.checkbox-group {
    display: flex;
    align-items: center;
    gap: 8px;
    margin: 2px 0 16px;
    color: var(--jm-muted);
    font-size: 14px;
}

.skills-container {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-bottom: 16px;
}

.skill-tag {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    min-height: 30px;
    padding: 0 10px;
    border: 1px solid #c9dcf5;
    border-radius: 999px;
    background: #eef5ff;
    color: var(--jm-blue);
    font-size: 13px;
    font-weight: 700;
}

.skill-tag .remove {
    display: inline-grid;
    place-items: center;
    width: 22px;
    height: 22px;
    padding: 0;
    border: 1px solid #c9dcf5;
    border-radius: 999px;
    background: #ffffff;
    color: #9f1d1d;
    cursor: pointer;
}

.skill-tag .remove i {
    display: none;
}

.skill-tag .remove::before {
    content: "x";
    font-weight: 800;
}

.skill-tag .remove:hover {
    background: #9f1d1d;
    border-color: #9f1d1d;
    color: #ffffff;
}

.skill-tag.is-removing {
    opacity: 0;
    transform: translateY(-4px);
}

.skill-input-wrapper {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 10px;
}

.save-indicator {
    position: fixed;
    right: 24px;
    bottom: 24px;
    z-index: 20;
    display: none;
    align-items: center;
    gap: 10px;
    padding: 12px 14px;
    border: 1px solid var(--jm-line);
    border-radius: 8px;
    background: #ffffff;
    color: var(--jm-muted);
}

.save-indicator.saving {
    display: flex;
}

.spinner {
    width: 18px;
    height: 18px;
    border: 2px solid #d7e3f2;
    border-top-color: var(--jm-blue);
    border-radius: 999px;
    animation: spin 0.8s linear infinite;
}

.toast {
    position: fixed;
    left: 50%;
    bottom: 24px;
    z-index: 30;
    transform: translateX(-50%) translateY(20px);
    opacity: 0;
    max-width: min(92vw, 520px);
    padding: 12px 16px;
    border: 1px solid var(--jm-line);
    border-radius: 8px;
    background: #ffffff;
    color: var(--jm-ink);
    pointer-events: none;
    transition: opacity 0.2s ease, transform 0.2s ease;
}

.toast.show {
    opacity: 1;
    transform: translateX(-50%) translateY(0);
}

.toast.error {
    border-color: #f3b8b8;
    color: #9f1d1d;
    background: #fff7f7;
}

@media (max-width: 900px) {
    .editor-header {
        align-items: flex-start;
        flex-direction: column;
    }

    .editor-actions {
        justify-content: flex-start;
    }

    .form-row,
    .form-row.three-col,
    .editor-studio-strip {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 560px) {
    .editor-header,
    .section-card {
        padding: 22px;
    }

    .editor-title {
        font-size: 32px;
    }

    .skill-input-wrapper {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="editor-wrapper">
    
    <div class="editor-header">
        <div>
            <a href="<?= SITE_URL ?>/cv-builder/" class="editor-eyebrow">Back to CV Builder</a>
            <h1 class="editor-title"><?= e($cv['title'] ?: 'Edit your CV') ?></h1>
            <p class="editor-subtitle">Keep the details sharp, choose a template from the builder, then export a polished CV when you are ready.</p>
        </div>
        <div class="editor-actions">
            <a href="<?= SITE_URL ?>/cv-builder/preview.php?id=<?= $cvId ?>" target="_blank" class="btn btn-outline">
                Preview
            </a>
            <button type="button" onclick="saveCV()" class="btn btn-primary">
                Save Changes
            </button>
            <a href="<?= SITE_URL ?>/cv-builder/export.php?id=<?= $cvId ?>" target="_blank" class="btn btn-accent">
                Download PDF
            </a>
        </div>
    </div>

    <div class="editor-studio-strip" aria-label="CV editor status">
        <?php foreach ($editorStats as $label => $value): ?>
            <span><b><?= e((string) $value) ?></b><?= e($label) ?></span>
        <?php endforeach; ?>
    </div>

    <form id="cvForm">
        <input type="hidden" name="cv_id" value="<?= $cvId ?>">

        <!-- PERSONAL INFORMATION -->
        <div class="section-card">
            <div class="section-header">
                <div class="section-title">
                    <div class="section-icon"><i class="fas fa-user"></i></div>
                    Personal Information
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Full Name *</label>
                    <input type="text" class="form-input" name="full_name" value="<?= e($cv['full_name'] ?? '') ?>" placeholder="John Doe" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Professional Headline *</label>
                    <input type="text" class="form-input" name="headline" value="<?= e($cv['headline'] ?? '') ?>" placeholder="Senior Software Engineer">
                </div>
            </div>
            
            <div class="form-row three-col">
                <div class="form-group">
                    <label class="form-label">Email *</label>
                    <input type="email" class="form-input" name="email" value="<?= e($cv['email'] ?? '') ?>" placeholder="john@example.com" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Phone</label>
                    <input type="tel" class="form-input" name="phone" value="<?= e($cv['phone'] ?? '') ?>" placeholder="+234 800 000 0000">
                </div>
                <div class="form-group">
                    <label class="form-label">Location</label>
                    <input type="text" class="form-input" name="location" value="<?= e($cv['location'] ?? $cv['city'] ?? '') ?>" placeholder="Lagos, Nigeria">
                </div>
            </div>
            
            <div class="form-row three-col">
                <div class="form-group">
                    <label class="form-label">LinkedIn URL</label>
                    <input type="url" class="form-input" name="linkedin_url" value="<?= e($cv['linkedin_url'] ?? '') ?>" placeholder="linkedin.com/in/johndoe">
                </div>
                <div class="form-group">
                    <label class="form-label">Portfolio/Website</label>
                    <input type="url" class="form-input" name="portfolio_url" value="<?= e($cv['portfolio_url'] ?? '') ?>" placeholder="johndoe.com">
                </div>
                <div class="form-group">
                    <label class="form-label">GitHub</label>
                    <input type="url" class="form-input" name="github_url" value="<?= e($cv['github_url'] ?? '') ?>" placeholder="github.com/johndoe">
                </div>
            </div>
            
            <div class="form-group full-width">
                <label class="form-label">Professional Summary</label>
                <textarea class="form-textarea" name="summary" placeholder="Write a compelling 2-3 sentence summary of your professional background, key skills, and career goals..."><?= e($cv['summary'] ?? '') ?></textarea>
            </div>
        </div>

        <!-- WORK EXPERIENCE -->
        <div class="section-card">
            <div class="section-header">
                <div class="section-title">
                    <div class="section-icon"><i class="fas fa-briefcase"></i></div>
                    Work Experience
                </div>
                <button type="button" class="btn-add" onclick="addExperience()">
                    <i class="fas fa-plus"></i> Add Experience
                </button>
            </div>
            
            <div id="experienceList">
                <?php foreach ($experience as $exp): ?>
                <div class="entry-item" data-type="experience">
                    <div class="entry-actions" style="position: absolute; top: 16px; right: 16px;">
                        <button type="button" class="btn-icon btn-delete" onclick="removeEntry(this)" aria-label="Remove entry">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Job Title *</label>
                            <input type="text" class="form-input exp-title" value="<?= e($exp['job_title'] ?? '') ?>" placeholder="Software Engineer">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Company *</label>
                            <input type="text" class="form-input exp-company" value="<?= e($exp['company'] ?? '') ?>" placeholder="Google">
                        </div>
                    </div>
                    <div class="form-row three-col">
                        <div class="form-group">
                            <label class="form-label">Location</label>
                            <input type="text" class="form-input exp-location" value="<?= e($exp['location'] ?? '') ?>" placeholder="Lagos, Nigeria">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Start Date</label>
                            <input type="month" class="form-input exp-start" value="<?= !empty($exp['start_date']) ? date('Y-m', strtotime($exp['start_date'])) : '' ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">End Date</label>
                            <input type="month" class="form-input exp-end" value="<?= !empty($exp['end_date']) ? date('Y-m', strtotime($exp['end_date'])) : '' ?>" <?= !empty($exp['is_current']) ? 'disabled' : '' ?>>
                        </div>
                    </div>
                    <div class="checkbox-group">
                        <input type="checkbox" class="exp-current" <?= !empty($exp['is_current']) ? 'checked' : '' ?> onchange="this.closest('.entry-item').querySelector('.exp-end').disabled = this.checked">
                        <label>I currently work here</label>
                    </div>
                    <div class="form-group full-width" style="margin-top: 20px;">
                        <label class="form-label">Description & Achievements</label>
                        <textarea class="form-textarea exp-description" placeholder="• Led a team of 5 developers to deliver...&#10;• Increased system performance by 40%...&#10;• Implemented CI/CD pipeline reducing deployment time..."><?= e($exp['description'] ?? '') ?></textarea>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- EDUCATION -->
        <div class="section-card">
            <div class="section-header">
                <div class="section-title">
                    <div class="section-icon"><i class="fas fa-graduation-cap"></i></div>
                    Education
                </div>
                <button type="button" class="btn-add" onclick="addEducation()">
                    <i class="fas fa-plus"></i> Add Education
                </button>
            </div>
            
            <div id="educationList">
                <?php foreach ($education as $edu): ?>
                <div class="entry-item" data-type="education">
                    <div class="entry-actions" style="position: absolute; top: 16px; right: 16px;">
                        <button type="button" class="btn-icon btn-delete" onclick="removeEntry(this)" aria-label="Remove entry">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Institution *</label>
                            <input type="text" class="form-input edu-institution" value="<?= e($edu['institution'] ?? '') ?>" placeholder="University of Lagos">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Degree</label>
                            <input type="text" class="form-input edu-degree" value="<?= e($edu['degree'] ?? '') ?>" placeholder="Bachelor of Science">
                        </div>
                    </div>
                    <div class="form-row three-col">
                        <div class="form-group">
                            <label class="form-label">Field of Study</label>
                            <input type="text" class="form-input edu-field" value="<?= e($edu['field_of_study'] ?? '') ?>" placeholder="Computer Science">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Start Date</label>
                            <input type="month" class="form-input edu-start" value="<?= !empty($edu['start_date']) ? date('Y-m', strtotime($edu['start_date'])) : '' ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">End Date</label>
                            <input type="month" class="form-input edu-end" value="<?= !empty($edu['end_date']) ? date('Y-m', strtotime($edu['end_date'])) : '' ?>">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Grade/GPA</label>
                            <input type="text" class="form-input edu-grade" value="<?= e($edu['grade'] ?? '') ?>" placeholder="First Class Honours / 3.8 GPA">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Location</label>
                            <input type="text" class="form-input edu-location" value="<?= e($edu['location'] ?? '') ?>" placeholder="Lagos, Nigeria">
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- SKILLS -->
        <div class="section-card">
            <div class="section-header">
                <div class="section-title">
                    <div class="section-icon"><i class="fas fa-tools"></i></div>
                    Skills
                </div>
            </div>
            
            <div class="skills-container" id="skillsList">
                <?php foreach ($skills as $skill): ?>
                <div class="skill-tag">
                    <span class="skill-name"><?= e($skill['skill_name']) ?></span>
                    <button type="button" class="remove" onclick="removeSkill(this)" aria-label="Remove skill"><i class="fas fa-times"></i></button>
                </div>
                <?php endforeach; ?>
            </div>
            
            <div class="skill-input-wrapper">
                <input type="text" class="form-input" id="skillInput" placeholder="Type a skill (e.g., JavaScript, Project Management, Adobe Photoshop)">
                <button type="button" class="btn btn-primary" onclick="addSkill()">
                    <i class="fas fa-plus"></i> Add
                </button>
            </div>
            <p style="color: var(--text-muted); font-size: 0.8rem; margin-top: 12px;">
                <i class="fas fa-lightbulb" style="color: var(--accent);"></i> 
                Tip: Press Enter to add multiple skills quickly
            </p>
        </div>

        <!-- CERTIFICATIONS -->
        <div class="section-card">
            <div class="section-header">
                <div class="section-title">
                    <div class="section-icon"><i class="fas fa-certificate"></i></div>
                    Certifications & Licenses
                </div>
                <button type="button" class="btn-add" onclick="addCertification()">
                    <i class="fas fa-plus"></i> Add Certification
                </button>
            </div>
            
            <div id="certificationsList">
                <?php foreach ($certifications as $cert): ?>
                <div class="entry-item" data-type="certification">
                    <div class="entry-actions" style="position: absolute; top: 16px; right: 16px;">
                        <button type="button" class="btn-icon btn-delete" onclick="removeEntry(this)" aria-label="Remove entry">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Certification Name *</label>
                            <input type="text" class="form-input cert-name" value="<?= e($cert['name'] ?? '') ?>" placeholder="AWS Solutions Architect">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Issuing Organization</label>
                            <input type="text" class="form-input cert-issuer" value="<?= e($cert['issuing_organization'] ?? '') ?>" placeholder="Amazon Web Services">
                        </div>
                    </div>
                    <div class="form-row three-col">
                        <div class="form-group">
                            <label class="form-label">Issue Date</label>
                            <input type="month" class="form-input cert-issue" value="<?= !empty($cert['issue_date']) ? date('Y-m', strtotime($cert['issue_date'])) : '' ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Expiry Date</label>
                            <input type="month" class="form-input cert-expiry" value="<?= !empty($cert['expiry_date']) ? date('Y-m', strtotime($cert['expiry_date'])) : '' ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Credential URL</label>
                            <input type="url" class="form-input cert-url" value="<?= e($cert['credential_url'] ?? '') ?>" placeholder="https://...">
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- LANGUAGES -->
        <div class="section-card">
            <div class="section-header">
                <div class="section-title">
                    <div class="section-icon"><i class="fas fa-language"></i></div>
                    Languages
                </div>
                <button type="button" class="btn-add" onclick="addLanguage()">
                    <i class="fas fa-plus"></i> Add Language
                </button>
            </div>
            
            <div id="languagesList">
                <?php foreach ($languages as $lang): ?>
                <div class="entry-item" data-type="language" style="padding: 16px;">
                    <div class="entry-actions" style="position: absolute; top: 12px; right: 12px;">
                        <button type="button" class="btn-icon btn-delete" onclick="removeEntry(this)" aria-label="Remove entry">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Language</label>
                            <input type="text" class="form-input lang-name" value="<?= e($lang['language'] ?? '') ?>" placeholder="English">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Proficiency</label>
                            <select class="form-select lang-level">
                                <option value="basic" <?= ($lang['proficiency'] ?? '') === 'basic' ? 'selected' : '' ?>>Basic</option>
                                <option value="conversational" <?= ($lang['proficiency'] ?? '') === 'conversational' ? 'selected' : '' ?>>Conversational</option>
                                <option value="professional" <?= ($lang['proficiency'] ?? '') === 'professional' ? 'selected' : '' ?>>Professional Working</option>
                                <option value="fluent" <?= ($lang['proficiency'] ?? '') === 'fluent' ? 'selected' : '' ?>>Fluent</option>
                                <option value="native" <?= ($lang['proficiency'] ?? '') === 'native' ? 'selected' : '' ?>>Native</option>
                            </select>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- PROJECTS -->
        <div class="section-card">
            <div class="section-header">
                <div class="section-title">
                    <div class="section-icon"><i class="fas fa-rocket"></i></div>
                    Projects
                </div>
                <button type="button" class="btn-add" onclick="addProject()">
                    <i class="fas fa-plus"></i> Add Project
                </button>
            </div>
            
            <div id="projectsList">
                <?php foreach ($projects as $proj): ?>
                <div class="entry-item" data-type="project">
                    <div class="entry-actions" style="position: absolute; top: 16px; right: 16px;">
                        <button type="button" class="btn-icon btn-delete" onclick="removeEntry(this)" aria-label="Remove entry">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Project Name *</label>
                            <input type="text" class="form-input proj-name" value="<?= e($proj['name'] ?? '') ?>" placeholder="E-commerce Platform">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Your Role</label>
                            <input type="text" class="form-input proj-role" value="<?= e($proj['role'] ?? '') ?>" placeholder="Lead Developer">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Project URL</label>
                            <input type="url" class="form-input proj-url" value="<?= e($proj['url'] ?? '') ?>" placeholder="https://...">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Technologies Used</label>
                            <input type="text" class="form-input proj-tech" value="<?= e($proj['technologies'] ?? '') ?>" placeholder="React, Node.js, MongoDB">
                        </div>
                    </div>
                    <div class="form-group full-width">
                        <label class="form-label">Description</label>
                        <textarea class="form-textarea proj-description" placeholder="Describe the project, your contributions, and impact..."><?= e($proj['description'] ?? '') ?></textarea>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

    </form>
</div>

<!-- Save Indicator -->
<div class="save-indicator" id="saveIndicator">
    <div class="spinner"></div>
    <span>Saving...</span>
</div>

<!-- Toast -->
<div class="toast" id="toast"></div>

<script>
// Skill input Enter key
document.getElementById('skillInput').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        addSkill();
    }
});

function addSkill() {
    const input = document.getElementById('skillInput');
    const skill = input.value.trim();
    if (!skill) return;
    
    const container = document.getElementById('skillsList');
    const tag = document.createElement('div');
    tag.className = 'skill-tag';
    tag.innerHTML = `
        <span class="skill-name">${escapeHtml(skill)}</span>
        <button type="button" class="remove" onclick="removeSkill(this)" aria-label="Remove skill"><i class="fas fa-times"></i></button>
    `;
    container.appendChild(tag);
    input.value = '';
    input.focus();
}

function addExperience() {
    const list = document.getElementById('experienceList');
    const item = createEntryItem('experience', `
        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Job Title *</label>
                <input type="text" class="form-input exp-title" placeholder="Software Engineer">
            </div>
            <div class="form-group">
                <label class="form-label">Company *</label>
                <input type="text" class="form-input exp-company" placeholder="Google">
            </div>
        </div>
        <div class="form-row three-col">
            <div class="form-group">
                <label class="form-label">Location</label>
                <input type="text" class="form-input exp-location" placeholder="Lagos, Nigeria">
            </div>
            <div class="form-group">
                <label class="form-label">Start Date</label>
                <input type="month" class="form-input exp-start">
            </div>
            <div class="form-group">
                <label class="form-label">End Date</label>
                <input type="month" class="form-input exp-end">
            </div>
        </div>
        <div class="checkbox-group">
            <input type="checkbox" class="exp-current" onchange="this.closest('.entry-item').querySelector('.exp-end').disabled = this.checked">
            <label>I currently work here</label>
        </div>
        <div class="form-group full-width" style="margin-top: 20px;">
            <label class="form-label">Description & Achievements</label>
            <textarea class="form-textarea exp-description" placeholder="• Led a team of 5 developers to deliver...&#10;• Increased system performance by 40%..."></textarea>
        </div>
    `);
    list.appendChild(item);
}

function addEducation() {
    const list = document.getElementById('educationList');
    const item = createEntryItem('education', `
        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Institution *</label>
                <input type="text" class="form-input edu-institution" placeholder="University of Lagos">
            </div>
            <div class="form-group">
                <label class="form-label">Degree</label>
                <input type="text" class="form-input edu-degree" placeholder="Bachelor of Science">
            </div>
        </div>
        <div class="form-row three-col">
            <div class="form-group">
                <label class="form-label">Field of Study</label>
                <input type="text" class="form-input edu-field" placeholder="Computer Science">
            </div>
            <div class="form-group">
                <label class="form-label">Start Date</label>
                <input type="month" class="form-input edu-start">
            </div>
            <div class="form-group">
                <label class="form-label">End Date</label>
                <input type="month" class="form-input edu-end">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Grade/GPA</label>
                <input type="text" class="form-input edu-grade" placeholder="First Class Honours">
            </div>
            <div class="form-group">
                <label class="form-label">Location</label>
                <input type="text" class="form-input edu-location" placeholder="Lagos, Nigeria">
            </div>
        </div>
    `);
    list.appendChild(item);
}

function addCertification() {
    const list = document.getElementById('certificationsList');
    const item = createEntryItem('certification', `
        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Certification Name *</label>
                <input type="text" class="form-input cert-name" placeholder="AWS Solutions Architect">
            </div>
            <div class="form-group">
                <label class="form-label">Issuing Organization</label>
                <input type="text" class="form-input cert-issuer" placeholder="Amazon Web Services">
            </div>
        </div>
        <div class="form-row three-col">
            <div class="form-group">
                <label class="form-label">Issue Date</label>
                <input type="month" class="form-input cert-issue">
            </div>
            <div class="form-group">
                <label class="form-label">Expiry Date</label>
                <input type="month" class="form-input cert-expiry">
            </div>
            <div class="form-group">
                <label class="form-label">Credential URL</label>
                <input type="url" class="form-input cert-url" placeholder="https://...">
            </div>
        </div>
    `);
    list.appendChild(item);
}

function addLanguage() {
    const list = document.getElementById('languagesList');
    const item = createEntryItem('language', `
        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Language</label>
                <input type="text" class="form-input lang-name" placeholder="English">
            </div>
            <div class="form-group">
                <label class="form-label">Proficiency</label>
                <select class="form-select lang-level">
                    <option value="basic">Basic</option>
                    <option value="conversational">Conversational</option>
                    <option value="professional" selected>Professional Working</option>
                    <option value="fluent">Fluent</option>
                    <option value="native">Native</option>
                </select>
            </div>
        </div>
    `, 'padding: 16px;');
    list.appendChild(item);
}

function addProject() {
    const list = document.getElementById('projectsList');
    const item = createEntryItem('project', `
        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Project Name *</label>
                <input type="text" class="form-input proj-name" placeholder="E-commerce Platform">
            </div>
            <div class="form-group">
                <label class="form-label">Your Role</label>
                <input type="text" class="form-input proj-role" placeholder="Lead Developer">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Project URL</label>
                <input type="url" class="form-input proj-url" placeholder="https://...">
            </div>
            <div class="form-group">
                <label class="form-label">Technologies Used</label>
                <input type="text" class="form-input proj-tech" placeholder="React, Node.js, MongoDB">
            </div>
        </div>
        <div class="form-group full-width">
            <label class="form-label">Description</label>
            <textarea class="form-textarea proj-description" placeholder="Describe the project..."></textarea>
        </div>
    `);
    list.appendChild(item);
}

function createEntryItem(type, content, style = '') {
    const div = document.createElement('div');
    div.className = 'entry-item';
    div.setAttribute('data-type', type);
    if (style) div.style = style;
    div.innerHTML = `
        <div class="entry-actions" style="position: absolute; top: 16px; right: 16px;">
            <button type="button" class="btn-icon btn-delete" onclick="removeEntry(this)" aria-label="Remove entry">
                <i class="fas fa-trash"></i>
            </button>
        </div>
        ${content}
    `;
    return div;
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function removeEntry(button) {
    const item = button.closest('.entry-item');
    if (!item) return;
    item.classList.add('is-removing');
    setTimeout(() => item.remove(), 160);
}

function removeSkill(button) {
    const tag = button.closest('.skill-tag');
    if (!tag) return;
    tag.classList.add('is-removing');
    setTimeout(() => tag.remove(), 140);
}

function showToast(message, type = 'success') {
    const toast = document.getElementById('toast');
    toast.textContent = message.trim();
    toast.className = `toast ${type} show`;
    setTimeout(() => toast.classList.remove('show'), 3000);
}

async function saveCV() {
    const indicator = document.getElementById('saveIndicator');
    indicator.classList.add('saving');
    
    const form = document.getElementById('cvForm');
    
    // Collect all data
    const data = {
        cv_id: form.querySelector('[name="cv_id"]').value,
        personal: {
            full_name: form.querySelector('[name="full_name"]').value,
            headline: form.querySelector('[name="headline"]').value,
            email: form.querySelector('[name="email"]').value,
            phone: form.querySelector('[name="phone"]').value,
            location: form.querySelector('[name="location"]').value,
            linkedin_url: form.querySelector('[name="linkedin_url"]').value,
            portfolio_url: form.querySelector('[name="portfolio_url"]').value,
            github_url: form.querySelector('[name="github_url"]').value,
            summary: form.querySelector('[name="summary"]').value
        },
        experience: [],
        education: [],
        skills: [],
        certifications: [],
        languages: [],
        projects: []
    };
    
    // Experience
    document.querySelectorAll('#experienceList .entry-item').forEach(item => {
        data.experience.push({
            job_title: item.querySelector('.exp-title')?.value || '',
            company: item.querySelector('.exp-company')?.value || '',
            location: item.querySelector('.exp-location')?.value || '',
            start_date: item.querySelector('.exp-start')?.value || '',
            end_date: item.querySelector('.exp-end')?.value || '',
            is_current: item.querySelector('.exp-current')?.checked || false,
            description: item.querySelector('.exp-description')?.value || ''
        });
    });
    
    // Education
    document.querySelectorAll('#educationList .entry-item').forEach(item => {
        data.education.push({
            institution: item.querySelector('.edu-institution')?.value || '',
            degree: item.querySelector('.edu-degree')?.value || '',
            field_of_study: item.querySelector('.edu-field')?.value || '',
            location: item.querySelector('.edu-location')?.value || '',
            start_date: item.querySelector('.edu-start')?.value || '',
            end_date: item.querySelector('.edu-end')?.value || '',
            grade: item.querySelector('.edu-grade')?.value || ''
        });
    });
    
    // Skills
    document.querySelectorAll('#skillsList .skill-tag .skill-name').forEach(tag => {
        data.skills.push({ name: tag.textContent.trim() });
    });
    
    // Certifications
    document.querySelectorAll('#certificationsList .entry-item').forEach(item => {
        data.certifications.push({
            name: item.querySelector('.cert-name')?.value || '',
            issuing_organization: item.querySelector('.cert-issuer')?.value || '',
            issue_date: item.querySelector('.cert-issue')?.value || '',
            expiry_date: item.querySelector('.cert-expiry')?.value || '',
            credential_url: item.querySelector('.cert-url')?.value || ''
        });
    });
    
    // Languages
    document.querySelectorAll('#languagesList .entry-item').forEach(item => {
        data.languages.push({
            language: item.querySelector('.lang-name')?.value || '',
            proficiency: item.querySelector('.lang-level')?.value || 'professional'
        });
    });
    
    // Projects
    document.querySelectorAll('#projectsList .entry-item').forEach(item => {
        data.projects.push({
            name: item.querySelector('.proj-name')?.value || '',
            role: item.querySelector('.proj-role')?.value || '',
            url: item.querySelector('.proj-url')?.value || '',
            technologies: item.querySelector('.proj-tech')?.value || '',
            description: item.querySelector('.proj-description')?.value || ''
        });
    });
    
    try {
        const response = await fetch('<?= SITE_URL ?>/cv-builder/save-complete.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        indicator.classList.remove('saving');
        
        if (result.success) {
            showToast('CV saved successfully.', 'success');
        } else {
            showToast(result.message || 'Could not save CV.', 'error');
        }
    } catch (error) {
        indicator.classList.remove('saving');
        showToast('Network error: ' + error.message, 'error');
    }
}
</script>

<?php jm_cv_footer(); ?>
