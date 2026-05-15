<?php
require_once __DIR__ . '/_disabled.php';

/**
 * JOBMINGTON - The Simulation (Course Assessment)
 * Aesthetic: Cyber-Obsidian & Neon
 * Features: Gamified Quiz, Holographic Results, Certificate Unlock
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
$quizId = (int) get('id', 0);
$userId = Session::userId();

if ($quizId <= 0) redirect('/jobmington/learn');

// Fetch Quiz Data
$stmt = $pdo->prepare("SELECT q.*, c.course_id, c.title as course_title FROM quizzes q JOIN courses c ON q.course_id = c.course_id WHERE q.quiz_id = ? AND c.is_published = 1");
$stmt->execute([$quizId]);
$quiz = $stmt->fetch();

if (!$quiz) { Session::flash('error', 'Simulation not found.'); redirect('/jobmington/learn'); }

// Check Enrollment
$stmt = $pdo->prepare("SELECT * FROM course_enrollments WHERE user_id = ? AND course_id = ?");
$stmt->execute([$userId, $quiz['course_id']]);
$enrollment = $stmt->fetch();

if (!$enrollment) { Session::flash('info', 'Access Denied.'); redirect('/jobmington/learn/course.php?id=' . $quiz['course_id']); }

// Fetch Questions
$stmt = $pdo->prepare("SELECT * FROM quiz_questions WHERE quiz_id = ? ORDER BY question_id");
$stmt->execute([$quizId]);
$questions = $stmt->fetchAll();

// Logic
$showResults = false;
$score = 0;
$passed = false;
$userAnswers = [];
$certificateCode = null;

if (isPost() && Security::verifyCSRF()) {
    $showResults = true;
    $correct = 0;
    
    foreach ($questions as $q) {
        $answer = post('q_' . $q['question_id'], '');
        $userAnswers[$q['question_id']] = $answer;
        if (strtolower($answer) === strtolower($q['correct_option'])) $correct++;
    }
    
    $score = count($questions) > 0 ? round(($correct / count($questions)) * 100) : 0;
    $passed = $score >= $quiz['passing_score'];
    
    if ($passed) {
        // Issue Certificate
        $stmt = $pdo->prepare("SELECT * FROM certificates WHERE user_id = ? AND course_id = ?");
        $stmt->execute([$userId, $quiz['course_id']]);
        $existingCert = $stmt->fetch();
        
        if (!$existingCert) {
            $certificateCode = 'JMT-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(4)));
            $stmt = $pdo->prepare("INSERT INTO certificates (cert_code, user_id, course_id) VALUES (?, ?, ?)");
            $stmt->execute([$certificateCode, $userId, $quiz['course_id']]);
            
            // Notification Logic Here (Optional)
        } else {
            $certificateCode = $existingCert['cert_code'];
        }
        $pdo->prepare("UPDATE course_enrollments SET progress = 100 WHERE enrollment_id = ?")->execute([$enrollment['enrollment_id']]);
    }
}

$pageTitle = 'Simulation: ' . e($quiz['title']) . ' | ' . SITE_NAME;
require_once __DIR__ . '/../includes/header.php';
?>

<script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>

<style>
    /* --- THEME --- */
    body {
        background-color: #020617;
        color: #f8fafc;
        background-image: 
            linear-gradient(rgba(255,255,255,0.02) 1px, transparent 1px),
            linear-gradient(90deg, rgba(255,255,255,0.02) 1px, transparent 1px);
        background-size: 30px 30px;
    }

    /* --- QUESTION CARD --- */
    .quiz-card {
        background: rgba(15, 23, 42, 0.6);
        backdrop-filter: blur(12px);
        border: 1px solid rgba(255,255,255,0.05);
        border-radius: 1rem;
        padding: 1.5rem;
        margin-bottom: 1.5rem;
        transition: 0.3s;
    }
    .quiz-card:hover { border-color: rgba(255,255,255,0.1); transform: translateX(5px); }

    /* Options */
    .option-label {
        display: flex; align-items: center; gap: 1rem;
        padding: 1rem;
        background: rgba(255,255,255,0.02);
        border: 1px solid rgba(255,255,255,0.05);
        border-radius: 0.75rem;
        cursor: pointer;
        transition: 0.2s;
    }
    .option-label:hover { background: rgba(255,255,255,0.05); }
    
    /* Checked State */
    .option-input:checked + .option-indicator {
        background: #3b82f6; border-color: #3b82f6; box-shadow: 0 0 10px #3b82f6;
    }
    .option-input:checked ~ .option-text { color: #fff; font-weight: bold; }

    .option-indicator {
        width: 20px; height: 20px; border-radius: 50%;
        border: 2px solid #475569;
        transition: 0.2s;
    }

    /* --- RESULTS --- */
    .result-ring {
        width: 120px; height: 120px; border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        font-size: 2rem; font-weight: 900;
        margin: 0 auto 1.5rem;
        position: relative;
    }
    .result-pass { border: 4px solid #10b981; color: #10b981; box-shadow: 0 0 30px rgba(16, 185, 129, 0.3); }
    .result-fail { border: 4px solid #ef4444; color: #ef4444; box-shadow: 0 0 30px rgba(239, 68, 68, 0.3); }

    /* Button */
    .btn-submit {
        background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        color: white; font-weight: 800; text-transform: uppercase; letter-spacing: 0.1em;
        padding: 1rem 2rem; border-radius: 0.75rem; width: 100%;
        transition: 0.3s;
        box-shadow: 0 10px 20px -5px rgba(37, 99, 235, 0.4);
    }
    .btn-submit:hover { transform: translateY(-2px); box-shadow: 0 15px 30px -5px rgba(37, 99, 235, 0.6); }

</style>

<div class="max-w-3xl mx-auto px-4 py-12">
    
    <div class="mb-10 flex flex-col md:flex-row justify-between items-start md:items-end gap-4">
        <div>
            <a href="/jobmington/learn/course.php?id=<?= $quiz['course_id'] ?>" class="text-[10px] font-bold text-slate-500 uppercase tracking-widest hover:text-white transition mb-2 block">
                <i class="fas fa-arrow-left mr-1"></i> Abort Simulation
            </a>
            <h1 class="text-3xl font-black text-white tracking-tight">Assessment Protocol</h1>
            <p class="text-slate-400 font-mono text-xs">Required Accuracy: <?= $quiz['passing_score'] ?>%</p>
        </div>
        
        <?php if (!$showResults): ?>
        <div class="px-4 py-2 bg-white/5 border border-white/10 rounded-lg text-yellow-500 font-mono font-bold text-lg">
            <i class="fas fa-stopwatch mr-2"></i> <span id="timer">00:00</span>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($showResults): ?>
    <div class="text-center py-10 bg-white/5 border border-white/10 rounded-2xl mb-12">
        <div class="result-ring <?= $passed ? 'result-pass' : 'result-fail' ?>">
            <?= $score ?>%
        </div>
        
        <h2 class="text-2xl font-bold text-white mb-2"><?= $passed ? 'Simulation Passed' : 'Protocol Failed' ?></h2>
        <p class="text-slate-400 text-sm mb-8">
            <?= $passed ? 'Congratulations! You have passed and unlocked your certificate.' : 'You did not pass this time. Please review and try again.' ?>
        </p>

        <div class="flex justify-center gap-4">
            <?php if ($passed): ?>
                <a href="/jobmington/certificates" class="px-6 py-3 bg-emerald-600 text-white font-bold text-xs uppercase tracking-widest rounded-lg hover:bg-emerald-500 transition shadow-[0_0_20px_rgba(16,185,129,0.4)]">
                    Access Certificate
                </a>
            <?php else: ?>
                <a href="/jobmington/learn/quiz.php?id=<?= $quizId ?>" class="px-6 py-3 bg-white/10 text-white font-bold text-xs uppercase tracking-widest rounded-lg hover:bg-white/20 transition">
                    Retry Simulation
                </a>
            <?php endif; ?>
            <a href="/jobmington/learn/course.php?id=<?= $quiz['course_id'] ?>" class="px-6 py-3 bg-transparent border border-white/20 text-white font-bold text-xs uppercase tracking-widest rounded-lg hover:bg-white/5 transition">
                Return to Hub
            </a>
        </div>
        
        <?php if ($passed): ?>
        <script>
            // Celebration
            confetti({ particleCount: 150, spread: 70, origin: { y: 0.6 }, colors: ['#10b981', '#ffffff'] });
        </script>
        <?php endif; ?>
    </div>
    
    <div class="space-y-6">
        <h3 class="text-xs font-bold text-slate-500 uppercase tracking-widest mb-4">Diagnostic Report</h3>
        <?php foreach ($questions as $idx => $q): 
            $userAns = $userAnswers[$q['question_id']] ?? '';
            $isCorrect = strtolower($userAns) === strtolower($q['correct_option']);
            $borderColor = $isCorrect ? 'border-emerald-500/50' : 'border-red-500/50';
        ?>
        <div class="quiz-card <?= $borderColor ?>" style="border-left-width: 4px;">
            <div class="flex gap-4">
                <div class="text-2xl <?= $isCorrect ? 'text-emerald-500' : 'text-red-500' ?>">
                    <i class="fas <?= $isCorrect ? 'fa-check-circle' : 'fa-times-circle' ?>"></i>
                </div>
                <div>
                    <h4 class="text-white font-bold mb-2"><?= e($q['question']) ?></h4>
                    <p class="text-xs text-slate-400 mb-1">Your Input: <span class="text-white font-mono"><?= strtoupper($userAns) ?></span></p>
                    <?php if (!$isCorrect): ?>
                    <p class="text-xs text-emerald-400">Correct Protocol: <span class="font-mono"><?= strtoupper($q['correct_option']) ?></span></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php else: ?>
    <form method="POST">
        <?= csrf_field() ?>
        
        <?php if (empty($questions)): ?>
            <div class="p-10 text-center border border-dashed border-slate-700 rounded-2xl">
                <p class="text-slate-500">No data packets found.</p>
            </div>
        <?php else: ?>
            
            <div class="space-y-8">
                <?php foreach ($questions as $idx => $q): ?>
                <div class="quiz-card">
                    <div class="flex items-start gap-4 mb-6">
                        <span class="text-blue-500 font-black text-xl">0<?= $idx + 1 ?></span>
                        <h3 class="text-lg font-bold text-white"><?= e($q['question']) ?></h3>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 pl-8">
                        <?php foreach (['a', 'b', 'c', 'd'] as $opt): 
                            $val = $q['option_' . $opt];
                            if (empty($val)) continue;
                        ?>
                        <label class="option-label group">
                            <input type="radio" name="q_<?= $q['question_id'] ?>" value="<?= $opt ?>" class="hidden option-input" required>
                            <div class="option-indicator"></div>
                            <span class="option-text text-sm text-slate-400 group-hover:text-white transition"><?= e($val) ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="mt-12">
                <button type="submit" class="btn-submit">
                    Submit Data for Analysis
                </button>
            </div>

        <?php endif; ?>
    </form>
    <?php endif; ?>

</div>

<script>
    // Simple Timer Logic
    let seconds = 0;
    const timerEl = document.getElementById('timer');
    if (timerEl) {
        setInterval(() => {
            seconds++;
            const m = Math.floor(seconds / 60).toString().padStart(2, '0');
            const s = (seconds % 60).toString().padStart(2, '0');
            timerEl.innerText = `${m}:${s}`;
        }, 1000);
    }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
