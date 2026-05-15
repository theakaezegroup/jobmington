<?php
require_once __DIR__ . '/_disabled.php';

/**
 * JOBMINGTON - Course Details (Premium Design)
 * Handles both internal courses and external resources
 */

define('JOBMINGTON', true);
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';

Session::start();

$pdo = db();
$courseId = (int) get('id', 0);

if ($courseId <= 0) {
    redirect('/jobmington/learn');
}

// Get Course
$stmt = $pdo->prepare("
    SELECT c.*, cc.name as category_name, cc.icon as category_icon
    FROM courses c
    LEFT JOIN course_categories cc ON c.category_id = cc.id
    WHERE c.course_id = ? AND c.is_published = 1
");
$stmt->execute([$courseId]);
$course = $stmt->fetch();

if (!$course) {
    Session::flash('error', 'Course not found.');
    redirect('/jobmington/learn');
}

// Get Modules (for internal courses)
$stmt = $pdo->prepare("
    SELECT * FROM course_modules 
    WHERE course_id = ? 
    ORDER BY sort_order ASC
");
$stmt->execute([$courseId]);
$modules = $stmt->fetchAll();

// Get Quiz (if any)
$stmt = $pdo->prepare("SELECT * FROM quizzes WHERE course_id = ? LIMIT 1");
$stmt->execute([$courseId]);
$quiz = $stmt->fetch();

// Check if user is enrolled
$isEnrolled = false;
$enrollment = null;
$hasPurchased = false;
$userId = Session::userId();

if ($userId) {
    $stmt = $pdo->prepare("SELECT * FROM course_enrollments WHERE user_id = ? AND course_id = ?");
    $stmt->execute([$userId, $courseId]);
    $enrollment = $stmt->fetch();
    $isEnrolled = (bool) $enrollment;
    
    // Check if user purchased this course (for premium courses)
    if (!$course['is_free']) {
        $stmt = $pdo->prepare("SELECT * FROM course_purchases WHERE user_id = ? AND course_id = ?");
        $stmt->execute([$userId, $courseId]);
        $hasPurchased = (bool) $stmt->fetch();
    }
}

// For free courses, access is always granted
$hasAccess = $course['is_free'] || $hasPurchased;

// Handle Enrollment (mark as enrolled when clicking external link)
if (isPost() && isset($_POST['enroll'])) {
    if (!$userId) {
        Session::flash('info', 'Please login to track your learning progress.');
        redirect('/jobmington/auth/login.php?redirect=' . urlencode('/learn/course.php?id=' . $courseId));
    }
    
    // For premium courses, redirect to checkout
    if (!$course['is_free'] && !$hasPurchased) {
        redirect('/jobmington/learn/checkout.php?course=' . $courseId);
    }
    
    if (!$isEnrolled && $hasAccess && Security::verifyCSRF()) {
        $stmt = $pdo->prepare("INSERT INTO course_enrollments (user_id, course_id) VALUES (?, ?)");
        $stmt->execute([$userId, $courseId]);
        
        // Update enrollment count
        $pdo->prepare("UPDATE courses SET enrollment_count = enrollment_count + 1 WHERE course_id = ?")->execute([$courseId]);
        
        $isEnrolled = true;
        
        // If external course, redirect to external URL
        if ($course['is_external'] && $course['external_url']) {
            redirect($course['external_url']);
        }
        
        Session::flash('success', 'Successfully enrolled! Start learning now.');
        redirect('/jobmington/learn/course.php?id=' . $courseId);
    }
}

// Helper function to get provider color
function getProviderColor($instructor) {
    $instructor = strtolower($instructor ?? '');
    if (strpos($instructor, 'google') !== false) return ['from-blue-500 to-green-500', 'bg-blue-500'];
    if (strpos($instructor, 'hubspot') !== false) return ['from-orange-500 to-red-500', 'bg-orange-500'];
    if (strpos($instructor, 'jobberman') !== false) return ['from-green-600 to-teal-500', 'bg-green-600'];
    if (strpos($instructor, 'career') !== false) return ['from-red-500 to-pink-500', 'bg-red-500'];
    if (strpos($instructor, 'kevin') !== false) return ['from-green-500 to-emerald-500', 'bg-green-500'];
    return ['from-primary to-blue-600', 'bg-primary'];
}

// Helper to convert YouTube URL to embed URL
function getYouTubeEmbedUrl($url) {
    $videoId = null;
    
    // Handle youtube.com/watch?v= format
    if (preg_match('/youtube\.com\/watch\?v=([a-zA-Z0-9_-]+)/', $url, $matches)) {
        $videoId = $matches[1];
    }
    // Handle youtu.be/ format
    elseif (preg_match('/youtu\.be\/([a-zA-Z0-9_-]+)/', $url, $matches)) {
        $videoId = $matches[1];
    }
    // Handle youtube.com/embed/ format (already embedded)
    elseif (preg_match('/youtube\.com\/embed\/([a-zA-Z0-9_-]+)/', $url, $matches)) {
        $videoId = $matches[1];
    }
    
    if ($videoId) {
        return 'https://www.youtube.com/embed/' . $videoId . '?rel=0&modestbranding=1';
    }
    
    return null;
}

// Check if URL is embeddable
function isEmbeddable($url) {
    if (strpos($url, 'youtube.com') !== false || strpos($url, 'youtu.be') !== false) {
        return 'youtube';
    }
    // Add more embeddable sources as needed
    return false;
}

$embedType = $course['is_external'] ? isEmbeddable($course['external_url'] ?? '') : false;
$embedUrl = $embedType === 'youtube' ? getYouTubeEmbedUrl($course['external_url']) : null;

// Helper to get course type badge
function getCourseTypeBadge($type) {
    $badges = [
        'video' => ['icon' => 'fa-play-circle', 'label' => 'Video Course', 'color' => 'bg-red-500'],
        'article' => ['icon' => 'fa-file-alt', 'label' => 'Article', 'color' => 'bg-blue-500'],
        'certification' => ['icon' => 'fa-certificate', 'label' => 'Certification', 'color' => 'bg-amber-500'],
        'full-course' => ['icon' => 'fa-graduation-cap', 'label' => 'Full Course', 'color' => 'bg-purple-500'],
        'guide' => ['icon' => 'fa-book-open', 'label' => 'Guide', 'color' => 'bg-teal-500'],
    ];
    return $badges[$type] ?? $badges['video'];
}

$colors = getProviderColor($course['instructor_name'] ?? '');
$typeBadge = getCourseTypeBadge($course['course_type'] ?? 'video');

$pageTitle = e($course['title']) . ' - ' . SITE_NAME;
$pageDescription = excerpt($course['short_description'] ?? $course['description'], 160);
require_once __DIR__ . '/../includes/header.php';
?>

<style>
    .hero-gradient {
        background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #334155 100%);
    }
    .glass-card {
        background: rgba(255, 255, 255, 0.05);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.1);
    }
    .glow-button {
        box-shadow: 0 0 30px rgba(251, 191, 36, 0.3);
    }
    .glow-button:hover {
        box-shadow: 0 0 50px rgba(251, 191, 36, 0.5);
    }
    @keyframes float {
        0%, 100% { transform: translateY(0); }
        50% { transform: translateY(-10px); }
    }
    .float-icon {
        animation: float 3s ease-in-out infinite;
    }
</style>

<!-- Hero Section -->
<div class="hero-gradient relative overflow-hidden">
    <!-- Background Pattern -->
    <div class="absolute inset-0 opacity-10">
        <div class="absolute top-0 left-0 w-96 h-96 bg-amber-500 rounded-full filter blur-3xl -translate-x-1/2 -translate-y-1/2"></div>
        <div class="absolute bottom-0 right-0 w-96 h-96 bg-blue-500 rounded-full filter blur-3xl translate-x-1/2 translate-y-1/2"></div>
    </div>
    
    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 lg:py-20">
        <!-- Breadcrumb -->
        <nav class="flex items-center gap-2 text-sm text-slate-400 mb-8">
            <a href="/jobmington/" class="hover:text-white transition">Home</a>
            <i class="fas fa-chevron-right text-xs"></i>
            <a href="/jobmington/learn" class="hover:text-white transition">Academy</a>
            <i class="fas fa-chevron-right text-xs"></i>
            <span class="text-slate-300"><?= e($course['category_name'] ?? 'Course') ?></span>
        </nav>
        
        <div class="grid lg:grid-cols-2 gap-12 items-center">
            <!-- Left: Course Info -->
            <div>
                <!-- Badges -->
                <div class="flex flex-wrap items-center gap-3 mb-6">
                    <?php if ($course['is_free']): ?>
                    <span class="inline-flex items-center gap-1.5 bg-green-500/20 text-green-400 text-sm font-bold px-4 py-2 rounded-full border border-green-500/30">
                        <i class="fas fa-gift"></i> FREE
                    </span>
                    <?php else: ?>
                    <span class="inline-flex items-center gap-1.5 bg-amber-500/20 text-amber-400 text-sm font-bold px-4 py-2 rounded-full border border-amber-500/30">
                        <i class="fas fa-crown"></i> PREMIUM
                    </span>
                    <?php if ($hasPurchased): ?>
                    <span class="inline-flex items-center gap-1.5 bg-green-500/20 text-green-400 text-sm font-bold px-4 py-2 rounded-full border border-green-500/30">
                        <i class="fas fa-unlock"></i> UNLOCKED
                    </span>
                    <?php endif; ?>
                    <?php endif; ?>
                    
                    <span class="inline-flex items-center gap-1.5 <?= $typeBadge['color'] ?>/20 text-white text-sm font-medium px-4 py-2 rounded-full border border-white/20">
                        <i class="fas <?= $typeBadge['icon'] ?>"></i> <?= $typeBadge['label'] ?>
                    </span>
                    
                    <?php if ($course['has_certificate']): ?>
                    <span class="inline-flex items-center gap-1.5 bg-amber-500/20 text-amber-400 text-sm font-bold px-4 py-2 rounded-full border border-amber-500/30">
                        <i class="fas fa-award"></i> Certificate Included
                    </span>
                    <?php endif; ?>
                </div>
                
                <!-- Title -->
                <h1 class="text-3xl md:text-4xl lg:text-5xl font-bold text-white leading-tight mb-6">
                    <?= e($course['title']) ?>
                </h1>
                
                <!-- Short Description -->
                <p class="text-lg text-slate-300 mb-8 leading-relaxed">
                    <?= e($course['short_description'] ?? excerpt(strip_tags($course['description']), 200)) ?>
                </p>
                
                <!-- Meta Info -->
                <div class="flex flex-wrap items-center gap-6 text-slate-400 mb-8">
                    <?php if ($course['instructor_name']): ?>
                    <div class="flex items-center gap-2">
                        <div class="w-10 h-10 <?= $colors[1] ?> rounded-full flex items-center justify-center text-white">
                            <i class="fas fa-chalkboard-teacher"></i>
                        </div>
                        <div>
                            <p class="text-xs text-slate-500">Instructor</p>
                            <p class="text-white font-medium"><?= e($course['instructor_name']) ?></p>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($course['duration_hours'] > 0): ?>
                    <div class="flex items-center gap-2">
                        <div class="w-10 h-10 bg-slate-700 rounded-full flex items-center justify-center text-slate-300">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div>
                            <p class="text-xs text-slate-500">Duration</p>
                            <p class="text-white font-medium">
                                <?php if ($course['duration_hours'] >= 1): ?>
                                    <?= $course['duration_hours'] ?> hour<?= $course['duration_hours'] > 1 ? 's' : '' ?>
                                <?php else: ?>
                                    <?= round($course['duration_hours'] * 60) ?> minutes
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="flex items-center gap-2">
                        <div class="w-10 h-10 bg-slate-700 rounded-full flex items-center justify-center text-slate-300">
                            <i class="fas fa-users"></i>
                        </div>
                        <div>
                            <p class="text-xs text-slate-500">Enrolled</p>
                            <p class="text-white font-medium"><?= number_format($course['enrollment_count']) ?>+ learners</p>
                        </div>
                    </div>
                    
                    <div class="flex items-center gap-2">
                        <div class="w-10 h-10 bg-slate-700 rounded-full flex items-center justify-center text-slate-300">
                            <i class="fas fa-signal"></i>
                        </div>
                        <div>
                            <p class="text-xs text-slate-500">Level</p>
                            <p class="text-white font-medium capitalize"><?= e($course['difficulty'] ?? 'Beginner') ?></p>
                        </div>
                    </div>
                </div>
                
                <!-- CTA Buttons -->
                <div class="flex flex-wrap gap-4">
                    <?php if ($course['is_external'] && $course['external_url']): ?>
                        <!-- External Course -->
                        <?php if ($embedUrl): ?>
                            <!-- Video is embedded above - show scroll hint or mark as complete -->
                            <span class="inline-flex items-center gap-2 text-green-400 font-medium bg-green-500/20 px-6 py-3 rounded-xl">
                                <i class="fas fa-play-circle"></i> Video ready to watch above ↑
                            </span>
                            <?php if (!$isEnrolled && $userId): ?>
                            <form method="POST" class="inline">
                                <?= csrf_field() ?>
                                <button type="submit" name="enroll" 
                                        class="inline-flex items-center gap-2 bg-slate-700 hover:bg-slate-600 text-white font-medium px-6 py-3 rounded-xl transition">
                                    <i class="fas fa-bookmark"></i> Save to My Courses
                                </button>
                            </form>
                            <?php endif; ?>
                        <?php elseif ($isEnrolled): ?>
                            <a href="<?= e($course['external_url']) ?>" 
                               target="_blank" 
                               rel="noopener"
                               class="inline-flex items-center gap-3 bg-gradient-to-r from-amber-500 to-orange-500 hover:from-amber-400 hover:to-orange-400 text-slate-900 font-bold text-lg px-8 py-4 rounded-xl transition-all glow-button">
                                <i class="fas fa-external-link-alt"></i>
                                Open Course
                            </a>
                        <?php elseif (!$hasAccess): ?>
                            <!-- Premium Course - Show Unlock Button -->
                            <a href="/jobmington/learn/checkout.php?course=<?= $courseId ?>" 
                               class="inline-flex items-center gap-3 bg-gradient-to-r from-amber-500 to-orange-500 hover:from-amber-400 hover:to-orange-400 text-slate-900 font-bold text-lg px-8 py-4 rounded-xl transition-all glow-button">
                                <i class="fas fa-unlock"></i>
                                Unlock for ₦<?= number_format($course['price']) ?>
                            </a>
                            <?php if ($course['seed_price'] > 0): ?>
                            <a href="/jobmington/learn/checkout.php?course=<?= $courseId ?>&method=seeds" 
                               class="inline-flex items-center gap-3 bg-gradient-to-r from-green-500 to-emerald-500 hover:from-green-400 hover:to-emerald-400 text-white font-bold text-lg px-8 py-4 rounded-xl transition-all">
                                <i class="fas fa-seedling"></i>
                                Or <?= number_format($course['seed_price']) ?> Seeds
                            </a>
                            <?php endif; ?>
                        <?php else: ?>
                            <form method="POST" class="inline">
                                <?= csrf_field() ?>
                                <button type="submit" name="enroll" 
                                        class="inline-flex items-center gap-3 bg-gradient-to-r from-amber-500 to-orange-500 hover:from-amber-400 hover:to-orange-400 text-slate-900 font-bold text-lg px-8 py-4 rounded-xl transition-all glow-button">
                                    <i class="fas fa-rocket"></i>
                                    Start Free Course
                                </button>
                            </form>
                        <?php endif; ?>
                    <?php else: ?>
                        <!-- Internal Course -->
                        <?php if ($isEnrolled && !empty($modules)): ?>
                            <a href="/jobmington/learn/module.php?id=<?= $modules[0]['module_id'] ?>" 
                               class="inline-flex items-center gap-3 bg-gradient-to-r from-amber-500 to-orange-500 hover:from-amber-400 hover:to-orange-400 text-slate-900 font-bold text-lg px-8 py-4 rounded-xl transition-all glow-button">
                                <i class="fas fa-play"></i>
                                Continue Learning
                            </a>
                        <?php elseif (!$isEnrolled): ?>
                            <?php if (!$hasAccess): ?>
                                <!-- Premium Course - Show Unlock Button -->
                                <a href="/jobmington/learn/checkout.php?course=<?= $courseId ?>" 
                                   class="inline-flex items-center gap-3 bg-gradient-to-r from-amber-500 to-orange-500 hover:from-amber-400 hover:to-orange-400 text-slate-900 font-bold text-lg px-8 py-4 rounded-xl transition-all glow-button">
                                    <i class="fas fa-unlock"></i>
                                    Unlock for ₦<?= number_format($course['price']) ?>
                                </a>
                                <?php if ($course['seed_price'] > 0): ?>
                                <a href="/jobmington/learn/checkout.php?course=<?= $courseId ?>&method=seeds" 
                                   class="inline-flex items-center gap-3 bg-gradient-to-r from-green-500 to-emerald-500 hover:from-green-400 hover:to-emerald-400 text-white font-bold text-lg px-8 py-4 rounded-xl transition-all">
                                    <i class="fas fa-seedling"></i>
                                    Or <?= number_format($course['seed_price']) ?> Seeds
                                </a>
                                <?php endif; ?>
                            <?php else: ?>
                            <form method="POST" class="inline">
                                <?= csrf_field() ?>
                                <button type="submit" name="enroll" 
                                        class="inline-flex items-center gap-3 bg-gradient-to-r from-amber-500 to-orange-500 hover:from-amber-400 hover:to-orange-400 text-slate-900 font-bold text-lg px-8 py-4 rounded-xl transition-all glow-button">
                                    <i class="fas fa-graduation-cap"></i>
                                    Enroll Now - It's Free
                                </button>
                            </form>
                            <?php endif; ?>
                        <?php endif; ?>
                    <?php endif; ?>
                    
                    <?php if ($isEnrolled): ?>
                    <span class="inline-flex items-center gap-2 text-green-400 font-medium">
                        <i class="fas fa-check-circle"></i> You're enrolled
                    </span>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Right: Course Thumbnail / Preview -->
            <div class="relative">
                <div class="glass-card rounded-2xl overflow-hidden shadow-2xl">
                    <?php if ($embedUrl): ?>
                        <!-- Embedded YouTube Video -->
                        <div class="aspect-video">
                            <iframe 
                                src="<?= e($embedUrl) ?>" 
                                class="w-full h-full"
                                frameborder="0" 
                                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
                                allowfullscreen>
                            </iframe>
                        </div>
                    <?php elseif ($course['thumbnail'] && strpos($course['thumbnail'], 'http') === 0): ?>
                        <img src="<?= e($course['thumbnail']) ?>" 
                             alt="<?= e($course['title']) ?>" 
                             class="w-full aspect-video object-cover">
                    <?php elseif ($course['thumbnail']): ?>
                        <img src="<?= upload('course-thumbnails/' . $course['thumbnail']) ?>" 
                             alt="<?= e($course['title']) ?>" 
                             class="w-full aspect-video object-cover">
                    <?php else: ?>
                        <div class="w-full aspect-video bg-gradient-to-br <?= $colors[0] ?> flex items-center justify-center">
                            <div class="text-center">
                                <i class="fas <?= $typeBadge['icon'] ?> text-white/30 text-8xl float-icon"></i>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Play overlay for video courses (only if not embedded) -->
                    <?php if (!$embedUrl && ($course['course_type'] ?? '') === 'video' && $course['is_external'] && $course['external_url']): ?>
                    <a href="<?= e($course['external_url']) ?>" 
                       target="_blank"
                       class="absolute inset-0 flex items-center justify-center bg-black/30 hover:bg-black/40 transition group">
                        <div class="w-20 h-20 bg-white/90 rounded-full flex items-center justify-center shadow-xl transform group-hover:scale-110 transition">
                            <i class="fas fa-play text-red-500 text-2xl ml-1"></i>
                        </div>
                    </a>
                    <?php endif; ?>
                </div>
                
                <!-- Provider Badge -->
                <?php if ($course['instructor_name']): ?>
                <div class="absolute -bottom-4 -right-4 glass-card rounded-xl p-4 shadow-xl">
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 <?= $colors[1] ?> rounded-lg flex items-center justify-center text-white">
                            <?php if (stripos($course['instructor_name'], 'google') !== false): ?>
                                <i class="fab fa-google text-xl"></i>
                            <?php elseif (stripos($course['instructor_name'], 'hubspot') !== false): ?>
                                <i class="fab fa-hubspot text-xl"></i>
                            <?php elseif (stripos($course['instructor_name'], 'youtube') !== false || stripos($course['instructor_name'], 'career') !== false || stripos($course['instructor_name'], 'kevin') !== false): ?>
                                <i class="fab fa-youtube text-xl"></i>
                            <?php else: ?>
                                <i class="fas fa-building text-xl"></i>
                            <?php endif; ?>
                        </div>
                        <div>
                            <p class="text-xs text-slate-400">Provided by</p>
                            <p class="text-white font-bold"><?= e(explode('(', $course['instructor_name'])[0]) ?></p>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Course Details Section -->
<div class="bg-slate-50 py-16">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid lg:grid-cols-3 gap-12">
            
            <!-- Main Content -->
            <div class="lg:col-span-2 space-y-10">
                
                <!-- About This Course -->
                <div class="bg-white rounded-2xl shadow-sm p-8">
                    <h2 class="text-2xl font-bold text-slate-900 mb-6 flex items-center gap-3">
                        <span class="w-10 h-10 bg-primary/10 rounded-lg flex items-center justify-center">
                            <i class="fas fa-info-circle text-primary"></i>
                        </span>
                        About This Course
                    </h2>
                    <div class="prose prose-lg max-w-none text-slate-600">
                        <?= $course['description'] ?>
                    </div>
                </div>
                
                <!-- What You'll Learn (if has modules or is external) -->
                <?php if ($course['is_external']): ?>
                <div class="bg-white rounded-2xl shadow-sm p-8">
                    <h2 class="text-2xl font-bold text-slate-900 mb-6 flex items-center gap-3">
                        <span class="w-10 h-10 bg-green-500/10 rounded-lg flex items-center justify-center">
                            <i class="fas fa-check-circle text-green-500"></i>
                        </span>
                        Why We Recommend This
                    </h2>
                    <div class="grid sm:grid-cols-2 gap-4">
                        <div class="flex items-start gap-3 p-4 bg-slate-50 rounded-xl">
                            <i class="fas fa-check text-green-500 mt-1"></i>
                            <span class="text-slate-700">Curated by Jobmington experts</span>
                        </div>
                        <div class="flex items-start gap-3 p-4 bg-slate-50 rounded-xl">
                            <i class="fas fa-check text-green-500 mt-1"></i>
                            <span class="text-slate-700"><?= $course['is_free'] ? '100% free, no hidden costs' : 'One-time payment, lifetime access' ?></span>
                        </div>
                        <div class="flex items-start gap-3 p-4 bg-slate-50 rounded-xl">
                            <i class="fas fa-check text-green-500 mt-1"></i>
                            <span class="text-slate-700">Practical, job-ready skills</span>
                        </div>
                        <div class="flex items-start gap-3 p-4 bg-slate-50 rounded-xl">
                            <i class="fas fa-check text-green-500 mt-1"></i>
                            <span class="text-slate-700">Learn at your own pace</span>
                        </div>
                        <?php if ($course['has_certificate']): ?>
                        <div class="flex items-start gap-3 p-4 bg-amber-50 rounded-xl sm:col-span-2">
                            <i class="fas fa-award text-amber-500 mt-1"></i>
                            <span class="text-slate-700"><strong>Bonus:</strong> Earn an official certificate from <?= e($course['certificate_provider'] ?? $course['instructor_name']) ?> to add to your CV!</span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Course Curriculum (for internal courses) -->
                <?php if (!$course['is_external'] && !empty($modules)): ?>
                <div class="bg-white rounded-2xl shadow-sm overflow-hidden">
                    <div class="p-8 border-b border-slate-100">
                        <h2 class="text-2xl font-bold text-slate-900 flex items-center gap-3">
                            <span class="w-10 h-10 bg-purple-500/10 rounded-lg flex items-center justify-center">
                                <i class="fas fa-list-ol text-purple-500"></i>
                            </span>
                            Course Curriculum
                        </h2>
                        <p class="text-slate-500 mt-2"><?= count($modules) ?> lessons</p>
                    </div>
                    
                    <div class="divide-y divide-slate-100">
                        <?php foreach ($modules as $index => $module): ?>
                        <div class="p-5 hover:bg-slate-50 transition flex items-center gap-4">
                            <div class="w-12 h-12 bg-primary/10 rounded-xl flex items-center justify-center text-primary font-bold flex-shrink-0">
                                <?= $index + 1 ?>
                            </div>
                            <div class="flex-1">
                                <h4 class="font-semibold text-slate-900"><?= e($module['title']) ?></h4>
                                <div class="flex items-center gap-4 text-sm text-slate-500 mt-1">
                                    <?php if ($module['video_url']): ?>
                                        <span><i class="fas fa-play-circle mr-1"></i> Video</span>
                                    <?php else: ?>
                                        <span><i class="fas fa-file-alt mr-1"></i> Reading</span>
                                    <?php endif; ?>
                                    <?php if ($module['duration_minutes']): ?>
                                        <span><i class="fas fa-clock mr-1"></i> <?= $module['duration_minutes'] ?> min</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <?php if ($isEnrolled): ?>
                                <a href="/jobmington/learn/module.php?id=<?= $module['module_id'] ?>" 
                                   class="w-10 h-10 bg-primary/10 hover:bg-primary hover:text-white rounded-lg flex items-center justify-center text-primary transition">
                                    <i class="fas fa-play"></i>
                                </a>
                            <?php else: ?>
                                <div class="w-10 h-10 bg-slate-100 rounded-lg flex items-center justify-center text-slate-400">
                                    <i class="fas fa-lock"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                        
                        <?php if ($quiz): ?>
                        <div class="p-5 bg-amber-50 flex items-center gap-4">
                            <div class="w-12 h-12 bg-amber-500 rounded-xl flex items-center justify-center text-white flex-shrink-0">
                                <i class="fas fa-trophy"></i>
                            </div>
                            <div class="flex-1">
                                <h4 class="font-semibold text-slate-900">Final Assessment</h4>
                                <p class="text-sm text-slate-500">Pass with <?= $quiz['passing_score'] ?>% to earn your certificate</p>
                            </div>
                            <?php if ($isEnrolled): ?>
                                <a href="/jobmington/learn/quiz.php?id=<?= $quiz['quiz_id'] ?>" 
                                   class="w-10 h-10 bg-amber-500 hover:bg-amber-600 rounded-lg flex items-center justify-center text-white transition">
                                    <i class="fas fa-arrow-right"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Tags -->
                <?php if ($course['tags']): ?>
                <div class="flex flex-wrap gap-2">
                    <?php foreach (explode(',', $course['tags']) as $tag): ?>
                    <span class="inline-flex items-center gap-1 bg-slate-200 text-slate-600 text-sm px-3 py-1 rounded-full">
                        <i class="fas fa-tag text-xs"></i> <?= e(trim($tag)) ?>
                    </span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Sidebar -->
            <div class="space-y-6">
                
                <!-- Quick Action Card -->
                <div class="bg-white rounded-2xl shadow-sm p-6 sticky top-24">
                    <!-- Price -->
                    <div class="text-center mb-6 pb-6 border-b border-slate-100">
                        <?php if ($course['is_free']): ?>
                        <div class="inline-flex items-center gap-2 bg-green-500/10 text-green-600 text-3xl font-bold px-6 py-3 rounded-xl">
                            <i class="fas fa-gift"></i> FREE
                        </div>
                        <?php elseif ($hasPurchased): ?>
                        <div class="inline-flex items-center gap-2 bg-green-500/10 text-green-600 text-2xl font-bold px-6 py-3 rounded-xl">
                            <i class="fas fa-unlock"></i> UNLOCKED
                        </div>
                        <?php else: ?>
                        <div class="text-4xl font-bold text-slate-900">
                            ₦<?= number_format($course['price'] ?? 0) ?>
                        </div>
                        <?php if ($course['seed_price'] > 0): ?>
                        <div class="text-lg text-green-600 font-medium mt-2">
                            or <?= number_format($course['seed_price']) ?> <i class="fas fa-seedling"></i> Seeds
                        </div>
                        <?php endif; ?>
                        <?php endif; ?>
                        <p class="text-slate-500 text-sm mt-2">Lifetime access</p>
                    </div>
                    
                    <!-- CTA -->
                    <?php if ($course['is_external'] && $course['external_url']): ?>
                        <?php if ($embedUrl): ?>
                            <!-- Embedded video - show completion/save option -->
                            <div class="bg-green-50 border border-green-200 rounded-xl p-4 text-center mb-4">
                                <i class="fas fa-play-circle text-green-500 text-2xl mb-2"></i>
                                <p class="text-green-700 font-bold">Watch Above</p>
                                <p class="text-green-600 text-sm">Video is embedded on this page</p>
                            </div>
                            <?php if (!$isEnrolled && $userId): ?>
                            <form method="POST">
                                <?= csrf_field() ?>
                                <button type="submit" name="enroll" 
                                        class="w-full bg-slate-700 hover:bg-slate-600 text-white font-bold py-4 rounded-xl transition-all shadow-lg mb-2">
                                    <i class="fas fa-bookmark mr-2"></i> Save to My Courses
                                </button>
                            </form>
                            <?php elseif ($isEnrolled): ?>
                            <div class="text-center text-slate-500 text-sm">
                                <i class="fas fa-check-circle text-green-500 mr-1"></i> Saved to your courses
                            </div>
                            <?php endif; ?>
                            
                            <!-- Link to full resource -->
                            <a href="<?= e($course['external_url']) ?>" 
                               target="_blank" 
                               rel="noopener"
                               class="block w-full text-center text-slate-500 hover:text-primary text-sm mt-4 py-2">
                                <i class="fas fa-external-link-alt mr-1"></i>
                                Open on <?= e(explode(' ', $course['instructor_name'])[0]) ?>
                            </a>
                        <?php else: ?>
                            <!-- Non-embeddable external course -->
                            <?php if (!$hasAccess): ?>
                                <a href="/jobmington/learn/checkout.php?course=<?= $courseId ?>" 
                                   class="block w-full bg-gradient-to-r from-amber-500 to-orange-500 hover:from-amber-400 hover:to-orange-400 text-slate-900 font-bold text-center py-4 rounded-xl transition-all shadow-lg mb-4">
                                    <i class="fas fa-unlock mr-2"></i>
                                    Unlock for ₦<?= number_format($course['price']) ?>
                                </a>
                                <?php if ($course['seed_price'] > 0): ?>
                                <a href="/jobmington/learn/checkout.php?course=<?= $courseId ?>&method=seeds" 
                                   class="block w-full bg-gradient-to-r from-green-500 to-emerald-500 hover:from-green-400 hover:to-emerald-400 text-white font-bold text-center py-3 rounded-xl transition-all mb-4">
                                    <i class="fas fa-seedling mr-2"></i>
                                    Or <?= number_format($course['seed_price']) ?> Seeds
                                </a>
                                <?php endif; ?>
                            <?php else: ?>
                            <a href="<?= e($course['external_url']) ?>" 
                               target="_blank" 
                               rel="noopener"
                               class="block w-full bg-gradient-to-r from-amber-500 to-orange-500 hover:from-amber-400 hover:to-orange-400 text-slate-900 font-bold text-center py-4 rounded-xl transition-all shadow-lg mb-4">
                                <i class="fas fa-external-link-alt mr-2"></i>
                                <?= $isEnrolled ? 'Continue on ' . explode(' ', $course['instructor_name'])[0] : 'Start Free Course' ?>
                            </a>
                            <?php endif; ?>
                            <p class="text-center text-slate-500 text-sm">
                                <i class="fas fa-info-circle mr-1"></i>
                                Opens on <?= e(explode(' ', $course['instructor_name'])[0]) ?>
                            </p>
                        <?php endif; ?>
                    <?php elseif (!$isEnrolled): ?>
                        <?php if (!$hasAccess): ?>
                        <a href="/jobmington/learn/checkout.php?course=<?= $courseId ?>" 
                           class="block w-full bg-gradient-to-r from-amber-500 to-orange-500 hover:from-amber-400 hover:to-orange-400 text-slate-900 font-bold text-center py-4 rounded-xl transition-all shadow-lg mb-3">
                            <i class="fas fa-unlock mr-2"></i> Unlock for ₦<?= number_format($course['price']) ?>
                        </a>
                        <?php if ($course['seed_price'] > 0): ?>
                        <a href="/jobmington/learn/checkout.php?course=<?= $courseId ?>&method=seeds" 
                           class="block w-full bg-gradient-to-r from-green-500 to-emerald-500 hover:from-green-400 hover:to-emerald-400 text-white font-bold text-center py-3 rounded-xl transition-all">
                            <i class="fas fa-seedling mr-2"></i> Or <?= number_format($course['seed_price']) ?> Seeds
                        </a>
                        <?php endif; ?>
                        <?php else: ?>
                        <form method="POST">
                            <?= csrf_field() ?>
                            <button type="submit" name="enroll" 
                                    class="w-full bg-gradient-to-r from-amber-500 to-orange-500 hover:from-amber-400 hover:to-orange-400 text-slate-900 font-bold py-4 rounded-xl transition-all shadow-lg">
                                <i class="fas fa-graduation-cap mr-2"></i> Enroll Now
                            </button>
                        </form>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="bg-green-50 border border-green-200 rounded-xl p-4 text-center mb-4">
                            <i class="fas fa-check-circle text-green-500 text-2xl mb-2"></i>
                            <p class="text-green-700 font-bold">You're Enrolled!</p>
                        </div>
                        <?php if (!empty($modules)): ?>
                        <a href="/jobmington/learn/module.php?id=<?= $modules[0]['module_id'] ?>" 
                           class="block w-full bg-primary hover:bg-blue-700 text-white font-bold text-center py-4 rounded-xl transition">
                            <i class="fas fa-play mr-2"></i> Continue Learning
                        </a>
                        <?php endif; ?>
                    <?php endif; ?>
                    
                    <!-- Course Includes -->
                    <div class="mt-6 pt-6 border-t border-slate-100">
                        <h4 class="font-bold text-slate-900 mb-4">This course includes:</h4>
                        <ul class="space-y-3">
                            <?php if ($course['duration_hours'] > 0): ?>
                            <li class="flex items-center gap-3 text-slate-600">
                                <span class="w-8 h-8 bg-slate-100 rounded-lg flex items-center justify-center">
                                    <i class="fas fa-clock text-primary text-sm"></i>
                                </span>
                                <?php if ($course['duration_hours'] >= 1): ?>
                                    <?= $course['duration_hours'] ?> hour<?= $course['duration_hours'] > 1 ? 's' : '' ?> of content
                                <?php else: ?>
                                    <?= round($course['duration_hours'] * 60) ?> minutes
                                <?php endif; ?>
                            </li>
                            <?php endif; ?>
                            
                            <li class="flex items-center gap-3 text-slate-600">
                                <span class="w-8 h-8 bg-slate-100 rounded-lg flex items-center justify-center">
                                    <i class="fas fa-infinity text-primary text-sm"></i>
                                </span>
                                Full lifetime access
                            </li>
                            
                            <li class="flex items-center gap-3 text-slate-600">
                                <span class="w-8 h-8 bg-slate-100 rounded-lg flex items-center justify-center">
                                    <i class="fas fa-mobile-alt text-primary text-sm"></i>
                                </span>
                                Access on any device
                            </li>
                            
                            <?php if ($course['has_certificate']): ?>
                            <li class="flex items-center gap-3 text-amber-600 font-medium">
                                <span class="w-8 h-8 bg-amber-100 rounded-lg flex items-center justify-center">
                                    <i class="fas fa-certificate text-amber-500 text-sm"></i>
                                </span>
                                Official certificate
                            </li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
                
                <!-- Share Card -->
                <div class="bg-white rounded-2xl shadow-sm p-6">
                    <h4 class="font-bold text-slate-900 mb-4">Share this course</h4>
                    <div class="flex gap-3">
                        <a href="https://twitter.com/intent/tweet?url=<?= urlencode(currentUrl()) ?>&text=<?= urlencode('Check out this free course: ' . $course['title']) ?>" 
                           target="_blank" 
                           class="w-10 h-10 bg-slate-100 hover:bg-sky-500 hover:text-white rounded-lg flex items-center justify-center transition">
                            <i class="fab fa-twitter"></i>
                        </a>
                        <a href="https://www.facebook.com/sharer/sharer.php?u=<?= urlencode(currentUrl()) ?>" 
                           target="_blank" 
                           class="w-10 h-10 bg-slate-100 hover:bg-blue-600 hover:text-white rounded-lg flex items-center justify-center transition">
                            <i class="fab fa-facebook-f"></i>
                        </a>
                        <a href="https://www.linkedin.com/sharing/share-offsite/?url=<?= urlencode(currentUrl()) ?>" 
                           target="_blank" 
                           class="w-10 h-10 bg-slate-100 hover:bg-blue-700 hover:text-white rounded-lg flex items-center justify-center transition">
                            <i class="fab fa-linkedin-in"></i>
                        </a>
                        <a href="https://wa.me/?text=<?= urlencode('Check out this free course: ' . $course['title'] . ' ' . currentUrl()) ?>" 
                           target="_blank" 
                           class="w-10 h-10 bg-slate-100 hover:bg-green-500 hover:text-white rounded-lg flex items-center justify-center transition">
                            <i class="fab fa-whatsapp"></i>
                        </a>
                        <button onclick="navigator.clipboard.writeText('<?= currentUrl() ?>'); JM.toast('Link copied to clipboard!', 'success');" 
                                class="w-10 h-10 bg-slate-100 hover:bg-slate-700 hover:text-white rounded-lg flex items-center justify-center transition">
                            <i class="fas fa-link"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
