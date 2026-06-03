<?php
/**
 * JOBMINGTON - Tools & Utilities Hub
 * Silicon Valley Grade Design
 */

define('JOBMINGTON', true);
$root = dirname(__DIR__);
require_once $root . '/config/env.php';
require_once $root . '/config/constants.php';
require_once $root . '/includes/security.php';
require_once $root . '/includes/session.php';
require_once $root . '/includes/functions.php';

Session::start();
$pageTitle = 'Tools & Utilities - ' . SITE_NAME;
require_once __DIR__ . '/includes/header.php';

$tools = [
    [
        'name' => 'AI Resume Optimizer',
        'icon' => 'file-alt',
        'description' => 'Enhance your resume with AI-powered suggestions. Improve keywords, formatting, and content to match job descriptions.',
        'url' => '/jobmington/cv-builder/index.php',
        'color' => 'brand',
        'badge' => 'New',
        'stats' => 'Used by 5,000+ jobseekers'
    ],
    [
        'name' => 'Salary Calculator',
        'icon' => 'calculator',
        'description' => 'Discover competitive salary ranges for your role, experience level, and location. Benchmark against industry standards.',
        'url' => '#',
        'color' => 'emerald',
        'stats' => 'Data from 100K+ jobs'
    ],
    [
        'name' => 'Cover Letter AI',
        'icon' => 'pen-fancy',
        'description' => 'Create professional cover letters tailored to each job. AI suggestions help you stand out.',
        'url' => '/jobmington/ai/cover-letter.php',
        'color' => 'purple',
        'badge' => 'New',
        'stats' => 'AI-powered'
    ],
    [
        'name' => 'Cold Pitch AI',
        'icon' => 'paper-plane',
        'description' => 'Write human, specific cold pitches that earn the micro-yes — for email, DM, or LinkedIn.',
        'url' => '/jobmington/ai/cold-pitch.php',
        'color' => 'blue',
        'badge' => 'New',
        'stats' => 'Email · DM · LinkedIn'
    ],
    [
        'name' => 'Interview Prep',
        'icon' => 'microphone',
        'description' => 'Practice mock interviews with AI feedback. Record yourself and get detailed performance analysis.',
        'url' => '#',
        'color' => 'orange',
        'stats' => 'Premium feature'
    ],
    [
        'name' => 'Job Analytics',
        'icon' => 'chart-bar',
        'description' => 'Analyze job market trends, top companies, and in-demand skills in your field.',
        'url' => '/jobmington/jobs/search.php',
        'color' => 'pink',
        'stats' => 'Real-time data'
    ],
    [
        'name' => 'Profile Strength Meter',
        'icon' => 'shield-alt',
        'description' => 'Get actionable tips to improve your profile visibility. Increase your chances of being discovered.',
        'url' => '/jobmington/seeker/profile.php',
        'color' => 'indigo',
        'stats' => 'Personalized advice'
    ],
    [
        'name' => 'Certificate Verifier',
        'icon' => 'check-circle',
        'description' => 'Verify and display your earned certificates. Blockchain-verified credentials.',
        'url' => '/jobmington/certificates/index.php',
        'color' => 'yellow',
        'stats' => 'Blockchain backed'
    ],
];
?>

<div class="min-h-screen bg-slate-900">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20">
        
        <!-- Hero -->
        <div class="text-center mb-20">
            <h1 class="text-5xl md:text-6xl font-heading font-black text-white mb-6">
                Career Tools & Utilities<span class="text-slate-500">.</span>
            </h1>
            <p class="text-xl text-slate-300 max-w-2xl mx-auto">
                Powerful tools designed to help you succeed. From resume optimization to interview prep, we've got everything you need to land your dream job.
            </p>
        </div>

        <!-- Tools Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-20">
            <?php foreach ($tools as $tool): 
                $colorClasses = [
                    'brand' => 'from-slate-500/20 to-slate-500/0 border-slate-500/50 hover:border-slate-400',
                    'emerald' => 'from-emerald-500/20 to-emerald-500/0 border-emerald-500/50 hover:border-emerald-500',
                    'blue' => 'from-blue-500/20 to-blue-500/0 border-blue-500/50 hover:border-blue-500',
                    'purple' => 'from-amber-500/20 to-amber-500/0 border-amber-500/50 hover:border-amber-500',
                    'orange' => 'from-orange-500/20 to-orange-500/0 border-orange-500/50 hover:border-orange-500',
                    'pink' => 'from-pink-500/20 to-pink-500/0 border-pink-500/50 hover:border-pink-500',
                    'indigo' => 'from-indigo-500/20 to-indigo-500/0 border-indigo-500/50 hover:border-indigo-500',
                    'yellow' => 'from-yellow-500/20 to-yellow-500/0 border-yellow-500/50 hover:border-yellow-500',
                ];
                $iconColors = [
                    'brand' => 'text-slate-400',
                    'emerald' => 'text-emerald-500',
                    'blue' => 'text-blue-500',
                    'purple' => 'text-amber-500',
                    'orange' => 'text-orange-500',
                    'pink' => 'text-pink-500',
                    'indigo' => 'text-indigo-500',
                    'yellow' => 'text-yellow-500',
                ];
            ?>
            <a href="<?= $tool['url'] ?>" class="group bg-gradient-to-br <?= $colorClasses[$tool['color']] ?> border border-white/10 rounded-2xl p-6 hover:border-white/20 transition duration-300 flex flex-col">
                <div class="flex items-start justify-between mb-4">
                    <div class="w-14 h-14 bg-white/10 rounded-xl flex items-center justify-center group-hover:bg-white/20 transition">
                        <i class="fas fa-<?= $tool['icon'] ?> text-2xl <?= $iconColors[$tool['color']] ?>"></i>
                    </div>
                    <?php if (isset($tool['badge'])): ?>
                    <span class="px-3 py-1 bg-emerald-500/20 text-emerald-400 rounded-full text-xs font-bold"><?= $tool['badge'] ?></span>
                    <?php endif; ?>
                </div>
                
                <h3 class="text-lg font-bold text-white mb-2"><?= $tool['name'] ?></h3>
                <p class="text-sm text-slate-300 flex-grow mb-4"><?= $tool['description'] ?></p>
                
                <div class="flex items-center justify-between">
                    <span class="text-xs text-slate-400"><?= $tool['stats'] ?></span>
                    <i class="fas fa-arrow-right text-slate-400 group-hover:text-white transition"></i>
                </div>
            </a>
            <?php endforeach; ?>
        </div>

        <!-- Featured Tool CTA -->
        <div class="bg-gradient-to-r from-slate-800/50 to-slate-900/50 border border-white/10 rounded-2xl p-12 md:p-16 text-center mb-20">
            <h2 class="text-3xl font-bold text-white mb-4">AI Resume Optimizer</h2>
            <p class="text-slate-300 mb-8 text-lg max-w-2xl mx-auto">
                Transform your resume with our AI-powered optimization engine. Match job descriptions, improve keywords, and increase your chances of getting hired by 40%.
            </p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="/jobmington/cv-builder/index.php" class="inline-flex items-center justify-center gap-2 bg-white hover:bg-slate-100 text-black font-bold py-3 px-8 rounded-xl transition">
                    <i class="fas fa-rocket"></i> Start Optimizing
                </a>
                <a href="/jobmington/faq.php" class="inline-flex items-center justify-center gap-2 bg-white/10 hover:bg-white/20 text-white font-bold py-3 px-8 rounded-xl transition border border-white/20">
                    <i class="fas fa-question-circle"></i> Learn More
                </a>
            </div>
        </div>

        <!-- Statistics -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mb-20">
            <div class="text-center">
                <div class="text-4xl font-black text-white mb-2">10+</div>
                <p class="text-slate-300">Professional Tools Available</p>
            </div>
            <div class="text-center">
                <div class="text-4xl font-black text-white mb-2">50K+</div>
                <p class="text-slate-300">Users Empowered Monthly</p>
            </div>
            <div class="text-center">
                <div class="text-4xl font-black text-white mb-2">40%</div>
                <p class="text-slate-300">Average Interview Success Rate Increase</p>
            </div>
        </div>

    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
