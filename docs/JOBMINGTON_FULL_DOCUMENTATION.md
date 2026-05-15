# Jobmington Full Documentation

Last updated: 2026-05-14  
Source: current local codebase at `C:\Users\USER\Desktop\Jobmington`

## 1. What Jobmington Is

Jobmington is a job marketplace for African talent. The product is built around a simple promise:

- Job seekers should be able to find relevant jobs, build a clean CV, apply quickly, and track progress.
- Employers should be able to post roles, manage applications, and pay for better visibility when needed.
- The platform owner should be able to monitor jobs, users, payments, scraping, and launch readiness from admin.

The current strongest version of the product is a job board plus CV Studio, employer dashboard, paid job visibility, automated job importing, and admin operations.

The codebase also contains older or secondary modules: learning academy, wallet/seeds, talent passports, blog, community, certificates, Andika AI, and API endpoints. Some are usable but not all are part of the current homepage-first launch surface.

## 2. Product Positioning

Primary message:

> Find work that fits. Hire talent that moves.

Homepage direction:

- Calm, modern job marketplace.
- Real work imagery from Unsplash.
- Minimal shadows.
- Clean color separation rather than heavy cards.
- Jobs and CV tools are the first-class features.
- Deactivated or less-ready modules should not be over-promoted on the homepage.

Primary users:

- Job seekers looking for African, remote, and global work opportunities.
- Employers hiring African talent.
- Platform admin/operator managing content, payments, jobs, scraping, and launch health.

## 3. Current Primary Navigation

Homepage navigation currently points to:

- `/jobmington/jobs/` - job search and browsing.
- `/jobmington/cv-builder/` - CV Studio.
- `/jobmington/employer/` or `/jobmington/employer/dashboard.php` - employer area.
- `/jobmington/pricing.php` - employer job posting packages.
- `/jobmington/auth/login.php` - sign in.
- `/jobmington/auth/register.php` - account creation.

Logged-in users are sent to different dashboards:

- Admin: `/jobmington/admin/`
- Employer: `/jobmington/employer/dashboard.php`
- Job seeker: `/jobmington/seeker/dashboard.php`

## 4. Access Logic

### Public

Public visitors can view:

- Homepage.
- Job listings.
- Job details.
- Pricing.
- Employer post-job entry screen.
- Login/register.
- Some public blog/community/tool pages, depending on module state.

### Job Seeker

Job seekers can:

- Use dashboard.
- Build and export CVs.
- Save jobs.
- Apply to jobs.
- Track applications.
- Update profile.
- Use job match guidance when CV data exists.

Important URLs:

- `/jobmington/seeker/dashboard.php`
- `/jobmington/seeker/profile.php`
- `/jobmington/seeker/applications.php`
- `/jobmington/jobs/saved.php`
- `/jobmington/cv-builder/`

### Employer

Employers can:

- Create or edit company profile.
- Post jobs.
- Manage jobs.
- Review applications.
- Update application status.
- Browse talent pool if the talent passport access system is enabled.
- Pay for featured job packages.

Important URLs:

- `/jobmington/employer/dashboard.php`
- `/jobmington/employer/company-profile.php`
- `/jobmington/employer/post-job.php`
- `/jobmington/employer/manage-jobs.php`
- `/jobmington/employer/applications.php`
- `/jobmington/employer/view-applicant.php`
- `/jobmington/employer/talent-pool.php`

### Admin

Admins can:

- View platform KPIs.
- Monitor system health.
- Manage users, jobs, categories, countries, courses, blog, forum, certificates, badges, and settings.
- View launch readiness checks.

Important URLs:

- `/jobmington/admin/`
- `/jobmington/admin/operations.php`
- `/jobmington/admin/jobs.php`
- `/jobmington/admin/users.php`
- `/jobmington/admin/settings.php`

Admin access is protected by `Session::requireAdmin()`.

## 5. Core Technical Stack

Current stack:

- PHP application, no framework.
- MySQL/MariaDB through PDO.
- HTML/CSS/JavaScript.
- Paystack for payments.
- Cron-based job importing.
- Local development currently served under `/jobmington/`.

Important requirement:

The app expects to live under the `/jobmington` URL path. Many links are absolute, such as `/jobmington/jobs/`. On production, Nginx should serve the app at:

```text
http://your-domain.com/jobmington/
```

or:

```text
https://your-domain.com/jobmington/
```

## 6. Main Configuration Files

### `config/env.php`

Loads environment values from:

1. `.env.local`
2. `.env`
3. safe local defaults

Important environment keys:

```env
DB_HOST=localhost
DB_NAME=jobmington_db
DB_USER=jobmington_user
DB_PASS=change_me

APP_ENV=production
APP_DEBUG=false
APP_TIMEZONE=Africa/Lagos

PAYSTACK_PUBLIC_KEY=pk_live_xxx
PAYSTACK_SECRET_KEY=sk_live_xxx
PAYSTACK_BASE_URL=https://api.paystack.co
PAYSTACK_CALLBACK_URL=https://your-domain.com/jobmington/payments/verify.php
PAYSTACK_WEBHOOK_URL=https://your-domain.com/jobmington/api/webhooks/paystack.php

GEMINI_API_KEY=
GROQ_API_KEY=
OPENROUTER_API_KEY=

JOB_SCRAPER_LIMIT=80
JOB_SCRAPER_USER_AGENT=JobmingtonBot/1.0 (+https://your-domain.com/contact)
```

### `config/constants.php`

Defines:

- `SITE_NAME`
- `SITE_URL`
- upload paths
- job types
- experience levels
- user roles
- upload limits
- session settings
- application statuses

Important constants:

```php
USER_TYPE_SEEKER
USER_TYPE_EMPLOYER
USER_TYPE_ADMIN
JOB_TYPES
EXPERIENCE_LEVELS
APPLICATION_STATUSES
MAX_CV_SIZE
MAX_AVATAR_SIZE
```

### `config/database.php`

Creates the PDO singleton connection. It reads database credentials from environment variables.

## 7. Security Model

Important files:

- `includes/security.php`
- `includes/session.php`

Security currently includes:

- Argon2id password hashing through `Security::hashPassword()`.
- Password verification.
- CSRF token generation and verification.
- HTML escaping through `e()`.
- Rate limit helper using `rate_limits`.
- Activity logging through `activity_logs`.
- File upload validation using MIME checks and max byte limits.
- Secure-ish session handling with HTTP-only cookies, strict mode, SameSite Strict, session name, and periodic regeneration.
- Login lockout after repeated failed attempts.

Known security notes:

- Production must set `APP_ENV=production` and `APP_DEBUG=false`.
- `.env` and `.env.local` must never be public.
- Nginx should deny direct access to sensitive folders such as `config`, `database`, `logs`, `.agent`, and hidden files.
- OAuth endpoints exist but are not production-complete.
- Some older modules still use older UI and should be reviewed before being promoted publicly.

## 8. Homepage

File:

- `index.php`

Current homepage features:

- Minimal header.
- Announcement/update slider near the top.
- Hero with real Unsplash image.
- Search form with keyword, country, and type filters.
- Popular search links.
- Live stats from database:
  - open jobs
  - hiring companies
  - remote roles
  - categories
- Featured jobs.
- Category/job discovery sections.
- Job seeker and employer explanation sections.
- Unified minimal footer.

Important design file:

- `assets/css/minimal-jobmington.css`

Current visual system:

- Deep ink: `#061426`
- Blue: `#0640a3`
- Orange: `#f59f22`
- Green: `#0f766e`
- Soft backgrounds: `#f7faff`, `#fbfdff`
- Shadows intentionally reduced or disabled.

## 9. Job Marketplace

Important files:

- `jobs/index.php`
- `jobs/search.php`
- `jobs/category.php`
- `jobs/view.php`
- `jobs/apply.php`
- `jobs/saved.php`
- `jobs/_job_helpers.php`

### Job Browse

URL:

- `/jobmington/jobs/`

Supports:

- keyword search
- category filter
- country filter
- job type filter
- pagination
- salary display
- job tags

Only active, non-expired jobs are shown:

```sql
j.is_active = 1
AND (j.expires_at IS NULL OR j.expires_at >= CURDATE())
```

### Job Details

URL:

- `/jobmington/jobs/view.php?id={job_id}`

Shows:

- company
- job title
- location
- type
- experience
- salary
- description
- requirements
- benefits
- tags
- job summary
- company summary
- related jobs
- save job action
- application action

Recent improvements:

- Job details content is formatted into clean readable rich text.
- Tags show featured, remote, category, experience, salary listed, and curated status.
- "Apply smarter" match coach shows fit guidance.
- "Interview prep" provides likely questions, preparation notes, questions to ask, and a practice pitch.

### Apply Flow

URL:

- `/jobmington/jobs/apply.php?id={job_id}`

Logic:

- If the job has `application_url` or `apply_link`, user is sent to an external application flow.
- If the job is internal, user must log in.
- Employers cannot apply unless admin.
- User can submit a cover note and optional CV file.
- Uploaded CV must be PDF, DOC, or DOCX.
- Application creates a `job_applications` row.
- Job `applications_count` is incremented.
- Employer notification is attempted when employer owner exists.

### Saved Jobs

URL:

- `/jobmington/jobs/saved.php`

Users can save from the job detail page and manage saved jobs from this page.

## 10. Job Match and Interview Prep

Important files:

- `jobs/_job_helpers.php`
- `ai/JobMatcher.php`
- `api/job-matches.php`

There are two matching layers:

### Lightweight Role Coach

Implemented in `jm_job_match_coach()`.

Used on:

- job detail page
- application page

It compares:

- user CV headline
- summary
- skills
- experience
- role keywords
- job category
- location

It returns:

- match score
- label
- matched skills
- missing skills
- coaching bullets
- suggested opening cover note
- CTA to sign in, create CV, or improve CV

### JobMatcher Class

Implemented in `ai/JobMatcher.php`.

Used on seeker dashboard and API.

Weights:

- skills: 35
- title: 20
- location: 15
- experience: 15
- category: 10
- type: 5

It ranks jobs for a logged-in user based on profile and CV data.

## 11. CV Studio

Important files:

- `cv-builder/index.php`
- `cv-builder/create.php`
- `cv-builder/editor-complete.php`
- `cv-builder/save-complete.php`
- `cv-builder/templates.php`
- `cv-builder/export.php`
- `cv-builder/export-complete.php`
- `cv-builder/import.php`
- `cv-builder/delete.php`
- `cv-builder/_cv_helpers.php`

URL:

- `/jobmington/cv-builder/`

Access:

- Requires login.
- If a public visitor clicks CV Builder, they are redirected to login.
- Login and register preserve safe `/jobmington/...` redirects so a user can return to CV Builder after authentication.

### CV Builder Landing Page

Shows:

- active CV focus card
- next best move
- CV version library
- ready-to-export status
- CV section summary
- import lanes
- delete confirmation modal
- template controls

### CV Completion

Completion is calculated from:

- full name
- email
- headline
- summary
- experience
- education
- skills

Status labels include:

- Ready to export
- Review next
- Needs polish
- Draft setup

### CV Templates

Defined in `jm_cv_templates()`:

- Executive (`obsidian`)
- Modern (`cybernetic`)
- Technical (`blueprint`)

Templates carry into the export page.

### CV Editor

URL:

- `/jobmington/cv-builder/editor-complete.php?id={cv_id}`

Editor loads:

- profile
- experience
- education
- skills
- certifications
- languages
- projects

Saving is handled through `save-complete.php`, which updates the main CV profile and rewrites section rows.

### CV Import

URL:

- `/jobmington/cv-builder/import.php`

Supports:

- PDF
- DOCX
- LinkedIn ZIP export

Flow:

1. Validate upload.
2. Extract text from file.
3. Parse using Gemini if `GEMINI_API_KEY` exists.
4. Fall back to basic parser if no Gemini key or API fails.
5. Create new `cv_profiles` row.
6. Insert experience, education, and skills.
7. Return JSON with redirect to editor.

Important requirement:

- PHP needs `fileinfo`, `curl`, and preferably `ZipArchive` for best document import support.

### CV Export

URL:

- `/jobmington/cv-builder/export.php?id={cv_id}`

This redirects to:

- `/jobmington/cv-builder/export-complete.php?id={cv_id}`

Export page renders an A4 resume layout with print-friendly CSS. It is browser-print based rather than a server-generated PDF download.

## 12. Employer Platform

Important files:

- `employer/dashboard.php`
- `employer/company-profile.php`
- `employer/post-job.php`
- `employer/manage-jobs.php`
- `employer/edit-job.php`
- `employer/applications.php`
- `employer/view-applicant.php`
- `employer/talent-pool.php`
- `employer/_employer_helpers.php`

### Employer Setup

Employer accounts need a company profile before posting jobs.

If no company exists, helpers redirect to:

```text
/jobmington/employer/company-profile.php?setup=1
```

### Employer Dashboard

Shows:

- total jobs
- active jobs
- applications
- views
- recent applications
- recent jobs

### Post Job

URL:

- `/jobmington/employer/post-job.php`

Public visitor:

- sees sign-in/create-account prompt

Logged-in seeker:

- blocked from employer posting

Logged-in employer without company:

- redirected to company setup

Logged-in employer with company:

- can post a job

Fields:

- title
- category
- description
- requirements
- benefits
- job type
- experience level
- salary range
- salary visibility
- country
- city
- application email
- external application URL
- deadline
- posting package

Paid plans save jobs as inactive first. Jobs are activated after payment succeeds.

### Applications Management

URL:

- `/jobmington/employer/applications.php`

Employers can:

- filter by status
- filter by job
- search candidate, email, or job title
- update status
- open applicant details

Supported statuses:

- pending
- reviewed
- shortlisted
- interview
- rejected
- hired

## 13. Monetization

Important files:

- `includes/monetization.php`
- `pricing.php`
- `employer/post-job.php`
- `payments/job-posting.php`
- `payments/job-posting-callback.php`
- `includes/paystack.php`

Current monetization model:

Job seekers do not pay to apply. Employers can pay for job visibility.

### Job Posting Packages

Defined in `jm_job_posting_packages()`.

#### Start

- Price: Free
- Duration: 30 days
- Featured: no
- Use case: one simple hire

#### Reach more candidates

- Price: NGN 15,000
- Duration: 45 days
- Featured: yes
- Use case: important roles that need more visibility

#### Hire faster

- Price: NGN 45,000
- Duration: 60 days
- Featured: yes
- Use case: urgent roles and priority listing help

### Paid Job Flow

1. Employer posts job with paid package.
2. Job is inserted with `is_active = 0`.
3. Pending transaction is created.
4. Employer is sent to `/payments/job-posting.php?ref=...`.
5. Paystack initializes payment.
6. Paystack redirects to callback.
7. Callback verifies transaction.
8. Transaction becomes `completed`.
9. Job becomes active and featured if package requires it.
10. Expiry is set based on package duration.

### Paystack Setup

Production needs:

```env
PAYSTACK_PUBLIC_KEY=pk_live_xxx
PAYSTACK_SECRET_KEY=sk_live_xxx
PAYSTACK_CALLBACK_URL=https://your-domain.com/jobmington/payments/job-posting-callback.php
PAYSTACK_WEBHOOK_URL=https://your-domain.com/jobmington/api/webhooks/paystack.php
```

Current callback for job posting uses:

```php
SITE_URL . '/payments/job-posting-callback.php'
```

So `SITE_URL` must resolve correctly on the VPS.

## 14. Payments and Wallet

Important files:

- `includes/paystack.php`
- `api/webhooks/paystack.php`
- `payments/index.php`
- `payments/checkout.php`
- `payments/verify.php`
- `wallet/index.php`
- `includes/seeds.php`

There are two payment ideas in the codebase:

1. Current launch monetization: employer paid job listings.
2. Older/secondary wallet and Seeds system.

### Seeds

Seeds are an internal credit system.

Implemented in:

- `includes/seeds.php`
- `wallet/index.php`
- `database/migrations/create_seeds_system.php`

Seeds can be:

- awarded
- spent
- purchased
- tracked through seed transactions

Used by:

- Andika premium tools
- CV Roast unlock
- talent passport pricing ideas

Launch note:

Seeds are present but should not be pushed as the main monetization story until the wallet, Paystack purchase flow, and user-facing copy are reviewed.

## 15. Automated Job Scraping

Important files:

- `cron/run_job_scrapers.php`
- `cron/fetch_wwr_jobs.php`
- `cron/README.md`

Current recommended runner:

```bash
php cron/run_job_scrapers.php --limit=80
```

Supported sources:

- `wwr` - We Work Remotely RSS
- `remoteok` - RemoteOK API

Single source examples:

```bash
php cron/run_job_scrapers.php --source=wwr --limit=60
php cron/run_job_scrapers.php --source=remoteok --limit=60
```

### Scraper Features

The runner:

- uses cURL
- uses a lock file to prevent overlap
- maps companies
- creates shadow companies when needed
- maps countries
- maps categories
- checks duplicates by `guid`, `apply_link`, company/title/date
- filters out obvious location-restricted jobs
- inserts valid jobs into `jobs`
- writes a log to `logs/job-scraper.log`
- writes latest run summary to `logs/job-scraper-status.json`

### Geographic Filter

Keeps broad/global roles such as:

- worldwide
- global
- EMEA
- Africa
- Nigeria
- Ghana
- Kenya
- South Africa
- remote

Skips obvious restricted roles such as:

- USA only
- US only
- Canada only
- UK only
- North America only
- LATAM only

### Production Cron

Current recommended cron:

```cron
*/45 * * * * cd /var/www/jobmington && /usr/bin/php cron/run_job_scrapers.php --limit=80 >> logs/job-scraper-cron.log 2>&1
```

Important:

If the app is deployed at `/var/www/html/jobmington`, update the path:

```cron
*/45 * * * * cd /var/www/html/jobmington && /usr/bin/php cron/run_job_scrapers.php --limit=80 >> logs/job-scraper-cron.log 2>&1
```

### Jobberman

Do not scrape Jobberman listing or detail pages without written permission.

The proper production route is:

- approved partner feed
- API
- XML feed
- CSV export
- webhook
- direct agreement

Once access is approved, add a `jobberman` adapter to `cron/run_job_scrapers.php` that maps the approved feed into the existing `jobs` table.

## 16. Admin Platform

Important files:

- `admin/index.php`
- `admin/operations.php`
- `admin/jobs.php`
- `admin/users.php`
- `admin/categories.php`
- `admin/countries.php`
- `admin/courses.php`
- `admin/modules.php`
- `admin/quizzes.php`
- `admin/certificates.php`
- `admin/badges.php`
- `admin/blog.php`
- `admin/forum.php`
- `admin/settings.php`

### Admin Dashboard

URL:

- `/jobmington/admin/`

Shows:

- total users
- active users
- companies
- unverified companies
- total jobs
- live jobs
- inactive jobs
- expired jobs
- applications
- pending applications
- revenue
- transactions
- failed payments
- courses
- certificates
- passports
- unread notifications
- failed logins
- rate limit records
- recent users
- recent jobs
- recent applications
- recent payments
- latest activity
- system health

### Admin Operations

URL:

- `/jobmington/admin/operations.php`

This is the most important admin page for launch.

Checks:

- production mode
- Paystack live keys
- HTTPS callback and webhook URLs
- uploads writable
- logs writable
- PHP extensions
- job scraper freshness
- payment queue
- applications older than 7 days

Shows:

- live jobs
- new jobs this week
- paid jobs awaiting payment
- pending applications
- scraper last run
- scraper inserted/duplicates/failed
- cron command
- smoke-test links
- latest scraper log tail

## 17. Learning Academy

Important files:

- `learn/_disabled.php`
- `learn/index.php`
- `learn/course.php`
- `learn/enroll.php`
- `learn/module.php`
- `learn/quiz.php`
- `learn/my-courses.php`
- `learn/checkout.php`
- `learn/verify-purchase.php`
- `database/migrations/setup_courses.php`

Current status:

The learning module is currently disabled at the entry point. `learn/index.php` begins with:

```php
require_once __DIR__ . '/_disabled.php';
```

`_disabled.php` redirects to:

```text
/jobmington/jobs/
```

Meaning:

- Learning code exists.
- Database migrations exist.
- Admin pages for courses/modules/quizzes/certificates exist.
- The public learning surface is not part of the current active launch.

Recommendation:

Do not promote Learning Academy until it is intentionally reactivated and redesigned to match the current minimal Jobmington style.

## 18. Andika AI and CV Roast

Important files:

- `ai/andika.php`
- `ai/andika-brain.php`
- `ai/roast.php`
- `api/andika.php`

Andika is a career assistant that can support:

- basic chat
- interview practice
- salary guide
- career roadmap
- CV review

`api/andika.php` uses:

- `GROQ_API_KEY` for chat completions
- Seeds for paid tools
- user location context from session/profile

Tool costs:

- chat: 0 Seeds
- interview practice: 100 Seeds
- salary guide: 0 Seeds
- career roadmap: 75 Seeds
- CV roast: 50 Seeds

Launch note:

This module has older/heavier UI language and should be redesigned before being presented as a central product feature.

## 19. Talent Passport

Important files:

- `wallet/passport/index.php`
- `wallet/passport/assessment.php`
- `wallet/passport/endorse.php`
- `wallet/passport/verify.php`
- `database/migrations/create_talent_passports.php`

Concept:

Talent Passport is a public verified profile/credential record for high-quality talent.

Tables include:

- `talent_passports`
- `passport_verifications`
- `passport_endorsements`
- `employer_talent_access`
- `passport_contacts`
- `passport_views`
- `passport_pricing`

Potential monetization:

- skill verification
- visibility boost
- verified CV export
- employer subscription
- single talent contact purchase

Launch note:

This is promising but not the core homepage launch. It needs product tightening before being sold.

## 20. Blog and Community

### Blog

Important files:

- `blog/index.php`
- `blog/post.php`
- `blog/category.php`
- `admin/blog.php`

Purpose:

- career content
- platform updates
- SEO
- side promotion of jobs, CV Builder, and Andika

Status:

- Functional code exists.
- Design is older/darker than the new minimal homepage.
- Should be visually aligned before being pushed heavily.

### Community

Important files:

- `community/index.php`
- `community/new-topic.php`
- `community/topic.php`
- `community/reply.php`
- `admin/forum.php`

Purpose:

- professional networking feed
- discussion categories
- posts/replies

Status:

- Functional code exists.
- Design is older/heavier.
- Should not be a primary launch surface yet.

## 21. API Endpoints

Important files:

- `api/config.php`
- `api/auth.php`
- `api/jobs.php`
- `api/job-matches.php`
- `api/andika.php`
- `api/webhooks/paystack.php`
- `api/employer/contact-talent.php`
- `api/passport/verify.php`

### Jobs API

URL:

- `/jobmington/api/jobs.php`

Supports GET with optional:

- `country`
- `keyword`

Returns active jobs as JSON.

### Auth API

URL:

- `/jobmington/api/auth.php`

Supports:

- JSON register
- JSON login
- OAuth redirect scaffolding for Google and LinkedIn

Production note:

OAuth callbacks are not complete. The user-facing PHP auth pages are currently more complete than API auth.

### Job Matches API

URL:

- `/jobmington/api/job-matches.php`

Requires login.

Returns:

- matches
- count
- profile completion hint

### Paystack Webhook

URL:

- `/jobmington/api/webhooks/paystack.php`

Validates Paystack signature and handles events.

Production note:

The webhook currently has older wallet/Seeds behavior for generic `charge.success`. Job posting payment callback is more directly tied to paid job activation.

## 22. Database Overview

Major table groups:

### Identity

- `users`
- `companies`
- `activity_logs`
- `rate_limits`
- `failed_logins`
- `notifications`

### Jobs

- `jobs`
- `job_categories`
- `countries`
- `job_applications`
- `saved_jobs`

### CV Builder

- `cv_profiles`
- `cv_experience`
- `cv_education`
- `cv_skills`
- `cv_certifications`
- `cv_languages`
- `cv_projects`
- optional extended CV tables in migrations

### Payments

- `transactions`
- `payment_methods`
- `subscriptions`
- `webhook_logs`

### Seeds/Wallet

- `wallets`
- `seed_transactions`
- `seed_rates`
- `seed_packages`

### Learning

- `course_categories`
- `courses`
- `course_modules`
- `course_lessons`
- `module_quizzes`
- `certificates`
- `course_enrollments`

### Talent Passport

- `talent_passports`
- `passport_verifications`
- `passport_endorsements`
- `employer_talent_access`
- `passport_contacts`
- `passport_views`
- `passport_pricing`

### Blog/Community

- `blog_posts`
- `blog_categories`
- `forum_categories`
- `forum_topics`
- `forum_replies`

## 23. Deployment Notes for Linode LEMP

Recommended stack:

- Ubuntu LTS or Debian
- Nginx
- MariaDB or MySQL
- PHP-FPM
- PHP extensions:
  - `php-cli`
  - `php-fpm`
  - `php-mysql`
  - `php-curl`
  - `php-mbstring`
  - `php-xml`
  - `php-zip`
  - `php-gd`
  - `php-intl`
  - `php-bcmath`
  - `php-fileinfo` if packaged separately

Recommended production path:

```text
/var/www/html/jobmington
```

Alternative if Nginx root is `/var/www`:

```text
/var/www/jobmington
```

The important thing is that the public URL path remains:

```text
/jobmington/
```

### Recommended Nginx Protection

Add denies for sensitive areas:

```nginx
location ~ ^/jobmington/(config|database|logs|\.agent)/ {
    deny all;
}

location ~ ^/jobmington/(\.env|\.git|.*\.sql|sess_) {
    deny all;
}
```

PHP handling must support PHP files under `/jobmington`.

### Server Writable Directories

Must exist and be writable by the web server:

```text
uploads/
logs/
uploads/avatars/
uploads/resumes/
uploads/company-logos/
uploads/blog-images/
uploads/course-thumbnails/
uploads/cvs/
```

### Files Not To Upload

Do not deploy:

- `.env`
- `.env.local`
- `.agent/`
- local session files such as `sess_*`
- local logs
- local debug/test scripts unless intentionally needed

### Environment on VPS

Create `.env` on the server only:

```env
DB_HOST=localhost
DB_NAME=jobmington_db
DB_USER=jobmington_user
DB_PASS=strong_password_here
APP_ENV=production
APP_DEBUG=false
APP_TIMEZONE=Africa/Lagos
```

Add Paystack live keys only when ready to accept real paid listings.

## 24. Launch Readiness Checklist

Before public launch:

- SSH key access to VPS works.
- LEMP stack installed and running.
- Nginx serves `/jobmington/`.
- Database exists.
- Migrations or schema are installed.
- `.env` exists on server.
- `APP_ENV=production`.
- `APP_DEBUG=false`.
- `uploads/` is writable.
- `logs/` is writable.
- Paystack live keys are added if paid listings are live.
- Paystack callback URL uses HTTPS.
- Paystack webhook URL uses HTTPS.
- Admin account exists.
- Admin can open `/jobmington/admin/`.
- Admin can open `/jobmington/admin/operations.php`.
- Job scraper runs once manually.
- Cron is installed for recurring scraping.
- Homepage loads.
- Job listing loads.
- Job detail loads.
- Login/register redirects work.
- CV Builder opens after login.
- Employer can create company profile.
- Employer can post free job.
- Employer can create paid job and reach payment screen.
- Application submission works.
- Sensitive folders are blocked from web access.

## 25. Current Known Gaps

### VPS Access

At the time this document was written, SSH to the Linode VPS still required the local public key to be added to `root` authorized keys. Password authentication was rejected by the server because it accepts public key only.

### Paystack Production

Payment code exists, but production keys and HTTPS callback/webhook URLs must be configured on the server.

### Jobberman

No Jobberman adapter is currently implemented. Add only through an approved feed/API/CSV/XML route.

### CV Import

CV import works best with:

- readable PDF/DOCX
- PHP ZIP support
- `GEMINI_API_KEY`

Without Gemini, it falls back to a simpler parser.

### Learning, Community, Wallet, Passport, Andika

These modules exist but are not all aligned with the new minimal homepage/product language. Treat them as secondary until reviewed.

### Admin Jobs Page

`admin/jobs.php` uses the older global header/footer style, not the newest minimal admin operations style.

### OAuth

OAuth scaffolding exists but is not production-complete.

### Email

Mailer exists, but production SMTP configuration should be confirmed before relying on email verification, password reset, and notifications.

## 26. Recommended Roadmap

### Phase 1: Ship Core Job Board

- Deploy to VPS.
- Confirm jobs, CV Builder, auth, employer posting, and admin operations.
- Add Paystack live keys.
- Run job scraper via cron.
- Keep homepage focused on jobs, CV Builder, and employer posting.

### Phase 2: Improve Revenue

- Add employer invoice records and payment receipts.
- Add admin payment dashboard refinements.
- Add featured job placements on homepage and job listing.
- Add employer analytics for job views/applications.

### Phase 3: Better Job Supply

- Add approved feeds beyond WWR and RemoteOK.
- Add import adapter for CSV/XML feeds.
- Add admin moderation queue for scraped jobs.
- Add source quality scoring.

### Phase 4: Upgrade CV Studio

- Add PDF generation or one-click download.
- Improve parsing quality.
- Add role-specific CV tailoring.
- Add stronger template previews.

### Phase 5: Reactivate Secondary Modules Selectively

- Redesign Andika to match the new calm interface.
- Decide if Seeds/Wallet should be public.
- Decide if Talent Passport is a separate premium employer feature.
- Reactivate Learning only if content and business model are ready.
- Bring Blog/Community into the same design system.

## 27. Quick Operator Guide

### View Admin

1. Log in with an admin account.
2. Open:

```text
http://127.0.0.1:8000/jobmington/admin/
```

or production:

```text
https://your-domain.com/jobmington/admin/
```

### View Operations

```text
/jobmington/admin/operations.php
```

### Run Scraper Locally

```bash
php cron/run_job_scrapers.php --source=wwr --limit=10
```

### Run All Scrapers

```bash
php cron/run_job_scrapers.php --limit=80
```

### Open CV Builder

```text
/jobmington/cv-builder/
```

### Post A Job

```text
/jobmington/employer/post-job.php
```

### Manage Employer Applications

```text
/jobmington/employer/applications.php
```

### Browse Jobs

```text
/jobmington/jobs/
```

## 28. One Sentence Summary

Jobmington is ready to be treated as a focused African job marketplace with CV-building, employer posting, paid job visibility, automated job importing, and admin operations as its core launch pillars.
