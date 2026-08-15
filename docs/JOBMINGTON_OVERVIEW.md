# Jobmington - Application Overview

_Last updated: 2026-08-15. This reflects the codebase as currently deployed. It supersedes the initial-commit `JOBMINGTON_FULL_DOCUMENTATION.md` everywhere they differ._

---

## 1. What Jobmington is

Jobmington is a career platform for African talent. It combines a **job marketplace**, a **tool catalogue** (AI and non-AI career tools), a **CV studio**, an **employer hiring console**, a **learning academy**, an **ebook library**, an **events/webinar system**, a **wallet and rewards system**, a **community forum**, and an **admin back office** into a single PHP application.

Tagline in the product: _"Simple hiring for African talent."_ Brand tagline constant: _"Preparing Africa's Workforce for the Future."_

- **Market:** Africa-wide. Nigeria is the largest single market and the payment rail, but the product is not built around it (see §11 on currency).
- **Domain:** https://jobmington.com
- **Audience:** job seekers, employers, and platform admins.

---

## 2. Technical stack

| Layer | Choice |
|------|--------|
| Language | PHP 8.3 (procedural + light OOP) |
| Database | MySQL / MariaDB (PDO, `utf8mb4`), DB name `jobmington_db` |
| Web server (prod) | nginx + php-fpm (unix socket) on a Linode VPS, behind Cloudflare |
| Web server (dev) | XAMPP/Apache on Windows, served under `/jobmington/` |
| CSS | `minimal-jobmington.css` (hand-written design system) plus per-area sheets: `admin.css`, `andika.css`, `andika-panel.css`, `badges.css`, `brand-platform.css`, `dark-mode.css`, `neo-brutalist.css`, `premium-design-system.css`, `custom.css`. Tailwind CDN remains on 3 files only |
| Icons | Inline SVG |
| Payments | Paystack (NGN, kobo) |
| Email | Brevo (HTTP API, SMTP, `mail()` fallback chain), sent through an async queue |
| AI | OpenRouter (free Llama 3.2 3B by default), with Gemini/Groq keys wired |
| Fonts | Futura Cyrillic (Demi/Book), self-hosted, woff2 |
| PWA | manifest + service worker + branded boot ground |

**Deployment model:** the VPS is the source of truth. Work is committed locally, pushed to GitHub (`theakaezegroup/jobmington`), then deployed by `git pull` on the VPS at `/var/www/jobmington`, followed by `php database/migrate.php`. nginx rewrites legacy `/jobmington/*` paths to root and rewrites `.php` URLs to extensionless.

---

## 3. Directory map

```
config/        env loader, constants, PDO singleton
includes/      shared libs (see §3.1)
auth/          register, login, logout, verify-email, forgot/reset password
jobs/          browse, view, apply, saved, search, category (+ _job_helpers.php)
seeker/        dashboard, applications, profile, settings, notifications, badges
employer/      dashboard, post-job, manage-jobs, edit-job, applications,
               view-applicant, company-profile, talent-pool (+ _employer_helpers.php)
admin/         see §12
ai/            andika, andika-brain, roast, cover-letter, cold-pitch, JobMatcher
cv-builder/    landing, editor, templates, import, analyze, preview, export
learn/         academy: index, course, enroll, module, quiz, checkout, my-courses
ebooks/        library index, view, gated download
events/        webinars and workshops: index, view
certificates/  index, view, preview, claim, generate, download, example
community/     forum index, topic, new-topic, reply
blog/          index, post, category, search
payments/      seeker-premium, job-posting, credits, talent-access, checkout,
               process, verify, success/failed, per-feature callbacks
wallet/        Seeds wallet + history; wallet/passport (Talent Passport)
go/            ad.php, the sponsor click-through redirector
tools/         internal maintenance scripts (path fixers, render check)
api/           JSON endpoints + middleware + webhooks/paystack.php
cron/          9 scheduled scripts (see §19)
database/      migrate.php + migrations/schema/0001..0040
assets/        css, js, images, fonts
uploads/       resumes, avatars, company-logos, campaign-posters, ebooks
```

### 3.1 `includes/`

Core: `security.php`, `session.php`, `functions.php`, `mailer.php`, `paystack.php`, `monetization.php`, `seeds.php`, `badges.php`, `backup.php`, `location_detect.php`.

Added since the last revision of this doc:

| File | What it does |
|------|--------------|
| `navigation.php` | The site navigation defined once. Previously lived inside `header.php`, so `ai-header.php` pages hand-wrote their own list and drifted. |
| `tools.php` | The single tool registry (§15). Every tool is defined here and nowhere else. |
| `pricing_display.php` | Decides what price a visitor sees and in which currency (§11). |
| `email_queue.php` | Async email queue writer, drained by cron. |
| `remember.php` | Persistent login, selector/validator cookie scheme. |
| `account_deletion.php` | Deletes a person without gutting the content they authored. |
| `maintenance.php` | Site settings store and the maintenance gate. The admin toggle used to be decoration. |
| `feedback.php` | One toast and sound implementation, replacing three that disagreed. |
| `header_ads.php` | Renders at most one scheduled sponsor banner. Image and link only, never markup. |
| `notification_bell.php` | Header bell. |
| `forum_reactions.php` | Community reactions, three kinds rather than a single like. |
| `event_registration.php` | Event signup, including the signed-out intent flow. |
| `certificates.php` | Certificate issuing helpers. |
| `seeker_premium.php` | Premium entitlement checks. |
| `job_locations.php` | Location normalisation for listings. |
| `andika_widget.php` | The Andika panel that opens on any page. |
| `boot.php` | The blue launch ground for the installed app. |
| `sticky_header.php` | Scroll state for the frosted-to-blue header. |
| `learn_nav.php`, `illustration-states.php`, `stacktable.php` | Academy nav, empty-state art, responsive tables. |

---

## 4. Configuration layer

- **`config/env.php`** loads `.env.local` then `.env` (hand-rolled parser, no Composer dependency), then applies safe defaults for DB, app env, Paystack, OAuth, AI keys, and the job scrapers. Existing real env vars are never overwritten.
- **`config/constants.php`** holds site identity, paths, upload limits, pagination, session/security constants, **all pricing in both currencies**, per-tool credit costs, the NGN/USD rate, and the enums (user types, job types, experience levels, application statuses, difficulty levels).
- **`config/database.php`** is the `Database` PDO singleton: exception mode, assoc fetch, real prepares (`EMULATE_PREPARES => false`), `utf8mb4_unicode_ci`. Exposes the global `db()` helper. Prints the raw error only when `APP_DEBUG=true`.

---

## 5. Security model

| Concern | Implementation (`includes/security.php`, `session.php`, `remember.php`) |
|--------|------------------|
| Password hashing | Argon2id, memory_cost 65536, time_cost 4, threads 3 |
| CSRF | per-session token, `hash_equals` verify, `csrfField()` helper, rotated after sensitive actions |
| Sessions | httponly, SameSite=Strict, strict mode, cookies-only, secure under HTTPS, periodic `session_regenerate_id`, UA-binding, fixation prevention on login |
| Persistent login | selector/validator split. The selector is a public lookup key, the validator is stored hashed, so a stolen database row cannot be replayed as a cookie |
| Output escaping | `e()` / `Security::escape()` = `htmlspecialchars(ENT_QUOTES \| ENT_HTML5)` |
| File uploads | MIME sniff via `finfo`, size cap, random filename, per-type allow-list, PHP execution denied under `/uploads` in nginx |
| Rate limiting | `checkRateLimit()` backed by a `rate_limits` table (sha256(key+IP)) |
| Activity logging | `logActivity()` writes to `activity_logs` (user, action, IP, UA) |
| Access control | `Session::requireLogin / requireRole / requireAdmin / requireVerified`. Admin role is re-checked against the DB on `requireAdmin` |
| Tool access | `tool_flags` (global per-tool switch) and `tool_grants` (per-person grant), enforced through `includes/tools.php` |

Roles: `seeker`, `employer`, `admin`. Admin passes all role checks. `isEmployer()` also returns true for admins. Accounts flagged as official render a verified tick in the community.

---

## 6. Access logic by role

- **Public:** homepage, job browse/view/search, blog, ebook library, events, certificate verification, pricing, FAQ, legal pages, auth pages, tool landing pages.
- **Job seeker (verified):** apply to jobs, dashboard, applications, saved jobs, profile, settings, notifications, badges, CV studio, the tool catalogue (gated by free/credit/Seeds rules), wallet, Talent Passport, academy, ebook downloads, event registration, forum posting.
- **Employer:** company profile (required before posting), post/manage/edit jobs, review applications, view applicants, talent pool, employer subscriptions.
- **Admin:** the full back office (§12).

---

## 7. Authentication

- **Register** (`auth/register.php`), seeker or employer, sends a verification email with an `activation_token`.
- **Verify email** (`auth/verify-email.php`), token lookup, sets `is_verified = 1`, clears the token, refreshes the session, sends the welcome email, awards the verification badge, and completes any event registration the visitor had started before signing up.
- **Login** (`auth/login.php`), failed-attempt counter and lockout (`failed_login_attempts`, `locked_until`), role-based dashboard redirect, safe-redirect support, optional persistent login.
- **Forgot password** (`auth/forgot-password.php`), 32-byte token with 1-hour expiry, emails the reset link, always shows a neutral "if that email exists" message so accounts cannot be enumerated.
- **Reset password** (`auth/reset-password.php`), validates token and expiry, enforces strength, clears the token, sends a confirmation.
- **Logout** clears the session and revokes the remember token.

Session redirects are built from `SITE_URL` rather than a hardcoded path prefix.

---

## 8. Job marketplace

- **Browse** (`jobs/index.php`), filters, pagination (`JOBS_PER_PAGE = 12`), location-aware.
- **Search** (`jobs/search.php`), backed by MySQL fulltext indexes on title, description and requirements. It previously matched with `LIKE '%term%'`.
- **Category** (`jobs/category.php`).
- **View** (`jobs/view.php`), full role detail, company info, salary range, match coaching.
- **Apply** (`jobs/apply.php`): external-URL jobs send the seeker off-site; internal jobs require login and verification, and employers cannot apply. On submit it inserts into `job_applications` (status `pending`), bumps `applications_count`, notifies the employer in-app, emails the seeker a confirmation and the employer an alert.
- **Saved jobs** (`jobs/saved.php`).

Jobs are live when `is_active = 1`. Free and starter packages publish immediately; paid packages stay unpublished until payment succeeds. Three indexes back the listings: the live-job filter, the fulltext search, and the featured-then-recent ordering both listings end on.

---

## 9. CV studio (`cv-builder/`)

Landing, completion meter, template gallery, editor, import (parse an existing CV), analyze, preview, and export. CVs can be renamed, duplicated, deleted, and created genuinely from scratch. Export produces a multi-page printable document with a running line, controlled page breaks, a repeated footer carrying the mark and address, and a verification mark. Backed by `cv_profiles` and the tables in migration `0037`. Feeds the tools and the job-match coach.

---

## 10. Employer platform (`employer/`)

- **Company profile** is required before posting and is redirect-guarded.
- **Post job** (`post-job.php`), full validation (title, description, country, type, experience, salary sanity, deadline), unique slug, posting-package selection. Paid packages create a pending `transactions` row and redirect to payment; free packages publish and email a "job is live" confirmation.
- **Manage / edit jobs**, and **applications** with the status workflow pending, reviewed, shortlisted, interview, rejected/hired. A status change emails the seeker and notifies them in-app.
- **View applicant**, **talent pool**, and paid talent access.

---

## 11. Money: currency policy, pricing, payments, wallet

### Currency policy (`includes/pricing_display.php`)

Jobmington is an African platform, not a Nigerian one, so **the dollar leads on every page** and the visitor's own currency sits beside it. Naira is one of those currencies rather than the one the page is built around.

Two numbers are set by hand and never drift:

- **The dollar price** is the plan, and it is what the page leads with.
- **The Naira price** is what Paystack actually charges. It is set separately and kept round, so no Nigerian is repriced overnight because a market rate moved.

Every other currency is derived from daily rates (`cron/fetch_exchange_rates.php`) and labelled approximate, because it is an indication rather than an offer. The visitor's country comes from Cloudflare's `CF-IPCountry` header, so the lookup cannot fail as a page-load dependency. `jm_format_ngn()` is untouched, so receipts and admin reporting keep showing what was really charged.

### Prices (`config/constants.php`, all env-overridable)

| Item | USD | NGN |
|------|-----|-----|
| Employer single post | $22 | ₦30,000 |
| Featured add-on | $2 | ₦3,000 |
| Employer Basic monthly | $5.50 | ₦7,500 |
| Employer Pro monthly | $11 | ₦15,000 |
| Seeker Premium monthly | $2 | ₦3,000 |
| Seeker Premium annual | $22 | ₦30,000 |
| Credits x1 | $0.50 | ₦500 |
| Credits x5 | $1.50 | ₦2,000 |
| Credits x10 | $2 | ₦3,000 |
| Job Toolkit bundle | $1 | ₦1,500 |

Also priced: certificates (2 credits or 150 Seeds), premium certificates (2 credits or 200 Seeds), passport boost (200 Seeds/week), and per-tool credit costs (§15). `NGN_USD_RATE` defaults to 1,600.

### Paystack and flows

**`includes/paystack.php`** does initialize/verify, charge authorization (recurring), plans, transfers, and bank resolve. Amounts are in kobo. Webhook signatures are validated with HMAC-SHA512 and `hash_equals`, `SSL_VERIFYPEER` on.

**`payments/`** covers seeker premium, job posting, credits, talent access, generic checkout, process, verify, success/failed, and per-feature callbacks. Callbacks verify the transaction, mark it completed inside a DB transaction, then fulfil (activate subscription, add credits, publish job). The seeker-premium callback emails a receipt.

**`api/webhooks/paystack.php`** is the idempotent, transactional safety net for `charge.success/failed`, transfers, and subscription lifecycle, routing fulfilment by transaction type. The `jobs.status` column bug recorded in the June review is fixed.

**Wallet and Seeds** (`includes/seeds.php`, `wallet/`) is the internal rewards currency. Seeds are earned, granted by admins, or purchased, and spent on gated tools. Tables: `wallets`, `seed_transactions`, `seed_rates`. Seed purchases carry the Paystack reference separately from the internal `reference_id`.

---

## 12. Admin back office (`admin/`)

- **Command center** (`index.php`), KPIs, "needs attention" alerts, operations health bars, quick tiles, recent activity.
- **Users** (`users.php`), search/filter/sort, role change, suspend/activate, verify, unlock, temp password, CSV export. **`delete-user.php`** performs the full deletion described in §17.
- **Jobs**, moderate listings (toggle active, feature, delete).
- **Tools** (`tools.php`), the global on/off switch per tool and per-person grants.
- **Operations**, launch-readiness checks, backups, storage and DB footprint, scraper status, smoke-test links.
- **Settings**, site config, feature toggles, the maintenance gate, system health.
- **Learning**, courses, modules, quizzes, certificates, and **certificate branding**.
- **Ebooks** and **Events**, with **event-registrants**.
- **Categories / Countries**, taxonomy and market coverage.
- **Badges and Seeds**, create badges, distribute Seeds singly or in bulk, seed rates, transactions.
- **Blog / Forum**, content management and moderation.
- **Audience** and **Outreach** (`email-campaigns.php`), the email marketing console (§13).
- **Header ads** (`header-ads.php`), sponsor banners and their placements.
- **Activity** (`activity.php`) and **Online** (`online.php`), the audit log and live presence.
- **backup-download.php**, admin-only secure backup streaming.

---

## 13. Outreach, the email marketing console (`admin/email-campaigns.php`)

- **Campaign list:** subject, segment, recipient count, delivered/failed, status badge, date, with Edit (drafts), Reuse, and Delete.
- **Compose:** subject and preview text; recipient segment (all verified, seekers, employers, unverified, joined last 7 days, joined last 30 days) with a live AJAX count; free-text additional recipients merged and de-duplicated at send time; poster upload (JPG/PNG/GIF/WebP, 8 MB cap) rendered full width in email-safe markup; an HTML body editor with toolbar shortcuts and a live preview inside the real template; personalisation tokens `{{name}}`, `{{first_name}}`, `{{email}}`.
- **Sending** enqueues to `email_queue` and is drained by cron, so the request never blocks on bulk delivery.
- **Unsubscribe and suppression:** every campaign email carries a per-recipient HMAC-signed unsubscribe link (`/unsubscribe`). Unsubscribes land in `email_unsubscribes` and are skipped on future sends, counted as skipped rather than failed. Transactional email is exempt.
- **Stuck-send recovery:** anything left in `sending` for more than 15 minutes is marked failed on the next page load.
- **Token safety:** blank names fall back to "there", and any unreplaced `{{token}}` is stripped before send.

---

## 14. Transactional email (`includes/mailer.php`)

`Mailer` sends via Brevo HTTP API, then SMTP, then `mail()`, wrapping content in a branded responsive template. Implemented messages:

1. Verification email, Welcome
2. Application confirmation (seeker)
3. Application status update (seeker)
4. Job match alert (seeker), sent by the alert cron
5. Payment receipt (seeker)
6. New application alert (employer)
7. Job posting confirmed (employer)
8. Event registration confirmation, with a calendar link
9. Event reminder, per reminder window
10. Password changed, Password reset

---

## 15. The tool catalogue (`includes/tools.php`, `ai/`, `api/`)

Every tool is defined once in `includes/tools.php`, with a key, name, URL, group, backing API endpoints, whether it is built, whether it is free, its credit cost, and whether it offers a free preview. `tests/tools_audit.php` keeps the registry and its three readers in agreement.

| Tool | Group | Access |
|------|-------|--------|
| CV Builder | CV | free |
| CV Optimizer (`ai/roast.php`) | AI | 1 credit, score shown free, fixes paid |
| Cover Letter AI (`ai/cover-letter.php`) | AI | 1 credit |
| Cold Pitch (`ai/cold-pitch.php`) | AI | 1 credit |
| Andika (`ai/andika.php`) | AI | gated |
| Job Match (`ai/JobMatcher.php`) | Jobs | gated |
| Talent Passport | Profile | Seeds |
| Certificates | Learning | credits or Seeds |
| Interview Prep | AI | 2 credits |
| Skills Gap | AI | 1 credit |
| Salary Analyzer | Data | gated |
| Tax Calculator | Data | gated |

**Andika** is the career assistant. `ai/andika-brain.php` holds its reasoning, `includes/andika_widget.php` gives it a panel that opens on any page, and `api/andika.php` serves it. It is location-aware (profile country, then IP geo, then a sensible default), and a signed-out visitor is told the door is closed rather than shown something broken. The gate means the same thing on every page it appears.

Models run through OpenRouter (free Llama 3.2 3B by default, Gemini and Groq keys available). Costs are deducted from credits or the Seeds wallet.

---

## 16. Learning, ebooks, events, certificates

- **Academy** (`learn/`): courses, modules, quizzes, enrollment, checkout, my-courses. Backed by migration `0011`.
- **Ebooks** (`ebooks/`): a library of free and paid downloadable resources, with the download itself gated.
- **Events** (`events/`): webinars, workshops and meetups, online or in-person, with registration, a confirmation email carrying a calendar link, and reminders sent by cron. A visitor who clicks Register while signed out has that intent stored against their account and completed after verification, and can have more than one pending at a time.
- **Certificates** (`certificates/`): issued on course completion, with verification codes (`CERT_PREFIX = JMT`, pass score 70), public verification, admin branding, claim, preview and download. The code is exposed as both `cert_code` and `verification_code` because different parts of the app read different names.

---

## 17. Accounts, presence and the community

- **Talent Passport** (`wallet/passport/`) is a verifiable skills profile with public verification, endorsements, employer access, contact logs, and view analytics.
- **Account deletion** (`includes/account_deletion.php`) removes the person without gutting the site. Only one foreign key points at `users.user_id`, so authorship is detached rather than cascaded, and `tests/account_deletion_audit.php` checks the routine against the live schema.
- **Presence** (`admin/online.php`, migration `0030`) records who is here now, which `last_login` cannot answer.
- **Community** (`community/`) has categories, topics, replies, and three kinds of reaction rather than a single like, capped at one per person per post. Official accounts show a verified tick.
- **Broadcasts** (migration `0031`) store an announcement once instead of copying a notification row to every member.
- **Content views** (migration `0029`) record who looked at what, where `blog_posts.views` and `forum_topics.views` were bare counters.

---

## 18. Blog and sponsorship

- **Blog** (`blog/`): articles with categories, search and author attribution, managed in admin.
- **Sponsor banners**: `includes/header_ads.php` renders at most one active, scheduled, highest-priority banner. Ads carry a placement so they can sit in more than one position. Clicks go through `go/ad.php`. Nothing stored is ever rendered as markup.

---

## 19. Scheduled work (`cron/`)

| Script | Cadence | What it does |
|--------|---------|--------------|
| `run_job_scrapers.php` | every 45 min | Pulls external roles from 13 sources (see `cron/README.md`), geo-filtered toward Africa, deduplicated. Keyed sources auto-skip when their env vars are blank |
| `fetch_wwr_jobs.php` | with the above | WeWorkRemotely specifically |
| `process_email_queue.php` | every 2 min | Drains `email_queue` via Brevo, retries up to 3 times, reconciles campaign counts |
| `send_job_match_alerts.php` | daily 07:00 | Matches verified seekers to new jobs in their country and enqueues the alert. Gated by `JOB_MATCH_ALERTS_ENABLED`, supports `--dry-run` and `--force` |
| `send_event_reminders.php` | daily | Event reminders, tracked per registration so none is sent twice |
| `fetch_exchange_rates.php` | daily | Refreshes the derived-currency rates used by `pricing_display.php` |
| `backfill_job_countries.php` | one-off/ad hoc | Fills country on historic job rows |
| `prune_old_records.php` | periodic | Retention cleanup |
| `run_backup.php` | daily | Database and uploads backups with retention, surfaced in admin Operations |

Sources, keys and single-source invocations are documented in `cron/README.md`.

---

## 20. Tests (`tests/`)

Dependency-free, run with plain `php`.

- **`run.php`**, unit checks (Paystack math, password hashing and strength, escaping, unsubscribe HMAC, match composer) plus opt-in HTTP smoke checks with `--base=URL`, including an `/uploads` PHP-exec regression guard.
- **`schema_audit.php`**, checks every INSERT and UPDATE in the codebase against the real columns. Written after a profile save failed silently against a column that did not exist. Migration `0025` came out of this.
- **`tools_audit.php`**, keeps the tool registry and its readers in agreement.
- **`account_deletion_audit.php`**, checks deletion against the live schema.
- **`event_intent_audit.php`**, walks the signed-out event registration journey end to end.

---

## 21. JSON API (`api/`)

`jobs`, `job-matches`, `auth`, `user`, `config`, `countries`, `courses`, `certificates`, `notifications`, `redeem-seeds`, `andika`, `cv-extract`, `cv-roast`, `cover-letter`, `cold-pitch`, `_ai_tools` (shared tool plumbing), `employer/contact-talent`, `passport/verify`, `v1/test`, and `webhooks/paystack`.

Auth runs through `api/middleware.php` (`api_require_login`, `api_require_role`, JSON 401/403). Input comes through `api_input()`, which accepts a JSON body or a form post.

---

## 22. Frontend and navigation

- Navigation is defined once in `includes/navigation.php`. It previously lived inside `header.php`, which meant pages built on `ai-header.php` could not reach it and hand-wrote their own.
- The header is frosted at rest and brand blue once the page moves (`sticky_header.php` decides when, CSS decides how).
- One toast and sound system (`feedback.php`) replaced three that disagreed on markup.
- The footer is unified across the `jm_minimal_footer()` and `includes/footer.php` paths.
- **PWA:** no splash of ours. Android draws its own from the manifest, and `boot.php` supplies the blue ground so the launch does not flash white.
- **Performance:** the 4.6 MB hero photograph is gone, the hero is served at its displayed size and preloaded only where it is on the first screen, brand fonts ship as woff2, and duplicated ticker logos are out of the tab order.

---

## 23. Database migrations and deployment

Schema changes are managed by an ordered, idempotent runner. Schema is never created in a request path.

- **Runner:** `database/migrate.php` (CLI only). Tracks applied versions in `schema_migrations` and is safe to run on every deploy.
- **Migrations:** `database/migrations/schema/NNNN_*.php`, each returning `function (PDO $pdo): void`, guarded with `jm_mig_has_table` / `jm_mig_has_column`. **Current set: 0001 through 0040.**
- Broadly: 0001-0006 auth, campaigns, queue; 0007-0016 passport, badges, certificates, courses, ebooks/events, blog/forum, notifications, settings, content monetization; 0017-0024 remember tokens, ads and placements, official accounts, forum reactions, badge realignment, event reminders; 0025-0036 schema drift repair, seed references, tool gating, catalogue merge, content views, presence, broadcasts, event intent, detachable authorship, user settings, country dedupe; 0037-0040 CV builder tables and the three job indexes.
- **Deploy:** `git pull` on the VPS, then `php database/migrate.php`.
- Legacy ad-hoc scripts in `database/migrations/` (`*.sql`, older `*.php`) are not picked up by the runner, which only matches `NNNN_*.php`.

---

## 24. Known issues and open decisions

The June 2026 audit ([JOBMINGTON_REVIEW_2026.md](JOBMINGTON_REVIEW_2026.md)) is closed except for two items. Both are still open as of this revision.

**Open**

- **Header scaffolding.** `navigation.php` fixed the nav drift, but the header scaffolding itself is still duplicated across `header.php`, `ai-header.php`, and the area-specific headers. One parameterised partial would end it.
- **One CSS system.** Tailwind CDN is down from site-wide to three files (`includes/header.php`, `includes/ai-header.php`, `wallet/passport/endorse.php`), but it is still a runtime CDN build in production, alongside ten hand-written stylesheets. The open decision is whether to compile and purge Tailwind or finish removing the utility classes from those three.

**Not yet switched on**

- `JOB_MATCH_ALERTS_ENABLED` is still off by default. The matching logic is country-based and wants tuning before real sends.

**Closed since the last revision**

Debug and setup scripts removed; `jobs.status` webhook bug fixed; `/uploads` PHP execution denied in nginx; runtime `CREATE`/`ALTER` consolidated into the migration runner; unsubscribe and suppression list live; email sending moved to the async cron queue; job-match alert cron written; `session.php` reformatted; smoke tests added, then joined by four audit scripts; schema drift between the code and the live database found and repaired.
