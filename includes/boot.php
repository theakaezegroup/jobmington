<?php
/**
 * The boot screen: what an installed app shows between the tap and the paint.
 *
 * It lives in one file because 43 pages carry their own <body>, and the one
 * that matters most is index.php. That is the manifest's start_url, so it is
 * what an installed app actually opens, and it does not use header.php. A copy
 * in each place would drift apart the way the tool catalogue drifted from the
 * paywall.
 *
 * Include it immediately after the opening <body> tag.
 */

if (!defined('JOBMINGTON')) {
    die('Direct access not permitted');
}

// Two includes on one page would paint two curtains and leave one behind.
if (defined('JM_BOOT_RENDERED')) {
    return;
}
define('JM_BOOT_RENDERED', true);
?>
<style>
    /*
     * BOOT SCREEN
     *
     * What an installed app shows in the gap between tapping the icon and
     * the first paint. The field is the same blue the manifest paints for
     * the native splash, so one hands over to the other with no flash of a
     * different colour in between, and the mark carries that blue baked in,
     * which is why it sits on the field with no edge showing.
     *
     * The rest is restraint. One mark, one hairline, no wordmark, no
     * percentage. We do not know a percentage, and a number invented to be
     * watched only makes people wait for work that has already finished.
     */
    #jm-boot {
        position: fixed;
        inset: 0;
        z-index: 99999;
        background: #0640a3;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 26px;
        /* The failsafe. If the script never runs, the screen still leaves
           on its own, so a broken load can never strand anyone on a blue
           rectangle with no way forward. */
        animation: jm-boot-out 0.45s ease 5s forwards;
    }

    /* Higher specificity than the rule above, so dismissing wins over the
       failsafe rather than racing it. */
    #jm-boot.is-done {
        animation: jm-boot-out 0.45s ease forwards;
    }

    @keyframes jm-boot-out {
        to { opacity: 0; visibility: hidden; }
    }

    .jm-boot-mark {
        width: 84px;
        height: 84px;
        object-fit: contain;
        animation: jm-boot-in 0.6s cubic-bezier(0.16, 1, 0.3, 1) both;
    }

    @keyframes jm-boot-in {
        from { opacity: 0; transform: scale(0.94); }
        to   { opacity: 1; transform: scale(1); }
    }

    /* A hairline that reports work is happening, and reports nothing else. */
    .jm-boot-line {
        width: 104px;
        height: 2px;
        border-radius: 2px;
        background: rgba(255, 255, 255, 0.22);
        overflow: hidden;
    }

    .jm-boot-line::after {
        content: '';
        display: block;
        width: 40%;
        height: 100%;
        border-radius: 2px;
        background: #ffffff;
        animation: jm-boot-sweep 1.15s cubic-bezier(0.65, 0, 0.35, 1) infinite;
    }

    @keyframes jm-boot-sweep {
        0%   { transform: translateX(-100%); }
        100% { transform: translateX(350%); }
    }

    @media (prefers-reduced-motion: reduce) {
        .jm-boot-mark { animation: none; }
        .jm-boot-line::after { animation: none; width: 100%; opacity: 0.55; }
    }
</style>

    <div id="jm-boot" role="status" aria-label="Loading Jobmington">
        <img src="/jobmington/assets/images/pwa-icon-192.png?v=brand-29" alt="" class="jm-boot-mark">
        <span class="jm-boot-line"></span>
    </div>

    <script>
        /*
         * Dismissing the boot screen.
         *
         * An installed app shows it on every launch, because every launch has a
         * gap to cover. A browser tab shows it once and then never again, so a
         * returning visitor is not made to sit through a curtain they have
         * already seen.
         *
         * It leaves when the page is actually ready. The screen this replaces
         * advanced a bar by a random amount every 120ms and then held on
         * "READY!" for another half second, which came to roughly two seconds
         * of watching a progress bar report work that had finished before the
         * bar started drawing.
         */
        (function () {
            var boot = document.getElementById('jm-boot');
            if (!boot) { return; }

            function get(store, key) {
                try { return window[store].getItem(key) === 'true'; } catch (e) { return false; }
            }
            function set(store, key) {
                try { window[store].setItem(key, 'true'); } catch (e) {}
            }

            var standalone = (window.matchMedia && window.matchMedia('(display-mode: standalone)').matches)
                          || window.navigator.standalone === true;

            function skip() {
                boot.style.display = 'none';
            }

            /*
             * Which storage answers "has this been shown" decides everything,
             * because this is a multi-page app: inside an installed app every
             * link is a full page load, so a naive check would repaint the
             * curtain on every tap.
             *
             * sessionStorage is the one that means "this launch". It survives
             * navigation within the app and dies when the app closes, which is
             * exactly the boundary we want. localStorage means "ever", and
             * that is the right answer for a browser tab, where the screen is
             * a first-impression and not something to re-serve.
             *
             * The launch flag is written now rather than on dismiss, so a page
             * that fails halfway still counts as launched and the next tap
             * does not bring the curtain back.
             */
            if (standalone) {
                if (get('sessionStorage', 'jm_boot_launch')) { skip(); return; }
                set('sessionStorage', 'jm_boot_launch');
            } else if (get('localStorage', 'jm_boot_seen')) {
                skip();
                return;
            }

            var shownAt = Date.now();
            var MIN_MS  = 340;   // below this it reads as a flicker, worse than no screen at all
            var done    = false;

            function dismiss() {
                if (done) { return; }
                done = true;
                set('localStorage', 'jm_boot_seen');
                setTimeout(function () {
                    boot.classList.add('is-done');
                    setTimeout(function () { boot.style.display = 'none'; }, 450);
                }, Math.max(0, MIN_MS - (Date.now() - shownAt)));
            }

            if (document.readyState === 'complete') {
                dismiss();
            } else {
                window.addEventListener('load', dismiss);
            }

            // Ahead of the CSS failsafe at 5s, so the tidy path normally wins
            // and the stylesheet only ever catches a page where script died.
            setTimeout(dismiss, 4000);
        })();
    </script>
