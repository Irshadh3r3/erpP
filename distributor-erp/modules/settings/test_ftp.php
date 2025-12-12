<?php
/**
 * Simple FTP Test Script
 * Usage: php test_ftp.php
 */

echo "=== FTP Connection Test ===\n\n";

// Test FTP connection
$server = 'ftpupload.net';  // Change this to your FTP server
$username = 'if0_40574492'; // Change this
$password = 'CkGFpSCuYcaY'; // Change this
$directory = '/backups/';

echo "Testing connection to: $server\n";
echo "Username: $username\n";
echo "Directory: $directory\n\n";

// Connect
$conn_id = @ftp_connect($server, 21, 10);
if (!$conn_id) {
    die("❌ ERROR: Cannot connect to FTP server: $server\n");
}
echo "✓ Connected to server\n";

// Login
$login_result = @ftp_login($conn_id, $username, $password);
if (!$login_result) {
    ftp_close($conn_id);
    die("❌ ERROR: Login failed. Check username/password\n");
}
echo "✓ Login successful\n";

// Enable passive mode
ftp_pasv($conn_id, true);
echo "✓ Passive mode enabled\n";

// Try to change directory
if (!empty($directory) && $directory !== '/') {
    if (@ftp_chdir($conn_id, $directory)) {
        echo "✓ Directory exists: $directory\n";
    } else {
        echo "⚠ Directory not found. Attempting to create...\n";
        if (@ftp_mkdir($conn_id, $directory)) {
            echo "✓ Directory created: $directory\n";
        } else {
            echo "❌ Cannot create directory. Check permissions\n";
        }
    }
}

// Get current directory
$pwd = ftp_pwd($conn_id);
echo "✓ Current directory: $pwd\n";

// Test upload with small file
$test_content = "FTP Test - " . date('Y-m-d H:i:s');
$test_filename = 'ftp_test_' . time() . '.txt';
$temp_file = tempnam(sys_get_temp_dir(), 'ftp_test_');
file_put_contents($temp_file, $test_content);

echo "\nTesting file upload...\n";
if (@ftp_put($conn_id, $test_filename, $temp_file, FTP_ASCII)) {
    echo "✓ File uploaded successfully: $test_filename\n";
    
    // Try to delete it
    if (@ftp_delete($conn_id, $test_filename)) {
        echo "✓ Test file deleted\n";
    }
} else {
    echo "❌ File upload failed. Check write permissions\n";
}

unlink($temp_file);

// List files
echo "\nFiles in current directory:\n";
$files = @ftp_nlist($conn_id, ".");
if ($files) {
    foreach ($files as $file) {
        echo "  - $file\n";
    }
}

ftp_close($conn_id);
echo "\n✅ FTP Test Completed Successfully!\n";
echo "\nUse these settings in your ERP:\n";
echo "Server: $server\n";
echo "Username: $username\n";
echo "Password: [your password]\n";
echo "Directory: $pwd\n";
?>