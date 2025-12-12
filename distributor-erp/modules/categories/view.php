<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';

requireLogin();

$conn = getDBConnection();
$pageTitle = 'View Category';

// Check permission
requirePermission($conn, $_SESSION['role'], 'categories', 'view');

$category_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$category_id) {
    $_SESSION['error_message'] = 'Invalid category ID';
    header('Location: list.php');
    exit;
}

// Fetch category with product count
$stmt = $conn->prepare("SELECT c.*, COUNT(p.id) as product_count FROM categories c LEFT JOIN products p ON c.id = p.category_id WHERE c.id = ? GROUP BY c.id");
$stmt->bind_param('i', $category_id);
$stmt->execute();
$result = $stmt->get_result();
$category = $result->fetch_assoc();

if (!$category) {
    $_SESSION['error_message'] = 'Category not found';
    header('Location: list.php');
    exit;
}

// Get products in this category
$products_stmt = $conn->prepare("SELECT id, name, variety, category_id, unit_price, stock_quantity, is_active FROM products WHERE category_id = ? ORDER BY name ASC");
$products_stmt->bind_param('i', $category_id);
$products_stmt->execute();
$products_result = $products_stmt->get_result();
$products = $products_result->fetch_all(MYSQLI_ASSOC);

include '../../includes/header.php';
?>

<div class="mb-6">
    <h1 class="text-3xl font-bold text-gray-800 mb-2"><?php echo htmlspecialchars($category['name']); ?></h1>
    <a href="list.php" class="text-blue-600 hover:text-blue-800">← Back to Categories</a>
</div>

<!-- Category Info -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
    <div class="bg-white shadow-md rounded-lg p-6">
        <p class="text-gray-500 text-sm">Category Name</p>
        <h3 class="text-2xl font-bold text-gray-800"><?php echo htmlspecialchars($category['name']); ?></h3>
    </div>
    
    <div class="bg-white shadow-md rounded-lg p-6">
        <p class="text-gray-500 text-sm">Products in Category</p>
        <h3 class="text-2xl font-bold text-blue-600"><?php echo $category['product_count']; ?></h3>
    </div>
    
    <div class="bg-white shadow-md rounded-lg p-6 space-y-2">
        <p class="text-gray-500 text-sm">Actions</p>
        <div class="flex gap-2">
            <a href="edit.php?id=<?php echo $category['id']; ?>" class="text-green-600 hover:text-green-800 font-semibold">Edit</a>
            <?php if ($category['product_count'] == 0): ?>
            <a href="delete.php?id=<?php echo $category['id']; ?>" class="text-red-600 hover:text-red-800 font-semibold" onclick="return confirm('Delete this category?');">Delete</a>
            <?php else: ?>
            <span class="text-gray-400 text-sm">(Cannot delete - has products)</span>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Description -->
<?php if (!empty($category['description'])): ?>
<div class="bg-white shadow-md rounded-lg p-6 mb-6">
    <h3 class="text-lg font-semibold text-gray-800 mb-2">Description</h3>
    <p class="text-gray-600"><?php echo nl2br(htmlspecialchars($category['description'])); ?></p>
</div>
<?php endif; ?>

<!-- Products in Category -->
<div class="bg-white shadow-md rounded-lg overflow-hidden">
    <div class="px-6 py-4 border-b bg-gray-50">
        <h3 class="text-lg font-semibold text-gray-800">Products in this Category</h3>
    </div>
    
    <?php if (empty($products)): ?>
    <div class="px-6 py-6 text-center text-gray-500">
        No products in this category yet
    </div>
    <?php else: ?>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-100 border-b">
                <tr>
                    <th class="px-6 py-3 text-left text-sm font-semibold text-gray-700">Product Name</th>
                    <th class="px-6 py-3 text-left text-sm font-semibold text-gray-700">Variety</th>
                    <th class="px-6 py-3 text-center text-sm font-semibold text-gray-700">Unit Price</th>
                    <th class="px-6 py-3 text-center text-sm font-semibold text-gray-700">Stock</th>
                    <th class="px-6 py-3 text-center text-sm font-semibold text-gray-700">Status</th>
                    <th class="px-6 py-3 text-center text-sm font-semibold text-gray-700">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($products as $product): ?>
                <tr class="border-b hover:bg-gray-50">
                    <td class="px-6 py-4 text-sm font-medium text-gray-800"><?php echo htmlspecialchars($product['name']); ?></td>
                    <td class="px-6 py-4 text-sm text-gray-600"><?php echo htmlspecialchars($product['variety']); ?></td>
                    <td class="px-6 py-4 text-sm text-center font-semibold"><?php echo formatCurrency($product['unit_price']); ?></td>
                    <td class="px-6 py-4 text-sm text-center">
                        <span class="<?php echo $product['stock_quantity'] > 0 ? 'text-green-600' : 'text-red-600'; ?> font-semibold">
                            <?php echo number_format($product['stock_quantity'], 3); ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 text-sm text-center">
                        <span class="px-2 py-1 rounded text-xs font-semibold <?php echo $product['is_active'] ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-700'; ?>">
                            <?php echo $product['is_active'] ? 'Active' : 'Inactive'; ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 text-sm text-center">
                        <a href="../products/list.php?search=<?php echo urlencode($product['name']); ?>" class="text-blue-600 hover:text-blue-800 font-semibold">View</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

</main>
</div>
<?php include '../../includes/footer.php'; ?>
