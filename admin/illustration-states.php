<?php
define('JOBMINGTON', true);
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';

Session::start();
Session::requireAdmin();

$states = jm_illustration_states();
$pageTitle = 'State Icons | ' . SITE_NAME;
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?></title>
    <link rel="stylesheet" href="/jobmington/assets/css/minimal-jobmington.css?v=brand-15">
    <style>
        body.jm-minimal {
            background: #f6f9fd;
        }

        .state-preview-header {
            align-items: flex-start;
            display: flex;
            gap: 24px;
            justify-content: space-between;
            margin-bottom: 34px;
        }

        .state-preview-header h1 {
            color: var(--jm-ink);
            font-size: clamp(34px, 5vw, 58px);
            line-height: 1.02;
            margin: 0;
        }

        .state-preview-header p {
            color: var(--jm-muted);
            margin: 14px 0 0;
            max-width: 680px;
        }

        .state-preview-tools {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            justify-content: flex-end;
            min-width: 230px;
        }

        .state-preview-tile {
            min-width: 0;
        }

        .state-preview-tile .jm-state-preview-meta {
            padding-left: 2px;
        }

        @media (max-width: 760px) {
            .state-preview-header {
                display: block;
            }

            .state-preview-tools {
                justify-content: flex-start;
                margin-top: 20px;
                min-width: 0;
            }
        }
    </style>
</head>
<body class="jm-minimal">
    <div class="jm-shell">
        <header class="jm-header">
            <a class="jm-logo" href="/jobmington/admin/">
                <img src="/jobmington/assets/images/badge.png?v=logo-7" alt="">
                <span>Jobmington Admin</span>
            </a>
            <nav class="jm-nav" aria-label="Admin navigation">
                <a href="/jobmington/admin/">Dashboard</a>
                <a href="/jobmington/admin/operations.php">Operations</a>
                <a href="/jobmington/admin/jobs.php">Jobs</a>
                <a href="/jobmington/admin/users.php">Users</a>
                <a class="jm-button secondary" href="/jobmington/">View site</a>
            </nav>
        </header>

        <main class="jm-section" style="padding-top:0;">
            <div class="state-preview-header">
                <div>
                    <p class="jm-kicker">Illustration states</p>
                    <h1>Notification and empty-state icons.</h1>
                    <p>Review every Jobmington notice design in one place before it appears across dashboards, alerts, confirmations, and quiet empty moments.</p>
                </div>
                <div class="state-preview-tools">
                    <a class="jm-button secondary" href="/jobmington/admin/">Admin dashboard</a>
                </div>
            </div>

            <div class="jm-state-grid" aria-label="Jobmington illustration states">
                <?php foreach ($states as $state): ?>
                    <div class="state-preview-tile">
                        <?= jm_illustration_state_card($state, [
                            'compact' => true,
                            'class' => 'jm-state-preview-card',
                            'title_tag' => 'h2',
                        ]) ?>
                        <span class="jm-state-preview-meta"><?= e($state['key']) ?> / <?= e($state['type'] ?? 'state') ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </main>
    </div>
</body>
</html>
