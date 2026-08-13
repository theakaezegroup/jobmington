<?php
/**
 * JOBMINGTON - what price a visitor sees, and in which currency.
 *
 * Jobmington is an African platform, not a Nigerian one. A page that opens in
 * Naira tells a Kenyan otherwise before they have read a word, so the dollar
 * leads everywhere and the visitor's own currency sits beside it. Naira is one
 * of those currencies rather than the one the page is built around.
 *
 * Two numbers are set and never move:
 *
 *   The dollar price, which is the plan. It is what the page leads with, and a
 *   plan whose headline figure drifts with the currency market is not a plan.
 *
 *   The Naira price, which is what Paystack charges. Set separately and kept
 *   round, so no Nigerian is ever repriced because a rate moved overnight.
 *
 * Everything else is derived from the daily rates and labelled approximate,
 * because it is: an indication so a visitor can size the price, not an offer.
 *
 * jm_format_ngn() is untouched. Receipts and admin reporting read it and keep
 * showing what was really charged.
 */

if (!defined('JOBMINGTON')) {
    http_response_code(403);
    exit('Forbidden');
}

require_once __DIR__ . '/../config/database.php';

/**
 * The visitor's country, as a two-letter code, or '' when it is not known.
 *
 * Cloudflare adds CF-IPCountry to every request it forwards, so the lookup is
 * free and cannot fail as a page-load dependency. Not knowing is a normal
 * answer, and the caller then shows dollars alone.
 */
function jm_visitor_country(): string
{
    static $country = null;
    if ($country !== null) {
        return $country;
    }

    $raw = strtoupper(trim((string) ($_SERVER['HTTP_CF_IPCOUNTRY'] ?? '')));

    // XX is an address Cloudflare cannot place, T1 is Tor.
    if ($raw === '' || $raw === 'XX' || $raw === 'T1' || strlen($raw) !== 2) {
        return $country = '';
    }
    return $country = $raw;
}

/**
 * The currency of the visitor's country, from the countries table.
 *
 * @return array{code:string, symbol:string}|null
 */
function jm_visitor_currency(): ?array
{
    static $currency = false;
    if ($currency !== false) {
        return $currency;
    }

    $code = jm_visitor_country();
    if ($code === '') {
        return $currency = null;
    }

    try {
        $stmt = db()->prepare("SELECT currency_code, currency_symbol FROM countries
                               WHERE iso_code = ? AND currency_code IS NOT NULL AND currency_code <> '' LIMIT 1");
        $stmt->execute([$code]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        error_log('jm_visitor_currency: ' . $e->getMessage());
        return $currency = null;
    }

    if (!$row) {
        return $currency = null;
    }

    return $currency = [
        'code'   => strtoupper((string) $row['currency_code']),
        'symbol' => trim((string) $row['currency_symbol']) ?: strtoupper((string) $row['currency_code']),
    ];
}

/** The stored daily rates, keyed by currency, relative to one US dollar. */
function jm_rates(): array
{
    static $rates = null;
    if ($rates !== null) {
        return $rates;
    }

    require_once __DIR__ . '/maintenance.php';
    $stored = json_decode((string) jm_setting('exchange_rates', '{}'), true);

    return $rates = is_array($stored) ? $stored : [];
}

/** When the rates were last fetched, so a stale set is visible in admin. */
function jm_rates_fetched_at(): ?string
{
    require_once __DIR__ . '/maintenance.php';
    $when = (string) jm_setting('exchange_rates_fetched_at', '');
    return $when !== '' ? $when : null;
}

/**
 * Round to something a person would say out loud.
 *
 * A converted figure of 2,455.37 is accurate and unreadable. Big numbers lose
 * their tail entirely, because nobody reads the last two digits of a price in
 * shillings and their presence only makes the figure look calculated.
 */
function jm_round_money(float $amount): float
{
    if ($amount >= 10000) {
        return round($amount / 100) * 100;
    }
    if ($amount >= 1000) {
        return round($amount / 10) * 10;
    }
    if ($amount >= 100) {
        return round($amount);
    }
    if ($amount >= 10) {
        return round($amount * 2) / 2;
    }
    return round($amount, 2);
}

/** A money figure with its symbol, spaced only where the symbol needs it. */
function jm_money(float $amount, string $symbol): string
{
    $rounded  = jm_round_money($amount);
    $decimals = ($rounded < 100 && fmod($rounded, 1.0) !== 0.0) ? 2 : 0;
    $figure   = number_format($rounded, $decimals);

    /*
     * A one-character symbol hugs the number; a short code like KSh needs air.
     * Counted in characters, not bytes: the cedi sign is three bytes and would
     * otherwise be treated as a word and printed as "₵ 255". No mbstring on
     * the server, so the /u regex does the counting.
     */
    $length = preg_match_all('/./u', $symbol) ?: strlen($symbol);
    return $length > 2 ? $symbol . ' ' . $figure : $symbol . $figure;
}

/**
 * The plan price in dollars.
 *
 * Set explicitly per plan, so the headline figure is stable. Where a plan has
 * no dollar price of its own it is derived from the Naira at the day's rate,
 * which is correct but will drift, so anything customer-facing should be set.
 */
function jm_usd_price(float $ngn, ?float $usd = null): float
{
    if ($usd !== null && $usd > 0) {
        return $usd;
    }

    $rate = (float) (jm_rates()['NGN'] ?? 0);
    if ($rate <= 0) {
        $rate = (float) (defined('NGN_USD_RATE') ? NGN_USD_RATE : 1600);
    }
    return jm_round_money($ngn / $rate);
}

/** The dollar figure as text. */
function jm_usd_text(float $ngn, ?float $usd = null): string
{
    if ($ngn <= 0 && ($usd === null || $usd <= 0)) {
        return 'Free';
    }
    return jm_money(jm_usd_price($ngn, $usd), '$');
}

/**
 * The visitor's own currency figure for a dollar price, or null.
 *
 * Null when we do not know where they are, when their currency is the dollar,
 * or when today's rates do not carry it. In every one of those cases the
 * dollar alone is the honest answer, and inventing a second figure would not
 * help anybody.
 */
function jm_local_equivalent(float $ngn, ?float $usd = null): ?array
{
    $currency = jm_visitor_currency();
    if ($currency === null || $currency['code'] === 'USD') {
        return null;
    }

    /*
     * Nigeria is the exception, and it is exact rather than approximate: the
     * Naira price is set, not converted, because it is the amount Paystack
     * will actually take. Quoting a Nigerian an approximation of the number
     * they are about to be charged would be worse than quoting nothing.
     */
    if ($currency['code'] === 'NGN') {
        return $ngn > 0 ? ['text' => jm_format_ngn($ngn), 'exact' => true] : null;
    }

    $rate = (float) (jm_rates()[$currency['code']] ?? 0);
    if ($rate <= 0) {
        return null;
    }

    return ['text' => jm_money(jm_usd_price($ngn, $usd) * $rate, $currency['symbol']), 'exact' => false];
}

/**
 * A price, ready to put on a page.
 *
 * @param string $suffix something like '/mo', attached to the leading figure
 * @return array{lead:string, note:string}
 */
function jm_price_parts(float $ngn, string $suffix = '', ?float $usd = null): array
{
    if ($ngn <= 0 && ($usd === null || $usd <= 0)) {
        return ['lead' => 'Free', 'note' => ''];
    }

    $lead  = jm_usd_text($ngn, $usd) . $suffix;
    $local = jm_local_equivalent($ngn, $usd);

    if ($local === null) {
        return ['lead' => $lead, 'note' => ''];
    }

    return [
        'lead' => $lead,
        // The literal character, not "\u{2248}": that escape is only read
        // inside double quotes and would otherwise print as backslash-u.
        'note' => $local['exact'] ? $local['text'] : '≈ ' . $local['text'],
    ];
}

/**
 * The leading figure alone, as plain text.
 *
 * For the places a price is a label rather than markup: a plan feature reading
 * "Save $4 vs monthly", a badge, an email subject. Those strings get escaped by
 * whatever renders them, so they cannot carry the spans jm_price() returns, and
 * a saving is not worth a second currency beside it anyway.
 */
function jm_price_text(float $ngn, ?float $usd = null): string
{
    return jm_usd_text($ngn, $usd);
}

/** The same thing as one string, for the many places that just need a price. */
function jm_price(float $ngn, string $suffix = '', ?float $usd = null): string
{
    $parts = jm_price_parts($ngn, $suffix, $usd);

    if ($parts['note'] === '') {
        return '<span class="jm-price-lead">' . $parts['lead'] . '</span>';
    }

    return '<span class="jm-price-lead">' . $parts['lead'] . '</span>'
         . '<span class="jm-price-note">' . e($parts['note']) . '</span>';
}

/**
 * The one line a page says about currency, instead of repeating it per price.
 *
 * Payment is taken in Naira because that is what the gateway does, and nobody
 * should discover that at the card form. Said once, quietly, rather than beside
 * every figure, which would put Naira back at the centre of the page.
 */
function jm_currency_footnote(): string
{
    $currency = jm_visitor_currency();

    if ($currency !== null && $currency['code'] === 'NGN') {
        return 'Prices in US dollars, with the Naira you will be charged beside them.';
    }

    if ($currency !== null && $currency['code'] !== 'USD') {
        return 'Prices in US dollars. Local amounts are approximate; payment is taken in Naira.';
    }

    return 'Prices in US dollars. Payment is taken in Naira, and international cards are accepted.';
}
