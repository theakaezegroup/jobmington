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
require_once __DIR__ . '/../includes/seeds.php';

header('Content-Type: application/json');

Session::start();

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
    'interview_practice' => 100,
    'salary_guide' => 0,        // Free
    'career_roadmap' => 75,
    'cv_roast' => 50
];

$cost = $toolCosts[$tool] ?? 0;

// Get user location from session (geo_data is set by LocationDetector)
// Keys: code, name, city, region, lat, lon, timezone, isp, currency, symbol, db_id
$geoData = $_SESSION['geo_data'] ?? null;

// Debug: Log what we're getting
error_log("ANDIKA DEBUG - geo_data: " . json_encode($geoData));

$userCountry = ($geoData && isset($geoData['name']) && !empty($geoData['name'])) ? $geoData['name'] : DEFAULT_COUNTRY_NAME;
$userRegion = ($geoData && isset($geoData['region'])) ? $geoData['region'] : '';
$userCity = ($geoData && isset($geoData['city'])) ? $geoData['city'] : '';

// Debug: Log the extracted values
error_log("ANDIKA DEBUG - userCountry: $userCountry, userRegion: $userRegion, userCity: $userCity");

$userName = '';
if ($userId) {
    $pdo = db();
    $stmt = $pdo->prepare("SELECT first_name, country_id FROM users WHERE user_id = ?");
    $stmt->execute([$userId]);
    $userData = $stmt->fetch();
    $userName = $userData['first_name'] ?? '';
    
    // If user has a country set in profile, use that instead
    if ($userData['country_id']) {
        $stmt = $pdo->prepare("SELECT name FROM countries WHERE country_id = ?");
        $stmt->execute([$userData['country_id']]);
        $countryData = $stmt->fetch();
        if ($countryData['name']) {
            $userCountry = $countryData['name'];
        }
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
    $result = spendSeeds($userId, $tool, null, "Used {$tool} feature");
    if (!$result['success']) {
        echo json_encode([
            "success" => false,
            "error" => "payment_failed", 
            "message" => $result['message']
        ]);
        exit;
    }
}

// Get current balance for response
$newBalance = $userId ? getSeedBalance($userId) : 0;

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

// Groq API (free tier)
$groqKey = getenv('GROQ_API_KEY');
if (empty($groqKey)) {
    $groqKey = 'YOUR_GROQ_API_KEY'; // Get free key at https://console.groq.com/keys
}

$data = [
    "model" => "llama-3.3-70b-versatile",
    "messages" => [
        ["role" => "system", "content" => $systemPrompt],
        ["role" => "user", "content" => $message]
    ],
    "temperature" => 0.7,
    "max_tokens" => 1024
];

$ch = curl_init("https://api.groq.com/openai/v1/chat/completions");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json",
    "Authorization: Bearer " . $groqKey
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    echo json_encode([
        "success" => false,
        "error" => "connection_error",
        "message" => "Connection failed: " . $curlError
    ]);
    exit;
}

if ($httpCode !== 200) {
    // Refund seeds if API call failed
    if ($cost > 0 && $userId) {
        awardSeeds($userId, 'refund', null, "Refund for failed {$tool} request", $cost);
    }
    $errorData = json_decode($response, true);
    $errorMsg = $errorData['error']['message'] ?? "HTTP Error $httpCode";
    echo json_encode([
        "success" => false,
        "error" => "api_error",
        "message" => "API Error: " . $errorMsg,
        "debug" => $response
    ]);
    exit;
}

$result = json_decode($response, true);
$reply = $result['choices'][0]['message']['content'] ?? "Sorry, I couldn't process that.";

echo json_encode([
    "success" => true,
    "reply" => $reply,
    "tool" => $tool,
    "cost" => $cost,
    "balance" => $newBalance
]);
?>
