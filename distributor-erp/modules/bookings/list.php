<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';

requireLogin();

$conn = getDBConnection();
$pageTitle = 'Bookings';

// Filters
$search = isset($_GET['search']) ? clean($_GET['search']) : '';
$status = isset($_GET['status']) ? clean($_GET['status']) : '';
$booker_id = isset($_GET['booker_id']) ? (int)$_GET['booker_id'] : 0;
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Build query
$where = "1=1";
if (!empty($search)) {
    $where .= " AND (b.booking_number LIKE '%$search%' OR b.customer_name LIKE '%$search%' OR b.customer_phone LIKE '%$search%')";
}
if (!empty($status)) {
    $where .= " AND b.status = '$status'";
}
if ($booker_id > 0) {
    $where .= " AND b.booker_id = $booker_id";
}
if (!empty($date_from)) {
    $where .= " AND b.booking_date >= '$date_from'";
}
if (!empty($date_to)) {
    $where .= " AND b.booking_date <= '$date_to'";
}

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Get total count
$countQuery = "SELECT COUNT(*) as total FROM bookings b WHERE $where";
$countResult = $conn->query($countQuery);
$totalRecords = $countResult->fetch_assoc()['total'];
$totalPages = ceil($totalRecords / $perPage);

// Get bookings
$query = "SELECT b.*, 
          bk.name as booker_name,
          bk.booker_code,
          u.full_name as created_by
          FROM bookings b
          JOIN bookers bk ON b.booker_id = bk.id
          LEFT JOIN users u ON b.user_id = u.id
          WHERE $where
          ORDER BY b.created_at DESC
          LIMIT $perPage OFFSET $offset";
$bookings = $conn->query($query);

// Get bookers for filter
$bookersQuery = "SELECT id, name, booker_code FROM bookers WHERE is_active = 1 ORDER BY name ASC";
$bookers = $conn->query($bookersQuery);

include '../../includes/header.php';
?>

<!-- Page Header -->
<div class="mb-6 flex items-center justify-between">
    <div>
        <h1 class="text-3xl font-bold text-gray-800">Bookings / Orders</h1>
        <p class="text-gray-600">Manage orders taken by bookers</p>
    </div>
    <div>
    <a href="loadsheet.php" class="bg-gray-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-semibold transition" style="margin-right: 10px;">
        Load Sheet
    </a>
    <a href="add.php" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-semibold transition">
        + New Booking
    </a>
    </div>
</div>

<!-- Stats Cards -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
    <?php
    $statsQuery = "SELECT 
               COUNT(*) as total,
               SUM(CASE WHEN b.status = 'pending' THEN 1 ELSE 0 END) as pending,
               SUM(CASE WHEN b.status = 'confirmed' THEN 1 ELSE 0 END) as confirmed,
               SUM(CASE WHEN b.status = 'invoiced' THEN 1 ELSE 0 END) as invoiced,
               SUM(b.total_amount) as total_amount
               FROM bookings b
               WHERE $where";

    $stats = $conn->query($statsQuery)->fetch_assoc();
    ?>
    
    <div class="bg-white rounded-lg shadow p-6">
        <p class="text-gray-500 text-sm">Total Bookings</p>
        <h3 class="text-2xl font-bold text-gray-800"><?php echo $stats['total']; ?></h3>
    </div>
    
    <div class="bg-white rounded-lg shadow p-6">
        <p class="text-gray-500 text-sm">Pending</p>
        <h3 class="text-2xl font-bold text-yellow-600"><?php echo $stats['pending']; ?></h3>
    </div>
    
    <div class="bg-white rounded-lg shadow p-6">
        <p class="text-gray-500 text-sm">Confirmed</p>
        <h3 class="text-2xl font-bold text-blue-600"><?php echo $stats['confirmed']; ?></h3>
    </div>
    
    <div class="bg-white rounded-lg shadow p-6">
        <p class="text-gray-500 text-sm">Invoiced</p>
        <h3 class="text-2xl font-bold text-green-600"><?php echo $stats['invoiced']; ?></h3>
    </div>
</div>

<!-- Filters -->
<div class="bg-white rounded-lg shadow p-6 mb-6">
    <form method="GET" action="" class="grid grid-cols-1 md:grid-cols-6 gap-4">
        <div class="md:col-span-2">
            <input type="text" 
                   name="search" 
                   value="<?php echo $search; ?>"
                   placeholder="Search by booking #, customer..." 
                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
        
        <div>
            <select name="status" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">All Status</option>
                <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                <option value="confirmed" <?php echo $status === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                <option value="invoiced" <?php echo $status === 'invoiced' ? 'selected' : ''; ?>>Invoiced</option>
                <option value="cancelled" <?php echo $status === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
            </select>
        </div>
        
        <div>
            <select name="booker_id" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">All Bookers</option>
                <?php while ($booker = $bookers->fetch_assoc()): ?>
                    <option value="<?php echo $booker['id']; ?>" <?php echo $booker_id == $booker['id'] ? 'selected' : ''; ?>>
                        <?php echo $booker['name']; ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
        
        <div>
            <input type="date" 
                   name="date_from" 
                   value="<?php echo $date_from; ?>"
                   placeholder="From Date"
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

<!-- Bookings Table -->
<div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Booking #</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Booker</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Customer</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if ($bookings->num_rows > 0): ?>
                    <?php while ($booking = $bookings->fetch_assoc()): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="font-mono text-sm font-semibold"><?php echo $booking['booking_number']; ?></span>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm font-medium text-gray-900"><?php echo $booking['booker_name']; ?></div>
                                <div class="text-xs text-gray-500"><?php echo $booking['booker_code']; ?></div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm font-medium text-gray-900"><?php echo $booking['customer_name']; ?></div>
                                <?php if ($booking['customer_phone']): ?>
                                    <div class="text-xs text-gray-500"><?php echo $booking['customer_phone']; ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                <?php echo formatDate($booking['booking_date']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-900">
                                <?php echo formatCurrency($booking['total_amount']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php
                                $statusColors = [
                                    'pending' => 'bg-yellow-100 text-yellow-700',
                                    'confirmed' => 'bg-blue-100 text-blue-700',
                                    'invoiced' => 'bg-green-100 text-green-700',
                                    'cancelled' => 'bg-red-100 text-red-700'
                                ];
                                $statusClass = $statusColors[$booking['status']] ?? 'bg-gray-100 text-gray-700';
                                ?>
                                <span class="<?php echo $statusClass; ?> px-2 py-1 rounded text-xs font-semibold">
                                    <?php echo ucfirst($booking['status']); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <a href="view.php?id=<?php echo $booking['id']; ?>" 
                                   class="text-blue-600 hover:text-blue-900 mr-3">View</a>
                                <?php if ($booking['status'] !== 'invoiced' && $booking['status'] !== 'cancelled'): ?>
                                    <a href="edit.php?id=<?php echo $booking['id']; ?>" 
                                       class="text-green-600 hover:text-green-900 mr-3">Edit</a>
                                <?php endif; ?>
                                <?php if ($booking['status'] === 'confirmed'): ?>
                                    <a href="convert_invoice.php?id=<?php echo $booking['id']; ?>" 
                                       class="text-purple-600 hover:text-purple-900">Invoice</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="px-6 py-8 text-center text-gray-500">
                            No bookings found. <a href="add.php" class="text-blue-600 hover:text-blue-700">Create your first booking</a>
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
                    Showing <?php echo min($offset + 1, $totalRecords); ?> to <?php echo min($offset + $perPage, $totalRecords); ?> of <?php echo $totalRecords; ?> bookings
                </div>
                <div class="flex gap-2">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>&search=<?php echo $search; ?>&status=<?php echo $status; ?>&booker_id=<?php echo $booker_id; ?>" 
                           class="px-4 py-2 bg-white border rounded-lg hover:bg-gray-50">Previous</a>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                        <a href="?page=<?php echo $i; ?>&search=<?php echo $search; ?>&status=<?php echo $status; ?>&booker_id=<?php echo $booker_id; ?>" 
                           class="px-4 py-2 border rounded-lg <?php echo $i === $page ? 'bg-blue-600 text-white' : 'bg-white hover:bg-gray-50'; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&search=<?php echo $search; ?>&status=<?php echo $status; ?>&booker_id=<?php echo $booker_id; ?>" 
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