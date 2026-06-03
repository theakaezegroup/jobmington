<?php
/**
 * JOBMINGTON - Events & Webinars (public)
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

$upcoming = $past = [];
try {
    $upcoming = $pdo->query("SELECT * FROM events WHERE is_published = 1 AND (ends_at IS NOT NULL AND ends_at >= NOW() OR ends_at IS NULL AND starts_at >= NOW()) ORDER BY starts_at ASC")->fetchAll(PDO::FETCH_ASSOC);
    $past     = $pdo->query("SELECT * FROM events WHERE is_published = 1 AND COALESCE(ends_at, starts_at) < NOW() ORDER BY starts_at DESC LIMIT 12")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { $upcoming = $past = []; }

$pageTitle = 'Events & Webinars - ' . SITE_NAME;
require_once __DIR__ . '/../includes/header.php';

function jm_event_card(array $ev, bool $isPast = false): string {
    $date = date('D, M d, Y', strtotime($ev['starts_at']));
    $time = date('h:i A', strtotime($ev['starts_at']));
    ob_start(); ?>
    <a class="jm-ev-item" href="/jobmington/events/view.php?slug=<?= e($ev['slug']) ?>">
        <div class="jm-ev-cover">
            <?php if (!empty($ev['cover_image'])): ?>
                <img src="<?= e($ev['cover_image']) ?>" alt="<?= e($ev['title']) ?>">
            <?php else: ?>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            <?php endif; ?>
            <span class="jm-ev-type"><?= e(ucfirst($ev['event_type'])) ?></span>
        </div>
        <div class="jm-ev-body">
            <span class="jm-ev-date"><?= e($date) ?> &middot; <?= e($time) ?></span>
            <span class="jm-ev-title"><?= e($ev['title']) ?></span>
            <span class="jm-ev-where"><?= $ev['is_online'] ? 'Online' : e($ev['location'] ?: 'In person') ?><?= $ev['host_name'] ? ' &middot; ' . e($ev['host_name']) : '' ?></span>
            <div class="jm-ev-foot">
                <span class="jm-ev-price"><?= $ev['is_free'] ? 'Free' : '₦' . number_format((float)$ev['price']) ?></span>
                <span class="jm-ev-cta"><?= $isPast ? 'View recap' : 'Register' ?> &rarr;</span>
            </div>
        </div>
    </a>
    <?php return ob_get_clean();
}
?>
<style>
.jm-ev-page { max-width:1100px; margin:0 auto; padding:48px 20px 72px; }
.jm-ev-head h1 { font-size:clamp(30px,5vw,46px); font-weight:800; letter-spacing:-.02em; color:#061426; margin:0 0 12px; }
.jm-ev-head p { font-size:17px; color:#53667f; margin:0 0 36px; max-width:600px; line-height:1.6; }
.jm-ev-section-title { font-size:13px; font-weight:800; text-transform:uppercase; letter-spacing:.08em; color:#5b6b82; margin:36px 0 16px; }
.jm-ev-grid { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:18px; }
@media (max-width:860px){ .jm-ev-grid { grid-template-columns:repeat(2,minmax(0,1fr)); } }
@media (max-width:560px){ .jm-ev-grid { grid-template-columns:1fr; } }
.jm-ev-item { display:flex; flex-direction:column; background:#fff; border:1px solid #e4eaf3; border-radius:14px; overflow:hidden; text-decoration:none; transition:box-shadow .16s, transform .16s; }
.jm-ev-item:hover { box-shadow:0 12px 30px rgba(6,20,38,.1); transform:translateY(-3px); }
.jm-ev-cover { position:relative; aspect-ratio:16/9; background:linear-gradient(135deg,#eef5ff,#f7faff); display:grid; place-items:center; overflow:hidden; }
.jm-ev-cover img { width:100%; height:100%; object-fit:cover; }
.jm-ev-cover svg { width:40px; height:40px; color:#9bb0c7; }
.jm-ev-type { position:absolute; top:10px; left:10px; font-size:10px; font-weight:800; text-transform:uppercase; letter-spacing:.06em; background:#0640a3; color:#fff; padding:3px 9px; border-radius:99px; }
.jm-ev-body { padding:14px 16px 16px; display:flex; flex-direction:column; gap:6px; flex:1; }
.jm-ev-date { font-size:11px; font-weight:800; text-transform:uppercase; letter-spacing:.05em; color:#0640a3; }
.jm-ev-title { font-size:16px; font-weight:800; color:#061426; line-height:1.3; }
.jm-ev-where { font-size:12px; color:#94a3b8; }
.jm-ev-foot { margin-top:auto; display:flex; align-items:center; justify-content:space-between; padding-top:10px; }
.jm-ev-price { font-size:12px; font-weight:800; color:#0a6454; }
.jm-ev-cta { font-size:12px; font-weight:700; color:#0640a3; }
.jm-ev-empty { text-align:center; color:#94a3b8; padding:48px 20px; background:#fff; border:1px solid #e4eaf3; border-radius:14px; }
</style>

<div class="jm-ev-page">
    <?= jm_breadcrumbs([['label' => 'Events']]) ?>
    <div class="jm-ev-head">
        <h1>Events &amp; webinars.</h1>
        <p>Live sessions, workshops, and webinars to level up your career — most are free.</p>
    </div>

    <div class="jm-ev-section-title">Upcoming</div>
    <?php if (empty($upcoming)): ?>
        <div class="jm-ev-empty">No upcoming events right now. Check back soon.</div>
    <?php else: ?>
        <div class="jm-ev-grid"><?php foreach ($upcoming as $ev) echo jm_event_card($ev); ?></div>
    <?php endif; ?>

    <?php if (!empty($past)): ?>
        <div class="jm-ev-section-title">Past events</div>
        <div class="jm-ev-grid"><?php foreach ($past as $ev) echo jm_event_card($ev, true); ?></div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
