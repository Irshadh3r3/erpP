<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';

requireLogin();

$conn = getDBConnection();

$supplierId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($supplierId <= 0) {
    $_SESSION['error_message'] = 'Invalid supplier ID';
    header('Location: list.php');
    exit;
}

// Check if supplier exists
$checkQuery = "SELECT id, name FROM suppliers WHERE id = $supplierId";
$checkResult = $conn->query($checkQuery);

if ($checkResult->num_rows === 0) {
    $_SESSION['error_message'] = 'Supplier not found';
    header('Location: list.php');
    exit;
}

$supplier = $checkResult->fetch_assoc();

// Check if supplier is used in any purchases
$purchasesCheck = $conn->query("SELECT COUNT(*) as count FROM purchases WHERE supplier_id = $supplierId");
$purchasesCount = $purchasesCheck->fetch_assoc()['count'];

if ($purchasesCount > 0) {
    // Soft delete - just mark as inactive
    $updateQuery = "UPDATE suppliers SET is_active = 0 WHERE id = $supplierId";
    $conn->query($updateQuery);
    $_SESSION['success_message'] = "Supplier '{$supplier['name']}' has been deactivated (has purchase history)";
} else {
    // Hard delete - completely remove
    $deleteQuery = "DELETE FROM suppliers WHERE id = $supplierId";
    $conn->query($deleteQuery);
    $_SESSION['success_message'] = "Supplier '{$supplier['name']}' has been deleted successfully";
}

header('Location: list.php');
exit;
?>