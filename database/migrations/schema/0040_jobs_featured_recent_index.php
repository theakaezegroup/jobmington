<?php
/**
 * The index the job listings actually order by.
 *
 * Both listings end in the same clause:
 *
 *     ORDER BY j.is_featured DESC, j.posted_at DESC LIMIT n
 *
 * 0038 added idx_jobs_live_sort (is_active, expires_at, is_featured,
 * posted_at) and it cannot serve that ordering, for a reason worth writing
 * down: expires_at is matched by a range, not an equality, and a range ends an
 * index's usefulness for ordering every column that follows it. So the planner
 * used the index to find rows and then sorted them by hand, which EXPLAIN
 * reports as Using filesort, over sixteen thousand rows, while selecting j.*
 * and therefore dragging the long description column along for each one.
 *
 * This index puts the two ordering columns immediately after an equality, so
 * the rows arrive in order and the LIMIT can stop early. The expires_at filter
 * is then applied to the handful of rows actually read.
 *
 * idx_jobs_live_sort is left in place. It is genuinely used by the counts,
 * where nothing is ordered and covering the filter is the whole benefit.
 */

return function (PDO $pdo) {
    $existing = [];
    foreach ($pdo->query('SHOW INDEX FROM jobs')->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $existing[$row['Key_name']] = true;
    }

    if (!isset($existing['idx_jobs_featured_recent'])) {
        $pdo->exec('CREATE INDEX idx_jobs_featured_recent ON jobs (is_active, is_featured, posted_at)');
        echo "  created idx_jobs_featured_recent\n";
    } else {
        echo "  idx_jobs_featured_recent already present\n";
    }
};
