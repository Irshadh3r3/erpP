<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';

requireLogin();

$conn = getDBConnection();
$pageTitle = 'Add Product';

$errors = [];
$success = '';

// Get categories for dropdown
$categoriesQuery = "SELECT * FROM categories ORDER BY name ASC";
$categories = $conn->query($categoriesQuery);

// Auto-generate SKU
$autoSku = '';
if (empty($_POST['sku']) || !isset($_POST['sku'])) {
    $prefix = 'PRD-';
    $query = "SELECT sku FROM products WHERE sku LIKE '$prefix%' ORDER BY id DESC LIMIT 1";
    $result = $conn->query($query);
    
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $lastNumber = intval(substr($row['sku'], -5));
        $newNumber = $lastNumber + 1;
    } else {
        $newNumber = 1;
    }
    
    $autoSku = $prefix . str_pad($newNumber, 5, '0', STR_PAD_LEFT);
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sku = !empty(clean($_POST['sku'])) ? clean($_POST['sku']) : $autoSku;
    $name = clean($_POST['name']);
    $category_id = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
    $variety = isset($_POST['variety']) ? clean($_POST['variety']) : null;
    $description = clean($_POST['description']);
    $unit = clean($_POST['unit']);
    $purchase_price = (float)$_POST['purchase_price'];
    $selling_price = (float)$_POST['selling_price'];
    // Some units (ml, kg, ltr) can use fractional quantities, allow float for those
    $stock_quantity = in_array($unit, ['ml','kg','ltr']) ? (float)$_POST['stock_quantity'] : (int)$_POST['stock_quantity'];
    $reorder_level = (int)$_POST['reorder_level'];
    $barcode = clean($_POST['barcode']);
    
    // Validation
    if (empty($sku)) {
        $errors[] = 'SKU could not be generated';
    }
    if (empty($name)) {
        $errors[] = 'Product name is required';
    }
    if ($purchase_price <= 0) {
        $errors[] = 'Purchase price must be greater than 0';
    }
    if ($selling_price <= 0) {
        $errors[] = 'Selling price must be greater than 0';
    }
    if ($stock_quantity < 0) {
        $errors[] = 'Stock quantity cannot be negative';
    }
    
    // Check if SKU already exists
    $checkQuery = "SELECT id FROM products WHERE sku = '$sku'";
    $checkResult = $conn->query($checkQuery);
    if ($checkResult->num_rows > 0) {
        $errors[] = 'SKU already exists';
    }
    
    // If no errors, insert product
    if (empty($errors)) {
        // Include variety and allow fractional stock quantity
        // If category_id is NULL we must insert SQL NULL instead of binding 0 (which would violate FK)
        if ($category_id === null) {
            $stmt = $conn->prepare("INSERT INTO products (sku, name, category_id, variety, description, unit, purchase_price, selling_price, stock_quantity, reorder_level, barcode) VALUES (?, NULL, ?, ?, ?, ?, ?, ?, ?, ?)");
            // sku, name, variety, description, unit, purchase_price, selling_price, stock_quantity, reorder_level, barcode
            $stmt->bind_param("sssssdddis", $sku, $name, $variety, $description, $unit, $purchase_price, $selling_price, $stock_quantity, $reorder_level, $barcode);
        } else {
            $stmt = $conn->prepare("INSERT INTO products (sku, name, category_id, variety, description, unit, purchase_price, selling_price, stock_quantity, reorder_level, barcode) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssisssdddis", $sku, $name, $category_id, $variety, $description, $unit, $purchase_price, $selling_price, $stock_quantity, $reorder_level, $barcode);
        }
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = 'Product added successfully!';
            header('Location: list.php');
            exit;
        } else {
            $errors[] = 'Error adding product: ' . $conn->error;
        }
    }
}

include '../../includes/header.php';
?>

<!-- Page Header -->
<div class="mb-6">
    <h1 class="text-3xl font-bold text-gray-800">Add New Product</h1>
    <p class="text-gray-600">Fill in the details below to add a new product</p>
</div>

<!-- Error Messages -->
<?php if (!empty($errors)): ?>
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
        <ul class="list-disc list-inside">
            <?php foreach ($errors as $error): ?>
                <li><?php echo $error; ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<!-- Add Product Form -->
<form method="POST" action="" class="bg-white rounded-lg shadow p-6">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- SKU -->
        <div>
            <label for="sku" class="block text-gray-700 font-semibold mb-2">SKU</label>
            <input type="text" 
                   id="sku" 
                   name="sku" 
                   value="<?php echo isset($_POST['sku']) && !empty($_POST['sku']) ? $_POST['sku'] : $autoSku; ?>"
                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                   placeholder="Auto-generated or enter custom SKU">
            <p class="text-xs text-gray-500 mt-1">Leave blank for auto-generation</p>
        </div>

        <!-- Product Name -->
        <div>
            <label for="name" class="block text-gray-700 font-semibold mb-2">Product Name <span class="text-red-500">*</span></label>
            <input type="text" 
                   id="name" 
                   name="name" 
                   value="<?php echo isset($_POST['name']) ? $_POST['name'] : ''; ?>"
                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                   placeholder="e.g., Samsung Galaxy S21"
                   required>
        </div>

            <!-- Variety -->
            <div>
                <label for="variety" class="block text-gray-700 font-semibold mb-2">Variety</label>
                <input type="text"
                       id="variety"
                       name="variety"
                       value="<?php echo isset($_POST['variety']) ? $_POST['variety'] : ''; ?>"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                       placeholder="e.g., Red, 500ml, 250mg">
            </div>

        <!-- Category -->
        <div>
            <label for="category_id" class="block text-gray-700 font-semibold mb-2">Category</label>
            <div class="flex gap-2">
                <select id="category_id" 
                        name="category_id" 
                        class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">Select Category</option>
                    <?php 
                    $categories->data_seek(0);
                    while ($cat = $categories->fetch_assoc()): 
                    ?>
                        <option value="<?php echo $cat['id']; ?>" <?php echo (isset($_POST['category_id']) && $_POST['category_id'] == $cat['id']) ? 'selected' : ''; ?>>
                            <?php echo $cat['name']; ?>
                        </option>
                    <?php endwhile; ?>
                </select>
                <button type="button" 
                        onclick="showAddCategory()"
                        class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg font-semibold transition">
                    +
                </button>
            </div>
        </div>

        <!-- Unit -->
        <div>
            <label for="unit" class="block text-gray-700 font-semibold mb-2">Unit <span class="text-red-500">*</span></label>
            <select id="unit" 
                    name="unit" 
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                    required>
                <option value="pcs" <?php echo (isset($_POST['unit']) && $_POST['unit'] == 'pcs') ? 'selected' : 'selected'; ?>>Pieces (pcs)</option>
                <option value="kg" <?php echo (isset($_POST['unit']) && $_POST['unit'] == 'kg') ? 'selected' : ''; ?>>Kilograms (kg)</option>
                <option value="ml" <?php echo (isset($_POST['unit']) && $_POST['unit'] == 'ml') ? 'selected' : ''; ?>>Milliliters (ml)</option>
                <option value="ltr" <?php echo (isset($_POST['unit']) && $_POST['unit'] == 'ltr') ? 'selected' : ''; ?>>Liters (ltr)</option>
                <option value="box" <?php echo (isset($_POST['unit']) && $_POST['unit'] == 'box') ? 'selected' : ''; ?>>Box</option>
                <option value="pack" <?php echo (isset($_POST['unit']) && $_POST['unit'] == 'pack') ? 'selected' : ''; ?>>Pack</option>
                <option value="dozen" <?php echo (isset($_POST['unit']) && $_POST['unit'] == 'dozen') ? 'selected' : ''; ?>>Dozen</option>
            </select>
        </div>

        <!-- Purchase Price -->
        <div>
            <label for="purchase_price" class="block text-gray-700 font-semibold mb-2">Purchase Price <span class="text-red-500">*</span></label>
            <input type="number" 
                   id="purchase_price" 
                   name="purchase_price" 
                   value="<?php echo isset($_POST['purchase_price']) ? $_POST['purchase_price'] : ''; ?>"
                   step="0.01" 
                   min="0"
                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                   placeholder="0.00"
                   required>
        </div>

        <!-- Selling Price -->
        <div>
            <label for="selling_price" class="block text-gray-700 font-semibold mb-2">Selling Price <span class="text-red-500">*</span></label>
            <input type="number" 
                   id="selling_price" 
                   name="selling_price" 
                   value="<?php echo isset($_POST['selling_price']) ? $_POST['selling_price'] : ''; ?>"
                   step="0.01" 
                   min="0"
                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                   placeholder="0.00"
                   required>
        </div>

        <!-- Stock Quantity -->
        <div>
            <label for="stock_quantity" class="block text-gray-700 font-semibold mb-2">Opening Stock Quantity</label>
                 <input type="number" 
                   id="stock_quantity" 
                   name="stock_quantity" 
                   value="<?php echo isset($_POST['stock_quantity']) ? $_POST['stock_quantity'] : '0'; ?>"
                     min="0"
                     step="<?php echo (isset($_POST['unit']) && in_array($_POST['unit'], ['ml','kg','ltr'])) ? '0.01' : '1'; ?>"
                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                   placeholder="0">
        </div>

        <!-- Reorder Level -->
        <div>
            <label for="reorder_level" class="block text-gray-700 font-semibold mb-2">Reorder Level</label>
            <input type="number" 
                   id="reorder_level" 
                   name="reorder_level" 
                   value="<?php echo isset($_POST['reorder_level']) ? $_POST['reorder_level'] : '10'; ?>"
                   min="0"
                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                   placeholder="10">
            <p class="text-xs text-gray-500 mt-1">Alert when stock falls below this level</p>
        </div>

        <!-- Barcode -->
        <div class="md:col-span-2">
            <label for="barcode" class="block text-gray-700 font-semibold mb-2">Barcode</label>
            <input type="text" 
                   id="barcode" 
                   name="barcode" 
                   value="<?php echo isset($_POST['barcode']) ? $_POST['barcode'] : ''; ?>"
                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                   placeholder="e.g., 1234567890123">
        </div>

        <!-- Description -->
        <div class="md:col-span-2">
            <label for="description" class="block text-gray-700 font-semibold mb-2">Description</label>
            <textarea id="description" 
                      name="description" 
                      rows="4"
                      class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                      placeholder="Enter product description..."><?php echo isset($_POST['description']) ? $_POST['description'] : ''; ?></textarea>
        </div>
    </div>

    <!-- Form Actions -->
    <div class="flex items-center justify-end gap-4 mt-6 pt-6 border-t">
        <a href="list.php" class="px-6 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 font-semibold transition">
            Cancel
        </a>
        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg font-semibold transition">
            Add Product
        </button>
    </div>
</form>

<!-- Add Category Modal -->
<div id="categoryModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white rounded-lg p-6 w-full max-w-md">
        <h3 class="text-xl font-bold mb-4">Add New Category</h3>
        <form id="categoryForm" onsubmit="return addCategory(event)">
            <div class="mb-4">
                <label class="block text-gray-700 font-semibold mb-2">Category Name</label>
                <input type="text" 
                       id="new_category_name" 
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                       placeholder="Enter category name"
                       required>
            </div>
            <div class="mb-4">
                <label class="block text-gray-700 font-semibold mb-2">Description</label>
                <textarea id="new_category_desc" 
                          rows="3"
                          class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                          placeholder="Enter category description"></textarea>
            </div>
            <div class="flex gap-4">
                <button type="button" 
                        onclick="hideAddCategory()"
                        class="flex-1 px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
                    Cancel
                </button>
                <button type="submit" 
                        class="flex-1 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-semibold">
                    Add Category
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function showAddCategory() {
    document.getElementById('categoryModal').classList.remove('hidden');
}

function hideAddCategory() {
    document.getElementById('categoryModal').classList.add('hidden');
    document.getElementById('new_category_name').value = '';
    document.getElementById('new_category_desc').value = '';
}

function addCategory(event) {
    event.preventDefault();
    
    const name = document.getElementById('new_category_name').value;
    const description = document.getElementById('new_category_desc').value;
    
    // Send AJAX request to add category
    fetch('../../modules/categories/add_ajax.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'name=' + encodeURIComponent(name) + '&description=' + encodeURIComponent(description)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Add new option to select
            const select = document.getElementById('category_id');
            const option = new Option(data.name, data.id, true, true);
            select.add(option);
            
            hideAddCategory();
            alert('Category added successfully!');
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        alert('Error adding category');
        console.error(error);
    });
    
    return false;
}
</script>

<script>
// Change stock step dynamically based on unit selection
document.addEventListener('DOMContentLoaded', function () {
    const unitSelect = document.getElementById('unit');
    const stockInput = document.getElementById('stock_quantity');

    function updateStockStep() {
        const u = unitSelect.value;
        if (['ml', 'kg', 'ltr'].includes(u)) {
            stockInput.step = '0.01';
        } else {
            stockInput.step = '1';
        }
    }

    unitSelect.addEventListener('change', updateStockStep);
    updateStockStep();
});
</script>

<?php
include '../../includes/footer.php';
?>