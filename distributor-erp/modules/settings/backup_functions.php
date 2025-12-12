<?php
/**
 * Functions for scheduled backups
 */

function runScheduledBackup() {
    global $conn;
    
    // Get settings
    $backup_method = getSetting($conn, "backup_method", "local");
    
    // Create backup
    $result = createDatabaseBackup($backup_method);
    
    if ($result['success']) {
        setSetting($conn, "last_backup_status", "success");
        setSetting($conn, "last_backup_message", 'Backup completed: ' . $result['filename']);
    } else {
        setSetting($conn, "last_backup_status", "failed");
        setSetting($conn, "last_backup_message", $result['message']);
    }
    
    return $result;
}

function checkPendingBackups() {
    global $conn;
    
    $pending_dir = '../../backups/pending/';
    if (!file_exists($pending_dir)) {
        return;
    }
    
    $files = scandir($pending_dir);
    $backup_method = getSetting($conn, "backup_method", "local");
    $uploaded_count = 0;
    
    foreach ($files as $file) {
        if ($file === '.' || $file === '..' || !str_ends_with($file, '.sql')) {
            continue;
        }
        
        $filepath = $pending_dir . $file;
        
        // Check internet connection
        if (checkInternetConnection()) {
            // Upload backup
            require_once "backup.php"; // To access uploadBackup()
            $result = uploadBackup($filepath, $file, $backup_method);
            
            if ($result['success']) {
                unlink($filepath);
                $uploaded_count++;
            }
        } else {
            break; // No internet, stop trying
        }
    }
    
    if ($uploaded_count > 0) {
        error_log("Uploaded $uploaded_count pending backup(s)");
    }
}

function checkInternetConnection($host = '8.8.8.8', $port = 53, $timeout = 3) {
    try {
        $socket = @fsockopen($host, $port, $errno, $errstr, $timeout);
        if ($socket) {
            fclose($socket);
            return true;
        }
        return false;
    } catch (Exception $e) {
        return false;
    }
}

function testFTPConnection($server, $username, $password, $directory) {
    $conn_id = @ftp_connect($server, 21, 5);
    if (!$conn_id) {
        return ['success' => false, 'message' => "Cannot connect to FTP server: $server"];
    }
    
    $login_result = @ftp_login($conn_id, $username, $password);
    if (!$login_result) {
        ftp_close($conn_id);
        return ['success' => false, 'message' => 'FTP login failed. Check credentials'];
    }
    
    ftp_pasv($conn_id, true);
    
    // Test directory access
    if (!empty($directory) && $directory !== '/') {
        if (!@ftp_chdir($conn_id, $directory)) {
            // Try to create directory
            $dir_created = @ftp_mkdir($conn_id, $directory);
            if (!$dir_created) {
                ftp_close($conn_id);
                return ['success' => false, 'message' => "Cannot access or create directory: $directory"];
            }
        }
    }
    
    // Test file upload with small test file
    $test_content = "FTP Test - " . date('Y-m-d H:i:s');
    $test_filename = 'ftp_test_' . time() . '.txt';
    $temp_file = tempnam(sys_get_temp_dir(), 'ftp_test_');
    file_put_contents($temp_file, $test_content);
    
    $upload = @ftp_put($conn_id, $test_filename, $temp_file, FTP_ASCII);
    unlink($temp_file);
    
    if ($upload) {
        // Try to delete test file
        @ftp_delete($conn_id, $test_filename);
        ftp_close($conn_id);
        return ['success' => true, 'message' => "FTP connection successful! Server: $server, Directory: $directory"];
    } else {
        ftp_close($conn_id);
        return ['success' => false, 'message' => 'FTP upload test failed. Check write permissions'];
    }
}


?>