<?php
/**
 * JOBMINGTON - Location Detection
 * Version 2.0: The Global Eye
 * Features: IP Triangulation, Lat/Lon Locking, Timezone Sync, Fail-Safe API
 */

declare(strict_types=1);

// Prevent direct access
if (!defined('JOBMINGTON')) {
    die('Direct access not permitted');
}

class LocationDetector {
    
    // Primary Intel Source
    private static $apiUrl = 'http://ip-api.com/json/';
    
    /**
     * Detect User Location
     * Detects location or respects manual override
     */
    public static function detect(): void {
        // 1. Manual Override (The Teleporter)
        if (isset($_GET['switch_country']) && isset($_GET['name'])) {
            self::teleport(
                strtolower(Security::clean($_GET['switch_country'])),
                Security::clean($_GET['name'])
            );
            // Teleport always overrides and exits
        }

        // 2. If no location is set, auto-detect
        if (!isset($_SESSION['geo_data'])) {
            // 3. Scan Network (IP Detection)
            $data = self::scanNetwork();
            // 4. Calibrate (Set Session)
            if ($data) {
                self::saveLocationData($data);
            } else {
                // Signal lost, loading default protocols
                self::loadDefaultProtocol();
            }
        }
    }
    
    /**
     * Manual Country Switch (Teleport Mode)
     */
    private static function teleport(string $code, string $name): void {
        // We only have code/name from GET, so we fetch the rest from DB
        $pdo = db();
        $code = strtolower($code); // Normalize to lowercase
        $stmt = $pdo->prepare("SELECT * FROM countries WHERE LOWER(iso_code) = ? LIMIT 1");
        $stmt->execute([$code]);
        $country = $stmt->fetch();

        // Construct a synthetic data packet
        // We use defaults for city/lat/lon since we are spoofing the location
        $data = [
            'countryCode' => $code,
            'country'     => $name,
            'city'        => 'Unknown Location', 
            'regionName'  => $country['region'] ?? 'Global',
            'lat'         => 0.0,
            'lon'         => 0.0,
            'timezone'    => 'UTC',
            'isp'         => 'Manual Override',
            'currency_code'   => $country['currency_code'] ?? 'USD',
            'currency_symbol' => $country['currency_symbol'] ?? '$',
            'db_id'       => $country['country_id'] ?? null
        ];

        // Save to session
        $_SESSION['geo_data'] = self::formatSessionData($data);

        // Redirect to cleanse URL of query params
        $url = strtok($_SERVER['REQUEST_URI'], '?');
        header("Location: {$url}");
        exit;
    }
    
    /**
     * Execute Network Scan
     * Returns raw API data or null
     */
    private static function scanNetwork(): ?array {
        $ip = Security::getClientIP();
        
        // Localhost Bypass (Dev Mode)
        if (in_array($ip, ['127.0.0.1', '::1', '0.0.0.0'])) {
            return null; // Triggers default protocol
        }
        
        try {
            // IMPORTANT: Set timeout to 2s to prevent site hanging if API is down
            $ctx = stream_context_create(['http' => ['timeout' => 2]]);
            $json = @file_get_contents(self::$apiUrl . $ip, false, $ctx);
            
            if ($json === false) return null;
            
            $data = json_decode($json, true);
            
            if ($data && $data['status'] === 'success') {
                return $data;
            }
        } catch (Exception $e) {
            error_log("Geo-Locator Malfunction: " . $e->getMessage());
        }
        
        return null;
    }
    
    /**
     * Cache Location Data
     */
    private static function saveLocationData(array $apiData): void {
        $pdo = db();
        $code = strtolower($apiData['countryCode']);
        
        // Cross-reference with our Database for financial data
        $stmt = $pdo->prepare("SELECT * FROM countries WHERE iso_code = ? LIMIT 1");
        $stmt->execute([$code]);
        $dbCountry = $stmt->fetch();
        
        // Merge API data with DB data
        $sessionPayload = [
            'countryCode' => $code,
            'country'     => $apiData['country'],
            'city'        => $apiData['city'] ?? 'Unknown',
            'regionName'  => $apiData['regionName'] ?? ($dbCountry['region'] ?? 'Global'),
            'lat'         => $apiData['lat'] ?? 0.0,
            'lon'         => $apiData['lon'] ?? 0.0,
            'timezone'    => $apiData['timezone'] ?? 'UTC',
            'isp'         => $apiData['isp'] ?? 'Unknown',
            'currency_code'   => $dbCountry['currency_code'] ?? 'USD',
            'currency_symbol' => $dbCountry['currency_symbol'] ?? '$',
            'db_id'       => $dbCountry['country_id'] ?? null
        ];

        $_SESSION['geo_data'] = self::formatSessionData($sessionPayload);
    }
    
    /**
     * Load Default Protocol (Fallback)
     */
    private static function loadDefaultProtocol(): void {
        $_SESSION['geo_data'] = [
            'code'      => DEFAULT_COUNTRY_CODE,
            'name'      => DEFAULT_COUNTRY_NAME,
            'city'      => '',
            'region'    => '',
            'lat'       => 0.0,
            'lon'       => 0.0,
            'timezone'  => 'Africa/Lagos',
            'isp'       => 'System Default',
            'currency'  => 'NGN',
            'symbol'    => '₦',
            'db_id'     => null
        ];
    }

    /**
     * Formatter to keep array keys consistent
     */
    private static function formatSessionData(array $data): array {
        return [
            'code'      => $data['countryCode'],
            'name'      => $data['country'],
            'city'      => $data['city'],
            'region'    => $data['regionName'],
            'lat'       => $data['lat'],
            'lon'       => $data['lon'],
            'timezone'  => $data['timezone'],
            'isp'       => $data['isp'],
            'currency'  => $data['currency_code'],
            'symbol'    => $data['currency_symbol'],
            'db_id'     => $data['db_id']
        ];
    }
    
    // --- PUBLIC INTERFACE (Getters) ---
    
    public static function getCode(): string { return $_SESSION['geo_data']['code'] ?? DEFAULT_COUNTRY_CODE; }
    public static function getName(): string { return $_SESSION['geo_data']['name'] ?? DEFAULT_COUNTRY_NAME; }
    public static function getCity(): string { return $_SESSION['geo_data']['city'] ?? ''; }
    public static function getRegion(): string { return $_SESSION['geo_data']['region'] ?? ''; }
    public static function getCurrencyCode(): string { return $_SESSION['geo_data']['currency'] ?? 'USD'; }
    public static function getCurrencySymbol(): string { return $_SESSION['geo_data']['symbol'] ?? '$'; }
    public static function getCountryId(): ?int { return $_SESSION['geo_data']['db_id'] ?? null; }
    
    public static function getFlagUrl(int $width = 40): string { 
        return "https://flagcdn.com/w{$width}/" . self::getCode() . ".png"; 
    }
    
    /**
     * Get Database of Active Nations
     * Filtered to Africa only, organized by Region for the Switcher UI
     * Priority: West Africa (Nigeria) first
     */
    public static function getGlobalNetwork(): array {
        $pdo = db();
        // Only fetch African countries
        $stmt = $pdo->query("
            SELECT * FROM countries 
            WHERE is_active = 1 
            AND region IN ('West Africa', 'East Africa', 'North Africa', 'Southern Africa', 'Central Africa')
            ORDER BY 
                CASE 
                    WHEN region = 'West Africa' THEN 1
                    WHEN region = 'East Africa' THEN 2
                    WHEN region = 'North Africa' THEN 3
                    WHEN region = 'Central Africa' THEN 4
                    WHEN region = 'Southern Africa' THEN 5
                    ELSE 6
                END,
                CASE WHEN iso_code = 'ng' THEN 0 ELSE 1 END,
                name
        ");
        $countries = $stmt->fetchAll();
        
        $network = [];
        foreach ($countries as $c) {
            $network[$c['region']][] = $c;
        }
        return $network;
    }
}

// --- INITIALIZATION ---
// Start the scan immediately upon inclusion
LocationDetector::detect();

// --- EXPOSE HELPER VARIABLES FOR TEMPLATES ---
// This allows you to simply use $currentCountryName in your HTML
$currentCountryCode = LocationDetector::getCode();
$currentCountryName = LocationDetector::getName();
$currentFlag        = LocationDetector::getFlagUrl();
$currencySymbol     = LocationDetector::getCurrencySymbol();
?>