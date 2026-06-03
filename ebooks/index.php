<?php
/**
 * JOBMINGTON - Ebooks library (public)
 */
define('JOBMINGTON', true);
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/learn_nav.php';

Session::start();
$pdo = db();

$ebooks = [];
try {
    $ebooks = $pdo->query("SELECT * FROM ebooks WHERE is_published = 1 ORDER BY is_featured DESC, created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { $ebooks = []; }

$pageTitle = 'Ebooks & Guides - ' . SITE_NAME;
$activeAIPage = 'learn';
require_once __DIR__ . '/../includes/ai-header.php';
?>
<style>
.jm-eb-page { max-width:1100px; margin:0 auto; padding:48px 20px 72px; }
.jm-eb-head h1 { font-size:clamp(30px,5vw,46px); font-weight:800; letter-spacing:-.02em; color:#061426; margin:0 0 12px; }
.jm-eb-head p { font-size:17px; color:#53667f; margin:0 0 36px; max-width:600px; line-height:1.6; }
.jm-eb-grid { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:18px; }
@media (max-width:900px){ .jm-eb-grid { grid-template-columns:repeat(2,minmax(0,1fr)); } }
@media (max-width:540px){ .jm-eb-grid { grid-template-columns:1fr; } }
.jm-eb-item { display:flex; flex-direction:column; background:#fff; border:1px solid #e4eaf3; border-radius:14px; overflow:hidden; text-decoration:none; transition:box-shadow .16s, transform .16s; }
.jm-eb-item:hover { box-shadow:0 12px 30px rgba(6,20,38,.1); transform:translateY(-3px); }
.jm-eb-cover { aspect-ratio:3/4; background:linear-gradient(135deg,#eef5ff,#f7faff); display:grid; place-items:center; overflow:hidden; }
.jm-eb-cover img { width:100%; height:100%; object-fit:cover; }
.jm-eb-cover svg { width:42px; height:42px; color:#9bb0c7; }
.jm-eb-body { padding:14px 16px 16px; display:flex; flex-direction:column; gap:6px; flex:1; }
.jm-eb-cat { font-size:10px; font-weight:800; text-transform:uppercase; letter-spacing:.07em; color:#0640a3; }
.jm-eb-title { font-size:15px; font-weight:800; color:#061426; line-height:1.3; }
.jm-eb-author { font-size:12px; color:#94a3b8; }
.jm-eb-foot { margin-top:auto; display:flex; align-items:center; justify-content:space-between; padding-top:8px; }
.jm-eb-price { font-size:12px; font-weight:800; color:#0a6454; }
.jm-eb-empty { text-align:center; color:#94a3b8; padding:60px 20px; background:#fff; border:1px solid #e4eaf3; border-radius:14px; }
</style>

<div class="jm-eb-page">
    <?= jm_breadcrumbs([['label' => 'Ebooks']]) ?>
    <?= jm_learn_nav('ebooks') ?>
    <div class="jm-eb-head">
        <h1>Ebooks &amp; guides.</h1>
        <p>Practical, downloadable resources to grow your career — free and premium.</p>
    </div>

    <?php if (empty($ebooks)): ?>
        <div class="jm-eb-empty">No ebooks published yet. Check back soon.</div>
    <?php else: ?>
        <div class="jm-eb-grid">
            <?php foreach ($ebooks as $b): ?>
                <a class="jm-eb-item" href="/jobmington/ebooks/view.php?slug=<?= e($b['slug']) ?>">
                    <div class="jm-eb-cover">
                        <?php if (!empty($b['cover_image'])): ?>
                            <img src="<?= e($b['cover_image']) ?>" alt="<?= e($b['title']) ?>">
                        <?php else: ?>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
                        <?php endif; ?>
                    </div>
                    <div class="jm-eb-body">
                        <?php if ($b['category']): ?><span class="jm-eb-cat"><?= e($b['category']) ?></span><?php endif; ?>
                        <span class="jm-eb-title"><?= e($b['title']) ?></span>
                        <?php if ($b['author']): ?><span class="jm-eb-author">by <?= e($b['author']) ?></span><?php endif; ?>
                        <div class="jm-eb-foot">
                            <span class="jm-eb-price"><?= $b['is_free'] ? 'Free' : '₦' . number_format((float)$b['price']) ?></span>
                            <span style="font-size:11px;color:#94a3b8;"><?= (int)$b['download_count'] ?> downloads</span>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/ai-footer.php'; ?>
