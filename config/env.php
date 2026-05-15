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
