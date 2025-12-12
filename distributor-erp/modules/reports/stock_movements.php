<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';

requireLogin();

$conn = getDBConnection();
$pageTitle = 'Stock Movements Report';

// Date filters
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-d', strtotime('-30 days'));
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');
$product_id = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;
$movement_type = isset($_GET['movement_type']) ? clean($_GET['movement_type']) : 'all';
$category_id = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;

// Build query
$where = "DATE(sm.created_at) BETWEEN '$date_from' AND '$date_to'";
if ($product_id > 0) {
    $where .= " AND sm.product_id = $product_id";
}
if ($movement_type !== 'all') {
    $where .= " AND sm.movement_type = '$movement_type'";
}
if ($category_id > 0) {
    $where .= " AND p.category_id = $category_id";
}

// Get stock movements
$query = "SELECT 
          sm.id,
          sm.movement_type,
          sm.quantity,
          sm.reference_id,
          sm.reference_type,
          sm.notes,
          sm.created_at,
          p.id as product_id,
          p.sku,
          p.name as product_name,
          p.unit,
          p.stock_quantity as current_stock,
          c.name as category_name,
          u.full_name as recorded_by,
          CASE 
            WHEN sm.movement_type = 'sale' THEN s.invoice_number
            WHEN sm.movement_type = 'purchase' THEN pur.purchase_number
            ELSE sm.reference_id
          END as reference_number
          FROM stock_movements sm
          JOIN products p ON sm.product_id = p.id
          LEFT JOIN categories c ON p.category_id = c.id
          LEFT JOIN users u ON sm.user_id = u.id
          LEFT JOIN sales s ON sm.reference_type = 'sale' AND sm.reference_id = s.id
          LEFT JOIN purchases pur ON sm.reference_type = 'purchase' AND sm.reference_id = pur.id
          WHERE $where
          ORDER BY sm.created_at DESC";

$movements = $conn->query($query);

// Get products for filter
$productsQuery = "SELECT id, name, sku FROM products WHERE is_active = 1 ORDER BY name ASC LIMIT 100";
$products = $conn->query($productsQuery);

// Get categories for filter
$categoriesQuery = "SELECT * FROM categories ORDER BY name ASC";
$categories = $conn->query($categoriesQuery);

// Get summary stats
$summaryQuery = "SELECT 
                 COUNT(*) as total_movements,
                 SUM(CASE WHEN sm.movement_type IN ('purchase', 'return', 'adjustment') AND sm.quantity > 0 THEN sm.quantity ELSE 0 END) as total_in,
                 SUM(CASE WHEN sm.movement_type IN ('sale') OR (sm.movement_type = 'adjustment' AND sm.quantity < 0) THEN ABS(sm.quantity) ELSE 0 END) as total_out,
                 COUNT(DISTINCT sm.product_id) as unique_products
                 FROM stock_movements sm
                 JOIN products p ON sm.product_id = p.id
                 WHERE $where";
$summary = $conn->query($summaryQuery)->fetch_assoc();

// Movement type breakdown
$typeBreakdown = "SELECT 
                  sm.movement_type,
                  COUNT(*) as count,
                  SUM(ABS(sm.quantity)) as total_quantity
                  FROM stock_movements sm
                  JOIN products p ON sm.product_id = p.id
                  WHERE $where
                  GROUP BY sm.movement_type";
$types = $conn->query($typeBreakdown);

// Daily movement trend
$dailyTrend = "SELECT 
               DATE(sm.created_at) as date,
               COUNT(*) as movement_count,
               SUM(CASE WHEN sm.movement_type IN ('purchase', 'return') OR (sm.movement_type = 'adjustment' AND sm.quantity > 0) THEN sm.quantity ELSE 0 END) as stock_in,
               SUM(CASE WHEN sm.movement_type = 'sale' OR (sm.movement_type = 'adjustment' AND sm.quantity < 0) THEN ABS(sm.quantity) ELSE 0 END) as stock_out
               FROM stock_movements sm
               JOIN products p ON sm.product_id = p.id
               WHERE $where
               GROUP BY DATE(sm.created_at)
               ORDER BY date DESC
               LIMIT 10";
$daily = $conn->query($dailyTrend);

// Most active products
$activeProducts = "SELECT 
                   p.name,
                   p.sku,
                   COUNT(sm.id) as movement_count,
                   SUM(ABS(sm.quantity)) as total_quantity
                   FROM stock_movements sm
                   JOIN products p ON sm.product_id = p.id
                   WHERE $where
                   GROUP BY p.id
                   ORDER BY movement_count DESC
                   LIMIT 10";
$activeProds = $conn->query($activeProducts);

include '../../includes/header.php';
?>

<!-- Page Header -->
<div class="mb-6">
    <h1 class="text-3xl font-bold text-gray-800">Stock Movements Report</h1>
    <p class="text-gray-600">Track all inventory transactions and changes</p>
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
            <label class="block text-sm font-semibold text-gray-700 mb-2">Product</label>
            <select name="product_id" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="0">All Products</option>
                <?php 
                $products->data_seek(0);
                while ($product = $products->fetch_assoc()): 
                ?>
                    <option value="<?php echo $product['id']; ?>" <?php echo $product_id == $product['id'] ? 'selected' : ''; ?>>
                        <?php echo $product['name']; ?> (<?php echo $product['sku']; ?>)
                    </option>
                <?php endwhile; ?>
            </select>
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
            <label class="block text-sm font-semibold text-gray-700 mb-2">Movement Type</label>
            <select name="movement_type" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="all" <?php echo $movement_type === 'all' ? 'selected' : ''; ?>>All Types</option>
                <option value="sale" <?php echo $movement_type === 'sale' ? 'selected' : ''; ?>>Sales</option>
                <option value="purchase" <?php echo $movement_type === 'purchase' ? 'selected' : ''; ?>>Purchases</option>
                <option value="adjustment" <?php echo $movement_type === 'adjustment' ? 'selected' : ''; ?>>Adjustments</option>
                <option value="return" <?php echo $movement_type === 'return' ? 'selected' : ''; ?>>Returns</option>
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
        <p class="text-gray-500 text-sm">Total Movements</p>
        <h3 class="text-3xl font-bold text-gray-800"><?php echo number_format($summary['total_movements']); ?></h3>
        <p class="text-xs text-gray-500 mt-1">Transactions</p>
    </div>
    
    <div class="bg-white rounded-lg shadow p-6">
        <p class="text-gray-500 text-sm">Stock In</p>
        <h3 class="text-3xl font-bold text-green-600"><?php echo number_format($summary['total_in']); ?></h3>
        <p class="text-xs text-gray-500 mt-1">Units added</p>
    </div>
    
    <div class="bg-white rounded-lg shadow p-6">
        <p class="text-gray-500 text-sm">Stock Out</p>
        <h3 class="text-3xl font-bold text-red-600"><?php echo number_format($summary['total_out']); ?></h3>
        <p class="text-xs text-gray-500 mt-1">Units removed</p>
    </div>
    
    <div class="bg-white rounded-lg shadow p-6">
        <p class="text-gray-500 text-sm">Net Change</p>
        <h3 class="text-3xl font-bold <?php echo ($summary['total_in'] - $summary['total_out']) >= 0 ? 'text-green-600' : 'text-red-600'; ?>">
            <?php echo ($summary['total_in'] - $summary['total_out']) >= 0 ? '+' : ''; ?><?php echo number_format($summary['total_in'] - $summary['total_out']); ?>
        </h3>
        <p class="text-xs text-gray-500 mt-1">In - Out</p>
    </div>
</div>

<!-- Movement Type Breakdown -->
<div class="bg-white rounded-lg shadow p-6 mb-6">
    <h3 class="text-lg font-bold text-gray-800 mb-4">Movement Type Breakdown</h3>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <?php 
        $types->data_seek(0);
        $typeColors = [
            'sale' => 'bg-red-100 text-red-700 border-red-300',
            'purchase' => 'bg-green-100 text-green-700 border-green-300',
            'adjustment' => 'bg-blue-100 text-blue-700 border-blue-300',
            'return' => 'bg-purple-100 text-purple-700 border-purple-300'
        ];
        $typeIcons = [
            'sale' => '📤',
            'purchase' => '📥',
            'adjustment' => '⚙️',
            'return' => '↩️'
        ];
        while ($type = $types->fetch_assoc()): 
            $colorClass = $typeColors[$type['movement_type']] ?? 'bg-gray-100 text-gray-700 border-gray-300';
            $icon = $typeIcons[$type['movement_type']] ?? '📦';
        ?>
            <div class="<?php echo $colorClass; ?> border-2 rounded-lg p-4 text-center">
                <div class="text-3xl mb-2"><?php echo $icon; ?></div>
                <p class="text-sm font-semibold uppercase"><?php echo ucfirst($type['movement_type']); ?></p>
                <p class="text-2xl font-bold mt-2"><?php echo number_format($type['total_quantity']); ?></p>
                <p class="text-xs mt-1"><?php echo $type['count']; ?> transactions</p>
            </div>
        <?php endwhile; ?>
    </div>
</div>

<!-- Daily Movement Trend -->
<div class="bg-white rounded-lg shadow p-6 mb-6">
    <h3 class="text-lg font-bold text-gray-800 mb-4">Recent Daily Movement Trend</h3>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Transactions</th>
                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Stock In</th>
                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Stock Out</th>
                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Net Change</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Trend</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php while ($day = $daily->fetch_assoc()): 
                    $netChange = $day['stock_in'] - $day['stock_out'];
                ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-sm font-medium"><?php echo formatDate($day['date']); ?></td>
                        <td class="px-4 py-3 text-sm text-right"><?php echo $day['movement_count']; ?></td>
                        <td class="px-4 py-3 text-sm text-right font-semibold text-green-600">
                            +<?php echo number_format($day['stock_in']); ?>
                        </td>
                        <td class="px-4 py-3 text-sm text-right font-semibold text-red-600">
                            -<?php echo number_format($day['stock_out']); ?>
                        </td>
                        <td class="px-4 py-3 text-sm text-right font-bold <?php echo $netChange >= 0 ? 'text-green-600' : 'text-red-600'; ?>">
                            <?php echo $netChange >= 0 ? '+' : ''; ?><?php echo number_format($netChange); ?>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center">
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <?php if ($netChange >= 0): ?>
                                        <div class="bg-green-500 h-2 rounded-full" style="width: <?php echo min(100, abs($netChange) / max($day['stock_in'], $day['stock_out']) * 100); ?>%"></div>
                                    <?php else: ?>
                                        <div class="bg-red-500 h-2 rounded-full" style="width: <?php echo min(100, abs($netChange) / max($day['stock_in'], $day['stock_out']) * 100); ?>%"></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Most Active Products -->
<div class="bg-white rounded-lg shadow p-6 mb-6">
    <h3 class="text-lg font-bold text-gray-800 mb-4">Top 10 Most Active Products</h3>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">#</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Product</th>
                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Movements</th>
                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Total Quantity</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php 
                $rank = 1;
                while ($prod = $activeProds->fetch_assoc()): 
                ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-sm font-bold"><?php echo $rank++; ?></td>
                        <td class="px-4 py-3">
                            <div class="text-sm font-medium text-gray-900"><?php echo $prod['name']; ?></div>
                            <div class="text-xs text-gray-500"><?php echo $prod['sku']; ?></div>
                        </td>
                        <td class="px-4 py-3 text-sm text-right font-semibold"><?php echo $prod['movement_count']; ?></td>
                        <td class="px-4 py-3 text-sm text-right font-bold text-blue-600">
                            <?php echo number_format($prod['total_quantity']); ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Detailed Movements Table -->
<div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="p-6 border-b">
        <h3 class="text-lg font-bold text-gray-800">Detailed Movement History</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date/Time</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Product</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Quantity</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Current Stock</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Reference</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Recorded By</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Notes</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php if ($movements->num_rows > 0): ?>
                    <?php while ($movement = $movements->fetch_assoc()): ?>
                        <?php
                        $typeColors = [
                            'sale' => 'bg-red-100 text-red-700',
                            'purchase' => 'bg-green-100 text-green-700',
                            'adjustment' => 'bg-blue-100 text-blue-700',
                            'return' => 'bg-purple-100 text-purple-700'
                        ];
                        $typeColor = $typeColors[$movement['movement_type']] ?? 'bg-gray-100 text-gray-700';
                        
                        $isIncoming = in_array($movement['movement_type'], ['purchase', 'return']) || 
                                     ($movement['movement_type'] === 'adjustment' && $movement['quantity'] > 0);
                        $quantityColor = $isIncoming ? 'text-green-600' : 'text-red-600';
                        $quantitySign = $isIncoming ? '+' : '-';
                        ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 text-sm text-gray-900">
                                <?php echo date('M d, Y H:i', strtotime($movement['created_at'])); ?>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm font-medium text-gray-900"><?php echo $movement['product_name']; ?></div>
                                <div class="text-xs text-gray-500"><?php echo $movement['sku']; ?></div>
                            </td>
                            <td class="px-6 py-4">
                                <span class="<?php echo $typeColor; ?> px-2 py-1 rounded text-xs font-semibold uppercase">
                                    <?php echo $movement['movement_type']; ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm text-right font-bold <?php echo $quantityColor; ?>">
                                <?php echo $quantitySign; ?><?php echo number_format(abs($movement['quantity'])); ?> <?php echo $movement['unit']; ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-right font-semibold">
                                <?php echo number_format($movement['current_stock']); ?>
                            </td>
                            <td class="px-6 py-4 text-sm">
                                <?php if ($movement['reference_number']): ?>
                                    <span class="text-blue-600 font-mono"><?php echo $movement['reference_number']; ?></span>
                                <?php else: ?>
                                    <span class="text-gray-500">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-600">
                                <?php echo $movement['recorded_by'] ?: 'System'; ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-600">
                                <?php echo $movement['notes'] ?: '-'; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" class="px-6 py-8 text-center text-gray-500">
                            No stock movements found for the selected period
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Legend -->
<div class="mt-6 bg-gray-50 border border-gray-200 rounded-lg p-6">
    <h3 class="text-lg font-bold text-gray-800 mb-3">📋 Movement Types Legend</h3>
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 text-sm">
        <div>
            <p class="font-semibold text-green-700 mb-1">📥 Purchase:</p>
            <p class="text-gray-600">Stock received from suppliers via purchase orders</p>
        </div>
        <div>
            <p class="font-semibold text-red-700 mb-1">📤 Sale:</p>
            <p class="text-gray-600">Stock sold to customers via invoices</p>
        </div>
        <div>
            <p class="font-semibold text-blue-700 mb-1">⚙️ Adjustment:</p>
            <p class="text-gray-600">Manual stock corrections for damaged, lost, or found items</p>
        </div>
        <div>
            <p class="font-semibold text-purple-700 mb-1">↩️ Return:</p>
            <p class="text-gray-600">Items returned from customers or to suppliers</p>
        </div>
    </div>
</div>


<?php
include '../../includes/footer.php';
?>