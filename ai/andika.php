<?php
/**
 * JOBMINGTON - Andika AI v27.5 (Mobile Response Fix)
 * ---------------------------------------------------
 * - FIX: Mobile Layout uses '100dvh' to prevent cutoff.
 * - FIX: Sidebars explicitly hidden on small screens.
 * - FEATURE: Real Voice & File logic maintained.
 */

define('JOBMINGTON', true);
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/seeds.php';

Session::start();

// Get real user credits
$userId = Session::userId();
$userCredits = $userId ? getSeedBalance($userId) : 0;
$isLoggedIn = Session::isLoggedIn();

require_once __DIR__ . '/../includes/header.php';
?>

<style>
    /* --- NEO-BRUTALIST ANDIKA THEME --- */
    :root {
        /* Core palette - Neo-Brutalist (matching homepage) */
        --color-ink: #051B3B;
        --color-primary: #0640a3;
        --color-secondary: #FFE135;
        --color-accent: #FF9800;
        --color-canvas: #f3f4f8;
        --color-surface: #ffffff;
        
        /* Legacy mappings for compatibility */
        --bg-app: var(--color-canvas);
        --bg-elevated: var(--color-surface);
        --bg-panel: var(--color-surface);
        --bg-surface: rgba(5, 27, 59, 0.03);
        --bg-surface-hover: rgba(5, 27, 59, 0.06);
        
        /* Text hierarchy */
        --text-primary: var(--color-ink);
        --text-secondary: #475569;
        --text-tertiary: #64748b;
        --text-main: var(--color-ink);
        --text-muted: var(--text-secondary);
        
        /* Brand colors */
        --brand-primary: var(--color-primary);
        --brand-primary-dim: rgba(6, 64, 163, 0.1);
        --brand-purple: var(--color-primary);
        
        /* Semantic colors */
        --semantic-success: #16a34a;
        --semantic-warning: #ca8a04;
        --semantic-error: #dc2626;
        
        /* Neo-Brutalist borders & shadows */
        --border-standard: 2px solid var(--color-ink);
        --border-subtle: 1px solid rgba(5, 27, 59, 0.1);
        --border-default: 2px solid var(--color-ink);
        --border-glass: var(--border-subtle);
        --shadow-standard: 4px 4px 0px 0px var(--color-ink);
        --shadow-hover: 6px 6px 0px 0px var(--color-ink);
        
        /* Chat specific */
        --bubble-user: var(--color-primary);
        --input-bg: var(--color-surface);
    }
    
    /* Premium SVG Icons */
    .icon {
        width: 1em;
        height: 1em;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }
    .icon svg {
        width: 100%;
        height: 100%;
        stroke: currentColor;
        stroke-width: 2;
        stroke-linecap: round;
        stroke-linejoin: round;
        fill: none;
    }
    .icon-sm { font-size: 0.875rem; }
    .icon-md { font-size: 1.125rem; }
    .icon-lg { font-size: 1.5rem; }
    
    /* Premium AI Sphere Icon */
    .ai-icon-sphere {
        width: 42px;
        height: 42px;
        border-radius: 50%;
        background: linear-gradient(135deg, #7c3aed 0%, #db2777 50%, #f59e0b 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        border: 2px solid var(--color-ink);
        box-shadow: 2px 2px 0px 0px var(--color-ink);
        flex-shrink: 0;
        position: relative;
    }
    .ai-icon-sphere svg {
        width: 22px;
        height: 22px;
        fill: white;
        stroke: none;
    }
    
    /* Small variant for bubbles/sidebar */
    .ai-icon-sphere.sm {
        width: 32px;
        height: 32px;
    }
    .ai-icon-sphere.sm svg {
        width: 16px;
        height: 16px;
    }

    /* === PREMIUM TYPOGRAPHY SYSTEM === */
    html, body { 
        height: 100%; 
        margin: 0; 
        background-color: var(--bg-app); 
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; 
        overflow: hidden; 
        color: var(--text-primary);
        font-feature-settings: 'cv02', 'cv03', 'cv04', 'cv11';
        -webkit-font-smoothing: antialiased;
        -moz-osx-font-smoothing: grayscale;
    }
    
    /* Type scale */
    .text-display { font-size: 1.75rem; font-weight: 700; letter-spacing: -0.025em; line-height: 1.2; }
    .text-title { font-size: 1.25rem; font-weight: 600; letter-spacing: -0.02em; line-height: 1.3; }
    .text-body { font-size: 0.9375rem; font-weight: 400; line-height: 1.5; }
    .text-caption { font-size: 0.8125rem; font-weight: 400; color: var(--text-secondary); line-height: 1.4; }
    .text-overline { 
        font-size: 0.6875rem; 
        font-weight: 600; 
        text-transform: uppercase; 
        letter-spacing: 0.1em; 
        color: var(--text-tertiary); 
    }
    
    /* Interactive text - consistent style */
    .text-link {
        color: var(--brand-primary);
        font-weight: 500;
        text-decoration: none;
        transition: opacity 0.15s ease;
    }
    .text-link:hover { opacity: 0.8; }
    
    /* Custom scrollbar styling */
    ::-webkit-scrollbar {
        width: 4px;
        height: 4px;
    }
    ::-webkit-scrollbar-track {
        background: transparent;
    }
    ::-webkit-scrollbar-thumb {
        background: rgba(100, 116, 139, 0.3);
        border-radius: 2px;
        transition: background 0.2s;
    }
    ::-webkit-scrollbar-thumb:hover {
        background: rgba(100, 116, 139, 0.5);
    }
    
    /* Neo-Brutalist backgrounds don't use ambient glows or noise typically, 
       but we can keep a very subtle grain if desired, or remove it for a clean 'paper' look. */
    .noise-overlay { display: none; }
    .ambient-glow { display: none; }
    
    /* Top blend gradient - smooth transition from header */
    .top-blend {
        position: fixed;
        top: 80px;
        left: 0;
        right: 0;
        height: 60px;
        background: linear-gradient(to bottom, 
            var(--color-canvas) 0%,
            transparent 100%);
        pointer-events: none;
        z-index: 5;
    }

    #app-workspace { display: flex; height: calc(100vh - 80px); margin-top: 80px; width: 100vw; position: relative; }

    /* --- SIDEBARS (Neo-Brutalist) --- */
    .glass-panel {
        width: 280px; 
        height: 100%; 
        display: flex; 
        flex-direction: column; 
        flex-shrink: 0;
        background: var(--color-surface);
        border-right: 2px solid var(--color-ink);
        overflow-y: auto; 
        padding-top: 20px; 
        padding-bottom: 100px;
        direction: rtl; /* Move scrollbar to left */
    }
    .glass-panel > * {
        direction: ltr; /* Reset content direction */
    }
    .glass-panel.right-panel { 
        width: 320px; 
        border-right: none; 
        border-left: 2px solid var(--color-ink); 
        padding: 24px 20px 100px 20px;
        direction: ltr; /* Keep scrollbar on right for right panel */
        background: var(--color-canvas); /* Soft wash for card contrast */
    }
    
    /* Scrollbars (Neo-Brutalist Refinement) */
    .glass-panel::-webkit-scrollbar {
        width: 8px;
    }
    .glass-panel::-webkit-scrollbar-track {
        background: var(--color-canvas);
        border-left: 1px solid rgba(5, 27, 59, 0.1);
    }
    .glass-panel::-webkit-scrollbar-thumb {
        background: var(--color-ink);
        border: 2px solid var(--color-canvas);
        border-radius: 0;
    }
    .glass-panel {
        scrollbar-width: thin;
        scrollbar-color: var(--color-ink) var(--color-canvas);
        box-shadow: inset -1px 0 0 0 rgba(5, 27, 59, 0.05); /* Subtle depth */
    }
    .glass-panel.right-panel {
        box-shadow: inset 1px 0 0 0 rgba(5, 27, 59, 0.05);
    }
    
    .panel-header { 
        padding: 0 20px; 
        font-size: 0.75rem; 
        font-weight: 800; 
        text-transform: uppercase; 
        letter-spacing: 0.15em; 
        color: var(--color-ink); 
        margin-top: 24px;
        margin-bottom: 12px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .panel-header::after {
        content: '';
        flex: 1;
        height: 2px;
        background: var(--color-ink);
        opacity: 0.1;
    }
    
    .sidebar-subtext {
        font-size: 0.7rem; 
        color: var(--color-ink); 
        padding: 0 20px; 
        margin-bottom: 20px; 
        opacity: 0.6;
        font-weight: 500;
        line-height: 1.4;
    }
    
    .section-label {
        font-size: 0.6875rem;
        font-weight: 600;
        color: var(--text-tertiary);
        text-transform: uppercase;
        letter-spacing: 0.1em;
        display: block;
        margin-bottom: 12px;
        margin-top: 12px;
        text-align: center;
    }

    .hud-widget {
        background: var(--color-surface);
        border: 2px solid var(--color-ink);
        padding: 18px;
        border-radius: 8px;
        margin-bottom: 20px; /* Increased for breathability */
        box-shadow: 3px 3px 0px 0px var(--color-ink);
        transition: all 0.15s ease;
    }
    .hud-widget:hover {
        transform: translate(-2px, -2px);
        box-shadow: 5px 5px 0px 0px var(--color-ink);
    }
    
    .hud-widget:last-child {
        margin-bottom: 0;
    }
    
    /* Widget Internals (Neo-Brutalist) */
    .strength-bar-bg {
        height: 10px;
        background: #e2e8f0;
        border: 2px solid var(--color-ink);
        border-radius: 2px;
        overflow: hidden;
        margin-bottom: 8px;
        position: relative;
    }
    .strength-bar-fill {
        height: 100%;
        background: var(--color-primary);
        border-right: 2px solid var(--color-ink);
    }
    .strength-label {
        display: flex;
        justify-content: space-between;
        font-size: 0.75rem;
        font-weight: 700;
        color: var(--color-ink);
    }
    
    .quick-actions-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 10px;
        margin-top: 12px;
    }
    .quick-action-btn {
        background: var(--color-surface);
        border: 2px solid var(--color-ink);
        padding: 12px 8px;
        border-radius: 6px;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 8px;
        text-decoration: none;
        color: var(--color-ink);
        font-size: 0.7rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        transition: all 0.15s ease;
        box-shadow: 3px 3px 0px 0px var(--color-ink);
    }
    .quick-action-btn:hover {
        transform: translate(-1.5px, -1.5px);
        box-shadow: 4.5px 4.5px 0px 0px var(--color-ink);
        background: var(--color-secondary);
    }
    .quick-action-btn .icon { font-size: 1.1rem; opacity: 0.9; }
    
    .trend-row {
        display: flex;
        justify-content: space-between;
        padding: 8px 0;
        border-bottom: 2px solid var(--color-ink);
        font-size: 0.8rem;
        font-weight: 700;
        color: var(--color-ink);
    }
    .trend-row:last-child { border-bottom: none; }
    .trend-up { color: var(--semantic-success); }
    .trend-down { color: var(--semantic-error); }
    
    /* Credit Balance */
    .credit-balance {
        font-size: 1.875rem;
        font-weight: 800;
        color: var(--color-ink);
        margin-top: 10px;
        letter-spacing: -0.02em;
        font-family: 'JetBrains Mono', monospace;
    }
    .credit-balance .seeds-label {
        font-size: 0.875rem;
        font-weight: 700;
        color: var(--color-ink);
        opacity: 0.6;
        margin-left: 4px;
    }
    .seeds-link {
        font-size: 0.75rem;
        color: var(--color-primary);
        text-decoration: underline;
        text-underline-offset: 2px;
        display: inline-block;
        margin-top: 8px;
        font-weight: 700;
    }
    .seeds-link:hover { color: var(--color-ink); }
    
    .matches-new {
        font-size: 0.6875rem;
        color: var(--semantic-success);
        margin: 2px 0 4px 0;
        font-weight: 500;
    }

    /* Widget Icons Pop */
    .hud-widget .icon {
        color: var(--color-primary) !important;
        opacity: 0.9 !important;
    }
    
    .hud-widget .text-xs,
    .hud-widget .text-\[10px\] {
        font-size: 0.7rem !important;
        font-weight: 800 !important;
        text-transform: uppercase;
        letter-spacing: 0.12em !important;
        color: var(--color-ink) !important;
        opacity: 0.7 !important;
        margin-bottom: 8px;
    }

    .icon-btn {
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgba(100, 116, 139, 0.1);
        border: 1px solid rgba(100, 116, 139, 0.2);
        border-radius: 10px;
        color: var(--text-muted);
        cursor: pointer;
        transition: 0.2s;
        font-size: 0.9rem;
    }

    .icon-btn:hover {
        background: rgba(255, 255, 255, 0.1);
        color: var(--text-main);
        border-color: rgba(255, 255, 255, 0.2);
    }

    .icon-btn.listening {
        background: rgba(255, 255, 255, 0.15);
        color: #ffffff;
        border-color: #ffffff;
        animation: pulse 0.6s infinite;
    }

    @keyframes pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.7; }
    }

    .send-btn {
        background: #f59e0b;
        border-color: #f59e0b;
        color: #0f172a;
    }

    .send-btn:hover {
        background: #fbbf24;
    }

    .chat-helper-examples {
        padding: 0 16px;
        margin-top: 8px;
        text-align: center;
        max-width: 320px;
        margin-left: auto;
        margin-right: auto;
    }

    .chat-helper-examples .block {
        display: block;
        font-size: 0.75rem;
        color: var(--text-muted);
        opacity: 0.65;
    }

    .nav-item.theme-toggle {
        display: flex;
        align-items: center;
        gap: 12px;
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid var(--border-glass);
        border-radius: 12px;
        padding: 10px 14px;
        margin: 16px 12px;
        cursor: pointer;
        transition: 0.2s;
    }

    .nav-item.theme-toggle:hover {
        background: rgba(255, 255, 255, 0.1);
        border-color: var(--border-glass);
    }

    .theme-toggle-icon {
        width: 20px;
        height: 20px;
        background: rgba(255, 255, 255, 0.08);
        border: 1px solid rgba(255, 255, 255, 0.12);
        border-radius: 6px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.7rem;
        color: #a3a3a3;
    }
    
    .nav-item {
        margin: 4px 12px; 
        padding: 10px 14px; 
        border-radius: 8px; 
        cursor: pointer; 
        transition: all 0.15s ease;
        display: flex; 
        align-items: center; 
        gap: 12px; 
        color: var(--color-ink); 
        font-size: 0.875rem; 
        font-weight: 600;
        border: 2px solid transparent; 
        text-decoration: none;
    }
    .nav-item .icon { color: var(--color-ink); opacity: 0.6; }
    .nav-item:hover { 
        background: var(--color-canvas); 
        border-color: var(--color-ink);
    }
    .nav-item.active { 
        background: var(--color-secondary); 
        color: var(--color-ink); 
        border-color: var(--color-ink);
        box-shadow: 2px 2px 0px 0px var(--color-ink);
    }
    .nav-item.active .icon { color: var(--color-ink) !important; opacity: 1; }
    
    .new-chat-btn {
        margin: 0 12px 16px 12px; 
        padding: 12px; 
        background: var(--color-primary); 
        color: white;
        border-radius: 8px; 
        font-weight: 700; 
        font-size: 0.875rem; 
        display: flex; 
        align-items: center; 
        justify-content: center; 
        gap: 8px; 
        cursor: pointer; 
        transition: all 0.15s ease;
        border: 2px solid var(--color-ink);
        text-transform: uppercase;
        letter-spacing: 0.05em;
        box-shadow: 3px 3px 0px 0px var(--color-ink);
        font-family: 'JetBrains Mono', monospace;
    }
    .new-chat-btn:hover { 
        transform: translate(-1px, -1px);
        box-shadow: 4px 4px 0px 0px var(--color-ink);
    }

    /* --- CENTER STAGE --- */
    #main-chat-view { 
        flex: 1; 
        height: 100%; 
        position: relative; 
        display: flex; 
        flex-direction: column; 
        overflow: hidden; 
    }
    
    /* Header Refinement (Locked-in shell look) */
    #chat-header {
        flex-shrink: 0;
        width: 100%;
        background: var(--color-canvas);
        z-index: 10;
        padding: 24px 20px 16px 20px;
        display: flex;
        flex-direction: column;
        align-items: center;
        position: relative;
        border-bottom: 1px solid rgba(5, 27, 59, 0.05);
    }
    
    /* Scrollable Content Area */
    #scroll-container { 
        flex: 1;
        width: 100%; 
        overflow-y: auto; 
        scroll-behavior: smooth; 
        display: flex; 
        flex-direction: column; 
    }
    
    #chat-thread { 
        width: 100%; 
        max-width: 900px; 
        padding: 20px 20px 40px 20px; 
        margin: 0 auto; 
    }

    #welcome-ui {
        display: flex; 
        flex-direction: column; 
        align-items: center;
        width: 100%; 
        text-align: center;
    }

    .hero-title { 
        font-size: 2.5rem; font-weight: 900; letter-spacing: -0.04em; margin-bottom: 8px; 
        background: var(--hero-gradient); -webkit-background-clip: text; -webkit-text-fill-color: transparent; 
        text-align: center; width: 100%;
    }
    
    /* === NEW CHAT-FIRST DESIGN === */
    
    /* AI Greeting - Compact for header */
    .ai-greeting {
        text-align: center;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 16px;
        margin-bottom: 20px;
    }
    
    .greeting-avatar {
        width: 64px;
        height: 64px;
        border-radius: 8px;
        background: var(--color-primary);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        border: 2px solid var(--color-ink);
        box-shadow: 4px 4px 0px 0px var(--color-ink);
        flex-shrink: 0;
        position: relative;
    }
    
    .greeting-avatar::before {
        content: '';
        position: absolute;
        top: 8px;
        left: 12px;
        width: 14px;
        height: 8px;
        background: rgba(255,255,255,0.5);
        border-radius: 50%;
        filter: blur(3px);
    }
    
    .greeting-avatar .avatar-icon {
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .greeting-avatar .avatar-icon svg {
        width: 100%;
        height: 100%;
        fill: white;
    }
    
    .greeting-content {
        text-align: left;
    }
    
    .greeting-text {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--text-primary);
        margin: 0;
        letter-spacing: -0.025em;
        line-height: 1.2;
    }
    
    .greeting-text .wave {
        display: inline-block;
        animation: wave 2s ease-in-out infinite;
    }
    
    @keyframes wave {
        0%, 100% { transform: rotate(0deg); }
        25% { transform: rotate(20deg); }
        75% { transform: rotate(-10deg); }
    }
    
    .greeting-sub {
        font-size: 0.875rem;
        color: var(--text-secondary);
        margin: 6px 0 0 0;
        font-weight: 400;
    }
    
    .by-brand {
        color: var(--brand-primary);
        font-weight: 600;
    }
    
    .trust-note {
        font-size: 0.75rem;
        color: var(--text-tertiary);
        opacity: 0.6;
        margin-top: 6px;
    }
    .trust-note .icon { color: var(--semantic-success); vertical-align: -2px; }
    
    .input-hint {
        font-size: 0.75rem;
        color: var(--text-tertiary);
        margin: 10px 0 0 0;
        text-align: center;
    }
    
    /* Main Input Area - Fixed in header */
    .main-input-area {
        width: 100%;
        max-width: 600px;
    }
    
    .input-wrapper {
        background: var(--color-surface);
        border: 2px solid var(--color-ink);
        border-radius: 8px;
        padding: 6px 8px 6px 16px;
        display: flex;
        align-items: center;
        gap: 6px;
        transition: all 0.2s ease;
        box-shadow: 4px 4px 0px 0px var(--color-ink);
    }
    
    .input-wrapper:focus-within {
        transform: translate(-2px, -2px);
        box-shadow: 6px 6px 0px 0px var(--color-ink);
    }
    
    .input-wrapper input {
        flex: 1;
        background: transparent;
        border: none;
        color: var(--color-ink);
        font-size: 0.9375rem;
        padding: 10px 4px;
        outline: none;
        font-family: 'Plus Jakarta Sans', sans-serif;
        font-weight: 500;
    }
    
    .input-wrapper input::placeholder {
        color: #94a3b8;
    }
    
    .input-action {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        border: none;
        background: transparent;
        color: var(--text-tertiary);
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.15s ease;
        font-size: 0.95rem;
    }
    
    .input-action:hover {
        background: var(--bg-surface-hover);
        color: var(--text-secondary);
    }
    
    .input-action.listening {
        background: rgba(239, 68, 68, 0.15);
        color: var(--semantic-error);
        animation: pulse 1s infinite;
    }
    
    .send-action {
        width: 42px;
        height: 42px;
        border-radius: 8px;
        border: 2px solid var(--color-ink);
        background: var(--color-primary);
        color: white;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.15s ease;
        font-size: 0.95rem;
        box-shadow: 3px 3px 0px 0px var(--color-ink);
    }
    
    .send-action:hover {
        transform: translate(-2px, -2px);
        box-shadow: 5px 5px 0px 0px var(--color-ink);
    }
    
    .send-action:active {
        transform: translate(0, 0);
        box-shadow: 2px 2px 0px 0px var(--color-ink);
    }
    
    .send-action.is-loading {
        background: var(--color-accent);
    }
    
    .send-action.is-loading:hover {
        background: #e67e00;
    }
    
    /* Quick Action Chips */
    .quick-chips {
        display: flex;
        flex-wrap: wrap;
        justify-content: center;
        gap: 8px;
        margin-top: 12px;
        max-width: 600px;
    }
    
    .chip {
        display: flex;
        align-items: center;
        gap: 6px;
        padding: 10px 16px;
        background: var(--color-surface);
        border: 2px solid var(--color-ink);
        border-radius: 8px;
        color: var(--color-ink);
        font-size: 0.8125rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.15s ease;
        box-shadow: 2px 2px 0px 0px var(--color-ink);
    }
    
    .chip:hover {
        background: var(--color-secondary);
        transform: translate(-1px, -1px);
        box-shadow: 3px 3px 0px 0px var(--color-ink);
    }
    
    .chip .icon {
        opacity: 0.7;
    }
    .chip:hover .icon {
        opacity: 1;
    }
    
    .chip.chip-primary {
        background: linear-gradient(135deg, #8b5cf6 0%, #d946ef 50%, #f59e0b 100%);
        background-size: 200% 200%;
        color: white;
        border: none;
        font-weight: 600;
        box-shadow: 0 4px 12px rgba(139, 92, 246, 0.3);
    }
    .chip.chip-primary:hover {
        background-position: 100% 50%;
        transform: translateY(-1px);
        box-shadow: 0 6px 20px rgba(139, 92, 246, 0.4);
    }
    .chip.chip-primary .icon {
        opacity: 1;
    }
    
    /* Matches Loading/List Styles */
    .matches-loading {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 12px 0;
        font-size: 0.75rem;
        color: var(--text-tertiary);
    }
    .loader-spinner {
        width: 18px;
        height: 18px;
        border: 2px solid var(--border-subtle);
        border-top-color: var(--brand-primary);
        border-radius: 50%;
        animation: spin 0.8s linear infinite;
    }
    @keyframes spin {
        to { transform: rotate(360deg); }
    }
    .matches-top-list {
        margin: 8px 0;
    }
    .match-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 8px 10px;
        border-radius: 4px;
        margin-bottom: 6px;
        background: var(--color-surface);
        border: 2px solid var(--color-ink);
        cursor: pointer;
        transition: all 0.15s ease;
        box-shadow: 2px 2px 0px 0px var(--color-ink);
    }
    .match-item:hover {
        transform: translate(-1px, -1px);
        box-shadow: 3px 3px 0px 0px var(--color-ink);
        background: var(--color-canvas);
    }
    .match-title {
        font-size: 0.7rem;
        font-weight: 600;
        color: var(--color-ink);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        max-width: 140px;
    }
    .match-score {
        font-size: 0.65rem;
        font-weight: 700;
        padding: 2px 6px;
        border-radius: 10px;
        min-width: 36px;
        text-align: center;
    }
    .match-score.high {
        background: rgba(34, 197, 94, 0.15);
        color: #22c55e;
    }
    .match-score.medium {
        background: rgba(245, 158, 11, 0.15);
        color: #f59e0b;
    }
    .match-score.low {
        background: rgba(148, 163, 184, 0.15);
        color: var(--text-secondary);
    }
    
    /* Chat Area - Full height scrollable */
    .chat-area {
        width: 100%;
        max-width: 650px;
        margin: 0 auto;
        padding: 0;
    }
    
    .chat-placeholder {
        background: var(--color-surface);
        border: 2px dashed var(--color-ink);
        border-radius: 12px;
        padding: 50px 20px;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 12px;
        color: var(--color-ink);
        opacity: 0.6;
        font-size: 0.85rem;
        box-shadow: 4px 4px 0px 0px var(--color-ink);
        margin: 20px 0;
    }
    
    .chat-placeholder i {
        font-size: 2rem;
        color: var(--color-primary);
        margin-bottom: 4px;
    }
    
    .chat-placeholder.hidden {
        display: none;
    }
    
    /* Chat messages - no container, flows naturally */
    .chat-messages {
        display: none;
    }
    
    .chat-messages.active {
        display: block;
    }
    
    .chat-messages .msg-user,
    .chat-messages .msg-ai {
        animation: fadeIn 0.3s ease;
    }
    
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(8px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    .chat-messages .msg-user {
        background: var(--color-surface);
        padding: 14px 18px;
        border-radius: 8px;
        margin-left: 15%;
        margin-bottom: 24px;
        font-size: 0.9375rem;
        line-height: 1.6;
        border: 2px solid var(--color-ink);
        color: var(--color-ink);
        box-shadow: 4px 4px 0px 0px var(--color-ink);
        font-weight: 500;
        position: relative;
    }
    
    .chat-messages .msg-ai {
        display: flex;
        gap: 12px;
        margin-bottom: 16px;
        padding-right: 20%;
    }
    
    .chat-messages .msg-ai .ai-icon {
        width: 40px;
        height: 40px;
        border-radius: 8px;
        background: var(--color-primary);
        flex-shrink: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 0.9rem;
        border: 2px solid var(--color-ink);
        box-shadow: 3px 3px 0px 0px var(--color-ink);
        position: relative;
    }
    
    .chat-messages .msg-ai .ai-icon::before {
        content: '';
        position: absolute;
        top: 5px;
        left: 8px;
        width: 10px;
        height: 6px;
        background: rgba(255,255,255,0.5);
        border-radius: 50%;
        filter: blur(2px);
    }
    
    .chat-messages .msg-ai .ai-icon .icon {
        width: 18px;
        height: 18px;
        display: flex;
        align-items: center;
        justify-content: center;
        filter: drop-shadow(0 1px 2px rgba(0,0,0,0.3));
    }
    
    .chat-messages .msg-ai .ai-icon .icon svg {
        width: 100%;
        height: 100%;
        fill: white;
    }
    
    @keyframes andika-globe-spin {
        0%, 100% { background-position: 0% 50%; }
        50% { background-position: 100% 50%; }
    }
    
    .chat-messages .msg-ai .ai-text {
        background: transparent;
        padding: 0;
        font-size: 1rem;
        line-height: 1.75;
        color: var(--text-primary);
        position: relative;
        flex: 1;
        text-align: left;
    }
    
    .chat-messages .msg-ai .ai-content {
        display: block;
    }
    
    /* ChatGPT-style Markdown formatting */
    .chat-messages .msg-ai .ai-text p {
        margin: 0 0 16px 0;
        text-align: left;
        line-height: 1.75;
    }
    .chat-messages .msg-ai .ai-text p:last-child { margin-bottom: 0; }
    
    .chat-messages .msg-ai .ai-text strong {
        color: var(--text-primary);
        font-weight: 600;
    }
    
    /* Lists - ChatGPT style */
    .chat-messages .msg-ai .ai-text ul,
    .chat-messages .msg-ai .ai-text ol {
        margin: 16px 0;
        padding-left: 0;
        list-style: none;
    }
    
    .chat-messages .msg-ai .ai-text ul li,
    .chat-messages .msg-ai .ai-text ol li {
        position: relative;
        padding-left: 24px;
        margin-bottom: 12px;
        line-height: 1.7;
    }
    
    .chat-messages .msg-ai .ai-text ul li::before {
        content: '';
        position: absolute;
        left: 8px;
        top: 10px;
        width: 6px;
        height: 6px;
        background: var(--brand-primary);
        border-radius: 50%;
    }
    
    .chat-messages .msg-ai .ai-text ol {
        counter-reset: item;
    }
    
    .chat-messages .msg-ai .ai-text ol li {
        counter-increment: item;
    }
    
    .chat-messages .msg-ai .ai-text ol li::before {
        content: counter(item) '.';
        position: absolute;
        left: 0;
        top: 0;
        font-weight: 600;
        color: var(--brand-primary);
    }
    
    /* Headers */
    .chat-messages .msg-ai .ai-text h1,
    .chat-messages .msg-ai .ai-text h2,
    .chat-messages .msg-ai .ai-text h3 {
        font-weight: 700;
        color: var(--text-primary);
        text-align: left;
        margin: 24px 0 12px 0;
        line-height: 1.4;
    }
    .chat-messages .msg-ai .ai-text h1:first-child,
    .chat-messages .msg-ai .ai-text h2:first-child,
    .chat-messages .msg-ai .ai-text h3:first-child { margin-top: 0; }
    .chat-messages .msg-ai .ai-text h1 { font-size: 1.375rem; }
    .chat-messages .msg-ai .ai-text h2 { font-size: 1.25rem; }
    .chat-messages .msg-ai .ai-text h3 { font-size: 1.125rem; }
    
    /* Code */
    .chat-messages .msg-ai .ai-text code {
        background: #f1f5f9;
        padding: 3px 8px;
        border-radius: 4px;
        border: 1px solid var(--color-ink);
        font-family: 'JetBrains Mono', monospace;
        font-size: 0.875em;
        color: var(--color-primary);
        font-weight: 600;
    }
    
    .chat-messages .msg-ai .ai-text pre {
        background: #f8fafc;
        padding: 20px;
        border-radius: 8px;
        overflow-x: auto;
        margin: 20px 0;
        border: 2px solid var(--color-ink);
        box-shadow: 4px 4px 0px 0px var(--color-ink);
    }
    .chat-messages .msg-ai .ai-text pre code {
        background: none;
        padding: 0;
        color: var(--color-ink);
        border: none;
    }
    
    /* Blockquote */
    .chat-messages .msg-ai .ai-text blockquote {
        border-left: 3px solid var(--brand-primary);
        margin: 16px 0;
        padding: 8px 0 8px 16px;
        color: var(--text-secondary);
        font-style: italic;
    }
    
    /* Copy button at bottom */
    .copy-btn {
        background: var(--color-surface);
        border: 2px solid var(--color-ink);
        color: var(--color-ink);
        padding: 8px 14px;
        border-radius: 4px;
        font-size: 0.8125rem;
        cursor: pointer;
        opacity: 0;
        transition: all 0.2s;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        margin-top: 16px;
        box-shadow: 2px 2px 0px 0px var(--color-ink);
        font-family: 'JetBrains Mono', monospace;
        font-weight: 600;
        text-transform: uppercase;
    }
    .chat-messages .msg-ai:hover .copy-btn { opacity: 1; }
    .copy-btn:hover { 
        background: var(--color-secondary); 
        transform: translate(-1px, -1px);
        box-shadow: 3px 3px 0px 0px var(--color-ink);
    }
    .copy-btn.copied { 
        background: var(--semantic-success); 
        color: white; 
        border-color: var(--color-ink); 
    }
    
    /* Typing indicator animation */
    .typing-dots span {
        animation: typingBounce 1.4s infinite ease-in-out;
        display: inline-block;
        font-size: 1.5rem;
        line-height: 0.5;
    }
    .typing-dots span:nth-child(1) { animation-delay: 0s; }
    .typing-dots span:nth-child(2) { animation-delay: 0.2s; }
    .typing-dots span:nth-child(3) { animation-delay: 0.4s; }
    
    @keyframes typingBounce {
        0%, 60%, 100% { transform: translateY(0); opacity: 0.4; }
        30% { transform: translateY(-4px); opacity: 1; }
    }
    
    /* Section Divider */
    .section-divider {
        display: flex;
        align-items: center;
        gap: 16px;
        margin-bottom: 24px;
        padding: 0 20px;
        max-width: 600px;
        margin-left: auto;
        margin-right: auto;
    }
    
    .section-divider::before,
    .section-divider::after {
        content: '';
        flex: 1;
        height: 1px;
        background: var(--border-subtle);
    }
    
    .section-divider span {
        font-size: 0.6875rem;
        color: var(--text-tertiary);
        text-transform: uppercase;
        letter-spacing: 0.1em;
        font-weight: 500;
    }
    
    /* Tools Grid */
    .tools-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(200px, 280px));
        gap: 14px;
        width: fit-content;
        margin: 0 auto;
        padding: 0 20px 24px 20px;
        justify-content: center;
    }
    
    .tool-card {
        background: var(--color-surface);
        border: 2px solid var(--color-ink);
        border-radius: 8px;
        padding: 16px;
        display: flex;
        align-items: center;
        gap: 12px;
        cursor: pointer;
        transition: all 0.2s ease;
        box-shadow: 4px 4px 0px 0px var(--color-ink);
    }
    
    .tool-card:hover {
        transform: translate(-2px, -2px);
        box-shadow: 6px 6px 0px 0px var(--color-ink);
        background: var(--color-secondary);
    }
    
    .tool-icon {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        font-size: 1rem;
        /* Default monochrome */
        background: var(--bg-surface-hover);
        color: var(--text-secondary);
    }
    
    /* Semantic colors only for tool icons */
    .tool-icon.fire {
        background: rgba(239, 68, 68, 0.1);
        color: var(--semantic-error);
    }
    
    .tool-icon.green {
        background: rgba(16, 185, 129, 0.1);
        color: var(--semantic-success);
    }
    
    .tool-icon.amber {
        background: rgba(245, 158, 11, 0.1);
        color: var(--semantic-warning);
    }
    
    .tool-icon.blue {
        background: var(--brand-primary-dim);
        color: var(--brand-primary);
    }
    
    .tool-info {
        flex: 1;
        min-width: 0;
    }
    
    .tool-info h4 {
        font-size: 0.875rem;
        font-weight: 600;
        color: var(--text-primary);
        margin: 0 0 2px 0;
        letter-spacing: -0.01em;
    }
    
    .tool-info p {
        font-size: 0.75rem;
        color: var(--text-tertiary);
        margin: 0;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    
    .tool-cost {
        font-size: 0.6875rem;
        font-weight: 600;
        color: var(--text-secondary);
        background: var(--bg-surface);
        padding: 4px 10px;
        border-radius: 6px;
        white-space: nowrap;
        letter-spacing: 0.02em;
    }
    
    .tool-cost.free {
        color: var(--semantic-success);
        background: rgba(16, 185, 129, 0.1);
    }
    
    /* =========================================
       TOAST NOTIFICATIONS
       ========================================= */
    .toast-container {
        position: fixed;
        top: 100px;
        right: 24px;
        z-index: 9999;
        display: flex;
        flex-direction: column;
        gap: 12px;
        pointer-events: none;
    }
    
    .toast {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        padding: 16px 20px;
        border-radius: 8px;
        background: var(--color-surface);
        border: 2px solid var(--color-ink);
        box-shadow: 6px 6px 0px 0px var(--color-ink);
        min-width: 300px;
        max-width: 420px;
        pointer-events: auto;
        animation: toastSlideIn 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        transform-origin: top right;
    }
    
    .toast.hiding {
        animation: toastSlideOut 0.25s ease-in forwards;
    }
    
    @keyframes toastSlideIn {
        from { opacity: 0; transform: translateX(100%) scale(0.9); }
        to { opacity: 1; transform: translateX(0) scale(1); }
    }
    
    @keyframes toastSlideOut {
        from { opacity: 1; transform: translateX(0) scale(1); }
        to { opacity: 0; transform: translateX(100%) scale(0.9); }
    }
    
    .toast-icon {
        width: 24px;
        height: 24px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }
    .toast-icon .icon { font-size: 0.875rem; }
    
    .toast.toast-success .toast-icon { background: rgba(34, 197, 94, 0.15); color: #22c55e; }
    .toast.toast-error .toast-icon { background: rgba(239, 68, 68, 0.15); color: #ef4444; }
    .toast.toast-warning .toast-icon { background: rgba(245, 158, 11, 0.15); color: #f59e0b; }
    .toast.toast-info .toast-icon { background: rgba(59, 130, 246, 0.15); color: #3b82f6; }
    
    .toast-content { flex: 1; }
    .toast-title {
        font-size: 0.875rem;
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 2px;
    }
    .toast-message {
        font-size: 0.8125rem;
        color: var(--text-secondary);
        line-height: 1.5;
    }
    
    .toast-close {
        background: none;
        border: none;
        color: var(--text-tertiary);
        cursor: pointer;
        padding: 4px;
        margin: -4px -4px -4px 8px;
        border-radius: 6px;
        transition: all 0.15s ease;
    }
    .toast-close:hover {
        background: rgba(255, 255, 255, 0.1);
        color: var(--text-primary);
    }
    .toast-close .icon { font-size: 1rem; }
    
    /* Progress bar for auto-dismiss */
    .toast-progress {
        position: absolute;
        bottom: 0;
        left: 0;
        height: 3px;
        border-radius: 0 0 12px 12px;
        animation: toastProgress linear forwards;
    }
    .toast.toast-success .toast-progress { background: #22c55e; }
    .toast.toast-error .toast-progress { background: #ef4444; }
    .toast.toast-warning .toast-progress { background: #f59e0b; }
    .toast.toast-info .toast-progress { background: #3b82f6; }
    
    @keyframes toastProgress {
        from { width: 100%; }
        to { width: 0%; }
    }
    
    .toast { position: relative; overflow: hidden; }
    
    /* =========================================
       MODAL DIALOGS
       ========================================= */
    .modal-overlay {
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.7);
        backdrop-filter: blur(4px);
        -webkit-backdrop-filter: blur(4px);
        z-index: 10000;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
        opacity: 0;
        visibility: hidden;
        transition: all 0.2s ease;
    }
    .modal-overlay.active {
        opacity: 1;
        visibility: visible;
    }
    
    .modal-dialog {
        background: var(--color-surface);
        border: 3px solid var(--color-ink);
        border-radius: 8px;
        box-shadow: 12px 12px 0px 0px var(--color-ink);
        max-width: 420px;
        width: 100%;
        transform: scale(0.95) translateY(-10px);
        transition: transform 0.25s cubic-bezier(0.16, 1, 0.3, 1);
    }
    .modal-overlay.active .modal-dialog {
        transform: scale(1) translateY(0);
    }
    
    .modal-header {
        padding: 24px 24px 0 24px;
        display: flex;
        align-items: flex-start;
        gap: 16px;
    }
    
    .modal-icon {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }
    .modal-icon .icon { font-size: 1.5rem; }
    
    .modal-icon.confirm { background: rgba(59, 130, 246, 0.15); color: #3b82f6; }
    .modal-icon.warning { background: rgba(245, 158, 11, 0.15); color: #f59e0b; }
    .modal-icon.danger { background: rgba(239, 68, 68, 0.15); color: #ef4444; }
    .modal-icon.success { background: rgba(34, 197, 94, 0.15); color: #22c55e; }
    
    .modal-title {
        font-size: 1.125rem;
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 8px;
    }
    .modal-message {
        font-size: 0.875rem;
        color: var(--text-secondary);
        line-height: 1.6;
    }
    
    .modal-body {
        padding: 20px 24px;
    }
    
    .modal-footer {
        padding: 0 24px 24px 24px;
        display: flex;
        gap: 12px;
        justify-content: flex-end;
    }
    
    .modal-btn {
        padding: 10px 20px;
        border-radius: 10px;
        font-size: 0.875rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.15s ease;
        border: none;
    }
    .modal-btn-secondary {
        background: rgba(255, 255, 255, 0.08);
        color: var(--text-secondary);
    }
    .modal-btn-secondary:hover {
        background: rgba(255, 255, 255, 0.12);
        color: var(--text-primary);
    }
    .modal-btn-primary {
        background: var(--brand-primary);
        color: #000;
    }
    .modal-btn-primary:hover {
        filter: brightness(1.1);
    }
    .modal-btn-danger {
        background: #ef4444;
        color: white;
    }
    .modal-btn-danger:hover {
        background: #dc2626;
    }
    
    .powered-by {
        text-align: center;
        font-size: 0.65rem;
        color: var(--color-ink);
        opacity: 0.5;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.1em;
        padding: 24px 0;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
    }
    
    .powered-by i {
        margin-right: 4px;
    }
    
    /* =========================================
       DESKTOP LAYOUT: Side-by-side Chat + Tools
       ========================================= */
    
    /* Section labels - hidden by default on mobile */
    .section-label {
        display: none;
        font-size: 0.65rem;
        font-weight: 700;
        color: var(--text-tertiary);
        text-transform: uppercase;
        letter-spacing: 0.1em;
        margin-bottom: 12px;
    }
    
    /* Tools section wrapper */
    .tools-section {
        display: contents; /* On mobile, let tools-grid styles apply */
    }
    
    @media (min-width: 1025px) {
        #welcome-ui {
            display: flex;
            flex-direction: row;
            gap: 40px;
            align-items: flex-start;
            justify-content: center;
            padding: 20px;
            max-width: 900px;
            margin: 0 auto;
        }
        
        /* Show section labels on desktop */
        .section-label {
            display: block;
        }
        
        /* Chat area on left */
        .chat-area {
            flex: 1;
            min-width: 280px;
            max-width: 400px;
        }
        
        .chat-placeholder {
            padding: 40px 24px;
        }
        
        /* Hide the horizontal divider on desktop */
        .section-divider {
            display: none;
        }
        
        /* Tools section container on right */
        .tools-section {
            display: block;
            flex: 1;
            max-width: 400px;
        }
        
        /* Tools grid inside the tools section */
        .tools-grid {
            grid-template-columns: 1fr;
            gap: 12px;
            padding: 0;
            margin: 0;
            width: 100%;
        }
        
        /* Powered by below the flex container */
        .powered-by {
            position: absolute;
            bottom: 16px;
            left: 50%;
            transform: translateX(-50%);
        }
        
        #scroll-container {
            position: relative;
        }
        
        #chat-thread {
            padding-bottom: 60px;
        }
        
        /* Desktop: Hide tools when chat is active */
        #welcome-ui.chat-active {
            justify-content: flex-start;
        }
        
        #welcome-ui.chat-active .tools-section,
        #welcome-ui.chat-active .powered-by {
            display: none;
        }
        
        #welcome-ui.chat-active .chat-area {
            max-width: 100%;
            min-width: 100%;
        }
        
        #welcome-ui.chat-active .section-label {
            display: none;
        }
    }

    /* Legacy starter-card styles kept for compatibility */
    .starter-grid { 
        display: grid; grid-template-columns: 1fr 1fr; gap: 16px; 
        width: 100%; max-width: 680px; 
        margin: 0 auto;
        padding-bottom: 20px;
    }
    
    .starter-card { 
        background: var(--bg-panel); border: 1px solid var(--border-glass); 
        padding: 24px; border-radius: 16px; cursor: pointer; transition: 0.2s; position: relative; 
        text-align: left; display: flex; flex-direction: column; justify-content: center;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
    }
    .starter-card:hover { border-color: rgba(255, 255, 255, 0.15); transform: translateY(-2px); box-shadow: 0 10px 30px -5px rgba(0,0,0,0.4); }
    
    .starter-card:first-child::after {
        content: "Start here";
        position: absolute;
        top: -8px;
        right: 20px;
        font-size: 0.65rem;
        font-weight: 700;
        color: #ffffff;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        background: var(--bg-app);
        padding: 2px 8px;
        border-radius: 6px;
        border: 1px solid rgba(255, 255, 255, 0.2);
    }
    
    .cost-badge { 
        position: absolute; top: 16px; right: 16px; font-size: 0.6rem; font-weight: 800;
        text-transform: uppercase; letter-spacing: 0.05em;
        background: rgba(255, 255, 255, 0.06); 
        padding: 4px 10px;
        border-radius: 8px;
        color: #a3a3a3;
        border: 1px solid rgba(255, 255, 255, 0.1); 
    }

    /* --- INPUT DOCK (legacy, kept for fallback) --- */
    #console-dock { display: none; }
    .glass-pill { background: #1e293b; border: 1px solid var(--border-glass); border-radius: 24px; padding: 14px 16px; display: flex; align-items: center; box-shadow: 0 15px 40px -10px rgba(0,0,0,0.6); transition: 0.3s; }
    .glass-pill.listening { border-color: #f59e0b; box-shadow: 0 0 30px rgba(245, 158, 11, 0.2); }
    
    .console-input { flex: 1; background: transparent; border: none; color: var(--text-main); padding: 10px; font-size: 1rem; outline: none; }
    .icon-btn { width: 36px; height: 36px; border-radius: 10px; border: none; background: transparent; color: var(--text-muted); cursor: pointer; display: flex; align-items: center; justify-content: center; }
    .icon-btn:hover { background: var(--accent-glow); color: var(--text-main); }
    .icon-btn.listening { color: #ef4444; animation: pulse 1.5s infinite; }
    @keyframes pulse { 0% { opacity: 1; } 50% { opacity: 0.5; } 100% { opacity: 1; } }
    
    .send-btn { background: var(--text-main); color: var(--bg-app); margin-left: 8px; }

    /* --- BUBBLES --- */
    .bubble-user { background: var(--bubble-user); padding: 14px 20px; border-radius: 18px 18px 4px 18px; max-width: 85%; margin-left: auto; margin-bottom: 24px; border: 1px solid var(--border-glass); }
    .bubble-ai { width: 100%; margin-bottom: 32px; display: flex; gap: 16px; font-size: 1rem; line-height: 1.7; }
    .ai-avatar { width: 32px; height: 32px; border-radius: 8px; background: linear-gradient(135deg, #303030, #1a1a1a); flex-shrink: 0; display: flex; align-items: center; justify-content: center; color: white; border: 1px solid rgba(255,255,255,0.1); }

    /* --- HUD --- */
    .hud-widget { background: var(--bg-panel); border: 1px solid var(--border-glass); border-radius: 20px; padding: 20px; margin-bottom: 16px; }
    .credit-balance { font-size: 2rem; font-weight: 800; color: var(--text-main); letter-spacing: -0.02em; }
    .trend-row { display: flex; justify-content: space-between; margin-bottom: 10px; font-size: 0.8125rem; color: var(--text-secondary); }
    .trend-up { color: var(--semantic-success); font-weight: 600; }
    
    /* Profile Strength */
    .profile-strength { margin-top: 12px; }
    .strength-bar-bg {
        width: 100%;
        height: 6px;
        background: var(--bg-surface-hover);
        border-radius: 3px;
        overflow: hidden;
    }
    .strength-bar-fill {
        height: 100%;
        background: var(--brand-primary);
        border-radius: 3px;
        transition: width 0.5s ease;
    }
    .strength-label {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: 8px;
        font-size: 0.75rem;
    }
    .strength-label span:first-child { color: var(--text-tertiary); }
    .strength-label span:last-child { color: var(--text-primary); font-weight: 600; }
    
    /* Job Matches */
    .job-matches-widget {
        display: flex;
        align-items: center;
        gap: 16px;
    }
    .matches-count {
        font-size: 2.25rem;
        font-weight: 700;
        color: var(--text-primary);
        line-height: 1;
        letter-spacing: -0.02em;
    }
    .matches-info {
        flex: 1;
    }
    .matches-info p {
        font-size: 0.8125rem;
        color: var(--text-secondary);
        margin: 0 0 4px 0;
    }
    .matches-link {
        font-size: 0.75rem;
        color: var(--brand-primary);
        text-decoration: none;
        font-weight: 500;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        transition: opacity 0.15s ease;
    }
    .matches-link:hover { opacity: 0.8; }
    
    /* Quick Actions */
    .quick-actions-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 8px;
        margin-top: 12px;
    }
    .quick-action-btn {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 6px;
        padding: 14px 10px;
        background: var(--bg-surface);
        border: 1px solid var(--border-subtle);
        border-radius: 10px;
        cursor: pointer;
        transition: all 0.15s ease;
        text-decoration: none;
    }
    .quick-action-btn:hover {
        background: var(--bg-surface-hover);
        border-color: var(--border-default);
    }
    .quick-action-btn .icon {
        font-size: 1.1rem;
        color: var(--text-tertiary);
        transition: color 0.15s ease;
    }
    .quick-action-btn:hover .icon { color: var(--text-secondary); }
    .quick-action-btn span:last-child {
        font-size: 0.6875rem;
        font-weight: 500;
        color: var(--text-secondary);
    }

    /* =========================================
       MOBILE RESPONSE ENGINE
       ========================================= */
    @media (max-width: 1024px) {
        /* 1. Dynamic Viewport Height */
        #app-workspace { 
            height: calc(100dvh - 60px);
            margin-top: 60px;
            overflow: hidden;
        }
        
        /* Adjust top blend for mobile header */
        .top-blend {
            top: 60px;
            height: 80px;
        }
        
        /* 2. Hide Sidebars */
        .glass-panel, .glass-panel.right-panel { display: none !important; }
        
        /* 3. Main View - flex column so header + scroll work */
        #main-chat-view { 
            width: 100%; 
            min-width: 100%; 
            display: flex;
            flex-direction: column;
            height: 100%;
            overflow: hidden;
        }
        
        /* 4. Header - more compact on mobile */
        #chat-header {
            padding: 16px 16px 12px 16px;
        }
        
        /* 5. Greeting - stacked on mobile */
        .ai-greeting {
            flex-direction: column;
            gap: 8px;
            margin-bottom: 12px;
        }
        .greeting-avatar { width: 48px; height: 48px; }
        .greeting-avatar .avatar-icon { width: 22px; height: 22px; }
        .greeting-content { text-align: center; }
        .greeting-text { font-size: 1.2rem; }
        .greeting-sub { font-size: 0.85rem; }
        
        /* 6. Input area */
        .main-input-area { width: 100%; }
        .input-wrapper { padding: 4px 6px 4px 12px; }
        .input-action, .send-action { width: 34px; height: 34px; border-radius: 10px; }
        .input-wrapper input { font-size: 0.9rem; padding: 6px 4px; }
        .input-hint { display: none; } /* Hide on mobile - too cramped */
        .trust-note { font-size: 0.65rem; margin-top: 4px; }
        
        /* 7. Quick chips - horizontal scroll */
        .quick-chips {
            flex-wrap: nowrap;
            overflow-x: auto;
            justify-content: flex-start;
            width: calc(100% + 32px);
            margin-left: -16px;
            padding: 0 16px;
            margin-top: 10px;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: none;
        }
        .quick-chips::-webkit-scrollbar { display: none; }
        .chip { flex-shrink: 0; padding: 8px 12px; font-size: 0.75rem; }
        
        /* 8. Chat area - must scroll properly */
        .chat-area { padding: 0; }
        .chat-placeholder { padding: 30px 16px; }
        
        /* 9. Scroll container - takes remaining height */
        #scroll-container {
            flex: 1;
            overflow-y: auto;
            min-height: 0; /* Important for flex scroll */
        }
        
        /* 10. Chat thread */
        #chat-thread { padding: 12px 16px 100px 16px; }
        
        /* 11. Tools grid - 2 columns, compact on mobile */
        .tools-section { display: contents; }
        .tools-grid {
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            padding: 0 0 16px 0;
            width: 100%;
        }
        .tool-card { padding: 10px; flex-direction: column; text-align: center; gap: 6px; }
        .tool-icon { width: 32px; height: 32px; font-size: 0.8rem; margin: 0 auto; }
        .tool-info h4 { font-size: 0.75rem; }
        .tool-info p { display: none; }
        .tool-cost { font-size: 0.6rem; padding: 2px 6px; }
        
        /* 12. Section divider */
        .section-divider { padding: 0; margin-bottom: 12px; margin-top: 8px; }
        
        /* 13. Hide tools when chat is active on mobile */
        #welcome-ui.chat-active .section-divider,
        #welcome-ui.chat-active .tools-section,
        #welcome-ui.chat-active .powered-by {
            display: none;
        }
        
        /* 14. Legacy styles */
        .hero-title { font-size: 1.8rem; margin-top: 10px; }
        .starter-grid { grid-template-columns: 1fr; gap: 12px; }
        .seeds-hint { display: none; }
    }
    
    /* === CHAT HISTORY STYLES === */
    .history-list {
        padding: 0 8px;
        flex: 1;
        min-height: 200px;
        overflow-y: auto;
    }
    
    .history-item {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 10px 12px;
        border-radius: 10px;
        cursor: pointer;
        transition: 0.2s;
        margin-bottom: 4px;
    }
    
    .history-item:hover {
        background: var(--accent-glow);
    }
    
    .history-item.active {
        background: rgba(100, 116, 139, 0.15);
        border: 1px solid rgba(100, 116, 139, 0.3);
    }
    
    .history-icon {
        width: 28px;
        height: 28px;
        border-radius: 8px;
        background: rgba(148, 163, 184, 0.1);
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        font-size: 0.7rem;
        color: var(--text-muted);
    }
    
    .history-content {
        flex: 1;
        min-width: 0;
    }
    
    .history-title {
        font-size: 0.8rem;
        font-weight: 600;
        color: var(--text-main);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        margin: 0;
    }
    
    .history-time {
        font-size: 0.65rem;
        color: var(--text-muted);
        opacity: 0.6;
    }
    
    .history-delete {
        opacity: 0;
        width: 24px;
        height: 24px;
        border-radius: 6px;
        background: transparent;
        border: none;
        color: var(--text-muted);
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: 0.2s;
    }
    
    .history-item:hover .history-delete {
        opacity: 1;
    }
    
    .history-delete:hover {
        background: rgba(239, 68, 68, 0.2);
        color: #f87171;
    }
    
    .history-empty {
        text-align: center;
        padding: 24px 16px;
        color: var(--text-muted);
        font-size: 0.75rem;
        display: flex;
        flex-direction: column;
        align-items: center;
    }
    
    .history-empty .icon {
        display: block;
        font-size: 1.5rem;
        margin-bottom: 8px;
    }
    
    /* === MOBILE HISTORY DRAWER === */
    .mobile-header-actions {
        display: none;
    }
    
    .mobile-drawer-overlay {
        display: none;
        position: fixed;
        top: 60px; /* Account for site header on mobile */
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.6);
        z-index: 999;
        backdrop-filter: blur(4px);
    }
    
    .mobile-drawer-overlay.active {
        display: block;
    }
    
    .mobile-drawer {
        position: fixed;
        top: 60px; /* Account for site header on mobile */
        left: 0;
        width: 85%;
        max-width: 320px;
        height: calc(100% - 60px);
        background: var(--bg-app);
        z-index: 1000;
        transform: translateX(-100%);
        transition: transform 0.3s ease;
        display: flex;
        flex-direction: column;
        border-right: 1px solid var(--border-glass);
    }
    
    .mobile-drawer.active {
        transform: translateX(0);
    }
    
    .drawer-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 16px;
        border-bottom: 1px solid var(--border-glass);
    }
    
    .drawer-header h3 {
        font-size: 1rem;
        font-weight: 700;
        color: var(--text-main);
        margin: 0;
    }
    
    .drawer-close {
        width: 36px;
        height: 36px;
        border-radius: 10px;
        background: rgba(148, 163, 184, 0.1);
        border: none;
        color: var(--text-muted);
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1rem;
    }
    
    .drawer-close:hover {
        background: rgba(148, 163, 184, 0.2);
        color: var(--text-main);
    }
    
    .drawer-content {
        flex: 1;
        overflow-y: auto;
        padding: 12px;
    }
    
    .drawer-new-chat {
        margin: 12px;
        padding: 14px;
        background: #fbbf24;
        color: #000;
        border: none;
        border-radius: 12px;
        font-weight: 700;
        font-size: 0.9rem;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
    }
    
    .drawer-new-chat:hover {
        background: #f59e0b;
    }
    
    @media (max-width: 1024px) {
        .mobile-header-actions {
            display: flex;
            align-items: center;
            gap: 8px;
            position: absolute;
            top: 12px;
            left: 12px;
        }
        
        .mobile-action-btn {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            background: rgba(148, 163, 184, 0.1);
            border: 1px solid var(--border-glass);
            color: var(--text-muted);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.95rem;
            transition: 0.2s;
        }
        
        .mobile-action-btn:hover {
            background: rgba(100, 116, 139, 0.2);
            color: var(--text-main);
        }
        
        .mobile-action-btn .badge {
            position: absolute;
            top: -4px;
            right: -4px;
            width: 18px;
            height: 18px;
            background: #fbbf24;
            color: #000;
            font-size: 0.6rem;
            font-weight: 700;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        #chat-header {
            position: relative;
        }
    }
</style>

<!-- Noise Texture Overlay -->
<div class="noise-overlay"></div>

<!-- Top Blend Gradient -->
<div class="top-blend"></div>

<div class="ambient-glow"></div>

<!-- Mobile History Drawer -->
<div class="mobile-drawer-overlay" id="drawer-overlay" onclick="Andika.closeDrawer()"></div>
<div class="mobile-drawer" id="mobile-drawer">
    <div class="drawer-header">
        <h3><span class="icon" style="margin-right: 8px;"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></span> Chat History</h3>
        <button class="drawer-close" onclick="Andika.closeDrawer()">
            <span class="icon"><svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></span>
        </button>
    </div>
    <button class="drawer-new-chat" onclick="Andika.newChat(); Andika.closeDrawer();">
        <span class="icon icon-sm"><svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg></span> New Chat
    </button>
    <div class="drawer-content" id="mobile-history-list">
        <div class="history-empty" id="mobile-history-empty">
            <span class="icon icon-lg"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></span>
            No chat history yet
        </div>
    </div>
</div>

<!-- Toast Container -->
<div class="toast-container" id="toast-container"></div>

<!-- Modal Overlay -->
<div class="modal-overlay" id="modal-overlay">
    <div class="modal-dialog" id="modal-dialog">
        <div class="modal-header">
            <div class="modal-icon confirm" id="modal-icon">
                <span class="icon"><svg viewBox="0 0 24 24"><path d="M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10z"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg></span>
            </div>
            <div>
                <h3 class="modal-title" id="modal-title">Confirm Action</h3>
                <p class="modal-message" id="modal-message">Are you sure you want to proceed?</p>
            </div>
        </div>
        <div class="modal-footer">
            <button class="modal-btn modal-btn-secondary" id="modal-cancel">Cancel</button>
            <button class="modal-btn modal-btn-primary" id="modal-confirm">Confirm</button>
        </div>
    </div>
</div>

<div id="app-workspace">
    <aside class="glass-panel">
        <div style="padding: 16px 12px">
            <button class="new-chat-btn" onclick="Andika.newChat()"><span class="icon icon-sm" style="margin-right: 8px;"><svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg></span>New Session</button>
        </div>
        <div class="panel-header">Recent Chats</div>
        <div class="history-list" id="history-list">
            <div class="history-empty" id="history-empty">
                <span class="icon icon-lg" style="opacity: 0.4; margin-bottom: 8px;"><svg viewBox="0 0 24 24"><path d="M21 11.5a8.38 8.38 0 01-.9 3.8 8.5 8.5 0 01-7.6 4.7 8.38 8.38 0 01-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 01-.9-3.8 8.5 8.5 0 014.7-7.6 8.38 8.38 0 013.8-.9h.5a8.48 8.48 0 018 8v.5z"/></svg></span>
                <p style="font-size: 12px; opacity: 0.7; margin-bottom: 8px;">No conversations yet</p>
                <p style="font-size: 11px; opacity: 0.5; line-height: 1.4;">Start by uploading your CV or ask: "What roles am I a good fit for?"</p>
            </div>
        </div>
        <div class="panel-header">Platform</div>
        <a href="/Jobmington/ai/andika.php" class="nav-item active">
            <div class="ai-icon-sphere sm">
                <svg viewBox="0 0 24 24" fill="white">
                    <circle cx="8" cy="12" r="5.5" opacity="1"/>
                    <circle cx="16" cy="12" r="5.5" opacity="0.7"/>
                </svg>
            </div>
            <span>Andika AI</span>
        </a>
        <a href="/Jobmington/jobs/" class="nav-item"><span class="icon"><svg viewBox="0 0 24 24"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 00-2-2h-4a2 2 0 00-2 2v2"/></svg></span><span>Find Jobs</span></a>
        <a href="/Jobmington/jobs/" class="nav-item"><span class="icon"><svg viewBox="0 0 24 24"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"/><path d="M16 21V5a2 2 0 00-2-2h-4a2 2 0 00-2 2v16"/></svg></span><span>Job Board</span></a>
        <a href="/Jobmington/cv-builder/" class="nav-item"><span class="icon"><svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg></span><span>CV Builder</span></a>
        <a href="/Jobmington/ai/roast.php" class="nav-item"><span class="icon"><svg viewBox="0 0 24 24"><path d="M8.5 14.5A2.5 2.5 0 0011 12c0-1.38-.5-2-1-3-1.072-2.143-.224-4.054 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 11-14 0c0-1.153.433-2.294 1-3a2.5 2.5 0 002.5 2.5z"/></svg></span><span>CV Roast</span></a>
        <div class="panel-header" style="margin-top: 20px;">Account</div>
        <a href="/Jobmington/seeker/dashboard.php" class="nav-item"><span class="icon"><svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg></span><span>Dashboard</span></a>
        <a href="/Jobmington/seeker/settings.php" class="nav-item"><span class="icon"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-2 2 2 2 0 01-2-2v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83 0 2 2 0 010-2.83l.06-.06a1.65 1.65 0 00.33-1.82 1.65 1.65 0 00-1.51-1H3a2 2 0 01-2-2 2 2 0 012-2h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 010-2.83 2 2 0 012.83 0l.06.06a1.65 1.65 0 001.82.33H9a1.65 1.65 0 001-1.51V3a2 2 0 012-2 2 2 0 012 2v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 0 2 2 0 010 2.83l-.06.06a1.65 1.65 0 00-.33 1.82V9a1.65 1.65 0 001.51 1H21a2 2 0 012 2 2 2 0 01-2 2h-.09a1.65 1.65 0 00-1.51 1z"/></svg></span><span>Settings</span></a>
    </aside>

    <section id="main-chat-view">
        <!-- FIXED HEADER: Greeting + Input + Chips -->
        <div id="chat-header">
            <!-- Mobile Actions -->
            <div class="mobile-header-actions">
                <button class="mobile-action-btn" onclick="Andika.openDrawer()" title="Chat History" style="position: relative;">
                    <span class="icon"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></span>
                    <span class="badge" id="history-badge" style="display: none;">0</span>
                </button>
                <button class="mobile-action-btn" onclick="Andika.newChat()" title="New Chat">
                    <span class="icon"><svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg></span>
                </button>
            </div>
            
            <div class="ai-greeting">
                <div class="ai-icon-sphere">
                    <svg viewBox="0 0 24 24" fill="white">
                        <circle cx="8" cy="12" r="5.5" opacity="1"/>
                        <circle cx="16" cy="12" r="5.5" opacity="0.7"/>
                    </svg>
                </div>
                <div class="greeting-content">
                    <h1 class="greeting-text">Hi, I'm Andika <span class="wave"></span></h1>
                    <p class="greeting-sub">Your AI career assistant <span class="by-brand">by Jobmington</span></p>
                    <p class="trust-note"><span class="icon icon-sm"><svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg></span> I never apply on your behalf without your consent</p>
                </div>
            </div>
            
            <div class="main-input-area">
                <div class="input-wrapper">
                    <input type="file" id="file-upload" style="display:none" onchange="Andika.handleFileSelect(this)">
                    <button class="input-action" title="Upload CV" onclick="document.getElementById('file-upload').click()">
                        <span class="icon"><svg viewBox="0 0 24 24"><path d="M21.44 11.05l-9.19 9.19a6 6 0 01-8.49-8.49l9.19-9.19a4 4 0 015.66 5.66l-9.2 9.19a2 2 0 01-2.83-2.83l8.49-8.48"/></svg></span>
                    </button>
                    <input type="text" id="chat-input" placeholder="Ask me anything about jobs, CVs, careers..." autocomplete="off">
                    <button id="mic-btn" class="input-action" title="Voice" onclick="Andika.toggleVoice()">
                        <span class="icon"><svg viewBox="0 0 24 24"><path d="M12 2a3 3 0 00-3 3v7a3 3 0 006 0V5a3 3 0 00-3-3z"/><path d="M19 10v2a7 7 0 01-14 0v-2"/><line x1="12" y1="19" x2="12" y2="22"/></svg></span>
                    </button>
                    <button id="send-btn" onclick="Andika.handleSendClick()" class="send-action" title="Send">
                        <span class="icon send-icon"><svg viewBox="0 0 24 24"><line x1="12" y1="19" x2="12" y2="5"/><polyline points="5 12 12 5 19 12"/></svg></span>
                        <span class="icon stop-icon" style="display:none"><svg viewBox="0 0 24 24"><rect x="6" y="6" width="12" height="12" rx="2" fill="white"/></svg></span>
                    </button>
                </div>
                <p class="input-hint">Try: "Rewrite my CV summary for a product role" or "What remote jobs suit my skills?"</p>
            </div>
            
            <div class="quick-chips">
                <button class="chip chip-primary" onclick="Andika.loadJobMatches()" id="find-matches-chip"><span class="icon icon-sm"><svg viewBox="0 0 24 24"><path d="M12 3l1.912 5.813a2 2 0 001.272 1.272L21 12l-5.813 1.912a2 2 0 00-1.272 1.272L12 21l-1.912-5.813a2 2 0 00-1.272-1.272L3 12l5.813-1.912a2 2 0 001.272-1.272L12 3z"/></svg></span> Find my matches</button>
                <button class="chip" onclick="Andika.send('Find jobs near me')"><span class="icon icon-sm"><svg viewBox="0 0 24 24"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"/><path d="M16 21V5a2 2 0 00-2-2h-4a2 2 0 00-2 2v16"/></svg></span> Find jobs</button>
                <button class="chip" onclick="window.location.href='roast.php'"><span class="icon icon-sm"><svg viewBox="0 0 24 24"><path d="M8.5 14.5A2.5 2.5 0 0011 12c0-1.38-.5-2-1-3-1.072-2.143-.224-4.054 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 11-14 0c0-1.153.433-2.294 1-3a2.5 2.5 0 002.5 2.5z"/></svg></span> Roast my CV</button>
                <button class="chip" onclick="Andika.send('Start Interview Prep')"><span class="icon icon-sm"><svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg></span> Interview prep</button>
            </div>
        </div>
        
        <!-- SCROLLABLE CONTENT -->
        <div id="scroll-container">
            <div id="chat-thread">
                <div id="welcome-ui">
                    <!-- Chat History Area -->
                    <div class="chat-area" id="chat-area">
                        <h5 class="section-label">Chat</h5>
                        <div class="chat-placeholder" id="chat-placeholder">
                            <span class="icon icon-lg" style="opacity: 0.5;"><svg viewBox="0 0 24 24"><path d="M21 11.5a8.38 8.38 0 01-.9 3.8 8.5 8.5 0 01-7.6 4.7 8.38 8.38 0 01-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 01-.9-3.8 8.5 8.5 0 014.7-7.6 8.38 8.38 0 013.8-.9h.5a8.48 8.48 0 018 8v.5z"/></svg></span>
                            <span>Your conversation will appear here</span>
                        </div>
                        <div class="chat-messages" id="chat-messages"></div>
                    </div>
                    
                    <!-- Divider (mobile only) -->
                    <div class="section-divider">
                        <span>or explore tools</span>
                    </div>
                    
                    <!-- Tools Section -->
                    <div class="tools-section">
                        <h5 class="section-label">Quick Tools</h5>
                        <div class="tools-grid">
                            <div class="tool-card" onclick="window.location.href='roast.php'">
                                <div class="tool-icon fire"><span class="icon"><svg viewBox="0 0 24 24"><path d="M8.5 14.5A2.5 2.5 0 0011 12c0-1.38-.5-2-1-3-1.072-2.143-.224-4.054 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 11-14 0c0-1.153.433-2.294 1-3a2.5 2.5 0 002.5 2.5z"/></svg></span></div>
                                <div class="tool-info">
                                    <h4>CV Roast</h4>
                                    <p>Get brutally honest feedback</p>
                                </div>
                                <span class="tool-cost">50 Seeds</span>
                            </div>
                            <div class="tool-card" onclick="Andika.useTool('interview_practice', 'Start Interview Prep', 100)">
                                <div class="tool-icon green"><span class="icon"><svg viewBox="0 0 24 24"><path d="M12 2a3 3 0 00-3 3v7a3 3 0 006 0V5a3 3 0 00-3-3z"/><path d="M19 10v2a7 7 0 01-14 0v-2"/><line x1="12" y1="19" x2="12" y2="22"/></svg></span></div>
                                <div class="tool-info">
                                    <h4>Interview Practice</h4>
                                    <p>Rehearse with AI interviewer</p>
                                </div>
                                <span class="tool-cost">100 Seeds</span>
                            </div>
                            <div class="tool-card" onclick="Andika.useTool('salary_guide', 'Show Salary Insights', 0)">
                                <div class="tool-icon amber"><span class="icon"><svg viewBox="0 0 24 24"><path d="M3 3v18h18"/><path d="M18.7 8l-5.1 5.2-2.8-2.7L7 14.3"/></svg></span></div>
                                <div class="tool-info">
                                    <h4>Salary Guide</h4>
                                    <p>See what jobs really pay</p>
                                </div>
                                <span class="tool-cost free">Free</span>
                            </div>
                            <div class="tool-card" onclick="Andika.useTool('career_roadmap', 'Help me pivot careers', 75)">
                                <div class="tool-icon blue"><span class="icon"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 2a14.5 14.5 0 000 20 14.5 14.5 0 000-20"/><path d="M2 12h20"/></svg></span></div>
                                <div class="tool-info">
                                    <h4>Career Roadmap</h4>
                                    <p>Plan your next career move</p>
                                </div>
                                <span class="tool-cost">75 Seeds</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="powered-by">
                        <div class="ai-icon-sphere sm" style="transform: scale(0.6); margin-right: -8px;">
                            <svg viewBox="0 0 24 24" fill="white">
                                <circle cx="8" cy="12" r="5.5" opacity="1"/>
                                <circle cx="16" cy="12" r="5.5" opacity="0.7"/>
                            </svg>
                        </div>
                        <span>Powered by Andika AI</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <aside class="glass-panel right-panel">
        <div class="panel-header">My Stash</div>
        <p class="sidebar-subtext" style="display: flex; align-items: center; gap: 8px;">
            <div class="ai-icon-sphere sm" style="transform: scale(0.6); margin: -4px -8px -4px -8px;">
                <svg viewBox="0 0 24 24" fill="white">
                    <circle cx="8" cy="12" r="5.5" opacity="1"/>
                    <circle cx="16" cy="12" r="5.5" opacity="0.7"/>
                </svg>
            </div>
            Today's snapshot powered by Andika AI.
        </p>
        <div class="px-5">
            <!-- Profile Strength -->
            <div class="hud-widget">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-bold opacity-60 tracking-wider">PROFILE STRENGTH</span>
                    <span class="icon" style="opacity: 0.5;"><svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></span>
                </div>
                <div class="profile-strength">
                    <div class="strength-bar-bg">
                        <div class="strength-bar-fill" style="width: 68%;"></div>
                    </div>
                    <div class="strength-label">
                        <span>Add skills to improve</span>
                        <span>68%</span>
                    </div>
                </div>
            </div>
            
            <!-- Job Matches (Dynamic) -->
            <div class="hud-widget" id="job-matches-widget">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-bold opacity-60 tracking-wider">AI JOB MATCHES</span>
                    <span class="icon" style="opacity: 0.5;"><svg viewBox="0 0 24 24"><path d="M12 3l1.912 5.813a2 2 0 001.272 1.272L21 12l-5.813 1.912a2 2 0 00-1.272 1.272L12 21l-1.912-5.813a2 2 0 00-1.272-1.272L3 12l5.813-1.912a2 2 0 001.272-1.272L12 3z"/></svg></span>
                </div>
                <div class="job-matches-widget" id="matches-container">
                    <div class="matches-loading" id="matches-loading">
                        <div class="loader-spinner"></div>
                        <span>Finding matches...</span>
                    </div>
                    <div class="matches-content" id="matches-content" style="display: none;">
                        <div class="matches-count" id="matches-count">0</div>
                        <div class="matches-info">
                            <p id="matches-message">Jobs match your profile</p>
                            <div id="matches-top-list" class="matches-top-list"></div>
                            <a href="#" onclick="Andika.loadJobMatches(); return false;" class="matches-link" id="matches-view-link">
                                View matches <span class="icon icon-sm"><svg viewBox="0 0 24 24"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></span>
                            </a>
                        </div>
                    </div>
                    <div class="matches-empty" id="matches-empty" style="display: none;">
                        <p style="opacity: 0.7; font-size: 0.75rem;">Complete your CV to get job matches</p>
                        <a href="/Jobmington/cv-builder/" class="matches-link">Build CV →</a>
                    </div>
                </div>
            </div>
            
            <!-- Career Credits -->
            <div class="hud-widget">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-bold opacity-60 tracking-wider">CAREER CREDITS</span>
                    <span class="seeds-info-trigger" title="Seeds are credits you use to access premium AI tools. Earn them by completing courses or purchase more."><span class="icon" style="opacity: 0.5; cursor: help;"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 015.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg></span></span>
                </div>
                <div class="credit-balance"><?= number_format($userCredits) ?> <span class="seeds-label">Seeds</span></div>
                <a href="/Jobmington/wallet/" class="seeds-link">What are Seeds?</a>
            </div>
            
            <!-- Market Radar -->
            <div class="hud-widget">
                <span class="text-[10px] font-bold opacity-60 block mb-3">MARKET RADAR</span>
                <div class="trend-row"><span>Remote Jobs</span><span class="trend-up">▲ 14%</span></div>
                <div class="trend-row"><span>Tech Salaries</span><span class="trend-up">▲ 8%</span></div>
            </div>
            
            <!-- Quick Actions -->
            <div class="hud-widget">
                <span class="text-[10px] font-bold opacity-60 block">QUICK ACTIONS</span>
                <div class="quick-actions-grid">
                    <a href="/Jobmington/cv-builder/" class="quick-action-btn">
                        <span class="icon"><svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg></span>
                        <span>My CV</span>
                    </a>
                    <a href="/Jobmington/seeker/profile.php" class="quick-action-btn">
                        <span class="icon"><svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></span>
                        <span>Profile</span>
                    </a>
                    <a href="/Jobmington/seeker/applications.php" class="quick-action-btn">
                        <span class="icon"><svg viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg></span>
                        <span>Applications</span>
                    </a>
                    <a href="/Jobmington/jobs/saved.php" class="quick-action-btn">
                        <span class="icon"><svg viewBox="0 0 24 24"><path d="M19 21l-7-5-7 5V5a2 2 0 012-2h10a2 2 0 012 2z"/></svg></span>
                        <span>Saved Jobs</span>
                    </a>
                </div>
            </div>
        </div>
    </aside>
</div>

<script>
const Andika = {
    elements: {},
    recognition: null,
    isListening: false,
    currentChatId: null,
    messages: [],

    init: function() {
        // Cache DOM elements
        this.elements = {
            container: document.getElementById('scroll-container'),
            input: document.getElementById('chat-input'),
            inputWrapper: document.querySelector('.input-wrapper'),
            micBtn: document.getElementById('mic-btn'),
            chatArea: document.getElementById('chat-area'),
            chatPlaceholder: document.getElementById('chat-placeholder'),
            chatMessages: document.getElementById('chat-messages'),
            historyList: document.getElementById('history-list'),
            historyEmpty: document.getElementById('history-empty'),
            // Mobile elements
            mobileDrawer: document.getElementById('mobile-drawer'),
            drawerOverlay: document.getElementById('drawer-overlay'),
            mobileHistoryList: document.getElementById('mobile-history-list'),
            mobileHistoryEmpty: document.getElementById('mobile-history-empty'),
            historyBadge: document.getElementById('history-badge')
        };
        
        // Voice Logic
        if ('webkitSpeechRecognition' in window) {
            this.recognition = new webkitSpeechRecognition();
            this.recognition.continuous = false;
            this.recognition.interimResults = false;
            
            this.recognition.onstart = () => {
                this.isListening = true;
                this.elements.inputWrapper.classList.add('listening');
                this.elements.micBtn.classList.add('listening');
                this.elements.input.placeholder = "Listening...";
            };
            
            this.recognition.onend = () => {
                this.isListening = false;
                this.elements.inputWrapper.classList.remove('listening');
                this.elements.micBtn.classList.remove('listening');
                this.elements.input.placeholder = "Ask me anything...";
            };
            
            this.recognition.onresult = (event) => {
                const text = event.results[0][0].transcript;
                this.elements.input.value = text;
            };
        }
        
        // Enter key listener
        this.elements.input.addEventListener('keydown', (e) => { 
            if(e.key === 'Enter') this.submit(); 
        });
        
        // Load chat history
        this.loadHistoryList();
    },

    // === CHAT HISTORY FUNCTIONS ===
    
    getChats: function() {
        const chats = localStorage.getItem('andika_chats');
        return chats ? JSON.parse(chats) : [];
    },
    
    saveChats: function(chats) {
        localStorage.setItem('andika_chats', JSON.stringify(chats));
    },
    
    generateId: function() {
        return 'chat_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
    },
    
    getTimeAgo: function(timestamp) {
        const now = Date.now();
        const diff = now - timestamp;
        const minutes = Math.floor(diff / 60000);
        const hours = Math.floor(diff / 3600000);
        const days = Math.floor(diff / 86400000);
        
        if (minutes < 1) return 'Just now';
        if (minutes < 60) return `${minutes}m ago`;
        if (hours < 24) return `${hours}h ago`;
        if (days < 7) return `${days}d ago`;
        return new Date(timestamp).toLocaleDateString();
    },
    
    loadHistoryList: function() {
        const chats = this.getChats();
        
        // Update badge count
        if (this.elements.historyBadge) {
            if (chats.length > 0) {
                this.elements.historyBadge.textContent = chats.length;
                this.elements.historyBadge.style.display = 'flex';
            } else {
                this.elements.historyBadge.style.display = 'none';
            }
        }
        
        // Handle empty state for both desktop and mobile
        if (chats.length === 0) {
            if (this.elements.historyEmpty) this.elements.historyEmpty.style.display = 'block';
            if (this.elements.mobileHistoryEmpty) this.elements.mobileHistoryEmpty.style.display = 'block';
            return;
        }
        
        if (this.elements.historyEmpty) this.elements.historyEmpty.style.display = 'none';
        if (this.elements.mobileHistoryEmpty) this.elements.mobileHistoryEmpty.style.display = 'none';
        
        // Clear existing items
        if (this.elements.historyList) {
            const items = this.elements.historyList.querySelectorAll('.history-item');
            items.forEach(item => item.remove());
        }
        if (this.elements.mobileHistoryList) {
            const mobileItems = this.elements.mobileHistoryList.querySelectorAll('.history-item');
            mobileItems.forEach(item => item.remove());
        }
        
        // Sort by most recent
        chats.sort((a, b) => b.updatedAt - a.updatedAt);
        
        // Render history items (max 10) for both lists
        chats.slice(0, 10).forEach(chat => {
            const itemHTML = `
                <div class="ai-icon-sphere sm" style="margin-right: -4px; transform: scale(0.85);">
                    <svg viewBox="0 0 24 24" fill="white">
                        <circle cx="8" cy="12" r="5.5" opacity="1"/>
                        <circle cx="16" cy="12" r="5.5" opacity="0.7"/>
                    </svg>
                </div>
                <div class="history-content">
                    <p class="history-title">${this.escapeHtml(chat.title)}</p>
                    <span class="history-time">${this.getTimeAgo(chat.updatedAt)}</span>
                </div>
                <button class="history-delete" onclick="event.stopPropagation(); Andika.deleteChat('${chat.id}')" title="Delete">
                    <span class="icon"><svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg></span>
                </button>
            `;
            
            // Desktop sidebar
            if (this.elements.historyList) {
                const item = document.createElement('div');
                item.className = 'history-item' + (chat.id === this.currentChatId ? ' active' : '');
                item.innerHTML = itemHTML;
                item.onclick = () => this.loadChat(chat.id);
                this.elements.historyList.appendChild(item);
            }
            
            // Mobile drawer
            if (this.elements.mobileHistoryList) {
                const mobileItem = document.createElement('div');
                mobileItem.className = 'history-item' + (chat.id === this.currentChatId ? ' active' : '');
                mobileItem.innerHTML = itemHTML;
                mobileItem.onclick = () => { this.loadChat(chat.id); this.closeDrawer(); };
                this.elements.mobileHistoryList.appendChild(mobileItem);
            }
        });
    },
    
    saveCurrentChat: function() {
        if (this.messages.length === 0) return;
        
        const chats = this.getChats();
        
        // Get title from first user message
        const firstUserMsg = this.messages.find(m => m.type === 'user');
        const title = firstUserMsg ? firstUserMsg.text.substring(0, 40) + (firstUserMsg.text.length > 40 ? '...' : '') : 'New Chat';
        
        if (this.currentChatId) {
            // Update existing chat
            const index = chats.findIndex(c => c.id === this.currentChatId);
            if (index !== -1) {
                chats[index].messages = this.messages;
                chats[index].updatedAt = Date.now();
                chats[index].title = title;
            }
        } else {
            // Create new chat
            this.currentChatId = this.generateId();
            chats.push({
                id: this.currentChatId,
                title: title,
                messages: this.messages,
                createdAt: Date.now(),
                updatedAt: Date.now()
            });
        }
        
        this.saveChats(chats);
        this.loadHistoryList();
    },
    
    loadChat: function(chatId) {
        const chats = this.getChats();
        const chat = chats.find(c => c.id === chatId);
        
        if (!chat) return;
        
        this.currentChatId = chatId;
        this.messages = chat.messages || [];
        
        // Clear current display
        this.elements.chatMessages.innerHTML = '';
        
        // Activate chat area
        this.activateChat();
        
        // Render all messages
        this.messages.forEach(msg => {
            this.renderMessage(msg.text, msg.type);
        });
        
        // Update history list to show active
        this.loadHistoryList();
    },
    
    deleteChat: function(chatId) {
        let chats = this.getChats();
        chats = chats.filter(c => c.id !== chatId);
        this.saveChats(chats);
        
        // If deleting current chat, reset
        if (chatId === this.currentChatId) {
            this.newChat();
        }
        
        this.loadHistoryList();
    },
    
    newChat: function() {
        this.currentChatId = null;
        this.messages = [];
        
        // Reset UI
        this.elements.chatMessages.innerHTML = '';
        this.elements.chatPlaceholder.classList.remove('hidden');
        this.elements.chatMessages.classList.remove('active');
        
        const welcomeUI = document.getElementById('welcome-ui');
        if (welcomeUI) {
            welcomeUI.classList.remove('chat-active');
        }
        
        this.loadHistoryList();
    },
    
    // === MOBILE DRAWER FUNCTIONS ===
    
    openDrawer: function() {
        if (this.elements.mobileDrawer) {
            this.elements.mobileDrawer.classList.add('active');
        }
        if (this.elements.drawerOverlay) {
            this.elements.drawerOverlay.classList.add('active');
        }
        document.body.style.overflow = 'hidden';
    },
    
    closeDrawer: function() {
        if (this.elements.mobileDrawer) {
            this.elements.mobileDrawer.classList.remove('active');
        }
        if (this.elements.drawerOverlay) {
            this.elements.drawerOverlay.classList.remove('active');
        }
        document.body.style.overflow = '';
    },
    
    escapeHtml: function(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    },

    // === EXISTING FUNCTIONS ===

    toggleVoice: function() {
        if (!this.recognition) {
            this.toast("Voice input requires a modern browser", "warning", "Not Supported");
            return;
        }
        if (this.isListening) {
            this.recognition.stop();
        } else {
            this.recognition.start();
        }
    },

    handleFileSelect: function(input) {
        if (input.files && input.files[0]) {
            const fileName = input.files[0].name;
            this.elements.input.value = `[Attached: ${fileName}] `;
            this.elements.input.focus();
        }
    },

    send: function(msg) { 
        this.elements.input.value = msg; 
        this.submit(); 
    },

    // Tool usage with seed payment
    useTool: function(toolType, message, cost) {
        const isLoggedIn = <?= $isLoggedIn ? 'true' : 'false' ?>;
        const currentBalance = <?= $userCredits ?>;
        
        if (cost > 0 && !isLoggedIn) {
            this.toast('Please log in to use this feature', 'warning', 'Login Required');
            setTimeout(() => window.location.href = '/jobmington/auth/login.php', 1500);
            return;
        }
        
        if (cost > 0 && currentBalance < cost) {
            this.toast(`You need ${cost} Seeds but only have ${currentBalance}`, 'error', 'Insufficient Seeds');
            return;
        }
        
        // Confirm payment for paid tools
        if (cost > 0) {
            this.confirm({
                title: 'Use Seeds?',
                message: `This will use <strong>${cost} Seeds</strong> from your balance. You currently have <strong>${currentBalance} Seeds</strong>.`,
                icon: 'confirm',
                confirmText: 'Continue',
                onConfirm: () => {
                    this.currentTool = toolType;
                    this.elements.input.value = message;
                    this.submit();
                }
            });
            return;
        }
        
        this.currentTool = toolType;
        this.elements.input.value = message;
        this.submit();
    },

    // Modern Toast Notifications
    toast: function(message, type = 'info', title = null) {
        const container = document.getElementById('toast-container');
        const icons = {
            success: '<svg viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>',
            error: '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>',
            warning: '<svg viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
            info: '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>'
        };
        const titles = {
            success: 'Success',
            error: 'Error',
            warning: 'Warning',
            info: 'Info'
        };
        
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.innerHTML = `
            <div class="toast-icon"><span class="icon">${icons[type]}</span></div>
            <div class="toast-content">
                <div class="toast-title">${title || titles[type]}</div>
                <div class="toast-message">${message}</div>
            </div>
            <button class="toast-close" onclick="this.parentElement.classList.add('hiding'); setTimeout(() => this.parentElement.remove(), 250);">
                <span class="icon"><svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></span>
            </button>
            <div class="toast-progress" style="animation-duration: 4s;"></div>
        `;
        
        container.appendChild(toast);
        
        // Auto dismiss
        setTimeout(() => {
            toast.classList.add('hiding');
            setTimeout(() => toast.remove(), 250);
        }, 4000);
    },
    
    // Modern Confirm Dialog
    confirm: function(options) {
        const overlay = document.getElementById('modal-overlay');
        const iconEl = document.getElementById('modal-icon');
        const titleEl = document.getElementById('modal-title');
        const messageEl = document.getElementById('modal-message');
        const confirmBtn = document.getElementById('modal-confirm');
        const cancelBtn = document.getElementById('modal-cancel');
        
        const icons = {
            confirm: '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>',
            warning: '<svg viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
            danger: '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>',
            success: '<svg viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>'
        };
        
        iconEl.className = `modal-icon ${options.icon || 'confirm'}`;
        iconEl.innerHTML = `<span class="icon">${icons[options.icon || 'confirm']}</span>`;
        titleEl.textContent = options.title || 'Confirm';
        messageEl.innerHTML = options.message || 'Are you sure?';
        confirmBtn.textContent = options.confirmText || 'Confirm';
        confirmBtn.className = `modal-btn ${options.danger ? 'modal-btn-danger' : 'modal-btn-primary'}`;
        
        overlay.classList.add('active');
        
        const cleanup = () => {
            overlay.classList.remove('active');
            confirmBtn.onclick = null;
            cancelBtn.onclick = null;
        };
        
        confirmBtn.onclick = () => {
            cleanup();
            if (options.onConfirm) options.onConfirm();
        };
        
        cancelBtn.onclick = () => {
            cleanup();
            if (options.onCancel) options.onCancel();
        };
        
        overlay.onclick = (e) => {
            if (e.target === overlay) {
                cleanup();
                if (options.onCancel) options.onCancel();
            }
        };
    },
    
    // Alert dialog (simple, no cancel)
    alert: function(message, type = 'info', title = null) {
        this.toast(message, type, title);
    },

    // Legacy showNotification - redirects to toast
    showNotification: function(message, type = 'info') {
        this.toast(message, type);
    },

    currentTool: 'chat',

    activateChat: function() {
        // Show chat messages, hide placeholder
        if (this.elements.chatPlaceholder) {
            this.elements.chatPlaceholder.classList.add('hidden');
        }
        if (this.elements.chatMessages) {
            this.elements.chatMessages.classList.add('active');
        }
        // Add chat-active class to welcome-ui (hides tools on mobile)
        const welcomeUI = document.getElementById('welcome-ui');
        if (welcomeUI) {
            welcomeUI.classList.add('chat-active');
        }
    },

    isGenerating: false,
    abortController: null,
    
    handleSendClick: function() {
        if (this.isGenerating) {
            this.stopGeneration();
        } else {
            this.submit();
        }
    },
    
    stopGeneration: function() {
        if (this.abortController) {
            this.abortController.abort();
            this.abortController = null;
        }
        this.isGenerating = false;
        this.hideTyping();
        this.updateSendButton(false);
        this.addMessage("Response stopped.", 'ai');
        this.showNotification('Response cancelled', 'info');
    },
    
    updateSendButton: function(isLoading) {
        const btn = document.getElementById('send-btn');
        const sendIcon = btn.querySelector('.send-icon');
        const stopIcon = btn.querySelector('.stop-icon');
        
        if (isLoading) {
            btn.classList.add('is-loading');
            btn.title = 'Stop';
            sendIcon.style.display = 'none';
            stopIcon.style.display = 'flex';
        } else {
            btn.classList.remove('is-loading');
            btn.title = 'Send';
            sendIcon.style.display = 'flex';
            stopIcon.style.display = 'none';
        }
    },

    submit: async function() {
        const text = this.elements.input.value.trim();
        if (!text || this.isGenerating) return;
        
        // Activate chat area
        this.activateChat();
        
        // Add user message
        this.addMessage(text, 'user');
        this.elements.input.value = '';
        
        // Show typing indicator and update button
        this.isGenerating = true;
        this.updateSendButton(true);
        this.showTyping();
        
        // Create abort controller for this request
        this.abortController = new AbortController();
        
        try {
            // Call the real API with tool type
            const response = await fetch('/jobmington/api/andika.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    message: text,
                    tool: this.currentTool || 'chat'
                }),
                signal: this.abortController.signal
            });
            
            const data = await response.json();
            
            // Hide typing indicator
            this.hideTyping();
            this.isGenerating = false;
            this.updateSendButton(false);
            
            if (data.success) {
                this.addMessage(data.reply, 'ai');
                
                // Update balance display if seeds were spent
                if (data.cost > 0) {
                    this.updateBalanceDisplay(data.balance);
                    this.showNotification(`-${data.cost} Seeds used`, 'info');
                }
            } else {
                // Handle errors
                if (data.error === 'login_required') {
                    this.addMessage("Please log in to continue. Redirecting...", 'ai');
                    setTimeout(() => window.location.href = '/jobmington/auth/login.php', 2000);
                } else if (data.error === 'insufficient_seeds') {
                    this.addMessage(`${data.message} Visit your wallet to get more seeds!`, 'ai');
                } else {
                    this.addMessage(data.message || "Sorry, something went wrong. Please try again.", 'ai');
                }
            }
        } catch (error) {
            this.hideTyping();
            this.isGenerating = false;
            this.updateSendButton(false);
            
            // Only show error if not aborted by user
            if (error.name !== 'AbortError') {
                this.addMessage("Connection error. Please check your internet and try again.", 'ai');
            }
        }
        
        // Reset tool after use
        this.currentTool = 'chat';
        this.abortController = null;
    },

    showTyping: function() {
        const typingDiv = document.createElement('div');
        typingDiv.id = 'typing-indicator';
        typingDiv.className = 'msg-ai';
        typingDiv.innerHTML = `
            <div class="ai-icon-sphere sm">
                <svg viewBox="0 0 24 24" fill="white">
                    <circle cx="8" cy="12" r="5.5" opacity="1"/>
                    <circle cx="16" cy="12" r="5.5" opacity="0.7"/>
                </svg>
            </div>
            <div class="ai-text"><span class="typing-dots"><span>.</span><span>.</span><span>.</span></span></div>
        `;
        if (this.elements.chatMessages) {
            this.elements.chatMessages.appendChild(typingDiv);
            this.elements.chatMessages.scrollTop = this.elements.chatMessages.scrollHeight;
        }
    },

    hideTyping: function() {
        const typing = document.getElementById('typing-indicator');
        if (typing) typing.remove();
    },

    updateBalanceDisplay: function(newBalance) {
        const balanceEl = document.querySelector('.credit-balance');
        if (balanceEl) {
            balanceEl.innerHTML = `${Number(newBalance).toLocaleString()} <span class="seeds-label">Seeds</span>`;
        }
    },
    
    addMessage: function(text, type) {
        // Store message
        this.messages.push({ text, type, timestamp: Date.now() });
        
        // Render it
        this.renderMessage(text, type);
        
        // Save to history
        this.saveCurrentChat();
    },

    renderMessage: function(text, type) {
        const msgDiv = document.createElement('div');
        
        if (type === 'user') {
            msgDiv.className = 'msg-user';
            msgDiv.textContent = text;
        } else {
            msgDiv.className = 'msg-ai';
            const formattedText = this.formatMarkdown(text);
            const uniqueId = 'msg-' + Date.now();
            msgDiv.innerHTML = `
                <div class="ai-icon-sphere sm">
                    <svg viewBox="0 0 24 24" fill="white">
                        <circle cx="8" cy="12" r="5.5" opacity="1"/>
                        <circle cx="16" cy="12" r="5.5" opacity="0.7"/>
                    </svg>
                </div>
                <div class="ai-text" id="${uniqueId}">
                    <div class="ai-content">${formattedText}</div>
                    <button class="copy-btn" onclick="Andika.copyMessage('${uniqueId}')">
                        <i class="fas fa-copy"></i> Copy
                    </button>
                </div>
            `;
        }
        
        if (this.elements.chatMessages) {
            this.elements.chatMessages.appendChild(msgDiv);
            this.elements.chatMessages.scrollTop = this.elements.chatMessages.scrollHeight;
        }
        
        // Scroll main container too
        this.elements.container.scrollTo({
            top: this.elements.container.scrollHeight,
            behavior: 'smooth'
        });
    },
    
    formatMarkdown: function(text) {
        // Convert markdown to HTML - ChatGPT style
        let html = text
            // Escape HTML first
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
        
        // Process line by line for better control
        let lines = html.split('\n');
        let result = [];
        let inList = false;
        let listType = '';
        let inCodeBlock = false;
        
        for (let i = 0; i < lines.length; i++) {
            let line = lines[i];
            
            // Code blocks
            if (line.trim().startsWith('```')) {
                if (inCodeBlock) {
                    result.push('</code></pre>');
                    inCodeBlock = false;
                } else {
                    if (inList) { result.push(listType === 'ul' ? '</ul>' : '</ol>'); inList = false; }
                    result.push('<pre><code>');
                    inCodeBlock = true;
                }
                continue;
            }
            
            if (inCodeBlock) {
                result.push(line);
                continue;
            }
            
            // Headers
            if (line.match(/^### (.+)$/)) {
                if (inList) { result.push(listType === 'ul' ? '</ul>' : '</ol>'); inList = false; }
                result.push(line.replace(/^### (.+)$/, '<h3>$1</h3>'));
                continue;
            }
            if (line.match(/^## (.+)$/)) {
                if (inList) { result.push(listType === 'ul' ? '</ul>' : '</ol>'); inList = false; }
                result.push(line.replace(/^## (.+)$/, '<h2>$1</h2>'));
                continue;
            }
            if (line.match(/^# (.+)$/)) {
                if (inList) { result.push(listType === 'ul' ? '</ul>' : '</ol>'); inList = false; }
                result.push(line.replace(/^# (.+)$/, '<h1>$1</h1>'));
                continue;
            }
            
            // Unordered list
            if (line.match(/^[\*\-•] (.+)$/)) {
                if (!inList || listType !== 'ul') {
                    if (inList) result.push('</ol>');
                    result.push('<ul>');
                    inList = true;
                    listType = 'ul';
                }
                result.push(line.replace(/^[\*\-•] (.+)$/, '<li>$1</li>'));
                continue;
            }
            
            // Ordered list
            if (line.match(/^\d+[\.\)] (.+)$/)) {
                if (!inList || listType !== 'ol') {
                    if (inList) result.push('</ul>');
                    result.push('<ol>');
                    inList = true;
                    listType = 'ol';
                }
                result.push(line.replace(/^\d+[\.\)] (.+)$/, '<li>$1</li>'));
                continue;
            }
            
            // Close list if we hit a non-list line
            if (inList && line.trim() !== '') {
                result.push(listType === 'ul' ? '</ul>' : '</ol>');
                inList = false;
            }
            
            // Empty line = paragraph break
            if (line.trim() === '') {
                if (result.length > 0 && !result[result.length-1].match(/<\/(ul|ol|h[123]|pre)>$/)) {
                    result.push('<br><br>');
                }
                continue;
            }
            
            // Regular text
            result.push(line);
        }
        
        // Close any open list
        if (inList) {
            result.push(listType === 'ul' ? '</ul>' : '</ol>');
        }
        
        html = result.join('\n');
        
        // Inline formatting
        html = html
            .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
            .replace(/\*(.+?)\*/g, '<em>$1</em>')
            .replace(/`([^`]+)`/g, '<code>$1</code>');
        
        // Clean up excessive breaks
        html = html.replace(/(<br>){3,}/g, '<br><br>');
        html = html.replace(/^<br><br>/, '');
        html = html.replace(/<br><br>$/, '');
        
        return html;
    },
    
    copyMessage: function(elementId) {
        const element = document.getElementById(elementId);
        if (!element) return;
        
        // Get text content from ai-content div
        const contentDiv = element.querySelector('.ai-content');
        const text = contentDiv ? contentDiv.innerText.trim() : element.innerText.replace('Copy', '').trim();
        
        navigator.clipboard.writeText(text).then(() => {
            const btn = element.querySelector('.copy-btn');
            if (btn) {
                btn.innerHTML = '<i class="fas fa-check"></i> Copied!';
                btn.classList.add('copied');
                setTimeout(() => {
                    btn.innerHTML = '<i class="fas fa-copy"></i> Copy';
                    btn.classList.remove('copied');
                }, 2000);
            }
        });
    },
    
    // === JOB MATCHES ===
    
    loadJobMatches: function() {
        const loading = document.getElementById('matches-loading');
        const content = document.getElementById('matches-content');
        const empty = document.getElementById('matches-empty');
        
        if (loading) loading.style.display = 'flex';
        if (content) content.style.display = 'none';
        if (empty) empty.style.display = 'none';
        
        fetch('/Jobmington/api/job-matches.php?limit=5')
            .then(response => response.json())
            .then(data => {
                if (loading) loading.style.display = 'none';
                
                if (data.success && data.matches && data.matches.length > 0) {
                    this.displayMatches(data.matches, data.count);
                } else if (data.success && (!data.matches || data.matches.length === 0)) {
                    if (empty) {
                        empty.style.display = 'block';
                        if (data.tip) {
                            empty.querySelector('p').textContent = data.tip;
                        }
                    }
                } else {
                    if (empty) empty.style.display = 'block';
                }
            })
            .catch(err => {
                console.error('Job matches error:', err);
                if (loading) loading.style.display = 'none';
                if (empty) empty.style.display = 'block';
            });
    },
    
    displayMatches: function(matches, totalCount) {
        const content = document.getElementById('matches-content');
        const countEl = document.getElementById('matches-count');
        const messageEl = document.getElementById('matches-message');
        const listEl = document.getElementById('matches-top-list');
        
        if (!content) return;
        
        content.style.display = 'block';
        if (countEl) countEl.textContent = totalCount;
        if (messageEl) messageEl.textContent = totalCount === 1 ? 'Job matches your profile' : 'Jobs match your profile';
        
        // Render top matches
        if (listEl) {
            listEl.innerHTML = matches.slice(0, 3).map(match => {
                const scoreClass = match.score >= 70 ? 'high' : (match.score >= 50 ? 'medium' : 'low');
                return `
                    <a href="/Jobmington/jobs/view.php?id=${match.job_id}" class="match-item" title="${this.escapeHtml(match.title)} at ${this.escapeHtml(match.company)}">
                        <span class="match-title">${this.escapeHtml(match.title)}</span>
                        <span class="match-score ${scoreClass}">${match.score}%</span>
                    </a>
                `;
            }).join('');
        }
        
        // Show toast with top match
        if (matches.length > 0) {
            const top = matches[0];
            this.showToast('info', 'Matches Found', `Your best match: ${top.title} (${top.score}% fit)`);
        }
    }
};

document.addEventListener('DOMContentLoaded', () => {
    Andika.init();
    // Auto-load job matches
    setTimeout(() => Andika.loadJobMatches(), 500);
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
