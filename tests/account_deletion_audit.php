<?php
/**
 * Deleting an account, checked against the live schema.
 *
 * Two halves, and the first matters more than it looks.
 *
 * COVERAGE. There is exactly one foreign key in this database pointing at
 * users.user_id, so nothing cascades and the delete works from a hand-written
 * list of tables. A hand-written list rots: the next feature adds a table with
 * a user_id in it, nobody remembers this file, and from then on every deletion
 * quietly leaves rows behind pointing at a person who does not exist. So the
 * list is checked against information_schema on every run, and a table nobody
 * has decided about is a failure rather than a surprise a year from now.
 *
 * BEHAVIOUR. A fixture account with a row in as many tables as the audit can
 * reach, deleted, then checked: personal rows gone, public content still there
 * and pointing at nobody, money still there, seats given back.
 *
 * @return array<int, string> problems found, empty when healthy
 */
function jm_account_deletion_audit(PDO $pdo): array
{
    require_once __DIR__ . '/../includes/account_deletion.php';

    $problems = [];
    $fail = static function (string $case, string $detail) use (&$problems): void {
        $problems[] = $case . ': ' . $detail;
    };

    /* ================================================================
     * 1. Coverage. Every user-referencing column is classified.
     * ============================================================== */
    $remove = jm_account_remove_map();
    $detach = jm_account_detach_map();

    /*
     * Columns that name a person but are deliberately not part of a deletion.
     * Kept here with a reason so "not covered" always means "nobody decided",
     * never "decided, elsewhere, silently".
     */
    $exempt = [
        'users.user_id' => 'the account row itself',

        /*
         * Dead schema. Present only on older copies of this database, absent
         * from production, and referenced by no code in the repository. Listed
         * rather than classified, because guessing whether a table nobody uses
         * holds personal or public data is exactly the guess this audit exists
         * to prevent. If any of them comes back into use, it comes back with a
         * decision attached.
         */
        'academy_progress.user_id' => 'legacy, no code references it, not on production',
        'password_resets.user_id'  => 'legacy, no code references it, not on production',
        'plaza_posts.user_id'      => 'legacy, no code references it, not on production',
    ];

    $sql = "
        SELECT TABLE_NAME, COLUMN_NAME
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND COLUMN_NAME IN ('user_id','author_id','created_by','seeker_id','posted_by','uploaded_by','sender_id','recipient_id')
        ORDER BY TABLE_NAME, COLUMN_NAME
    ";

    foreach ($pdo->query($sql, PDO::FETCH_ASSOC) as $row) {
        $table = (string) $row['TABLE_NAME'];
        $col   = (string) $row['COLUMN_NAME'];
        $key   = $table . '.' . $col;

        if (isset($exempt[$key])) {
            continue;
        }
        $covered = (isset($remove[$table]) && $remove[$table] === $col)
                || (isset($detach[$table]) && $detach[$table][0] === $col);

        if (!$covered) {
            $fail('coverage', $key . ' is not in the remove or detach list, so deleting an account leaves it orphaned');
        }
    }

    // The reverse: a listed table that no longer exists is a delete that
    // silently does nothing.
    foreach (array_keys($remove) as $table) {
        if (!jm_table_exists($pdo, $table)) {
            $fail('coverage', "remove list names {$table}, which is not in the database");
        }
    }
    foreach (array_keys($detach) as $table) {
        if (!jm_table_exists($pdo, $table)) {
            $fail('coverage', "detach list names {$table}, which is not in the database");
        }
    }

    /* ================================================================
     * 2. Behaviour, on a fixture account.
     * ============================================================== */
    $stamp   = 'jmdel_' . bin2hex(random_bytes(5));
    $userId  = 0;
    $eventId = 0;
    $topicId = 0;

    try {
        $pdo->prepare("
            INSERT INTO users (first_name, last_name, full_name, email, password_hash,
                               user_type, is_active, is_verified, created_at)
            VALUES ('Delete', 'Fixture', 'Delete Fixture', ?, 'x', ?, 1, 1, NOW())
        ")->execute([$stamp . '@example.invalid', USER_TYPE_SEEKER]);
        $userId = (int) $pdo->lastInsertId();

        // Personal: a notification, and a seat on an event.
        $pdo->prepare("INSERT INTO notifications (user_id, type, title, message, created_at) VALUES (?, 'account', 'Fixture', '', NOW())")
            ->execute([$userId]);

        $pdo->prepare("
            INSERT INTO events (title, slug, description, starts_at, ends_at, timezone,
                                is_online, capacity, registration_count, is_published, created_at)
            VALUES (?, ?, 'fixture', ?, ?, 'Africa/Lagos', 1, 50, 5, 0, NOW())
        ")->execute([
            'Delete Audit Event ' . $stamp, $stamp . '-ev',
            date('Y-m-d H:i:s', strtotime('+20 days')),
            date('Y-m-d H:i:s', strtotime('+20 days') + 3600),
        ]);
        $eventId = (int) $pdo->lastInsertId();

        // Five other people, then this one, so the counter and the rows agree
        // the way they do in life. Deleting the account must put it back to 5.
        $pdo->prepare("INSERT INTO event_registrations (event_id, user_id, name, email) VALUES (?, ?, 'Delete Fixture', ?)")
            ->execute([$eventId, $userId, $stamp . '@example.invalid']);
        $pdo->prepare("UPDATE events SET registration_count = 6 WHERE event_id = ?")->execute([$eventId]);

        // Public: a forum topic, which must survive the person.
        $pdo->prepare("INSERT INTO forum_topics (user_id, title, content, created_at) VALUES (?, ?, 'fixture body', NOW())")
            ->execute([$userId, 'Delete Audit Topic ' . $stamp]);
        $topicId = (int) $pdo->lastInsertId();

        // Money: a seeds ledger entry, which must survive the person.
        $hasSeeds = jm_table_exists($pdo, 'seed_transactions');
        if ($hasSeeds) {
            $cols = [];
            foreach ($pdo->query("SHOW COLUMNS FROM seed_transactions") as $c) {
                $cols[$c['Field']] = $c;
            }
            if (isset($cols['amount'])) {
                $pdo->prepare("INSERT INTO seed_transactions (user_id, amount, created_at) VALUES (?, 10, NOW())")
                    ->execute([$userId]);
            } else {
                $hasSeeds = false;
            }
        }

        /* ---- the preview describes it before anything happens ---- */
        $preview = jm_account_deletion_preview($userId, 0);
        if (!$preview['user'])                       { $fail('preview', 'could not read the account'); }
        if (($preview['remove']['notifications'] ?? 0) < 1) { $fail('preview', 'did not count the notification'); }
        if (($preview['detach']['forum_topics']['count'] ?? 0) < 1) { $fail('preview', 'did not count the forum topic'); }
        if ($preview['blockers'])                    { $fail('preview', 'blocked a deletable account: ' . $preview['blockers'][0]); }

        /* ---- guards ---- */
        $self = jm_account_deletion_preview($userId, $userId);
        if (!$self['blockers']) { $fail('guard', 'an admin can delete the account they are signed in with'); }

        /* ---- do it ---- */
        $result = jm_delete_account($userId, 0);
        if (!$result['ok']) { $fail('delete', 'reported failure: ' . $result['message']); }

        /* ---- the account is gone ---- */
        $q = $pdo->prepare("SELECT COUNT(*) FROM users WHERE user_id = ?");
        $q->execute([$userId]);
        if ((int) $q->fetchColumn() !== 0) { $fail('delete', 'the account row survived'); }

        /* ---- nothing personal is left anywhere ---- */
        foreach ($remove as $table => $column) {
            if (!jm_table_exists($pdo, $table)) { continue; }
            $q = $pdo->prepare("SELECT COUNT(*) FROM `{$table}` WHERE `{$column}` = ?");
            $q->execute([$userId]);
            $left = (int) $q->fetchColumn();
            if ($left > 0) { $fail('orphans', "{$left} row(s) left in {$table}"); }
        }

        /* ---- public content survives, pointing at nobody ---- */
        $q = $pdo->prepare("SELECT user_id, title FROM forum_topics WHERE topic_id = ?");
        $q->execute([$topicId]);
        $topic = $q->fetch(PDO::FETCH_ASSOC);
        if (!$topic)                        { $fail('detach', 'the forum topic was deleted with the account'); }
        elseif ($topic['user_id'] !== null) { $fail('detach', 'the forum topic still names the deleted person'); }

        /* ---- money survives, pointing at nobody ---- */
        if ($hasSeeds) {
            $q = $pdo->prepare("SELECT COUNT(*) FROM seed_transactions WHERE user_id = ?");
            $q->execute([$userId]);
            if ((int) $q->fetchColumn() !== 0) { $fail('detach', 'a seeds row still names the deleted person'); }

            $q = $pdo->prepare("SELECT COUNT(*) FROM seed_transactions WHERE user_id IS NULL AND amount = 10");
            $q->execute();
            if ((int) $q->fetchColumn() < 1) { $fail('detach', 'the seeds row was deleted rather than kept'); }
        }

        /* ---- the seat went back ---- */
        $q = $pdo->prepare("SELECT registration_count FROM events WHERE event_id = ?");
        $q->execute([$eventId]);
        $count = (int) $q->fetchColumn();
        if ($count !== 5) { $fail('seats', "registration_count is {$count}, expected it back at 5"); }

    } catch (Throwable $e) {
        $problems[] = 'audit threw: ' . $e->getMessage();
    } finally {
        try {
            if ($userId) {
                foreach (array_keys($remove) as $t) {
                    if (jm_table_exists($pdo, $t)) {
                        $pdo->prepare("DELETE FROM `{$t}` WHERE `{$remove[$t]}` = ?")->execute([$userId]);
                    }
                }
                $pdo->prepare("DELETE FROM users WHERE user_id = ?")->execute([$userId]);
            }
            if ($topicId) { $pdo->prepare("DELETE FROM forum_topics WHERE topic_id = ?")->execute([$topicId]); }
            if ($eventId) {
                $pdo->prepare("DELETE FROM event_registrations WHERE event_id = ?")->execute([$eventId]);
                $pdo->prepare("DELETE FROM events WHERE event_id = ?")->execute([$eventId]);
            }
            // The detached fixture rows have no user to find them by any more.
            if (jm_table_exists($pdo, 'seed_transactions')) {
                $pdo->exec("DELETE FROM seed_transactions WHERE user_id IS NULL AND amount = 10");
            }
        } catch (Throwable $e) {
            $problems[] = 'cleanup failed: ' . $e->getMessage();
        }
    }

    return $problems;
}
