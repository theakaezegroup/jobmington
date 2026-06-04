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
require_once __DIR__ . '/../includes/seeds.php';

Session::start();
$pdo = db();

$slug = trim((string) get('slug', ''));
$ebook = null;
if ($slug !== '') {
    $stmt = $pdo->prepare("SELECT * FROM ebooks WHERE slug = ? AND is_published = 1 LIMIT 1");
    $stmt->execute([$slug]);
    $ebook = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

// ── Premium unlock (Seeds / Credits) ──────────────────────────────────────────
$ebMsg = '';
if ($ebook && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['unlock_ebook'])) {
    if (!Session::isLoggedIn()) {
        redirect('/jobmington/auth/login.php?redirect=' . urlencode('/jobmington/ebooks/view.php?slug=' . $ebook['slug']));
    }
    if (!Security::verifyCSRF()) {
        $ebMsg = 'Session expired. Please try again.';
    } else {
        $uid = (int) Session::userId();
        $p   = jm_content_prices((float) $ebook['price'], (int) ($ebook['seed_price'] ?? 0), (int) ($ebook['credit_price'] ?? 0));
        $method = (string) post('method', 'seeds');
        if (jm_ebook_has_access($pdo, $uid, $ebook)) {
            redirect('/jobmington/ebooks/download.php?slug=' . $ebook['slug']);
        }
        $pay = $method === 'credits'
            ? jm_pay_with_credits($uid, (int) $p['credits'], 'ebook_unlock', 'Ebook: ' . $ebook['title'], (int) $ebook['ebook_id'])
            : jm_pay_with_seeds($uid, (float) $p['seeds'], 'ebook_unlock', 'Ebook: ' . $ebook['title'], (int) $ebook['ebook_id']);
        if (!empty($pay['success'])) {
            try {
                $pdo->prepare("INSERT IGNORE INTO ebook_purchases (user_id, ebook_id, method, amount) VALUES (?, ?, ?, ?)")
                    ->execute([$uid, (int) $ebook['ebook_id'], $method, $method === 'credits' ? (int) $p['credits'] : (int) $p['seeds']]);
            } catch (Throwable $e) {}
            redirect('/jobmington/ebooks/download.php?slug=' . $ebook['slug']);
        }
        $ebMsg = $pay['message'] ?? 'Payment failed.';
    }
}

if (!$ebook) {
    http_response_code(404);
    $pageTitle = 'Ebook not found - ' . SITE_NAME;
    $activeAIPage = "learn"; require_once __DIR__ . '/../includes/ai-header.php';
    echo '<div style="max-width:680px;margin:80px auto;text-align:center;padding:0 20px;"><h1 style="color:#061426;">Ebook not found</h1><p style="color:#53667f;">It may have been unpublished. <a href="/jobmington/ebooks/" style="color:#0640a3;">Back to library</a></p></div>';
    require_once __DIR__ . '/../includes/ai-footer.php';
    exit;
}

$ebPrices    = jm_content_prices((float) $ebook['price'], (int) ($ebook['seed_price'] ?? 0), (int) ($ebook['credit_price'] ?? 0));
$ebHasAccess = jm_ebook_has_access($pdo, (int) Session::userId(), $ebook);
$ebWallet    = Session::isLoggedIn() ? jm_wallet_summary((int) Session::userId()) : ['seeds' => 0, 'credits' => 0];

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
            <?php if ($ebMsg): ?><div style="background:#fef2f2;border:1px solid #fecaca;color:#b42318;padding:10px 14px;border-radius:10px;font-size:13px;margin-bottom:14px;"><?= e($ebMsg) ?></div><?php endif; ?>
            <div class="jm-ebv-cta">
                <?php if (empty($ebook['file_path'])): ?>
                    <span style="color:#94a3b8;font-size:14px;">Download coming soon.</span>
                <?php elseif (!Session::isLoggedIn()): ?>
                    <a class="jm-ebv-btn" href="/jobmington/auth/login.php?redirect=<?= urlencode('/jobmington/ebooks/view.php?slug=' . $ebook['slug']) ?>">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                        Sign in to download
                    </a>
                    <span class="jm-ebv-price"><?= $ebook['is_free'] ? 'Free' : '₦' . number_format((float)$ebook['price']) ?></span>
                <?php elseif ($ebHasAccess): ?>
                    <a class="jm-ebv-btn" href="/jobmington/ebooks/download.php?slug=<?= e($ebook['slug']) ?>">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                        <?= $ebook['is_free'] ? 'Download free' : 'Download' ?>
                    </a>
                    <?php if (!$ebook['is_free']): ?><span class="jm-ebv-price" style="color:#0a6454;">Unlocked</span><?php endif; ?>
                <?php else: /* premium, logged in, not yet purchased */ ?>
                    <form method="post" style="display:inline;">
                        <?= csrf_field() ?><input type="hidden" name="method" value="seeds">
                        <button type="submit" name="unlock_ebook" class="jm-ebv-btn" <?= $ebWallet['seeds'] < $ebPrices['seeds'] ? 'disabled style="opacity:.55;cursor:not-allowed;"' : '' ?>>
                            Unlock with <?= number_format($ebPrices['seeds']) ?> Seeds
                        </button>
                    </form>
                    <form method="post" style="display:inline;">
                        <?= csrf_field() ?><input type="hidden" name="method" value="credits">
                        <button type="submit" name="unlock_ebook" class="jm-ebv-btn" style="background:#0a6454;" <?= $ebWallet['credits'] < $ebPrices['credits'] ? 'disabled style="background:#0a6454;opacity:.55;cursor:not-allowed;"' : '' ?>>
                            or <?= number_format($ebPrices['credits']) ?> Credit<?= $ebPrices['credits'] > 1 ? 's' : '' ?>
                        </button>
                    </form>
                    <span class="jm-ebv-price">₦<?= number_format((float)$ebook['price']) ?></span>
                <?php endif; ?>
            </div>
            <?php if (Session::isLoggedIn() && !$ebHasAccess && !$ebook['is_free']): ?>
            <p style="font-size:12.5px;color:#7c8aa0;margin-top:10px;">Your balance: <?= number_format($ebWallet['seeds']) ?> Seeds &middot; <?= number_format($ebWallet['credits']) ?> Credits &middot; <a href="/jobmington/wallet/" style="color:#0640a3;">Top up or convert</a></p>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/ai-footer.php'; ?>
