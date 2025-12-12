<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';

requireLogin();

$conn = getDBConnection();
$pageTitle = 'Add Category';

// Check permission
requirePermission($conn, $_SESSION['role'], 'categories', 'add');

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = clean($_POST['name'] ?? '');
    $description = clean($_POST['description'] ?? '');
    
    // Validation
    if (empty($name)) {
        $errors[] = 'Category name is required';
    }
    
    // Check duplicate
    if (!$errors) {
        $stmt = $conn->prepare("SELECT id FROM categories WHERE name = ?");
        $stmt->bind_param("s", $name);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $errors[] = 'Category name already exists';
        }
    }
    
    if (empty($errors)) {
        $stmt = $conn->prepare("INSERT INTO categories (name, description) VALUES (?, ?)");
        $stmt->bind_param("ss", $name, $description);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = 'Category added successfully';
            header('Location: list.php');
            exit;
        } else {
            $errors[] = 'Error adding category: ' . $conn->error;
        }
    }
}

include '../../includes/header.php';
?>

<div class="mb-6">
    <h1 class="text-3xl font-bold text-gray-800 mb-2">Add New Category</h1>
    <a href="list.php" class="text-blue-600 hover:text-blue-800">← Back to Categories</a>
</div>

<?php if (!empty($errors)): ?>
<div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
    <ul class="list-disc pl-5">
        <?php foreach ($errors as $error): ?>
        <li><?php echo htmlspecialchars($error); ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<div class="bg-white shadow-md rounded-lg p-6 max-w-2xl">
    <form method="POST" class="space-y-4">
        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">Category Name *</label>
            <input type="text" name="name" required
                   placeholder="e.g., Electronics, Beverages, Clothing"
                   class="w-full px-4 py-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
        
        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">Description</label>
            <textarea name="description" rows="4"
                      placeholder="Enter category description..."
                      class="w-full px-4 py-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
        </div>
        
        <div class="flex gap-4 pt-4">
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded font-semibold transition">
                Add Category
            </button>
            <a href="list.php" class="bg-gray-400 hover:bg-gray-500 text-white px-6 py-2 rounded font-semibold transition">
                Cancel
            </a>
        </div>
    </form>
</div>

</main>
</div>
<?php include '../../includes/footer.php'; ?>
