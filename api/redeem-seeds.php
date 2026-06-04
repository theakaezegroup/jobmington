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

$input     = json_decode(file_get_contents('php://input'), true) ?: [];
$direction = $input['direction'] ?? 'to_credits'; // to_credits | to_seeds
$credits   = (int) ($input['credits'] ?? 1);
if ($credits < 1 || $credits > 100) {
    jsonError('Choose between 1 and 100 credits.');
}

$userId = (int) Session::userId();

if ($direction === 'to_seeds') {
    $result = jm_convert_credits_to_seeds($userId, $credits);
    if (!$result['success']) {
        jsonResponse([
            'success'        => false,
            'error'          => 'insufficient_credits',
            'message'        => $result['message'],
            'seeds_balance'  => $result['seeds_balance'] ?? getSeedBalance($userId),
            'credit_balance' => $result['credit_balance'] ?? 0,
            'rate'           => SEEDS_PER_CREDIT_REVERSE,
        ], 402);
    }
    jsonSuccess([
        'direction'      => 'to_seeds',
        'seeds_added'    => $result['seeds_added'],
        'credits_spent'  => $result['credits_spent'],
        'seeds_balance'  => $result['seeds_balance'],
        'credit_balance' => $result['credit_balance'],
        'rate'           => SEEDS_PER_CREDIT_REVERSE,
    ], $result['message']);
}

// default: seeds -> credits
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
    'direction'      => 'to_credits',
    'credits_added'  => $result['credits_added'],
    'seeds_spent'    => $result['seeds_spent'],
    'seeds_balance'  => $result['seeds_balance'],
    'credit_balance' => $result['credit_balance'],
    'rate'           => SEEDS_PER_CREDIT,
], $result['message']);
