<?php
// SESSION TEST SCRIPT
session_start();

if (isset($_GET['set'])) {
    $_SESSION['test_country'] = $_GET['set'];
    echo "Session value set to: " . $_SESSION['test_country'] . "<br>";
    echo "<a href='session_test.php'>Reload to check persistence</a>";
    exit;
}

if (isset($_GET['clear'])) {
    session_destroy();
    echo "Session cleared. Geo data will be re-detected on next page load.<br>";
    echo "<a href='session_test.php'>Reload</a>";
    exit;
}

if (isset($_GET['reset_geo'])) {
    unset($_SESSION['geo_data']);
    echo "Geo data cleared. Will be re-detected on next page load.<br>";
    echo "<a href='session_test.php'>Reload</a>";
    exit;
}

echo "<h2>Session Test</h2>";
echo "Test value: " . ($_SESSION['test_country'] ?? 'NOT SET') . "<br>";
echo "<h3>Geo Data:</h3>";
echo "<pre>" . print_r($_SESSION['geo_data'] ?? 'NOT SET', true) . "</pre>";
echo "<a href='session_test.php?set=Kenya'>Set to Kenya</a> | ";
echo "<a href='session_test.php?set=Nigeria'>Set to Nigeria</a> | ";
echo "<a href='session_test.php?reset_geo=1'>Reset Geo</a> | ";
echo "<a href='session_test.php?clear=1'>Clear All Session</a>";
?>