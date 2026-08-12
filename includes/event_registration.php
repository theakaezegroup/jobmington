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
    $pdo = db();

    try {
        $stmt = $pdo->prepare("SELECT pending_event_id FROM users WHERE user_id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $eventId = (int) ($stmt->fetchColumn() ?: 0);
    } catch (Throwable $e) {
        error_log('Reading pending event intent failed: ' . $e->getMessage());
        return false;
    }

    if ($eventId <= 0) {
        return false;
    }

    $who = $pdo->prepare("SELECT full_name, email FROM users WHERE user_id = ? LIMIT 1");
    $who->execute([$userId]);
    $person = $who->fetch(PDO::FETCH_ASSOC) ?: ['full_name' => '', 'email' => ''];

    $outcome = jm_register_for_event($eventId, $userId, (string) $person['full_name'], (string) $person['email']);
    $event   = $outcome['event'];
    $title   = (string) ($event['title'] ?? 'the event');
    $link    = '/jobmington/events/view.php?slug=' . rawurlencode((string) ($event['slug'] ?? ''));

    // The date, in the event's own timezone rather than the server's.
    $when = '';
    try {
        if (!empty($event['starts_at'])) {
            $when = (new DateTime($event['starts_at'], new DateTimeZone(($event['timezone'] ?? '') ?: 'Africa/Lagos')))
                ->format('l j F, g:ia');
        }
    } catch (Throwable $e) {
        $when = '';
    }

    /*
     * The note names the thing they originally came to do. Someone who signed
     * up purely to attend an event does not arrive at a dashboard thinking
     * "dashboard", they arrive thinking "did my event registration work".
     */
    switch ($outcome['status']) {
        case 'registered':
        case 'already':
            jm_flash_set(
                'You are registered for ' . $title . '.',
                'That is what you came to Jobmington for, and it is confirmed'
                    . ($when !== '' ? ' for ' . $when : '')
                    . '. The details are in your email, and we will remind you the day before and an hour before it starts.',
                $link,
                'View the event',
                'ok'
            );
            break;

        case 'full':
            jm_flash_set(
                'We could not hold you a seat for ' . $title . '.',
                'It filled up while you were confirming your email address. Your account is ready, and the event page will say if a place opens up.',
                $link,
                'View the event',
                'warn'
            );
            break;

        case 'past':
            jm_flash_set(
                $title . ' has already taken place.',
                'That is the event you signed up to attend. Your account is ready, and the recording will be on the event page if there is one.',
                $link,
                'View the event',
                'warn'
            );
            break;

        default:
            // Leave the intent in place so the next sign-in can try again.
            jm_flash_set(
                'We could not finish your registration for ' . $title . '.',
                'Your account is verified and ready. Open the event and press Register once more, and it will go through.',
                $link,
                'Finish registering',
                'warn'
            );
            return true;
    }

    try {
        $pdo->prepare("UPDATE users SET pending_event_id = NULL WHERE user_id = ?")->execute([$userId]);
    } catch (Throwable $e) {
        error_log('Clearing pending event intent failed: ' . $e->getMessage());
    }

    return true;
}
