<?php
/**
 * JOBMINGTON - Admin: Blog management (CRUD + cover upload)
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

// Ensure at least one category exists.
if ((int) $pdo->query("SELECT COUNT(*) FROM blog_categories")->fetchColumn() === 0) {
    $pdo->exec("INSERT INTO blog_categories (name, slug) VALUES ('General', 'general')");
}

function jm_blog_slug(PDO $pdo, string $title, int $ignoreId = 0): string {
    $base = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $title), '-')) ?: 'post';
    $slug = $base;
    for ($i = 2; $i < 60; $i++) {
        $stmt = $pdo->prepare("SELECT post_id FROM blog_posts WHERE slug = ? AND post_id <> ? LIMIT 1");
        $stmt->execute([$slug, $ignoreId]);
        if (!$stmt->fetchColumn()) break;
        $slug = $base . '-' . $i;
    }
    return $slug;
}

function jm_blog_upload(): ?string {
    if (empty($_FILES['featured_image']['name']) || $_FILES['featured_image']['error'] !== UPLOAD_ERR_OK) return null;
    $ext = strtolower(pathinfo($_FILES['featured_image']['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg','jpeg','png','webp'], true)) return null;
    $dir = dirname(__DIR__) . '/uploads/blog-images';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $name = bin2hex(random_bytes(8)) . '.' . $ext;
    if (move_uploaded_file($_FILES['featured_image']['tmp_name'], $dir . '/' . $name)) {
        return '/jobmington/uploads/blog-images/' . $name;
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
                $id          = (int) ($_POST['post_id'] ?? 0);
                $title       = trim($_POST['title'] ?? '');
                $categoryId  = (int) ($_POST['category_id'] ?? 0);
                $excerpt     = trim($_POST['excerpt'] ?? '');
                $content     = (string) ($_POST['content'] ?? '');
                $isPublished = isset($_POST['is_published']) ? 1 : 0;

                if ($categoryId <= 0) {
                    $categoryId = (int) $pdo->query("SELECT id FROM blog_categories ORDER BY id LIMIT 1")->fetchColumn();
                }
                if ($title === '' || trim($content) === '') {
                    $err = 'Title and content are required.';
                } else {
                    $cover = jm_blog_upload();
                    if ($id > 0) {
                        $slug = jm_blog_slug($pdo, $title, $id);
                        $sql = "UPDATE blog_posts SET category_id=?, title=?, slug=?, excerpt=?, content=?, is_published=?, published_at=COALESCE(published_at, CASE WHEN ?=1 THEN NOW() ELSE NULL END)"
                            . ($cover ? ", featured_image=?" : "") . " WHERE post_id=?";
                        $params = [$categoryId,$title,$slug,$excerpt,$content,$isPublished,$isPublished];
                        if ($cover) $params[] = $cover;
                        $params[] = $id;
                        $pdo->prepare($sql)->execute($params);
                        $msg = 'Post updated.';
                    } else {
                        $slug = jm_blog_slug($pdo, $title);
                        $pdo->prepare("INSERT INTO blog_posts (category_id, author_id, title, slug, excerpt, content, featured_image, is_published, published_at) VALUES (?,?,?,?,?,?,?,?,?)")
                            ->execute([$categoryId, (int) Session::userId(), $title, $slug, $excerpt, $content, $cover, $isPublished, $isPublished ? date('Y-m-d H:i:s') : null]);
                        $msg = 'Post created.';
                    }
                }
            } elseif ($action === 'delete') {
                $pdo->prepare("DELETE FROM blog_posts WHERE post_id = ?")->execute([(int) $_POST['post_id']]);
                $msg = 'Post deleted.';
            } elseif ($action === 'toggle') {
                $pdo->prepare("UPDATE blog_posts SET is_published = 1 - is_published, published_at = COALESCE(published_at, NOW()) WHERE post_id = ?")->execute([(int) $_POST['post_id']]);
                $msg = 'Visibility updated.';
            } elseif ($action === 'add_category') {
                $cn = trim($_POST['cat_name'] ?? '');
                if ($cn !== '') {
                    $cslug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $cn), '-'));
                    $pdo->prepare("INSERT IGNORE INTO blog_categories (name, slug) VALUES (?, ?)")->execute([$cn, $cslug]);
                    $msg = 'Category added.';
                }
            }
        } catch (Throwable $e) {
            $err = 'Error: ' . $e->getMessage();
        }
    }
    Security::regenerateCSRF();
}

$categories = $pdo->query("SELECT id, name FROM blog_categories ORDER BY name")->fetchAll();

$editing = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM blog_posts WHERE post_id = ?");
    $stmt->execute([(int) $_GET['edit']]);
    $editing = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

$posts = $pdo->query("
    SELECT bp.*, bc.name AS cat_name, u.full_name
    FROM blog_posts bp
    LEFT JOIN blog_categories bc ON bp.category_id = bc.id
    LEFT JOIN users u ON bp.author_id = u.user_id
    ORDER BY bp.created_at DESC
")->fetchAll();

$pageTitle = 'Blog - ' . SITE_NAME;
require_once __DIR__ . '/../includes/header.php';
?>
<style>
.jm-ad-wrap { display:grid; grid-template-columns: 420px 1fr; gap:20px; align-items:start; }
@media (max-width:1100px){ .jm-ad-wrap { grid-template-columns:1fr; } }
.jm-ad-card { background:#fff; border:1px solid #e4eaf3; border-radius:12px; padding:20px; }
.jm-ad-card h2 { margin:0 0 14px; font-size:15px; font-weight:800; color:#0b1b33; }
.jm-ad-field { margin-bottom:12px; }
.jm-ad-field label { display:block; font-size:12px; font-weight:700; color:#5b6b82; margin-bottom:5px; }
.jm-ad-field input, .jm-ad-field textarea, .jm-ad-field select { width:100%; box-sizing:border-box; border:1px solid #d8e4f4; border-radius:8px; padding:9px 11px; font:inherit; font-size:13px; background:#fbfdff; }
.jm-ad-field textarea.body { min-height:220px; resize:vertical; line-height:1.6; }
.jm-ad-field textarea { min-height:60px; resize:vertical; }
.jm-ad-checks { display:flex; gap:14px; flex-wrap:wrap; margin:6px 0 14px; }
.jm-ad-checks label { display:flex; align-items:center; gap:6px; font-size:13px; font-weight:600; color:#0b1b33; }
.jm-ad-table { width:100%; border-collapse:collapse; background:#fff; border:1px solid #e4eaf3; border-radius:12px; overflow:hidden; }
.jm-ad-table th { text-align:left; font-size:11px; text-transform:uppercase; letter-spacing:.06em; color:#5b6b82; padding:11px 14px; background:#fafbfd; border-bottom:1px solid #e4eaf3; }
.jm-ad-table td { padding:11px 14px; border-bottom:1px solid #f0f4f9; font-size:13px; color:#0b1b33; vertical-align:middle; }
.jm-ad-thumb { width:54px; height:32px; border-radius:5px; object-fit:cover; background:#eef5ff; }
.jm-ad-pill { font-size:10px; font-weight:800; text-transform:uppercase; padding:2px 8px; border-radius:99px; }
.jm-ad-pill.on { background:#e6f5f1; color:#0a6454; } .jm-ad-pill.off { background:#f0f3f8; color:#475569; }
.jm-ad-actions { display:flex; gap:6px; flex-wrap:wrap; }
.jm-ad-actions button, .jm-ad-actions a { font-size:11px; font-weight:700; padding:5px 9px; border-radius:6px; border:1px solid #d8e4f4; background:#fff; color:#0640a3; cursor:pointer; text-decoration:none; }
.jm-ad-msg { padding:11px 14px; border-radius:8px; margin-bottom:16px; font-size:13px; font-weight:600; }
.jm-ad-msg.ok { background:#e6f5f1; color:#0a6454; } .jm-ad-msg.err { background:#fdecea; color:#991b1b; }
.jm-ad-btn { background:#0640a3; color:#fff; border:0; border-radius:8px; padding:10px 16px; font:inherit; font-size:13px; font-weight:700; cursor:pointer; width:100%; }
.jm-ad-catrow { display:flex; gap:8px; margin-top:8px; }
.jm-ad-catrow input { flex:1; }
.jm-ad-catrow button { background:#eef5ff; color:#0640a3; border:1px solid #d8e4f4; border-radius:8px; padding:0 14px; font-weight:700; cursor:pointer; }
.jm-rte-toolbar { display:flex; flex-wrap:wrap; gap:4px; padding:7px; border:1px solid #d8e4f4; border-bottom:0; border-radius:8px 8px 0 0; background:#f7faff; }
.jm-rte-toolbar button { border:1px solid transparent; background:transparent; border-radius:6px; padding:5px 9px; font-size:12.5px; font-weight:700; color:#0b1b33; cursor:pointer; min-width:30px; }
.jm-rte-toolbar button:hover { background:#fff; border-color:#d8e4f4; }
.jm-rte { min-height:240px; border:1px solid #d8e4f4; border-radius:0 0 8px 8px; padding:14px 16px; background:#fff; font-size:14px; line-height:1.7; color:#1f2d3d; outline:none; overflow-y:auto; max-height:480px; }
.jm-rte:focus { border-color:#0640a3; box-shadow:0 0 0 3px rgba(6,64,163,.08); }
.jm-rte h2 { font-size:20px; font-weight:800; margin:.6em 0 .3em; }
.jm-rte h3 { font-size:17px; font-weight:800; margin:.6em 0 .3em; }
.jm-rte p { margin:0 0 .8em; }
.jm-rte ul, .jm-rte ol { margin:0 0 .8em; padding-left:1.4em; }
.jm-rte blockquote { border-left:3px solid #c8d8ef; margin:0 0 .8em; padding:2px 0 2px 14px; color:#53667f; }
.jm-rte img { max-width:100%; border-radius:8px; }
.jm-rte a { color:#0640a3; }
</style>

<div class="ja-pagehead"><div><h1>Blog</h1><p>Write and manage blog posts.</p></div>
<a class="ja-statuschip" href="/jobmington/blog/" target="_blank">View blog</a></div>

<?php if ($msg): ?><div class="jm-ad-msg ok"><?= e($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="jm-ad-msg err"><?= e($err) ?></div><?php endif; ?>

<div class="jm-ad-wrap">
    <form class="jm-ad-card" method="post" enctype="multipart/form-data">
        <h2><?= $editing ? 'Edit post' : 'New post' ?></h2>
        <?= Security::csrfField() ?>
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="post_id" value="<?= (int) ($editing['post_id'] ?? 0) ?>">
        <div class="jm-ad-field"><label>Title *</label><input type="text" name="title" value="<?= e($editing['title'] ?? '') ?>" required></div>
        <div class="jm-ad-field"><label>Category</label><select name="category_id">
            <?php foreach ($categories as $cat): ?>
                <option value="<?= (int)$cat['id'] ?>" <?= ($editing['category_id'] ?? 0) == $cat['id'] ? 'selected' : '' ?>><?= e($cat['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <div class="jm-ad-catrow">
            <input type="text" name="cat_name" placeholder="New category…" form="addcat">
            <button type="submit" form="addcat">Add</button>
        </div>
        </div>
        <div class="jm-ad-field"><label>Excerpt</label><textarea name="excerpt"><?= e($editing['excerpt'] ?? '') ?></textarea></div>
        <div class="jm-ad-field"><label>Content *</label>
            <div class="jm-rte-toolbar" aria-label="Formatting">
                <button type="button" data-cmd="bold" title="Bold"><b>B</b></button>
                <button type="button" data-cmd="italic" title="Italic"><i>I</i></button>
                <button type="button" data-cmd="formatBlock" data-val="h2" title="Heading">H2</button>
                <button type="button" data-cmd="formatBlock" data-val="h3" title="Subheading">H3</button>
                <button type="button" data-cmd="formatBlock" data-val="p" title="Paragraph">P</button>
                <button type="button" data-cmd="insertUnorderedList" title="Bulleted list">&bull; List</button>
                <button type="button" data-cmd="insertOrderedList" title="Numbered list">1. List</button>
                <button type="button" data-cmd="formatBlock" data-val="blockquote" title="Quote">&ldquo; Quote</button>
                <button type="button" data-act="link" title="Insert link">&#128279; Link</button>
                <button type="button" data-act="image" title="Insert image by URL">&#128247; Image</button>
            </div>
            <div id="jm-rte" class="jm-rte" contenteditable="true"><?= $editing['content'] ?? '' ?></div>
            <textarea name="content" id="jm-rte-input" style="display:none;"><?= e($editing['content'] ?? '') ?></textarea>
        </div>
        <div class="jm-ad-field"><label>Cover image (jpg/png, 16:9)</label><input type="file" name="featured_image" accept="image/*">
            <?php if (!empty($editing['featured_image'])): ?><div style="margin-top:6px;"><img src="<?= e($editing['featured_image']) ?>" class="jm-ad-thumb"></div><?php endif; ?>
        </div>
        <div class="jm-ad-checks">
            <label><input type="checkbox" name="is_published" <?= (!$editing || $editing['is_published']) ? 'checked' : '' ?>> Published</label>
        </div>
        <button class="jm-ad-btn" type="submit"><?= $editing ? 'Save changes' : 'Publish post' ?></button>
        <?php if ($editing): ?><div style="text-align:center;margin-top:10px;"><a href="/jobmington/admin/blog.php" style="font-size:12px;color:#5b6b82;">Cancel edit</a></div><?php endif; ?>
    </form>
    <!-- separate form for add-category so it doesn't submit the post -->
    <form id="addcat" method="post" style="display:none;"><?= Security::csrfField() ?><input type="hidden" name="action" value="add_category"></form>

    <div>
        <div class="jm-tablewrap"><table class="jm-ad-table">
            <thead><tr><th></th><th>Title</th><th>Category</th><th>Views</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php if (empty($posts)): ?>
                <tr><td colspan="6" style="text-align:center;color:#94a3b8;padding:28px;">No posts yet. Write your first one.</td></tr>
            <?php else: foreach ($posts as $p): ?>
                <tr>
                    <td><?php if (!empty($p['featured_image'])): ?><img src="<?= e($p['featured_image']) ?>" class="jm-ad-thumb"><?php else: ?><div class="jm-ad-thumb"></div><?php endif; ?></td>
                    <td><strong><?= e($p['title']) ?></strong><div style="font-size:11px;color:#94a3b8;"><?= e($p['full_name'] ?: 'Admin') ?></div></td>
                    <td><?= e($p['cat_name'] ?: '—') ?></td>
                    <td><?= (int)$p['views'] ?></td>
                    <td><span class="jm-ad-pill <?= $p['is_published'] ? 'on' : 'off' ?>"><?= $p['is_published'] ? 'Live' : 'Draft' ?></span></td>
                    <td>
                        <div class="jm-ad-actions">
                            <a href="/jobmington/admin/blog.php?edit=<?= (int)$p['post_id'] ?>">Edit</a>
                            <form method="post" style="display:inline;"><?= Security::csrfField() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="post_id" value="<?= (int)$p['post_id'] ?>"><button type="submit"><?= $p['is_published'] ? 'Unpublish' : 'Publish' ?></button></form>
                            <form method="post" onsubmit="return confirm('Delete this post?');" style="display:inline;"><?= Security::csrfField() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="post_id" value="<?= (int)$p['post_id'] ?>"><button type="submit" style="color:#b42318;">Delete</button></form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table></div>
    </div>
</div>

<script>
(function () {
    var rte = document.getElementById('jm-rte');
    var input = document.getElementById('jm-rte-input');
    if (!rte || !input) return;
    function sync() { input.value = rte.innerHTML; }
    document.querySelectorAll('.jm-rte-toolbar button').forEach(function (b) {
        b.addEventListener('click', function () {
            rte.focus();
            if (b.dataset.cmd) {
                document.execCommand(b.dataset.cmd, false, b.dataset.val || null);
            } else if (b.dataset.act === 'link') {
                var u = prompt('Link URL:', 'https://');
                if (u) document.execCommand('createLink', false, u);
            } else if (b.dataset.act === 'image') {
                var img = prompt('Image URL:', 'https://');
                if (img) document.execCommand('insertImage', false, img);
            }
            sync();
        });
    });
    rte.addEventListener('input', sync);
    rte.addEventListener('blur', sync);
    var form = rte.closest('form');
    if (form) form.addEventListener('submit', sync);
    sync();
})();
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
