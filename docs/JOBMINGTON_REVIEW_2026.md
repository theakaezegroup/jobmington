# Jobmington — Code Review, Feedback & Recommendations

_Review date: 2026-06-01 · Reviewer: engineering audit pass · Scope: full codebase (184 PHP files, ~52k LOC), live VPS, DB schema._

This document is an honest assessment. It leads with the things that need attention, then records what is genuinely well-built, then gives a prioritised action list. A companion document, [JOBMINGTON_OVERVIEW.md](JOBMINGTON_OVERVIEW.md), describes how the app actually works today.

---

## 0. TL;DR

Jobmington is a genuinely ambitious, feature-rich platform — job board, AI tools, CV studio, payments, learning academy, wallet, community, and now an email-marketing console. The core security primitives (password hashing, CSRF, prepared statements, Paystack webhook signature checks) are **done correctly**. The architecture is clean PHP with a sensible config/includes/feature-folder layout.

The problems are not in the hard parts — they are in **housekeeping and operational hygiene**:

- **Debug/setup scripts and a real admin session file are live and world-readable on production.** This is the single most urgent item.
- A **broken column reference** in the Paystack webhook can fail employer-payment fulfilment.
- **Uploads are not protected against script execution on nginx** (the `.htaccess` guard is silently ignored).
- The schema is **created imperatively at runtime** (`CREATE TABLE IF NOT EXISTS` / `ALTER` scattered across pages) rather than via ordered migrations.

None of these require a rewrite. They are a focused day or two of cleanup.

---

## 1. 🔴 Critical — fix today

### 1.1 Debug & setup scripts are publicly reachable on production

All of the following return **HTTP 200** on `https://jobmington.com`:

| URL | What it is | Risk |
|-----|-----------|------|
| `/sess_codexAdminRender3` | A serialized **admin** PHP session file committed to the repo | Leaks a real admin's name, email, and `user_id` + confirms they are admin. Possible session artefact reuse. |
| `/setup` | Creates countries/categories in the DB | Unauthenticated DB writes |
| `/upgrade_db` | Runs `ALTER TABLE` | Unauthenticated schema change |
| `/import` | Bulk job import (root jobs.json importer) | Unauthenticated data injection |
| `/session_test` | Raw `session_start()` that **sets arbitrary session values from `$_GET`** | Session manipulation primitive |
| `/debug_country` | Country-switch debug dump | Info disclosure |
| `/jobs.json` | 76 KB scraper dump | Minor info disclosure |

These are all **tracked in git** and deployed via `git pull`. The `.gitignore` covers `.env` but not these.

**Recommendation (in order):**
1. `git rm` all of them: `setup.php`, `upgrade_db.php`, `import.php`, `debug_country.php`, `session_test.php`, `sess_codexAdminRender3`, `jobs.json`, `mobile_menu_code.txt`. Commit, push, pull on VPS, then delete any stragglers on disk. (NOT `cv-builder/import.php` — that is the live, auth-guarded CV-import endpoint.)
2. Treat the leaked admin session as compromised: that admin should change their password (the session file also tells an attacker which `user_id` to target).
3. Add a deny rule in nginx for safety-net coverage of dev artefacts (e.g. `location ~* \.(json|sql|log|txt)$ { deny all; }` scoped appropriately, plus block `sess_*`).
4. Genuine one-off migration scripts belong in `database/migrations/` and should be **CLI-only** (guard with `if (PHP_SAPI !== 'cli') exit;`).

### 1.2 Paystack webhook references a non-existent `jobs.status` column

In `api/webhooks/paystack.php` (`handleChargeSuccess`, employer-post branch):

```php
$pdo->prepare("UPDATE jobs SET is_active = 1, status = 'active' WHERE job_id = ?")->execute([$jobId]);
```

The `jobs` table has **`is_active` only — there is no `status` column** (verified against the live schema). With `PDO::ERRMODE_EXCEPTION` on, this throws, the surrounding `beginTransaction()` is **rolled back** (un-marking the transaction as completed), and the handler returns **500** — so Paystack keeps retrying the webhook and the job is never activated by this path.

It is currently masked because the synchronous `job-posting-callback` activates jobs on redirect, but the webhook is the safety net for exactly the case where the user closes the tab before redirect — i.e. it fails when it matters most.

**Recommendation:** drop `, status = 'active'` (use `is_active = 1` only), or add a `status` column if you intend to use a richer lifecycle. Then re-send a test event from the Paystack dashboard and confirm a 200.

### 1.3 Uploaded files are executable on nginx

`uploads/.htaccess` contains `<Files *.php> deny from all`, but **nginx does not read `.htaccess`** — it is dead weight. There is no nginx `location` block preventing PHP execution under `/uploads`. Today the campaign-poster upload validates MIME type (good), but every upload surface (CVs, avatars, company logos, posters) writes into a directory that nginx will happily execute if a `.php` ever lands there.

**Recommendation:** add to the nginx server block:

```nginx
location ^~ /uploads/ {
    location ~ \.php$ { deny all; return 403; }
}
```

---

## 2. 🟠 High — fix this week

### 2.1 Schema is built at runtime instead of via migrations

`email_campaigns` is created with `CREATE TABLE IF NOT EXISTS` inside `admin/email-campaigns.php`, and columns (`poster_url`, `custom_emails`) are added with inline `ALTER`s guarded by `INFORMATION_SCHEMA` checks. The same pattern appears in `auth/forgot-password.php` (reset columns) and `admin/users.php` (lockout columns).

This works, but it means:
- Every page load runs `INFORMATION_SCHEMA` queries it doesn't need.
- Schema state depends on **which page happened to be visited first**.
- There's no single source of truth for the schema, and no rollback.

**Recommendation:** consolidate into ordered, idempotent migration files in `database/migrations/` plus a tiny runner (a `migrations` table tracking applied versions). Keep the runtime guards only as a transitional safety net, then remove them.

### 2.2 `.env` discipline & secret rotation

`.env` is correctly git-ignored. But given that debug endpoints were exposed and the repo was pulled to a shared host, **rotate the Paystack secret key, any AI API keys, and the DB password** as a precaution. Confirm `APP_ENV=production` and `APP_DEBUG=false` on the VPS — `config/database.php` prints the raw DB error to the page when `APP_DEBUG=true`, which would leak connection details.

### 2.3 Email sending is synchronous and unthrottled

The campaign sender (`admin/email-campaigns.php`) loops recipients and calls Brevo inline within the request. For a few hundred users this is fine; past that you'll hit:
- PHP `max_execution_time` → the request dies mid-send, leaving the campaign stuck in `sending`.
- Brevo rate limits → a burst of failures counted as "failed".

**Recommendations:**
- Add a "stuck campaign" recovery (any campaign in `sending` older than N minutes → mark `failed`/resumable).
- For volume, move sending to a cron-driven queue (a `campaign_recipients` table with per-row status), or use **Brevo's native campaign API** rather than per-recipient transactional sends.
- Add a real unsubscribe link + suppression list. Right now the template footer links `#`. Bulk email without a working unsubscribe risks deliverability and is legally required in most jurisdictions.

### 2.4 Personalisation token edge case

Tokens (`{{name}}`, `{{first_name}}`, `{{email}}`) are substituted for registered users, but **custom (non-registered) recipients have no name** — they'll receive `{{first_name}}` rendered as empty / "there". Confirm the templates degrade gracefully (e.g. "Hi there," when name is blank), and strip any unreplaced `{{…}}` tokens before send so raw tokens never reach an inbox.

---

## 3. 🟡 Medium — quality & consistency

### 3.1 `includes/session.php` formatting is corrupted
Every line is double-spaced (blank line between each line of code). It works, but it's 643 lines for ~250 lines of logic and is unpleasant to maintain. Reformat it.

### 3.2 Two parallel footers / header systems
- `includes/footer.php` (used by blog, learn, admin, payments, community…) was just unified to the `jm-footer` markup — good.
- `jm_minimal_footer()` in `functions.php` is the canonical one.
- There are still **5 header patterns** (main `header.php`, `jm_jobs_header`, `jm_employer_header`, `ai-header.php`, inline auth/seeker). Navs were just standardised, but the *header scaffolding* is still duplicated. Consider one parameterised header partial to kill the drift permanently.

### 3.3 Mixed styling stacks
The app loads **both Tailwind (CDN) and a hand-written `minimal-jobmington.css`**, plus `premium-design-system.css` on some pages. The Tailwind CDN build is not meant for production (no purge, large download, runtime compile). Pick one system or compile Tailwind properly.

### 3.4 `Security::clean()` uses `strip_tags`
`strip_tags` is a blunt instrument — it silently mangles legitimate input containing `<` (e.g. "salary < 50k"). Prefer validating/escaping on output (you already have `e()` / `htmlspecialchars` everywhere) rather than destroying input on the way in.

### 3.5 Inconsistent path handling
URLs are hardcoded as `/jobmington/...` throughout, and the VPS papers over this with an nginx `rewrite` + `sub_filter` that rewrites outgoing HTML. This is fragile (sub_filter only catches patterns it knows; it also disables some optimisations). A `base_url()` helper already exists in `functions.php` — standardise on it and drop the rewrite layer over time.

### 3.6 Activity logging / rate limiting depend on tables that may not exist
`Security::logActivity()` and `checkRateLimit()` assume `activity_logs` and `rate_limits` tables. If they're missing on a fresh install, these throw. Ensure they're in the canonical migration set.

---

## 4. 🟢 What's done well (keep doing this)

- **Password security:** Argon2id with sane cost params. Above industry norm.
- **CSRF:** token generated, `hash_equals` comparison, rotated after sensitive actions, helper `csrfField()` used consistently across forms.
- **SQL:** prepared statements with bound params essentially everywhere I looked — no string-concatenated user input into queries.
- **Paystack webhook:** raw-body HMAC-SHA512 verified with `hash_equals`, idempotency guard (`status === 'completed'`), wrapped in a DB transaction, typed fulfilment switch. Textbook.
- **Session hardening:** httponly, SameSite=Strict, strict mode, secure cookie under HTTPS, periodic ID regeneration, UA-binding, fixation prevention on login.
- **Email deliverability path:** Brevo HTTP API first, SMTP fallback, then `mail()` — resilient, and avoids IP-whitelist pain. The transactional templates are clean and on-brand.
- **Defensive DB access:** the `admin_scalar` / `jm_*_rows` wrappers swallow and log errors so one bad query doesn't white-screen a dashboard.
- **Feature completeness:** for a solo/small-team build this is a remarkable surface area, and the recent work (transactional emails, forgot-password, nav standardisation, outreach console, CSV export) is cohesive.

---

## 5. Recommendations beyond bug-fixing (product/eng direction)

1. **Background job runner.** Several things want to be async: campaign sends, job-match alert emails (#3 in the email plan — still unimplemented), scraper runs, backups. A single cron-driven queue table would unlock all of them cleanly.
2. **Job-match alert emails.** The `Mailer::sendJobMatchAlert()` template exists but nothing calls it. A nightly cron matching seekers to new roles would make it real and is high-value retention.
3. **Test coverage.** There are zero automated tests. Even a thin smoke-test suite (login, apply, post job, pay callback) would catch regressions like the `jobs.status` bug before users do.
4. **Observability.** You log to PHP error_log liberally (good instinct) but there's no aggregation. A lightweight error tracker (or even tailing into the admin Operations panel) would surface webhook 500s.
5. **Admin audit trail.** Admin actions (role changes, seed grants, bulk emails) should be logged to `activity_logs` with the acting admin's id — partially done; make it universal.
6. **Rate-limit the AI + auth endpoints.** `checkRateLimit()` exists; ensure it actually wraps `auth/login.php`, `api/andika.php`, and the campaign send.

---

## 6. Prioritised action checklist

**Today**
- [ ] Remove all debug/setup/session files from repo + disk; push + pull.
- [ ] Rotate the exposed admin's password; rotate Paystack/AI/DB secrets.
- [ ] Fix the `jobs.status` webhook bug.
- [ ] Add nginx `/uploads` PHP-exec deny block.
- [ ] Confirm `APP_DEBUG=false`, `APP_ENV=production` on VPS.

**This week** — ✅ all done 2026-06-01
- [x] Working unsubscribe + suppression list (`email_unsubscribes`, HMAC-signed `/unsubscribe`, per-recipient footer link, send-loop skip).
- [x] Stuck-`sending` recovery (`started_at` column; >15 min in `sending` → `failed` on load).
- [x] Strip unreplaced `{{tokens}}` before send; blank-name falls back to "there".
- [x] Ordered migration runner (`database/migrate.php` + `database/migrations/schema/`, `schema_migrations` tracking); inline `CREATE/ALTER` removed from login/users/forgot-password/email-campaigns.

**This month**
- [ ] Introduce a cron queue; move campaign + match-alert emails onto it.
- [ ] Implement the job-match alert cron (use the existing template).
- [ ] Reformat `session.php`; unify header scaffolding.
- [ ] Decide on one CSS system; stop shipping Tailwind CDN to production.
- [ ] Add a smoke-test suite around auth + payments.
