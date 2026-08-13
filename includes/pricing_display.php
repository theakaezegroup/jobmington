<?php
/**
 * JOBMINGTON - what price a visitor sees, and in which currency.
 *
 * Prices are set, stored, and charged in Naira. That does not change here and
 * must not: Paystack takes Naira, so Naira is the only number that is ever
 * true at the moment money moves.
 *
 * What changes is the number a visitor reads first. Someone in Lagos thinks in
 * Naira and someone in Nairobi does not, and a page that opens with a figure
 * the reader cannot size is a page they leave. So the currency they think in
 * leads, and the Naira that will actually be charged is stated underneath.
 *
 * Three rules hold this together, and breaking any one of them turns a helpful
 * conversion into a misleading price:
 *
 *   1. The converted figure is always marked approximate. It is an indication,
 *      not an offer, because the rate is a number in a settings table and the
 *      real one moved this morning.
 *   2. The Naira amount is always shown alongside it. Nobody should reach
 *      Paystack and meet a number they have not already seen.
 *   3. Nothing here touches what is charged. jm_format_ngn() stays exactly what
 *      it was, and receipts and admin reporting keep using it.
 */

if (!defined('JOBMINGTON')) {
    http_response_code(403);
    exit('Forbidden');
}

require_once __DIR__ . '/../config/database.php';

/**
 * The visitor's country, as a two-letter code, or '' when it is not known.
 *
 * Cloudflare sits in front of the site and adds CF-IPCountry to every request
 * it forwards, so the lookup is free, needs no third party, and cannot be a
 * page-load dependency that fails. Not knowing is a normal answer: a request
 * that reaches the origin directly has no header, and the caller falls back to
 * showing dollars rather than guessing.
 */
function jm_visitor_country(): string
{
    static $country = null;
    if ($country !== null) {
        return $country;
    }

    $raw = strtoupper(trim((string) ($_SERVER['HTTP_CF_IPCOUNTRY'] ?? '')));

    // Cloudflare sends XX for an address it cannot place, and T1 for Tor.
    if ($raw === '' || $raw === 'XX' || $raw === 'T1' || strlen($raw) !== 2) {
        return $country = '';
    }
    return $country = $raw;
}

/** Naira per US dollar. The settings row wins; the constant is the fallback. */
function jm_usd_rate(): float
{
    static $rate = null;
    if ($rate !== null) {
        return $rate;
    }

    require_once __DIR__ . '/maintenance.php';
    $stored = (float) jm_setting('ngn_usd_rate', 0);

    // A nonsense rate is worse than no conversion at all, so anything outside
    // a plausible band is ignored in favour of the constant.
    if ($stored >= 100 && $stored <= 100000) {
        return $rate = $stored;
    }
    return $rate = (float) (defined('NGN_USD_RATE') ? NGN_USD_RATE : 1600);
}

/** When the rate was last set, so the admin can see it going stale. */
function jm_usd_rate_updated_at(): ?string
{
    require_once __DIR__ . '/maintenance.php';
    $stamp = (string) jm_setting('ngn_usd_rate_updated_at', '');
    return $stamp !== '' ? $stamp : null;
}

/**
 * Naira to dollars, rounded to something a person would say out loud.
 *
 * $18.99 is a real conversion and a terrible price. Under ten dollars keeps a
 * half so small amounts do not all collapse to the same figure; above it,
 * whole dollars.
 */
function jm_usd_amount(float $ngn): float
{
    $usd = $ngn / jm_usd_rate();

    if ($usd < 1) {
        return round($usd, 2);
    }
    if ($usd < 10) {
        return round($usd * 2) / 2;
    }
    return (float) round($usd);
}

/** The dollar figure as text, without the approximation marker. */
function jm_usd_text(float $ngn): string
{
    $usd = jm_usd_amount($ngn);

    if ($usd <= 0) {
        return 'Free';
    }
    if ($usd < 1) {
        return 'under $1';
    }

    // Whole dollars lose the decimals; a half keeps both of them. Stripping
    // trailing zeros generally gives "$4.5", which is not how money is written.
    $withCents = number_format($usd, 2);
    return '$' . (str_ends_with($withCents, '.00') ? number_format($usd, 0) : $withCents);
}

/** Should this visitor see Naira first? */
function jm_shows_naira_first(): bool
{
    // Nigeria only. Everywhere else, including a request we cannot place,
    // leads with dollars, because dollars is the currency this audience
    // already thinks in for remote work.
    return jm_visitor_country() === 'NG';
}

/**
 * A price, ready to put on a page.
 *
 * Returns the leading figure and the line that goes under it, rather than
 * finished markup, so a card can lay them out however it already lays things
 * out and nothing here has to know about CSS.
 *
 * @param string $suffix something like '/mo', attached to the leading figure
 * @return array{lead:string, note:string, ngn:string, usd:string, naira_first:bool}
 */
function jm_price_parts(float $ngn, string $suffix = ''): array
{
    $nairaText = jm_format_ngn($ngn);
    $usdText   = jm_usd_text($ngn);
    $free      = $ngn <= 0;

    if ($free) {
        return ['lead' => 'Free', 'note' => '', 'ngn' => 'Free', 'usd' => 'Free', 'naira_first' => true];
    }

    if (jm_shows_naira_first()) {
        return [
            'lead'        => $nairaText . $suffix,
            'note'        => 'about ' . $usdText,
            'ngn'         => $nairaText,
            'usd'         => $usdText,
            'naira_first' => true,
        ];
    }

    return [
        'lead'        => $usdText . $suffix,
        // Rule 2: the amount Paystack will actually take, before they get there.
        'note'        => 'approx. Billed as ' . $nairaText,
        'ngn'         => $nairaText,
        'usd'         => $usdText,
        'naira_first' => false,
    ];
}

/**
 * The same thing as one string, for the many places that just need a price.
 *
 * The note is a span so a card can style or hide it, and it is always present:
 * a converted figure with nothing qualifying it is the misleading version.
 */
function jm_price(float $ngn, string $suffix = ''): string
{
    $parts = jm_price_parts($ngn, $suffix);

    if ($parts['note'] === '') {
        return '<span class="jm-price-lead">' . $parts['lead'] . '</span>';
    }

    return '<span class="jm-price-lead">' . $parts['lead'] . '</span>'
         . '<span class="jm-price-note">' . e($parts['note']) . '</span>';
}
