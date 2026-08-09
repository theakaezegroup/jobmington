<?php
define('JOBMINGTON', true);
require_once __DIR__ . '/config/env.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/functions.php';

Session::start();

$faqs = [
    'Getting started' => [
        ['How do I create an account?', 'Use Create account, choose whether you want to find jobs or hire talent, then complete the form.'],
        ['What is Jobmington?', 'Jobmington is a simple job board for finding roles and helping employers publish openings.'],
        ['Is Jobmington free for job seekers?', 'Yes. Job seekers can browse roles and apply without paying.'],
    ],
    'Jobs and applications' => [
        ['How do I apply for a job?', 'Open a job listing and use the Apply button. Some jobs use an external application link, while others use the Jobmington application form.'],
        ['Can I save jobs?', 'Yes. Sign in first, then save jobs from the listing page.'],
        ['How do I search by country?', 'Use the country filter on the Find jobs page or the homepage search.'],
    ],
    'Employers' => [
        ['How do I post a job?', 'Open Post a job, sign in with an employer account, and publish the role details.'],
        ['Can I manage applicants?', 'Yes. Employer accounts can use the dashboard to manage listings and review applications.'],
        ['What should a listing include?', 'Use a clear title, company name, country, job type, salary range, and a practical description.'],
    ],
    'Account' => [
        ['How do I reset my password?', 'Use the Forgot password link on the sign-in page.'],
        ['Can I create an employer account directly?', 'Yes. Use Create account with the employer option selected.'],
        ['Who can I contact for help?', 'Use the contact page and send a short message about the issue.'],
    ],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FAQ | Jobmington</title>
    <link rel="stylesheet" href="/jobmington/assets/css/minimal-jobmington.css?v=brand-15">
</head>
<body class="jm-minimal">
    <div class="jm-shell">
        <header class="jm-header">
            <a href="/jobmington/" class="jm-logo"><img src="/jobmington/assets/images/badge.png?v=logo-7" alt=""><span>Jobmington</span></a>
            <nav class="jm-nav" aria-label="Primary">
                <a href="/jobmington/jobs/">Find jobs</a>
                <a href="/jobmington/employer/">Employers</a>
                <a href="/jobmington/auth/login.php">Sign in</a>
                <a href="/jobmington/employer/post-job.php" class="jm-button secondary">Post a job</a>
            </nav>
        </header>

        <section class="jm-hero">
            <div>
                <p class="jm-kicker">FAQ</p>
                <h1>Common questions.</h1>
                <p>Short answers about using Jobmington as a job seeker or employer.</p>
            </div>
            <aside class="jm-panel">
                <h2>Need more help?</h2>
                <p>Send us a message and we will point you in the right direction.</p>
                <a href="/jobmington/contact.php" class="jm-button">Contact us</a>
            </aside>
        </section>

        <section class="jm-section">
            <?php foreach ($faqs as $category => $items): ?>
                <div class="jm-card" style="margin-bottom:24px;">
                    <h2><?= e($category) ?></h2>
                    <div class="jm-job-list">
                        <?php foreach ($items as [$question, $answer]): ?>
                            <details class="jm-job-row" style="display:block;">
                                <summary style="cursor:pointer;font-weight:600;color:var(--jm-ink);"><?= e($question) ?></summary>
                                <p style="margin:12px 0 0;color:var(--jm-muted);"><?= e($answer) ?></p>
                            </details>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </section>

        <?php jm_minimal_footer(); ?>
    </div>
</body>
</html>
