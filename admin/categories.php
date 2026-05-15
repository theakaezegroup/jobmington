<?php
/**
 * JOBMINGTON - Admin Categories Management
 * Manage job and course categories
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
$activeTab = get('tab', 'job');

function jm_admin_slug(string $value): string {
    $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $value), '-'));
    return $slug ?: 'category-' . time();
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = post('action', '');
    
    if ($action === 'create_job_category') {
        $name = Security::clean(post('name', ''));
        $icon = Security::clean(post('icon', 'fa-briefcase'));
        $description = Security::clean(post('description', ''));
        
        if ($name) {
            try {
                $stmt = $pdo->prepare("INSERT INTO job_categories (name, slug, icon) VALUES (?, ?, ?)");
                $stmt->execute([$name, jm_admin_slug($name), $icon]);
                $success = "Job category '{$name}' created!";
            } catch (Exception $e) {
                $error = 'Error creating category.';
            }
        }
    } elseif ($action === 'create_course_category') {
        $name = Security::clean(post('name', ''));
        $icon = Security::clean(post('icon', 'fa-book'));
        $description = Security::clean(post('description', ''));
        
        if ($name) {
            try {
                $stmt = $pdo->prepare("INSERT INTO course_categories (name, icon, description, created_at) VALUES (?, ?, ?, NOW())");
                $stmt->execute([$name, $icon, $description]);
                $success = "Course category '{$name}' created!";
            } catch (Exception $e) {
                $error = 'Error creating category.';
            }
        }
    } elseif ($action === 'delete_job_category') {
        $id = (int) post('category_id', 0);
        if ($id > 0) {
            try {
                $pdo->prepare("DELETE FROM job_categories WHERE category_id = ?")->execute([$id]);
                $success = 'Category deleted!';
            } catch (Exception $e) { $error = 'Error deleting category.'; }
        }
    } elseif ($action === 'delete_course_category') {
        $id = (int) post('category_id', 0);
        if ($id > 0) {
            try {
                $pdo->prepare("DELETE FROM course_categories WHERE id = ?")->execute([$id]);
                $success = 'Category deleted!';
            } catch (Exception $e) { $error = 'Error deleting category.'; }
        }
    }
}

// Fetch categories
try { $jobCategories = $pdo->query("SELECT * FROM job_categories ORDER BY name")->fetchAll(); } catch (Exception $e) { $jobCategories = []; }
try { $courseCategories = $pdo->query("SELECT * FROM course_categories ORDER BY name")->fetchAll(); } catch (Exception $e) { $courseCategories = []; }

$pageTitle = 'Categories Management - ' . SITE_NAME;
require_once __DIR__ . '/../includes/header.php';
?>

<div class="min-h-screen bg-slate-900">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        
        <div class="flex items-center justify-between mb-8">
            <div>
                <a href="/jobmington/admin/" class="text-slate-400 hover:text-white text-xs font-bold uppercase tracking-widest mb-2 inline-block transition">
                    <i class="fas fa-arrow-left mr-1"></i> Admin Dashboard
                </a>
                <h1 class="text-4xl font-heading font-black text-white">Categories</h1>
                <p class="text-slate-400 mt-2">Manage job and course categories</p>
            </div>
        </div>

        <?php if ($success): ?>
            <div class="bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 p-4 rounded-xl mb-6"><i class="fas fa-check-circle mr-2"></i> <?= e($success) ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="bg-red-500/10 border border-red-500/30 text-red-400 p-4 rounded-xl mb-6"><i class="fas fa-exclamation-circle mr-2"></i> <?= e($error) ?></div>
        <?php endif; ?>

        <!-- Tabs -->
        <div class="flex gap-2 mb-8 border-b border-white/10 pb-4">
            <a href="?tab=job" class="px-4 py-2 rounded-lg text-sm font-bold <?= $activeTab === 'job' ? 'bg-blue-500 text-white' : 'bg-white/5 text-white hover:bg-white/10' ?> transition"><i class="fas fa-briefcase mr-2"></i> Job Categories</a>
            <a href="?tab=course" class="px-4 py-2 rounded-lg text-sm font-bold <?= $activeTab === 'course' ? 'bg-emerald-500 text-white' : 'bg-white/5 text-white hover:bg-white/10' ?> transition"><i class="fas fa-graduation-cap mr-2"></i> Course Categories</a>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Create Form -->
            <div class="bg-white/5 border border-white/10 rounded-xl p-6">
                <h3 class="text-lg font-bold text-white mb-4">Create <?= $activeTab === 'job' ? 'Job' : 'Course' ?> Category</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="create_<?= $activeTab ?>_category">
                    
                    <div class="mb-4">
                        <label class="block text-xs font-bold text-slate-400 uppercase mb-2">Name</label>
                        <input type="text" name="name" required class="w-full bg-white/5 border border-white/10 rounded-lg px-4 py-3 text-white focus:border-amber-500 focus:outline-none">
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-xs font-bold text-slate-400 uppercase mb-2">Icon</label>
                        <select name="icon" class="w-full bg-white/5 border border-white/10 rounded-lg px-4 py-3 text-white focus:border-amber-500 focus:outline-none">
                            <option value="fa-briefcase">Briefcase</option>
                            <option value="fa-code">Code</option>
                            <option value="fa-chart-line">Chart</option>
                            <option value="fa-paint-brush">Design</option>
                            <option value="fa-cog">Engineering</option>
                            <option value="fa-heartbeat">Healthcare</option>
                            <option value="fa-graduation-cap">Education</option>
                            <option value="fa-gavel">Legal</option>
                            <option value="fa-building">Real Estate</option>
                            <option value="fa-truck">Logistics</option>
                        </select>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-xs font-bold text-slate-400 uppercase mb-2">Description</label>
                        <textarea name="description" rows="2" class="w-full bg-white/5 border border-white/10 rounded-lg px-4 py-3 text-white focus:border-amber-500 focus:outline-none"></textarea>
                    </div>
                    
                    <button type="submit" class="w-full bg-<?= $activeTab === 'job' ? 'blue' : 'emerald' ?>-500 hover:bg-<?= $activeTab === 'job' ? 'blue' : 'emerald' ?>-400 text-white font-bold py-3 rounded-lg transition">Create Category</button>
                </form>
            </div>
            
            <!-- Existing Categories -->
            <div class="lg:col-span-2">
                <h3 class="text-lg font-bold text-white mb-4"><?= $activeTab === 'job' ? 'Job' : 'Course' ?> Categories</h3>
                <?php 
                $categories = $activeTab === 'job' ? $jobCategories : $courseCategories;
                $idField = $activeTab === 'job' ? 'category_id' : 'id';
                ?>
                <?php if (empty($categories)): ?>
                    <div class="bg-white/5 border border-white/10 rounded-xl p-8 text-center">
                        <i class="fas fa-layer-group text-4xl text-slate-600 mb-4"></i>
                        <p class="text-slate-400">No categories yet.</p>
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <?php foreach ($categories as $cat): ?>
                        <div class="bg-white/5 border border-white/10 rounded-xl p-4 hover:border-white/20 transition flex items-center justify-between">
                            <div class="flex items-center gap-4">
                                <div class="w-10 h-10 bg-<?= $activeTab === 'job' ? 'blue' : 'emerald' ?>-500/20 rounded-lg flex items-center justify-center text-<?= $activeTab === 'job' ? 'blue' : 'emerald' ?>-400">
                                    <i class="fas <?= e($cat['icon'] ?? 'fa-folder') ?>"></i>
                                </div>
                                <div>
                                    <h4 class="font-bold text-white"><?= e($cat['name']) ?></h4>
                                    <p class="text-xs text-slate-400"><?= e($cat['description'] ?? 'No description') ?></p>
                                </div>
                            </div>
                            <form method="POST" onsubmit="return confirm('Delete this category?')">
                                <input type="hidden" name="action" value="delete_<?= $activeTab ?>_category">
                                <input type="hidden" name="category_id" value="<?= $cat[$idField] ?>">
                                <button type="submit" class="text-red-400 hover:text-red-300"><i class="fas fa-trash"></i></button>
                            </form>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
