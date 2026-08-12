<?php
/**
 * The journey: click Register on an event, be told to make an account, verify,
 * and arrive somewhere that says what happened to the registration.
 *
 * This existed as a bug report before it existed as a test. People who signed
 * up purely to attend an event were verified and dropped on a dashboard with no
 * mention of the event, and a good number were never actually registered,
 * because the intent lived in the session and the verification link is very
 * often opened on a different device from the one that made the account.
 *
 * Every case below is a route a real person takes. The plain sign-in ones are
 * here because they are the common path and must stay untouched by all of this.
 *
 * Everything is created and removed inside the run, so it is safe against a
 * live database.
 *
 * @return array<int, string> problems found, empty when healthy
 */
function jm_event_intent_audit(PDO $pdo): array
{
    require_once __DIR__ . '/../includes/event_registration.php';

    $problems = [];
    $stamp    = 'jmtest_' . bin2hex(random_bytes(5));
    $userIds  = [];
    $eventIds = [];

    $fail = static function (string $case, string $detail) use (&$problems): void {
        $problems[] = $case . ': ' . $detail;
    };

    /** Make a throwaway event. $when is anything strtotime understands. */
    $makeEvent = function (string $when, int $capacity, int $taken = 0) use ($pdo, $stamp, &$eventIds): int {
        $slug = $stamp . '-' . bin2hex(random_bytes(3));
        $pdo->prepare("
            INSERT INTO events (title, slug, description, starts_at, ends_at, timezone,
                                is_online, capacity, registration_count, is_published, created_at)
            VALUES (?, ?, 'audit fixture', ?, ?, 'Africa/Lagos', 1, ?, ?, 1, NOW())
        ")->execute([
            'Audit Event ' . $slug,
            $slug,
            date('Y-m-d H:i:s', strtotime($when)),
            date('Y-m-d H:i:s', strtotime($when) + 3600),
            $capacity,
            $taken,
        ]);
        $id = (int) $pdo->lastInsertId();
        $eventIds[] = $id;
        return $id;
    };

    /** Make a throwaway account, optionally with an event intent parked on it. */
    $makeUser = function (?int $pendingEventId) use ($pdo, $stamp, &$userIds): int {
        $email = $stamp . '-' . bin2hex(random_bytes(3)) . '@example.invalid';
        $pdo->prepare("
            INSERT INTO users (first_name, last_name, full_name, email, password_hash,
                               user_type, is_active, is_verified, pending_event_id, created_at)
            VALUES ('Audit', 'Fixture', 'Audit Fixture', ?, 'x', 'job_seeker', 1, 1, ?, NOW())
        ")->execute([$email, $pendingEventId]);
        $id = (int) $pdo->lastInsertId();
        $userIds[] = $id;
        return $id;
    };

    $registered = function (int $eventId, int $userId) use ($pdo): bool {
        $s = $pdo->prepare("SELECT 1 FROM event_registrations WHERE event_id = ? AND user_id = ? LIMIT 1");
        $s->execute([$eventId, $userId]);
        return (bool) $s->fetchColumn();
    };
    $countOn = function (int $eventId) use ($pdo): int {
        $s = $pdo->prepare("SELECT registration_count FROM events WHERE event_id = ?");
        $s->execute([$eventId]);
        return (int) $s->fetchColumn();
    };
    $intentOn = function (int $userId) use ($pdo): int {
        $s = $pdo->prepare("SELECT pending_event_id FROM users WHERE user_id = ?");
        $s->execute([$userId]);
        return (int) ($s->fetchColumn() ?: 0);
    };
    $notifications = function (int $userId) use ($pdo): int {
        $s = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND type = 'event'");
        $s->execute([$userId]);
        return (int) $s->fetchColumn();
    };

    try {
        /* ---------------------------------------------------------------
         * 1. The whole point: an intent is settled, and the note names the
         *    event rather than saying something generic about the account.
         * ------------------------------------------------------------- */
        $event = $makeEvent('+30 days', 50);
        $user  = $makeUser($event);
        $_SESSION = [];

        $settled = jm_settle_pending_event($user);
        $flash   = jm_flash_take();

        if (!$settled)                       { $fail('settle', 'reported nothing to do when an intent was parked'); }
        if (!$registered($event, $user))     { $fail('settle', 'no registration row was written'); }
        if ($countOn($event) !== 1)          { $fail('settle', 'registration_count is ' . $countOn($event) . ', expected 1'); }
        if ($intentOn($user) !== 0)          { $fail('settle', 'intent was left on the account after settling'); }
        if ($notifications($user) !== 1)     { $fail('settle', 'expected 1 event notification, got ' . $notifications($user)); }

        if (!$flash) {
            $fail('note', 'no note was left for the dashboard');
        } else {
            $title = $pdo->query("SELECT title FROM events WHERE event_id = {$event}")->fetchColumn();
            if (!str_contains($flash['title'], (string) $title)) {
                $fail('note', 'the note does not name the event that brought them in');
            }
            if (($flash['tone'] ?? '') !== 'ok') {
                $fail('note', 'a successful registration produced a warning note');
            }
            if (!str_contains($flash['link_url'], 'events/')) {
                $fail('note', 'the note does not link back to the event');
            }
        }

        /* ---------------------------------------------------------------
         * 2. Read once. A refresh must not keep re-announcing old news.
         * ------------------------------------------------------------- */
        if (jm_flash_take() !== null) {
            $fail('note', 'the note survived being read, so a refresh repeats it');
        }

        /* ---------------------------------------------------------------
         * 3. Settling twice. The mail scanner, the browser and a refresh can
         *    all arrive; none of them may book a second seat.
         * ------------------------------------------------------------- */
        $again = jm_settle_pending_event($user);
        jm_flash_take();
        if ($again)                 { $fail('repeat', 'a second settle found work to do'); }
        if ($countOn($event) !== 1) { $fail('repeat', 'registration_count moved to ' . $countOn($event) . ' on a repeat'); }

        /* ---------------------------------------------------------------
         * 4. Registering directly is idempotent too, and reports "already"
         *    rather than inventing a second row.
         * ------------------------------------------------------------- */
        $direct = jm_register_for_event($event, $user, 'Audit Fixture', '');
        if ($direct['status'] !== 'already') { $fail('idempotent', 'a repeat registration returned ' . $direct['status']); }
        if ($countOn($event) !== 1)          { $fail('idempotent', 'a repeat registration moved the counter'); }

        /* ---------------------------------------------------------------
         * 5. Plain sign-in, nothing parked. The common path: it must do
         *    nothing at all and leave no note behind.
         * ------------------------------------------------------------- */
        $plain = $makeUser(null);
        if (jm_settle_pending_event($plain)) { $fail('plain sign-in', 'reported work to do for a user with no intent'); }
        if (jm_flash_take() !== null)        { $fail('plain sign-in', 'left a note on a dashboard for no reason'); }
        if ($notifications($plain) !== 0)    { $fail('plain sign-in', 'sent a notification for no reason'); }

        /* ---------------------------------------------------------------
         * 6. An intent pointing at an event that filled up while they were
         *    reading their email. Say so; do not claim a seat that is gone.
         * ------------------------------------------------------------- */
        $fullEvent = $makeEvent('+30 days', 2, 2);
        $fullUser  = $makeUser($fullEvent);
        jm_settle_pending_event($fullUser);
        $fullFlash = jm_flash_take();

        if ($registered($fullEvent, $fullUser))       { $fail('full event', 'registered someone into a full event'); }
        if (!$fullFlash)                              { $fail('full event', 'said nothing about the seat being gone'); }
        elseif (($fullFlash['tone'] ?? '') !== 'warn'){ $fail('full event', 'reported a full event as success'); }
        if ($intentOn($fullUser) !== 0)               { $fail('full event', 'left the intent parked forever'); }

        /* ---------------------------------------------------------------
         * 7. An intent pointing at an event that has already happened.
         * ------------------------------------------------------------- */
        $pastEvent = $makeEvent('-10 days', 50);
        $pastUser  = $makeUser($pastEvent);
        jm_settle_pending_event($pastUser);
        $pastFlash = jm_flash_take();

        if ($registered($pastEvent, $pastUser))       { $fail('past event', 'registered someone into a finished event'); }
        if (!$pastFlash)                              { $fail('past event', 'said nothing about the event being over'); }
        elseif (($pastFlash['tone'] ?? '') !== 'warn'){ $fail('past event', 'reported a finished event as success'); }

        /* ---------------------------------------------------------------
         * 8. An intent pointing at an event that has since been deleted.
         *    The account must still come out of this usable.
         * ------------------------------------------------------------- */
        $goneUser = $makeUser(2147483600);
        jm_settle_pending_event($goneUser);
        $goneFlash = jm_flash_take();
        if (!$goneFlash) { $fail('missing event', 'said nothing at all'); }

    } catch (Throwable $e) {
        $problems[] = 'audit threw: ' . $e->getMessage();
    } finally {
        // Fixtures never outlive the run, on a live database or otherwise.
        try {
            if ($userIds) {
                $in = implode(',', array_map('intval', $userIds));
                $pdo->exec("DELETE FROM event_registrations WHERE user_id IN ({$in})");
                $pdo->exec("DELETE FROM notifications WHERE user_id IN ({$in})");
                $pdo->exec("DELETE FROM activity_logs WHERE user_id IN ({$in})");
                $pdo->exec("DELETE FROM users WHERE user_id IN ({$in})");
            }
            if ($eventIds) {
                $in = implode(',', array_map('intval', $eventIds));
                $pdo->exec("DELETE FROM event_registrations WHERE event_id IN ({$in})");
                $pdo->exec("DELETE FROM events WHERE event_id IN ({$in})");
            }
        } catch (Throwable $e) {
            $problems[] = 'cleanup failed: ' . $e->getMessage();
        }
        unset($_SESSION['jm_flash']);
    }

    return $problems;
}
