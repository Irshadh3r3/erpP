<?php
// Run this file ONCE to create the admin user
// Access it at: http://localhost/distributor-erp/setup.php
// Delete this file after running it!

require_once 'config/database.php';

$conn = getDBConnection();

// Check if admin already exists
$check = $conn->query("SELECT id FROM users WHERE username = 'admin'");

if ($check->num_rows > 0) {
    echo "<h2>Admin user already exists!</h2>";
    echo "<p>Username: <strong>admin</strong></p>";
    echo "<p>If you forgot the password, delete the admin user from database and run this file again.</p>";
    echo "<p><a href='auth/login.php'>Go to Login</a></p>";
    exit;
}

// Create admin user with password: admin123
$username = 'admin';
$password = password_hash('admin123', PASSWORD_DEFAULT);
$full_name = 'Administrator';
$email = 'admin@example.com';
$role = 'admin';

$stmt = $conn->prepare("INSERT INTO users (username, password, full_name, email, role) VALUES (?, ?, ?, ?, ?)");
$stmt->bind_param("sssss", $username, $password, $full_name, $email, $role);

if ($stmt->execute()) {
    echo "<h2 style='color: green;'>✓ Admin user created successfully!</h2>";
    echo "<p><strong>Username:</strong> admin</p>";
    echo "<p><strong>Password:</strong> admin123</p>";
    echo "<hr>";
    echo "<p><a href='auth/login.php' style='background: #3B82F6; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Go to Login Page</a></p>";
    echo "<hr>";
    echo "<p style='color: red;'><strong>IMPORTANT:</strong> Delete this setup.php file after logging in for security!</p>";
} else {
    echo "<h2 style='color: red;'>Error creating admin user!</h2>";
    echo "<p>" . $conn->error . "</p>";
}

$stmt->close();
$conn->close();
?>