<?php
/**
 * JOBMINGTON - Admin: Tool access
 *
 * One switchboard for every tool. Each is off, in beta, or on, and a tool in
 * beta can be opened to named people one at a time.
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
require_once __DIR__ . '/../includes/tools.php';

Session::start();
Session::requireAdmin();
$pdo = db();

$msg = '';
$err = '';
$adminId = (int) Session::userId();
$tools = jm_tools();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::verifyCSRF()) {
        $err = 'Please refresh the page and try again.';
    } else {
        $action = $_POST['action'] ?? '';
        $key = (string) ($_POST['tool_key'] ?? '');

        if (!isset($tools[$key])) {
            $err = 'Unknown tool.';
        } elseif ($action === 'set_status') {
            $status = $_POST['status'] ?? 'on';
            if (!in_array($status, ['off', 'beta', 'on'], true)) {
                $err = 'Unknown status.';
            } else {
                $before = jm_tool_status($key);
                $pdo->prepare("
                    INSERT INTO tool_flags (tool_key, status, note, updated_by)
                    VALUES (?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE status = VALUES(status), note = VALUES(note), updated_by = VALUES(updated_by)
                ")->execute([$key, $status, Security::clean($_POST['note'] ?? '') ?: null, $adminId]);

                jm_log_activity($adminId, 'admin_tool_status', "{$tools[$key]['name']}: {$before} to {$status}");
                $msg = $tools[$key]['name'] . ' is now ' . strtoupper($status) . '.';
            }
        } elseif ($action === 'grant') {
            $email = trim(Security::clean($_POST['email'] ?? ''));
            $stmt = $pdo->prepare("SELECT user_id, full_name FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if (!$user) {
                $err = 'No account with that email.';
            } else {
                $pdo->prepare("
                    INSERT INTO tool_grants (tool_key, user_id, granted_by, note)
                    VALUES (?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE granted_by = VALUES(granted_by), note = VALUES(note)
                ")->execute([$key, (int) $user['user_id'], $adminId, Security::clean($_POST['grant_note'] ?? '') ?: null]);

                jm_log_activity($adminId, 'admin_tool_grant', "{$tools[$key]['name']} to {$email}");
                sendNotification(
                    (int) $user['user_id'],
                    'tool_access',
                    $tools[$key]['name'] . ' is open to you',
                    'You have early access while it is in beta. It is free to use for now.',
                    $tools[$key]['url'] ?: '/tools/'
                );
                $msg = $user['full_name'] . ' can now use ' . $tools[$key]['name'] . '.';
            }
        } elseif ($action === 'revoke') {
            $uid = (int) ($_POST['user_id'] ?? 0);
            $pdo->prepare("DELETE FROM tool_grants WHERE tool_key = ? AND user_id = ?")->execute([$key, $uid]);
            jm_log_activity($adminId, 'admin_tool_revoke', "{$tools[$key]['name']} from user {$uid}");
            $msg = 'Access removed.';
        }
    }
}

// Read after writing so the page always shows the current state.
$statuses = [];
foreach ($pdo->query("SELECT tool_key, status, updated_at FROM tool_flags") as $row) {
    $statuses[$row['tool_key']] = $row;
}

$grants = [];
$stmt = $pdo->query("
    SELECT g.tool_key, g.user_id, g.created_at, u.full_name, u.email
    FROM tool_grants g
    JOIN users u ON u.user_id = g.user_id
    ORDER BY g.created_at DESC
");
foreach ($stmt as $row) {
    $grants[$row['tool_key']][] = $row;
}

$openKey = (string) get('open', '');

$pageTitle = 'Tool Access - Admin';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="min-h-screen bg-slate-100 py-8">
    <div class="max-w-5xl mx-auto px-4">

        <div class="mb-8">
            <h1 class="text-3xl font-bold text-slate-900">Tool Access</h1>
            <p class="text-slate-600">Choose who can reach each tool while it is still in beta.</p>
        </div>

        <?php if ($msg): ?>
            <div class="mb-6 px-4 py-3 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm font-semibold"><?= e($msg) ?></div>
        <?php endif; ?>
        <?php if ($err): ?>
            <div class="mb-6 px-4 py-3 rounded-lg bg-red-50 border border-red-200 text-red-800 text-sm font-semibold"><?= e($err) ?></div>
        <?php endif; ?>

        <div class="mb-6 px-4 py-3 rounded-lg bg-white border border-slate-200 text-slate-600 text-sm">
            <span class="font-bold text-slate-900">On</span> is everyone signed in.
            <span class="font-bold text-slate-900">Beta</span> is only the people listed under that tool.
            <span class="font-bold text-slate-900">Off</span> is nobody.
            You keep access to everything either way.
        </div>

        <div class="space-y-4">
            <?php foreach ($tools as $key => $tool):
                $status = $statuses[$key]['status'] ?? 'on';
                $list = $grants[$key] ?? [];
                $isOpen = $openKey === $key;
                $tone = ['on' => 'emerald', 'beta' => 'amber', 'off' => 'slate'][$status];
            ?>
            <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
                <div class="p-5 flex flex-col md:flex-row md:items-center gap-4">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <h2 class="text-lg font-bold text-slate-900"><?= e($tool['name']) ?></h2>
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-<?= $tone ?>-100 text-<?= $tone ?>-800"><?= e($status) ?></span>
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-slate-100 text-slate-500"><?= e($tool['group']) ?></span>
                        </div>
                        <p class="text-xs text-slate-500 mt-1 font-mono"><?= e($tool['url']) ?></p>
                    </div>

                    <form method="post" class="flex items-center gap-2">
                        <?= Security::csrfField() ?>
                        <input type="hidden" name="action" value="set_status">
                        <input type="hidden" name="tool_key" value="<?= e($key) ?>">
                        <select name="status" class="px-3 py-2 rounded-lg border border-slate-200 bg-white text-sm font-semibold">
                            <option value="on"   <?= $status === 'on' ? 'selected' : '' ?>>On for everyone</option>
                            <option value="beta" <?= $status === 'beta' ? 'selected' : '' ?>>Beta, invited only</option>
                            <option value="off"  <?= $status === 'off' ? 'selected' : '' ?>>Off</option>
                        </select>
                        <button class="px-4 py-2 bg-slate-900 text-white rounded-lg font-bold text-sm" type="submit">Save</button>
                    </form>

                    <a href="?open=<?= e($isOpen ? '' : $key) ?>" class="text-sm font-bold text-blue-700 whitespace-nowrap">
                        <?= count($list) ?> invited<?= $isOpen ? ' (hide)' : '' ?>
                    </a>
                </div>

                <?php if ($isOpen): ?>
                <div class="border-t border-slate-200 bg-slate-50 p-5">
                    <form method="post" class="flex flex-col sm:flex-row gap-2 mb-4">
                        <?= Security::csrfField() ?>
                        <input type="hidden" name="action" value="grant">
                        <input type="hidden" name="tool_key" value="<?= e($key) ?>">
                        <input type="email" name="email" required placeholder="Their account email"
                               class="flex-1 px-3 py-2 rounded-lg border border-slate-200 bg-white text-sm">
                        <input type="text" name="grant_note" placeholder="Note, optional"
                               class="flex-1 px-3 py-2 rounded-lg border border-slate-200 bg-white text-sm">
                        <button class="px-4 py-2 bg-blue-700 text-white rounded-lg font-bold text-sm" type="submit">Invite</button>
                    </form>

                    <?php if (!$list): ?>
                        <p class="text-sm text-slate-500">Nobody invited yet. While this tool is in beta, only you can reach it.</p>
                    <?php else: ?>
                        <div class="jm-tablewrap"><table class="w-full text-sm jm-stacktable">
                            <thead class="bg-white text-slate-500 uppercase text-xs">
                                <tr>
                                    <th class="text-left px-3 py-2">Name</th>
                                    <th class="text-left px-3 py-2">Email</th>
                                    <th class="text-left px-3 py-2">Invited</th>
                                    <th class="px-3 py-2"></th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($list as $g): ?>
                                <tr class="border-t border-slate-200">
                                    <td class="px-3 py-2 font-semibold text-slate-900"><?= e($g['full_name']) ?></td>
                                    <td class="px-3 py-2 text-slate-600"><?= e($g['email']) ?></td>
                                    <td class="px-3 py-2 text-slate-500"><?= e(date('j M Y', strtotime($g['created_at']))) ?></td>
                                    <td class="px-3 py-2 text-right">
                                        <form method="post" onsubmit="return confirm('Remove access for <?= e($g['full_name']) ?>?');">
                                            <?= Security::csrfField() ?>
                                            <input type="hidden" name="action" value="revoke">
                                            <input type="hidden" name="tool_key" value="<?= e($key) ?>">
                                            <input type="hidden" name="user_id" value="<?= (int) $g['user_id'] ?>">
                                            <button class="text-red-700 font-bold" type="submit">Remove</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table></div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
