<?php
/**
 * Jobmington - WWR RSS Fetcher
 * Strategy: Global Local Pivot (Strict Filtering)
 */

define('JOBMINGTON', true);
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/database.php';

$pdo = db();

// 1. Configuration
$rss_url = "https://weworkremotely.com/remote-jobs.rss";
$user_agent = "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36";

// Geographic Filters
$skip_keywords = ["USA Only", "US Only", "North America", "UK Only", "US Resident", "Timezone: EST", "PST", "Canada Only", "LatAm"];
$keep_keywords = ["Anywhere", "Global", "EMEA", "Africa", "UTC", "Worldwide"];

// Category Mapping
$cat_map = [
    'Programming' => 1,
    'Design' => 9,
    'Marketing' => 3,
    'Customer Support' => 7,
    'DevOps' => 1,
    'Product' => 1,
    'Business' => 2,
    'Copywriting' => 14,
    'Management' => 11
];

function fetch_rss($url, $ua) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, $ua);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_ENCODING, ''); // Handle all encodings (gzip, deflate, etc)
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['body' => $response, 'code' => $httpCode];
}

echo " Fetching WWR RSS feed...\n";
$result = fetch_rss($rss_url, $user_agent);
$rss_content = $result['body'];

if ($result['code'] !== 200) {
    die(" Error: HTTP {$result['code']} when fetching feed.\n");
}

if (!$rss_content) {
    die(" Error: Empty response from WWR.\n");
}

libxml_use_internal_errors(true);
$xml = simplexml_load_string($rss_content, null, LIBXML_NOCDATA);
if (!$xml) {
    echo " Error: Could not parse XML.\n";
    echo "--- RESPONSE START (500 chars) ---\n";
    echo substr($rss_content, 0, 500) . "\n";
    echo "--- RESPONSE END ---\n";
    die();
}

echo " Processing jobs...\n";
$count = 0;
$skipped = 0;

foreach ($xml->channel->item as $item) {
    $guid = (string)$item->guid;
    $title_raw = (string)$item->title; // "Company Name: Job Title"
    $link = (string)$item->link;
    $pubDate = (string)$item->pubDate;
    $description = (string)$item->description;
    
    // Parse Company and Title
    $parts = explode(":", $title_raw, 2);
    $company_name = trim($parts[0] ?? 'Unknown');
    $job_title = trim($parts[1] ?? $title_raw);
    
    // Extract Location from title or description if possible
    // WWR usually has location in brackets or separate field, but RSS might just have it in title or desc
    $location = "Remote";
    if (preg_match('/\((.*?)\)/', $title_raw, $matches)) {
        $location = $matches[1];
    }

    // 2. Strict Filtering
    $should_skip = false;
    foreach ($skip_keywords as $keyword) {
        if (stripos($location, $keyword) !== false || stripos($title_raw, $keyword) !== false) {
            $should_skip = true;
            break;
        }
    }

    $is_global = false;
    foreach ($keep_keywords as $keyword) {
        if (stripos($location, $keyword) !== false || stripos($title_raw, $keyword) !== false) {
            $is_global = true;
            break;
        }
    }

    if ($should_skip && !$is_global) {
        $skipped++;
        continue;
    }

    // 3. Database Check (Duplicate)
    $stmt = $pdo->prepare("SELECT job_id FROM jobs WHERE guid = ?");
    $stmt->execute([$guid]);
    if ($stmt->fetch()) {
        continue;
    }

    // 4. Shadow Company Logic
    $stmt = $pdo->prepare("SELECT company_id FROM companies WHERE name = ?");
    $stmt->execute([$company_name]);
    $company = $stmt->fetch();
    
    if (!$company) {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $company_name), '-'));
        // Assign shadow companies to a default user (ID 1)
        $stmt = $pdo->prepare("INSERT INTO companies (name, slug, user_id, is_verified) VALUES (?, ?, 1, 0)");
        $stmt->execute([$company_name, $slug]);
        $company_id = $pdo->lastInsertId();
    } else {
        $company_id = $company['company_id'];
    }

    // 5. Category Mapping (Dynamic Lookup)
    $category_id = 1; // Default to Technology
    $match_cat = 'Technology';
    foreach ($cat_map as $wwr_cat => $jm_cat_id) {
        if (stripos($description, $wwr_cat) !== false || stripos($job_title, $wwr_cat) !== false) {
            $match_cat = $wwr_cat;
            break;
        }
    }
    
    // Convert WWR cat to JM cat name
    $jm_cat_name = match($match_cat) {
        'Design' => 'Design',
        'Marketing' => 'Marketing',
        'Customer Support' => 'Customer Service',
        'Finance' => 'Finance',
        default => 'Technology'
    };

    $stmt = $pdo->prepare("SELECT category_id FROM job_categories WHERE name LIKE ? LIMIT 1");
    $stmt->execute(["%$jm_cat_name%"]);
    $cat = $stmt->fetch();
    if ($cat) {
        $category_id = $cat['category_id'];
    }

    // 6. Salary Extraction (Heuristic)
    $salary_min = null;
    $salary_max = null;
    if (preg_match('/\$(\d{2,3}),?(\d{3})/', $description, $m)) {
        $val = (int)($m[1] . $m[2]);
        $salary_min = $val;
        $salary_max = $val * 1.2; // Estimation
    }

    // 6b. Get US Country ID for USD currency
    $country_id = 1; // Fallback
    $stmt = $pdo->prepare("SELECT country_id FROM countries WHERE iso_code = 'US' LIMIT 1");
    $stmt->execute();
    $usCountry = $stmt->fetch();
    if ($usCountry) {
        $country_id = $usCountry['country_id'];
    } else {
        // Create US if not exists
        $stmt = $pdo->prepare("INSERT INTO countries (name, iso_code, currency_code, currency_symbol, phone_code, flag_emoji, is_active) VALUES ('United States', 'US', 'USD', '$', '1', '', 1)");
        $stmt->execute();
        $country_id = $pdo->lastInsertId();
    }

    // 7. Insert Job
    $stmt = $pdo->prepare("
        INSERT INTO jobs (
            guid, source, company_id, title, description, 
            original_location, city, country_id, category_id, job_type, 
            salary_min, salary_max, apply_link, posted_at, is_active
        ) VALUES (
            :guid, 'WWR', :company_id, :title, :description, 
            :location, :city, :country_id, :category_id, 'Remote', 
            :salary_min, :salary_max, :link, :posted_at, 1
        )
    ");
    
    $posted_at = date('Y-m-d H:i:s', strtotime($pubDate));
    $truncated_title = substr($job_title, 0, 200);
    
    $stmt->execute([
        'guid' => $guid,
        'company_id' => $company_id,
        'title' => $truncated_title,
        'description' => $description,
        'location' => $location,
        'city' => "Remote",
        'country_id' => $country_id,
        'category_id' => (int)$category_id,
        'salary_min' => $salary_min,
        'salary_max' => $salary_max,
        'link' => $link,
        'posted_at' => $posted_at
    ]);

    $count++;
}

echo " Success: Imported {$count} jobs from WWR. (Skipped {$skipped} due to geo-filters)\n";
