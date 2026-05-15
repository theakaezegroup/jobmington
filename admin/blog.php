<?php
/**
 * JOBMINGTON - Admin Blog Management
 * Create, edit, and manage blog posts.
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
$editId = (int) get('id', 0);

function jm_blog_slug(string $value): string {
    $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $value), '-'));
    return $slug ?: 'post-' . time();
}

try {
    $categories = $pdo->query("SELECT id, name, slug FROM blog_categories ORDER BY name")->fetchAll();
    if (empty($categories)) {
        $pdo->prepare("INSERT INTO blog_categories (name, slug) VALUES ('General', 'general')")->execute();
        $categories = $pdo->query("SELECT id, name, slug FROM blog_categories ORDER BY name")->fetchAll();
    }
} catch (Exception $e) {
    $categories = [];
}

$defaultCategoryId = (int) ($categories[0]['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = post('action', '');

    if ($postAction === 'create' || $postAction === 'update') {
        $title = Security::clean(post('title', ''));
        $slug = Security::clean(post('slug', '')) ?: jm_blog_slug($title);
        $excerpt = Security::clean(post('excerpt', ''));
        $content = trim($_POST['content'] ?? '');
        $categoryId = (int) post('category_id', $defaultCategoryId);
        $isPublished = post('status', 'draft') === 'published' ? 1 : 0;
        $featuredImage = Security::clean(post('featured_image', ''));

        if ($categoryId <= 0) {
            $categoryId = $defaultCategoryId;
        }

        if ($title && $content && $categoryId > 0) {
            try {
                if ($postAction === 'create') {
                    $stmt = $pdo->prepare("
                        INSERT INTO blog_posts
                            (category_id, author_id, title, slug, excerpt, content, featured_image, is_published, published_at, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, " . ($isPublished ? "NOW()" : "NULL") . ", NOW())
                    ");
                    $stmt->execute([
                        $categoryId,
                        Session::userId(),
                        $title,
                        $slug,
                        $excerpt ?: null,
                        $content,
                        $featuredImage ?: null,
                        $isPublished,
                    ]);
                    $success = 'Blog post created successfully.';
                } else {
                    $postId = (int) post('post_id', 0);
                    $stmt = $pdo->prepare("
                        UPDATE blog_posts
                        SET category_id = ?,
                            title = ?,
                            slug = ?,
                            excerpt = ?,
                            content = ?,
                            featured_image = ?,
                            is_published = ?,
                            published_at = " . ($isPublished ? "COALESCE(published_at, NOW())" : "NULL") . ",
                            updated_at = NOW()
                        WHERE post_id = ?
                    ");
                    $stmt->execute([
                        $categoryId,
                        $title,
                        $slug,
                        $excerpt ?: null,
                        $content,
                        $featuredImage ?: null,
                        $isPublished,
                        $postId,
                    ]);
                    $success = 'Blog post updated successfully.';
                }
                $action = 'list';
            } catch (Exception $e) {
                $error = 'Error saving post: ' . $e->getMessage();
            }
        } else {
            $error = 'Title, category, and content are required.';
        }
    } elseif ($postAction === 'delete') {
        $postId = (int) post('post_id', 0);
        if ($postId > 0) {
            try {
                $pdo->prepare("DELETE FROM blog_posts WHERE post_id = ?")->execute([$postId]);
                $success = 'Post deleted successfully.';
            } catch (Exception $e) {
                $error = 'Error deleting post.';
            }
        }
    }
}

try {
    $posts = $pdo->query("
        SELECT bp.*, bc.name AS category_name, u.first_name, u.last_name
        FROM blog_posts bp
        LEFT JOIN blog_categories bc ON bp.category_id = bc.id
        LEFT JOIN users u ON bp.author_id = u.user_id
        ORDER BY bp.created_at DESC
    ")->fetchAll();
} catch (Exception $e) {
    $posts = [];
}

$editPost = null;
if ($action === 'edit' && $editId > 0) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM blog_posts WHERE post_id = ?");
        $stmt->execute([$editId]);
        $editPost = $stmt->fetch();
    } catch (Exception $e) {
        $editPost = null;
    }
}

$pageTitle = 'Blog Management - ' . SITE_NAME;
require_once __DIR__ . '/../includes/header.php';
?>

<div class="min-h-screen bg-slate-900">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div class="flex items-center justify-between mb-8">
            <div>
                <a href="/jobmington/admin/" class="text-slate-400 hover:text-white text-xs font-bold uppercase tracking-widest mb-2 inline-block transition">
                    <i class="fas fa-arrow-left mr-1"></i> Admin Dashboard
                </a>
                <h1 class="text-4xl font-heading font-black text-white">Blog Management</h1>
            </div>
            <?php if ($action === 'list'): ?>
            <a href="?action=create" class="px-6 py-3 bg-amber-500 hover:bg-amber-400 text-black rounded-xl font-bold transition">
                <i class="fas fa-plus mr-2"></i> New Post
            </a>
            <?php else: ?>
            <a href="?action=list" class="px-6 py-3 bg-white/10 hover:bg-white/20 text-white rounded-xl font-bold transition">
                <i class="fas fa-list mr-2"></i> View All Posts
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
        <div class="bg-white/5 border border-white/10 rounded-xl overflow-hidden">
            <?php if (empty($posts)): ?>
                <div class="p-12 text-center">
                    <i class="fas fa-newspaper text-4xl text-slate-600 mb-4"></i>
                    <p class="text-slate-400">No blog posts yet. Create your first post.</p>
                </div>
            <?php else: ?>
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-white/10 bg-white/5">
                            <th class="text-left py-4 px-6 text-slate-400 text-xs font-bold uppercase">Title</th>
                            <th class="text-left py-4 px-6 text-slate-400 text-xs font-bold uppercase">Category</th>
                            <th class="text-left py-4 px-6 text-slate-400 text-xs font-bold uppercase">Status</th>
                            <th class="text-left py-4 px-6 text-slate-400 text-xs font-bold uppercase">Date</th>
                            <th class="text-left py-4 px-6 text-slate-400 text-xs font-bold uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($posts as $post): ?>
                        <tr class="border-b border-white/5 hover:bg-white/5">
                            <td class="py-4 px-6">
                                <span class="text-white font-medium"><?= e($post['title']) ?></span><br>
                                <span class="text-slate-500 text-xs"><?= e(trim(($post['first_name'] ?? '') . ' ' . ($post['last_name'] ?? '')) ?: 'Admin') ?></span>
                            </td>
                            <td class="py-4 px-6 text-slate-400"><?= e($post['category_name'] ?? 'General') ?></td>
                            <td class="py-4 px-6"><?= (int) $post['is_published'] === 1 ? '<span class="px-2 py-1 bg-emerald-500/20 text-emerald-400 rounded-full text-xs">Published</span>' : '<span class="px-2 py-1 bg-slate-500/20 text-slate-400 rounded-full text-xs">Draft</span>' ?></td>
                            <td class="py-4 px-6 text-slate-500 text-sm"><?= date('M d, Y', strtotime($post['created_at'])) ?></td>
                            <td class="py-4 px-6">
                                <a href="?action=edit&id=<?= (int) $post['post_id'] ?>" class="text-blue-400 hover:text-blue-300 mr-3"><i class="fas fa-edit"></i></a>
                                <form method="POST" class="inline" onsubmit="return confirm('Delete this post?')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="post_id" value="<?= (int) $post['post_id'] ?>">
                                    <button type="submit" class="text-red-400 hover:text-red-300"><i class="fas fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <div class="bg-white/5 border border-white/10 rounded-xl p-8">
            <h2 class="text-xl font-bold text-white mb-6"><?= $action === 'edit' ? 'Edit Post' : 'Create New Post' ?></h2>
            <form method="POST">
                <input type="hidden" name="action" value="<?= $action === 'edit' ? 'update' : 'create' ?>">
                <?php if ($editPost): ?><input type="hidden" name="post_id" value="<?= (int) $editPost['post_id'] ?>"><?php endif; ?>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                    <div>
                        <label class="block text-xs font-bold text-slate-400 uppercase mb-2">Title</label>
                        <input type="text" name="title" value="<?= e($editPost['title'] ?? '') ?>" required class="w-full bg-white/5 border border-white/10 rounded-lg px-4 py-3 text-white focus:border-amber-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-400 uppercase mb-2">Slug</label>
                        <input type="text" name="slug" value="<?= e($editPost['slug'] ?? '') ?>" placeholder="auto-generated" class="w-full bg-white/5 border border-white/10 rounded-lg px-4 py-3 text-white focus:border-amber-500 focus:outline-none">
                    </div>
                </div>

                <div class="mb-6">
                    <label class="block text-xs font-bold text-slate-400 uppercase mb-2">Excerpt</label>
                    <textarea name="excerpt" rows="2" class="w-full bg-white/5 border border-white/10 rounded-lg px-4 py-3 text-white focus:border-amber-500 focus:outline-none"><?= e($editPost['excerpt'] ?? '') ?></textarea>
                </div>

                <div class="mb-6">
                    <label class="block text-xs font-bold text-slate-400 uppercase mb-2">Content</label>
                    <textarea name="content" rows="12" required class="w-full bg-white/5 border border-white/10 rounded-lg px-4 py-3 text-white focus:border-amber-500 focus:outline-none font-mono"><?= e($editPost['content'] ?? '') ?></textarea>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
                    <div>
                        <label class="block text-xs font-bold text-slate-400 uppercase mb-2">Category</label>
                        <select name="category_id" class="w-full bg-white/5 border border-white/10 rounded-lg px-4 py-3 text-white focus:border-amber-500 focus:outline-none">
                            <?php foreach ($categories as $category): ?>
                                <option value="<?= (int) $category['id'] ?>" <?= (int) ($editPost['category_id'] ?? $defaultCategoryId) === (int) $category['id'] ? 'selected' : '' ?>><?= e($category['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-400 uppercase mb-2">Status</label>
                        <select name="status" class="w-full bg-white/5 border border-white/10 rounded-lg px-4 py-3 text-white focus:border-amber-500 focus:outline-none">
                            <option value="draft" <?= empty($editPost['is_published']) ? 'selected' : '' ?>>Draft</option>
                            <option value="published" <?= !empty($editPost['is_published']) ? 'selected' : '' ?>>Published</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-400 uppercase mb-2">Featured Image URL</label>
                        <input type="url" name="featured_image" value="<?= e($editPost['featured_image'] ?? '') ?>" class="w-full bg-white/5 border border-white/10 rounded-lg px-4 py-3 text-white focus:border-amber-500 focus:outline-none">
                    </div>
                </div>

                <div class="flex gap-4">
                    <button type="submit" class="px-8 py-3 bg-amber-500 hover:bg-amber-400 text-black font-bold rounded-lg transition"><?= $action === 'edit' ? 'Update Post' : 'Create Post' ?></button>
                    <a href="?action=list" class="px-8 py-3 bg-white/10 hover:bg-white/20 text-white font-bold rounded-lg transition">Cancel</a>
                </div>
            </form>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
