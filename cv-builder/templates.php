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
$templates = jm_cv_templates();
$selectedTemplate = strtolower(trim(Security::clean($_GET['select'] ?? '')));
$cvId = (int) ($_GET['cv_id'] ?? 0);

if ($selectedTemplate !== '' && isset($templates[$selectedTemplate])) {
    if ($cvId > 0) {
        $stmt = $pdo->prepare("UPDATE cv_profiles SET template = ?, updated_at = NOW() WHERE cv_id = ? AND user_id = ?");
        $stmt->execute([$selectedTemplate, $cvId, Session::userId()]);
        redirect(SITE_URL . '/cv-builder/editor-complete.php?id=' . $cvId);
    }

    $stmt = $pdo->prepare("
        INSERT INTO cv_profiles (user_id, title, template, created_at, updated_at)
        VALUES (?, ?, ?, NOW(), NOW())
    ");
    $stmt->execute([Session::userId(), 'My ' . $templates[$selectedTemplate]['name'] . ' CV', $selectedTemplate]);
    redirect(SITE_URL . '/cv-builder/editor-complete.php?id=' . (int) $pdo->lastInsertId());
}

$pageTitle = 'CV Templates | ' . SITE_NAME;
jm_cv_header($pageTitle, 'cv');
?>

<section class="jm-section jm-cv-page" style="padding-top:0;">
    <div class="jm-cv-hero">
        <div>
            <p class="jm-kicker">CV templates</p>
            <h1>Pick the structure before you write.</h1>
            <p>These templates are already wired into the export flow, so the selected style follows your CV through editing and download.</p>
        </div>
        <a class="jm-button secondary" href="/jobmington/cv-builder/create.php">Create from scratch</a>
    </div>

    <div class="jm-cv-template-grid feature">
        <?php foreach ($templates as $template): ?>
            <article class="jm-cv-template-card">
                <div class="jm-cv-template-inner">
                    <?php jm_cv_template_preview($template); ?>
                    <div class="jm-cv-template-copy">
                        <strong><?= e($template['name']) ?></strong>
                        <small><?= e($template['description']) ?></small>
                    </div>
                    <div class="jm-tag-row compact">
                        <span class="jm-job-tag <?= e('tone-' . $template['tone']) ?>">ATS <?= (int) $template['score'] ?></span>
                        <span class="jm-job-tag"><?= e($template['best_for']) ?></span>
                    </div>
                    <a class="jm-button" href="/jobmington/cv-builder/templates.php?select=<?= e($template['id']) ?><?= $cvId > 0 ? '&cv_id=' . (int) $cvId : '' ?>">Use template</a>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</section>

<?php jm_cv_footer(); ?>
