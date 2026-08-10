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
require_once __DIR__ . '/../includes/learn_nav.php';

Session::start();
$pdo = db();

$upcoming = $past = [];
try {
    $upcoming = $pdo->query("SELECT * FROM events WHERE is_published = 1 AND (ends_at IS NOT NULL AND ends_at >= NOW() OR ends_at IS NULL AND starts_at >= NOW()) ORDER BY starts_at ASC")->fetchAll(PDO::FETCH_ASSOC);
    $past     = $pdo->query("SELECT * FROM events WHERE is_published = 1 AND COALESCE(ends_at, starts_at) < NOW() ORDER BY starts_at DESC LIMIT 12")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { $upcoming = $past = []; }

$tab  = get('tab', '') === 'past' ? 'past' : 'upcoming';
$list = $tab === 'past' ? $past : $upcoming;

$pageTitle = 'Events & Webinars - ' . SITE_NAME;
$activeAIPage = 'learn';
require_once __DIR__ . '/../includes/ai-header.php';

/** Relative day label ("Today", "In 4 days") for an upcoming event. */
function jm_event_countdown(array $ev): array {
    $start = strtotime($ev['starts_at']);
    $end   = strtotime($ev['ends_at'] ?: $ev['starts_at']);
    $now   = time();

    if ($start <= $now && $end >= $now) {
        return ['live', 'Happening now'];
    }
    $days = (int) floor(($start - strtotime('today')) / 86400);
    if ($days === 0) { return ['soon', 'Today']; }
    if ($days === 1) { return ['soon', 'Tomorrow']; }
    if ($days > 1 && $days <= 14) { return ['', 'In ' . $days . ' days']; }
    return ['', ''];
}

function jm_event_row(array $ev, bool $isPast = false): string {
    $start = strtotime($ev['starts_at']);
    [$state, $countdown] = $isPast ? ['', ''] : jm_event_countdown($ev);

    $where = $ev['is_online'] ? 'Online' : ($ev['location'] ?: 'In person');
    $seatsLeft = ((int) $ev['capacity'] > 0) ? max(0, (int) $ev['capacity'] - (int) $ev['registration_count']) : null;

    ob_start(); ?>
    <a class="jm-ev-row<?= $isPast ? ' is-past' : '' ?>" href="/jobmington/events/view.php?slug=<?= e($ev['slug']) ?>">
        <div class="jm-ev-date" aria-hidden="true">
            <span class="m"><?= e(strtoupper(date('M', $start))) ?></span>
            <span class="d"><?= e(date('j', $start)) ?></span>
            <span class="w"><?= e(strtoupper(date('D', $start))) ?></span>
        </div>

        <div class="jm-ev-main">
            <div class="jm-ev-tags">
                <span class="jm-ev-type"><?= e(ucfirst($ev['event_type'])) ?></span>
                <?php if ($countdown): ?>
                    <span class="jm-ev-badge<?= $state ? ' is-' . $state : '' ?>"><?= e($countdown) ?></span>
                <?php endif; ?>
            </div>

            <h3 class="jm-ev-title"><?= e($ev['title']) ?></h3>

            <p class="jm-ev-meta">
                <?= e(date('D, j M', $start)) ?> &middot; <?= e(date('g:i A', $start)) ?><?= $ev['timezone'] ? ' ' . e($ev['timezone']) : '' ?>
            </p>
            <p class="jm-ev-meta">
                <?= e($where) ?><?= $ev['host_name'] ? ' &middot; Hosted by ' . e($ev['host_name']) : '' ?>
            </p>

            <div class="jm-ev-foot">
                <span class="jm-ev-price<?= $ev['is_free'] ? ' is-free' : '' ?>">
                    <?= $ev['is_free'] ? 'Free' : '&#8358;' . number_format((float) $ev['price']) ?>
                </span>
                <?php if ((int) $ev['registration_count'] > 0): ?>
                    <span class="jm-ev-dot"></span>
                    <span><?= (int) $ev['registration_count'] ?> registered</span>
                <?php endif; ?>
                <?php if (!$isPast && $seatsLeft !== null && $seatsLeft <= 20): ?>
                    <span class="jm-ev-dot"></span>
                    <span class="jm-ev-scarce"><?= $seatsLeft > 0 ? $seatsLeft . ' spots left' : 'Fully booked' ?></span>
                <?php endif; ?>
            </div>
        </div>

        <div class="jm-ev-thumb">
            <?php if (!empty($ev['cover_image'])): ?>
                <img src="<?= e($ev['cover_image']) ?>" alt="" loading="lazy">
            <?php else: ?>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            <?php endif; ?>
        </div>

        <span class="jm-ev-go" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
        </span>
    </a>
    <?php return ob_get_clean();
}
?>
<style>
.jm-ev-page { max-width: 940px; margin: 0 auto; padding: 40px 20px 80px; }

/* Head */
.jm-ev-head h1 { font-size: clamp(28px,4.4vw,40px); font-weight: 800; letter-spacing: -.025em; color: #061426; margin: 0 0 10px; line-height: 1.12; }
.jm-ev-head p { font-size: 16px; color: #53667f; margin: 0; max-width: 560px; line-height: 1.65; }

/* Tabs */
.jm-ev-tabs { display: flex; gap: 26px; border-bottom: 1px solid #e4eaf3; margin: 32px 0 8px; }
.jm-ev-tab { position: relative; display: inline-flex; align-items: center; gap: 7px; padding: 0 0 13px; font-size: 14px; font-weight: 700; color: #53667f; text-decoration: none; border-bottom: 2px solid transparent; margin-bottom: -1px; transition: color .14s, border-color .14s; }
.jm-ev-tab:hover { color: #061426; }
.jm-ev-tab.active { color: #0640a3; border-bottom-color: #0640a3; }
.jm-ev-tab .n { font-size: 11.5px; font-weight: 800; color: #53667f; background: #f1f5fb; border-radius: 99px; padding: 2px 7px; min-width: 20px; text-align: center; }
.jm-ev-tab.active .n { background: #eaf1fd; color: #0640a3; }

/* Row */
.jm-ev-list { display: flex; flex-direction: column; }
.jm-ev-row {
    display: grid;
    grid-template-columns: 64px minmax(0,1fr) 132px 20px;
    gap: 20px;
    align-items: center;
    padding: 22px 4px;
    border-bottom: 1px solid #eef2f8;
    text-decoration: none;
    transition: background .14s;
}
.jm-ev-row:hover { background: #fafcff; }
.jm-ev-row:last-child { border-bottom: 0; }
.jm-ev-row.is-past { opacity: .72; }
.jm-ev-row.is-past:hover { opacity: 1; }

/* Date block */
.jm-ev-date { display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; border: 1px solid #e4eaf3; border-radius: 10px; padding: 9px 4px 8px; background: #fff; }
.jm-ev-date .m { font-size: 10.5px; font-weight: 800; letter-spacing: .1em; color: #0640a3; line-height: 1; }
.jm-ev-date .d { font-size: 23px; font-weight: 800; color: #061426; line-height: 1.15; letter-spacing: -.02em; }
.jm-ev-date .w { font-size: 9.5px; font-weight: 700; letter-spacing: .09em; color: #94a3b8; line-height: 1; }

/* Main */
.jm-ev-main { min-width: 0; }
.jm-ev-tags { display: flex; align-items: center; gap: 7px; flex-wrap: wrap; margin-bottom: 7px; }
.jm-ev-type { font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: .07em; color: #0640a3; background: #eaf1fd; padding: 3px 8px; border-radius: 4px; }
.jm-ev-badge { font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: .07em; color: #53667f; background: #f1f5fb; padding: 3px 8px; border-radius: 4px; }
.jm-ev-badge.is-soon { color: #8a5300; background: #fdf2e0; }
.jm-ev-badge.is-live { color: #fff; background: #c81e1e; }
.jm-ev-title { font-size: 17px; font-weight: 800; color: #061426; line-height: 1.35; margin: 0 0 6px; letter-spacing: -.012em; }
.jm-ev-row:hover .jm-ev-title { color: #0640a3; }
.jm-ev-meta { font-size: 13px; color: #53667f; margin: 0 0 2px; line-height: 1.5; }
.jm-ev-foot { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-top: 9px; font-size: 12.5px; color: #53667f; }
.jm-ev-price { font-weight: 800; color: #061426; }
.jm-ev-price.is-free { color: #0a6454; }
.jm-ev-scarce { font-weight: 700; color: #8a5300; }
.jm-ev-dot { width: 3px; height: 3px; border-radius: 50%; background: #c3cede; }

/* Thumb */
.jm-ev-thumb { width: 132px; aspect-ratio: 16/10; border-radius: 10px; overflow: hidden; border: 1px solid #e4eaf3; background: #f7faff; display: grid; place-items: center; }
.jm-ev-thumb img { width: 100%; height: 100%; object-fit: cover; display: block; }
.jm-ev-thumb svg { width: 26px; height: 26px; color: #b8c8df; }

.jm-ev-go { display: grid; place-items: center; color: #c3cede; transition: color .14s, transform .14s; }
.jm-ev-go svg { width: 18px; height: 18px; }
.jm-ev-row:hover .jm-ev-go { color: #0640a3; transform: translateX(3px); }

/* Empty */
.jm-ev-empty { text-align: center; padding: 64px 20px; border: 1px dashed #dbe4f0; border-radius: 12px; margin-top: 24px; }
.jm-ev-empty svg { width: 30px; height: 30px; color: #b8c8df; margin-bottom: 12px; }
.jm-ev-empty h3 { font-size: 16px; font-weight: 800; color: #061426; margin: 0 0 6px; }
.jm-ev-empty p { font-size: 14px; color: #53667f; margin: 0; }

@media (max-width: 720px) {
    /* The poster used to be hidden here, which lost the most compelling part of
       a listing on the screen most people browse it from. It now drops to a
       full-width band beneath the details instead of being squeezed beside them. */
    .jm-ev-row {
        grid-template-columns: 52px minmax(0,1fr);
        grid-template-areas: "date main" "thumb thumb";
        gap: 14px;
        padding: 18px 2px;
        align-items: start;
    }
    .jm-ev-date  { grid-area: date; padding: 7px 3px 6px; }
    .jm-ev-main  { grid-area: main; }
    .jm-ev-thumb {
        grid-area: thumb;
        width: 100%;
        aspect-ratio: 16 / 9;
        margin-top: 2px;
    }
    .jm-ev-go { display: none; }
    .jm-ev-date .d { font-size: 20px; }
    .jm-ev-title { font-size: 15.5px; }
    .jm-ev-tabs { gap: 20px; }
}
</style>

<div class="jm-ev-page">
    <?= jm_breadcrumbs([['label' => 'Events']]) ?>
    <?= jm_learn_nav('events') ?>

    <div class="jm-ev-head">
        <h1>Events &amp; webinars</h1>
        <p>Live sessions, workshops and webinars to move your career forward — most of them free.</p>
    </div>

    <nav class="jm-ev-tabs" aria-label="Event filter">
        <a class="jm-ev-tab<?= $tab === 'upcoming' ? ' active' : '' ?>" href="/jobmington/events/">
            Upcoming <span class="n"><?= count($upcoming) ?></span>
        </a>
        <a class="jm-ev-tab<?= $tab === 'past' ? ' active' : '' ?>" href="/jobmington/events/?tab=past">
            Past <span class="n"><?= count($past) ?></span>
        </a>
    </nav>

    <?php if (empty($list)): ?>
        <div class="jm-ev-empty">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            <h3><?= $tab === 'past' ? 'No past events yet' : 'No upcoming events right now' ?></h3>
            <p><?= $tab === 'past' ? 'Once sessions wrap up they will be listed here.' : 'New sessions are added regularly — check back soon.' ?></p>
        </div>
    <?php else: ?>
        <div class="jm-ev-list">
            <?php foreach ($list as $ev) { echo jm_event_row($ev, $tab === 'past'); } ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/ai-footer.php'; ?>
