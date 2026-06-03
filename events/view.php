<?php
/**
 * JOBMINGTON - Event detail + registration (public)
 */
define('JOBMINGTON', true);
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';

Session::start();
$pdo = db();

$slug = trim((string) get('slug', ''));
$stmt = $pdo->prepare("SELECT * FROM events WHERE slug = ? AND is_published = 1 LIMIT 1");
$stmt->execute([$slug]);
$event = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$event) {
    http_response_code(404);
    $pageTitle = 'Event not found - ' . SITE_NAME;
    $activeAIPage = "learn"; require_once __DIR__ . '/../includes/ai-header.php';
    echo '<div style="max-width:680px;margin:80px auto;text-align:center;padding:0 20px;"><h1 style="color:#061426;">Event not found</h1><p style="color:#53667f;"><a href="/jobmington/events/" style="color:#0640a3;">Back to events</a></p></div>';
    require_once __DIR__ . '/../includes/ai-footer.php';
    exit;
}

$eventId   = (int) $event['event_id'];
$isPast    = strtotime($event['ends_at'] ?: $event['starts_at']) < time();
$isFull    = $event['capacity'] > 0 && $event['registration_count'] >= $event['capacity'];
$message   = '';
$justJoined = false;

// Is the current user already registered?
$registered = false;
if (Session::isLoggedIn()) {
    $chk = $pdo->prepare("SELECT 1 FROM event_registrations WHERE event_id = ? AND user_id = ? LIMIT 1");
    $chk->execute([$eventId, (int) Session::userId()]);
    $registered = (bool) $chk->fetchColumn();
}

// Handle registration.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'register') {
    if (!Session::isLoggedIn()) {
        redirect('/jobmington/auth/login.php?redirect=' . urlencode('/jobmington/events/view.php?slug=' . $event['slug']));
    } elseif (!Security::verifyCSRF()) {
        $message = 'Security check failed. Please try again.';
    } elseif ($isPast) {
        $message = 'This event has already taken place.';
    } elseif ($isFull) {
        $message = 'This event is fully booked.';
    } elseif (!$registered) {
        try {
            $pdo->prepare("INSERT INTO event_registrations (event_id, user_id, name, email) VALUES (?, ?, ?, ?)")
                ->execute([$eventId, (int) Session::userId(), Session::get('full_name'), Session::get('email')]);
            $pdo->prepare("UPDATE events SET registration_count = registration_count + 1 WHERE event_id = ?")->execute([$eventId]);
            $registered = true;
            $justJoined = true;
            $event['registration_count']++;
        } catch (Throwable $e) {
            $registered = true; // unique key — already registered
        }
        Security::regenerateCSRF();
    }
}

$pageTitle = $event['title'] . ' - ' . SITE_NAME;
$activeAIPage = "learn"; require_once __DIR__ . '/../includes/ai-header.php';
?>
<style>
.jm-evv { max-width:900px; margin:0 auto; padding:40px 20px 72px; }
.jm-evv-cover { aspect-ratio:16/7; border-radius:16px; overflow:hidden; background:linear-gradient(135deg,#eef5ff,#f7faff); display:grid; place-items:center; border:1px solid #e4eaf3; margin-bottom:24px; }
.jm-evv-cover img { width:100%; height:100%; object-fit:cover; }
.jm-evv-cover svg { width:52px; height:52px; color:#9bb0c7; }
.jm-evv-type { display:inline-block; font-size:11px; font-weight:800; text-transform:uppercase; letter-spacing:.07em; color:#fff; background:#0640a3; padding:4px 12px; border-radius:99px; }
.jm-evv h1 { font-size:clamp(26px,4vw,38px); font-weight:800; letter-spacing:-.02em; color:#061426; margin:12px 0 16px; line-height:1.15; }
.jm-evv-grid { display:grid; grid-template-columns:1fr 300px; gap:32px; align-items:start; }
@media (max-width:760px){ .jm-evv-grid { grid-template-columns:1fr; } }
.jm-evv-desc { font-size:15px; color:#0b1b33; line-height:1.75; white-space:pre-wrap; }
.jm-evv-side { border:1px solid #e4eaf3; border-radius:14px; padding:20px; background:#fff; position:sticky; top:20px; }
.jm-evv-meta { display:flex; gap:10px; align-items:flex-start; margin-bottom:14px; }
.jm-evv-meta svg { width:18px; height:18px; color:#0640a3; flex-shrink:0; margin-top:2px; }
.jm-evv-meta .l { font-size:11px; font-weight:800; text-transform:uppercase; letter-spacing:.05em; color:#94a3b8; }
.jm-evv-meta .v { font-size:14px; font-weight:600; color:#0b1b33; }
.jm-evv-btn { display:block; width:100%; box-sizing:border-box; text-align:center; background:#0640a3; color:#fff; border:0; border-radius:10px; padding:14px; font:inherit; font-weight:800; font-size:14px; cursor:pointer; text-decoration:none; }
.jm-evv-btn:hover { background:#052f78; }
.jm-evv-btn.disabled { background:#e4eaf3; color:#94a3b8; cursor:not-allowed; }
.jm-evv-note { font-size:12px; color:#53667f; text-align:center; margin-top:10px; line-height:1.5; }
.jm-evv-join { display:block; text-align:center; background:#0f766e; color:#fff; border-radius:10px; padding:14px; font-weight:800; font-size:14px; text-decoration:none; }
.jm-evv-flash { background:#e6f5f1; color:#0a6454; border-radius:8px; padding:11px 14px; font-size:13px; font-weight:600; margin-bottom:16px; }
</style>

<div class="jm-evv">
    <?= jm_breadcrumbs([['label' => 'Events', 'url' => '/jobmington/events/'], ['label' => $event['title']]]) ?>

    <div class="jm-evv-cover">
        <?php if (!empty($event['cover_image'])): ?>
            <img src="<?= e($event['cover_image']) ?>" alt="<?= e($event['title']) ?>">
        <?php else: ?>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        <?php endif; ?>
    </div>

    <span class="jm-evv-type"><?= e(ucfirst($event['event_type'])) ?></span>
    <h1><?= e($event['title']) ?></h1>

    <?php if ($justJoined): ?><div class="jm-evv-flash">You're registered! Details are below.</div><?php endif; ?>
    <?php if ($message): ?><div class="jm-evv-flash" style="background:#fdecea;color:#991b1b;"><?= e($message) ?></div><?php endif; ?>

    <div class="jm-evv-grid">
        <div class="jm-evv-desc"><?= e($event['description'] ?: 'Details coming soon.') ?></div>

        <aside class="jm-evv-side">
            <div class="jm-evv-meta">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                <div><div class="l">When</div><div class="v"><?= e(date('D, M d, Y', strtotime($event['starts_at']))) ?><br><?= e(date('h:i A', strtotime($event['starts_at']))) ?> <?= e($event['timezone']) ?></div></div>
            </div>
            <div class="jm-evv-meta">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                <div><div class="l">Where</div><div class="v"><?= $event['is_online'] ? 'Online' : e($event['location'] ?: 'In person') ?></div></div>
            </div>
            <div class="jm-evv-meta">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                <div><div class="l">Registered</div><div class="v"><?= (int)$event['registration_count'] ?><?= $event['capacity'] ? ' / ' . (int)$event['capacity'] : '' ?></div></div>
            </div>

            <div style="margin-top:18px;">
            <?php if ($registered): ?>
                <?php if (!$isPast && !empty($event['meeting_url'])): ?>
                    <a class="jm-evv-join" href="<?= e($event['meeting_url']) ?>" target="_blank" rel="noopener">Join the session</a>
                    <p class="jm-evv-note">You're registered. The link is also emailed nearer the time.</p>
                <?php elseif (!$isPast): ?>
                    <div class="jm-evv-btn disabled">You're registered</div>
                    <p class="jm-evv-note">The join link will appear here before it starts.</p>
                <?php else: ?>
                    <div class="jm-evv-btn disabled">Event ended</div>
                <?php endif; ?>
            <?php elseif ($isPast): ?>
                <div class="jm-evv-btn disabled">Event ended</div>
            <?php elseif ($isFull): ?>
                <div class="jm-evv-btn disabled">Fully booked</div>
            <?php else: ?>
                <form method="post">
                    <?= Security::csrfField() ?>
                    <input type="hidden" name="action" value="register">
                    <button class="jm-evv-btn" type="submit"><?= $event['is_free'] ? 'Register free' : 'Register — ₦' . number_format((float)$event['price']) ?></button>
                </form>
                <?php if (!Session::isLoggedIn()): ?><p class="jm-evv-note">You'll be asked to sign in first.</p><?php endif; ?>
            <?php endif; ?>
            </div>
        </aside>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/ai-footer.php'; ?>
