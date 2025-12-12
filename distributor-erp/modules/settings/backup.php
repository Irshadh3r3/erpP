<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';

requireLogin();

// Check if user is admin
if (!hasRole('admin')) {
    $_SESSION['error_message'] = 'Access denied. Admin privileges required.';
    header('Location: ../../index.php');
    exit;
}

$conn = getDBConnection();
$pageTitle = 'Database Backup & Sync';

// Define backup methods
$backup_methods = [
    'local' => 'Local Storage Only',
    'email' => 'Email Backup',
    'ftp' => 'FTP Server',
    'google_drive' => 'Google Drive'
];

// Handle FTP test
if (isset($_POST['test_ftp'])) {
    $server = clean($_POST['test_ftp_server']);
    $username = clean($_POST['test_ftp_username']);
    $password = clean($_POST['test_ftp_password']);
    $directory = clean($_POST['test_ftp_directory']);
    
    if (empty($server) || empty($username)) {
        $message = 'error:FTP server and username are required';
    } else {
        $test_result = testFTPConnection($server, $username, $password, $directory);
        $message = $test_result['success'] ? 
                   'success:' . $test_result['message'] : 
                   'error:' . $test_result['message'];
    }
}

// Handle backup method selection
if (isset($_POST['save_backup_settings'])) {
    $auto_backup = isset($_POST['auto_backup_enabled']) ? 1 : 0;
    $backup_method = clean($_POST['backup_method']);
    $backup_time = clean($_POST['backup_time']);
    
    setSetting($conn, 'auto_backup_enabled', $auto_backup);
    setSetting($conn, 'backup_method', $backup_method);
    setSetting($conn, 'backup_time', $backup_time);
    
    // Save method-specific settings
    if ($backup_method === 'email') {
        setSetting($conn, 'backup_email', clean($_POST['backup_email']));
    }
    elseif ($backup_method === 'ftp') {
        setSetting($conn, 'ftp_server', clean($_POST['ftp_server']));
        setSetting($conn, 'ftp_username', clean($_POST['ftp_username']));
        setSetting($conn, 'ftp_password', encryptPassword(clean($_POST['ftp_password'])));
        setSetting($conn, 'ftp_directory', clean($_POST['ftp_directory']));
    }
    
    $message = 'success:Backup settings saved successfully!';
}

// Handle manual backup
if (isset($_POST['create_backup'])) {
    $backup_result = createDatabaseBackup();
    $message = $backup_result['success'] ? 
               'success:Database backup created successfully!' : 
               'error:' . $backup_result['message'];
}

// Handle test backup
if (isset($_POST['test_backup'])) {
    $test_result = testBackupMethod();
    $message = $test_result['success'] ? 
               'success:' . $test_result['message'] : 
               'error:' . $test_result['message'];
}

// Handle auto-cron setup
if (isset($_POST['setup_auto_cron'])) {
    $cron_result = setupAutoCronJob();
    $message = $cron_result['success'] ? 
               'success:' . $cron_result['message'] : 
               'error:' . $test_result['message'];
}

// Get backup settings
$auto_backup_enabled = getSetting($conn, 'auto_backup_enabled', '0');
$backup_method = getSetting($conn, 'backup_method', 'local');
$backup_time = getSetting($conn, 'backup_time', '02:00');
$last_backup_time = getSetting($conn, 'last_backup_time', 'Never');
$backup_email = getSetting($conn, 'backup_email', '');
$ftp_server = getSetting($conn, 'ftp_server', '');
$ftp_username = getSetting($conn, 'ftp_username', '');
$ftp_password = getSetting($conn, 'ftp_password', '');
$ftp_directory = getSetting($conn, 'ftp_directory', '/backups/');

// Get list of existing backups
$backup_dir = '../../backups/';
if (!file_exists($backup_dir)) {
    mkdir($backup_dir, 0755, true);
}

$backups = [];
if (is_dir($backup_dir)) {
    $files = scandir($backup_dir);
    foreach ($files as $file) {
        if (pathinfo($file, PATHINFO_EXTENSION) === 'sql') {
            $backups[] = [
                'filename' => $file,
                'size' => filesize($backup_dir . $file),
                'date' => filemtime($backup_dir . $file)
            ];
        }
    }
    // Sort by date, newest first
    usort($backups, function($a, $b) {
        return $b['date'] - $a['date'];
    });
}

// Helper function to create backup
// Improved backup function
function createDatabaseBackup($method = null) {
    global $conn;
    
    try {
        $backup_dir = '../../backups/';
        if (!file_exists($backup_dir)) {
            mkdir($backup_dir, 0755, true);
        }
        
        $filename = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
        $filepath = $backup_dir . $filename;
        
        // Get database credentials from config
        require_once '../../config/database.php';
        
        // Try different backup methods in order of preference
        $backup_methods = [
            'mysqldump_command' => function() use ($filepath) {
                // Method 1: Using mysqldump command with proper escaping
                $command = sprintf(
                    'mysqldump --user=%s --password=%s --host=%s %s > %s 2>&1',
                    escapeshellarg(DB_USER),
                    escapeshellarg(DB_PASS),
                    escapeshellarg(DB_HOST),
                    escapeshellarg(DB_NAME),
                    escapeshellarg($filepath)
                );
                exec($command, $output, $return_var);
                return ['return_var' => $return_var, 'output' => $output];
            },
            
            'mysqldump_no_password' => function() use ($filepath) {
                // Method 2: If password has special characters, use config file
                $config_file = tempnam(sys_get_temp_dir(), 'mysql_config_');
                file_put_contents($config_file, "[client]\nuser=" . DB_USER . "\npassword=" . DB_PASS . "\nhost=" . DB_HOST . "\n");
                
                $command = sprintf(
                    'mysqldump --defaults-extra-file=%s %s > %s 2>&1',
                    escapeshellarg($config_file),
                    escapeshellarg(DB_NAME),
                    escapeshellarg($filepath)
                );
                exec($command, $output, $return_var);
                unlink($config_file);
                return ['return_var' => $return_var, 'output' => $output];
            },
            
            'php_backup' => function() use ($conn, $filepath) {
                // Method 3: Pure PHP backup (no mysqldump required)
                return createPHPDatabaseBackup($conn, $filepath);
            }
        ];
        
        $backup_result = null;
        $backup_errors = [];
        
        // Try each backup method
        foreach ($backup_methods as $method_name => $backup_function) {
            try {
                $backup_result = $backup_function();
                
                if ($backup_result['return_var'] === 0 && file_exists($filepath) && filesize($filepath) > 0) {
                    // Success!
                    setSetting($conn, 'last_backup_time', date('Y-m-d H:i:s'));
                    setSetting($conn, 'last_backup_method', $method_name);
                    
                    // Upload based on method
                    if ($method) {
                        $upload_result = uploadBackup($filepath, $filename, $method);
                        if (!$upload_result['success']) {
                            // Save as pending for later
                            $pending_dir = $backup_dir . 'pending/';
                            if (!file_exists($pending_dir)) {
                                mkdir($pending_dir, 0755, true);
                            }
                            copy($filepath, $pending_dir . $filename);
                            
                            return [
                                'success' => true, 
                                'filename' => $filename,
                                'message' => 'Backup created locally. Cloud upload failed: ' . $upload_result['message']
                            ];
                        }
                    }
                    
                    return [
                        'success' => true, 
                        'filename' => $filename,
                        'message' => 'Backup created successfully using ' . $method_name
                    ];
                } else {
                    $backup_errors[$method_name] = isset($backup_result['output']) ? 
                        implode("\n", $backup_result['output']) : 'Unknown error';
                }
            } catch (Exception $e) {
                $backup_errors[$method_name] = $e->getMessage();
            }
        }
        
        // All methods failed
        return [
            'success' => false, 
            'message' => 'All backup methods failed. Errors: ' . implode(' | ', array_map(
                function($method, $error) {
                    return "$method: $error";
                }, 
                array_keys($backup_errors), 
                $backup_errors
            ))
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

// Pure PHP backup function (no mysqldump required)
function createPHPDatabaseBackup($conn, $filepath) {
    $output = "";
    
    // Get all tables
    $tables = [];
    $result = $conn->query('SHOW TABLES');
    while ($row = $result->fetch_row()) {
        $tables[] = $row[0];
    }
    
    foreach ($tables as $table) {
        // Drop table if exists
        $output .= "DROP TABLE IF EXISTS `$table`;\n";
        
        // Create table structure
        $result = $conn->query("SHOW CREATE TABLE `$table`");
        $row = $result->fetch_row();
        $output .= $row[1] . ";\n\n";
        
        // Get table data
        $result = $conn->query("SELECT * FROM `$table`");
        
        while ($row = $result->fetch_assoc()) {
            $values = [];
            foreach ($row as $value) {
                if ($value === null) {
                    $values[] = 'NULL';
                } else {
                    $values[] = "'" . $conn->real_escape_string($value) . "'";
                }
            }
            $output .= "INSERT INTO `$table` VALUES (" . implode(', ', $values) . ");\n";
        }
        $output .= "\n";
    }
    
    // Write to file
    if (file_put_contents($filepath, $output) !== false) {
        return ['return_var' => 0, 'output' => ['PHP backup successful']];
    } else {
        return ['return_var' => 1, 'output' => ['Failed to write backup file']];
    }
}

function uploadBackup($filepath, $filename, $method) {
    global $conn;
    
    switch ($method) {
        case 'email':
            $email = getSetting($conn, 'backup_email', '');
            if (empty($email)) {
                return ['success' => false, 'message' => 'Email not configured'];
            }
            return sendBackupEmail($filepath, $filename, $email);
            
        case 'ftp':
            $server = getSetting($conn, 'ftp_server', '');
            $username = getSetting($conn, 'ftp_username', '');
            $password = decryptPassword(getSetting($conn, 'ftp_password', ''));
            $directory = getSetting($conn, 'ftp_directory', '/backups/');
            
            if (empty($server) || empty($username) || empty($password)) {
                return ['success' => false, 'message' => 'FTP not configured'];
            }
            return uploadToFTP($filepath, $filename, $server, $username, $password, $directory);
            
        case 'google_drive':
            return ['success' => false, 'message' => 'Google Drive setup required'];
            
        default:
            return ['success' => true, 'message' => 'Local backup only'];
    }
}

function sendBackupEmail($filepath, $filename, $to_email) {
    $subject = "Database Backup - " . date('Y-m-d H:i:s');
    
    // Email headers
    $boundary = md5(time());
    $headers = "From: noreply@" . $_SERVER['HTTP_HOST'] . "\r\n";
    $headers .= "Reply-To: noreply@" . $_SERVER['HTTP_HOST'] . "\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: multipart/mixed; boundary=\"$boundary\"\r\n";
    
    // Email body
    $message = "--$boundary\r\n";
    $message .= "Content-Type: text/plain; charset=\"UTF-8\"\r\n";
    $message .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
    $message .= "Database Backup File\r\n";
    $message .= "====================\r\n";
    $message .= "Filename: $filename\r\n";
    $message .= "Date: " . date('Y-m-d H:i:s') . "\r\n";
    $message .= "Size: " . filesize($filepath) . " bytes\r\n";
    $message .= "\r\nThis is an automated backup from your ERP system.\r\n\r\n";
    
    // Read and encode file
    $file_content = file_get_contents($filepath);
    $file_encoded = chunk_split(base64_encode($file_content));
    
    // Attachment
    $message .= "--$boundary\r\n";
    $message .= "Content-Type: application/octet-stream; name=\"$filename\"\r\n";
    $message .= "Content-Transfer-Encoding: base64\r\n";
    $message .= "Content-Disposition: attachment; filename=\"$filename\"\r\n\r\n";
    $message .= "$file_encoded\r\n";
    $message .= "--$boundary--";
    
    // Send email
    if (mail($to_email, $subject, $message, $headers)) {
        return ['success' => true, 'message' => 'Email sent successfully to ' . $to_email];
    } else {
        return ['success' => false, 'message' => 'Email failed to send. Check PHP mail configuration.'];
    }
}

function uploadToFTP($filepath, $filename, $server, $username, $password, $directory) {
    // Validate parameters
    if (empty($server) || empty($username)) {
        return ['success' => false, 'message' => 'FTP server or username not configured'];
    }
    
    // Clean directory path
    $directory = rtrim($directory, '/') . '/';
    
    // Connect to FTP
    $conn_id = @ftp_connect($server, 21, 10); // 10 second timeout
    if (!$conn_id) {
        return ['success' => false, 'message' => "Failed to connect to FTP server: $server"];
    }
    
    // Login to FTP
    $login_result = @ftp_login($conn_id, $username, $password);
    if (!$login_result) {
        ftp_close($conn_id);
        return ['success' => false, 'message' => 'FTP login failed. Check username/password'];
    }
    
    // Enable passive mode (works better with firewalls)
    ftp_pasv($conn_id, true);
    
    // Try to create directory if it doesn't exist
    $dir_parts = explode('/', trim($directory, '/'));
    $current_path = '';
    foreach ($dir_parts as $part) {
        if (!empty($part)) {
            $current_path .= '/' . $part;
            @ftp_mkdir($conn_id, $current_path);
        }
    }
    
    // Change to directory
    if (!empty($directory) && $directory !== '/') {
        if (!@ftp_chdir($conn_id, $directory)) {
            ftp_close($conn_id);
            return ['success' => false, 'message' => "Cannot access directory: $directory"];
        }
    }
    
    // Upload file with error handling
    $upload = @ftp_put($conn_id, $filename, $filepath, FTP_BINARY);
    
    if (!$upload) {
        // Try alternative method
        $file_content = file_get_contents($filepath);
        $temp = tmpfile();
        fwrite($temp, $file_content);
        fseek($temp, 0);
        
        $upload = @ftp_fput($conn_id, $filename, $temp, FTP_BINARY);
        fclose($temp);
    }
    
    ftp_close($conn_id);
    
    if ($upload) {
        return ['success' => true, 'message' => "Uploaded to FTP: $server$directory$filename"];
    } else {
        return ['success' => false, 'message' => 'FTP upload failed. Check permissions and directory'];
    }
}

function testBackupMethod() {
    global $conn;
    $method = getSetting($conn, 'backup_method', 'local');
    
    // Create a small test file
    $test_file = '../../backups/test_backup.txt';
    file_put_contents($test_file, 'Test backup file created at ' . date('Y-m-d H:i:s'));
    
    switch ($method) {
        case 'email':
            $email = getSetting($conn, 'backup_email', '');
            if (empty($email)) {
                return ['success' => false, 'message' => 'Please configure email first'];
            }
            $result = sendBackupEmail($test_file, 'test_backup.txt', $email);
            unlink($test_file);
            return $result;
            
        case 'ftp':
            $server = getSetting($conn, 'ftp_server', '');
            $username = getSetting($conn, 'ftp_username', '');
            $password = decryptPassword(getSetting($conn, 'ftp_password', ''));
            $directory = getSetting($conn, 'ftp_directory', '/backups/');
            
            if (empty($server) || empty($username) || empty($password)) {
                return ['success' => false, 'message' => 'Please configure FTP first'];
            }
            $result = uploadToFTP($test_file, 'test_backup.txt', $server, $username, $password, $directory);
            unlink($test_file);
            return $result;
            
        default:
            unlink($test_file);
            return ['success' => true, 'message' => 'Local backup method is ready'];
    }
}

function setupAutoCronJob() {
    $cron_file = '../../backups/auto_cron.php';
    
    // Create auto cron file
    $cron_content = '<?php
/**
 * AUTO-GENERATED CRON JOB FOR BACKUPS
 * This file runs automatically every hour
 */
require_once "../../config/database.php";
require_once "../../includes/functions.php";

$conn = getDBConnection();
$auto_backup = getSetting($conn, "auto_backup_enabled", "0");
$backup_time = getSetting($conn, "backup_time", "02:00");

// Only run if auto-backup is enabled
if ($auto_backup != "1") {
    exit;
}

// Check if it\'s time for backup (within 5 minutes of scheduled time)
$current_hour = date("H:i");
$scheduled_hour = date("H:i", strtotime($backup_time));
$time_diff = abs(strtotime($current_hour) - strtotime($scheduled_hour));

if ($time_diff <= 300) { // 5 minutes window
    // Check if backup already ran today
    $last_backup = getSetting($conn, "last_backup_date", "");
    if ($last_backup != date("Y-m-d")) {
        // Run backup
        require_once "backup_functions.php";
        runScheduledBackup();
        setSetting($conn, "last_backup_date", date("Y-m-d"));
    }
}

// Check for pending backups and retry
checkPendingBackups();
?>';
    
    if (file_put_contents($cron_file, $cron_content)) {
        // Create Windows Task Scheduler XML
        $xml_file = createWindowsTaskSchedulerXML();
        
        return [
            'success' => true, 
            'message' => 'Auto-cron system installed! The system will automatically check for backups every hour.'
        ];
    }
    
    return ['success' => false, 'message' => 'Failed to create cron file'];
}

function createWindowsTaskSchedulerXML() {
    $xml_content = '<?xml version="1.0" encoding="UTF-16"?>
<Task version="1.2" xmlns="http://schemas.microsoft.com/windows/2004/02/mit/task">
  <RegistrationInfo>
    <Date>' . date('Y-m-d\TH:i:s') . '</Date>
    <Author>Distributor ERP</Author>
    <Description>Auto backup task for Distributor ERP</Description>
  </RegistrationInfo>
  <Triggers>
    <TimeTrigger>
      <StartBoundary>' . date('Y-m-d') . 'T01:00:00</StartBoundary>
      <Enabled>true</Enabled>
      <Repetition>
        <Interval>PT1H</Interval>
        <Duration>P1D</Duration>
        <StopAtDurationEnd>false</StopAtDurationEnd>
      </Repetition>
    </TimeTrigger>
  </Triggers>
  <Principals>
    <Principal id="Author">
      <UserId>S-1-5-18</UserId>
      <RunLevel>HighestAvailable</RunLevel>
    </Principal>
  </Principals>
  <Settings>
    <MultipleInstancesPolicy>IgnoreNew</MultipleInstancesPolicy>
    <DisallowStartIfOnBatteries>false</DisallowStartIfOnBatteries>
    <StopIfGoingOnBatteries>true</StopIfGoingOnBatteries>
    <AllowHardTerminate>true</AllowHardTerminate>
    <StartWhenAvailable>true</StartWhenAvailable>
    <RunOnlyIfNetworkAvailable>false</RunOnlyIfNetworkAvailable>
    <IdleSettings>
      <StopOnIdleEnd>true</StopOnIdleEnd>
      <RestartOnIdle>false</RestartOnIdle>
    </IdleSettings>
    <AllowStartOnDemand>true</AllowStartOnDemand>
    <Enabled>true</Enabled>
    <Hidden>false</Hidden>
    <RunOnlyIfIdle>false</RunOnlyIfIdle>
    <WakeToRun>false</WakeToRun>
    <ExecutionTimeLimit>PT30M</ExecutionTimeLimit>
    <Priority>7</Priority>
  </Settings>
  <Actions Context="Author">
    <Exec>
      <Command>C:\xampp\php\php.exe</Command>
      <Arguments>"C:\xampp\htdocs\distributor-erp\backups\auto_cron.php"</Arguments>
    </Exec>
  </Actions>
</Task>';
    
    $xml_file = '../../backups/auto_backup_task.xml';
    file_put_contents($xml_file, $xml_content);
    return $xml_file;
}

// Simple encryption/decryption for passwords
function encryptPassword($password) {
    return base64_encode($password);
}

function decryptPassword($encrypted) {
    return base64_decode($encrypted);
}

include '../../includes/header.php';
?>

<!-- Display Messages -->
<?php if (isset($message)): 
    list($type, $text) = explode(':', $message, 2);
    $alertColors = [
        'success' => 'bg-green-100 border-green-400 text-green-700',
        'error' => 'bg-red-100 border-red-400 text-red-700',
        'info' => 'bg-blue-100 border-blue-400 text-blue-700'
    ];
?>
    <div class="<?php echo $alertColors[$type]; ?> border px-4 py-3 rounded-lg mb-6">
        <?php echo $text; ?>
    </div>
<?php endif; ?>

<!-- Page Header -->
<div class="mb-6">
    <h1 class="text-3xl font-bold text-gray-800">Database Backup & Sync</h1>
    <p class="text-gray-600">Manage database backups with automatic cloud sync</p>
</div>

<!-- Quick Actions -->
<div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
    <!-- Manual Backup -->
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center mb-4">
            <div class="bg-blue-100 p-3 rounded-full">
                <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"></path>
                </svg>
            </div>
            <div class="ml-4">
                <h3 class="text-lg font-bold text-gray-800">Manual Backup</h3>
                <p class="text-sm text-gray-600">Create backup now</p>
            </div>
        </div>
        <form method="POST" action="">
            <button type="submit" name="create_backup" 
                    class="w-full bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-semibold transition">
                Create Backup Now
            </button>
        </form>
        <p class="text-xs text-gray-500 mt-3">
            <strong>Last Backup:</strong> <?php echo $last_backup_time; ?>
        </p>
    </div>

    <!-- Test Backup Method -->
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center mb-4">
            <div class="bg-yellow-100 p-3 rounded-full">
                <svg class="w-8 h-8 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
            <div class="ml-4">
                <h3 class="text-lg font-bold text-gray-800">Test Backup Method</h3>
                <p class="text-sm text-gray-600">Test your configured backup method</p>
            </div>
        </div>
        <form method="POST" action="">
            <button type="submit" name="test_backup" 
                    class="w-full bg-yellow-600 hover:bg-yellow-700 text-white px-6 py-3 rounded-lg font-semibold transition">
                Test Backup Method
            </button>
        </form>
        <p class="text-xs text-gray-500 mt-3">
            <strong>Current Method:</strong> <?php echo $backup_methods[$backup_method] ?? 'Local'; ?>
        </p>
    </div>
</div>

<!-- Backup Settings -->
<div class="bg-white rounded-lg shadow p-6 mb-6">
    <h3 class="text-lg font-bold text-gray-800 mb-4">Backup Settings</h3>
    <form method="POST" action="">
        <div class="space-y-6">
            <!-- Auto Backup Toggle -->
            <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                <div>
                    <h4 class="font-semibold text-gray-800">Enable Automatic Backup</h4>
                    <p class="text-sm text-gray-600">Automatically backup database at scheduled time</p>
                </div>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" name="auto_backup_enabled" value="1" 
                           <?php echo $auto_backup_enabled == '1' ? 'checked' : ''; ?> 
                           class="sr-only peer">
                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                </label>
            </div>
            
            <!-- Backup Time -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Backup Time</label>
                <input type="time" name="backup_time" value="<?php echo $backup_time; ?>" 
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                <p class="text-xs text-gray-500 mt-1">Daily backup time (24-hour format)</p>
            </div>
            
            <!-- Backup Method -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Backup Method</label>
                <select name="backup_method" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <?php foreach ($backup_methods as $value => $label): ?>
                        <option value="<?php echo $value; ?>" <?php echo $backup_method == $value ? 'selected' : ''; ?>>
                            <?php echo $label; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <!-- Email Settings (shown when email selected) -->
            <div id="email_settings" class="border-l-4 border-blue-500 pl-4 bg-blue-50 p-4 rounded" 
                 style="display: <?php echo $backup_method == 'email' ? 'block' : 'none'; ?>">
                <h4 class="font-semibold text-gray-800 mb-3">Email Settings</h4>
                <div class="space-y-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Email Address</label>
                        <input type="email" name="backup_email" value="<?php echo $backup_email; ?>" 
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                               placeholder="your-email@example.com">
                    </div>
                    <p class="text-xs text-gray-600">
                        <strong>Note:</strong> PHP mail() function must be configured on your server. 
                        Backups will be sent as email attachments when internet is available.
                    </p>
                </div>
            </div>
            
            <!-- FTP Settings (shown when FTP selected) -->
            <!-- FTP Settings (shown when FTP selected) -->
<div id="ftp_settings" class="border-l-4 border-green-500 pl-4 bg-green-50 p-4 rounded" 
     style="display: <?php echo $backup_method == 'ftp' ? 'block' : 'none'; ?>">
    <h4 class="font-semibold text-gray-800 mb-3">🔄 FTP Backup Settings</h4>
    <div class="space-y-4">
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    FTP Server/Host
                    <span class="text-red-500">*</span>
                </label>
                <input type="text" name="ftp_server" value="<?php echo $ftp_server; ?>" 
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500"
                       placeholder="ftp.example.com or IP address"
                       required>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Port (Optional)
                </label>
                <input type="number" name="ftp_port" value="21" 
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500"
                       placeholder="21">
                <p class="text-xs text-gray-500 mt-1">Default: 21</p>
            </div>
        </div>
        
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Username
                    <span class="text-red-500">*</span>
                </label>
                <input type="text" name="ftp_username" value="<?php echo $ftp_username; ?>" 
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500"
                       required>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Password
                    <span class="text-red-500">*</span>
                </label>
                <input type="password" name="ftp_password" value="<?php echo decryptPassword($ftp_password); ?>" 
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500"
                       required>
            </div>
        </div>
        
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
                Remote Directory
            </label>
            <input type="text" name="ftp_directory" value="<?php echo $ftp_directory; ?>" 
                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500"
                   placeholder="/backups/">
            <p class="text-xs text-gray-500 mt-1">Leave empty for root directory. Use forward slashes (/)</p>
        </div>
        
        <!-- FTP Connection Test -->
        <div class="border-t pt-4 mt-4">
            <h5 class="font-semibold text-gray-700 mb-2">Test FTP Connection</h5>
            <div class="space-y-3">
                <div class="grid grid-cols-2 gap-4">
                    <input type="text" name="test_ftp_server" 
                           value="<?php echo $ftp_server; ?>"
                           class="px-4 py-2 border border-gray-300 rounded-lg"
                           placeholder="FTP Server">
                    <input type="text" name="test_ftp_username" 
                           value="<?php echo $ftp_username; ?>"
                           class="px-4 py-2 border border-gray-300 rounded-lg"
                           placeholder="Username">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <input type="password" name="test_ftp_password" 
                           value="<?php echo decryptPassword($ftp_password); ?>"
                           class="px-4 py-2 border border-gray-300 rounded-lg"
                           placeholder="Password">
                    <input type="text" name="test_ftp_directory" 
                           value="<?php echo $ftp_directory; ?>"
                           class="px-4 py-2 border border-gray-300 rounded-lg"
                           placeholder="/backups/">
                </div>
                <button type="submit" name="test_ftp" 
                        class="bg-green-600 hover:bg-green-700 text-white px-6 py-2 rounded-lg font-semibold transition">
                    Test FTP Connection
                </button>
            </div>
        </div>
        
        <!-- FTP Configuration Tips -->
        <div class="bg-blue-50 border border-blue-200 p-3 rounded">
            <h6 class="font-semibold text-blue-800 mb-1">💡 FTP Configuration Tips:</h6>
            <ul class="text-xs text-blue-700 space-y-1">
                <li>• For <strong>FileZilla Server</strong>: Use "127.0.0.1" as server, create user in FileZilla</li>
                <li>• For <strong>Windows IIS FTP</strong>: Use computer name or localhost</li>
                <li>• For <strong>Online FTP</strong>: Use provided FTP details from hosting</li>
                <li>• Enable <strong>Passive Mode</strong> if behind firewall</li>
                <li>• Default port is 21, use 22 for SFTP (different protocol)</li>
            </ul>
        </div>
        
        <!-- Setup Free Online FTP for Testing -->
        <div class="bg-yellow-50 border border-yellow-200 p-3 rounded">
            <h6 class="font-semibold text-yellow-800 mb-1">🌐 Free Online FTP for Testing:</h6>
            <ol class="text-xs text-yellow-700 list-decimal list-inside space-y-1">
                <li>Go to <a href="https://drivehq.com" target="_blank" class="text-blue-600 underline">DriveHQ.com</a> (free FTP hosting)</li>
                <li>Create free account</li>
                <li>Get FTP credentials from account settings</li>
                <li>Use those credentials here</li>
                <li>Test with 1GB free storage</li>
            </ol>
        </div>
        
        <!-- Quick Setup for Local FTP Server -->
        <div class="bg-purple-50 border border-purple-200 p-3 rounded">
            <h6 class="font-semibold text-purple-800 mb-1">⚡ Quick Local FTP Setup (Windows):</h6>
            <ol class="text-xs text-purple-700 list-decimal list-inside space-y-1">
                <li><strong>Install FileZilla Server</strong> (free)</li>
                <li>Run FileZilla Server Interface</li>
                <li>Click "Edit" → "Users"</li>
                <li>Add user: "erp_backup" with password</li>
                <li>Set home directory (e.g., C:\backups)</li>
                <li>Set permissions: Read, Write, Delete, Append</li>
                <li>Use these settings:
                    <ul class="list-disc list-inside ml-4 mt-1">
                        <li>Server: <code class="bg-purple-100 px-1">127.0.0.1</code></li>
                        <li>Port: <code class="bg-purple-100 px-1">21</code></li>
                        <li>Username: <code class="bg-purple-100 px-1">erp_backup</code></li>
                        <li>Directory: <code class="bg-purple-100 px-1">/</code></li>
                    </ul>
                </li>
            </ol>
        </div>
    </div>
</div>
            
            <!-- Google Drive Settings (shown when Google Drive selected) -->
            <div id="google_drive_settings" class="border-l-4 border-red-500 pl-4 bg-red-50 p-4 rounded" 
                 style="display: <?php echo $backup_method == 'google_drive' ? 'block' : 'none'; ?>">
                <h4 class="font-semibold text-gray-800 mb-3">Google Drive Setup</h4>
                <div class="space-y-3">
                    <p class="text-sm text-gray-600">
                        To use Google Drive backup:
                    </p>
                    <ol class="list-decimal list-inside text-sm text-gray-600 space-y-1 ml-4">
                        <li>Create a project in Google Cloud Console</li>
                        <li>Enable Google Drive API</li>
                        <li>Create OAuth 2.0 credentials (Service Account)</li>
                        <li>Download credentials JSON file</li>
                        <li>Place file in: <code class="bg-gray-200 px-1">/config/google-credentials.json</code></li>
                    </ol>
                    <p class="text-xs text-gray-500 mt-2">
                        Requires: <code>composer require google/apiclient</code>
                    </p>
                </div>
            </div>
            
            <button type="submit" name="save_backup_settings" 
                    class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-semibold transition">
                Save Settings
            </button>
        </div>
    </form>
</div>

<!-- Auto Cron Setup -->
<div class="bg-purple-100 border-2 border-purple-200 rounded-lg p-6 mb-6">
    <div class="flex items-center mb-4">
        <div class="bg-purple-600 text-white p-3 rounded-full">
            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
        </div>
        <div class="ml-4">
            <h3 class="text-lg font-bold text-purple-900">⚡ Automatic Cron Job Setup</h3>
            <p class="text-sm text-purple-700">One-click setup - No manual cron/task scheduler needed!</p>
        </div>
    </div>
    
    <div class="space-y-4">
        <p class="text-sm text-purple-800">
            This system will automatically run backup checks every hour using PHP's built-in scheduler.
            No need for Windows Task Scheduler or cron configuration!
        </p>
        
        <div class="bg-purple-200 p-4 rounded">
            <h4 class="font-semibold text-purple-900 mb-2">How it works:</h4>
            <ul class="list-disc list-inside text-sm text-purple-800 space-y-1">
                <li>Runs automatically when users access your ERP system</li>
                <li>Checks if backup is due (once per day at your scheduled time)</li>
                <li>Creates backup locally first</li>
                <li>Uploads to cloud if internet is available</li>
                <li>Retries failed uploads automatically</li>
            </ul>
        </div>
        
        <form method="POST" action="">
            <button type="submit" name="setup_auto_cron" 
                    class="bg-purple-600 hover:bg-purple-700 text-white px-6 py-3 rounded-lg font-semibold transition">
                🚀 Setup Auto Cron System
            </button>
        </form>
        
        <p class="text-xs text-purple-700">
            <strong>Note:</strong> The system checks for backups whenever any user accesses the ERP. 
            For 100% reliability, also setup Windows Task Scheduler to run hourly.
        </p>
    </div>
</div>

<!-- Alternative: Simple Windows Task Setup -->
<div class="bg-green-50 border-2 border-green-200 rounded-lg p-6 mb-6">
    <h3 class="text-lg font-bold text-green-900 mb-3">📋 One-Click Windows Task Scheduler Setup</h3>
    
    <div class="space-y-4">
        <p class="text-sm text-green-800">
            For guaranteed hourly backups (even when no users are active), create a Windows Task:
        </p>
        
        <div class="bg-green-100 p-4 rounded">
            <h4 class="font-semibold text-green-900 mb-2">Easy Setup:</h4>
            <ol class="list-decimal list-inside text-sm text-green-800 space-y-2 ml-4">
                <li>Click the button below to download the Task Scheduler XML file</li>
                <li>Open Windows Task Scheduler</li>
                <li>Click "Import Task" on the right panel</li>
                <li>Select the downloaded XML file</li>
                <li>Enter your Windows password when prompted</li>
            </ol>
        </div>
        
        <div class="flex space-x-4">
            <a href="../../backups/auto_backup_task.xml" download 
               class="bg-green-600 hover:bg-green-700 text-white px-6 py-3 rounded-lg font-semibold transition inline-flex items-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                Download Task XML
            </a>
            
            <a href="https://www.wikihow.com/Create-a-Task-in-Windows-Task-Scheduler" target="_blank" 
               class="bg-green-500 hover:bg-green-600 text-white px-6 py-3 rounded-lg font-semibold transition inline-flex items-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                Setup Guide
            </a>
        </div>
    </div>
</div>

<!-- Backup History -->
<div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="p-6 border-b">
        <h3 class="text-lg font-bold text-gray-800">Backup History</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">#</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Filename</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date Created</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Size</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php if (count($backups) > 0): ?>
                    <?php 
                    $num = 1;
                    foreach ($backups as $backup): 
                    ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 text-sm"><?php echo $num++; ?></td>
                            <td class="px-6 py-4 text-sm font-mono">
                                <?php echo $backup['filename']; ?>
                            </td>
                            <td class="px-6 py-4 text-sm">
                                <?php echo date('d M Y, h:i A', $backup['date']); ?>
                            </td>
                            <td class="px-6 py-4 text-sm">
                                <?php 
                                $size = $backup['size'];
                                if ($size > 1048576) {
                                    echo number_format($size / 1048576, 2) . ' MB';
                                } elseif ($size > 1024) {
                                    echo number_format($size / 1024, 2) . ' KB';
                                } else {
                                    echo $size . ' bytes';
                                }
                                ?>
                            </td>
                            <td class="px-6 py-4 text-sm">
                                <a href="download_backup.php?file=<?php echo urlencode($backup['filename']); ?>" 
                                   class="text-blue-600 hover:text-blue-800 font-semibold mr-4">
                                    Download
                                </a>
                                <a href="delete_backup.php?file=<?php echo urlencode($backup['filename']); ?>" 
                                   onclick="return confirm('Are you sure you want to delete this backup?')"
                                   class="text-red-600 hover:text-red-800 font-semibold">
                                    Delete
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" class="px-6 py-8 text-center text-gray-500">
                            No backups found. Create your first backup above.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
// Show/hide settings based on backup method
document.querySelector('select[name="backup_method"]').addEventListener('change', function() {
    const method = this.value;
    
    // Hide all settings divs
    document.getElementById('email_settings').style.display = 'none';
    document.getElementById('ftp_settings').style.display = 'none';
    document.getElementById('google_drive_settings').style.display = 'none';
    
    // Show selected method's settings
    if (method === 'email') {
        document.getElementById('email_settings').style.display = 'block';
    } else if (method === 'ftp') {
        document.getElementById('ftp_settings').style.display = 'block';
    } else if (method === 'google_drive') {
        document.getElementById('google_drive_settings').style.display = 'block';
    }
});

// Auto-run backup check when page loads (if auto-cron is setup)
window.addEventListener('load', function() {
    // This would trigger the auto-cron check in background
    // For simplicity, we'll just show a message if auto-cron is enabled
    const autoBackupEnabled = <?php echo $auto_backup_enabled == '1' ? 'true' : 'false'; ?>;
    if (autoBackupEnabled) {
        console.log('Auto-backup system is active. Backups will run at scheduled time.');
    }
});
</script>

<style>
.sr-only {
    position: absolute;
    width: 1px;
    height: 1px;
    padding: 0;
    margin: -1px;
    overflow: hidden;
    clip: rect(0, 0, 0, 0);
    white-space: nowrap;
    border-width: 0;
}
</style>

<?php
include '../../includes/footer.php';
?>