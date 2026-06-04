<?php
/**
 * JOBMINGTON - Claim certificate for a FREE course (paid issuance).
 * Paid courses issue the certificate automatically for free.
 */
define('JOBMINGTON', true);
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/seeds.php';
require_once __DIR__ . '/../includes/certificates.php';

Session::start();
$pdo = db();

$courseId = (int) get('course', 0);
if (!$courseId) redirect('/jobmington/learn/');

if (!Session::isLoggedIn()) {
    redirect('/jobmington/auth/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI'] ?? '/certificates/claim.php?course=' . $courseId));
}
$userId = (int) Session::userId();

$stmt = $pdo->prepare("SELECT * FROM courses WHERE course_id = ? LIMIT 1");
$stmt->execute([$courseId]);
$course = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$course || empty($course['has_certificate'])) redirect('/jobmington/learn/');

// Already have it?
$existing = jm_user_certificate($pdo, $userId, $courseId);
if ($existing) redirect('/jobmington/certificates/view.php?code=' . $existing['cert_code']);

// Paid course -> certificate is free; issue immediately.
if (jm_cert_included($course)) {
    $code = jm_issue_certificate($pdo, $userId, $courseId, false);
    redirect('/jobmington/certificates/view.php?code=' . $code);
}

// Must have completed the (free) course before paying for the certificate.
if (!jm_course_completed($pdo, $userId, $courseId)) {
    $_SESSION['error'] = 'Finish the course before claiming your certificate.';
    redirect('/jobmington/learn/course.php?id=' . $courseId);
}

$price   = jm_cert_price($course);
$wallet  = jm_wallet_summary($userId);
$err     = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['claim'])) {
    if (!Security::verifyCSRF()) {
        $err = 'Session expired. Please try again.';
    } else {
        $method = (string) post('method', 'seeds');
        $pay = $method === 'credits'
            ? jm_pay_with_credits($userId, (int) $price['credits'], 'certificate_issue', 'Certificate: ' . $course['title'], $courseId)
            : jm_pay_with_seeds($userId, (float) $price['seeds'], 'certificate_issue', 'Certificate: ' . $course['title'], $courseId);
        if (!empty($pay['success'])) {
            $code = jm_issue_certificate($pdo, $userId, $courseId, true);
            redirect('/jobmington/certificates/view.php?code=' . $code . '&claimed=1');
        }
        $err = $pay['message'] ?? 'Payment failed.';
    }
}

$pageTitle = 'Claim your certificate - ' . SITE_NAME;
$activeAIPage = 'learn';
require_once __DIR__ . '/../includes/ai-header.php';
?>
<div style="max-width:560px;margin:0 auto;padding:40px 20px 72px;">
    <?= jm_breadcrumbs([['label' => 'Learn', 'url' => '/jobmington/learn/'], ['label' => 'Claim certificate']]) ?>
    <div style="background:#fff;border:1px solid #e4eaf3;border-radius:16px;padding:28px;text-align:center;">
        <div style="width:64px;height:64px;border-radius:50%;background:#eef3ff;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;color:#0640a3;">
            <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="6"/><path d="M15.477 12.89 17 22l-5-3-5 3 1.523-9.11"/></svg>
        </div>
        <h1 style="font-size:22px;font-weight:800;color:#061426;margin:0 0 6px;">Claim your certificate</h1>
        <p style="font-size:14px;color:#53667f;margin:0 0 4px;"><?= e($course['title']) ?></p>
        <p style="font-size:13px;color:#7c8aa0;margin:0 0 22px;">You completed this free course. Issue your verified certificate with Seeds or Credits.</p>

        <?php if ($err): ?><div style="background:#fef2f2;border:1px solid #fecaca;color:#b42318;padding:10px 14px;border-radius:10px;font-size:13px;margin-bottom:16px;"><?= e($err) ?></div><?php endif; ?>

        <div style="display:flex;gap:10px;justify-content:center;flex-wrap:wrap;">
            <form method="post" style="display:inline;">
                <?= csrf_field() ?><input type="hidden" name="method" value="seeds">
                <button type="submit" name="claim" style="background:#0640a3;color:#fff;border:0;border-radius:10px;padding:13px 22px;font-weight:800;font-size:14px;cursor:pointer;<?= $wallet['seeds'] < $price['seeds'] ? 'opacity:.55;cursor:not-allowed;' : '' ?>" <?= $wallet['seeds'] < $price['seeds'] ? 'disabled' : '' ?>>
                    Pay <?= number_format($price['seeds']) ?> Seeds
                </button>
            </form>
            <form method="post" style="display:inline;">
                <?= csrf_field() ?><input type="hidden" name="method" value="credits">
                <button type="submit" name="claim" style="background:#fff;color:#0640a3;border:1px solid #d8e4f4;border-radius:10px;padding:13px 22px;font-weight:800;font-size:14px;cursor:pointer;<?= $wallet['credits'] < $price['credits'] ? 'opacity:.55;cursor:not-allowed;' : '' ?>" <?= $wallet['credits'] < $price['credits'] ? 'disabled' : '' ?>>
                    or <?= (int) $price['credits'] ?> Credits
                </button>
            </form>
        </div>
        <p style="font-size:12px;color:#7c8aa0;margin:16px 0 0;">Balance: <?= number_format($wallet['seeds']) ?> Seeds &middot; <?= number_format($wallet['credits']) ?> Credits &middot; <a href="/jobmington/wallet/" style="color:#0640a3;">Top up / convert</a></p>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/ai-footer.php'; ?>
