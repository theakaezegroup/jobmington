<?php
/**
 * JOBMINGTON - Admin: Courses management (CRUD + thumbnail upload)
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

function jm_course_slug(PDO $pdo, string $title, int $ignoreId = 0): string {
    $base = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $title), '-')) ?: 'course';
    $slug = $base;
    for ($i = 2; $i < 60; $i++) {
        $stmt = $pdo->prepare("SELECT course_id FROM courses WHERE slug = ? AND course_id <> ? LIMIT 1");
        $stmt->execute([$slug, $ignoreId]);
        if (!$stmt->fetchColumn()) break;
        $slug = $base . '-' . $i;
    }
    return $slug;
}

function jm_course_upload(): ?string {
    if (empty($_FILES['thumbnail']['name']) || $_FILES['thumbnail']['error'] !== UPLOAD_ERR_OK) return null;
    $ext = strtolower(pathinfo($_FILES['thumbnail']['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg','jpeg','png','webp'], true)) return null;
    $dir = dirname(__DIR__) . '/uploads/course-thumbnails';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $name = bin2hex(random_bytes(8)) . '.' . $ext;
    if (move_uploaded_file($_FILES['thumbnail']['tmp_name'], $dir . '/' . $name)) {
        return '/jobmington/uploads/course-thumbnails/' . $name;
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
                $id          = (int) ($_POST['course_id'] ?? 0);
                $title       = trim($_POST['title'] ?? '');
                $categoryId  = (int) ($_POST['category_id'] ?? 0) ?: null;
                $short       = trim($_POST['short_description'] ?? '');
                $desc        = trim($_POST['description'] ?? '');
                $instructor  = trim($_POST['instructor_name'] ?? '');
                $instrBio    = trim($_POST['instructor_bio'] ?? '');
                $difficulty  = in_array($_POST['difficulty'] ?? '', ['beginner','intermediate','advanced'], true) ? $_POST['difficulty'] : 'beginner';
                $type        = in_array($_POST['course_type'] ?? '', ['video','article','certification','full-course','guide'], true) ? $_POST['course_type'] : 'full-course';
                $hours       = (float) ($_POST['duration_hours'] ?? 0);
                $isExternal  = isset($_POST['is_external']) ? 1 : 0;
                $externalUrl = trim($_POST['external_url'] ?? '');
                $isFree      = isset($_POST['is_free']) ? 1 : 0;
                $price       = (float) ($_POST['price'] ?? 0);
                $seedPrice   = (int) ($_POST['seed_price'] ?? 0);
                $hasCert     = isset($_POST['has_certificate']) ? 1 : 0;
                $certProv    = trim($_POST['certificate_provider'] ?? '');
                $isPublished = isset($_POST['is_published']) ? 1 : 0;
                $isFeatured  = isset($_POST['is_featured']) ? 1 : 0;
                $tags        = trim($_POST['tags'] ?? '');

                if ($title === '') {
                    $err = 'Title is required.';
                } else {
                    $thumb = jm_course_upload();
                    if ($id > 0) {
                        $slug = jm_course_slug($pdo, $title, $id);
                        $sql = "UPDATE courses SET category_id=?, title=?, slug=?, short_description=?, description=?, instructor_name=?, instructor_bio=?, difficulty=?, course_type=?, duration_hours=?, is_external=?, external_url=?, is_free=?, price=?, seed_price=?, has_certificate=?, certificate_provider=?, is_published=?, is_featured=?, tags=?"
                            . ($thumb ? ", thumbnail=?" : "") . " WHERE course_id=?";
                        $params = [$categoryId,$title,$slug,$short,$desc,$instructor,$instrBio,$difficulty,$type,$hours,$isExternal,$externalUrl ?: null,$isFree,$price,$seedPrice,$hasCert,$certProv ?: null,$isPublished,$isFeatured,$tags ?: null];
                        if ($thumb) $params[] = $thumb;
                        $params[] = $id;
                        $pdo->prepare($sql)->execute($params);
                        $msg = 'Course updated.';
                    } else {
                        $slug = jm_course_slug($pdo, $title);
                        $pdo->prepare("INSERT INTO courses (category_id,title,slug,short_description,description,instructor_name,instructor_bio,difficulty,course_type,duration_hours,is_external,external_url,is_free,price,seed_price,has_certificate,certificate_provider,is_published,is_featured,tags,thumbnail) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
                            ->execute([$categoryId,$title,$slug,$short,$desc,$instructor,$instrBio,$difficulty,$type,$hours,$isExternal,$externalUrl ?: null,$isFree,$price,$seedPrice,$hasCert,$certProv ?: null,$isPublished,$isFeatured,$tags ?: null,$thumb]);
                        $msg = 'Course created.';
                    }
                }
            } elseif ($action === 'delete') {
                $pdo->prepare("DELETE FROM courses WHERE course_id = ?")->execute([(int) $_POST['course_id']]);
                $msg = 'Course deleted.';
            } elseif ($action === 'toggle') {
                $pdo->prepare("UPDATE courses SET is_published = 1 - is_published WHERE course_id = ?")->execute([(int) $_POST['course_id']]);
                $msg = 'Visibility updated.';
            }
        } catch (Throwable $e) {
            $err = 'Error: ' . $e->getMessage();
        }
    }
    Security::regenerateCSRF();
}

$categories = $pdo->query("SELECT id, name FROM course_categories ORDER BY name")->fetchAll();

$editing = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM courses WHERE course_id = ?");
    $stmt->execute([(int) $_GET['edit']]);
    $editing = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

$courses = $pdo->query("
    SELECT c.*, cc.name AS category_name,
           (SELECT COUNT(*) FROM course_modules WHERE course_id = c.course_id) AS module_count,
           (SELECT COUNT(*) FROM course_enrollments WHERE course_id = c.course_id) AS enrollments
    FROM courses c LEFT JOIN course_categories cc ON c.category_id = cc.id
    ORDER BY c.created_at DESC
")->fetchAll();

$pageTitle = 'Courses - ' . SITE_NAME;
require_once __DIR__ . '/../includes/header.php';
?>
<style>
.jm-ad-wrap { display:grid; grid-template-columns: 400px 1fr; gap:20px; align-items:start; }
@media (max-width:1100px){ .jm-ad-wrap { grid-template-columns:1fr; } }
.jm-ad-card { background:#fff; border:1px solid #e4eaf3; border-radius:12px; padding:20px; }
.jm-ad-card h2 { margin:0 0 14px; font-size:15px; font-weight:800; color:#0b1b33; }
.jm-ad-field { margin-bottom:12px; }
.jm-ad-field label { display:block; font-size:12px; font-weight:700; color:#5b6b82; margin-bottom:5px; }
.jm-ad-field input, .jm-ad-field textarea, .jm-ad-field select { width:100%; box-sizing:border-box; border:1px solid #d8e4f4; border-radius:8px; padding:9px 11px; font:inherit; font-size:13px; background:#fbfdff; }
.jm-ad-field textarea { min-height:70px; resize:vertical; }
.jm-ad-row2 { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
@media (max-width:560px){ .jm-ad-row2 { grid-template-columns:1fr; } }
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
.jm-ad-hint { font-size:11px; color:#94a3b8; margin-top:4px; }
</style>

<div class="ja-pagehead"><div><h1>Courses</h1><p>Create and manage the learning academy. Modules are managed under <a href="/jobmington/admin/modules.php" style="color:#0640a3;">Modules</a>.</p></div>
<a class="ja-statuschip" href="/jobmington/learn/" target="_blank">View academy</a></div>

<?php if ($msg): ?><div class="jm-ad-msg ok"><?= e($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="jm-ad-msg err"><?= e($err) ?></div><?php endif; ?>

<div class="jm-ad-wrap">
    <form class="jm-ad-card" method="post" enctype="multipart/form-data">
        <h2><?= $editing ? 'Edit course' : 'Add course' ?></h2>
        <?= Security::csrfField() ?>
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="course_id" value="<?= (int) ($editing['course_id'] ?? 0) ?>">
        <div class="jm-ad-field"><label>Title *</label><input type="text" name="title" value="<?= e($editing['title'] ?? '') ?>" required></div>
        <div class="jm-ad-field"><label>Category</label><select name="category_id">
            <option value="">— None —</option>
            <?php foreach ($categories as $cat): ?>
                <option value="<?= (int)$cat['id'] ?>" <?= ($editing['category_id'] ?? 0) == $cat['id'] ? 'selected' : '' ?>><?= e($cat['name']) ?></option>
            <?php endforeach; ?>
        </select></div>
        <div class="jm-ad-field"><label>Short description</label><input type="text" name="short_description" value="<?= e($editing['short_description'] ?? '') ?>"></div>
        <div class="jm-ad-field"><label>Full description</label><textarea name="description"><?= e($editing['description'] ?? '') ?></textarea></div>
        <div class="jm-ad-row2">
            <div class="jm-ad-field"><label>Instructor</label><input type="text" name="instructor_name" value="<?= e($editing['instructor_name'] ?? '') ?>"></div>
            <div class="jm-ad-field"><label>Difficulty</label><select name="difficulty">
                <?php foreach (['beginner','intermediate','advanced'] as $d): ?>
                    <option value="<?= $d ?>" <?= ($editing['difficulty'] ?? 'beginner') === $d ? 'selected' : '' ?>><?= ucfirst($d) ?></option>
                <?php endforeach; ?>
            </select></div>
        </div>
        <div class="jm-ad-row2">
            <div class="jm-ad-field"><label>Duration (hours)</label><input type="number" step="0.5" name="duration_hours" value="<?= e($editing['duration_hours'] ?? '0') ?>"></div>
            <div class="jm-ad-field"><label>Price (₦)</label><input type="number" step="0.01" name="price" value="<?= e($editing['price'] ?? '0') ?>"></div>
        </div>
        <div class="jm-ad-field"><label>External course URL <span style="font-weight:400;color:#94a3b8;">(if hosted elsewhere)</span></label><input type="text" name="external_url" value="<?= e($editing['external_url'] ?? '') ?>"></div>
        <div class="jm-ad-field"><label>Certificate provider</label><input type="text" name="certificate_provider" value="<?= e($editing['certificate_provider'] ?? '') ?>"></div>
        <div class="jm-ad-field"><label>Thumbnail (jpg/png, 16:9)</label><input type="file" name="thumbnail" accept="image/*">
            <?php if (!empty($editing['thumbnail'])): ?><div class="jm-ad-hint">Current: <img src="<?= e($editing['thumbnail']) ?>" class="jm-ad-thumb" style="vertical-align:middle;"></div><?php endif; ?>
        </div>
        <div class="jm-ad-checks">
            <label><input type="checkbox" name="is_external" <?= ($editing && $editing['is_external']) ? 'checked' : '' ?>> External</label>
            <label><input type="checkbox" name="is_free" <?= (!$editing || $editing['is_free']) ? 'checked' : '' ?>> Free</label>
            <label><input type="checkbox" name="has_certificate" <?= ($editing && $editing['has_certificate']) ? 'checked' : '' ?>> Certificate</label>
            <label><input type="checkbox" name="is_published" <?= (!$editing || $editing['is_published']) ? 'checked' : '' ?>> Published</label>
            <label><input type="checkbox" name="is_featured" <?= ($editing && $editing['is_featured']) ? 'checked' : '' ?>> Featured</label>
        </div>
        <button class="jm-ad-btn" type="submit"><?= $editing ? 'Save changes' : 'Add course' ?></button>
        <?php if ($editing): ?><div style="text-align:center;margin-top:10px;"><a href="/jobmington/admin/courses.php" style="font-size:12px;color:#5b6b82;">Cancel edit</a></div><?php endif; ?>
    </form>

    <div>
        <div class="jm-tablewrap"><table class="jm-ad-table jm-stacktable">
            <thead><tr><th></th><th>Course</th><th>Category</th><th>Modules</th><th>Enrolled</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php if (empty($courses)): ?>
                <tr><td colspan="7" style="text-align:center;color:#94a3b8;padding:28px;">No courses yet. Add your first one.</td></tr>
            <?php else: foreach ($courses as $c): ?>
                <tr>
                    <td><?php if (!empty($c['thumbnail'])): ?><img src="<?= e($c['thumbnail']) ?>" class="jm-ad-thumb"><?php else: ?><div class="jm-ad-thumb"></div><?php endif; ?></td>
                    <td><strong><?= e($c['title']) ?></strong><div style="font-size:11px;color:#94a3b8;"><?= e(ucfirst($c['difficulty'])) ?><?= $c['is_external'] ? ' · External' : '' ?></div></td>
                    <td><?= e($c['category_name'] ?: '—') ?></td>
                    <td><?= (int)$c['module_count'] ?></td>
                    <td><a href="/jobmington/admin/audience.php?type=course&id=<?= (int) $c['course_id'] ?>&action=enrolled" style="color:#0640a3;font-weight:700;"><?= (int)$c['enrollments'] ?></a></td>
                    <td><span class="jm-ad-pill <?= $c['is_published'] ? 'on' : 'off' ?>"><?= $c['is_published'] ? 'Live' : 'Hidden' ?></span></td>
                    <td>
                        <div class="jm-ad-actions">
                            <a href="/jobmington/admin/courses.php?edit=<?= (int)$c['course_id'] ?>">Edit</a>
                            <form method="post" style="display:inline;"><?= Security::csrfField() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="course_id" value="<?= (int)$c['course_id'] ?>"><button type="submit"><?= $c['is_published'] ? 'Hide' : 'Show' ?></button></form>
                            <form method="post" onsubmit="return confirm('Delete this course?');" style="display:inline;"><?= Security::csrfField() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="course_id" value="<?= (int)$c['course_id'] ?>"><button type="submit" style="color:#b42318;">Delete</button></form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table></div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
