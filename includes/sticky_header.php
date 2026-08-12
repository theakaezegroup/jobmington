<?php
/**
 * JOBMINGTON - the sticky header's scroll state.
 *
 * The bar is frosted at rest and brand blue once the page has moved. CSS does
 * the appearance; this only says when.
 *
 * Its own file because three different footers close the public site, and the
 * first version of this lived in one of them, so the home page and the job list
 * had it while events, tools, learn and community did not. Same lesson as the
 * three toast implementations and the three footers themselves: put it in one
 * place and have everything include it.
 */

if (!defined('JOBMINGTON')) {
    http_response_code(403);
    exit('Forbidden');
}

if (defined('JM_STICKY_HEADER_EMITTED')) {
    return;
}
define('JM_STICKY_HEADER_EMITTED', true);

$jmStickyNonce = isset($cspNonce) && $cspNonce !== '' ? ' nonce="' . htmlspecialchars($cspNonce, ENT_QUOTES) . '"' : '';
?>
<script<?= $jmStickyNonce ?>>
(function () {
    if (window.__jmStickyHeader) { return; }
    window.__jmStickyHeader = true;

    document.addEventListener('DOMContentLoaded', function () {
        var headers = document.querySelectorAll('.jm-header');
        if (!headers.length) { return; }

        Array.prototype.forEach.call(headers, function (header) {
            /* The logo swaps to the transparent mark on blue. The badge is a
               blue tile, and a blue tile on a blue bar is the mismatched square
               already taken off the mobile header. */
            var logo  = header.querySelector('.jm-logo img');
            var plain = logo ? logo.getAttribute('src') : null;
            var mark  = plain ? plain.replace('badge.png', 'badge-mark.png') : null;
            var swaps = !!(plain && mark && mark !== plain);

            var onPhone = window.matchMedia('(max-width: 900px)');
            var stuck = null;
            var shown = null;
            function paint() {
                var next = window.scrollY > 20;
                if (next !== stuck) {
                    stuck = next;
                    header.classList.toggle('is-stuck', next);
                }
                /* The phone bar is blue whether or not the page has moved, so
                   the mark is right there at all times. On desktop it follows
                   the scroll state. */
                if (swaps) {
                    var wantMark = onPhone.matches || next;
                    if (wantMark !== shown) {
                        shown = wantMark;
                        logo.src = wantMark ? mark : plain;
                    }
                }
            }
            if (onPhone.addEventListener) { onPhone.addEventListener('change', paint); }

            paint();
            window.addEventListener('scroll', paint, { passive: true });
        });
    });
})();
</script>
