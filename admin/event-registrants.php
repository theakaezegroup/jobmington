<?php
/**
 * JOBMINGTON - Admin: Registrants for one event
 *
 * The panel on the events page is a peek. This is the list: searchable,
 * paginated, showing whether each person has had their reminders, with the
 * same view downloadable as CSV.
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

$eventId = (int) get('event_id', 0);
$q       = trim((string) get('q', ''));
$page    = max(1, (int) get('p', 1));
$perPage = 100;

$ev = $pdo->prepare("SELECT event_id, title, slug, starts_at FROM events WHERE event_id = ? LIMIT 1");
$ev->execute([$eventId]);
$event = $ev->fetch(PDO::FETCH_ASSOC);

if (!$event) {
    header('Location: ' . SITE_URL . '/admin/events.php');
    exit;
}

// One filter, shared by the table and the CSV, so the download always matches
// what is on screen.
$where = ['r.event_id = ?'];
$args  = [$eventId];

if ($q !== '') {
    $where[] = '(r.name LIKE ? OR r.email LIKE ?)';
    $args[] = "%{$q}%";
    $args[] = "%{$q}%";
}

$sql = "
    FROM event_registrations r
    LEFT JOIN users u ON u.user_id = r.user_id
    LEFT JOIN countries c ON c.country_id = u.country_id
    WHERE " . implode(' AND ', $where);

// ---- CSV ------------------------------------------------------------
// Fields beginning with =, +, - or @ are prefixed with an apostrophe: a
// spreadsheet treats those as formulas, so a name like "=cmd|..." would
// execute on open. The prefix is invisible in the cell and defuses it.
if (get('export') === 'csv') {
    $rs = $pdo->prepare("
        SELECT r.name, r.email, r.registered_at, r.reminder_24h_at, r.reminder_1h_at,
               u.phone, c.name AS country, u.user_id
        {$sql}
        ORDER BY r.registered_at ASC
    ");
    $rs->execute($args);

    $safe = static function ($v): string {
        $v = (string) $v;
        return ($v !== '' && strpbrk($v[0], "=+-@") !== false) ? "'" . $v : $v;
    };

    $file = 'registrants-' . preg_replace('/[^a-z0-9\-]+/i', '-', (string) $event['slug'])
          . '-' . date('Y-m-d') . '.csv';

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $file . '"');
    header('Cache-Control: no-store');

    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");   // BOM, so Excel reads accents correctly
    fputcsv($out, ['Name', 'Email', 'Phone', 'Country', 'Has account', 'Registered at', 'Reminded 24h', 'Reminded 1h']);
    while ($r = $rs->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($out, [
            $safe($r['name']), $safe($r['email']), $safe($r['phone']), $safe($r['country']),
            $r['user_id'] ? 'Yes' : 'No',
            $r['registered_at'], $r['reminder_24h_at'] ?: '', $r['reminder_1h_at'] ?: '',
        ]);
    }
    fclose($out);

    jm_log_activity((int) Session::userId(), 'admin_export_registrants', $event['title']);
    exit;
}

// ---- table ----------------------------------------------------------
$countStmt = $pdo->prepare("SELECT COUNT(*) {$sql}");
$countStmt->execute($args);
$total = (int) $countStmt->fetchColumn();
$pages = max(1, (int) ceil($total / $perPage));
$page  = min($page, $pages);

$rowStmt = $pdo->prepare("
    SELECT r.*, u.phone, u.user_id AS account_id, c.name AS country
    {$sql}
    ORDER BY r.registered_at DESC
    LIMIT {$perPage} OFFSET " . (($page - 1) * $perPage) . "
");
$rowStmt->execute($args);
$rows = $rowStmt->fetchAll(PDO::FETCH_ASSOC);

$stats = $pdo->prepare("
    SELECT COUNT(*) total,
           SUM(user_id IS NOT NULL) with_account,
           SUM(reminder_24h_at IS NOT NULL) got_24h,
           SUM(reminder_1h_at IS NOT NULL) got_1h
    FROM event_registrations WHERE event_id = ?
");
$stats->execute([$eventId]);
$s = $stats->fetch(PDO::FETCH_ASSOC);

$pageTitle = 'Registrants - ' . $event['title'];
require_once __DIR__ . '/../includes/header.php';
?>

<div class="min-h-screen bg-slate-100 py-8">
    <div class="max-w-7xl mx-auto px-4">

        <div class="flex flex-col md:flex-row md:items-end justify-between gap-4 mb-8">
            <div class="min-w-0">
                <a href="/jobmington/admin/events.php" class="text-slate-500 hover:text-slate-900 text-xs font-bold uppercase tracking-widest">Events &amp; Webinars</a>
                <h1 class="text-3xl font-bold text-slate-900 mt-2"><?= e($event['title']) ?></h1>
                <p class="text-slate-600"><?= e(date('l j F Y, H:i', strtotime($event['starts_at']))) ?></p>
            </div>
            <a href="?event_id=<?= (int) $eventId ?>&q=<?= e(urlencode($q)) ?>&export=csv"
               class="px-4 py-2 bg-slate-900 text-white rounded-lg font-bold text-sm self-start whitespace-nowrap">Download CSV</a>
        </div>

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
            <?php foreach ([
                ['Registered', $s['total']],
                ['With an account', $s['with_account']],
                ['Had 24h reminder', $s['got_24h']],
                ['Had 1h reminder', $s['got_1h']],
            ] as [$label, $value]): ?>
            <div class="bg-white border border-slate-200 rounded-xl p-4">
                <p class="text-2xl font-bold text-slate-900"><?= number_format((int) $value) ?></p>
                <p class="text-xs text-slate-500 uppercase tracking-wider font-bold mt-1"><?= e($label) ?></p>
            </div>
            <?php endforeach; ?>
        </div>

        <form method="get" class="bg-white border border-slate-200 rounded-xl p-4 mb-6 flex flex-col sm:flex-row gap-3">
            <input type="hidden" name="event_id" value="<?= (int) $eventId ?>">
            <input type="text" name="q" value="<?= e($q) ?>" placeholder="Search name or email"
                   class="flex-1 px-3 py-2 rounded-lg border border-slate-200 text-sm">
            <button class="px-4 py-2 bg-slate-900 text-white rounded-lg font-bold text-sm" type="submit">Search</button>
            <?php if ($q !== ''): ?>
                <a href="?event_id=<?= (int) $eventId ?>" class="px-4 py-2 rounded-lg border border-slate-200 font-bold text-sm text-center">Clear</a>
            <?php endif; ?>
        </form>

        <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
            <?php if (!$rows): ?>
                <p class="p-8 text-center text-slate-500">
                    <?= $q !== '' ? 'Nobody matches that search.' : 'Nobody has registered for this one yet.' ?>
                </p>
            <?php else: ?>
            <div class="jm-tablewrap"><table class="w-full text-sm jm-stacktable">
                <thead class="bg-slate-50 text-slate-500 uppercase text-xs">
                    <tr>
                        <th class="text-left px-4 py-3">Name</th>
                        <th class="text-left px-4 py-3">Email</th>
                        <th class="text-left px-4 py-3">Phone</th>
                        <th class="text-left px-4 py-3">Country</th>
                        <th class="text-left px-4 py-3">Account</th>
                        <th class="text-left px-4 py-3">Registered</th>
                        <th class="text-left px-4 py-3">Reminders</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr class="border-t border-slate-100">
                        <td class="px-4 py-3 font-semibold text-slate-900"><?= e($r['name']) ?></td>
                        <td data-label="Email" class="px-4 py-3 text-slate-600"><?= e($r['email']) ?></td>
                        <td data-label="Phone" class="px-4 py-3 text-slate-600"><?= e($r['phone'] ?? '') ?></td>
                        <td data-label="Country" class="px-4 py-3 text-slate-600"><?= e($r['country'] ?? '') ?></td>
                        <td data-label="Account" class="px-4 py-3">
                            <?php if ($r['account_id']): ?>
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-emerald-100 text-emerald-800">Yes</span>
                            <?php else: ?>
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-slate-100 text-slate-500">Guest</span>
                            <?php endif; ?>
                        </td>
                        <td data-label="Registered" class="px-4 py-3 text-slate-500 whitespace-nowrap"><?= e(date('j M, H:i', strtotime($r['registered_at']))) ?></td>
                        <td data-label="Reminders" class="px-4 py-3 text-xs whitespace-nowrap">
                            <span class="<?= $r['reminder_24h_at'] ? 'text-emerald-700 font-bold' : 'text-slate-400' ?>">24h</span>
                            <span class="text-slate-300">/</span>
                            <span class="<?= $r['reminder_1h_at'] ? 'text-emerald-700 font-bold' : 'text-slate-400' ?>">1h</span>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>

            <?php if ($pages > 1): ?>
            <div class="flex items-center justify-between px-4 py-3 border-t border-slate-200 text-sm">
                <span class="text-slate-500"><?= number_format($total) ?> people, page <?= $page ?> of <?= $pages ?></span>
                <div class="flex gap-2">
                    <?php if ($page > 1): ?>
                        <a href="?event_id=<?= (int) $eventId ?>&q=<?= e(urlencode($q)) ?>&p=<?= $page - 1 ?>" class="px-3 py-1.5 rounded-lg border border-slate-200 font-bold">Previous</a>
                    <?php endif; ?>
                    <?php if ($page < $pages): ?>
                        <a href="?event_id=<?= (int) $eventId ?>&q=<?= e(urlencode($q)) ?>&p=<?= $page + 1 ?>" class="px-3 py-1.5 rounded-lg border border-slate-200 font-bold">Next</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
