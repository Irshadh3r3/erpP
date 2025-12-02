<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';

requireLogin();

$conn = getDBConnection();
$pageTitle = 'Purchases';

// Filters
$search = isset($_GET['search']) ? clean($_GET['search']) : '';
$status = isset($_GET['status']) ? clean($_GET['status']) : '';
$supplier_id = isset($_GET['supplier_id']) ? (int)$_GET['supplier_id'] : 0;
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Build query
$where = "1=1";
if (!empty($search)) {
    $where .= " AND (p.purchase_number LIKE '%$search%' OR s.name LIKE '%$search%')";
}
if (!empty($status)) {
    $where .= " AND p.payment_status = '$status'";
}
if ($supplier_id > 0) {
    $where .= " AND p.supplier_id = $supplier_id";
}
if (!empty($date_from)) {
    $where .= " AND p.purchase_date >= '$date_from'";
}
if (!empty($date_to)) {
    $where .= " AND p.purchase_date <= '$date_to'";
}

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Get total count
$countQuery = "SELECT COUNT(*) as total FROM purchases p JOIN suppliers s ON p.supplier_id = s.id WHERE $where";
$countResult = $conn->query($countQuery);
$totalRecords = $countResult->fetch_assoc()['total'];
$totalPages = ceil($totalRecords / $perPage);

// Get purchases
$query = "SELECT p.*, 
          s.name as supplier_name,
          s.supplier_code,
          u.full_name as created_by
          FROM purchases p
          JOIN suppliers s ON p.supplier_id = s.id
          LEFT JOIN users u ON p.user_id = u.id
          WHERE $where
          ORDER BY p.created_at DESC
          LIMIT $perPage OFFSET $offset";
$purchases = $conn->query($query);

// Get suppliers for filter
$suppliersQuery = "SELECT id, name, supplier_code FROM suppliers WHERE is_active = 1 ORDER BY name ASC";
$suppliers = $conn->query($suppliersQuery);

// Get stats
$statsQuery = "SELECT 
               COUNT(*) as total,
               SUM(total_amount) as total_purchases,
               SUM(paid_amount) as total_paid,
               SUM(total_amount - paid_amount) as total_pending
               FROM purchases p WHERE $where";
$stats = $conn->query($statsQuery)->fetch_assoc();

include '../../includes/header.php';
?>

<!-- Page Header -->
<div class="mb-6 flex items-center justify-between">
    <div>
        <h1 class="text-3xl font-bold text-gray-800">Purchase Orders</h1>
        <p class="text-gray-600">Manage your purchase orders and inventory</p>
    </div>
    <a href="add.php" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-semibold transition">
        + New Purchase Order
    </a>
</div>

<!-- Stats Cards -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
    <div class="bg-white rounded-lg shadow p-6">
        <p class="text-gray-500 text-sm">Total Purchase Orders</p>
        <h3 class="text-2xl font-bold text-gray-800"><?php echo $stats['total']; ?></h3>
    </div>
    
    <div class="bg-white rounded-lg shadow p-6">
        <p class="text-gray-500 text-sm">Total Purchases</p>
        <h3 class="text-2xl font-bold text-blue-600"><?php echo formatCurrency($stats['total_purchases']); ?></h3>
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
                   placeholder="Search by purchase # or supplier..." 
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
            <select name="supplier_id" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">All Suppliers</option>
                <?php while ($supplier = $suppliers->fetch_assoc()): ?>
                    <option value="<?php echo $supplier['id']; ?>" <?php echo $supplier_id == $supplier['id'] ? 'selected' : ''; ?>>
                        <?php echo $supplier['name']; ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
        
        <div class="md:col-span-2 flex gap-2">
            <input type="date" 
                   name="date_from" 
                   value="<?php echo $date_from; ?>"
                   placeholder="From"
                   class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
            <input type="date" 
                   name="date_to" 
                   value="<?php echo $date_to; ?>"
                   placeholder="To"
                   class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
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

<!-- Purchases Table -->
<div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Purchase #</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Supplier</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Paid</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if ($purchases->num_rows > 0): ?>
                    <?php while ($purchase = $purchases->fetch_assoc()): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="font-mono text-sm font-semibold"><?php echo $purchase['purchase_number']; ?></span>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm font-medium text-gray-900"><?php echo $purchase['supplier_name']; ?></div>
                                <div class="text-xs text-gray-500"><?php echo $purchase['supplier_code']; ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                <?php echo formatDate($purchase['purchase_date']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-900 text-right">
                                <?php echo formatCurrency($purchase['total_amount']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-green-600 text-right">
                                <?php echo formatCurrency($purchase['paid_amount']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php
                                $statusColors = [
                                    'paid' => 'bg-green-100 text-green-700',
                                    'partial' => 'bg-yellow-100 text-yellow-700',
                                    'unpaid' => 'bg-red-100 text-red-700'
                                ];
                                $statusClass = $statusColors[$purchase['payment_status']] ?? 'bg-gray-100 text-gray-700';
                                ?>
                                <span class="<?php echo $statusClass; ?> px-2 py-1 rounded text-xs font-semibold">
                                    <?php echo ucfirst($purchase['payment_status']); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <a href="view.php?id=<?php echo $purchase['id']; ?>" 
                                   class="text-blue-600 hover:text-blue-900">View</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="px-6 py-8 text-center text-gray-500">
                            No purchase orders found. <a href="add.php" class="text-blue-600 hover:text-blue-700">Create your first purchase order</a>
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
                    Showing <?php echo min($offset + 1, $totalRecords); ?> to <?php echo min($offset + $perPage, $totalRecords); ?> of <?php echo $totalRecords; ?> purchases
                </div>
                <div class="flex gap-2">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>&search=<?php echo $search; ?>&status=<?php echo $status; ?>&supplier_id=<?php echo $supplier_id; ?>" 
                           class="px-4 py-2 bg-white border rounded-lg hover:bg-gray-50">Previous</a>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                        <a href="?page=<?php echo $i; ?>&search=<?php echo $search; ?>&status=<?php echo $status; ?>&supplier_id=<?php echo $supplier_id; ?>" 
                           class="px-4 py-2 border rounded-lg <?php echo $i === $page ? 'bg-blue-600 text-white' : 'bg-white hover:bg-gray-50'; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&search=<?php echo $search; ?>&status=<?php echo $status; ?>&supplier_id=<?php echo $supplier_id; ?>" 
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