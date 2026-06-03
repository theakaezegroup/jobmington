<?php if (!defined('JOBMINGTON')) { exit; } ?>
<style>
@keyframes jmFadeUp { from{opacity:0;transform:translateY(16px)} to{opacity:1;transform:translateY(0)} }
@keyframes jmSpin { to { transform: rotate(360deg); } }
.jm-spin { animation: jmSpin .8s linear infinite; }

.jm-tool-wrap { max-width: 1100px; margin: 0 auto; padding: 32px 24px 64px; }

.jm-tool-hero { margin-bottom: 32px; animation: jmFadeUp .5s ease both; }
.jm-tool-hero h1 { font-size: clamp(28px, 4vw, 42px); font-weight: 800; color: var(--jm-ink); margin: 0 0 10px; line-height: 1.05; }
.jm-tool-hero p { font-size: 16px; color: var(--jm-muted); margin: 0; max-width: 560px; line-height: 1.7; }

.jm-tool-grid { display: grid; grid-template-columns: 380px minmax(0, 1fr); gap: 24px; align-items: start; }
@media (max-width: 880px) { .jm-tool-grid { grid-template-columns: 1fr; } }

.jm-tool-aside { display: grid; gap: 16px; animation: jmFadeUp .5s ease both .1s; }
.jm-tool-card { border: 1px solid var(--jm-line); border-radius: 12px; background: #fff; padding: 22px; }

.jm-tool-label { display: block; font-size: 13px; font-weight: 700; color: var(--jm-ink); margin: 14px 0 6px; }
.jm-tool-card > .jm-tool-label:first-child { margin-top: 0; }
.jm-tool-label .req { color: #b42318; }
.jm-tool-label .opt { color: var(--jm-muted); font-weight: 400; }

.jm-tool-input, .jm-tool-textarea {
    width: 100%; box-sizing: border-box; border: 1px solid #b8c8df; border-radius: 8px;
    background: var(--jm-soft); color: var(--jm-ink); padding: 11px 13px; font: inherit; font-size: 14px;
    outline: none; transition: border-color .15s, box-shadow .15s;
}
.jm-tool-textarea { min-height: 90px; resize: vertical; line-height: 1.6; }
.jm-tool-input:focus, .jm-tool-textarea:focus { border-color: var(--jm-blue); box-shadow: 0 0 0 3px rgba(6,64,163,.08); background: #fff; }
.jm-tool-input::placeholder, .jm-tool-textarea::placeholder { color: #94a3b8; }
select.jm-tool-input { cursor: pointer; }

.jm-tool-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
@media (max-width: 480px) { .jm-tool-row { grid-template-columns: 1fr; } }

.jm-tool-btn { width: 100%; justify-content: center; margin-top: 18px; min-height: 46px; font-size: 14px; font-weight: 700; gap: 8px; }
.jm-tool-btn:disabled { opacity: .6; cursor: not-allowed; }

/* channel segmented control */
.jm-tool-seg { display: flex; gap: 6px; }
.jm-tool-seg button {
    flex: 1; padding: 10px 8px; border: 1px solid #b8c8df; border-radius: 8px; background: var(--jm-soft);
    font: inherit; font-size: 13px; font-weight: 700; color: var(--jm-muted); cursor: pointer;
    display: flex; align-items: center; justify-content: center; gap: 6px; transition: all .15s;
}
.jm-tool-seg button.active { border-color: var(--jm-blue); background: #eef5ff; color: var(--jm-blue); }
.jm-tool-seg button svg { width: 14px; height: 14px; }

.jm-tool-credit { display: flex; align-items: center; justify-content: space-between; border: 1px solid var(--jm-line); border-radius: 8px; background: var(--jm-soft); padding: 12px 16px; font-size: 13px; }
.jm-tool-credit-label { color: var(--jm-muted); font-weight: 600; }
.jm-tool-credit-val { color: var(--jm-ink); font-weight: 800; }
.jm-tool-premium-pill { background: var(--jm-green); color: #fff; font-size: 10px; font-weight: 800; padding: 2px 9px; border-radius: 99px; text-transform: uppercase; letter-spacing: .06em; }

.jm-tool-result { border: 1px solid var(--jm-line); border-radius: 12px; background: #fff; overflow: hidden; min-height: 460px; display: flex; flex-direction: column; animation: jmFadeUp .5s ease both .15s; }
.jm-tool-result-head { display: flex; align-items: center; justify-content: space-between; padding: 14px 22px; border-bottom: 1px solid var(--jm-line); background: var(--jm-soft); }
.jm-tool-result-head > span { font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: .09em; color: var(--jm-muted); }
.jm-tool-copy { display: inline-flex; align-items: center; gap: 6px; border: 1px solid #b8c8df; background: #fff; color: var(--jm-blue); font: inherit; font-size: 12px; font-weight: 700; padding: 6px 12px; border-radius: 7px; cursor: pointer; transition: background .15s; }
.jm-tool-copy:hover { background: #eef5ff; }
.jm-tool-result-body { padding: 26px; flex: 1; }

.jm-tool-empty { display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 340px; text-align: center; gap: 14px; }
.jm-tool-empty-icon { width: 52px; height: 52px; border-radius: 12px; background: var(--jm-soft); border: 1px solid var(--jm-line); display: grid; place-items: center; color: var(--jm-blue); }
.jm-tool-empty h3 { font-size: 17px; color: var(--jm-ink); margin: 0 0 4px; }
.jm-tool-empty p { font-size: 14px; color: var(--jm-muted); margin: 0; max-width: 380px; line-height: 1.6; }

.jm-tool-subject { display: flex; flex-direction: column; gap: 4px; padding: 12px 16px; background: var(--jm-soft); border: 1px solid var(--jm-line); border-radius: 8px; margin-bottom: 16px; }
.jm-tool-subject span { font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: .09em; color: var(--jm-muted); }
.jm-tool-subject strong { font-size: 15px; color: var(--jm-ink); }

.jm-tool-letter { white-space: pre-wrap; font-size: 15px; line-height: 1.75; color: var(--jm-ink); }

.jm-tool-highlights { margin-top: 22px; padding-top: 18px; border-top: 1px solid var(--jm-line); }
.jm-tool-highlights-label { font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: .09em; color: var(--jm-green); margin-bottom: 12px; }
.jm-tool-highlight { display: flex; align-items: flex-start; gap: 8px; font-size: 13px; color: var(--jm-ink); padding: 5px 0; line-height: 1.5; }
.jm-tool-highlight svg { flex-shrink: 0; color: var(--jm-green); margin-top: 2px; }

.jm-tool-variant { margin-top: 14px; padding: 12px 14px; border: 1px dashed var(--jm-line); border-radius: 8px; background: var(--jm-soft); font-size: 14px; line-height: 1.6; color: var(--jm-ink); }
.jm-tool-variant span { display: block; font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: .09em; color: var(--jm-muted); margin-bottom: 5px; }

.jm-tool-cta { display: flex; gap: 10px; margin-top: 22px; padding-top: 18px; border-top: 1px solid var(--jm-line); }
.jm-tool-cta .jm-button { flex: 1; justify-content: center; }

.hidden { display: none !important; }
</style>
