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
$msg = '';

/*
 * Mark one reply per thread as the verified answer.
 *
 * Setting one clears the rest in the same topic, so "verified" stays a single
 * pointer rather than a badge that can quietly accumulate across a thread.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'verify_answer') {
    if (!Security::verifyCSRF()) {
        $msg = 'Security check failed. Please try again.';
    } else {
        $replyId = (int) ($_POST['reply_id'] ?? 0);
        $topicId = (int) ($_POST['topic_id'] ?? 0);
        $on      = (int) ($_POST['on'] ?? 0) === 1;
        try {
            $pdo->prepare("UPDATE forum_replies SET is_verified_answer = 0 WHERE topic_id = ?")->execute([$topicId]);
            if ($on && $replyId) {
                $pdo->prepare("UPDATE forum_replies SET is_verified_answer = 1 WHERE reply_id = ? AND topic_id = ?")
                    ->execute([$replyId, $topicId]);
                $msg = 'Marked as the verified answer.';
            } else {
                $msg = 'Verified answer removed.';
            }
            Security::regenerateCSRF();
        } catch (Throwable $e) {
            error_log('Verify answer failed: ' . $e->getMessage());
            $msg = 'Could not update that just now.';
        }
    }
}

$openTopicId = (int) ($_GET['topic'] ?? 0);
$openTopic   = null;
$openReplies = [];
if ($openTopicId > 0) {
    try {
        $s = $pdo->prepare("SELECT ft.*, u.full_name FROM forum_topics ft
                            LEFT JOIN users u ON ft.user_id = u.user_id
                            WHERE ft.topic_id = ? LIMIT 1");
        $s->execute([$openTopicId]);
        $openTopic = $s->fetch() ?: null;

        $s = $pdo->prepare("SELECT fr.*, u.full_name, u.is_official FROM forum_replies fr
                            LEFT JOIN users u ON fr.user_id = u.user_id
                            WHERE fr.topic_id = ? ORDER BY fr.created_at ASC");
        $s->execute([$openTopicId]);
        $openReplies = $s->fetchAll();
    } catch (Throwable $e) {
        $openTopic = null;
        $openReplies = [];
    }
}

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

        <?php if ($msg): ?>
            <div class="mb-5 rounded-lg bg-emerald-50 text-emerald-800 px-4 py-3 text-sm font-semibold"><?= e($msg) ?></div>
        <?php endif; ?>

        <?php if ($openTopic): ?>
            <div class="bg-white border border-slate-200 rounded-xl p-6 mb-6">
                <div class="flex items-start justify-between gap-4 flex-wrap mb-1">
                    <h2 class="text-lg font-bold text-slate-900"><?= e($openTopic['title']) ?></h2>
                    <a href="/jobmington/admin/forum.php" class="text-sm font-semibold text-slate-500 hover:text-slate-800">Close</a>
                </div>
                <p class="text-xs text-slate-500 mb-5">Pick the reply that best answers this thread. Only one can hold the mark, and it shows on the public thread as Verified by Jobmington.</p>

                <?php if (!$openReplies): ?>
                    <p class="text-sm text-slate-400">No replies yet, so there is nothing to verify.</p>
                <?php else: ?>
                    <div class="space-y-3">
                        <?php foreach ($openReplies as $r): $on = !empty($r['is_verified_answer']); ?>
                            <div class="border <?= $on ? 'border-blue-300 bg-blue-50' : 'border-slate-200' ?> rounded-lg p-4">
                                <div class="flex items-center justify-between gap-3 flex-wrap mb-2">
                                    <div class="text-sm font-bold text-slate-800">
                                        <?= e($r['full_name'] ?: 'Member') ?>
                                        <span class="text-slate-400 font-medium">/ <?= e(timeAgo($r['created_at'])) ?></span>
                                        <?php if ($on): ?><span class="ml-2 text-xs font-black uppercase tracking-wide text-blue-700">Verified answer</span><?php endif; ?>
                                    </div>
                                    <form method="post" class="shrink-0">
                                        <?= Security::csrfField() ?>
                                        <input type="hidden" name="action" value="verify_answer">
                                        <input type="hidden" name="topic_id" value="<?= (int) $openTopicId ?>">
                                        <input type="hidden" name="reply_id" value="<?= (int) $r['reply_id'] ?>">
                                        <input type="hidden" name="on" value="<?= $on ? 0 : 1 ?>">
                                        <button class="rounded-lg px-3 py-1.5 text-xs font-bold <?= $on ? 'bg-slate-200 text-slate-700 hover:bg-slate-300' : 'bg-blue-700 text-white hover:bg-blue-800' ?>">
                                            <?= $on ? 'Remove mark' : 'Mark as verified' ?>
                                        </button>
                                    </form>
                                </div>
                                <p class="text-sm text-slate-600 whitespace-pre-wrap"><?= e(truncate($r['content'], 320)) ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

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
                <div class="jm-tablewrap"><table class="w-full text-sm jm-stacktable">
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
                                        <a class="font-bold text-blue-600" href="?topic=<?= (int) $topic['topic_id'] ?>"><?= e($topic['title'] ?: 'Untitled topic') ?></a>
                                        <a class="text-slate-400 hover:text-slate-700 ml-2" href="/jobmington/community/topic.php?id=<?= (int) $topic['topic_id'] ?>" target="_blank" rel="noopener" title="View on the site">&#8599;</a>
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
