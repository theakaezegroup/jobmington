<?php
/**
 * JOBMINGTON - Admin: who, not how many
 *
 * Every count in this panel used to be a dead number. This is the page behind
 * them: who viewed a course, who enrolled, who downloaded an ebook, who read a
 * topic, with the same view downloadable as CSV.
 *
 * One page for every content type rather than one per thing. The question is
 * identical in each case, only the table it reads from changes, and five
 * near-identical pages would drift apart the way the two tool catalogues did.
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

/**
 * What each content type is, where its title comes from, and which actions are
 * worth asking about. `sql` returns rows of person + when + optional detail.
 */
$types = [
    'course' => [
        'label'  => 'Course',
        'table'  => 'courses',
        'id'     => 'course_id',
        'title'  => 'title',
        'back'   => '/jobmington/admin/courses.php',
        'actions' => [
            'enrolled' => [
                'label' => 'Enrolled',
                'sql'   => "SELECT e.user_id, e.started_at AS at, CONCAT(e.progress, '% complete') AS detail
                            FROM course_enrollments e WHERE e.course_id = :id",
            ],
            'viewed' => [
                'label' => 'Viewed',
                'sql'   => "SELECT v.user_id, v.created_at AS at, v.ip_address AS detail
                            FROM content_views v WHERE v.content_type = 'course' AND v.content_id = :id",
            ],
        ],
    ],
    'ebook' => [
        'label'  => 'Ebook',
        'table'  => 'ebooks',
        'id'     => 'ebook_id',
        'title'  => 'title',
        'back'   => '/jobmington/admin/ebooks.php',
        'actions' => [
            'downloaded' => [
                'label' => 'Downloaded',
                'sql'   => "SELECT p.user_id, p.created_at AS at, p.method AS detail
                            FROM ebook_purchases p WHERE p.ebook_id = :id",
            ],
            'viewed' => [
                'label' => 'Viewed',
                'sql'   => "SELECT v.user_id, v.created_at AS at, v.ip_address AS detail
                            FROM content_views v WHERE v.content_type = 'ebook' AND v.content_id = :id",
            ],
        ],
    ],
    'forum_topic' => [
        'label'  => 'Topic',
        'table'  => 'forum_topics',
        'id'     => 'topic_id',
        'title'  => 'title',
        'back'   => '/jobmington/admin/forum.php',
        'actions' => [
            'viewed' => [
                'label' => 'Viewed',
                'sql'   => "SELECT v.user_id, v.created_at AS at, v.ip_address AS detail
                            FROM content_views v WHERE v.content_type = 'forum_topic' AND v.content_id = :id",
            ],
            'replied' => [
                'label' => 'Replied',
                'sql'   => "SELECT r.user_id, r.created_at AS at, NULL AS detail
                            FROM forum_replies r WHERE r.topic_id = :id",
            ],
        ],
    ],
    'blog_post' => [
        'label'  => 'Post',
        'table'  => 'blog_posts',
        'id'     => 'post_id',
        'title'  => 'title',
        'back'   => '/jobmington/admin/blog.php',
        'actions' => [
            'viewed' => [
                'label' => 'Viewed',
                'sql'   => "SELECT v.user_id, v.created_at AS at, v.ip_address AS detail
                            FROM content_views v WHERE v.content_type = 'blog_post' AND v.content_id = :id",
            ],
        ],
    ],
];

$type = (string) get('type', '');
$id   = (int) get('id', 0);

if (!isset($types[$type]) || $id <= 0) {
    header('Location: ' . SITE_URL . '/admin/');
    exit;
}

$spec = $types[$type];
$action = (string) get('action', '');
if (!isset($spec['actions'][$action])) {
    $action = array_key_first($spec['actions']);
}

$item = $pdo->prepare("SELECT `{$spec['id']}` AS id, `{$spec['title']}` AS title FROM `{$spec['table']}` WHERE `{$spec['id']}` = ? LIMIT 1");
$item->execute([$id]);
$item = $item->fetch(PDO::FETCH_ASSOC);

if (!$item) {
    header('Location: ' . $spec['back']);
    exit;
}

/**
 * Wrap an action's query so it always comes back as person + when + detail.
 * A signed-out view has no user, so the join is a left one and the name falls
 * back to the word Visitor rather than an empty cell.
 */
$fetch = static function (string $sql, int $id, PDO $pdo, string $search = '', int $limit = 0, int $offset = 0): array {
    $where = $search !== '' ? "WHERE (u.full_name LIKE :s OR u.email LIKE :s)" : '';
    $page = $limit > 0 ? "LIMIT {$limit} OFFSET {$offset}" : '';

    $stmt = $pdo->prepare("
        SELECT x.user_id, x.at, x.detail, u.full_name, u.email
        FROM ({$sql}) x
        LEFT JOIN users u ON u.user_id = x.user_id
        {$where}
        ORDER BY x.at DESC
        {$page}
    ");
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    if ($search !== '') {
        $stmt->bindValue(':s', '%' . $search . '%');
    }
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
};

$q       = trim((string) get('q', ''));
$page    = max(1, (int) get('p', 1));
$perPage = 100;
$sql     = $spec['actions'][$action]['sql'];

// ---- CSV ------------------------------------------------------------
if (get('export') === 'csv') {
    $rows = $fetch($sql, $id, $pdo, $q);

    // A leading =, +, - or @ makes a spreadsheet treat the cell as a formula.
    $safe = static function ($v): string {
        $v = (string) $v;
        return ($v !== '' && strpbrk($v[0], "=+-@") !== false) ? "'" . $v : $v;
    };

    $file = strtolower($action) . '-' . preg_replace('/[^a-z0-9\-]+/i', '-', (string) $item['title']) . '-' . date('Y-m-d') . '.csv';

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $file . '"');
    header('Cache-Control: no-store');

    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Name', 'Email', 'When', 'Detail']);
    foreach ($rows as $r) {
        fputcsv($out, [
            $safe($r['full_name'] ?? 'Signed-out visitor'),
            $safe($r['email'] ?? ''),
            $r['at'],
            $safe($r['detail'] ?? ''),
        ]);
    }
    fclose($out);

    jm_log_activity((int) Session::userId(), 'admin_export_audience', $spec['label'] . ' ' . $action . ': ' . $item['title']);
    exit;
}

// ---- table ----------------------------------------------------------
$all   = $fetch($sql, $id, $pdo, $q);
$total = count($all);
$pages = max(1, (int) ceil($total / $perPage));
$page  = min($page, $pages);
$rows  = array_slice($all, ($page - 1) * $perPage, $perPage);

// Counts for the tabs, so you can see at a glance which action has anyone.
$counts = [];
foreach ($spec['actions'] as $key => $def) {
    $counts[$key] = count($fetch($def['sql'], $id, $pdo));
}
$known = count(array_filter($all, static fn($r) => !empty($r['user_id'])));

$qs = static function (array $extra) use ($type, $id, $action, $q): string {
    return http_build_query(array_merge(['type' => $type, 'id' => $id, 'action' => $action, 'q' => $q], $extra));
};

$pageTitle = $spec['actions'][$action]['label'] . ' - ' . $item['title'];
require_once __DIR__ . '/../includes/header.php';
?>

<div class="min-h-screen bg-slate-100 py-8">
    <div class="max-w-7xl mx-auto px-4">

        <div class="flex flex-col md:flex-row md:items-end justify-between gap-4 mb-8">
            <div class="min-w-0">
                <a href="<?= e($spec['back']) ?>" class="text-slate-500 hover:text-slate-900 text-xs font-bold uppercase tracking-widest"><?= e($spec['label']) ?>s</a>
                <h1 class="text-3xl font-bold text-slate-900 mt-2"><?= e($item['title']) ?></h1>
                <p class="text-slate-600"><?= number_format($total) ?> <?= e(strtolower($spec['actions'][$action]['label'])) ?>, <?= number_format($known) ?> with an account.</p>
            </div>
            <a href="?<?= e($qs(['export' => 'csv'])) ?>" class="px-4 py-2 bg-slate-900 text-white rounded-lg font-bold text-sm self-start whitespace-nowrap">Download CSV</a>
        </div>

        <div class="flex gap-2 mb-6 flex-wrap">
            <?php foreach ($spec['actions'] as $key => $def): ?>
                <a href="?<?= e(http_build_query(['type' => $type, 'id' => $id, 'action' => $key])) ?>"
                   class="px-4 py-2 rounded-lg text-sm font-bold border <?= $key === $action ? 'bg-slate-900 text-white border-slate-900' : 'bg-white text-slate-700 border-slate-200' ?>">
                    <?= e($def['label']) ?> <span class="opacity-70">(<?= number_format($counts[$key]) ?>)</span>
                </a>
            <?php endforeach; ?>
        </div>

        <form method="get" class="bg-white border border-slate-200 rounded-xl p-4 mb-6 flex flex-col sm:flex-row gap-3">
            <input type="hidden" name="type" value="<?= e($type) ?>">
            <input type="hidden" name="id" value="<?= (int) $id ?>">
            <input type="hidden" name="action" value="<?= e($action) ?>">
            <input type="text" name="q" value="<?= e($q) ?>" placeholder="Search name or email"
                   class="flex-1 px-3 py-2 rounded-lg border border-slate-200 text-sm">
            <button class="px-4 py-2 bg-slate-900 text-white rounded-lg font-bold text-sm" type="submit">Search</button>
            <?php if ($q !== ''): ?>
                <a href="?<?= e(http_build_query(['type' => $type, 'id' => $id, 'action' => $action])) ?>" class="px-4 py-2 rounded-lg border border-slate-200 font-bold text-sm text-center">Clear</a>
            <?php endif; ?>
        </form>

        <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
            <?php if (!$rows): ?>
                <p class="p-8 text-center text-slate-500">
                    <?= $q !== '' ? 'Nobody matches that search.' : 'Nobody yet.' ?>
                </p>
            <?php else: ?>
            <div class="jm-tablewrap"><table class="w-full text-sm jm-stacktable">
                <thead class="bg-slate-50 text-slate-500 uppercase text-xs">
                    <tr>
                        <th class="text-left px-4 py-3">Person</th>
                        <th class="text-left px-4 py-3">Email</th>
                        <th class="text-left px-4 py-3">When</th>
                        <th class="text-left px-4 py-3">Detail</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr class="border-t border-slate-100">
                        <td class="px-4 py-3">
                            <?php if (!empty($r['full_name'])): ?>
                                <span class="font-semibold text-slate-900"><?= e($r['full_name']) ?></span>
                            <?php else: ?>
                                <span class="text-slate-400">Signed-out visitor</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3 text-slate-600"><?= e($r['email'] ?? '') ?></td>
                        <td class="px-4 py-3 text-slate-500 whitespace-nowrap"><?= e(date('j M Y, H:i', strtotime((string) $r['at']))) ?></td>
                        <td class="px-4 py-3 text-slate-500"><?= e((string) ($r['detail'] ?? '')) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>

            <?php if ($pages > 1): ?>
            <div class="flex items-center justify-between px-4 py-3 border-t border-slate-200 text-sm">
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
