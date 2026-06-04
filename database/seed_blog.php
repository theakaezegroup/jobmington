<?php
/**
 * JOBMINGTON - Real blog post seeder (idempotent, text only).
 * Cover images are produced by database/generate_graphics.php (GD).
 *     php database/seed_blog.php
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only\n"); }

define('JOBMINGTON', true);
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';

$pdo = db();

function sb_slug(string $s): string {
    return strtolower(substr(trim(preg_replace('/[^a-z0-9]+/i', '-', $s), '-'), 0, 200)) ?: 'post';
}
function sb_category(PDO $pdo, string $name): int {
    $stmt = $pdo->prepare("SELECT id FROM blog_categories WHERE name LIKE ? LIMIT 1");
    $stmt->execute(['%' . $name . '%']);
    $id = (int) $stmt->fetchColumn();
    if ($id) return $id;
    $slug = sb_slug($name);
    $pdo->prepare("INSERT INTO blog_categories (name, slug) VALUES (?, ?)")->execute([$name, $slug]);
    return (int) $pdo->lastInsertId();
}

$authorId = (int) ($pdo->query("SELECT user_id FROM users WHERE user_type = 'admin' ORDER BY user_id LIMIT 1")->fetchColumn()
    ?: $pdo->query("SELECT user_id FROM users ORDER BY user_id LIMIT 1")->fetchColumn());

$posts = [
    [
        'title' => 'How to Land Your First Remote Job from Africa',
        'category' => 'Job Search',
        'excerpt' => 'A clear, practical path to your first remote role — where to look, how to stand out, and the application habits that actually get replies.',
        'content' => "<p>Remote work has opened doors that were closed to African talent a decade ago. You can now work for a company in Berlin or San Francisco from Lagos, Nairobi, or Accra — and get paid in stronger currencies. But the competition is global, so you need to be deliberate.</p>
<h2>1. Look in the right places</h2>
<p>Don't scatter applications across random sites. Focus where remote-first employers actually hire: curated remote boards, company career pages, LinkedIn with the right filters, and Jobmington's job alerts tuned to your skills. Applying within 24 hours of a posting dramatically improves your odds.</p>
<h2>2. Position yourself, even with little experience</h2>
<p>Everyone starts somewhere. Reframe side projects, volunteering, coursework, and freelance gigs as real experience. Lead with outcomes, not titles. A single strong portfolio piece often beats a long, vague CV.</p>
<h2>3. Apply so you actually get replies</h2>
<p>Tailor every application. Mirror the language of the job description, address the top three requirements directly, and open with one specific line that proves you read the post. Generic applications are invisible.</p>
<h2>4. Treat it like a system</h2>
<p>Fix your CV and LinkedIn in week one. Set alerts and build a portfolio piece in week two. Apply to five tailored roles a day and follow up in week three. Practise interviews in week four. Consistency beats intensity.</p>
<p>Your first remote job is the hardest to get. After that, momentum is on your side.</p>",
    ],
    [
        'title' => '5 CV Mistakes That Get You Rejected (And How to Fix Them)',
        'category' => 'Career Advice',
        'excerpt' => 'Recruiters spend seconds on a first scan. These five common mistakes get CVs binned — and each one is quick to fix.',
        'content' => "<p>Your CV has one job: get you the interview. Most CVs fail not because the person is unqualified, but because of avoidable mistakes. Here are the five we see most.</p>
<h2>1. Listing duties instead of results</h2>
<p>\"Responsible for social media\" tells a recruiter nothing. \"Grew Instagram following from 2,000 to 15,000 in six months\" tells them everything. Use the formula: action verb + what you did + measurable result.</p>
<h2>2. Ignoring the ATS</h2>
<p>Most CVs are filtered by software before a human sees them. Use a clean, single-column layout, standard headings, no tables or images, and mirror the exact keywords from the job description — truthfully.</p>
<h2>3. A weak or missing summary</h2>
<p>The first three lines decide whether a recruiter keeps reading. Write a sharp two-to-three line summary: who you are, your strongest proof, and what you want — tailored to the role.</p>
<h2>4. One CV for every job</h2>
<p>Sending the same CV everywhere is the fastest way to get ignored. Spend ten minutes tailoring the summary and top bullets to each role. It is the highest-return ten minutes in your job search.</p>
<h2>5. Small but fatal details</h2>
<p>Typos, an unprofessional email address, inconsistent formatting, and a vague file name like \"CV final 2.pdf\". Fix these and you are already ahead of most applicants. Save it as \"Firstname-Lastname-CV.pdf\".</p>",
    ],
    [
        'title' => 'Getting Paid in USD: A Guide for African Freelancers',
        'category' => 'Remote Work',
        'excerpt' => 'You won the client — now make sure you actually get paid. A practical guide to receiving and managing international payments from Africa.',
        'content' => "<p>Landing international clients is only half the battle. Getting paid reliably — and keeping your earnings safe from inflation — is what turns freelancing into a real income.</p>
<h2>The main options</h2>
<p>Three tools cover most African freelancers: <strong>Payoneer</strong> (widely accepted, gives you receiving accounts in USD/EUR/GBP), <strong>Wise</strong> (great exchange rates and multi-currency balances), and <strong>Grey</strong> (built for Africans, with virtual foreign accounts). Many freelancers use a combination.</p>
<h2>Always take a deposit</h2>
<p>With any new client, agree a deposit of 30–50% before you start work. It protects your time and filters out clients who were never serious. Professionals ask for deposits; it signals that you run a real business.</p>
<h2>Invoice clearly</h2>
<p>Send a simple, itemised invoice with your rate, the scope, the due date, and your payment details. Keep a record of every invoice. Clear paperwork gets you paid faster and protects you if there is a dispute.</p>
<h2>Protect your earnings</h2>
<p>If your local currency loses value quickly, holding part of your income in USD can protect your savings. Earning globally is a powerful hedge — use it deliberately.</p>
<h2>Watch for red flags</h2>
<p>Clients who refuse deposits, rush you, or ask for free \"tests\" are warning signs. Use contracts, agree scope in writing, and never hand over final files before final payment.</p>",
    ],
    [
        'title' => 'Breaking Into Tech Without a Degree in 2026',
        'category' => 'Tech',
        'excerpt' => 'You do not need a Computer Science degree to work in tech. Here is a realistic, step-by-step roadmap from Africa.',
        'content' => "<p>Some of the best people in tech are self-taught. Employers increasingly hire for proven skill, not paper. What you need is a focused plan, free resources, and the discipline to build real things.</p>
<h2>Pick one track</h2>
<p>Don't try to learn everything. Choose one path to start: front-end (HTML, CSS, JavaScript), data (SQL, Python, analysis), QA/testing, or tech support moving toward engineering. Go deep before you go wide.</p>
<h2>Learn for free</h2>
<p>freeCodeCamp, The Odin Project, CS50, and YouTube cover almost everything at no cost. The key is to follow a structured curriculum to the end rather than jumping between random tutorials.</p>
<h2>Build proof</h2>
<p>Knowledge alone won't get you hired — you need evidence. Build two or three real projects and put them on GitHub. A small, finished, deployed project beats a dozen half-done tutorials.</p>
<h2>Get visible</h2>
<p>A simple portfolio, a clean GitHub, and a LinkedIn that tells your story. Write about what you build — it compounds and attracts opportunities you didn't apply for.</p>
<h2>Land the first role</h2>
<p>Apply for junior, internship, and apprenticeship roles. Contribute to open source. Be active in communities. The first role is the hardest; after that, experience opens doors.</p>",
    ],
    [
        'title' => "How to Answer 'Tell Me About Yourself'",
        'category' => 'Career Advice',
        'excerpt' => 'The most common interview opener — and the one most people get wrong. Here is a simple framework that works every time.',
        'content' => "<p>\"Tell me about yourself\" is almost always the first question. It feels casual, but it sets the tone for the whole interview. The mistake most people make is treating it like a life story.</p>
<h2>It's not your biography</h2>
<p>The interviewer doesn't want your childhood or a list of every job you've had. They want a focused, confident answer that shows why you're right for <em>this</em> role.</p>
<h2>The three-part formula</h2>
<p>Keep it to about 45 seconds, in three beats:</p>
<ul>
<li><strong>Who you are professionally</strong> — your current role or focus in one line.</li>
<li><strong>A proof point or two</strong> — a relevant achievement that builds credibility.</li>
<li><strong>Why this role</strong> — a sentence connecting your experience to the job you're interviewing for.</li>
</ul>
<h2>Rehearse, but don't memorise</h2>
<p>Practise out loud until it flows, but don't recite it word for word — you'll sound robotic. Aim for confident and natural. End on why you're excited about the opportunity, and you'll start the interview on the front foot.</p>",
    ],
    [
        'title' => 'Remote Work Tools Every African Professional Should Know',
        'category' => 'Remote Work',
        'excerpt' => 'The software that makes remote work smooth — communication, collaboration, and getting paid. A starter toolkit.',
        'content' => "<p>Working remotely well isn't just about discipline — it's about using the right tools so you can collaborate across timezones and look professional doing it.</p>
<h2>Communication</h2>
<p><strong>Slack</strong> and <strong>Microsoft Teams</strong> for team chat, <strong>Zoom</strong> and <strong>Google Meet</strong> for calls. Learn the etiquette: keep your status updated, over-communicate in writing, and default to async when timezones don't overlap.</p>
<h2>Collaboration</h2>
<p><strong>Google Workspace</strong> and <strong>Notion</strong> for documents and notes, <strong>Trello</strong> or <strong>Asana</strong> for tasks, <strong>Figma</strong> for design, and <strong>GitHub</strong> for code. Being comfortable in these tools is often assumed in remote roles.</p>
<h2>Getting paid</h2>
<p><strong>Payoneer</strong>, <strong>Wise</strong>, and <strong>Grey</strong> for receiving international payments in USD and other currencies. Set these up early so you're ready the moment a client wants to pay.</p>
<h2>Staying productive</h2>
<p>A reliable internet backup (a second SIM or router), a noise-cancelling headset, and a simple daily routine matter more than any app. The best tool is the habit of showing up every day.</p>",
    ],
];

$made = 0;
foreach ($posts as $p) {
    $slug = sb_slug($p['title']);
    $exists = $pdo->prepare("SELECT post_id FROM blog_posts WHERE slug = ? LIMIT 1");
    $exists->execute([$slug]);
    if ($exists->fetchColumn()) continue;

    $pdo->prepare("INSERT INTO blog_posts (category_id, author_id, title, slug, excerpt, content, is_published, published_at)
        VALUES (?,?,?,?,?,?,1,NOW())")
        ->execute([sb_category($pdo, $p['category']), $authorId, $p['title'], $slug, $p['excerpt'], $p['content']]);
    $made++;
}

echo "Blog posts added: $made\n";
