<?php
/**
 * JOBMINGTON - Admin: Header Ads
 *
 * The banner is defined once as a schema of typed, self-describing fields
 * (the Keystatic idea) and the form, validation and save all read from that
 * one definition. Adding a field means adding one entry here, not editing
 * markup, validation and the query separately.
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

$msg = '';
$err = '';

$UPLOAD_REL = '/uploads/ads';
$UPLOAD_DIR = dirname(__DIR__) . $UPLOAD_REL;

/** The ad, described once. Everything below is driven by this. */
$AD_FIELDS = [
    'name' => [
        'kind' => 'text', 'label' => 'Campaign name', 'required' => true, 'max' => 150,
        'description' => 'Internal label so you can tell campaigns apart. Never shown to visitors.',
    ],
    'placement' => [
        'kind' => 'select', 'label' => 'Where it appears', 'default' => 'header',
        'options' => ['header' => 'Header strip (above the nav, every page)', 'inline' => 'In content (job list, job detail, blog posts)'],
        'description' => 'Header strip is wide and short, around 1200x120. In content is a card, around 1200x300. One ad runs per position.',
    ],
    'image_alt' => [
        'kind' => 'text', 'label' => 'Image description', 'max' => 200,
        'description' => 'Read aloud by screen readers and shown if the image fails to load. Describe the offer, not the picture.',
    ],
    'click_url' => [
        'kind' => 'url', 'label' => 'Click-through URL', 'max' => 500,
        'description' => 'Where the banner sends visitors. Must start with https://. Leave empty for a banner that is not clickable.',
    ],
    'bg_color' => [
        'kind' => 'color', 'label' => 'Strip background', 'default' => '#f4f8ff',
        'description' => 'Fills the space around the image on wide screens. Pick something close to the banner edges so it blends.',
    ],
    'starts_at' => [
        'kind' => 'datetime', 'label' => 'Runs from',
        'description' => 'Leave empty to start as soon as it is active.',
    ],
    'ends_at' => [
        'kind' => 'datetime', 'label' => 'Runs until',
        'description' => 'Leave empty to keep running until you switch it off.',
    ],
    'priority' => [
        'kind' => 'integer', 'label' => 'Priority', 'default' => 0,
        'description' => 'When more than one ad is live at the same time, the highest number wins.',
    ],
    'is_active' => [
        'kind' => 'checkbox', 'label' => 'Active', 'default' => 1,
        'description' => 'Uncheck to pull the banner immediately without deleting it or losing its stats.',
    ],
];

/** Read + validate one field from POST according to its own definition. */
function jm_ad_read(string $key, array $def, array &$errors) {
    $raw = trim((string) ($_POST[$key] ?? ''));

    switch ($def['kind']) {
        case 'checkbox':
            return isset($_POST[$key]) ? 1 : 0;

        case 'integer':
            return $raw === '' ? (int) ($def['default'] ?? 0) : (int) $raw;

        case 'datetime':
            if ($raw === '') { return null; }
            $ts = strtotime($raw);
            if ($ts === false) { $errors[] = $def['label'] . ' is not a valid date.'; return null; }
            return date('Y-m-d H:i:s', $ts);

        case 'select':
            return isset($def['options'][$raw]) ? $raw : ($def['default'] ?? null);

        case 'color':
            return preg_match('/^#[0-9a-f]{6}$/i', $raw) ? $raw : ($def['default'] ?? null);

        case 'url':
            if ($raw === '') { return null; }
            if (!preg_match('#^https?://#i', $raw) || !filter_var($raw, FILTER_VALIDATE_URL)) {
                $errors[] = $def['label'] . ' must be a full URL starting with http:// or https://.';
                return null;
            }
            return substr($raw, 0, $def['max'] ?? 500);

        default: // text
            if (!empty($def['required']) && $raw === '') {
                $errors[] = $def['label'] . ' is required.';
            }
            return $raw === '' ? null : substr($raw, 0, $def['max'] ?? 255);
    }
}

/* ── Actions ─────────────────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::verifyCSRF()) {
        $err = 'Security check failed. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';
        $adId   = (int) ($_POST['ad_id'] ?? 0);

        if ($action === 'delete' && $adId) {
            $pdo->prepare("DELETE FROM header_ads WHERE ad_id = ?")->execute([$adId]);
            $msg = 'Ad deleted.';
        } elseif ($action === 'toggle' && $adId) {
            $pdo->prepare("UPDATE header_ads SET is_active = 1 - is_active WHERE ad_id = ?")->execute([$adId]);
            $msg = 'Ad updated.';
        } elseif ($action === 'save') {
            $errors = [];
            $values = [];
            foreach ($AD_FIELDS as $key => $def) {
                $values[$key] = jm_ad_read($key, $def, $errors);
            }

            // Image: required for a new ad, optional when editing.
            $imagePath = null;
            if (!empty($_FILES['image']['name']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                if ($_FILES['image']['size'] > 2 * 1024 * 1024) {
                    $errors[] = 'Banner image must be under 2MB.';
                } else {
                    $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
                    if (!in_array($ext, ['png', 'jpg', 'jpeg', 'webp', 'gif'], true)) {
                        $errors[] = 'Banner image must be PNG, JPG, WEBP or GIF.';
                    } else {
                        if (!is_dir($UPLOAD_DIR)) { @mkdir($UPLOAD_DIR, 0775, true); }
                        $fname = bin2hex(random_bytes(8)) . '.' . $ext;
                        if (move_uploaded_file($_FILES['image']['tmp_name'], $UPLOAD_DIR . '/' . $fname)) {
                            $imagePath = $UPLOAD_REL . '/' . $fname;
                        } else {
                            $errors[] = 'Could not save the banner image.';
                        }
                    }
                }
            } elseif (!$adId) {
                $errors[] = 'A banner image is required.';
            }

            if ($errors) {
                $err = implode(' ', $errors);
            } elseif ($adId) {
                $sql = "UPDATE header_ads SET name=?, placement=?, image_alt=?, click_url=?, bg_color=?, starts_at=?, ends_at=?, priority=?, is_active=?"
                     . ($imagePath ? ", image_path=?" : "") . " WHERE ad_id=?";
                $params = [$values['name'], $values['placement'], $values['image_alt'], $values['click_url'], $values['bg_color'],
                           $values['starts_at'], $values['ends_at'], $values['priority'], $values['is_active']];
                if ($imagePath) { $params[] = $imagePath; }
                $params[] = $adId;
                $pdo->prepare($sql)->execute($params);
                $msg = 'Ad saved.';
            } else {
                $pdo->prepare("INSERT INTO header_ads (name, placement, image_path, image_alt, click_url, bg_color, starts_at, ends_at, priority, is_active)
                               VALUES (?,?,?,?,?,?,?,?,?,?)")
                    ->execute([$values['name'], $values['placement'], $imagePath, $values['image_alt'], $values['click_url'], $values['bg_color'],
                               $values['starts_at'], $values['ends_at'], $values['priority'], $values['is_active']]);
                $msg = 'Ad created.';
            }
            Security::regenerateCSRF();
        }
    }
}

$editing = null;
if (!empty($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM header_ads WHERE ad_id = ? LIMIT 1");
    $stmt->execute([(int) $_GET['edit']]);
    $editing = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

$ads = $pdo->query("SELECT * FROM header_ads ORDER BY is_active DESC, priority DESC, updated_at DESC")->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Header Ads - Admin Panel';
require_once __DIR__ . '/../includes/header.php';

/** Render one field from its own definition. */
function jm_ad_field(string $key, array $def, ?array $row): void {
    $val = $row[$key] ?? ($def['default'] ?? '');
    if ($def['kind'] === 'datetime' && $val) { $val = date('Y-m-d\TH:i', strtotime((string) $val)); }
    $id = 'f_' . $key;
    ?>
    <div class="mb-5">
        <label for="<?= e($id) ?>" class="block text-sm font-bold text-slate-800"><?= e($def['label']) ?><?= !empty($def['required']) ? ' <span class="text-red-500">*</span>' : '' ?></label>
        <p class="text-xs text-slate-500 mt-0.5 mb-2 leading-relaxed"><?= e($def['description']) ?></p>
        <?php if ($def['kind'] === 'checkbox'): ?>
            <label class="inline-flex items-center gap-2">
                <input type="checkbox" id="<?= e($id) ?>" name="<?= e($key) ?>" value="1" <?= $val ? 'checked' : '' ?> class="w-4 h-4">
                <span class="text-sm text-slate-700">Enabled</span>
            </label>
        <?php elseif ($def['kind'] === 'select'): ?>
            <select id="<?= e($id) ?>" name="<?= e($key) ?>" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                <?php foreach ($def['options'] as $ov => $ol): ?>
                    <option value="<?= e($ov) ?>" <?= (string) $val === (string) $ov ? 'selected' : '' ?>><?= e($ol) ?></option>
                <?php endforeach; ?>
            </select>
        <?php elseif ($def['kind'] === 'color'): ?>
            <input type="color" id="<?= e($id) ?>" name="<?= e($key) ?>" value="<?= e($val ?: '#f4f8ff') ?>" class="h-10 w-20 rounded border border-slate-300 bg-white p-1">
        <?php else:
            $type = ['datetime' => 'datetime-local', 'integer' => 'number', 'url' => 'url'][$def['kind']] ?? 'text'; ?>
            <input type="<?= e($type) ?>" id="<?= e($id) ?>" name="<?= e($key) ?>" value="<?= e((string) $val) ?>"
                   <?= !empty($def['max']) ? 'maxlength="' . (int) $def['max'] . '"' : '' ?>
                   class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
        <?php endif; ?>
    </div>
    <?php
}
?>
<div class="min-h-screen bg-slate-100 py-8">
    <div class="max-w-5xl mx-auto px-4">

        <div class="mb-6">
            <h1 class="text-2xl font-black text-slate-900">Header ads</h1>
            <p class="text-sm text-slate-500 mt-1">A sponsor banner shown in a strip above the site header. One runs at a time — the highest priority whose schedule covers now.</p>
        </div>

        <?php if ($msg): ?><div class="mb-5 rounded-lg bg-emerald-50 text-emerald-800 px-4 py-3 text-sm font-semibold"><?= e($msg) ?></div><?php endif; ?>
        <?php if ($err): ?><div class="mb-5 rounded-lg bg-red-50 text-red-800 px-4 py-3 text-sm font-semibold"><?= e($err) ?></div><?php endif; ?>

        <div class="bg-white rounded-xl border border-slate-200 p-6 mb-8">
            <h2 class="text-base font-bold text-slate-900 mb-1"><?= $editing ? 'Edit ad' : 'New ad' ?></h2>
            <p class="text-xs text-slate-500 mb-5">Wide and short works best — around 1200&times;120. It is capped at 90px tall on desktop and 60px on mobile.</p>

            <form method="post" enctype="multipart/form-data">
                <?= Security::csrfField() ?>
                <input type="hidden" name="action" value="save">
                <?php if ($editing): ?><input type="hidden" name="ad_id" value="<?= (int) $editing['ad_id'] ?>"><?php endif; ?>

                <div class="mb-5">
                    <label for="f_image" class="block text-sm font-bold text-slate-800">Banner image<?= $editing ? '' : ' <span class="text-red-500">*</span>' ?></label>
                    <p class="text-xs text-slate-500 mt-0.5 mb-2 leading-relaxed">PNG, JPG, WEBP or GIF, under 2MB.<?= $editing ? ' Leave empty to keep the current image.' : '' ?></p>
                    <?php if ($editing && !empty($editing['image_path'])): ?>
                        <img src="/jobmington<?= e($editing['image_path']) ?>" alt="" class="mb-2 max-h-16 rounded border border-slate-200 bg-slate-50 p-1">
                    <?php endif; ?>
                    <input type="file" id="f_image" name="image" accept="image/*" class="block w-full text-sm text-slate-600">
                </div>

                <?php foreach ($AD_FIELDS as $key => $def) { jm_ad_field($key, $def, $editing); } ?>

                <div class="flex items-center gap-3 pt-2">
                    <button type="submit" class="rounded-lg bg-blue-700 px-5 py-2.5 text-sm font-bold text-white hover:bg-blue-800"><?= $editing ? 'Save changes' : 'Create ad' ?></button>
                    <?php if ($editing): ?><a href="/jobmington/admin/header-ads.php" class="text-sm font-semibold text-slate-500 hover:text-slate-800">Cancel</a><?php endif; ?>
                </div>
            </form>
        </div>

        <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-200"><h2 class="text-base font-bold text-slate-900">All ads</h2></div>
            <?php if (!$ads): ?>
                <p class="px-6 py-10 text-center text-sm text-slate-400">No ads yet. Create one above.</p>
            <?php else: ?>
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr><th class="px-6 py-3">Banner</th><th class="px-4 py-3">Schedule</th><th class="px-4 py-3">Views</th><th class="px-4 py-3">Clicks</th><th class="px-4 py-3">CTR</th><th class="px-4 py-3"></th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($ads as $a):
                        $ctr = $a['impressions'] > 0 ? round($a['clicks'] / $a['impressions'] * 100, 2) : 0; ?>
                        <tr class="border-t border-slate-100">
                            <td class="px-6 py-3">
                                <div class="flex items-center gap-3">
                                    <img src="/jobmington<?= e($a['image_path']) ?>" alt="" class="h-8 w-28 object-contain rounded bg-slate-50 border border-slate-200">
                                    <div>
                                        <div class="font-bold text-slate-800"><?= e($a['name']) ?></div>
                                        <div class="text-xs <?= $a['is_active'] ? 'text-emerald-600' : 'text-slate-400' ?> font-semibold"><?= $a['is_active'] ? 'Active' : 'Paused' ?> &middot; priority <?= (int) $a['priority'] ?></div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-xs text-slate-500">
                                <?= $a['starts_at'] ? e(date('j M Y', strtotime($a['starts_at']))) : 'Immediately' ?>
                                &rarr;
                                <?= $a['ends_at'] ? e(date('j M Y', strtotime($a['ends_at']))) : 'No end' ?>
                            </td>
                            <td class="px-4 py-3 font-semibold text-slate-700"><?= number_format((int) $a['impressions']) ?></td>
                            <td class="px-4 py-3 font-semibold text-slate-700"><?= number_format((int) $a['clicks']) ?></td>
                            <td class="px-4 py-3 font-semibold text-slate-700"><?= $ctr ?>%</td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <a href="?edit=<?= (int) $a['ad_id'] ?>" class="text-blue-700 font-semibold hover:underline">Edit</a>
                                <form method="post" class="inline ml-3"><?= Security::csrfField() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="ad_id" value="<?= (int) $a['ad_id'] ?>"><button class="text-slate-500 font-semibold hover:underline"><?= $a['is_active'] ? 'Pause' : 'Activate' ?></button></form>
                                <form method="post" class="inline ml-3" onsubmit="return confirm('Delete this ad? Its view and click history goes too.');"><?= Security::csrfField() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="ad_id" value="<?= (int) $a['ad_id'] ?>"><button class="text-red-600 font-semibold hover:underline">Delete</button></form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
