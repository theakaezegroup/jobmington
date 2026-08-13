<?php
/**
 * Fulltext indexes for job search.
 *
 * Search matched with LIKE '%term%' across title, description, requirements
 * and the company name. A leading wildcard cannot use an index, so every
 * search read the whole live set, and jobs holds 224MB of row data because
 * descriptions are long. Searches cost between 2.1 and 2.9 seconds.
 *
 * Measured on production, the columns are not equal offenders. For "developer":
 *
 *   the join alone, no search        0.026s
 *   title only                       0.046s
 *   title + company name             0.378s
 *   all four columns                 2.902s
 *
 * So description and requirements are 2.5 of the 2.9 seconds. Dropping them
 * would have been the cheap fix and it was the wrong one: it takes the result
 * count from 2455 to 766, so two thirds of what people search for lives in
 * those columns. Fulltext keeps the recall and drops the cost.
 *
 * A caution for whoever writes the query. The index is only used when MATCH is
 * ANDed. Putting the two MATCH clauses on either side of an OR makes the
 * planner give up and scan companies end to end, which measured no faster than
 * the LIKE it replaced. The form that works is a UNION of the two matches
 * joined back to jobs as a derived table.
 *
 * Boolean mode also has a floor: innodb_ft_min_token_size is 3 here, so a
 * prefix shorter than three characters matches nothing at all, and a caller
 * needs a fallback for those rather than showing an empty page.
 */

return function (PDO $pdo) {
    $existing = [];
    foreach (['jobs', 'companies'] as $table) {
        foreach ($pdo->query("SHOW INDEX FROM `$table`")->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $existing[$row['Key_name']] = true;
        }
    }

    if (!isset($existing['ft_jobs_search'])) {
        $pdo->exec('CREATE FULLTEXT INDEX ft_jobs_search ON jobs (title, description, requirements)');
        echo "  created ft_jobs_search\n";
    } else {
        echo "  ft_jobs_search already present\n";
    }

    if (!isset($existing['ft_companies_name'])) {
        $pdo->exec('CREATE FULLTEXT INDEX ft_companies_name ON companies (name)');
        echo "  created ft_companies_name\n";
    } else {
        echo "  ft_companies_name already present\n";
    }
};
