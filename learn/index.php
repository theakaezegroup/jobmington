<?php
require_once __DIR__ . '/_disabled.php';

/**
 * JOBMINGTON - Learning Academy v7.3 (Final Fix)
 * Features:
 * - Stats Bar: Manual borders used to perfectly control lines on mobile vs desktop.
 * - Search Bar: Locked background & spacious padding.
 * - Cinematic Cards: Full design preserved.
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

// Get Categories
$categories = $pdo->query("SELECT * FROM course_categories ORDER BY name")->fetchAll();

// Filters
$filterCategory = Security::clean(get('category', ''));
$filterSearch = Security::clean(get('q', ''));

// Build Query
$sql = "SELECT c.*, cc.name as category_name, 
        (SELECT COUNT(*) FROM course_modules WHERE course_id = c.course_id) as module_count
        FROM courses c
        LEFT JOIN course_categories cc ON c.category_id = cc.id
        WHERE c.is_published = 1";
$params = [];

if ($filterCategory) {
    $sql .= " AND cc.slug = ?";
    $params[] = $filterCategory;
}

if ($filterSearch) {
    $sql .= " AND (c.title LIKE ? OR c.description LIKE ?)";
    $params[] = "%$filterSearch%";
    $params[] = "%$filterSearch%";
}

$sql .= " ORDER BY c.enrollment_count DESC, c.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$courses = $stmt->fetchAll();

// Stats
$totalCourses = count($courses);
$totalEnrollments = array_sum(array_column($courses, 'enrollment_count'));

$pageTitle = 'Learning Academy - ' . SITE_NAME;
$pageDescription = 'Master in-demand skills with Jobmington Academy.';
require_once __DIR__ . '/../includes/header.php';
?>

<style>
    /* --- ANIMATIONS --- */
    @keyframes float-y { 0%, 100% { transform: translateY(0px); } 50% { transform: translateY(-20px); } }
    .animate-float { animation: float-y 6s ease-in-out infinite; }
    
    /* --- HERO MESH --- */
    .bg-academy-hero {
        background-color: #0f172a;
        background-image: 
            radial-gradient(at 0% 0%, hsla(253,16%,7%,1) 0, transparent 50%), 
            radial-gradient(at 50% 100%, hsla(225,39%,30%,1) 0, transparent 50%), 
            radial-gradient(at 100% 0%, hsla(339,49%,30%,1) 0, transparent 50%);
    }

    /* --- GLASS PILLS --- */
    .glass-pill {
        background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); color: #e2e8f0; backdrop-filter: blur(10px);
    }
    .glass-pill:hover {
        background: rgba(255, 255, 255, 0.1);
    }
    .glass-pill.active {
        background: rgba(255, 255, 255, 0.15); border-color: #f59e0b; color: white; box-shadow: 0 0 15px rgba(245, 158, 11, 0.3);
    }

    /* --- CINEMATIC CARD --- */
    .cinematic-card {
        isolation: isolate;
        transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .cinematic-card:hover {
        transform: translateY(-10px) scale(1.02);
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
    }
    .cinematic-card:hover .card-overlay {
        opacity: 1;
        background: linear-gradient(to top, rgba(15, 23, 42, 0.95) 0%, rgba(15, 23, 42, 0.6) 50%, rgba(15, 23, 42, 0.3) 100%);
    }
    .cinematic-card:hover .card-content { transform: translateY(0); }
    .cinematic-card .card-content {
        transform: translateY(20px);
        transition: transform 0.5s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .play-btn {
        transition: all 0.3s ease;
        opacity: 0; transform: scale(0.5);
    }
    .cinematic-card:hover .play-btn {
        opacity: 1; transform: scale(1);
    }
</style>

<div class="relative bg-academy-hero pt-24 pb-20 overflow-hidden">
    <div class="absolute top-0 left-1/4 w-[800px] h-[800px] bg-blue-600/10 rounded-full blur-[120px] animate-pulse"></div>
    <div class="absolute bottom-0 right-1/4 w-[600px] h-[600px] bg-cyan-600/10 rounded-full blur-[120px] animate-pulse" style="animation-delay: 2s"></div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10 text-center">
        
        <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-white/5 border border-white/10 mb-8 backdrop-blur-md animate-float">
            <span class="relative flex h-2 w-2">
              <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-slate-400 opacity-75"></span>
              <span class="relative inline-flex rounded-full h-2 w-2 bg-slate-300"></span>
            </span>
            <span class="text-xs font-bold text-slate-200 tracking-widest uppercase">Jobmington Academy</span>
        </div>
        
        <h1 class="text-5xl md:text-7xl font-heading font-extrabold text-white mb-6 tracking-tight leading-[1.1]">
            Master the 
            <span class="text-transparent bg-clip-text bg-gradient-to-r from-yellow-400 via-amber-200 to-orange-400">Future of Work</span>
        </h1>
        
        <p class="text-xl text-slate-300 mb-14 max-w-2xl mx-auto leading-relaxed font-light">
            Unlock your potential with industry-verified courses. Learn, pass assessments, and watch your <span class="text-white font-medium border-b border-white/20">Digital Wallet</span> value grow.
        </p>
        
        <form action="" method="GET" class="max-w-2xl mx-auto relative group z-30">
            <div class="absolute -inset-1.5 bg-gradient-to-r from-blue-500 via-cyan-500 to-blue-500 rounded-full blur-xl opacity-30 group-hover:opacity-60 transition duration-700 animate-pulse"></div>
            
            <div class="relative flex items-center bg-slate-900 border border-white/10 rounded-full p-2 shadow-2xl ring-1 ring-white/5 transition-transform group-hover:scale-[1.01]">
                
                <i class="fas fa-search absolute left-6 text-slate-400 text-xl z-10 group-focus-within:text-slate-300 transition-colors"></i>
                
                <input type="text" name="q" value="<?= e($filterSearch) ?>" 
                       placeholder="What skill do you want to learn?" 
                       class="w-full pl-16 pr-6 py-4 bg-transparent border-none text-white placeholder-slate-500 focus:ring-0 text-lg font-medium rounded-full">
                
                <button type="submit" class="bg-gradient-to-r from-white to-slate-200 text-slate-900 px-10 h-14 rounded-full font-bold shadow-lg hover:shadow-white/20 hover:scale-105 transition-all duration-300">
                    Explore
                </button>
            </div>
        </form>

        <div class="absolute top-20 left-10 hidden lg:block animate-float delay-100 opacity-40">
            <i class="fas fa-code text-6xl text-white/5 rotate-12"></i>
        </div>
        <div class="absolute bottom-20 right-10 hidden lg:block animate-float delay-200 opacity-40">
            <i class="fas fa-layer-group text-6xl text-white/5 -rotate-12"></i>
        </div>
    </div>
</div>

<div class="relative z-20 -mt-12 px-4">
    <div class="max-w-5xl mx-auto bg-slate-900/90 backdrop-blur-xl border border-white/10 rounded-full shadow-xl p-4 md:px-8">
        
        <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
            
            <div class="text-center px-2">
                <div class="text-2xl font-black text-white mb-0.5"><?= $totalCourses ?></div>
                <div class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Courses</div>
            </div>

            <div class="text-center px-2 border-l border-white/10">
                <div class="text-2xl font-black text-white mb-0.5"><?= number_format($totalEnrollments) ?>+</div>
                <div class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Learners</div>
            </div>

            <div class="text-center px-2 border-l-0 md:border-l border-white/10">
                <div class="text-2xl font-black text-white mb-0.5">24/7</div>
                <div class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Access</div>
            </div>

            <div class="text-center px-2 border-l border-white/10">
                <div class="text-2xl font-black text-white mb-0.5">Free+</div>
                <div class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Premium</div>
            </div>

        </div>
    </div>
</div>

<div class="relative bg-slate-50 dark:bg-slate-900 min-h-screen py-16 transition-colors duration-300 overflow-hidden">
    <!-- Background Orbs (Matching Footer) -->
    <div class="absolute top-0 left-1/4 w-96 h-96 bg-slate-600/5 rounded-full blur-3xl pointer-events-none"></div>
    <div class="absolute bottom-0 right-0 w-[500px] h-[500px] bg-slate-600/5 rounded-full blur-3xl pointer-events-none"></div>
    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 z-10">
        
        <div class="mb-12 overflow-x-auto hide-scrollbar py-3">
            <div class="flex gap-2 px-4">
                <a href="/jobmington/learn" 
                   class="glass-pill px-4 py-2 rounded-full text-xs font-bold transition flex items-center gap-1.5 flex-shrink-0 <?= empty($filterCategory) ? 'active' : '' ?>">
                   <i class="fas fa-th-large"></i> All
                </a>
                <?php foreach ($categories as $cat): ?>
                <a href="?category=<?= e($cat['slug']) ?>" 
                   class="glass-pill px-4 py-2 rounded-full text-xs font-bold transition flex items-center gap-1.5 flex-shrink-0 <?= $filterCategory === $cat['slug'] ? 'active' : '' ?>">
                    <?= e($cat['name']) ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>

        <?php if (empty($courses)): ?>
            <div class="glass-pill rounded-2xl p-12 text-center border-dashed border border-white/10 max-w-2xl mx-auto">
                <div class="w-24 h-24 bg-white/5 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-search text-4xl text-slate-600"></i>
                </div>
                <h3 class="text-2xl font-bold text-white mb-2">No courses found</h3>
                <p class="text-slate-400 mb-6">We couldn't find any courses matching your criteria.</p>
                <a href="/jobmington/learn" class="inline-flex items-center gap-2 px-8 py-3 bg-slate-700 hover:bg-slate-600 text-white font-bold rounded-full transition shadow-lg">
                    Clear Filters
                </a>
            </div>
        <?php else: ?>
            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php foreach ($courses as $course): ?>
                <a href="/jobmington/learn/course.php?id=<?= $course['course_id'] ?>" class="group block h-full">
                    
                    <div class="cinematic-card bg-white/5 backdrop-blur-sm rounded-2xl overflow-hidden border border-white/10 shadow-lg h-full flex flex-col relative group hover:border-amber-500/20">
                        
                        <div class="relative aspect-[4/3] overflow-hidden">
                            <?php if ($course['thumbnail'] && strpos($course['thumbnail'], 'http') === 0): ?>
                                <img src="<?= e($course['thumbnail']) ?>" 
                                     alt="<?= e($course['title']) ?>" 
                                     class="card-image-zoom w-full h-full object-cover transition duration-1000 group-hover:scale-110">
                            <?php elseif ($course['thumbnail']): ?>
                                <img src="<?= upload('course-thumbnails/' . $course['thumbnail']) ?>" 
                                     alt="<?= e($course['title']) ?>" 
                                     class="card-image-zoom w-full h-full object-cover transition duration-1000 group-hover:scale-110">
                            <?php else: ?>
                                <div class="w-full h-full bg-gradient-to-br from-slate-800 to-slate-950 flex items-center justify-center">
                                    <i class="fas fa-graduation-cap text-white/10 text-8xl"></i>
                                </div>
                            <?php endif; ?>
                            
                            <div class="absolute inset-0 bg-gradient-to-t from-slate-900 via-slate-900/40 to-transparent opacity-90"></div>
                            
                            <div class="absolute inset-0 bg-blue-600/20 opacity-0 group-hover:opacity-100 transition duration-500 mix-blend-overlay"></div>

                            <div class="absolute top-5 left-5 right-5 flex justify-between items-start z-10">
                                <?php if ($course['category_name']): ?>
                                <span class="bg-white/10 backdrop-blur-md border border-white/20 text-white text-[10px] font-bold px-3 py-1.5 rounded-full uppercase tracking-wider">
                                    <?= e($course['category_name']) ?>
                                </span>
                                <?php endif; ?>
                                <?php if ($course['is_free']): ?>
                                <span class="bg-emerald-500/90 backdrop-blur-md text-white text-[10px] font-bold px-3 py-1.5 rounded-full uppercase tracking-wider shadow-lg">
                                    Free
                                </span>
                                <?php else: ?>
                                <span class="bg-gradient-to-r from-amber-500 to-orange-500 backdrop-blur-md text-slate-900 text-[10px] font-bold px-3 py-1.5 rounded-full uppercase tracking-wider shadow-lg flex items-center gap-1">
                                    <i class="fas fa-crown"></i> Premium
                                </span>
                                <?php endif; ?>
                            </div>

                            <div class="absolute bottom-0 left-0 right-0 p-6 z-10 card-content">
                                <h3 class="text-2xl font-heading font-extrabold text-white mb-2 leading-tight drop-shadow-md">
                                    <?= e($course['title']) ?>
                                </h3>
                                <div class="flex items-center gap-4 text-xs font-bold text-slate-300 uppercase tracking-wide">
                                    <?php if ($course['is_external'] ?? false): ?>
                                    <span><i class="fas fa-external-link-alt mr-1.5 text-amber-400"></i> External</span>
                                    <?php if ($course['has_certificate'] ?? false): ?>
                                    <span><i class="fas fa-certificate mr-1.5 text-amber-400"></i> Certificate</span>
                                    <?php endif; ?>
                                    <?php else: ?>
                                    <span><i class="fas fa-book-open mr-1.5 text-slate-400"></i> <?= $course['module_count'] ?> Modules</span>
                                    <?php endif; ?>
                                    <span><i class="fas fa-users mr-1.5 text-slate-400"></i> <?= number_format($course['enrollment_count']) ?></span>
                                </div>
                            </div>
                            
                            <div class="play-btn absolute inset-0 flex items-center justify-center z-20 pointer-events-none">
                                <div class="w-16 h-16 rounded-full bg-white/20 backdrop-blur-md border border-white/40 flex items-center justify-center shadow-2xl">
                                    <i class="fas fa-play text-white text-2xl ml-1"></i>
                                </div>
                            </div>
                        </div>
                        
                        <div class="px-6 pb-6 pt-2 bg-slate-900/50 border-t border-white/10 relative z-20">
                            <p class="text-slate-400 text-sm line-clamp-2 leading-relaxed">
                                <?= excerpt($course['short_description'] ?? strip_tags($course['description']), 100) ?>
                            </p>
                            <div class="mt-4 flex items-center gap-2 text-xs font-bold text-slate-300 group-hover:translate-x-2 transition-transform">
                                Start Learning <i class="fas fa-arrow-right"></i>
                            </div>
                        </div>
                    </div>
                    
                </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <?php if (Session::isLoggedIn()): ?>
        <div class="mt-16 relative rounded-2xl overflow-hidden p-8 md:p-12 text-center group shadow-xl">
            <div class="absolute inset-0 bg-gradient-to-br from-slate-900 to-obsidian-950"></div>
            <div class="absolute inset-0 bg-[url('/jobmington/assets/images/pattern.svg')] opacity-5 group-hover:opacity-10 transition duration-1000 group-hover:scale-105"></div>
            <div class="absolute top-0 left-0 w-64 h-64 bg-blue-500/10 rounded-full blur-[80px]"></div>
            <div class="absolute bottom-0 right-0 w-64 h-64 bg-cyan-500/10 rounded-full blur-[80px]"></div>
            
            <div class="relative z-10 max-w-3xl mx-auto">
                <div class="w-16 h-16 bg-white/10 rounded-xl flex items-center justify-center mx-auto mb-4 backdrop-blur-md border border-white/10">
                    <i class="fas fa-rocket text-2xl text-slate-300"></i>
                </div>
                <h3 class="text-3xl md:text-4xl font-heading font-extrabold text-white mb-4">Ready to Resume?</h3>
                <p class="text-slate-300 text-base mb-8 font-light">Track your progress, finish your assessments, and claim your certificates in your personal dashboard.</p>
                <a href="/jobmington/learn/my-courses.php" class="inline-flex items-center gap-3 bg-white text-slate-900 font-bold px-10 py-4 rounded-full hover:bg-slate-50 transition shadow-xl hover:shadow-slate-500/20 transform hover:-translate-y-1">
                    <span>Go to Dashboard</span>
                    <i class="fas fa-arrow-right"></i>
                </a>
            </div>
        </div>
        <?php endif; ?>

    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
