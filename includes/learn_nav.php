<?php
if (!defined('JOBMINGTON')) { exit; }

/**
 * Shared sub-navigation for the Learn / Resources section.
 * Renders a clean tab bar (Courses · Ebooks · Events · Blog · Community).
 * Self-contained: prints its CSS once per request.
 *
 * @param string $active one of: courses, ebooks, events, blog, forum
 */
function jm_learn_nav(string $active = ''): string {
    static $stylePrinted = false;
    $style = '';
    if (!$stylePrinted) {
        $stylePrinted = true;
        $style = '<style>'
            . '.jm-learnnav{display:flex;gap:6px;flex-wrap:wrap;margin:0 0 28px;padding:6px;background:#fff;border:1px solid #e4eaf3;border-radius:12px;}'
            . '.jm-learnnav a{display:inline-flex;align-items:center;gap:7px;padding:9px 15px;border-radius:8px;font-size:13.5px;font-weight:700;color:#53667f;text-decoration:none;transition:background .14s,color .14s;}'
            . '.jm-learnnav a svg{width:15px;height:15px;}'
            . '.jm-learnnav a:hover{background:#f3f7fd;color:#0640a3;}'
            . '.jm-learnnav a.active{background:#0640a3;color:#fff;}'
            . '@media(max-width:560px){.jm-learnnav{overflow-x:auto;flex-wrap:nowrap;}.jm-learnnav a{white-space:nowrap;}}'
            . '</style>';
    }

    $items = [
        'courses' => ['label' => 'Courses', 'url' => '/jobmington/learn/',  'icon' => '<path d="M22 10L12 5 2 10l10 5 10-5z"/><path d="M6 12v5c0 1 3 2 6 2s6-1 6-2v-5"/>'],
        'ebooks'  => ['label' => 'Ebooks',  'url' => '/jobmington/ebooks/', 'icon' => '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>'],
        'events'  => ['label' => 'Events',  'url' => '/jobmington/events/', 'icon' => '<rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>'],
        'blog'    => ['label' => 'Blog',    'url' => '/jobmington/blog/',   'icon' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/>'],
        'forum'   => ['label' => 'Community','url' => '/jobmington/community/', 'icon' => '<path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/>'],
    ];

    $links = '';
    foreach ($items as $key => $it) {
        $cls = $key === $active ? ' class="active"' : '';
        $links .= '<a' . $cls . ' href="' . $it['url'] . '">'
            . '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' . $it['icon'] . '</svg>'
            . htmlspecialchars($it['label']) . '</a>';
    }

    return $style . '<nav class="jm-learnnav" aria-label="Learn sections">' . $links . '</nav>';
}
