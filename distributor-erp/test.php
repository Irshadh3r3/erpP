<?php
// test_connection.php
echo "<h2>MySQLi Extension Test</h2>";

// Test 1: Check if MySQLi extension is loaded
if (extension_loaded('mysqli')) {
    echo "✅ <b>MySQLi extension is LOADED</b><br>";
} else {
    echo "❌ <b>MySQLi extension is NOT LOADED</b><br>";
    echo "Please enable 'extension=mysqli' in php.ini<br>";
}

// Test 2: Check if function exists
if (function_exists('mysqli_connect')) {
    echo "✅ mysqli_connect() function exists<br>";
} else {
    echo "❌ mysqli_connect() function NOT found<br>";
}

// Test 3: Try to connect
try {
    $test_conn = @new mysqli('localhost', 'root', '', 'distributor_erp');
    if ($test_conn->connect_error) {
        echo "⚠️ Connection failed: " . $test_conn->connect_error . "<br>";
    } else {
        echo "✅ Successfully connected to database!<br>";
        $test_conn->close();
    }
} catch (Exception $e) {
    echo "❌ Exception: " . $e->getMessage() . "<br>";
}

// Show loaded extensions
echo "<h3>Loaded Extensions:</h3>";
$extensions = get_loaded_extensions();
sort($extensions);
foreach ($extensions as $ext) {
    echo "- $ext<br>";
}
?>