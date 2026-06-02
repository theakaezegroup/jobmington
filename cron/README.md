# Job Scraping Cron

Use `run_job_scrapers.php` on the Linode VPS to import jobs from stable feeds/APIs.

Recommended cron schedule:

```cron
*/45 * * * * /usr/bin/php /var/www/jobmington/cron/run_job_scrapers.php --limit=80 >> /var/www/jobmington/logs/job-scraper-cron.log 2>&1
```

Sources (all run by default; each can be run alone with `--source=`):

| source | type | key needed |
| --- | --- | --- |
| `wwr` | WeWorkRemotely RSS | no |
| `remoteok` | RemoteOK JSON API | no |
| `remotive` | Remotive JSON API | no |
| `jobicy` | Jobicy JSON API | no |
| `arbeitnow` | Arbeitnow job-board JSON API | no |
| `himalayas` | Himalayas JSON API | no |
| `themuse` | The Muse API (key optional, recommended) | optional |
| `devitjobs` | DevITjobs UK JSON feed | no |
| `adzuna` | Adzuna aggregator API (Indeed-style breadth) | **yes** |
| `jooble` | Jooble search engine (covers Nigeria) | **yes** |
| `theirstack` | TheirStack (LinkedIn/Indeed/Glassdoor + 16 sites) | **yes** |
| `fantasticjobs` | Fantastic.jobs via RapidAPI (Active Jobs DB) | **yes** |
| `careerjet` | Careerjet aggregator (affiliate id) | **yes** |

Keyed sources auto-skip when their env vars are blank, so `--source=all` never logs
a guaranteed failure. Keys go in `.env` (see `.env.example`):

| source | env vars | get a key |
| --- | --- | --- |
| `themuse` | `THEMUSE_API_KEY` (optional) | https://www.themuse.com/developers/api/v2 |
| `jooble` | `JOOBLE_API_KEY`, `JOOBLE_KEYWORDS`, `JOOBLE_LOCATION` | https://jooble.org/api/about |
| `theirstack` | `THEIRSTACK_API_KEY`, `THEIRSTACK_MAX_AGE_DAYS` | https://theirstack.com/en/api |
| `fantasticjobs` | `FANTASTICJOBS_RAPIDAPI_KEY`, `FANTASTICJOBS_RAPIDAPI_HOST` | https://rapidapi.com (Active Jobs DB) |
| `careerjet` | `CAREERJET_AFFID`, `CAREERJET_LOCATION` | https://www.careerjet.com/partners/api/ |

Single-source runs:

```bash
php /var/www/jobmington/cron/run_job_scrapers.php --source=remotive --limit=60
php /var/www/jobmington/cron/run_job_scrapers.php --source=jobicy --limit=60
php /var/www/jobmington/cron/run_job_scrapers.php --source=adzuna --limit=60
```

Adzuna setup:

Adzuna is an official aggregator with a free API key (https://developer.adzuna.com). It is
the legitimate route to Indeed-style breadth. Without `ADZUNA_APP_ID` / `ADZUNA_APP_KEY`
the runner silently skips the source, so `--source=all` never logs a guaranteed failure.
Set the keys plus `ADZUNA_COUNTRIES` (comma-separated Adzuna country codes, e.g. `za,gb`)
in `.env`. The adapter queries each country for `what=remote` roles and spreads `--limit`
across them.

Indeed / Jobgether / RemoteWorkHer:

These were evaluated and intentionally not added. Indeed retired its public Publisher API
and prohibits scraping; Jobgether returns HTTP 403 on everything (Cloudflare bot block);
RemoteWorkHer is a JavaScript SPA with no RSS/JSON feed and no individual job URLs in its
sitemap. Use Adzuna for Indeed-style coverage instead. If any of them later offer a partner
feed/API, add an adapter to `run_job_scrapers.php` the same way.

Jobberman:

Do not scrape Jobberman listing/detail pages without written permission. Their public robots rules block `/job/`, `/api/`, and many search/filter URLs, so the right production route is a partner feed, API, XML, CSV, or webhook from Jobberman. Once that access is approved, add a `jobberman` source adapter to `run_job_scrapers.php` that consumes the approved feed and maps fields into the existing `jobs` table.

Notes:

- The runner uses a lock file so overlapping cron runs exit safely.
- It writes a structured text log to `logs/job-scraper.log`.
- It writes the latest run summary to `logs/job-scraper-status.json`, which the admin Operations page reads.
- It filters out obvious location-restricted jobs and keeps global, EMEA, Africa, Nigeria, Ghana, Kenya, South Africa, and broad remote roles.
- Prefer feeds/APIs over brittle page scraping. If a source has no feed/API, add it only after confirming the source permits automated access.
