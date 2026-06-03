<?php
/**
 * JOBMINGTON - Learning Academy (courses listing)
 */
require_once __DIR__ . '/_disabled.php';

define('JOBMINGTON', true);
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/learn_nav.php';

Session::start();
$pdo = db();

$categories = $pdo->query("SELECT * FROM course_categories WHERE is_active = 1 ORDER BY name")->fetchAll();

$filterCategory = Security::clean(get('category', ''));
$filterSearch   = Security::clean(get('q', ''));

$sql = "SELECT c.*, cc.name AS category_name, cc.slug AS category_slug,
               (SELECT COUNT(*) FROM course_modules WHERE course_id = c.course_id) AS module_count
        FROM courses c
        LEFT JOIN course_categories cc ON c.category_id = cc.id
        WHERE c.is_published = 1";
$params = [];
if ($filterCategory) { $sql .= " AND cc.slug = ?"; $params[] = $filterCategory; }
if ($filterSearch)   { $sql .= " AND (c.title LIKE ? OR c.description LIKE ?)"; $params[] = "%$filterSearch%"; $params[] = "%$filterSearch%"; }
$sql .= " ORDER BY c.is_featured DESC, c.enrollment_count DESC, c.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$courses = $stmt->fetchAll();

$totalEnrollments = (int) ($pdo->query("SELECT COALESCE(SUM(enrollment_count),0) FROM courses WHERE is_published = 1")->fetchColumn());

$pageTitle = 'Learning Academy - ' . SITE_NAME;
$activeAIPage = 'learn';
require_once __DIR__ . '/../includes/ai-header.php';
?>
<style>
.jm-learn { max-width:1100px; margin:0 auto; padding:36px 20px 72px; }
.jm-learn-hero h1 { font-size:clamp(30px,5vw,46px); font-weight:800; letter-spacing:-.02em; color:#061426; margin:0 0 12px; }
.jm-learn-hero p { font-size:17px; color:#53667f; margin:0 0 24px; max-width:600px; line-height:1.6; }
.jm-learn-search { display:flex; gap:10px; flex-wrap:wrap; margin-bottom:18px; }
.jm-learn-search input { flex:1; min-width:220px; border:1px solid #d8e4f4; border-radius:10px; padding:12px 15px; font:inherit; font-size:14px; background:#fbfdff; }
.jm-learn-search input:focus { outline:none; border-color:#0640a3; box-shadow:0 0 0 3px rgba(6,64,163,.08); }
.jm-learn-search button { background:#0640a3; color:#fff; border:0; border-radius:10px; padding:0 22px; font-weight:700; font-size:14px; cursor:pointer; }
.jm-learn-cats { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:30px; }
.jm-learn-cat { font-size:13px; font-weight:700; padding:7px 14px; border-radius:99px; border:1px solid #e4eaf3; background:#fff; color:#53667f; text-decoration:none; transition:all .14s; }
.jm-learn-cat:hover { border-color:#c8d8ef; color:#0640a3; }
.jm-learn-cat.active { background:#0640a3; border-color:#0640a3; color:#fff; }
.jm-learn-grid { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:18px; }
@media(max-width:860px){ .jm-learn-grid{grid-template-columns:repeat(2,minmax(0,1fr));} }
@media(max-width:560px){ .jm-learn-grid{grid-template-columns:1fr;} }
.jm-course { display:flex; flex-direction:column; background:#fff; border:1px solid #e4eaf3; border-radius:14px; overflow:hidden; text-decoration:none; transition:box-shadow .16s,transform .16s; }
.jm-course:hover { box-shadow:0 12px 30px rgba(6,20,38,.1); transform:translateY(-3px); }
.jm-course-thumb { position:relative; aspect-ratio:16/9; background:linear-gradient(135deg,#eef5ff,#f7faff); display:grid; place-items:center; overflow:hidden; }
.jm-course-thumb img { width:100%; height:100%; object-fit:cover; }
.jm-course-thumb svg { width:38px; height:38px; color:#9bb0c7; }
.jm-course-badge { position:absolute; top:10px; left:10px; font-size:10px; font-weight:800; text-transform:uppercase; letter-spacing:.05em; background:#0640a3; color:#fff; padding:3px 9px; border-radius:99px; }
.jm-course-body { padding:14px 16px 16px; display:flex; flex-direction:column; gap:6px; flex:1; }
.jm-course-cat { font-size:10px; font-weight:800; text-transform:uppercase; letter-spacing:.06em; color:#0640a3; }
.jm-course-title { font-size:16px; font-weight:800; color:#061426; line-height:1.3; }
.jm-course-desc { font-size:13px; color:#53667f; line-height:1.5; flex:1; }
.jm-course-meta { display:flex; align-items:center; gap:12px; font-size:11.5px; color:#94a3b8; margin-top:6px; }
.jm-course-foot { display:flex; align-items:center; justify-content:space-between; padding-top:8px; border-top:1px solid #f0f4f9; margin-top:8px; }
.jm-course-price { font-size:13px; font-weight:800; color:#0a6454; }
.jm-learn-empty { text-align:center; color:#94a3b8; padding:60px 20px; background:#fff; border:1px solid #e4eaf3; border-radius:14px; }
.jm-learn-stat { font-size:13px; color:#94a3b8; margin-bottom:18px; }
</style>

<div class="jm-learn">
    <?= jm_breadcrumbs([['label' => 'Learn']]) ?>
    <?= jm_learn_nav('courses') ?>

    <div class="jm-learn-hero">
        <h1>Learning Academy.</h1>
        <p>Master in-demand skills with practical courses — earn certificates that add weight to your CV.</p>
        <form class="jm-learn-search" method="get">
            <input type="text" name="q" value="<?= e($filterSearch) ?>" placeholder="Search courses…">
            <button type="submit">Search</button>
        </form>
        <div class="jm-learn-cats">
            <a class="jm-learn-cat <?= $filterCategory === '' ? 'active' : '' ?>" href="/jobmington/learn/">All</a>
            <?php foreach ($categories as $cat): ?>
                <a class="jm-learn-cat <?= $filterCategory === $cat['slug'] ? 'active' : '' ?>" href="/jobmington/learn/?category=<?= e($cat['slug']) ?>"><?= e($cat['name']) ?></a>
            <?php endforeach; ?>
        </div>
    </div>

    <?php if (empty($courses)): ?>
        <div class="jm-learn-empty">No courses<?= $filterSearch || $filterCategory ? ' match your filter' : ' yet' ?>. Check back soon.</div>
    <?php else: ?>
        <div class="jm-learn-stat"><?= count($courses) ?> course<?= count($courses) !== 1 ? 's' : '' ?> &middot; <?= number_format($totalEnrollments) ?> enrollments</div>
        <div class="jm-learn-grid">
            <?php foreach ($courses as $c): ?>
                <a class="jm-course" href="/jobmington/learn/course.php?id=<?= (int)$c['course_id'] ?>">
                    <div class="jm-course-thumb">
                        <?php if (!empty($c['thumbnail'])): ?>
                            <img src="<?= e($c['thumbnail']) ?>" alt="<?= e($c['title']) ?>">
                        <?php else: ?>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10L12 5 2 10l10 5 10-5z"/><path d="M6 12v5c0 1 3 2 6 2s6-1 6-2v-5"/></svg>
                        <?php endif; ?>
                        <?php if (!empty($c['has_certificate'])): ?><span class="jm-course-badge">Certificate</span><?php endif; ?>
                    </div>
                    <div class="jm-course-body">
                        <?php if ($c['category_name']): ?><span class="jm-course-cat"><?= e($c['category_name']) ?></span><?php endif; ?>
                        <span class="jm-course-title"><?= e($c['title']) ?></span>
                        <span class="jm-course-desc"><?= e(excerpt($c['short_description'] ?: $c['description'] ?: '', 90)) ?></span>
                        <div class="jm-course-meta">
                            <span><?= e(ucfirst($c['difficulty'])) ?></span>
                            <?php if ((float)$c['duration_hours'] > 0): ?><span><?= rtrim(rtrim(number_format((float)$c['duration_hours'],1),'0'),'.') ?>h</span><?php endif; ?>
                            <?php if ((int)$c['module_count'] > 0): ?><span><?= (int)$c['module_count'] ?> modules</span><?php endif; ?>
                        </div>
                        <div class="jm-course-foot">
                            <span class="jm-course-price"><?= $c['is_free'] ? 'Free' : '₦' . number_format((float)$c['price']) ?></span>
                            <span style="font-size:11px;color:#94a3b8;"><?= number_format((int)$c['enrollment_count']) ?> enrolled</span>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/ai-footer.php'; ?>
