<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';

requireLogin();

$conn = getDBConnection();
$pageTitle = 'Top Products Report';

// Date filters
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-01');
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-t');
$category_id = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;
$sort_by = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'revenue';
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;

// Build query
$where = "s.sale_date BETWEEN '$date_from' AND '$date_to'";
if ($category_id > 0) {
    $where .= " AND p.category_id = $category_id";
}

// Sort field
$sortField = $sort_by === 'quantity' ? 'total_quantity' : 'total_revenue';

$query = "SELECT 
          p.id,
          p.sku,
          p.name as product_name,
          p.unit,
          p.selling_price,
          p.purchase_price,
          c.name as category_name,
          SUM(si.quantity) as total_quantity,
          SUM(si.subtotal) as total_revenue,
          COUNT(DISTINCT s.id) as times_sold,
          COUNT(DISTINCT s.customer_id) as unique_customers,
          SUM(si.quantity * p.purchase_price) as total_cost,
          SUM(si.subtotal - (si.quantity * p.purchase_price)) as total_profit,
          AVG(si.unit_price) as avg_selling_price
          FROM sales_items si
          JOIN sales s ON si.sale_id = s.id
          JOIN products p ON si.product_id = p.id
          LEFT JOIN categories c ON p.category_id = c.id
          WHERE $where
          GROUP BY p.id
          ORDER BY $sortField DESC
          LIMIT $limit";

$products = $conn->query($query);

// Get categories for filter
$categoriesQuery = "SELECT * FROM categories ORDER BY name ASC";
$categories = $conn->query($categoriesQuery);

// Get overall totals
$totalsQuery = "SELECT 
                SUM(si.quantity) as total_quantity,
                SUM(si.subtotal) as total_revenue,
                COUNT(DISTINCT si.product_id) as unique_products
                FROM sales_items si
                JOIN sales s ON si.sale_id = s.id
                JOIN products p ON si.product_id = p.id
                WHERE $where";
$totals = $conn->query($totalsQuery)->fetch_assoc();

include '../../includes/header.php';
?>

<!-- Page Header -->
<div class="mb-6">
    <h1 class="text-3xl font-bold text-gray-800">Top Products Report</h1>
    <p class="text-gray-600">Best performing products by sales volume and revenue</p>
</div>

<!-- Filters -->
<div class="bg-white rounded-lg shadow p-6 mb-6">
    <form method="GET" action="" class="grid grid-cols-1 md:grid-cols-6 gap-4">
        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">From Date</label>
            <input type="date" 
                   name="date_from" 
                   value="<?php echo $date_from; ?>"
                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">To Date</label>
            <input type="date" 
                   name="date_to" 
                   value="<?php echo $date_to; ?>"
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
            <label class="block text-sm font-semibold text-gray-700 mb-2">Sort By</label>
            <select name="sort_by" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="revenue" <?php echo $sort_by === 'revenue' ? 'selected' : ''; ?>>Revenue</option>
                <option value="quantity" <?php echo $sort_by === 'quantity' ? 'selected' : ''; ?>>Quantity Sold</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">Show Top</label>
            <select name="limit" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="10" <?php echo $limit == 10 ? 'selected' : ''; ?>>Top 10</option>
                <option value="20" <?php echo $limit == 20 ? 'selected' : ''; ?>>Top 20</option>
                <option value="50" <?php echo $limit == 50 ? 'selected' : ''; ?>>Top 50</option>
                <option value="100" <?php echo $limit == 100 ? 'selected' : ''; ?>>Top 100</option>
            </select>
        </div>
        <div class="flex items-end gap-2">
            <button type="submit" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-semibold transition">
                Generate
            </button>
            <button type="button" onclick="window.print()" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg font-semibold transition">
                Print
            </button>
        </div>
    </form>
</div>

<!-- Summary Cards -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
    <div class="bg-white rounded-lg shadow p-6">
        <p class="text-gray-500 text-sm">Total Revenue</p>
        <h3 class="text-2xl font-bold text-blue-600"><?php echo formatCurrency($totals['total_revenue']); ?></h3>
    </div>
    
    <div class="bg-white rounded-lg shadow p-6">
        <p class="text-gray-500 text-sm">Units Sold</p>
        <h3 class="text-2xl font-bold text-green-600"><?php echo number_format($totals['total_quantity']); ?></h3>
    </div>
    
    <div class="bg-white rounded-lg shadow p-6">
        <p class="text-gray-500 text-sm">Unique Products</p>
        <h3 class="text-2xl font-bold text-purple-600"><?php echo $totals['unique_products']; ?></h3>
    </div>
</div>

<!-- Products Table -->
<div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="p-6 border-b">
        <h3 class="text-lg font-bold text-gray-800">Top <?php echo $limit; ?> Products</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">#</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Product</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Category</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Qty Sold</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Avg Price</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Revenue</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Profit</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Margin %</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Times Sold</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php if ($products->num_rows > 0): ?>
                    <?php 
                    $rank = 1;
                    while ($product = $products->fetch_assoc()): 
                        $profitMargin = ($product['total_revenue'] > 0) ? 
                                       (($product['total_profit'] / $product['total_revenue']) * 100) : 0;
                    ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 text-sm font-bold text-gray-900"><?php echo $rank++; ?></td>
                            <td class="px-6 py-4">
                                <div class="text-sm font-medium text-gray-900"><?php echo $product['product_name']; ?></div>
                                <div class="text-xs text-gray-500">SKU: <?php echo $product['sku']; ?></div>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-600">
                                <?php echo $product['category_name'] ?: 'N/A'; ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-right font-semibold">
                                <?php echo number_format($product['total_quantity']); ?> <?php echo $product['unit']; ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-right">
                                <?php echo formatCurrency($product['avg_selling_price']); ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-right font-bold text-blue-600">
                                <?php echo formatCurrency($product['total_revenue']); ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-right font-semibold text-green-600">
                                <?php echo formatCurrency($product['total_profit']); ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-right">
                                <span class="<?php echo $profitMargin > 20 ? 'text-green-600' : ($profitMargin > 10 ? 'text-yellow-600' : 'text-red-600'); ?> font-semibold">
                                    <?php echo number_format($profitMargin, 1); ?>%
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm text-right text-gray-600">
                                <?php echo $product['times_sold']; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="9" class="px-6 py-8 text-center text-gray-500">
                            No product data found for the selected period
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
include '../../includes/footer.php';
?>