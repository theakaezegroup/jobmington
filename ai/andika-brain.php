<?php
/**
 * JOBMINGTON - Andika Brain
 * Backend API handler for AI chat using OpenRouter
 */

define('JOBMINGTON', true);
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';

Session::start();

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method not allowed', 405);
}

// Rate limiting
$identifier = Session::isLoggedIn() ? 'user_' . Session::userId() : 'ip_' . Security::getClientIP();
if (!Security::checkRateLimit('andika_' . $identifier, 30, 3600)) {
    jsonError('Rate limit exceeded. Please try again later.', 429);
}

// Get input
$input = json_decode(file_get_contents('php://input'), true);
$message = trim($input['message'] ?? '');
$history = $input['history'] ?? [];

if (empty($message)) {
    jsonError('Message is required');
}

// Sanitize message
$message = Security::clean($message);

if (strlen($message) > 5000) {
    jsonError('Message is too long. Maximum 5000 characters.');
}

// Get user location from session
$userCountry = $_SESSION['user_country'] ?? $_SESSION['detected_country'] ?? 'Nigeria';
$userState = $_SESSION['user_state'] ?? $_SESSION['detected_state'] ?? '';
$userName = '';
if (Session::isLoggedIn()) {
    $pdo = db();
    $stmt = $pdo->prepare("SELECT first_name, country_id FROM users WHERE user_id = ?");
    $stmt->execute([Session::userId()]);
    $userData = $stmt->fetch();
    $userName = $userData['first_name'] ?? '';
    if ($userData['country_id']) {
        $stmt = $pdo->prepare("SELECT name FROM countries WHERE country_id = ?");
        $stmt->execute([$userData['country_id']]);
        $countryData = $stmt->fetch();
        $userCountry = $countryData['name'] ?? $userCountry;
    }
}

$locationContext = $userState ? "$userState, $userCountry" : $userCountry;
$userGreetName = $userName ? " $userName" : "";

// System prompt with location context
$isFirstMessage = empty($history);
$greetingInstruction = $isFirstMessage 
    ? "IMPORTANT: This is the user's FIRST message. You MUST start with a warm, friendly welcome greeting. Example: 'Hey{$userGreetName}!  Great to meet you! I'm Andika, your career buddy here at Jobmington. I can help with job hunting, CV tips, interview prep, salary advice — you name it! What would you like help with today?' Be warm, welcoming and personable."
    : "This is a continuing conversation. Do NOT greet or introduce yourself again. Just respond directly to the user's question.";

$systemPrompt = <<<PROMPT
You are Andika, an AI career assistant for Jobmington (an initiative of Truthsprout), a Pan-African job portal.

{$greetingInstruction}

The user is located in **{$locationContext}**. Tailor your advice to the job market, industries, salary expectations, and cultural norms of {$locationContext}.

Your role is to help job seekers with:
1. CV/Resume reviews and improvements
2. Cover letter writing  
3. Interview preparation (with local context)
4. Career advice specific to their location
5. Salary negotiation tips (with local salary ranges)
6. LinkedIn profile optimization
7. Job search strategies for their market
8. Professional development

Guidelines:
- Keep responses concise and well-formatted
- Use **bold** for key points, bullet points for lists
- Consider the local job market of {$locationContext}
- Reference local companies, industries, and salary ranges when relevant
- Be encouraging but direct - avoid unnecessary filler text
- Never repeat your greeting or introduction after the first message

Remember: You are helping someone in {$locationContext} succeed in their career!
PROMPT;

// Build conversation for OpenRouter
$messages = [
    ['role' => 'system', 'content' => $systemPrompt]
];

// Add conversation history
foreach ($history as $msg) {
    if (isset($msg['role']) && isset($msg['content'])) {
        $role = $msg['role'] === 'user' ? 'user' : 'assistant';
        $messages[] = [
            'role' => $role,
            'content' => $msg['content']
        ];
    }
}

// Add current message
$messages[] = [
    'role' => 'user',
    'content' => $message
];

// Build request payload for Groq
$payload = [
    'model' => 'llama-3.3-70b-versatile',
    'messages' => $messages,
    'temperature' => 0.7,
    'max_tokens' => 1024,
];

// Get Groq API key
$groqKey = getenv('GROQ_API_KEY');
if (empty($groqKey)) {
    $groqKey = 'YOUR_GROQ_API_KEY'; // Get free key at https://console.groq.com/keys
}

// Call Groq API
$ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $groqKey
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

// Handle errors
if ($curlError) {
    error_log("Andika API Error: " . $curlError);
    jsonError('Connection error: ' . $curlError);
}

if ($httpCode !== 200) {
    error_log("Andika API HTTP Error: " . $httpCode . " - " . $response);
    $errorData = json_decode($response, true);
    $errorMsg = $errorData['error']['message'] ?? "HTTP $httpCode";
    jsonError('API Error: ' . $errorMsg);
}

// Parse response
$data = json_decode($response, true);

if (!$data) {
    error_log("Andika API Parse Error: " . $response);
    jsonError('Could not parse AI response.');
}

// Extract reply text (Groq/OpenAI format)
$reply = '';
if (isset($data['choices'][0]['message']['content'])) {
    $reply = $data['choices'][0]['message']['content'];
} else {
    error_log("Andika API Unexpected Response: " . json_encode($data));
    $reply = "I'm having trouble processing your request. Please try again.";
}

// Log the interaction (optional, for analytics)
if (Session::isLoggedIn()) {
    try {
        $pdo = db();
        Security::logActivity(Session::userId(), 'andika_chat', substr($message, 0, 100) . '...');
    } catch (Exception $e) {
        // Silently fail - don't break the response
    }
}

// Return success
jsonSuccess(['reply' => $reply]);
?>
