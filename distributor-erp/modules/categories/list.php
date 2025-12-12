<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';

requireLogin();

// Only admins can manage categories
if (!hasRole('admin')) {
    $_SESSION['error_message'] = 'You do not have permission to access this page';
    header('Location: ../../index.php');
    exit;
}

$conn = getDBConnection();
$pageTitle = 'Categories';

$success = '';
$errors = [];

// Handle delete
if (isset($_GET['delete'])) {
    $deleteId = (int)$_GET['delete'];
    
    // Check if category is used by any products
    $checkQuery = "SELECT COUNT(*) as count FROM products WHERE category_id = $deleteId";
    $checkResult = $conn->query($checkQuery);
    $productCount = $checkResult->fetch_assoc()['count'];
    
    if ($productCount > 0) {
        $_SESSION['error_message'] = "Cannot delete category. It is used by $productCount product(s)";
    } else {
        $deleteQuery = "DELETE FROM categories WHERE id = $deleteId";
        if ($conn->query($deleteQuery)) {
            $_SESSION['success_message'] = 'Category deleted successfully';
        }
    }
    header('Location: list.php');
    exit;
}

// Handle add/edit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $name = clean($_POST['name']);
    $description = clean($_POST['description']);
    
    if (empty($name)) {
        $errors[] = 'Category name is required';
    }
    
    // Check duplicate
    $checkQuery = "SELECT id FROM categories WHERE name = '$name' AND id != $id";
    $checkResult = $conn->query($checkQuery);
    if ($checkResult->num_rows > 0) {
        $errors[] = 'Category name already exists';
    }
    
    if (empty($errors)) {
        if ($id > 0) {
            // Update
            $stmt = $conn->prepare("UPDATE categories SET name = ?, description = ? WHERE id = ?");
            $stmt->bind_param("ssi", $name, $description, $id);
            $message = 'Category updated successfully';
        } else {
            // Insert
            $stmt = $conn->prepare("INSERT INTO categories (name, description) VALUES (?, ?)");
            $stmt->bind_param("ss", $name, $description);
            $message = 'Category added successfully';
        }
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = $message;
            header('Location: list.php');
            exit;
        } else {
            $errors[] = 'Error saving category';
        }
    }
}

// Get category to edit
$editCategory = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $editQuery = "SELECT * FROM categories WHERE id = $editId";
    $editResult = $conn->query($editQuery);
    if ($editResult->num_rows > 0) {
        $editCategory = $editResult->fetch_assoc();
    }
}

// Get all categories
$categoriesQuery = "SELECT c.*, COUNT(p.id) as product_count 
                    FROM categories c 
                    LEFT JOIN products p ON c.id = p.category_id 
                    GROUP BY c.id 
                    ORDER BY c.name ASC";
$categories = $conn->query($categoriesQuery);

include '../../includes/header.php';
?>

<!-- Page Header -->
<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-3xl font-bold text-gray-800">Category Management</h1>
        <p class="text-gray-600">Manage product categories</p>
    </div>
    <a href="add.php" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded font-semibold transition">
        + Add Category
    </a>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Add/Edit Form -->
    <div class="lg:col-span-1">
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-xl font-bold mb-4"><?php echo $editCategory ? 'Edit Category' : 'Add New Category'; ?></h2>
            
            <?php if (!empty($errors)): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                    <ul class="list-disc list-inside text-sm">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo $error; ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <?php if ($editCategory): ?>
                    <input type="hidden" name="id" value="<?php echo $editCategory['id']; ?>">
                <?php endif; ?>
                
                <div class="mb-4">
                    <label for="name" class="block text-gray-700 font-semibold mb-2">Category Name <span class="text-red-500">*</span></label>
                    <input type="text" 
                           id="name" 
                           name="name" 
                           value="<?php echo $editCategory ? $editCategory['name'] : ''; ?>"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                           placeholder="e.g., Electronics"
                           required>
                </div>
                
                <div class="mb-4">
                    <label for="description" class="block text-gray-700 font-semibold mb-2">Description</label>
                    <textarea id="description" 
                              name="description" 
                              rows="3"
                              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                              placeholder="Enter description..."><?php echo $editCategory ? $editCategory['description'] : ''; ?></textarea>
                </div>
                
                <div class="flex gap-2">
                    <?php if ($editCategory): ?>
                        <a href="list.php" class="flex-1 text-center px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
                            Cancel
                        </a>
                    <?php endif; ?>
                    <button type="submit" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-semibold">
                        <?php echo $editCategory ? 'Update' : 'Add'; ?> Category
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Categories List -->
    <div class="lg:col-span-2">
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50 border-b">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Description</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Products</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php if ($categories->num_rows > 0): ?>
                            <?php while ($category = $categories->fetch_assoc()): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4">
                                        <span class="font-semibold text-gray-900"><?php echo $category['name']; ?></span>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-600">
                                        <?php echo $category['description'] ?: 'N/A'; ?>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-900">
                                        <?php echo $category['product_count']; ?> products
                                    </td>
                                    <td class="px-6 py-4 text-sm space-x-3">
                                        <a href="view.php?id=<?php echo $category['id']; ?>" class="text-blue-600 hover:text-blue-900">View</a>
                                        <a href="edit.php?id=<?php echo $category['id']; ?>" class="text-green-600 hover:text-green-900">Edit</a>
                                        <?php if ($category['product_count'] == 0): ?>
                                        <a href="delete.php?id=<?php echo $category['id']; ?>" 
                                           onclick="return confirm('Delete this category?')"
                                           class="text-red-600 hover:text-red-900">Delete</a>
                                        <?php else: ?>
                                        <span class="text-gray-400 text-xs">(Cannot delete)</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="px-6 py-8 text-center text-gray-500">
                                    No categories found. Add your first category using the form.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php
include '../../includes/footer.php';
?>