<?php
/**
 * Jobmington - lightweight smoke + unit test runner (no dependencies).
 *
 *   php tests/run.php                              # unit checks only
 *   php tests/run.php --base=https://jobmington.com  # + HTTP smoke checks
 *
 * Exit code 0 = all passed, 1 = one or more failed (CI-friendly).
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

define('JOBMINGTON', true);
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/paystack.php';
require_once __DIR__ . '/../includes/mailer.php';

$opts = getopt('', ['base::', 'no-http']);
$base = isset($opts['base']) ? rtrim((string) $opts['base'], '/') : null;

$passed = 0;
$failed = 0;

function check(string $name, bool $cond): void {
    global $passed, $failed;
    if ($cond) { $passed++; echo "  PASS  {$name}\n"; }
    else       { $failed++; echo "  FAIL  {$name}\n"; }
}

echo "== Unit checks ==\n";

/* Paystack money conversion */
check('Paystack::toKobo(100) == 10000', Paystack::toKobo(100.0) === 10000);
check('Paystack::toNaira(10000) == 100.0', Paystack::toNaira(10000) === 100.0);
check('Paystack reference has prefix', str_starts_with(Paystack::generateReference('JMT'), 'JMT-'));

/* Password hashing + strength */
$hash = Security::hashPassword('Sup3rSecret!');
check('password hash verifies', Security::verifyPassword('Sup3rSecret!', $hash));
check('password verify rejects wrong', !Security::verifyPassword('nope', $hash));
check('weak password rejected', count(Security::validatePasswordStrength('abc')) > 0);
check('strong password accepted', count(Security::validatePasswordStrength('Abcdef12')) === 0);

/* Output escaping */
check('escape neutralises <script>', !str_contains(Security::escape('<script>'), '<script>'));

/* Email validation */
check('valid email accepted', Security::validateEmail('a@b.com'));
check('invalid email rejected', !Security::validateEmail('not-an-email'));

/* Unsubscribe token (HMAC) */
$tok = Mailer::unsubscribeToken('user@example.com');
check('unsubscribe token verifies', Mailer::verifyUnsubscribeToken('user@example.com', $tok));
check('unsubscribe token is case-normalised', Mailer::verifyUnsubscribeToken('USER@example.com', $tok));
check('tampered unsubscribe token rejected', !Mailer::verifyUnsubscribeToken('user@example.com', $tok . 'x'));
check('token for other email rejected', !Mailer::verifyUnsubscribeToken('other@example.com', $tok));

/* Job-match composer (no DB) */
$compose = Mailer::composeJobMatchAlert('Ada Obi', 3, [['title' => 'Engineer', 'company' => 'Flutterwave']]);
check('match alert composes subject', str_contains($compose['subject'], '3 new job'));
check('match alert content has first name', str_contains($compose['content'], 'Ada'));

/* HTTP smoke checks (opt-in) */
if ($base && !isset($opts['no-http'])) {
    echo "== HTTP smoke checks ({$base}) ==\n";

    $status = function (string $path) use ($base): int {
        $ch = curl_init($base . $path);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15, CURLOPT_NOBODY => true, CURLOPT_FOLLOWLOCATION => false]);
        curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return $code;
    };
    $body = function (string $path) use ($base): string {
        $ch = curl_init($base . $path);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15]);
        $out = (string) curl_exec($ch);
        curl_close($ch);
        return $out;
    };

    /*
     * A site in maintenance mode is meant to answer 503 to a signed-out
     * visitor, so asserting 200 would report the feature working as two
     * failures. The expectation flips instead, which also checks that the
     * gate is actually holding.
     */
    $down = false;
    try {
        require_once __DIR__ . '/../config/database.php';
        require_once __DIR__ . '/../includes/maintenance.php';
        $down = jm_maintenance_on();
    } catch (Throwable $e) {
        // No database from here: fall through and assume the site is up.
    }

    if ($down) {
        echo "  [note] maintenance mode is ON, so public pages are expected to answer 503\n";
        check('GET / -> 503 while down',        $status('/') === 503);
        check('GET /pricing -> 503 while down', $status('/pricing') === 503);
    } else {
        check('GET / -> 200',        $status('/') === 200);
        check('GET /pricing -> 200', $status('/pricing') === 200);
    }

    check('GET /jobs reachable',     in_array($status('/jobs'), [200, 301, 302, 503], true));
    // Sign-in stays open either way, which is what stops maintenance mode
    // locking the person who switched it on out of the switch.
    check('GET /auth/login -> 200',  $status('/auth/login') === 200);
    check('uploads PHP blocked (403)', $status('/uploads/__smoketest.php') === 403);
    check('bad unsubscribe token shows invalid', str_contains($body('/unsubscribe?e=a@b.com&t=bad'), 'invalid'));
}

// Tool registry audit. No database needed: it reads the files.
require_once __DIR__ . '/../includes/tools.php';
require_once __DIR__ . '/../includes/monetization.php';
require_once __DIR__ . '/tools_audit.php';

$toolProblems = jm_tools_audit(dirname(__DIR__));
check('tool gating agrees with the registry', $toolProblems === []);
foreach ($toolProblems as $problem) {
    echo "      {$problem}\n";
}

// Schema audit. Skipped rather than failed when the database is unreachable,
// so the unit checks still run on a machine without MySQL.
require_once __DIR__ . '/schema_audit.php';

try {
    require_once __DIR__ . '/../config/database.php';
    $issues = jm_schema_audit(db(), dirname(__DIR__));

    check('no INSERT/UPDATE names a missing column', $issues === []);
    foreach ($issues as [$file, $kind, $table, $col]) {
        printf("      %-42s %-6s %s.%s\n", $file, $kind, $table, $col);
    }
} catch (Throwable $e) {
    echo "  [skip] schema audit: " . $e->getMessage() . "\n";
}

echo "\n== Result: {$passed} passed, {$failed} failed ==\n";
exit($failed === 0 ? 0 : 1);
