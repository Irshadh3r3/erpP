<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';

requireLogin();

if (!hasRole('admin')) {
    die('Access denied');
}

if (isset($_GET['file'])) {
    $file = basename($_GET['file']);
    $filepath = '../../backups/' . $file;
    
    if (file_exists($filepath) && unlink($filepath)) {
        header('Location: backup.php?message=success:Backup deleted successfully');
    } else {
        header('Location: backup.php?message=error:Failed to delete backup');
    }
    exit;
}
?>