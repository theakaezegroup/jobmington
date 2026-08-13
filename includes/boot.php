<?php
/**
 * The boot screen: what an installed app shows between the tap and the paint.
 *
 * Two pieces, because they belong in different parts of the document and only
 * work if they are in the right one:
 *
 *   jm_boot_ground()  goes in <head>. It paints the document's ground brand
 *                     blue before anything else renders. Without it the native
 *                     splash hands over to a page whose default canvas is
 *                     white, and that white gets one frame on screen at the
 *                     moment the app opens. The curtain itself cannot prevent
 *                     that: it lives in the body, so it is painted a beat too
 *                     late to cover the very first frame.
 *
 *   jm_boot_screen()  goes immediately after <body>. The mark and the hairline.
 *
 * It lives in one file because 43 pages carry their own <body>, and the one
 * that matters most is index.php. That is the manifest's start_url, so it is
 * what an installed app actually opens, and it does not use header.php. A copy
 * in each place would drift apart the way the tool catalogue drifted from the
 * paywall.
 */

if (!defined('JOBMINGTON')) {
    die('Direct access not permitted');
}

/**
 * The blue ground, for <head>.
 *
 * The class is added by script rather than sitting on the markup, and that is
 * deliberate: the same script that turns the ground blue is the one that turns
 * it back, so a browser with no script never gets a ground it cannot clear.
 * The animation is a second belt for the case where script starts and then
 * dies: transparent restores the canvas to whatever the page itself declares,
 * which is the honest way to undo this rather than guessing at white.
 */
function jm_boot_ground(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    ?>
    <style>
        /*
         * The body has to be painted too, not just the root.
         *
         * A root background alone leaves the strip at the foot of a phone
         * white, because the body carries its own background and paints it
         * over the root's within its own box, and on Android the colour Chrome
         * gives the gesture bar is read off the body. So both, and !important,
         * because one of these bodies declares its background inline and an
         * inline style beats a stylesheet every time.
         */
        html.jm-booting,
        html.jm-booting body {
            background: #0640a3 !important;
            animation: jm-ground-out 0.01s linear 5.2s forwards;
        }
        @keyframes jm-ground-out {
            to { background: transparent !important; }
        }
    </style>
    <script>document.documentElement.classList.add('jm-booting');</script>
    <?php
}

/**
 * The curtain itself, for immediately after <body>.
 */
function jm_boot_screen(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    ?>
    <style>
        /*
         * The field is the same blue the manifest paints for the native
         * splash, so one hands over to the other with no change of colour in
         * between, and the mark carries that blue baked in, which is why it
         * sits on the field with no edge showing.
         *
         * The rest is restraint. One mark, one hairline, no wordmark, no
         * percentage. We do not know a percentage, and a number invented to be
         * watched only makes people wait for work that has already finished.
         */
        #jm-boot {
            position: fixed;
            inset: 0;
            /* inset alone leaves a phone's bottom strip uncovered when the
               viewport reported to CSS is shorter than the glass. dvh is the
               height that accounts for the browser's own chrome; vh is the
               fallback for engines that do not know dvh yet. */
            width: 100%;
            height: 100vh;
            height: 100dvh;
            z-index: 99999;
            background: #0640a3;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 20px;
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
            width: 48px;
            height: 48px;
            object-fit: contain;
            animation: jm-boot-in 0.6s cubic-bezier(0.16, 1, 0.3, 1) both;
        }

        @keyframes jm-boot-in {
            from { opacity: 0; transform: scale(0.94); }
            to   { opacity: 1; transform: scale(1); }
        }

        /* A hairline that reports work is happening, and reports nothing else. */
        .jm-boot-line {
            width: 80px;
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

        /*
         * Installed, the system has already drawn the mark: larger, centred,
         * a moment earlier. Drawing our own underneath it is what produced two
         * logos at two sizes a beat apart, and no amount of resizing fixes
         * that, because the problem is that there are two of them.
         *
         * So installed, there is no second mark. The hairline stays and moves
         * to sit below the middle of the screen, which is where the system
         * puts its icon, so it reads as a loader under the main logo rather
         * than a loader belonging to a smaller one of our own.
         *
         * In a browser tab none of this applies: nothing drew a mark before
         * us, so ours is the only one and it keeps its place above the line.
         */
        @media (display-mode: standalone) {
            .jm-boot-mark {
                display: none;
            }

            .jm-boot-line {
                position: absolute;
                top: 58%;
                left: 50%;
                transform: translateX(-50%);
            }
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

            var root = document.documentElement;

            function get(store, key) {
                try { return window[store].getItem(key) === 'true'; } catch (e) { return false; }
            }
            function set(store, key) {
                try { window[store].setItem(key, 'true'); } catch (e) {}
            }

            // The ground goes back to the page's own colour whenever the
            // curtain goes, by either route. Leaving it blue would tint every
            // overscroll and every gap the page does not paint itself.
            function clearGround() {
                root.classList.remove('jm-booting');
            }

            var standalone = (window.matchMedia && window.matchMedia('(display-mode: standalone)').matches)
                          || window.navigator.standalone === true;

            function skip() {
                boot.style.display = 'none';
                clearGround();
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
             * a first impression and not something to re-serve.
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
                    setTimeout(function () {
                        boot.style.display = 'none';
                        clearGround();
                    }, 450);
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
    <?php
}
