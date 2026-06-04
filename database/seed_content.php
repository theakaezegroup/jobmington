<?php
/**
 * JOBMINGTON - Real content seeder (Courses, Events, Ebooks).
 *
 * Idempotent (matches on slug). Ebook PDFs + covers are generated on disk, so
 * this must run on each server (uploads/* is git-ignored):
 *     php database/seed_content.php
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only\n"); }

define('JOBMINGTON', true);
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';

$pdo  = db();
$root = dirname(__DIR__);

function sc_slug(string $s): string {
    return substr(trim(preg_replace('/[^a-z0-9]+/i', '-', $s), '-'), 0, 200) ?: 'item';
}

/** Resolve a course category id by trying name/slug candidates; fall back to any. */
function sc_category(PDO $pdo, array $candidates): ?int {
    foreach ($candidates as $c) {
        $stmt = $pdo->prepare("SELECT id FROM course_categories WHERE name LIKE ? OR slug LIKE ? LIMIT 1");
        $stmt->execute(['%' . $c . '%', '%' . sc_slug($c) . '%']);
        $id = (int) $stmt->fetchColumn();
        if ($id) return $id;
    }
    $any = (int) $pdo->query("SELECT id FROM course_categories ORDER BY id LIMIT 1")->fetchColumn();
    return $any ?: null;
}

/* ─────────────────────────────  COURSES  ───────────────────────────── */
$courses = [
    [
        'title' => 'CV & Resume Masterclass', 'cat' => ['Career', 'career'],
        'desc' => 'Build a sharp, ATS-friendly CV that gets you shortlisted. Learn structure, wording, and the mistakes that get CVs binned.',
        'short' => 'Build an ATS-friendly CV that gets you shortlisted.',
        'instructor' => 'Jobmington Academy', 'difficulty' => 'beginner', 'hours' => 2.5, 'cert' => 1, 'featured' => 1,
        'modules' => [
            ['Anatomy of a winning CV', 'A recruiter spends 7 seconds on a first scan. Learn the sections that matter — headline, summary, experience, skills — and the order that works.'],
            ['Writing achievement bullets', 'Turn duties into results. Use the formula: action verb + what you did + measurable outcome. We rewrite weak bullets into strong ones together.'],
            ['Beating the ATS', 'Most CVs are filtered by software before a human sees them. Learn how to mirror the job description, use the right keywords, and keep formatting clean.'],
            ['Final polish & templates', 'Length, fonts, file names, and the 12-point checklist to run before every application.'],
        ],
    ],
    [
        'title' => 'Ace the Remote Job Interview', 'cat' => ['Career', 'career'],
        'desc' => 'Walk into any remote interview prepared and calm. Master the STAR method, remote-specific questions, and how to stand out on video.',
        'short' => 'Master remote interviews with structured, confident answers.',
        'instructor' => 'Jobmington Academy', 'difficulty' => 'beginner', 'hours' => 2.0, 'cert' => 1, 'featured' => 0,
        'modules' => [
            ['Before the call', 'Research the company, test your setup, and prepare your environment. A checklist for sound, lighting, and backup internet.'],
            ['The STAR method', 'Structure behavioural answers with Situation, Task, Action, Result. Practise with 8 common prompts.'],
            ['Remote-specific questions', 'How do you stay productive at home? How do you communicate across timezones? Answer the questions remote employers actually ask.'],
            ['Questions to ask them', 'Smart questions that show you think like an owner — and help you spot a bad employer early.'],
        ],
    ],
    [
        'title' => 'Freelancing Fundamentals for African Talent', 'cat' => ['Business', 'Entrepreneur', 'business'],
        'desc' => 'Start earning in dollars as a freelancer. Pick a service, set your rates, land your first clients, and get paid reliably from anywhere in Africa.',
        'short' => 'Start freelancing and earn in dollars from anywhere.',
        'instructor' => 'Jobmington Academy', 'difficulty' => 'beginner', 'hours' => 3.0, 'cert' => 1, 'featured' => 1,
        'modules' => [
            ['Choosing your service', 'Match a skill you have (or can learn fast) to what clients pay for. The difference between a hobby and a sellable service.'],
            ['Setting your rates', 'Hourly vs project pricing, how to price as a beginner without underselling, and raising rates as you grow.'],
            ['Finding your first clients', 'Upwork, LinkedIn, Twitter, and cold outreach that works. How to write a proposal that gets replies.'],
            ['Getting paid', 'Receiving USD in Africa — Payoneer, Wise, and Grey. Invoicing, deposits, and avoiding non-payment.'],
            ['Avoiding scams', 'The red flags of fake clients and "tests", and how to protect your time and money.'],
        ],
    ],
    [
        'title' => 'Land Your First Remote Job', 'cat' => ['Career', 'career'],
        'desc' => 'A step-by-step path from zero to your first remote role: where to look, how to position yourself, and how to apply so you actually get replies.',
        'short' => 'A step-by-step path to your first remote role.',
        'instructor' => 'Jobmington Academy', 'difficulty' => 'beginner', 'hours' => 2.5, 'cert' => 1, 'featured' => 0,
        'modules' => [
            ['Where the remote jobs are', 'The best job boards and how to use Jobmington alerts, plus the roles most open to African talent.'],
            ['Building a remote-ready profile', 'LinkedIn, a simple portfolio, and a Talent Passport that makes employers trust you.'],
            ['Applications that convert', 'Tailoring each application, the 2-line opener that gets noticed, and following up the right way.'],
            ['Your first 30 days', 'Onboarding remotely, communicating well, and turning a probation period into a long-term role.'],
        ],
    ],
    [
        'title' => 'Personal Finance for Young Professionals', 'cat' => ['Finance', 'Business', 'business'],
        'desc' => 'Take control of your money. Budgeting that survives real life, building an emergency fund, and protecting your earnings against inflation.',
        'short' => 'Budget, save, and protect your earnings.',
        'instructor' => 'Jobmington Academy', 'difficulty' => 'beginner', 'hours' => 1.5, 'cert' => 0, 'featured' => 0,
        'modules' => [
            ['Budgeting that works', 'A simple percentage-based budget you can actually stick to, even with an irregular freelance income.'],
            ['Your emergency fund', 'Why 3-6 months of expenses changes everything, and how to build it on any income.'],
            ['Earning and saving in USD', 'Protecting your money against local inflation and currency swings.'],
        ],
    ],
    // External free courses (link out to genuinely real resources)
    [
        'title' => 'Responsive Web Design (freeCodeCamp)', 'cat' => ['Technology', 'tech'],
        'desc' => 'Learn HTML and CSS by building real projects. A free, hands-on certification from freeCodeCamp — perfect for starting in tech.',
        'short' => 'Learn HTML & CSS by building real projects.',
        'instructor' => 'freeCodeCamp', 'difficulty' => 'beginner', 'hours' => 30.0, 'cert' => 0, 'featured' => 0,
        'external' => 'https://www.freecodecamp.org/learn/2022/responsive-web-design/',
    ],
    [
        'title' => 'Data Analysis with Python (freeCodeCamp)', 'cat' => ['Technology', 'tech'],
        'desc' => 'Go from spreadsheets to real data analysis with Python, Pandas, and NumPy. A free certification course with projects.',
        'short' => 'Real data analysis with Python and Pandas.',
        'instructor' => 'freeCodeCamp', 'difficulty' => 'intermediate', 'hours' => 30.0, 'cert' => 0, 'featured' => 0,
        'external' => 'https://www.freecodecamp.org/learn/data-analysis-with-python/',
    ],
    [
        'title' => 'Fundamentals of Digital Marketing (Google)', 'cat' => ['Marketing', 'digital', 'business'],
        'desc' => "Google's free, certificate-bearing course covering SEO, search ads, social, and analytics — a strong base for a marketing career.",
        'short' => 'Google\'s free digital marketing certificate.',
        'instructor' => 'Google', 'difficulty' => 'beginner', 'hours' => 40.0, 'cert' => 0, 'featured' => 0,
        'external' => 'https://learndigital.withgoogle.com/digitalgarage/course/digital-marketing',
    ],
];

$cMade = 0;
foreach ($courses as $c) {
    $slug = sc_slug($c['title']);
    $exists = $pdo->prepare("SELECT course_id FROM courses WHERE slug = ? LIMIT 1");
    $exists->execute([$slug]);
    if ($exists->fetchColumn()) continue;

    $isExternal = !empty($c['external']);
    $pdo->prepare("INSERT INTO courses
        (category_id, title, slug, description, short_description, instructor_name, course_type,
         external_url, is_external, duration_hours, difficulty, is_free, price, has_certificate,
         certificate_provider, is_published, is_featured)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,1,?)")
        ->execute([
            sc_category($pdo, $c['cat']), $c['title'], $slug, $c['desc'], $c['short'], $c['instructor'],
            $isExternal ? 'video' : 'full-course', $c['external'] ?? null, $isExternal ? 1 : 0,
            $c['hours'], $c['difficulty'], 1, 0, $c['cert'], $isExternal ? $c['instructor'] : 'Jobmington',
            $c['featured'],
        ]);
    $courseId = (int) $pdo->lastInsertId();

    if (!empty($c['modules'])) {
        $order = 1;
        foreach ($c['modules'] as $m) {
            $pdo->prepare("INSERT INTO course_modules (course_id, title, description, content, duration_minutes, sort_order, is_free_preview) VALUES (?,?,?,?,?,?,?)")
                ->execute([$courseId, $m[0], $m[1], $m[1], 20, $order, $order === 1 ? 1 : 0]);
            $order++;
        }
    }
    $cMade++;
}

/* ─────────────────────────────  EVENTS  ───────────────────────────── */
$events = [
    ['Landing Your First Remote Job in 2026', 'webinar', '2026-06-18 16:00:00', 'Jobmington Team',
     "A practical, no-fluff session on getting hired remotely this year. We'll cover where the real opportunities are, how to position yourself when you have little experience, and the application habits that get replies. Live Q&A at the end."],
    ['Live CV Clinic: Get Yours Reviewed', 'workshop', '2026-06-25 16:00:00', 'Jobmington Academy',
     "Bring your CV and we'll review real submissions live, on the call. See exactly what recruiters notice first, the wording that works, and quick fixes that make a big difference. Submit your CV when you register."],
    ['Breaking Into Tech Without a Degree', 'webinar', '2026-07-02 16:00:00', 'Guest: Senior Engineer',
     "You don't need a Computer Science degree to work in tech. We map the realistic paths — front-end, data, QA, support-to-engineer — the free resources to learn, and how to land that first role from Africa."],
    ['Freelancing 101: Finding Clients Abroad', 'webinar', '2026-07-16 16:00:00', 'Jobmington Team',
     "How to find and win international clients as an African freelancer. Platforms vs direct outreach, writing proposals that convert, pricing in USD, and getting paid reliably with Payoneer and Wise."],
    ['Salary Negotiation for Remote Roles', 'webinar', '2026-07-30 16:00:00', 'Jobmington Academy',
     "Most people leave money on the table. Learn how to research fair pay for remote roles, talk numbers with confidence, and negotiate offers — including in USD — without losing the opportunity."],
    ['Build a Standout LinkedIn Profile', 'workshop', '2026-08-13 16:00:00', 'Jobmington Team',
     "Your LinkedIn is your storefront. In this hands-on workshop we rebuild your headline, About section, and experience so recruiters reaching out to you becomes the norm, not the exception."],
];

$eMade = 0;
foreach ($events as $ev) {
    $slug = sc_slug($ev[0]);
    $exists = $pdo->prepare("SELECT event_id FROM events WHERE slug = ? LIMIT 1");
    $exists->execute([$slug]);
    if ($exists->fetchColumn()) continue;

    $pdo->prepare("INSERT INTO events
        (title, slug, description, event_type, host_name, starts_at, ends_at, timezone, is_online,
         location, meeting_url, capacity, is_free, price, is_published, is_featured)
        VALUES (?,?,?,?,?,?,?,?,1,?,?,?,1,0,1,?)")
        ->execute([
            $ev[0], $slug, $ev[4], $ev[1], $ev[3], $ev[2],
            date('Y-m-d H:i:s', strtotime($ev[2] . ' +90 minutes')), 'Africa/Lagos',
            'Online (Zoom)', 'https://us02web.zoom.us/j/jobmington', 500,
            $slug === sc_slug($events[0][0]) ? 1 : 0,
        ]);
    $eMade++;
}

/* ─────────────────────────────  EBOOKS  ───────────────────────────── */
require_once __DIR__ . '/seed_ebook_lib.php';

$brandFont = is_file($root . '/assets/fonts/FuturaCyrillicDemi.ttf') ? $root . '/assets/fonts/FuturaCyrillicDemi.ttf' : null;

$ebooks = [
    [
        'title' => 'The Remote Job Seeker\'s Handbook', 'author' => 'Jobmington', 'category' => 'Career',
        'desc' => 'Everything you need to find, apply for, and land a remote job from Africa — where to look, how to stand out, and how to avoid the common traps.',
        'sections' => [
            ['Why remote, and why now', "Remote work has quietly become the biggest opportunity for African talent in a generation. You can earn in stronger currencies, work for global teams, and build a career without leaving home. But the competition is global too — so you need to be deliberate.\n\nThis handbook is the practical playbook: no theory you can't use."],
            ['Where remote jobs actually are', "Don't spray-and-pray across random boards. Focus where remote-friendly employers post:\n\n- Curated remote boards (We Work Remotely, RemoteOK, Remotive)\n- Company career pages of remote-first companies\n- LinkedIn with the right filters\n- Jobmington job alerts, tuned to your skills\n\nSet alerts so the jobs come to you. Applying within 24 hours of a posting dramatically improves your odds."],
            ['Positioning when you lack experience', "Everyone starts somewhere. Reframe what you have: side projects, volunteering, coursework, and freelance gigs all count. Lead with outcomes, not titles. A clear portfolio or a single strong project often beats a long, vague CV."],
            ['Applications that get replies', "Tailor every application. Mirror the language of the job description, address the top 3 requirements directly, and open with one specific line that proves you read the post. Generic applications are invisible."],
            ['Interviewing remotely', "Test your setup before the call. Use the STAR method for behavioural questions. Have two or three thoughtful questions ready — they signal that you think like an owner."],
            ['Avoiding scams', "If an 'employer' asks you to pay for training, equipment, or a 'background check', walk away. Real employers pay you. Verify the company, the email domain, and never share banking details before a signed offer."],
            ['Your 30-day action plan', "Week 1: fix your CV and LinkedIn.\nWeek 2: set alerts, build or polish one portfolio piece.\nWeek 3: apply to 5 tailored roles a day, follow up.\nWeek 4: practise interviews, refine, repeat.\n\nConsistency beats intensity. Show up every day."],
        ],
    ],
    [
        'title' => '50 Interview Questions & Winning Answers', 'author' => 'Jobmington', 'category' => 'Career',
        'desc' => 'The questions you will actually be asked — with frameworks and example answers for behavioural, remote-specific, and tough situational questions.',
        'sections' => [
            ['How to use this guide', "Don't memorise answers word for word — you'll sound robotic. Instead, learn the framework behind each answer and adapt it with your own stories. Prepare 5-6 real examples from your life that you can flex to many questions."],
            ['The STAR framework', "For any behavioural question, answer in four beats:\nSituation - set the scene briefly.\nTask - what needed to happen.\nAction - what YOU specifically did.\nResult - the measurable outcome.\n\nKeep it to 60-90 seconds. End on the result."],
            ['Tell me about yourself', "This is not your life story. Give a 3-part answer: who you are professionally, a proof point or two, and why you're excited about THIS role. 45 seconds, confident, rehearsed."],
            ['Behavioural questions (with answers)', "1. Tell me about a time you solved a hard problem.\n2. Describe a conflict and how you handled it.\n3. A time you failed — and what you learned.\n4. When you had to learn something fast.\n5. A time you took initiative.\n\nFor each, prepare a STAR story in advance."],
            ['Remote-specific questions', "How do you stay productive at home? How do you communicate across timezones? How do you handle being blocked when no one is online? Answer with concrete systems and tools, not vibes."],
            ['Questions about money', "When asked your salary expectations, give a researched range, not a single number, and anchor on the value you bring. It's fine to ask what range the role is budgeted for."],
            ['Questions to ask them', "Always have questions. Good ones: What does success look like in 90 days? How does the team communicate? What's the biggest challenge facing the team right now? Never say 'no, I'm good.'"],
        ],
    ],
    [
        'title' => 'The African Freelancer\'s Starter Guide', 'author' => 'Jobmington', 'category' => 'Business',
        'desc' => 'Go from zero to your first paying client. Pick a service, price it right, find clients abroad, and get paid reliably from anywhere in Africa.',
        'sections' => [
            ['The freelance opportunity', "As a freelancer you can earn in USD, choose your clients, and grow on your own terms. The barrier to entry has never been lower — but neither has the competition. Specialise, deliver well, and reputation will compound."],
            ['Choosing a service to sell', "Start with a skill clients already pay for: writing, design, web development, virtual assistance, social media, data entry, video editing. Pick one, get genuinely good, and niche down. 'I build Shopify stores for fashion brands' beats 'I do web stuff.'"],
            ['Pricing without underselling', "Beginners undercharge out of fear. Research what others charge, start slightly below market to build reviews, then raise rates every few clients. Prefer project pricing once you can estimate well — you're paid for value, not hours."],
            ['Finding your first clients', "Three channels: marketplaces (Upwork, Fiverr) for volume and reviews; LinkedIn and Twitter for relationships; and direct cold outreach to businesses that visibly need your service. Send 10 tailored messages a day."],
            ['Writing proposals that win', "Lead with their problem, not your CV. Show you understand the job, give a short relevant example, and end with a clear next step. Keep it short. Personalise the first line every time."],
            ['Getting paid in Africa', "Use Payoneer, Wise, or Grey to receive USD and convert locally. Always take a deposit (30-50%) before starting with a new client. Invoice clearly and keep records for every job."],
            ['Protecting yourself', "Red flags: clients who rush you, refuse deposits, or ask for free 'tests'. Use contracts, agree scope in writing, and never deliver final files before final payment."],
        ],
    ],
    [
        'title' => 'The CV Writing Playbook', 'author' => 'Jobmington', 'category' => 'Career',
        'desc' => 'A practical, recruiter-tested guide to writing a CV that passes the ATS and earns the interview — with templates, wording, and a final checklist.',
        'sections' => [
            ['What a CV is really for', "Your CV has one job: get you the interview. It is a marketing document, not an autobiography. Every line should earn its place by making you look like the obvious choice for THIS role."],
            ['Structure that works', "Top to bottom: name and contact, a sharp headline, a 2-3 line summary, skills, experience (most recent first), education. Keep it to one page early in your career, two at most."],
            ['Writing achievement bullets', "Formula: strong verb + what you did + measurable result. 'Increased newsletter sign-ups by 40% in 3 months by redesigning the landing page' beats 'Responsible for the newsletter.' Numbers make you credible."],
            ['Beating the ATS', "Applicant Tracking Systems scan before humans do. Use a clean single-column layout, standard section headings, no tables or images, and mirror the exact keywords from the job description (truthfully)."],
            ['The summary that hooks', "Three lines: who you are, your strongest proof, and what you want. Tailor it to the role. This is the first thing a recruiter reads — make it count."],
            ['Common mistakes', "Typos, generic objectives, walls of text, listing duties instead of results, an unprofessional email address, and the same CV sent everywhere. Fix these and you're ahead of most applicants."],
            ['The pre-send checklist', "Tailored to the job? Keywords matched? Achievements quantified? One/two pages? Consistent formatting? Saved as 'Firstname-Lastname-CV.pdf'? Proofread twice? Then send."],
        ],
    ],
    [
        'title' => 'Breaking Into Tech: A Roadmap', 'author' => 'Jobmington', 'category' => 'Technology',
        'desc' => 'No degree required. A realistic, step-by-step path into a tech career from Africa — choosing a track, learning for free, building proof, and getting hired.',
        'sections' => [
            ['You don\'t need a degree', "Many of the best people in tech are self-taught. Employers increasingly hire for proven skill, not paper. What you need is a focused plan, free resources, and the discipline to build real things."],
            ['Choosing your track', "Pick one to start: Front-end (HTML, CSS, JavaScript), Data (SQL, Python, analysis), QA/Testing, or Tech Support moving toward engineering. Don't learn everything — go deep on one path first."],
            ['Learn for free', "freeCodeCamp, The Odin Project, CS50, and YouTube cover almost everything for free. Follow a structured curriculum to the end rather than jumping between random tutorials."],
            ['Build proof', "Knowledge isn't enough — you need evidence. Build 2-3 real projects and put them on GitHub. A small, finished, deployed project beats a dozen half-done tutorials."],
            ['Your portfolio & profile', "A simple portfolio site, a clean GitHub, and a LinkedIn that tells your story. Write about what you build — it compounds and attracts opportunities."],
            ['Getting the first role', "Apply for junior, internship, and apprenticeship roles. Contribute to open source. Be active in communities. Your first role is the hardest — after that, experience opens doors."],
            ['Staying the course', "You will feel stuck and behind. Everyone does. Consistency over months is what separates those who make it. Keep building, keep applying, keep learning in public."],
        ],
    ],
];

$coversDir = $root . '/uploads/ebooks/covers';
$filesDir  = $root . '/uploads/ebooks/files';
@mkdir($coversDir, 0755, true);
@mkdir($filesDir, 0755, true);

$bMade = 0;
foreach ($ebooks as $b) {
    $slug = sc_slug($b['title']);
    $exists = $pdo->prepare("SELECT ebook_id FROM ebooks WHERE slug = ? LIMIT 1");
    $exists->execute([$slug]);
    if ($exists->fetchColumn()) continue;

    $coverPath = $coversDir . '/' . $slug . '.png';
    $pdfPath   = $filesDir . '/' . $slug . '.pdf';

    $pages = sc_make_ebook_cover($coverPath, $b['title'], $b['author'], $b['category'], $brandFont);
    $pageCount = sc_make_ebook_pdf($pdfPath, $b['title'], $b['author'], $b['sections'], $root);

    $pdo->prepare("INSERT INTO ebooks
        (title, slug, author, category, description, cover_image, file_path, pages, is_free, price, is_published, is_featured)
        VALUES (?,?,?,?,?,?,?,?,1,0,1,?)")
        ->execute([
            $b['title'], $slug, $b['author'], $b['category'], $b['desc'],
            is_file($coverPath) ? '/jobmington/uploads/ebooks/covers/' . $slug . '.png' : null,
            is_file($pdfPath) ? '/jobmington/uploads/ebooks/files/' . $slug . '.pdf' : null,
            $pageCount, $slug === sc_slug($ebooks[0]['title']) ? 1 : 0,
        ]);
    $bMade++;
}

echo "Seed complete.\n";
echo "  Courses added: $cMade\n";
echo "  Events added:  $eMade\n";
echo "  Ebooks added:  $bMade\n";
