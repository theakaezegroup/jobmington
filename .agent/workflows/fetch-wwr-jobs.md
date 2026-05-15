---
description: How to run the WWR RSS job fetcher
---

# Running the WWR RSS Fetcher

This workflow describes how to fetch remote jobs from the WeWorkRemotely RSS feed.

## Manual Execution

// turbo
1. Open a terminal in the project root (`c:\xampp\htdocs\Jobmington`).
// turbo
2. Run the fetcher script:
   ```bash
   php cron/fetch_wwr_jobs.php
   ```
3. The script will output:
   - Jobs that are skipped due to geo-filters (US Only, UK Only, etc.)
   - Jobs that are successfully imported
   - A final count of imported and skipped jobs

## Automating with Windows Task Scheduler

1. Open **Task Scheduler** (search in Start Menu).
2. Click **Create Basic Task**.
3. Name it: `Jobmington WWR Fetcher`
4. Set trigger: **Daily** (or as needed).
5. Set action: **Start a program**
   - Program: `C:\xampp\php\php.exe`
   - Arguments: `C:\xampp\htdocs\Jobmington\cron\fetch_wwr_jobs.php`
6. Finish and enable the task.

## Automating with Linux Cron (if on Linux server)

Add this line to your crontab (`crontab -e`):

```
0 */4 * * * /usr/bin/php /var/www/html/Jobmington/cron/fetch_wwr_jobs.php >> /var/log/wwr_fetch.log 2>&1
```

This runs the fetcher every 4 hours.
