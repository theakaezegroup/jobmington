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
$categories = [];
$topics = [];
try {
    $categories = $pdo->query("
        SELECT fc.*, COUNT(DISTINCT ft.topic_id) AS topic_count
        FROM forum_categories fc
        LEFT JOIN forum_topics ft ON fc.id = ft.category_id
        GROUP BY fc.id
        ORDER BY fc.name
    ")->fetchAll();

    $topics = $pdo->query("
        SELECT ft.*, fc.name AS category_name, u.full_name,
               COUNT(fr.reply_id) AS reply_count
        FROM forum_topics ft
        LEFT JOIN forum_categories fc ON ft.category_id = fc.id
        LEFT JOIN users u ON ft.user_id = u.user_id
        LEFT JOIN forum_replies fr ON ft.topic_id = fr.topic_id
        GROUP BY ft.topic_id
        ORDER BY ft.created_at DESC
        LIMIT 100
    ")->fetchAll();
} catch (Throwable $e) {
    $categories = [];
    $topics = [];
}

$pageTitle = 'Forum - Admin';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="min-h-screen bg-slate-100 py-8">
    <div class="max-w-7xl mx-auto px-4">
        <div class="mb-8">
            <a href="/jobmington/admin/" class="text-slate-500 hover:text-slate-900 text-xs font-bold uppercase tracking-widest">Admin Dashboard</a>
            <h1 class="text-3xl font-bold text-slate-900 mt-2">Community Forum</h1>
            <p class="text-slate-600">Forum categories and recent community topics.</p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <aside class="bg-white border border-slate-200 rounded-xl p-6">
                <h2 class="text-xl font-bold text-slate-900 mb-4">Categories</h2>
                <div class="space-y-3">
                    <?php foreach ($categories as $category): ?>
                        <div class="border-b border-slate-100 pb-3">
                            <div class="font-bold text-slate-900"><?= e($category['name']) ?></div>
                            <div class="text-slate-500 text-sm"><?= number_format((int) $category['topic_count']) ?> topics</div>
                        </div>
                    <?php endforeach; ?>
                    <?php if (empty($categories)): ?><p class="text-slate-500">No categories found.</p><?php endif; ?>
                </div>
            </aside>

            <main class="lg:col-span-2 bg-white border border-slate-200 rounded-xl overflow-hidden">
                <div class="jm-tablewrap"><table class="w-full text-sm">
                    <thead class="bg-slate-50 text-slate-500 uppercase text-xs">
                        <tr>
                            <th class="text-left p-4">Topic</th>
                            <th class="text-left p-4">Category</th>
                            <th class="text-left p-4">Replies</th>
                            <th class="text-left p-4">Views</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($topics)): ?>
                            <tr><td colspan="4" class="p-8 text-center text-slate-500">No topics yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($topics as $topic): ?>
                                <tr class="border-t border-slate-100">
                                    <td class="p-4">
                                        <a class="font-bold text-blue-600" href="/jobmington/community/topic.php?id=<?= (int) $topic['topic_id'] ?>"><?= e($topic['title'] ?: 'Untitled topic') ?></a>
                                        <div class="text-slate-500">By <?= e($topic['full_name'] ?: 'Member') ?> / <?= e(timeAgo($topic['created_at'])) ?></div>
                                    </td>
                                    <td class="p-4 text-slate-700"><?= e($topic['category_name'] ?? 'Uncategorized') ?></td>
                                    <td class="p-4 text-slate-700"><?= number_format((int) $topic['reply_count']) ?></td>
                                    <td class="p-4 text-slate-700"><?= number_format((int) $topic['views']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table></div>
            </main>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
