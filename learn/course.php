<?php
/**
 * JOBMINGTON - Course detail (enroll, curriculum, progress, certificate)
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

Session::start();
$pdo = db();
$courseId = (int) get('id', 0);
if ($courseId <= 0) redirect('/jobmington/learn/');

$stmt = $pdo->prepare("SELECT c.*, cc.name AS category_name FROM courses c LEFT JOIN course_categories cc ON c.category_id = cc.id WHERE c.course_id = ? AND c.is_published = 1");
$stmt->execute([$courseId]);
$course = $stmt->fetch();
if (!$course) redirect('/jobmington/learn/');

$modules = $pdo->prepare("SELECT * FROM course_modules WHERE course_id = ? ORDER BY sort_order ASC, module_id ASC");
$modules->execute([$courseId]);
$modules = $modules->fetchAll();

$quiz = $pdo->prepare("SELECT * FROM quizzes WHERE course_id = ? LIMIT 1");
$quiz->execute([$courseId]);
$quiz = $quiz->fetch();

$userId = Session::userId();
$isEnrolled = false; $enrollment = null; $hasPurchased = false;
$completedIds = []; $certificate = null;

if ($userId) {
    $s = $pdo->prepare("SELECT * FROM course_enrollments WHERE user_id = ? AND course_id = ?");
    $s->execute([$userId, $courseId]);
    $enrollment = $s->fetch();
    $isEnrolled = (bool) $enrollment;

    if (!$course['is_free']) {
        $s = $pdo->prepare("SELECT 1 FROM course_purchases WHERE user_id = ? AND course_id = ? LIMIT 1");
        $s->execute([$userId, $courseId]);
        $hasPurchased = (bool) $s->fetchColumn();
    }
    if ($modules) {
        $ph = implode(',', array_fill(0, count($modules), '?'));
        $s = $pdo->prepare("SELECT module_id FROM module_progress WHERE user_id = ? AND is_completed = 1 AND module_id IN ($ph)");
        $s->execute(array_merge([$userId], array_column($modules, 'module_id')));
        $completedIds = $s->fetchAll(PDO::FETCH_COLUMN);
    }
    $s = $pdo->prepare("SELECT * FROM certificates WHERE user_id = ? AND course_id = ? LIMIT 1");
    $s->execute([$userId, $courseId]);
    $certificate = $s->fetch();
}

$hasAccess  = $course['is_free'] || $hasPurchased;
$totalMods  = count($modules);
$doneMods   = count($completedIds);
$allDone    = $totalMods > 0 && $doneMods >= $totalMods;

// Next module to continue with.
$nextModule = null;
foreach ($modules as $m) {
    if (!in_array($m['module_id'], $completedIds)) { $nextModule = $m; break; }
}
if (!$nextModule && $modules) $nextModule = $modules[0];

// Enroll handler.
if (isPost() && isset($_POST['enroll'])) {
    if (!$userId) {
        redirect('/jobmington/auth/login.php?redirect=' . urlencode('/jobmington/learn/course.php?id=' . $courseId));
    }
    if (!$course['is_free'] && !$hasPurchased) {
        redirect('/jobmington/learn/checkout.php?course=' . $courseId);
    }
    if (Security::verifyCSRF()) {
        if (!$isEnrolled) {
            $pdo->prepare("INSERT IGNORE INTO course_enrollments (user_id, course_id) VALUES (?, ?)")->execute([$userId, $courseId]);
            $pdo->prepare("UPDATE courses SET enrollment_count = enrollment_count + 1 WHERE course_id = ?")->execute([$courseId]);
        }
        if ($course['is_external'] && $course['external_url']) {
            redirect($course['external_url']);
        }
        Session::flash('success', 'You are enrolled. Start learning!');
        redirect('/jobmington/learn/course.php?id=' . $courseId);
    }
}

// Issue certificate for module-only courses (no quiz) once everything is done.
if ($isEnrolled && $hasAccess && $course['has_certificate'] && !$quiz && $allDone && !$certificate && $userId) {
    $code = 'JMT-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(4)));
    try {
        $pdo->prepare("INSERT INTO certificates (cert_code, user_id, course_id) VALUES (?,?,?)")->execute([$code, $userId, $courseId]);
        $pdo->prepare("UPDATE course_enrollments SET progress = 100, completed_at = NOW() WHERE enrollment_id = ?")->execute([$enrollment['enrollment_id']]);
        try { awardSeeds($userId, 'course_complete', $courseId); } catch (Throwable $e) {}
        redirect('/jobmington/certificates/view.php?code=' . $code);
    } catch (Throwable $e) {}
}

$pageTitle = $course['title'] . ' - ' . SITE_NAME;
$activeAIPage = 'learn';
require_once __DIR__ . '/../includes/ai-header.php';
?>
<style>
.jm-cd { max-width:1040px; margin:0 auto; padding:32px 20px 72px; }
.jm-cd-grid { display:grid; grid-template-columns:1fr 330px; gap:30px; align-items:start; }
@media(max-width:820px){ .jm-cd-grid{grid-template-columns:1fr;} }
.jm-cd-hero { border-radius:16px; overflow:hidden; margin-bottom:22px; aspect-ratio:16/7; background:linear-gradient(135deg,#0640a3,#04122f); }
.jm-cd-hero img { width:100%; height:100%; object-fit:cover; }
.jm-cd-cat { font-size:11px; font-weight:800; text-transform:uppercase; letter-spacing:.08em; color:#0640a3; }
.jm-cd h1 { font-size:clamp(26px,4vw,38px); font-weight:800; letter-spacing:-.02em; color:#061426; line-height:1.15; margin:8px 0 12px; }
.jm-cd-meta { display:flex; flex-wrap:wrap; gap:8px; margin-bottom:24px; }
.jm-cd-chip { display:inline-flex; align-items:center; gap:6px; font-size:12.5px; font-weight:600; color:#0b1b33; background:#f0f4f9; border-radius:99px; padding:6px 12px; }
.jm-cd-sec { margin-bottom:28px; }
.jm-cd-sec h2 { font-size:18px; font-weight:800; color:#061426; margin:0 0 12px; }
.jm-cd-desc { font-size:15px; line-height:1.75; color:#1f2d3d; white-space:pre-wrap; }
.jm-cd-mod { display:flex; align-items:center; gap:14px; padding:14px 16px; border:1px solid #e4eaf3; border-radius:10px; margin-bottom:8px; background:#fff; }
.jm-cd-mod-n { width:30px; height:30px; border-radius:50%; background:#eef5ff; color:#0640a3; font-weight:800; font-size:13px; display:grid; place-items:center; flex-shrink:0; }
.jm-cd-mod.done .jm-cd-mod-n { background:#0f766e; color:#fff; }
.jm-cd-mod-main { flex:1; min-width:0; }
.jm-cd-mod-title { font-size:14.5px; font-weight:700; color:#061426; }
.jm-cd-mod-sub { font-size:12px; color:#94a3b8; margin-top:2px; }
.jm-cd-mod-tag { font-size:10px; font-weight:800; text-transform:uppercase; color:#0a6454; background:#e6f5f1; padding:2px 7px; border-radius:5px; }
.jm-cd-side { position:sticky; top:20px; border:1px solid #e4eaf3; border-radius:16px; background:#fff; padding:22px; box-shadow:0 4px 14px rgba(6,20,38,.05); }
.jm-cd-price { font-size:28px; font-weight:800; color:#061426; letter-spacing:-.03em; }
.jm-cd-price small { font-size:13px; color:#94a3b8; font-weight:600; }
.jm-cd-btn { display:block; width:100%; box-sizing:border-box; text-align:center; background:#0640a3; color:#fff; border:0; border-radius:10px; padding:14px; font:inherit; font-weight:800; font-size:14px; cursor:pointer; text-decoration:none; margin-top:14px; }
.jm-cd-btn:hover { background:#052f78; }
.jm-cd-btn.green { background:#0f766e; } .jm-cd-btn.green:hover { background:#0a5d56; }
.jm-cd-progress { margin:14px 0; }
.jm-cd-bar { height:8px; border-radius:99px; background:#eef2f7; overflow:hidden; }
.jm-cd-bar span { display:block; height:100%; background:#0f766e; border-radius:99px; }
.jm-cd-progress-label { font-size:12px; color:#53667f; margin-top:6px; font-weight:600; }
.jm-cd-incl { list-style:none; padding:0; margin:16px 0 0; }
.jm-cd-incl li { display:flex; gap:9px; align-items:flex-start; font-size:13px; color:#1f2d3d; padding:5px 0; }
.jm-cd-incl svg { color:#0f766e; flex-shrink:0; margin-top:2px; }
.jm-cd-flash { background:#e6f5f1; color:#0a6454; border-radius:8px; padding:11px 14px; font-size:13px; font-weight:600; margin-bottom:18px; }
.jm-cd-cert { background:#fffbf0; border:1px solid #f3d8a8; border-radius:10px; padding:14px; margin-top:14px; text-align:center; }
.jm-cd-cert a { color:#8a5a12; font-weight:800; text-decoration:none; }
</style>

<div class="jm-cd">
    <?= jm_breadcrumbs([['label' => 'Learn', 'url' => '/jobmington/learn/'], ['label' => $course['title']]]) ?>

    <?php foreach (Session::getFlash('success') as $f): ?><div class="jm-cd-flash"><?= e($f) ?></div><?php endforeach; ?>

    <div class="jm-cd-grid">
        <div>
            <div class="jm-cd-hero">
                <?php if (!empty($course['thumbnail'])): ?><img src="<?= e($course['thumbnail']) ?>" alt="<?= e($course['title']) ?>"><?php endif; ?>
            </div>
            <?php if ($course['category_name']): ?><span class="jm-cd-cat"><?= e($course['category_name']) ?></span><?php endif; ?>
            <h1><?= e($course['title']) ?></h1>
            <div class="jm-cd-meta">
                <span class="jm-cd-chip"><?= e(ucfirst($course['difficulty'])) ?></span>
                <?php if ((float)$course['duration_hours']>0): ?><span class="jm-cd-chip"><?= rtrim(rtrim(number_format((float)$course['duration_hours'],1),'0'),'.') ?> hours</span><?php endif; ?>
                <?php if ($totalMods): ?><span class="jm-cd-chip"><?= $totalMods ?> modules</span><?php endif; ?>
                <span class="jm-cd-chip"><?= number_format((int)$course['enrollment_count']) ?> enrolled</span>
                <?php if ($course['instructor_name']): ?><span class="jm-cd-chip">By <?= e($course['instructor_name']) ?></span><?php endif; ?>
            </div>

            <div class="jm-cd-sec">
                <h2>About this course</h2>
                <div class="jm-cd-desc"><?= e($course['description'] ?: $course['short_description'] ?: 'Course details coming soon.') ?></div>
            </div>

            <?php if ($totalMods): ?>
            <div class="jm-cd-sec">
                <h2>Curriculum</h2>
                <?php foreach ($modules as $i => $m): $done = in_array($m['module_id'], $completedIds); ?>
                    <?php $modOpen = $isEnrolled && $hasAccess; $modHtml = '<div class="jm-cd-mod ' . ($done?'done':'') . '">'
                        . '<span class="jm-cd-mod-n">' . ($done ? '&#10003;' : ($i+1)) . '</span>'
                        . '<div class="jm-cd-mod-main"><div class="jm-cd-mod-title">' . e($m['title']) . '</div>'
                        . ($m['duration_minutes'] ? '<div class="jm-cd-mod-sub">' . (int)$m['duration_minutes'] . ' min</div>' : '')
                        . '</div>' . ($m['is_free_preview'] ? '<span class="jm-cd-mod-tag">Preview</span>' : '') . '</div>'; ?>
                    <?php if ($modOpen): ?>
                        <a href="/jobmington/learn/module.php?id=<?= (int)$m['module_id'] ?>" style="text-decoration:none;"><?= $modHtml ?></a>
                    <?php elseif ($m['is_free_preview']): ?>
                        <a href="/jobmington/learn/module.php?id=<?= (int)$m['module_id'] ?>" style="text-decoration:none;"><?= $modHtml ?></a>
                    <?php else: ?>
                        <?= $modHtml ?>
                    <?php endif; ?>
                <?php endforeach; ?>
                <?php if ($quiz): ?>
                    <div class="jm-cd-mod"><span class="jm-cd-mod-n">&#9733;</span><div class="jm-cd-mod-main"><div class="jm-cd-mod-title">Final quiz: <?= e($quiz['title'] ?: 'Assessment') ?></div><div class="jm-cd-mod-sub">Pass <?= (int)$quiz['passing_score'] ?>% to earn your certificate</div></div></div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

        <aside class="jm-cd-side">
            <div class="jm-cd-price">
                <?= $course['is_free'] ? 'Free' : '₦' . number_format((float)$course['price']) ?>
                <?php if (!$course['is_free'] && (int)$course['seed_price']>0): ?><small>or <?= number_format((int)$course['seed_price']) ?> seeds</small><?php endif; ?>
            </div>

            <?php if ($certificate): ?>
                <div class="jm-cd-progress"><div class="jm-cd-bar"><span style="width:100%"></span></div><div class="jm-cd-progress-label">Completed</div></div>
                <a class="jm-cd-btn green" href="/jobmington/certificates/view.php?code=<?= e($certificate['cert_code']) ?>">View certificate</a>
            <?php elseif ($isEnrolled && $hasAccess): ?>
                <div class="jm-cd-progress"><div class="jm-cd-bar"><span style="width:<?= $totalMods ? round($doneMods/$totalMods*100) : 0 ?>%"></span></div><div class="jm-cd-progress-label"><?= $doneMods ?>/<?= max(1,$totalMods) ?> modules done</div></div>
                <?php if ($course['is_external'] && $course['external_url']): ?>
                    <a class="jm-cd-btn" href="<?= e($course['external_url']) ?>" target="_blank" rel="noopener">Go to course &rarr;</a>
                <?php elseif ($nextModule): ?>
                    <a class="jm-cd-btn" href="/jobmington/learn/module.php?id=<?= (int)$nextModule['module_id'] ?>"><?= $doneMods ? 'Continue learning' : 'Start learning' ?></a>
                <?php endif; ?>
                <?php if ($allDone && $quiz): ?><a class="jm-cd-btn green" href="/jobmington/learn/quiz.php?id=<?= (int)$quiz['quiz_id'] ?>">Take the quiz</a><?php endif; ?>
            <?php else: ?>
                <form method="post">
                    <?= Security::csrfField() ?>
                    <button class="jm-cd-btn" type="submit" name="enroll" value="1">
                        <?php if (!$course['is_free'] && !$hasPurchased): ?>Buy this course<?php elseif ($course['is_external']): ?>Enroll &amp; start<?php else: ?>Enroll free<?php endif; ?>
                    </button>
                </form>
                <?php if (!$userId): ?><p style="font-size:12px;color:#94a3b8;text-align:center;margin-top:8px;">You'll sign in first.</p><?php endif; ?>
            <?php endif; ?>

            <ul class="jm-cd-incl">
                <?php
                $svgc = '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>';
                if ($totalMods) echo "<li>$svgc " . $totalMods . " on-demand modules</li>";
                if ($course['has_certificate']) echo "<li>$svgc Certificate of completion</li>";
                echo "<li>$svgc Lifetime access</li>";
                echo "<li>$svgc Learn at your own pace</li>";
                ?>
            </ul>
            <?php if ($course['has_certificate'] && !$certificate): ?>
                <div class="jm-cd-cert">Finish the course to earn your certificate.</div>
            <?php endif; ?>
        </aside>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/ai-footer.php'; ?>
