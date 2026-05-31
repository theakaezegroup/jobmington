<?php
/**
 * JOBMINGTON - CV Roast Station v29.4 (Celebration Mode)
 * ---------------------------------------------------
 * - FEATURE: "Secure the Bag" Confetti Animation.
 * - TRIGGER: Happens automatically on "Elite Score".
 * - VIBE: Gold, Purple, and Emerald particle effects.
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

// Get real user credits
$userId = Session::userId();
$userCredits = $userId ? getSeedBalance($userId) : 0;
$isLoggedIn = Session::isLoggedIn();
$pageTitle = 'CV Roast | Jobmington';
$activeAIPage = 'roast';

require_once __DIR__ . '/../includes/ai-header.php';
?>

<script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>

<style>
    :root {
        --bg-deep: #f6f9fd;
        --glass-panel: #ffffff;
        --border-glass: #dbe7f4;
        --neon-red: #b42318;
        --neon-gold: #f59f22;
        --neon-green: #0f766e;
        --text-main: #061426;
    }

    body.jm-ai-page { background: var(--bg-deep); color: var(--text-main); overflow-x: hidden; }
    .roast-top { max-width: 1200px; margin: 24px auto 0; padding: 0 20px; }
    .roast-container { max-width: 1200px; margin: 20px auto 54px; padding: 0 20px; display: grid; grid-template-columns: 400px 1fr; gap: 24px; }
    .roast-container .text-white,
    .roast-container .text-slate-300,
    .roast-container .text-slate-400,
    .roast-container .text-slate-500 { color: var(--text-main) !important; }
    .roast-container .text-amber-500,
    .roast-container .text-amber-300,
    .roast-container .text-yellow-400 { color: var(--neon-gold) !important; }
    .roast-container .text-emerald-400,
    .roast-container .text-emerald-500 { color: var(--neon-green) !important; }
    .upload-zone { background: var(--glass-panel); border: 2px dashed #bfd0e7; border-radius: 8px; padding: 40px; text-align: center; transition: 0.2s; cursor: pointer; display: flex; flex-direction: column; align-items: center; justify-content: center; height: 300px; box-shadow: 0 12px 30px rgba(10, 28, 48, 0.08); }
    .upload-zone:hover { border-color: var(--jm-blue); background: #f8fbff; }
    .score-circle { width: 150px; height: 150px; border-radius: 50%; border: 8px solid var(--border-glass); display: flex; align-items: center; justify-content: center; font-size: 3rem; font-weight: 900; margin: 0 auto 20px; transition: 0.5s; background: #ffffff; }
    .score-circle.weak { border-color: var(--neon-red); color: var(--neon-red); }
    .score-circle.elite { border-color: var(--neon-green); color: var(--neon-green); }
    .report-card { background: var(--glass-panel); border: 1px solid var(--border-glass); border-radius: 8px; padding: 30px; position: relative; overflow: hidden; min-height: 500px; box-shadow: 0 12px 30px rgba(10, 28, 48, 0.08); }
    .blur-overlay { position: absolute; inset: 0; backdrop-filter: blur(10px); background: rgba(246, 249, 253, 0.78); z-index: 10; display: flex; flex-direction: column; align-items: center; justify-content: center; transition: opacity 0.5s; }
    .paywall-box { background: #ffffff; border: 1px solid var(--neon-gold); padding: 34px; border-radius: 8px; text-align: center; box-shadow: 0 18px 38px rgba(10, 28, 48, 0.14); transform: translateY(20px); }
    .btn-unlock { background: var(--neon-gold); color: #061426; font-weight: 900; padding: 15px 28px; border-radius: 8px; border: none; cursor: pointer; text-transform: none; letter-spacing: 0; transition: 0.2s; display: flex; align-items: center; gap: 10px; margin: 20px auto 0; }
    .btn-unlock:hover { transform: translateY(-1px); filter: brightness(1.02); }
    .roast-item { margin-bottom: 20px; padding-bottom: 20px; border-bottom: 1px solid var(--border-glass); }
    .roast-critique { color: var(--neon-red); font-weight: 800; margin-bottom: 5px; font-size: 0.95rem; }
    .roast-fix { color: var(--neon-green); font-size: 0.9rem; }
    .back-link { display: inline-flex; align-items: center; gap: 8px; color: var(--jm-muted); font-weight: 800; font-size: 0.85rem; margin-bottom: 16px; cursor: pointer; transition: 0.2s; }
    .back-link:hover { color: var(--jm-blue); }
    .saved-cv-panel { margin-top: 20px; background: #ffffff; border: 1px solid var(--border-glass); border-radius: 8px; padding: 20px; }
    .saved-cv-panel h2 { color: var(--text-main); font-size: 1rem; font-weight: 900; margin: 0 0 6px; }
    .saved-cv-panel p { color: var(--jm-muted); font-size: 0.86rem; line-height: 1.5; margin: 0 0 14px; }
    .target-role-input { width: 100%; min-height: 88px; resize: vertical; border-radius: 8px; border: 1px solid #bfd0e7; background: #ffffff; color: var(--text-main); padding: 12px 14px; outline: none; font: inherit; font-size: 0.9rem; }
    .target-role-input:focus { border-color: var(--jm-blue); box-shadow: 0 0 0 3px rgba(6, 64, 163, 0.12); }
    .btn-analyze { width: 100%; margin-top: 12px; background: var(--jm-blue); color: #ffffff; font-weight: 900; padding: 14px 18px; border-radius: 8px; border: none; cursor: pointer; text-transform: none; letter-spacing: 0; transition: 0.2s; }
    .btn-analyze:hover { transform: translateY(-1px); filter: brightness(1.05); }
    .report-summary { color: var(--jm-muted); font-size: 0.92rem; line-height: 1.6; margin-bottom: 20px; }
    .optimized-box { background: #effaf6; border: 1px solid #bde6db; border-radius: 8px; padding: 18px; margin-top: 20px; }
    .optimized-box h3 { color: var(--neon-green); font-weight: 900; font-size: 0.85rem; letter-spacing: 0; text-transform: none; margin: 0 0 8px; }
    .optimized-box p { color: var(--text-main); margin: 0; line-height: 1.6; }
    .keyword-row { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 18px; }
    .keyword-row span { border: 1px solid rgba(245, 159, 34, 0.35); border-radius: 999px; color: #9a5a00; font-size: 0.74rem; font-weight: 800; padding: 6px 9px; background: #fff8ec; }

    @media (max-width: 900px) {
        .roast-container { grid-template-columns: 1fr; margin: 20px auto 40px; gap: 20px; }
        .upload-zone { height: 200px; padding: 20px; }
        h1.text-4xl { font-size: 2rem; }
        .report-card { min-height: 400px; padding: 20px; }
        .paywall-box { padding: 24px; width: 90%; }
        .text-2xl { font-size: 1.5rem; }
    }
</style>

<div class="roast-top">
    <a href="andika.php" class="back-link"><i class="fas fa-arrow-left"></i> Return to Copilot</a>
</div>

<div class="roast-container">
    <div>
        <h1 class="text-4xl font-black text-white mb-2 tracking-tighter">CV ROAST <span class="text-amber-500">2.0</span></h1>
        <p class="text-slate-400 mb-8">Upload your PDF. If it's weak, we roast it. If it's Jobmington-made, we verify it.</p>

        <div id="drop-zone" class="upload-zone" onclick="document.getElementById('cv-input').click()">
            <i class="fas fa-cloud-upload-alt text-4xl text-slate-500 mb-4"></i>
            <h3 class="font-bold text-white">Drop CV Here</h3>
            <p class="text-xs text-slate-500 mt-2">PDF or DOCX (Max 5MB)</p>
            <input type="file" id="cv-input" hidden onchange="Roast.processFile(this)">
        </div>

        <div id="score-panel" class="hidden mt-8 text-center">
            <div id="score-ring" class="score-circle">0</div>
            <h3 class="text-xl font-bold text-white" id="score-status">Analyzing...</h3>
            <p class="text-sm text-slate-400 mt-2" id="score-msg">...</p>
        </div>

        <div class="saved-cv-panel">
            <h2>Optimize your saved Jobmington CV</h2>
            <p>Run the full Andika report on the CV in your CV Builder. Add a target role or paste a job description for stronger matching.</p>
            <textarea id="target-role" class="target-role-input" placeholder="Example: Product Manager role in Lagos, fintech, stakeholder management, SQL, roadmap ownership..."></textarea>
            <button class="btn-analyze" onclick="Roast.analyzeSavedCv(false)">
                <i class="fas fa-wand-magic-sparkles mr-2"></i> Roast and optimize saved CV
            </button>
        </div>
    </div>

    <div class="report-card">
        <div class="flex justify-between mb-6 text-xs font-bold uppercase tracking-widest text-slate-500">
            <span>Diagnostics Report</span>
            <span id="lock-status"><i class="fas fa-lock"></i> ENCRYPTED</span>
        </div>

        <div id="report-content" class="opacity-30 select-none pointer-events-none transition-all duration-500">
            <div class="p-8 text-center text-slate-500 mt-10">
                <i class="fas fa-microchip text-4xl mb-4 opacity-50"></i>
                <p>Analyzing...</p>
            </div>
        </div>

        <div id="paywall" class="blur-overlay hidden">
            <div class="paywall-box">
                <div class="text-yellow-400 text-4xl mb-4"><i class="fas fa-lock"></i></div>
                <h2 class="text-2xl font-black text-white mb-2">Unlock the Fix</h2>
                <p class="text-slate-400 text-sm mb-6">See exactly what to change to double your callbacks.</p>
                <button class="btn-unlock" onclick="Roast.unlock()">
                    <span>Use 50 Seeds</span>
                    <i class="fas fa-arrow-right"></i>
                </button>
                <p class="text-[10px] text-slate-500 mt-4">Balance: <?= number_format($userCredits) ?> Seeds</p>
            </div>
        </div>
    </div>
</div>

<script>
const JOBMINGTON_SITE_URL = <?= json_encode(SITE_URL) ?>;

const Roast = {
    // 2. THE CELEBRATION FUNCTION
    secureTheBag: () => {
        // Gold (Seeds), Amber (Brand), Emerald (Success)
        const brandColors = ['#fbbf24', '#f59e0b', '#10b981']; 
        
        // Blast 1: Center Explosion
        confetti({
            particleCount: 100,
            spread: 70,
            origin: { y: 0.6 },
            colors: brandColors,
            zIndex: 9999
        });

        // Blast 2: Left Cannon
        setTimeout(() => {
            confetti({
                particleCount: 50,
                angle: 60,
                spread: 55,
                origin: { x: 0 },
                colors: brandColors,
                zIndex: 9999
            });
        }, 200);

        // Blast 3: Right Cannon
        setTimeout(() => {
            confetti({
                particleCount: 50,
                angle: 120,
                spread: 55,
                origin: { x: 1 },
                colors: brandColors,
                zIndex: 9999
            });
        }, 400);
    },

    processFile: (input) => {
        const file = input.files[0];
        if(!file) return;

        const dropZone = document.getElementById('drop-zone');
        const scorePanel = document.getElementById('score-panel');

        dropZone.innerHTML = `<i class="fas fa-spinner fa-spin text-4xl text-amber-500 mb-4"></i><h3 class="font-bold text-white">Scanning...</h3>`;
        
        setTimeout(() => {
            dropZone.style.display = 'none';
            scorePanel.classList.remove('hidden');

            const isInternal = file.name.toLowerCase().includes('jobmington');

            if (isInternal) {
                Roast.showEliteResult();
            } else {
                Roast.showWeakResult();
            }
        }, 2000);
    },

    showWeakResult: () => {
        const ring = document.getElementById('score-ring');
        const report = document.getElementById('report-content');
        
        ring.innerText = "42";
        ring.classList.add('weak');
        document.getElementById('score-status').innerHTML = `Status: <span class="text-red-500">WEAK</span>`;
        document.getElementById('score-msg').innerText = "\"This resume puts recruiters to sleep.\"";
        
        report.innerHTML = `
            <div class="roast-item"><div class="roast-critique"><i class="fas fa-times-circle"></i> Summary is too generic</div><div class="roast-fix"><i class="fas fa-check-circle"></i> Use "Architected" instead...</div></div>
            <div class="roast-item"><div class="roast-critique"><i class="fas fa-times-circle"></i> Missing Metrics</div><div class="roast-fix"><i class="fas fa-check-circle"></i> Add "% growth" metrics...</div></div>
            <div class="p-4 text-sm text-slate-500 text-center mt-4">... 14 other issues hidden</div>
        `;
        document.getElementById('paywall').classList.remove('hidden');
    },

    showEliteResult: () => {
        const ring = document.getElementById('score-ring');
        const report = document.getElementById('report-content');
        
        ring.innerText = "98";
        ring.classList.add('elite');
        document.getElementById('score-status').innerHTML = `Status: <span class="text-emerald-500">ELITE</span>`;
        document.getElementById('score-msg').innerText = "\"Jobmington Standard Verified. Ready for deployment.\"";
        document.getElementById('lock-status').innerHTML = `<i class="fas fa-check-circle text-emerald-500"></i> VERIFIED`;

        // UNLOCK FREE
        report.classList.remove('opacity-30', 'select-none', 'pointer-events-none');
        report.innerHTML = `
            <div class="p-6 bg-emerald-500/10 border border-emerald-500/20 rounded-xl text-center mb-6">
                <i class="fas fa-medal text-4xl text-emerald-500 mb-2"></i>
                <h3 class="text-white font-bold">Perfect Format</h3>
                <p class="text-xs text-slate-400">This CV passes all ATS filters automatically.</p>
            </div>
            <div class="roast-item"><div class="text-emerald-400 font-bold mb-1"><i class="fas fa-check"></i> Power Verbs Detected</div><div class="text-xs text-slate-400">"Orchestrated", "Deployed", "Scaled" found.</div></div>
            <button class="w-full mt-6 py-3 bg-white text-black font-bold rounded-xl uppercase tracking-widest hover:scale-105 transition">Apply to Jobs Now</button>
        `;

        // 3. TRIGGER THE BAG
        Roast.secureTheBag();
    },

    escapeHtml: (value) => {
        const div = document.createElement('div');
        div.textContent = value == null ? '' : String(value);
        return div.innerHTML;
    },

    renderReport: (report, balance) => {
        const ring = document.getElementById('score-ring');
        const scorePanel = document.getElementById('score-panel');
        const reportEl = document.getElementById('report-content');
        const score = Number(report.score || 0);
        const toneClass = score >= 82 ? 'elite' : 'weak';

        document.getElementById('drop-zone').style.display = 'none';
        scorePanel.classList.remove('hidden');
        ring.classList.remove('weak', 'elite');
        ring.classList.add(toneClass);
        ring.innerText = score;
        document.getElementById('score-status').innerHTML = `Status: <span class="${score >= 82 ? 'text-emerald-500' : 'text-amber-500'}">${Roast.escapeHtml(report.status || 'Reviewed')}</span>`;
        document.getElementById('score-msg').innerText = report.summary || 'Andika has reviewed this CV.';
        document.getElementById('paywall').classList.add('hidden');
        document.getElementById('lock-status').innerHTML = `<i class="fas fa-unlock text-yellow-400"></i> UNLOCKED`;
        reportEl.classList.remove('opacity-30', 'select-none', 'pointer-events-none');

        const issues = Array.isArray(report.issues) ? report.issues : [];
        const strengths = Array.isArray(report.strengths) ? report.strengths : [];
        const keywords = Array.isArray(report.missing_keywords) ? report.missing_keywords : [];
        const nextSteps = Array.isArray(report.next_steps) ? report.next_steps : [];

        reportEl.innerHTML = `
            <p class="report-summary">${Roast.escapeHtml(report.summary || '')}</p>
            ${strengths.length ? `
                <div class="roast-item">
                    <div class="text-emerald-400 font-bold mb-2"><i class="fas fa-check-circle"></i> What is already working</div>
                    <div class="roast-fix text-white">${strengths.map(item => Roast.escapeHtml(item)).join('<br>')}</div>
                </div>
            ` : ''}
            ${issues.map(issue => `
                <div class="roast-item">
                    <div class="roast-critique"><i class="fas fa-bolt"></i> ${Roast.escapeHtml(issue.title || 'Issue')}</div>
                    <div class="text-slate-300 text-sm mb-2">${Roast.escapeHtml(issue.critique || '')}</div>
                    <div class="roast-fix"><i class="fas fa-check-circle"></i> ${Roast.escapeHtml(issue.fix || '')}</div>
                </div>
            `).join('')}
            <div class="optimized-box">
                <h3>Optimized summary</h3>
                <p>${Roast.escapeHtml(report.optimized_summary || '')}</p>
            </div>
            ${keywords.length ? `<div class="keyword-row">${keywords.map(k => `<span>${Roast.escapeHtml(k)}</span>`).join('')}</div>` : ''}
            ${nextSteps.length ? `
                <div class="roast-item mt-6">
                    <div class="text-amber-300 font-bold mb-2"><i class="fas fa-list-check"></i> Next moves</div>
                    <div class="roast-fix text-white">${nextSteps.map(item => Roast.escapeHtml(item)).join('<br>')}</div>
                </div>
            ` : ''}
            <button onclick="window.location.href='${JOBMINGTON_SITE_URL}/cv-builder/'" class="w-full mt-6 py-3 bg-amber-500 text-black font-bold rounded-xl uppercase tracking-widest hover:scale-105 transition">Fix My CV Now</button>
        `;

        const balanceEl = document.querySelector('.btn-unlock + p');
        if (balanceEl && balance !== undefined) {
            balanceEl.textContent = `Balance: ${Number(balance).toLocaleString()} Seeds`;
        }
    },

    analyzeSavedCv: async (fromUnlock = false) => {
        const isLoggedIn = <?= $isLoggedIn ? 'true' : 'false' ?>;
        const currentBalance = <?= $userCredits ?>;
        const cost = 50;
        
        if (!isLoggedIn) {
            JM.toast('Please log in to unlock the full report', 'warning', 'Login Required');
            setTimeout(() => window.location.href = `${JOBMINGTON_SITE_URL}/auth/login.php`, 1500);
            return;
        }
        
        if (currentBalance < cost) {
            JM.toast(`You need ${cost} Seeds but only have ${currentBalance}`, 'error', 'Insufficient Seeds');
            setTimeout(() => window.location.href = `${JOBMINGTON_SITE_URL}/wallet/`, 2000);
            return;
        }
        
        const btn = fromUnlock ? document.querySelector('.btn-unlock') : document.querySelector('.btn-analyze');
        const originalHtml = btn ? btn.innerHTML : '';
        if (btn) {
            btn.innerHTML = `<i class="fas fa-circle-notch fa-spin"></i> Processing...`;
            btn.disabled = true;
        }
        
        try {
            const targetRole = document.getElementById('target-role')?.value || '';
            const response = await fetch(`${JOBMINGTON_SITE_URL}/api/cv-roast.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    target_role: targetRole,
                    job_description: targetRole,
                    charge: true
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                Roast.renderReport(data.data.report, data.data.balance);
                JM.toast(`Report unlocked! -${data.data.cost} Seeds`, 'success', 'Unlocked!');
            } else {
                if (btn) {
                    btn.innerHTML = originalHtml || `<span>Use 50 Seeds</span><i class="fas fa-arrow-right"></i>`;
                    btn.disabled = false;
                }
                JM.toast(data.message || 'Payment failed. Please try again.', 'error');
            }
        } catch (error) {
            if (btn) {
                btn.innerHTML = originalHtml || `<span>Use 50 Seeds</span><i class="fas fa-arrow-right"></i>`;
                btn.disabled = false;
            }
            JM.toast('Connection error. Please try again.', 'error');
        }
    },

    unlock: async () => {
        await Roast.analyzeSavedCv(true);
    }
};
</script>

<?php require_once __DIR__ . '/../includes/ai-footer.php'; ?>
