<?php
/**
 * JOBMINGTON - Admin: who is online
 *
 * Presence comes from users.last_seen_at, stamped by Session::enforceActive on
 * a one-minute throttle. That means "online" is really "seen within the last
 * few minutes", which is the honest answer: HTTP has no way to tell you
 * somebody closed a tab, so anything claiming live presence is guessing.
 *
 * Page styling deliberately matches the rest of this admin panel.
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

// Minutes of silence before we stop calling someone online. Five is two missed
// heartbeats: enough to ride out a slow page or a tab in the background.
$window = max(2, min(60, (int) get('window', 5)));

$rows = [];
$counts = ['online' => 0, 'today' => 0, 'week' => 0];

try {
    $stmt = $pdo->prepare("
        SELECT user_id, full_name, email, user_type, profile_image,
               last_seen_at, last_seen_route,
               TIMESTAMPDIFF(SECOND, last_seen_at, NOW()) AS quiet_for
        FROM users
        WHERE last_seen_at IS NOT NULL
          AND last_seen_at >= DATE_SUB(NOW(), INTERVAL ? MINUTE)
        ORDER BY last_seen_at DESC
        LIMIT 200
    ");
    $stmt->execute([$window]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $counts['online'] = count($rows);
    $counts['today'] = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE last_seen_at >= CURDATE()")->fetchColumn();
    $counts['week']  = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE last_seen_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
} catch (Throwable $e) {
    error_log('admin/online: ' . $e->getMessage());
}

// Answer the count on its own for the dashboard tile, without the page around it.
if (get('format') === 'json') {
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'online' => $counts['online']]);
    exit;
}

$pageTitle = 'Who is online - Admin';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="min-h-screen bg-slate-100 py-8">
    <div class="max-w-7xl mx-auto px-4">

        <div class="flex flex-col md:flex-row md:items-end justify-between gap-4 mb-8">
            <div class="min-w-0">
                <h1 class="text-3xl font-bold text-slate-900">Who is online</h1>
                <p class="text-slate-600">
                    Signed-in people seen in the last <?= (int) $window ?> minutes. This page refreshes itself.
                </p>
            </div>
            <form method="get" class="flex gap-2 w-full md:w-auto">
                <select name="window" class="flex-1 min-w-0 md:flex-none px-3 py-2 rounded-lg text-sm">
                    <?php foreach ([2, 5, 15, 30, 60] as $m): ?>
                        <option value="<?= $m ?>" <?= $window === $m ? 'selected' : '' ?>>Last <?= $m ?> minutes</option>
                    <?php endforeach; ?>
                </select>
                <button class="px-4 py-2 bg-slate-900 text-white rounded-lg font-bold text-sm whitespace-nowrap" type="submit">Apply</button>
            </form>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
            <?php foreach ([
                ['Online now', $counts['online'], 'emerald'],
                ['Seen today', $counts['today'], 'slate'],
                ['Seen this week', $counts['week'], 'slate'],
            ] as [$label, $value, $tone]): ?>
                <div class="bg-white rounded-xl p-5">
                    <p class="text-3xl font-bold text-<?= $tone ?>-<?= $tone === 'emerald' ? '600' : '900' ?>"><?= number_format((int) $value) ?></p>
                    <p class="text-xs text-slate-500 uppercase tracking-wider font-bold mt-1"><?= e($label) ?></p>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="bg-white rounded-xl overflow-hidden">
            <?php if (!$rows): ?>
                <p class="p-10 text-center text-slate-500">
                    Nobody signed in has been active in the last <?= (int) $window ?> minutes.
                </p>
            <?php else: ?>
            <div class="jm-tablewrap"><table class="w-full text-sm jm-stacktable">
                <thead class="bg-slate-50 text-slate-500 uppercase text-xs">
                    <tr>
                        <th class="text-left px-4 py-3">Person</th>
                        <th class="text-left px-4 py-3">Role</th>
                        <th class="text-left px-4 py-3">Last seen</th>
                        <th class="text-left px-4 py-3">Where</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $r):
                    $quiet = (int) $r['quiet_for'];
                    // Under a minute is a live heartbeat; beyond that they are
                    // simply the most recent thing we know.
                    $fresh = $quiet < 90;
                ?>
                    <tr class="border-t border-slate-100">
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full <?= $fresh ? 'bg-emerald-500' : 'bg-amber-400' ?>" title="<?= $fresh ? 'Active now' : 'Idle' ?>"></span>
                                <span>
                                    <span class="font-semibold text-slate-900"><?= e($r['full_name'] ?: 'Member') ?></span>
                                    <span class="block text-xs text-slate-500"><?= e($r['email']) ?></span>
                                </span>
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-slate-100 text-slate-700"><?= e($r['user_type']) ?></span>
                        </td>
                        <td class="px-4 py-3 text-slate-500 whitespace-nowrap">
                            <?= $quiet < 60 ? 'just now' : (int) floor($quiet / 60) . 'm ago' ?>
                        </td>
                        <td class="px-4 py-3 text-slate-500 text-xs font-mono"><?= e($r['last_seen_route'] ?? '') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
            <?php endif; ?>
        </div>

        <p class="text-xs text-slate-400 mt-4">
            Presence is stamped at most once a minute per person, so someone who has just
            arrived can take a moment to appear. Signed-out visitors are not counted:
            there is no account to attach them to.
        </p>
    </div>
</div>

<script>
    // Reload rather than poll a fragment: the page is small, admin-only, and a
    // full refresh cannot drift out of step with the counts above it.
    setTimeout(function () {
        if (!document.hidden) { location.reload(); }
    }, 30000);
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
