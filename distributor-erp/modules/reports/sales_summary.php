<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';

requireLogin();

$conn = getDBConnection();
$pageTitle = 'Sales Summary Report';

// Date filters
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-01');
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-t');
$group_by = isset($_GET['group_by']) ? $_GET['group_by'] : 'daily';

// Build query based on grouping
$groupField = '';
$dateFormat = '';
switch ($group_by) {
    case 'daily':
        $groupField = 'DATE(s.sale_date)';
        $dateFormat = 'sale_date';
        break;
    case 'weekly':
        $groupField = 'YEARWEEK(s.sale_date)';
        $dateFormat = "CONCAT(YEAR(s.sale_date), '-W', WEEK(s.sale_date))";
        break;
    case 'monthly':
        $groupField = "DATE_FORMAT(s.sale_date, '%Y-%m')";
        $dateFormat = "DATE_FORMAT(s.sale_date, '%Y-%m')";
        break;
}

// Get sales summary
$query = "SELECT 
          $dateFormat as period,
          COUNT(DISTINCT s.id) as total_invoices,
          SUM(s.subtotal) as total_subtotal,
          SUM(s.discount) as total_discount,
          SUM(s.tax) as total_tax,
          SUM(s.total_amount) as total_sales,
          SUM(s.paid_amount) as total_paid,
          SUM(s.total_amount - s.paid_amount) as total_pending,
          COUNT(DISTINCT s.customer_id) as unique_customers
          FROM sales s
          WHERE s.sale_date BETWEEN '$date_from' AND '$date_to'
          GROUP BY $groupField
          ORDER BY period DESC";

$results = $conn->query($query);

// Get overall totals
$totalsQuery = "SELECT 
                COUNT(DISTINCT s.id) as total_invoices,
                SUM(s.subtotal) as total_subtotal,
                SUM(s.discount) as total_discount,
                SUM(s.tax) as total_tax,
                SUM(s.total_amount) as total_sales,
                SUM(s.paid_amount) as total_paid,
                SUM(s.total_amount - s.paid_amount) as total_pending,
                COUNT(DISTINCT s.customer_id) as unique_customers,
                AVG(s.total_amount) as avg_invoice_value
                FROM sales s
                WHERE s.sale_date BETWEEN '$date_from' AND '$date_to'";
$totals = $conn->query($totalsQuery)->fetch_assoc();

// Get payment status breakdown
$paymentQuery = "SELECT 
                 payment_status,
                 COUNT(*) as count,
                 SUM(total_amount) as amount
                 FROM sales
                 WHERE sale_date BETWEEN '$date_from' AND '$date_to'
                 GROUP BY payment_status";
$paymentBreakdown = $conn->query($paymentQuery);

include '../../includes/header.php';
?>

<!-- Page Header -->
<div class="mb-6">
    <h1 class="text-3xl font-bold text-gray-800">Sales Summary Report</h1>
    <p class="text-gray-600">Comprehensive sales analysis and insights</p>
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
                Generate Report
            </button>
        </div>
    </form>
</div>

<!-- Summary Cards -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
    <div class="bg-white rounded-lg shadow p-6">
        <p class="text-gray-500 text-sm">Total Sales</p>
        <h3 class="text-2xl font-bold text-blue-600"><?php echo formatCurrency($totals['total_sales']); ?></h3>
        <p class="text-xs text-gray-500 mt-1"><?php echo $totals['total_invoices']; ?> invoices</p>
    </div>
    
    <div class="bg-white rounded-lg shadow p-6">
        <p class="text-gray-500 text-sm">Amount Collected</p>
        <h3 class="text-2xl font-bold text-green-600"><?php echo formatCurrency($totals['total_paid']); ?></h3>
        <p class="text-xs text-gray-500 mt-1">
            <?php echo number_format(($totals['total_paid'] / $totals['total_sales']) * 100, 1); ?>% collection rate
        </p>
    </div>
    
    <div class="bg-white rounded-lg shadow p-6">
        <p class="text-gray-500 text-sm">Pending Amount</p>
        <h3 class="text-2xl font-bold text-red-600"><?php echo formatCurrency($totals['total_pending']); ?></h3>
        <p class="text-xs text-gray-500 mt-1">Outstanding receivables</p>
    </div>
    
    <div class="bg-white rounded-lg shadow p-6">
        <p class="text-gray-500 text-sm">Avg Invoice Value</p>
        <h3 class="text-2xl font-bold text-purple-600"><?php echo formatCurrency($totals['avg_invoice_value']); ?></h3>
        <p class="text-xs text-gray-500 mt-1"><?php echo $totals['unique_customers']; ?> unique customers</p>
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
                <p class="text-xs mt-1"><?php echo $status['count']; ?> invoices</p>
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
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Invoices</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Customers</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Subtotal</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Discount</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Tax</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total Sales</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Collected</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Pending</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php if ($results->num_rows > 0): ?>
                    <?php while ($row = $results->fetch_assoc()): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 text-sm font-medium text-gray-900"><?php echo $row['period']; ?></td>
                            <td class="px-6 py-4 text-sm text-right"><?php echo $row['total_invoices']; ?></td>
                            <td class="px-6 py-4 text-sm text-right"><?php echo $row['unique_customers']; ?></td>
                            <td class="px-6 py-4 text-sm text-right"><?php echo formatCurrency($row['total_subtotal']); ?></td>
                            <td class="px-6 py-4 text-sm text-right text-red-600">
                                <?php echo $row['total_discount'] > 0 ? '-' . formatCurrency($row['total_discount']) : '-'; ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-right">
                                <?php echo $row['total_tax'] > 0 ? formatCurrency($row['total_tax']) : '-'; ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-right font-bold"><?php echo formatCurrency($row['total_sales']); ?></td>
                            <td class="px-6 py-4 text-sm text-right text-green-600 font-semibold">
                                <?php echo formatCurrency($row['total_paid']); ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-right text-red-600 font-semibold">
                                <?php echo formatCurrency($row['total_pending']); ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    <!-- Totals Row -->
                    <tr class="bg-gray-100 font-bold">
                        <td class="px-6 py-4 text-sm">TOTAL</td>
                        <td class="px-6 py-4 text-sm text-right"><?php echo $totals['total_invoices']; ?></td>
                        <td class="px-6 py-4 text-sm text-right"><?php echo $totals['unique_customers']; ?></td>
                        <td class="px-6 py-4 text-sm text-right"><?php echo formatCurrency($totals['total_subtotal']); ?></td>
                        <td class="px-6 py-4 text-sm text-right text-red-600">
                            <?php echo $totals['total_discount'] > 0 ? '-' . formatCurrency($totals['total_discount']) : '-'; ?>
                        </td>
                        <td class="px-6 py-4 text-sm text-right">
                            <?php echo $totals['total_tax'] > 0 ? formatCurrency($totals['total_tax']) : '-'; ?>
                        </td>
                        <td class="px-6 py-4 text-sm text-right text-blue-600"><?php echo formatCurrency($totals['total_sales']); ?></td>
                        <td class="px-6 py-4 text-sm text-right text-green-600"><?php echo formatCurrency($totals['total_paid']); ?></td>
                        <td class="px-6 py-4 text-sm text-right text-red-600"><?php echo formatCurrency($totals['total_pending']); ?></td>
                    </tr>
                <?php else: ?>
                    <tr>
                        <td colspan="9" class="px-6 py-8 text-center text-gray-500">
                            No sales data found for the selected period
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