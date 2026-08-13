<?php
/**
 * Index the "is this job still live" filter.
 *
 * Every page that counts or lists jobs asks the same thing:
 *
 *     is_active = 1 AND (expires_at IS NULL OR expires_at >= CURDATE())
 *
 * The only index that touched it was idx_active, on is_active alone. So the
 * planner matched roughly nine thousand rows by index and then read every one
 * of them off disk to look at expires_at. The jobs table holds 224MB of row
 * data for 32,000 rows, because job descriptions are long, and the box has
 * 961MB of RAM, so those reads miss the buffer pool and go to the disk. Four
 * counts on the homepage were costing between 1.2 and 1.8 seconds each.
 *
 * Both indexes below put expires_at directly after is_active, which is what
 * lets the filter be answered inside the index. The trailing columns are there
 * to make the index cover the query: with them present the counts never touch
 * the table at all.
 *
 *   idx_jobs_live       the four homepage counts, the country list and the
 *                       per-category counts. Every column any of them reads is
 *                       in here.
 *   idx_jobs_live_sort  the featured list, which orders by is_featured then
 *                       posted_at. That one still reads rows, because it
 *                       selects j.*, but it only reads the six it returns
 *                       instead of sorting nine thousand.
 *
 * idx_active is left alone. It is narrower and other queries may prefer it,
 * and an unused index on a 32k-row table costs almost nothing.
 */

return function (PDO $pdo) {
    $existing = [];
    foreach ($pdo->query('SHOW INDEX FROM jobs')->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $existing[$row['Key_name']] = true;
    }

    if (!isset($existing['idx_jobs_live'])) {
        $pdo->exec(
            'CREATE INDEX idx_jobs_live ON jobs
                (is_active, expires_at, category_id, company_id, country_id, job_type)'
        );
        echo "  created idx_jobs_live\n";
    } else {
        echo "  idx_jobs_live already present\n";
    }

    if (!isset($existing['idx_jobs_live_sort'])) {
        $pdo->exec(
            'CREATE INDEX idx_jobs_live_sort ON jobs
                (is_active, expires_at, is_featured, posted_at)'
        );
        echo "  created idx_jobs_live_sort\n";
    } else {
        echo "  idx_jobs_live_sort already present\n";
    }
};
