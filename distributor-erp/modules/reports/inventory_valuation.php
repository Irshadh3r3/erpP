<?php
// distributor-erp/modules/reports/stock_valuation.php
require_once '../../config/database.php';
require_once '../../includes/functions.php';

requireLogin();

$conn = getDBConnection();
$pageTitle = 'Stock Valuation Report';

// Filters
$category_id = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;
$stock_status = isset($_GET['stock_status']) ? clean($_GET['stock_status']) : 'all';
$valuation_method = isset($_GET['valuation_method']) ? clean($_GET['valuation_method']) : 'purchase';
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

// Get inventory data
$query = "SELECT 
          p.*,
          c.name as category_name,
          p.stock_quantity * p.purchase_price as purchase_value,
          p.stock_quantity * p.selling_price as selling_value,
          (p.stock_quantity * p.selling_price) - (p.stock_quantity * p.purchase_price) as potential_profit,
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
                SUM(stock_quantity * purchase_price) as total_purchase_value,
                SUM(stock_quantity * selling_price) as total_selling_value,
                SUM((stock_quantity * selling_price) - (stock_quantity * purchase_price)) as total_potential_profit
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
    <h1 class="text-3xl font-bold text-gray-800">Stock Valuation Report</h1>
    <p class="text-gray-600">Complete inventory analysis and valuation</p>
</div>

<!-- Filters -->
<div class="bg-white rounded-lg shadow p-6 mb-6">
    <form method="GET" action="" class="grid grid-cols-1 md:grid-cols-5 gap-4">
        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">Search</label>
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
        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">Valuation Method</label>
            <select name="valuation_method" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="purchase" <?php echo $valuation_method === 'purchase' ? 'selected' : ''; ?>>Purchase Price</option>
                <option value="selling" <?php echo $valuation_method === 'selling' ? 'selected' : ''; ?>>Selling Price</option>
            </select>
        </div>
        <div class="flex items-end gap-2">
            <button type="submit" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-semibold transition">
                Generate
            </button>
        </div>
    </form>
</div>

<!-- Summary Cards -->
<div class="grid grid-cols-1 md:grid-cols-5 gap-6 mb-6">
    <div class="bg-white rounded-lg shadow p-6">
        <p class="text-gray-500 text-sm">Total Products</p>
        <h3 class="text-3xl font-bold text-gray-800"><?php echo number_format($totals['total_products']); ?></h3>
    </div>
    
    <div class="bg-white rounded-lg shadow p-6">
        <p class="text-gray-500 text-sm">Total Units</p>
        <h3 class="text-3xl font-bold text-blue-600"><?php echo number_format($totals['total_units']); ?></h3>
    </div>
    
    <div class="bg-white rounded-lg shadow p-6">
        <p class="text-gray-500 text-sm">Purchase Value</p>
        <h3 class="text-2xl font-bold text-purple-600"><?php echo formatCurrency($totals['total_purchase_value']); ?></h3>
    </div>
    
    <div class="bg-white rounded-lg shadow p-6">
        <p class="text-gray-500 text-sm">Selling Value</p>
        <h3 class="text-2xl font-bold text-green-600"><?php echo formatCurrency($totals['total_selling_value']); ?></h3>
    </div>
    
    <div class="bg-white rounded-lg shadow p-6">
        <p class="text-gray-500 text-sm">Potential Profit</p>
        <h3 class="text-2xl font-bold text-orange-600"><?php echo formatCurrency($totals['total_potential_profit']); ?></h3>
    </div>
</div>

<!-- Stock Status Overview -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
    <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-bold text-gray-800 mb-4">Stock Status Overview</h3>
        <div class="space-y-3">
            <div class="flex justify-between items-center p-3 bg-green-50 rounded">
                <div class="flex items-center">
                    <div class="w-3 h-3 bg-green-500 rounded-full mr-3"></div>
                    <span class="text-gray-700">In Stock</span>
                </div>
                <span class="font-bold text-green-600"><?php echo $statusData['in_stock']; ?></span>
            </div>
            <div class="flex justify-between items-center p-3 bg-yellow-50 rounded">
                <div class="flex items-center">
                    <div class="w-3 h-3 bg-yellow-500 rounded-full mr-3"></div>
                    <span class="text-gray-700">Low Stock</span>
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

    <div class="md:col-span-2 bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-bold text-gray-800 mb-4">Category-wise Breakdown</h3>
        <div class="space-y-2">
            <?php while ($catData = $categoryData->fetch_assoc()): ?>
                <div class="flex items-center justify-between p-2 hover:bg-gray-50 rounded">
                    <div class="flex items-center flex-1">
                        <span class="font-medium text-gray-700 w-40"><?php echo $catData['category'] ?: 'Uncategorized'; ?></span>
                        <div class="flex-1 mx-4">
                            <?php 
                            $percentage = $totals['total_purchase_value'] > 0 ? 
                                         ($catData['value'] / $totals['total_purchase_value']) * 100 : 0;
                            ?>
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div class="bg-blue-500 h-2 rounded-full" style="width: <?php echo $percentage; ?>%"></div>
                            </div>
                        </div>
                    </div>
                    <div class="text-right ml-4">
                        <p class="text-sm font-semibold text-gray-800"><?php echo formatCurrency($catData['value']); ?></p>
                        <p class="text-xs text-gray-500"><?php echo $catData['products']; ?> products</p>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    </div>
</div>

<!-- Detailed Inventory Table -->
<div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="p-6 border-b">
        <h3 class="text-lg font-bold text-gray-800">Detailed Inventory List</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">SKU</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Product</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Category</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Stock Qty</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Reorder Level</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Purchase Price</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Selling Price</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Stock Value</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Potential Profit</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php if ($products->num_rows > 0): ?>
                    <?php while ($product = $products->fetch_assoc()): ?>
                        <?php
                        $stockValue = $valuation_method === 'selling' ? $product['selling_value'] : $product['purchase_value'];
                        $statusColors = [
                            'Out of Stock' => 'bg-red-100 text-red-700',
                            'Low Stock' => 'bg-yellow-100 text-yellow-700',
                            'In Stock' => 'bg-green-100 text-green-700'
                        ];
                        $statusClass = $statusColors[$product['stock_status']];
                        ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 text-sm font-mono"><?php echo $product['sku']; ?></td>
                            <td class="px-6 py-4">
                                <div class="text-sm font-medium text-gray-900"><?php echo $product['name']; ?></div>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-600">
                                <?php echo $product['category_name'] ?: 'N/A'; ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-right font-semibold">
                                <?php
                                if (in_array($product['unit'], ['ml','kg','ltr'])) {
                                    echo number_format((float)$product['stock_quantity'], 2);
                                } else {
                                    echo number_format((int)$product['stock_quantity']);
                                }
                                ?> <?php echo $product['unit']; ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-right text-gray-600">
                                <?php echo $product['reorder_level']; ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-right">
                                <?php echo formatCurrency($product['purchase_price']); ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-right">
                                <?php echo formatCurrency($product['selling_price']); ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-right font-bold text-blue-600">
                                <?php echo formatCurrency($stockValue); ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-right font-semibold text-green-600">
                                <?php echo formatCurrency($product['potential_profit']); ?>
                            </td>
                            <td class="px-6 py-4">
                                <span class="<?php echo $statusClass; ?> px-2 py-1 rounded text-xs font-semibold">
                                    <?php echo $product['stock_status']; ?>
                                </span>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    
                    <!-- Totals Row -->
                    <tr class="bg-gray-100 font-bold">
                        <td colspan="3" class="px-6 py-4 text-sm">TOTAL</td>
                        <td class="px-6 py-4 text-sm text-right"><?php echo number_format((float)$totals['total_units'], 2); ?></td>
                        <td colspan="3"></td>
                        <td class="px-6 py-4 text-sm text-right text-blue-600">
                            <?php echo formatCurrency($valuation_method === 'selling' ? $totals['total_selling_value'] : $totals['total_purchase_value']); ?>
                        </td>
                        <td class="px-6 py-4 text-sm text-right text-green-600">
                            <?php echo formatCurrency($totals['total_potential_profit']); ?>
                        </td>
                        <td></td>
                    </tr>
                <?php else: ?>
                    <tr>
                        <td colspan="10" class="px-6 py-8 text-center text-gray-500">
                            No products found matching your criteria
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Key Insights -->
<div class="mt-6 bg-blue-50 border border-blue-200 rounded-lg p-6">
    <h3 class="text-lg font-bold text-blue-900 mb-3">📊 Key Insights</h3>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
        <div>
            <p class="text-blue-700 font-semibold">Profit Margin:</p>
            <p class="text-blue-900 text-xl font-bold">
                <?php 
                $profitMargin = $totals['total_selling_value'] > 0 ? 
                               (($totals['total_potential_profit'] / $totals['total_selling_value']) * 100) : 0;
                echo number_format($profitMargin, 2); 
                ?>%
            </p>
        </div>
        <div>
            <p class="text-blue-700 font-semibold">Avg Stock Value per Product:</p>
            <p class="text-blue-900 text-xl font-bold">
                <?php 
                $avgValue = $totals['total_products'] > 0 ? 
                           ($totals['total_purchase_value'] / $totals['total_products']) : 0;
                echo formatCurrency($avgValue); 
                ?>
            </p>
        </div>
        <div>
            <p class="text-blue-700 font-semibold">Products Needing Attention:</p>
            <p class="text-blue-900 text-xl font-bold">
                <?php echo ($statusData['low_stock'] + $statusData['out_of_stock']); ?>
            </p>
        </div>
    </div>
</div>


<?php
include '../../includes/footer.php';
?>