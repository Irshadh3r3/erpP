<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';

requireLogin();

$conn = getDBConnection();

$productId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($productId <= 0) {
    $_SESSION['error_message'] = 'Invalid product ID';
    header('Location: list.php');
    exit;
}

// Check if product exists
$checkQuery = "SELECT id, name FROM products WHERE id = $productId";
$checkResult = $conn->query($checkQuery);

if ($checkResult->num_rows === 0) {
    $_SESSION['error_message'] = 'Product not found';
    header('Location: list.php');
    exit;
}

$product = $checkResult->fetch_assoc();

// Check if product is used in any sales or purchases
$salesCheck = $conn->query("SELECT COUNT(*) as count FROM sales_items WHERE product_id = $productId");
$purchasesCheck = $conn->query("SELECT COUNT(*) as count FROM purchase_items WHERE product_id = $productId");

$salesCount = $salesCheck->fetch_assoc()['count'];
$purchasesCount = $purchasesCheck->fetch_assoc()['count'];

if ($salesCount > 0 || $purchasesCount > 0) {
    // Soft delete - just mark as inactive
    $updateQuery = "UPDATE products SET is_active = 0 WHERE id = $productId";
    $conn->query($updateQuery);
    $_SESSION['success_message'] = "Product '{$product['name']}' has been deactivated (used in transactions)";
} else {
    // Hard delete - completely remove
    $deleteQuery = "DELETE FROM products WHERE id = $productId";
    $conn->query($deleteQuery);
    $_SESSION['success_message'] = "Product '{$product['name']}' has been deleted successfully";
}

header('Location: list.php');
exit;
?>