    </main>

    <?php require_once __DIR__ . '/feedback.php'; ?>
    <?php require_once __DIR__ . '/sticky_header.php'; ?>

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

        // Toast lives in includes/feedback.php now. This file used to define
        // a second one over the top of the first.

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
