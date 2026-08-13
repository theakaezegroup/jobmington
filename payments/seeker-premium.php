<?php
define('JOBMINGTON', true);
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/paystack.php';
require_once __DIR__ . '/../includes/monetization.php';
require_once __DIR__ . '/../includes/seeker_premium.php';

Session::start();
Session::requireLogin();

$pdo    = db();
$userId = (int) Session::userId();

$selectedPlan = Security::clean($_GET['plan'] ?? 'monthly');
if (!isset(jm_seeker_plans()[$selectedPlan])) $selectedPlan = 'monthly';

$plans        = jm_seeker_plans();
$currentSub   = jm_seeker_subscription($pdo, $userId);
$creditBalance = jm_seeker_credit_balance($pdo, $userId);

$stmt = $pdo->prepare("SELECT email, full_name FROM users WHERE user_id = ? LIMIT 1");
$stmt->execute([$userId]);
$user = $stmt->fetch();

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::verifyCSRF()) {
        $error = 'Session expired. Please refresh and try again.';
    } else {
        $plan    = Security::clean($_POST['plan'] ?? 'monthly');
        if (!isset($plans[$plan])) $plan = 'monthly';
        $planData = $plans[$plan];
        $ref      = jm_seeker_premium_reference();
        try {
            $pdo->prepare("
                INSERT INTO transactions (user_id, type, plan, amount, currency_code, ngn_equivalent, txn_ref, method, status, created_at)
                VALUES (?, ?, ?, ?, 'NGN', ?, ?, 'paystack', 'pending', NOW())
            ")->execute([$userId, TXN_TYPE_SEEKER_PREMIUM, 'Seeker Premium: ' . ucfirst($plan), $planData['price'], $planData['price'], $ref]);

            $response = Paystack::initializeTransaction(
                $user['email'], Paystack::toKobo((float)$planData['price']), $ref,
                ['user_id' => $userId, 'payment_type' => TXN_TYPE_SEEKER_PREMIUM, 'seeker_plan' => $plan,
                 'custom_fields' => [['display_name'=>'Plan','variable_name'=>'plan','value'=>$planData['name']]]],
                SITE_URL . '/payments/seeker-premium-callback.php'
            );
            if (!empty($response['data']['authorization_url'])) {
                header('Location: ' . $response['data']['authorization_url']); exit;
            }
            throw new Exception($response['message'] ?? 'Could not start payment.');
        } catch (Throwable $e) {
            error_log('Seeker premium: ' . $e->getMessage());
            $error = strpos($e->getMessage(), 'not configured') !== false
                ? 'Payment gateway not configured yet.' : 'Payment could not start. Please try again.';
        }
    }
}

$pageTitle = 'Upgrade to Premium | ' . SITE_NAME;
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?></title>
    <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/minimal-jobmington.css?v=brand-26">
    <style>
    @keyframes fadeUp { from{opacity:0;transform:translateY(18px)} to{opacity:1;transform:translateY(0)} }
    @keyframes fadeIn { from{opacity:0} to{opacity:1} }

    .jm-prem-layout {
        display:grid;
        grid-template-columns:minmax(0,1fr) minmax(300px,420px);
        gap:40px; align-items:start;
        max-width:940px; margin:0 auto;
        animation: fadeUp .5s ease both .1s;
    }
    @media(max-width:760px){ .jm-prem-layout{ grid-template-columns:1fr; } }

    /* Left: plan selector */
    .jm-plan-option { display:block; cursor:pointer; }
    .jm-plan-option input { position:absolute; opacity:0; width:0; height:0; }
    .jm-plan-inner {
        border:2px solid var(--jm-line); border-radius:12px;
        padding:22px 24px; margin-bottom:14px;
        transition:border-color .2s, background .2s, transform .18s;
        position:relative; background:#ffffff;
    }
    .jm-plan-option:hover .jm-plan-inner {
        border-color:#9ab8e0; background:var(--jm-soft);
        transform:translateY(-2px);
    }
    .jm-plan-option input:checked + .jm-plan-inner {
        border-color:var(--jm-blue);
        background:linear-gradient(135deg,#f4f8ff,#ffffff);
        box-shadow:0 4px 16px rgba(6,64,163,.1);
    }
    .jm-plan-check {
        position:absolute; top:20px; right:20px;
        width:20px; height:20px; border-radius:999px;
        border:2px solid var(--jm-line);
        transition:border-color .2s, background .2s;
        display:grid; place-items:center;
    }
    .jm-plan-option input:checked + .jm-plan-inner .jm-plan-check {
        background:var(--jm-blue); border-color:var(--jm-blue);
    }
    .jm-plan-check::after {
        content:''; width:8px; height:5px;
        border-left:2px solid #fff; border-bottom:2px solid #fff;
        transform:rotate(-45deg) translateY(-1px);
        opacity:0; transition:opacity .15s;
    }
    .jm-plan-option input:checked + .jm-plan-inner .jm-plan-check::after { opacity:1; }

    .jm-plan-name { font-size:13px; font-weight:800; text-transform:uppercase; letter-spacing:.07em; color:var(--jm-blue); margin-bottom:6px; }
    .jm-plan-price { display:flex; align-items:baseline; gap:4px; }
    .jm-plan-price strong { font-size:34px; font-weight:800; color:var(--jm-ink); }
    .jm-plan-price span { color:var(--jm-muted); font-size:14px; }
    .jm-plan-badge {
        display:inline-block; background:var(--jm-orange); color:var(--jm-ink);
        font-size:10px; font-weight:800; text-transform:uppercase;
        letter-spacing:.06em; padding:3px 9px; border-radius:99px;
        margin-left:10px; vertical-align:middle;
    }
    .jm-plan-sub { font-size:13px; color:var(--jm-muted); margin-top:6px; }

    /* Right: perks sidebar */
    .jm-prem-sidebar {
        border:1px solid var(--jm-line); border-radius:12px;
        background:linear-gradient(160deg,#f4f8ff,#ffffff);
        padding:32px 28px;
        position:sticky; top:20px;
        animation: fadeUp .55s ease both .2s;
    }
    .jm-perk-list { list-style:none; padding:0; margin:18px 0 0; display:grid; gap:13px; }
    .jm-perk-list li {
        display:flex; align-items:flex-start; gap:10px;
        font-size:14px; color:var(--jm-ink); line-height:1.55;
    }
    .jm-perk-icon {
        flex:0 0 28px; width:28px; height:28px;
        background:#ecfdf8; border:1px solid #bde9df;
        border-radius:8px; display:grid; place-items:center;
        font-size:14px; margin-top:1px;
    }

    .jm-prem-active {
        border:1px solid #bde9df; border-radius:12px;
        background:#f0fdf9; padding:24px; text-align:center;
        animation: fadeIn .4s ease both;
    }
    .jm-prem-active-badge {
        display:inline-flex; align-items:center; gap:6px;
        background:var(--jm-green); color:#fff;
        font-size:12px; font-weight:800; padding:4px 12px;
        border-radius:99px; margin-bottom:10px;
    }
    </style>
</head>
<body class="jm-minimal">
<div class="jm-shell">
    <header class="jm-header">
        <a class="jm-logo" href="<?= SITE_URL ?>/"><img src="<?= ASSETS_URL ?>/images/badge.png?v=logo-8" alt=""><span>Jobmington</span></a>
            <?php require_once __DIR__ . '/../includes/navigation.php'; jm_workspace_nav([], ''); ?>
    </header>

    <!-- Hero -->
    <div style="text-align:center;padding:44px 0 40px;animation:fadeUp .45s ease both;">
        <p class="jm-kicker" style="font-size:13px;letter-spacing:.08em;text-transform:uppercase;font-weight:800;">Premium Membership</p>
        <h1 style="font-size:clamp(28px,4vw,46px);margin:0 0 12px;">Unlock your full potential.</h1>
        <p style="color:var(--jm-muted);font-size:16px;margin:0;">Unlimited AI tools, priority applications, and early job alerts.</p>
    </div>

    <?php if ($error): ?>
        <div class="jm-alert" style="max-width:680px;margin:0 auto 24px;"><?= e($error) ?></div>
    <?php endif; ?>

    <?php if ($currentSub): ?>
    <!-- Already premium -->
    <div class="jm-prem-active" style="max-width:480px;margin:0 auto;">
        <span class="jm-prem-active-badge"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg> Active Premium</span>
        <h2 style="margin:0 0 8px;font-size:22px;">You're already on Premium.</h2>
        <p style="color:var(--jm-muted);font-size:14px;margin:0 0 20px;">
            Your <strong><?= e(ucfirst($currentSub['plan'])) ?></strong> plan is active until
            <strong><?= date('d M Y', strtotime($currentSub['expires_at'])) ?></strong>.
        </p>
        <a class="jm-button secondary" href="<?= SITE_URL ?>/seeker/dashboard.php">Back to dashboard</a>
    </div>

    <?php else: ?>
    <!-- Plan selection -->
    <div class="jm-prem-layout">

        <!-- Left -->
        <div>
            <p style="font-size:13px;font-weight:800;text-transform:uppercase;letter-spacing:.08em;color:var(--jm-muted);margin:0 0 16px;">Choose your plan</p>
            <form method="post" id="prem-form">
                <?= Security::csrfField() ?>
                <?php foreach ($plans as $planId => $plan): $isAnnual = $planId === 'annual'; ?>
                <label class="jm-plan-option">
                    <input type="radio" name="plan" value="<?= e($planId) ?>"
                           <?= $selectedPlan === $planId ? 'checked' : '' ?>>
                    <div class="jm-plan-inner">
                        <div class="jm-plan-check"></div>
                        <div class="jm-plan-name"><?= e($plan['name']) ?></div>
                        <div class="jm-plan-price">
                            <strong><?= jm_format_ngn($plan['price']) ?></strong>
                            <span>/<?= $isAnnual ? 'year' : 'month' ?></span>
                            <?php if ($plan['badge']): ?>
                                <span class="jm-plan-badge"><?= e($plan['badge']) ?></span>
                            <?php endif; ?>
                        </div>
                        <?php if ($isAnnual): ?><div class="jm-plan-sub" style="color:var(--jm-green);font-weight:700;">= <?= jm_format_ngn(PRICE_SEEKER_PREMIUM_MONTHLY) ?>/mo — 2 months free</div><?php else: ?>
                            <div class="jm-plan-sub"><?= jm_format_ngn_with_usd($plan['price']) ?></div>
                        <?php endif; ?>
                    </div>
                </label>
                <?php endforeach; ?>

                <button class="jm-button" type="submit" style="width:100%;justify-content:center;min-height:50px;font-size:15px;margin-top:8px;">
                    Upgrade with Paystack →
                </button>
                <p style="text-align:center;font-size:12px;color:var(--jm-muted);margin-top:12px;">
                    Cancel any time. No hidden fees.
                </p>
            </form>

            <div style="border-top:1px solid var(--jm-line);padding-top:18px;margin-top:8px;">
                <p style="font-size:13px;color:var(--jm-muted);margin:0;">
                    Prefer to pay per use?
                    <a href="<?= SITE_URL ?>/payments/credits.php" style="color:var(--jm-blue);font-weight:700;">Buy credits instead →</a>
                </p>
            </div>
        </div>

        <!-- Right: perks sidebar -->
        <aside class="jm-prem-sidebar">
            <span style="font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.08em;color:var(--jm-muted);">What you unlock</span>
            <h2 style="font-size:22px;margin:8px 0 0;color:var(--jm-ink);">Everything in one membership.</h2>
            <ul class="jm-perk-list">
                <?php
                $perks = [
                    ['<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="10" rx="2"/><circle cx="12" cy="5" r="2"/><path d="M12 7v4"/><path d="M8 15h.01M12 15h.01M16 15h.01"/></svg>', 'Unlimited AI CV optimisation'],
                    ['<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="M2 8l10 6 10-6"/></svg>', 'Unlimited cover letter generation'],
                    ['<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 8v4l3 3"/></svg>', 'Interview prep (unlimited sessions)'],
                    ['<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>', 'Full skills gap reports with resource links'],
                    ['<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><line x1="9" y1="15" x2="15" y2="15"/></svg>', 'Download your CV as PDF and Word'],
                    ['<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="18 15 12 9 6 15"/></svg>', 'Priority application — shown higher to employers'],
                    ['<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>', 'Early job alerts — 24h before free users'],
                    ['<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/><line x1="11" y1="8" x2="11" y2="14"/><line x1="8" y1="11" x2="14" y2="11"/></svg>', 'Saved searches and email digests'],
                ];
                foreach ($perks as [$icon, $text]): ?>
                <li>
                    <span class="jm-perk-icon"><?= $icon ?></span>
                    <?= e($text) ?>
                </li>
                <?php endforeach; ?>
            </ul>
            <div style="margin-top:22px;padding-top:18px;border-top:1px solid var(--jm-line);display:flex;align-items:center;gap:8px;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--jm-green)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;"><polyline points="20 6 9 17 4 12"/></svg>
                <span style="font-size:13px;color:var(--jm-muted);">Your credits: <strong style="color:var(--jm-ink);"><?= $creditBalance ?></strong> — still usable even with Premium.</span>
            </div>
        </aside>
    </div>
    <?php endif; ?>

    <?php jm_minimal_footer(); ?>
</div>
</body>
</html>
