    </main>

    <div class="jm-ai-toast-stack" id="jm-toast-container" aria-live="polite"></div>

    <script>
    (() => {
        const body = document.body;
        const header = document.querySelector('.jm-header');
        const nav = header?.querySelector('.jm-nav');
        const toggle = header?.querySelector('.jm-mobile-nav-toggle');

        if (nav && toggle) {
            const backdrop = document.createElement('div');
            backdrop.className = 'jm-nav-backdrop';
            backdrop.setAttribute('aria-hidden', 'true');
            body.appendChild(backdrop);
            body.classList.add('jm-mobile-nav-ready');

            const setOpen = (open) => {
                body.classList.toggle('jm-nav-open', open);
                toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
                toggle.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
            };

            toggle.addEventListener('click', () => setOpen(!body.classList.contains('jm-nav-open')));
            backdrop.addEventListener('click', () => setOpen(false));
            nav.querySelectorAll('a').forEach((link) => link.addEventListener('click', () => setOpen(false)));
            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') setOpen(false);
            });
        }

        window.JM = window.JM || {};
        window.JM.toast = (message, type = 'info', title = '') => {
            const stack = document.getElementById('jm-toast-container');
            if (!stack) return;

            const toast = document.createElement('div');
            toast.className = `jm-ai-toast ${type}`;
            toast.innerHTML = `${title ? `<strong>${title}</strong>` : ''}<span>${message}</span>`;
            stack.appendChild(toast);
            window.setTimeout(() => {
                toast.style.opacity = '0';
                toast.style.transform = 'translateY(-8px)';
                window.setTimeout(() => toast.remove(), 220);
            }, 4200);
        };

        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                const pathBase = window.location.pathname.toLowerCase().startsWith('/jobmington/') ? '/jobmington' : '';
                navigator.serviceWorker.register(`${pathBase}/service-worker.js?v=brand-16`).catch(() => {});
            });
        }
    })();
    </script>
</body>
</html>
