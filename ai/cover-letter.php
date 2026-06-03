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
Session::requireLogin();

$pdo         = db();
$userId      = (int) Session::userId();
$isPremium   = jm_seeker_is_premium($pdo, $userId);
$userCredits = jm_seeker_credit_balance($pdo, $userId);
$toolCost    = TOOL_COST_COVER_LETTER;
$pageTitle   = 'Cover Letter AI | Jobmington';
$activeAIPage = 'cover_letter';

require_once __DIR__ . '/../includes/ai-header.php';
require_once __DIR__ . '/_tool_styles.php';
?>

<div class="jm-tool-wrap">
    <div class="jm-tool-hero">
        <p class="jm-kicker" style="font-size:12px;letter-spacing:.1em;text-transform:uppercase;font-weight:800;margin-bottom:10px;">AI-Powered</p>
        <h1>Cover Letter AI.</h1>
        <p>Paste a job description and get a tailored, human cover letter in seconds — built from your Jobmington profile.</p>
    </div>

    <div class="jm-tool-grid">
        <!-- Form -->
        <aside class="jm-tool-aside">
            <div class="jm-tool-card">
                <label class="jm-tool-label" for="cl-jd">Job description <span class="req">*</span></label>
                <textarea id="cl-jd" class="jm-tool-textarea" style="min-height:150px;" placeholder="Paste the full job description here…"></textarea>

                <div class="jm-tool-row">
                    <div>
                        <label class="jm-tool-label" for="cl-company">Company</label>
                        <input id="cl-company" class="jm-tool-input" placeholder="e.g. Flutterwave">
                    </div>
                    <div>
                        <label class="jm-tool-label" for="cl-role">Role title</label>
                        <input id="cl-role" class="jm-tool-input" placeholder="e.g. Product Manager">
                    </div>
                </div>

                <label class="jm-tool-label" for="cl-tone">Tone</label>
                <select id="cl-tone" class="jm-tool-input">
                    <option value="professional">Professional</option>
                    <option value="warm">Warm</option>
                    <option value="confident">Confident</option>
                </select>

                <label class="jm-tool-label" for="cl-notes">Anything to emphasise <span class="opt">(optional)</span></label>
                <textarea id="cl-notes" class="jm-tool-textarea" placeholder="e.g. led a team of 5, grew revenue 30%, open to relocation…"></textarea>

                <button class="jm-button jm-tool-btn" id="cl-generate" onclick="CoverLetter.run()">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 19l7-7 3 3-7 7-3-3z"/><path d="M18 13l-1.5-7.5L2 2l3.5 14.5L13 18l5-5z"/><path d="M2 2l7.586 7.586"/><circle cx="11" cy="11" r="2"/></svg>
                    Generate letter
                </button>
            </div>

            <div class="jm-tool-credit">
                <span class="jm-tool-credit-label">Access</span>
                <?php if ($isPremium): ?>
                    <span class="jm-tool-premium-pill">Premium — unlimited</span>
                <?php else: ?>
                    <span class="jm-tool-credit-val"><?= $userCredits ?> credit<?= $userCredits !== 1 ? 's' : '' ?> · <?= $toolCost ?>/letter</span>
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
                <span>Your cover letter</span>
                <button class="jm-tool-copy hidden" id="cl-copy" onclick="CoverLetter.copy()">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                    Copy
                </button>
            </div>
            <div class="jm-tool-result-body">
                <div class="jm-tool-empty" id="cl-empty">
                    <div class="jm-tool-empty-icon">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16v16H4z" opacity="0"/><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="8" y1="13" x2="16" y2="13"/><line x1="8" y1="17" x2="13" y2="17"/></svg>
                    </div>
                    <div>
                        <h3>No letter yet</h3>
                        <p>Paste a job description and click “Generate letter”. Your tailored cover letter appears here, ready to copy.</p>
                    </div>
                </div>
                <div id="cl-output" style="display:none;"></div>
            </div>
        </main>
    </div>
</div>

<script>
const JOBMINGTON_SITE_URL = <?= json_encode(SITE_URL) ?>;
const CoverLetter = {
    lastLetter: '',
    e: (v) => { const d = document.createElement('div'); d.textContent = v == null ? '' : String(v); return d.innerHTML; },

    run: async () => {
        const jd      = document.getElementById('cl-jd').value.trim();
        const company = document.getElementById('cl-company').value.trim();
        const role    = document.getElementById('cl-role').value.trim();
        if (!jd && !role) { JM.toast('Paste the job description (or at least the role title).', 'warning'); return; }

        const isPremium = <?= $isPremium ? 'true' : 'false' ?>;
        const balance   = <?= $userCredits ?>;
        const cost      = <?= $toolCost ?>;
        if (!isPremium && balance < cost) {
            JM.toast(`You need ${cost} credit but only have ${balance}.`, 'error', 'Insufficient credits');
            setTimeout(() => window.location.href = `${JOBMINGTON_SITE_URL}/payments/credits.php?tool=cover_letter&paywall=1`, 1800);
            return;
        }

        const btn = document.getElementById('cl-generate');
        const orig = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = `<svg class="jm-spin" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M21 12a9 9 0 1 1-6.22-8.56"/></svg> Writing…`;

        try {
            const res = await fetch(`${JOBMINGTON_SITE_URL}/api/cover-letter.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    job_description: jd,
                    company_name: company,
                    role_title: role,
                    tone: document.getElementById('cl-tone').value,
                    notes: document.getElementById('cl-notes').value.trim(),
                    charge: true,
                }),
            });
            const data = await res.json();
            btn.disabled = false; btn.innerHTML = orig;

            if (data.success) {
                CoverLetter.render(data.data.result);
                const msg = data.data.premium ? 'Premium — unlimited' : `−${data.data.cost} credit${data.data.cost !== 1 ? 's' : ''}`;
                JM.toast(`Letter ready. ${msg}`, 'success', 'Done');
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
        CoverLetter.lastLetter = result.letter || '';
        const highlights = Array.isArray(result.highlights) ? result.highlights : [];
        const svgCheck = `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>`;

        let html = '';
        if (result.subject) {
            html += `<div class="jm-tool-subject"><span>Subject</span><strong>${CoverLetter.e(result.subject)}</strong></div>`;
        }
        html += `<div class="jm-tool-letter">${CoverLetter.e(result.letter || '')}</div>`;
        if (highlights.length) {
            html += `<div class="jm-tool-highlights"><div class="jm-tool-highlights-label">Why this works</div>${highlights.map(h => `<div class="jm-tool-highlight">${svgCheck} ${CoverLetter.e(h)}</div>`).join('')}</div>`;
        }
        html += `<div class="jm-tool-cta">
            <a class="jm-button secondary" href="${JOBMINGTON_SITE_URL}/jobs/">Browse jobs</a>
        </div>`;

        document.getElementById('cl-empty').style.display = 'none';
        const out = document.getElementById('cl-output');
        out.style.display = 'block';
        out.innerHTML = html;
        document.getElementById('cl-copy').classList.remove('hidden');
    },

    copy: () => {
        if (!CoverLetter.lastLetter) return;
        navigator.clipboard.writeText(CoverLetter.lastLetter)
            .then(() => JM.toast('Letter copied to clipboard.', 'success'))
            .catch(() => JM.toast('Could not copy. Select and copy manually.', 'error'));
    },
};
</script>

<?php require_once __DIR__ . '/../includes/ai-footer.php'; ?>
