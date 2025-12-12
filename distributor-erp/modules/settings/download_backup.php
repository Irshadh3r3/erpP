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
    
    if (file_exists($filepath)) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($filepath) . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($filepath));
        readfile($filepath);
        exit;
    } else {
        die('File not found');
    }
}
?>