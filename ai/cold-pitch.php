<?php
define('JOBMINGTON', true);
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/monetization.php';
require_once __DIR__ . '/../includes/seeker_premium.php';

Session::start();
Session::requireLogin('Sign in or create a free account to write your cold pitch. We will bring you straight back here.');

$pdo         = db();
$userId      = (int) Session::userId();
$isPremium   = jm_seeker_is_premium($pdo, $userId);
$userCredits = jm_seeker_credit_balance($pdo, $userId);
$toolCost    = TOOL_COST_COLD_PITCH;
$pageTitle   = 'Cold Pitch AI | Jobmington';
$activeAIPage = 'cold_pitch';

require_once __DIR__ . '/../includes/ai-header.php';
require_once __DIR__ . '/_tool_styles.php';
?>

<div class="jm-tool-wrap">
    <?= jm_breadcrumbs([['label' => 'Tools', 'url' => '/jobmington/tools/'], ['label' => 'Cold Pitch AI']]) ?>
    <div class="jm-tool-hero">
        <p class="jm-kicker" style="font-size:12px;letter-spacing:.1em;text-transform:uppercase;font-weight:800;margin-bottom:10px;">AI-Powered</p>
        <h1>Cold Pitch AI.</h1>
        <p>Write human, specific cold pitches that earn the micro-yes — for email, DM, or LinkedIn.</p>
    </div>

    <div class="jm-tool-grid">
        <!-- Form -->
        <aside class="jm-tool-aside">
            <div class="jm-tool-card">
                <label class="jm-tool-label">Channel</label>
                <div class="jm-tool-seg" id="cp-channel">
                    <button type="button" class="active" data-channel="email" onclick="ColdPitch.setChannel(this)">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="M22 7l-10 5L2 7"/></svg> Email
                    </button>
                    <button type="button" data-channel="dm" onclick="ColdPitch.setChannel(this)">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg> DM
                    </button>
                    <button type="button" data-channel="linkedin" onclick="ColdPitch.setChannel(this)">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-4 0v7h-4v-7a6 6 0 0 1 6-6z"/><rect x="2" y="9" width="4" height="12"/><circle cx="4" cy="4" r="2"/></svg> LinkedIn
                    </button>
                </div>

                <label class="jm-tool-label" for="cp-recipient">Who are you pitching? <span class="req">*</span></label>
                <input id="cp-recipient" class="jm-tool-input" placeholder="e.g. Head of Growth at a Lagos fintech">

                <label class="jm-tool-label" for="cp-goal">The ask — your micro-yes <span class="req">*</span></label>
                <textarea id="cp-goal" class="jm-tool-textarea" placeholder="e.g. a 10-minute call to share 2 growth ideas; a referral; a reply pointing me to the right person…"></textarea>

                <label class="jm-tool-label" for="cp-offer">What you bring / context <span class="opt">(optional)</span></label>
                <textarea id="cp-offer" class="jm-tool-textarea" placeholder="e.g. 3 years running paid acquisition; grew a newsletter to 20k; I shipped a similar feature at…"></textarea>

                <button class="jm-button jm-tool-btn" id="cp-generate" onclick="ColdPitch.run()">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 2L11 13"/><path d="M22 2l-7 20-4-9-9-4 20-7z"/></svg>
                    Write my pitch
                </button>
            </div>

            <div class="jm-tool-credit">
                <span class="jm-tool-credit-label">Access</span>
                <?php if ($isPremium): ?>
                    <span class="jm-tool-premium-pill">Premium — unlimited</span>
                <?php else: ?>
                    <span class="jm-tool-credit-val"><?= $userCredits ?> credit<?= $userCredits !== 1 ? 's' : '' ?> · <?= $toolCost ?>/pitch</span>
                <?php endif; ?>
            </div>
            <?php if (!$isPremium): ?>
            <div style="text-align:center;">
                <a href="<?= SITE_URL ?>/payments/seeker-premium.php" style="font-size:13px;font-weight:700;color:var(--jm-blue);text-decoration:none;">Go Premium for unlimited access →</a>
            </div>
            <?php endif; ?>
        </aside>

        <!-- Result -->
        <main class="jm-tool-result">
            <div class="jm-tool-result-head">
                <span>Your cold pitch</span>
                <button class="jm-tool-copy hidden" id="cp-copy" onclick="ColdPitch.copy()">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                    Copy
                </button>
            </div>
            <div class="jm-tool-result-body">
                <div class="jm-tool-empty" id="cp-empty">
                    <div class="jm-tool-empty-icon">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 2L11 13"/><path d="M22 2l-7 20-4-9-9-4 20-7z"/></svg>
                    </div>
                    <div>
                        <h3>No pitch yet</h3>
                        <p>Pick a channel, say who you’re pitching and what you want, then click “Write my pitch”.</p>
                    </div>
                </div>
                <div id="cp-output" style="display:none;"></div>
            </div>
        </main>
    </div>
</div>

<script>
const JOBMINGTON_SITE_URL = <?= json_encode(SITE_URL) ?>;
const ColdPitch = {
    channel: 'email',
    lastMessage: '',
    e: (v) => { const d = document.createElement('div'); d.textContent = v == null ? '' : String(v); return d.innerHTML; },

    setChannel: (btn) => {
        ColdPitch.channel = btn.dataset.channel;
        document.querySelectorAll('#cp-channel button').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
    },

    run: async () => {
        const recipient = document.getElementById('cp-recipient').value.trim();
        const goal      = document.getElementById('cp-goal').value.trim();
        if (!recipient || !goal) { JM.toast('Tell us who you are pitching and what you want.', 'warning'); return; }

        const isPremium = <?= $isPremium ? 'true' : 'false' ?>;
        const balance   = <?= $userCredits ?>;
        const cost      = <?= $toolCost ?>;
        if (!isPremium && balance < cost) {
            JM.toast(`You need ${cost} credit but only have ${balance}.`, 'error', 'Insufficient credits');
            setTimeout(() => window.location.href = `${JOBMINGTON_SITE_URL}/payments/credits.php?tool=cold_pitch&paywall=1`, 1800);
            return;
        }

        const btn = document.getElementById('cp-generate');
        const orig = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = `<svg class="jm-spin" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M21 12a9 9 0 1 1-6.22-8.56"/></svg> Writing…`;

        try {
            const res = await fetch(`${JOBMINGTON_SITE_URL}/api/cold-pitch.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    recipient,
                    goal,
                    offer: document.getElementById('cp-offer').value.trim(),
                    channel: ColdPitch.channel,
                    charge: true,
                }),
            });
            const data = await res.json();
            btn.disabled = false; btn.innerHTML = orig;

            if (data.success) {
                ColdPitch.render(data.data.result);
                const msg = data.data.premium ? 'Premium — unlimited' : `−${data.data.cost} credit${data.data.cost !== 1 ? 's' : ''}`;
                JM.toast(`Pitch ready. ${msg}`, 'success', 'Done');
            } else if (data.error === 'insufficient_credits') {
                JM.toast(data.message, 'error', 'Insufficient credits');
                setTimeout(() => window.location.href = data.buy || `${JOBMINGTON_SITE_URL}/payments/credits.php`, 1800);
            } else {
                JM.toast(data.message || 'Something went wrong.', 'error');
            }
        } catch (err) {
            btn.disabled = false; btn.innerHTML = orig;
            JM.toast('Connection error. Please try again.', 'error');
        }
    },

    render: (result) => {
        ColdPitch.lastMessage = result.message || '';
        const variants = Array.isArray(result.variants) ? result.variants : [];
        const tips = Array.isArray(result.tips) ? result.tips : [];
        const svgCheck = `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>`;

        let html = '';
        if (result.subject) {
            html += `<div class="jm-tool-subject"><span>Subject</span><strong>${ColdPitch.e(result.subject)}</strong></div>`;
        }
        html += `<div class="jm-tool-letter">${ColdPitch.e(result.message || '')}</div>`;
        variants.forEach(v => {
            html += `<div class="jm-tool-variant"><span>Alternative opener</span>${ColdPitch.e(v)}</div>`;
        });
        if (tips.length) {
            html += `<div class="jm-tool-highlights"><div class="jm-tool-highlights-label">Send tips</div>${tips.map(t => `<div class="jm-tool-highlight">${svgCheck} ${ColdPitch.e(t)}</div>`).join('')}</div>`;
        }

        document.getElementById('cp-empty').style.display = 'none';
        const out = document.getElementById('cp-output');
        out.style.display = 'block';
        out.innerHTML = html;
        document.getElementById('cp-copy').classList.remove('hidden');
    },

    copy: () => {
        if (!ColdPitch.lastMessage) return;
        navigator.clipboard.writeText(ColdPitch.lastMessage)
            .then(() => JM.toast('Pitch copied to clipboard.', 'success'))
            .catch(() => JM.toast('Could not copy. Select and copy manually.', 'error'));
    },
};
</script>

<?php require_once __DIR__ . '/../includes/ai-footer.php'; ?>
