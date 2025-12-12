<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';

requireLogin();

$conn = getDBConnection();
$pageTitle = 'Stock Summary Report';

// Filters
$category_id = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;
$stock_status = isset($_GET['stock_status']) ? clean($_GET['stock_status']) : 'all';
$search = isset($_GET['search']) ? clean($_GET['search']) : '';

// Build query
$where = "p.is_active = 1";
if ($category_id > 0) {
    $where .= " AND p.category_id = $category_id";
}
if (!empty($search)) {
    $where .= " AND (p.name LIKE '%$search%' OR p.sku LIKE '%$search%')";
}

// Stock status filter
switch ($stock_status) {
    case 'in_stock':
        $where .= " AND p.stock_quantity > p.reorder_level";
        break;
    case 'low_stock':
        $where .= " AND p.stock_quantity <= p.reorder_level AND p.stock_quantity > 0";
        break;
    case 'out_of_stock':
        $where .= " AND p.stock_quantity = 0";
        break;
}

// Get stock data
$query = "SELECT 
          p.*,
          c.name as category_name,
          p.stock_quantity * p.purchase_price as stock_value,
          CASE 
            WHEN p.stock_quantity = 0 THEN 'Out of Stock'
            WHEN p.stock_quantity <= p.reorder_level THEN 'Low Stock'
            ELSE 'In Stock'
          END as stock_status
          FROM products p
          LEFT JOIN categories c ON p.category_id = c.id
          WHERE $where
          ORDER BY 
            CASE 
              WHEN p.stock_quantity = 0 THEN 1
              WHEN p.stock_quantity <= p.reorder_level THEN 2
              ELSE 3
            END,
            p.name ASC";

$products = $conn->query($query);

// Calculate totals
$totalsQuery = "SELECT 
                COUNT(*) as total_products,
                SUM(stock_quantity) as total_units,
                SUM(stock_quantity * purchase_price) as total_value
                FROM products p
                WHERE $where";
$totals = $conn->query($totalsQuery)->fetch_assoc();

// Get categories for filter
$categoriesQuery = "SELECT * FROM categories ORDER BY name ASC";
$categories = $conn->query($categoriesQuery);

// Get stock status summary
$statusSummary = "SELECT 
                  SUM(CASE WHEN stock_quantity = 0 THEN 1 ELSE 0 END) as out_of_stock,
                  SUM(CASE WHEN stock_quantity <= reorder_level AND stock_quantity > 0 THEN 1 ELSE 0 END) as low_stock,
                  SUM(CASE WHEN stock_quantity > reorder_level THEN 1 ELSE 0 END) as in_stock
                  FROM products
                  WHERE is_active = 1";
$statusData = $conn->query($statusSummary)->fetch_assoc();

// Get category-wise breakdown
$categoryBreakdown = "SELECT 
                      c.name as category,
                      COUNT(p.id) as products,
                      SUM(p.stock_quantity) as units,
                      SUM(p.stock_quantity * p.purchase_price) as value
                      FROM products p
                      LEFT JOIN categories c ON p.category_id = c.id
                      WHERE p.is_active = 1
                      GROUP BY p.category_id
                      ORDER BY value DESC";
$categoryData = $conn->query($categoryBreakdown);

include '../../includes/header.php';
?>

<!-- Page Header -->
<div class="mb-6">
    <h1 class="text-3xl font-bold text-gray-800">Stock Summary Report</h1>
    <p class="text-gray-600">Current inventory status and stock levels</p>
</div>

<!-- Filters -->
<div class="bg-white rounded-lg shadow p-6 mb-6">
    <form method="GET" action="" class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">Search Product</label>
            <input type="text" 
                   name="search" 
                   value="<?php echo $search; ?>"
                   placeholder="Product name or SKU..." 
                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">Category</label>
            <select name="category_id" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="0">All Categories</option>
                <?php 
                $categories->data_seek(0);
                while ($cat = $categories->fetch_assoc()): 
                ?>
                    <option value="<?php echo $cat['id']; ?>" <?php echo $category_id == $cat['id'] ? 'selected' : ''; ?>>
                        <?php echo $cat['name']; ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">Stock Status</label>
            <select name="stock_status" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="all" <?php echo $stock_status === 'all' ? 'selected' : ''; ?>>All Status</option>
                <option value="in_stock" <?php echo $stock_status === 'in_stock' ? 'selected' : ''; ?>>In Stock</option>
                <option value="low_stock" <?php echo $stock_status === 'low_stock' ? 'selected' : ''; ?>>Low Stock</option>
                <option value="out_of_stock" <?php echo $stock_status === 'out_of_stock' ? 'selected' : ''; ?>>Out of Stock</option>
            </select>
        </div>
        <div class="flex items-end gap-2">
            <button type="submit" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-semibold transition">
                Filter
            </button>
        </div>
    </form>
</div>

<!-- Summary Cards -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
    <div class="bg-white rounded-lg shadow p-6">
        <p class="text-gray-500 text-sm">Total Products</p>
        <h3 class="text-3xl font-bold text-gray-800"><?php echo number_format($totals['total_products']); ?></h3>
        <p class="text-xs text-gray-500 mt-1">Active SKUs</p>
    </div>
    
    <div class="bg-white rounded-lg shadow p-6">
        <p class="text-gray-500 text-sm">Total Units</p>
        <h3 class="text-3xl font-bold text-blue-600"><?php echo number_format($totals['total_units']); ?></h3>
        <p class="text-xs text-gray-500 mt-1">In stock</p>
    </div>
    
    <div class="bg-white rounded-lg shadow p-6">
        <p class="text-gray-500 text-sm">Stock Value</p>
        <h3 class="text-2xl font-bold text-green-600"><?php echo formatCurrency($totals['total_value']); ?></h3>
        <p class="text-xs text-gray-500 mt-1">At purchase price</p>
    </div>
    
    <div class="bg-white rounded-lg shadow p-6">
        <p class="text-gray-500 text-sm">Low/Out Stock</p>
        <h3 class="text-3xl font-bold text-red-600">
            <?php echo ($statusData['low_stock'] + $statusData['out_of_stock']); ?>
        </h3>
        <p class="text-xs text-gray-500 mt-1">Needs attention</p>
    </div>
</div>

<!-- Stock Status Overview -->
<div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
    <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-bold text-gray-800 mb-4">Stock Status Overview</h3>
        <div class="space-y-3">
            <div class="flex justify-between items-center p-3 bg-green-50 rounded">
                <div class="flex items-center">
                    <div class="w-3 h-3 bg-green-500 rounded-full mr-3"></div>
                    <span class="text-gray-700">In Stock (Above Reorder)</span>
                </div>
                <span class="font-bold text-green-600"><?php echo $statusData['in_stock']; ?></span>
            </div>
            <div class="flex justify-between items-center p-3 bg-yellow-50 rounded">
                <div class="flex items-center">
                    <div class="w-3 h-3 bg-yellow-500 rounded-full mr-3"></div>
                    <span class="text-gray-700">Low Stock (At/Below Reorder)</span>
                </div>
                <span class="font-bold text-yellow-600"><?php echo $statusData['low_stock']; ?></span>
            </div>
            <div class="flex justify-between items-center p-3 bg-red-50 rounded">
                <div class="flex items-center">
                    <div class="w-3 h-3 bg-red-500 rounded-full mr-3"></div>
                    <span class="text-gray-700">Out of Stock</span>
                </div>
                <span class="font-bold text-red-600"><?php echo $statusData['out_of_stock']; ?></span>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-bold text-gray-800 mb-4">Category-wise Stock Value</h3>
        <div class="space-y-2">
            <?php 
            $categoryData->data_seek(0);
            while ($catData = $categoryData->fetch_assoc()): 
                $percentage = $totals['total_value'] > 0 ? 
                             ($catData['value'] / $totals['total_value']) * 100 : 0;
            ?>
                <div class="flex items-center justify-between">
                    <div class="flex items-center flex-1">
                        <span class="font-medium text-gray-700 w-32"><?php echo $catData['category'] ?: 'Uncategorized'; ?></span>
                        <div class="flex-1 mx-4">
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div class="bg-blue-500 h-2 rounded-full" style="width: <?php echo $percentage; ?>%"></div>
                            </div>
                        </div>
                    </div>
                    <div class="text-right ml-4">
                        <p class="text-sm font-semibold text-gray-800"><?php echo formatCurrency($catData['value']); ?></p>
                        <p class="text-xs text-gray-500"><?php echo $catData['products']; ?> items</p>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    </div>
</div>

<!-- Products Table -->
<div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="p-6 border-b">
        <h3 class="text-lg font-bold text-gray-800">Stock Details</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">SKU</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Product</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Category</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Current Stock</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Reorder Level</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Unit Price</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Stock Value</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php if ($products->num_rows > 0): ?>
                    <?php while ($product = $products->fetch_assoc()): ?>
                        <?php
                        $statusColors = [
                            'Out of Stock' => 'bg-red-100 text-red-700 border-red-300',
                            'Low Stock' => 'bg-yellow-100 text-yellow-700 border-yellow-300',
                            'In Stock' => 'bg-green-100 text-green-700 border-green-300'
                        ];
                        $statusClass = $statusColors[$product['stock_status']];
                        ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 text-sm font-mono text-gray-900"><?php echo $product['sku']; ?></td>
                            <td class="px-6 py-4">
                                <div class="text-sm font-medium text-gray-900"><?php echo $product['name']; ?></div>
                                <div class="text-xs text-gray-500"><?php echo $product['unit']; ?></div>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-600">
                                <?php echo $product['category_name'] ?: 'N/A'; ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-right font-bold">
                                <?php
                                if (in_array($product['unit'], ['ml','kg','ltr'])) {
                                    echo number_format((float)$product['stock_quantity'], 2);
                                } else {
                                    echo number_format((int)$product['stock_quantity']);
                                }
                                ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-right text-gray-600">
                                <?php echo $product['reorder_level']; ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-right">
                                <?php echo formatCurrency($product['purchase_price']); ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-right font-bold text-blue-600">
                                <?php echo formatCurrency($product['stock_value']); ?>
                            </td>
                            <td class="px-6 py-4">
                                <span class="<?php echo $statusClass; ?> border px-2 py-1 rounded text-xs font-semibold">
                                    <?php echo $product['stock_status']; ?>
                                </span>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    
                    <!-- Totals Row -->
                    <tr class="bg-gray-100 font-bold">
                        <td colspan="3" class="px-6 py-4 text-sm">TOTAL</td>
                        <td class="px-6 py-4 text-sm text-right"><?php echo number_format((float)$totals['total_units'], 2); ?></td>
                        <td colspan="2"></td>
                        <td class="px-6 py-4 text-sm text-right text-blue-600"><?php echo formatCurrency($totals['total_value']); ?></td>
                        <td></td>
                    </tr>
                <?php else: ?>
                    <tr>
                        <td colspan="8" class="px-6 py-8 text-center text-gray-500">
                            No products found matching your criteria
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Action Recommendations -->
<?php if ($statusData['low_stock'] > 0 || $statusData['out_of_stock'] > 0): ?>
<div class="mt-6 bg-yellow-50 border border-yellow-200 rounded-lg p-6">
    <h3 class="text-lg font-bold text-yellow-900 mb-3">⚠️ Action Required</h3>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <?php if ($statusData['out_of_stock'] > 0): ?>
        <div class="bg-red-50 border border-red-200 rounded p-4">
            <p class="font-semibold text-red-800 mb-2">
                <?php echo $statusData['out_of_stock']; ?> Products Out of Stock
            </p>
            <p class="text-sm text-red-700">
                Immediate reordering required to avoid lost sales. Review out of stock items and place purchase orders.
            </p>
        </div>
        <?php endif; ?>
        
        <?php if ($statusData['low_stock'] > 0): ?>
        <div class="bg-yellow-50 border border-yellow-200 rounded p-4">
            <p class="font-semibold text-yellow-800 mb-2">
                <?php echo $statusData['low_stock']; ?> Products Below Reorder Level
            </p>
            <p class="text-sm text-yellow-700">
                Stock levels are at or below reorder points. Plan purchase orders to maintain optimal inventory.
            </p>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>


<?php
include '../../includes/footer.php';
?>