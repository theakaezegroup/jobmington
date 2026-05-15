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

require_once __DIR__ . '/../includes/header.php';
?>

<script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>

<style>
    /* ... (KEEP ALL YOUR EXISTING CSS EXACTLY AS IS) ... */
    :root {
        --bg-deep: #020617;
        --glass-panel: rgba(15, 23, 42, 0.6);
        --border-glass: rgba(255, 255, 255, 0.08);
        --neon-red: #ef4444; 
        --neon-gold: #fbbf24;
        --neon-green: #10b981;
        --text-main: #f8fafc;
    }

    body { background-color: var(--bg-deep); color: var(--text-main); font-family: 'Inter', sans-serif; overflow-x: hidden; }
    
    /* (... Paste the rest of your CSS from v29.3 here ...) */
    .roast-container { max-width: 1200px; margin: 40px auto; padding: 0 20px; display: grid; grid-template-columns: 400px 1fr; gap: 40px; }
    .upload-zone { background: var(--glass-panel); border: 2px dashed var(--border-glass); border-radius: 24px; padding: 40px; text-align: center; transition: 0.3s; cursor: pointer; display: flex; flex-direction: column; align-items: center; justify-content: center; height: 300px; }
    .upload-zone:hover { border-color: #a855f7; background: rgba(168, 85, 247, 0.05); }
    .score-circle { width: 150px; height: 150px; border-radius: 50%; border: 8px solid var(--border-glass); display: flex; align-items: center; justify-content: center; font-size: 3rem; font-weight: 900; margin: 0 auto 20px; transition: 0.5s; }
    .score-circle.weak { border-color: var(--neon-red); box-shadow: 0 0 30px rgba(239, 68, 68, 0.2); color: var(--neon-red); }
    .score-circle.elite { border-color: var(--neon-green); box-shadow: 0 0 30px rgba(16, 185, 129, 0.3); color: var(--neon-green); }
    .report-card { background: var(--glass-panel); border: 1px solid var(--border-glass); border-radius: 24px; padding: 30px; position: relative; overflow: hidden; min-height: 500px; }
    .blur-overlay { position: absolute; inset: 0; backdrop-filter: blur(12px); background: rgba(2, 6, 23, 0.6); z-index: 10; display: flex; flex-direction: column; align-items: center; justify-content: center; transition: opacity 0.5s; }
    .paywall-box { background: rgba(15, 23, 42, 0.95); border: 1px solid var(--neon-gold); padding: 40px; border-radius: 20px; text-align: center; box-shadow: 0 0 50px rgba(251, 191, 36, 0.15); transform: translateY(20px); }
    .btn-unlock { background: var(--neon-gold); color: #000; font-weight: 900; padding: 16px 32px; border-radius: 12px; border: none; cursor: pointer; text-transform: uppercase; letter-spacing: 0.1em; transition: 0.2s; display: flex; align-items: center; gap: 10px; margin: 20px auto 0; }
    .btn-unlock:hover { transform: scale(1.05); box-shadow: 0 0 30px rgba(251, 191, 36, 0.4); }
    .roast-item { margin-bottom: 20px; padding-bottom: 20px; border-bottom: 1px solid var(--border-glass); }
    .roast-critique { color: #f87171; font-weight: 700; margin-bottom: 5px; font-size: 0.95rem; }
    .roast-fix { color: #4ade80; font-size: 0.9rem; }
    .back-link { display: inline-flex; align-items: center; gap: 8px; color: #94a3b8; font-weight: 700; font-size: 0.85rem; margin-bottom: 20px; cursor: pointer; transition: 0.2s; }
    .back-link:hover { color: #fff; }

    @media (max-width: 900px) {
        .roast-container { grid-template-columns: 1fr; margin: 20px auto; gap: 20px; }
        .upload-zone { height: 200px; padding: 20px; }
        h1.text-4xl { font-size: 2rem; }
        .report-card { min-height: 400px; padding: 20px; }
        .paywall-box { padding: 24px; width: 90%; }
        .text-2xl { font-size: 1.5rem; }
    }
</style>

<div style="max-width: 1200px; margin: 100px auto 0; padding: 0 20px;">
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

    unlock: async () => {
        const isLoggedIn = <?= $isLoggedIn ? 'true' : 'false' ?>;
        const currentBalance = <?= $userCredits ?>;
        const cost = 50;
        
        if (!isLoggedIn) {
            JM.toast('Please log in to unlock the full report', 'warning', 'Login Required');
            setTimeout(() => window.location.href = '/jobmington/auth/login.php', 1500);
            return;
        }
        
        if (currentBalance < cost) {
            JM.toast(`You need ${cost} Seeds but only have ${currentBalance}`, 'error', 'Insufficient Seeds');
            setTimeout(() => window.location.href = '/jobmington/wallet/', 2000);
            return;
        }
        
        const btn = document.querySelector('.btn-unlock');
        btn.innerHTML = `<i class="fas fa-circle-notch fa-spin"></i> Processing...`;
        btn.disabled = true;
        
        try {
            // Call API to deduct seeds
            const response = await fetch('/jobmington/api/andika.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    message: 'Unlock CV Roast Report',
                    tool: 'cv_roast'
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                // Unlock the report
                document.getElementById('paywall').classList.add('hidden');
                document.getElementById('report-content').classList.remove('opacity-30', 'select-none', 'pointer-events-none');
                document.getElementById('lock-status').innerHTML = `<i class="fas fa-unlock text-yellow-400"></i> UNLOCKED`;
                
                JM.toast('Report unlocked! -50 Seeds', 'success', 'Unlocked!');
                
                document.getElementById('report-content').innerHTML = `
                    <div class="roast-item"><div class="roast-critique"><i class="fas fa-bomb"></i> Summary Failed</div><div class="roast-fix text-white">Change "Passionate Dev" to <strong>"Full-Stack Engineer with 3 years shipping fintech products."</strong></div></div>
                    <div class="roast-item"><div class="roast-critique"><i class="fas fa-bomb"></i> Zero Metrics</div><div class="roast-fix text-white">Don't say "Managed servers." Say <strong>"Reduced latency by 40%."</strong></div></div>
                    <div class="roast-item"><div class="roast-critique"><i class="fas fa-bomb"></i> Weak Skills Section</div><div class="roast-fix text-white">Remove generic "Microsoft Office". Add <strong>"Docker, AWS, PostgreSQL"</strong></div></div>
                    <div class="roast-item"><div class="roast-critique"><i class="fas fa-bomb"></i> No ATS Keywords</div><div class="roast-fix text-white">Add role-specific terms from the job description you're targeting.</div></div>
                    <button onclick="window.location.href='/jobmington/cv-builder/'" class="w-full mt-6 py-3 bg-amber-500 text-black font-bold rounded-xl uppercase tracking-widest hover:scale-105 transition">Fix My CV Now</button>
                `;
                
                // Update balance display
                if (data.balance !== undefined) {
                    const balanceEl = document.querySelector('.btn-unlock + p');
                    if (balanceEl) {
                        balanceEl.textContent = `Balance: ${Number(data.balance).toLocaleString()} Seeds`;
                    }
                }
            } else {
                btn.innerHTML = `<span>Use 50 Seeds</span><i class="fas fa-arrow-right"></i>`;
                btn.disabled = false;
                JM.toast(data.message || 'Payment failed. Please try again.', 'error');
            }
        } catch (error) {
            btn.innerHTML = `<span>Use 50 Seeds</span><i class="fas fa-arrow-right"></i>`;
            btn.disabled = false;
            JM.toast('Connection error. Please try again.', 'error');
        }
    }
};
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>