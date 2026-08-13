<?php
/**
 * JOBMINGTON - turning a stated job location into a country.
 *
 * Its own file because the scraper is not the only thing that needs it. The
 * backfill has to reach the same verdict on rows already in the table, and
 * requiring cron/run_job_scrapers.php to borrow the function would start a
 * scrape as a side effect of asking a question.
 *
 * Pure: no database, no network, so it can be tested directly.
 */

if (!defined('JOBMINGTON')) {
    http_response_code(403);
    exit('Forbidden');
}

/**
 * The gazetteer behind jm_scraper_country_of().
 *
 * Country names and the cities that actually appear in the feeds, which is why
 * the city list is uneven: Germany gets two dozen entries because German boards
 * supply thousands of listings that name only a city, and most countries get
 * none because their listings name the country.
 *
 * ISO code, currency and symbol travel with each entry so a country missing
 * from the table can still be created correctly.
 *
 * @return array<string, array{0:string,1:string,2:string,3:string}> needle => [name, iso, currency, symbol]
 */
function jm_scraper_gazetteer(): array {
    $countries = [
        // The markets Jobmington is actually for. Cities included because a
        // local board writes "Lagos", not "Lagos, Nigeria".
        'nigeria' => ['Nigeria', 'NG', 'NGN', 'NGN'],
        'lagos' => 'nigeria', 'abuja' => 'nigeria', 'port harcourt' => 'nigeria',
        'ibadan' => 'nigeria', 'benin city' => 'nigeria', 'kano' => 'nigeria',

        'kenya' => ['Kenya', 'KE', 'KES', 'KES'],
        'nairobi' => 'kenya', 'mombasa' => 'kenya', 'kisumu' => 'kenya',

        'ghana' => ['Ghana', 'GH', 'GHS', 'GHS'],
        'accra' => 'ghana', 'kumasi' => 'ghana', 'takoradi' => 'ghana',

        'south africa' => ['South Africa', 'ZA', 'ZAR', 'ZAR'],
        'johannesburg' => 'south africa', 'cape town' => 'south africa',
        'pretoria' => 'south africa', 'durban' => 'south africa',
        'gauteng' => 'south africa', 'western cape' => 'south africa',
        'kwazulu' => 'south africa', 'eastern cape' => 'south africa',

        'egypt' => ['Egypt', 'EG', 'EGP', 'EGP'], 'cairo' => 'egypt',
        'rwanda' => ['Rwanda', 'RW', 'RWF', 'RWF'], 'kigali' => 'rwanda',
        'uganda' => ['Uganda', 'UG', 'UGX', 'UGX'], 'kampala' => 'uganda',
        'tanzania' => ['Tanzania', 'TZ', 'TZS', 'TZS'], 'dar es salaam' => 'tanzania',
        'ethiopia' => ['Ethiopia', 'ET', 'ETB', 'ETB'], 'addis ababa' => 'ethiopia',
        'morocco' => ['Morocco', 'MA', 'MAD', 'MAD'], 'casablanca' => 'morocco',
        'senegal' => ['Senegal', 'SN', 'XOF', 'XOF'], 'dakar' => 'senegal',
        'ivory coast' => ['Ivory Coast', 'CI', 'XOF', 'XOF'],
        'cote d' => 'ivory coast', 'abidjan' => 'ivory coast',
        'cameroon' => ['Cameroon', 'CM', 'XAF', 'XAF'],
        'zambia' => ['Zambia', 'ZM', 'ZMW', 'ZMW'],
        'zimbabwe' => ['Zimbabwe', 'ZW', 'USD', '$'],
        'botswana' => ['Botswana', 'BW', 'BWP', 'BWP'],
        'namibia' => ['Namibia', 'NA', 'NAD', 'NAD'],
        'tunisia' => ['Tunisia', 'TN', 'TND', 'TND'],
        'algeria' => ['Algeria', 'DZ', 'DZD', 'DZD'],

        // Everywhere the existing feeds actually supply from, in volume order.
        'united states' => ['United States', 'US', 'USD', '$'],
        'usa' => 'united states', 'u.s.' => 'united states',
        'new york' => 'united states', 'san francisco' => 'united states',
        'los angeles' => 'united states', 'chicago' => 'united states',
        'boston' => 'united states', 'seattle' => 'united states',
        'austin' => 'united states', 'atlanta' => 'united states',
        'denver' => 'united states', 'washington, dc' => 'united states',

        'germany' => ['Germany', 'DE', 'EUR', 'EUR'],
        'deutschland' => 'germany', 'berlin' => 'germany', 'munich' => 'germany',
        'munchen' => 'germany', 'hamburg' => 'germany', 'cologne' => 'germany',
        'koln' => 'germany', 'frankfurt' => 'germany', 'stuttgart' => 'germany',
        'dusseldorf' => 'germany', 'leipzig' => 'germany', 'nuremberg' => 'germany',
        'karlsruhe' => 'germany', 'bremen' => 'germany', 'dresden' => 'germany',
        'mannheim' => 'germany', 'bonn' => 'germany', 'munster' => 'germany',
        'aachen' => 'germany', 'wurzburg' => 'germany', 'bochum' => 'germany',
        'heidelberg' => 'germany', 'braunschweig' => 'germany', 'essen' => 'germany',
        'dortmund' => 'germany', 'hannover' => 'germany', 'mainz' => 'germany',

        'united kingdom' => ['United Kingdom', 'GB', 'GBP', 'GBP'],
        'uk' => 'united kingdom', 'u.k.' => 'united kingdom',
        'england' => 'united kingdom', 'scotland' => 'united kingdom',
        'wales' => 'united kingdom', 'london' => 'united kingdom',
        'manchester' => 'united kingdom', 'birmingham' => 'united kingdom',
        'edinburgh' => 'united kingdom', 'bristol' => 'united kingdom',

        'canada' => ['Canada', 'CA', 'CAD', 'CAD'],
        'toronto' => 'canada', 'vancouver' => 'canada', 'montreal' => 'canada',

        'philippines' => ['Philippines', 'PH', 'PHP', 'PHP'], 'manila' => 'philippines',
        'india' => ['India', 'IN', 'INR', 'INR'],
        'bangalore' => 'india', 'bengaluru' => 'india', 'mumbai' => 'india',
        'new delhi' => 'india', 'hyderabad' => 'india', 'pune' => 'india',
        'brazil' => ['Brazil', 'BR', 'BRL', 'BRL'],
        'sao paulo' => 'brazil', 'rio de janeiro' => 'brazil',
        'poland' => ['Poland', 'PL', 'PLN', 'PLN'],
        'warsaw' => 'poland', 'krakow' => 'poland', 'wroclaw' => 'poland',
        'mexico' => ['Mexico', 'MX', 'MXN', 'MXN'],
        'spain' => ['Spain', 'ES', 'EUR', 'EUR'], 'madrid' => 'spain', 'barcelona' => 'spain',
        'australia' => ['Australia', 'AU', 'AUD', 'AUD'],
        'sydney' => 'australia', 'melbourne' => 'australia',
        'france' => ['France', 'FR', 'EUR', 'EUR'], 'paris' => 'france',
        'portugal' => ['Portugal', 'PT', 'EUR', 'EUR'], 'lisbon' => 'portugal',
        'colombia' => ['Colombia', 'CO', 'COP', 'COP'], 'bogota' => 'colombia',
        'ukraine' => ['Ukraine', 'UA', 'UAH', 'UAH'], 'kyiv' => 'ukraine',
        'netherlands' => ['Netherlands', 'NL', 'EUR', 'EUR'], 'amsterdam' => 'netherlands',
        'argentina' => ['Argentina', 'AR', 'ARS', 'ARS'],
        'ireland' => ['Ireland', 'IE', 'EUR', 'EUR'], 'dublin' => 'ireland',
        'singapore' => ['Singapore', 'SG', 'SGD', 'SGD'],
        'japan' => ['Japan', 'JP', 'JPY', 'JPY'], 'tokyo' => 'japan',
        'united arab emirates' => ['United Arab Emirates', 'AE', 'AED', 'AED'],
        'dubai' => 'united arab emirates',
        'romania' => ['Romania', 'RO', 'RON', 'RON'], 'bucharest' => 'romania',
        'italy' => ['Italy', 'IT', 'EUR', 'EUR'], 'milan' => 'italy',
        'sweden' => ['Sweden', 'SE', 'SEK', 'SEK'], 'stockholm' => 'sweden',
        'switzerland' => ['Switzerland', 'CH', 'CHF', 'CHF'], 'zurich' => 'switzerland',
        'austria' => ['Austria', 'AT', 'EUR', 'EUR'], 'vienna' => 'austria',
        'belgium' => ['Belgium', 'BE', 'EUR', 'EUR'], 'brussels' => 'belgium',
        'turkey' => ['Turkey', 'TR', 'TRY', 'TRY'], 'istanbul' => 'turkey',
        'pakistan' => ['Pakistan', 'PK', 'PKR', 'PKR'],
        'indonesia' => ['Indonesia', 'ID', 'IDR', 'IDR'], 'jakarta' => 'indonesia',
        'vietnam' => ['Vietnam', 'VN', 'VND', 'VND'],
        'new zealand' => ['New Zealand', 'NZ', 'NZD', 'NZD'],
        'sheffield' => 'united kingdom', 'leeds' => 'united kingdom',
        'newcastle' => 'united kingdom', 'glasgow' => 'united kingdom',
        'liverpool' => 'united kingdom', 'cardiff' => 'united kingdom',
        'china' => ['China', 'CN', 'CNY', 'CNY'], 'shanghai' => 'china', 'beijing' => 'china',
        'serbia' => ['Serbia', 'RS', 'RSD', 'RSD'], 'belgrade' => 'serbia',
        'bulgaria' => ['Bulgaria', 'BG', 'BGN', 'BGN'], 'sofia' => 'bulgaria',
        'costa rica' => ['Costa Rica', 'CR', 'CRC', 'CRC'],
        'south korea' => ['South Korea', 'KR', 'KRW', 'KRW'], 'seoul' => 'south korea',
        'czechia' => ['Czechia', 'CZ', 'CZK', 'CZK'],
        'czech republic' => 'czechia', 'prague' => 'czechia',
        'hungary' => ['Hungary', 'HU', 'HUF', 'HUF'], 'budapest' => 'hungary',
        'greece' => ['Greece', 'GR', 'EUR', 'EUR'], 'athens' => 'greece',
        'denmark' => ['Denmark', 'DK', 'DKK', 'DKK'], 'copenhagen' => 'denmark',
        'norway' => ['Norway', 'NO', 'NOK', 'NOK'], 'oslo' => 'norway',
        'finland' => ['Finland', 'FI', 'EUR', 'EUR'], 'helsinki' => 'finland',
        'chile' => ['Chile', 'CL', 'CLP', 'CLP'],
        'peru' => ['Peru', 'PE', 'PEN', 'PEN'],
        'israel' => ['Israel', 'IL', 'ILS', 'ILS'], 'tel aviv' => 'israel',
        'malaysia' => ['Malaysia', 'MY', 'MYR', 'MYR'], 'kuala lumpur' => 'malaysia',
        'thailand' => ['Thailand', 'TH', 'THB', 'THB'], 'bangkok' => 'thailand',
    ];

    // Resolve the city aliases to the country entry they point at.
    foreach ($countries as $needle => $value) {
        if (is_string($value)) {
            $countries[$needle] = $countries[$value];
        }
    }
    return $countries;
}

/**
 * Which single country a stated location names, or '' when there is no answer.
 *
 * Returning nothing is a real answer here and the common one. "Remote",
 * "Worldwide", "EMEA" and "LATAM" are not countries, and a job advertised
 * across three of them is not in any one of them. Filing all of that under a
 * default was how 31,698 listings came to claim they were in the United States
 * and how a Lagos job seeker was shown Johannesburg roles as local.
 *
 * The old version also tested str_contains($location, 'africa') and filed the
 * result as Nigeria, before the South Africa branch four lines below it ever
 * ran. "South Africa" contains "africa". That single line accounted for 296 of
 * the 364 jobs the site believed were Nigerian.
 *
 * @return array{0:string,1:string,2:string,3:string}|null [name, iso, currency, symbol]
 */
function jm_scraper_country_of(string $location): ?array {
    // Accents are stripped so Munchen and Munich, Koln and Cologne, Dusseldorf
    // and Duesseldorf all land on Germany without nine spellings each.
    $needle = strtolower(trim($location));
    $needle = strtr($needle, [
        'ü' => 'u', 'ö' => 'o', 'ä' => 'a', 'ß' => 'ss', 'é' => 'e', 'è' => 'e',
        'ç' => 'c', 'á' => 'a', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n',
        'â' => 'a', 'ô' => 'o', 'ê' => 'e', 'å' => 'a', 'ø' => 'o', 'ã' => 'a',
    ]);
    if ($needle === '') {
        return null;
    }

    $found = [];
    foreach (jm_scraper_gazetteer() as $token => $country) {
        // Word boundaries, or "india" matches Indiana and "kano" matches Kanoya.
        if (preg_match('/(?<![a-z])' . preg_quote($token, '/') . '(?![a-z])/', $needle)) {
            $found[$country[1]] = $country;
        }
    }

    // Exactly one country, or none. "Australia, Canada, France" names three and
    // belongs to no single one of them, so it gets no country rather than the
    // first one the loop happened to see.
    return count($found) === 1 ? reset($found) : null;
}

/** The country row id for a location, or null when the location names none. */
