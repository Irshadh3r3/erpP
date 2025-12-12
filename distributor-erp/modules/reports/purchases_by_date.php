<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';

requireLogin();

$conn = getDBConnection();
$pageTitle = 'Purchases by Date Report';

// Date filters
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-01');
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-t');
$group_by = isset($_GET['group_by']) ? $_GET['group_by'] : 'daily';

// Build query based on grouping
$groupField = '';
$dateFormat = '';
switch ($group_by) {
    case 'daily':
        $groupField = 'DATE(p.purchase_date)';
        $dateFormat = 'purchase_date';
        break;
    case 'weekly':
        $groupField = 'YEARWEEK(p.purchase_date)';
        $dateFormat = "CONCAT(YEAR(p.purchase_date), '-W', WEEK(p.purchase_date))";
        break;
    case 'monthly':
        $groupField = "DATE_FORMAT(p.purchase_date, '%Y-%m')";
        $dateFormat = "DATE_FORMAT(p.purchase_date, '%Y-%m')";
        break;
}

// Get purchases summary
$query = "SELECT 
          $dateFormat as period,
          COUNT(DISTINCT p.id) as total_orders,
          COUNT(DISTINCT p.supplier_id) as unique_suppliers,
          SUM(p.subtotal) as total_subtotal,
          SUM(p.discount) as total_discount,
          SUM(p.tax) as total_tax,
          SUM(p.total_amount) as total_purchases,
          SUM(p.paid_amount) as total_paid,
          SUM(p.total_amount - p.paid_amount) as total_pending,
          AVG(p.total_amount) as avg_order_value
          FROM purchases p
          WHERE p.purchase_date BETWEEN '$date_from' AND '$date_to'
          GROUP BY $groupField
          ORDER BY period DESC";

$results = $conn->query($query);

// Get overall totals
$totalsQuery = "SELECT 
                COUNT(DISTINCT p.id) as total_orders,
                COUNT(DISTINCT p.supplier_id) as unique_suppliers,
                SUM(p.subtotal) as total_subtotal,
                SUM(p.discount) as total_discount,
                SUM(p.tax) as total_tax,
                SUM(p.total_amount) as total_purchases,
                SUM(p.paid_amount) as total_paid,
                SUM(p.total_amount - p.paid_amount) as total_pending,
                AVG(p.total_amount) as avg_order_value
                FROM purchases p
                WHERE p.purchase_date BETWEEN '$date_from' AND '$date_to'";
$totals = $conn->query($totalsQuery)->fetch_assoc();

// Get payment status breakdown
$paymentQuery = "SELECT 
                 payment_status,
                 COUNT(*) as count,
                 SUM(total_amount) as amount
                 FROM purchases
                 WHERE purchase_date BETWEEN '$date_from' AND '$date_to'
                 GROUP BY payment_status";
$paymentBreakdown = $conn->query($paymentQuery);

include '../../includes/header.php';
?>

<!-- Page Header -->
<div class="mb-6">
    <h1 class="text-3xl font-bold text-gray-800">Purchases by Date Report</h1>
    <p class="text-gray-600">Track purchasing patterns over time</p>
</div>

<!-- Filters -->
<div class="bg-white rounded-lg shadow p-6 mb-6">
    <form method="GET" action="" class="grid grid-cols-1 md:grid-cols-4 gap-4">
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
            <label class="block text-sm font-semibold text-gray-700 mb-2">Group By</label>
            <select name="group_by" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="daily" <?php echo $group_by === 'daily' ? 'selected' : ''; ?>>Daily</option>
                <option value="weekly" <?php echo $group_by === 'weekly' ? 'selected' : ''; ?>>Weekly</option>
                <option value="monthly" <?php echo $group_by === 'monthly' ? 'selected' : ''; ?>>Monthly</option>
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
<div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
    <div class="bg-white rounded-lg shadow p-6">
        <p class="text-gray-500 text-sm">Total Purchases</p>
        <h3 class="text-2xl font-bold text-red-600"><?php echo formatCurrency($totals['total_purchases']); ?></h3>
        <p class="text-xs text-gray-500 mt-1"><?php echo $totals['total_orders']; ?> orders</p>
    </div>
    
    <div class="bg-white rounded-lg shadow p-6">
        <p class="text-gray-500 text-sm">Amount Paid</p>
        <h3 class="text-2xl font-bold text-green-600"><?php echo formatCurrency($totals['total_paid']); ?></h3>
        <p class="text-xs text-gray-500 mt-1">
            <?php echo $totals['total_purchases'] > 0 ? number_format(($totals['total_paid'] / $totals['total_purchases']) * 100, 1) : 0; ?>% paid
        </p>
    </div>
    
    <div class="bg-white rounded-lg shadow p-6">
        <p class="text-gray-500 text-sm">Pending Amount</p>
        <h3 class="text-2xl font-bold text-orange-600"><?php echo formatCurrency($totals['total_pending']); ?></h3>
        <p class="text-xs text-gray-500 mt-1">Outstanding payables</p>
    </div>
    
    <div class="bg-white rounded-lg shadow p-6">
        <p class="text-gray-500 text-sm">Avg Order Value</p>
        <h3 class="text-2xl font-bold text-purple-600"><?php echo formatCurrency($totals['avg_order_value']); ?></h3>
        <p class="text-xs text-gray-500 mt-1"><?php echo $totals['unique_suppliers']; ?> suppliers</p>
    </div>
</div>

<!-- Payment Status Breakdown -->
<div class="bg-white rounded-lg shadow p-6 mb-6">
    <h3 class="text-lg font-bold text-gray-800 mb-4">Payment Status Breakdown</h3>
    <div class="grid grid-cols-3 gap-4">
        <?php 
        $paymentBreakdown->data_seek(0);
        while ($status = $paymentBreakdown->fetch_assoc()): 
            $colors = [
                'paid' => 'bg-green-100 text-green-700 border-green-300',
                'partial' => 'bg-yellow-100 text-yellow-700 border-yellow-300',
                'unpaid' => 'bg-red-100 text-red-700 border-red-300'
            ];
            $colorClass = $colors[$status['payment_status']] ?? 'bg-gray-100 text-gray-700 border-gray-300';
        ?>
            <div class="<?php echo $colorClass; ?> border-2 rounded-lg p-4 text-center">
                <p class="text-sm font-semibold uppercase"><?php echo $status['payment_status']; ?></p>
                <p class="text-2xl font-bold mt-2"><?php echo formatCurrency($status['amount']); ?></p>
                <p class="text-xs mt-1"><?php echo $status['count']; ?> orders</p>
            </div>
        <?php endwhile; ?>
    </div>
</div>

<!-- Detailed Report Table -->
<div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="p-6 border-b">
        <h3 class="text-lg font-bold text-gray-800">Detailed Breakdown</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Period</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Orders</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Suppliers</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Subtotal</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Discount</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Tax</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Paid</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Pending</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php if ($results->num_rows > 0): ?>
                    <?php while ($row = $results->fetch_assoc()): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 text-sm font-medium text-gray-900"><?php echo $row['period']; ?></td>
                            <td class="px-6 py-4 text-sm text-right"><?php echo $row['total_orders']; ?></td>
                            <td class="px-6 py-4 text-sm text-right"><?php echo $row['unique_suppliers']; ?></td>
                            <td class="px-6 py-4 text-sm text-right"><?php echo formatCurrency($row['total_subtotal']); ?></td>
                            <td class="px-6 py-4 text-sm text-right text-red-600">
                                <?php echo $row['total_discount'] > 0 ? '-' . formatCurrency($row['total_discount']) : '-'; ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-right">
                                <?php echo $row['total_tax'] > 0 ? formatCurrency($row['total_tax']) : '-'; ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-right font-bold text-red-600">
                                <?php echo formatCurrency($row['total_purchases']); ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-right text-green-600 font-semibold">
                                <?php echo formatCurrency($row['total_paid']); ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-right text-orange-600 font-semibold">
                                <?php echo formatCurrency($row['total_pending']); ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    
                    <!-- Totals Row -->
                    <tr class="bg-gray-100 font-bold">
                        <td class="px-6 py-4 text-sm">TOTAL</td>
                        <td class="px-6 py-4 text-sm text-right"><?php echo $totals['total_orders']; ?></td>
                        <td class="px-6 py-4 text-sm text-right"><?php echo $totals['unique_suppliers']; ?></td>
                        <td class="px-6 py-4 text-sm text-right"><?php echo formatCurrency($totals['total_subtotal']); ?></td>
                        <td class="px-6 py-4 text-sm text-right text-red-600">
                            <?php echo $totals['total_discount'] > 0 ? '-' . formatCurrency($totals['total_discount']) : '-'; ?>
                        </td>
                        <td class="px-6 py-4 text-sm text-right">
                            <?php echo $totals['total_tax'] > 0 ? formatCurrency($totals['total_tax']) : '-'; ?>
                        </td>
                        <td class="px-6 py-4 text-sm text-right text-red-600"><?php echo formatCurrency($totals['total_purchases']); ?></td>
                        <td class="px-6 py-4 text-sm text-right text-green-600"><?php echo formatCurrency($totals['total_paid']); ?></td>
                        <td class="px-6 py-4 text-sm text-right text-orange-600"><?php echo formatCurrency($totals['total_pending']); ?></td>
                    </tr>
                <?php else: ?>
                    <tr>
                        <td colspan="9" class="px-6 py-8 text-center text-gray-500">
                            No purchase data found for the selected period
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<style>
@media print {
    .no-print {
        display: none !important;
    }
}
</style>

<?php
include '../../includes/footer.php';
?>