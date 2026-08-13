<?php
/**
 * JOBMINGTON - the day's exchange rates, so a visitor sees a price in money
 * they recognise.
 *
 * Jobmington is an African platform, not a Nigerian one, and a page that leads
 * with Naira tells a Kenyan otherwise before they have read a word. So prices
 * lead in dollars and carry the visitor's own currency beside them, which needs
 * a rate for every currency rather than the one hand-set Naira rate.
 *
 * Rates are stored, not fetched per request. A pricing page must never wait on
 * a third party, and must never break when that third party is down: if this
 * cron fails, yesterday's stored rates keep serving and the site does not
 * notice. That is the whole reason the fetch lives here and not in the page.
 *
 *   php cron/fetch_exchange_rates.php
 *   php cron/fetch_exchange_rates.php --show
 *
 * open.er-api.com needs no key and covers 166 currencies including every
 * African one the site lists. It publishes once a day, so running this more
 * than daily buys nothing.
 */

define('JOBMINGTON', true);

require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/maintenance.php';

$options = getopt('', ['show']);

function jm_rates_log(string $line): void
{
    echo '[' . date('Y-m-d H:i:s') . '] ' . $line . PHP_EOL;
}

if (isset($options['show'])) {
    $stored = json_decode((string) jm_setting('exchange_rates', '{}'), true);
    $when   = (string) jm_setting('exchange_rates_fetched_at', 'never');
    jm_rates_log('Stored ' . (is_array($stored) ? count($stored) : 0) . ' rate(s), fetched ' . $when);
    foreach (['NGN', 'KES', 'GHS', 'ZAR', 'EGP', 'EUR', 'GBP'] as $code) {
        jm_rates_log(sprintf('  %-4s %s', $code, $stored[$code] ?? 'missing'));
    }
    exit(0);
}

$url = 'https://open.er-api.com/v6/latest/USD';

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 12,
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_USERAGENT      => 'JobmingtonBot/1.0 (+https://jobmington.com/contact)',
    CURLOPT_SSL_VERIFYPEER => true,
]);
$body = curl_exec($ch);
$code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err  = curl_error($ch);
curl_close($ch);

if ($body === false || $code < 200 || $code >= 300) {
    // Deliberately not clearing anything. The stored rates stay in place and
    // the site keeps quoting yesterday's numbers, which is right: a stale rate
    // marked approximate is a small inaccuracy, and no rate at all is a
    // pricing page with a hole in it.
    jm_rates_log('Fetch failed (' . ($err ?: 'HTTP ' . $code) . '). Keeping the stored rates.');
    exit(1);
}

$data = json_decode((string) $body, true);

if (!is_array($data) || ($data['result'] ?? '') !== 'success' || empty($data['rates']) || !is_array($data['rates'])) {
    jm_rates_log('Response was not usable. Keeping the stored rates.');
    exit(1);
}

/*
 * Keep only what the site can actually show: the currencies its own countries
 * table names, plus the majors. Storing all 166 puts a payload in a settings
 * row that nothing ever reads.
 */
$wanted = ['USD', 'EUR', 'GBP', 'CAD', 'AUD'];
try {
    foreach (db()->query("SELECT DISTINCT currency_code FROM countries WHERE currency_code IS NOT NULL AND currency_code <> ''") as $row) {
        $wanted[] = strtoupper(trim((string) $row['currency_code']));
    }
} catch (Throwable $e) {
    jm_rates_log('Could not read the countries table: ' . $e->getMessage());
}
$wanted = array_values(array_unique($wanted));

$rates = [];
$missing = [];
foreach ($wanted as $currency) {
    if (isset($data['rates'][$currency]) && is_numeric($data['rates'][$currency]) && $data['rates'][$currency] > 0) {
        $rates[$currency] = round((float) $data['rates'][$currency], 6);
    } else {
        $missing[] = $currency;
    }
}

// A response that has lost the currencies the site is built around is a bad
// response, whatever its status code said.
if (!isset($rates['NGN']) || count($rates) < 5) {
    jm_rates_log('Response was missing core currencies. Keeping the stored rates.');
    exit(1);
}

jm_setting_save('exchange_rates', json_encode($rates));
jm_setting_save('exchange_rates_fetched_at', date('Y-m-d H:i:s'));
jm_setting_save('exchange_rates_source_date', (string) ($data['time_last_update_utc'] ?? ''));

jm_rates_log('Stored ' . count($rates) . ' rate(s). 1 USD = ' . $rates['NGN'] . ' NGN.');
if ($missing) {
    jm_rates_log('Not published for: ' . implode(', ', $missing));
}
exit(0);
