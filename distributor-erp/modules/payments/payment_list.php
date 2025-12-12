<?php
// distributor-erp/modules/payments/payment_list.php
require_once '../../config/database.php';
require_once '../../includes/functions.php';

requireLogin();

$conn = getDBConnection();
$pageTitle = 'Payment History';

// Filters
$search = isset($_GET['search']) ? clean($_GET['search']) : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$payment_method = isset($_GET['payment_method']) ? clean($_GET['payment_method']) : '';

// Build query
$where = "1=1";
if (!empty($search)) {
    $where .= " AND (s.invoice_number LIKE '%$search%' OR c.name LIKE '%$search%' OR p.reference_number LIKE '%$search%')";
}
if (!empty($date_from)) {
    $where .= " AND p.payment_date >= '$date_from'";
}
if (!empty($date_to)) {
    $where .= " AND p.payment_date <= '$date_to'";
}
if (!empty($payment_method)) {
    $where .= " AND p.payment_method = '$payment_method'";
}

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Get payments
$query = "SELECT 
          p.*,
          s.invoice_number,
          s.total_amount as invoice_total,
          c.name as customer_name,
          c.customer_code,
          u.full_name as recorded_by
          FROM payments p
          JOIN sales s ON p.invoice_id = s.id
          JOIN customers c ON s.customer_id = c.id
          LEFT JOIN users u ON p.user_id = u.id
          WHERE $where
          ORDER BY p.payment_date DESC, p.created_at DESC
          LIMIT $perPage OFFSET $offset";

$payments = $conn->query($query);

// Get total count
$countQuery = "SELECT COUNT(*) as total 
               FROM payments p
               JOIN sales s ON p.invoice_id = s.id
               JOIN customers c ON s.customer_id = c.id
               WHERE $where";
$totalRecords = $conn->query($countQuery)->fetch_assoc()['total'];
$totalPages = ceil($totalRecords / $perPage);

// Get summary stats
$statsQuery = "SELECT 
               COUNT(*) as total_payments,
               SUM(payment_amount) as total_collected
               FROM payments p
               WHERE $where";
$stats = $conn->query($statsQuery)->fetch_assoc();

// Get today's collections
$todayQuery = "SELECT 
               COUNT(*) as count,
               SUM(payment_amount) as amount
               FROM payments
               WHERE DATE(payment_date) = CURDATE()";
$today = $conn->query($todayQuery)->fetch_assoc();

include '../../includes/header.php';
?>

<!-- Page Header -->
<div class="mb-6 flex items-center justify-between">
    <div>
        <h1 class="text-3xl font-bold text-gray-800">Payment History</h1>
        <p class="text-gray-600">View and manage payment records</p>
    </div>
    <a href="record_payment.php" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-semibold transition">
        + Record Payment
    </a>
</div>

<!-- Summary Cards -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
    <div class="bg-white rounded-lg shadow p-6">
        <p class="text-gray-500 text-sm">Total Collected (Filtered)</p>
        <h3 class="text-2xl font-bold text-green-600"><?php echo formatCurrency($stats['total_collected']); ?></h3>
        <p class="text-xs text-gray-500 mt-1"><?php echo $stats['total_payments']; ?> payments</p>
    </div>
    
    <div class="bg-white rounded-lg shadow p-6">
        <p class="text-gray-500 text-sm">Today's Collections</p>
        <h3 class="text-2xl font-bold text-blue-600"><?php echo formatCurrency($today['amount']); ?></h3>
        <p class="text-xs text-gray-500 mt-1"><?php echo $today['count']; ?> payments today</p>
    </div>
    
    <?php
    $outstandingQuery = "SELECT SUM(total_amount - paid_amount) as outstanding FROM sales WHERE payment_status != 'paid'";
    $outstanding = $conn->query($outstandingQuery)->fetch_assoc()['outstanding'];
    ?>
    
    <div class="bg-white rounded-lg shadow p-6">
        <p class="text-gray-500 text-sm">Still Outstanding</p>
        <h3 class="text-2xl font-bold text-red-600"><?php echo formatCurrency($outstanding); ?></h3>
        <p class="text-xs text-gray-500 mt-1">Pending collections</p>
    </div>
</div>

<!-- Filters -->
<div class="bg-white rounded-lg shadow p-6 mb-6">
    <form method="GET" action="" class="grid grid-cols-1 md:grid-cols-6 gap-4">
        <div class="md:col-span-2">
            <input type="text" 
                   name="search" 
                   value="<?php echo $search; ?>"
                   placeholder="Search by invoice, customer, or reference..." 
                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
            <input type="date" 
                   name="date_from" 
                   value="<?php echo $date_from; ?>"
                   placeholder="From Date"
                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
            <input type="date" 
                   name="date_to" 
                   value="<?php echo $date_to; ?>"
                   placeholder="To Date"
                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
            <select name="payment_method" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">All Methods</option>
                <option value="cash" <?php echo $payment_method === 'cash' ? 'selected' : ''; ?>>Cash</option>
                <option value="bank_transfer" <?php echo $payment_method === 'bank_transfer' ? 'selected' : ''; ?>>Bank Transfer</option>
                <option value="cheque" <?php echo $payment_method === 'cheque' ? 'selected' : ''; ?>>Cheque</option>
                <option value="credit_card" <?php echo $payment_method === 'credit_card' ? 'selected' : ''; ?>>Credit Card</option>
                <option value="online" <?php echo $payment_method === 'online' ? 'selected' : ''; ?>>Online</option>
            </select>
        </div>
        <div class="flex gap-2">
            <button type="submit" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-semibold transition">
                Filter
            </button>
            <a href="payment_list.php" class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-4 py-2 rounded-lg font-semibold transition">
                Reset
            </a>
        </div>
    </form>
</div>

<!-- Payments Table -->
<div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Payment Date</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Invoice #</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Customer</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Payment Amount</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Method</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Reference</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Recorded By</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php if ($payments->num_rows > 0): ?>
                    <?php while ($payment = $payments->fetch_assoc()): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?php echo formatDate($payment['payment_date']); ?>
                            </td>
                            <td class="px-6 py-4">
                                <a href="../sales/view.php?id=<?php echo $payment['invoice_id']; ?>" 
                                   class="text-blue-600 hover:text-blue-800 font-mono text-sm">
                                    <?php echo $payment['invoice_number']; ?>
                                </a>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm font-medium text-gray-900"><?php echo $payment['customer_name']; ?></div>
                                <div class="text-xs text-gray-500"><?php echo $payment['customer_code']; ?></div>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <span class="text-lg font-bold text-green-600"><?php echo formatCurrency($payment['payment_amount']); ?></span>
                            </td>
                            <td class="px-6 py-4 text-sm">
                                <span class="bg-blue-100 text-blue-700 px-2 py-1 rounded text-xs font-semibold">
                                    <?php echo ucwords(str_replace('_', ' ', $payment['payment_method'])); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-600">
                                <?php echo $payment['reference_number'] ?: 'N/A'; ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-600">
                                <?php echo $payment['recorded_by'] ?? 'System'; ?>
                            </td>
                            <td class="px-6 py-4 text-sm">
                                <a href="payment_receipt.php?id=<?php echo $payment['id']; ?>" 
                                   target="_blank"
                                   class="text-blue-600 hover:text-blue-900">Receipt</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" class="px-6 py-8 text-center text-gray-500">
                            No payments found. <a href="record_payment.php" class="text-blue-600 hover:text-blue-700">Record your first payment</a>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
        <div class="bg-gray-50 px-6 py-4 border-t">
            <div class="flex items-center justify-between">
                <div class="text-sm text-gray-600">
                    Showing <?php echo min($offset + 1, $totalRecords); ?> to <?php echo min($offset + $perPage, $totalRecords); ?> of <?php echo $totalRecords; ?> payments
                </div>
                <div class="flex gap-2">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>&search=<?php echo $search; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>&payment_method=<?php echo $payment_method; ?>" 
                           class="px-4 py-2 bg-white border rounded-lg hover:bg-gray-50">Previous</a>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                        <a href="?page=<?php echo $i; ?>&search=<?php echo $search; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>&payment_method=<?php echo $payment_method; ?>" 
                           class="px-4 py-2 border rounded-lg <?php echo $i === $page ? 'bg-blue-600 text-white' : 'bg-white hover:bg-gray-50'; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&search=<?php echo $search; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>&payment_method=<?php echo $payment_method; ?>" 
                           class="px-4 py-2 bg-white border rounded-lg hover:bg-gray-50">Next</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php
include '../../includes/footer.php';
?>