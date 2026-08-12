<?php
/**
 * JOBMINGTON - Admin: Activity
 *
 * Everything people do on the app, newest first, filterable by person, action
 * and date, with the same view downloadable as CSV.
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

$action  = trim((string) get('action_type', ''));
$who     = trim((string) get('q', ''));
$from    = trim((string) get('from', ''));
$to      = trim((string) get('to', ''));
$page    = max(1, (int) get('p', 1));
$perPage = 100;

// One filter definition, used by both the table and the CSV, so the download
// is always exactly what is on screen.
$where = [];
$args  = [];

if ($action !== '') {
    $where[] = 'a.action = ?';
    $args[] = $action;
}
if ($who !== '') {
    $where[] = '(u.email LIKE ? OR u.full_name LIKE ? OR a.details LIKE ?)';
    $args[] = "%{$who}%";
    $args[] = "%{$who}%";
    $args[] = "%{$who}%";
}
if ($from !== '' && strtotime($from)) {
    $where[] = 'a.created_at >= ?';
    $args[] = date('Y-m-d 00:00:00', strtotime($from));
}
if ($to !== '' && strtotime($to)) {
    $where[] = 'a.created_at <= ?';
    $args[] = date('Y-m-d 23:59:59', strtotime($to));
}

$sql = "FROM activity_logs a LEFT JOIN users u ON u.user_id = a.user_id";
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}

// ---- CSV ------------------------------------------------------------
if (get('export') === 'csv') {
    $stmt = $pdo->prepare("
        SELECT a.created_at, a.action, a.details, a.route, a.method, a.ip_address,
               u.full_name, u.email
        {$sql}
        ORDER BY a.created_at DESC
        LIMIT 10000
    ");
    $stmt->execute($args);

    // A leading =, +, - or @ makes Excel treat the cell as a formula. Details
    // come from user input, so they get quoted out.
    $safe = static function ($v): string {
        $v = (string) $v;
        return ($v !== '' && strpbrk($v[0], "=+-@") !== false) ? "'" . $v : $v;
    };

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="jobmington-activity-' . date('Y-m-d') . '.csv"');

    $out = fopen('php://output', 'w');
    fputcsv($out, ['When', 'Name', 'Email', 'Action', 'Details', 'Route', 'Method', 'IP']);
    foreach ($stmt as $r) {
        fputcsv($out, [
            $r['created_at'],
            $safe($r['full_name'] ?? ''),
            $safe($r['email'] ?? ''),
            $safe($r['action']),
            $safe($r['details'] ?? ''),
            $safe($r['route'] ?? ''),
            $r['method'] ?? '',
            $r['ip_address'] ?? '',
        ]);
    }
    fclose($out);
    exit;
}

// ---- table ----------------------------------------------------------
$countStmt = $pdo->prepare("SELECT COUNT(*) {$sql}");
$countStmt->execute($args);
$total = (int) $countStmt->fetchColumn();
$pages = max(1, (int) ceil($total / $perPage));
$page  = min($page, $pages);

$rowStmt = $pdo->prepare("
    SELECT a.*, u.full_name, u.email
    {$sql}
    ORDER BY a.created_at DESC
    LIMIT {$perPage} OFFSET " . (($page - 1) * $perPage) . "
");
$rowStmt->execute($args);
$rows = $rowStmt->fetchAll();

$actions = $pdo->query("SELECT action, COUNT(*) c FROM activity_logs GROUP BY action ORDER BY c DESC")->fetchAll();

$today = (int) $pdo->query("SELECT COUNT(*) FROM activity_logs WHERE created_at >= CURDATE()")->fetchColumn();
$people = (int) $pdo->query("SELECT COUNT(DISTINCT user_id) FROM activity_logs WHERE created_at >= CURDATE()")->fetchColumn();

$qs = static function (array $extra) use ($action, $who, $from, $to): string {
    return http_build_query(array_merge([
        'action_type' => $action, 'q' => $who, 'from' => $from, 'to' => $to,
    ], $extra));
};

$pageTitle = 'Activity - Admin';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="min-h-screen bg-slate-100 py-8">
    <div class="max-w-7xl mx-auto px-4">

        <div class="flex flex-col md:flex-row md:items-end justify-between gap-4 mb-8">
            <div>
                <h1 class="text-3xl font-bold text-slate-900">Activity</h1>
                <p class="text-slate-600"><?= number_format($today) ?> actions today from <?= number_format($people) ?> people.</p>
            </div>
            <a href="?<?= e($qs(['export' => 'csv'])) ?>" class="px-4 py-2 bg-slate-900 text-white rounded-lg font-bold text-sm self-start">Download CSV</a>
        </div>

        <form method="get" class="bg-white border border-slate-200 rounded-xl p-4 mb-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
            <input type="text" name="q" value="<?= e($who) ?>" placeholder="Name, email or detail"
                   class="px-3 py-2 rounded-lg border border-slate-200 text-sm lg:col-span-2">
            <select name="action_type" class="px-3 py-2 rounded-lg border border-slate-200 bg-white text-sm">
                <option value="">Every action</option>
                <?php foreach ($actions as $a): ?>
                    <option value="<?= e($a['action']) ?>" <?= $action === $a['action'] ? 'selected' : '' ?>>
                        <?= e($a['action']) ?> (<?= number_format($a['c']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
            <input type="date" name="from" value="<?= e($from) ?>" class="px-3 py-2 rounded-lg border border-slate-200 text-sm">
            <div class="flex gap-2">
                <input type="date" name="to" value="<?= e($to) ?>" class="flex-1 px-3 py-2 rounded-lg border border-slate-200 text-sm">
                <button class="px-4 py-2 bg-slate-900 text-white rounded-lg font-bold text-sm" type="submit">Filter</button>
            </div>
        </form>

        <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
            <?php if (!$rows): ?>
                <p class="p-8 text-center text-slate-500">Nothing recorded for that filter yet.</p>
            <?php else: ?>
            <div class="jm-tablewrap"><table class="w-full text-sm jm-densetable">
                <thead class="bg-slate-50 text-slate-500 uppercase text-xs">
                    <tr>
                        <th class="text-left px-4 py-3">When</th>
                        <th class="text-left px-4 py-3">Who</th>
                        <th class="text-left px-4 py-3">Action</th>
                        <th class="text-left px-4 py-3">Details</th>
                        <th class="text-left px-4 py-3 jm-col-optional">Route</th>
                        <th class="text-left px-4 py-3 jm-col-optional">IP</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr class="border-t border-slate-100">
                        <td class="px-4 py-3 text-slate-500"><?= e(date('j M, H:i', strtotime($r['created_at']))) ?></td>
                        <td class="px-4 py-3">
                            <?php if ($r['full_name']): ?>
                                <span class="font-semibold text-slate-900"><?= e($r['full_name']) ?></span>
                                <span class="block text-xs text-slate-500"><?= e($r['email']) ?></span>
                            <?php else: ?>
                                <span class="text-slate-400">Signed out</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-slate-100 text-slate-700"><?= e($r['action']) ?></span>
                        </td>
                        <td class="px-4 py-3 text-slate-600"><?= e($r['details'] ?? '') ?></td>
                        <td class="px-4 py-3 text-slate-400 text-xs font-mono jm-col-optional"><?= e($r['route'] ?? '') ?></td>
                        <td class="px-4 py-3 text-slate-400 text-xs font-mono jm-col-optional"><?= e($r['ip_address'] ?? '') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>

            <?php if ($pages > 1): ?>
            <div class="flex items-center justify-between px-4 py-3 border-t border-slate-200 text-sm flex-wrap gap-3">
                <span class="text-slate-500"><?= number_format($total) ?> records, page <?= $page ?> of <?= $pages ?></span>
                <div class="flex gap-2">
                    <?php if ($page > 1): ?>
                        <a href="?<?= e($qs(['p' => $page - 1])) ?>" class="px-3 py-1.5 rounded-lg border border-slate-200 font-bold">Previous</a>
                    <?php endif; ?>
                    <?php if ($page < $pages): ?>
                        <a href="?<?= e($qs(['p' => $page + 1])) ?>" class="px-3 py-1.5 rounded-lg border border-slate-200 font-bold">Next</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
