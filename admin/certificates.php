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
$certificates = [];
try {
    $certificates = $pdo->query("
        SELECT cert.*, u.full_name, u.email, c.title AS course_title
        FROM certificates cert
        JOIN users u ON cert.user_id = u.user_id
        JOIN courses c ON cert.course_id = c.course_id
        ORDER BY cert.issued_at DESC
        LIMIT 200
    ")->fetchAll();
} catch (Throwable $e) {
    $certificates = [];
}

$pageTitle = 'Certificates - Admin';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="min-h-screen bg-slate-100 py-8">
    <div class="max-w-7xl mx-auto px-4">
        <div class="mb-8">
            <a href="/jobmington/admin/" class="text-slate-500 hover:text-slate-900 text-xs font-bold uppercase tracking-widest">Admin Dashboard</a>
            <h1 class="text-3xl font-bold text-slate-900 mt-2">Certificates</h1>
            <p class="text-slate-600">Issued course certificates and verification codes.</p>
        </div>

        <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-500 uppercase text-xs">
                    <tr>
                        <th class="text-left p-4">Certificate</th>
                        <th class="text-left p-4">Learner</th>
                        <th class="text-left p-4">Course</th>
                        <th class="text-left p-4">Issued</th>
                        <th class="text-left p-4">File</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($certificates)): ?>
                        <tr><td colspan="5" class="p-8 text-center text-slate-500">No certificates issued yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($certificates as $certificate): ?>
                            <tr class="border-t border-slate-100">
                                <td class="p-4 font-mono text-slate-900"><?= e($certificate['verification_code']) ?></td>
                                <td class="p-4">
                                    <div class="font-bold text-slate-900"><?= e($certificate['full_name']) ?></div>
                                    <div class="text-slate-500"><?= e($certificate['email']) ?></div>
                                </td>
                                <td class="p-4 text-slate-700"><?= e($certificate['course_title']) ?></td>
                                <td class="p-4 text-slate-700"><?= e(formatDate($certificate['issued_at'])) ?></td>
                                <td class="p-4">
                                    <?php if (!empty($certificate['pdf_path'])): ?>
                                        <a class="text-blue-600 font-bold" href="<?= e(upload($certificate['pdf_path'])) ?>" target="_blank" rel="noopener">Open PDF</a>
                                    <?php else: ?>
                                        <span class="text-slate-400">No PDF</span>
                                    <?php endif; ?>
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
