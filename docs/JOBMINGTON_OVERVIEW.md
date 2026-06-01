# Jobmington — Application Overview

_Last updated: 2026-06-01. This reflects the codebase as currently deployed (supersedes the initial-commit `JOBMINGTON_FULL_DOCUMENTATION.md` where they differ — notably transactional emails, forgot/reset password, standardised navigation, and the admin Outreach console)._

---

## 1. What Jobmington is

Jobmington is a career platform for African talent. It combines a **job marketplace**, **AI career tools**, a **CV studio**, an **employer hiring console**, a **learning academy**, a **wallet/rewards system**, a **community forum**, and an **admin back office** into a single PHP application.

Tagline in the product: _"Simple hiring for African talent."_ Brand tagline constant: _"Preparing Africa's Workforce for the Future."_

- **Primary market:** Nigeria-first, broader Africa. Default country = Nigeria; currency NGN.
- **Domain:** https://jobmington.com
- **Audience:** job seekers, employers, and platform admins.

---

## 2. Technical stack

| Layer | Choice |
|------|--------|
| Language | PHP 8.3 (procedural + light OOP) |
| Database | MySQL / MariaDB (PDO, `utf8mb4`), DB name `jobmington_db` |
| Web server (prod) | nginx + php-fpm (unix socket) on a Linode VPS |
| Web server (dev) | XAMPP/Apache on Windows, served under `/jobmington/` |
| CSS | `minimal-jobmington.css` (hand-written design system) + Tailwind (CDN) + `premium-design-system.css` on some pages |
| Icons | Inline SVG (preferred) + Font Awesome 6 (admin/legacy pages) |
| Payments | Paystack (NGN, kobo) |
| Email | Brevo (HTTP API → SMTP → `mail()` fallback chain) |
| AI | OpenRouter (free Llama 3.2 3B by default), with Gemini/Groq keys wired |
| Fonts | Futura Cyrillic (Demi/Book), self-hosted |
| PWA | manifest + service worker |

**Deployment model:** VPS is the source of truth. Work is committed locally, pushed to GitHub (`theakaezegroup/jobmington`), then deployed by `git pull` on the VPS at `/var/www/jobmington`. nginx rewrites legacy `/jobmington/*` paths to root and rewrites `.php` URLs to extensionless.

---

## 3. Directory map

```
config/        env loader, constants, PDO singleton
includes/      shared libs: security, session, functions, mailer, paystack,
               monetization, seeds, badges, backup, location_detect,
               header.php / footer.php / ai-header.php / ai-footer.php
auth/          register, login, logout, verify-email, forgot/reset password
jobs/          browse, view, apply, saved  (+ _job_helpers.php)
seeker/        dashboard, applications, profile, settings, notifications, badges
employer/      dashboard, post-job, manage-jobs, edit-job, applications,
               view-applicant, company-profile, talent-pool (+ _employer_helpers.php)
admin/         command center, users, jobs, operations, settings, categories,
               countries, courses, modules, quizzes, certificates, badges,
               blog, forum, illustration-states, email-campaigns, backup-download
ai/            andika.php, roast.php  (AI tool front-ends)
cv-builder/    landing, editor, templates, import, export
learn/         academy: index, course, module, quiz, checkout, my-courses
payments/      seeker-premium, job-posting, credits, talent-access, checkout,
               success/failed, verify, callbacks
wallet/        Seeds wallet + history; wallet/passport (Talent Passport)
community/     forum index, topic, new-topic
blog/          index, post, category, search
certificates/  index, view, example
api/           JSON endpoints + middleware + webhooks/paystack.php
cron/          run_job_scrapers, fetch_wwr_jobs, run_backup
database/migrations/  schema + seed scripts
assets/        css, js, images, fonts
uploads/       resumes, avatars, company-logos, campaign-posters
```

---

## 4. Configuration layer

- **`config/env.php`** — loads `.env.local` then `.env` (no Composer/vlucas dependency; a small hand-rolled parser), then applies safe defaults for DB, app env, Paystack, OAuth, AI keys, and the job scraper. Existing real env vars are never overwritten.
- **`config/constants.php`** — site identity, paths, upload limits, pagination sizes, session/security constants, **all monetization pricing** (env-overridable, NGN), the NGN/USD rate, and enums (user types, job types, experience levels, application statuses, difficulty levels).
- **`config/database.php`** — `Database` PDO singleton: exception mode, assoc fetch, real prepares (`EMULATE_PREPARES => false`), `utf8mb4_unicode_ci`. Exposes the global `db()` helper. Prints raw error only when `APP_DEBUG=true`.

---

## 5. Security model

| Concern | Implementation (`includes/security.php`, `session.php`) |
|--------|------------------|
| Password hashing | Argon2id, memory_cost 65536, time_cost 4, threads 3 |
| CSRF | per-session token, `hash_equals` verify, `csrfField()` helper, rotated after sensitive actions |
| Sessions | httponly, SameSite=Strict, strict mode, cookies-only, secure under HTTPS, periodic `session_regenerate_id`, UA-binding, fixation prevention on login |
| Output escaping | `e()` / `Security::escape()` = `htmlspecialchars(ENT_QUOTES \| ENT_HTML5)` |
| File uploads | MIME sniff via `finfo`, size cap, random filename, per-type allow-list |
| Rate limiting | `checkRateLimit()` backed by a `rate_limits` table (sha256(key+IP)) |
| Activity logging | `logActivity()` → `activity_logs` (user, action, IP, UA) |
| Access control | `Session::requireLogin / requireRole / requireAdmin / requireVerified`; admin role is re-checked against the DB on `requireAdmin` |

Roles: `seeker`, `employer`, `admin`. Admin passes all role checks. `isEmployer()` also returns true for admins.

> See [JOBMINGTON_REVIEW_2026.md](JOBMINGTON_REVIEW_2026.md) §1 for current security gaps (exposed debug scripts, uploads exec on nginx).

---

## 6. Access logic by role

- **Public:** homepage, job browse/view, blog, pricing, FAQ, legal pages, auth pages, AI tool landing.
- **Job seeker (verified):** apply to jobs, dashboard, applications, saved jobs, profile, settings, notifications, badges, CV studio, AI tools (some gated by Seeds/Premium), wallet, Talent Passport, learning academy.
- **Employer:** company profile (required before posting), post/manage/edit jobs, review applications, view applicants, talent pool, employer subscriptions.
- **Admin:** full back office (see §12). Admin nav: Command · Users · Jobs · Operations · Learning · **Outreach** · Settings.

---

## 7. Authentication

- **Register** (`auth/register.php`) — seeker or employer; sends a verification email (`Mailer::sendVerificationEmail`) with an `activation_token`.
- **Verify email** (`auth/verify-email.php`) — token lookup, sets `is_verified = 1`, clears token, refreshes session; sends welcome email.
- **Login** (`auth/login.php`) — failed-attempt counter + lockout (`failed_login_attempts`, `locked_until`), auto-creates lockout columns if missing, role-based dashboard redirect, safe-redirect support.
- **Forgot password** (`auth/forgot-password.php`) — validates email, generates a 32-byte token with 1-hour expiry (`reset_token`, `reset_expires` columns auto-created), emails the reset link via `Mailer::sendPasswordReset`. Always shows a neutral "if that email exists…" message (no enumeration).
- **Reset password** (`auth/reset-password.php`) — validates token + expiry, enforces password strength, hashes with `password_hash`, clears the token, sends `Mailer::sendPasswordChanged` confirmation.
- **Logout** clears the session.

Navigation across auth pages is standardised: _Find jobs · CV Builder · Employers_ + a contextual CTA.

---

## 8. Job marketplace

- **Browse** (`jobs/index.php`) — filters, pagination (`JOBS_PER_PAGE = 12`), location-aware.
- **View** (`jobs/view.php`) — full role detail, company info, salary range, match coaching.
- **Apply** (`jobs/apply.php`):
  - External-URL jobs send the seeker off-site.
  - Internal jobs require login + verification; employers can't apply.
  - On submit: inserts into `job_applications` (status `pending`), bumps `applications_count`, notifies the employer in-app, **emails the seeker a confirmation** (`sendApplicationConfirmation`) and **emails the employer a new-application alert** (`sendNewApplicationAlert`).
- **Saved jobs** (`jobs/saved.php`).
- **Match coaching** — a lightweight role-fit coach + cover-note suggestion (`jm_job_match_coach`).

Jobs are activated via `is_active = 1`. Free/starter packages publish immediately; paid packages stay unpublished until payment succeeds.

---

## 9. CV studio (`cv-builder/`)

Landing, completion meter, template gallery, editor, import (parse an existing CV), and export. Backed by `cv_profiles`. Feeds the AI tools and the job-match coach.

---

## 10. Employer platform (`employer/`)

- **Company profile** — required before posting; redirect-guarded.
- **Post job** (`post-job.php`) — full validation (title, description, country, type, experience, salary sanity, deadline), unique slug, posting-package selection. Paid packages create a pending `transactions` row and redirect to payment; free packages publish and **email a "job is live" confirmation** (`sendJobPostingConfirmed`).
- **Manage / edit jobs**, **applications** (status workflow: pending → reviewed → shortlisted → interview → rejected/hired). Changing status **emails the seeker** (`sendApplicationStatusUpdate`) and notifies in-app.
- **View applicant**, **talent pool**.
- Nav: Dashboard · Company · Jobs · Applications · Talent · Pricing + Post a job.

---

## 11. Monetization, payments & wallet

**Pricing (NGN, env-overridable — see `constants.php`):**
- Employer: single post ₦30,000; featured add-on ₦3,000; Basic ₦7,500/mo (3 posts); Pro ₦15,000/mo.
- Seeker Premium: ₦3,000/mo or ₦30,000/yr.
- Credit packs: ₦500×1, ₦2,000×5, ₦3,000×10.
- Per-tool credit costs (CV optimizer, cover letter, interview prep, skills-gap).
- Bundle: Job Toolkit ₦1,500.

**Paystack (`includes/paystack.php`):** initialize/verify transactions, charge authorization (recurring), plans, transfers, bank resolve. Amounts in kobo. Webhook signature validated with HMAC-SHA512 + `hash_equals`. `SSL_VERIFYPEER` on.

**Payment flows (`payments/`):** seeker premium, job posting, credits, talent access, generic checkout, success/failed, verify, and per-feature callbacks. Callbacks verify the transaction, mark it completed inside a DB transaction, then fulfil (activate subscription / add credits / publish job). **The seeker-premium callback also emails a receipt** (`sendPaymentReceipt`).

**Webhook (`api/webhooks/paystack.php`):** idempotent, transactional safety-net for `charge.success/failed`, transfers, and subscription lifecycle. Routes fulfilment by transaction type. _(Note: the employer-post branch has a column bug — see review §1.2.)_

**Wallet / Seeds (`includes/seeds.php`, `wallet/`):** an internal rewards currency ("Seeds"). Earned, granted by admins, or purchased; spent on premium AI tools. `wallets` + `seed_transactions` + `seed_rates`.

---

## 12. Admin back office (`admin/`)

- **Command center** (`index.php`) — KPIs (users, live jobs, applications, revenue, employers, learning, passports, signals), "needs attention" alerts, operations health bars, quick-access tiles, recent-activity feeds.
- **Users** (`users.php`) — search/filter/sort, role change, suspend/activate, verify, unlock, generate temp password, and **CSV export** (ID, name, email, role, verified, active, joined, last login — respects current filters, UTF-8 BOM for Excel).
- **Jobs** — moderate listings (toggle active, feature, delete).
- **Operations** — launch-readiness checks, backups (run/download), storage & DB footprint, scraper status, smoke-test links.
- **Settings** — site config, feature toggles, system health.
- **Learning** — courses, modules, quizzes (+ questions), certificates.
- **Categories / Countries** — taxonomy & market coverage.
- **Badges & Seeds** — create badges, distribute Seeds (single & bulk), seed-rate config, transactions.
- **Blog / Forum** — content management & moderation.
- **Outreach** (`email-campaigns.php`) — the email-marketing console (see §13).
- **backup-download.php** — admin-only secure backup file streaming.

---

## 13. Outreach — email marketing console (`admin/email-campaigns.php`)

A Mailchimp-style tool built on the Brevo sending layer.

- **Campaign list:** subject, segment, recipient count, delivered/failed, status badge (draft/sending/sent/failed), date. Per-row **Edit** (drafts), **Reuse** (duplicate any campaign → new draft), **Delete**.
- **Compose:**
  - Subject + inbox preview text.
  - **Recipient segment:** all verified / seekers / employers / unverified / joined-last-7-days / joined-last-30-days, with a **live AJAX recipient count**.
  - **Additional recipients:** free-text box for any email addresses (one per line / comma / semicolon), validated + de-duplicated live; merged with the segment at send time, duplicates skipped. Lets you email people who aren't registered.
  - **Poster/flier upload:** drag-and-drop or browse (JPG/PNG/GIF/WebP, ≤8 MB), stored under `uploads/campaign-posters/`, rendered full-width (email-safe table markup) above the body in every recipient's email. Edit view shows the current poster with a remove option.
  - **HTML body editor** with toolbar shortcuts (H2, paragraph, CTA button, divider, footer note, jobs list) and a **live preview** that renders the content inside the real Jobmington email template.
  - **Personalisation tokens:** `{{name}}`, `{{first_name}}`, `{{email}}`.
  - **Save draft** or **Send now**. Sending loops recipients via Brevo and records sent/failed/skipped counts.
  - **Unsubscribe & suppression:** every campaign email carries a per-recipient HMAC-signed unsubscribe link (`/unsubscribe`). Unsubscribes land in `email_unsubscribes` and are skipped on future sends (counted as "skipped", not failed). Transactional emails are exempt.
  - **Stuck-send recovery:** a send that dies mid-loop (PHP timeout) leaves `status='sending'`; anything in that state >15 min is auto-marked `failed` on the next page load (`started_at` column).
  - **Token safety:** blank names fall back to "there"; any unreplaced `{{token}}` is stripped before send.
- **Storage:** `email_campaigns` + `email_unsubscribes` tables, created by the migration runner (see §23).

---

## 14. Transactional email (`includes/mailer.php`)

`Mailer` sends via **Brevo HTTP API → SMTP → `mail()`** fallback, wrapping content in a branded, responsive HTML template (white header, badge logo, orange accent, footer with privacy/terms). Implemented messages:

1. Verification email · Welcome
2. **Application confirmation** (seeker)
3. **Application status update** (seeker) — shortlisted/interview/hired/rejected/reviewed
4. **Job match alert** (seeker) — _template exists; not yet triggered_
5. **Payment receipt** (seeker)
6. **New application alert** (employer)
7. **Job posting confirmed** (employer)
8. **Password changed** + **Password reset**

---

## 15. AI tools (`ai/`, `api/andika.php`)

- **Andika AI** — career chat + tools (interview practice, salary guide, career roadmap), location-aware (profile country → IP geo → Nigeria default), some tools gated by Seeds cost (`seed_rates`).
- **CV Roast** (`ai/roast.php`, `api/cv-roast.php`) — CV scoring, missing keywords, rewrite guidance.
- Model via OpenRouter (free Llama 3.2 3B default; Gemini/Groq keys available). Costs deducted from the Seeds wallet.

---

## 16. Learning academy (`learn/`) & certificates

Courses → modules → quizzes; enrollments; completion certificates with verification codes (`CERT_PREFIX = JMT`, pass score 70). Public certificate verification under `certificates/` and `api/passport/verify.php`. Managed from the admin Learning area.

---

## 17. Talent Passport (`wallet/passport/`)

A verifiable skills/assessment profile with public verification — positions a seeker's validated competencies for employers.

---

## 18. Blog & community

- **Blog** (`blog/`) — articles with categories, search, author attribution; managed in admin.
- **Community** (`community/`) — forum categories, topics, replies; moderated in admin.

---

## 19. Automated job ingestion (`cron/`)

- `run_job_scrapers.php` / `fetch_wwr_jobs.php` — pull external roles (e.g. WeWorkRemotely), geo-filtered toward Africa, deduplicated; status tracked for the admin Operations panel.
- `run_backup.php` — database + uploads backups with retention, surfaced in admin Operations and downloadable via `backup-download.php`.
- `process_email_queue.php` — **async email worker** (every 2 min). Delivers queued campaign + match-alert emails via Brevo, retries up to 3×, updates campaign counts, reconciles finished campaigns. Backed by `email_queue` + `includes/email_queue.php`.
- `send_job_match_alerts.php` — **job-match alerts** (daily 07:00). Matches verified seekers to new jobs in their country and enqueues the alert email. **Gated by `JOB_MATCH_ALERTS_ENABLED` (off by default)**; supports `--dry-run` / `--force`; dedupes via `users.last_job_match_alert`.
- Cron commands are documented in the admin Operations page and `cron/README.md`.

**Testing:** `tests/run.php` is a dependency-free runner — unit checks (Paystack math, password hashing/strength, escaping, unsubscribe HMAC, match composer) plus opt-in HTTP smoke checks (`--base=URL`, includes an `/uploads` PHP-exec-blocked regression guard). Run `php tests/run.php` (unit) or `php tests/run.php --base=https://jobmington.com` (full).

---

## 20. JSON API (`api/`)

Endpoints: `jobs`, `job-matches`, `auth`, `user`, `countries`, `courses`, `certificates`, `andika`, `cv-extract`, `cv-roast`, `employer/contact-talent`, `passport/verify`, `v1/test`, and `webhooks/paystack`. Auth via `api/middleware.php` (`api_require_login` / `api_require_role`, JSON 401/403). Input via `api_input()` (JSON body or form post).

---

## 21. Frontend & navigation

- Three canonical nav patterns (recently standardised): **public** (Find jobs · CV Builder · Andika AI · Employers · Pricing + auth CTA), **seeker** (Find jobs · CV Builder · Dashboard · Applications · Saved jobs · Profile + Sign out), **auth** (Find jobs · CV Builder · Employers + contextual CTA). Employer and admin areas have their own dedicated navs.
- Footer is unified across both the `jm_minimal_footer()` and `includes/footer.php` paths.
- Mobile menu, toast system, and confirm-modal system are global (in `footer.php`).

---

## 22. Database migrations & deployment

Schema changes are managed by an ordered, idempotent runner — **schema is no longer created at runtime in request paths**.

- **Runner:** `database/migrate.php` (CLI-only). Tracks applied versions in `schema_migrations`; safe to run on every deploy (no-op when current).
- **Migrations:** `database/migrations/schema/NNNN_*.php`, each returning `function (PDO $pdo): void { … }`, guarded with `jm_mig_has_table` / `jm_mig_has_column` so first runs are safe on databases whose columns predate the runner. Current set: `0001` user security columns, `0002` password-reset columns, `0003` email_campaigns, `0004` email_unsubscribes.
- **Deploy:** `git pull` on the VPS, then `php database/migrate.php`.
- Legacy ad-hoc scripts in `database/migrations/` (`*.sql`, older `*.php`) are **not** picked up by the runner (it only matches `NNNN_*.php`).

---

## 23. Known issues & roadmap

See **[JOBMINGTON_REVIEW_2026.md](JOBMINGTON_REVIEW_2026.md)** for the full audit. Status:

- ✅ Removed publicly-exposed debug/setup scripts + committed admin session file.
- ✅ Fixed the `jobs.status` column reference in the Paystack webhook.
- ✅ Blocked PHP execution under `/uploads` in nginx (images still served).
- ✅ Consolidated runtime `CREATE/ALTER` into ordered migrations + runner.
- ✅ Real unsubscribe + suppression list for campaigns.
- ✅ Async cron queue (`email_queue`); campaign + match-alert sends are now queued, not synchronous.
- ✅ Job-match alert cron implemented (env-gated off until opted in).
- ✅ `session.php` reformatted; smoke-test suite added (`tests/run.php`).
- 🟠 **Unify header scaffolding** — 5 header patterns remain (deferred; multi-page regression risk).
- 🟠 **One CSS system** — still ships Tailwind CDN + hand-written CSS + premium-design-system. Open decision: compile/purge Tailwind vs. remove utilities.
- 🟢 Enable `JOB_MATCH_ALERTS_ENABLED` once the matching logic (currently country-based) is tuned to taste.
