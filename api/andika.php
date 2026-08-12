<?php
/**
 * Andika AI API - With Seeds Integration
 * Handles chat requests and seed deductions for premium tools
 */

define('JOBMINGTON', true);
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/tools.php';
require_once __DIR__ . '/../includes/seeds.php';

header('Content-Type: application/json');

Session::start();
jm_require_tool_api('andika');

$input = json_decode(file_get_contents("php://input"), true);
$message = $input['message'] ?? '';
$tool = $input['tool'] ?? 'chat'; // chat, interview_practice, salary_guide, career_roadmap, cv_roast

if (empty($message)) {
    echo json_encode(["success" => false, "error" => "No message provided"]);
    exit;
}

$userId = Session::userId();

// Define tool costs (must match seed_rates table)
$toolCosts = [
    'chat' => 0,                // Free basic chat
    'interview_practice' => getActionCostWithFallback('interview_practice', 100),
    'salary_guide' => 0,        // Free
    'career_roadmap' => getActionCostWithFallback('career_roadmap', 75),
    'cv_roast' => getActionCostWithFallback('cv_roast', 50)
];

if (!array_key_exists($tool, $toolCosts)) {
    $tool = 'chat';
}

$cost = $toolCosts[$tool];

// Resolve user location — priority: profile country > session geo_data > default (Nigeria)
$geoData    = $_SESSION['geo_data'] ?? null;
$userCountry = DEFAULT_COUNTRY_NAME;
$userRegion  = '';
$userCity    = '';
$userName    = '';

if ($userId) {
    $pdo  = db();
    $stmt = $pdo->prepare("SELECT first_name, country_id FROM users WHERE user_id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $userData = $stmt->fetch();
    $userName = $userData['first_name'] ?? '';

    // Profile country is source of truth — always wins over IP detection
    if (!empty($userData['country_id'])) {
        $cStmt = $pdo->prepare("SELECT name, region FROM countries WHERE country_id = ? LIMIT 1");
        $cStmt->execute([$userData['country_id']]);
        $cRow = $cStmt->fetch();
        if (!empty($cRow['name'])) {
            $userCountry = $cRow['name'];
            $userRegion  = $cRow['region'] ?? '';
        }
    } elseif ($geoData && !empty($geoData['name'])) {
        // Fall back to IP-based detection only if profile has no country set
        $userCountry = $geoData['name'];
        $userRegion  = $geoData['region'] ?? '';
        $userCity    = $geoData['city']   ?? '';
    }
}

// Build location context string
if ($userCity && $userCity !== 'Unknown' && $userCity !== 'Unknown Location' && $userCity !== 'Central Hub') {
    $locationContext = "$userCity, $userCountry";
} elseif ($userRegion && $userRegion !== 'Unknown' && $userRegion !== 'Global' && $userRegion !== 'Africa') {
    $locationContext = "$userRegion, $userCountry";
} else {
    $locationContext = $userCountry;
}

// Check and deduct seeds for paid tools
if ($cost > 0) {
    if (!$userId) {
        echo json_encode([
            "success" => false, 
            "error" => "login_required",
            "message" => "Please log in to use this feature."
        ]);
        exit;
    }
    
    $balance = getSeedBalance($userId);
    if ($balance < $cost) {
        echo json_encode([
            "success" => false,
            "error" => "insufficient_seeds",
            "message" => "You need {$cost} Seeds but only have " . number_format($balance) . ".",
            "required" => $cost,
            "balance" => $balance
        ]);
        exit;
    }
    
    // Deduct seeds
    $result = spendSeeds($userId, $tool, null, "Used {$tool} feature", $cost);
    if (!$result['success']) {
        echo json_encode([
            "success" => false,
            "error" => "payment_failed", 
            "message" => $result['message'],
            "balance" => $result['balance'] ?? getSeedBalance($userId)
        ]);
        exit;
    }
}

// Get current balance for response
$newBalance = $userId ? getSeedBalance($userId) : 0;
$responseCost = $cost;

// Check if this is the first message in the session
$isFirstMessage = !isset($_SESSION['andika_chat_started']);
if ($isFirstMessage) {
    $_SESSION['andika_chat_started'] = true;
}

$userGreetName = $userName ? " $userName" : "";

// Customize system prompt based on tool with location context
$greetingInstruction = $isFirstMessage && $tool === 'chat'
    ? "This is the user's FIRST message. Give a warm, friendly welcome like: 'Hey{$userGreetName}!  Great to have you here! I'm Andika, your career buddy at Jobmington. How can I help you today?' Be natural and conversational."
    : "";

$baseContext = <<<PROMPT
You are Andika, a friendly AI career assistant for Jobmington (an initiative of Truthsprout). 

THE USER is located in: {$locationContext}
You should tailor your job advice and career guidance to the {$locationContext} job market.

{$greetingInstruction}

IMPORTANT: You are an AI assistant. You don't have a physical location. The user is in {$locationContext} - help them with jobs and careers relevant to THEIR location.

PERSONALITY:
- Be conversational, warm, and human-like - NOT robotic or corporate
- Match the user's energy and tone. If they're casual, be casual back
- If someone says "hi", "hello", "good to be here" etc., respond naturally like a friend would - don't immediately launch into career advice
- Do not use emojis
- Have personality! You can joke, be encouraging, show empathy

WHEN TO GIVE CAREER ADVICE:
- Only provide career/job advice when the user ACTUALLY asks for it
- If the message is just a greeting or casual chat, respond conversationally first
- Don't assume every message is asking for career help

WHEN GIVING ADVICE:
- Keep it concise and practical
- Tailor advice to {$locationContext} job market
- Use bullet points for lists
- Be encouraging but honest
PROMPT;

$systemPrompts = [
    'chat' => $baseContext,
    'interview_practice' => "{$baseContext}\n\nMODE: Mock Interview. Ask one question at a time, give feedback, then continue. Consider local interview customs in {$userCountry}.",
    'salary_guide' => "{$baseContext}\n\nMODE: Salary Guide. Provide salary ranges for {$locationContext}, market demand, and negotiation tips.",
    'career_roadmap' => "{$baseContext}\n\nMODE: Career Roadmap. Create actionable plans with milestones and timelines relevant to {$locationContext}.",
    'cv_roast' => "{$baseContext}\n\nMODE: CV Review. Be direct but constructive. Focus on what {$locationContext} employers look for."
];

$systemPrompt = $systemPrompts[$tool] ?? $systemPrompts['chat'];

function jm_andika_local_reply(string $message, string $tool, string $locationContext): string {
    $message = trim($message);
    if ($tool === 'salary_guide') {
        return "I can help with salary direction for {$locationContext}. Share the role title, seniority, and industry, and I will compare market expectations, negotiation angles, and what to ask before accepting.";
    }
    if ($tool === 'career_roadmap') {
        return "Here is a practical roadmap: define your target role, list the 5 most repeated job-posting skills, close the largest 2 gaps first, update your CV with measurable proof, then apply in focused batches each week. Tell me the role you want and I will tailor this.";
    }
    if ($tool === 'interview_practice') {
        return "Let's practice. First question: tell me about a recent project or responsibility where your work created measurable value. Answer in 60-90 seconds, and I will give feedback.";
    }
    if ($tool === 'cv_roast') {
        return "For the full CV Roast, open the CV Roast page so Andika can read your saved Jobmington CV and produce a score, weak points, keyword gaps, and an optimized summary.";
    }
    if (preg_match('/^(hi|hello|hey|good\s*(morning|afternoon|evening))\b/i', $message)) {
        return "Hey! I'm Andika, your Jobmington career assistant. I can help you find roles, improve your CV, prepare for interviews, or think through a career move. What are we working on today?";
    }
    return "I can help with that. For the best answer, tell me your target role, location, current experience level, and whether you want jobs, CV help, interview prep, or a career plan.";
}

$providers = [];
$groqKey = trim((string) getenv('GROQ_API_KEY'));
if ($groqKey !== '' && stripos($groqKey, 'your_') === false && stripos($groqKey, 'YOUR_') === false) {
    $providers[] = [
        'url' => 'https://api.groq.com/openai/v1/chat/completions',
        'model' => 'llama-3.3-70b-versatile',
        'headers' => ['Authorization: Bearer ' . $groqKey],
    ];
}

$openRouterKey = trim((string) getenv('OPENROUTER_API_KEY'));
if ($openRouterKey !== '' && stripos($openRouterKey, 'your_') === false && stripos($openRouterKey, 'YOUR_') === false) {
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

$reply = null;
$lastError = null;

foreach ($providers as $provider) {
    $data = [
        "model" => $provider['model'],
        "messages" => [
            ["role" => "system", "content" => $systemPrompt],
            ["role" => "user", "content" => $message]
        ],
        "temperature" => 0.7,
        "max_tokens" => 1024
    ];

    $ch = curl_init($provider['url']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge(["Content-Type: application/json"], $provider['headers']));
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $response = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError || $httpCode < 200 || $httpCode >= 300) {
        $lastError = $curlError ?: "HTTP Error {$httpCode}";
        continue;
    }

    $result = json_decode((string) $response, true);
    $reply = $result['choices'][0]['message']['content'] ?? null;
    if ($reply) {
        break;
    }
}

if (!$reply) {
    $reply = jm_andika_local_reply($message, $tool, $locationContext);
}

echo json_encode([
    "success" => true,
    "reply" => $reply,
    "tool" => $tool,
    "cost" => $responseCost,
    "balance" => $newBalance
]);
?>
