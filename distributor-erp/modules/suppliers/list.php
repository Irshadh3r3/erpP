<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';

requireLogin();

$conn = getDBConnection();
$pageTitle = 'Suppliers';

// Search
$search = isset($_GET['search']) ? clean($_GET['search']) : '';

// Build query
$where = "s.is_active = 1";
if (!empty($search)) {
    $where .= " AND (s.name LIKE '%$search%' OR s.supplier_code LIKE '%$search%' OR s.phone LIKE '%$search%')";
}

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Get total count
$countQuery = "SELECT COUNT(*) as total FROM suppliers s WHERE $where";
$countResult = $conn->query($countQuery);
$totalRecords = $countResult->fetch_assoc()['total'];
$totalPages = ceil($totalRecords / $perPage);

// Get suppliers with purchase stats
$query = "SELECT s.*, 
          COUNT(DISTINCT p.id) as total_purchases,
          COALESCE(SUM(p.total_amount), 0) as total_amount,
          COALESCE(SUM(p.total_amount - p.paid_amount), 0) as outstanding_balance
          FROM suppliers s
          LEFT JOIN purchases p ON s.id = p.supplier_id
          WHERE $where
          GROUP BY s.id
          ORDER BY s.created_at DESC
          LIMIT $perPage OFFSET $offset";
$suppliers = $conn->query($query);

include '../../includes/header.php';
?>

<!-- Page Header -->
<div class="mb-6 flex items-center justify-between">
    <div>
        <h1 class="text-3xl font-bold text-gray-800">Suppliers</h1>
        <p class="text-gray-600">Manage your supplier database</p>
    </div>
    <a href="add.php" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-semibold transition">
        + Add New Supplier
    </a>
</div>

<!-- Search -->
<div class="bg-white rounded-lg shadow p-6 mb-6">
    <form method="GET" action="" class="flex gap-4">
        <input type="text" 
               name="search" 
               value="<?php echo $search; ?>"
               placeholder="Search by name, code, or phone..." 
               class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg font-semibold transition">
            Search
        </button>
        <a href="list.php" class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-6 py-2 rounded-lg font-semibold transition">
            Reset
        </a>
    </form>
</div>

<!-- Suppliers Table -->
<div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Code</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Supplier</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contact</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">City</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Purchases</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Total Amount</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Balance</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if ($suppliers->num_rows > 0): ?>
                    <?php while ($supplier = $suppliers->fetch_assoc()): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="font-mono text-sm"><?php echo $supplier['supplier_code']; ?></span>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm font-medium text-gray-900"><?php echo $supplier['name']; ?></div>
                                <?php if ($supplier['contact_person']): ?>
                                    <div class="text-xs text-gray-500">Contact: <?php echo $supplier['contact_person']; ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4">
                                <?php if ($supplier['phone']): ?>
                                    <div class="text-sm text-gray-900"><?php echo $supplier['phone']; ?></div>
                                <?php endif; ?>
                                <?php if ($supplier['email']): ?>
                                    <div class="text-xs text-gray-500"><?php echo $supplier['email']; ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                <?php echo $supplier['city'] ?: 'N/A'; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                <?php echo $supplier['total_purchases']; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-900 text-right">
                                <?php echo formatCurrency($supplier['total_amount']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                                <?php if ($supplier['outstanding_balance'] > 0): ?>
                                    <span class="text-red-600 font-semibold"><?php echo formatCurrency($supplier['outstanding_balance']); ?></span>
                                <?php else: ?>
                                    <span class="text-green-600">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <a href="view.php?id=<?php echo $supplier['id']; ?>" 
                                   class="text-blue-600 hover:text-blue-900 mr-3">View</a>
                                <a href="edit.php?id=<?php echo $supplier['id']; ?>" 
                                   class="text-green-600 hover:text-green-900 mr-3">Edit</a>
                                <a href="delete.php?id=<?php echo $supplier['id']; ?>" 
                                   onclick="return confirm('Are you sure you want to delete this supplier?')"
                                   class="text-red-600 hover:text-red-900">Delete</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" class="px-6 py-8 text-center text-gray-500">
                            No suppliers found. <a href="add.php" class="text-blue-600 hover:text-blue-700">Add your first supplier</a>
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
                    Showing <?php echo min($offset + 1, $totalRecords); ?> to <?php echo min($offset + $perPage, $totalRecords); ?> of <?php echo $totalRecords; ?> suppliers
                </div>
                <div class="flex gap-2">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>&search=<?php echo $search; ?>" 
                           class="px-4 py-2 bg-white border rounded-lg hover:bg-gray-50">Previous</a>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                        <a href="?page=<?php echo $i; ?>&search=<?php echo $search; ?>" 
                           class="px-4 py-2 border rounded-lg <?php echo $i === $page ? 'bg-blue-600 text-white' : 'bg-white hover:bg-gray-50'; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&search=<?php echo $search; ?>" 
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