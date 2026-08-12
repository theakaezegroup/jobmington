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
        </div><!-- /jm-admin-main -->
    </div><!-- /jm-admin-layout -->
    <div class="jm-admin-backdrop" id="jmAdminBackdrop" aria-hidden="true"></div>
    <script>
    (function () {
        var body = document.body;
        var burger = document.getElementById('jmAdminBurger');
        var backdrop = document.getElementById('jmAdminBackdrop');
        function close() { body.classList.remove('nav-open'); }
        if (burger) burger.addEventListener('click', function () { body.classList.toggle('nav-open'); });
        if (backdrop) backdrop.addEventListener('click', close);
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });
        // Close the drawer after tapping a nav link on mobile.
        document.querySelectorAll('.jm-admin-navgroup a').forEach(function (a) {
            a.addEventListener('click', close);
        });
    })();
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            const pathBase = window.location.pathname.toLowerCase().startsWith('/jobmington/') ? '/jobmington' : '';
            navigator.serviceWorker.register(`${pathBase}/service-worker.js?v=brand-16`).catch(() => {});
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
<?php require_once __DIR__ . '/feedback.php'; ?>
<?php require_once __DIR__ . '/sticky_header.php'; ?>

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

<footer class="jm-footer" aria-label="Site footer">
    <div style="max-width:1172px;margin:0 auto;padding:0 24px;">
        <div class="jm-footer-inner">
            <div class="jm-footer-brand">
                <a class="jm-logo" href="/jobmington/">
                    <img src="/jobmington/assets/images/badge.png?v=logo-8" alt="">
                    <span>Jobmington</span>
                </a>
                <p>Simple hiring for African talent. Find jobs, apply quickly, and manage hiring without the noise.</p>
            </div>

            <nav class="jm-footer-links" aria-label="Job seeker links">
                <h2>Job Seekers</h2>
                <a href="/jobmington/jobs/">Find jobs</a>
                <a href="/jobmington/cv-builder/">CV Builder</a>
                <a href="/jobmington/jobs/search.php">Search jobs</a>
                <a href="/jobmington/jobs/?type=Remote">Remote jobs</a>
                <a href="/jobmington/auth/register.php">Create account</a>
            </nav>

            <nav class="jm-footer-links" aria-label="Employer links">
                <h2>Employers</h2>
                <a href="/jobmington/employer/">Hire talent</a>
                <a href="/jobmington/employer/post-job.php">Post a job</a>
                <a href="/jobmington/employer/dashboard.php">Employer dashboard</a>
                <a href="/jobmington/pricing.php">Pricing</a>
            </nav>

            <nav class="jm-footer-links" aria-label="Learn links">
                <h2>Learn</h2>
                <a href="/jobmington/learn/">Online courses</a>
                <a href="/jobmington/ebooks/">Free ebooks &amp; guides</a>
                <a href="/jobmington/events/">Events &amp; webinars</a>
                <a href="/jobmington/blog/">Career blog</a>
                <a href="/jobmington/community/">Community forum</a>
                <a href="/jobmington/tools/">Career tools</a>
            </nav>

            <nav class="jm-footer-links" aria-label="Company links">
                <h2>Company</h2>
                <a href="/jobmington/contact.php">Contact</a>
                <a href="/jobmington/faq.php">FAQ</a>
                <a href="/jobmington/privacy-policy.php">Privacy policy</a>
                <a href="/jobmington/terms-of-service.php">Terms of service</a>
            </nav>

            <nav class="jm-footer-links" aria-label="Popular job searches">
                <h2>Popular Searches</h2>
                <a href="/jobmington/jobs/search.php?q=developer">Developer jobs</a>
                <a href="/jobmington/jobs/search.php?q=marketing">Marketing jobs</a>
                <a href="/jobmington/jobs/search.php?q=designer">Design jobs</a>
                <a href="/jobmington/jobs/search.php?q=operations">Operations jobs</a>
            </nav>
        </div>

        <div class="jm-footer-bottom">
            <span>&copy; <?= date('Y') ?> Jobmington</span>
            <span>Lagos, Nigeria</span>
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
        navigator.serviceWorker.register(`${pathBase}/service-worker.js?v=brand-16`).catch(err => console.log('SW fail:', err));
    });
}
</script>

<script src="<?= asset('js/app.js') ?>"></script>

<!-- Global Toast & Modal System -->
<script>
window.JM = window.JM || {};

// Toasts and sound now live in includes/feedback.php, so the public site,
// the AI pages and the admin panel all get the same one.

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
