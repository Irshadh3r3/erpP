<?php
session_start();
require '../../config/database.php';
require '../../includes/functions.php';

// Admin-only access
if ($_SESSION['role'] !== 'admin') {
    $_SESSION['error_message'] = 'Access denied. Admin privileges required.';
    header('Location: ../../index.php');
    exit;
}

$conn = getDBConnection();

$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$user_id) {
    $_SESSION['error_message'] = 'Invalid user ID';
    header('Location: list.php');
    exit;
}

// Prevent self-deletion
if ($user_id === $_SESSION['user_id']) {
    $_SESSION['error_message'] = 'Cannot delete your own account';
    header('Location: list.php');
    exit;
}

// Soft delete (mark as inactive)
$is_active = 0;
$stmt = $conn->prepare("UPDATE users SET is_active = ? WHERE id = ?");
$stmt->bind_param('ii', $is_active, $user_id);

if ($stmt->execute()) {
    $_SESSION['success_message'] = 'User deactivated successfully';
} else {
    $_SESSION['error_message'] = 'Error deleting user';
}

header('Location: list.php');
exit;
