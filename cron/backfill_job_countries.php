<?php
/**
 * JOBMINGTON - re-derive a job's country and work style from what it states.
 *
 * The scraper filed listings under a default of United States and matched
 * "africa" before "south africa", so 25,451 of 31,752 rows with a stated
 * location were tagged to a country that location does not name. Every South
 * African job was filed as Nigerian, which is how a Lagos job seeker came to be
 * shown Johannesburg roles as local.
 *
 * The mapper is fixed. This settles the rows already in the table, using the
 * same includes/job_locations.php the scraper now uses, so a row written today
 * and a row corrected here cannot disagree.
 *
 * A job whose location names no single country gets no country. "Remote",
 * "Worldwide", "EMEA" and "Australia, Canada, France" are all real answers of
 * "not one country", and pretending otherwise is what caused this.
 *
 * It also settles remote against on-site. A local board advertises a remote
 * role with a country in the location field and the word Remote only in the
 * title, so reading the location alone filed "Outreach Agent, Remote" as
 * on-site work in Nigeria, which makes an on-site filter worse than useless.
 *
 *   php cron/backfill_job_countries.php                    # say what would change
 *   php cron/backfill_job_countries.php --apply            # change it
 *   php cron/backfill_job_countries.php --apply --limit=500
 *
 * --create-countries adds a country the listings clearly name but the table
 * does not have. Off by default, because adding a country puts it in the public
 * filter, which is a visible change and should be a decision rather than a side
 * effect of a data fix. The scraper creates countries on this same path, so
 * anything skipped here appears on the next run anyway.
 */

define('JOBMINGTON', true);

require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/job_locations.php';

$options = getopt('', ['apply', 'limit::', 'create-countries']);
$apply   = isset($options['apply']);
$create  = isset($options['create-countries']);
$limit   = isset($options['limit']) ? max(1, (int) $options['limit']) : 0;
$pdo     = db();

function jm_backfill_log(string $line): void
{
    echo $line . PHP_EOL;
}

/* Country ids, resolved once, and added to as --create-countries creates any.
   Without that flag a country the table does not have simply cannot claim its
   rows, and they are reported rather than moved somewhere approximate. */
$byIso = [];
$byName = [];
foreach ($pdo->query('SELECT country_id, name, iso_code FROM countries') as $row) {
    $byIso[strtoupper((string) $row['iso_code'])] = (int) $row['country_id'];
    $byName[strtolower((string) $row['name'])] = (int) $row['country_id'];
}

jm_backfill_log('Re-deriving job countries' . ($apply ? '' : ' (dry run, nothing will change)'));
jm_backfill_log(sprintf('  %d countries in the table', count($byIso)));

$sql = 'SELECT job_id, country_id, original_location, city, title, job_type FROM jobs ORDER BY job_id';
if ($limit > 0) {
    $sql .= ' LIMIT ' . $limit;
}

$update = $pdo->prepare('UPDATE jobs SET country_id = ? WHERE job_id = ?');

$seen = 0;
$changed = 0;
$cleared = 0;
$retyped = 0;
$unchanged = 0;
$missingCountry = [];
$created = [];
$moves = [];

foreach ($pdo->query($sql, PDO::FETCH_ASSOC) as $job) {
    $seen++;

    // The stated location first, the city only as a fallback: city is often
    // just the town with no country, and the scraper wrote both from the same
    // source string anyway.
    $stated = trim((string) ($job['original_location'] ?? ''));
    if ($stated === '') {
        $stated = trim((string) ($job['city'] ?? ''));
    }

    /*
     * Remote or on-site, re-read from the title as well as the location.
     *
     * Above the country work on purpose: a row whose country is not in the
     * table skips the rest of this loop, and its work style still needs
     * settling. The two questions are independent.
     *
     * Only the one-way correction is made. The 32,000 rows from the remote
     * boards were written when job_type was the literal string 'Remote' for
     * everything, and they are genuinely remote, so rewriting them would be
     * churn. What this catches is the opposite mistake: a remote role filed as
     * on-site because only its title said so, which is what a local board does
     * when it puts the country in the location field.
     */
    if (strtolower((string) $job['job_type']) !== 'remote'
        && jm_scraper_is_remote_job($stated, (string) ($job['title'] ?? ''))) {
        $retyped++;
        if ($apply) {
            $pdo->prepare('UPDATE jobs SET job_type = ? WHERE job_id = ?')
                ->execute(['remote', (int) $job['job_id']]);
        }
    }

    $country = jm_scraper_country_of($stated);
    $wanted = null;

    if ($country !== null) {
        $iso = strtoupper($country[1]);
        $name = strtolower($country[0]);
        $wanted = $byIso[$iso] ?? $byName[$name] ?? null;

        if ($wanted === null) {
            // Recognised the place, but this database has no row for it.
            $missingCountry[$country[0]] = ($missingCountry[$country[0]] ?? 0) + 1;

            if (!$create || !$apply) {
                $unchanged++;
                continue;
            }

            $insert = $pdo->prepare('INSERT INTO countries (name, iso_code, currency_code, currency_symbol, is_active)
                                     VALUES (?, ?, ?, ?, 1)');
            $insert->execute([$country[0], $country[1], $country[2], $country[3]]);
            $wanted = (int) $pdo->lastInsertId();

            // Remember it, or the next row for the same country inserts again.
            $byIso[$iso] = $wanted;
            $byName[$name] = $wanted;
            $created[$country[0]] = $wanted;
        }
    }

    $current = $job['country_id'] === null ? null : (int) $job['country_id'];

    if ($current === $wanted) {
        $unchanged++;
        continue;
    }

    if ($wanted === null) {
        $cleared++;
    } else {
        $changed++;
    }

    // Kept for the report so a dry run shows where the rows are actually going.
    $from = $current === null ? '(none)' : ('#' . $current);
    $to   = $wanted === null ? '(none)' : ('#' . $wanted);
    $key  = $from . ' -> ' . $to;
    $moves[$key] = ($moves[$key] ?? 0) + 1;

    if ($apply) {
        $update->execute([$wanted, (int) $job['job_id']]);
    }
}

$names = [];
foreach ($pdo->query('SELECT country_id, name FROM countries') as $row) {
    $names['#' . $row['country_id']] = (string) $row['name'];
}
$label = static fn(string $token): string => $token === '(none)' ? 'no country' : ($names[$token] ?? $token);

jm_backfill_log('');
jm_backfill_log(sprintf('  %-28s %s', 'jobs examined', number_format($seen)));
jm_backfill_log(sprintf('  %-28s %s', 'already correct', number_format($unchanged)));
jm_backfill_log(sprintf('  %-28s %s', 'moved to a real country', number_format($changed)));
jm_backfill_log(sprintf('  %-28s %s', 'country removed', number_format($cleared)));
jm_backfill_log(sprintf('  %-28s %s', 're-marked as remote', number_format($retyped)));

if ($moves) {
    arsort($moves);
    jm_backfill_log('');
    jm_backfill_log('  where they move:');
    $shown = 0;
    foreach ($moves as $move => $count) {
        [$from, $to] = explode(' -> ', $move);
        jm_backfill_log(sprintf('    %-22s -> %-22s %s', $label($from), $label($to), number_format($count)));
        if (++$shown >= 20) {
            jm_backfill_log(sprintf('    ... and %d more pairs', count($moves) - $shown));
            break;
        }
    }
}

if ($created) {
    jm_backfill_log('');
    jm_backfill_log('  countries added to the table:');
    foreach ($created as $name => $id) {
        jm_backfill_log(sprintf('    %-22s #%d', $name, $id));
    }
}

if ($missingCountry && !$created) {
    jm_backfill_log('');
    jm_backfill_log('  recognised but not in the countries table, so left alone:');
    foreach ($missingCountry as $name => $count) {
        jm_backfill_log(sprintf('    %-22s %s job(s)', $name, number_format($count)));
    }
    jm_backfill_log('    Re-run with --create-countries to add them and place these jobs.');
}

jm_backfill_log('');
jm_backfill_log($apply ? 'Applied.' : 'Nothing was changed. Re-run with --apply to write it.');
exit(0);
