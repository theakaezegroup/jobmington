<?php
if (!defined('JOBMINGTON')) {
    die('Direct access not permitted');
}

function jm_cv_templates(): array {
    return [
        'obsidian' => [
            'id' => 'obsidian',
            'name' => 'Executive',
            'tone' => 'orange',
            'score' => 98,
            'description' => 'A refined single-column CV with strong hierarchy, generous spacing, and boardroom polish.',
            'best_for' => 'Leadership roles',
        ],
        'cybernetic' => [
            'id' => 'cybernetic',
            'name' => 'Modern',
            'tone' => 'green',
            'score' => 95,
            'description' => 'A fresh modern profile with a balanced header and crisp section rhythm for fast scanning.',
            'best_for' => 'Tech and product',
        ],
        'blueprint' => [
            'id' => 'blueprint',
            'name' => 'Technical',
            'tone' => 'blue',
            'score' => 96,
            'description' => 'A precise technical layout with compact metadata, structured projects, and clean skills.',
            'best_for' => 'Engineering roles',
        ],
    ];
}

function jm_cv_template(string $templateId): array {
    $templates = jm_cv_templates();
    $key = strtolower(trim($templateId));

    return $templates[$key] ?? $templates['obsidian'];
}

function jm_cv_header(string $pageTitle, string $active = 'cv'): void {
    $isLoggedIn = Session::isLoggedIn();
    $dashboardUrl = Session::isAdmin()
        ? '/jobmington/admin/'
        : (Session::isEmployer() ? '/jobmington/employer/dashboard.php' : '/jobmington/seeker/dashboard.php');
    ?>
    <!doctype html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?= e($pageTitle) ?></title>
    <link rel="preload" as="font" type="font/ttf" href="/jobmington/assets/fonts/FuturaCyrillicDemi.ttf" crossorigin>
    <link rel="preload" as="font" type="font/ttf" href="/jobmington/assets/fonts/FuturaCyrillicBook.ttf" crossorigin>
        <link rel="stylesheet" href="/jobmington/assets/css/minimal-jobmington.css?v=brand-27">
    </head>
    <body class="jm-minimal">
        <div class="jm-shell">
            <header class="jm-header">
                <a class="jm-logo" href="/jobmington/">
                    <img src="/jobmington/assets/images/badge.png?v=logo-8" alt="">
                    <span>Jobmington</span>
                </a>
            <?php require_once __DIR__ . '/../includes/navigation.php'; jm_site_nav('/cv-builder'); ?>
            </header>
    <?php
}

function jm_cv_footer(): void {
    ?>
            <?php jm_minimal_footer(); ?>
        </div>
    </body>
    </html>
    <?php
}

function jm_cv_template_preview(array $template): void {
    ?>
    <div class="jm-cv-preview tone-<?= e($template['tone']) ?> template-<?= e($template['id']) ?>">
        <div class="jm-cv-preview-paper">
            <div class="jm-cv-preview-head">
                <span></span>
                <div>
                    <i></i>
                    <i></i>
                </div>
            </div>
            <div class="jm-cv-preview-body">
                <div class="jm-cv-preview-block">
                    <b></b>
                    <i></i>
                    <i></i>
                </div>
                <div class="jm-cv-preview-block">
                    <b></b>
                    <i></i>
                    <i></i>
                </div>
                <div class="jm-cv-preview-tags">
                    <em></em>
                    <em></em>
                    <em></em>
                </div>
            </div>
        </div>
    </div>
    <?php
}
