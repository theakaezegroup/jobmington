<?php
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
$courseId = (int) get('course_id', 0);

$msg = '';
$err = '';

/*
 * This page was read only: no add, no edit, no delete, no reorder, while the
 * courses page told you modules were managed here. The only way to change a
 * lesson was in the database.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::verifyCSRF()) {
        $err = 'Please refresh the page and try again.';
    } else {
        $action = $_POST['action'] ?? '';
        $moduleId = (int) ($_POST['module_id'] ?? 0);

        try {
            if ($action === 'save') {
                $target   = (int) ($_POST['course_id'] ?? 0);
                $title    = trim(Security::clean($_POST['title'] ?? ''));
                $desc     = trim($_POST['description'] ?? '');
                $content  = trim($_POST['content'] ?? '');
                $video    = trim(Security::clean($_POST['video_url'] ?? ''));
                $duration = max(0, (int) ($_POST['duration_minutes'] ?? 0));
                $order    = max(0, (int) ($_POST['sort_order'] ?? 0));
                $preview  = isset($_POST['is_free_preview']) ? 1 : 0;

                if ($title === '' || $target <= 0) {
                    $err = 'Pick a course and give the module a title.';
                } elseif ($video !== '' && !filter_var($video, FILTER_VALIDATE_URL)) {
                    $err = 'Enter a valid video URL.';
                } else {
                    if ($moduleId > 0) {
                        $pdo->prepare("
                            UPDATE course_modules
                            SET course_id = ?, title = ?, description = ?, content = ?, video_url = ?,
                                duration_minutes = ?, sort_order = ?, is_free_preview = ?
                            WHERE module_id = ?
                        ")->execute([$target, $title, $desc ?: null, $content ?: null, $video ?: null, $duration, $order, $preview, $moduleId]);
                        jm_log_activity((int) Session::userId(), 'admin_module_updated', $title);
                        $msg = 'Module updated.';
                    } else {
                        // Default to the end of the course rather than 0, which
                        // would silently tie with the first lesson.
                        if ($order === 0) {
                            $next = $pdo->prepare("SELECT COALESCE(MAX(sort_order), 0) + 1 FROM course_modules WHERE course_id = ?");
                            $next->execute([$target]);
                            $order = (int) $next->fetchColumn();
                        }
                        $pdo->prepare("
                            INSERT INTO course_modules (course_id, title, description, content, video_url, duration_minutes, sort_order, is_free_preview, created_at)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
                        ")->execute([$target, $title, $desc ?: null, $content ?: null, $video ?: null, $duration, $order, $preview]);
                        jm_log_activity((int) Session::userId(), 'admin_module_created', $title);
                        $msg = 'Module added.';
                    }
                }
            } elseif ($action === 'delete' && $moduleId > 0) {
                $pdo->prepare("DELETE FROM course_modules WHERE module_id = ?")->execute([$moduleId]);
                jm_log_activity((int) Session::userId(), 'admin_module_deleted', 'module ' . $moduleId);
                $msg = 'Module deleted.';
            } elseif ($action === 'preview' && $moduleId > 0) {
                $pdo->prepare("UPDATE course_modules SET is_free_preview = 1 - is_free_preview WHERE module_id = ?")->execute([$moduleId]);
                $msg = 'Preview flag updated.';
            }
        } catch (Throwable $e) {
            error_log('admin/modules: ' . $e->getMessage());
            $err = 'That could not be saved.';
        }
    }
    Security::regenerateCSRF();
}

$editing = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM course_modules WHERE module_id = ?");
    $stmt->execute([(int) $_GET['edit']]);
    $editing = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

$courses = [];
$modules = [];
try {
    $courses = $pdo->query("SELECT course_id, title FROM courses ORDER BY title")->fetchAll();
    $where = $courseId > 0 ? "WHERE cm.course_id = ?" : "";
    $stmt = $pdo->prepare("
        SELECT cm.*, c.title AS course_title
        FROM course_modules cm
        JOIN courses c ON cm.course_id = c.course_id
        {$where}
        ORDER BY c.title ASC, cm.sort_order ASC, cm.module_id ASC
        LIMIT 200
    ");
    $stmt->execute($courseId > 0 ? [$courseId] : []);
    $modules = $stmt->fetchAll();
} catch (Throwable $e) {
    // Silence here is why an empty list and a broken query looked identical.
    error_log('admin/modules list: ' . $e->getMessage());
    $err = $err ?: 'The module list could not be loaded.';
    $modules = [];
}

$pageTitle = 'Course Modules - Admin';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="min-h-screen bg-slate-100 py-8">
    <div class="max-w-7xl mx-auto px-4">
        <div class="flex items-center justify-between mb-8">
            <div>
                <a href="/jobmington/admin/courses.php" class="text-slate-500 hover:text-slate-900 text-xs font-bold uppercase tracking-widest">Courses</a>
                <h1 class="text-3xl font-bold text-slate-900 mt-2">Course Modules</h1>
                <p class="text-slate-600">Add, edit and order the lessons inside each course.</p>
            </div>
            <form method="get" class="flex gap-2">
                <select name="course_id" class="px-3 py-2 rounded-lg border border-slate-200 bg-white">
                    <option value="0">All courses</option>
                    <?php foreach ($courses as $course): ?>
                        <option value="<?= (int) $course['course_id'] ?>" <?= $courseId === (int) $course['course_id'] ? 'selected' : '' ?>><?= e($course['title']) ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="px-4 py-2 bg-slate-900 text-white rounded-lg font-bold" type="submit">Filter</button>
            </form>
        </div>

        <?php if ($msg): ?>
            <div class="mb-6 px-4 py-3 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm font-semibold"><?= e($msg) ?></div>
        <?php endif; ?>
        <?php if ($err): ?>
            <div class="mb-6 px-4 py-3 rounded-lg bg-red-50 border border-red-200 text-red-800 text-sm font-semibold"><?= e($err) ?></div>
        <?php endif; ?>

        <form method="post" class="bg-white border border-slate-200 rounded-xl p-5 mb-6">
            <?= Security::csrfField() ?>
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="module_id" value="<?= (int) ($editing['module_id'] ?? 0) ?>">

            <div class="flex items-center justify-between mb-4 flex-wrap gap-2">
                <h2 class="text-lg font-bold text-slate-900"><?= $editing ? 'Edit module' : 'Add a module' ?></h2>
                <?php if ($editing): ?>
                    <a href="/jobmington/admin/modules.php?course_id=<?= (int) $courseId ?>" class="text-sm font-bold text-slate-500">Cancel</a>
                <?php endif; ?>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <label class="block">
                    <span class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Course</span>
                    <select name="course_id" required class="w-full px-3 py-2 rounded-lg border border-slate-200 bg-white text-sm">
                        <?php foreach ($courses as $course): ?>
                            <option value="<?= (int) $course['course_id'] ?>"
                                <?= (int) ($editing['course_id'] ?? $courseId) === (int) $course['course_id'] ? 'selected' : '' ?>>
                                <?= e($course['title']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="block">
                    <span class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Title</span>
                    <input type="text" name="title" required value="<?= e($editing['title'] ?? '') ?>"
                           class="w-full px-3 py-2 rounded-lg border border-slate-200 text-sm">
                </label>
            </div>

            <label class="block mt-3">
                <span class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Description</span>
                <textarea name="description" rows="2" class="w-full px-3 py-2 rounded-lg border border-slate-200 text-sm"><?= e($editing['description'] ?? '') ?></textarea>
            </label>

            <label class="block mt-3">
                <span class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Lesson content</span>
                <textarea name="content" rows="5" class="w-full px-3 py-2 rounded-lg border border-slate-200 text-sm"><?= e($editing['content'] ?? '') ?></textarea>
            </label>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mt-3">
                <label class="block">
                    <span class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Video URL</span>
                    <input type="url" name="video_url" value="<?= e($editing['video_url'] ?? '') ?>" placeholder="Optional"
                           class="w-full px-3 py-2 rounded-lg border border-slate-200 text-sm">
                </label>
                <label class="block">
                    <span class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Duration (min)</span>
                    <input type="number" name="duration_minutes" min="0" value="<?= (int) ($editing['duration_minutes'] ?? 0) ?>"
                           class="w-full px-3 py-2 rounded-lg border border-slate-200 text-sm">
                </label>
                <label class="block">
                    <span class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Order</span>
                    <input type="number" name="sort_order" min="0" value="<?= (int) ($editing['sort_order'] ?? 0) ?>" placeholder="0 = put it last"
                           class="w-full px-3 py-2 rounded-lg border border-slate-200 text-sm">
                </label>
            </div>

            <label class="flex items-center gap-2 mt-4 text-sm font-semibold text-slate-700">
                <input type="checkbox" name="is_free_preview" value="1" <?= (int) ($editing['is_free_preview'] ?? 0) === 1 ? 'checked' : '' ?>>
                Free preview, readable without enrolling
            </label>

            <button class="mt-4 px-5 py-2.5 bg-slate-900 text-white rounded-lg font-bold text-sm" type="submit">
                <?= $editing ? 'Save changes' : 'Add module' ?>
            </button>
        </form>

        <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
            <div class="jm-tablewrap"><table class="w-full text-sm jm-stacktable" data-title-col="1">
                <thead class="bg-slate-50 text-slate-500 uppercase text-xs">
                    <tr>
                        <th class="text-left p-4">Order</th>
                        <th class="text-left p-4">Module</th>
                        <th class="text-left p-4">Course</th>
                        <th class="text-left p-4">Duration</th>
                        <th class="text-left p-4">Preview</th>
                        <th class="text-left p-4"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($modules)): ?>
                        <tr><td colspan="6" class="p-8 text-center text-slate-500">No modules yet. Add the first one above.</td></tr>
                    <?php else: ?>
                        <?php foreach ($modules as $module): ?>
                            <tr class="border-t border-slate-100">
                                <td class="p-4 font-mono text-slate-500"><?= (int) $module['sort_order'] ?></td>
                                <td class="p-4">
                                    <div class="font-bold text-slate-900"><?= e($module['title']) ?></div>
                                    <div class="text-slate-500"><?= e(excerpt($module['description'] ?? '', 90)) ?></div>
                                </td>
                                <td class="p-4 text-slate-700"><?= e($module['course_title']) ?></td>
                                <td class="p-4 text-slate-700"><?= number_format((int) $module['duration_minutes']) ?> min</td>
                                <td class="p-4">
                                    <span class="px-2 py-1 rounded-full text-xs font-bold <?= (int) $module['is_free_preview'] === 1 ? 'bg-blue-100 text-blue-700' : 'bg-slate-100 text-slate-600' ?>">
                                        <?= (int) $module['is_free_preview'] === 1 ? 'Free preview' : 'Locked' ?>
                                    </span>
                                </td>
                                <td class="p-4">
                                    <div class="flex gap-2 flex-wrap">
                                        <a href="?course_id=<?= (int) $courseId ?>&edit=<?= (int) $module['module_id'] ?>"
                                           class="px-2.5 py-1 rounded-md border border-slate-200 text-xs font-bold text-blue-700">Edit</a>
                                        <form method="post" style="display:inline;">
                                            <?= Security::csrfField() ?>
                                            <input type="hidden" name="action" value="preview">
                                            <input type="hidden" name="module_id" value="<?= (int) $module['module_id'] ?>">
                                            <button type="submit" class="px-2.5 py-1 rounded-md border border-slate-200 text-xs font-bold text-slate-700">
                                                <?= (int) $module['is_free_preview'] === 1 ? 'Lock' : 'Unlock' ?>
                                            </button>
                                        </form>
                                        <form method="post" style="display:inline;" onsubmit="return confirm('Delete this module?');">
                                            <?= Security::csrfField() ?>
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="module_id" value="<?= (int) $module['module_id'] ?>">
                                            <button type="submit" class="px-2.5 py-1 rounded-md border border-slate-200 text-xs font-bold text-red-700">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table></div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
