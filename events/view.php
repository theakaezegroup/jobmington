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
require_once __DIR__ . '/../includes/event_registration.php';

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

/*
 * Registration intent survives the auth detour.
 *
 * A guest who clicks Register is sent to sign in / sign up. Signup creates a
 * session but then force-redirects to verify-email.php, dropping any ?redirect,
 * so a URL-carried return target is not reliable. Instead the intent is parked
 * in the session and completed the moment the user is next on this page while
 * logged in — no second click, and it survives the verification detour.
 */
$intentKey    = 'pending_event_registration';
$postedIntent = $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'register';
$pendingHere  = (int) ($_SESSION[$intentKey] ?? 0) === $eventId;

if ($postedIntent && !Session::isLoggedIn()) {
    // Park the intent, then send them to sign in (that page links to sign-up).
    // auth_context tells the auth pages why the user is there, so they do not
    // land on generic "Welcome back" copy with no sign their click registered.
    $returnTo = '/jobmington/events/view.php?slug=' . $event['slug'];
    $_SESSION[$intentKey] = $eventId;
    $_SESSION['auth_context'] = 'You\'re registering for "' . $event['title'] . '". Sign in or create a free account and we\'ll complete it automatically.';
    $_SESSION['auth_context_for'] = $returnTo;
    redirect('/jobmington/auth/login.php?redirect=' . urlencode($returnTo));
}

// A posted form needs CSRF; a resumed intent is already trusted (it is server-side).
$wantsRegister = false;
if ($postedIntent) {
    if (Security::verifyCSRF()) {
        $wantsRegister = true;
    } else {
        $message = 'Security check failed. Please try again.';
    }
} elseif ($pendingHere && Session::isLoggedIn() && !$registered) {
    $wantsRegister = true;
}

// Already registered before the intent could resume — nothing left to do.
if ($pendingHere && Session::isLoggedIn() && $registered) {
    unset($_SESSION[$intentKey], $_SESSION['auth_context'], $_SESSION['auth_context_for']);
}

if ($wantsRegister && Session::isLoggedIn()) {
    unset($_SESSION[$intentKey], $_SESSION['auth_context'], $_SESSION['auth_context_for']);

    if (!$registered) {
        /*
         * The insert, the counter, the notification and the confirmation email
         * all live in includes/event_registration.php, because verification
         * completes registrations too and the two paths drifting apart is how
         * one of them ends up silently skipping the email.
         */
        $outcome = jm_register_for_event(
            $eventId,
            (int) Session::userId(),
            (string) Session::get('full_name'),
            (string) Session::get('email')
        );

        if ($outcome['status'] === 'registered') {
            $registered = true;
            $justJoined = true;
            $event['registration_count']++;
        } elseif ($outcome['status'] === 'already') {
            $registered = true;
        } else {
            $message = $outcome['message'];
        }

        // The account-level copy of the intent, in case one was parked there
        // and the person got back here before verification consumed it.
        $pdo->prepare("UPDATE users SET pending_event_id = NULL WHERE user_id = ? AND pending_event_id = ?")
            ->execute([(int) Session::userId(), $eventId]);

        Security::regenerateCSRF();
    }
}

$start    = strtotime($event['starts_at']);
$where    = $event['is_online'] ? 'Online' : ($event['location'] ?: 'In person');
$capacity = (int) $event['capacity'];
$taken    = (int) $event['registration_count'];
$seatsLeft = $capacity > 0 ? max(0, $capacity - $taken) : null;
$fillPct  = $capacity > 0 ? min(100, (int) round($taken / $capacity * 100)) : 0;

// "Add to calendar" link, for the button on this page. The confirmation email
// builds its own from the same helper.
$gcalUrl = jm_event_calendar_url($event);

$pageTitle = $event['title'] . ' - ' . SITE_NAME;

// Link preview: the event's own cover, falling back to the brand cover.
$evPlain = trim(preg_replace('/\s+/', ' ', strip_tags((string) $event['description'])));
$evSnip  = preg_match('/^.{0,140}/us', $evPlain, $em) ? rtrim($em[0]) : '';
if (strlen($evPlain) > strlen($evSnip)) { $evSnip .= '...'; }
$pageDescription = trim(date('l, j F Y', $start) . ' at ' . date('g:i A', $start)
    . ($event['timezone'] ? ' ' . $event['timezone'] : '') . ' - ' . $where . '. ' . $evSnip);
$pageImage     = (string) ($event['cover_image'] ?? '');
$pageCanonical = SITE_URL . '/events/view.php?slug=' . rawurlencode((string) $event['slug']);
$pageType      = 'article';

$activeAIPage = "learn"; require_once __DIR__ . '/../includes/ai-header.php';
?>
<style>
.jm-evd { max-width: 940px; margin: 0 auto; padding: 36px 20px 80px; }

/* Title block */
.jm-evd-type { display: inline-block; font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: .08em; color: #0640a3; background: #eaf1fd; padding: 4px 9px; border-radius: 4px; }
.jm-evd h1 { font-size: clamp(25px,3.8vw,36px); font-weight: 800; letter-spacing: -.025em; color: #061426; margin: 12px 0 10px; line-height: 1.16; }
.jm-evd-sub { font-size: 14.5px; color: #53667f; margin: 0 0 26px; line-height: 1.6; }
.jm-evd-sub b { color: #061426; font-weight: 700; }

/* Layout */
.jm-evd-grid { display: grid; grid-template-columns: minmax(0,1fr) 312px; gap: 40px; align-items: start; }
@media (max-width: 820px) { .jm-evd-grid { grid-template-columns: 1fr; gap: 28px; } }

/* Cover */
.jm-evd-cover { aspect-ratio: 16/8; border-radius: 12px; overflow: hidden; border: 1px solid #e4eaf3; background: #f7faff; display: grid; place-items: center; margin-bottom: 30px; }
.jm-evd-cover img { width: 100%; height: 100%; object-fit: cover; display: block; }
.jm-evd-cover svg { width: 44px; height: 44px; color: #b8c8df; }

/* Sections */
.jm-evd-sec + .jm-evd-sec { margin-top: 30px; padding-top: 30px; border-top: 1px solid #eef2f8; }
.jm-evd-sec h2 { font-size: 12px; font-weight: 800; text-transform: uppercase; letter-spacing: .09em; color: #5b6b82; margin: 0 0 12px; }
.jm-evd-desc { font-size: 15.5px; color: #0b1b33; line-height: 1.75; white-space: pre-wrap; margin: 0; }

/* Host */
.jm-evd-host { display: flex; gap: 11px; align-items: flex-start; }
/* Deliberately smaller than the 42px header logo so it reads as a byline mark, not a second brand lockup. */
.jm-evd-host-av { width: 26px; height: 26px; border-radius: 6px; flex-shrink: 0; display: block; object-fit: contain; margin-top: 1px; }
.jm-evd-host-name { font-size: 15px; font-weight: 800; color: #061426; margin: 0 0 3px; }
.jm-evd-host-bio { font-size: 14px; color: #53667f; line-height: 1.65; margin: 0; white-space: pre-wrap; }

/* Sidebar */
.jm-evd-side { position: sticky; top: 20px; }
.jm-evd-card { border: 1px solid #e4eaf3; border-radius: 12px; padding: 20px; background: #fff; }
.jm-evd-price { font-size: 22px; font-weight: 800; color: #061426; letter-spacing: -.02em; margin: 0 0 16px; }
.jm-evd-price.is-free { color: #0a6454; }
.jm-evd-row { display: flex; gap: 11px; align-items: flex-start; padding: 11px 0; border-top: 1px solid #f1f5fb; }
.jm-evd-row:first-of-type { border-top: 0; padding-top: 0; }
.jm-evd-row svg { width: 17px; height: 17px; color: #94a3b8; flex-shrink: 0; margin-top: 2px; }
.jm-evd-row .l { font-size: 10.5px; font-weight: 800; text-transform: uppercase; letter-spacing: .07em; color: #94a3b8; margin-bottom: 2px; }
.jm-evd-row .v { font-size: 14px; font-weight: 600; color: #0b1b33; line-height: 1.5; }

/* Capacity */
.jm-evd-bar { height: 4px; border-radius: 99px; background: #eef2f8; overflow: hidden; margin-top: 8px; }
.jm-evd-bar span { display: block; height: 100%; background: #0640a3; border-radius: 99px; }
.jm-evd-left { font-size: 12px; font-weight: 700; color: #8a5300; margin-top: 6px; }

/* Actions */
.jm-evd-act { margin-top: 18px; }
.jm-evd-btn { display: block; width: 100%; box-sizing: border-box; text-align: center; background: #0640a3; color: #fff; border: 0; border-radius: 8px; padding: 13px; font: inherit; font-weight: 800; font-size: 14px; cursor: pointer; text-decoration: none; transition: background .14s; }
.jm-evd-btn:hover { background: #052f78; }
.jm-evd-btn.disabled { background: #f1f5fb; color: #94a3b8; cursor: not-allowed; }
.jm-evd-btn.join { background: #0a6454; }
.jm-evd-btn.join:hover { background: #08533f; }
.jm-evd-note { font-size: 12px; color: #53667f; text-align: center; margin: 9px 0 0; line-height: 1.5; }
.jm-evd-cal { display: flex; align-items: center; justify-content: center; gap: 7px; margin-top: 10px; padding: 11px; border: 1px solid #e4eaf3; border-radius: 8px; font-size: 13px; font-weight: 700; color: #53667f; text-decoration: none; transition: border-color .14s, color .14s; }
.jm-evd-cal:hover { border-color: #0640a3; color: #0640a3; }
.jm-evd-cal svg { width: 15px; height: 15px; }

/* Flash */
.jm-evd-flash { border-radius: 8px; padding: 12px 15px; font-size: 13.5px; font-weight: 600; margin-bottom: 20px; }
.jm-evd-flash.ok { background: #e6f5f1; color: #0a6454; }
.jm-evd-flash.err { background: #fdecea; color: #991b1b; }
</style>

<div class="jm-evd">
    <?= jm_breadcrumbs([['label' => 'Events', 'url' => '/jobmington/events/'], ['label' => $event['title']]]) ?>

    <span class="jm-evd-type"><?= e(ucfirst($event['event_type'])) ?></span>
    <h1><?= e($event['title']) ?></h1>
    <p class="jm-evd-sub">
        <b><?= e(date('l, j F Y', $start)) ?></b> &middot; <?= e(date('g:i A', $start)) ?><?= $event['timezone'] ? ' ' . e($event['timezone']) : '' ?> &middot; <?= e($where) ?>
    </p>

    <?php if ($justJoined): ?><div class="jm-evd-flash ok">You're registered. Details are below.</div><?php endif; ?>
    <?php if ($message): ?><div class="jm-evd-flash err"><?= e($message) ?></div><?php endif; ?>

    <div class="jm-evd-grid">
        <div class="jm-evd-main">
            <div class="jm-evd-cover">
                <?php if (!empty($event['cover_image'])): ?>
                    <img src="<?= e($event['cover_image']) ?>" alt="<?= e($event['title']) ?>">
                <?php else: ?>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                <?php endif; ?>
            </div>

            <div class="jm-evd-sec">
                <h2>About this event</h2>
                <p class="jm-evd-desc"><?= e($event['description'] ?: 'Details coming soon.') ?></p>
            </div>

            <?php if (!empty($event['host_name'])): ?>
            <div class="jm-evd-sec">
                <h2>Host</h2>
                <div class="jm-evd-host">
                    <img class="jm-evd-host-av" src="/jobmington/assets/images/badge.png?v=logo-8" alt="">
                    <div>
                        <p class="jm-evd-host-name"><?= e($event['host_name']) ?></p>
                        <?php if (!empty($event['host_bio'])): ?>
                            <p class="jm-evd-host-bio"><?= e($event['host_bio']) ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <aside class="jm-evd-side">
            <div class="jm-evd-card">
                <p class="jm-evd-price<?= $event['is_free'] ? ' is-free' : '' ?>">
                    <?= $event['is_free'] ? 'Free' : '&#8358;' . number_format((float) $event['price']) ?>
                </p>

                <div class="jm-evd-row">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    <div>
                        <div class="l">When</div>
                        <div class="v"><?= e(date('D, j M Y', $start)) ?><br><?= e(date('g:i A', $start)) ?> <?= e($event['timezone']) ?></div>
                    </div>
                </div>

                <div class="jm-evd-row">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                    <div>
                        <div class="l">Where</div>
                        <div class="v"><?= e($where) ?></div>
                    </div>
                </div>

                <div class="jm-evd-row">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                    <div style="flex:1;min-width:0;">
                        <div class="l">Registered</div>
                        <div class="v"><?= $taken ?><?= $capacity > 0 ? ' of ' . $capacity : '' ?></div>
                        <?php if ($capacity > 0): ?>
                            <div class="jm-evd-bar"><span style="width:<?= $fillPct ?>%"></span></div>
                            <?php if (!$isPast && $seatsLeft !== null && $seatsLeft <= 20): ?>
                                <div class="jm-evd-left"><?= $seatsLeft > 0 ? $seatsLeft . ' spots left' : 'Fully booked' ?></div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="jm-evd-act">
                <?php if ($registered): ?>
                    <?php if (!$isPast && !empty($event['meeting_url'])): ?>
                        <a class="jm-evd-btn join" href="<?= e($event['meeting_url']) ?>" target="_blank" rel="noopener">Join the session</a>
                        <p class="jm-evd-note">You're registered. The link is also emailed nearer the time.</p>
                    <?php elseif (!$isPast): ?>
                        <div class="jm-evd-btn disabled">You're registered</div>
                        <p class="jm-evd-note">The join link will appear here before it starts.</p>
                    <?php else: ?>
                        <div class="jm-evd-btn disabled">Event ended</div>
                    <?php endif; ?>
                <?php elseif ($isPast): ?>
                    <div class="jm-evd-btn disabled">Event ended</div>
                <?php elseif ($isFull): ?>
                    <div class="jm-evd-btn disabled">Fully booked</div>
                <?php else: ?>
                    <form method="post">
                        <?= Security::csrfField() ?>
                        <input type="hidden" name="action" value="register">
                        <button class="jm-evd-btn" type="submit"><?= $event['is_free'] ? 'Register free' : 'Register &mdash; &#8358;' . number_format((float) $event['price']) ?></button>
                    </form>
                    <?php if (!Session::isLoggedIn()): ?><p class="jm-evd-note">Sign in or create a free account &mdash; we'll finish your registration automatically.</p><?php endif; ?>
                <?php endif; ?>

                <?php if (!$isPast && $gcalUrl): ?>
                    <a class="jm-evd-cal" href="<?= e($gcalUrl) ?>" target="_blank" rel="noopener">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><line x1="12" y1="14" x2="12" y2="18"/><line x1="10" y1="16" x2="14" y2="16"/></svg>
                        Add to calendar
                    </a>
                <?php endif; ?>
                </div>
            </div>
        </aside>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/ai-footer.php'; ?>
