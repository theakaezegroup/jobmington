# Job Scraping Cron

Use `run_job_scrapers.php` on the Linode VPS to import jobs from stable feeds/APIs.

Recommended cron schedule:

```cron
*/45 * * * * /usr/bin/php /var/www/jobmington/cron/run_job_scrapers.php --limit=80 >> /var/www/jobmington/logs/job-scraper-cron.log 2>&1
```

Single-source runs:

```bash
php /var/www/jobmington/cron/run_job_scrapers.php --source=wwr --limit=60
php /var/www/jobmington/cron/run_job_scrapers.php --source=remoteok --limit=60
```

Jobberman:

Do not scrape Jobberman listing/detail pages without written permission. Their public robots rules block `/job/`, `/api/`, and many search/filter URLs, so the right production route is a partner feed, API, XML, CSV, or webhook from Jobberman. Once that access is approved, add a `jobberman` source adapter to `run_job_scrapers.php` that consumes the approved feed and maps fields into the existing `jobs` table.

Notes:

- The runner uses a lock file so overlapping cron runs exit safely.
- It writes a structured text log to `logs/job-scraper.log`.
- It writes the latest run summary to `logs/job-scraper-status.json`, which the admin Operations page reads.
- It filters out obvious location-restricted jobs and keeps global, EMEA, Africa, Nigeria, Ghana, Kenya, South Africa, and broad remote roles.
- Prefer feeds/APIs over brittle page scraping. If a source has no feed/API, add it only after confirming the source permits automated access.
