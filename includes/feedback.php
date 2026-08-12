<?php
/**
 * JOBMINGTON - toasts and sound, in one place.
 *
 * There were three toast implementations: one in footer.php, a second in
 * ai-footer.php that redefined window.JM.toast with different markup and a
 * different signature, and a third inside andika.php. Two of them wrote to the
 * same element id, so which one you got depended on which footer the page
 * happened to load.
 *
 * Both of the shared ones also built their markup with innerHTML and dropped
 * the caller's message straight in, so any message carrying user data was an
 * XSS. Nothing escapes here because nothing is interpolated: the nodes are
 * built with textContent.
 *
 * Styles are self-contained rather than borrowed from the page, so this works
 * the same on the public site, the AI pages and the admin panel.
 */

if (!defined('JOBMINGTON')) {
    http_response_code(403);
    exit('Forbidden');
}

if (defined('JM_FEEDBACK_EMITTED')) {
    return;
}
define('JM_FEEDBACK_EMITTED', true);

$jmFbNonce = isset($cspNonce) && $cspNonce !== '' ? ' nonce="' . htmlspecialchars($cspNonce, ENT_QUOTES) . '"' : '';
?>
<div class="jm-fb-stack" id="jm-toast-container" role="status" aria-live="polite" aria-atomic="false"></div>
<style<?= $jmFbNonce ?>>
.jm-fb-stack {
    position: fixed; top: 18px; right: 18px; z-index: 9999;
    display: flex; flex-direction: column; gap: 10px;
    pointer-events: none; max-width: min(380px, calc(100vw - 24px));
}
@media (max-width: 560px) { .jm-fb-stack { top: 12px; right: 12px; left: 12px; max-width: none; } }

.jm-fb {
    pointer-events: auto;
    display: flex; align-items: flex-start; gap: 11px;
    padding: 13px 14px;
    background: #fff; border-radius: 14px;
    box-shadow: 0 2px 6px rgba(11,27,51,.06), 0 18px 40px -18px rgba(11,27,51,.34);
    font-family: 'Futura Cyrillic Book','Century Gothic','Trebuchet MS',Helvetica,Arial,sans-serif;
    transform: translateY(-8px); opacity: 0;
    transition: transform .22s cubic-bezier(.2,.8,.2,1), opacity .22s;
}
.jm-fb.in   { transform: translateY(0); opacity: 1; }
.jm-fb.out  { transform: translateY(-8px); opacity: 0; }

.jm-fb-ico { width: 20px; height: 20px; flex: none; margin-top: 1px; }
.jm-fb-ico svg { width: 100%; height: 100%; fill: none; stroke-width: 2.2; stroke-linecap: round; stroke-linejoin: round; }
.jm-fb-body { min-width: 0; flex: 1; }
.jm-fb-title { font-family: 'Futura Cyrillic Demi','Century Gothic',Helvetica,Arial,sans-serif; font-size: 13.5px; font-weight: 700; color: #0b1b33; line-height: 1.3; }
.jm-fb-msg { font-size: 13px; color: #56677f; line-height: 1.5; margin-top: 2px; overflow-wrap: anywhere; }
.jm-fb-x {
    flex: none; background: none; border: 0; cursor: pointer; padding: 2px;
    color: #b3becd; line-height: 0; border-radius: 6px;
}
.jm-fb-x:hover { color: #56677f; background: #f2f5fa; }
.jm-fb-x svg { width: 14px; height: 14px; fill: none; stroke-width: 2.4; stroke-linecap: round; }

.jm-fb.success .jm-fb-ico svg { stroke: #0f766e; }
.jm-fb.error   .jm-fb-ico svg { stroke: #b42318; }
.jm-fb.warning .jm-fb-ico svg { stroke: #b66b00; }
.jm-fb.info    .jm-fb-ico svg { stroke: #0640a3; }

@media (prefers-reduced-motion: reduce) {
    .jm-fb { transition: opacity .01s; transform: none; }
    .jm-fb.in, .jm-fb.out { transform: none; }
}
</style>
<script<?= $jmFbNonce ?>>
(function () {
    window.JM = window.JM || {};
    if (JM.__feedback) { return; }
    JM.__feedback = true;

    /* ── Sound ──────────────────────────────────────────────────────────
       Synthesised, so there is no audio file to ship, cache or 404.

       A browser will not let a page make noise before the visitor has
       interacted with it: an AudioContext created on page load starts
       suspended and stays silent. The old bell built one at load and never
       resumed it, which is why its chime never actually played. This one is
       created on the first real interaction and resumed if it is suspended. */
    var ctx = null;
    var unlocked = false;

    function unlock() {
        if (unlocked) { return; }
        unlocked = true;
        try {
            ctx = ctx || new (window.AudioContext || window.webkitAudioContext)();
            if (ctx.state === 'suspended') { ctx.resume(); }
        } catch (e) { ctx = null; }
        ['pointerdown', 'keydown', 'touchstart'].forEach(function (ev) {
            document.removeEventListener(ev, unlock, true);
        });
    }
    ['pointerdown', 'keydown', 'touchstart'].forEach(function (ev) {
        document.addEventListener(ev, unlock, true);
    });

    // Each cue is a list of [frequency, delay, duration, peak volume].
    var CUES = {
        notify:  [[880, 0, 0.5, 0.13], [1174.66, 0.11, 0.5, 0.11]],
        save:    [[660, 0, 0.16, 0.08], [990, 0.06, 0.2, 0.07]],
        success: [[587.33, 0, 0.22, 0.09], [880, 0.08, 0.3, 0.08]],
        error:   [[330, 0, 0.28, 0.09], [246.94, 0.1, 0.34, 0.08]]
    };

    JM.soundOn = function () {
        return localStorage.getItem('jm_sound') !== '0';
    };
    JM.setSound = function (on) {
        localStorage.setItem('jm_sound', on ? '1' : '0');
        if (on) { unlock(); JM.sound('save'); }   // confirm it audibly
    };

    JM.sound = function (name) {
        if (!JM.soundOn() || !ctx || ctx.state !== 'running') { return; }
        var cue = CUES[name];
        if (!cue) { return; }
        try {
            var now = ctx.currentTime;
            cue.forEach(function (n) {
                var osc = ctx.createOscillator();
                var gain = ctx.createGain();
                osc.type = 'sine';
                osc.frequency.value = n[0];
                osc.connect(gain);
                gain.connect(ctx.destination);
                var at = now + n[1];
                gain.gain.setValueAtTime(0, at);
                gain.gain.linearRampToValueAtTime(n[3], at + 0.02);
                gain.gain.exponentialRampToValueAtTime(0.0001, at + n[2]);
                osc.start(at);
                osc.stop(at + n[2] + 0.05);
            });
        } catch (e) { /* audio is a courtesy, never a failure */ }
    };

    /* ── Toast ───────────────────────────────────────────────────────── */
    var ICONS = {
        success: '<path d="M20 6L9 17l-5-5"/>',
        error:   '<circle cx="12" cy="12" r="10"/><path d="M15 9l-6 6M9 9l6 6"/>',
        warning: '<path d="M10.3 3.9L1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/><path d="M12 9v4M12 17h.01"/>',
        info:    '<circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/>'
    };
    var TITLES = { success: 'Done', error: 'Something went wrong', warning: 'Heads up', info: 'Note' };

    JM.toast = function (message, type, title) {
        type = ICONS[type] ? type : 'info';
        var stack = document.getElementById('jm-toast-container');
        if (!stack) { return; }

        var el = document.createElement('div');
        el.className = 'jm-fb ' + type;

        var ico = document.createElement('span');
        ico.className = 'jm-fb-ico';
        ico.innerHTML = '<svg viewBox="0 0 24 24">' + ICONS[type] + '</svg>';   // ours, not the caller's

        var body = document.createElement('div');
        body.className = 'jm-fb-body';

        var h = document.createElement('div');
        h.className = 'jm-fb-title';
        h.textContent = title || TITLES[type];

        var m = document.createElement('div');
        m.className = 'jm-fb-msg';
        m.textContent = message == null ? '' : String(message);   // never interpolated

        body.appendChild(h);
        if (m.textContent !== '') { body.appendChild(m); }

        var x = document.createElement('button');
        x.className = 'jm-fb-x';
        x.type = 'button';
        x.setAttribute('aria-label', 'Dismiss');
        x.innerHTML = '<svg viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12"/></svg>';

        el.appendChild(ico);
        el.appendChild(body);
        el.appendChild(x);
        stack.appendChild(el);

        requestAnimationFrame(function () { el.classList.add('in'); });

        var timer = setTimeout(close, 4200);
        function close() {
            clearTimeout(timer);
            el.classList.add('out');
            setTimeout(function () { el.remove(); }, 240);
        }
        x.addEventListener('click', close);

        if (type === 'error') { JM.sound('error'); }
        else if (type === 'success') { JM.sound('success'); }

        return el;
    };
})();
</script>
