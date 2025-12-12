<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';

requireLogin();

$conn = getDBConnection();
$pageTitle = 'Low Stock Alert Report';

// Filters
$category_id = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;
$alert_level = isset($_GET['alert_level']) ? clean($_GET['alert_level']) : 'all';
$search = isset($_GET['search']) ? clean($_GET['search']) : '';

// Build base where clause - only active products at or below reorder level
$where = "p.is_active = 1 AND p.stock_quantity <= p.reorder_level";

if ($category_id > 0) {
    $where .= " AND p.category_id = $category_id";
}
if (!empty($search)) {
    $where .= " AND (p.name LIKE '%$search%' OR p.sku LIKE '%$search%')";
}

// Alert level filter
switch ($alert_level) {
    case 'critical':
        $where .= " AND p.stock_quantity = 0";
        break;
    case 'low':
        $where .= " AND p.stock_quantity > 0 AND p.stock_quantity <= p.reorder_level * 0.5";
        break;
    case 'warning':
        $where .= " AND p.stock_quantity > p.reorder_level * 0.5 AND p.stock_quantity <= p.reorder_level";
        break;
}

// Get low stock products with sales data
$query = "SELECT 
          p.*,
          c.name as category_name,
          p.reorder_level - p.stock_quantity as shortage,
          CASE 
            WHEN p.stock_quantity = 0 THEN 'Critical'
            WHEN p.stock_quantity <= p.reorder_level * 0.5 THEN 'Low'
            ELSE 'Warning'
          END as alert_level,
          COALESCE(sales_data.units_sold_30d, 0) as units_sold_30d,
          COALESCE(sales_data.avg_daily_sales, 0) as avg_daily_sales,
          CASE 
            WHEN COALESCE(sales_data.avg_daily_sales, 0) > 0 
            THEN p.stock_quantity / sales_data.avg_daily_sales
            ELSE NULL
          END as days_of_stock
          FROM products p
          LEFT JOIN categories c ON p.category_id = c.id
          LEFT JOIN (
            SELECT 
              si.product_id,
              SUM(si.quantity) as units_sold_30d,
              SUM(si.quantity) / 30.0 as avg_daily_sales
            FROM sales_items si
            JOIN sales s ON si.sale_id = s.id
            WHERE s.sale_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            GROUP BY si.product_id
          ) as sales_data ON p.id = sales_data.product_id
          WHERE $where
          ORDER BY 
            CASE 
              WHEN p.stock_quantity = 0 THEN 1
              WHEN p.stock_quantity <= p.reorder_level * 0.5 THEN 2
              ELSE 3
            END,
            p.stock_quantity ASC";

$products = $conn->query($query);

// Get categories for filter
$categoriesQuery = "SELECT * FROM categories ORDER BY name ASC";
$categories = $conn->query($categoriesQuery);

// Get summary counts
$summaryQuery = "SELECT 
                 COUNT(*) as total_alerts,
                 SUM(CASE WHEN stock_quantity = 0 THEN 1 ELSE 0 END) as critical,
                 SUM(CASE WHEN stock_quantity > 0 AND stock_quantity <= reorder_level * 0.5 THEN 1 ELSE 0 END) as low,
                 SUM(CASE WHEN stock_quantity > reorder_level * 0.5 AND stock_quantity <= reorder_level THEN 1 ELSE 0 END) as warning,
                 SUM((reorder_level - stock_quantity) * purchase_price) as estimated_order_value
                 FROM products
                 WHERE is_active = 1 AND stock_quantity <= reorder_level";
$summary = $conn->query($summaryQuery)->fetch_assoc();

// Get suppliers for quick reference
$suppliersQuery = "SELECT id, name, supplier_code, phone FROM suppliers WHERE is_active = 1 ORDER BY name ASC";
$suppliers = $conn->query($suppliersQuery);

include '../../includes/header.php';
?>

<!-- Page Header -->
<div class="mb-6">
    <h1 class="text-3xl font-bold text-gray-800">Low Stock Alert Report</h1>
    <p class="text-gray-600">Products requiring immediate attention and reordering</p>
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
            <label class="block text-sm font-semibold text-gray-700 mb-2">Alert Level</label>
            <select name="alert_level" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="all" <?php echo $alert_level === 'all' ? 'selected' : ''; ?>>All Alerts</option>
                <option value="critical" <?php echo $alert_level === 'critical' ? 'selected' : ''; ?>>Critical (Out of Stock)</option>
                <option value="low" <?php echo $alert_level === 'low' ? 'selected' : ''; ?>>Low (≤50% of Reorder)</option>
                <option value="warning" <?php echo $alert_level === 'warning' ? 'selected' : ''; ?>>Warning (At Reorder Level)</option>
            </select>
        </div>
        <div class="flex items-end gap-2">
            <button type="submit" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-semibold transition">
                Filter
            </button>
        </div>
    </form>
</div>

<!-- Alert Summary Cards -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
    <div class="bg-red-50 border-2 border-red-300 rounded-lg shadow p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-red-600 text-sm font-semibold">CRITICAL</p>
                <h3 class="text-4xl font-bold text-red-700"><?php echo $summary['critical']; ?></h3>
                <p class="text-xs text-red-600 mt-1">Out of Stock</p>
            </div>
            <div class="bg-red-200 p-3 rounded-full">
                <svg class="w-8 h-8 text-red-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                </svg>
            </div>
        </div>
    </div>
    
    <div class="bg-orange-50 border-2 border-orange-300 rounded-lg shadow p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-orange-600 text-sm font-semibold">LOW STOCK</p>
                <h3 class="text-4xl font-bold text-orange-700"><?php echo $summary['low']; ?></h3>
                <p class="text-xs text-orange-600 mt-1">≤50% of Reorder Level</p>
            </div>
            <div class="bg-orange-200 p-3 rounded-full">
                <svg class="w-8 h-8 text-orange-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 17h8m0 0V9m0 8l-8-8-4 4-6-6"></path>
                </svg>
            </div>
        </div>
    </div>
    
    <div class="bg-yellow-50 border-2 border-yellow-300 rounded-lg shadow p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-yellow-600 text-sm font-semibold">WARNING</p>
                <h3 class="text-4xl font-bold text-yellow-700"><?php echo $summary['warning']; ?></h3>
                <p class="text-xs text-yellow-600 mt-1">At Reorder Level</p>
            </div>
            <div class="bg-yellow-200 p-3 rounded-full">
                <svg class="w-8 h-8 text-yellow-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
        </div>
    </div>
    
    <div class="bg-blue-50 border-2 border-blue-300 rounded-lg shadow p-6">
        <p class="text-blue-600 text-sm font-semibold">EST. ORDER VALUE</p>
        <h3 class="text-2xl font-bold text-blue-700"><?php echo formatCurrency($summary['estimated_order_value']); ?></h3>
        <p class="text-xs text-blue-600 mt-1">To reach reorder levels</p>
    </div>
</div>

<!-- Low Stock Products Table -->
<div class="bg-white rounded-lg shadow overflow-hidden mb-6">
    <div class="p-6 border-b">
        <h3 class="text-lg font-bold text-gray-800">Products Requiring Reorder</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Alert</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">SKU</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Product</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Category</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Current</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Reorder Level</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Shortage</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Avg Daily Sales</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Days of Stock</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Order Value</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php if ($products->num_rows > 0): ?>
                    <?php while ($product = $products->fetch_assoc()): ?>
                        <?php
                        $alertColors = [
                            'Critical' => 'bg-red-100 text-red-700 border-red-400',
                            'Low' => 'bg-orange-100 text-orange-700 border-orange-400',
                            'Warning' => 'bg-yellow-100 text-yellow-700 border-yellow-400'
                        ];
                        $alertClass = $alertColors[$product['alert_level']];
                        $orderQty = max(0, $product['shortage']);
                        $orderValue = $orderQty * $product['purchase_price'];
                        ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4">
                                <span class="<?php echo $alertClass; ?> border px-2 py-1 rounded text-xs font-bold uppercase">
                                    <?php echo $product['alert_level']; ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm font-mono text-gray-900"><?php echo $product['sku']; ?></td>
                            <td class="px-6 py-4">
                                <div class="text-sm font-medium text-gray-900"><?php echo $product['name']; ?></div>
                                <div class="text-xs text-gray-500"><?php echo $product['unit']; ?></div>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-600">
                                <?php echo $product['category_name'] ?: 'N/A'; ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-right font-bold">
                                <span class="<?php echo $product['stock_quantity'] == 0 ? 'text-red-600' : 'text-gray-900'; ?>">
                                    <?php
                                    if (in_array($product['unit'], ['ml','kg','ltr'])) {
                                        echo number_format((float)$product['stock_quantity'], 2);
                                    } else {
                                        echo number_format((int)$product['stock_quantity']);
                                    }
                                    ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm text-right text-gray-600">
                                <?php echo $product['reorder_level']; ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-right font-semibold text-red-600">
                                <?php echo number_format($orderQty); ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-right">
                                <?php echo $product['avg_daily_sales'] > 0 ? number_format($product['avg_daily_sales'], 1) : '-'; ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-right">
                                <?php 
                                if ($product['days_of_stock'] !== null) {
                                    $days = round($product['days_of_stock']);
                                    $daysColor = $days <= 3 ? 'text-red-600' : ($days <= 7 ? 'text-orange-600' : 'text-green-600');
                                    echo "<span class='$daysColor font-semibold'>$days days</span>";
                                } else {
                                    echo '<span class="text-gray-500">N/A</span>';
                                }
                                ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-right font-bold text-blue-600">
                                <?php echo formatCurrency($orderValue); ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="10" class="px-6 py-8 text-center">
                            <div class="text-green-600 font-semibold text-lg">✓ All products are adequately stocked!</div>
                            <p class="text-gray-500 text-sm mt-2">No items currently below reorder levels</p>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Supplier Quick Reference -->
<div class="bg-white rounded-lg shadow p-6 mb-6">
    <h3 class="text-lg font-bold text-gray-800 mb-4">📞 Supplier Quick Reference</h3>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <?php while ($supplier = $suppliers->fetch_assoc()): ?>
            <div class="border border-gray-300 rounded-lg p-4 hover:shadow-md transition">
                <p class="font-bold text-gray-900"><?php echo $supplier['name']; ?></p>
                <p class="text-xs text-gray-500 mb-2"><?php echo $supplier['supplier_code']; ?></p>
                <p class="text-sm text-blue-600">📱 <?php echo $supplier['phone']; ?></p>
            </div>
        <?php endwhile; ?>
    </div>
</div>

<!-- Action Recommendations -->
<div class="bg-blue-50 border border-blue-200 rounded-lg p-6">
    <h3 class="text-lg font-bold text-blue-900 mb-3">💡 Recommended Actions</h3>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm text-blue-800">
        <div class="bg-white rounded-lg p-4 border border-blue-200">
            <p class="font-semibold mb-2 text-red-700">🔴 Critical (Out of Stock):</p>
            <ul class="list-disc list-inside space-y-1 ml-2">
                <li>Place URGENT orders immediately</li>
                <li>Consider express shipping</li>
                <li>Notify customers of delays</li>
            </ul>
        </div>
        <div class="bg-white rounded-lg p-4 border border-blue-200">
            <p class="font-semibold mb-2 text-orange-700">🟠 Low Stock:</p>
            <ul class="list-disc list-inside space-y-1 ml-2">
                <li>Place orders within 1-2 days</li>
                <li>Monitor sales velocity</li>
                <li>Prepare backup suppliers</li>
            </ul>
        </div>
        <div class="bg-white rounded-lg p-4 border border-blue-200">
            <p class="font-semibold mb-2 text-yellow-700">🟡 Warning:</p>
            <ul class="list-disc list-inside space-y-1 ml-2">
                <li>Schedule regular reorders</li>
                <li>Review reorder levels</li>
                <li>Track usage patterns</li>
            </ul>
        </div>
    </div>
</div>


<?php
include '../../includes/footer.php';
?>