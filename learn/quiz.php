<?php
/**
 * JOBMINGTON - Course quiz (assessment + certificate)
 */
require_once __DIR__ . '/_disabled.php';

define('JOBMINGTON', true);
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/learn_nav.php';
require_once __DIR__ . '/../includes/seeds.php';
require_once __DIR__ . '/../includes/certificates.php';

Session::start();
Session::requireLogin();

$pdo = db();
$quizId = (int) get('id', 0);
$userId = (int) Session::userId();
if ($quizId <= 0) redirect('/jobmington/learn/');

$stmt = $pdo->prepare("SELECT q.*, c.course_id, c.title AS course_title, c.has_certificate, c.is_free FROM quizzes q JOIN courses c ON q.course_id = c.course_id WHERE q.quiz_id = ? AND c.is_published = 1");
$stmt->execute([$quizId]);
$quiz = $stmt->fetch();
if (!$quiz) redirect('/jobmington/learn/');

$stmt = $pdo->prepare("SELECT * FROM course_enrollments WHERE user_id = ? AND course_id = ?");
$stmt->execute([$userId, $quiz['course_id']]);
$enrollment = $stmt->fetch();
if (!$enrollment) {
    Session::flash('info', 'Enroll to take this quiz.');
    redirect('/jobmington/learn/course.php?id=' . $quiz['course_id']);
}

$questions = $pdo->prepare("SELECT * FROM quiz_questions WHERE quiz_id = ? ORDER BY question_id");
$questions->execute([$quizId]);
$questions = $questions->fetchAll();

$showResults = false; $score = 0; $passed = false; $userAnswers = []; $certCode = null; $certPayable = false;

if (isPost() && Security::verifyCSRF() && $questions) {
    $showResults = true;
    $correct = 0;
    foreach ($questions as $q) {
        $ans = strtolower(trim(post('q_' . $q['question_id'], '')));
        $userAnswers[$q['question_id']] = $ans;
        if ($ans !== '' && $ans === strtolower(trim((string)$q['correct_option']))) $correct++;
    }
    $score = count($questions) ? (int) round($correct / count($questions) * 100) : 0;
    $passed = $score >= (int) $quiz['passing_score'];

    if ($passed) {
        // Mark completion + award seeds (once).
        if (empty($enrollment['completed_at'])) {
            try { awardSeeds($userId, 'course_complete', (int)$quiz['course_id']); } catch (Throwable $e) {}
        }
        $pdo->prepare("UPDATE course_enrollments SET progress = 100, completed_at = NOW() WHERE enrollment_id = ?")->execute([$enrollment['enrollment_id']]);

        $s = $pdo->prepare("SELECT cert_code FROM certificates WHERE user_id = ? AND course_id = ? LIMIT 1");
        $s->execute([$userId, $quiz['course_id']]);
        $certCode = $s->fetchColumn();
        if (!$certCode) {
            if (jm_cert_included($quiz)) {
                // Paid course -> certificate included; issue free.
                $certCode = jm_issue_certificate($pdo, $userId, (int)$quiz['course_id'], false);
            } else {
                // Free course -> certificate must be claimed (paid).
                $certPayable = true;
            }
        }
    }
}

$pageTitle = ($quiz['title'] ?: 'Quiz') . ' - ' . SITE_NAME;
$activeAIPage = 'learn';
require_once __DIR__ . '/../includes/ai-header.php';
?>
<style>
.jm-qz { max-width:720px; margin:0 auto; padding:32px 20px 72px; }
.jm-qz h1 { font-size:clamp(24px,3.5vw,32px); font-weight:800; color:#061426; margin:6px 0 6px; }
.jm-qz-sub { font-size:14px; color:#53667f; margin-bottom:26px; }
.jm-qz-q { background:#fff; border:1px solid #e4eaf3; border-radius:12px; padding:20px; margin-bottom:14px; }
.jm-qz-q-title { font-size:15px; font-weight:700; color:#061426; margin-bottom:14px; }
.jm-qz-opt { display:flex; align-items:center; gap:10px; padding:11px 14px; border:1px solid #e4eaf3; border-radius:9px; margin-bottom:8px; cursor:pointer; font-size:14px; color:#1f2d3d; transition:border-color .12s, background .12s; }
.jm-qz-opt:hover { border-color:#c8d8ef; background:#fbfdff; }
.jm-qz-opt input { accent-color:#0640a3; }
.jm-qz-opt.correct { border-color:#0f766e; background:#e6f5f1; }
.jm-qz-opt.wrong { border-color:#dc2626; background:#fdecea; }
.jm-qz-btn { display:inline-block; background:#0640a3; color:#fff; border:0; border-radius:10px; padding:14px 28px; font:inherit; font-weight:800; font-size:14px; cursor:pointer; margin-top:8px; text-decoration:none; }
.jm-qz-btn:hover { background:#052f78; }
.jm-qz-result { text-align:center; background:#fff; border:1px solid #e4eaf3; border-radius:16px; padding:40px 24px; }
.jm-qz-score { font-size:56px; font-weight:800; letter-spacing:-.03em; line-height:1; }
.jm-qz-score.pass { color:#0f766e; } .jm-qz-score.fail { color:#dc2626; }
.jm-qz-result h2 { font-size:22px; font-weight:800; color:#061426; margin:14px 0 8px; }
.jm-qz-result p { font-size:15px; color:#53667f; margin:0 0 22px; }
.jm-qz-cta { display:flex; gap:10px; justify-content:center; flex-wrap:wrap; }
.jm-qz-empty { text-align:center; color:#94a3b8; padding:50px 20px; background:#fff; border:1px solid #e4eaf3; border-radius:14px; }
</style>

<div class="jm-qz">
    <?= jm_breadcrumbs([['label' => 'Learn', 'url' => '/jobmington/learn/'], ['label' => $quiz['course_title'], 'url' => '/jobmington/learn/course.php?id=' . (int)$quiz['course_id']], ['label' => 'Quiz']]) ?>

    <?php if ($showResults): ?>
        <div class="jm-qz-result">
            <div class="jm-qz-score <?= $passed ? 'pass' : 'fail' ?>"><?= $score ?>%</div>
            <h2><?= $passed ? 'Congratulations — you passed!' : 'Not quite there' ?></h2>
            <p>
                <?php if ($passed && $certPayable): ?>You scored <?= $score ?>% (pass mark <?= (int)$quiz['passing_score'] ?>%). Claim your certificate to finish.
                <?php elseif ($passed): ?>You scored <?= $score ?>% (pass mark <?= (int)$quiz['passing_score'] ?>%). Your certificate is ready.
                <?php else: ?>You scored <?= $score ?>%. You need <?= (int)$quiz['passing_score'] ?>% to pass — review the modules and try again.<?php endif; ?>
            </p>
            <div class="jm-qz-cta">
                <?php if ($passed && $certCode): ?>
                    <a class="jm-qz-btn" href="/jobmington/certificates/view.php?code=<?= e($certCode) ?>">View certificate</a>
                <?php elseif ($passed && $certPayable): $cp = jm_cert_price($quiz); ?>
                    <a class="jm-qz-btn" href="/jobmington/certificates/claim.php?course=<?= (int)$quiz['course_id'] ?>">Claim certificate &middot; <?= number_format($cp['seeds']) ?> Seeds</a>
                <?php else: ?>
                    <a class="jm-qz-btn" href="/jobmington/learn/quiz.php?id=<?= $quizId ?>">Try again</a>
                <?php endif; ?>
                <a class="jm-qz-btn" style="background:#fff;color:#0640a3;border:1px solid #d8e4f4;" href="/jobmington/learn/course.php?id=<?= (int)$quiz['course_id'] ?>">Back to course</a>
            </div>
        </div>
        <?php if ($passed): ?>
        <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>
        <script>window.addEventListener('load',function(){if(window.confetti)confetti({particleCount:120,spread:75,origin:{y:.6},colors:['#0640a3','#f59f22','#0f766e']});});</script>
        <?php endif; ?>
    <?php elseif (empty($questions)): ?>
        <h1><?= e($quiz['title'] ?: 'Quiz') ?></h1>
        <div class="jm-qz-empty">This quiz has no questions yet.</div>
    <?php else: ?>
        <h1><?= e($quiz['title'] ?: 'Final quiz') ?></h1>
        <p class="jm-qz-sub"><?= count($questions) ?> questions &middot; pass mark <?= (int)$quiz['passing_score'] ?>%</p>
        <form method="post">
            <?= Security::csrfField() ?>
            <?php foreach ($questions as $i => $q): ?>
                <div class="jm-qz-q">
                    <div class="jm-qz-q-title"><?= $i+1 ?>. <?= e($q['question']) ?></div>
                    <?php foreach (['a','b','c','d'] as $opt): if (empty($q['option_' . $opt])) continue; ?>
                        <label class="jm-qz-opt">
                            <input type="radio" name="q_<?= (int)$q['question_id'] ?>" value="<?= $opt ?>" required>
                            <?= e($q['option_' . $opt]) ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
            <button class="jm-qz-btn" type="submit">Submit answers</button>
        </form>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/ai-footer.php'; ?>
