<?php
/**
 * JOBMINGTON - AI Skill Assessment
 * 
 * Interactive AI-powered assessment for skill verification.
 */

define('JOBMINGTON', true);

require_once __DIR__ . '/../../config/env.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';

Session::start();
$pdo = db();

if (!Session::isLoggedIn()) {
    header('Location: ' . SITE_URL . '/auth/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

$userId = Session::get('user_id');
$skill = trim($_GET['skill'] ?? '');

if (!$skill) {
    header('Location: ' . SITE_URL . '/wallet/passport/verify.php');
    exit;
}

// Get passport
$stmt = $pdo->prepare("SELECT * FROM talent_passports WHERE user_id = ?");
$stmt->execute([$userId]);
$passport = $stmt->fetch();

if (!$passport) {
    header('Location: ' . SITE_URL . '/wallet/passport/?no_passport=1');
    exit;
}

// Get cost
$stmt = $pdo->prepare("
    SELECT price_seeds
    FROM passport_pricing
    WHERE item_type = 'verification'
      AND item_name = 'Skill Verification'
      AND is_active = 1
    ORDER BY pricing_id DESC
    LIMIT 1
");
$stmt->execute();
$pricing = $stmt->fetch();
$cost = $pricing ? $pricing['price_seeds'] : 100;

// Check Seeds
$balance = getSeeds($userId);
if ($balance < $cost) {
    header('Location: ' . SITE_URL . '/wallet/passport/verify.php?error=insufficient_seeds');
    exit;
}

// Deduct Seeds upfront
deductSeeds($userId, $cost, "Passport: AI Assessment - $skill");

$pageTitle = "Skill Assessment: $skill";
require_once __DIR__ . '/../../includes/header.php';
?>

<style>
    .assessment-container {
        max-width: 640px;
        margin: 0 auto;
    }
    .question-card {
        background: rgba(255,255,255,0.02);
        border: 1px solid rgba(255,255,255,0.1);
        border-radius: 16px;
        padding: 24px;
    }
    .answer-option {
        display: block;
        width: 100%;
        padding: 16px;
        margin-bottom: 8px;
        text-align: left;
        background: rgba(255,255,255,0.02);
        border: 1px solid rgba(255,255,255,0.1);
        border-radius: 12px;
        color: #e2e8f0;
        cursor: pointer;
        transition: all 0.2s;
    }
    .answer-option:hover:not(:disabled) {
        background: rgba(255,255,255,0.05);
        border-color: rgba(251, 191, 36, 0.3);
    }
    .answer-option.selected {
        border-color: rgba(251, 191, 36, 0.5);
        background: rgba(251, 191, 36, 0.1);
    }
    .answer-option.correct {
        border-color: rgba(34, 197, 94, 0.5);
        background: rgba(34, 197, 94, 0.1);
        color: #4ade80;
    }
    .answer-option.incorrect {
        border-color: rgba(239, 68, 68, 0.5);
        background: rgba(239, 68, 68, 0.1);
        color: #f87171;
    }
    .progress-bar {
        height: 4px;
        background: rgba(255,255,255,0.1);
        border-radius: 2px;
        overflow: hidden;
    }
    .progress-fill {
        height: 100%;
        background: linear-gradient(90deg, #f59e0b, #f97316);
        transition: width 0.3s;
    }
    .timer {
        font-variant-numeric: tabular-nums;
    }
</style>

<div class="min-h-screen bg-slate-950 py-8 px-4">
    <div class="assessment-container">
        
        <!-- Header -->
        <div class="text-center mb-8">
            <span class="inline-block px-4 py-2 rounded-full bg-blue-500/10 text-blue-400 text-sm font-bold mb-4">
                <i class="fas fa-robot mr-2"></i> AI Assessment
            </span>
            <h1 class="text-2xl font-bold text-white mb-2"><?= e($skill) ?> Assessment</h1>
            <p class="text-slate-400">Answer 10 questions to verify your expertise</p>
        </div>
        
        <!-- Progress -->
        <div class="mb-6">
            <div class="flex justify-between text-sm text-slate-400 mb-2">
                <span>Question <span id="currentQ">1</span>/10</span>
                <span class="timer" id="timer">5:00</span>
            </div>
            <div class="progress-bar">
                <div class="progress-fill" id="progressBar" style="width: 10%"></div>
            </div>
        </div>
        
        <!-- Question -->
        <div id="questionContainer" class="question-card">
            <div id="loading" class="text-center py-12">
                <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-blue-500/10 flex items-center justify-center animate-pulse">
                    <i class="fas fa-brain text-2xl text-blue-400"></i>
                </div>
                <p class="text-slate-400">Generating assessment questions...</p>
            </div>
            
            <div id="questionContent" class="hidden">
                <h2 id="questionText" class="text-lg text-white mb-6"></h2>
                <div id="answersContainer"></div>
            </div>
        </div>
        
        <!-- Navigation -->
        <div class="flex justify-between mt-6">
            <button id="prevBtn" disabled onclick="prevQuestion()" class="px-6 py-3 rounded-xl bg-white/5 text-slate-400 font-bold disabled:opacity-50 hover:bg-white/10 transition">
                <i class="fas fa-arrow-left mr-2"></i> Previous
            </button>
            <button id="nextBtn" onclick="nextQuestion()" class="px-6 py-3 rounded-xl bg-amber-500 text-black font-bold hover:bg-amber-400 transition">
                Next <i class="fas fa-arrow-right ml-2"></i>
            </button>
        </div>
        
        <!-- Results (hidden initially) -->
        <div id="resultsContainer" class="hidden">
            <div class="question-card text-center py-12">
                <div id="resultIcon" class="w-24 h-24 mx-auto mb-6 rounded-full flex items-center justify-center"></div>
                <h2 id="resultTitle" class="text-2xl font-bold text-white mb-2"></h2>
                <p id="resultMessage" class="text-slate-400 mb-6"></p>
                
                <div class="flex gap-4 justify-center">
                    <a href="<?= SITE_URL ?>/wallet/passport/verify.php" class="px-6 py-3 rounded-xl bg-white/10 text-white font-bold hover:bg-white/20 transition">
                        Back to Verification
                    </a>
                    <a href="<?= SITE_URL ?>/wallet/passport/" class="px-6 py-3 rounded-xl bg-amber-500 text-black font-bold hover:bg-amber-400 transition">
                        View Passport
                    </a>
                </div>
            </div>
        </div>
        
    </div>
</div>

<script>
const skill = <?= json_encode($skill) ?>;
let questions = [];
let currentQuestion = 0;
let answers = [];
let timerInterval;
let timeLeft = 300; // 5 minutes

// Generate questions via API
async function loadQuestions() {
    try {
        const response = await fetch('<?= SITE_URL ?>/api/andika.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                action: 'generate_assessment',
                skill: skill
            })
        });
        
        const data = await response.json();
        
        if (data.questions && data.questions.length > 0) {
            questions = data.questions;
        } else {
            // Fallback questions
            questions = generateFallbackQuestions(skill);
        }
        
        showQuestion(0);
        startTimer();
        
    } catch (e) {
        console.error('Failed to load questions:', e);
        questions = generateFallbackQuestions(skill);
        showQuestion(0);
        startTimer();
    }
}

function generateFallbackQuestions(skill) {
    // Generic skill questions
    return [
        {
            question: `What is your experience level with ${skill}?`,
            options: ['Beginner (0-1 years)', 'Intermediate (1-3 years)', 'Advanced (3-5 years)', 'Expert (5+ years)'],
            correct: -1 // Any answer valid
        },
        {
            question: `In a typical ${skill} project, what's the first step you take?`,
            options: ['Jump straight into coding', 'Understand requirements thoroughly', 'Copy from previous projects', 'Ask someone else to do it'],
            correct: 1
        },
        {
            question: `How do you stay updated with ${skill} best practices?`,
            options: ['I don\'t', 'Read documentation and articles', 'Follow industry leaders and communities', 'Both B and C'],
            correct: 3
        },
        {
            question: `When you encounter a difficult problem in ${skill}, what do you do?`,
            options: ['Give up immediately', 'Search for solutions online', 'Break it down into smaller parts', 'Both B and C'],
            correct: 3
        },
        {
            question: `How would you explain ${skill} to a complete beginner?`,
            options: ['Use complex technical jargon', 'Use simple analogies and examples', 'Tell them it\'s too complicated', 'Avoid the conversation'],
            correct: 1
        },
        {
            question: `What's the most important quality for mastering ${skill}?`,
            options: ['Natural talent only', 'Consistent practice and learning', 'Expensive courses', 'Memorizing everything'],
            correct: 1
        },
        {
            question: `How do you handle feedback on your ${skill} work?`,
            options: ['Ignore all criticism', 'Take it personally', 'Use it to improve', 'Argue against it'],
            correct: 2
        },
        {
            question: `What role does collaboration play in ${skill}?`,
            options: ['None, work alone always', 'Very important for growth', 'Only when required', 'Slows me down'],
            correct: 1
        },
        {
            question: `How do you measure your progress in ${skill}?`,
            options: ['I don\'t track progress', 'By completed projects', 'By peer feedback and results', 'Both B and C'],
            correct: 3
        },
        {
            question: `What motivates you to improve in ${skill}?`,
            options: ['Just for money', 'Personal growth and challenges', 'Pressure from others', 'Nothing'],
            correct: 1
        }
    ];
}

function showQuestion(index) {
    document.getElementById('loading').classList.add('hidden');
    document.getElementById('questionContent').classList.remove('hidden');
    
    const q = questions[index];
    document.getElementById('questionText').textContent = q.question;
    document.getElementById('currentQ').textContent = index + 1;
    document.getElementById('progressBar').style.width = ((index + 1) / questions.length * 100) + '%';
    
    const container = document.getElementById('answersContainer');
    container.innerHTML = '';
    
    q.options.forEach((option, i) => {
        const btn = document.createElement('button');
        btn.className = 'answer-option';
        btn.textContent = option;
        btn.onclick = () => selectAnswer(i);
        
        if (answers[index] === i) {
            btn.classList.add('selected');
        }
        
        container.appendChild(btn);
    });
    
    // Update navigation
    document.getElementById('prevBtn').disabled = index === 0;
    document.getElementById('nextBtn').textContent = index === questions.length - 1 ? 'Submit' : 'Next';
    document.getElementById('nextBtn').innerHTML = index === questions.length - 1 
        ? '<i class="fas fa-check mr-2"></i> Submit' 
        : 'Next <i class="fas fa-arrow-right ml-2"></i>';
}

function selectAnswer(index) {
    answers[currentQuestion] = index;
    
    document.querySelectorAll('.answer-option').forEach((btn, i) => {
        btn.classList.toggle('selected', i === index);
    });
}

function nextQuestion() {
    if (answers[currentQuestion] === undefined) {
        alert('Please select an answer');
        return;
    }
    
    if (currentQuestion < questions.length - 1) {
        currentQuestion++;
        showQuestion(currentQuestion);
    } else {
        submitAssessment();
    }
}

function prevQuestion() {
    if (currentQuestion > 0) {
        currentQuestion--;
        showQuestion(currentQuestion);
    }
}

function startTimer() {
    timerInterval = setInterval(() => {
        timeLeft--;
        
        const mins = Math.floor(timeLeft / 60);
        const secs = timeLeft % 60;
        document.getElementById('timer').textContent = `${mins}:${secs.toString().padStart(2, '0')}`;
        
        if (timeLeft <= 0) {
            clearInterval(timerInterval);
            submitAssessment();
        }
    }, 1000);
}

async function submitAssessment() {
    clearInterval(timerInterval);
    
    // Calculate score
    let correct = 0;
    questions.forEach((q, i) => {
        if (q.correct === -1 || answers[i] === q.correct) {
            correct++;
        }
    });
    
    const score = Math.round((correct / questions.length) * 100);
    const passed = score >= 70;
    
    // Send result to server
    const formData = new FormData();
    formData.append('action', 'ai_assessment_complete');
    formData.append('skill', skill);
    formData.append('score', score);
    
    try {
        await fetch('<?= SITE_URL ?>/api/passport/verify.php', {
            method: 'POST',
            body: formData
        });
    } catch (e) {
        console.error('Failed to save result:', e);
    }
    
    // Show results
    document.getElementById('questionContainer').classList.add('hidden');
    document.querySelectorAll('.flex.justify-between.mt-6').forEach(el => el.classList.add('hidden'));
    document.getElementById('resultsContainer').classList.remove('hidden');
    
    const icon = document.getElementById('resultIcon');
    const title = document.getElementById('resultTitle');
    const message = document.getElementById('resultMessage');
    
    if (passed) {
        icon.innerHTML = '<i class="fas fa-check text-4xl text-emerald-400"></i>';
        icon.className = 'w-24 h-24 mx-auto mb-6 rounded-full flex items-center justify-center bg-emerald-500/20';
        title.textContent = ' Skill Verified!';
        title.className = 'text-2xl font-bold text-emerald-400 mb-2';
        message.textContent = `You scored ${score}%. Your ${skill} skill has been verified on your Talent Passport.`;
    } else {
        icon.innerHTML = '<i class="fas fa-times text-4xl text-red-400"></i>';
        icon.className = 'w-24 h-24 mx-auto mb-6 rounded-full flex items-center justify-center bg-red-500/20';
        title.textContent = 'Assessment Not Passed';
        title.className = 'text-2xl font-bold text-red-400 mb-2';
        message.textContent = `You scored ${score}%. You need 70% to pass. You can try again later.`;
    }
}

// Start
loadQuestions();
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
