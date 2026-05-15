<?php
/**
 * Debug: Country Switching Test
 */
define('JOBMINGTON', true);
session_start();

echo "<pre style='background:#1e293b;color:#e2e8f0;padding:20px;font-family:monospace;'>";
echo "=== COUNTRY SWITCH DEBUG ===\n\n";

echo "1. GET Parameters:\n";
print_r($_GET);

echo "\n2. Current SESSION geo_data:\n";
print_r($_SESSION['geo_data'] ?? 'NOT SET');

echo "\n3. Testing switch...\n";

if (isset($_GET['switch_country'])) {
    echo "   - switch_country detected: " . $_GET['switch_country'] . "\n";
    echo "   - name: " . ($_GET['name'] ?? 'NOT SET') . "\n";
    
    // Manually update session to test
    $_SESSION['geo_data'] = [
        'code'      => strtolower($_GET['switch_country']),
        'name'      => $_GET['name'] ?? 'Test',
        'city'      => 'Manual Test',
        'region'    => 'Test Region',
        'lat'       => 0.0,
        'lon'       => 0.0,
        'timezone'  => 'UTC',
        'isp'       => 'Debug Mode',
        'currency'  => 'USD',
        'symbol'    => '$',
        'db_id'     => null
    ];
    
    echo "\n4. SESSION UPDATED TO:\n";
    print_r($_SESSION['geo_data']);
    
    echo "\n<a href='debug_country.php' style='color:#60a5fa;'>Click here to verify session persists</a>";
} else {
    echo "   - No switch_country in URL\n";
    echo "\n<a href='debug_country.php?switch_country=ke&name=Kenya' style='color:#60a5fa;'>Click here to test switching to Kenya</a>";
}

echo "\n</pre>";
?>
