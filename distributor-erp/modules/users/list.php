<?php
session_start();
require '../../config/database.php';
require '../../includes/functions.php';

// Admin-only access
if ($_SESSION['role'] !== 'admin') {
    $_SESSION['error_message'] = 'Access denied. Admin privileges required.';
    header('Location: ../../index.php');
    exit;
}

$conn = getDBConnection();
$pageTitle = 'Users Management';

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Search & Filter
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter_role = isset($_GET['role']) ? trim($_GET['role']) : '';
$filter_status = isset($_GET['status']) ? trim($_GET['status']) : '';

// Build WHERE clause
$where = [];
$params = [];
$types = '';

if ($search) {
    $where[] = "(username LIKE ? OR full_name LIKE ? OR email LIKE ?)";
    $search_param = "%{$search}%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'sss';
}

if ($filter_role) {
    $where[] = "role = ?";
    $params[] = $filter_role;
    $types .= 's';
}

if ($filter_status !== '') {
    $where[] = "is_active = ?";
    $params[] = $filter_status == '1' ? 1 : 0;
    $types .= 'i';
}

$where_clause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Get total count for pagination
$count_sql = "SELECT COUNT(*) as total FROM users {$where_clause}";
$stmt = $conn->prepare($count_sql);
if ($types && $params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$count_result = $stmt->get_result();
$count_row = $count_result->fetch_assoc();
$total = $count_row['total'];
$total_pages = ceil($total / $per_page);

// Get users for current page
$sql = "SELECT id, username, full_name, email, role, is_active, created_at 
        FROM users {$where_clause} 
        ORDER BY created_at DESC 
        LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);
$params[] = $per_page;
$params[] = $offset;
$types .= 'ii';
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$users = $result->fetch_all(MYSQLI_ASSOC);

include '../../includes/header.php';
?>

<div class="mb-6 flex justify-between items-center">
    <h1 class="text-3xl font-bold text-gray-800">Users Management</h1>
    <a href="add.php" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded font-semibold transition">
        + Add User
    </a>
</div>

<!-- Search & Filter Form -->
<div class="bg-white shadow-md rounded-lg p-4 mb-6">
    <form method="get" class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">Search</label>
            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                   placeholder="Username, name, or email..." class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
        
        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">Role</label>
            <select name="role" class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">All Roles</option>
                <option value="admin" <?php echo $filter_role === 'admin' ? 'selected' : ''; ?>>Admin</option>
                <option value="manager" <?php echo $filter_role === 'manager' ? 'selected' : ''; ?>>Manager</option>
                <option value="cashier" <?php echo $filter_role === 'cashier' ? 'selected' : ''; ?>>Cashier</option>
                <option value="inventory" <?php echo $filter_role === 'inventory' ? 'selected' : ''; ?>>Inventory</option>
            </select>
        </div>
        
        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">Status</label>
            <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">All Status</option>
                <option value="1" <?php echo $filter_status === '1' ? 'selected' : ''; ?>>Active</option>
                <option value="0" <?php echo $filter_status === '0' ? 'selected' : ''; ?>>Inactive</option>
            </select>
        </div>
        
        <div class="flex items-end">
            <button type="submit" class="w-full bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded font-semibold transition">
                Search
            </button>
        </div>
    </form>
</div>

<!-- Users Table -->
<div class="bg-white shadow-md rounded-lg overflow-hidden">
    <table class="w-full">
        <thead class="bg-gray-100 border-b">
            <tr>
                <th class="px-6 py-3 text-left text-sm font-semibold text-gray-700">Username</th>
                <th class="px-6 py-3 text-left text-sm font-semibold text-gray-700">Full Name</th>
                <th class="px-6 py-3 text-left text-sm font-semibold text-gray-700">Email</th>
                <th class="px-6 py-3 text-left text-sm font-semibold text-gray-700">Role</th>
                <th class="px-6 py-3 text-left text-sm font-semibold text-gray-700">Status</th>
                <th class="px-6 py-3 text-left text-sm font-semibold text-gray-700">Created</th>
                <th class="px-6 py-3 text-center text-sm font-semibold text-gray-700">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($users)): ?>
            <tr>
                <td colspan="7" class="px-6 py-4 text-center text-gray-500">No users found</td>
            </tr>
            <?php else: ?>
                <?php foreach ($users as $user): ?>
                <tr class="border-b hover:bg-gray-50">
                    <td class="px-6 py-4 text-sm text-gray-800"><?php echo htmlspecialchars($user['username']); ?></td>
                    <td class="px-6 py-4 text-sm text-gray-800"><?php echo htmlspecialchars($user['full_name']); ?></td>
                    <td class="px-6 py-4 text-sm text-gray-600"><?php echo htmlspecialchars($user['email']); ?></td>
                    <td class="px-6 py-4 text-sm">
                        <span class="px-3 py-1 rounded-full text-xs font-semibold <?php 
                            echo match($user['role']) {
                                'admin' => 'bg-red-100 text-red-700',
                                'manager' => 'bg-blue-100 text-blue-700',
                                'cashier' => 'bg-green-100 text-green-700',
                                'inventory' => 'bg-yellow-100 text-yellow-700',
                                default => 'bg-gray-100 text-gray-700'
                            };
                        ?>">
                            <?php echo strtoupper($user['role']); ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 text-sm">
                        <span class="px-3 py-1 rounded-full text-xs font-semibold <?php 
                            echo $user['is_active'] ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-700';
                        ?>">
                            <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-600"><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                    <td class="px-6 py-4 text-center text-sm">
                        <div class="flex justify-center space-x-2">
                            <a href="edit.php?id=<?php echo $user['id']; ?>" class="text-blue-600 hover:text-blue-800 font-semibold">Edit</a>
                            <a href="delete.php?id=<?php echo $user['id']; ?>" class="text-red-600 hover:text-red-800 font-semibold" onclick="return confirm('Are you sure?');">Delete</a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Pagination -->
<?php if ($total_pages > 1): ?>
<div class="flex justify-center items-center space-x-2 mt-6">
    <?php if ($page > 1): ?>
        <a href="?page=1<?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $filter_role ? '&role=' . urlencode($filter_role) : ''; ?><?php echo $filter_status !== '' ? '&status=' . urlencode($filter_status) : ''; ?>" 
           class="px-3 py-2 bg-gray-200 text-gray-800 rounded hover:bg-gray-300">First</a>
        <a href="?page=<?php echo $page - 1; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $filter_role ? '&role=' . urlencode($filter_role) : ''; ?><?php echo $filter_status !== '' ? '&status=' . urlencode($filter_status) : ''; ?>" 
           class="px-3 py-2 bg-gray-200 text-gray-800 rounded hover:bg-gray-300">Previous</a>
    <?php endif; ?>
    
    <span class="text-gray-600">Page <?php echo $page; ?> of <?php echo $total_pages; ?></span>
    
    <?php if ($page < $total_pages): ?>
        <a href="?page=<?php echo $page + 1; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $filter_role ? '&role=' . urlencode($filter_role) : ''; ?><?php echo $filter_status !== '' ? '&status=' . urlencode($filter_status) : ''; ?>" 
           class="px-3 py-2 bg-gray-200 text-gray-800 rounded hover:bg-gray-300">Next</a>
        <a href="?page=<?php echo $total_pages; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $filter_role ? '&role=' . urlencode($filter_role) : ''; ?><?php echo $filter_status !== '' ? '&status=' . urlencode($filter_status) : ''; ?>" 
           class="px-3 py-2 bg-gray-200 text-gray-800 rounded hover:bg-gray-300">Last</a>
    <?php endif; ?>
</div>
<?php endif; ?>

</main>
</div>
<?php include '../../includes/footer.php'; ?>
