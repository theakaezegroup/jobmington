<?php
/**
 * JOBMINGTON - Redeem Seeds -> Credits
 * Bridges the free (Seeds) and paid (Credits) economies at SEEDS_PER_CREDIT.
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
    jsonError('Please log in to redeem Seeds.', 401);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Invalid request method.', 405);
}

$input   = json_decode(file_get_contents('php://input'), true);
$credits = (int) ($input['credits'] ?? 1);
if ($credits < 1 || $credits > 100) {
    jsonError('Choose between 1 and 100 credits to redeem.');
}

$userId = (int) Session::userId();
$result = jm_redeem_seeds_for_credits($userId, $credits);

if (!$result['success']) {
    jsonResponse([
        'success'        => false,
        'error'          => 'insufficient_seeds',
        'message'        => $result['message'],
        'seeds_balance'  => $result['seeds_balance'] ?? getSeedBalance($userId),
        'credit_balance' => $result['credit_balance'] ?? 0,
        'rate'           => SEEDS_PER_CREDIT,
    ], 402);
}

jsonSuccess([
    'credits_added'  => $result['credits_added'],
    'seeds_spent'    => $result['seeds_spent'],
    'seeds_balance'  => $result['seeds_balance'],
    'credit_balance' => $result['credit_balance'],
    'rate'           => SEEDS_PER_CREDIT,
], $result['message']);
