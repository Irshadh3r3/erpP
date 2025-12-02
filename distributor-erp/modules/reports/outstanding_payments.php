<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';

requireLogin();

$conn = getDBConnection();
$pageTitle = 'Outstanding Payments Report';

// Filters
$customer_id = isset($_GET['customer_id']) ? (int)$_GET['customer_id'] : 0;
$age_filter = isset($_GET['age_filter']) ? $_GET['age_filter'] : 'all';
$min_amount = isset($_GET['min_amount']) ? (float)$_GET['min_amount'] : 0;

// Build query for outstanding invoices
$where = "s.payment_status != 'paid'";
if ($customer_id > 0) {
    $where .= " AND s.customer_id = $customer_id";
}
if ($min_amount > 0) {
    $where .= " AND (s.total_amount - s.paid_amount) >= $min_amount";
}

// Age filter
$ageCondition = '';
switch ($age_filter) {
    case 'current':
        $ageCondition = "AND DATEDIFF(CURDATE(), s.sale_date) <= 30";
        break;
    case '30-60':
        $ageCondition = "AND DATEDIFF(CURDATE(), s.sale_date) BETWEEN 31 AND 60";
        break;
    case '60-90':
        $ageCondition = "AND DATEDIFF(CURDATE(), s.sale_date) BETWEEN 61 AND 90";
        break;
    case 'over90':
        $ageCondition = "AND DATEDIFF(CURDATE(), s.sale_date) > 90";
        break;
}

$query = "SELECT 
          s.id,
          s.invoice_number,
          s.sale_date,
          s.total_amount,
          s.paid_amount,
          (s.total_amount - s.paid_amount) as outstanding_amount,
          DATEDIFF(CURDATE(), s.sale_date) as days_outstanding,
          c.id as customer_id,
          c.customer_code,
          c.name as customer_name,
          c.phone as customer_phone,
          c.credit_limit,
          c.current_balance
          FROM sales s
          JOIN customers c ON s.customer_id = c.id
          WHERE $where $ageCondition
          ORDER BY days_outstanding DESC, outstanding_amount DESC";

$invoices = $conn->query($query);

// Get customers for filter
$customersQuery = "SELECT id, name, customer_code FROM customers WHERE is_active = 1 ORDER BY name ASC";
$customers = $conn->query($customersQuery);

// Calculate aging summary
$agingSummary = "SELECT 
                 SUM(CASE WHEN DATEDIFF(CURDATE(), sale_date) <= 30 THEN (total_amount - paid_amount) ELSE 0 END) as current_30,
                 SUM(CASE WHEN DATEDIFF(CURDATE(), sale_date) BETWEEN 31 AND 60 THEN (total_amount - paid_amount) ELSE 0 END) as days_30_60,
                 SUM(CASE WHEN DATEDIFF(CURDATE(), sale_date) BETWEEN 61 AND 90 THEN (total_amount - paid_amount) ELSE 0 END) as days_60_90,
                 SUM(CASE WHEN DATEDIFF(CURDATE(), sale_date) > 90 THEN (total_amount - paid_amount) ELSE 0 END) as over_90,
                 SUM(total_amount - paid_amount) as total_outstanding,
                 COUNT(*) as total_invoices,
                 COUNT(DISTINCT customer_id) as unique_customers
                 FROM sales
                 WHERE payment_status != 'paid'";
$aging = $conn->query($agingSummary)->fetch_assoc();

// Get customer-wise summary
$customerSummaryQuery = "SELECT 
                         c.id,
                         c.customer_code,
                         c.name as customer_name,
                         c.phone,
                         c.credit_limit,
                         COUNT(s.id) as outstanding_invoices,
                         SUM(s.total_amount - s.paid_amount) as total_outstanding,
                         MAX(DATEDIFF(CURDATE(), s.sale_date)) as oldest_invoice_days
                         FROM customers c
                         JOIN sales s ON c.id = s.customer_id
                         WHERE s.payment_status != 'paid'
                         GROUP BY c.id
                         ORDER BY total_outstanding DESC
                         LIMIT 10";
$topCustomers = $conn->query($customerSummaryQuery);

include '../../includes/header.php';
?>

<!-- Page Header -->
<div class="mb-6">
    <h1 class="text-3xl font-bold text-gray-800">Outstanding Payments Report</h1>
    <p class="text-gray-600">Track and manage accounts receivable</p>
</div>

<!-- Filters -->
<div class="bg-white rounded-lg shadow p-6 mb-6">
    <form method="GET" action="" class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">Customer</label>
            <select name="customer_id" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="0">All Customers</option>
                <?php 
                $customers->data_seek(0);
                while ($customer = $customers->fetch_assoc()): 
                ?>
                    <option value="<?php echo $customer['id']; ?>" <?php echo $customer_id == $customer['id'] ? 'selected' : ''; ?>>
                        <?php echo $customer['name']; ?> (<?php echo $customer['customer_code']; ?>)
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">Age Filter</label>
            <select name="age_filter" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="all" <?php echo $age_filter === 'all' ? 'selected' : ''; ?>>All Ages</option>
                <option value="current" <?php echo $age_filter === 'current' ? 'selected' : ''; ?>>0-30 Days</option>
                <option value="30-60" <?php echo $age_filter === '30-60' ? 'selected' : ''; ?>>31-60 Days</option>
                <option value="60-90" <?php echo $age_filter === '60-90' ? 'selected' : ''; ?>>61-90 Days</option>
                <option value="over90" <?php echo $age_filter === 'over90' ? 'selected' : ''; ?>>Over 90 Days</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">Min Amount</label>
            <input type="number" 
                   name="min_amount" 
                   value="<?php echo $min_amount; ?>"
                   step="0.01"
                   min="0"
                   placeholder="0.00"
                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
        <div class="flex items-end gap-2">
            <button type="submit" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-semibold transition">
                Filter
            </button>
            <a href="outstanding_payments.php" class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-4 py-2 rounded-lg font-semibold transition">
                Reset
            </a>
        </div>
    </form>
</div>

<!-- Summary Cards -->
<div class="grid grid-cols-1 md:grid-cols-5 gap-6 mb-6">
    <div class="bg-white rounded-lg shadow p-6">
        <p class="text-gray-500 text-sm">Total Outstanding</p>
        <h3 class="text-2xl font-bold text-red-600"><?php echo formatCurrency($aging['total_outstanding']); ?></h3>
        <p class="text-xs text-gray-500 mt-1"><?php echo $aging['total_invoices']; ?> invoices</p>
    </div>
    
    <div class="bg-white rounded-lg shadow p-6">
        <p class="text-gray-500 text-sm">0-30 Days</p>
        <h3 class="text-2xl font-bold text-green-600"><?php echo formatCurrency($aging['current_30']); ?></h3>
        <p class="text-xs text-gray-500 mt-1">Current</p>
    </div>
    
    <div class="bg-white rounded-lg shadow p-6">
        <p class="text-gray-500 text-sm">31-60 Days</p>
        <h3 class="text-2xl font-bold text-yellow-600"><?php echo formatCurrency($aging['days_30_60']); ?></h3>
        <p class="text-xs text-gray-500 mt-1">Attention needed</p>
    </div>
    
    <div class="bg-white rounded-lg shadow p-6">
        <p class="text-gray-500 text-sm">61-90 Days</p>
        <h3 class="text-2xl font-bold text-orange-600"><?php echo formatCurrency($aging['days_60_90']); ?></h3>
        <p class="text-xs text-gray-500 mt-1">Overdue</p>
    </div>
    
    <div class="bg-white rounded-lg shadow p-6">
        <p class="text-gray-500 text-sm">Over 90 Days</p>
        <h3 class="text-2xl font-bold text-red-700"><?php echo formatCurrency($aging['over_90']); ?></h3>
        <p class="text-xs text-gray-500 mt-1">Critical</p>
    </div>
</div>

<!-- Aging Analysis Chart -->
<div class="bg-white rounded-lg shadow p-6 mb-6">
    <h3 class="text-lg font-bold text-gray-800 mb-4">Aging Analysis</h3>
    <div class="grid grid-cols-4 gap-2">
        <?php
        $total = $aging['total_outstanding'];
        $percentages = [
            ['label' => '0-30 Days', 'amount' => $aging['current_30'], 'color' => 'bg-green-500'],
            ['label' => '31-60 Days', 'amount' => $aging['days_30_60'], 'color' => 'bg-yellow-500'],
            ['label' => '61-90 Days', 'amount' => $aging['days_60_90'], 'color' => 'bg-orange-500'],
            ['label' => 'Over 90 Days', 'amount' => $aging['over_90'], 'color' => 'bg-red-600']
        ];
        
        foreach ($percentages as $item):
            $percentage = $total > 0 ? ($item['amount'] / $total) * 100 : 0;
        ?>
            <div class="text-center">
                <div class="<?php echo $item['color']; ?> rounded-t-lg p-4 text-white">
                    <p class="text-xs font-semibold"><?php echo $item['label']; ?></p>
                    <p class="text-2xl font-bold mt-2"><?php echo number_format($percentage, 1); ?>%</p>
                </div>
                <div class="bg-gray-100 rounded-b-lg p-2">
                    <p class="text-sm font-semibold text-gray-800"><?php echo formatCurrency($item['amount']); ?></p>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Top 10 Customers with Outstanding -->
<div class="bg-white rounded-lg shadow p-6 mb-6">
    <h3 class="text-lg font-bold text-gray-800 mb-4">Top 10 Customers by Outstanding Amount</h3>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Customer</th>
                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Invoices</th>
                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Outstanding</th>
                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Credit Limit</th>
                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Oldest (Days)</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php while ($cust = $topCustomers->fetch_assoc()): ?>
                    <?php
                    $utilizationPercent = $cust['credit_limit'] > 0 ? 
                                         ($cust['total_outstanding'] / $cust['credit_limit']) * 100 : 0;
                    $utilizationColor = $utilizationPercent >= 90 ? 'text-red-600' : 
                                       ($utilizationPercent >= 70 ? 'text-orange-600' : 'text-green-600');
                    ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3">
                            <div class="text-sm font-medium text-gray-900"><?php echo $cust['customer_name']; ?></div>
                            <div class="text-xs text-gray-500"><?php echo $cust['customer_code']; ?></div>
                        </td>
                        <td class="px-4 py-3 text-sm text-right"><?php echo $cust['outstanding_invoices']; ?></td>
                        <td class="px-4 py-3 text-sm text-right font-bold text-red-600">
                            <?php echo formatCurrency($cust['total_outstanding']); ?>
                        </td>
                        <td class="px-4 py-3 text-sm text-right">
                            <?php echo formatCurrency($cust['credit_limit']); ?>
                            <div class="text-xs <?php echo $utilizationColor; ?> font-semibold">
                                <?php echo number_format($utilizationPercent, 0); ?>% used
                            </div>
                        </td>
                        <td class="px-4 py-3 text-sm text-right">
                            <?php
                            $daysColor = $cust['oldest_invoice_days'] > 90 ? 'text-red-600' :
                                        ($cust['oldest_invoice_days'] > 60 ? 'text-orange-600' : 'text-yellow-600');
                            ?>
                            <span class="<?php echo $daysColor; ?> font-semibold">
                                <?php echo $cust['oldest_invoice_days']; ?> days
                            </span>
                        </td>
                        <td class="px-4 py-3 text-sm">
                            <a href="../customers/view.php?id=<?php echo $cust['id']; ?>" 
                               class="text-blue-600 hover:text-blue-800">View Details</a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Detailed Invoice List -->
<div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="p-6 border-b">
        <h3 class="text-lg font-bold text-gray-800">Outstanding Invoices Details</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Invoice #</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Customer</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Days</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Paid</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Outstanding</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php if ($invoices->num_rows > 0): ?>
                    <?php while ($invoice = $invoices->fetch_assoc()): ?>
                        <?php
                        $ageColor = $invoice['days_outstanding'] > 90 ? 'text-red-600 font-bold' :
                                   ($invoice['days_outstanding'] > 60 ? 'text-orange-600 font-semibold' :
                                   ($invoice['days_outstanding'] > 30 ? 'text-yellow-600' : 'text-green-600'));
                        ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4">
                                <a href="../sales/view.php?id=<?php echo $invoice['id']; ?>" 
                                   class="text-blue-600 hover:text-blue-800 font-mono text-sm">
                                    <?php echo $invoice['invoice_number']; ?>
                                </a>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm font-medium text-gray-900"><?php echo $invoice['customer_name']; ?></div>
                                <div class="text-xs text-gray-500"><?php echo $invoice['customer_code']; ?></div>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-600"><?php echo formatDate($invoice['sale_date']); ?></td>
                            <td class="px-6 py-4 text-sm text-right">
                                <span class="<?php echo $ageColor; ?>">
                                    <?php echo $invoice['days_outstanding']; ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm text-right"><?php echo formatCurrency($invoice['total_amount']); ?></td>
                            <td class="px-6 py-4 text-sm text-right text-green-600">
                                <?php echo formatCurrency($invoice['paid_amount']); ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-right font-bold text-red-600">
                                <?php echo formatCurrency($invoice['outstanding_amount']); ?>
                            </td>
                            <td class="px-6 py-4 text-sm">
                                <?php
                                if ($invoice['days_outstanding'] > 90) {
                                    echo '<span class="bg-red-100 text-red-700 px-2 py-1 rounded text-xs font-semibold">Critical</span>';
                                } elseif ($invoice['days_outstanding'] > 60) {
                                    echo '<span class="bg-orange-100 text-orange-700 px-2 py-1 rounded text-xs font-semibold">Overdue</span>';
                                } elseif ($invoice['days_outstanding'] > 30) {
                                    echo '<span class="bg-yellow-100 text-yellow-700 px-2 py-1 rounded text-xs font-semibold">Due</span>';
                                } else {
                                    echo '<span class="bg-green-100 text-green-700 px-2 py-1 rounded text-xs font-semibold">Current</span>';
                                }
                                ?>
                            </td>
                            <td class="px-6 py-4 text-sm">
                                <a href="../sales/view.php?id=<?php echo $invoice['id']; ?>" 
                                   class="text-blue-600 hover:text-blue-900">View</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="9" class="px-6 py-8 text-center text-gray-500">
                            No outstanding invoices found
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