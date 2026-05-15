<?php
require_once __DIR__ . '/config.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method !== 'GET') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

$courseId = (int) ($_GET['id'] ?? 0);
$category = trim($_GET['category'] ?? '');
$limit = min(50, max(1, (int) ($_GET['limit'] ?? 20)));

try {
    if ($courseId > 0) {
        $stmt = $pdo->prepare("
            SELECT c.*, cc.name AS category_name
            FROM courses c
            LEFT JOIN course_categories cc ON c.category_id = cc.id
            WHERE c.course_id = ? AND c.is_published = 1
            LIMIT 1
        ");
        $stmt->execute([$courseId]);
        $course = $stmt->fetch();

        if (!$course) {
            jsonResponse(['success' => false, 'message' => 'Course not found'], 404);
        }

        $modules = $pdo->prepare("
            SELECT module_id, title, description, duration_minutes, sort_order, is_free_preview
            FROM course_modules
            WHERE course_id = ?
            ORDER BY sort_order ASC, module_id ASC
        ");
        $modules->execute([$courseId]);
        $course['modules'] = $modules->fetchAll();

        jsonResponse(['success' => true, 'data' => $course]);
    }

    $where = ['c.is_published = 1'];
    $params = [];

    if ($category !== '') {
        $where[] = 'cc.slug = ?';
        $params[] = $category;
    }

    $stmt = $pdo->prepare("
        SELECT c.course_id, c.title, c.slug, c.short_description, c.thumbnail,
               c.instructor_name, c.course_type, c.duration_hours, c.difficulty,
               c.is_free, c.price, c.seed_price, c.enrollment_count,
               c.rating_avg, c.has_certificate, cc.name AS category_name
        FROM courses c
        LEFT JOIN course_categories cc ON c.category_id = cc.id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY c.is_featured DESC, c.created_at DESC
        LIMIT ?
    ");
    $stmt->execute(array_merge($params, [$limit]));

    jsonResponse(['success' => true, 'count' => $stmt->rowCount(), 'data' => $stmt->fetchAll()]);
} catch (Throwable $e) {
    jsonResponse(['success' => false, 'message' => 'Unable to load courses'], 500);
}
