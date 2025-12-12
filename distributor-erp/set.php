<?php
// install_database.php - Run this once to set up MySQL database
echo "<h2>MySQL Database Setup</h2>";

// Database configuration form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $host = $_POST['host'];
    $username = $_POST['username'];
    $password = $_POST['password'];
    $database = $_POST['database'];
    
    // Test connection
    $conn = @new mysqli($host, $username, $password);
    
    if ($conn->connect_error) {
        echo "<div style='color: red; padding: 10px; border: 1px solid red;'>";
        echo "Connection failed: " . $conn->connect_error;
        echo "</div>";
    } else {
        echo "<div style='color: green; padding: 10px; border: 1px solid green;'>";
        echo "✅ Connected to MySQL server successfully!<br>";
        
        // Create database if not exists
        if ($conn->query("CREATE DATABASE IF NOT EXISTS `$database`")) {
            echo "✅ Database '$database' ready<br>";
            $conn->select_db($database);
            
            // Import your database schema
            if (importDatabaseSchema($conn)) {
                echo "✅ Database schema imported successfully!<br>";
                
                // Save configuration
                saveDatabaseConfig($host, $username, $password, $database);
                echo "✅ Configuration saved!<br>";
                echo "<a href='login.php'>Click here to login</a>";
            }
        } else {
            echo "Failed to create database: " . $conn->error;
        }
        echo "</div>";
        
        $conn->close();
    }
}

function importDatabaseSchema($conn) {
    // Your database schema SQL
    $sql = file_get_contents('database_schema.sql');
    
    // Execute multiple queries
    $queries = explode(';', $sql);
    foreach ($queries as $query) {
        $query = trim($query);
        if (!empty($query)) {
            if (!$conn->query($query)) {
                echo "Error executing query: " . $conn->error . "<br>";
                echo "Query: " . $query . "<br>";
                return false;
            }
        }
    }
    return true;
}

function saveDatabaseConfig($host, $username, $password, $database) {
    $config = "<?php\n";
    $config .= "// Database Configuration\n";
    $config .= "define('DB_HOST', '" . addslashes($host) . "');\n";
    $config .= "define('DB_USER', '" . addslashes($username) . "');\n";
    $config .= "define('DB_PASS', '" . addslashes($password) . "');\n";
    $config .= "define('DB_NAME', '" . addslashes($database) . "');\n";
    $config .= "?>";
    
    file_put_contents('config/db_config.php', $config);
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Database Setup</title>
    <style>
        body { font-family: Arial; margin: 40px; }
        .form-group { margin: 15px 0; }
        label { display: inline-block; width: 150px; }
        input { padding: 8px; width: 300px; }
        button { padding: 10px 20px; background: #007bff; color: white; border: none; cursor: pointer; }
        .note { background: #f8f9fa; padding: 10px; border-left: 4px solid #007bff; margin: 20px 0; }
    </style>
</head>
<body>
    <div class="note">
        <strong>Requirements:</strong><br>
        1. MySQL Server must be installed<br>
        2. MySQL service must be running<br>
        3. Default port 3306 should be accessible<br>
    </div>
    
    <form method="POST">
        <div class="form-group">
            <label>MySQL Host:</label>
            <input type="text" name="host" value="localhost" required>
        </div>
        
        <div class="form-group">
            <label>Username:</label>
            <input type="text" name="username" value="root" required>
        </div>
        
        <div class="form-group">
            <label>Password:</label>
            <input type="password" name="password" value="">
        </div>
        
        <div class="form-group">
            <label>Database Name:</label>
            <input type="text" name="database" value="distributor_erp" required>
        </div>
        
        <button type="submit">Setup Database</button>
    </form>
    
    <div class="note" style="margin-top: 30px;">
        <strong>Default MySQL Credentials:</strong><br>
        • XAMPP/WAMP: root (no password)<br>
        • MySQL Installer: root (password you set during installation)<br>
        • Local Server: 127.0.0.1 or localhost
    </div>
</body>
</html>