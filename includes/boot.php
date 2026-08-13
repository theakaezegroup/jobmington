<?php
/**
 * The blue ground for launch.
 *
 * There is no splash screen of ours any more, and that is deliberate. Android
 * draws its own for an installed app, from the manifest, and it cannot be
 * suppressed: there is no field, meta tag or API for it, and on Android 12 and
 * later the system draws it before Chrome even holds the page. So anything we
 * painted after it was always a second screen following the real one, which is
 * what put two logos on screen a beat apart.
 *
 * What is left is the one part that was never a splash. The system's splash is
 * brand blue, but the document underneath it defaults to white, and that white
 * gets a frame on screen at the moment the app opens. This paints the ground
 * blue for exactly that frame, then hands back as soon as the page has drawn
 * its own.
 *
 * Include in <head>, before the stylesheets: its whole job is to be the colour
 * of the first frame, and a rule arriving after the first frame has missed the
 * only moment it mattered.
 */

if (!defined('JOBMINGTON')) {
    die('Direct access not permitted');
}

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
         * The body is painted too, not just the root. A root background alone
         * leaves the strip at the foot of a phone white, because the body
         * carries its own background and paints it over the root's within its
         * own box, and on Android the colour Chrome gives the gesture bar is
         * read off the body. !important because one of these bodies declares
         * its background inline, and an inline style beats a stylesheet.
         */
        html.jm-booting,
        html.jm-booting body {
            background: #0640a3 !important;
            /* Belt for a script that starts and then dies. transparent puts
               the canvas back to whatever the page declares, rather than
               guessing at a colour on its behalf. */
            animation: jm-ground-out 0.01s linear 4s forwards;
        }

        @keyframes jm-ground-out {
            to { background: transparent !important; }
        }
    </style>
    <script>
        (function () {
            var root = document.documentElement;
            root.classList.add('jm-booting');

            function clear() {
                root.classList.remove('jm-booting');
            }

            /*
             * Cleared once the document has been parsed and had a frame to
             * paint, not on load. Waiting for load would wait for every image
             * on the page, and holding the ground blue that long would show as
             * a blue page rather than a blue first frame.
             *
             * The class is added by script and removed by script on purpose:
             * a browser with no script never receives a ground it has no way
             * to clear.
             */
            function armed() {
                if (window.requestAnimationFrame) {
                    requestAnimationFrame(clear);
                } else {
                    clear();
                }
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', armed);
            } else {
                armed();
            }

            setTimeout(clear, 3000);
        })();
    </script>
    <?php
}
