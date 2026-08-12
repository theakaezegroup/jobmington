<?php
define('JOBMINGTON', true);
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';

Session::start();
Session::requireLogin();

$pdo = db();
$userId = Session::userId();

$earnedBadges = [];
$availableBadges = [];
try {
    $stmt = $pdo->prepare("
        SELECT ub.*, b.name, b.description, b.icon
        FROM user_badges ub
        JOIN badges b ON ub.badge_id = b.badge_id
        WHERE ub.user_id = ?
        ORDER BY ub.earned_at DESC
    ");
    $stmt->execute([$userId]);
    $earnedBadges = $stmt->fetchAll();

    $stmt = $pdo->prepare("
        SELECT b.*
        FROM badges b
        WHERE b.badge_id NOT IN (SELECT badge_id FROM user_badges WHERE user_id = ?)
        ORDER BY b.name
    ");
    $stmt->execute([$userId]);
    $availableBadges = $stmt->fetchAll();
} catch (Throwable $e) {
    $earnedBadges = [];
    $availableBadges = [];
}

$pageTitle = 'Badges | ' . SITE_NAME;
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?></title>
    <link rel="preload" as="font" type="font/ttf" href="/jobmington/assets/fonts/FuturaCyrillicDemi.ttf" crossorigin>
    <link rel="preload" as="font" type="font/ttf" href="/jobmington/assets/fonts/FuturaCyrillicBook.ttf" crossorigin>
    <link rel="stylesheet" href="/jobmington/assets/css/minimal-jobmington.css?v=brand-24">
</head>
<body class="jm-minimal">
    <div class="jm-shell">
        <header class="jm-header">
            <a class="jm-logo" href="/jobmington/"><img src="/jobmington/assets/images/badge.png?v=logo-8" alt=""><span>Jobmington</span></a>
            <?php require_once __DIR__ . '/../includes/navigation.php'; jm_workspace_nav(['dashboard' => ['/jobmington/seeker/dashboard.php', 'Dashboard'], 'applications' => ['/jobmington/seeker/applications.php', 'Applications'], 'saved' => ['/jobmington/jobs/saved.php', 'Saved jobs'], 'profile' => ['/jobmington/seeker/profile.php', 'Profile']], 'profile'); ?>
        </header>

        <section class="jm-section" style="padding-top:0;">
            <div class="jm-section-head">
                <div>
                    <p class="jm-kicker">Badges</p>
                    <h1>Your proof of progress.</h1>
                    <p>Badges you earn through learning and verification will appear here.</p>
                </div>
                <span class="jm-badge"><?= number_format(count($earnedBadges)) ?> earned</span>
            </div>

            <?php if (empty($earnedBadges)): ?>
                <div class="jm-empty">
                    <h2>No badges yet</h2>
                    <p>Complete your profile and keep your applications active to earn badges.</p>
                    <a class="jm-button" href="/jobmington/seeker/profile.php">Improve profile</a>
                </div>
            <?php else: ?>
                <div class="jm-grid-3">
                    <?php foreach ($earnedBadges as $badge): ?>
                        <article class="jm-card">
                            <?php if (!empty($badge['icon'])): ?>
                                <img src="<?= e(upload('badges/' . $badge['icon'])) ?>" alt="" style="width:44px;height:44px;object-fit:contain;margin-bottom:14px;">
                            <?php endif; ?>
                            <h3><?= e($badge['name']) ?></h3>
                            <p><?= e($badge['description'] ?? 'Achievement earned on Jobmington.') ?></p>
                            <span class="jm-badge">Earned <?= e(formatDate($badge['earned_at'], 'M d, Y')) ?></span>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <?php if (!empty($availableBadges)): ?>
            <section class="jm-section">
                <div class="jm-section-head">
                    <div>
                        <h2>Available badges</h2>
                        <p>These can be earned as your account grows.</p>
                    </div>
                </div>
                <div class="jm-grid-3">
                    <?php foreach ($availableBadges as $badge): ?>
                        <article class="jm-card">
                            <h3><?= e($badge['name']) ?></h3>
                            <p><?= e($badge['description'] ?? 'Available achievement.') ?></p>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <?php jm_minimal_footer(); ?>
    </div>
</body>
</html>
