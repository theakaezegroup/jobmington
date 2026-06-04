<?php
/**
 * Reusable notification bell for members. Self-contained (own styles + JS +
 * Web-Audio chime). Call jm_notification_bell() inside any site header's
 * right-hand area. Renders nothing for guests. Safe to include once per page.
 */
if (!defined('JOBMINGTON')) { exit; }

function jm_notification_bell(): void {
    if (!class_exists('Session') || !Session::isLoggedIn()) return;

    static $rendered = false;
    if ($rendered) return; // one bell per page
    $rendered = true;

    $base = defined('SITE_URL') ? SITE_URL : '';
    ?>
    <div class="jm-bell" style="position:relative;">
        <button type="button" id="jm-bell-btn" class="jm-bell-btn" title="Notifications" aria-label="Notifications">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
            <span id="jm-bell-badge" class="jm-bell-badge">0</span>
        </button>
        <div id="jm-bell-panel" class="jm-bell-panel" hidden>
            <div class="jm-bell-head"><span>Notifications</span><button type="button" id="jm-bell-readall">Mark all read</button></div>
            <div id="jm-bell-list" class="jm-bell-list"><div class="jm-bell-empty">Loading&hellip;</div></div>
            <a class="jm-bell-foot" href="/jobmington/seeker/notifications.php">View all notifications</a>
        </div>
    </div>
    <style>
        .jm-bell-btn { position:relative; width:38px; height:38px; display:inline-flex; align-items:center; justify-content:center; border-radius:10px; background:#fff; border:1px solid #e8edf5; color:#101828; cursor:pointer; padding:0; transition:background .15s; }
        .jm-bell-btn:hover { background:#f6f8fb; }
        .jm-bell-btn svg { width:18px; height:18px; }
        .jm-bell-badge { position:absolute; top:-5px; right:-5px; min-width:17px; height:17px; padding:0 4px; border-radius:99px; background:#e11d2a; color:#fff; font-size:10px; font-weight:800; line-height:17px; text-align:center; box-shadow:0 0 0 2px #fff; display:none; }
        .jm-bell-badge.show { display:block; }
        .jm-bell-ring { animation: jmBellRing .9s ease; }
        @keyframes jmBellRing { 0%,100%{transform:rotate(0)} 20%{transform:rotate(14deg)} 40%{transform:rotate(-12deg)} 60%{transform:rotate(8deg)} 80%{transform:rotate(-5deg)} }
        .jm-bell-panel { position:absolute; top:48px; right:0; width:340px; max-width:88vw; background:#fff; border:1px solid #e8edf5; border-radius:14px; box-shadow:0 20px 48px -16px rgba(16,24,40,.28); z-index:200; overflow:hidden; }
        .jm-bell-panel[hidden] { display:none; }
        .jm-bell-head { display:flex; align-items:center; justify-content:space-between; padding:13px 16px; border-bottom:1px solid #f0f4f9; }
        .jm-bell-head span { font-size:13px; font-weight:800; color:#101828; text-transform:uppercase; letter-spacing:.05em; }
        .jm-bell-head button { background:none; border:0; color:#0640a3; font-size:12px; font-weight:700; cursor:pointer; }
        .jm-bell-list { max-height:380px; overflow-y:auto; }
        .jm-bell-item { display:flex; gap:11px; padding:13px 16px; border-bottom:1px solid #f5f8fc; text-decoration:none; transition:background .12s; cursor:pointer; }
        .jm-bell-item:hover { background:#f8fbff; }
        .jm-bell-item.unread { background:#f3f8ff; }
        .jm-bell-d { width:8px; height:8px; border-radius:50%; background:#0640a3; flex-shrink:0; margin-top:5px; opacity:0; }
        .jm-bell-item.unread .jm-bell-d { opacity:1; }
        .jm-bell-t { flex:1; min-width:0; }
        .jm-bell-t b { display:block; font-size:13.5px; font-weight:700; color:#101828; line-height:1.35; }
        .jm-bell-t small { display:block; font-size:12px; color:#667085; margin-top:2px; line-height:1.45; }
        .jm-bell-ago { font-size:11px; color:#98a2b3; margin-top:4px; display:block; }
        .jm-bell-empty { padding:34px 16px; text-align:center; color:#98a2b3; font-size:13px; }
        .jm-bell-foot { display:block; text-align:center; padding:12px; font-size:13px; font-weight:700; color:#0640a3; text-decoration:none; border-top:1px solid #f0f4f9; }
        .jm-bell-foot:hover { background:#f8fbff; }
    </style>
    <script>
    (function () {
        var btn = document.getElementById('jm-bell-btn');
        var panel = document.getElementById('jm-bell-panel');
        var list = document.getElementById('jm-bell-list');
        var badge = document.getElementById('jm-bell-badge');
        var readAll = document.getElementById('jm-bell-readall');
        if (!btn || !panel || btn.dataset.init) return;
        btn.dataset.init = '1';
        var base = <?= json_encode($base) ?>;
        var lastUnread = parseInt(localStorage.getItem('jm_notif_seen') || '0', 10) || 0;
        var actx = null;

        function chime() {
            try {
                actx = actx || new (window.AudioContext || window.webkitAudioContext)();
                var now = actx.currentTime;
                [[880, 0], [1174.66, 0.11]].forEach(function (n) {
                    var o = actx.createOscillator(), g = actx.createGain();
                    o.type = 'sine'; o.frequency.value = n[0]; o.connect(g); g.connect(actx.destination);
                    var t = now + n[1];
                    g.gain.setValueAtTime(0, t);
                    g.gain.linearRampToValueAtTime(0.14, t + 0.02);
                    g.gain.exponentialRampToValueAtTime(0.0001, t + 0.5);
                    o.start(t); o.stop(t + 0.55);
                });
            } catch (e) {}
        }
        function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; }
        function setBadge(n) { if (n > 0) { badge.textContent = n > 99 ? '99+' : n; badge.classList.add('show'); } else { badge.classList.remove('show'); } }
        function render(items) {
            if (!items.length) { list.innerHTML = '<div class="jm-bell-empty">You\'re all caught up.</div>'; return; }
            list.innerHTML = items.map(function (n) {
                var inner = '<span class="jm-bell-d"></span><span class="jm-bell-t"><b>' + esc(n.title) + '</b>'
                    + (n.message ? '<small>' + esc(n.message) + '</small>' : '')
                    + '<span class="jm-bell-ago">' + esc(n.ago) + '</span></span>';
                var cls = 'jm-bell-item' + (n.is_read ? '' : ' unread');
                return n.link ? '<a class="' + cls + '" data-id="' + n.id + '" href="' + esc(n.link) + '">' + inner + '</a>'
                              : '<div class="' + cls + '" data-id="' + n.id + '">' + inner + '</div>';
            }).join('');
            list.querySelectorAll('[data-id]').forEach(function (el) {
                el.addEventListener('click', function () {
                    fetch(base + '/api/notifications.php?action=read&id=' + encodeURIComponent(el.getAttribute('data-id')), { method: 'POST' });
                    el.classList.remove('unread');
                });
            });
        }
        function load(open) {
            fetch(base + '/api/notifications.php?action=list', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (r) { return r.ok ? r.json() : null; })
                .then(function (d) {
                    if (!d || !d.success) return;
                    var u = d.data.unread || 0; setBadge(u);
                    if (u > lastUnread) { btn.classList.add('jm-bell-ring'); setTimeout(function () { btn.classList.remove('jm-bell-ring'); }, 1000); chime(); }
                    lastUnread = u; localStorage.setItem('jm_notif_seen', u);
                    if (open) render(d.data.items || []);
                }).catch(function () {});
        }
        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            if (panel.hasAttribute('hidden')) { panel.removeAttribute('hidden'); list.innerHTML = '<div class="jm-bell-empty">Loading&hellip;</div>'; load(true); }
            else { panel.setAttribute('hidden', ''); }
        });
        document.addEventListener('click', function (e) { if (!panel.contains(e.target) && e.target !== btn && !btn.contains(e.target)) panel.setAttribute('hidden', ''); });
        if (readAll) readAll.addEventListener('click', function () {
            fetch(base + '/api/notifications.php?action=read_all', { method: 'POST' }).then(function () {
                setBadge(0); lastUnread = 0; localStorage.setItem('jm_notif_seen', '0');
                list.querySelectorAll('.jm-bell-item').forEach(function (i) { i.classList.remove('unread'); });
            });
        });
        load(false);
        setInterval(function () { load(false); }, 45000);
    })();
    </script>
    <?php
}
