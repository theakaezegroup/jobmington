<?php
/**
 * JOBMINGTON - registering a person for an event, in one place.
 *
 * There are two ways in and they must produce the same result:
 *
 *   1. Someone signed in clicks Register on the event page.
 *   2. Someone signed out clicks Register, is sent to create an account, and
 *      their intent is completed the moment they verify their email.
 *
 * The second route used to be a hand-back: park the destination, and after
 * verification bounce the browser to the event page so route 1 could run.
 * That only worked while the intent was in the same browser session, and a
 * verification link is very often opened somewhere else, because the account
 * gets made on a laptop and the email is read on a phone. When that happened
 * the person was verified, sent to a dashboard, and never registered for the
 * thing that brought them to Jobmington in the first place. Nothing told them.
 *
 * So the intent is stored on the account rather than in the session, and the
 * registration is completed on the server at verification time. Both routes
 * now call the same function, which is the point of the file: the counter, the
 * notification and the confirmation email cannot be right on one path and
 * missing on the other.
 */

if (!defined('JOBMINGTON')) {
    http_response_code(403);
    exit('Forbidden');
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';

/**
 * "Add to calendar" link. Times are stored in the event's own timezone and
 * Google wants UTC.
 */
function jm_event_calendar_url(array $event): string
{
    try {
        $details = preg_match('/^.{0,900}/us', (string) ($event['description'] ?? ''), $dm) ? $dm[0] : '';
        $tz  = new DateTimeZone(($event['timezone'] ?? '') ?: 'Africa/Lagos');
        $gs  = new DateTime($event['starts_at'], $tz);
        $ge  = !empty($event['ends_at']) ? new DateTime($event['ends_at'], $tz) : (clone $gs)->modify('+1 hour');
        $utc = new DateTimeZone('UTC');
        $gs->setTimezone($utc);
        $ge->setTimezone($utc);

        return 'https://calendar.google.com/calendar/render?' . http_build_query([
            'action'   => 'TEMPLATE',
            'text'     => $event['title'] ?? '',
            'dates'    => $gs->format('Ymd\THis\Z') . '/' . $ge->format('Ymd\THis\Z'),
            'details'  => $details,
            'location' => !empty($event['is_online']) ? (($event['meeting_url'] ?? '') ?: 'Online') : (string) ($event['location'] ?? ''),
        ]);
    } catch (Throwable $e) {
        return '';
    }
}

/**
 * Put a person on an event.
 *
 * Safe to call twice: the unique key on (event_id, user_id) is caught and
 * reported as "already", so a retried request cannot double-book a seat or
 * inflate the counter.
 *
 * @return array{status:string, event:array|null, message:string}
 *         status is one of: registered, already, full, past, missing, failed
 */
function jm_register_for_event(int $eventId, int $userId, string $name, string $email): array
{
    $pdo = db();

    $stmt = $pdo->prepare("SELECT * FROM events WHERE event_id = ? LIMIT 1");
    $stmt->execute([$eventId]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$event) {
        return ['status' => 'missing', 'event' => null, 'message' => 'That event could not be found.'];
    }
    if (strtotime($event['starts_at']) < time()) {
        return ['status' => 'past', 'event' => $event, 'message' => 'This event has already taken place.'];
    }

    $held = $pdo->prepare("SELECT 1 FROM event_registrations WHERE event_id = ? AND user_id = ? LIMIT 1");
    $held->execute([$eventId, $userId]);
    if ($held->fetchColumn()) {
        return ['status' => 'already', 'event' => $event, 'message' => 'You are already registered.'];
    }

    $capacity = (int) $event['capacity'];
    if ($capacity > 0 && (int) $event['registration_count'] >= $capacity) {
        return ['status' => 'full', 'event' => $event, 'message' => 'This event is fully booked.'];
    }

    try {
        // Insert and bump the counter together, so the count cannot drift from
        // the number of rows if either statement fails.
        $pdo->beginTransaction();
        $pdo->prepare("INSERT INTO event_registrations (event_id, user_id, name, email) VALUES (?, ?, ?, ?)")
            ->execute([$eventId, $userId, $name, $email]);
        $pdo->prepare("UPDATE events SET registration_count = registration_count + 1 WHERE event_id = ?")
            ->execute([$eventId]);
        $pdo->commit();
        $event['registration_count'] = (int) $event['registration_count'] + 1;
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($e->getCode() === '23000') {
            return ['status' => 'already', 'event' => $event, 'message' => 'You are already registered.'];
        }
        error_log('Event registration failed for event ' . $eventId . ': ' . $e->getMessage());
        return ['status' => 'failed', 'event' => $event, 'message' => 'We could not complete your registration. Please try again.'];
    }

    // Everything past this point is a courtesy. The seat is already booked, so
    // none of it may throw its way back out and make a success look like a
    // failure to the person who just registered.
    try {
        jm_log_activity($userId, 'event_register', (string) ($event['title'] ?? ('event ' . $eventId)));
    } catch (Throwable $e) {
        error_log('Event registration activity log failed: ' . $e->getMessage());
    }

    try {
        sendNotification(
            $userId,
            'event',
            'You are registered for ' . ($event['title'] ?? 'the event'),
            'We will remind you the day before and an hour before it starts.',
            '/events/view.php?slug=' . ($event['slug'] ?? '')
        );
    } catch (Throwable $e) {
        error_log('Event registration notification failed: ' . $e->getMessage());
    }

    if (trim($email) !== '') {
        try {
            require_once __DIR__ . '/mailer.php';
            Mailer::sendEventRegistration(
                $email,
                $name,
                $event,
                SITE_URL . '/events/view.php?slug=' . rawurlencode((string) ($event['slug'] ?? '')),
                jm_event_calendar_url($event)
            );
        } catch (Throwable $e) {
            error_log('Event confirmation email failed for event ' . $eventId . ': ' . $e->getMessage());
        }
    }

    return ['status' => 'registered', 'event' => $event, 'message' => 'You are registered.'];
}

/**
 * The events someone clicked Register on before they had an account.
 *
 * Held per person rather than one at a time, because a visitor browsing events
 * signed out will click Register on two of them and then go and make an
 * account, and losing the first one is not a reasonable outcome.
 */
function jm_park_pending_event(int $userId, int $eventId): void
{
    if ($userId <= 0 || $eventId <= 0) {
        return;
    }
    try {
        db()->prepare("INSERT IGNORE INTO pending_event_registrations (user_id, event_id) VALUES (?, ?)")
            ->execute([$userId, $eventId]);
    } catch (Throwable $e) {
        error_log('Parking pending event intent failed: ' . $e->getMessage());
    }
}

/** @return array<int, int> event ids, oldest click first */
function jm_pending_events(int $userId): array
{
    if ($userId <= 0) {
        return [];
    }
    try {
        $stmt = db()->prepare("SELECT event_id FROM pending_event_registrations WHERE user_id = ? ORDER BY created_at, event_id");
        $stmt->execute([$userId]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    } catch (Throwable $e) {
        error_log('Reading pending event intents failed: ' . $e->getMessage());
        return [];
    }
}

function jm_clear_pending_event(int $userId, int $eventId): void
{
    try {
        db()->prepare("DELETE FROM pending_event_registrations WHERE user_id = ? AND event_id = ?")
            ->execute([$userId, $eventId]);
    } catch (Throwable $e) {
        error_log('Clearing pending event intent failed: ' . $e->getMessage());
    }
}

/**
 * The same set, carried in the session before there is an account to hang it
 * on. Tolerates the single integer the key used to hold, so a session that was
 * already open when this shipped still resumes instead of being dropped.
 *
 * @return array<int, int>
 */
function jm_session_intents(): array
{
    $raw = $_SESSION['pending_event_registration'] ?? null;

    if (is_array($raw)) {
        return array_values(array_unique(array_filter(array_map('intval', $raw))));
    }
    return ((int) $raw) > 0 ? [(int) $raw] : [];
}

function jm_session_intent_add(int $eventId): void
{
    $ids = jm_session_intents();
    if (!in_array($eventId, $ids, true)) {
        $ids[] = $eventId;
    }
    $_SESSION['pending_event_registration'] = $ids;
}

function jm_session_intent_remove(int $eventId): void
{
    $ids = array_values(array_diff(jm_session_intents(), [$eventId]));

    if ($ids) {
        $_SESSION['pending_event_registration'] = $ids;
    } else {
        // Nothing left to finish, so the context that explained the detour
        // goes too rather than following them around the site.
        unset($_SESSION['pending_event_registration'], $_SESSION['auth_context'], $_SESSION['auth_context_for']);
    }
}

/** When the event starts, in its own timezone rather than the server's. */
function jm_event_when(array $event): string
{
    try {
        if (empty($event['starts_at'])) {
            return '';
        }
        return (new DateTime($event['starts_at'], new DateTimeZone(($event['timezone'] ?? '') ?: 'Africa/Lagos')))
            ->format('l j F, g:ia');
    } catch (Throwable $e) {
        return '';
    }
}

/**
 * Finish the registration someone started before they had an account, and
 * leave them a note saying so.
 *
 * Only ever called once there is a signed-in person on the other end of the
 * request, which matters more than it looks. Mail providers fetch the links in
 * a message to scan them, so the first hit on a verification link is routinely
 * a machine with no session. If the intent were settled there, the seat would
 * be booked by the scanner and the note left in a session nobody will ever
 * load, and the human would arrive to the same silence this whole thing exists
 * to fix. So an unsigned request verifies the address and leaves the intent
 * alone; whoever signs in next picks it up.
 *
 * @return bool whether there was an intent to settle
 */
function jm_settle_pending_event(int $userId): bool
{
    $ids = jm_pending_events($userId);
    if (!$ids) {
        return false;
    }

    $pdo = db();
    $who = $pdo->prepare("SELECT full_name, email FROM users WHERE user_id = ? LIMIT 1");
    $who->execute([$userId]);
    $person = $who->fetch(PDO::FETCH_ASSOC) ?: ['full_name' => '', 'email' => ''];

    $done = [];   // registered or already on the list
    $lost = [];   // full, finished or gone: worth saying, nothing to retry
    $stuck = 0;   // a real failure, left parked for the next attempt

    foreach ($ids as $eventId) {
        $outcome = jm_register_for_event($eventId, $userId, (string) $person['full_name'], (string) $person['email']);
        $event   = $outcome['event'];
        $title   = (string) ($event['title'] ?? 'an event');
        $slug    = (string) ($event['slug'] ?? '');

        switch ($outcome['status']) {
            case 'registered':
            case 'already':
                $done[] = ['title' => $title, 'slug' => $slug, 'when' => jm_event_when($event ?? [])];
                jm_clear_pending_event($userId, $eventId);
                break;

            case 'full':
                $lost[] = $title . ' filled up while you were confirming your email';
                jm_clear_pending_event($userId, $eventId);
                break;

            case 'past':
                $lost[] = $title . ' has already taken place';
                jm_clear_pending_event($userId, $eventId);
                break;

            case 'missing':
                // Nothing to go back to, so nothing to retry either.
                jm_clear_pending_event($userId, $eventId);
                break;

            default:
                // A real error. Keep it parked so the next sign-in tries again.
                $stuck++;
                break;
        }
    }

    jm_flash_set(...jm_compose_event_note($done, $lost, $stuck));

    return true;
}

/**
 * Turn what happened into one note.
 *
 * Split out because settling several events at once has more outcomes than
 * reads comfortably inside the loop that produces them, and because the exact
 * wording is the whole point of this feature rather than an afterthought.
 *
 * @param array<int, array{title:string,slug:string,when:string}> $done
 * @param array<int, string> $lost human phrases, already past-tense
 * @return array{0:string,1:string,2:string,3:string,4:string} args for jm_flash_set
 */
function jm_compose_event_note(array $done, array $lost, int $stuck): array
{
    $listing = static function (array $phrases): string {
        if (count($phrases) === 1) {
            return $phrases[0];
        }
        $last = array_pop($phrases);
        return implode(', ', $phrases) . ' and ' . $last;
    };

    $eventsUrl = '/jobmington/events/';
    $link      = $done ? '/jobmington/events/view.php?slug=' . rawurlencode($done[0]['slug']) : $eventsUrl;
    $linkText  = 'View the event';

    if (count($done) > 1) {
        $link     = $eventsUrl;
        $linkText = 'See all events';
    }

    // Nothing went through at all.
    if (!$done) {
        if ($stuck > 0) {
            return [
                'We could not finish your event registration.',
                'Your account is verified and ready. Open the event and press Register once more, and it will go through.',
                $eventsUrl, 'Browse events', 'warn',
            ];
        }
        if ($lost) {
            return [
                'About the event you signed up for.',
                ucfirst($listing($lost)) . '. Your account is ready, and the event page will have the details.',
                $eventsUrl, 'Browse events', 'warn',
            ];
        }
        // An intent pointing at nothing that still exists.
        return [
            'Your account is ready.',
            'The event you originally signed up for is no longer listed, but everything else on Jobmington is open to you.',
            $eventsUrl, 'Browse events', 'warn',
        ];
    }

    $titles = array_column($done, 'title');

    if (count($done) === 1) {
        $when  = $done[0]['when'];
        $title = 'You are registered for ' . $titles[0] . '.';
        $body  = 'That is what you came to Jobmington for, and it is confirmed'
               . ($when !== '' ? ' for ' . $when : '')
               . '. The details are in your email, and we will remind you the day before and an hour before it starts.';
    } else {
        $each = [];
        foreach ($done as $d) {
            $each[] = $d['when'] !== '' ? $d['title'] . ' on ' . $d['when'] : $d['title'];
        }
        $title = 'You are registered for ' . count($done) . ' events.';
        $body  = $listing($each) . '. Those are what you came to Jobmington for, the details are in your email, '
               . 'and we will remind you before each one.';
    }

    // Some worked and some did not. Say both rather than only the good half.
    if ($lost || $stuck > 0) {
        if ($lost) {
            $body .= ' One thing though: ' . $listing($lost) . '.';
        }
        if ($stuck > 0) {
            $body .= ' We could not finish one of the others, so open it and press Register once more.';
        }
        return [$title, $body, $link, $linkText, 'warn'];
    }

    return [$title, $body, $link, $linkText, 'ok'];
}
