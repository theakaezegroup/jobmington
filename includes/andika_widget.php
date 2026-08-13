<?php
/**
 * The Andika launcher.
 *
 * This is the only part that loads on every page, and it is kept to a button
 * and a few lines of script for a measured reason: the full Andika page is
 * 139KB and the homepage's entire critical path is 243KB. Putting the panel on
 * every page would add more than half the critical path again to every page on
 * the site and undo a day of work. So the stylesheet and the panel script are
 * fetched on the first click and never before.
 *
 * Include once, just before </body>.
 */

if (!defined('JOBMINGTON')) {
    die('Direct access not permitted');
}

/**
 * Should the launcher appear on this page at all?
 *
 * Not on the Andika page itself, where it would offer to open a small version
 * of what is already on screen. Not in admin, which is not a place anyone is
 * job hunting. And not when the tool is switched off, because a button that
 * opens a panel only to say "not available" is worse than no button.
 */
function jm_andika_launcher_applies(): bool
{
    $path = strtolower((string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH));

    if (str_contains($path, '/ai/andika') || str_contains($path, '/admin/')) {
        return false;
    }

    if (function_exists('jm_tool_available') && !jm_tool_available('andika')) {
        return false;
    }

    return true;
}

function jm_andika_launcher(): void
{
    static $done = false;
    if ($done || !jm_andika_launcher_applies()) {
        return;
    }
    $done = true;

    $base = defined('SITE_URL') ? rtrim(SITE_URL, '/') : '/jobmington';
    $mark = $base . '/assets/images/pwa-icon-192.png?v=brand-30';

    /*
     * Whether they are signed in has to be settled here, on the server, before
     * the panel offers anyone a text box.
     *
     * Andika's API calls Session::requireLogin(), which sends a 302 to the
     * login page rather than a JSON 401. A fetch follows that redirect, gets
     * HTML back with a 200, and fails on parse. Handled naively that surfaces
     * as a network error, so a signed-out person would type a question, wait,
     * and be told their connection is broken. Better to say so before they
     * type.
     */
    $signedIn = class_exists('Session') && Session::isLoggedIn();
    $loginUrl = $base . '/auth/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI'] ?? '/');
    ?>
    <style>
        #jm-ak-launcher {
            position: fixed;
            right: 20px;
            bottom: 20px;
            z-index: 2147482000;
            width: 54px;
            height: 54px;
            border: 0;
            border-radius: 50%;
            padding: 0;
            background: #0640a3;
            cursor: pointer;
            display: grid;
            place-items: center;
            box-shadow: 0 10px 26px -6px rgba(6, 20, 38, .38), 0 3px 8px -3px rgba(6, 20, 38, .22);
            transition: transform .18s cubic-bezier(.16, 1, .3, 1), box-shadow .18s;
        }
        #jm-ak-launcher:hover {
            transform: translateY(-2px) scale(1.04);
            box-shadow: 0 16px 32px -8px rgba(6, 20, 38, .44), 0 4px 10px -3px rgba(6, 20, 38, .26);
        }
        #jm-ak-launcher:active { transform: scale(.96); }
        #jm-ak-launcher:focus-visible { outline: 3px solid #f59f22; outline-offset: 3px; }
        #jm-ak-launcher img { width: 28px; height: 28px; object-fit: contain; display: block; }
        #jm-ak-launcher[hidden] { display: none; }
        @media (max-width: 560px) {
            #jm-ak-launcher { right: 16px; bottom: 16px; width: 50px; height: 50px; }
            #jm-ak-launcher img { width: 26px; height: 26px; }
        }
        @media (prefers-reduced-motion: reduce) {
            #jm-ak-launcher { transition: none; }
            #jm-ak-launcher:hover, #jm-ak-launcher:active { transform: none; }
        }
    </style>

    <button type="button" id="jm-ak-launcher" aria-label="Ask Andika, your career assistant" title="Ask Andika">
        <img src="<?= e($mark) ?>" alt="">
    </button>

    <script>
    window.JM_ANDIKA = {
        base: <?= json_encode($base) ?>,
        mark: <?= json_encode($mark) ?>,
        signedIn: <?= $signedIn ? 'true' : 'false' ?>,
        loginUrl: <?= json_encode($loginUrl) ?>
    };
    (function () {
        var btn = document.getElementById('jm-ak-launcher');
        if (!btn) { return; }
        var loading = false;

        btn.addEventListener('click', function () {
            // Already fetched once: just reopen, no second request.
            if (window.JMAndikaPanel) {
                btn.hidden = true;
                window.JMAndikaPanel.open();
                return;
            }
            if (loading) { return; }
            loading = true;
            btn.hidden = true;

            var css = document.createElement('link');
            css.rel = 'stylesheet';
            css.href = window.JM_ANDIKA.base + '/assets/css/andika-panel.css?v=brand-30';
            document.head.appendChild(css);

            var js = document.createElement('script');
            js.src = window.JM_ANDIKA.base + '/assets/js/andika-panel.js?v=brand-30';
            js.onerror = function () {
                // Never strand someone behind a button that did nothing.
                loading = false;
                btn.hidden = false;
            };
            document.body.appendChild(js);
        });
    })();
    </script>
    <?php
}
