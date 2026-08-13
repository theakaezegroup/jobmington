<?php
/**
 * JOBMINGTON - the tool registry and its access gate.
 *
 * One list of every tool the site offers, shared by the admin switchboard,
 * the Tools page and the gate on each tool itself, so a tool cannot be locked
 * in one place and open in another.
 *
 * Three states per tool:
 *   on    everyone who is signed in
 *   beta  only people explicitly granted it, plus admins
 *   off   nobody but admins
 *
 * Admins always pass. Locking yourself out of your own beta would make the
 * switchboard useless.
 */

if (!defined('JOBMINGTON')) {
    http_response_code(403);
    exit('Forbidden');
}

// The statuses live in the database, so this file depends on db(). Declaring
// it here rather than trusting every caller to have loaded it: pricing.php had
// not, and the gate quietly fell open as a result.
require_once __DIR__ . '/../config/database.php';

/**
 * Every tool, described once.
 *
 * Access and price are separate questions but they belong to the same tool, so
 * they live in the same entry. There used to be a second catalogue in
 * monetization.php, and the two had already drifted: the CV optimizer was
 * 'cv_roast' to the gate and 'cv_optimizer' to the paywall.
 *
 * The word is CV, not resume. They name the same document, but resume is the
 * American half of the pair and everyone we build for says CV: Nigeria, Kenya,
 * Ghana, South Africa, and the British-descended usage across the continent.
 * These names are what people read on the tools page and the dashboard, so
 * they say CV. Keep resume out of the interface; it belongs only in the AI
 * prompts, which need both words to recognise an uploaded file, and in search
 * tags, where an American reader has to be able to find us.
 *
 *   api           the endpoints that do the real work. Gating only the page
 *                 would be theatre, so these are gated too.
 *   credit_cost   what a run costs. 0 with is_free means it never charges.
 *   built         false for a tool that has no page yet. It seeds as off, so
 *                 it is not advertised as purchasable before it exists.
 */
function jm_tools(): array
{
    return [
        'cv_builder' => [
            'key'          => 'cv_builder',
            'name'         => 'CV Builder',
            'url'          => '/cv-builder/',
            'group'        => 'CV',
            'api'          => ['api/cv-extract.php'],
            'built'        => true,
            'is_free'      => true,
            'credit_cost'  => 0,
            'free_preview' => false,
            'description'  => 'Build an ATS-friendly CV from polished templates.',
        ],
        'cv_optimizer' => [
            'key'          => 'cv_optimizer',
            'name'         => 'CV Optimizer',
            'url'          => '/ai/roast.php',
            'group'        => 'AI',
            'api'          => ['api/cv-roast.php', 'api/cv-extract.php'],
            'built'        => true,
            'is_free'      => false,
            'credit_cost'  => TOOL_COST_CV_OPTIMIZER,
            'free_preview' => true,   // score shows free, the fixes are paid
            'description'  => 'Get a detailed ATS score and actionable improvement tips.',
        ],
        'cover_letter' => [
            'key'          => 'cover_letter',
            'name'         => 'Cover Letter AI',
            'url'          => '/ai/cover-letter.php',
            'group'        => 'AI',
            'api'          => ['api/cover-letter.php'],
            'built'        => true,
            'is_free'      => false,
            'credit_cost'  => TOOL_COST_COVER_LETTER,
            'free_preview' => false,
            'description'  => 'AI-written cover letter tailored to the job description.',
        ],
        'cold_pitch' => [
            'key'          => 'cold_pitch',
            'name'         => 'Cold Pitch AI',
            'url'          => '/ai/cold-pitch.php',
            'group'        => 'AI',
            'api'          => ['api/cold-pitch.php'],
            'built'        => true,
            'is_free'      => false,
            'credit_cost'  => TOOL_COST_COLD_PITCH,
            'free_preview' => false,
            'description'  => 'Human, specific cold pitches that earn the micro-yes.',
        ],
        'andika' => [
            'key'          => 'andika',
            'name'         => 'Andika AI',
            'url'          => '/ai/andika.php',
            'group'        => 'AI',
            'api'          => ['api/andika.php'],
            'built'        => true,
            'is_free'      => true,
            'credit_cost'  => 0,
            'free_preview' => false,
            'description'  => 'Your career assistant: ask it anything about work.',
        ],
        'job_match' => [
            'key'          => 'job_match',
            'name'         => 'Job Matches',
            'url'          => '/jobs/search.php',
            'group'        => 'Jobs',
            'api'          => ['api/job-matches.php'],
            'built'        => true,
            'is_free'      => true,
            'credit_cost'  => 0,
            'free_preview' => false,
            'description'  => 'Roles matched to your profile, ranked by fit.',
        ],
        'passport' => [
            'key'          => 'passport',
            'name'         => 'Talent Passport',
            'url'          => '/wallet/passport/',
            'group'        => 'Profile',
            'api'          => [],
            'built'        => true,
            'is_free'      => true,
            'credit_cost'  => 0,
            'free_preview' => false,
            'description'  => 'A verifiable profile you can share with employers.',
        ],
        'certificates' => [
            'key'          => 'certificates',
            'name'         => 'Certificates',
            'url'          => '/certificates/',
            'group'        => 'Learn',
            'api'          => ['api/certificates.php'],
            'built'        => true,
            'is_free'      => true,
            'credit_cost'  => 0,
            'free_preview' => false,
            'description'  => 'Certificates for the courses you have finished.',
        ],

        // Priced and listed, but never built. They were on the pricing page as
        // things people could buy. They seed as off, so they stay off it until
        // there is something to sell.
        'interview_prep' => [
            'key'          => 'interview_prep',
            'name'         => 'Interview Prep',
            'url'          => '',
            'group'        => 'AI',
            'api'          => [],
            'built'        => false,
            'is_free'      => false,
            'credit_cost'  => TOOL_COST_INTERVIEW_PREP,
            'free_preview' => false,
            'description'  => '5 likely questions and model answers for your target role.',
        ],
        'skills_gap' => [
            'key'          => 'skills_gap',
            'name'         => 'Skills Gap Analyser',
            'url'          => '',
            'group'        => 'AI',
            'api'          => [],
            'built'        => false,
            'is_free'      => false,
            'credit_cost'  => TOOL_COST_SKILLS_GAP_REPORT,
            'free_preview' => true,
            'description'  => 'See the gap between your profile and your target role.',
        ],
        'salary_analyzer' => [
            'key'          => 'salary_analyzer',
            'name'         => 'Salary Analyser',
            'url'          => '',
            'group'        => 'AI',
            'api'          => [],
            'built'        => false,
            'is_free'      => true,
            'credit_cost'  => 0,
            'free_preview' => false,
            'description'  => 'Market salary ranges for your role and location.',
        ],
        'tax_calculator' => [
            'key'          => 'tax_calculator',
            'name'         => 'Tax Calculator',
            'url'          => '',
            'group'        => 'AI',
            'api'          => [],
            'built'        => false,
            'is_free'      => true,
            'credit_cost'  => 0,
            'free_preview' => false,
            'description'  => 'Estimate your take-home pay after Nigerian income tax.',
        ],
    ];
}

function jm_tool(string $key): array
{
    return jm_tools()[$key] ?? [];
}

/**
 * Status for every tool, keyed by tool. Read once per request: a tool page
 * plus the Tools grid would otherwise hit the same table a dozen times.
 */
function jm_tool_statuses(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    // Fail closed. If the flags cannot be read we do not know what is meant to
    // be open, and guessing 'on' means one missing include silently unlocks
    // every gated tool, which is exactly what happened on pricing.php. A tool
    // with a row is whatever that row says; the seed gives every real tool one.
    $cache = array_fill_keys(array_keys(jm_tools()), 'off');

    try {
        foreach (db()->query("SELECT tool_key, status FROM tool_flags") as $row) {
            $cache[$row['tool_key']] = $row['status'];
        }
    } catch (Throwable $e) {
        error_log('jm_tool_statuses (every tool now reads as off): ' . $e->getMessage());
    }

    return $cache;
}

function jm_tool_status(string $key): string
{
    // Unknown key means an unregistered tool, which nobody should reach.
    return jm_tool_statuses()[$key] ?? 'off';
}

/**
 * The tools this user has been granted by hand. Also cached per request.
 */
function jm_tool_grants_for(?int $userId): array
{
    static $cache = [];
    if ($userId === null || $userId <= 0) {
        return [];
    }
    if (isset($cache[$userId])) {
        return $cache[$userId];
    }

    $cache[$userId] = [];
    try {
        $stmt = db()->prepare("SELECT tool_key FROM tool_grants WHERE user_id = ?");
        $stmt->execute([$userId]);
        $cache[$userId] = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    } catch (Throwable $e) {
        error_log('jm_tool_grants_for: ' . $e->getMessage());
    }

    return $cache[$userId];
}

/**
 * Can this person use this tool right now?
 */
function jm_tool_available(string $key, ?int $userId = null): bool
{
    if ($userId === null) {
        $userId = Session::isLoggedIn() ? Session::userId() : null;
    }

    if (Session::isLoggedIn() && Session::isAdmin()) {
        return true;
    }

    switch (jm_tool_status($key)) {
        case 'on':
            return true;
        case 'beta':
            return in_array($key, jm_tool_grants_for($userId), true);
        default:
            return false;
    }
}

/**
 * True when this person is testing the tool rather than buying it.
 *
 * Somebody invited into a beta is doing you a favour on software you have
 * labelled unreliable. Charging them is the worst of both worlds: the revenue
 * is negligible and you have taken money for something you warned them about.
 * So a beta invite is also a free pass, and the paywall steps aside.
 */
function jm_tool_beta_pass(string $key, ?int $userId = null): bool
{
    if (jm_tool_status($key) !== 'beta') {
        return false;
    }
    if (Session::isLoggedIn() && Session::isAdmin()) {
        return true;
    }
    if ($userId === null) {
        $userId = Session::isLoggedIn() ? Session::userId() : null;
    }
    return in_array($key, jm_tool_grants_for($userId), true);
}

/**
 * Gate a tool page. Sends anyone without access to the Tools page with a note
 * rather than a blank 403, and stops the script.
 *
 * Call after Session::requireLogin so a signed-out visitor is asked to sign in
 * first and lands back on the tool once it is open to them.
 */
function jm_require_tool(string $key): void
{
    if (jm_tool_available($key)) {
        return;
    }

    $tool = jm_tool($key);
    $_SESSION['tool_locked'] = $tool['name'] ?? 'That tool';

    header('Location: ' . SITE_URL . '/tools/?locked=' . urlencode($key));
    exit;
}

/**
 * Gate a JSON endpoint. The page gate is cosmetic on its own: these endpoints
 * are what actually spend credits and call the model.
 *
 * Pass several keys for an endpoint more than one tool uses, such as CV
 * extraction, which both the builder and the optimizer call. Access to either
 * tool is enough.
 */
function jm_require_tool_api(string ...$keys): void
{
    foreach ($keys as $key) {
        if (jm_tool_available($key)) {
            return;
        }
    }
    $key = $keys[0];

    $tool = jm_tool($key);
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error'   => 'tool_locked',
        'message' => ($tool['name'] ?? 'This tool') . ' is in beta and not open to your account yet.',
    ]);
    exit;
}
