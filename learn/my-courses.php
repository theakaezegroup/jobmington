<?php
require_once __DIR__ . '/_disabled.php';

/**
 * JOBMINGTON - My Courses
 * Displays enrolled courses, progress, and certificates
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
$userId = Session::userId();

// Fetch Data
$stmt = $pdo->prepare("
    SELECT ce.*, c.title, c.thumbnail, c.description, c.is_external, c.external_url,
           (SELECT COUNT(*) FROM course_modules WHERE course_id = c.course_id) as module_count,
           (SELECT verification_code FROM certificates WHERE user_id = ce.user_id AND course_id = c.course_id LIMIT 1) as cert_code
    FROM course_enrollments ce
    JOIN courses c ON ce.course_id = c.course_id
    WHERE ce.user_id = ?
    ORDER BY ce.started_at DESC
");
$stmt->execute([$userId]);
$enrollments = $stmt->fetchAll();

// Stats
$totalCourses = count($enrollments);
$completed = count(array_filter($enrollments, fn($c) => $c['progress'] >= 100));
$inProgress = $totalCourses - $completed;

$pageTitle = 'My Courses | ' . SITE_NAME;
require_once __DIR__ . '/../includes/header.php';
?>

<style>
    /* --- ARCHIVE THEME --- */
    body {
        background-color: #020617;
        background-image: 
            radial-gradient(circle at 90% 10%, rgba(59, 130, 246, 0.05), transparent 40%),
            linear-gradient(rgba(255,255,255,0.02) 1px, transparent 1px);
        background-size: 100% 100%, 40px 40px;
        color: #f8fafc;
    }

    /* --- DATA CARTRIDGE (Course Card) --- */
    .cartridge {
        position: relative;
        background: rgba(15, 23, 42, 0.6);
        backdrop-filter: blur(12px);
        border: 1px solid rgba(255, 255, 255, 0.05);
        border-radius: 1.5rem;
        overflow: hidden;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .cartridge:hover {
        transform: translateY(-5px);
        border-color: rgba(255, 255, 255, 0.1);
        box-shadow: 0 20px 40px -10px rgba(0,0,0,0.6);
    }

    /* Thumbnail Area */
    .cartridge-thumb {
        position: relative; height: 160px; overflow: hidden;
        border-bottom: 1px solid rgba(255,255,255,0.05);
    }
    .cartridge-thumb img {
        width: 100%; height: 100%; object-fit: cover;
        transition: transform 0.5s ease;
    }
    .cartridge:hover .cartridge-thumb img { transform: scale(1.05); }

    /* Progress Ring (SVG) */
    .progress-ring { position: absolute; bottom: -20px; right: 20px; width: 60px; height: 60px; background: #0f172a; border-radius: 50%; padding: 4px; box-shadow: 0 0 20px rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; z-index: 10; }
    .progress-text { font-size: 10px; font-weight: bold; color: white; }

    /* Completion Seal */
    .seal-verified {
        position: absolute; top: 10px; right: 10px;
        background: rgba(16, 185, 129, 0.9);
        backdrop-filter: blur(4px);
        color: white; font-size: 10px; font-weight: bold;
        padding: 4px 10px; border-radius: 20px;
        box-shadow: 0 0 15px rgba(16, 185, 129, 0.4);
        border: 1px solid rgba(255,255,255,0.2);
    }

    /* Stats Header */
    .stat-pill {
        background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.05);
        padding: 10px 20px; border-radius: 12px;
        display: flex; flex-direction: column; align-items: center; justify-content: center;
    }
    .stat-val { font-size: 1.2rem; font-weight: 800; color: white; }
    .stat-label { font-size: 0.65rem; text-transform: uppercase; letter-spacing: 0.1em; color: #64748b; }

</style>

<div class="max-w-7xl mx-auto px-4 py-12">
    
    <div class="flex flex-col md:flex-row justify-between items-end mb-12 gap-8">
        <div>
            <div class="text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-2"><i class="fas fa-database mr-2"></i>Skill Repository</div>
            <h1 class="text-4xl font-black text-white tracking-tight">My <span class="text-transparent bg-clip-text bg-gradient-to-r from-blue-400 to-cyan-400">Courses</span></h1>
        </div>
        
        <div class="flex gap-4">
            <div class="stat-pill">
                <span class="stat-val"><?= $totalCourses ?></span>
                <span class="stat-label">Enrolled</span>
            </div>
            <div class="stat-pill border-emerald-500/20 bg-emerald-500/5">
                <span class="stat-val text-emerald-400"><?= $completed ?></span>
                <span class="stat-label text-emerald-600">Mastered</span>
            </div>
            <a href="/jobmington/learn" class="px-6 py-3 rounded-xl bg-primary text-white font-bold text-xs uppercase tracking-widest hover:bg-blue-700 transition shadow-[0_0_20px_rgba(37,99,235,0.3)] flex items-center h-full">
                <i class="fas fa-plus mr-2"></i> Acquire Skill
            </a>
        </div>
    </div>

    <?php if (empty($enrollments)): ?>
        <div class="p-16 rounded-3xl border border-dashed border-slate-700 text-center">
            <i class="fas fa-microchip text-5xl text-slate-800 mb-6"></i>
            <h3 class="text-xl font-bold text-white mb-2">Memory Banks Empty</h3>
            <p class="text-slate-500 text-sm mb-6">No skill data found in local storage.</p>
            <a href="/jobmington/learn" class="text-slate-300 hover:text-white font-bold text-sm uppercase tracking-widest">Browse Courses &rarr;</a>
        </div>
    <?php else: ?>
        <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8">
            <?php foreach ($enrollments as $course): 
                $isDone = $course['progress'] >= 100;
                $strokeColor = $isDone ? '#10b981' : '#3b82f6';
            ?>
            <div class="cartridge group">
                
                <div class="cartridge-thumb">
                    <?php if ($course['thumbnail'] && strpos($course['thumbnail'], 'http') === 0): ?>
                        <img src="<?= e($course['thumbnail']) ?>" alt="<?= e($course['title']) ?>">
                    <?php elseif ($course['thumbnail']): ?>
                        <img src="<?= upload('course-thumbnails/' . $course['thumbnail']) ?>" alt="<?= e($course['title']) ?>">
                    <?php else: ?>
                        <div class="w-full h-full bg-gradient-to-br from-slate-800 to-slate-900 flex items-center justify-center">
                            <i class="fas fa-cube text-4xl text-slate-700"></i>
                        </div>
                    <?php endif; ?>

                    <?php if ($course['cert_code']): ?>
                        <div class="seal-verified"><i class="fas fa-check-circle mr-1"></i> VERIFIED</div>
                    <?php endif; ?>

                    <div class="progress-ring">
                        <svg class="w-full h-full transform -rotate-90">
                            <circle cx="26" cy="26" r="22" stroke="rgba(255,255,255,0.1)" stroke-width="4" fill="none"></circle>
                            <circle cx="26" cy="26" r="22" stroke="<?= $strokeColor ?>" stroke-width="4" fill="none" 
                                    stroke-dasharray="138" stroke-dashoffset="<?= 138 - (138 * $course['progress'] / 100) ?>"></circle>
                        </svg>
                        <span class="absolute text-[10px] font-bold text-white"><?= $course['progress'] ?>%</span>
                    </div>
                </div>

                <div class="p-6 pt-8">
                    <h3 class="font-bold text-lg text-white mb-2 line-clamp-1 group-hover:text-slate-300 transition">
                        <?= e($course['title']) ?>
                    </h3>
                    <p class="text-xs text-slate-400 mb-6 flex items-center gap-2">
                        <i class="fas fa-layer-group"></i> <?= $course['module_count'] ?> Modules
                        <span class="w-1 h-1 bg-slate-600 rounded-full"></span>
                        <?= $isDone ? 'Completed' : 'In Progress' ?>
                    </p>

                    <div class="flex gap-3">
                        <a href="/jobmington/learn/course.php?id=<?= $course['course_id'] ?>" 
                           class="flex-1 py-3 rounded-lg bg-white/5 border border-white/10 text-center text-xs font-bold uppercase tracking-wide hover:bg-white/10 hover:text-white transition text-slate-300">
                           <?= $isDone ? 'Review Data' : 'Resume Uplink' ?>
                        </a>
                        <?php if ($isDone && $course['cert_code']): ?>
                        <a href="/jobmington/certificates" class="px-4 py-3 rounded-lg bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 hover:bg-emerald-500/20 transition" title="Download Certificate">
                            <i class="fas fa-certificate"></i>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
