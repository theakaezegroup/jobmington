<?php
/**
 * JOBMINGTON - Admin: Events / Webinars management
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

/*
 * CSV export of an event's registrants.
 *
 * Runs before any output so the download headers are the first thing sent.
 * Fields that begin with =, +, - or @ are prefixed with an apostrophe: a
 * spreadsheet treats those as formulas, so a name like "=cmd|..." would
 * execute on open. The prefix is invisible in the cell and defuses it.
 */
if (isset($_GET['export'])) {
    $exportId = (int) $_GET['export'];
    $ev = $pdo->prepare("SELECT title, slug, starts_at FROM events WHERE event_id = ? LIMIT 1");
    $ev->execute([$exportId]);
    $evRow = $ev->fetch(PDO::FETCH_ASSOC);

    if ($evRow) {
        $rs = $pdo->prepare("
            SELECT r.name, r.email, r.registered_at, r.reminder_24h_at, r.reminder_1h_at,
                   u.phone, u.city
            FROM event_registrations r
            LEFT JOIN users u ON u.user_id = r.user_id
            WHERE r.event_id = ?
            ORDER BY r.registered_at ASC
        ");
        $rs->execute([$exportId]);

        $safe = static function ($v): string {
            $v = (string) $v;
            return ($v !== '' && strpbrk($v[0], "=+-@") !== false) ? "'" . $v : $v;
        };

        $file = 'registrants-' . preg_replace('/[^a-z0-9\-]+/i', '-', (string) $evRow['slug'])
              . '-' . date('Y-m-d') . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $file . '"');
        header('Cache-Control: no-store');

        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");   // BOM, so Excel reads accents correctly
        fputcsv($out, ['Name', 'Email', 'Phone', 'City', 'Registered at', 'Reminded 24h', 'Reminded 1h']);
        while ($r = $rs->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($out, [
                $safe($r['name']), $safe($r['email']), $safe($r['phone']), $safe($r['city']),
                $r['registered_at'], $r['reminder_24h_at'] ?: '', $r['reminder_1h_at'] ?: '',
            ]);
        }
        fclose($out);
        exit;
    }
}
$pdo = db();

$msg = '';
$err = '';

function jm_event_slug(PDO $pdo, string $title, int $ignoreId = 0): string {
    $base = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $title), '-')) ?: 'event';
    $slug = $base;
    for ($i = 2; $i < 50; $i++) {
        $stmt = $pdo->prepare("SELECT event_id FROM events WHERE slug = ? AND event_id <> ? LIMIT 1");
        $stmt->execute([$slug, $ignoreId]);
        if (!$stmt->fetchColumn()) break;
        $slug = $base . '-' . $i;
    }
    return $slug;
}

function jm_event_cover(): ?string {
    if (empty($_FILES['cover_image']['name']) || $_FILES['cover_image']['error'] !== UPLOAD_ERR_OK) return null;
    $ext = strtolower(pathinfo($_FILES['cover_image']['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg','jpeg','png','webp'], true)) return null;
    $dir = dirname(__DIR__) . '/uploads/events';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $name = bin2hex(random_bytes(8)) . '.' . $ext;
    if (move_uploaded_file($_FILES['cover_image']['tmp_name'], $dir . '/' . $name)) {
        return '/jobmington/uploads/events/' . $name;
    }
    return null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::verifyCSRF()) {
        $err = 'Security check failed. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';
        try {
            if ($action === 'save') {
                $id          = (int) ($_POST['event_id'] ?? 0);
                $title       = trim($_POST['title'] ?? '');
                $type        = in_array($_POST['event_type'] ?? '', ['webinar','workshop','meetup','conference'], true) ? $_POST['event_type'] : 'webinar';
                $description = trim($_POST['description'] ?? '');
                $host        = trim($_POST['host_name'] ?? '');
                $startsAt    = trim($_POST['starts_at'] ?? '');
                $endsAt      = trim($_POST['ends_at'] ?? '');
                $isOnline    = isset($_POST['is_online']) ? 1 : 0;
                $location    = trim($_POST['location'] ?? '');
                $meetingUrl  = trim($_POST['meeting_url'] ?? '');
                $capacity    = (int) ($_POST['capacity'] ?? 0);
                $isFree      = isset($_POST['is_free']) ? 1 : 0;
                $price       = (float) ($_POST['price'] ?? 0);
                $isPublished = isset($_POST['is_published']) ? 1 : 0;
                $isFeatured  = isset($_POST['is_featured']) ? 1 : 0;

                if ($title === '' || $startsAt === '') {
                    $err = 'Title and start date/time are required.';
                } else {
                    $startsAt = date('Y-m-d H:i:s', strtotime($startsAt));
                    $endsAt   = $endsAt !== '' ? date('Y-m-d H:i:s', strtotime($endsAt)) : null;
                    $cover    = jm_event_cover();

                    if ($id > 0) {
                        $slug = jm_event_slug($pdo, $title, $id);
                        $sql = "UPDATE events SET title=?, slug=?, event_type=?, description=?, host_name=?, starts_at=?, ends_at=?, is_online=?, location=?, meeting_url=?, capacity=?, is_free=?, price=?, is_published=?, is_featured=?"
                             . ($cover ? ", cover_image=?" : "") . " WHERE event_id=?";
                        $params = [$title,$slug,$type,$description,$host,$startsAt,$endsAt,$isOnline,$location,$meetingUrl,$capacity,$isFree,$price,$isPublished,$isFeatured];
                        if ($cover) $params[] = $cover;
                        $params[] = $id;
                        $pdo->prepare($sql)->execute($params);
                        $msg = 'Event updated.';
                    } else {
                        $slug = jm_event_slug($pdo, $title);
                        $pdo->prepare("INSERT INTO events (title, slug, event_type, description, host_name, starts_at, ends_at, is_online, location, meeting_url, capacity, is_free, price, is_published, is_featured, cover_image) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
                            ->execute([$title,$slug,$type,$description,$host,$startsAt,$endsAt,$isOnline,$location,$meetingUrl,$capacity,$isFree,$price,$isPublished,$isFeatured,$cover]);
                        $msg = 'Event created.';
                    }
                }
            } elseif ($action === 'delete') {
                $pdo->prepare("DELETE FROM events WHERE event_id = ?")->execute([(int) $_POST['event_id']]);
                $msg = 'Event deleted.';
            } elseif ($action === 'toggle') {
                $pdo->prepare("UPDATE events SET is_published = 1 - is_published WHERE event_id = ?")->execute([(int) $_POST['event_id']]);
                $msg = 'Visibility updated.';
            }
        } catch (Throwable $e) {
            $err = 'Error: ' . $e->getMessage();
        }
    }
    Security::regenerateCSRF();
}

$editing = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM events WHERE event_id = ?");
    $stmt->execute([(int) $_GET['edit']]);
    $editing = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

$events = $pdo->query("SELECT * FROM events ORDER BY starts_at DESC")->fetchAll(PDO::FETCH_ASSOC);
$viewId = (int) ($_GET['registrants'] ?? 0);
$viewEvent = null;
$registrants = [];
if ($viewId > 0) {
    try {
        $q = $pdo->prepare("SELECT * FROM events WHERE event_id = ? LIMIT 1");
        $q->execute([$viewId]);
        $viewEvent = $q->fetch(PDO::FETCH_ASSOC) ?: null;

        $q = $pdo->prepare("SELECT r.*, u.phone FROM event_registrations r
                            LEFT JOIN users u ON u.user_id = r.user_id
                            WHERE r.event_id = ? ORDER BY r.registered_at ASC");
        $q->execute([$viewId]);
        $registrants = $q->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $viewEvent = null; $registrants = [];
    }
}

$pageTitle = 'Events - ' . SITE_NAME;
require_once __DIR__ . '/../includes/header.php';
?>
<style>
.jm-ev-wrap { display:grid; grid-template-columns: 380px 1fr; gap:20px; align-items:start; }
@media (max-width:1000px){ .jm-ev-wrap { grid-template-columns:1fr; } }
.jm-ev-card { background:#fff; border:1px solid #e4eaf3; border-radius:12px; padding:20px; }
.jm-ev-card h2 { margin:0 0 14px; font-size:15px; font-weight:800; color:#0b1b33; }
.jm-ev-field { margin-bottom:12px; }
.jm-ev-field label { display:block; font-size:12px; font-weight:700; color:#5b6b82; margin-bottom:5px; }
.jm-ev-field input, .jm-ev-field textarea, .jm-ev-field select { width:100%; box-sizing:border-box; border:1px solid #d8e4f4; border-radius:8px; padding:9px 11px; font:inherit; font-size:13px; background:#fbfdff; }
.jm-ev-field textarea { min-height:80px; resize:vertical; }
.jm-ev-row2 { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
@media (max-width:560px){ .jm-ev-row2 { grid-template-columns:1fr; } }
.jm-ev-checks { display:flex; gap:16px; flex-wrap:wrap; margin:6px 0 14px; }
.jm-ev-checks label { display:flex; align-items:center; gap:6px; font-size:13px; font-weight:600; color:#0b1b33; }
.jm-ev-table { width:100%; border-collapse:collapse; background:#fff; border:1px solid #e4eaf3; border-radius:12px; overflow:hidden; }
.jm-ev-table th { text-align:left; font-size:11px; text-transform:uppercase; letter-spacing:.06em; color:#5b6b82; padding:11px 14px; background:#fafbfd; border-bottom:1px solid #e4eaf3; }
.jm-ev-table td { padding:11px 14px; border-bottom:1px solid #f0f4f9; font-size:13px; color:#0b1b33; }
.jm-ev-pill { font-size:10px; font-weight:800; text-transform:uppercase; padding:2px 8px; border-radius:99px; }
.jm-ev-pill.on { background:#e6f5f1; color:#0a6454; } .jm-ev-pill.off { background:#f0f3f8; color:#475569; }
.jm-ev-actions { display:flex; gap:6px; flex-wrap:wrap; }
.jm-ev-actions button, .jm-ev-actions a { font-size:11px; font-weight:700; padding:5px 9px; border-radius:6px; border:1px solid #d8e4f4; background:#fff; color:#0640a3; cursor:pointer; text-decoration:none; }
.jm-ev-msg { padding:11px 14px; border-radius:8px; margin-bottom:16px; font-size:13px; font-weight:600; }
.jm-ev-msg.ok { background:#e6f5f1; color:#0a6454; } .jm-ev-msg.err { background:#fdecea; color:#991b1b; }
.jm-ev-btn { background:#0640a3; color:#fff; border:0; border-radius:8px; padding:10px 16px; font:inherit; font-size:13px; font-weight:700; cursor:pointer; width:100%; }
</style>

<div class="ja-pagehead"><div><h1>Events &amp; Webinars</h1><p>Schedule and manage upcoming sessions.</p></div>
<a class="ja-statuschip" href="/jobmington/events/" target="_blank">View public page</a></div>

<?php if ($msg): ?><div class="jm-ev-msg ok"><?= e($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="jm-ev-msg err"><?= e($err) ?></div><?php endif; ?>

<div class="jm-ev-wrap">
    <form class="jm-ev-card" method="post" enctype="multipart/form-data">
        <h2><?= $editing ? 'Edit event' : 'Add event' ?></h2>
        <?= Security::csrfField() ?>
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="event_id" value="<?= (int) ($editing['event_id'] ?? 0) ?>">
        <div class="jm-ev-field"><label>Title *</label><input type="text" name="title" value="<?= e($editing['title'] ?? '') ?>" required></div>
        <div class="jm-ev-row2">
            <div class="jm-ev-field"><label>Type</label><select name="event_type">
                <?php foreach (['webinar','workshop','meetup','conference'] as $t): ?>
                    <option value="<?= $t ?>" <?= ($editing['event_type'] ?? 'webinar') === $t ? 'selected' : '' ?>><?= ucfirst($t) ?></option>
                <?php endforeach; ?>
            </select></div>
            <div class="jm-ev-field"><label>Host</label><input type="text" name="host_name" value="<?= e($editing['host_name'] ?? '') ?>"></div>
        </div>
        <div class="jm-ev-row2">
            <div class="jm-ev-field"><label>Starts *</label><input type="datetime-local" name="starts_at" value="<?= $editing ? date('Y-m-d\TH:i', strtotime($editing['starts_at'])) : '' ?>" required></div>
            <div class="jm-ev-field"><label>Ends</label><input type="datetime-local" name="ends_at" value="<?= ($editing && $editing['ends_at']) ? date('Y-m-d\TH:i', strtotime($editing['ends_at'])) : '' ?>"></div>
        </div>
        <div class="jm-ev-field"><label>Description</label><textarea name="description"><?= e($editing['description'] ?? '') ?></textarea></div>
        <div class="jm-ev-field"><label>Location (or "Online")</label><input type="text" name="location" value="<?= e($editing['location'] ?? '') ?>"></div>
        <div class="jm-ev-field"><label>Meeting / join URL</label><input type="text" name="meeting_url" value="<?= e($editing['meeting_url'] ?? '') ?>"></div>
        <div class="jm-ev-row2">
            <div class="jm-ev-field"><label>Capacity (0 = unlimited)</label><input type="number" name="capacity" value="<?= (int) ($editing['capacity'] ?? 0) ?>"></div>
            <div class="jm-ev-field"><label>Price (₦)</label><input type="number" step="0.01" name="price" value="<?= e($editing['price'] ?? '0') ?>"></div>
        </div>
        <div class="jm-ev-field"><label>Cover image</label><input type="file" name="cover_image" accept="image/*"></div>
        <div class="jm-ev-checks">
            <label><input type="checkbox" name="is_online" <?= (!$editing || $editing['is_online']) ? 'checked' : '' ?>> Online</label>
            <label><input type="checkbox" name="is_free" <?= (!$editing || $editing['is_free']) ? 'checked' : '' ?>> Free</label>
            <label><input type="checkbox" name="is_published" <?= (!$editing || $editing['is_published']) ? 'checked' : '' ?>> Published</label>
            <label><input type="checkbox" name="is_featured" <?= ($editing && $editing['is_featured']) ? 'checked' : '' ?>> Featured</label>
        </div>
        <button class="jm-ev-btn" type="submit"><?= $editing ? 'Save changes' : 'Add event' ?></button>
        <?php if ($editing): ?><div style="text-align:center;margin-top:10px;"><a href="/jobmington/admin/events.php" style="font-size:12px;color:#5b6b82;">Cancel edit</a></div><?php endif; ?>
    </form>

    <div>
        <?php if ($viewEvent): ?>
            <div class="jm-ev-card" style="margin-bottom:18px;">
                <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:4px;">
                    <h2 style="margin:0;">Registrants &mdash; <?= e($viewEvent['title']) ?></h2>
                    <div style="display:flex;gap:8px;align-items:center;">
                        <?php if ($registrants): ?>
                            <a class="jm-ev-btn" style="width:auto;display:inline-block;text-decoration:none;padding:8px 14px;"
                               href="?export=<?= (int)$viewId ?>">Download CSV</a>
                        <?php endif; ?>
                        <a href="/jobmington/admin/events.php" style="font-size:13px;font-weight:700;color:#5b6b82;text-decoration:none;">Close</a>
                    </div>
                </div>
                <p style="font-size:12px;color:#5b6b82;margin:0 0 14px;"><?= count($registrants) ?> registered. The CSV includes phone and city where the person has them on their profile.</p>

                <?php if (!$registrants): ?>
                    <p style="font-size:13px;color:#94a3b8;margin:0;">Nobody has registered yet.</p>
                <?php else: ?>
                    <div class="jm-tablewrap"><table class="jm-ev-table">
                        <thead><tr><th>Name</th><th>Email</th><th>Registered</th><th>Reminders</th></tr></thead>
                        <tbody>
                        <?php foreach ($registrants as $r): ?>
                            <tr>
                                <td><?= e($r['name'] ?: 'Member') ?></td>
                                <td><a href="mailto:<?= e($r['email']) ?>" style="color:#0640a3;"><?= e($r['email']) ?></a></td>
                                <td style="font-size:12px;color:#5b6b82;"><?= e(date('j M Y, g:i A', strtotime($r['registered_at']))) ?></td>
                                <td style="font-size:12px;color:#5b6b82;">
                                    <?= $r['reminder_24h_at'] ? 'day-before sent' : 'day-before pending' ?><br>
                                    <?= $r['reminder_1h_at'] ? 'hour-before sent' : 'hour-before pending' ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table></div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="jm-tablewrap"><table class="jm-ev-table">
            <thead><tr><th>Event</th><th>When</th><th>Regs</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php if (empty($events)): ?>
                <tr><td colspan="5" style="text-align:center;color:#94a3b8;padding:28px;">No events yet. Schedule your first one.</td></tr>
            <?php else: foreach ($events as $ev): ?>
                <tr>
                    <td><strong><?= e($ev['title']) ?></strong><div style="font-size:11px;color:#94a3b8;"><?= ucfirst($ev['event_type']) ?> &middot; <?= $ev['is_online'] ? 'Online' : e($ev['location'] ?: 'In person') ?></div></td>
                    <td style="font-size:12px;"><?= e(date('M d, Y', strtotime($ev['starts_at']))) ?><br><span style="color:#94a3b8;"><?= e(date('h:i A', strtotime($ev['starts_at']))) ?></span></td>
                    <td><a href="?registrants=<?= (int)$ev['event_id'] ?>" style="color:#0640a3;font-weight:700;"><?= (int) $ev['registration_count'] ?><?= $ev['capacity'] ? '/' . (int)$ev['capacity'] : '' ?></a></td>
                    <td><span class="jm-ev-pill <?= $ev['is_published'] ? 'on' : 'off' ?>"><?= $ev['is_published'] ? 'Live' : 'Hidden' ?></span></td>
                    <td>
                        <div class="jm-ev-actions">
                            <a href="/jobmington/admin/events.php?edit=<?= (int)$ev['event_id'] ?>">Edit</a>
                            <form method="post" style="display:inline;"><?= Security::csrfField() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="event_id" value="<?= (int)$ev['event_id'] ?>"><button type="submit"><?= $ev['is_published'] ? 'Hide' : 'Show' ?></button></form>
                            <form method="post" onsubmit="return confirm('Delete this event?');" style="display:inline;"><?= Security::csrfField() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="event_id" value="<?= (int)$ev['event_id'] ?>"><button type="submit" style="color:#b42318;">Delete</button></form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table></div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
