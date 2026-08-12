<?php
/**
 * JOBMINGTON - Cold Pitch AI
 * Writes human, specific cold pitches (email / DM / LinkedIn) that earn a reply.
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
    jsonError('Please log in to write a cold pitch.', 401);
}
jm_require_tool_api('cold_pitch');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Invalid request method.', 405);
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    jsonError('Invalid JSON payload.');
}

$pdo          = db();
$userId       = (int) Session::userId();
$recipient    = trim((string) ($input['recipient'] ?? ''));     // who/role/company
$goal         = trim((string) ($input['goal'] ?? ''));          // the ask / micro-yes
$offer        = trim((string) ($input['offer'] ?? ''));         // what you bring / context
$channel      = strtolower(trim((string) ($input['channel'] ?? 'email')));
$cvId         = (int) ($input['cv_id'] ?? 0);
$shouldCharge = (bool) ($input['charge'] ?? true);
$toolId       = 'cold_pitch';
$cost         = TOOL_COST_COLD_PITCH;
$isPremium    = jm_seeker_is_premium($pdo, $userId);

if ($recipient === '' || $goal === '') {
    jsonError('Tell us who you are pitching and what you want (the ask).');
}

$allowedChannels = ['email', 'dm', 'linkedin'];
if (!in_array($channel, $allowedChannels, true)) {
    $channel = 'email';
}

$candidate = jm_ai_candidate_profile($pdo, $userId, $cvId);

// ── Credit gate ──────────────────────────────────────────────────────────────
if ($shouldCharge && !$isPremium) {
    $balance = jm_seeker_credit_balance($pdo, $userId);
    if ($balance < $cost) {
        jsonResponse([
            'success'  => false,
            'error'    => 'insufficient_credits',
            'message'  => "You need {$cost} credit to write a cold pitch. Your balance: {$balance}.",
            'required' => $cost,
            'balance'  => $balance,
            'upgrade'  => SITE_URL . '/payments/seeker-premium.php',
            'buy'      => SITE_URL . '/payments/credits.php?tool=' . urlencode($toolId),
        ], 402);
    }
}

// ── Fallback pitch ───────────────────────────────────────────────────────────
function jm_cold_pitch_fallback(array $candidate, string $recipient, string $goal, string $offer, string $channel): array {
    $name   = $candidate['name'] !== '' ? $candidate['name'] : 'Your Name';
    $skills = !empty($candidate['skills']) ? implode(', ', array_slice($candidate['skills'], 0, 3)) : 'work that maps to what you do';
    $offerLine = $offer !== '' ? $offer : "I work in {$skills}";

    if ($channel === 'email') {
        $subject = 'Quick idea for ' . (mb_strlen($recipient) > 40 ? 'your team' : $recipient);
        $message = "Hi,\n\nI'll keep this short. {$offerLine}, and I noticed {$recipient} could be a strong fit for what I do.\n\n{$goal}\n\nWould you be open to a 10-minute call this week? If it's not a fit, no worries at all — I'll get out of your inbox.\n\nThanks,\n{$name}";
    } elseif ($channel === 'linkedin') {
        $subject = '';
        $message = "Hi — I'll be brief. {$offerLine}. I follow {$recipient} and wanted to reach out directly.\n\n{$goal}\n\nOpen to a quick chat? Totally fine if the timing's off.\n\n— {$name}";
    } else { // dm
        $subject = '';
        $message = "Hey! Quick one — {$offerLine} and I think there's overlap with {$recipient}. {$goal} Worth a 10-min chat? No stress if not 🙏";
    }

    return [
        'channel'    => $channel,
        'subject'    => $subject,
        'message'    => $message,
        'variants'   => [],
        'tips'       => [
            'Lead with their world, not your CV.',
            'Ask for one small yes — a 10-minute call, not a job.',
            'Keep it under 120 words; send before 9am their time.',
        ],
    ];
}

// ── AI pitch ─────────────────────────────────────────────────────────────────
function jm_cold_pitch_ai(array $candidate, string $recipient, string $goal, string $offer, string $channel, array $fallback): array {
    $channelGuide = [
        'email'    => 'a cold email with a short subject line; 70-130 words; skimmable.',
        'dm'       => 'a direct message (Twitter/IG/WhatsApp); under 60 words; casual but specific; no subject line.',
        'linkedin' => 'a LinkedIn connection note or InMail; under 90 words; professional but human; no subject line.',
    ][$channel] ?? 'a cold email';

    $cvText = $candidate['cv_text'] !== '' ? "\nBackground:\n{$candidate['cv_text']}" : '';

    $prompt = "Write {$channelGuide}\n"
        . "Return strict JSON only with keys: subject, message, variants, tips.\n"
        . "- subject: short subject line (empty string for dm/linkedin).\n"
        . "- message: the pitch as plain text with real line breaks (\\n). Earn a 'micro-yes' — ask for ONE small, easy thing (a short call, a reply, a pointer), never a job outright.\n"
        . "- variants: array of 1 alternative opening line.\n"
        . "- tips: array of 3 short send tips specific to this pitch.\n"
        . "Rules: sound like a real person, not a template. Be specific to the recipient. No buzzwords, no 'I hope this finds you well', no fake flattery. Never invent facts about the candidate.\n\n"
        . "Recipient (who/role/company): {$recipient}\n"
        . "The ask (micro-yes): {$goal}\n"
        . "What the sender offers / context: {$offer}\n"
        . "Sender name: {$candidate['name']}\nHeadline: {$candidate['headline']}{$cvText}";

    $messages = [
        ['role' => 'system', 'content' => 'You are Andika, Jobmington career AI. You write cold pitches that get replies — specific, human, and respectful of the reader\'s time. Return valid JSON only.'],
        ['role' => 'user', 'content' => $prompt],
    ];

    $report = jm_ai_chat_json($messages, 1100, 0.7);
    if (is_array($report) && !empty($report['message'])) {
        return [
            'channel'  => $channel,
            'subject'  => (string) ($report['subject'] ?? $fallback['subject']),
            'message'  => (string) $report['message'],
            'variants' => is_array($report['variants'] ?? null) ? array_slice(array_map('strval', $report['variants']), 0, 3) : [],
            'tips'     => is_array($report['tips'] ?? null) ? array_slice(array_map('strval', $report['tips']), 0, 4) : $fallback['tips'],
        ];
    }

    return $fallback;
}

$fallback = jm_cold_pitch_fallback($candidate, $recipient, $goal, $offer, $channel);
$result   = jm_cold_pitch_ai($candidate, $recipient, $goal, $offer, $channel, $fallback);

if ($shouldCharge && !$isPremium) {
    jm_seeker_spend_credit($pdo, $userId, $toolId);
}

jsonSuccess([
    'premium' => $isPremium,
    'cost'    => ($shouldCharge && !$isPremium) ? $cost : 0,
    'balance' => jm_seeker_credit_balance($pdo, $userId),
    'result'  => $result,
], 'Cold pitch ready.');
