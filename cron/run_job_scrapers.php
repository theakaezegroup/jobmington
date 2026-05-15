<?php
/**
 * Jobmington - automated job scraper runner
 *
 * Intended for cron on the VPS:
 * php /var/www/jobmington/cron/run_job_scrapers.php --limit=80
 */

define('JOBMINGTON', true);
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/database.php';

date_default_timezone_set(getenv('APP_TIMEZONE') ?: 'Africa/Lagos');

$pdo = db();
$options = getopt('', ['source::', 'limit::']);
$selectedSource = strtolower(trim((string) ($options['source'] ?? 'all')));
$limit = max(1, min(200, (int) ($options['limit'] ?? getenv('JOB_SCRAPER_LIMIT') ?: 80)));
$logDir = dirname(__DIR__) . '/logs';
$logFile = $logDir . '/job-scraper.log';
$statusFile = $logDir . '/job-scraper-status.json';
$runStartedAt = date('c');
$runStartedMicro = microtime(true);

if (!is_dir($logDir)) {
    @mkdir($logDir, 0755, true);
}

function jm_scraper_log(string $message): void {
    global $logFile;
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
    echo $line;
    @file_put_contents($logFile, $line, FILE_APPEND);
}

function jm_scraper_write_status(array $status): void {
    global $statusFile;
    @file_put_contents($statusFile, json_encode($status, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

function jm_scraper_slug(string $value): string {
    $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $value), '-'));
    return $slug !== '' ? substr($slug, 0, 180) : 'item-' . substr(sha1($value . microtime(true)), 0, 10);
}

function jm_scraper_fetch(string $url, array $headers = []): string {
    if (!function_exists('curl_init')) {
        throw new RuntimeException('PHP curl extension is required for job scraping.');
    }

    $userAgent = getenv('JOB_SCRAPER_USER_AGENT') ?: 'JobmingtonBot/1.0 (+https://jobmington.com/contact)';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERAGENT => $userAgent,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_ENCODING => '',
        CURLOPT_CONNECTTIMEOUT => 12,
        CURLOPT_TIMEOUT => 35,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    $body = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($body === false || $httpCode < 200 || $httpCode >= 300) {
        throw new RuntimeException('Fetch failed for ' . $url . ($error ? ': ' . $error : ' HTTP ' . $httpCode));
    }

    return (string) $body;
}

function jm_scraper_owner_user_id(PDO $pdo): int {
    $stmt = $pdo->query("SELECT user_id FROM users WHERE user_type = 'admin' AND is_active = 1 ORDER BY user_id LIMIT 1");
    $id = (int) $stmt->fetchColumn();
    if ($id > 0) {
        return $id;
    }

    $stmt = $pdo->query("SELECT user_id FROM users ORDER BY user_id LIMIT 1");
    return max(1, (int) $stmt->fetchColumn());
}

function jm_scraper_country_id(PDO $pdo, string $location): int {
    $locationLower = strtolower($location);
    $countryName = 'United States';
    $isoCode = 'US';
    $currencyCode = 'USD';
    $currencySymbol = '$';

    if (str_contains($locationLower, 'nigeria') || str_contains($locationLower, 'lagos') || str_contains($locationLower, 'abuja') || str_contains($locationLower, 'africa')) {
        $countryName = 'Nigeria';
        $isoCode = 'NG';
        $currencyCode = 'NGN';
        $currencySymbol = 'NGN';
    } elseif (str_contains($locationLower, 'ghana')) {
        $countryName = 'Ghana';
        $isoCode = 'GH';
        $currencyCode = 'GHS';
        $currencySymbol = 'GHS';
    } elseif (str_contains($locationLower, 'kenya')) {
        $countryName = 'Kenya';
        $isoCode = 'KE';
        $currencyCode = 'KES';
        $currencySymbol = 'KES';
    } elseif (str_contains($locationLower, 'south africa')) {
        $countryName = 'South Africa';
        $isoCode = 'ZA';
        $currencyCode = 'ZAR';
        $currencySymbol = 'ZAR';
    }

    $stmt = $pdo->prepare('SELECT country_id FROM countries WHERE iso_code = ? OR name = ? LIMIT 1');
    $stmt->execute([$isoCode, $countryName]);
    $id = (int) $stmt->fetchColumn();
    if ($id > 0) {
        return $id;
    }

    $stmt = $pdo->prepare('INSERT INTO countries (name, iso_code, currency_code, currency_symbol, is_active) VALUES (?, ?, ?, ?, 1)');
    $stmt->execute([$countryName, $isoCode, $currencyCode, $currencySymbol]);
    return (int) $pdo->lastInsertId();
}

function jm_scraper_category_id(PDO $pdo, string $title, string $description, array $tags): int {
    $haystack = strtolower($title . ' ' . $description . ' ' . implode(' ', $tags));
    $map = [
        'Technology' => ['developer', 'engineer', 'software', 'php', 'python', 'javascript', 'devops', 'data', 'security', 'ai', 'backend', 'frontend', 'full-stack'],
        'Design' => ['designer', 'figma', 'ui', 'ux', 'product design', 'creative'],
        'Marketing' => ['marketing', 'seo', 'content', 'copywriter', 'growth', 'social media'],
        'Customer Service' => ['support', 'customer', 'success', 'service'],
        'Sales' => ['sales', 'account executive', 'business development'],
        'Finance' => ['finance', 'accounting', 'payroll', 'bookkeeper'],
        'Operations' => ['operations', 'project manager', 'program manager', 'assistant', 'coordinator'],
    ];

    foreach ($map as $categoryName => $needles) {
        foreach ($needles as $needle) {
            if (str_contains($haystack, $needle)) {
                $stmt = $pdo->prepare('SELECT category_id FROM job_categories WHERE name LIKE ? LIMIT 1');
                $stmt->execute(['%' . $categoryName . '%']);
                $id = (int) $stmt->fetchColumn();
                if ($id > 0) {
                    return $id;
                }
            }
        }
    }

    $stmt = $pdo->query('SELECT category_id FROM job_categories ORDER BY category_id LIMIT 1');
    return max(1, (int) $stmt->fetchColumn());
}

function jm_scraper_company_id(PDO $pdo, string $companyName, int $ownerUserId): int {
    $companyName = trim($companyName) !== '' ? trim($companyName) : 'Hiring Company';
    $stmt = $pdo->prepare('SELECT company_id FROM companies WHERE name = ? LIMIT 1');
    $stmt->execute([$companyName]);
    $id = (int) $stmt->fetchColumn();
    if ($id > 0) {
        return $id;
    }

    $baseSlug = jm_scraper_slug($companyName);
    $slug = $baseSlug;
    for ($i = 2; $i < 25; $i++) {
        $stmt = $pdo->prepare('SELECT company_id FROM companies WHERE slug = ? LIMIT 1');
        $stmt->execute([$slug]);
        if (!$stmt->fetchColumn()) {
            break;
        }
        $slug = $baseSlug . '-' . $i;
    }

    $stmt = $pdo->prepare('INSERT INTO companies (user_id, name, slug, is_verified, created_at, updated_at) VALUES (?, ?, ?, 0, NOW(), NOW())');
    $stmt->execute([$ownerUserId, $companyName, $slug]);
    return (int) $pdo->lastInsertId();
}

function jm_scraper_allowed_job(array $job): bool {
    $text = strtolower(($job['title'] ?? '') . ' ' . ($job['location'] ?? '') . ' ' . substr(strip_tags((string) ($job['description'] ?? '')), 0, 700));
    $skip = ['usa only', 'us only', 'u.s. only', 'united states only', 'canada only', 'uk only', 'north america only', 'latam only', 'must be located in the us'];
    $keep = ['anywhere', 'worldwide', 'global', 'emea', 'africa', 'nigeria', 'ghana', 'kenya', 'south africa', 'remote'];

    $hasSkip = false;
    foreach ($skip as $needle) {
        if (str_contains($text, $needle)) {
            $hasSkip = true;
            break;
        }
    }

    if (!$hasSkip) {
        return true;
    }

    foreach ($keep as $needle) {
        if (str_contains($text, $needle)) {
            return true;
        }
    }

    return false;
}

function jm_scraper_duplicate_exists(PDO $pdo, array $job, int $companyId): bool {
    $stmt = $pdo->prepare('
        SELECT job_id
        FROM jobs
        WHERE guid = ?
           OR apply_link = ?
           OR (company_id = ? AND title = ? AND posted_at >= DATE_SUB(NOW(), INTERVAL 60 DAY))
        LIMIT 1
    ');
    $stmt->execute([
        $job['guid'],
        $job['apply_link'],
        $companyId,
        substr($job['title'], 0, 200),
    ]);

    return (bool) $stmt->fetchColumn();
}

function jm_scraper_insert_job(PDO $pdo, array $job, int $ownerUserId): string {
    if (!jm_scraper_allowed_job($job)) {
        return 'skipped_geo';
    }

    $companyId = jm_scraper_company_id($pdo, $job['company'], $ownerUserId);
    if (jm_scraper_duplicate_exists($pdo, $job, $companyId)) {
        return 'duplicate';
    }

    $categoryId = jm_scraper_category_id($pdo, $job['title'], $job['description'], $job['tags'] ?? []);
    $countryId = jm_scraper_country_id($pdo, $job['location'] ?? 'Remote');
    $title = substr(trim($job['title']), 0, 200);
    $slug = jm_scraper_slug($title . '-' . $job['company'] . '-' . $job['source']);
    $description = trim($job['description']) !== '' ? trim($job['description']) : 'See the application link for full details.';
    $postedAt = !empty($job['posted_at']) && strtotime($job['posted_at']) ? date('Y-m-d H:i:s', strtotime($job['posted_at'])) : date('Y-m-d H:i:s');
    $expiresAt = date('Y-m-d', strtotime('+45 days'));
    $salaryMin = isset($job['salary_min']) && $job['salary_min'] !== '' ? (float) $job['salary_min'] : null;
    $salaryMax = isset($job['salary_max']) && $job['salary_max'] !== '' ? (float) $job['salary_max'] : null;

    $stmt = $pdo->prepare('
        INSERT INTO jobs (
            guid, source, company_id, category_id, title, slug, description, apply_link,
            original_location, city, country_id, job_type, experience_level,
            salary_min, salary_max, salary_currency, show_salary, posted_at, expires_at, is_active
        ) VALUES (
            :guid, :source, :company_id, :category_id, :title, :slug, :description, :apply_link,
            :original_location, :city, :country_id, :job_type, :experience_level,
            :salary_min, :salary_max, :salary_currency, :show_salary, :posted_at, :expires_at, 1
        )
    ');

    $stmt->execute([
        'guid' => $job['guid'],
        'source' => $job['source'],
        'company_id' => $companyId,
        'category_id' => $categoryId,
        'title' => $title,
        'slug' => $slug,
        'description' => $description,
        'apply_link' => $job['apply_link'],
        'original_location' => $job['location'] ?? 'Remote',
        'city' => 'Remote',
        'country_id' => $countryId,
        'job_type' => 'Remote',
        'experience_level' => $job['experience_level'] ?? 'Mid',
        'salary_min' => $salaryMin,
        'salary_max' => $salaryMax,
        'salary_currency' => $job['salary_currency'] ?? 'USD',
        'show_salary' => ($salaryMin || $salaryMax) ? 1 : 0,
        'posted_at' => $postedAt,
        'expires_at' => $expiresAt,
    ]);

    return 'inserted';
}

function jm_scraper_wwr_jobs(int $limit): array {
    $body = jm_scraper_fetch('https://weworkremotely.com/remote-jobs.rss', ['Accept: application/rss+xml,text/xml']);
    libxml_use_internal_errors(true);
    $xml = simplexml_load_string($body, null, LIBXML_NOCDATA);
    if (!$xml || empty($xml->channel->item)) {
        throw new RuntimeException('Could not parse WWR RSS.');
    }

    $jobs = [];
    foreach ($xml->channel->item as $item) {
        if (count($jobs) >= $limit) {
            break;
        }

        $rawTitle = trim((string) $item->title);
        $parts = explode(':', $rawTitle, 2);
        $company = trim($parts[0] ?? 'Hiring Company');
        $title = trim($parts[1] ?? $rawTitle);
        $description = (string) $item->description;
        $location = 'Remote';
        if (preg_match('/\((.*?)\)/', $rawTitle, $match)) {
            $location = trim($match[1]);
        }

        $salaryMin = null;
        $salaryMax = null;
        if (preg_match_all('/\$(\d{2,3})(?:,?(\d{3}))?/', strip_tags($description), $matches)) {
            $values = [];
            foreach ($matches[0] as $value) {
                $values[] = (float) preg_replace('/[^\d.]/', '', $value);
            }
            if (!empty($values)) {
                $salaryMin = min($values);
                $salaryMax = max($values);
            }
        }

        $externalId = trim((string) $item->guid) !== '' ? (string) $item->guid : (string) $item->link;

        $jobs[] = [
            'source' => 'WWR',
            'guid' => 'wwr:' . sha1($externalId),
            'company' => $company,
            'title' => $title,
            'description' => $description,
            'location' => $location,
            'apply_link' => (string) $item->link,
            'posted_at' => (string) $item->pubDate,
            'salary_min' => $salaryMin,
            'salary_max' => $salaryMax,
            'salary_currency' => 'USD',
            'tags' => [(string) $item->category],
        ];
    }

    return $jobs;
}

function jm_scraper_remoteok_jobs(int $limit): array {
    $body = jm_scraper_fetch('https://remoteok.com/api', ['Accept: application/json']);
    $rows = json_decode($body, true);
    if (!is_array($rows)) {
        throw new RuntimeException('Could not parse RemoteOK JSON.');
    }

    $jobs = [];
    foreach ($rows as $row) {
        if (count($jobs) >= $limit) {
            break;
        }
        if (empty($row['position']) || empty($row['company'])) {
            continue;
        }

        $applyLink = $row['apply_url'] ?? $row['url'] ?? '';
        if ($applyLink === '') {
            continue;
        }
        if (str_starts_with($applyLink, '/')) {
            $applyLink = 'https://remoteok.com' . $applyLink;
        }

        $jobs[] = [
            'source' => 'RemoteOK',
            'guid' => 'remoteok:' . ($row['id'] ?? sha1($applyLink)),
            'company' => (string) $row['company'],
            'title' => (string) $row['position'],
            'description' => (string) ($row['description'] ?? ''),
            'location' => (string) ($row['location'] ?? 'Remote'),
            'apply_link' => $applyLink,
            'posted_at' => (string) ($row['date'] ?? 'now'),
            'salary_min' => $row['salary_min'] ?? null,
            'salary_max' => $row['salary_max'] ?? null,
            'salary_currency' => 'USD',
            'tags' => is_array($row['tags'] ?? null) ? $row['tags'] : [],
        ];
    }

    return $jobs;
}

$lockPath = sys_get_temp_dir() . '/jobmington-job-scraper.lock';
$lockHandle = fopen($lockPath, 'c');
if (!$lockHandle || !flock($lockHandle, LOCK_EX | LOCK_NB)) {
    jm_scraper_log('Another scraper run is already active. Exiting.');
    exit(0);
}

$sources = [
    'wwr' => 'jm_scraper_wwr_jobs',
    'remoteok' => 'jm_scraper_remoteok_jobs',
];

if ($selectedSource !== 'all') {
    $sources = array_filter($sources, static fn($key) => $key === $selectedSource, ARRAY_FILTER_USE_KEY);
}

if (empty($sources)) {
    jm_scraper_log('No matching scraper source selected.');
    exit(1);
}

$ownerUserId = jm_scraper_owner_user_id($pdo);
$totals = ['inserted' => 0, 'duplicate' => 0, 'skipped_geo' => 0, 'failed' => 0];
$sourceResults = [];

jm_scraper_log('Starting job scraper. sources=' . implode(',', array_keys($sources)) . ' limit=' . $limit);

foreach ($sources as $sourceName => $fetcher) {
    $sourceResults[$sourceName] = ['fetched' => 0, 'inserted' => 0, 'duplicate' => 0, 'skipped_geo' => 0, 'failed' => 0, 'error' => null];
    try {
        $jobs = $fetcher($limit);
        $sourceResults[$sourceName]['fetched'] = count($jobs);
        jm_scraper_log($sourceName . ': fetched ' . count($jobs) . ' jobs.');

        foreach ($jobs as $job) {
            try {
                $status = jm_scraper_insert_job($pdo, $job, $ownerUserId);
                $totals[$status] = ($totals[$status] ?? 0) + 1;
                $sourceResults[$sourceName][$status] = ($sourceResults[$sourceName][$status] ?? 0) + 1;
            } catch (Throwable $e) {
                $totals['failed']++;
                $sourceResults[$sourceName]['failed']++;
                jm_scraper_log($sourceName . ': failed "' . ($job['title'] ?? 'Untitled') . '" - ' . $e->getMessage());
            }
        }
    } catch (Throwable $e) {
        $totals['failed']++;
        $sourceResults[$sourceName]['failed']++;
        $sourceResults[$sourceName]['error'] = $e->getMessage();
        jm_scraper_log($sourceName . ': source failed - ' . $e->getMessage());
    }
}

jm_scraper_log('Finished job scraper. inserted=' . $totals['inserted'] . ' duplicate=' . $totals['duplicate'] . ' skipped_geo=' . $totals['skipped_geo'] . ' failed=' . $totals['failed']);
jm_scraper_write_status([
    'started_at' => $runStartedAt,
    'finished_at' => date('c'),
    'duration_seconds' => round(microtime(true) - $runStartedMicro, 2),
    'selected_source' => $selectedSource,
    'limit' => $limit,
    'totals' => $totals,
    'sources' => $sourceResults,
]);

if ($lockHandle) {
    flock($lockHandle, LOCK_UN);
    fclose($lockHandle);
}
