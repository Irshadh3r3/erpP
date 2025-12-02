<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';

requireLogin();

$conn = getDBConnection();

$customerId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($customerId <= 0) {
    $_SESSION['error_message'] = 'Invalid customer ID';
    header('Location: list.php');
    exit;
}

// Check if customer exists
$checkQuery = "SELECT id, name FROM customers WHERE id = $customerId";
$checkResult = $conn->query($checkQuery);

if ($checkResult->num_rows === 0) {
    $_SESSION['error_message'] = 'Customer not found';
    header('Location: list.php');
    exit;
}

$customer = $checkResult->fetch_assoc();

// Check if customer is used in any sales or bookings
$salesCheck = $conn->query("SELECT COUNT(*) as count FROM sales WHERE customer_id = $customerId");
$bookingsCheck = $conn->query("SELECT COUNT(*) as count FROM bookings WHERE customer_id = $customerId");

$salesCount = $salesCheck->fetch_assoc()['count'];
$bookingsCount = $bookingsCheck->fetch_assoc()['count'];

if ($salesCount > 0 || $bookingsCount > 0) {
    // Soft delete - just mark as inactive
    $updateQuery = "UPDATE customers SET is_active = 0 WHERE id = $customerId";
    $conn->query($updateQuery);
    $_SESSION['success_message'] = "Customer '{$customer['name']}' has been deactivated (has transaction history)";
} else {
    // Hard delete - completely remove
    $deleteQuery = "DELETE FROM customers WHERE id = $customerId";
    $conn->query($deleteQuery);
    $_SESSION['success_message'] = "Customer '{$customer['name']}' has been deleted successfully";
}

header('Location: list.php');
exit;
?>