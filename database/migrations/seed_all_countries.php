<?php
/**
 * Migration: Seed comprehensive list of countries
 * Run once to populate countries table
 */

define('JOBMINGTON', true);
require_once dirname(__DIR__, 2) . '/config/env.php';
require_once dirname(__DIR__, 2) . '/config/database.php';

$pdo = db();

echo "<!DOCTYPE html><html><head><title>Country Seeder</title></head><body>";
echo "<div style='font-family: system-ui; max-width: 900px; margin: 40px auto; padding: 30px; background: linear-gradient(135deg, #0f172a, #1e293b); color: #e2e8f0; border-radius: 16px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5);'>";
echo "<h2 style='margin:0 0 20px 0;'> Comprehensive Country Seeder</h2><hr style='border-color: #334155; margin-bottom: 20px;'>";

// Ensure columns exist
try {
    $pdo->exec("ALTER TABLE countries ADD COLUMN IF NOT EXISTS region VARCHAR(50) DEFAULT 'Global'");
    $pdo->exec("ALTER TABLE countries ADD COLUMN IF NOT EXISTS currency_code VARCHAR(10) DEFAULT 'USD'");
    $pdo->exec("ALTER TABLE countries ADD COLUMN IF NOT EXISTS is_active TINYINT(1) DEFAULT 1");
    echo " Table structure verified<br>";
} catch (Exception $e) {
    echo " Column check: " . $e->getMessage() . "<br>";
}

// Comprehensive country list with regions and currencies
$countries = [
    // ===== AFRICA =====
    // West Africa
    ['Nigeria', 'ng', 'West Africa', 'NGN', '₦'],
    ['Ghana', 'gh', 'West Africa', 'GHS', '₵'],
    ['Senegal', 'sn', 'West Africa', 'XOF', 'CFA'],
    ['Ivory Coast', 'ci', 'West Africa', 'XOF', 'CFA'],
    ['Cameroon', 'cm', 'Central Africa', 'XAF', 'FCFA'],
    ['Mali', 'ml', 'West Africa', 'XOF', 'CFA'],
    ['Burkina Faso', 'bf', 'West Africa', 'XOF', 'CFA'],
    ['Niger', 'ne', 'West Africa', 'XOF', 'CFA'],
    ['Guinea', 'gn', 'West Africa', 'GNF', 'FG'],
    ['Benin', 'bj', 'West Africa', 'XOF', 'CFA'],
    ['Togo', 'tg', 'West Africa', 'XOF', 'CFA'],
    ['Sierra Leone', 'sl', 'West Africa', 'SLL', 'Le'],
    ['Liberia', 'lr', 'West Africa', 'LRD', 'L$'],
    ['Gambia', 'gm', 'West Africa', 'GMD', 'D'],
    ['Guinea-Bissau', 'gw', 'West Africa', 'XOF', 'CFA'],
    ['Cape Verde', 'cv', 'West Africa', 'CVE', '$'],
    ['Mauritania', 'mr', 'West Africa', 'MRU', 'UM'],
    
    // East Africa
    ['Kenya', 'ke', 'East Africa', 'KES', 'KSh'],
    ['Tanzania', 'tz', 'East Africa', 'TZS', 'TSh'],
    ['Uganda', 'ug', 'East Africa', 'UGX', 'USh'],
    ['Rwanda', 'rw', 'East Africa', 'RWF', 'FRw'],
    ['Ethiopia', 'et', 'East Africa', 'ETB', 'Br'],
    ['Somalia', 'so', 'East Africa', 'SOS', 'Sh'],
    ['Eritrea', 'er', 'East Africa', 'ERN', 'Nfk'],
    ['Djibouti', 'dj', 'East Africa', 'DJF', 'Fdj'],
    ['South Sudan', 'ss', 'East Africa', 'SSP', '£'],
    ['Sudan', 'sd', 'East Africa', 'SDG', '£'],
    ['Burundi', 'bi', 'East Africa', 'BIF', 'FBu'],
    
    // Southern Africa
    ['South Africa', 'za', 'Southern Africa', 'ZAR', 'R'],
    ['Zimbabwe', 'zw', 'Southern Africa', 'ZWL', 'Z$'],
    ['Botswana', 'bw', 'Southern Africa', 'BWP', 'P'],
    ['Zambia', 'zm', 'Southern Africa', 'ZMW', 'ZK'],
    ['Namibia', 'na', 'Southern Africa', 'NAD', 'N$'],
    ['Mozambique', 'mz', 'Southern Africa', 'MZN', 'MT'],
    ['Malawi', 'mw', 'Southern Africa', 'MWK', 'MK'],
    ['Angola', 'ao', 'Southern Africa', 'AOA', 'Kz'],
    ['Lesotho', 'ls', 'Southern Africa', 'LSL', 'L'],
    ['Eswatini', 'sz', 'Southern Africa', 'SZL', 'E'],
    ['Madagascar', 'mg', 'Southern Africa', 'MGA', 'Ar'],
    ['Mauritius', 'mu', 'Southern Africa', 'MUR', '₨'],
    ['Seychelles', 'sc', 'Southern Africa', 'SCR', '₨'],
    ['Comoros', 'km', 'Southern Africa', 'KMF', 'CF'],
    
    // North Africa
    ['Egypt', 'eg', 'North Africa', 'EGP', 'E£'],
    ['Morocco', 'ma', 'North Africa', 'MAD', 'د.م.'],
    ['Algeria', 'dz', 'North Africa', 'DZD', 'د.ج'],
    ['Tunisia', 'tn', 'North Africa', 'TND', 'د.ت'],
    ['Libya', 'ly', 'North Africa', 'LYD', 'ل.د'],
    
    // Central Africa
    ['Democratic Republic of Congo', 'cd', 'Central Africa', 'CDF', 'FC'],
    ['Republic of Congo', 'cg', 'Central Africa', 'XAF', 'FCFA'],
    ['Central African Republic', 'cf', 'Central Africa', 'XAF', 'FCFA'],
    ['Gabon', 'ga', 'Central Africa', 'XAF', 'FCFA'],
    ['Equatorial Guinea', 'gq', 'Central Africa', 'XAF', 'FCFA'],
    ['Chad', 'td', 'Central Africa', 'XAF', 'FCFA'],
    ['São Tomé and Príncipe', 'st', 'Central Africa', 'STN', 'Db'],
    
    // ===== EUROPE =====
    ['United Kingdom', 'gb', 'Europe', 'GBP', '£'],
    ['Germany', 'de', 'Europe', 'EUR', '€'],
    ['France', 'fr', 'Europe', 'EUR', '€'],
    ['Italy', 'it', 'Europe', 'EUR', '€'],
    ['Spain', 'es', 'Europe', 'EUR', '€'],
    ['Netherlands', 'nl', 'Europe', 'EUR', '€'],
    ['Belgium', 'be', 'Europe', 'EUR', '€'],
    ['Portugal', 'pt', 'Europe', 'EUR', '€'],
    ['Ireland', 'ie', 'Europe', 'EUR', '€'],
    ['Sweden', 'se', 'Europe', 'SEK', 'kr'],
    ['Norway', 'no', 'Europe', 'NOK', 'kr'],
    ['Denmark', 'dk', 'Europe', 'DKK', 'kr'],
    ['Finland', 'fi', 'Europe', 'EUR', '€'],
    ['Switzerland', 'ch', 'Europe', 'CHF', 'CHF'],
    ['Austria', 'at', 'Europe', 'EUR', '€'],
    ['Poland', 'pl', 'Europe', 'PLN', 'zł'],
    ['Greece', 'gr', 'Europe', 'EUR', '€'],
    
    // ===== AMERICAS =====
    ['United States', 'us', 'North America', 'USD', '$'],
    ['Canada', 'ca', 'North America', 'CAD', 'C$'],
    ['Mexico', 'mx', 'North America', 'MXN', '$'],
    ['Brazil', 'br', 'South America', 'BRL', 'R$'],
    ['Argentina', 'ar', 'South America', 'ARS', '$'],
    ['Colombia', 'co', 'South America', 'COP', '$'],
    ['Chile', 'cl', 'South America', 'CLP', '$'],
    ['Peru', 'pe', 'South America', 'PEN', 'S/'],
    ['Venezuela', 've', 'South America', 'VES', 'Bs'],
    ['Ecuador', 'ec', 'South America', 'USD', '$'],
    
    // ===== ASIA =====
    ['India', 'in', 'Asia', 'INR', '₹'],
    ['China', 'cn', 'Asia', 'CNY', '¥'],
    ['Japan', 'jp', 'Asia', 'JPY', '¥'],
    ['South Korea', 'kr', 'Asia', 'KRW', '₩'],
    ['Indonesia', 'id', 'Asia', 'IDR', 'Rp'],
    ['Philippines', 'ph', 'Asia', 'PHP', '₱'],
    ['Vietnam', 'vn', 'Asia', 'VND', '₫'],
    ['Thailand', 'th', 'Asia', 'THB', '฿'],
    ['Malaysia', 'my', 'Asia', 'MYR', 'RM'],
    ['Singapore', 'sg', 'Asia', 'SGD', 'S$'],
    ['Pakistan', 'pk', 'Asia', 'PKR', '₨'],
    ['Bangladesh', 'bd', 'Asia', 'BDT', '৳'],
    ['Sri Lanka', 'lk', 'Asia', 'LKR', '₨'],
    ['Nepal', 'np', 'Asia', 'NPR', '₨'],
    
    // ===== MIDDLE EAST =====
    ['United Arab Emirates', 'ae', 'Middle East', 'AED', 'د.إ'],
    ['Saudi Arabia', 'sa', 'Middle East', 'SAR', '﷼'],
    ['Qatar', 'qa', 'Middle East', 'QAR', '﷼'],
    ['Kuwait', 'kw', 'Middle East', 'KWD', 'د.ك'],
    ['Bahrain', 'bh', 'Middle East', 'BHD', '.د.ب'],
    ['Oman', 'om', 'Middle East', 'OMR', '﷼'],
    ['Israel', 'il', 'Middle East', 'ILS', '₪'],
    ['Jordan', 'jo', 'Middle East', 'JOD', 'د.ا'],
    ['Lebanon', 'lb', 'Middle East', 'LBP', 'ل.ل'],
    ['Iraq', 'iq', 'Middle East', 'IQD', 'ع.د'],
    ['Turkey', 'tr', 'Middle East', 'TRY', '₺'],
    
    // ===== OCEANIA =====
    ['Australia', 'au', 'Oceania', 'AUD', 'A$'],
    ['New Zealand', 'nz', 'Oceania', 'NZD', 'NZ$'],
];

// Insert or update countries
$insertStmt = $pdo->prepare("
    INSERT IGNORE INTO countries (name, iso_code, region, currency_code, currency_symbol, is_active) 
    VALUES (?, ?, ?, ?, ?, 1)
");

$count = 0;
$errors = [];
foreach ($countries as $c) {
    try {
        $insertStmt->execute($c);
        if ($insertStmt->rowCount() > 0) $count++;
    } catch (Exception $e) {
        $errors[] = "{$c[0]}: {$e->getMessage()}";
    }
}

echo " Processed {$count} countries<br>";
if (!empty($errors)) {
    echo "<details><summary> " . count($errors) . " errors (click to expand)</summary><pre>" . implode("\n", $errors) . "</pre></details>";
}

// Verify
$stmt = $pdo->query("SELECT COUNT(*) as total FROM countries WHERE is_active = 1");
$result = $stmt->fetch();
echo "<hr style='border-color: #334155; margin: 20px 0;'>";
echo "<h3 style='color: #22c55e; margin: 0;'> Total: {$result['total']} active countries</h3>";

// Show by region
echo "<h4 style='margin: 20px 0 10px 0;'>Countries by Region:</h4>";
$stmt = $pdo->query("SELECT region, GROUP_CONCAT(name ORDER BY name SEPARATOR ', ') as countries, COUNT(*) as cnt FROM countries WHERE is_active = 1 GROUP BY region ORDER BY region");
$regions = $stmt->fetchAll();
foreach ($regions as $r) {
    echo "<div style='margin-bottom: 12px; padding: 12px; background: rgba(255,255,255,0.05); border-radius: 8px;'>";
    echo "<strong style='color: #60a5fa;'>{$r['region']}</strong> <span style='color: #94a3b8;'>({$r['cnt']})</span><br>";
    echo "<span style='font-size: 0.85em; color: #94a3b8;'>{$r['countries']}</span>";
    echo "</div>";
}

echo "<p style='margin-top: 20px; padding: 12px; background: rgba(34, 197, 94, 0.1); border-radius: 8px; border-left: 3px solid #22c55e;'>";
echo " Nigeria is now active: ";
$ng = $pdo->query("SELECT * FROM countries WHERE iso_code = 'ng'")->fetch();
echo $ng ? "<strong>{$ng['name']}</strong> ({$ng['currency_symbol']} {$ng['currency_code']})" : "NOT FOUND!";
echo "</p>";

echo "</div></body></html>";
