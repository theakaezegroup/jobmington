<?php
require_once __DIR__ . '/_disabled.php';

/**
 * JOBMINGTON - Course Module
 * Aesthetic: Deep Space Cockpit
 * Features: Cinematic Player, Holographic Sidebar, Focus Mode
 */

define('JOBMINGTON', true);
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';

Session::start();
Session::requireLogin();

$pdo = db();
$moduleId = (int) get('id', 0);
$userId = Session::userId();

if ($moduleId <= 0) redirect('/jobmington/learn');

// Fetch Module & Course
$stmt = $pdo->prepare("SELECT m.*, c.course_id, c.title as course_title, c.slug as course_slug FROM course_modules m JOIN courses c ON m.course_id = c.course_id WHERE m.module_id = ? AND c.is_published = 1");
$stmt->execute([$moduleId]);
$module = $stmt->fetch();

if (!$module) { Session::flash('error', 'Data fragment missing.'); redirect('/jobmington/learn'); }

// Check Enrollment
$stmt = $pdo->prepare("SELECT * FROM course_enrollments WHERE user_id = ? AND course_id = ?");
$stmt->execute([$userId, $module['course_id']]);
$enrollment = $stmt->fetch();

if (!$enrollment) { Session::flash('info', 'Access Denied. Enrollment Required.'); redirect('/jobmington/learn/course.php?id=' . $module['course_id']); }

// Get Syllabus
$stmt = $pdo->prepare("SELECT * FROM course_modules WHERE course_id = ? ORDER BY order_index ASC");
$stmt->execute([$module['course_id']]);
$allModules = $stmt->fetchAll();

// Navigation Logic
$currentIndex = null;
foreach ($allModules as $index => $m) {
    if ($m['module_id'] == $moduleId) { $currentIndex = $index; break; }
}
$prevModule = $currentIndex > 0 ? $allModules[$currentIndex - 1] : null;
$nextModule = $currentIndex < count($allModules) - 1 ? $allModules[$currentIndex + 1] : null;

// Check for Quiz
$stmt = $pdo->prepare("SELECT * FROM quizzes WHERE course_id = ? LIMIT 1");
$stmt->execute([$module['course_id']]);
$quiz = $stmt->fetch();

// Update Progress
$progress = min(100, round((($currentIndex + 1) / count($allModules)) * 100));
$pdo->prepare("UPDATE course_enrollments SET progress = ? WHERE enrollment_id = ?")->execute([$progress, $enrollment['enrollment_id']]);

// Video Parser
$videoId = '';
if (preg_match('/youtube\.com\/watch\?v=([^\&\?\/]+)/', $module['video_url'], $matches)) $videoId = $matches[1];
elseif (preg_match('/youtu\.be\/([^\&\?\/]+)/', $module['video_url'], $matches)) $videoId = $matches[1];
elseif (preg_match('/youtube\.com\/embed\/([^\&\?\/]+)/', $module['video_url'], $matches)) $videoId = $matches[1];

$pageTitle = e($module['title']) . ' | Jobmington Learn';
require_once __DIR__ . '/../includes/header.php';
?>

<style>
    /* --- COCKPIT THEME --- */
    body {
        background-color: #050505;
        color: #e2e8f0;
        background-image: radial-gradient(circle at 50% -20%, #1e1b4b 0%, #000000 70%);
        min-height: 100vh;
    }

    /* --- CINEMATIC PLAYER --- */
    .viewport-container {
        position: relative;
        border-radius: 1rem;
        overflow: hidden;
        box-shadow: 
            0 0 50px -10px rgba(59, 130, 246, 0.2), /* Blue Ambient Glow */
            0 20px 40px -5px rgba(0,0,0,0.8);
        border: 1px solid rgba(255,255,255,0.1);
        background: black;
    }
    
    .viewport-frame {
        position: relative; padding-bottom: 56.25%; height: 0;
    }
    .viewport-frame iframe, .viewport-frame video {
        position: absolute; top: 0; left: 0; width: 100%; height: 100%;
    }

    /* --- SIDEBAR HUD --- */
    .hud-panel {
        background: rgba(255,255,255,0.03);
        border: 1px solid rgba(255,255,255,0.05);
        border-radius: 1rem;
        max-height: calc(100vh - 100px);
        overflow-y: auto;
    }

    .module-item {
        display: flex; items-center; gap: 1rem;
        padding: 1rem;
        border-bottom: 1px solid rgba(255,255,255,0.03);
        transition: 0.2s;
        cursor: pointer;
    }
    .module-item:hover { background: rgba(255,255,255,0.05); }
    
    /* Active Lesson */
    .module-item.active {
        background: rgba(59, 130, 246, 0.1);
        border-left: 3px solid #3b82f6;
    }
    .module-item.active .module-title { color: #fff; font-weight: bold; }
    .module-item.active .status-indicator { color: #3b82f6; animation: pulse 2s infinite; }

    /* Completed Lesson */
    .module-item.completed .status-indicator { color: #10b981; }

    /* --- CONTROLS --- */
    .control-btn {
        display: inline-flex; align-items: center; gap: 0.5rem;
        padding: 0.75rem 1.5rem;
        border-radius: 0.75rem;
        font-weight: 700; font-size: 0.85rem; letter-spacing: 0.05em; text-transform: uppercase;
        transition: 0.3s;
    }
    .btn-prev { background: rgba(255,255,255,0.05); color: #94a3b8; }
    .btn-prev:hover { background: rgba(255,255,255,0.1); color: white; }
    
    .btn-next {
        background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        color: white;
        box-shadow: 0 0 20px rgba(59, 130, 246, 0.3);
    }
    .btn-next:hover { box-shadow: 0 0 30px rgba(59, 130, 246, 0.5); transform: translateY(-2px); }

    /* Scrollbar */
    ::-webkit-scrollbar { width: 6px; }
    ::-webkit-scrollbar-track { background: #0f172a; }
    ::-webkit-scrollbar-thumb { background: #334155; border-radius: 3px; }

</style>

<div class="border-b border-white/5 bg-black/20 backdrop-blur-md sticky top-0 z-50">
    <div class="max-w-[1600px] mx-auto px-6 py-4 flex items-center justify-between">
        <div class="flex items-center gap-4">
            <a href="/jobmington/learn/course.php?id=<?= $module['course_id'] ?>" class="w-8 h-8 flex items-center justify-center rounded-full bg-white/5 hover:bg-white/10 transition text-slate-400">
                <i class="fas fa-chevron-left"></i>
            </a>
            <div>
                <div class="text-[10px] uppercase tracking-widest text-slate-500 font-bold"><?= e($module['course_title']) ?></div>
                <h1 class="text-white font-bold text-sm"><?= e($module['title']) ?></h1>
            </div>
        </div>
        <div class="flex items-center gap-3">
            <div class="text-right hidden sm:block">
                <div class="text-[10px] text-slate-500 uppercase font-bold">Upload Status</div>
                    <div class="text-xs font-mono text-blue-400"><?= $progress ?>% COMPLETE</div>
                </div>
            <div class="w-12 h-12 relative flex items-center justify-center">
                <svg class="w-full h-full transform -rotate-90">
                    <circle cx="24" cy="24" r="18" stroke="rgba(255,255,255,0.1)" stroke-width="3" fill="none"></circle>
                    <circle cx="24" cy="24" r="18" stroke="#3b82f6" stroke-width="3" fill="none" stroke-dasharray="113" stroke-dashoffset="<?= 113 - (113 * $progress / 100) ?>"></circle>
                </svg>
            </div>
        </div>
    </div>
</div>

<div class="max-w-[1600px] mx-auto px-4 md:px-6 py-8">
    <div class="grid lg:grid-cols-12 gap-8">
        
        <div class="lg:col-span-8 xl:col-span-9">
            
            <?php if ($module['video_url']): ?>
            <div class="viewport-container mb-8">
                <div class="viewport-frame">
                    <?php if ($videoId): ?>
                        <iframe src="https://www.youtube.com/embed/<?= e($videoId) ?>?rel=0&modestbranding=1" allowfullscreen></iframe>
                    <?php else: ?>
                        <video controls><source src="<?= e($module['video_url']) ?>" type="video/mp4"></video>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <div class="flex flex-col md:flex-row justify-between gap-8">
                <div class="flex-1">
                    <h2 class="text-2xl font-bold text-white mb-4">Lesson Directive</h2>
                    <div class="prose prose-invert prose-sm text-slate-400 leading-relaxed max-w-none">
                        <?= nl2br(e($module['content'])) ?>
                    </div>
                </div>

                <div class="flex gap-4 self-start flex-shrink-0">
                    <?php if ($prevModule): ?>
                    <a href="?id=<?= $prevModule['module_id'] ?>" class="control-btn btn-prev">
                        <i class="fas fa-arrow-left"></i> Prev
                    </a>
                    <?php endif; ?>

                    <?php if ($nextModule): ?>
                    <a href="?id=<?= $nextModule['module_id'] ?>" class="control-btn btn-next">
                        Next Phase <i class="fas fa-arrow-right"></i>
                    </a>
                    <?php elseif ($quiz): ?>
                    <a href="/jobmington/learn/quiz.php?id=<?= $quiz['quiz_id'] ?>" class="control-btn btn-next bg-gradient-to-r from-yellow-500 to-orange-500 shadow-orange-500/20">
                        Initiate Quiz <i class="fas fa-crosshairs"></i>
                    </a>
                    <?php else: ?>
                    <a href="/jobmington/learn/course.php?id=<?= $module['course_id'] ?>" class="control-btn btn-next bg-gradient-to-r from-emerald-500 to-teal-500 shadow-emerald-500/20">
                        Complete <i class="fas fa-check"></i>
                    </a>
                    <?php endif; ?>
                </div>
            </div>

        </div>

        <div class="lg:col-span-4 xl:col-span-3">
            <div class="hud-panel sticky top-24">
                <div class="p-4 border-b border-white/5 bg-white/[0.02]">
                    <h3 class="text-xs font-bold text-slate-400 uppercase tracking-widest">Data Stream</h3>
                </div>
                
                <div>
                    <?php foreach ($allModules as $idx => $m): 
                        $isCurrent = ($m['module_id'] == $moduleId);
                        $isCompleted = ($idx < $currentIndex);
                    ?>
                    <a href="?id=<?= $m['module_id'] ?>" class="module-item <?= $isCurrent ? 'active' : '' ?> <?= $isCompleted ? 'completed' : '' ?>">
                        <div class="status-indicator w-6 flex justify-center text-sm">
                            <?php if ($isCurrent): ?><i class="fas fa-play text-[10px]"></i>
                            <?php elseif ($isCompleted): ?><i class="fas fa-check-circle"></i>
                            <?php else: ?><span class="text-slate-600 font-mono text-xs"><?= $idx + 1 ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="flex-1">
                            <div class="module-title text-sm text-slate-400 transition"><?= e($m['title']) ?></div>
                            <div class="text-[10px] text-slate-600 font-mono mt-0.5">Packet #00<?= $idx + 1 ?></div>
                        </div>
                    </a>
                    <?php endforeach; ?>

                    <?php if ($quiz): ?>
                    <a href="/jobmington/learn/quiz.php?id=<?= $quiz['quiz_id'] ?>" class="module-item border-t border-white/10 hover:bg-yellow-500/5">
                        <div class="status-indicator text-yellow-500 w-6 flex justify-center"><i class="fas fa-trophy"></i></div>
                        <div class="text-sm font-bold text-yellow-500">Final Assessment</div>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
