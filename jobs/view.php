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
$pageTitle = $job['title'] . ' | ' . SITE_NAME;
jm_jobs_header($pageTitle, 'jobs');
?>

<section class="jm-section jm-job-detail" style="padding-top:0;">
    <div class="jm-job-detail-hero">
        <div class="jm-job-detail-company">
            <div class="jm-company-mark">
                <?php if (!empty($job['company_logo'])): ?>
                    <img src="<?= e($job['company_logo']) ?>" alt="">
                <?php else: ?>
                    <span><?= e(strtoupper(substr($job['company_name'], 0, 1))) ?></span>
                <?php endif; ?>
            </div>
            <div>
                <p class="jm-kicker"><?= e($job['company_name']) ?></p>
                <h1><?= e($job['title']) ?></h1>
                <p class="jm-job-detail-meta">
                    <?= e(jm_job_location($job)) ?> / <?= e($job['job_type']) ?> / <?= e($job['experience_level'] ?? 'Entry') ?>
                </p>
            </div>
        </div>

        <?php if (!empty($jobTags)): ?>
            <div class="jm-tag-row">
                <?php foreach ($jobTags as $tag): ?>
                    <span class="jm-job-tag <?= e('tone-' . $tag['tone']) ?>"><?= e($tag['label']) ?></span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="jm-job-detail-actions">
            <?php if ($hasApplied): ?>
                <a class="jm-button secondary" href="/jobmington/seeker/applications.php">Applied</a>
            <?php else: ?>
                <a class="jm-button" href="/jobmington/jobs/apply.php?id=<?= (int) $jobId ?>">Apply now</a>
            <?php endif; ?>
            <?php if (Session::isLoggedIn() && !Session::isEmployer() && !$isSaved): ?>
                <form method="post" style="margin:0;">
                    <?= Security::csrfField() ?>
                    <button class="jm-button secondary" type="submit" name="save_job" value="1">Save job</button>
                </form>
            <?php elseif ($isSaved): ?>
                <a class="jm-button secondary" href="/jobmington/jobs/saved.php">Saved</a>
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
