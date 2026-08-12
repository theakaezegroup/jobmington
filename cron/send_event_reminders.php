<?php
/**
 * JOBMINGTON - Event reminders.
 *
 * Sends two reminders per registration: roughly 24 hours before an event, and
 * roughly 1 hour before. Intended to run every 15 minutes.
 *
 *   php cron/send_event_reminders.php            send
 *   php cron/send_event_reminders.php --dry-run  report what would send
 *
 * Each row is claimed with a conditional UPDATE before its mail is sent, so an
 * overlapping or slow run cannot mail the same person twice. The cost of that
 * ordering is that a send failing means the reminder is skipped rather than
 * retried, which is the right way round: a missed reminder is a small loss, a
 * duplicate is an annoyance the recipient cannot undo.
 */
define('JOBMINGTON', true);
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/mailer.php';

$dryRun = in_array('--dry-run', $argv ?? [], true);
$pdo    = db();
$now    = date('Y-m-d H:i:s');

function jm_log(string $line): void {
    echo '[' . date('Y-m-d H:i:s') . '] ' . $line . PHP_EOL;
}

jm_log($dryRun ? 'Event reminders (dry run)' : 'Event reminders');

/**
 * @param string $column   reminder_24h_at or reminder_1h_at
 * @param string $within   MySQL interval for how far ahead to look
 * @param string $window   '24h' or '1h', passed to the mail composer
 */
function jm_send_window(PDO $pdo, string $column, string $within, string $window, bool $dryRun): array {
    $sent = 0; $failed = 0; $skipped = 0;

    // Only future events, only published ones, only registrations that have not
    // had this reminder. Ordering by start keeps the most urgent first if a run
    // is interrupted.
    $stmt = $pdo->prepare("
        SELECT r.registration_id, r.user_id, r.name, r.email,
               e.event_id, e.title, e.slug, e.description, e.cover_image, e.starts_at, e.ends_at,
               e.timezone, e.is_online, e.location, e.meeting_url, e.host_name
        FROM event_registrations r
        JOIN events e ON e.event_id = r.event_id
        WHERE e.is_published = 1
          AND e.starts_at > NOW()
          AND e.starts_at <= DATE_ADD(NOW(), INTERVAL {$within})
          AND r.{$column} IS NULL
        ORDER BY e.starts_at ASC
    ");
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$rows) {
        jm_log("  {$window}: nothing due");
        return [0, 0, 0];
    }

    $claim = $pdo->prepare("UPDATE event_registrations SET {$column} = NOW()
                            WHERE registration_id = ? AND {$column} IS NULL");

    foreach ($rows as $r) {
        $to = trim((string) $r['email']);
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $skipped++;
            jm_log("  {$window}: skip registration {$r['registration_id']}, no usable address");
            continue;
        }

        if ($dryRun) {
            $sent++;
            jm_log("  {$window}: would send to {$to} for \"{$r['title']}\"");
            continue;
        }

        // Claim first. If another run took it, affected rows is 0 and we move on.
        $claim->execute([(int) $r['registration_id']]);
        if ($claim->rowCount() !== 1) {
            $skipped++;
            continue;
        }

        try {
            $eventUrl = SITE_URL . '/events/view.php?slug=' . rawurlencode((string) $r['slug']);
            $calendar = jm_event_calendar_url($r);
            $ok = Mailer::sendEventReminder($to, (string) $r['name'], $r, $eventUrl, $window, $calendar);
            // Same reminder in the app, for anyone who does not read email.
            if ($ok && !empty($r['user_id'])) {
                sendNotification(
                    (int) $r['user_id'],
                    'event',
                    $window === '1h' ? 'Starting within the hour: ' . $r['title'] : 'Tomorrow: ' . $r['title'],
                    $window === '1h' ? 'Your session begins shortly.' : 'A reminder that you are registered.',
                    $eventUrl
                );
            }
            if ($ok) {
                $sent++;
                jm_log("  {$window}: sent to {$to}");
            } else {
                $failed++;
                jm_log("  {$window}: FAILED for {$to}");
            }
        } catch (Throwable $e) {
            $failed++;
            jm_log("  {$window}: ERROR for {$to}: " . $e->getMessage());
        }
    }

    return [$sent, $failed, $skipped];
}

/** Google Calendar link, built from the event's own timezone. */
function jm_event_calendar_url(array $e): string {
    try {
        // preg rather than mb_substr: mbstring is not installed on the server.
        $details = preg_match('/^.{0,900}/us', (string) $e['description'], $m) ? $m[0] : '';
        $tz  = new DateTimeZone($e['timezone'] ?: 'Africa/Lagos');
        $gs  = new DateTime($e['starts_at'], $tz);
        $ge  = !empty($e['ends_at']) ? new DateTime($e['ends_at'], $tz) : (clone $gs)->modify('+1 hour');
        $utc = new DateTimeZone('UTC');
        $gs->setTimezone($utc);
        $ge->setTimezone($utc);
        return 'https://calendar.google.com/calendar/render?' . http_build_query([
            'action'   => 'TEMPLATE',
            'text'     => $e['title'],
            'dates'    => $gs->format('Ymd\THis\Z') . '/' . $ge->format('Ymd\THis\Z'),
            'details'  => $details,
            'location' => !empty($e['is_online']) ? ($e['meeting_url'] ?: 'Online') : (string) $e['location'],
        ]);
    } catch (Throwable $e) {
        return '';
    }
}

// The 1-hour pass runs first: if a run is cut short, the more urgent mail has
// already gone out.
[$s1, $f1, $k1] = jm_send_window($pdo, 'reminder_1h_at',  '1 HOUR',  '1h',  $dryRun);
[$s2, $f2, $k2] = jm_send_window($pdo, 'reminder_24h_at', '24 HOUR', '24h', $dryRun);

jm_log(sprintf('Done. sent=%d failed=%d skipped=%d', $s1 + $s2, $f1 + $f2, $k1 + $k2));
