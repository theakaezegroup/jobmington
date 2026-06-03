<?php
/**
 * JOBMINGTON - Ebook detail (public)
 */
define('JOBMINGTON', true);
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';

Session::start();
$pdo = db();

$slug = trim((string) get('slug', ''));
$ebook = null;
if ($slug !== '') {
    $stmt = $pdo->prepare("SELECT * FROM ebooks WHERE slug = ? AND is_published = 1 LIMIT 1");
    $stmt->execute([$slug]);
    $ebook = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

if (!$ebook) {
    http_response_code(404);
    $pageTitle = 'Ebook not found - ' . SITE_NAME;
    $activeAIPage = "learn"; require_once __DIR__ . '/../includes/ai-header.php';
    echo '<div style="max-width:680px;margin:80px auto;text-align:center;padding:0 20px;"><h1 style="color:#061426;">Ebook not found</h1><p style="color:#53667f;">It may have been unpublished. <a href="/jobmington/ebooks/" style="color:#0640a3;">Back to library</a></p></div>';
    require_once __DIR__ . '/../includes/ai-footer.php';
    exit;
}

$pageTitle = $ebook['title'] . ' - ' . SITE_NAME;
$activeAIPage = "learn"; require_once __DIR__ . '/../includes/ai-header.php';
?>
<style>
.jm-ebv { max-width:980px; margin:0 auto; padding:40px 20px 72px; }
.jm-ebv-grid { display:grid; grid-template-columns:280px 1fr; gap:36px; align-items:start; }
@media (max-width:760px){ .jm-ebv-grid { grid-template-columns:1fr; } }
.jm-ebv-cover { aspect-ratio:3/4; border-radius:16px; overflow:hidden; background:linear-gradient(135deg,#eef5ff,#f7faff); display:grid; place-items:center; border:1px solid #e4eaf3; }
.jm-ebv-cover img { width:100%; height:100%; object-fit:cover; }
.jm-ebv-cover svg { width:54px; height:54px; color:#9bb0c7; }
.jm-ebv-cat { font-size:11px; font-weight:800; text-transform:uppercase; letter-spacing:.08em; color:#0640a3; }
.jm-ebv h1 { font-size:clamp(26px,4vw,38px); font-weight:800; letter-spacing:-.02em; color:#061426; margin:8px 0 8px; line-height:1.15; }
.jm-ebv-meta { font-size:14px; color:#53667f; margin-bottom:18px; }
.jm-ebv-desc { font-size:15px; color:#0b1b33; line-height:1.7; white-space:pre-wrap; margin-bottom:24px; }
.jm-ebv-cta { display:flex; gap:12px; flex-wrap:wrap; align-items:center; }
.jm-ebv-btn { display:inline-flex; align-items:center; gap:8px; background:#0640a3; color:#fff; border-radius:10px; padding:13px 22px; font-weight:800; font-size:14px; text-decoration:none; }
.jm-ebv-btn:hover { background:#052f78; }
.jm-ebv-price { font-size:15px; font-weight:800; color:#0a6454; }
</style>

<div class="jm-ebv">
    <?= jm_breadcrumbs([['label' => 'Ebooks', 'url' => '/jobmington/ebooks/'], ['label' => $ebook['title']]]) ?>
    <div class="jm-ebv-grid">
        <div class="jm-ebv-cover">
            <?php if (!empty($ebook['cover_image'])): ?>
                <img src="<?= e($ebook['cover_image']) ?>" alt="<?= e($ebook['title']) ?>">
            <?php else: ?>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
            <?php endif; ?>
        </div>
        <div>
            <?php if ($ebook['category']): ?><span class="jm-ebv-cat"><?= e($ebook['category']) ?></span><?php endif; ?>
            <h1><?= e($ebook['title']) ?></h1>
            <div class="jm-ebv-meta">
                <?php if ($ebook['author']): ?>by <strong><?= e($ebook['author']) ?></strong> &middot; <?php endif; ?>
                <?= $ebook['pages'] ? (int)$ebook['pages'] . ' pages &middot; ' : '' ?>
                <?= (int)$ebook['download_count'] ?> downloads
            </div>
            <div class="jm-ebv-desc"><?= e($ebook['description'] ?: 'No description provided.') ?></div>
            <div class="jm-ebv-cta">
                <?php if (!empty($ebook['file_path'])): ?>
                    <a class="jm-ebv-btn" href="/jobmington/ebooks/download.php?slug=<?= e($ebook['slug']) ?>">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                        <?= $ebook['is_free'] ? 'Download free' : 'Download' ?>
                    </a>
                <?php else: ?>
                    <span style="color:#94a3b8;font-size:14px;">Download coming soon.</span>
                <?php endif; ?>
                <span class="jm-ebv-price"><?= $ebook['is_free'] ? 'Free' : '₦' . number_format((float)$ebook['price']) ?></span>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/ai-footer.php'; ?>
