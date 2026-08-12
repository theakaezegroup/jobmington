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

/**
 * Every gateable tool. `api` lists the endpoints that do the actual work, so
 * gating the page cannot be sidestepped by posting straight to the API.
 */
function jm_tools(): array
{
    return [
        'cv_builder' => [
            'key'   => 'cv_builder',
            'name'  => 'Resume Builder',
            'url'   => '/cv-builder/',
            'group' => 'CV',
            'api'   => ['api/cv-extract.php'],
        ],
        'cv_roast' => [
            'key'   => 'cv_roast',
            'name'  => 'Resume Optimizer',
            'url'   => '/ai/roast.php',
            'group' => 'AI',
            'api'   => ['api/cv-roast.php', 'api/cv-extract.php'],
        ],
        'cover_letter' => [
            'key'   => 'cover_letter',
            'name'  => 'Cover Letter AI',
            'url'   => '/ai/cover-letter.php',
            'group' => 'AI',
            'api'   => ['api/cover-letter.php'],
        ],
        'cold_pitch' => [
            'key'   => 'cold_pitch',
            'name'  => 'Cold Pitch AI',
            'url'   => '/ai/cold-pitch.php',
            'group' => 'AI',
            'api'   => ['api/cold-pitch.php'],
        ],
        'andika' => [
            'key'   => 'andika',
            'name'  => 'Andika AI',
            'url'   => '/ai/andika.php',
            'group' => 'AI',
            'api'   => ['api/andika.php'],
        ],
        'job_match' => [
            'key'   => 'job_match',
            'name'  => 'Job Matches',
            'url'   => '/jobs/search.php',
            'group' => 'Jobs',
            'api'   => ['api/job-matches.php'],
        ],
        'passport' => [
            'key'   => 'passport',
            'name'  => 'Talent Passport',
            'url'   => '/wallet/passport/',
            'group' => 'Profile',
            'api'   => [],
        ],
        'certificates' => [
            'key'   => 'certificates',
            'name'  => 'Certificates',
            'url'   => '/certificates/',
            'group' => 'Learn',
            'api'   => ['api/certificates.php'],
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

    // A tool with no row yet is treated as on, matching the migration's seed.
    $cache = array_fill_keys(array_keys(jm_tools()), 'on');

    try {
        foreach (db()->query("SELECT tool_key, status FROM tool_flags") as $row) {
            $cache[$row['tool_key']] = $row['status'];
        }
    } catch (Throwable $e) {
        error_log('jm_tool_statuses: ' . $e->getMessage());
    }

    return $cache;
}

function jm_tool_status(string $key): string
{
    return jm_tool_statuses()[$key] ?? 'on';
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
