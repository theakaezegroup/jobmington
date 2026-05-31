<?php
/**
 * JOBMINGTON - CV Roast / Optimizer API
 * Builds a practical, role-aware CV report from a user's saved CV.
 */

define('JOBMINGTON', true);
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/seeds.php';

header('Content-Type: application/json');
Session::start();

if (!Session::isLoggedIn()) {
    jsonError('Please log in to roast or optimize a CV.', 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Invalid request method.', 405);
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    jsonError('Invalid JSON payload.');
}

$pdo = db();
$userId = (int) Session::userId();
$cvId = (int) ($input['cv_id'] ?? 0);
$targetRole = trim((string) ($input['target_role'] ?? ''));
$jobDescription = trim((string) ($input['job_description'] ?? ''));
$shouldCharge = (bool) ($input['charge'] ?? true);
$cost = getActionCostWithFallback('cv_roast', 50);

function jm_cv_roast_fetch_rows(PDO $pdo, string $sql, array $params): array {
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        error_log('CV roast fetch error: ' . $e->getMessage());
        return [];
    }
}

function jm_cv_roast_latest_cv_id(PDO $pdo, int $userId): int {
    $stmt = $pdo->prepare("SELECT cv_id FROM cv_profiles WHERE user_id = ? ORDER BY updated_at DESC, created_at DESC LIMIT 1");
    $stmt->execute([$userId]);
    return (int) ($stmt->fetchColumn() ?: 0);
}

function jm_cv_roast_load_cv(PDO $pdo, int $userId, int $cvId): ?array {
    if ($cvId <= 0) {
        $cvId = jm_cv_roast_latest_cv_id($pdo, $userId);
    }

    if ($cvId <= 0) {
        return null;
    }

    $stmt = $pdo->prepare("SELECT * FROM cv_profiles WHERE cv_id = ? AND user_id = ? LIMIT 1");
    $stmt->execute([$cvId, $userId]);
    $profile = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$profile) {
        return null;
    }

    return [
        'profile' => $profile,
        'experience' => jm_cv_roast_fetch_rows($pdo, "SELECT * FROM cv_experience WHERE cv_id = ? ORDER BY is_current DESC, start_date DESC", [$cvId]),
        'education' => jm_cv_roast_fetch_rows($pdo, "SELECT * FROM cv_education WHERE cv_id = ? ORDER BY is_current DESC, start_date DESC", [$cvId]),
        'skills' => jm_cv_roast_fetch_rows($pdo, "SELECT * FROM cv_skills WHERE cv_id = ? ORDER BY skill_name ASC", [$cvId]),
        'projects' => jm_cv_roast_fetch_rows($pdo, "SELECT * FROM cv_projects WHERE cv_id = ? ORDER BY name ASC", [$cvId]),
        'certifications' => jm_cv_roast_fetch_rows($pdo, "SELECT * FROM cv_certifications WHERE cv_id = ? ORDER BY issue_date DESC", [$cvId]),
    ];
}

function jm_cv_roast_text(array $cv): string {
    $profile = $cv['profile'];
    $parts = [
        'Name: ' . ($profile['full_name'] ?? ''),
        'Headline: ' . ($profile['headline'] ?? ''),
        'Summary: ' . ($profile['summary'] ?? ''),
    ];

    if (!empty($cv['skills'])) {
        $skills = array_map(static fn($row) => (string) ($row['skill_name'] ?? ''), $cv['skills']);
        $parts[] = 'Skills: ' . implode(', ', array_filter($skills));
    }

    foreach ($cv['experience'] as $row) {
        $parts[] = 'Experience: ' . trim(($row['job_title'] ?? '') . ' at ' . ($row['company'] ?? '') . '. ' . ($row['description'] ?? '') . ' ' . ($row['achievements'] ?? ''));
    }

    foreach ($cv['projects'] as $row) {
        $parts[] = 'Project: ' . trim(($row['name'] ?? '') . ' ' . ($row['role'] ?? '') . ' ' . ($row['technologies'] ?? '') . ' ' . ($row['description'] ?? ''));
    }

    foreach ($cv['education'] as $row) {
        $parts[] = 'Education: ' . trim(($row['degree'] ?? '') . ' ' . ($row['field_of_study'] ?? '') . ' at ' . ($row['institution'] ?? ''));
    }

    return trim(implode("\n", array_filter($parts)));
}

function jm_cv_roast_keywords(string $text): array {
    $text = strtolower(strip_tags($text));
    $words = preg_split('/[^a-z0-9+#.]+/i', $text) ?: [];
    $stop = array_flip(['the','and','for','with','that','this','from','your','you','are','will','our','job','role','work','team','years','experience','using','into','their','they','have','has','can','able','must','need','skills','strong','good']);
    $counts = [];

    foreach ($words as $word) {
        $word = trim($word);
        if (strlen($word) < 3 || isset($stop[$word]) || is_numeric($word)) {
            continue;
        }
        $counts[$word] = ($counts[$word] ?? 0) + 1;
    }

    arsort($counts);
    return array_slice(array_keys($counts), 0, 14);
}

function jm_cv_roast_fallback(array $cv, string $targetRole, string $jobDescription): array {
    $profile = $cv['profile'];
    $cvText = jm_cv_roast_text($cv);
    $targetText = trim($targetRole . ' ' . $jobDescription);
    $targetKeywords = jm_cv_roast_keywords($targetText);
    $cvKeywords = jm_cv_roast_keywords($cvText);
    $missingKeywords = array_values(array_diff($targetKeywords, $cvKeywords));

    $summary = trim((string) ($profile['summary'] ?? ''));
    $headline = trim((string) ($profile['headline'] ?? ''));
    $skillCount = count($cv['skills']);
    $experienceCount = count($cv['experience']);
    $hasMetrics = (bool) preg_match('/\b(\d+%|\$?\d+[kKmM]?|\d+\s*(users|customers|clients|projects|people|hours|days|weeks|months))\b/', $cvText);
    $score = 48;

    if ($headline !== '') $score += 8;
    if (strlen($summary) >= 120) $score += 12;
    if ($skillCount >= 3) $score += 8;
    if ($skillCount >= 7) $score += 4;
    if ($experienceCount >= 1) $score += 10;
    if ($experienceCount >= 3) $score += 4;
    if ($hasMetrics) $score += 10;
    if (!empty($cv['projects'])) $score += 4;
    if ($targetText !== '' && count($missingKeywords) <= max(2, floor(count($targetKeywords) / 2))) $score += 5;
    $score = max(28, min(98, $score));

    $issues = [];
    if ($headline === '') {
        $issues[] = [
            'title' => 'No sharp headline',
            'critique' => 'Recruiters should know your target role in the first three seconds.',
            'fix' => 'Add a headline like "' . ($targetRole ?: 'Operations Specialist') . ' with measurable delivery experience."',
        ];
    }
    if (strlen($summary) < 120) {
        $issues[] = [
            'title' => 'Summary is too thin',
            'critique' => 'The opening does not yet sell your strongest value or target direction.',
            'fix' => 'Use 2-3 lines that combine role, years or domain, top skills, and one measurable outcome.',
        ];
    }
    if (!$hasMetrics) {
        $issues[] = [
            'title' => 'Impact is not quantified',
            'critique' => 'Responsibilities alone sound passive. Numbers make the CV feel credible.',
            'fix' => 'Add metrics such as revenue, speed, cost saved, users supported, cycle time, or team size.',
        ];
    }
    if ($skillCount < 6) {
        $issues[] = [
            'title' => 'Skills section is underpowered',
            'critique' => 'A short skills list makes matching and ATS discovery weaker.',
            'fix' => 'Add 6-10 role-specific skills and tools. Prioritize the words used in the job description.',
        ];
    }
    if ($targetText !== '' && !empty($missingKeywords)) {
        $issues[] = [
            'title' => 'Target-role keywords are missing',
            'critique' => 'The CV is not fully speaking the language of the role you want.',
            'fix' => 'Work in these terms where truthful: ' . implode(', ', array_slice($missingKeywords, 0, 6)) . '.',
        ];
    }

    if (empty($issues)) {
        $issues[] = [
            'title' => 'Good base, now tailor it',
            'critique' => 'The CV has enough structure, but each application still needs role-specific tuning.',
            'fix' => 'Mirror 4-6 important words from the job description in the summary and first two experience bullets.',
        ];
    }

    $topSkills = array_slice(array_filter(array_map(static fn($row) => (string) ($row['skill_name'] ?? ''), $cv['skills'])), 0, 4);
    $optimizedSummary = trim(($headline ?: ($targetRole ?: 'Career professional')) . ' with experience across ' . (empty($topSkills) ? 'role-relevant delivery' : implode(', ', $topSkills)) . '. Focused on measurable outcomes, clear communication, and practical execution for teams hiring across African and remote markets.');

    return [
        'score' => $score,
        'status' => $score >= 82 ? 'Strong' : ($score >= 65 ? 'Promising' : 'Needs work'),
        'summary' => $score >= 82 ? 'This CV has a solid base. Tailoring and proof points will make it stronger.' : 'This CV can become much more persuasive with sharper proof, keywords, and quantified wins.',
        'issues' => array_slice($issues, 0, 5),
        'strengths' => array_values(array_filter([
            $headline !== '' ? 'Clear professional headline' : null,
            $experienceCount > 0 ? $experienceCount . ' experience section' . ($experienceCount > 1 ? 's' : '') . ' present' : null,
            $skillCount >= 3 ? $skillCount . ' skills available for matching' : null,
            $hasMetrics ? 'Some measurable impact is present' : null,
        ])),
        'optimized_summary' => $optimizedSummary,
        'target_keywords' => $targetKeywords,
        'missing_keywords' => array_slice($missingKeywords, 0, 10),
        'next_steps' => [
            'Rewrite the summary around the exact target role.',
            'Turn the first two experience bullets into achievement statements.',
            'Add truthful keywords from the job post before applying.',
        ],
    ];
}

function jm_cv_roast_ai_report(array $cv, string $targetRole, string $jobDescription, array $fallback): array {
    $cvText = jm_cv_roast_text($cv);
    $prompt = "Return strict JSON only with keys score, status, summary, issues, strengths, optimized_summary, target_keywords, missing_keywords, next_steps. Each issue must have title, critique, fix. Be direct but constructive.\n\nTarget role/job description:\n{$targetRole}\n{$jobDescription}\n\nCV:\n{$cvText}";
    $messages = [
        ['role' => 'system', 'content' => 'You are Andika, Jobmington career AI. You roast CVs constructively and optimize them for African and remote hiring markets. Return valid JSON only.'],
        ['role' => 'user', 'content' => $prompt],
    ];

    $providers = [];
    $groqKey = trim((string) getenv('GROQ_API_KEY'));
    if ($groqKey !== '' && stripos($groqKey, 'your_') === false) {
        $providers[] = [
            'url' => 'https://api.groq.com/openai/v1/chat/completions',
            'model' => 'llama-3.3-70b-versatile',
            'headers' => ['Authorization: Bearer ' . $groqKey],
        ];
    }

    $openRouterKey = trim((string) getenv('OPENROUTER_API_KEY'));
    if ($openRouterKey !== '' && stripos($openRouterKey, 'your_') === false) {
        $providers[] = [
            'url' => 'https://openrouter.ai/api/v1/chat/completions',
            'model' => defined('ANDIKA_MODEL') ? ANDIKA_MODEL : 'meta-llama/llama-3.2-3b-instruct:free',
            'headers' => [
                'Authorization: Bearer ' . $openRouterKey,
                'HTTP-Referer: ' . (defined('SITE_URL') ? SITE_URL : 'https://jobmington.com'),
                'X-Title: Jobmington',
            ],
        ];
    }

    foreach ($providers as $provider) {
        $ch = curl_init($provider['url']);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => array_merge(['Content-Type: application/json'], $provider['headers']),
            CURLOPT_POSTFIELDS => json_encode([
                'model' => $provider['model'],
                'messages' => $messages,
                'temperature' => 0.35,
                'max_tokens' => 1400,
                'response_format' => ['type' => 'json_object'],
            ]),
            CURLOPT_TIMEOUT => 25,
        ]);

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error || $httpCode < 200 || $httpCode >= 300) {
            continue;
        }

        $payload = json_decode((string) $response, true);
        $content = $payload['choices'][0]['message']['content'] ?? '';
        $report = json_decode($content, true);

        if (is_array($report) && isset($report['score'], $report['issues'])) {
            return array_merge($fallback, [
                'score' => max(1, min(100, (int) $report['score'])),
                'status' => (string) ($report['status'] ?? $fallback['status']),
                'summary' => (string) ($report['summary'] ?? $fallback['summary']),
                'issues' => is_array($report['issues']) ? array_slice($report['issues'], 0, 5) : $fallback['issues'],
                'strengths' => is_array($report['strengths'] ?? null) ? array_slice($report['strengths'], 0, 5) : $fallback['strengths'],
                'optimized_summary' => (string) ($report['optimized_summary'] ?? $fallback['optimized_summary']),
                'target_keywords' => is_array($report['target_keywords'] ?? null) ? array_slice($report['target_keywords'], 0, 14) : $fallback['target_keywords'],
                'missing_keywords' => is_array($report['missing_keywords'] ?? null) ? array_slice($report['missing_keywords'], 0, 10) : $fallback['missing_keywords'],
                'next_steps' => is_array($report['next_steps'] ?? null) ? array_slice($report['next_steps'], 0, 5) : $fallback['next_steps'],
            ]);
        }
    }

    return $fallback;
}

$cv = jm_cv_roast_load_cv($pdo, $userId, $cvId);
if (!$cv) {
    jsonError('No saved CV found. Create a CV first, then run the optimizer.', 404);
}

if ($shouldCharge) {
    $balance = getSeedBalance($userId);
    if ($balance < $cost) {
        jsonResponse([
            'success' => false,
            'error' => 'insufficient_seeds',
            'message' => "You need {$cost} Seeds but only have " . number_format($balance) . '.',
            'required' => $cost,
            'balance' => $balance,
        ], 402);
    }

    $payment = spendSeeds($userId, 'cv_roast', (int) $cv['profile']['cv_id'], 'CV Roast optimizer report', $cost);
    if (!$payment['success']) {
        jsonError($payment['message'] ?? 'Could not process Seeds payment.', 402);
    }
}

$fallback = jm_cv_roast_fallback($cv, $targetRole, $jobDescription);
$report = jm_cv_roast_ai_report($cv, $targetRole, $jobDescription, $fallback);

jsonSuccess([
    'cv_id' => (int) $cv['profile']['cv_id'],
    'cost' => $shouldCharge ? $cost : 0,
    'balance' => getSeedBalance($userId),
    'report' => $report,
], 'CV optimizer report ready.');
