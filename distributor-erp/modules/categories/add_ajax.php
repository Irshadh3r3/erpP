<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';

requireLogin();

header('Content-Type: application/json');

$conn = getDBConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = clean($_POST['name']);
    $description = clean($_POST['description']);
    
    if (empty($name)) {
        echo json_encode(['success' => false, 'message' => 'Category name is required']);
        exit;
    }
    
    // Check if category already exists
    $checkQuery = "SELECT id FROM categories WHERE name = '$name'";
    $checkResult = $conn->query($checkQuery);
    
    if ($checkResult->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Category already exists']);
        exit;
    }
    
    // Insert category
    $stmt = $conn->prepare("INSERT INTO categories (name, description) VALUES (?, ?)");
    $stmt->bind_param("ss", $name, $description);
    
    if ($stmt->execute()) {
        $categoryId = $conn->insert_id;
        echo json_encode([
            'success' => true, 
            'id' => $categoryId,
            'name' => $name,
            'message' => 'Category added successfully'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error adding category']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>