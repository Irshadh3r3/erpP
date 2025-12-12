<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';

requireLogin();

$conn = getDBConnection();
$pageTitle = 'Booker Details';

$bookerId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($bookerId <= 0) {
    $_SESSION['error_message'] = 'Invalid booker ID';
    header('Location: list.php');
    exit;
}

// Get date range filter
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');

// Get booker details
$bookerQuery = "SELECT * FROM bookers WHERE id = $bookerId";
$bookerResult = $conn->query($bookerQuery);

if ($bookerResult->num_rows === 0) {
    $_SESSION['error_message'] = 'Booker not found';
    header('Location: list.php');
    exit;
}

$booker = $bookerResult->fetch_assoc();

// Get stats for date range
$stats = getBookerStats($conn, $bookerId, $startDate, $endDate);

// Calculate commission earned
$commissionEarned = $stats['total_commission'];

// Get recent bookings
$bookingsQuery = "SELECT b.*, 
                  CASE 
                    WHEN b.customer_id IS NOT NULL THEN c.name
                    ELSE b.customer_name
                  END as customer_display_name
                  FROM bookings b
                  LEFT JOIN customers c ON b.customer_id = c.id
                  WHERE b.booker_id = $bookerId 
                  AND b.booking_date BETWEEN '$startDate' AND '$endDate'
                  ORDER BY b.created_at DESC
                  LIMIT 10";
$recentBookings = $conn->query($bookingsQuery);

// Get area-wise performance (if booker has multiple areas)
$areaStatsQuery = "SELECT 
                   COALESCE(b.customer_address, 'Unknown') as area,
                   COUNT(*) as booking_count,
                   SUM(CASE WHEN b.status = 'invoiced' THEN b.total_amount ELSE 0 END) as total_sales
                   FROM bookings b
                   WHERE b.booker_id = $bookerId
                   AND b.booking_date BETWEEN '$startDate' AND '$endDate'
                   GROUP BY area
                   ORDER BY total_sales DESC
                   LIMIT 5";
$areaStats = $conn->query($areaStatsQuery);

include '../../includes/header.php';
?>

<!-- Page Header -->
<div class="mb-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-bold text-gray-800"><?php echo $booker['name']; ?></h1>
            <p class="text-gray-600"><?php echo $booker['booker_code']; ?> • <?php echo $booker['area'] ?? 'No area assigned'; ?></p>
        </div>
        <div class="flex gap-2">
            <a href="../bookings/add.php?booker_id=<?php echo $booker['id']; ?>" 
               class="bg-green-600 hover:bg-green-700 text-white px-6 py-2 rounded-lg font-semibold transition">
                + New Booking
            </a>
            <a href="edit.php?id=<?php echo $booker['id']; ?>" 
               class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg font-semibold transition">
                Edit Booker
            </a>
        </div>
    </div>
</div>

<!-- Booker Info Card -->
<div class="bg-white rounded-lg shadow p-6 mb-6">
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
        <div>
            <p class="text-sm text-gray-500 mb-1">Phone</p>
            <p class="font-semibold"><?php echo $booker['phone']; ?></p>
        </div>
        <div>
            <p class="text-sm text-gray-500 mb-1">Email</p>
            <p class="font-semibold"><?php echo $booker['email'] ?: 'N/A'; ?></p>
        </div>
        <div>
            <p class="text-sm text-gray-500 mb-1">Commission Rate</p>
            <p class="font-semibold text-blue-600"><?php echo $booker['commission_percentage']; ?>%</p>
        </div>
        <div>
            <p class="text-sm text-gray-500 mb-1">Joining Date</p>
            <p class="font-semibold"><?php echo $booker['joining_date'] ? formatDate($booker['joining_date']) : 'N/A'; ?></p>
        </div>
    </div>
</div>

<!-- Date Range Filter -->
<div class="bg-white rounded-lg shadow p-6 mb-6">
    <form method="GET" action="" class="flex items-end gap-4">
        <input type="hidden" name="id" value="<?php echo $bookerId; ?>">
        <div class="flex-1">
            <label class="block text-sm font-semibold text-gray-700 mb-2">Start Date</label>
            <input type="date" 
                   name="start_date" 
                   value="<?php echo $startDate; ?>"
                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
        <div class="flex-1">
            <label class="block text-sm font-semibold text-gray-700 mb-2">End Date</label>
            <input type="date" 
                   name="end_date" 
                   value="<?php echo $endDate; ?>"
                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg font-semibold transition">
            Apply Filter
        </button>
        <a href="?id=<?php echo $bookerId; ?>" class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-6 py-2 rounded-lg font-semibold transition">
            Reset
        </a>
    </form>
</div>

<!-- Performance Stats -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-gray-500 text-sm">Total Bookings</p>
                <h3 class="text-3xl font-bold text-gray-800 mt-1"><?php echo $stats['total_bookings']; ?></h3>
            </div>
            <div class="bg-blue-100 p-3 rounded-full">
                <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-gray-500 text-sm">Invoiced</p>
                <h3 class="text-3xl font-bold text-green-600 mt-1"><?php echo $stats['completed_bookings']; ?></h3>
            </div>
            <div class="bg-green-100 p-3 rounded-full">
                <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-gray-500 text-sm">Total Sales</p>
                <h3 class="text-2xl font-bold text-gray-800 mt-1"><?php echo formatCurrency($stats['total_sales']); ?></h3>
            </div>
            <div class="bg-purple-100 p-3 rounded-full">
                <svg class="w-8 h-8 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-gray-500 text-sm">Commission Earned</p>
                <h3 class="text-2xl font-bold text-orange-600 mt-1"><?php echo formatCurrency($commissionEarned); ?></h3>
            </div>
            <div class="bg-orange-100 p-3 rounded-full">
                <svg class="w-8 h-8 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
                </svg>
            </div>
        </div>
    </div>
</div>

<!-- Recent Bookings -->
<div class="bg-white rounded-lg shadow mb-6">
    <div class="p-6 border-b">
        <h2 class="text-xl font-bold text-gray-800">Recent Bookings</h2>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Booking #</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Customer</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php if ($recentBookings->num_rows > 0): ?>
                    <?php while ($booking = $recentBookings->fetch_assoc()): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4">
                                <a href="../bookings/view.php?id=<?php echo $booking['id']; ?>" class="text-blue-600 hover:text-blue-800 font-mono">
                                    <?php echo $booking['booking_number']; ?>
                                </a>
                            </td>
                            <td class="px-6 py-4"><?php echo $booking['customer_display_name']; ?></td>
                            <td class="px-6 py-4 text-sm"><?php echo formatDate($booking['booking_date']); ?></td>
                            <td class="px-6 py-4 font-semibold"><?php echo formatCurrency($booking['total_amount']); ?></td>
                            <td class="px-6 py-4">
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
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" class="px-6 py-8 text-center text-gray-500">No bookings found for selected period</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
include '../../includes/footer.php';
?>