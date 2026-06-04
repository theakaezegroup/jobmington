<?php
/**
 * JOBMINGTON - Wallet (Seeds + Credits, converter, top-up, passport, history)
 */
define('JOBMINGTON', true);
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/seeds.php';

Session::start();
Session::requireLogin();
$pdo = db();
$userId = (int) Session::userId();

$wallet         = getWallet($userId) ?: ['balance' => 0, 'tool_credits' => 0, 'lifetime_earned' => 0, 'lifetime_spent' => 0];
$seeds          = (float) ($wallet['balance'] ?? 0);
$credits        = (int) ($wallet['tool_credits'] ?? 0);
$lifetimeEarned = (float) ($wallet['lifetime_earned'] ?? 0);
$lifetimeSpent  = (float) ($wallet['lifetime_spent'] ?? 0);
$transactions   = getRecentTransactions($userId, 12);
$packages       = getSeedPackages();
awardDailyLoginBonus($userId);

// Talent passport (for the linked card)
$passport = null;
try {
    $ps = $pdo->prepare("SELECT * FROM talent_passports WHERE user_id = ? LIMIT 1");
    $ps->execute([$userId]);
    $passport = $ps->fetch(PDO::FETCH_ASSOC) ?: null;
} catch (Throwable $e) {}

$fwd  = defined('SEEDS_PER_CREDIT') ? (int) SEEDS_PER_CREDIT : 100;
$rev  = defined('SEEDS_PER_CREDIT_REVERSE') ? (int) SEEDS_PER_CREDIT_REVERSE : 80;

$pageTitle = 'Wallet — ' . SITE_NAME;
require_once __DIR__ . '/../includes/header.php';

$svgSeed = '<svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M12 2C8 2 4 6 4 12c0 4 2 8 8 10 6-2 8-6 8-10 0-6-4-10-8-10zm0 4c1.2 0 2.4.8 3.2 2.4-.8-.4-2-.4-3.2.4-1.2-.8-2.4-.8-3.2-.4C9.6 6.8 10.8 6 12 6z"/></svg>';
?>
<style>
.jm-wal { max-width:1040px; margin:0 auto; padding:26px 20px 72px; }
.jm-wal-head { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px; margin-bottom:20px; }
.jm-wal-head h1 { font-size:clamp(22px,3.5vw,30px); font-weight:800; color:#061426; margin:0; }
.jm-wal-head p { font-size:13.5px; color:#53667f; margin:3px 0 0; }
.jm-wal-grid { display:grid; grid-template-columns:1.5fr 1fr; gap:16px; align-items:start; }
@media (max-width:820px){ .jm-wal-grid { grid-template-columns:1fr; } }

.jm-wal-bal { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
.jm-bal { border-radius:16px; padding:20px; color:#fff; position:relative; overflow:hidden; }
.jm-bal.seeds { background:linear-gradient(135deg,#0640a3,#052f78); }
.jm-bal.credits { background:linear-gradient(135deg,#0a1b3a,#13294f); }
.jm-bal .lab { font-size:11px; font-weight:800; letter-spacing:.14em; text-transform:uppercase; opacity:.7; display:flex; align-items:center; gap:7px; }
.jm-bal .num { font-size:34px; font-weight:800; letter-spacing:-.02em; margin-top:8px; line-height:1; }
.jm-bal .sub { font-size:12px; opacity:.7; margin-top:6px; }
.jm-bal .deco { position:absolute; right:-20px; bottom:-22px; opacity:.12; }
.jm-bal .deco svg { width:110px; height:110px; }

.jm-card { background:#fff; border:1px solid #e8edf5; border-radius:16px; padding:18px 20px; }
.jm-card h3 { font-size:13px; font-weight:800; color:#061426; text-transform:uppercase; letter-spacing:.05em; margin:0 0 4px; }
.jm-card .hint { font-size:12px; color:#7c8aa0; margin:0 0 14px; }

.jm-stat-row { display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-top:14px; }
.jm-stat { background:#f7faff; border:1px solid #eef3fb; border-radius:12px; padding:12px 14px; text-align:center; }
.jm-stat b { display:block; font-size:18px; font-weight:800; color:#061426; }
.jm-stat span { font-size:11px; color:#7c8aa0; text-transform:uppercase; letter-spacing:.06em; }

/* converter */
.jm-conv-row { display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
.jm-conv-row input { width:70px; border:1px solid #d8e4f4; border-radius:9px; padding:9px; text-align:center; font-weight:800; color:#061426; font-size:15px; }
.jm-conv-row input:focus { outline:2px solid #0640a3; border-color:#0640a3; }
.jm-conv-btn { flex:1; min-width:130px; border:1px solid #d8e4f4; border-radius:9px; padding:10px; font-size:12.5px; font-weight:800; cursor:pointer; background:#fff; color:#0640a3; }
.jm-conv-btn:hover { background:#eef5ff; }
.jm-conv-btn.alt { color:#0a6454; border-color:#cdeee5; } .jm-conv-btn.alt:hover { background:#e9f7f2; }
.jm-conv-msg { font-size:12px; margin-top:9px; min-height:16px; }

.jm-actions { display:grid; grid-template-columns:repeat(3,1fr); gap:10px; }
.jm-act { display:flex; flex-direction:column; align-items:center; gap:6px; background:#fff; border:1px solid #e8edf5; border-radius:12px; padding:14px 8px; font-size:12px; font-weight:700; color:#061426; text-decoration:none; cursor:pointer; }
.jm-act:hover { background:#f6f8fb; }
.jm-act .ic { width:34px; height:34px; border-radius:9px; background:#eef3ff; color:#0640a3; display:flex; align-items:center; justify-content:center; }

.jm-pass { background:linear-gradient(135deg,#f7faff,#eef4ff); border:1px solid #e1ebfa; border-radius:16px; padding:18px 20px; }
.jm-pass-top { display:flex; align-items:center; justify-content:space-between; gap:10px; }
.jm-pass-top b { font-size:14px; font-weight:800; color:#061426; }
.jm-pass-num { font-family:"Courier New",monospace; font-weight:800; color:#0640a3; font-size:14px; margin-top:6px; }
.jm-pass-cta { display:inline-flex; align-items:center; gap:6px; background:#0640a3; color:#fff; border-radius:9px; padding:9px 16px; font-size:13px; font-weight:800; text-decoration:none; margin-top:12px; }
.jm-pass-cta:hover { background:#052f78; }

.jm-tx { display:flex; align-items:center; gap:12px; padding:11px 0; border-bottom:1px solid #f1f5fb; }
.jm-tx:last-child { border-bottom:0; }
.jm-tx .ic { width:34px; height:34px; border-radius:9px; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.jm-tx.in .ic { background:#e6f5f1; color:#0a6454; } .jm-tx.out .ic { background:#fef2f2; color:#b42318; }
.jm-tx .desc { flex:1; min-width:0; } .jm-tx .desc b { display:block; font-size:13px; color:#061426; font-weight:700; }
.jm-tx .desc span { font-size:11.5px; color:#94a3b8; }
.jm-tx .amt { font-weight:800; font-size:13.5px; } .jm-tx.in .amt { color:#0a6454; } .jm-tx.out .amt { color:#b42318; }
.jm-tx-empty { text-align:center; padding:30px 10px; color:#94a3b8; font-size:13px; }
</style>

<div class="jm-wal">
    <div class="jm-wal-head">
        <div>
            <h1>Your Wallet</h1>
            <p>Seeds are earned. Credits are paid. Convert between them and unlock courses, ebooks &amp; certificates.</p>
        </div>
        <button type="button" class="jm-pass-cta" style="margin:0;background:#061426;" onclick="document.getElementById('jmTopup').showModal()">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
            Top up Seeds
        </button>
    </div>

    <div class="jm-wal-bal" style="margin-bottom:16px;">
        <div class="jm-bal seeds">
            <div class="lab"><?= $svgSeed ?> Seeds</div>
            <div class="num"><?= number_format($seeds, 0) ?></div>
            <div class="sub">Earned currency</div>
            <div class="deco"><?= $svgSeed ?></div>
        </div>
        <div class="jm-bal credits">
            <div class="lab"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M9.5 9.5a2.5 2.5 0 1 1 0 5"/></svg> Credits</div>
            <div class="num"><?= number_format($credits) ?></div>
            <div class="sub">Paid currency</div>
            <div class="deco"><svg width="110" height="110" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="1.4"><circle cx="12" cy="12" r="9"/></svg></div>
        </div>
    </div>

    <div class="jm-wal-grid">
        <div style="display:flex;flex-direction:column;gap:16px;">

            <!-- Converter -->
            <div class="jm-card">
                <h3>Convert</h3>
                <p class="hint">Seeds &harr; Credits &middot; <?= $fwd ?> Seeds = 1 Credit &middot; 1 Credit = <?= $rev ?> Seeds</p>
                <div class="jm-conv-row">
                    <input id="jmcAmount" type="number" min="1" max="100" value="1">
                    <span style="font-size:12.5px;color:#7c8aa0;">credits</span>
                    <button type="button" class="jm-conv-btn" data-dir="to_credits">Buy with Seeds</button>
                    <button type="button" class="jm-conv-btn alt" data-dir="to_seeds">Cash to Seeds</button>
                </div>
                <p id="jmcMsg" class="jm-conv-msg"></p>
                <div class="jm-stat-row">
                    <div class="jm-stat"><b><?= number_format($lifetimeEarned, 0) ?></b><span>Earned</span></div>
                    <div class="jm-stat"><b><?= number_format($lifetimeSpent, 0) ?></b><span>Spent</span></div>
                </div>
            </div>

            <!-- Quick actions -->
            <div class="jm-actions">
                <a class="jm-act" href="/jobmington/jobs/"><span class="ic"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/></svg></span>Browse jobs</a>
                <a class="jm-act" href="/jobmington/certificates/"><span class="ic"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="6"/><path d="M15.5 12.9 17 22l-5-3-5 3 1.5-9.1"/></svg></span>Certificates</a>
                <a class="jm-act" href="/jobmington/wallet/history.php"><span class="ic"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 3v18h18"/><path d="M7 14l4-4 3 3 5-6"/></svg></span>Full history</a>
            </div>

            <!-- Talent Passport -->
            <div class="jm-pass">
                <div class="jm-pass-top">
                    <b>Talent Passport</b>
                    <?php if ($passport): ?><span style="font-size:11px;color:#7c8aa0;text-transform:uppercase;letter-spacing:.08em;">Level <?= (int) ($passport['level'] ?? 1) ?></span><?php endif; ?>
                </div>
                <?php if ($passport): ?>
                    <div class="jm-pass-num"><?= e($passport['passport_number']) ?></div>
                    <a class="jm-pass-cta" href="/jobmington/wallet/passport/">Open passport
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                    </a>
                <?php else: ?>
                    <p style="font-size:13px;color:#53667f;margin:8px 0 0;">Claim your verified talent credential — showcase achievements to employers.</p>
                    <a class="jm-pass-cta" href="/jobmington/wallet/passport/">Get your passport</a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Transactions -->
        <div class="jm-card">
            <h3>Recent activity</h3>
            <p class="hint">Last 12 movements</p>
            <?php if (empty($transactions)): ?>
                <div class="jm-tx-empty">No transactions yet.<br>Earn Seeds by completing courses &amp; quizzes.</div>
            <?php else: foreach ($transactions as $t):
                $isIn = in_array(($t['type'] ?? ''), ['earn', 'bonus', 'refund', 'purchase', 'transfer_in'], true);
                $amt  = (float) ($t['amount'] ?? 0); ?>
                <div class="jm-tx <?= $isIn ? 'in' : 'out' ?>">
                    <span class="ic">
                        <?php if ($isIn): ?><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M12 19V5M5 12l7-7 7 7"/></svg>
                        <?php else: ?><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M12 5v14M5 12l7 7 7-7"/></svg><?php endif; ?>
                    </span>
                    <div class="desc">
                        <b><?= e($t['description'] ?: ucfirst((string)($t['type'] ?? 'Transaction'))) ?></b>
                        <span><?= isset($t['created_at']) ? date('M j, Y', strtotime($t['created_at'])) : '' ?></span>
                    </div>
                    <div class="amt"><?= $isIn ? '+' : '−' ?><?= number_format($amt, 0) ?></div>
                </div>
            <?php endforeach; endif; ?>
        </div>
    </div>
</div>

<!-- Top-up modal -->
<dialog id="jmTopup" style="border:0;border-radius:18px;padding:0;max-width:440px;width:92%;box-shadow:0 30px 80px -20px rgba(6,20,38,.4);">
    <div style="padding:22px 22px 8px;display:flex;align-items:center;justify-content:space-between;">
        <div><div style="font-size:17px;font-weight:800;color:#061426;">Top up Seeds</div><div style="font-size:12.5px;color:#7c8aa0;">Pay with card via Paystack</div></div>
        <button onclick="document.getElementById('jmTopup').close()" style="border:0;background:#f1f5f9;width:32px;height:32px;border-radius:8px;cursor:pointer;color:#475569;">&times;</button>
    </div>
    <form id="jmTopupForm" method="POST" action="/jobmington/payments/checkout.php">
        <input type="hidden" name="plan" id="selectedPlan" value="">
        <input type="hidden" name="amount" id="selectedAmount" value="">
        <input type="hidden" name="credits" id="selectedCredits" value="">
    </form>
    <div style="padding:6px 18px 20px;display:flex;flex-direction:column;gap:8px;">
        <?php foreach ($packages as $pkg): $totalSeeds = (int) $pkg['seeds_amount'] + (int) $pkg['bonus_seeds']; ?>
        <button type="button" onclick="jmSelectPackage('<?= e($pkg['name']) ?>', <?= (int) $pkg['price_ngn'] ?>, <?= $totalSeeds ?>)"
                style="display:flex;align-items:center;justify-content:space-between;gap:12px;border:1px solid <?= !empty($pkg['is_featured']) ? '#0640a3' : '#e8edf5' ?>;background:<?= !empty($pkg['is_featured']) ? '#f3f8ff' : '#fff' ?>;border-radius:12px;padding:13px 15px;cursor:pointer;text-align:left;">
            <div>
                <div style="font-weight:800;color:#061426;font-size:14px;"><?= e($pkg['name']) ?></div>
                <div style="font-size:12px;color:#53667f;margin-top:2px;"><?= number_format($totalSeeds) ?> Seeds<?php if ((int)$pkg['bonus_seeds'] > 0): ?> <span style="color:#0a6454;font-weight:700;">+<?= number_format((int)$pkg['bonus_seeds']) ?> bonus</span><?php endif; ?></div>
            </div>
            <div style="text-align:right;"><div style="font-weight:800;color:#061426;">₦<?= number_format((int)$pkg['price_ngn']) ?></div><div style="font-size:11px;color:#0640a3;font-weight:700;">Select →</div></div>
        </button>
        <?php endforeach; ?>
        <p style="font-size:12px;color:#7c8aa0;text-align:center;margin:8px 0 0;">Or earn free Seeds: courses +50 &middot; quizzes +25 &middot; daily login +5</p>
    </div>
</dialog>

<script>
function jmSelectPackage(plan, amount, credits){
    document.getElementById('selectedPlan').value = plan;
    document.getElementById('selectedAmount').value = amount;
    document.getElementById('selectedCredits').value = credits;
    document.getElementById('jmTopupForm').submit();
}
(function(){
    var amt = document.getElementById('jmcAmount'), msg = document.getElementById('jmcMsg');
    function nf(n){ return Number(n).toLocaleString(); }
    document.querySelectorAll('.jm-conv-btn').forEach(function(b){
        b.addEventListener('click', function(){
            var dir = b.getAttribute('data-dir');
            var credits = Math.max(1, Math.min(100, parseInt(amt.value||'1',10)));
            b.disabled = true; msg.style.color='#7c8aa0'; msg.textContent='Processing…';
            fetch('/jobmington/api/redeem-seeds.php', { method:'POST', headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'}, body: JSON.stringify({ direction: dir, credits: credits }) })
                .then(function(r){ return r.json(); }).then(function(d){
                    b.disabled = false;
                    if (d && d.success){ msg.style.color='#0a6454'; msg.textContent = d.message || 'Done.'; setTimeout(function(){ location.reload(); }, 800); }
                    else { msg.style.color='#b42318'; msg.textContent = (d && d.message) || 'Conversion failed.'; }
                }).catch(function(){ b.disabled=false; msg.style.color='#b42318'; msg.textContent='Network error.'; });
        });
    });
    if (new URLSearchParams(location.search).get('topup') === '1') { document.getElementById('jmTopup').showModal(); }
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
