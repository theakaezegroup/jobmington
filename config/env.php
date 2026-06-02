<?php
// JOBMINGTON - Environment Configuration

if (!function_exists('jm_env_load_file')) {
    function jm_env_load_file(string $path): void {
        if (!is_readable($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!$lines) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            if ($key === '') {
                continue;
            }

            if (
                (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                (str_starts_with($value, "'") && str_ends_with($value, "'"))
            ) {
                $value = substr($value, 1, -1);
            }

            if (getenv($key) === false) {
                putenv($key . '=' . $value);
            }
            $_ENV[$key] = getenv($key) !== false ? getenv($key) : $value;
            $_SERVER[$key] = $_ENV[$key];
        }
    }
}

if (!function_exists('jm_env_default')) {
    function jm_env_default(string $key, string $value): void {
        if (getenv($key) === false) {
            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}

$rootDir = dirname(__DIR__);
jm_env_load_file($rootDir . '/.env.local');
jm_env_load_file($rootDir . '/.env');

// Local development defaults. Production values should come from server env or .env.
jm_env_default('DB_HOST', 'localhost');
jm_env_default('DB_NAME', 'jobmington_db');
jm_env_default('DB_USER', 'root');
jm_env_default('DB_PASS', '');

jm_env_default('APP_ENV', 'development');
jm_env_default('APP_DEBUG', 'true');
jm_env_default('APP_TIMEZONE', 'Africa/Lagos');

// Secret used to sign unsubscribe tokens (and other low-value HMACs).
// Set a unique value in production .env; the default keeps links stable in dev.
jm_env_default('APP_KEY', 'jm-default-app-key-change-me');

jm_env_default('PAYSTACK_PUBLIC_KEY', '');
jm_env_default('PAYSTACK_SECRET_KEY', '');
jm_env_default('PAYSTACK_BASE_URL', 'https://api.paystack.co');
jm_env_default('PAYSTACK_CALLBACK_URL', 'http://localhost/jobmington/payments/verify.php');
jm_env_default('PAYSTACK_WEBHOOK_URL', 'http://localhost/jobmington/api/webhooks/paystack.php');

jm_env_default('GOOGLE_CLIENT_ID', '');
jm_env_default('GOOGLE_CLIENT_SECRET', '');
jm_env_default('LINKEDIN_CLIENT_ID', '');
jm_env_default('LINKEDIN_CLIENT_SECRET', '');

jm_env_default('OPENROUTER_API_KEY', '');
jm_env_default('GEMINI_API_KEY', '');
jm_env_default('GROQ_API_KEY', '');

jm_env_default('JOB_SCRAPER_LIMIT', '80');
jm_env_default('JOB_SCRAPER_USER_AGENT', 'JobmingtonBot/1.0 (+https://jobmington.com/contact)');

// Adzuna aggregator API (free key at https://developer.adzuna.com). Without
// credentials the adzuna source is skipped automatically. ADZUNA_COUNTRIES is a
// comma-separated list of Adzuna country codes (e.g. za,gb) queried for remote roles.
jm_env_default('ADZUNA_APP_ID', '');
jm_env_default('ADZUNA_APP_KEY', '');
jm_env_default('ADZUNA_COUNTRIES', 'za,gb');

// The Muse (https://www.themuse.com/developers/api/v2). Key is optional but
// recommended to avoid tight rate limits; the source runs keyless if blank.
jm_env_default('THEMUSE_API_KEY', '');

// Jooble (https://jooble.org/api/about). Free key; the source is skipped if blank.
// JOOBLE_KEYWORDS / JOOBLE_LOCATION shape the search (defaults target remote + Nigeria).
jm_env_default('JOOBLE_API_KEY', '');
jm_env_default('JOOBLE_KEYWORDS', 'remote');
jm_env_default('JOOBLE_LOCATION', '');

// TheirStack (https://theirstack.com/en/api). Bearer token; aggregates LinkedIn,
// Indeed, Glassdoor + more. Skipped if blank. Free plan caps job age (default 7 days).
jm_env_default('THEIRSTACK_API_KEY', '');
jm_env_default('THEIRSTACK_MAX_AGE_DAYS', '7');

// Fantastic.jobs via RapidAPI (Active Jobs DB). Needs a RapidAPI key; skipped if blank.
jm_env_default('FANTASTICJOBS_RAPIDAPI_KEY', '');
jm_env_default('FANTASTICJOBS_RAPIDAPI_HOST', 'active-jobs-db.p.rapidapi.com');

// Careerjet (https://www.careerjet.com/partners/api/). Needs an affiliate id (affid);
// skipped if blank. CAREERJET_LOCATION narrows the search (blank = anywhere).
jm_env_default('CAREERJET_AFFID', '');
jm_env_default('CAREERJET_LOCATION', '');

// Job-match alert emails (cron/send_job_match_alerts.php). Off by default so
// scheduling the cron never blasts real users until you explicitly opt in.
jm_env_default('JOB_MATCH_ALERTS_ENABLED', 'false');
