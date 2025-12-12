<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';

requireLogin();

$conn = getDBConnection();

// Permission
requirePermission($conn, $_SESSION['role'], 'purchases', 'delete');

$purchase_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$purchase_id) {
    $_SESSION['error_message'] = 'Invalid purchase ID';
    header('Location: list.php');
    exit;
}

// Fetch items for stock reversal
$stmt = $conn->prepare("SELECT product_id, quantity FROM purchase_items WHERE purchase_id = ?");
$stmt->bind_param('i', $purchase_id);
$stmt->execute();
$res = $stmt->get_result();
$items = $res->fetch_all(MYSQLI_ASSOC);

$conn->begin_transaction();
try {
    // For each item, decrease stock by the purchased quantity (reverse the purchase)
    foreach ($items as $it) {
        updateProductStock($conn, $it['product_id'], (float)$it['quantity'], 'sale', $purchase_id, 'purchase_delete', $_SESSION['user_id']);
    }

    // Delete purchase items and purchase record
    $delItems = $conn->prepare('DELETE FROM purchase_items WHERE purchase_id = ?');
    $delItems->bind_param('i', $purchase_id);
    $delItems->execute();

    $delPurchase = $conn->prepare('DELETE FROM purchases WHERE id = ?');
    $delPurchase->bind_param('i', $purchase_id);
    $delPurchase->execute();

    $conn->commit();
    $_SESSION['success_message'] = 'Purchase deleted and stock adjusted';
    header('Location: list.php');
    exit;

} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['error_message'] = 'Error deleting purchase: ' . $e->getMessage();
    header('Location: view.php?id=' . $purchase_id);
    exit;
}
