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
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::verifyCSRF()) {
        $error = 'Your session expired. Please try again.';
    } else {
        $title = trim(Security::clean($_POST['title'] ?? ''));
        $templateId = strtolower(trim(Security::clean($_POST['template'] ?? 'obsidian')));

        if ($title === '') {
            $title = 'My CV';
        }

        if (!isset($templates[$templateId])) {
            $templateId = 'obsidian';
        }

        try {
            $stmt = $pdo->prepare("
                INSERT INTO cv_profiles (user_id, title, template, created_at, updated_at)
                VALUES (?, ?, ?, NOW(), NOW())
            ");
            $stmt->execute([Session::userId(), substr($title, 0, 100), $templateId]);
            Security::regenerateCSRF();

            redirect(SITE_URL . '/cv-builder/editor-complete.php?id=' . (int) $pdo->lastInsertId());
        } catch (Throwable $e) {
            $error = 'Could not create the CV right now. Please try again.';
        }
    }
}

$pageTitle = 'Create CV | ' . SITE_NAME;
jm_cv_header($pageTitle, 'cv');
?>

<section class="jm-section jm-cv-page" style="padding-top:0;">
    <?php jm_cv_breadcrumb([['Templates', '/jobmington/cv-builder/templates.php'], ['New CV']]); ?>
    <div class="jm-cv-hero">
        <div>
            <p class="jm-kicker">CV Builder</p>
            <h1>Create a CV that feels ready to send.</h1>
            <p>Choose one of your existing templates, name the CV, and continue into the editor.</p>
        </div>
        <a class="jm-button secondary" href="/jobmington/cv-builder/">Back to CVs</a>
    </div>

    <?php if ($error !== ''): ?>
        <div class="jm-alert"><?= e($error) ?></div>
    <?php endif; ?>

    <form class="jm-panel jm-cv-create-form" method="post">
        <?= Security::csrfField() ?>

        <div class="jm-field">
            <label for="cv-title">CV title</label>
            <input class="jm-input" id="cv-title" type="text" name="title" value="<?= e($_POST['title'] ?? 'My CV') ?>" maxlength="100" placeholder="Example: Product manager CV" required>
        </div>

        <div class="jm-field">
            <label>Template</label>
            <div class="jm-cv-template-grid">
                <?php $first = true; ?>
                <?php foreach ($templates as $template): ?>
                    <?php
                        $selected = ($_POST['template'] ?? '') === $template['id'] || ($first && empty($_POST['template']));
                        $first = false;
                    ?>
                    <label class="jm-cv-template-card">
                        <input class="jm-sr-only" type="radio" name="template" value="<?= e($template['id']) ?>" <?= $selected ? 'checked' : '' ?>>
                        <div class="jm-cv-template-inner">
                            <?php jm_cv_template_preview($template); ?>
                            <span class="jm-cv-template-copy">
                                <strong><?= e($template['name']) ?></strong>
                                <small><?= e($template['description']) ?></small>
                            </span>
                            <span class="jm-tag-row compact">
                                <span class="jm-job-tag <?= e('tone-' . $template['tone']) ?>">ATS <?= (int) $template['score'] ?></span>
                                <span class="jm-job-tag"><?= e($template['best_for']) ?></span>
                            </span>
                        </div>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="jm-form-actions">
            <button class="jm-button" type="submit">Create CV</button>
            <a class="jm-button secondary" href="/jobmington/cv-builder/templates.php">Browse templates</a>
        </div>
    </form>
</section>

<?php jm_cv_footer(); ?>
