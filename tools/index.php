<?php
/**
 * JOBMINGTON - Tools & Utilities Hub
 */

define('JOBMINGTON', true);
$root = dirname(__DIR__);
require_once $root . '/config/env.php';
require_once $root . '/config/constants.php';
require_once $root . '/config/database.php';
require_once $root . '/includes/security.php';
require_once $root . '/includes/session.php';
require_once $root . '/includes/functions.php';
require_once $root . '/includes/tools.php';

Session::start();
$pageTitle = 'Career Tools - ' . SITE_NAME;
$activeAIPage = 'tools';

$tools = [
    [
        'name'  => 'Resume Builder',
        'key'   => 'cv_builder',
        'desc'  => 'Build an ATS-friendly resume from polished templates.',
        'url'   => '/jobmington/cv-builder/',
        'icon'  => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="8" y1="13" x2="16" y2="13"/><line x1="8" y1="17" x2="13" y2="17"/>',
        'badge' => '',
    ],
    [
        'name'  => 'Resume Optimizer',
        'key'   => 'cv_roast',
        'desc'  => 'Score your existing CV against ATS criteria and get targeted fixes.',
        'url'   => '/jobmington/ai/roast.php',
        'icon'  => '<circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/><line x1="11" y1="8" x2="11" y2="14"/><line x1="8" y1="11" x2="14" y2="11"/>',
        'badge' => '',
    ],
    [
        'name'  => 'Cover Letter AI',
        'key'   => 'cover_letter',
        'desc'  => 'Generate a tailored cover letter from a job description in seconds.',
        'url'   => '/jobmington/ai/cover-letter.php',
        'icon'  => '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="M22 7l-10 5L2 7"/>',
        'badge' => 'New',
    ],
    [
        'name'  => 'Cold Pitch AI',
        'key'   => 'cold_pitch',
        'desc'  => 'Write human, specific cold pitches that earn the micro-yes — email, DM, or LinkedIn.',
        'url'   => '/jobmington/ai/cold-pitch.php',
        'icon'  => '<path d="M22 2L11 13"/><path d="M22 2l-7 20-4-9-9-4 20-7z"/>',
        'badge' => 'New',
    ],
    [
        'name'  => 'Andika AI',
        'key'   => 'andika',
        'desc'  => 'Chat with your career co-pilot for advice, rewrites, and prep.',
        'url'   => '/jobmington/ai/andika.php',
        'icon'  => '<path d="M12 3l1.9 5.8a2 2 0 0 0 1.3 1.3L21 12l-5.8 1.9a2 2 0 0 0-1.3 1.3L12 21l-1.9-5.8a2 2 0 0 0-1.3-1.3L3 12l5.8-1.9a2 2 0 0 0 1.3-1.3L12 3z"/>',
        'badge' => '',
    ],
    [
        'name'  => 'Find Jobs',
        'key'   => 'job_match',
        'desc'  => 'Search live roles across remote and African markets.',
        'url'   => '/jobmington/jobs/search.php',
        'icon'  => '<rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/>',
        'badge' => '',
    ],
    [
        'name'  => 'Talent Passport',
        'key'   => 'passport',
        'desc'  => 'A verified, shareable profile employers can trust.',
        'url'   => '/jobmington/wallet/passport/',
        'icon'  => '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="2"/><path d="M15 8h3"/><path d="M15 12h3"/><path d="M7 16h10"/>',
        'badge' => '',
    ],
    [
        'name'  => 'Certificates',
        'key'   => 'certificates',
        'desc'  => 'View and verify the credentials you have earned.',
        'url'   => '/jobmington/certificates/',
        'icon'  => '<circle cx="12" cy="8" r="6"/><path d="M8.21 13.89L7 23l5-3 5 3-1.21-9.12"/>',
        'badge' => '',
    ],
];

require_once $root . '/includes/ai-header.php';
?>
<style>
.jm-tools-page { max-width: 1100px; margin: 0 auto; padding: 48px 20px 72px; }
.jm-tools-head { margin-bottom: 36px; }
.jm-tools-head h1 { font-size: clamp(30px, 5vw, 46px); font-weight: 800; letter-spacing: -.02em; color: #061426; margin: 0 0 12px; }
.jm-tools-head p { font-size: 17px; color: #53667f; margin: 0; max-width: 600px; line-height: 1.6; }
.jm-tools-grid { display: grid; grid-template-columns: repeat(3, minmax(0,1fr)); gap: 16px; }
@media (max-width: 900px) { .jm-tools-grid { grid-template-columns: repeat(2, minmax(0,1fr)); } }
@media (max-width: 560px) { .jm-tools-grid { grid-template-columns: 1fr; } }
.jm-tool-card {
    position: relative; display: flex; flex-direction: column; gap: 12px;
    padding: 22px; background: #fff; border: 1px solid #e4eaf3; border-radius: 14px;
    text-decoration: none; box-shadow: 0 1px 3px rgba(6,20,38,.04);
    transition: box-shadow .16s, transform .16s, border-color .16s;
}
.jm-tool-card:hover { box-shadow: 0 12px 30px rgba(6,20,38,.1); transform: translateY(-3px); border-color: #c8d8ef; }
.jm-tool-card-ico { width: 46px; height: 46px; border-radius: 12px; display: grid; place-items: center; background: #eef5ff; color: #0640a3; }
.jm-tool-card h3 { font-size: 16px; font-weight: 800; color: #061426; margin: 0; }
.jm-tool-card p { font-size: 13.5px; color: #53667f; margin: 0; line-height: 1.55; flex: 1; }
.jm-tool-card-go { display: inline-flex; align-items: center; gap: 6px; font-size: 13px; font-weight: 700; color: #0640a3; }
.jm-tool-card-badge { position: absolute; top: 16px; right: 16px; font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: .06em; color: #0a6454; background: #e6f5f1; padding: 3px 9px; border-radius: 99px; }
.jm-tool-card-badge.is-beta { color: #8a5a00; background: #fdf0d5; }
.jm-tool-card.is-locked { cursor: default; opacity: .62; }
.jm-tool-card.is-locked:hover { transform: none; box-shadow: none; }
.jm-tool-card.is-locked .jm-tool-card-go { color: #94a3b8; }
.jm-tools-notice { max-width: 1120px; margin: 0 auto 22px; padding: 12px 16px; border-radius: 12px; background: #fdf0d5; border: 1px solid #f3dda8; color: #7a4f00; font-size: 14px; font-weight: 600; }
</style>

<div class="jm-tools-page">
    <?= jm_breadcrumbs([['label' => 'Tools']]) ?>
    <div class="jm-tools-head">
        <h1>Career tools.</h1>
        <p>Everything you need to build, sharpen, and pitch yourself — powered by AI, tuned for African and remote talent.</p>
    </div>

    <?php
    // Someone who followed a link straight to a gated tool lands here, so say
    // why rather than leaving them wondering what happened.
    $lockedName = $_SESSION['tool_locked'] ?? '';
    unset($_SESSION['tool_locked']);
    if ($lockedName !== ''):
    ?>
        <p class="jm-tools-notice"><?= e($lockedName) ?> is still in beta. We will open it to everyone shortly.</p>
    <?php endif; ?>

    <div class="jm-tools-grid">
        <?php foreach ($tools as $tool):
            // A gated tool keeps its card so people can see what is coming, but
            // it is a div rather than a link, so there is nowhere to click.
            $locked = !empty($tool['key']) && !jm_tool_available($tool['key']);
            $tag    = $locked ? 'div' : 'a';
        ?>
            <<?= $tag ?> class="jm-tool-card<?= $locked ? ' is-locked' : '' ?>"<?= $locked ? ' aria-disabled="true"' : ' href="' . e($tool['url']) . '"' ?>>
                <?php if ($locked): ?>
                    <span class="jm-tool-card-badge is-beta">Beta</span>
                <?php elseif (!empty($tool['badge'])): ?>
                    <span class="jm-tool-card-badge"><?= e($tool['badge']) ?></span>
                <?php endif; ?>
                <span class="jm-tool-card-ico">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><?= $tool['icon'] ?></svg>
                </span>
                <h3><?= e($tool['name']) ?></h3>
                <p><?= e($tool['desc']) ?></p>
                <span class="jm-tool-card-go">
                    <?php if ($locked): ?>
                        Coming soon
                    <?php else: ?>
                        Open
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                    <?php endif; ?>
                </span>
            </<?= $tag ?>>
        <?php endforeach; ?>
    </div>
</div>

<?php require_once $root . '/includes/ai-footer.php'; ?>
