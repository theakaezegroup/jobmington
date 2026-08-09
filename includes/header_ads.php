<?php
/**
 * JOBMINGTON - Sponsor strip above the site header.
 *
 * Renders at most one banner: the highest-priority active ad whose schedule
 * covers now. Image and link only -- nothing here is echoed as markup, so a
 * banner cannot inject code into every page.
 */
if (!defined('JOBMINGTON')) { exit; }

/** The banner that should show right now, or null. */
function jm_header_ad_current(PDO $pdo): ?array {
    static $cached = false, $ad = null;
    if ($cached) { return $ad; }
    $cached = true;

    try {
        $stmt = $pdo->query("
            SELECT ad_id, name, image_path, image_alt, click_url, bg_color
            FROM header_ads
            WHERE is_active = 1
              AND (starts_at IS NULL OR starts_at <= NOW())
              AND (ends_at   IS NULL OR ends_at   >= NOW())
            ORDER BY priority DESC, updated_at DESC
            LIMIT 1
        ");
        $ad = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Throwable $e) {
        // A missing table or a database hiccup must never take the header down.
        $ad = null;
    }
    return $ad;
}

/**
 * Print the strip. Safe to call on any page; prints nothing when no ad is live.
 * Styles are inlined once so this works on every header variant without
 * touching the shared stylesheet.
 */
function jm_header_ad_bar(?PDO $pdo = null): void {
    static $printed = false;
    if ($printed) { return; }

    if ($pdo === null) {
        if (!function_exists('db')) { return; }
        try { $pdo = db(); } catch (Throwable $e) { return; }
    }

    $ad = jm_header_ad_current($pdo);
    if (!$ad) { return; }
    $printed = true;

    // Count the view. Failure here must not stop the page rendering.
    try {
        $pdo->prepare("UPDATE header_ads SET impressions = impressions + 1 WHERE ad_id = ?")
            ->execute([(int) $ad['ad_id']]);
    } catch (Throwable $e) { /* not worth interrupting a page view */ }

    $src  = $ad['image_path'];
    $alt  = (string) ($ad['image_alt'] ?: $ad['name']);
    $bg   = preg_match('/^#[0-9a-f]{3,8}$/i', (string) $ad['bg_color']) ? $ad['bg_color'] : '#f4f8ff';
    $href = !empty($ad['click_url']) ? '/jobmington/go/ad.php?id=' . (int) $ad['ad_id'] : '';
    ?>
    <style>
        .jm-adbar { position: relative; width: 100%; display: flex; align-items: center; justify-content: center; padding: 8px 44px 8px 16px; border-bottom: 1px solid #e4eaf3; box-sizing: border-box; }
        .jm-adbar img { display: block; max-width: 100%; height: auto; max-height: 90px; }
        .jm-adbar a { display: block; line-height: 0; }
        .jm-adbar-tag { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); font-size: 9px; font-weight: 800; letter-spacing: .09em; text-transform: uppercase; color: #94a3b8; }
        .jm-adbar-x { position: absolute; right: 10px; top: 50%; transform: translateY(-50%); width: 24px; height: 24px; display: grid; place-items: center; border: 0; background: transparent; color: #94a3b8; cursor: pointer; border-radius: 6px; padding: 0; }
        .jm-adbar-x:hover { background: rgba(6,20,38,.06); color: #0b1b33; }
        .jm-adbar-x svg { width: 13px; height: 13px; }
        @media (max-width: 640px) {
            .jm-adbar { padding: 7px 38px 7px 10px; }
            .jm-adbar-tag { display: none; }
            .jm-adbar img { max-height: 60px; }
        }
    </style>
    <div class="jm-adbar" id="jm-adbar" style="background: <?= e($bg) ?>;" role="complementary" aria-label="Sponsor">
        <span class="jm-adbar-tag">Ad</span>
        <?php if ($href !== ''): ?>
            <a href="<?= e($href) ?>" target="_blank" rel="noopener sponsored"><img src="<?= e($src) ?>" alt="<?= e($alt) ?>"></a>
        <?php else: ?>
            <img src="<?= e($src) ?>" alt="<?= e($alt) ?>">
        <?php endif; ?>
        <button class="jm-adbar-x" type="button" aria-label="Dismiss" onclick="(function(b){b.parentNode.removeChild(b);try{localStorage.setItem('jm_adbar_hidden','<?= (int) $ad['ad_id'] ?>');}catch(e){}})(document.getElementById('jm-adbar'))">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
    </div>
    <script>
        // Honour a previous dismissal of this same banner.
        try {
            if (localStorage.getItem('jm_adbar_hidden') === '<?= (int) $ad['ad_id'] ?>') {
                var b = document.getElementById('jm-adbar');
                if (b) { b.parentNode.removeChild(b); }
            }
        } catch (e) {}
    </script>
    <?php
}
