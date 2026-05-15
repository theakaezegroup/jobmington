<?php
/**
 * JOBMINGTON - Certificate Example
 * Simple page to demonstrate certificate generation and download
 */

define('JOBMINGTON', true);
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';

Session::start();
Session::requireLogin();

$pdo = db();
$userId = Session::userId();

// Get completed courses for this user
$stmt = $pdo->prepare("SELECT ce.course_id, c.title FROM course_enrollments ce JOIN courses c ON ce.course_id = c.course_id WHERE ce.user_id = ? AND ce.progress >= 100 ORDER BY c.title");
$stmt->execute([$userId]);
$courses = $stmt->fetchAll();

$pageTitle = 'Certificate Demo - ' . SITE_NAME;
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-4xl mx-auto px-4 py-12">
    <h1 class="text-2xl font-heading font-bold mb-4">Certificate Demo</h1>

    <?php if (empty($courses)): ?>
        <div class="bg-white p-6 rounded-lg shadow-md">
            <p class="text-gray-700">You don't have any completed courses yet. Complete a course to generate a real certificate.</p>
            <p class="text-sm text-gray-500 mt-3">This demo requires a completed course (progress &ge; 100%).</p>
        </div>
    <?php else: ?>
        <div class="bg-white p-6 rounded-lg shadow-md">
            <form method="get" action="/jobmington/certificates/generate.php">
                <label class="block text-sm font-medium text-gray-700 mb-2">Choose a completed course:</label>
                <select name="course_id" class="w-full p-2 rounded border mb-4">
                    <?php foreach ($courses as $c): ?>
                        <option value="<?= e($c['course_id']) ?>"><?= e($c['title']) ?></option>
                    <?php endforeach; ?>
                </select>

                <button type="submit" class="bg-primary text-white px-4 py-2 rounded font-bold">
                    Generate &amp; Download Certificate
                </button>
            </form>

            <p class="text-sm text-gray-500 mt-4">The certificate will be generated and saved to <code>/uploads/certificates/</code>, and a download will begin automatically.</p>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php';
