<?php
/**
 * JOBMINGTON - Cover Letter AI
 * Generates a tailored cover letter from a job description + the user's CV/profile.
 */

define('JOBMINGTON', true);
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/tools.php';
require_once __DIR__ . '/../includes/monetization.php';
require_once __DIR__ . '/../includes/seeker_premium.php';
require_once __DIR__ . '/_ai_tools.php';

header('Content-Type: application/json');
Session::start();

if (!Session::isLoggedIn()) {
    jsonError('Please log in to generate a cover letter.', 401);
}
jm_require_tool_api('cover_letter');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Invalid request method.', 405);
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    jsonError('Invalid JSON payload.');
}

$pdo            = db();
$userId         = (int) Session::userId();
$jobDescription = trim((string) ($input['job_description'] ?? ''));
$companyName    = trim((string) ($input['company_name'] ?? ''));
$roleTitle      = trim((string) ($input['role_title'] ?? ''));
$tone           = strtolower(trim((string) ($input['tone'] ?? 'professional')));
$extraNotes     = trim((string) ($input['notes'] ?? ''));
$cvId           = (int) ($input['cv_id'] ?? 0);
$shouldCharge   = (bool) ($input['charge'] ?? true);
$toolId         = 'cover_letter';
$cost           = TOOL_COST_COVER_LETTER;
$isPremium      = jm_seeker_is_premium($pdo, $userId);

if ($jobDescription === '' && $roleTitle === '') {
    jsonError('Paste the job description (or at least the role title) to tailor the letter.');
}

$allowedTones = ['professional', 'warm', 'confident'];
if (!in_array($tone, $allowedTones, true)) {
    $tone = 'professional';
}

$candidate = jm_ai_candidate_profile($pdo, $userId, $cvId);
$candidateName = $candidate['name'] !== '' ? $candidate['name'] : 'the candidate';

// ── Credit gate ──────────────────────────────────────────────────────────────
if ($shouldCharge && !$isPremium) {
    $balance = jm_seeker_credit_balance($pdo, $userId);
    if ($balance < $cost) {
        jsonResponse([
            'success'  => false,
            'error'    => 'insufficient_credits',
            'message'  => "You need {$cost} credit to generate a cover letter. Your balance: {$balance}.",
            'required' => $cost,
            'balance'  => $balance,
            'upgrade'  => SITE_URL . '/payments/seeker-premium.php',
            'buy'      => SITE_URL . '/payments/credits.php?tool=' . urlencode($toolId),
        ], 402);
    }
}

// ── Fallback (template) letter, used if AI is unavailable ────────────────────
function jm_cover_letter_fallback(array $candidate, string $companyName, string $roleTitle, string $jobDescription, string $tone): array {
    $name    = $candidate['name'] !== '' ? $candidate['name'] : 'Your Name';
    $role    = $roleTitle !== '' ? $roleTitle : 'this role';
    $company = $companyName !== '' ? $companyName : 'your team';
    $skills  = !empty($candidate['skills']) ? implode(', ', array_slice($candidate['skills'], 0, 5)) : 'the core skills this role needs';
    $headline = $candidate['headline'] !== '' ? $candidate['headline'] : 'a motivated professional';

    $openers = [
        'professional' => "I'm writing to apply for {$role} at {$company}. ",
        'warm'         => "I was genuinely excited to come across {$role} at {$company}. ",
        'confident'    => "When I read the description for {$role} at {$company}, it read like a list of the work I already do well. ",
    ];
    $opener = $openers[$tone] ?? $openers['professional'];

    $body = $opener
        . "As {$headline}, I bring hands-on strength in {$skills}, and I'm drawn to the chance to do focused, measurable work for your team.\n\n"
        . "In my recent work I've owned outcomes end to end — turning ambiguous goals into shipped results, collaborating across functions, and keeping quality high under real deadlines. I read the requirements closely and I'm confident the overlap with what you need is strong.\n\n"
        . "I'd welcome the chance to show how I'd contribute in the first 90 days. Thank you for considering my application — I'd be glad to talk further.";

    $subject = 'Application for ' . $role . ($company !== 'your team' ? ' — ' . $company : '');
    $letter = "Dear Hiring Manager,\n\n{$body}\n\nWarm regards,\n{$name}";

    return [
        'subject'    => $subject,
        'letter'     => $letter,
        'highlights' => array_values(array_filter([
            $candidate['headline'] !== '' ? 'Positions you as ' . $candidate['headline'] : null,
            !empty($candidate['skills']) ? 'Leads with your top skills' : null,
            'Closes with a concrete first-90-days offer',
        ])),
    ];
}

// ── AI letter ────────────────────────────────────────────────────────────────
function jm_cover_letter_ai(array $candidate, string $companyName, string $roleTitle, string $jobDescription, string $tone, string $extraNotes, array $fallback): array {
    $toneGuide = [
        'professional' => 'polished and professional, no fluff',
        'warm'         => 'warm and human, still concise',
        'confident'    => 'confident and direct, earns attention fast',
    ][$tone] ?? 'polished and professional';

    $cvText = $candidate['cv_text'];
    $notes  = $extraNotes !== '' ? "\n\nCandidate notes to weave in (only if relevant):\n{$extraNotes}" : '';

    $prompt = "Write a tailored cover letter. Return strict JSON only with keys: subject, letter, highlights.\n"
        . "- subject: a short email subject line.\n"
        . "- letter: the full cover letter as plain text with real line breaks (\\n). 220-320 words. Start with a greeting, 3 short paragraphs, and a sign-off using the candidate's name. No placeholders like [Company] — use the real values or omit gracefully.\n"
        . "- highlights: array of 3 short strings explaining what makes this letter strong.\n"
        . "Tone: {$toneGuide}. Be specific to the job; mirror 3-5 important phrases from the description truthfully. Never invent employers, titles, or metrics the candidate did not provide.\n\n"
        . "Company: {$companyName}\nRole: {$roleTitle}\n\nJob description:\n{$jobDescription}\n\nCandidate:\nName: {$candidate['name']}\nHeadline: {$candidate['headline']}\n{$cvText}{$notes}";

    $messages = [
        ['role' => 'system', 'content' => 'You are Andika, Jobmington career AI. You write sharp, human cover letters for African and remote job seekers. Return valid JSON only.'],
        ['role' => 'user', 'content' => $prompt],
    ];

    $report = jm_ai_chat_json($messages, 1300, 0.55);
    if (is_array($report) && !empty($report['letter'])) {
        return [
            'subject'    => (string) ($report['subject'] ?? $fallback['subject']),
            'letter'     => (string) $report['letter'],
            'highlights' => is_array($report['highlights'] ?? null) ? array_slice($report['highlights'], 0, 5) : $fallback['highlights'],
        ];
    }

    return $fallback;
}

$fallback = jm_cover_letter_fallback($candidate, $companyName, $roleTitle, $jobDescription, $tone);
$result   = jm_cover_letter_ai($candidate, $companyName, $roleTitle, $jobDescription, $tone, $extraNotes, $fallback);

if ($shouldCharge && !$isPremium) {
    jm_seeker_spend_credit($pdo, $userId, $toolId);
}

jsonSuccess([
    'premium' => $isPremium,
    'cost'    => ($shouldCharge && !$isPremium) ? $cost : 0,
    'balance' => jm_seeker_credit_balance($pdo, $userId),
    'result'  => $result,
], 'Cover letter ready.');
