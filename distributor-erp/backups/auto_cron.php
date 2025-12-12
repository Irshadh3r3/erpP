<?php
/**
 * AUTO-GENERATED CRON JOB FOR BACKUPS
 * This file runs automatically every hour
 * Place in: C:\xampp\htdocs\distributor-erp\backups\auto_cron.php
 */

// Set maximum execution time
set_time_limit(300); // 5 minutes
ignore_user_abort(true);

// Log file for debugging
$log_file = dirname(__FILE__) . '/auto_cron.log';
$lock_file = dirname(__FILE__) . '/auto_cron.lock';

// Prevent multiple instances from running
if (file_exists($lock_file)) {
    $lock_time = filemtime($lock_file);
    if (time() - $lock_time < 3500) { // Less than 1 hour
        exit("Another instance is already running.\n");
    }
}

// Create lock file
touch($lock_file);

// Function to log messages
function logMessage($message) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] $message\n";
    file_put_contents($log_file, $log_entry, FILE_APPEND);
    echo $log_entry;
}

logMessage("=== Auto Cron Started ===");

try {
    // Load ERP configuration
    require_once "../config/database.php";
    require_once "../includes/functions.php";
    
    logMessage("Configuration loaded");
    
    $conn = getDBConnection();
    
    // Check if auto-backup is enabled
    $auto_backup = getSetting($conn, "auto_backup_enabled", "0");
    logMessage("Auto backup setting: " . $auto_backup);
    
    if ($auto_backup != "1") {
        logMessage("Auto backup is disabled. Exiting.");
        unlink($lock_file);
        exit();
    }
    
    // Get backup time
    $backup_time = getSetting($conn, "backup_time", "02:00");
    $current_time = date("H:i");
    
    logMessage("Current time: $current_time, Scheduled time: $backup_time");
    
    // Check if it's time for backup (within 5 minutes of scheduled time)
    $time_diff = abs(strtotime($current_time) - strtotime($backup_time));
    logMessage("Time difference: " . $time_diff . " seconds");
    
    if ($time_diff <= 300) { // 5 minutes window
        // Check if backup already ran today
        $last_backup = getSetting($conn, "last_backup_date", "");
        $today = date("Y-m-d");
        
        logMessage("Last backup date: $last_backup, Today: $today");
        
        if ($last_backup != $today) {
            logMessage("Starting scheduled backup...");
            
            // Include backup functions
            $backup_functions = "../../modules/settings/backup_functions.php";
            if (file_exists($backup_functions)) {
                require_once $backup_functions;
                logMessage("Backup functions loaded");
            } else {
                // Fallback: Direct backup
                logMessage("Backup functions not found, using fallback");
                require_once "../../modules/settings/backup.php"; // To access createDatabaseBackup()
            }
            
            // Run backup
            if (function_exists('runScheduledBackup')) {
                $result = runScheduledBackup();
            } else {
                // Fallback backup
                $result = createDatabaseBackup(getSetting($conn, "backup_method", "local"));
            }
            
            if ($result['success']) {
                setSetting($conn, "last_backup_date", $today);
                setSetting($conn, "last_backup_status", "success");
                logMessage("✅ Backup completed successfully: " . $result['filename']);
                
                // Log details
                if (isset($result['message'])) {
                    logMessage("Message: " . $result['message']);
                }
            } else {
                setSetting($conn, "last_backup_status", "failed");
                logMessage("❌ Backup failed: " . $result['message']);
            }
        } else {
            logMessage("Backup already ran today. Skipping.");
        }
    } else {
        logMessage("Not backup time yet. Next check in 1 hour.");
    }
    
    // Check for pending backups and retry (every 6 hours)
    $last_pending_check = getSetting($conn, "last_pending_check", "0");
    if (time() - $last_pending_check >= 21600) { // 6 hours
        logMessage("Checking for pending backups...");
        
        $pending_dir = dirname(__FILE__) . '/pending/';
        if (file_exists($pending_dir)) {
            $files = scandir($pending_dir);
            $backup_method = getSetting($conn, "backup_method", "local");
            $uploaded_count = 0;
            
            foreach ($files as $file) {
                if ($file === '.' || $file === '..' || !str_ends_with($file, '.sql')) {
                    continue;
                }
                
                $filepath = $pending_dir . $file;
                
                // Check internet connection before trying upload
                if (checkInternetConnection()) {
                    logMessage("Attempting to upload pending backup: $file");
                    
                    // Try to upload
                    require_once "../../modules/settings/backup.php"; // For uploadBackup()
                    $result = uploadBackup($filepath, $file, $backup_method);
                    
                    if ($result['success']) {
                        unlink($filepath);
                        $uploaded_count++;
                        logMessage("✅ Uploaded pending backup: $file");
                    } else {
                        logMessage("⚠ Still cannot upload: " . $result['message']);
                    }
                } else {
                    logMessage("No internet connection. Cannot upload pending backups.");
                    break;
                }
            }
            
            if ($uploaded_count > 0) {
                logMessage("Uploaded $uploaded_count pending backup(s)");
            }
        }
        
        setSetting($conn, "last_pending_check", time());
    }
    
    // Clean old logs (keep 7 days)
    cleanOldLogs(7);
    
    $conn->close();
    
} catch (Exception $e) {
    logMessage("❌ ERROR: " . $e->getMessage());
    logMessage("Stack trace: " . $e->getTraceAsString());
}

// Remove lock file
unlink($lock_file);
logMessage("=== Auto Cron Finished ===\n");

// Helper functions
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

function cleanOldLogs($days_to_keep = 7) {
    $log_file = dirname(__FILE__) . '/auto_cron.log';
    $max_size = 10 * 1024 * 1024; // 10MB
    
    // Check file size
    if (file_exists($log_file) && filesize($log_file) > $max_size) {
        // Keep last 1000 lines
        $lines = file($log_file);
        if (count($lines) > 1000) {
            $recent_lines = array_slice($lines, -1000);
            file_put_contents($log_file, implode('', $recent_lines));
        }
    }
    
    // Clean old backup files (older than 30 days)
    $backup_dir = dirname(__FILE__) . '/';
    $files = scandir($backup_dir);
    $cutoff_time = time() - ($days_to_keep * 24 * 3600);
    
    foreach ($files as $file) {
        if (strpos($file, 'backup_') === 0 && strpos($file, '.sql') !== false) {
            $filepath = $backup_dir . $file;
            if (filemtime($filepath) < $cutoff_time) {
                unlink($filepath);
                logMessage("Cleaned old backup: $file");
            }
        }
    }
}

// If called via command line, show output
if (php_sapi_name() === 'cli') {
    // Output already shown via echo
} else {
    // Web access - just log, don't output
    header('Content-Type: text/plain');
    echo "Auto cron job executed. Check log file for details.\n";
}
?>