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
                <p class="text-slate-600">Review lessons, preview flags, and course structure.</p>
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

        <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-500 uppercase text-xs">
                    <tr>
                        <th class="text-left p-4">Order</th>
                        <th class="text-left p-4">Module</th>
                        <th class="text-left p-4">Course</th>
                        <th class="text-left p-4">Duration</th>
                        <th class="text-left p-4">Preview</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($modules)): ?>
                        <tr><td colspan="5" class="p-8 text-center text-slate-500">No modules found.</td></tr>
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
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
