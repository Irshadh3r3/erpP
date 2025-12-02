<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';

requireLogin();

$conn = getDBConnection();
$pageTitle = 'Sales / Invoices';

// Filters
$search = isset($_GET['search']) ? clean($_GET['search']) : '';
$status = isset($_GET['status']) ? clean($_GET['status']) : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Build query
$where = "1=1";
if (!empty($search)) {
    $where .= " AND (s.invoice_number LIKE '%$search%' OR c.name LIKE '%$search%')";
}
if (!empty($status)) {
    $where .= " AND s.payment_status = '$status'";
}
if (!empty($date_from)) {
    $where .= " AND s.sale_date >= '$date_from'";
}
if (!empty($date_to)) {
    $where .= " AND s.sale_date <= '$date_to'";
}

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Get total count
$countQuery = "SELECT COUNT(*) as total FROM sales s JOIN customers c ON s.customer_id = c.id WHERE $where";
$countResult = $conn->query($countQuery);
$totalRecords = $countResult->fetch_assoc()['total'];
$totalPages = ceil($totalRecords / $perPage);

// Get sales
$query = "SELECT s.*, 
          c.name as customer_name,
          c.customer_code,
          u.full_name as created_by
          FROM sales s
          JOIN customers c ON s.customer_id = c.id
          LEFT JOIN users u ON s.user_id = u.id
          WHERE $where
          ORDER BY s.created_at DESC
          LIMIT $perPage OFFSET $offset";
$sales = $conn->query($query);

// Get stats
$statsQuery = "SELECT 
               COUNT(*) as total,
               SUM(total_amount) as total_sales,
               SUM(paid_amount) as total_paid,
               SUM(total_amount - paid_amount) as total_pending
               FROM sales s WHERE $where";
$stats = $conn->query($statsQuery)->fetch_assoc();

include '../../includes/header.php';
?>

<!-- Page Header -->
<div class="mb-6 flex items-center justify-between">
    <div>
        <h1 class="text-3xl font-bold text-gray-800">Sales / Invoices</h1>
        <p class="text-gray-600">Manage your sales and invoices</p>
    </div>
    <a href="../bookings/list.php" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-semibold transition">
        View Bookings
    </a>
</div>

<!-- Stats Cards -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
    <div class="bg-white rounded-lg shadow p-6">
        <p class="text-gray-500 text-sm">Total Invoices</p>
        <h3 class="text-2xl font-bold text-gray-800"><?php echo $stats['total']; ?></h3>
    </div>
    
    <div class="bg-white rounded-lg shadow p-6">
        <p class="text-gray-500 text-sm">Total Sales</p>
        <h3 class="text-2xl font-bold text-blue-600"><?php echo formatCurrency($stats['total_sales']); ?></h3>
    </div>
    
    <div class="bg-white rounded-lg shadow p-6">
        <p class="text-gray-500 text-sm">Amount Paid</p>
        <h3 class="text-2xl font-bold text-green-600"><?php echo formatCurrency($stats['total_paid']); ?></h3>
    </div>
    
    <div class="bg-white rounded-lg shadow p-6">
        <p class="text-gray-500 text-sm">Pending Amount</p>
        <h3 class="text-2xl font-bold text-red-600"><?php echo formatCurrency($stats['total_pending']); ?></h3>
    </div>
</div>

<!-- Filters -->
<div class="bg-white rounded-lg shadow p-6 mb-6">
    <form method="GET" action="" class="grid grid-cols-1 md:grid-cols-6 gap-4">
        <div class="md:col-span-2">
            <input type="text" 
                   name="search" 
                   value="<?php echo $search; ?>"
                   placeholder="Search by invoice # or customer..." 
                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
        
        <div>
            <select name="status" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">All Status</option>
                <option value="paid" <?php echo $status === 'paid' ? 'selected' : ''; ?>>Paid</option>
                <option value="partial" <?php echo $status === 'partial' ? 'selected' : ''; ?>>Partial</option>
                <option value="unpaid" <?php echo $status === 'unpaid' ? 'selected' : ''; ?>>Unpaid</option>
            </select>
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
        
        <div class="flex gap-2">
            <button type="submit" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-semibold transition">
                Filter
            </button>
            <a href="list.php" class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-4 py-2 rounded-lg font-semibold transition">
                Reset
            </a>
        </div>
    </form>
</div>

<!-- Sales Table -->
<div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Invoice #</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Customer</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Paid</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if ($sales->num_rows > 0): ?>
                    <?php while ($sale = $sales->fetch_assoc()): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="font-mono text-sm font-semibold"><?php echo $sale['invoice_number']; ?></span>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm font-medium text-gray-900"><?php echo $sale['customer_name']; ?></div>
                                <div class="text-xs text-gray-500"><?php echo $sale['customer_code']; ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                <?php echo formatDate($sale['sale_date']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-900 text-right">
                                <?php echo formatCurrency($sale['total_amount']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-green-600 text-right">
                                <?php echo formatCurrency($sale['paid_amount']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php
                                $statusColors = [
                                    'paid' => 'bg-green-100 text-green-700',
                                    'partial' => 'bg-yellow-100 text-yellow-700',
                                    'unpaid' => 'bg-red-100 text-red-700'
                                ];
                                $statusClass = $statusColors[$sale['payment_status']] ?? 'bg-gray-100 text-gray-700';
                                ?>
                                <span class="<?php echo $statusClass; ?> px-2 py-1 rounded text-xs font-semibold">
                                    <?php echo ucfirst($sale['payment_status']); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <a href="invoice_print.php?id=<?php echo $sale['id']; ?>" 
                                   class="text-blue-600 hover:text-blue-900">View/Print</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="px-6 py-8 text-center text-gray-500">
                            No sales found. Start by converting bookings to invoices.
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
                    Showing <?php echo min($offset + 1, $totalRecords); ?> to <?php echo min($offset + $perPage, $totalRecords); ?> of <?php echo $totalRecords; ?> sales
                </div>
                <div class="flex gap-2">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>&search=<?php echo $search; ?>&status=<?php echo $status; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>" 
                           class="px-4 py-2 bg-white border rounded-lg hover:bg-gray-50">Previous</a>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                        <a href="?page=<?php echo $i; ?>&search=<?php echo $search; ?>&status=<?php echo $status; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>" 
                           class="px-4 py-2 border rounded-lg <?php echo $i === $page ? 'bg-blue-600 text-white' : 'bg-white hover:bg-gray-50'; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&search=<?php echo $search; ?>&status=<?php echo $status; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>" 
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