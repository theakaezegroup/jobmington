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
// Location to country. Shared with the backfill, which has to reach the same
// verdict on rows already in the table.
require_once __DIR__ . '/../includes/job_locations.php';

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

/** The country row id for a location, or null when the location names none. */
function jm_scraper_country_id(PDO $pdo, string $location): ?int {
    $country = jm_scraper_country_of($location);
    if ($country === null) {
        return null;
    }
    [$countryName, $isoCode, $currencyCode, $currencySymbol] = $country;

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

    $stmt = $pdo->prepare('INSERT INTO companies (user_id, name, slug, is_verified, created_at) VALUES (?, ?, ?, 0, NOW())');
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

function jm_scraper_jobs_columns(PDO $pdo): array {
    static $cols = null;
    if ($cols === null) {
        $cols = $pdo->query('SHOW COLUMNS FROM jobs')->fetchAll(PDO::FETCH_COLUMN);
    }
    return $cols;
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

    // Build the row from every field the scraper knows about, then keep only the
    // columns that actually exist on this database. Production (VPS, the source of
    // truth) lacks some columns the local dev DB has (slug, experience_level,
    // salary_currency, show_salary), so a fixed column list would break there.
    $candidate = [
        'guid' => $job['guid'],
        'source' => $job['source'],
        'company_id' => $companyId,
        'user_id' => $ownerUserId,
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
        'is_active' => 1,
    ];

    $available = jm_scraper_jobs_columns($pdo);
    $row = array_filter($candidate, static fn($col) => in_array($col, $available, true), ARRAY_FILTER_USE_KEY);

    $columns = array_keys($row);
    $placeholders = array_map(static fn($col) => ':' . $col, $columns);
    $sql = 'INSERT INTO jobs (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')';
    $pdo->prepare($sql)->execute($row);

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

function jm_scraper_remotive_jobs(int $limit): array {
    $body = jm_scraper_fetch('https://remotive.com/api/remote-jobs?limit=' . $limit, ['Accept: application/json']);
    $data = json_decode($body, true);
    if (!is_array($data) || empty($data['jobs'])) {
        throw new RuntimeException('Could not parse Remotive JSON.');
    }

    $jobs = [];
    foreach ($data['jobs'] as $row) {
        if (count($jobs) >= $limit) {
            break;
        }
        $applyLink = (string) ($row['url'] ?? '');
        if (empty($row['title']) || empty($row['company_name']) || $applyLink === '') {
            continue;
        }

        $salaryMin = null;
        $salaryMax = null;
        if (!empty($row['salary']) && preg_match_all('/(\d[\d,]{2,})/', (string) $row['salary'], $m)) {
            $values = array_map(static fn($v) => (float) str_replace(',', '', $v), $m[1]);
            if ($values) {
                $salaryMin = min($values);
                $salaryMax = max($values);
            }
        }

        $jobs[] = [
            'source' => 'Remotive',
            'guid' => 'remotive:' . ($row['id'] ?? sha1($applyLink)),
            'company' => (string) $row['company_name'],
            'title' => (string) $row['title'],
            'description' => (string) ($row['description'] ?? ''),
            'location' => (string) ($row['candidate_required_location'] ?? 'Remote'),
            'apply_link' => $applyLink,
            'posted_at' => (string) ($row['publication_date'] ?? 'now'),
            'salary_min' => $salaryMin,
            'salary_max' => $salaryMax,
            'salary_currency' => 'USD',
            'tags' => is_array($row['tags'] ?? null) ? $row['tags'] : [],
        ];
    }

    return $jobs;
}

function jm_scraper_jobicy_jobs(int $limit): array {
    $count = max(1, min(50, $limit));
    $body = jm_scraper_fetch('https://jobicy.com/api/v2/remote-jobs?count=' . $count, ['Accept: application/json']);
    $data = json_decode($body, true);
    if (!is_array($data) || empty($data['jobs'])) {
        throw new RuntimeException('Could not parse Jobicy JSON.');
    }

    $jobs = [];
    foreach ($data['jobs'] as $row) {
        if (count($jobs) >= $limit) {
            break;
        }
        $applyLink = (string) ($row['url'] ?? '');
        if (empty($row['jobTitle']) || empty($row['companyName']) || $applyLink === '') {
            continue;
        }

        $jobs[] = [
            'source' => 'Jobicy',
            'guid' => 'jobicy:' . ($row['id'] ?? sha1($applyLink)),
            'company' => (string) $row['companyName'],
            'title' => (string) $row['jobTitle'],
            'description' => (string) ($row['jobDescription'] ?? $row['jobExcerpt'] ?? ''),
            'location' => (string) ($row['jobGeo'] ?? 'Remote'),
            'apply_link' => $applyLink,
            'posted_at' => (string) ($row['pubDate'] ?? 'now'),
            'salary_min' => isset($row['annualSalaryMin']) && $row['annualSalaryMin'] !== '' ? (float) $row['annualSalaryMin'] : null,
            'salary_max' => isset($row['annualSalaryMax']) && $row['annualSalaryMax'] !== '' ? (float) $row['annualSalaryMax'] : null,
            'salary_currency' => (string) ($row['salaryCurrency'] ?? 'USD'),
            'tags' => array_filter([(string) ($row['jobIndustry'][0] ?? ''), (string) ($row['jobType'][0] ?? '')]),
        ];
    }

    return $jobs;
}

function jm_scraper_arbeitnow_jobs(int $limit): array {
    $body = jm_scraper_fetch('https://www.arbeitnow.com/api/job-board-api', ['Accept: application/json']);
    $data = json_decode($body, true);
    if (!is_array($data) || empty($data['data'])) {
        throw new RuntimeException('Could not parse Arbeitnow JSON.');
    }

    $jobs = [];
    foreach ($data['data'] as $row) {
        if (count($jobs) >= $limit) {
            break;
        }
        $applyLink = (string) ($row['url'] ?? '');
        if (empty($row['title']) || empty($row['company_name']) || $applyLink === '') {
            continue;
        }

        $location = (string) ($row['location'] ?? 'Remote');
        if (!empty($row['remote'])) {
            $location = $location !== '' ? $location . ' (Remote)' : 'Remote';
        }

        $jobs[] = [
            'source' => 'Arbeitnow',
            'guid' => 'arbeitnow:' . ($row['slug'] ?? sha1($applyLink)),
            'company' => (string) $row['company_name'],
            'title' => (string) $row['title'],
            'description' => (string) ($row['description'] ?? ''),
            'location' => $location,
            'apply_link' => $applyLink,
            'posted_at' => !empty($row['created_at']) ? date('c', (int) $row['created_at']) : 'now',
            'salary_min' => null,
            'salary_max' => null,
            'salary_currency' => 'EUR',
            'tags' => array_merge(
                is_array($row['tags'] ?? null) ? $row['tags'] : [],
                is_array($row['job_types'] ?? null) ? $row['job_types'] : []
            ),
        ];
    }

    return $jobs;
}

function jm_scraper_himalayas_jobs(int $limit): array {
    $body = jm_scraper_fetch('https://himalayas.app/jobs/api?limit=' . $limit, ['Accept: application/json']);
    $data = json_decode($body, true);
    if (!is_array($data) || empty($data['jobs'])) {
        throw new RuntimeException('Could not parse Himalayas JSON.');
    }

    $jobs = [];
    foreach ($data['jobs'] as $row) {
        if (count($jobs) >= $limit) {
            break;
        }
        $applyLink = (string) ($row['applicationLink'] ?? $row['guid'] ?? '');
        if (empty($row['title']) || empty($row['companyName']) || $applyLink === '') {
            continue;
        }

        $location = 'Remote';
        if (!empty($row['locationRestrictions']) && is_array($row['locationRestrictions'])) {
            $location = implode(', ', array_slice($row['locationRestrictions'], 0, 3));
        }
        $seniorityRaw = (!empty($row['seniority']) && is_array($row['seniority'])) ? strtolower((string) $row['seniority'][0]) : '';
        $seniority = 'Mid';
        if (preg_match('/intern|junior|entry|graduate/', $seniorityRaw)) {
            $seniority = 'Entry';
        } elseif (preg_match('/senior|lead|staff|principal/', $seniorityRaw)) {
            $seniority = 'Senior';
        } elseif (preg_match('/manager|director|head|chief|vp|executive|president/', $seniorityRaw)) {
            $seniority = 'Executive';
        }

        $jobs[] = [
            'source' => 'Himalayas',
            'guid' => 'himalayas:' . ($row['guid'] ?? sha1($applyLink)),
            'company' => (string) $row['companyName'],
            'title' => (string) $row['title'],
            'description' => (string) ($row['description'] ?? $row['excerpt'] ?? ''),
            'location' => $location !== '' ? $location : 'Remote',
            'apply_link' => $applyLink,
            'posted_at' => !empty($row['pubDate']) ? date('c', (int) $row['pubDate']) : 'now',
            'experience_level' => $seniority,
            'salary_min' => isset($row['minSalary']) && $row['minSalary'] !== '' ? (float) $row['minSalary'] : null,
            'salary_max' => isset($row['maxSalary']) && $row['maxSalary'] !== '' ? (float) $row['maxSalary'] : null,
            'salary_currency' => (string) ($row['salaryCurrency'] ?? 'USD'),
            'tags' => is_array($row['categories'] ?? null) ? $row['categories'] : [],
        ];
    }

    return $jobs;
}

function jm_scraper_adzuna_jobs(int $limit): array {
    $appId = trim((string) getenv('ADZUNA_APP_ID'));
    $appKey = trim((string) getenv('ADZUNA_APP_KEY'));
    if ($appId === '' || $appKey === '') {
        throw new RuntimeException('Adzuna credentials missing (set ADZUNA_APP_ID and ADZUNA_APP_KEY).');
    }

    $countries = array_filter(array_map('trim', explode(',', (string) getenv('ADZUNA_COUNTRIES') ?: 'za,gb')));
    if (empty($countries)) {
        $countries = ['za'];
    }
    $perCountry = max(1, (int) ceil($limit / count($countries)));

    $jobs = [];
    foreach ($countries as $country) {
        if (count($jobs) >= $limit) {
            break;
        }
        $country = strtolower(preg_replace('/[^a-z]/i', '', $country));
        if ($country === '') {
            continue;
        }

        $query = http_build_query([
            'app_id' => $appId,
            'app_key' => $appKey,
            'results_per_page' => min(50, $perCountry),
            'what' => 'remote',
            'content-type' => 'application/json',
        ]);
        $url = 'https://api.adzuna.com/v1/api/jobs/' . $country . '/search/1?' . $query;

        try {
            $body = jm_scraper_fetch($url, ['Accept: application/json']);
        } catch (Throwable $e) {
            jm_scraper_log('adzuna: ' . $country . ' fetch failed - ' . $e->getMessage());
            continue;
        }

        $data = json_decode($body, true);
        if (!is_array($data) || empty($data['results'])) {
            continue;
        }

        foreach ($data['results'] as $row) {
            if (count($jobs) >= $limit) {
                break;
            }
            $applyLink = (string) ($row['redirect_url'] ?? '');
            if (empty($row['title']) || $applyLink === '') {
                continue;
            }

            $jobs[] = [
                'source' => 'Adzuna',
                'guid' => 'adzuna:' . ($row['id'] ?? sha1($applyLink)),
                'company' => (string) ($row['company']['display_name'] ?? 'Hiring Company'),
                'title' => (string) $row['title'],
                'description' => (string) ($row['description'] ?? ''),
                'location' => (string) ($row['location']['display_name'] ?? strtoupper($country)),
                'apply_link' => $applyLink,
                'posted_at' => (string) ($row['created'] ?? 'now'),
                'salary_min' => isset($row['salary_min']) ? (float) $row['salary_min'] : null,
                'salary_max' => isset($row['salary_max']) ? (float) $row['salary_max'] : null,
                'salary_currency' => $country === 'za' ? 'ZAR' : ($country === 'gb' ? 'GBP' : 'USD'),
                'tags' => array_filter([(string) ($row['category']['label'] ?? '')]),
            ];
        }
    }

    return $jobs;
}

function jm_scraper_post_json(string $url, array $payload, array $headers = []): string {
    if (!function_exists('curl_init')) {
        throw new RuntimeException('PHP curl extension is required for job scraping.');
    }

    $userAgent = getenv('JOB_SCRAPER_USER_AGENT') ?: 'JobmingtonBot/1.0 (+https://jobmington.com/contact)';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_USERAGENT => $userAgent,
        CURLOPT_HTTPHEADER => array_merge(['Content-Type: application/json', 'Accept: application/json'], $headers),
        CURLOPT_CONNECTTIMEOUT => 12,
        CURLOPT_TIMEOUT => 40,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    $body = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($body === false || $httpCode < 200 || $httpCode >= 300) {
        throw new RuntimeException('POST failed for ' . $url . ($error ? ': ' . $error : ' HTTP ' . $httpCode));
    }

    return (string) $body;
}

function jm_scraper_themuse_jobs(int $limit): array {
    $apiKey = trim((string) getenv('THEMUSE_API_KEY'));
    $jobs = [];
    for ($page = 1; $page <= 5 && count($jobs) < $limit; $page++) {
        $params = ['page' => $page];
        if ($apiKey !== '') {
            $params['api_key'] = $apiKey;
        }
        $body = jm_scraper_fetch('https://www.themuse.com/api/public/jobs?' . http_build_query($params), ['Accept: application/json']);
        $data = json_decode($body, true);
        if (!is_array($data) || empty($data['results'])) {
            break;
        }

        foreach ($data['results'] as $row) {
            if (count($jobs) >= $limit) {
                break;
            }
            $applyLink = (string) ($row['refs']['landing_page'] ?? '');
            if (empty($row['name']) || $applyLink === '') {
                continue;
            }
            $location = !empty($row['locations']) ? (string) ($row['locations'][0]['name'] ?? 'Flexible') : 'Flexible';
            $level = !empty($row['levels']) ? strtolower((string) ($row['levels'][0]['name'] ?? '')) : '';
            $experience = 'Mid';
            if (preg_match('/intern|entry|junior/', $level)) {
                $experience = 'Entry';
            } elseif (preg_match('/senior|lead/', $level)) {
                $experience = 'Senior';
            } elseif (preg_match('/manager|director|executive/', $level)) {
                $experience = 'Executive';
            }

            $jobs[] = [
                'source' => 'TheMuse',
                'guid' => 'themuse:' . ($row['id'] ?? sha1($applyLink)),
                'company' => (string) ($row['company']['name'] ?? 'Hiring Company'),
                'title' => (string) $row['name'],
                'description' => (string) ($row['contents'] ?? ''),
                'location' => $location,
                'apply_link' => $applyLink,
                'posted_at' => (string) ($row['publication_date'] ?? 'now'),
                'experience_level' => $experience,
                'salary_min' => null,
                'salary_max' => null,
                'salary_currency' => 'USD',
                'tags' => array_map(static fn($c) => (string) ($c['name'] ?? ''), is_array($row['categories'] ?? null) ? $row['categories'] : []),
            ];
        }
    }

    return $jobs;
}

function jm_scraper_devitjobs_jobs(int $limit): array {
    // DevITjobs UK exposes a public JSON feed (no auth) as a flat array.
    $body = jm_scraper_fetch('https://devitjobs.uk/api/jobsLight', ['Accept: application/json']);
    $rows = json_decode($body, true);
    if (!is_array($rows)) {
        throw new RuntimeException('Could not parse DevITjobs JSON.');
    }

    $jobs = [];
    foreach ($rows as $row) {
        if (count($jobs) >= $limit) {
            break;
        }
        if (!is_array($row)) {
            continue;
        }
        $slug = (string) ($row['jobUrl'] ?? '');
        $applyLink = (string) ($row['redirectJobUrl'] ?? '');
        if ($applyLink === '' && $slug !== '') {
            $applyLink = 'https://devitjobs.uk/jobs/' . $slug;
        }
        $title = (string) ($row['name'] ?? '');
        $company = (string) ($row['company'] ?? '');
        if ($title === '' || $company === '' || $applyLink === '') {
            continue;
        }

        $location = (string) ($row['actualCity'] ?? $row['cityCategory'] ?? 'United Kingdom');
        if (!empty($row['workplace']) && stripos((string) $row['workplace'], 'remote') !== false) {
            $location = $location !== '' ? $location . ' (Remote)' : 'Remote';
        }
        $expLevelRaw = strtolower((string) ($row['expLevel'] ?? ''));
        $experience = 'Mid';
        if (preg_match('/intern|junior|entry|graduate/', $expLevelRaw)) {
            $experience = 'Entry';
        } elseif (preg_match('/senior|lead|principal/', $expLevelRaw)) {
            $experience = 'Senior';
        } elseif (preg_match('/manager|director|head|executive|cto/', $expLevelRaw)) {
            $experience = 'Executive';
        }

        $jobs[] = [
            'source' => 'DevITjobs',
            'guid' => 'devitjobs:' . ($row['_id'] ?? sha1($applyLink)),
            'company' => $company,
            'title' => $title,
            'description' => (string) ($row['name'] ?? '') . ' at ' . $company . '. See listing for full details.',
            'location' => $location !== '' ? $location : 'United Kingdom',
            'apply_link' => $applyLink,
            'posted_at' => (string) ($row['activeFrom'] ?? 'now'),
            'experience_level' => $experience,
            'salary_min' => isset($row['annualSalaryFrom']) && $row['annualSalaryFrom'] !== '' ? (float) $row['annualSalaryFrom'] : null,
            'salary_max' => isset($row['annualSalaryTo']) && $row['annualSalaryTo'] !== '' ? (float) $row['annualSalaryTo'] : null,
            'salary_currency' => 'GBP',
            'tags' => is_array($row['technologies'] ?? ($row['filterTags'] ?? null)) ? ($row['technologies'] ?? $row['filterTags']) : [],
        ];
    }

    return $jobs;
}

function jm_scraper_jooble_jobs(int $limit): array {
    $apiKey = trim((string) getenv('JOOBLE_API_KEY'));
    if ($apiKey === '') {
        throw new RuntimeException('Jooble API key missing (set JOOBLE_API_KEY).');
    }

    $payload = array_filter([
        'keywords' => (string) (getenv('JOOBLE_KEYWORDS') ?: 'remote'),
        'location' => (string) getenv('JOOBLE_LOCATION'),
        'page' => '1',
    ], static fn($v) => $v !== '' && $v !== null);

    $body = jm_scraper_post_json('https://jooble.org/api/' . rawurlencode($apiKey), $payload);
    $data = json_decode($body, true);
    if (!is_array($data) || empty($data['jobs'])) {
        throw new RuntimeException('Could not parse Jooble JSON.');
    }

    $jobs = [];
    foreach ($data['jobs'] as $row) {
        if (count($jobs) >= $limit) {
            break;
        }
        $applyLink = (string) ($row['link'] ?? '');
        if (empty($row['title']) || $applyLink === '') {
            continue;
        }

        $salaryMin = null;
        $salaryMax = null;
        if (!empty($row['salary']) && preg_match_all('/(\d[\d,]{2,})/', (string) $row['salary'], $m)) {
            $values = array_map(static fn($v) => (float) str_replace(',', '', $v), $m[1]);
            if ($values) {
                $salaryMin = min($values);
                $salaryMax = max($values);
            }
        }

        $jobs[] = [
            'source' => 'Jooble',
            'guid' => 'jooble:' . ($row['id'] ?? sha1($applyLink)),
            'company' => (string) ($row['company'] ?? 'Hiring Company'),
            'title' => (string) $row['title'],
            'description' => (string) ($row['snippet'] ?? ''),
            'location' => (string) ($row['location'] ?? 'Remote'),
            'apply_link' => $applyLink,
            'posted_at' => (string) ($row['updated'] ?? 'now'),
            'salary_min' => $salaryMin,
            'salary_max' => $salaryMax,
            'salary_currency' => 'USD',
            'tags' => array_filter([(string) ($row['type'] ?? '')]),
        ];
    }

    return $jobs;
}

function jm_scraper_theirstack_jobs(int $limit): array {
    $apiKey = trim((string) getenv('THEIRSTACK_API_KEY'));
    if ($apiKey === '') {
        throw new RuntimeException('TheirStack API key missing (set THEIRSTACK_API_KEY).');
    }

    $maxAge = max(1, (int) (getenv('THEIRSTACK_MAX_AGE_DAYS') ?: 7));
    $payload = [
        'page' => 0,
        'limit' => min(50, $limit),
        'posted_at_max_age_days' => $maxAge,
        'order_by' => [['field' => 'date_posted', 'desc' => true]],
        'remote' => true,
    ];

    $body = jm_scraper_post_json('https://api.theirstack.com/v1/jobs/search', $payload, [
        'Authorization: Bearer ' . $apiKey,
    ]);
    $data = json_decode($body, true);
    if (!is_array($data) || empty($data['data'])) {
        throw new RuntimeException('Could not parse TheirStack JSON.');
    }

    $jobs = [];
    foreach ($data['data'] as $row) {
        if (count($jobs) >= $limit) {
            break;
        }
        $applyLink = (string) ($row['final_url'] ?? $row['url'] ?? '');
        $company = (string) ($row['company'] ?? ($row['company_object']['name'] ?? ''));
        if (empty($row['job_title']) || $applyLink === '' || $company === '') {
            continue;
        }

        $seniorityRaw = strtolower((string) ($row['seniority'] ?? ''));
        $experience = 'Mid';
        if (preg_match('/intern|entry|junior/', $seniorityRaw)) {
            $experience = 'Entry';
        } elseif (preg_match('/senior|lead|staff|principal/', $seniorityRaw)) {
            $experience = 'Senior';
        } elseif (preg_match('/manager|director|head|executive|vp|chief/', $seniorityRaw)) {
            $experience = 'Executive';
        }

        $jobs[] = [
            'source' => 'TheirStack',
            'guid' => 'theirstack:' . ($row['id'] ?? sha1($applyLink)),
            'company' => $company,
            'title' => (string) $row['job_title'],
            'description' => (string) ($row['description'] ?? ''),
            'location' => (string) ($row['location'] ?? ($row['short_location'] ?? 'Remote')),
            'apply_link' => $applyLink,
            'posted_at' => (string) ($row['date_posted'] ?? 'now'),
            'experience_level' => $experience,
            'salary_min' => isset($row['min_annual_salary']) ? (float) $row['min_annual_salary'] : null,
            'salary_max' => isset($row['max_annual_salary']) ? (float) $row['max_annual_salary'] : null,
            'salary_currency' => (string) ($row['salary_currency'] ?? 'USD'),
            'tags' => is_array($row['technology_slugs'] ?? null) ? $row['technology_slugs'] : [],
        ];
    }

    return $jobs;
}

function jm_scraper_fantasticjobs_jobs(int $limit): array {
    $apiKey = trim((string) getenv('FANTASTICJOBS_RAPIDAPI_KEY'));
    $host = trim((string) getenv('FANTASTICJOBS_RAPIDAPI_HOST')) ?: 'active-jobs-db.p.rapidapi.com';
    if ($apiKey === '') {
        throw new RuntimeException('Fantastic.jobs RapidAPI key missing (set FANTASTICJOBS_RAPIDAPI_KEY).');
    }

    $query = http_build_query([
        'limit' => min(100, $limit),
        'offset' => 0,
        'description_type' => 'text',
    ]);
    $body = jm_scraper_fetch('https://' . $host . '/active-ats-7d?' . $query, [
        'Accept: application/json',
        'X-RapidAPI-Key: ' . $apiKey,
        'X-RapidAPI-Host: ' . $host,
    ]);
    $rows = json_decode($body, true);
    if (isset($rows['data']) && is_array($rows['data'])) {
        $rows = $rows['data'];
    }
    if (!is_array($rows)) {
        throw new RuntimeException('Could not parse Fantastic.jobs JSON.');
    }

    $jobs = [];
    foreach ($rows as $row) {
        if (count($jobs) >= $limit) {
            break;
        }
        if (!is_array($row)) {
            continue;
        }
        $applyLink = (string) ($row['url'] ?? ($row['apply_url'] ?? ''));
        $title = (string) ($row['title'] ?? '');
        $company = (string) ($row['organization'] ?? ($row['company'] ?? ''));
        if ($title === '' || $company === '' || $applyLink === '') {
            continue;
        }

        $location = 'Remote';
        if (!empty($row['locations_derived']) && is_array($row['locations_derived'])) {
            $location = (string) $row['locations_derived'][0];
        } elseif (!empty($row['location'])) {
            $location = (string) $row['location'];
        }

        $jobs[] = [
            'source' => 'FantasticJobs',
            'guid' => 'fantasticjobs:' . ($row['id'] ?? sha1($applyLink)),
            'company' => $company,
            'title' => $title,
            'description' => (string) ($row['description'] ?? ($row['description_text'] ?? '')),
            'location' => $location,
            'apply_link' => $applyLink,
            'posted_at' => (string) ($row['date_posted'] ?? ($row['date_created'] ?? 'now')),
            'salary_min' => null,
            'salary_max' => null,
            'salary_currency' => 'USD',
            'tags' => is_array($row['employment_type'] ?? null) ? $row['employment_type'] : [],
        ];
    }

    return $jobs;
}

function jm_scraper_careerjet_jobs(int $limit): array {
    $affid = trim((string) getenv('CAREERJET_AFFID'));
    if ($affid === '') {
        throw new RuntimeException('Careerjet affiliate id missing (set CAREERJET_AFFID).');
    }

    $query = http_build_query(array_filter([
        'affid' => $affid,
        'keywords' => (string) (getenv('JOOBLE_KEYWORDS') ?: 'remote'),
        'location' => (string) getenv('CAREERJET_LOCATION'),
        'pagesize' => min(99, $limit),
        'page' => 1,
        'sort' => 'date',
        'user_ip' => '127.0.0.1',
        'user_agent' => getenv('JOB_SCRAPER_USER_AGENT') ?: 'JobmingtonBot/1.0',
        'url' => 'https://jobmington.com',
    ], static fn($v) => $v !== '' && $v !== null));

    $body = jm_scraper_fetch('http://public.api.careerjet.net/search?' . $query, ['Accept: application/json']);
    $data = json_decode($body, true);
    if (!is_array($data) || empty($data['jobs'])) {
        throw new RuntimeException('Could not parse Careerjet JSON.');
    }

    $jobs = [];
    foreach ($data['jobs'] as $row) {
        if (count($jobs) >= $limit) {
            break;
        }
        $applyLink = (string) ($row['url'] ?? '');
        if (empty($row['title']) || $applyLink === '') {
            continue;
        }

        $salaryMin = isset($row['salary_min']) && $row['salary_min'] !== '' ? (float) $row['salary_min'] : null;
        $salaryMax = isset($row['salary_max']) && $row['salary_max'] !== '' ? (float) $row['salary_max'] : null;

        $jobs[] = [
            'source' => 'Careerjet',
            'guid' => 'careerjet:' . sha1($applyLink),
            'company' => (string) ($row['company'] ?? 'Hiring Company'),
            'title' => (string) $row['title'],
            'description' => (string) ($row['description'] ?? ''),
            'location' => (string) ($row['locations'] ?? 'Remote'),
            'apply_link' => $applyLink,
            'posted_at' => (string) ($row['date'] ?? 'now'),
            'salary_min' => $salaryMin,
            'salary_max' => $salaryMax,
            'salary_currency' => (string) ($row['salary_currency_code'] ?? 'USD'),
            'tags' => [],
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
    'remotive' => 'jm_scraper_remotive_jobs',
    'jobicy' => 'jm_scraper_jobicy_jobs',
    'arbeitnow' => 'jm_scraper_arbeitnow_jobs',
    'himalayas' => 'jm_scraper_himalayas_jobs',
    'themuse' => 'jm_scraper_themuse_jobs',
    'devitjobs' => 'jm_scraper_devitjobs_jobs',
];

// Keyed sources: only register when credentials exist, so default `--source=all`
// runs never log a guaranteed failure. Selecting one explicitly without its key
// gives a clear message instead of a generic failure.
$keyedSources = [
    'adzuna' => ['fn' => 'jm_scraper_adzuna_jobs', 'env' => ['ADZUNA_APP_ID', 'ADZUNA_APP_KEY']],
    'jooble' => ['fn' => 'jm_scraper_jooble_jobs', 'env' => ['JOOBLE_API_KEY']],
    'theirstack' => ['fn' => 'jm_scraper_theirstack_jobs', 'env' => ['THEIRSTACK_API_KEY']],
    'fantasticjobs' => ['fn' => 'jm_scraper_fantasticjobs_jobs', 'env' => ['FANTASTICJOBS_RAPIDAPI_KEY']],
    'careerjet' => ['fn' => 'jm_scraper_careerjet_jobs', 'env' => ['CAREERJET_AFFID']],
];

foreach ($keyedSources as $name => $cfg) {
    $hasAll = true;
    foreach ($cfg['env'] as $envKey) {
        if (trim((string) getenv($envKey)) === '') {
            $hasAll = false;
            break;
        }
    }
    if ($hasAll) {
        $sources[$name] = $cfg['fn'];
    } elseif ($selectedSource === $name) {
        jm_scraper_log($name . ' selected but ' . implode('/', $cfg['env']) . ' not set. Add to .env first.');
        exit(1);
    }
}

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
