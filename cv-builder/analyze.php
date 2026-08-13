<?php
/**
 * JOBMINGTON - CV Analysis Handler
 * Scores a CV against a job description using Groq AI.
 * Falls back to a heuristic scorer if no API key is set.
 */

define('JOBMINGTON', true);
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/tools.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/monetization.php';
require_once __DIR__ . '/../includes/seeker_premium.php';

header('Content-Type: application/json');
Session::start();

if (!Session::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Please log in to use the CV analyser.']);
    exit;
}

// The tool gate, after the sign-in check: a signed-out caller should be told
// they are signed out, not that a tool they cannot even see is locked.
jm_require_tool_api('cv_builder');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON payload.']);
    exit;
}

$pdo           = db();
$userId        = (int) Session::userId();
$cvId          = (int) ($input['cv_id'] ?? 0);
$jobDescription = trim((string) ($input['job_description'] ?? ''));

if (!$jobDescription) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Job description is required.']);
    exit;
}

// ── Premium / credit gate ─────────────────────────────────────────────────
$isPremium = jm_seeker_is_premium($pdo, $userId);
$credits   = jm_seeker_credit_balance($pdo, $userId);
$toolId    = 'cv_optimizer';
$cost      = TOOL_COST_CV_OPTIMIZER;

if (!$isPremium && $credits < $cost) {
    http_response_code(402);
    echo json_encode([
        'success'  => false,
        'error'    => 'insufficient_credits',
        'message'  => "You need {$cost} credit to use the CV Analyser. Your balance: {$credits}.",
        'required' => $cost,
        'balance'  => $credits,
        'upgrade'  => SITE_URL . '/payments/seeker-premium.php',
        'buy'      => SITE_URL . '/payments/credits.php?tool=' . urlencode($toolId),
    ]);
    exit;
}

// ── Load CV ───────────────────────────────────────────────────────────────
function jm_analyze_load_cv(PDO $pdo, int $userId, int $cvId): ?array {
    if ($cvId <= 0) {
        $stmt = $pdo->prepare("SELECT cv_id FROM cv_profiles WHERE user_id = ? ORDER BY updated_at DESC, created_at DESC LIMIT 1");
        $stmt->execute([$userId]);
        $cvId = (int) ($stmt->fetchColumn() ?: 0);
    }
    if ($cvId <= 0) return null;

    $stmt = $pdo->prepare("SELECT * FROM cv_profiles WHERE cv_id = ? AND user_id = ? LIMIT 1");
    $stmt->execute([$cvId, $userId]);
    $profile = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$profile) return null;

    $fetch = static function (string $sql, array $params) use ($pdo): array {
        try { $s = $pdo->prepare($sql); $s->execute($params); return $s->fetchAll(PDO::FETCH_ASSOC) ?: []; }
        catch (Throwable $e) { return []; }
    };

    return [
        'cv_id'          => $cvId,
        'profile'        => $profile,
        'skills'         => $fetch("SELECT skill_name FROM cv_skills WHERE cv_id = ?", [$cvId]),
        'experience'     => $fetch("SELECT job_title, company, description, achievements FROM cv_experience WHERE cv_id = ? ORDER BY is_current DESC, start_date DESC", [$cvId]),
        'education'      => $fetch("SELECT degree, field_of_study, institution FROM cv_education WHERE cv_id = ?", [$cvId]),
        'certifications' => $fetch("SELECT name FROM cv_certifications WHERE cv_id = ?", [$cvId]),
    ];
}

$cv = jm_analyze_load_cv($pdo, $userId, $cvId);
if (!$cv) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'No saved CV found. Create a CV first.']);
    exit;
}

// ── Build CV text for AI ──────────────────────────────────────────────────
function jm_analyze_cv_text(array $cv): string {
    $p = $cv['profile'];
    $lines = array_filter([
        'Headline: ' . ($p['headline'] ?? ''),
        'Summary: '  . ($p['summary']  ?? ''),
        !empty($cv['skills']) ? 'Skills: ' . implode(', ', array_column($cv['skills'], 'skill_name')) : '',
    ]);
    foreach ($cv['experience'] as $e) {
        $lines[] = 'Role: ' . ($e['job_title'] ?? '') . ' at ' . ($e['company'] ?? '') . '. ' . ($e['description'] ?? '') . ' ' . ($e['achievements'] ?? '');
    }
    foreach ($cv['education'] as $e) {
        $lines[] = 'Education: ' . ($e['degree'] ?? '') . ' ' . ($e['field_of_study'] ?? '') . ' — ' . ($e['institution'] ?? '');
    }
    foreach ($cv['certifications'] as $c) {
        $lines[] = 'Certification: ' . ($c['name'] ?? '');
    }
    return trim(implode("\n", array_filter($lines)));
}

// ── Heuristic fallback scorer ─────────────────────────────────────────────
function jm_analyze_fallback(array $cv, string $jobDescription): array {
    $cvText  = jm_analyze_cv_text($cv);
    $jdWords = array_unique(array_filter(preg_split('/[^a-z0-9+#.]+/i', strtolower($jobDescription)) ?: []));
    $cvLower = strtolower($cvText);

    $matched = [];
    $missing = [];
    $stop    = array_flip(['the','and','for','with','that','this','from','you','are','will','have','can','must']);
    foreach ($jdWords as $word) {
        if (strlen($word) < 3 || isset($stop[$word]) || is_numeric($word)) continue;
        if (str_contains($cvLower, $word)) $matched[] = $word;
        else $missing[] = $word;
    }
    $matched = array_slice($matched, 0, 10);
    $missing = array_slice($missing, 0, 8);

    $score = 40;
    $p = $cv['profile'];
    if (!empty($p['headline'])) $score += 10;
    if (strlen($p['summary'] ?? '') >= 100) $score += 12;
    if (count($cv['skills']) >= 5) $score += 10;
    if (count($cv['experience']) >= 1) $score += 10;
    if (preg_match('/\d+[%kKmM]?/', $cvText)) $score += 8;
    if (!empty($matched)) $score += min(10, count($matched));
    $score = max(30, min(96, $score));

    $recs = [];
    if (empty($p['headline'])) $recs[] = 'Add a clear professional headline targeting your desired role.';
    if (strlen($p['summary'] ?? '') < 100) $recs[] = 'Expand your summary to 2-3 lines covering role, skills, and one measurable outcome.';
    if (!preg_match('/\d+/', $cvText)) $recs[] = 'Add numbers — revenue, team size, % improvement, cost saved.';
    if (count($cv['skills']) < 5) $recs[] = 'List at least 6-8 role-specific skills and tools.';
    if (!empty($missing)) $recs[] = 'Add these keywords from the job description: ' . implode(', ', array_slice($missing, 0, 5)) . '.';
    if (empty($recs)) $recs[] = 'Tailor the first two experience bullets to mirror language in the job description.';

    return [
        'atsScore'        => $score,
        'scoreChange'     => $score >= 70 ? '+' . ($score - 60) : '-' . (60 - $score),
        'matchedKeywords' => $matched,
        'missingKeywords' => $missing,
        'recommendations' => array_slice($recs, 0, 5),
        'status'          => $score >= 80 ? 'Strong' : ($score >= 60 ? 'Promising' : 'Needs work'),
    ];
}

// ── AI call ───────────────────────────────────────────────────────────────
function jm_analyze_ai_call(array $cv, string $jobDescription, array $fallback): array {
    $cvText = jm_analyze_cv_text($cv);

    $prompt = <<<PROMPT
Analyse this CV against the job description. Return strict JSON only — no markdown, no explanation.

Required JSON keys:
- atsScore (integer 1-100)
- scoreChange (string like "+12" or "-5")
- matchedKeywords (array of strings found in both CV and job description, max 10)
- missingKeywords (array of important job description keywords missing from CV, max 8)
- recommendations (array of 3-5 short, specific, actionable strings)
- status (one of: "Strong", "Promising", "Needs work")

Job description:
{$jobDescription}

CV:
{$cvText}
PROMPT;

    $providers = [];
    $groqKey = trim((string) getenv('GROQ_API_KEY'));
    if ($groqKey !== '' && !str_contains(strtolower($groqKey), 'your_')) {
        $providers[] = [
            'url'     => 'https://api.groq.com/openai/v1/chat/completions',
            'model'   => 'llama-3.3-70b-versatile',
            'headers' => ['Authorization: Bearer ' . $groqKey],
        ];
    }
    $openRouterKey = trim((string) getenv('OPENROUTER_API_KEY'));
    if ($openRouterKey !== '' && !str_contains(strtolower($openRouterKey), 'your_')) {
        $providers[] = [
            'url'     => 'https://openrouter.ai/api/v1/chat/completions',
            'model'   => defined('ANDIKA_MODEL') ? ANDIKA_MODEL : 'meta-llama/llama-3.2-3b-instruct:free',
            'headers' => ['Authorization: Bearer ' . $openRouterKey, 'HTTP-Referer: ' . SITE_URL, 'X-Title: Jobmington'],
        ];
    }

    foreach ($providers as $provider) {
        $ch = curl_init($provider['url']);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_TIMEOUT        => 25,
            CURLOPT_HTTPHEADER     => array_merge(['Content-Type: application/json'], $provider['headers']),
            CURLOPT_POSTFIELDS     => json_encode([
                'model'           => $provider['model'],
                'messages'        => [
                    ['role' => 'system', 'content' => 'You are a CV analysis engine. Return only valid JSON.'],
                    ['role' => 'user',   'content' => $prompt],
                ],
                'temperature'     => 0.2,
                'max_tokens'      => 800,
                'response_format' => ['type' => 'json_object'],
            ]),
        ]);
        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode < 200 || $httpCode >= 300) continue;

        $payload = json_decode((string) $response, true);
        $content = $payload['choices'][0]['message']['content'] ?? '';
        $result  = json_decode($content, true);

        if (is_array($result) && isset($result['atsScore'])) {
            return [
                'atsScore'        => max(1, min(100, (int) $result['atsScore'])),
                'scoreChange'     => (string) ($result['scoreChange']     ?? $fallback['scoreChange']),
                'matchedKeywords' => array_slice((array) ($result['matchedKeywords'] ?? $fallback['matchedKeywords']), 0, 10),
                'missingKeywords' => array_slice((array) ($result['missingKeywords'] ?? $fallback['missingKeywords']), 0, 8),
                'recommendations' => array_slice((array) ($result['recommendations'] ?? $fallback['recommendations']), 0, 5),
                'status'          => (string) ($result['status'] ?? $fallback['status']),
            ];
        }
    }

    return $fallback;
}

// ── Run analysis ──────────────────────────────────────────────────────────
$fallback = jm_analyze_fallback($cv, $jobDescription);
$data     = jm_analyze_ai_call($cv, $jobDescription, $fallback);

// ── Deduct credit (after successful analysis) ─────────────────────────────
if (!$isPremium) {
    jm_seeker_spend_credit($pdo, $userId, $toolId);
}

$newBalance = jm_seeker_credit_balance($pdo, $userId);

echo json_encode([
    'success' => true,
    'message' => 'Analysis complete.',
    'premium' => $isPremium,
    'cost'    => $isPremium ? 0 : $cost,
    'balance' => $newBalance,
    'data'    => $data,
]);
