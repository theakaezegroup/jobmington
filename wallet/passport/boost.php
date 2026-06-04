<?php
/**
 * JOBMINGTON - Talent Passport: Boost Visibility
 * Spend Seeds to feature the passport to employers for 7 days.
 */
define('JOBMINGTON', true);
require_once __DIR__ . '/../../config/env.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/seeds.php';

Session::start();
$pdo = db();

if (!Session::isLoggedIn()) {
    header('Location: ' . SITE_URL . '/auth/login.php?redirect=' . urlencode('/jobmington/wallet/passport/boost.php'));
    exit;
}
$userId = (int) Session::userId();

// Load passport (must exist — created on first passport view)
$stmt = $pdo->prepare("SELECT * FROM talent_passports WHERE user_id = ? LIMIT 1");
$stmt->execute([$userId]);
$passport = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$passport) {
    header('Location: ' . SITE_URL . '/wallet/passport/');
    exit;
}

if (!defined('PRICE_PASSPORT_BOOST_SEEDS')) define('PRICE_PASSPORT_BOOST_SEEDS', 200);
$boostCost  = (int) PRICE_PASSPORT_BOOST_SEEDS;
$wallet     = jm_wallet_summary($userId);
$msg = ''; $err = '';

// Active boost window (uses last_featured_at as the boost timestamp)
$boostedUntil = (!empty($passport['last_featured_at']))
    ? strtotime($passport['last_featured_at'] . ' +7 days') : 0;
$isBoosted = $boostedUntil > time();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['boost'])) {
    if (!Security::verifyCSRF()) {
        $err = 'Session expired. Please try again.';
    } elseif ($isBoosted) {
        $err = 'Your passport is already boosted.';
    } else {
        $pay = jm_pay_with_seeds($userId, (float) $boostCost, 'passport_boost', 'Talent Passport visibility boost (7 days)', (int) $passport['passport_id']);
        if (!empty($pay['success'])) {
            try {
                $pdo->prepare("UPDATE talent_passports
                               SET last_featured_at = NOW(),
                                   times_featured = times_featured + 1,
                                   level_points = level_points + 10
                               WHERE passport_id = ?")->execute([(int) $passport['passport_id']]);
            } catch (Throwable $e) {}
            header('Location: ' . SITE_URL . '/wallet/passport/boost.php?boosted=1');
            exit;
        }
        $err = $pay['message'] ?? 'Payment failed.';
    }
}
if (isset($_GET['boosted'])) { $msg = 'Your passport is now boosted for 7 days.'; $isBoosted = true; $boostedUntil = time() + 7 * 86400; }

$canAfford = $wallet['seeds'] >= $boostCost;
$pageTitle = 'Boost Talent Passport - ' . SITE_NAME;
require_once __DIR__ . '/../../includes/header.php';
?>
<div class="min-h-screen bg-slate-950 py-10">
    <div class="max-w-2xl mx-auto px-4">
        <a href="<?= SITE_URL ?>/wallet/passport/" class="text-slate-400 hover:text-white text-xs uppercase tracking-wider mb-4 inline-block"><i class="fas fa-arrow-left mr-2"></i>Talent Passport</a>

        <div class="rounded-2xl border border-white/10 bg-gradient-to-br from-slate-900 to-slate-950 p-8">
            <div class="flex items-center gap-3 mb-5">
                <div class="h-12 w-12 rounded-xl bg-amber-500/15 border border-amber-500/25 flex items-center justify-center text-amber-400"><i class="fas fa-rocket text-xl"></i></div>
                <div>
                    <h1 class="text-white font-bold text-xl">Boost Visibility</h1>
                    <p class="text-slate-400 text-sm">Feature your passport to employers for 7 days</p>
                </div>
            </div>

            <?php if ($msg): ?><div class="bg-emerald-500/10 border border-emerald-500/30 text-emerald-300 rounded-xl px-4 py-3 mb-5 text-sm"><i class="fas fa-check-circle mr-2"></i><?= e($msg) ?></div><?php endif; ?>
            <?php if ($err): ?><div class="bg-red-500/10 border border-red-500/30 text-red-300 rounded-xl px-4 py-3 mb-5 text-sm"><i class="fas fa-exclamation-circle mr-2"></i><?= e($err) ?></div><?php endif; ?>

            <ul class="space-y-2 text-sm text-slate-300 mb-6">
                <li><i class="fas fa-check text-emerald-400 mr-2"></i>Priority placement in the employer talent pool</li>
                <li><i class="fas fa-check text-emerald-400 mr-2"></i>Highlighted passport card for 7 days</li>
                <li><i class="fas fa-check text-emerald-400 mr-2"></i>+10 passport level points</li>
            </ul>

            <div class="flex items-center justify-between rounded-xl bg-white/[0.03] border border-white/10 px-4 py-3 mb-5">
                <span class="text-slate-400 text-sm">Cost</span>
                <span class="text-white font-bold"><?= number_format($boostCost) ?> <span class="text-amber-400">Seeds</span> <span class="text-slate-500 text-xs">/ 7 days</span></span>
            </div>
            <div class="flex items-center justify-between text-xs text-slate-500 mb-6">
                <span>Your balance: <span class="text-emerald-400 font-bold"><?= number_format($wallet['seeds']) ?></span> Seeds &middot; <span class="text-sky-400 font-bold"><?= number_format($wallet['credits']) ?></span> Credits</span>
                <a href="<?= SITE_URL ?>/wallet/" class="text-amber-400 hover:underline">Top up / convert</a>
            </div>

            <?php if ($isBoosted): ?>
                <div class="w-full text-center bg-emerald-500/10 border border-emerald-500/30 text-emerald-300 rounded-xl py-4 font-bold">
                    <i class="fas fa-bolt mr-2"></i>Boost active until <?= date('M j, Y', $boostedUntil) ?>
                </div>
            <?php elseif ($canAfford): ?>
                <form method="post">
                    <?= csrf_field() ?>
                    <button type="submit" name="boost" class="w-full bg-amber-500 hover:bg-amber-400 text-black font-bold py-4 rounded-xl transition">
                        <i class="fas fa-rocket mr-2"></i>Boost for <?= number_format($boostCost) ?> Seeds
                    </button>
                </form>
            <?php else: ?>
                <div class="bg-amber-500/10 border border-amber-500/25 rounded-xl p-4 text-center">
                    <p class="text-amber-300 text-sm font-medium mb-2">You need <?= number_format($boostCost - $wallet['seeds']) ?> more Seeds</p>
                    <a href="<?= SITE_URL ?>/wallet/" class="text-amber-400 hover:underline text-sm font-bold">Earn, top up, or convert Credits →</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
