<?php
/**
 * Certificate issuance rules.
 *
 * Rule: a PAID course includes the certificate for free. A FREE course charges
 * for certificate issuance (Seeds or Credits). The certificate row only exists
 * once it has actually been issued.
 */
if (!defined('JOBMINGTON')) { die('Direct access not permitted'); }
require_once __DIR__ . '/seeds.php';

/** Is the certificate free for this course? (true when the course itself was paid for) */
function jm_cert_included(array $course): bool {
    return empty($course['is_free']); // is_free = 0  -> paid course -> cert included
}

/** Price for issuing this course's certificate. */
function jm_cert_price(array $course): array {
    if (jm_cert_included($course)) {
        return ['free' => true, 'seeds' => 0, 'credits' => 0];
    }
    return [
        'free'    => false,
        'seeds'   => defined('PRICE_CERT_SEEDS') ? (int) PRICE_CERT_SEEDS : 150,
        'credits' => defined('PRICE_CERT_CREDITS') ? (int) PRICE_CERT_CREDITS : 2,
    ];
}

/** Existing certificate row for a user/course, or null. */
function jm_user_certificate(PDO $pdo, int $userId, int $courseId): ?array {
    $s = $pdo->prepare("SELECT * FROM certificates WHERE user_id = ? AND course_id = ? LIMIT 1");
    $s->execute([$userId, $courseId]);
    return $s->fetch(PDO::FETCH_ASSOC) ?: null;
}

/** Issue (idempotently) and return the cert code. $premium marks paid-for free-course certs. */
function jm_issue_certificate(PDO $pdo, int $userId, int $courseId, bool $premium = false): string {
    $existing = jm_user_certificate($pdo, $userId, $courseId);
    if ($existing) return $existing['cert_code'];
    $code = 'JMT-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(4)));
    try {
        $pdo->prepare("INSERT INTO certificates (cert_code, user_id, course_id, is_premium) VALUES (?,?,?,?)")
            ->execute([$code, $userId, $courseId, $premium ? 1 : 0]);
    } catch (Throwable $e) {
        // older schema without is_premium
        // Finishing a course is the one skills signal we can actually verify.
        try {
            require_once __DIR__ . '/badges.php';
            awardBadge((int) $userId, 'verified-skills');
        } catch (Throwable $e) {
            error_log('Skills badge award failed: ' . $e->getMessage());
        }
        $pdo->prepare("INSERT INTO certificates (cert_code, user_id, course_id) VALUES (?,?,?)")
            ->execute([$code, $userId, $courseId]);
    }
    try { $pdo->prepare("UPDATE courses SET completion_count = completion_count + 1 WHERE course_id = ?")->execute([$courseId]); } catch (Throwable $e) {}
    return $code;
}

/** Has the user completed the course (enrollment marked complete)? */
function jm_course_completed(PDO $pdo, int $userId, int $courseId): bool {
    $s = $pdo->prepare("SELECT completed_at FROM course_enrollments WHERE user_id = ? AND course_id = ? LIMIT 1");
    $s->execute([$userId, $courseId]);
    $row = $s->fetch(PDO::FETCH_ASSOC);
    return $row && !empty($row['completed_at']);
}
