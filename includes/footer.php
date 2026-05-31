<?php
/**
 * JOBMINGTON - Footer Template v5.0 (The Crystal Edition)
 * Features:
 * - Obsidian Gradient Background
 * - Glassmorphism Newsletter Card
 * - Interactive Hover Effects
 * - Focused job-board footer
 */
$footerPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
if (preg_match('~^/(?:jobmington/)?admin(?:/|$)~i', $footerPath)) {
?>
        </main>
        <footer class="jm-admin-footer">
            <span>&copy; <?= date('Y') ?> Jobmington Admin</span>
            <span><?= e(date('D, M d, Y H:i T')) ?></span>
        </footer>
    </div>
    <script>
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            const pathBase = window.location.pathname.toLowerCase().startsWith('/jobmington/') ? '/jobmington' : '';
            navigator.serviceWorker.register(`${pathBase}/service-worker.js?v=brand-10`).catch(() => {});
        });
    }
    </script>
</body>
</html>
<?php
    return;
}
?>
</main>

<!-- Global Toast Container -->
<div class="toast-container" id="jm-toast-container"></div>

<!-- Global Modal Overlay -->
<div class="jm-modal-overlay" id="jm-modal-overlay">
    <div class="jm-modal-dialog" id="jm-modal-dialog">
        <div class="jm-modal-header">
            <div class="jm-modal-icon confirm" id="jm-modal-icon">
                <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>
            </div>
            <div>
                <h3 class="jm-modal-title" id="jm-modal-title">Confirm Action</h3>
                <p class="jm-modal-message" id="jm-modal-message">Are you sure you want to proceed?</p>
            </div>
        </div>
        <div class="jm-modal-footer">
            <button class="jm-modal-btn jm-modal-btn-secondary" id="jm-modal-cancel">Cancel</button>
            <button class="jm-modal-btn jm-modal-btn-primary" id="jm-modal-confirm">Confirm</button>
        </div>
    </div>
</div>

<footer class="relative border-t mt-auto overflow-hidden" style="background: #ffffff; border-top: 1px solid #e8edf5;">
    <!-- Removed dark background orbs for Neo-Brutalist clean aesthetic -->

    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 z-10">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-12 gap-12 lg:gap-8">
            
            <div class="lg:col-span-4 space-y-6">
                <a href="/jobmington/" class="flex items-center gap-3 group w-fit">
                    <img src="/jobmington/assets/images/badge.png?v=logo-7" alt="<?= SITE_NAME ?>" class="w-10 h-10 object-contain group-hover:scale-105 transition duration-300">
                    <span class="text-2xl font-heading font-extrabold tracking-tight" style="color: var(--color-ink);"><?= SITE_NAME ?></span>
                </a>
                
                <p class="text-sm leading-relaxed max-w-sm" style="color: var(--color-ink); opacity: 0.7;">
                    <?= SITE_TAGLINE ?>. Find jobs, apply quickly, and help employers reach qualified African talent.
                </p>

                <div class="flex items-center gap-3">
                    <?php 
                    $socials = [
                        ['icon' => 'fab fa-facebook-f', 'url' => '#'],
                        ['icon' => 'fab fa-twitter', 'url' => '#'], // Or fa-x-twitter
                        ['icon' => 'fab fa-linkedin-in', 'url' => '#'],
                        ['icon' => 'fab fa-instagram', 'url' => '#']
                    ];
                    foreach($socials as $social): ?>
                        <a href="<?= $social['url'] ?>" class="w-10 h-10 rounded-full flex items-center justify-center border transition-all duration-300" style="background: #ffffff; border: 1px solid #e8edf5; color: #101828;" onmouseover="this.style.background='#effaf6'; this.style.color='#073b2f';" onmouseout="this.style.background='#ffffff'; this.style.color='#101828';">
                            <i class="<?= $social['icon'] ?>"></i>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="lg:col-span-5 grid grid-cols-2 gap-8">
                <div>
                    <h3 class="font-heading font-bold text-lg mb-5 flex items-center gap-2" style="color: var(--color-ink);">
                        Seekers <span class="w-1.5 h-1.5 rounded-full" style="background: var(--color-accent);"></span>
                    </h3>
                    <ul class="space-y-3">
                        <?php 
                        $seekerLinks = [
                            ['label' => 'Browse Jobs', 'url' => '/jobmington/jobs'],
                            ['label' => 'Search Jobs', 'url' => '/jobmington/jobs/search.php'],
                            ['label' => 'Saved Jobs', 'url' => '/jobmington/jobs/saved.php'],
                            ['label' => 'My Profile', 'url' => '/jobmington/seeker/profile.php'],
                        ];
                        foreach($seekerLinks as $link): ?>
                        <li>
                            <a href="<?= $link['url'] ?>" class="text-sm transition-all duration-200 flex items-center gap-2 group" style="color: var(--color-ink); opacity: 0.7;" onmouseover="this.style.opacity='1';" onmouseout="this.style.opacity='0.7';">
                                <i class="fas fa-chevron-right text-[8px] opacity-0 group-hover:opacity-100 -ml-2 group-hover:ml-0 transition-all"></i>
                                <?= $link['label'] ?>
                            </a>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <div>
                    <h3 class="font-heading font-bold text-lg mb-5 flex items-center gap-2" style="color: var(--color-ink);">
                        Employers <span class="w-1.5 h-1.5 rounded-full" style="background: var(--color-primary);"></span>
                    </h3>
                    <ul class="space-y-3">
                        <li><a href="/jobmington/employer/post-job.php" class="text-sm transition-all duration-200 group flex items-center gap-2" style="color: var(--color-ink); opacity: 0.7;" onmouseover="this.style.opacity='1';" onmouseout="this.style.opacity='0.7';"><i class="fas fa-chevron-right text-[8px] opacity-0 group-hover:opacity-100 -ml-2 group-hover:ml-0 transition-all"></i> Post a Job</a></li>
                        <li><a href="/jobmington/employer/dashboard.php" class="text-sm transition-all duration-200 group flex items-center gap-2" style="color: var(--color-ink); opacity: 0.7;" onmouseover="this.style.opacity='1';" onmouseout="this.style.opacity='0.7';"><i class="fas fa-chevron-right text-[8px] opacity-0 group-hover:opacity-100 -ml-2 group-hover:ml-0 transition-all"></i> Dashboard</a></li>
                        <li><a href="/jobmington/employer/manage-jobs.php" class="text-sm transition-all duration-200 group flex items-center gap-2" style="color: var(--color-ink); opacity: 0.7;" onmouseover="this.style.opacity='1';" onmouseout="this.style.opacity='0.7';"><i class="fas fa-chevron-right text-[8px] opacity-0 group-hover:opacity-100 -ml-2 group-hover:ml-0 transition-all"></i> Manage Jobs</a></li>
                        <li><a href="/jobmington/employer/applications.php" class="text-sm transition-all duration-200 group flex items-center gap-2" style="color: var(--color-ink); opacity: 0.7;" onmouseover="this.style.opacity='1';" onmouseout="this.style.opacity='0.7';"><i class="fas fa-chevron-right text-[8px] opacity-0 group-hover:opacity-100 -ml-2 group-hover:ml-0 transition-all"></i> Applications</a></li>
                    </ul>
                </div>
            </div>
            
            <div class="lg:col-span-3">
                <div class="bg-white rounded-2xl p-6 transition duration-300" style="border: 1px solid #e8edf5; box-shadow: none;">
                    <h3 class="font-heading font-bold text-lg mb-2 flex items-center gap-2" style="color: var(--color-ink);">Stay Updated <span class="w-1.5 h-1.5 rounded-full" style="background: var(--color-accent);"></span></h3>
                    <p class="text-xs mb-4" style="color: var(--color-ink); opacity: 0.7;">Get the latest jobs and career tips delivered to your inbox.</p>
                    
                    <form action="/jobmington/subscribe.php" method="POST" class="relative">
                        <input type="email" name="email" placeholder="email@address.com" required
                               class="w-full text-sm rounded-xl px-4 py-3 focus:outline-none focus:ring-2 transition" style="background: #f6f8fb; border: 1px solid #e8edf5; color: #101828;" onfocus="this.style.borderColor='#073b2f'; this.style.boxShadow='0 0 0 2px rgba(7, 59, 47, 0.08)';" onblur="this.style.borderColor='#e8edf5'; this.style.boxShadow='none';">
                        <button type="submit" class="absolute right-1 top-1 bottom-1 px-3 rounded-lg text-sm font-medium transition-all hover:scale-105 active:scale-95" style="background: #073b2f; color: #ffffff; border: 0;">
                            <i class="fas fa-paper-plane"></i>
                        </button>
                    </form>

                    <div class="mt-6 pt-6 space-y-3" style="border-top: 1px solid rgba(12, 42, 76, 0.2);">
                         <div class="flex items-center gap-3 text-sm" style="color: var(--color-ink);">
                            <i class="fas fa-envelope" style="color: var(--color-accent);"></i>
                            <a href="mailto:<?= SITE_EMAIL ?>" class="hover:opacity-80 transition" style="color: var(--color-ink);"><?= SITE_EMAIL ?></a>
                        </div>
                         <div class="flex items-center gap-3 text-sm" style="color: var(--color-ink);">
                            <i class="fas fa-map-marker-alt" style="color: var(--color-ink); opacity: 0.6;"></i>
                            <span>Lagos, Nigeria</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="relative z-10" style="border-top: 1px solid #e8edf5; background: #f6f8fb;">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
            <div class="flex flex-col md:flex-row justify-between items-center gap-4">
                <p class="text-xs text-center md:text-left" style="color: var(--color-ink); opacity: 0.7;">
                    &copy; <?= date('Y') ?> <?= SITE_NAME ?>. Crafted with <i class="fas fa-heart mx-0.5" style="color: var(--color-accent);"></i> by <a href="https://truthsprout.cc" target="blank" class="hover:opacity-100 transition" style="color: var(--color-ink);">Truthsprout</a>.
                </p>
                <div class="flex flex-wrap justify-center gap-6 text-xs font-medium" style="color: var(--color-ink); opacity: 0.7;">
                    <a href="/jobmington/privacy-policy.php" class="hover:opacity-100 transition">Privacy</a>
                    <a href="/jobmington/terms-of-service.php" class="hover:opacity-100 transition">Terms</a>
                    <a href="/jobmington/faq.php" class="hover:opacity-100 transition">FAQ</a>
                </div>
            </div>
        </div>
    </div>
</footer>

<script>
// --- Mobile Menu Logic (Matched to Header IDs) ---
document.addEventListener('DOMContentLoaded', () => {
    const mobileToggle = document.getElementById('mobile-toggle');
    const mobileClose = document.getElementById('mobile-close');
    const mobileBackdrop = document.getElementById('mobile-backdrop');
    const body = document.body;

    function toggleMenu(show) {
        if (show) {
            body.classList.add('menu-open');
        } else {
            body.classList.remove('menu-open');
        }
    }

    if(mobileToggle) mobileToggle.addEventListener('click', (e) => {
        e.preventDefault();
        toggleMenu(true);
    });

    if(mobileClose) mobileClose.addEventListener('click', (e) => {
        e.preventDefault();
        toggleMenu(false);
    });

    if(mobileBackdrop) mobileBackdrop.addEventListener('click', (e) => {
        e.preventDefault();
        toggleMenu(false);
    });

    // Close on Escape key
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && body.classList.contains('menu-open')) {
            toggleMenu(false);
        }
    });
});

// --- Auto-hide Flash Messages ---
setTimeout(() => {
    const flashMessages = document.querySelectorAll('.fade-in');
    flashMessages.forEach(el => {
        if (el.closest('.fixed')) {
            el.style.transition = 'opacity 0.5s ease';
            el.style.opacity = '0';
            setTimeout(() => el.remove(), 500);
        }
    });
}, 5000);

// --- Smooth Scroll ---
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
            e.preventDefault();
            target.scrollIntoView({ behavior: 'smooth' });
        }
    });
});
</script>

<script>
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        const pathBase = window.location.pathname.toLowerCase().startsWith('/jobmington/') ? '/jobmington' : '';
        navigator.serviceWorker.register(`${pathBase}/service-worker.js?v=brand-10`).catch(err => console.log('SW fail:', err));
    });
}
</script>

<script src="<?= asset('js/app.js') ?>"></script>

<!-- Global Toast & Modal System -->
<script>
window.JM = window.JM || {};

// Toast Notifications
JM.toast = function(message, type = 'info', title = null) {
    const container = document.getElementById('jm-toast-container');
    if (!container) return;
    
    const icons = {
        success: '<svg viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>',
        error: '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>',
        warning: '<svg viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
        info: '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>'
    };
    const titles = { success: 'Success', error: 'Error', warning: 'Warning', info: 'Info' };
    
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.innerHTML = `
        <div class="toast-icon">${icons[type]}</div>
        <div class="toast-content">
            <div class="toast-title">${title || titles[type]}</div>
            <div class="toast-message">${message}</div>
        </div>
        <button class="toast-close" onclick="this.parentElement.classList.add('hiding'); setTimeout(() => this.parentElement.remove(), 250);">
            <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
        <div class="toast-progress" style="animation-duration: 4s;"></div>
    `;
    
    container.appendChild(toast);
    
    setTimeout(() => {
        toast.classList.add('hiding');
        setTimeout(() => toast.remove(), 250);
    }, 4000);
};

// Confirm Dialog
JM.confirm = function(options) {
    const overlay = document.getElementById('jm-modal-overlay');
    const iconEl = document.getElementById('jm-modal-icon');
    const titleEl = document.getElementById('jm-modal-title');
    const messageEl = document.getElementById('jm-modal-message');
    const confirmBtn = document.getElementById('jm-modal-confirm');
    const cancelBtn = document.getElementById('jm-modal-cancel');
    
    if (!overlay) return;
    
    const icons = {
        confirm: '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>',
        warning: '<svg viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
        danger: '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>',
        success: '<svg viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>'
    };
    
    iconEl.className = `jm-modal-icon ${options.icon || 'confirm'}`;
    iconEl.innerHTML = icons[options.icon || 'confirm'];
    titleEl.textContent = options.title || 'Confirm';
    messageEl.innerHTML = options.message || 'Are you sure?';
    confirmBtn.textContent = options.confirmText || 'Confirm';
    confirmBtn.className = `jm-modal-btn ${options.danger ? 'jm-modal-btn-danger' : 'jm-modal-btn-primary'}`;
    
    overlay.classList.add('active');
    
    const cleanup = () => {
        overlay.classList.remove('active');
        confirmBtn.onclick = null;
        cancelBtn.onclick = null;
    };
    
    confirmBtn.onclick = () => { cleanup(); if (options.onConfirm) options.onConfirm(); };
    cancelBtn.onclick = () => { cleanup(); if (options.onCancel) options.onCancel(); };
    overlay.onclick = (e) => { if (e.target === overlay) { cleanup(); if (options.onCancel) options.onCancel(); } };
};

// Simple alert (just a toast)
JM.alert = function(message, type = 'info', title = null) {
    JM.toast(message, type, title);
};
</script>

</body>
</html>
