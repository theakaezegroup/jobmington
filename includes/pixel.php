<?php
/**
 * JOBMINGTON - the Meta (Facebook) advertising pixel.
 *
 * Off unless FB_PIXEL_ID is set, so the site behaves exactly as it does today
 * until an id is put in .env. Nothing here is committed to git.
 *
 * Two things this does that the copy-pasted snippet from Meta does not:
 *
 *   Admins are never tracked. Every page an admin opens while moderating would
 *   otherwise be counted as audience behaviour, and the people who use the back
 *   office hardest are the ones least like a customer.
 *
 *   Conversions survive a redirect. The moments worth measuring all end in one:
 *   verifying an email lands on a dashboard, a Paystack callback lands on a
 *   success page. An event fired on the page that redirects is a race the
 *   browser usually loses, so events are queued in the session and rendered on
 *   the next page the visitor actually sees.
 */

declare(strict_types=1);

if (!defined('JOBMINGTON')) { http_response_code(403); exit('Direct access denied'); }

const JM_PIXEL_QUEUE_KEY = 'jm_pixel_events';

/** The configured pixel id, or '' when advertising tracking is off. */
function jm_pixel_id(): string {
    return defined('FB_PIXEL_ID') ? trim((string) FB_PIXEL_ID) : '';
}

/**
 * Whether to render anything at all.
 *
 * The id has to be set, and the visitor must not be staff. Session may not be
 * started on a bare page, so the role is read defensively.
 */
function jm_pixel_active(): bool {
    if (jm_pixel_id() === '') return false;
    if (class_exists('Session') && method_exists('Session', 'isAdmin') && Session::isAdmin()) return false;
    if (($_SESSION['user_type'] ?? '') === 'admin') return false;
    return true;
}

/**
 * Record a standard event to fire on the next rendered page.
 *
 * $params is passed to Meta as-is, so it must carry no personal data: value and
 * currency for a purchase, a content name at most. Never an email or a name.
 */
function jm_pixel_track(string $event, array $params = []): void {
    if (jm_pixel_id() === '') return;
    if (session_status() !== PHP_SESSION_ACTIVE) return;

    $_SESSION[JM_PIXEL_QUEUE_KEY][] = ['event' => $event, 'params' => $params];

    // A queue only grows if something upstream is looping. Cap it rather than
    // let a bad path fill the session.
    if (count($_SESSION[JM_PIXEL_QUEUE_KEY]) > 10) {
        $_SESSION[JM_PIXEL_QUEUE_KEY] = array_slice($_SESSION[JM_PIXEL_QUEUE_KEY], -10);
    }
}

/** Take the queued events and clear them, so each one fires exactly once. */
function jm_pixel_drain_queue(): array {
    $queued = $_SESSION[JM_PIXEL_QUEUE_KEY] ?? [];
    unset($_SESSION[JM_PIXEL_QUEUE_KEY]);
    return is_array($queued) ? $queued : [];
}

/**
 * The base pixel, PageView, and anything queued. Goes in <head>.
 *
 * $nonce is the page's CSP nonce where one exists. No policy is sent today, but
 * the header already mints a nonce for every other inline script and this
 * should not be the one that has to be found later.
 */
function jm_pixel_head(?string $nonce = null): void {
    if (!jm_pixel_active()) return;

    $id     = jm_pixel_id();
    $queued = jm_pixel_drain_queue();
    $attr   = ($nonce !== null && $nonce !== '') ? ' nonce="' . htmlspecialchars($nonce, ENT_QUOTES) . '"' : '';

    $lines = '';
    foreach ($queued as $item) {
        $event  = (string) ($item['event'] ?? '');
        if ($event === '' || !preg_match('/^[A-Za-z]+$/', $event)) continue;
        $params = is_array($item['params'] ?? null) ? $item['params'] : [];
        $lines .= $params
            ? "  fbq('track', " . json_encode($event) . ", " . json_encode($params, JSON_UNESCAPED_SLASHES) . ");\n"
            : "  fbq('track', " . json_encode($event) . ");\n";
    }
    ?>
    <script<?= $attr ?>>
      !function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?
      n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;
      n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;
      t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,
      document,'script','https://connect.facebook.net/en_US/fbevents.js');
      fbq('init', <?= json_encode($id) ?>);
      fbq('track', 'PageView');
<?= $lines ?>
    </script>
    <noscript><img height="1" width="1" style="display:none" alt=""
      src="https://www.facebook.com/tr?id=<?= rawurlencode($id) ?>&ev=PageView&noscript=1"></noscript>
    <?php
}
