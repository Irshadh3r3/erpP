<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';

requireLogin();

$conn = getDBConnection();

// Check permission
requirePermission($conn, $_SESSION['role'], 'categories', 'delete');

$category_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$category_id) {
    $_SESSION['error_message'] = 'Invalid category ID';
    header('Location: list.php');
    exit;
}

// Check if category is used by any products
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM products WHERE category_id = ?");
$stmt->bind_param('i', $category_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();

if ($row['count'] > 0) {
    $_SESSION['error_message'] = "Cannot delete category. It is used by {$row['count']} product(s).";
} else {
    // Delete category
    $stmt = $conn->prepare("DELETE FROM categories WHERE id = ?");
    $stmt->bind_param('i', $category_id);
    
    if ($stmt->execute()) {
        $_SESSION['success_message'] = 'Category deleted successfully';
    } else {
        $_SESSION['error_message'] = 'Error deleting category';
    }
}

header('Location: list.php');
exit;
