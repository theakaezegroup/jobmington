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

$stats = ['courses' => 0, 'published' => 0, 'enrollments' => 0, 'certificates' => 0];
try {
    $stats['courses'] = (int) $pdo->query("SELECT COUNT(*) FROM courses")->fetchColumn();
    $stats['published'] = (int) $pdo->query("SELECT COUNT(*) FROM courses WHERE is_published = 1")->fetchColumn();
    $stats['enrollments'] = (int) $pdo->query("SELECT COUNT(*) FROM course_enrollments")->fetchColumn();
    $stats['certificates'] = (int) $pdo->query("SELECT COUNT(*) FROM certificates")->fetchColumn();
} catch (Throwable $e) {}

$courses = [];
try {
    $courses = $pdo->query("
        SELECT c.*, cc.name AS category_name,
               COUNT(DISTINCT cm.module_id) AS module_count,
               COUNT(DISTINCT q.quiz_id) AS quiz_count,
               COUNT(DISTINCT ce.enrollment_id) AS live_enrollments
        FROM courses c
        LEFT JOIN course_categories cc ON c.category_id = cc.id
        LEFT JOIN course_modules cm ON c.course_id = cm.course_id
        LEFT JOIN quizzes q ON c.course_id = q.course_id
        LEFT JOIN course_enrollments ce ON c.course_id = ce.course_id
        GROUP BY c.course_id
        ORDER BY c.created_at DESC
        LIMIT 100
    ")->fetchAll();
} catch (Throwable $e) {
    $courses = [];
}

$pageTitle = 'Courses - Admin';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="min-h-screen bg-slate-100 py-8">
    <div class="max-w-7xl mx-auto px-4">
        <div class="flex items-center justify-between mb-8">
            <div>
                <a href="/jobmington/admin/" class="text-slate-500 hover:text-slate-900 text-xs font-bold uppercase tracking-widest">Admin Dashboard</a>
                <h1 class="text-3xl font-bold text-slate-900 mt-2">Course Management</h1>
                <p class="text-slate-600">Review published courses, modules, quizzes, enrollments, and certificates.</p>
            </div>
            <div class="flex gap-3">
                <a href="/jobmington/admin/modules.php" class="px-4 py-2 bg-white border border-slate-200 rounded-lg font-bold text-slate-700">Modules</a>
                <a href="/jobmington/admin/quizzes.php" class="px-4 py-2 bg-slate-900 text-white rounded-lg font-bold">Quizzes</a>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
            <?php foreach ($stats as $label => $value): ?>
                <div class="bg-white border border-slate-200 rounded-xl p-5">
                    <div class="text-xs uppercase tracking-wider text-slate-500 font-bold"><?= e(ucfirst($label)) ?></div>
                    <div class="text-3xl font-black text-slate-900 mt-2"><?= number_format((int) $value) ?></div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-500 uppercase text-xs">
                    <tr>
                        <th class="text-left p-4">Course</th>
                        <th class="text-left p-4">Category</th>
                        <th class="text-left p-4">Modules</th>
                        <th class="text-left p-4">Quizzes</th>
                        <th class="text-left p-4">Enrollment</th>
                        <th class="text-left p-4">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($courses)): ?>
                        <tr><td colspan="6" class="p-8 text-center text-slate-500">No courses found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($courses as $course): ?>
                            <tr class="border-t border-slate-100">
                                <td class="p-4">
                                    <div class="font-bold text-slate-900"><?= e($course['title']) ?></div>
                                    <div class="text-slate-500"><?= e($course['difficulty'] ?? 'beginner') ?> / <?= e($course['course_type'] ?? 'video') ?></div>
                                </td>
                                <td class="p-4 text-slate-700"><?= e($course['category_name'] ?? 'Uncategorized') ?></td>
                                <td class="p-4"><a class="text-blue-600 font-bold" href="/jobmington/admin/modules.php?course_id=<?= (int) $course['course_id'] ?>"><?= number_format((int) $course['module_count']) ?></a></td>
                                <td class="p-4"><a class="text-blue-600 font-bold" href="/jobmington/admin/quizzes.php?id=<?= (int) $course['course_id'] ?>"><?= number_format((int) $course['quiz_count']) ?></a></td>
                                <td class="p-4 text-slate-700"><?= number_format((int) ($course['live_enrollments'] ?: $course['enrollment_count'])) ?></td>
                                <td class="p-4">
                                    <span class="px-2 py-1 rounded-full text-xs font-bold <?= (int) $course['is_published'] === 1 ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-600' ?>">
                                        <?= (int) $course['is_published'] === 1 ? 'Published' : 'Draft' ?>
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
