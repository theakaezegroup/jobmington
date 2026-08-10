<?php
/**
 * JOBMINGTON - Admin: Ebooks library management
 */
define('JOBMINGTON', true);
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';

Session::start();
Session::requireAdmin();
$pdo = db();

$msg = '';
$err = '';

function jm_ebook_slug(PDO $pdo, string $title, int $ignoreId = 0): string {
    $base = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $title), '-')) ?: 'ebook';
    $slug = $base;
    for ($i = 2; $i < 50; $i++) {
        $stmt = $pdo->prepare("SELECT ebook_id FROM ebooks WHERE slug = ? AND ebook_id <> ? LIMIT 1");
        $stmt->execute([$slug, $ignoreId]);
        if (!$stmt->fetchColumn()) break;
        $slug = $base . '-' . $i;
    }
    return $slug;
}

function jm_ebook_upload(string $field, string $subdir, array $allowedExt): ?string {
    if (empty($_FILES[$field]['name']) || $_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
        return null;
    }
    $ext = strtolower(pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt, true)) {
        return null;
    }
    $dir = dirname(__DIR__) . '/uploads/' . $subdir;
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $name = bin2hex(random_bytes(8)) . '.' . $ext;
    if (move_uploaded_file($_FILES[$field]['tmp_name'], $dir . '/' . $name)) {
        return '/jobmington/uploads/' . $subdir . '/' . $name;
    }
    return null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::verifyCSRF()) {
        $err = 'Security check failed. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';
        try {
            if ($action === 'save') {
                $id          = (int) ($_POST['ebook_id'] ?? 0);
                $title       = trim($_POST['title'] ?? '');
                $author      = trim($_POST['author'] ?? '');
                $category    = trim($_POST['category'] ?? '');
                $description = trim($_POST['description'] ?? '');
                $pages       = (int) ($_POST['pages'] ?? 0);
                $isFree      = isset($_POST['is_free']) ? 1 : 0;
                $price       = (float) ($_POST['price'] ?? 0);
                $seedPrice   = (int) ($_POST['seed_price'] ?? 0);
                $isPublished = isset($_POST['is_published']) ? 1 : 0;
                $isFeatured  = isset($_POST['is_featured']) ? 1 : 0;

                if ($title === '') {
                    $err = 'Title is required.';
                } else {
                    $cover = jm_ebook_upload('cover_image', 'ebooks/covers', ['jpg','jpeg','png','webp']);
                    $file  = jm_ebook_upload('file', 'ebooks/files', ['pdf','epub','zip']);

                    if ($id > 0) {
                        $slug = jm_ebook_slug($pdo, $title, $id);
                        $sql = "UPDATE ebooks SET title=?, slug=?, author=?, category=?, description=?, pages=?, is_free=?, price=?, seed_price=?, is_published=?, is_featured=?"
                             . ($cover ? ", cover_image=?" : "")
                             . ($file ? ", file_path=?" : "")
                             . " WHERE ebook_id=?";
                        $params = [$title,$slug,$author,$category,$description,$pages,$isFree,$price,$seedPrice,$isPublished,$isFeatured];
                        if ($cover) $params[] = $cover;
                        if ($file)  $params[] = $file;
                        $params[] = $id;
                        $pdo->prepare($sql)->execute($params);
                        $msg = 'Ebook updated.';
                    } else {
                        $slug = jm_ebook_slug($pdo, $title);
                        $pdo->prepare("INSERT INTO ebooks (title, slug, author, category, description, pages, is_free, price, seed_price, is_published, is_featured, cover_image, file_path) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)")
                            ->execute([$title,$slug,$author,$category,$description,$pages,$isFree,$price,$seedPrice,$isPublished,$isFeatured,$cover,$file]);
                        $msg = 'Ebook created.';
                    }
                }
            } elseif ($action === 'delete') {
                $pdo->prepare("DELETE FROM ebooks WHERE ebook_id = ?")->execute([(int) $_POST['ebook_id']]);
                $msg = 'Ebook deleted.';
            } elseif ($action === 'toggle') {
                $pdo->prepare("UPDATE ebooks SET is_published = 1 - is_published WHERE ebook_id = ?")->execute([(int) $_POST['ebook_id']]);
                $msg = 'Visibility updated.';
            }
        } catch (Throwable $e) {
            $err = 'Error: ' . $e->getMessage();
        }
    }
    Security::regenerateCSRF();
}

$editing = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM ebooks WHERE ebook_id = ?");
    $stmt->execute([(int) $_GET['edit']]);
    $editing = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

$ebooks = $pdo->query("SELECT * FROM ebooks ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
$pageTitle = 'Ebooks - ' . SITE_NAME;
require_once __DIR__ . '/../includes/header.php';
?>
<style>
.jm-eb-wrap { display:grid; grid-template-columns: 360px 1fr; gap:20px; align-items:start; }
@media (max-width:1000px){ .jm-eb-wrap { grid-template-columns:1fr; } }
.jm-eb-card { background:#fff; border:1px solid #e4eaf3; border-radius:12px; padding:20px; }
.jm-eb-card h2 { margin:0 0 14px; font-size:15px; font-weight:800; color:#0b1b33; }
.jm-eb-field { margin-bottom:12px; }
.jm-eb-field label { display:block; font-size:12px; font-weight:700; color:#5b6b82; margin-bottom:5px; }
.jm-eb-field input[type=text], .jm-eb-field input[type=number], .jm-eb-field textarea, .jm-eb-field input[type=file] {
    width:100%; box-sizing:border-box; border:1px solid #d8e4f4; border-radius:8px; padding:9px 11px; font:inherit; font-size:13px; background:#fbfdff;
}
.jm-eb-field textarea { min-height:80px; resize:vertical; }
.jm-eb-checks { display:flex; gap:16px; flex-wrap:wrap; margin:6px 0 14px; }
.jm-eb-checks label { display:flex; align-items:center; gap:6px; font-size:13px; font-weight:600; color:#0b1b33; }
.jm-eb-row2 { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
@media (max-width:560px){ .jm-eb-row2 { grid-template-columns:1fr; } }
.jm-eb-table { width:100%; border-collapse:collapse; background:#fff; border:1px solid #e4eaf3; border-radius:12px; overflow:hidden; }
.jm-eb-table th { text-align:left; font-size:11px; text-transform:uppercase; letter-spacing:.06em; color:#5b6b82; padding:11px 14px; background:#fafbfd; border-bottom:1px solid #e4eaf3; }
.jm-eb-table td { padding:11px 14px; border-bottom:1px solid #f0f4f9; font-size:13px; color:#0b1b33; vertical-align:middle; }
.jm-eb-pill { font-size:10px; font-weight:800; text-transform:uppercase; padding:2px 8px; border-radius:99px; }
.jm-eb-pill.on { background:#e6f5f1; color:#0a6454; } .jm-eb-pill.off { background:#f0f3f8; color:#475569; }
.jm-eb-actions { display:flex; gap:6px; flex-wrap:wrap; }
.jm-eb-actions button, .jm-eb-actions a { font-size:11px; font-weight:700; padding:5px 9px; border-radius:6px; border:1px solid #d8e4f4; background:#fff; color:#0640a3; cursor:pointer; text-decoration:none; }
.jm-eb-msg { padding:11px 14px; border-radius:8px; margin-bottom:16px; font-size:13px; font-weight:600; }
.jm-eb-msg.ok { background:#e6f5f1; color:#0a6454; } .jm-eb-msg.err { background:#fdecea; color:#991b1b; }
.jm-eb-btn { background:#0640a3; color:#fff; border:0; border-radius:8px; padding:10px 16px; font:inherit; font-size:13px; font-weight:700; cursor:pointer; width:100%; }
</style>

<div class="ja-pagehead"><div><h1>Ebooks</h1><p>Manage the downloadable ebook library.</p></div>
<a class="ja-statuschip" href="/jobmington/ebooks/" target="_blank">View public page</a></div>

<?php if ($msg): ?><div class="jm-eb-msg ok"><?= e($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="jm-eb-msg err"><?= e($err) ?></div><?php endif; ?>

<div class="jm-eb-wrap">
    <form class="jm-eb-card" method="post" enctype="multipart/form-data">
        <h2><?= $editing ? 'Edit ebook' : 'Add ebook' ?></h2>
        <?= Security::csrfField() ?>
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="ebook_id" value="<?= (int) ($editing['ebook_id'] ?? 0) ?>">
        <div class="jm-eb-field"><label>Title *</label><input type="text" name="title" value="<?= e($editing['title'] ?? '') ?>" required></div>
        <div class="jm-eb-row2">
            <div class="jm-eb-field"><label>Author</label><input type="text" name="author" value="<?= e($editing['author'] ?? '') ?>"></div>
            <div class="jm-eb-field"><label>Category</label><input type="text" name="category" value="<?= e($editing['category'] ?? '') ?>"></div>
        </div>
        <div class="jm-eb-field"><label>Description</label><textarea name="description"><?= e($editing['description'] ?? '') ?></textarea></div>
        <div class="jm-eb-row2">
            <div class="jm-eb-field"><label>Pages</label><input type="number" name="pages" value="<?= (int) ($editing['pages'] ?? 0) ?>"></div>
            <div class="jm-eb-field"><label>Price (₦)</label><input type="number" step="0.01" name="price" value="<?= e($editing['price'] ?? '0') ?>"></div>
        </div>
        <div class="jm-eb-field"><label>Seed price</label><input type="number" name="seed_price" value="<?= (int) ($editing['seed_price'] ?? 0) ?>"></div>
        <div class="jm-eb-field"><label>Cover image (jpg/png)</label><input type="file" name="cover_image" accept="image/*"></div>
        <div class="jm-eb-field"><label>Ebook file (pdf/epub/zip)</label><input type="file" name="file" accept=".pdf,.epub,.zip"></div>
        <div class="jm-eb-checks">
            <label><input type="checkbox" name="is_free" <?= (!$editing || $editing['is_free']) ? 'checked' : '' ?>> Free</label>
            <label><input type="checkbox" name="is_published" <?= (!$editing || $editing['is_published']) ? 'checked' : '' ?>> Published</label>
            <label><input type="checkbox" name="is_featured" <?= ($editing && $editing['is_featured']) ? 'checked' : '' ?>> Featured</label>
        </div>
        <button class="jm-eb-btn" type="submit"><?= $editing ? 'Save changes' : 'Add ebook' ?></button>
        <?php if ($editing): ?><div style="text-align:center;margin-top:10px;"><a href="/jobmington/admin/ebooks.php" style="font-size:12px;color:#5b6b82;">Cancel edit</a></div><?php endif; ?>
    </form>

    <div>
        <div class="jm-tablewrap"><table class="jm-eb-table">
            <thead><tr><th>Title</th><th>Category</th><th>Price</th><th>Downloads</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php if (empty($ebooks)): ?>
                <tr><td colspan="6" style="text-align:center;color:#94a3b8;padding:28px;">No ebooks yet. Add your first one.</td></tr>
            <?php else: foreach ($ebooks as $b): ?>
                <tr>
                    <td><strong><?= e($b['title']) ?></strong><?php if ($b['author']): ?><div style="font-size:11px;color:#94a3b8;"><?= e($b['author']) ?></div><?php endif; ?></td>
                    <td><?= e($b['category'] ?: '—') ?></td>
                    <td><?= $b['is_free'] ? 'Free' : '₦' . number_format((float)$b['price']) ?></td>
                    <td><?= (int) $b['download_count'] ?></td>
                    <td><span class="jm-eb-pill <?= $b['is_published'] ? 'on' : 'off' ?>"><?= $b['is_published'] ? 'Live' : 'Hidden' ?></span></td>
                    <td>
                        <div class="jm-eb-actions">
                            <a href="/jobmington/admin/ebooks.php?edit=<?= (int)$b['ebook_id'] ?>">Edit</a>
                            <form method="post" onsubmit="return true;" style="display:inline;"><?= Security::csrfField() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="ebook_id" value="<?= (int)$b['ebook_id'] ?>"><button type="submit"><?= $b['is_published'] ? 'Hide' : 'Show' ?></button></form>
                            <form method="post" onsubmit="return confirm('Delete this ebook?');" style="display:inline;"><?= Security::csrfField() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="ebook_id" value="<?= (int)$b['ebook_id'] ?>"><button type="submit" style="color:#b42318;">Delete</button></form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table></div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
