<?php
define('JOBMINGTON', true);
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';

Session::start();
Session::requireLogin();

if (Session::isEmployer() && !Session::isAdmin()) {
    redirect('/jobmington/employer/dashboard.php');
}

$pdo = db();
$userId = Session::userId();

// Daily login Seeds (idempotent — awards at most once per calendar day).
try {
    require_once __DIR__ . '/../includes/seeds.php';
    awardDailyLoginBonus((int) $userId);
} catch (Throwable $e) {
    error_log('Daily login seed bonus failed: ' . $e->getMessage());
}

if (!function_exists('jm_dashboard_scalar')) {
    function jm_dashboard_scalar(PDO $pdo, string $sql, array $params = []): int {
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return (int) $stmt->fetchColumn();
        } catch (Throwable $e) {
            return 0;
        }
    }
}

if (!function_exists('jm_dashboard_rows')) {
    function jm_dashboard_rows(PDO $pdo, string $sql, array $params = []): array {
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (Throwable $e) {
            return [];
        }
    }
}

$stmt = $pdo->prepare("
    SELECT u.*, cv.headline, cv.summary
    FROM users u
    LEFT JOIN cv_profiles cv ON u.user_id = cv.user_id
    WHERE u.user_id = ?
    LIMIT 1
");
$stmt->execute([$userId]);
$user = $stmt->fetch() ?: [];

$displayName = trim($user['full_name'] ?? '');
if ($displayName === '') {
    $displayName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
}
if ($displayName === '') {
    $displayName = 'there';
}
$firstName = explode(' ', $displayName)[0];

// Wallet balances (Seeds = free/earned, Credits = paid/premium tools).
require_once __DIR__ . '/../includes/monetization.php';
require_once __DIR__ . '/../includes/seeker_premium.php';
$seedBalance   = (int) round(getSeedBalance((int) $userId));
$creditBalance = jm_seeker_credit_balance($pdo, (int) $userId);
$isPremiumUser = jm_seeker_is_premium($pdo, (int) $userId);
$seedsPerCredit = defined('SEEDS_PER_CREDIT') ? (int) SEEDS_PER_CREDIT : 100;
$redeemableCredits = (int) floor($seedBalance / max(1, $seedsPerCredit));

$stats = [
    'Applications' => jm_dashboard_scalar($pdo, "SELECT COUNT(*) FROM job_applications WHERE user_id = ?", [$userId]),
    'Pending' => jm_dashboard_scalar($pdo, "SELECT COUNT(*) FROM job_applications WHERE user_id = ? AND status = 'pending'", [$userId]),
    'Interviews' => jm_dashboard_scalar($pdo, "SELECT COUNT(*) FROM job_applications WHERE user_id = ? AND status = 'interview'", [$userId]),
    'Saved' => jm_dashboard_scalar($pdo, "SELECT COUNT(*) FROM saved_jobs WHERE user_id = ?", [$userId]),
];

$recentApplications = jm_dashboard_rows($pdo, "
    SELECT ja.*, j.title AS job_title, j.city, c.name AS country_name,
           co.name AS company_name
    FROM job_applications ja
    JOIN jobs j ON ja.job_id = j.job_id
    LEFT JOIN countries c ON j.country_id = c.country_id
    JOIN companies co ON j.company_id = co.company_id
    WHERE ja.user_id = ?
    ORDER BY ja.applied_at DESC
    LIMIT 5
", [$userId]);

$recommendedJobs = jm_dashboard_rows($pdo, "
    SELECT j.*, c.name AS country_name, c.currency_symbol,
           co.name AS company_name
    FROM jobs j
    LEFT JOIN countries c ON j.country_id = c.country_id
    JOIN companies co ON j.company_id = co.company_id
    WHERE j.is_active = 1
      AND (j.expires_at IS NULL OR j.expires_at >= CURDATE())
      AND j.job_id NOT IN (SELECT job_id FROM job_applications WHERE user_id = ?)
    ORDER BY j.is_featured DESC, j.posted_at DESC
    LIMIT 5
", [$userId]);

$matchedJobs = [];
try {
    require_once __DIR__ . '/../ai/JobMatcher.php';
    $matcher = new JobMatcher($pdo);
    $matchedJobs = $matcher->getTopMatches($userId, 4);
} catch (Throwable $e) {
    $matchedJobs = [];
}

$profileFields = [
    !empty($displayName) && $displayName !== 'there',
    !empty($user['email'] ?? ''),
    !empty($user['phone'] ?? ''),
    !empty($user['headline'] ?? ''),
    !empty($user['summary'] ?? ''),
];
$profileScore = (int) round((array_sum($profileFields) / count($profileFields)) * 100);
$pageTitle = 'Dashboard | ' . SITE_NAME;
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?></title>
    <link rel="preload" as="font" type="font/woff2" href="/jobmington/assets/fonts/FuturaCyrillicDemi.woff2" crossorigin>
    <link rel="preload" as="font" type="font/woff2" href="/jobmington/assets/fonts/FuturaCyrillicBook.woff2" crossorigin>
    <link rel="stylesheet" href="/jobmington/assets/css/minimal-jobmington.css?v=brand-30">
    <style>
        .jm-dashboard-top {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 260px;
            gap: 32px;
            align-items: end;
            padding-bottom: 48px;
            border-bottom: 1px solid var(--jm-line);
        }
        .jm-stats {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 16px;
            margin-top: 30px;
        }
        .jm-stat {
            border: 1px solid var(--jm-line);
            padding: 20px;
            background: #fff;
        }
        .jm-stat strong {
            display: block;
            color: var(--jm-ink);
            font-size: 32px;
            line-height: 1;
            margin-bottom: 8px;
        }
        .jm-stat span {
            color: var(--jm-muted);
            font-size: 14px;
            font-weight: 600;
        }
        .jm-dashboard-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.2fr) minmax(300px, 0.8fr);
            gap: 36px;
        }
        .jm-pill-row {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 14px;
        }
        @media (max-width: 900px) {
            .jm-dashboard-top,
            .jm-dashboard-grid,
            .jm-stats {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body class="jm-minimal">
    <div class="jm-shell">
        <header class="jm-header">
            <a class="jm-logo" href="/jobmington/">
                <img src="/jobmington/assets/images/badge.png?v=logo-8" alt="">
                <span>Jobmington</span>
            </a>
            <?php require_once __DIR__ . '/../includes/navigation.php'; jm_workspace_nav(['dashboard' => ['/jobmington/seeker/dashboard.php', 'Dashboard'], 'applications' => ['/jobmington/seeker/applications.php', 'Applications'], 'saved' => ['/jobmington/jobs/saved.php', 'Saved jobs'], 'profile' => ['/jobmington/seeker/profile.php', 'Profile']], 'dashboard'); ?>
        </header>

        <?php /* Anything finished on the request that redirected here says so,
                  above the fold. An event registration completed at verification
                  is the reason this exists. Shown once. */
              jm_flash_render(); ?>

        <section class="jm-dashboard-top">
            <div>
                <p class="jm-kicker">Dashboard</p>
                <?php /* Someone who just signed up has never been here, so "Welcome
                          back" is wrong. Flag is set at registration, consumed once. */
                      $isFirstVisit = !empty($_SESSION['is_new_signup']);
                      if ($isFirstVisit) { unset($_SESSION['is_new_signup']); } ?>
                <h1 style="margin:0;color:var(--jm-ink);font-size:52px;line-height:1.05;font-weight:600;"><?= $isFirstVisit ? 'Welcome to Jobmington, ' : 'Welcome back, ' ?><?= e($firstName) ?>.</h1>
                <p style="max-width:680px;margin:22px 0 0;color:var(--jm-muted);">Track your applications, saved roles, and matched jobs from one quiet workspace.</p>
            </div>
            <aside class="jm-panel">
                <h2><?= $profileScore ?>% profile</h2>
                <p>Complete your profile and keep a polished CV ready for applications.</p>
                <div class="jm-form-actions">
                    <a class="jm-button" href="/jobmington/cv-builder/">Open CV Builder</a>
                    <a class="jm-button secondary" href="/jobmington/seeker/profile.php">Update profile</a>
                </div>
            </aside>
        </section>

        <section class="jm-section">
            <div class="jm-stats">
                <?php foreach ($stats as $label => $value): ?>
                    <div class="jm-stat">
                        <strong><?= number_format((int) $value) ?></strong>
                        <span><?= e($label) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="jm-section">
            <style>
            .jm-wallet { display:grid; grid-template-columns:1fr 1fr auto; gap:16px; align-items:stretch; border:1px solid var(--jm-line); border-radius:14px; background:#fff; padding:6px; }
            @media (max-width:760px){ .jm-wallet { grid-template-columns:1fr 1fr; } .jm-wallet-action { grid-column:1 / -1; } }
            @media (max-width:440px){ .jm-wallet { grid-template-columns:1fr; } }
            .jm-wallet-cell { padding:16px 18px; border-radius:10px; }
            .jm-wallet-cell.seeds { background:#f0fdf9; }
            .jm-wallet-cell.credits { background:#eef5ff; }
            .jm-wallet-cell .lbl { display:flex; align-items:center; gap:7px; font-size:11px; font-weight:800; text-transform:uppercase; letter-spacing:.08em; color:var(--jm-muted); margin-bottom:8px; }
            .jm-wallet-cell .lbl svg { width:14px; height:14px; }
            .jm-wallet-cell.seeds .lbl svg { color:var(--jm-green); }
            .jm-wallet-cell.credits .lbl svg { color:var(--jm-blue); }
            .jm-wallet-cell .val { font-size:30px; font-weight:800; letter-spacing:-.03em; color:var(--jm-ink); line-height:1; }
            .jm-wallet-cell .sub { font-size:12px; color:var(--jm-muted); margin-top:6px; }
            .jm-wallet-action { display:flex; flex-direction:column; justify-content:center; gap:8px; padding:16px 18px; }
            .jm-wallet-action .jm-button { justify-content:center; white-space:nowrap; }
            </style>
            <div class="jm-section-head">
                <div>
                    <h2>Your wallet</h2>
                    <p style="margin:4px 0 0;color:var(--jm-muted);">Seeds are earned free; Credits power the premium AI tools. <?= $seedsPerCredit ?> Seeds = 1 Credit.</p>
                </div>
                <a class="jm-muted-link" href="/jobmington/wallet/">History</a>
            </div>
            <div class="jm-wallet">
                <div class="jm-wallet-cell seeds">
                    <span class="lbl">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2C7 7 7 12 12 22 17 12 17 7 12 2z"/></svg>
                        Seeds
                    </span>
                    <div class="val"><?= number_format($seedBalance) ?></div>
                    <div class="sub">Earn by applying, verifying, daily logins &amp; more.</div>
                </div>
                <div class="jm-wallet-cell credits">
                    <span class="lbl">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                        Credits
                    </span>
                    <div class="val"><?= $isPremiumUser ? '&infin;' : number_format($creditBalance) ?></div>
                    <div class="sub"><?= $isPremiumUser ? 'Premium — unlimited tools' : 'Spend on Optimizer, Cover Letter, Cold Pitch.' ?></div>
                </div>
                <div class="jm-wallet-action">
                    <?php if (!$isPremiumUser): ?>
                        <button class="jm-button" id="jm-redeem-btn" onclick="JMWallet.redeem()" <?= $redeemableCredits < 1 ? 'disabled' : '' ?>>
                            Redeem <?= $seedsPerCredit ?> Seeds → 1 Credit
                        </button>
                        <a class="jm-button secondary" href="/jobmington/payments/credits.php">Buy Credits</a>
                    <?php else: ?>
                        <a class="jm-button secondary" href="/jobmington/wallet/">Open wallet</a>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <section class="jm-section">
            <div class="jm-section-head">
                <div>
                    <h2>Career tools</h2>
                    <p style="margin:4px 0 0;color:var(--jm-muted);">AI-powered tools to build, sharpen, and pitch yourself.</p>
                </div>
                <a class="jm-muted-link" href="/jobmington/tools/">See all</a>
            </div>
            <style>
            .jm-dash-tools { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:14px; }
            @media (max-width:900px){ .jm-dash-tools { grid-template-columns:repeat(2,minmax(0,1fr)); } }
            @media (max-width:520px){ .jm-dash-tools { grid-template-columns:1fr; } }
            .jm-dash-tool { display:flex; flex-direction:column; gap:10px; padding:18px; border:1px solid var(--jm-line); border-radius:12px; background:#fff; text-decoration:none; transition:box-shadow .15s, transform .15s, border-color .15s; }
            .jm-dash-tool:hover { box-shadow:0 8px 22px rgba(6,20,38,.08); transform:translateY(-2px); border-color:#c8d8ef; }
            .jm-dash-tool-ico { width:40px; height:40px; border-radius:10px; display:grid; place-items:center; background:#eef5ff; color:var(--jm-blue); }
            .jm-dash-tool b { font-size:14px; font-weight:800; color:var(--jm-ink); }
            .jm-dash-tool span { font-size:12.5px; color:var(--jm-muted); line-height:1.5; }
            </style>
            <div class="jm-dash-tools">
                <?php
                $dashTools = [
                    ['name' => 'CV Builder', 'desc' => 'Build an ATS-friendly CV from polished templates.', 'url' => '/jobmington/cv-builder/', 'icon' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="8" y1="13" x2="16" y2="13"/><line x1="8" y1="17" x2="13" y2="17"/>'],
                    ['name' => 'CV Optimizer', 'desc' => 'Score your CV against ATS criteria and fix it.', 'url' => '/jobmington/ai/roast.php', 'icon' => '<circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/><line x1="11" y1="8" x2="11" y2="14"/><line x1="8" y1="11" x2="14" y2="11"/>'],
                    ['name' => 'Cover Letter AI', 'desc' => 'Tailored cover letter from any job description.', 'url' => '/jobmington/ai/cover-letter.php', 'icon' => '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="M22 7l-10 5L2 7"/>'],
                    ['name' => 'Cold Pitch AI', 'desc' => 'Human cold pitches for email, DM, or LinkedIn.', 'url' => '/jobmington/ai/cold-pitch.php', 'icon' => '<path d="M22 2L11 13"/><path d="M22 2l-7 20-4-9-9-4 20-7z"/>'],
                ];
                foreach ($dashTools as $t): ?>
                    <a class="jm-dash-tool" href="<?= e($t['url']) ?>">
                        <span class="jm-dash-tool-ico">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><?= $t['icon'] ?></svg>
                        </span>
                        <b><?= e($t['name']) ?></b>
                        <span><?= e($t['desc']) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="jm-section">
            <div class="jm-dashboard-grid">
                <div>
                    <div class="jm-section-head">
                        <div>
                            <h2>Matched jobs</h2>
                            <p>Roles ranked against your profile details.</p>
                        </div>
                        <a class="jm-muted-link" href="/jobmington/jobs/">Browse all</a>
                    </div>

                    <?php if (empty($matchedJobs)): ?>
                        <?= jm_empty_state_card('profile_incomplete', [
                            'actions' => '<a class="jm-button" href="/jobmington/seeker/profile.php">Update profile</a>',
                        ]) ?>
                    <?php else: ?>
                        <div class="jm-job-list">
                            <?php foreach ($matchedJobs as $job): ?>
                                <a class="jm-job-row" href="/jobmington/jobs/view.php?id=<?= (int) $job['job_id'] ?>">
                                    <strong>
                                        <?= e($job['title'] ?? 'Open role') ?>
                                        <small style="display:block;color:var(--jm-muted);font-weight:400;margin-top:4px;">
                                            <?= e($job['company'] ?? 'Company') ?> / <?= e($job['location'] ?? 'Remote') ?>
                                        </small>
                                    </strong>
                                    <span><?= (int) ($job['score'] ?? 0) ?>% match</span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <aside>
                    <div class="jm-panel">
                        <h2>Recent applications</h2>
                        <?php if (empty($recentApplications)): ?>
                            <p>No applications yet.</p>
                            <a class="jm-muted-link" href="/jobmington/jobs/">Find your first role</a>
                        <?php else: ?>
                            <div class="jm-stack">
                                <?php foreach ($recentApplications as $application): ?>
                                    <div>
                                        <strong><?= e($application['job_title'] ?? 'Role') ?></strong>
                                        <p style="margin:4px 0;color:var(--jm-muted);"><?= e($application['company_name'] ?? 'Company') ?></p>
                                        <span class="jm-badge"><?= e(ucfirst($application['status'] ?? 'pending')) ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="jm-form-actions">
                                <a class="jm-button secondary" href="/jobmington/seeker/applications.php">View all</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </aside>
            </div>
        </section>

        <section class="jm-section">
            <div class="jm-section-head">
                <div>
                    <h2>Recommended jobs</h2>
                    <p>Fresh roles you have not applied to yet.</p>
                </div>
            </div>

            <?php if (empty($recommendedJobs)): ?>
                <?= jm_empty_state_card('no_jobs_found', [
                    'actions' => '<a class="jm-button" href="/jobmington/jobs/">Browse jobs</a>',
                ]) ?>
            <?php else: ?>
                <div class="jm-job-list">
                    <?php foreach ($recommendedJobs as $job): ?>
                        <?php
                            $salary = (!empty($job['salary_min']) || !empty($job['salary_max']))
                                ? formatSalaryRange($job['salary_min'], $job['salary_max'], $job['currency_symbol'] ?? null)
                                : 'Salary not listed';
                        ?>
                        <a class="jm-job-row" href="/jobmington/jobs/view.php?id=<?= (int) $job['job_id'] ?>">
                            <strong>
                                <?= e($job['title'] ?? 'Open role') ?>
                                <small style="display:block;color:var(--jm-muted);font-weight:400;margin-top:4px;">
                                    <?= e($job['company_name'] ?? 'Company') ?> / <?= e($job['country_name'] ?? 'Remote') ?>
                                </small>
                            </strong>
                            <span><?= e($salary) ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <?php jm_minimal_footer(); ?>
    </div>
    <script>
    const JM_SITE = <?= json_encode(SITE_URL) ?>;
    const JMWallet = {
        redeem: async function () {
            const btn = document.getElementById('jm-redeem-btn');
            if (!btn || btn.disabled) return;
            const orig = btn.textContent;
            btn.disabled = true; btn.textContent = 'Redeeming…';
            try {
                const res = await fetch(`${JM_SITE}/api/redeem-seeds.php`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ credits: 1 })
                });
                const data = await res.json();
                if (data.success) {
                    if (window.JM && JM.toast) JM.toast(data.message, 'success');
                    location.reload();
                } else {
                    (window.JM && JM.toast) ? JM.toast(data.message || 'Could not redeem.', 'error') : alert(data.message || 'Could not redeem.');
                    btn.disabled = false; btn.textContent = orig;
                }
            } catch (e) {
                (window.JM && JM.toast) ? JM.toast('Connection error.', 'error') : alert('Connection error.');
                btn.disabled = false; btn.textContent = orig;
            }
        }
    };
    </script>
</body>
</html>
