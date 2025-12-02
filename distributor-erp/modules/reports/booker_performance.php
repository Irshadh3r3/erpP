<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';

requireLogin();

$conn = getDBConnection();
$pageTitle = 'Booker Performance Report';

// Date filters
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-01');
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-t');
$booker_id = isset($_GET['booker_id']) ? (int)$_GET['booker_id'] : 0;
$area = isset($_GET['area']) ? clean($_GET['area']) : '';

// Build query
$where = "b.booking_date BETWEEN '$date_from' AND '$date_to'";
if ($booker_id > 0) {
    $where .= " AND b.booker_id = $booker_id";
}
if (!empty($area)) {
    $where .= " AND bk.area = '$area'";
}

// Get booker performance data
$query = "SELECT 
          bk.id,
          bk.booker_code,
          bk.name as booker_name,
          bk.phone,
          bk.area,
          bk.commission_percentage,
          bk.joining_date,
          COUNT(DISTINCT b.id) as total_bookings,
          COUNT(DISTINCT CASE WHEN b.status = 'pending' THEN b.id END) as pending_bookings,
          COUNT(DISTINCT CASE WHEN b.status = 'confirmed' THEN b.id END) as confirmed_bookings,
          COUNT(DISTINCT CASE WHEN b.status = 'invoiced' THEN b.id END) as invoiced_bookings,
          COUNT(DISTINCT CASE WHEN b.status = 'cancelled' THEN b.id END) as cancelled_bookings,
          SUM(b.total_amount) as total_booking_value,
          SUM(CASE WHEN b.status = 'invoiced' THEN b.total_amount ELSE 0 END) as invoiced_value,
          SUM(CASE WHEN b.status = 'invoiced' THEN (b.total_amount * bk.commission_percentage / 100) ELSE 0 END) as total_commission,
          COUNT(DISTINCT CASE WHEN b.customer_id IS NOT NULL THEN b.customer_id ELSE b.customer_name END) as unique_customers,
          COUNT(DISTINCT DATE(b.booking_date)) as active_days
          FROM bookers bk
          LEFT JOIN bookings b ON bk.id = b.booker_id AND $where
          WHERE bk.is_active = 1
          GROUP BY bk.id
          ORDER BY invoiced_value DESC";

$bookers = $conn->query($query);

// Get bookers for filter
$bookersFilterQuery = "SELECT id, name, booker_code FROM bookers WHERE is_active = 1 ORDER BY name ASC";
$bookersFilter = $conn->query($bookersFilterQuery);

// Get areas for filter
$areasQuery = "SELECT DISTINCT area FROM bookers WHERE area IS NOT NULL AND area != '' ORDER BY area ASC";
$areas = $conn->query($areasQuery);

// Get overall totals
$totalsQuery = "SELECT 
                COUNT(DISTINCT b.id) as total_bookings,
                COUNT(DISTINCT CASE WHEN b.status = 'invoiced' THEN b.id END) as total_invoiced,
                SUM(CASE WHEN b.status = 'invoiced' THEN b.total_amount ELSE 0 END) as total_sales,
                SUM(CASE WHEN b.status = 'invoiced' THEN (b.total_amount * bk.commission_percentage / 100) ELSE 0 END) as total_commission
                FROM bookings b
                JOIN bookers bk ON b.booker_id = bk.id
                WHERE $where";
$totals = $conn->query($totalsQuery)->fetch_assoc();

// Get top performing booker
$topBookerQuery = "SELECT bk.name, SUM(CASE WHEN b.status = 'invoiced' THEN b.total_amount ELSE 0 END) as sales
                   FROM bookers bk
                   LEFT JOIN bookings b ON bk.id = b.booker_id AND $where
                   GROUP BY bk.id
                   ORDER BY sales DESC
                   LIMIT 1";
$topBooker = $conn->query($topBookerQuery)->fetch_assoc();

include '../../includes/header.php';
?>

<!-- Page Header -->
<div class="mb-6">
    <h1 class="text-3xl font-bold text-gray-800">Booker Performance Report</h1>
    <p class="text-gray-600">Track sales team performance and commission earnings</p>
</div>

<!-- Filters -->
<div class="bg-white rounded-lg shadow p-6 mb-6">
    <form method="GET" action="" class="grid grid-cols-1 md:grid-cols-5 gap-4">
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
            <label class="block text-sm font-semibold text-gray-700 mb-2">Booker</label>
            <select name="booker_id" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="0">All Bookers</option>
                <?php 
                $bookersFilter->data_seek(0);
                while ($bk = $bookersFilter->fetch_assoc()): 
                ?>
                    <option value="<?php echo $bk['id']; ?>" <?php echo $booker_id == $bk['id'] ? 'selected' : ''; ?>>
                        <?php echo $bk['name']; ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">Area</label>
            <select name="area" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">All Areas</option>
                <?php while ($areaRow = $areas->fetch_assoc()): ?>
                    <option value="<?php echo $areaRow['area']; ?>" <?php echo $area == $areaRow['area'] ? 'selected' : ''; ?>>
                        <?php echo $areaRow['area']; ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="flex items-end gap-2">
            <button type="submit" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-semibold transition">
                Generate
            </button>
            <button type="button" onclick="window.print()" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg font-semibold transition">
                Print
            </button>
        </div>
    </form>
</div>

<!-- Summary Cards -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
    <div class="bg-white rounded-lg shadow p-6">
        <p class="text-gray-500 text-sm">Total Bookings</p>
        <h3 class="text-3xl font-bold text-gray-800"><?php echo number_format($totals['total_bookings']); ?></h3>
        <p class="text-xs text-gray-500 mt-1"><?php echo $totals['total_invoiced']; ?> converted to sales</p>
    </div>
    
    <div class="bg-white rounded-lg shadow p-6">
        <p class="text-gray-500 text-sm">Total Sales Generated</p>
        <h3 class="text-2xl font-bold text-blue-600"><?php echo formatCurrency($totals['total_sales']); ?></h3>
        <p class="text-xs text-gray-500 mt-1">
            <?php echo $totals['total_bookings'] > 0 ? number_format(($totals['total_invoiced'] / $totals['total_bookings']) * 100, 1) : 0; ?>% conversion rate
        </p>
    </div>
    
    <div class="bg-white rounded-lg shadow p-6">
        <p class="text-gray-500 text-sm">Total Commission</p>
        <h3 class="text-2xl font-bold text-green-600"><?php echo formatCurrency($totals['total_commission']); ?></h3>
        <p class="text-xs text-gray-500 mt-1">Earned from converted sales</p>
    </div>
    
    <div class="bg-white rounded-lg shadow p-6">
        <p class="text-gray-500 text-sm">Top Performer</p>
        <h3 class="text-lg font-bold text-purple-600"><?php echo $topBooker['name'] ?? 'N/A'; ?></h3>
        <p class="text-xs text-gray-500 mt-1"><?php echo formatCurrency($topBooker['sales'] ?? 0); ?> sales</p>
    </div>
</div>

<!-- Performance Table -->
<div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="p-6 border-b">
        <h3 class="text-lg font-bold text-gray-800">Individual Booker Performance</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Rank</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Booker</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Area</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total Bookings</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Invoiced</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Conversion %</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Sales Value</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Commission Rate</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Commission Earned</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Avg Per Booking</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php if ($bookers->num_rows > 0): ?>
                    <?php 
                    $rank = 1;
                    while ($booker = $bookers->fetch_assoc()): 
                        $conversionRate = $booker['total_bookings'] > 0 ? 
                                         (($booker['invoiced_bookings'] / $booker['total_bookings']) * 100) : 0;
                        $avgPerBooking = $booker['invoiced_bookings'] > 0 ? 
                                        ($booker['invoiced_value'] / $booker['invoiced_bookings']) : 0;
                    ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 text-sm font-bold text-gray-900"><?php echo $rank++; ?></td>
                            <td class="px-6 py-4">
                                <div class="text-sm font-medium text-gray-900">
                                    <a href="../bookers/view.php?id=<?php echo $booker['id']; ?>" class="text-blue-600 hover:text-blue-800">
                                        <?php echo $booker['booker_name']; ?>
                                    </a>
                                </div>
                                <div class="text-xs text-gray-500"><?php echo $booker['booker_code']; ?></div>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-600">
                                <?php echo $booker['area'] ?: 'N/A'; ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-right font-semibold">
                                <?php echo number_format($booker['total_bookings']); ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-right">
                                <span class="text-green-600 font-semibold"><?php echo number_format($booker['invoiced_bookings']); ?></span>
                                <span class="text-xs text-gray-500">/ <?php echo $booker['pending_bookings']; ?> pending</span>
                            </td>
                            <td class="px-6 py-4 text-sm text-right">
                                <?php
                                $colorClass = $conversionRate >= 70 ? 'text-green-600' : 
                                            ($conversionRate >= 50 ? 'text-yellow-600' : 'text-red-600');
                                ?>
                                <span class="<?php echo $colorClass; ?> font-bold">
                                    <?php echo number_format($conversionRate, 1); ?>%
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm text-right font-bold text-blue-600">
                                <?php echo formatCurrency($booker['invoiced_value']); ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-right">
                                <span class="bg-purple-100 text-purple-700 px-2 py-1 rounded text-xs font-semibold">
                                    <?php echo $booker['commission_percentage']; ?>%
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm text-right font-bold text-green-600">
                                <?php echo formatCurrency($booker['total_commission']); ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-right text-gray-600">
                                <?php echo formatCurrency($avgPerBooking); ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="10" class="px-6 py-8 text-center text-gray-500">
                            No booker data found for the selected period
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Status Breakdown Section -->
<?php if ($booker_id > 0): ?>
    <?php
    // Get detailed breakdown for selected booker
    $breakdownQuery = "SELECT 
                       status,
                       COUNT(*) as count,
                       SUM(total_amount) as amount
                       FROM bookings
                       WHERE booker_id = $booker_id
                       AND booking_date BETWEEN '$date_from' AND '$date_to'
                       GROUP BY status";
    $breakdown = $conn->query($breakdownQuery);
    ?>
    
    <div class="mt-6 bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-bold text-gray-800 mb-4">Booking Status Breakdown</h3>
        <div class="grid grid-cols-4 gap-4">
            <?php while ($status = $breakdown->fetch_assoc()): ?>
                <?php
                $statusColors = [
                    'pending' => 'bg-yellow-100 text-yellow-700 border-yellow-300',
                    'confirmed' => 'bg-blue-100 text-blue-700 border-blue-300',
                    'invoiced' => 'bg-green-100 text-green-700 border-green-300',
                    'cancelled' => 'bg-red-100 text-red-700 border-red-300'
                ];
                $colorClass = $statusColors[$status['status']] ?? 'bg-gray-100 text-gray-700 border-gray-300';
                ?>
                <div class="<?php echo $colorClass; ?> border-2 rounded-lg p-4 text-center">
                    <p class="text-sm font-semibold uppercase"><?php echo $status['status']; ?></p>
                    <p class="text-2xl font-bold mt-2"><?php echo $status['count']; ?></p>
                    <p class="text-xs mt-1"><?php echo formatCurrency($status['amount']); ?></p>
                </div>
            <?php endwhile; ?>
        </div>
    </div>
<?php endif; ?>

<style>
@media print {
    .no-print {
        display: none !important;
    }
}
</style>

<?php
include '../../includes/footer.php';
?>