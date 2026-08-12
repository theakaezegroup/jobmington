<?php
/**
 * JOBMINGTON - Admin Quizzes Management
 * Create, edit, and manage course quizzes and questions
 */

define('JOBMINGTON', true);
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';

Session::start();
Session::requireAdmin();

$pdo = db();
$success = '';
$error = '';
$action = get('action', 'list');
$quizId = (int) get('id', 0);

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = post('action', '');
    
    if ($postAction === 'create_quiz') {
        $courseId = (int) post('course_id', 0);
        $title = Security::clean(post('title', ''));
        $description = Security::clean(post('description', ''));
        $passingScore = (int) post('passing_score', 70);
        $timeLimit = (int) post('time_limit', 0);
        
        if ($courseId > 0 && $title) {
            try {
                $stmt = $pdo->prepare("INSERT INTO quizzes (course_id, title, description, passing_score, time_limit, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
                $stmt->execute([$courseId, $title, $description, $passingScore, $timeLimit]);
                $success = "Quiz '{$title}' created!";
            } catch (Exception $e) {
                $error = 'Error creating quiz: ' . $e->getMessage();
            }
        } else {
            $error = 'Course and title are required.';
        }
    } elseif ($postAction === 'add_question') {
        $qzId = (int) post('quiz_id', 0);
        $question = Security::clean(post('question', ''));
        $optionA = Security::clean(post('option_a', ''));
        $optionB = Security::clean(post('option_b', ''));
        $optionC = Security::clean(post('option_c', ''));
        $optionD = Security::clean(post('option_d', ''));
        $correct = Security::clean(post('correct_option', 'A'));
        
        if ($qzId > 0 && $question && $optionA && $optionB) {
            try {
                $stmt = $pdo->prepare("INSERT INTO quiz_questions (quiz_id, question, option_a, option_b, option_c, option_d, correct_option) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$qzId, $question, $optionA, $optionB, $optionC, $optionD, $correct]);
                $success = "Question added!";
                $action = 'questions';
                $quizId = $qzId;
            } catch (Exception $e) {
                $error = 'Error adding question.';
            }
        } else {
            $error = 'Question and at least 2 options are required.';
        }
    } elseif ($postAction === 'delete_quiz') {
        $id = (int) post('quiz_id', 0);
        if ($id > 0) {
            try {
                $pdo->prepare("DELETE FROM quiz_questions WHERE quiz_id = ?")->execute([$id]);
                $pdo->prepare("DELETE FROM quizzes WHERE quiz_id = ?")->execute([$id]);
                $success = 'Quiz deleted!';
            } catch (Exception $e) { $error = 'Error deleting quiz.'; }
        }
    } elseif ($postAction === 'delete_question') {
        $id = (int) post('question_id', 0);
        $qzId = (int) post('quiz_id', 0);
        if ($id > 0) {
            try {
                $pdo->prepare("DELETE FROM quiz_questions WHERE question_id = ?")->execute([$id]);
                $success = 'Question deleted!';
                $action = 'questions';
                $quizId = $qzId;
            } catch (Exception $e) { $error = 'Error deleting question.'; }
        }
    }
}

// Fetch data
try { $quizzes = $pdo->query("SELECT q.*, c.title as course_title FROM quizzes q LEFT JOIN courses c ON q.course_id = c.course_id ORDER BY q.created_at DESC")->fetchAll(); } catch (Exception $e) { $quizzes = []; }
try { $courses = $pdo->query("SELECT course_id, title FROM courses ORDER BY title")->fetchAll(); } catch (Exception $e) { $courses = []; }

$quiz = null;
$questions = [];
if ($quizId > 0) {
    try {
        $stmt = $pdo->prepare("SELECT q.*, c.title as course_title FROM quizzes q LEFT JOIN courses c ON q.course_id = c.course_id WHERE q.quiz_id = ?");
        $stmt->execute([$quizId]);
        $quiz = $stmt->fetch();
        
        $stmt = $pdo->prepare("SELECT * FROM quiz_questions WHERE quiz_id = ? ORDER BY question_id");
        $stmt->execute([$quizId]);
        $questions = $stmt->fetchAll();
    } catch (Exception $e) {}
}

$pageTitle = 'Quiz Management - ' . SITE_NAME;
require_once __DIR__ . '/../includes/header.php';
?>

<div class="min-h-screen bg-slate-900">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-8">
            <div>
                <a href="/jobmington/admin/" class="text-slate-400 hover:text-white text-xs font-bold uppercase tracking-widest mb-2 inline-block transition">
                    <i class="fas fa-arrow-left mr-1"></i> Admin Dashboard
                </a>
                <h1 class="text-4xl font-heading font-black text-white">Quiz Management</h1>
            </div>
            <?php if ($action !== 'list'): ?>
            <a href="?action=list" class="px-6 py-3 bg-white/10 hover:bg-white/20 text-white rounded-xl font-bold transition">
                <i class="fas fa-list mr-2"></i> All Quizzes
            </a>
            <?php endif; ?>
        </div>

        <?php if ($success): ?>
            <div class="bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 p-4 rounded-xl mb-6"><i class="fas fa-check-circle mr-2"></i> <?= e($success) ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="bg-red-500/10 border border-red-500/30 text-red-400 p-4 rounded-xl mb-6"><i class="fas fa-exclamation-circle mr-2"></i> <?= e($error) ?></div>
        <?php endif; ?>

        <?php if ($action === 'list'): ?>
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Create Quiz Form -->
            <div class="bg-white/5 border border-white/10 rounded-xl p-6">
                <h3 class="text-lg font-bold text-white mb-4">Create New Quiz</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="create_quiz">
                    
                    <div class="mb-4">
                        <label class="block text-xs font-bold text-slate-400 uppercase mb-2">Course</label>
                        <select name="course_id" required class="w-full bg-white/5 border border-white/10 rounded-lg px-4 py-3 text-white focus:border-amber-500 focus:outline-none">
                            <option value="">Select Course...</option>
                            <?php foreach ($courses as $c): ?>
                                <option value="<?= $c['course_id'] ?>"><?= e($c['title']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-xs font-bold text-slate-400 uppercase mb-2">Quiz Title</label>
                        <input type="text" name="title" required class="w-full bg-white/5 border border-white/10 rounded-lg px-4 py-3 text-white focus:border-amber-500 focus:outline-none">
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-xs font-bold text-slate-400 uppercase mb-2">Description</label>
                        <textarea name="description" rows="2" class="w-full bg-white/5 border border-white/10 rounded-lg px-4 py-3 text-white focus:border-amber-500 focus:outline-none"></textarea>
                    </div>
                    
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-400 uppercase mb-2">Pass %</label>
                            <input type="number" name="passing_score" value="70" min="0" max="100" class="w-full bg-white/5 border border-white/10 rounded-lg px-4 py-3 text-white focus:border-amber-500 focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-400 uppercase mb-2">Time (min)</label>
                            <input type="number" name="time_limit" value="0" min="0" placeholder="0 = no limit" class="w-full bg-white/5 border border-white/10 rounded-lg px-4 py-3 text-white focus:border-amber-500 focus:outline-none">
                        </div>
                    </div>
                    
                    <button type="submit" class="w-full bg-blue-500 hover:bg-blue-400 text-white font-bold py-3 rounded-lg transition">Create Quiz</button>
                </form>
            </div>
            
            <!-- Quizzes List -->
            <div class="lg:col-span-2">
                <h3 class="text-lg font-bold text-white mb-4">Existing Quizzes</h3>
                <?php if (empty($quizzes)): ?>
                    <div class="bg-white/5 border border-white/10 rounded-xl p-8 text-center">
                        <i class="fas fa-question-circle text-4xl text-slate-600 mb-4"></i>
                        <p class="text-slate-400">No quizzes yet.</p>
                    </div>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($quizzes as $qz): ?>
                        <div class="bg-white/5 border border-white/10 rounded-xl p-4 hover:border-white/20 transition">
                            <div class="flex items-center justify-between flex-wrap gap-2">
                                <div>
                                    <h4 class="font-bold text-white"><?= e($qz['title']) ?></h4>
                                    <p class="text-xs text-slate-400"><?= e($qz['course_title']) ?></p>
                                    <span class="text-xs text-blue-400">Pass: <?= $qz['passing_score'] ?>%</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <a href="?action=questions&id=<?= $qz['quiz_id'] ?>" class="px-3 py-1 bg-blue-500/20 text-blue-400 rounded-lg text-xs font-bold hover:bg-blue-500/30 transition"><i class="fas fa-edit mr-1"></i> Questions</a>
                                    <form method="POST" class="inline" onsubmit="return confirm('Delete this quiz?')">
                                        <input type="hidden" name="action" value="delete_quiz">
                                        <input type="hidden" name="quiz_id" value="<?= $qz['quiz_id'] ?>">
                                        <button type="submit" class="px-3 py-1 bg-red-500/20 text-red-400 rounded-lg text-xs font-bold hover:bg-red-500/30 transition"><i class="fas fa-trash"></i></button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php elseif ($action === 'questions' && $quiz): ?>
        <!-- Questions Management -->
        <div class="mb-6">
            <h2 class="text-xl font-bold text-white">Quiz: <?= e($quiz['title']) ?></h2>
            <p class="text-slate-400">Course: <?= e($quiz['course_title']) ?> | Pass: <?= $quiz['passing_score'] ?>%</p>
        </div>
        
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Add Question Form -->
            <div class="bg-white/5 border border-white/10 rounded-xl p-6">
                <h3 class="text-lg font-bold text-white mb-4">Add Question</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="add_question">
                    <input type="hidden" name="quiz_id" value="<?= $quizId ?>">
                    
                    <div class="mb-4">
                        <label class="block text-xs font-bold text-slate-400 uppercase mb-2">Question</label>
                        <textarea name="question" rows="3" required class="w-full bg-white/5 border border-white/10 rounded-lg px-4 py-3 text-white focus:border-amber-500 focus:outline-none"></textarea>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-xs font-bold text-slate-400 uppercase mb-2">Option A</label>
                        <input type="text" name="option_a" required class="w-full bg-white/5 border border-white/10 rounded-lg px-4 py-3 text-white focus:border-amber-500 focus:outline-none">
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-xs font-bold text-slate-400 uppercase mb-2">Option B</label>
                        <input type="text" name="option_b" required class="w-full bg-white/5 border border-white/10 rounded-lg px-4 py-3 text-white focus:border-amber-500 focus:outline-none">
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-xs font-bold text-slate-400 uppercase mb-2">Option C</label>
                        <input type="text" name="option_c" class="w-full bg-white/5 border border-white/10 rounded-lg px-4 py-3 text-white focus:border-amber-500 focus:outline-none">
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-xs font-bold text-slate-400 uppercase mb-2">Option D</label>
                        <input type="text" name="option_d" class="w-full bg-white/5 border border-white/10 rounded-lg px-4 py-3 text-white focus:border-amber-500 focus:outline-none">
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-xs font-bold text-slate-400 uppercase mb-2">Correct Answer</label>
                        <select name="correct_option" class="w-full bg-white/5 border border-white/10 rounded-lg px-4 py-3 text-white focus:border-amber-500 focus:outline-none">
                            <option value="A">A</option>
                            <option value="B">B</option>
                            <option value="C">C</option>
                            <option value="D">D</option>
                        </select>
                    </div>
                    
                    <button type="submit" class="w-full bg-emerald-500 hover:bg-emerald-400 text-white font-bold py-3 rounded-lg transition">Add Question</button>
                </form>
            </div>
            
            <!-- Questions List -->
            <div class="lg:col-span-2">
                <h3 class="text-lg font-bold text-white mb-4">Questions (<?= count($questions) ?>)</h3>
                <?php if (empty($questions)): ?>
                    <div class="bg-white/5 border border-white/10 rounded-xl p-8 text-center">
                        <p class="text-slate-400">No questions yet. Add your first question!</p>
                    </div>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($questions as $i => $q): ?>
                        <div class="bg-white/5 border border-white/10 rounded-xl p-4">
                            <div class="flex justify-between items-start mb-3 flex-wrap gap-2">
                                <span class="text-white font-bold"><?= $i + 1 ?>. <?= e($q['question']) ?></span>
                                <form method="POST" class="inline" onsubmit="return confirm('Delete?')">
                                    <input type="hidden" name="action" value="delete_question">
                                    <input type="hidden" name="question_id" value="<?= $q['question_id'] ?>">
                                    <input type="hidden" name="quiz_id" value="<?= $quizId ?>">
                                    <button type="submit" class="text-red-400 hover:text-red-300"><i class="fas fa-trash"></i></button>
                                </form>
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-sm">
                                <span class="<?= $q['correct_option'] === 'A' ? 'text-emerald-400' : 'text-slate-400' ?>">A: <?= e($q['option_a']) ?></span>
                                <span class="<?= $q['correct_option'] === 'B' ? 'text-emerald-400' : 'text-slate-400' ?>">B: <?= e($q['option_b']) ?></span>
                                <?php if ($q['option_c']): ?><span class="<?= $q['correct_option'] === 'C' ? 'text-emerald-400' : 'text-slate-400' ?>">C: <?= e($q['option_c']) ?></span><?php endif; ?>
                                <?php if ($q['option_d']): ?><span class="<?= $q['correct_option'] === 'D' ? 'text-emerald-400' : 'text-slate-400' ?>">D: <?= e($q['option_d']) ?></span><?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>