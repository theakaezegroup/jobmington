<?php
/**
 * JOBMINGTON - Dynamic XML sitemap.
 *
 * The static sitemap.xml listed 10 URLs and no jobs, so the ~30k job pages --
 * effectively the whole indexable site -- were never announced to search
 * engines. Generated here instead so it cannot go stale.
 *
 *   /sitemap.php          sitemap index
 *   /sitemap.php?p=pages  static pages and content sections
 *   /sitemap.php?p=N      job pages, CHUNK per file
 */
define('JOBMINGTON', true);
require_once __DIR__ . '/config/env.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/config/database.php';

const SITEMAP_CHUNK = 5000;   // well inside the 50,000-URL limit per file

header('Content-Type: application/xml; charset=utf-8');
header('X-Robots-Tag: noindex');

$base = rtrim(SITE_URL, '/');
$pdo  = null;
try { $pdo = db(); } catch (Throwable $e) { $pdo = null; }

$url = function (string $loc, ?string $lastmod = null, string $freq = 'weekly', string $prio = '0.5'): void {
    echo "  <url>\n    <loc>" . htmlspecialchars($loc, ENT_XML1) . "</loc>\n";
    if ($lastmod) { echo "    <lastmod>" . htmlspecialchars($lastmod, ENT_XML1) . "</lastmod>\n"; }
    echo "    <changefreq>{$freq}</changefreq>\n    <priority>{$prio}</priority>\n  </url>\n";
};

$part = isset($_GET['p']) ? (string) $_GET['p'] : '';

/* ── Job pages, one chunk per request ─────────────────────────────── */
if ($part !== '' && ctype_digit($part)) {
    $offset = ((int) $part - 1) * SITEMAP_CHUNK;
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    if ($pdo) {
        try {
            $stmt = $pdo->prepare("
                SELECT job_id, posted_at
                FROM jobs
                WHERE is_active = 1 AND (expires_at IS NULL OR expires_at >= CURDATE())
                ORDER BY job_id
                LIMIT " . SITEMAP_CHUNK . " OFFSET " . (int) $offset
            );
            $stmt->execute();
            while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $url($base . '/jobs/view.php?id=' . (int) $r['job_id'],
                     $r['posted_at'] ? date('Y-m-d', strtotime($r['posted_at'])) : null,
                     'daily', '0.8');
            }
        } catch (Throwable $e) { /* an empty chunk beats a broken sitemap */ }
    }
    echo '</urlset>';
    exit;
}

/* ── Static pages and content sections ────────────────────────────── */
if ($part === 'pages') {
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

    foreach ([['/', 'daily', '1.0'], ['/jobs/', 'daily', '0.9'], ['/employer/', 'weekly', '0.7'],
              ['/pricing', 'monthly', '0.6'], ['/blog/', 'daily', '0.7'], ['/events/', 'weekly', '0.6'],
              ['/ebooks/', 'weekly', '0.6'], ['/community/', 'daily', '0.6'], ['/tools/', 'weekly', '0.6'],
              ['/contact', 'yearly', '0.3'], ['/faq', 'yearly', '0.3'],
              ['/privacy-policy', 'yearly', '0.2'], ['/terms-of-service', 'yearly', '0.2']] as $p) {
        $url($base . $p[0], null, $p[1], $p[2]);
    }

    if ($pdo) {
        // Published content that is publicly readable.
        $sets = [
            ["SELECT slug, updated_at FROM blog_posts WHERE is_published = 1", '/blog/post.php?slug=', 'weekly', '0.7'],
            ["SELECT slug, updated_at FROM events WHERE is_published = 1",     '/events/view.php?slug=', 'weekly', '0.6'],
        ];
        foreach ($sets as [$sql, $prefix, $freq, $prio]) {
            try {
                foreach ($pdo->query($sql) as $r) {
                    $url($base . $prefix . rawurlencode((string) $r['slug']),
                         !empty($r['updated_at']) ? date('Y-m-d', strtotime($r['updated_at'])) : null,
                         $freq, $prio);
                }
            } catch (Throwable $e) { /* skip a table that is absent or renamed */ }
        }
    }
    echo '</urlset>';
    exit;
}

/* ── Index ────────────────────────────────────────────────────────── */
$jobCount = 0;
if ($pdo) {
    try {
        $jobCount = (int) $pdo->query("
            SELECT COUNT(*) FROM jobs
            WHERE is_active = 1 AND (expires_at IS NULL OR expires_at >= CURDATE())
        ")->fetchColumn();
    } catch (Throwable $e) { $jobCount = 0; }
}
$chunks = (int) ceil($jobCount / SITEMAP_CHUNK);
$today  = date('Y-m-d');

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
echo "  <sitemap>\n    <loc>{$base}/sitemap.php?p=pages</loc>\n    <lastmod>{$today}</lastmod>\n  </sitemap>\n";
for ($i = 1; $i <= $chunks; $i++) {
    echo "  <sitemap>\n    <loc>{$base}/sitemap.php?p={$i}</loc>\n    <lastmod>{$today}</lastmod>\n  </sitemap>\n";
}
echo '</sitemapindex>';
