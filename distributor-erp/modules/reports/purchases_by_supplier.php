<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';

requireLogin();

$conn = getDBConnection();
$pageTitle = 'Purchases by Supplier Report';

// Date filters
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-01');
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-t');
$supplier_id = isset($_GET['supplier_id']) ? (int)$_GET['supplier_id'] : 0;
$payment_status = isset($_GET['payment_status']) ? clean($_GET['payment_status']) : 'all';
$sort_by = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'amount';

// Build query
$where = "p.purchase_date BETWEEN '$date_from' AND '$date_to'";
if ($supplier_id > 0) {
    $where .= " AND p.supplier_id = $supplier_id";
}
if ($payment_status !== 'all') {
    $where .= " AND p.payment_status = '$payment_status'";
}

// Sort field
$sortField = match($sort_by) {
    'orders' => 'total_orders',
    'items' => 'total_items',
    default => 'total_purchases'
};

// Get supplier purchase data
$query = "SELECT 
          s.id,
          s.supplier_code,
          s.name as supplier_name,
          s.contact_person,
          s.phone,
          s.city,
          s.current_balance,
          COUNT(DISTINCT p.id) as total_orders,
          COUNT(DISTINCT pi.product_id) as unique_products,
          SUM(pi.quantity) as total_items,
          SUM(p.subtotal) as subtotal,
          SUM(p.discount) as total_discount,
          SUM(p.tax) as total_tax,
          SUM(p.total_amount) as total_purchases,
          SUM(p.paid_amount) as total_paid,
          SUM(p.total_amount - p.paid_amount) as outstanding,
          AVG(p.total_amount) as avg_order_value,
          MIN(p.purchase_date) as first_purchase,
          MAX(p.purchase_date) as last_purchase,
          DATEDIFF('$date_to', MAX(p.purchase_date)) as days_since_last_order,
          COUNT(DISTINCT CASE WHEN p.payment_status = 'paid' THEN p.id END) as paid_orders,
          COUNT(DISTINCT CASE WHEN p.payment_status = 'partial' THEN p.id END) as partial_orders,
          COUNT(DISTINCT CASE WHEN p.payment_status = 'unpaid' THEN p.id END) as unpaid_orders
          FROM suppliers s
          JOIN purchases p ON s.id = p.supplier_id
          LEFT JOIN purchase_items pi ON p.id = pi.purchase_id
          WHERE $where
          GROUP BY s.id
          ORDER BY $sortField DESC";

$suppliers = $conn->query($query);

// Get suppliers for filter
$suppliersQuery = "SELECT id, name, supplier_code FROM suppliers WHERE is_active = 1 ORDER BY name ASC";
$suppliersFilter = $conn->query($suppliersQuery);

// Get overall totals
$totalsQuery = "SELECT 
                COUNT(DISTINCT s.id) as total_suppliers,
                COUNT(DISTINCT p.id) as total_orders,
                SUM(p.subtotal) as subtotal,
                SUM(p.discount) as total_discount,
                SUM(p.tax) as total_tax,
                SUM(p.total_amount) as total_purchases,
                SUM(p.paid_amount) as total_paid,
                SUM(p.total_amount - p.paid_amount) as outstanding,
                AVG(p.total_amount) as avg_order_value
                FROM suppliers s
                JOIN purchases p ON s.id = p.supplier_id
                WHERE $where";
$totals = $conn->query($totalsQuery)->fetch_assoc();

// Payment status breakdown
$statusBreakdown = "SELECT 
                    payment_status,
                    COUNT(*) as count,
                    SUM(total_amount) as amount
                    FROM purchases
                    WHERE purchase_date BETWEEN '$date_from' AND '$date_to'
                    GROUP BY payment_status";
$paymentStatus = $conn->query($statusBreakdown);

// Top supplier
$topSupplier = "SELECT 
                s.name,
                SUM(p.total_amount) as purchases
                FROM suppliers s
                JOIN purchases p ON s.id = p.supplier_id
                WHERE $where
                GROUP BY s.id
                ORDER BY purchases DESC
                LIMIT 1";
$topSupp = $conn->query($topSupplier)->fetch_assoc();

// Monthly trend
$monthlyTrend = "SELECT 
                 DATE_FORMAT(p.purchase_date, '%Y-%m') as month,
                 COUNT(DISTINCT p.id) as orders,
                 SUM(p.total_amount) as amount
                 FROM purchases p
                 WHERE $where
                 GROUP BY month
                 ORDER BY month DESC
                 LIMIT 6";
$monthly = $conn->query($monthlyTrend);

include '../../includes/header.php';
?>

<!-- Page Header -->
<div class="mb-6">
    <h1 class="text-3xl font-bold text-gray-800">Purchases by Supplier Report</h1>
    <p class="text-gray-600">Analyze purchasing patterns and supplier relationships</p>
</div>

<!-- Filters -->
<div class="bg-white rounded-lg shadow p-6 mb-6">
    <form method="GET" action="" class="grid grid-cols-1 md:grid-cols-5 gap-4">
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
            <label class="block text-sm font-semibold text-gray-700 mb-2">Supplier</label>
            <select name="supplier_id" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="0">All Suppliers</option>
                <?php 
                $suppliersFilter->data_seek(0);
                while ($supp = $suppliersFilter->fetch_assoc()): 
                ?>
                    <option value="<?php echo $supp['id']; ?>" <?php echo $supplier_id == $supp['id'] ? 'selected' : ''; ?>>
                        <?php echo $supp['name']; ?> (<?php echo $supp['supplier_code']; ?>)
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">Payment Status</label>
            <select name="payment_status" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="all" <?php echo $payment_status === 'all' ? 'selected' : ''; ?>>All Status</option>
                <option value="paid" <?php echo $payment_status === 'paid' ? 'selected' : ''; ?>>Paid</option>
                <option value="partial" <?php echo $payment_status === 'partial' ? 'selected' : ''; ?>>Partial</option>
                <option value="unpaid" <?php echo $payment_status === 'unpaid' ? 'selected' : ''; ?>>Unpaid</option>
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
        <p class="text-gray-500 text-sm">Active Suppliers</p>
        <h3 class="text-3xl font-bold text-gray-800"><?php echo $totals['total_suppliers']; ?></h3>
        <p class="text-xs text-gray-500 mt-1">Used in period</p>
    </div>
    
    <div class="bg-white rounded-lg shadow p-6">
        <p class="text-gray-500 text-sm">Total Orders</p>
        <h3 class="text-3xl font-bold text-blue-600"><?php echo number_format($totals['total_orders']); ?></h3>
        <p class="text-xs text-gray-500 mt-1">Purchase orders</p>
    </div>
    
    <div class="bg-white rounded-lg shadow p-6">
        <p class="text-gray-500 text-sm">Total Purchases</p>
        <h3 class="text-2xl font-bold text-green-600"><?php echo formatCurrency($totals['total_purchases']); ?></h3>
        <p class="text-xs text-gray-500 mt-1">Total spent</p>
    </div>
    
    <div class="bg-white rounded-lg shadow p-6">
        <p class="text-gray-500 text-sm">Avg Order Value</p>
        <h3 class="text-2xl font-bold text-purple-600"><?php echo formatCurrency($totals['avg_order_value']); ?></h3>
        <p class="text-xs text-gray-500 mt-1">Per purchase</p>
    </div>
    
    <div class="bg-white rounded-lg shadow p-6">
        <p class="text-gray-500 text-sm">Outstanding</p>
        <h3 class="text-2xl font-bold text-red-600"><?php echo formatCurrency($totals['outstanding']); ?></h3>
        <p class="text-xs text-gray-500 mt-1">Payables</p>
    </div>
</div>

<!-- Payment Status & Monthly Trend -->
<div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
    <!-- Payment Status Breakdown -->
    <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-bold text-gray-800 mb-4">Payment Status Breakdown</h3>
        <div class="space-y-3">
            <?php 
            $paymentStatus->data_seek(0);
            $statusColors = [
                'paid' => 'bg-green-100 text-green-700 border-green-300',
                'partial' => 'bg-yellow-100 text-yellow-700 border-yellow-300',
                'unpaid' => 'bg-red-100 text-red-700 border-red-300'
            ];
            while ($status = $paymentStatus->fetch_assoc()): 
                $colorClass = $statusColors[$status['payment_status']] ?? 'bg-gray-100 text-gray-700 border-gray-300';
                $percentage = $totals['total_purchases'] > 0 ? ($status['amount'] / $totals['total_purchases']) * 100 : 0;
            ?>
                <div class="<?php echo $colorClass; ?> border-2 rounded-lg p-4">
                    <div class="flex justify-between items-center mb-2">
                        <span class="font-bold uppercase"><?php echo $status['payment_status']; ?></span>
                        <span class="font-semibold"><?php echo $status['count']; ?> orders</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <div class="flex-1 mr-3">
                            <div class="w-full bg-white rounded-full h-2">
                                <div class="bg-current h-2 rounded-full opacity-50" style="width: <?php echo $percentage; ?>%"></div>
                            </div>
                        </div>
                        <span class="font-bold"><?php echo formatCurrency($status['amount']); ?></span>
                    </div>
                    <p class="text-xs mt-1"><?php echo number_format($percentage, 1); ?>% of total</p>
                </div>
            <?php endwhile; ?>
        </div>
    </div>

    <!-- Monthly Trend -->
    <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-bold text-gray-800 mb-4">Monthly Purchase Trend (Last 6 Months)</h3>
        <div class="space-y-3">
            <?php 
            $monthly->data_seek(0);
            $maxAmount = 0;
            $monthlyData = [];
            while ($m = $monthly->fetch_assoc()) {
                $monthlyData[] = $m;
                if ($m['amount'] > $maxAmount) $maxAmount = $m['amount'];
            }
            foreach ($monthlyData as $month): 
                $percentage = $maxAmount > 0 ? ($month['amount'] / $maxAmount) * 100 : 0;
            ?>
                <div class="border border-gray-300 rounded-lg p-3">
                    <div class="flex justify-between items-center mb-2">
                        <span class="font-semibold text-gray-700"><?php echo date('M Y', strtotime($month['month'] . '-01')); ?></span>
                        <span class="text-sm text-gray-600"><?php echo $month['orders']; ?> orders</span>
                    </div>
                    <div class="flex items-center">
                        <div class="flex-1 mr-3">
                            <div class="w-full bg-gray-200 rounded-full h-3">
                                <div class="bg-blue-500 h-3 rounded-full" style="width: <?php echo $percentage; ?>%"></div>
                            </div>
                        </div>
                        <span class="font-bold text-blue-600"><?php echo formatCurrency($month['amount']); ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Supplier Performance Table -->
<div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="p-6 border-b">
        <h3 class="text-lg font-bold text-gray-800">Supplier Purchase Details</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">#</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Supplier</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Contact</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Orders</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Products</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total Items</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total Purchases</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Avg Order</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Outstanding</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Last Order</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php if ($suppliers->num_rows > 0): ?>
                    <?php 
                    $rank = 1;
                    while ($supplier = $suppliers->fetch_assoc()): 
                        $paymentRate = $supplier['total_purchases'] > 0 ? 
                                      (($supplier['total_paid'] / $supplier['total_purchases']) * 100) : 0;
                    ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 text-sm font-bold text-gray-900"><?php echo $rank++; ?></td>
                            <td class="px-6 py-4">
                                <div class="text-sm font-medium text-gray-900">
                                    <a href="../suppliers/view.php?id=<?php echo $supplier['id']; ?>" 
                                       class="text-blue-600 hover:text-blue-800">
                                        <?php echo $supplier['supplier_name']; ?>
                                    </a>
                                </div>
                                <div class="text-xs text-gray-500"><?php echo $supplier['supplier_code']; ?></div>
                            </td>
                            <td class="px-6 py-4 text-sm">
                                <div><?php echo $supplier['contact_person'] ?: 'N/A'; ?></div>
                                <div class="text-xs text-gray-500"><?php echo $supplier['phone']; ?></div>
                            </td>
                            <td class="px-6 py-4 text-sm text-right font-semibold">
                                <?php echo number_format($supplier['total_orders']); ?>
                                <div class="text-xs text-gray-500">
                                    <?php echo $supplier['paid_orders']; ?>P / <?php echo $supplier['unpaid_orders']; ?>U
                                </div>
                            </td>
                            <td class="px-6 py-4 text-sm text-right">
                                <?php echo $supplier['unique_products']; ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-right font-semibold">
                                <?php echo number_format($supplier['total_items']); ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-right font-bold text-blue-600">
                                <?php echo formatCurrency($supplier['total_purchases']); ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-right">
                                <?php echo formatCurrency($supplier['avg_order_value']); ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-right">
                                <span class="<?php echo $supplier['outstanding'] > 0 ? 'text-red-600 font-semibold' : 'text-gray-600'; ?>">
                                    <?php echo formatCurrency($supplier['outstanding']); ?>
                                </span>
                                <?php if ($supplier['outstanding'] > 0): ?>
                                    <div class="text-xs text-gray-500">
                                        <?php echo number_format(100 - $paymentRate, 1); ?>% unpaid
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-right">
                                <div><?php echo formatDate($supplier['last_purchase']); ?></div>
                                <div class="text-xs <?php echo $supplier['days_since_last_order'] > 90 ? 'text-red-600' : 'text-gray-500'; ?>">
                                    <?php echo $supplier['days_since_last_order']; ?> days ago
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    
                    <!-- Totals Row -->
                    <tr class="bg-gray-100 font-bold">
                        <td colspan="3" class="px-6 py-4 text-sm">TOTAL</td>
                        <td class="px-6 py-4 text-sm text-right"><?php echo number_format($totals['total_orders']); ?></td>
                        <td colspan="2"></td>
                        <td class="px-6 py-4 text-sm text-right text-blue-600">
                            <?php echo formatCurrency($totals['total_purchases']); ?>
                        </td>
                        <td class="px-6 py-4 text-sm text-right">
                            <?php echo formatCurrency($totals['avg_order_value']); ?>
                        </td>
                        <td class="px-6 py-4 text-sm text-right text-red-600">
                            <?php echo formatCurrency($totals['outstanding']); ?>
                        </td>
                        <td></td>
                    </tr>
                <?php else: ?>
                    <tr>
                        <td colspan="10" class="px-6 py-8 text-center text-gray-500">
                            No supplier purchase data found for the selected period
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Supplier Insights -->
<div class="mt-6 bg-blue-50 border border-blue-200 rounded-lg p-6">
    <h3 class="text-lg font-bold text-blue-900 mb-3">📊 Supplier Insights</h3>
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 text-sm text-blue-800">
        <div>
            <p class="font-semibold mb-2">Avg Spend per Supplier:</p>
            <p class="text-2xl font-bold text-blue-900">
                <?php echo formatCurrency($totals['total_suppliers'] > 0 ? $totals['total_purchases'] / $totals['total_suppliers'] : 0); ?>
            </p>
            <p class="text-xs mt-1">In this period</p>
        </div>
        <div>
            <p class="font-semibold mb-2">Payment Rate:</p>
            <p class="text-2xl font-bold text-blue-900">
                <?php echo $totals['total_purchases'] > 0 ? number_format(($totals['total_paid'] / $totals['total_purchases']) * 100, 1) : 0; ?>%
            </p>
            <p class="text-xs mt-1">Invoices paid</p>
        </div>
        <div>
            <p class="font-semibold mb-2">Top Supplier:</p>
            <p class="text-lg font-bold text-blue-900"><?php echo $topSupp['name'] ?? 'N/A'; ?></p>
            <p class="text-xs mt-1"><?php echo formatCurrency($topSupp['purchases'] ?? 0); ?></p>
        </div>
        <div>
            <p class="font-semibold mb-2">Supplier Dependency:</p>
            <p class="text-2xl font-bold text-blue-900">
                <?php 
                $topSupplierPercent = $totals['total_purchases'] > 0 && isset($topSupp['purchases']) ? 
                                     (($topSupp['purchases'] / $totals['total_purchases']) * 100) : 0;
                echo number_format($topSupplierPercent, 1); 
                ?>%
            </p>
            <p class="text-xs mt-1">From top supplier</p>
        </div>
    </div>
</div>


<?php
include '../../includes/footer.php';
?>