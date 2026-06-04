<?php
/**
 * JOBMINGTON - Course module (lesson) viewer
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

Session::start();
$pdo = db();
$moduleId = (int) get('id', 0);
if ($moduleId <= 0) redirect('/jobmington/learn/');

$stmt = $pdo->prepare("SELECT m.*, c.course_id, c.title AS course_title, c.is_free, c.has_certificate
    FROM course_modules m JOIN courses c ON m.course_id = c.course_id
    WHERE m.module_id = ? AND c.is_published = 1");
$stmt->execute([$moduleId]);
$module = $stmt->fetch();
if (!$module) redirect('/jobmington/learn/');

$courseId = (int) $module['course_id'];
$userId = Session::userId();

$enrollment = null;
if ($userId) {
    $s = $pdo->prepare("SELECT * FROM course_enrollments WHERE user_id = ? AND course_id = ?");
    $s->execute([$userId, $courseId]);
    $enrollment = $s->fetch();
}
$isPreview = (bool) $module['is_free_preview'];
if (!$enrollment && !$isPreview) {
    Session::flash('info', 'Enroll to access this module.');
    redirect('/jobmington/learn/course.php?id=' . $courseId);
}

$all = $pdo->prepare("SELECT * FROM course_modules WHERE course_id = ? ORDER BY sort_order ASC, module_id ASC");
$all->execute([$courseId]);
$all = $all->fetchAll();
$idx = 0;
foreach ($all as $i => $m) { if ((int)$m['module_id'] === $moduleId) { $idx = $i; break; } }
$prev = $idx > 0 ? $all[$idx - 1] : null;
$next = $idx < count($all) - 1 ? $all[$idx + 1] : null;

$quiz = $pdo->prepare("SELECT * FROM quizzes WHERE course_id = ? LIMIT 1");
$quiz->execute([$courseId]);
$quiz = $quiz->fetch();

if (isPost() && isset($_POST['complete']) && $enrollment && Security::verifyCSRF()) {
    $pdo->prepare("INSERT INTO module_progress (user_id, module_id, is_completed, completed_at) VALUES (?,?,1,NOW())
                   ON DUPLICATE KEY UPDATE is_completed = 1, completed_at = NOW()")->execute([$userId, $moduleId]);

    $ph = implode(',', array_fill(0, count($all), '?'));
    $cs = $pdo->prepare("SELECT COUNT(*) FROM module_progress WHERE user_id = ? AND is_completed = 1 AND module_id IN ($ph)");
    $cs->execute(array_merge([$userId], array_column($all, 'module_id')));
    $done = (int) $cs->fetchColumn();
    $progress = count($all) ? (int) round($done / count($all) * 100) : 100;
    $pdo->prepare("UPDATE course_enrollments SET progress = ?, last_accessed = NOW() WHERE enrollment_id = ?")->execute([$progress, $enrollment['enrollment_id']]);

    redirect($next ? '/jobmington/learn/module.php?id=' . (int)$next['module_id'] : '/jobmington/learn/course.php?id=' . $courseId);
}

function jm_embed_url(string $url): string {
    if (preg_match('~(?:youtube\.com/watch\?v=|youtu\.be/)([\w-]+)~', $url, $m)) return 'https://www.youtube.com/embed/' . $m[1];
    if (preg_match('~vimeo\.com/(\d+)~', $url, $m)) return 'https://player.vimeo.com/video/' . $m[1];
    return $url;
}

$pageTitle = $module['title'] . ' - ' . SITE_NAME;
$activeAIPage = 'learn';
require_once __DIR__ . '/../includes/ai-header.php';
?>
<style>
.jm-md { max-width:820px; margin:0 auto; padding:28px 20px 72px; }
.jm-md-back { font-size:13px; color:#53667f; text-decoration:none; font-weight:600; }
.jm-md-back:hover { color:#0640a3; }
.jm-md-bar { height:6px; border-radius:99px; background:#eef2f7; overflow:hidden; margin:14px 0 6px; }
.jm-md-bar span { display:block; height:100%; background:#0f766e; }
.jm-md-bar-label { font-size:12px; color:#94a3b8; font-weight:600; margin-bottom:22px; }
.jm-md h1 { font-size:clamp(24px,3.5vw,32px); font-weight:800; color:#061426; line-height:1.2; margin:6px 0 18px; }
.jm-md-video { aspect-ratio:16/9; border-radius:14px; overflow:hidden; margin-bottom:24px; background:#000; }
.jm-md-video iframe { width:100%; height:100%; border:0; }
.jm-md-body { font-size:16px; line-height:1.8; color:#1f2d3d; white-space:pre-wrap; }
.jm-md-nav { display:flex; align-items:center; justify-content:space-between; gap:12px; margin-top:32px; padding-top:22px; border-top:1px solid #e4eaf3; }
.jm-md-btn { display:inline-flex; align-items:center; gap:8px; border-radius:10px; padding:12px 20px; font-weight:800; font-size:14px; text-decoration:none; cursor:pointer; border:0; }
.jm-md-btn.primary { background:#0640a3; color:#fff; } .jm-md-btn.primary:hover { background:#052f78; }
.jm-md-btn.ghost { background:#fff; color:#0640a3; border:1px solid #d8e4f4; }
</style>

<div class="jm-md">
    <a class="jm-md-back" href="/jobmington/learn/course.php?id=<?= $courseId ?>">&larr; <?= e($module['course_title']) ?></a>
    <?php $progPct = count($all) ? round(($idx+1)/count($all)*100) : 100; ?>
    <div class="jm-md-bar"><span style="width:<?= $progPct ?>%"></span></div>
    <div class="jm-md-bar-label">Module <?= $idx+1 ?> of <?= count($all) ?></div>

    <h1><?= e($module['title']) ?></h1>

    <?php if (!empty($module['video_url'])): ?>
        <div class="jm-md-video"><iframe src="<?= e(jm_embed_url($module['video_url'])) ?>" allowfullscreen loading="lazy"></iframe></div>
    <?php endif; ?>

    <div class="jm-md-body"><?= e($module['content'] ?: $module['description'] ?: 'Lesson content coming soon.') ?></div>

    <div class="jm-md-nav">
        <div>
            <?php if ($prev): ?><a class="jm-md-btn ghost" href="/jobmington/learn/module.php?id=<?= (int)$prev['module_id'] ?>">&larr; Previous</a><?php endif; ?>
        </div>
        <div style="display:flex;gap:10px;align-items:center;">
            <?php if ($enrollment): ?>
                <form method="post" style="margin:0;">
                    <?= Security::csrfField() ?>
                    <button class="jm-md-btn primary" type="submit" name="complete" value="1">
                        <?= $next ? 'Complete &amp; next &rarr;' : ($quiz ? 'Complete &rarr; quiz' : 'Complete course') ?>
                    </button>
                </form>
            <?php elseif ($isPreview): ?>
                <a class="jm-md-btn primary" href="/jobmington/learn/course.php?id=<?= $courseId ?>">Enroll to continue</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/ai-footer.php'; ?>
