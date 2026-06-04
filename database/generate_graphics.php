<?php
/**
 * JOBMINGTON - Generate + link cover graphics for courses, events, and blog posts.
 *
 * Where GD is available it generates a branded 16:9 banner; everywhere it links
 * any banner file that already exists on disk to the row (so it also works on
 * servers without php-gd, using committed images). Idempotent.
 *     php database/generate_graphics.php
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only\n"); }

define('JOBMINGTON', true);
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/seed_ebook_lib.php';

$pdo  = db();
$root = dirname(__DIR__);
$font = is_file($root . '/assets/fonts/FuturaCyrillicDemi.ttf') ? $root . '/assets/fonts/FuturaCyrillicDemi.ttf' : null;
$hasGd = extension_loaded('gd');

/** Generate (if possible) and link a banner. Returns 'made' | 'linked' | 'skip'. */
function gg_handle(PDO $pdo, string $subdir, string $slug, string $title, string $kicker, string $table, string $idCol, int $id, string $imgCol, string $root, ?string $font, bool $hasGd): string {
    $dir = $root . '/uploads/' . $subdir;
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $rel  = 'uploads/' . $subdir . '/' . $slug . '.png';
    $disk = $root . '/' . $rel;

    $made = false;
    if (!is_file($disk) && $hasGd) {
        $made = sc_make_banner($disk, $title, $kicker, $font);
    }
    if (is_file($disk)) {
        $pdo->prepare("UPDATE `$table` SET `$imgCol` = ? WHERE `$idCol` = ?")
            ->execute(['/jobmington/' . $rel, $id]);
        return $made ? 'made' : 'linked';
    }
    return 'skip';
}

$counts = ['made' => 0, 'linked' => 0, 'skip' => 0];

// Courses (thumbnail).
$rows = $pdo->query("SELECT c.course_id, c.slug, c.title, cc.name AS cat
                     FROM courses c LEFT JOIN course_categories cc ON c.category_id = cc.id
                     WHERE c.thumbnail IS NULL OR c.thumbnail = ''")->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    $res = gg_handle($pdo, 'course-thumbnails', $r['slug'], $r['title'], $r['cat'] ?: 'Course', 'courses', 'course_id', (int)$r['course_id'], 'thumbnail', $root, $font, $hasGd);
    $counts[$res]++;
}

// Events (cover_image).
$rows = $pdo->query("SELECT event_id, slug, title, event_type FROM events WHERE cover_image IS NULL OR cover_image = ''")->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    $res = gg_handle($pdo, 'events', $r['slug'], $r['title'], ucfirst($r['event_type']), 'events', 'event_id', (int)$r['event_id'], 'cover_image', $root, $font, $hasGd);
    $counts[$res]++;
}

// Blog posts (featured_image).
$rows = $pdo->query("SELECT bp.post_id, bp.slug, bp.title, bc.name AS cat
                     FROM blog_posts bp LEFT JOIN blog_categories bc ON bp.category_id = bc.id
                     WHERE bp.featured_image IS NULL OR bp.featured_image = ''")->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    $res = gg_handle($pdo, 'blog-images', $r['slug'], $r['title'], $r['cat'] ?: 'Blog', 'blog_posts', 'post_id', (int)$r['post_id'], 'featured_image', $root, $font, $hasGd);
    $counts[$res]++;
}

echo "GD available: " . ($hasGd ? 'yes' : 'no') . "\n";
echo "  Generated: {$counts['made']}\n";
echo "  Linked existing: {$counts['linked']}\n";
echo "  Skipped (no file): {$counts['skip']}\n";
