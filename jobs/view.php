<?php
define('JOBMINGTON', true);
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/_job_helpers.php';

Session::start();
// Public on purpose -- see jobs/index.php. The full description stays visible
// so the JobPosting schema matches what a visitor actually sees; only acting
// on the job (apply, save) requires an account.
$pdo = db();
$jobId = (int) get('id', 0);

if ($jobId <= 0) {
    redirect('/jobmington/jobs/');
}

$job = jm_job_find($pdo, $jobId);
if (!$job) {
    http_response_code(404);
    $pageTitle = 'Job not found | ' . SITE_NAME;
    jm_jobs_header($pageTitle, 'jobs');
    ?>
    <section class="jm-section" style="padding-top:0;">
        <?= jm_empty_state_card('job_expired', [
            'title_tag' => 'h1',
            'actions' => '<a class="jm-button" href="/jobmington/jobs/">Browse jobs</a>',
        ]) ?>
    </section>
    <?php
    jm_jobs_footer();
    exit;
}

$pdo->prepare("UPDATE jobs SET views = COALESCE(views, 0) + 1 WHERE job_id = ?")->execute([$jobId]);

$isSaved = false;
$hasApplied = false;
if (Session::isLoggedIn() && !Session::isEmployer()) {
    $stmt = $pdo->prepare("SELECT id FROM saved_jobs WHERE user_id = ? AND job_id = ? LIMIT 1");
    $stmt->execute([Session::userId(), $jobId]);
    $isSaved = (bool) $stmt->fetch();

    $stmt = $pdo->prepare("SELECT application_id FROM job_applications WHERE user_id = ? AND job_id = ? LIMIT 1");
    $stmt->execute([Session::userId(), $jobId]);
    $hasApplied = (bool) $stmt->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_job'])) {
    Session::requireLogin();
    if (Session::isEmployer() && !Session::isAdmin()) {
        redirect('/jobmington/errors/403.php');
    }
    if (Security::verifyCSRF()) {
        $pdo->prepare("INSERT IGNORE INTO saved_jobs (user_id, job_id) VALUES (?, ?)")->execute([Session::userId(), $jobId]);
        Security::regenerateCSRF();
        redirect('/jobmington/jobs/view.php?id=' . $jobId);
    }
}

$relatedJobs = [];
try {
    $stmt = $pdo->prepare("
        SELECT j.*, co.name AS company_name, c.name AS country_name, c.currency_symbol
        FROM jobs j
        JOIN companies co ON j.company_id = co.company_id
        LEFT JOIN countries c ON j.country_id = c.country_id
        WHERE j.job_id <> ?
          AND j.is_active = 1
          AND (j.expires_at IS NULL OR j.expires_at >= CURDATE())
          AND (j.category_id = ? OR j.company_id = ?)
        ORDER BY j.posted_at DESC
        LIMIT 4
    ");
    $stmt->execute([$jobId, $job['category_id'], $job['company_id']]);
    $relatedJobs = $stmt->fetchAll();
} catch (Throwable $e) {
    $relatedJobs = [];
}

$jobTags = jm_job_tags($job);
$matchUserId = Session::isLoggedIn() && !Session::isEmployer() ? (int) Session::userId() : null;
$matchCoach = jm_job_match_coach($pdo, $job, $matchUserId, Session::isLoggedIn());
$interviewKit = jm_job_interview_kit($job);
/* ── Search, sharing and Google-for-Jobs metadata ─────────────────── */
$jobWhere = trim(($job['city'] ?? '') . (!empty($job['city']) && !empty($job['country_name']) ? ', ' : '') . ($job['country_name'] ?? ''));
$isRemote = ($job['job_type'] ?? '') === 'remote';

// preg rather than mb_*: mbstring is not installed on the server.
$plain = trim(preg_replace('/\s+/', ' ', strip_tags((string) $job['description'])));
$snippet = preg_match('/^.{0,150}/us', $plain, $sm) ? rtrim($sm[0]) : '';
if (strlen($plain) > strlen($snippet)) { $snippet .= '…'; }

$jobDesc = trim($job['title'] . ' at ' . $job['company_name']
    . ($jobWhere !== '' ? ' — ' . $jobWhere : ($isRemote ? ' — Remote' : ''))
    . '. ' . $snippet);

$jobCanonical = SITE_URL . '/jobs/view.php?id=' . (int) $job['job_id'];

$employmentType = [
    'full-time'  => 'FULL_TIME',
    'part-time'  => 'PART_TIME',
    'contract'   => 'CONTRACTOR',
    'internship' => 'INTERN',
    'remote'     => 'FULL_TIME',
][$job['job_type'] ?? ''] ?? 'FULL_TIME';

$jobLd = [
    '@context'           => 'https://schema.org/',
    '@type'              => 'JobPosting',
    'title'              => (string) $job['title'],
    'description'        => (string) ($job['description'] ?: $job['title']),
    'datePosted'         => date('Y-m-d', strtotime((string) $job['posted_at'])),
    'employmentType'     => $employmentType,
    'hiringOrganization' => [
        '@type' => 'Organization',
        'name'  => (string) $job['company_name'],
        'sameAs' => SITE_URL,
    ],
    'directApply'        => empty($job['apply_link']),
];
if (!empty($job['expires_at'])) {
    $jobLd['validThrough'] = date('Y-m-d', strtotime((string) $job['expires_at']));
}
if ($isRemote) {
    // Google requires TELECOMMUTE plus where the applicant may be based.
    $jobLd['jobLocationType'] = 'TELECOMMUTE';
    $jobLd['applicantLocationRequirements'] = [
        '@type' => 'Country',
        'name'  => $job['country_name'] ?: 'Africa',
    ];
} elseif ($jobWhere !== '') {
    $jobLd['jobLocation'] = [
        '@type'   => 'Place',
        'address' => array_filter([
            '@type'           => 'PostalAddress',
            'addressLocality' => $job['city'] ?: null,
            'addressCountry'  => $job['country_name'] ?: null,
        ]),
    ];
}
if (!empty($job['salary_min']) || !empty($job['salary_max'])) {
    $jobLd['baseSalary'] = [
        '@type'    => 'MonetaryAmount',
        'currency' => $job['currency_symbol'] ?: 'USD',
        'value'    => array_filter([
            '@type'    => 'QuantitativeValue',
            'minValue' => $job['salary_min'] ? (float) $job['salary_min'] : null,
            'maxValue' => $job['salary_max'] ? (float) $job['salary_max'] : null,
            'unitText' => 'MONTH',
        ]),
    ];
}

$pageTitle = $job['title'] . ' at ' . $job['company_name'] . ' | ' . SITE_NAME;
jm_jobs_header($pageTitle, 'jobs', [
    'description' => $jobDesc,
    'canonical'   => $jobCanonical,
    'ogType'      => 'article',
    'jsonLd'      => $jobLd,
]);
?>

<section class="jm-section jm-job-detail" style="padding-top:0;">
    <?= jm_breadcrumbs([['label' => 'Jobs', 'url' => '/jobmington/jobs/'], ['label' => $job['title']]]) ?>
    <?php
    $companyLogo = jm_company_logo_url($job);
    $salaryText = trim((string) jm_job_salary($job));
    ?>
    <div class="jm-job-detail-hero">
        <div class="jm-job-detail-company">
            <div class="jm-company-mark" data-company="<?= e($job['company_name']) ?>">
                <?php if ($companyLogo !== ''): ?>
                    <img src="<?= e($companyLogo) ?>" alt="<?= e($job['company_name']) ?> logo">
                <?php endif; ?>
            </div>
            <div>
                <p class="jm-kicker"><?= e($job['company_name']) ?></p>
                <h1><?= e($job['title']) ?></h1>
            </div>
        </div>

        <div class="jm-job-facts">
            <span class="jm-job-fact">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                <?= e(jm_job_location($job)) ?>
            </span>
            <span class="jm-job-fact">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/></svg>
                <?= e($job['job_type']) ?>
            </span>
            <span class="jm-job-fact">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="6" y1="20" x2="6" y2="14"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="18" y1="20" x2="18" y2="10"/></svg>
                <?= e($job['experience_level'] ?? 'Entry') ?>
            </span>
            <?php if ($salaryText !== ''): ?>
                <span class="jm-job-fact is-salary">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                    <?= e($salaryText) ?>
                </span>
            <?php endif; ?>
        </div>

        <?php if (!empty($jobTags)): ?>
            <div class="jm-tag-row">
                <?php foreach ($jobTags as $tag): ?>
                    <span class="jm-job-tag <?= e('tone-' . $tag['tone']) ?>"><?= e($tag['label']) ?></span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="jm-job-detail-actions">
            <?php if (!Session::isLoggedIn()): ?>
                <a class="jm-button" href="/jobmington/auth/login.php?redirect=<?= urlencode('/jobmington/jobs/view.php?id=' . (int) $jobId) ?>">Sign in to apply</a>
                <span style="font-size:13px;color:#53667f;">Free account &mdash; we bring you straight back to this role.</span>
            <?php elseif ($hasApplied): ?>
                <a class="jm-button secondary" href="/jobmington/seeker/applications.php">Applied</a>
            <?php else: ?>
                <a class="jm-button" href="/jobmington/jobs/apply.php?id=<?= (int) $jobId ?>">Apply now</a>
            <?php endif; ?>
            <?php if (Session::isLoggedIn() && !Session::isEmployer()): ?>
                <button class="jm-button secondary jm-bookmark-btn-lg"
                        data-bookmark="<?= (int) $jobId ?>"
                        aria-pressed="<?= $isSaved ? 'true' : 'false' ?>"
                        aria-label="<?= $isSaved ? 'Unsave job' : 'Save job' ?>">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg>
                    <span data-bookmark-text><?= $isSaved ? 'Saved' : 'Save job' ?></span>
                </button>
            <?php endif; ?>
        </div>
    </div>

    <div class="jm-job-detail-layout">
        <main class="jm-job-detail-main">
            <article class="jm-panel jm-job-content-card">
                <h2>About the role</h2>
                <?= jm_job_content_html($job['description']) ?>
            </article>

            <?php if (!empty(jm_job_plain_text($job['requirements'] ?? ''))): ?>
                <article class="jm-panel jm-job-content-card">
                    <h2>Requirements</h2>
                    <?= jm_job_content_html($job['requirements']) ?>
                </article>
            <?php endif; ?>

            <?php if (!empty(jm_job_plain_text($job['benefits'] ?? ''))): ?>
                <article class="jm-panel jm-job-content-card">
                    <h2>Benefits</h2>
                    <?= jm_job_content_html($job['benefits']) ?>
                </article>
            <?php endif; ?>

            <article class="jm-panel jm-interview-kit">
                <div class="jm-interview-head">
                    <div>
                        <p class="jm-kicker">Interview prep</p>
                        <h2>Walk in with sharper answers.</h2>
                        <p>Use this as a quick practice sheet before you speak with the employer.</p>
                    </div>
                    <span><?= e($job['experience_level'] ?? 'Role') ?></span>
                </div>

                <?php if (!empty($interviewKit['focus'])): ?>
                    <div class="jm-interview-focus" aria-label="Topics to prepare">
                        <?php foreach ($interviewKit['focus'] as $focus): ?>
                            <span><?= e($focus) ?></span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="jm-interview-grid">
                    <section class="jm-interview-section">
                        <h3>Likely questions</h3>
                        <ol>
                            <?php foreach ($interviewKit['questions'] as $question): ?>
                                <li><?= e($question) ?></li>
                            <?php endforeach; ?>
                        </ol>
                    </section>

                    <section class="jm-interview-section">
                        <h3>Prepare before the call</h3>
                        <ul>
                            <?php foreach ($interviewKit['prepare'] as $item): ?>
                                <li><?= e($item) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </section>
                </div>

                <div class="jm-interview-bottom">
                    <section class="jm-interview-section">
                        <h3>Ask them</h3>
                        <ul>
                            <?php foreach ($interviewKit['ask'] as $question): ?>
                                <li><?= e($question) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </section>

                    <section class="jm-interview-pitch">
                        <span>Practice line</span>
                        <p><?= e($interviewKit['pitch']) ?></p>
                    </section>
                </div>
            </article>
        </main>

        <aside class="jm-job-detail-side">
            <div class="jm-panel jm-job-summary-card">
                <h2>Job summary</h2>
                <div class="jm-key-list">
                    <div><span>Company</span><strong><?= e($job['company_name']) ?></strong></div>
                    <div><span>Location</span><strong><?= e(jm_job_location($job)) ?></strong></div>
                    <div><span>Type</span><strong><?= e($job['job_type']) ?></strong></div>
                    <div><span>Experience</span><strong><?= e($job['experience_level'] ?? 'Entry') ?></strong></div>
                    <div><span>Salary</span><strong><?= e(jm_job_salary($job)) ?></strong></div>
                    <div><span>Posted</span><strong><?= e(formatDate($job['posted_at'])) ?></strong></div>
                    <div><span>Deadline</span><strong><?= e(!empty($job['deadline']) ? formatDate($job['deadline']) : 'Open') ?></strong></div>
                </div>

                <a class="jm-button jm-button-block" href="/jobmington/jobs/apply.php?id=<?= (int) $jobId ?>">Apply now</a>
            </div>

            <div class="jm-panel jm-match-card <?= e('tone-' . $matchCoach['tone']) ?>">
                <div class="jm-match-head">
                    <div>
                        <p class="jm-kicker">Apply smarter</p>
                        <h2><?= e($matchCoach['label']) ?></h2>
                    </div>
                    <div class="jm-match-score" aria-label="<?= $matchCoach['score'] === null ? 'Score unavailable' : e($matchCoach['score'] . ' percent match') ?>">
                        <?php if ($matchCoach['score'] === null): ?>
                            <strong>--</strong>
                        <?php else: ?>
                            <strong><?= (int) $matchCoach['score'] ?></strong><span>%</span>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($matchCoach['score'] !== null): ?>
                    <div class="jm-match-meter" aria-hidden="true">
                        <span style="width: <?= (int) $matchCoach['score'] ?>%;"></span>
                    </div>
                <?php endif; ?>

                <?php if (!empty($matchCoach['matched_skills']) || !empty($matchCoach['missing_skills'])): ?>
                    <div class="jm-match-tags">
                        <?php foreach ($matchCoach['matched_skills'] as $skill): ?>
                            <span class="jm-match-tag matched"><?= e($skill) ?></span>
                        <?php endforeach; ?>
                        <?php foreach ($matchCoach['missing_skills'] as $skill): ?>
                            <span class="jm-match-tag missing"><?= e($skill) ?></span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="jm-match-block">
                    <h3>Before you apply</h3>
                    <ul>
                        <?php foreach ($matchCoach['coaching'] as $tip): ?>
                            <li><?= e($tip) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <div class="jm-match-note">
                    <span>Opening line</span>
                    <p><?= e($matchCoach['cover_note']) ?></p>
                </div>

                <a class="jm-button secondary jm-button-block" href="<?= e($matchCoach['cta_url']) ?>"><?= e($matchCoach['cta_label']) ?></a>
            </div>

            <div class="jm-panel jm-company-card">
                <h2>About <?= e($job['company_name']) ?></h2>
                <p><?= e($job['company_description'] ?: 'This employer is hiring on Jobmington.') ?></p>
                <?php if (!empty($job['company_website'])): ?>
                    <a class="jm-muted-link" href="<?= e($job['company_website']) ?>" target="_blank" rel="noopener">Visit website</a>
                <?php endif; ?>
            </div>
        </aside>
    </div>
</section>

<?php if (!empty($relatedJobs)): ?>
    <section class="jm-section">
        <div class="jm-section-head">
            <?php require_once __DIR__ . '/../includes/header_ads.php'; jm_ad_inline(); ?>
            <div>
                <h2>Related jobs.</h2>
                <p>More roles from this company or category.</p>
            </div>
        </div>
        <div class="jm-job-list">
            <?php foreach ($relatedJobs as $related): ?>
                <a class="jm-job-row" href="/jobmington/jobs/view.php?id=<?= (int) $related['job_id'] ?>">
                    <strong>
                        <?= e($related['title']) ?>
                        <small class="jm-job-subtitle">
                            <?= e($related['company_name']) ?> / <?= e(jm_job_location($related)) ?>
                        </small>
                        <small class="jm-tag-row compact">
                            <?php foreach (jm_job_tags($related, 3) as $tag): ?>
                                <span class="jm-job-tag <?= e('tone-' . $tag['tone']) ?>"><?= e($tag['label']) ?></span>
                            <?php endforeach; ?>
                        </small>
                    </strong>
                    <span><?= e(jm_job_salary($related)) ?><br><?= e(timeAgo($related['posted_at'])) ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<?php jm_jobs_footer('/jobmington/jobs/', 'Browse jobs'); ?>
<script>
(function () {
    // Resolve a company logo from its name: Clearbit's public autocomplete
    // (CORS-enabled, no key) maps name -> domain, then Google's favicon service
    // gives the logo. Runs only for marks without a server-provided logo.
    function resolveByName(mark, name) {
        if (!name) { mark.remove(); return; }
        fetch('https://autocomplete.clearbit.com/v1/companies/suggest?query=' + encodeURIComponent(name))
            .then(function (r) { return r.ok ? r.json() : []; })
            .then(function (list) {
                var hit = (Array.isArray(list) && list.length) ? list[0] : null;
                if (!hit || !hit.domain) { mark.remove(); return; }
                var img = new Image();
                img.alt = name + ' logo';
                img.onload = function () { mark.innerHTML = ''; mark.appendChild(img); };
                img.onerror = function () { mark.remove(); };
                img.src = 'https://www.google.com/s2/favicons?domain=' + encodeURIComponent(hit.domain) + '&sz=128';
            })
            .catch(function () { mark.remove(); });
    }

    document.querySelectorAll('.jm-company-mark[data-company]').forEach(function (mark) {
        var name = mark.getAttribute('data-company') || '';
        var img = mark.querySelector('img');
        if (img) {
            img.addEventListener('error', function () { resolveByName(mark, name); });
        } else {
            resolveByName(mark, name);
        }
    });

    document.querySelectorAll('.jm-bookmark-btn-lg[data-bookmark], .jm-bookmark-btn[data-bookmark]').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault(); e.stopPropagation();
            var id = btn.dataset.bookmark;
            btn.disabled = true;
            fetch('/jobmington/jobs/saved.php?action=toggle&job_id=' + id, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d.success) {
                    var saved = d.data.saved;
                    btn.setAttribute('aria-pressed', saved ? 'true' : 'false');
                    btn.setAttribute('aria-label', saved ? 'Unsave job' : 'Save job');
                    var txt = btn.querySelector('[data-bookmark-text]');
                    if (txt) txt.textContent = saved ? 'Saved' : 'Save job';
                    if (window.JM && JM.toast) { JM.toast(d.message, 'success'); }
                    if (window.JM && JM.sound) { JM.sound('save'); }
                } else if (window.JM && JM.toast) {
                    JM.toast(d.message || 'That did not save.', 'error');
                }
            })
            .catch(function () {
                if (window.JM && JM.toast) { JM.toast('Check your connection and try again.', 'error'); }
            })
            .finally(function () { btn.disabled = false; });
        });
    });
})();
</script>
