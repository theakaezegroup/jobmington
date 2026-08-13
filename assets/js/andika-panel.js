/*
 * Andika panel.
 *
 * Fetched on first open, never on page load.
 *
 * It deliberately reuses two things the full page already owns, rather than
 * inventing its own:
 *
 *   the store   localStorage['andika_chats'], an array of
 *               { id, title, messages, createdAt, updatedAt } where a message
 *               is { text, type: 'user'|'ai', timestamp }
 *   the API     POST /api/andika.php  { message, tool }
 *                                  -> { success, reply, cost, balance }
 *
 * That is the whole point of the format being copied exactly: a conversation
 * begun in this panel is the same conversation when it is opened in the full
 * view, with no sync, no migration and no second source of truth.
 */
(function () {
    'use strict';

    if (window.JMAndikaPanel) { return; }

    var STORE = 'andika_chats';
    var cfg = window.JM_ANDIKA || {};
    var base = (cfg.base || '').replace(/\/$/, '');

    var panel = null, thread = null, input = null, sendBtn = null;
    var messages = [], chatId = null, busy = false, abort = null;

    /* ── The store, byte-compatible with the full page ───────────── */

    function readChats() {
        try { return JSON.parse(localStorage.getItem(STORE)) || []; } catch (e) { return []; }
    }

    function writeChats(chats) {
        try { localStorage.setItem(STORE, JSON.stringify(chats)); } catch (e) {}
    }

    function persist() {
        if (!messages.length) { return; }
        var chats = readChats();
        var first = null, i;
        for (i = 0; i < messages.length; i++) {
            if (messages[i].type === 'user') { first = messages[i]; break; }
        }
        var title = first ? first.text.substring(0, 40) + (first.text.length > 40 ? '...' : '') : 'New Chat';

        if (chatId) {
            for (i = 0; i < chats.length; i++) {
                if (chats[i].id === chatId) {
                    chats[i].messages = messages;
                    chats[i].updatedAt = Date.now();
                    chats[i].title = title;
                    writeChats(chats);
                    return;
                }
            }
        }
        chatId = 'chat_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
        chats.push({ id: chatId, title: title, messages: messages, createdAt: Date.now(), updatedAt: Date.now() });
        writeChats(chats);
    }

    /* Resume the most recent conversation, so opening the panel continues
       rather than restarts. Anything older than a day starts fresh: picking up
       a week-old thread is disorienting, not helpful. */
    function resume() {
        var chats = readChats();
        if (!chats.length) { return; }
        var latest = chats[0], i;
        for (i = 1; i < chats.length; i++) {
            if ((chats[i].updatedAt || 0) > (latest.updatedAt || 0)) { latest = chats[i]; }
        }
        if (!latest || !latest.messages || !latest.messages.length) { return; }
        if (Date.now() - (latest.updatedAt || 0) > 86400000) { return; }
        chatId = latest.id;
        messages = latest.messages.slice();
    }

    /* ── Rendering ───────────────────────────────────────────────── */

    function esc(s) {
        return String(s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    /*
     * Everything is escaped first and only then given back a little markup, in
     * that order, because the reverse is how injection happens. Bold and links
     * are the only things restored: they are what the model actually emits and
     * what a reader loses meaning without.
     */
    function render(text) {
        var html = esc(text);
        html = html.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
        html = html.replace(/(https?:\/\/[^\s<]+[^\s<.,;:!?)])/g,
            '<a href="$1" target="_blank" rel="noopener noreferrer">$1</a>');
        return html;
    }

    function bubble(text, type) {
        var el = document.createElement('div');
        el.className = 'jm-ak-msg ' + type;
        if (type === 'user') { el.textContent = text; } else { el.innerHTML = render(text); }
        thread.appendChild(el);
        return el;
    }

    function toBottom() {
        thread.scrollTop = thread.scrollHeight;
    }

    function paintThread() {
        thread.innerHTML = '';
        if (!messages.length) { paintEmpty(); return; }
        messages.forEach(function (m) { bubble(m.text, m.type === 'user' ? 'user' : 'ai'); });
        requestAnimationFrame(toBottom);
    }

    var SEEDS = [
        'Why do my applications get no reply?',
        'Help me pivot into a new field',
        'Practise an interview with me'
    ];

    function paintEmpty() {
        var wrap = document.createElement('div');
        wrap.className = 'jm-ak-empty';
        wrap.innerHTML =
            '<img class="jm-ak-empty-mark" src="' + esc(cfg.mark || '') + '" alt="">' +
            '<h3>Hi, I am Andika</h3>' +
            '<p>Your career assistant. Ask me anything about work, applications or getting hired.</p>';
        var seeds = document.createElement('div');
        seeds.className = 'jm-ak-seeds';
        SEEDS.forEach(function (s) {
            var b = document.createElement('button');
            b.type = 'button';
            b.className = 'jm-ak-seed';
            b.textContent = s;
            b.addEventListener('click', function () { send(s); });
            seeds.appendChild(b);
        });
        wrap.appendChild(seeds);
        thread.appendChild(wrap);
    }

    /* ── Sending ─────────────────────────────────────────────────── */

    function setBusy(state) {
        busy = state;
        sendBtn.disabled = state || !input.value.trim();
    }

    function typing(on) {
        var old = thread.querySelector('.jm-ak-typing');
        if (old) { old.remove(); }
        if (!on) { return; }
        var t = document.createElement('div');
        t.className = 'jm-ak-typing';
        t.innerHTML = '<i></i><i></i><i></i>';
        thread.appendChild(t);
        toBottom();
    }

    function fail(text) {
        typing(false);
        var el = bubble(text, 'err');
        el.className = 'jm-ak-msg err';
        toBottom();
    }

    function send(text) {
        text = (text || '').trim();
        if (!text || busy) { return; }

        var empty = thread.querySelector('.jm-ak-empty');
        if (empty) { empty.remove(); }

        messages.push({ text: text, type: 'user', timestamp: Date.now() });
        bubble(text, 'user');
        persist();

        input.value = '';
        input.style.height = 'auto';
        setBusy(true);
        typing(true);
        toBottom();

        abort = new AbortController();
        fetch(base + '/api/andika.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ message: text, tool: 'chat' }),
            signal: abort.signal
        }).then(function (r) {
            return r.json().then(function (d) { return { ok: r.ok, status: r.status, data: d }; });
        }).then(function (res) {
            typing(false);
            setBusy(false);
            if (res.status === 401) {
                fail('Please sign in to keep talking to Andika.');
                return;
            }
            if (res.status === 403) {
                fail('Andika is not available on your account right now.');
                return;
            }
            if (!res.ok || !res.data || !res.data.success || !res.data.reply) {
                fail('Andika could not answer that. Try again in a moment.');
                return;
            }
            messages.push({ text: res.data.reply, type: 'ai', timestamp: Date.now() });
            bubble(res.data.reply, 'ai');
            persist();
            toBottom();
        }).catch(function (e) {
            if (e && e.name === 'AbortError') { return; }
            typing(false);
            setBusy(false);
            fail('No connection. Check your network and try again.');
        });
    }

    /* ── Building ────────────────────────────────────────────────── */

    var ICON_FULL = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h6v6"/><path d="M10 14 21 3"/><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/></svg>';
    var ICON_CLOSE = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>';
    var ICON_SEND = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12 14 0"/><path d="m12 5 7 7-7 7"/></svg>';

    function build() {
        panel = document.createElement('div');
        panel.className = 'jm-ak-panel';
        panel.setAttribute('role', 'dialog');
        panel.setAttribute('aria-label', 'Andika, your career assistant');

        panel.innerHTML =
            '<div class="jm-ak-head">' +
                '<img class="jm-ak-mark" src="' + esc(cfg.mark || '') + '" alt="">' +
                '<div class="jm-ak-titles">' +
                    '<div class="jm-ak-name">Andika</div>' +
                    '<div class="jm-ak-sub"><span class="jm-ak-dot"></span>Career assistant</div>' +
                '</div>' +
                '<a class="jm-ak-hbtn" href="' + esc(base) + '/ai/andika" title="Open full view" aria-label="Open full view">' + ICON_FULL + '</a>' +
                '<button type="button" class="jm-ak-hbtn" data-ak="close" title="Close" aria-label="Close">' + ICON_CLOSE + '</button>' +
            '</div>' +
            '<div class="jm-ak-thread" id="jm-ak-thread"></div>' +
            '<div class="jm-ak-foot">' +
                '<div class="jm-ak-form">' +
                    '<textarea class="jm-ak-input" rows="1" placeholder="Ask Andika anything" aria-label="Message Andika"></textarea>' +
                    '<button type="button" class="jm-ak-send" disabled aria-label="Send">' + ICON_SEND + '</button>' +
                '</div>' +
                '<p class="jm-ak-legal">Andika can be wrong. Check anything that matters.</p>' +
            '</div>';

        document.body.appendChild(panel);

        thread = panel.querySelector('.jm-ak-thread');
        input = panel.querySelector('.jm-ak-input');
        sendBtn = panel.querySelector('.jm-ak-send');

        panel.querySelector('[data-ak="close"]').addEventListener('click', close);
        sendBtn.addEventListener('click', function () { send(input.value); });

        input.addEventListener('input', function () {
            input.style.height = 'auto';
            input.style.height = Math.min(input.scrollHeight, 120) + 'px';
            sendBtn.disabled = busy || !input.value.trim();
        });

        input.addEventListener('keydown', function (e) {
            // Enter sends, Shift+Enter breaks the line. On a phone Enter is a
            // newline, because there the send button is right there and a
            // keyboard that sends on Enter makes multi-line messages hard.
            if (e.key === 'Enter' && !e.shiftKey && window.innerWidth > 560) {
                e.preventDefault();
                send(input.value);
            }
        });

        document.addEventListener('keydown', onEsc);
        resume();
        paintThread();
    }

    function onEsc(e) {
        if (e.key === 'Escape' && panel) { close(); }
    }

    /* ── Open and close ──────────────────────────────────────────── */

    function open() {
        if (panel) { return; }
        build();
        setTimeout(function () {
            if (input && window.innerWidth > 560) { input.focus(); }
        }, 340);
    }

    function close() {
        if (!panel) { return; }
        if (abort) { try { abort.abort(); } catch (e) {} }
        document.removeEventListener('keydown', onEsc);
        var dying = panel;
        panel = null;
        dying.classList.add('is-closing');
        setTimeout(function () { dying.remove(); }, 220);
        var launcher = document.getElementById('jm-ak-launcher');
        if (launcher) { launcher.hidden = false; launcher.focus(); }
    }

    window.JMAndikaPanel = { open: open, close: close, isOpen: function () { return !!panel; } };
    open();
})();
