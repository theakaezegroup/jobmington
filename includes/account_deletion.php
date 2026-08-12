<?php
/**
 * JOBMINGTON - deleting an account, properly.
 *
 * Exactly one foreign key in this database points at users.user_id, on
 * saved_jobs. Nothing else cascades, so `DELETE FROM users` removes the person
 * and leaves thirty-odd tables of rows pointing at an id that no longer
 * exists: applications with no applicant, a wallet with no owner, forum posts
 * that break the page that renders them. Every table has to be named.
 *
 * Three fates, decided deliberately:
 *
 *   REMOVE   personal to that person and of no use to anyone else. CV,
 *            applications, notifications, saved jobs, progress, tokens.
 *
 *   DETACH   made by them but not only theirs. A forum topic a dozen people
 *            replied to, a published blog post, a live job listing. The row
 *            stays and points at nobody, so the site does not develop holes
 *            where a member used to be.
 *
 *   DETACH   money. Payments, purchases and subscriptions stay so revenue
 *   (money) history and Paystack reconciliation still add up. They stop naming a
 *            person, which is the part that actually needs to go.
 *
 * Anything that is neither removed nor detached is a bug, so the audit in
 * tests/ checks this list against the live schema and fails when a new table
 * with a user_id appears that nobody has decided about.
 */

if (!defined('JOBMINGTON')) {
    http_response_code(403);
    exit('Forbidden');
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/functions.php';

/**
 * Rows that go with the person.
 *
 * @return array<string, string> table => column
 */
function jm_account_remove_map(): array
{
    return [
        'activity_logs'               => 'user_id',
        'auth_tokens'                 => 'user_id',
        'broadcast_reads'             => 'user_id',
        'certificates'                => 'user_id',
        'content_views'               => 'user_id',
        'course_enrollments'          => 'user_id',
        'employer_talent_access'      => 'user_id',
        'event_registrations'         => 'user_id',
        'forum_likes'                 => 'user_id',
        'forum_reactions'             => 'user_id',
        'job_applications'            => 'user_id',
        'module_progress'             => 'user_id',
        'notifications'               => 'user_id',
        'payment_methods'             => 'user_id',
        'pending_event_registrations' => 'user_id',
        'saved_jobs'                  => 'user_id',
        'talent_passports'            => 'user_id',
        'tool_grants'                 => 'user_id',
        'tool_usage_log'              => 'user_id',
        'user_badges'                 => 'user_id',
        'user_settings'               => 'user_id',   // notification and privacy preferences
        'wallets'                     => 'user_id',
        'cv_profiles'                 => 'user_id',   // children handled separately
    ];
}

/**
 * Rows that stay, pointing at nobody.
 *
 * @return array<string, array{0:string,1:string}> table => [column, why]
 */
function jm_account_detach_map(): array
{
    return [
        'blog_posts'             => ['author_id',  'published posts stay on the blog'],
        'forum_topics'           => ['user_id',    'threads other people replied to stay readable'],
        'forum_replies'          => ['user_id',    'replies stay in their thread'],
        'course_reviews'         => ['user_id',    'reviews stay on the course'],
        'jobs'                   => ['user_id',    'listings stay live under the company'],
        'companies'              => ['user_id',    'the company and its jobs survive the account'],
        'broadcasts'             => ['created_by', 'announcements already sent stay sent'],
        'email_campaigns'        => ['created_by', 'campaign history stays'],
        'transactions'           => ['user_id',    'revenue history and Paystack reconciliation'],
        'course_purchases'       => ['user_id',    'revenue history'],
        'ebook_purchases'        => ['user_id',    'revenue history'],
        'subscriptions'          => ['user_id',    'revenue history'],
        'seeker_subscriptions'   => ['user_id',    'revenue history'],
        'employer_subscriptions' => ['user_id',    'revenue history'],
        'seed_transactions'      => ['user_id',    'Seeds ledger stays balanced'],
    ];
}

/** Does this table exist? Keeps the engine working on a database mid-migration. */
function jm_table_exists(PDO $pdo, string $table): bool
{
    static $cache = [];
    if (!isset($cache[$table])) {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
        $stmt->execute([$table]);
        $cache[$table] = (int) $stmt->fetchColumn() > 0;
    }
    return $cache[$table];
}

/**
 * And does it have the column?
 *
 * Checked separately because a table can be present while the column is not.
 * A copy of this database missing jobs.user_id turned an entire deletion into
 * a thrown query, which is a bad way for one stale column to behave: the whole
 * account then cannot be deleted at all.
 */
function jm_column_exists(PDO $pdo, string $table, string $column): bool
{
    static $cache = [];
    $key = $table . '.' . $column;

    if (!isset($cache[$key])) {
        if (!jm_table_exists($pdo, $table)) {
            return $cache[$key] = false;
        }
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
        $stmt->execute([$table, $column]);
        $cache[$key] = (int) $stmt->fetchColumn() > 0;
    }
    return $cache[$key];
}

/**
 * What deleting this account would do, before doing it.
 *
 * @return array{user:array|null, remove:array<string,int>, detach:array<string,array{count:int,why:string}>, blockers:array<int,string>}
 */
function jm_account_deletion_preview(int $userId, int $byAdminId): array
{
    $pdo = db();

    $stmt = $pdo->prepare('SELECT user_id, full_name, email, user_type, created_at FROM users WHERE user_id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

    $blockers = [];
    if (!$user) {
        return ['user' => null, 'remove' => [], 'detach' => [], 'blockers' => ['That account no longer exists.']];
    }
    if ($userId === $byAdminId) {
        $blockers[] = 'You cannot delete the account you are signed in with.';
    }
    if ($user['user_type'] === USER_TYPE_ADMIN) {
        $others = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE user_type = '" . USER_TYPE_ADMIN . "' AND is_active = 1")->fetchColumn();
        if ($others <= 1) {
            $blockers[] = 'This is the only active admin account. Promote someone else first.';
        }
    }

    $remove = [];
    foreach (jm_account_remove_map() as $table => $column) {
        if (!jm_column_exists($pdo, $table, $column)) {
            continue;
        }
        $q = $pdo->prepare("SELECT COUNT(*) FROM `{$table}` WHERE `{$column}` = ?");
        $q->execute([$userId]);
        $n = (int) $q->fetchColumn();
        if ($n > 0) {
            $remove[$table] = $n;
        }
    }

    $detach = [];
    foreach (jm_account_detach_map() as $table => [$column, $why]) {
        if (!jm_column_exists($pdo, $table, $column)) {
            continue;
        }
        $q = $pdo->prepare("SELECT COUNT(*) FROM `{$table}` WHERE `{$column}` = ?");
        $q->execute([$userId]);
        $n = (int) $q->fetchColumn();
        if ($n > 0) {
            $detach[$table] = ['count' => $n, 'why' => $why];
        }
    }

    return ['user' => $user, 'remove' => $remove, 'detach' => $detach, 'blockers' => $blockers];
}

/**
 * Delete the account.
 *
 * All of it in one transaction: a half-deleted account is worse than either a
 * deleted one or an intact one, and this touches too many tables to leave that
 * to chance.
 *
 * @return array{ok:bool, message:string, removed:int, detached:int}
 */
function jm_delete_account(int $userId, int $byAdminId): array
{
    $pdo     = db();
    $preview = jm_account_deletion_preview($userId, $byAdminId);

    if ($preview['blockers']) {
        return ['ok' => false, 'message' => $preview['blockers'][0], 'removed' => 0, 'detached' => 0];
    }

    $user     = $preview['user'];
    $removed  = 0;
    $detached = 0;

    try {
        $pdo->beginTransaction();

        /*
         * Seats first. registration_count is kept alongside the rows rather
         * than counted from them, so deleting the rows without this leaves
         * every event that person signed up for permanently one seat short.
         */
        if (jm_table_exists($pdo, 'event_registrations')) {
            $pdo->prepare("
                UPDATE events e
                JOIN (SELECT event_id, COUNT(*) AS n FROM event_registrations WHERE user_id = ? GROUP BY event_id) r
                  ON r.event_id = e.event_id
                SET e.registration_count = GREATEST(0, e.registration_count - r.n)
            ")->execute([$userId]);
        }

        /*
         * CV children hang off cv_profiles by cv_id, with no constraint to
         * carry the delete down to them. They go first, while the parent rows
         * are still there to identify them by.
         */
        if (jm_column_exists($pdo, 'cv_profiles', 'cv_id')) {
            foreach (['cv_experience', 'cv_education', 'cv_skills', 'cv_projects', 'cv_certifications', 'cv_references'] as $child) {
                if (!jm_column_exists($pdo, $child, 'cv_id')) {
                    continue;
                }
                $stmt = $pdo->prepare("DELETE FROM `{$child}` WHERE cv_id IN (SELECT cv_id FROM cv_profiles WHERE user_id = ?)");
                $stmt->execute([$userId]);
                $removed += $stmt->rowCount();
            }
        }

        foreach (jm_account_remove_map() as $table => $column) {
            if (!jm_column_exists($pdo, $table, $column)) {
                continue;
            }
            $stmt = $pdo->prepare("DELETE FROM `{$table}` WHERE `{$column}` = ?");
            $stmt->execute([$userId]);
            $removed += $stmt->rowCount();
        }

        foreach (jm_account_detach_map() as $table => [$column, $why]) {
            if (!jm_column_exists($pdo, $table, $column)) {
                continue;
            }
            $stmt = $pdo->prepare("UPDATE `{$table}` SET `{$column}` = NULL WHERE `{$column}` = ?");
            $stmt->execute([$userId]);
            $detached += $stmt->rowCount();
        }

        $stmt = $pdo->prepare('DELETE FROM users WHERE user_id = ?');
        $stmt->execute([$userId]);

        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException('The account row did not delete.');
        }

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Account deletion failed for user ' . $userId . ': ' . $e->getMessage());
        return ['ok' => false, 'message' => 'The deletion failed and nothing was changed. ' . $e->getMessage(), 'removed' => 0, 'detached' => 0];
    }

    /*
     * Logged against the admin who did it, not the person who is gone, so the
     * entry survives the very deletion it records. The email is in the text
     * because there is no longer a row to join to.
     */
    try {
        jm_log_activity(
            $byAdminId,
            'account_deleted',
            sprintf('%s <%s> (%s), %d rows removed, %d detached',
                $user['full_name'], $user['email'], $user['user_type'], $removed, $detached)
        );
    } catch (Throwable $e) {
        error_log('Logging the account deletion failed: ' . $e->getMessage());
    }

    return [
        'ok'       => true,
        'message'  => sprintf('%s was deleted. %s removed, %s kept but no longer named.',
                        $user['full_name'], number_format($removed) . ' rows', number_format($detached) . ' rows'),
        'removed'  => $removed,
        'detached' => $detached,
    ];
}
