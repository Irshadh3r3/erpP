<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';

requireLogin();

$conn = getDBConnection();
$pageTitle = 'Customers';

// Search and filter
$search = isset($_GET['search']) ? clean($_GET['search']) : '';
$area = isset($_GET['area']) ? clean($_GET['area']) : '';

// Build query
$where = "c.is_active = 1";
if (!empty($search)) {
    $where .= " AND (c.name LIKE '%$search%' OR c.customer_code LIKE '%$search%' OR c.phone LIKE '%$search%' OR c.business_name LIKE '%$search%')";
}
if (!empty($area)) {
    $where .= " AND c.area = '$area'";
}

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Get total count
$countQuery = "SELECT COUNT(*) as total FROM customers c WHERE $where";
$countResult = $conn->query($countQuery);
$totalRecords = $countResult->fetch_assoc()['total'];
$totalPages = ceil($totalRecords / $perPage);

// Get customers with purchase stats
$query = "SELECT c.*, 
          COUNT(DISTINCT s.id) as total_invoices,
          COALESCE(SUM(s.total_amount), 0) as total_purchases,
          COALESCE(SUM(s.total_amount - s.paid_amount), 0) as outstanding_balance
          FROM customers c
          LEFT JOIN sales s ON c.id = s.customer_id
          WHERE $where
          GROUP BY c.id
          ORDER BY c.created_at DESC
          LIMIT $perPage OFFSET $offset";
$customers = $conn->query($query);

// Get unique areas for filter
$areasQuery = "SELECT DISTINCT area FROM customers WHERE area IS NOT NULL AND area != '' ORDER BY area ASC";
$areas = $conn->query($areasQuery);

include '../../includes/header.php';
?>

<!-- Page Header -->
<div class="mb-6 flex items-center justify-between">
    <div>
        <h1 class="text-3xl font-bold text-gray-800">Customers</h1>
        <p class="text-gray-600">Manage your customer database</p>
    </div>
    <a href="add.php" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-semibold transition">
        + Add New Customer
    </a>
</div>

<!-- Search and Filter -->
<div class="bg-white rounded-lg shadow p-6 mb-6">
    <form method="GET" action="" class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="md:col-span-2">
            <input type="text" 
                   name="search" 
                   value="<?php echo $search; ?>"
                   placeholder="Search by name, code, phone, or business..." 
                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
            <select name="area" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">All Areas</option>
                <?php while ($areaRow = $areas->fetch_assoc()): ?>
                    <option value="<?php echo $areaRow['area']; ?>" <?php echo $area == $areaRow['area'] ? 'selected' : ''; ?>>
                        <?php echo $areaRow['area']; ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="flex gap-2">
            <button type="submit" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-semibold transition">
                Search
            </button>
            <a href="list.php" class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-4 py-2 rounded-lg font-semibold transition">
                Reset
            </a>
        </div>
    </form>
</div>

<!-- Customers Table -->
<div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Code</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contact</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Area</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Invoices</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Total Sales</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Balance</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if ($customers->num_rows > 0): ?>
                    <?php while ($customer = $customers->fetch_assoc()): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="font-mono text-sm"><?php echo $customer['customer_code']; ?></span>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm font-medium text-gray-900"><?php echo $customer['name']; ?></div>
                                <?php if ($customer['business_name']): ?>
                                    <div class="text-xs text-gray-500"><?php echo $customer['business_name']; ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4">
                                <?php if ($customer['phone']): ?>
                                    <div class="text-sm text-gray-900"><?php echo $customer['phone']; ?></div>
                                <?php endif; ?>
                                <?php if ($customer['email']): ?>
                                    <div class="text-xs text-gray-500"><?php echo $customer['email']; ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                <?php echo $customer['area'] ?: 'N/A'; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                <?php echo $customer['total_invoices']; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-900 text-right">
                                <?php echo formatCurrency($customer['total_purchases']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                                <?php if ($customer['outstanding_balance'] > 0): ?>
                                    <span class="text-red-600 font-semibold"><?php echo formatCurrency($customer['outstanding_balance']); ?></span>
                                <?php else: ?>
                                    <span class="text-green-600">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <a href="view.php?id=<?php echo $customer['id']; ?>" 
                                   class="text-blue-600 hover:text-blue-900 mr-3">View</a>
                                <a href="edit.php?id=<?php echo $customer['id']; ?>" 
                                   class="text-green-600 hover:text-green-900 mr-3">Edit</a>
                                <a href="delete.php?id=<?php echo $customer['id']; ?>" 
                                   onclick="return confirm('Are you sure you want to delete this customer?')"
                                   class="text-red-600 hover:text-red-900">Delete</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" class="px-6 py-8 text-center text-gray-500">
                            No customers found. <a href="add.php" class="text-blue-600 hover:text-blue-700">Add your first customer</a>
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
                    Showing <?php echo min($offset + 1, $totalRecords); ?> to <?php echo min($offset + $perPage, $totalRecords); ?> of <?php echo $totalRecords; ?> customers
                </div>
                <div class="flex gap-2">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>&search=<?php echo $search; ?>&area=<?php echo $area; ?>" 
                           class="px-4 py-2 bg-white border rounded-lg hover:bg-gray-50">Previous</a>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                        <a href="?page=<?php echo $i; ?>&search=<?php echo $search; ?>&area=<?php echo $area; ?>" 
                           class="px-4 py-2 border rounded-lg <?php echo $i === $page ? 'bg-blue-600 text-white' : 'bg-white hover:bg-gray-50'; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&search=<?php echo $search; ?>&area=<?php echo $area; ?>" 
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