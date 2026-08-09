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

$stmt = $pdo->prepare("SELECT email, full_name FROM users WHERE user_id = ? LIMIT 1");
$stmt->execute([$userId]);
$user = $stmt->fetch();

$packs         = jm_credit_packs();
$bundles       = jm_bundles();
$creditBalance = jm_seeker_credit_balance($pdo, $userId);
$isPremium     = jm_seeker_is_premium($pdo, $userId);
$fromTool      = Security::clean($_GET['tool']   ?? '');
$isPaywall     = (bool)($_GET['paywall'] ?? false);

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::verifyCSRF()) {
        $error = 'Session expired. Refresh and try again.';
    } else {
        $type = Security::clean($_POST['purchase_type'] ?? 'pack');
        if ($type === 'bundle') {
            $bundleId  = Security::clean($_POST['bundle_id'] ?? 'job_toolkit');
            $b         = $bundles[$bundleId] ?? $bundles['job_toolkit'];
            $amount = $b['price']; $credits = $b['credits'];
            $label = 'Bundle: ' . $b['name']; $txnType = TXN_TYPE_BUNDLE;
            $ref   = jm_bundle_reference();
        } else {
            $packId  = Security::clean($_POST['pack_id'] ?? 'pack_5');
            $p       = $packs[$packId] ?? $packs['pack_5'];
            $amount = $p['price']; $credits = $p['credits'];
            $label = 'Credits: ' . $p['name']; $txnType = TXN_TYPE_SEEKER_CREDITS;
            $ref   = jm_credits_reference();
        }
        try {
            $pdo->prepare("
                INSERT INTO transactions (user_id, type, plan, amount, currency_code, ngn_equivalent, credits, txn_ref, method, status, created_at)
                VALUES (?, ?, ?, ?, 'NGN', ?, ?, ?, 'paystack', 'pending', NOW())
            ")->execute([$userId, $txnType, $label, $amount, $amount, $credits, $ref]);

            $response = Paystack::initializeTransaction(
                $user['email'], Paystack::toKobo((float)$amount), $ref,
                ['user_id'=>$userId,'payment_type'=>$txnType,'credits'=>$credits,
                 'custom_fields'=>[['display_name'=>'Item','variable_name'=>'item','value'=>$label],
                                   ['display_name'=>'Credits','variable_name'=>'credits','value'=>$credits]]],
                SITE_URL . '/payments/credits-callback.php' . ($fromTool ? '?tool=' . urlencode($fromTool) : '')
            );
            if (!empty($response['data']['authorization_url'])) {
                header('Location: ' . $response['data']['authorization_url']); exit;
            }
            throw new Exception($response['message'] ?? 'Could not start payment.');
        } catch (Throwable $e) {
            error_log('Credits payment: ' . $e->getMessage());
            $error = strpos($e->getMessage(), 'not configured') !== false
                ? 'Payment gateway not configured yet.' : 'Payment could not start. Please try again.';
        }
    }
}

$pageTitle = 'Buy Credits | ' . SITE_NAME;
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?></title>
    <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/minimal-jobmington.css?v=brand-15">
    <style>
    @keyframes fadeUp { from{opacity:0;transform:translateY(18px)} to{opacity:1;transform:translateY(0)} }

    /* Layout */
    .jm-cr-layout {
        display:grid;
        grid-template-columns:minmax(0,1fr) 300px;
        gap:40px; align-items:start; max-width:960px; margin:0 auto;
    }
    @media(max-width:760px){ .jm-cr-layout{ grid-template-columns:1fr; } }

    /* Balance pill */
    .jm-cr-balance {
        display:inline-flex; align-items:center; gap:10px;
        background:var(--jm-soft); border:1px solid var(--jm-line);
        border-radius:8px; padding:10px 16px;
        font-size:14px; font-weight:600; color:var(--jm-muted);
        margin-bottom:28px; animation: fadeUp .4s ease both;
    }
    .jm-cr-balance strong { color:var(--jm-ink); font-size:16px; }
    .jm-cr-premium-pill {
        background:var(--jm-green); color:#fff;
        font-size:10px; font-weight:800; padding:2px 9px; border-radius:99px;
    }

    /* Paywall notice */
    .jm-cr-paywall {
        border-left:3px solid var(--jm-orange);
        background:var(--jm-warm); border-radius:0 8px 8px 0;
        padding:14px 18px; margin-bottom:24px; font-size:14px;
        animation: fadeUp .4s ease both .05s;
    }
    .jm-cr-paywall strong { color:var(--jm-ink); }
    .jm-cr-paywall span { color:#9a5a00; }

    /* Pack cards */
    .jm-cr-packs {
        display:grid;
        grid-template-columns:repeat(auto-fit,minmax(180px,1fr));
        gap:14px; margin-bottom:24px;
    }
    .jm-cr-pack {
        border:2px solid var(--jm-line); border-radius:12px;
        padding:22px 18px; cursor:pointer; position:relative;
        transition:border-color .2s, background .2s, transform .2s, box-shadow .2s;
        background:#ffffff;
    }
    .jm-cr-pack:hover {
        border-color:#9ab8e0; background:var(--jm-soft);
        transform:translateY(-3px);
        box-shadow:0 8px 24px rgba(6,64,163,.08);
    }
    .jm-cr-pack.selected {
        border-color:var(--jm-blue);
        background:linear-gradient(135deg,#f0f7ff,#fff);
        box-shadow:0 4px 16px rgba(6,64,163,.12);
    }
    .jm-cr-pack.best {
        border-color:var(--jm-orange);
    }
    .jm-cr-pack.best.selected {
        border-color:var(--jm-blue);
    }
    .jm-cr-pack-name { font-size:13px; font-weight:800; text-transform:uppercase; letter-spacing:.06em; color:var(--jm-muted); margin-bottom:8px; }
    .jm-cr-pack-price { font-size:28px; font-weight:800; color:var(--jm-ink); line-height:1; }
    .jm-cr-pack-credits { font-size:13px; color:var(--jm-muted); margin-top:5px; }
    .jm-cr-pack-save {
        display:inline-block; margin-top:8px;
        background:#dcfce7; color:#166534; font-size:11px;
        font-weight:800; padding:2px 9px; border-radius:99px;
    }
    .jm-cr-pack-badge {
        position:absolute; top:-11px; left:50%; transform:translateX(-50%);
        background:var(--jm-orange); color:var(--jm-ink); font-size:10px;
        font-weight:800; text-transform:uppercase; letter-spacing:.05em;
        padding:3px 10px; border-radius:99px; white-space:nowrap;
    }
    .jm-cr-pack-check {
        position:absolute; top:14px; right:14px;
        width:20px; height:20px; border-radius:999px;
        border:2px solid var(--jm-line); background:#fff;
        transition:all .2s; display:grid; place-items:center;
    }
    .jm-cr-pack.selected .jm-cr-pack-check {
        background:var(--jm-blue); border-color:var(--jm-blue);
    }
    .jm-cr-pack-check::after {
        content:''; width:8px; height:5px;
        border-left:2px solid #fff; border-bottom:2px solid #fff;
        transform:rotate(-45deg) translateY(-1px);
        opacity:0; transition:opacity .15s;
    }
    .jm-cr-pack.selected .jm-cr-pack-check::after { opacity:1; }

    /* Bundle */
    .jm-cr-bundle {
        border:1px solid var(--jm-line); border-radius:12px;
        padding:24px 28px; background:#ffffff;
        display:flex; align-items:center; justify-content:space-between;
        gap:20px; flex-wrap:wrap; margin-bottom:28px;
        transition:border-color .2s, box-shadow .2s;
    }
    .jm-cr-bundle:hover {
        border-color:#b6cceb;
        box-shadow:0 8px 24px rgba(6,64,163,.08);
    }
    .jm-cr-bundle-tag {
        display:inline-flex; align-items:center;
        background:var(--jm-warm); border:1px solid #f3d4a3;
        color:#9a5a00; font-size:11px; font-weight:800;
        padding:3px 10px; border-radius:99px; margin-bottom:8px;
    }
    .jm-cr-bundle-includes { display:flex; gap:8px; flex-wrap:wrap; margin-top:10px; }
    .jm-cr-bundle-includes span {
        background:var(--jm-soft); border:1px solid var(--jm-line);
        font-size:12px; font-weight:700; padding:4px 12px; border-radius:99px;
        color:var(--jm-ink);
    }
    .jm-cr-bundle-price strong {
        display:block; font-size:34px; font-weight:800; color:var(--jm-ink); line-height:1;
    }
    .jm-cr-bundle-price small { font-size:12px; color:var(--jm-muted); }

    /* Sidebar */
    .jm-cr-sidebar {
        border:1px solid var(--jm-line); border-radius:12px;
        background:linear-gradient(160deg,#f4f8ff,#ffffff);
        padding:26px 24px; position:sticky; top:20px;
        animation: fadeUp .5s ease both .15s;
    }
    .jm-cr-tool-list { list-style:none; padding:0; margin:12px 0 0; display:grid; gap:10px; }
    .jm-cr-tool-list li {
        display:flex; align-items:center; justify-content:space-between;
        font-size:13px; gap:10px; padding:8px 0;
        border-bottom:1px solid var(--jm-line);
    }
    .jm-cr-tool-list li:last-child { border-bottom:none; }
    .jm-cr-tool-name { color:var(--jm-ink); font-weight:600; }
    .jm-cr-tool-cost { color:var(--jm-blue); font-weight:800; white-space:nowrap; }
    .jm-cr-tool-cost.free { color:var(--jm-green); }

    /* Section labels */
    .jm-cr-label {
        font-size:11px; font-weight:800; text-transform:uppercase;
        letter-spacing:.08em; color:var(--jm-muted); display:block; margin-bottom:8px;
    }
    .jm-cr-h { font-size:22px; font-weight:700; color:var(--jm-ink); margin:0 0 5px; }
    .jm-cr-sub { font-size:14px; color:var(--jm-muted); margin:0 0 20px; }
    </style>
</head>
<body class="jm-minimal">
<div class="jm-shell">
    <header class="jm-header">
        <a class="jm-logo" href="<?= SITE_URL ?>/"><img src="<?= ASSETS_URL ?>/images/badge.png?v=logo-8" alt=""><span>Jobmington</span></a>
        <nav class="jm-nav">
            <a href="<?= SITE_URL ?>/jobs/">Jobs</a>
            <a class="jm-button secondary" href="<?= SITE_URL ?>/seeker/dashboard.php">Dashboard</a>
        </nav>
    </header>

    <!-- Hero -->
    <div style="padding:40px 0 36px;animation:fadeUp .45s ease both;">
        <p class="jm-kicker" style="font-size:13px;letter-spacing:.08em;text-transform:uppercase;font-weight:800;">AI Tool Credits</p>
        <h1 style="font-size:clamp(28px,4vw,44px);margin:0 0 10px;">Pay only for what you use.</h1>
        <p style="color:var(--jm-muted);font-size:16px;margin:0;">Credits never expire. Use them on any AI tool, any time.</p>
    </div>

    <?php if ($error): ?>
        <div class="jm-alert" style="max-width:960px;margin:0 auto 24px;"><?= e($error) ?></div>
    <?php endif; ?>

    <?php if ($isPaywall && $fromTool): $tool = jm_ai_tool($fromTool); ?>
    <div class="jm-cr-paywall" style="max-width:960px;margin:0 auto 0;">
        <strong><?= e($tool['name'] ?? $fromTool) ?> requires <?= $tool['credit_cost'] ?? 1 ?> credit<?= ($tool['credit_cost'] ?? 1) > 1 ? 's' : '' ?>.</strong>
        <span> Your current balance: <?= $creditBalance ?> credit<?= $creditBalance !== 1 ? 's' : '' ?>. Buy a pack below to continue.</span>
    </div>
    <?php endif; ?>

    <!-- Balance -->
    <div class="jm-cr-balance">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--jm-blue)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
        <span>Balance: <strong><?= $creditBalance ?> credit<?= $creditBalance !== 1 ? 's' : '' ?></strong></span>
        <?php if ($isPremium): ?><span class="jm-cr-premium-pill">Premium — unlimited tools</span><?php endif; ?>
    </div>

    <?php if (!$isPremium): ?>
    <div style="max-width:960px;margin:0 auto 28px;display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;border:1px solid var(--jm-line);border-radius:10px;padding:18px 24px;background:#ffffff;">
        <div>
            <strong style="display:block;font-size:15px;color:var(--jm-ink);margin-bottom:3px;">Want unlimited access?</strong>
            <span style="font-size:13px;color:var(--jm-muted);">Premium gives you unlimited AI tools for just <?= jm_format_ngn(PRICE_SEEKER_PREMIUM_MONTHLY) ?>/month.</span>
        </div>
        <a class="jm-button" href="<?= SITE_URL ?>/payments/seeker-premium.php">Go Premium →</a>
    </div>
    <?php endif; ?>

    <div class="jm-cr-layout" style="max-width:960px;margin:0 auto;">
        <!-- Main -->
        <div>
            <!-- Packs -->
            <span class="jm-cr-label">Credit packs</span>
            <h2 class="jm-cr-h">Pick your pack.</h2>
            <p class="jm-cr-sub">More credits = more savings. Credits never expire.</p>

            <form method="post" id="credits-form">
                <?= Security::csrfField() ?>
                <input type="hidden" name="purchase_type" value="pack">
                <input type="hidden" name="pack_id" id="selected-pack-id" value="pack_5">

                <div class="jm-cr-packs">
                    <?php foreach ($packs as $packId => $pack): $isBest = $pack['id'] === 'pack_5'; ?>
                    <div class="jm-cr-pack <?= $isBest ? 'best' : '' ?> <?= $packId === 'pack_5' ? 'selected' : '' ?>"
                         onclick="selectPack('<?= e($packId) ?>', this)">
                        <?php if ($pack['badge']): ?><span class="jm-cr-pack-badge"><?= e($pack['badge']) ?></span><?php endif; ?>
                        <div class="jm-cr-pack-check"></div>
                        <div class="jm-cr-pack-name"><?= e($pack['name']) ?></div>
                        <div class="jm-cr-pack-price"><?= jm_format_ngn($pack['price']) ?></div>
                        <div class="jm-cr-pack-credits"><?= $pack['credits'] ?> credit<?= $pack['credits'] > 1 ? 's' : '' ?></div>
                        <?php if ($pack['savings'] > 0): ?>
                            <span class="jm-cr-pack-save">Save <?= jm_format_ngn($pack['savings']) ?></span>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>

                <button class="jm-button" type="submit" style="min-height:48px;padding:0 28px;font-size:15px;">
                    Buy credits — Pay with Paystack
                </button>
            </form>

            <!-- Bundles -->
            <div style="margin-top:40px;">
                <span class="jm-cr-label">Bundle</span>
                <h2 class="jm-cr-h">Everything in one shot.</h2>
                <p class="jm-cr-sub">The Job Application Toolkit bundles three tools at a discount.</p>

                <?php foreach ($bundles as $bundleId => $bundle): ?>
                <form method="post">
                    <?= Security::csrfField() ?>
                    <input type="hidden" name="purchase_type" value="bundle">
                    <input type="hidden" name="bundle_id" value="<?= e($bundleId) ?>">
                    <div class="jm-cr-bundle">
                        <div>
                            <span class="jm-cr-bundle-tag"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg> <?= e($bundle['badge']) ?></span>
                            <strong style="font-size:17px;display:block;color:var(--jm-ink);"><?= e($bundle['name']) ?></strong>
                            <p style="margin:4px 0 0;font-size:13px;color:var(--jm-muted);"><?= e($bundle['description']) ?></p>
                            <div class="jm-cr-bundle-includes">
                                <?php foreach ($bundle['includes'] as $item): ?><span><?= e($item) ?></span><?php endforeach; ?>
                            </div>
                        </div>
                        <div class="jm-cr-bundle-price">
                            <strong><?= jm_format_ngn($bundle['price']) ?></strong>
                            <small>one-off</small>
                            <div style="margin-top:12px;">
                                <button class="jm-button" type="submit">Buy bundle</button>
                            </div>
                        </div>
                    </div>
                </form>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Sidebar -->
        <aside class="jm-cr-sidebar">
            <span style="font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.08em;color:var(--jm-muted);">What credits buy</span>
            <h3 style="font-size:18px;margin:8px 0 0;color:var(--jm-ink);">Tool cost guide</h3>
            <ul class="jm-cr-tool-list">
                <?php foreach (jm_ai_tools() as $tool): ?>
                <li>
                    <span class="jm-cr-tool-name"><?= e($tool['name']) ?></span>
                    <?php if ($tool['is_free']): ?>
                        <span class="jm-cr-tool-cost free">Free</span>
                    <?php else: ?>
                        <span class="jm-cr-tool-cost"><?= $tool['credit_cost'] ?> credit<?= $tool['credit_cost'] > 1 ? 's' : '' ?></span>
                    <?php endif; ?>
                </li>
                <?php endforeach; ?>
            </ul>
            <div style="margin-top:20px;padding:14px;background:var(--jm-soft);border:1px solid var(--jm-line);border-radius:8px;">
                <p style="margin:0;font-size:13px;color:var(--jm-muted);line-height:1.6;">
                    <strong style="color:var(--jm-ink);">Credits never expire.</strong>
                    Buy when it suits you and use at your own pace.
                </p>
            </div>
            <div style="margin-top:14px;">
                <a href="<?= SITE_URL ?>/payments/seeker-premium.php"
                   style="display:block;text-align:center;padding:12px;border:1px solid var(--jm-line);border-radius:8px;font-size:13px;font-weight:700;color:var(--jm-blue);text-decoration:none;transition:background .15s,border-color .15s;">
                    Or go Premium for unlimited access →
                </a>
            </div>
        </aside>
    </div>

    <?php jm_minimal_footer(); ?>
</div>
<script>
function selectPack(id, el) {
    document.getElementById('selected-pack-id').value = id;
    document.querySelectorAll('.jm-cr-pack').forEach(c => c.classList.remove('selected'));
    el.classList.add('selected');
}
</script>
</body>
</html>
