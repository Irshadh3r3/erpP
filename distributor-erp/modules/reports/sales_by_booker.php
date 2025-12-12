<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';

requireLogin();

$conn = getDBConnection();
$pageTitle = 'Sales by Booker Report';

// Date filters
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-01');
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-t');
$booker_id = isset($_GET['booker_id']) ? (int)$_GET['booker_id'] : 0;
$area = isset($_GET['area']) ? clean($_GET['area']) : '';
$status = isset($_GET['status']) ? clean($_GET['status']) : 'all';

// Build query
$where = "b.booking_date BETWEEN '$date_from' AND '$date_to'";
if ($booker_id > 0) {
    $where .= " AND b.booker_id = $booker_id";
}
if (!empty($area)) {
    $where .= " AND bk.area = '$area'";
}
if ($status !== 'all') {
    $where .= " AND b.status = '$status'";
}

// Get booker sales data - focusing on invoiced bookings
$query = "SELECT 
          bk.id,
          bk.booker_code,
          bk.name as booker_name,
          bk.phone,
          bk.area,
          bk.commission_percentage,
          COUNT(DISTINCT b.id) as total_bookings,
          COUNT(DISTINCT CASE WHEN b.status = 'pending' THEN b.id END) as pending_bookings,
          COUNT(DISTINCT CASE WHEN b.status = 'confirmed' THEN b.id END) as confirmed_bookings,
          COUNT(DISTINCT CASE WHEN b.status = 'invoiced' THEN b.id END) as invoiced_bookings,
          COUNT(DISTINCT CASE WHEN b.status = 'cancelled' THEN b.id END) as cancelled_bookings,
          SUM(b.total_amount) as total_booking_value,
          SUM(CASE WHEN b.status = 'invoiced' THEN b.total_amount ELSE 0 END) as invoiced_value,
          SUM(CASE WHEN b.status = 'invoiced' THEN (b.total_amount * bk.commission_percentage / 100) ELSE 0 END) as earned_commission,
          COUNT(DISTINCT b.customer_id) as unique_customers,
          AVG(b.total_amount) as avg_booking_value,
          MIN(b.booking_date) as first_booking,
          MAX(b.booking_date) as last_booking
          FROM bookers bk
          LEFT JOIN bookings b ON bk.id = b.booker_id AND $where
          WHERE bk.is_active = 1
          GROUP BY bk.id
          HAVING total_bookings > 0
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
                COUNT(DISTINCT CASE WHEN b.status = 'invoiced' THEN b.id END) as invoiced_bookings,
                SUM(b.total_amount) as total_value,
                SUM(CASE WHEN b.status = 'invoiced' THEN b.total_amount ELSE 0 END) as invoiced_value,
                COUNT(DISTINCT b.booker_id) as active_bookers,
                COUNT(DISTINCT b.customer_id) as total_customers
                FROM bookings b
                JOIN bookers bk ON b.booker_id = bk.id
                WHERE $where";
$totals = $conn->query($totalsQuery)->fetch_assoc();

// Top performing booker
$topBooker = "SELECT 
              bk.name,
              SUM(CASE WHEN b.status = 'invoiced' THEN b.total_amount ELSE 0 END) as sales
              FROM bookers bk
              LEFT JOIN bookings b ON bk.id = b.booker_id AND $where
              GROUP BY bk.id
              ORDER BY sales DESC
              LIMIT 1";
$topPerformer = $conn->query($topBooker)->fetch_assoc();

// Area-wise performance
$areaPerformance = "SELECT 
                    bk.area,
                    COUNT(DISTINCT bk.id) as booker_count,
                    COUNT(DISTINCT b.id) as bookings,
                    SUM(CASE WHEN b.status = 'invoiced' THEN b.total_amount ELSE 0 END) as sales
                    FROM bookers bk
                    LEFT JOIN bookings b ON bk.id = b.booker_id AND $where
                    WHERE bk.area IS NOT NULL AND bk.area != ''
                    GROUP BY bk.area
                    ORDER BY sales DESC";
$areaData = $conn->query($areaPerformance);

include '../../includes/header.php';
?>

<!-- Page Header -->
<div class="mb-6">
    <h1 class="text-3xl font-bold text-gray-800">Sales by Booker Report</h1>
    <p class="text-gray-600">Analyze sales performance by sales team</p>
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
                        <?php echo $bk['name']; ?> (<?php echo $bk['booker_code']; ?>)
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
            <a href="print.php?<?php echo http_build_query($_GET); ?>" 
   class="inline-block bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg font-semibold transition no-print">
    Print Report
</a>
        </div>
    </form>
</div>

<!-- Summary Cards -->
<div class="grid grid-cols-1 md:grid-cols-5 gap-6 mb-6">
    <div class="bg-white rounded-lg shadow p-6">
        <p class="text-gray-500 text-sm">Active Bookers</p>
        <h3 class="text-3xl font-bold text-gray-800"><?php echo $totals['active_bookers']; ?></h3>
        <p class="text-xs text-gray-500 mt-1">Sales team</p>
    </div>
    
    <div class="bg-white rounded-lg shadow p-6">
        <p class="text-gray-500 text-sm">Total Bookings</p>
        <h3 class="text-3xl font-bold text-blue-600"><?php echo number_format($totals['total_bookings']); ?></h3>
        <p class="text-xs text-gray-500 mt-1"><?php echo $totals['invoiced_bookings']; ?> invoiced</p>
    </div>
    
    <div class="bg-white rounded-lg shadow p-6">
        <p class="text-gray-500 text-sm">Total Sales</p>
        <h3 class="text-2xl font-bold text-green-600"><?php echo formatCurrency($totals['invoiced_value']); ?></h3>
        <p class="text-xs text-gray-500 mt-1">Invoiced value</p>
    </div>
    
    <div class="bg-white rounded-lg shadow p-6">
        <p class="text-gray-500 text-sm">Avg per Booker</p>
        <h3 class="text-2xl font-bold text-purple-600">
            <?php echo formatCurrency($totals['active_bookers'] > 0 ? $totals['invoiced_value'] / $totals['active_bookers'] : 0); ?>
        </h3>
        <p class="text-xs text-gray-500 mt-1">Sales per person</p>
    </div>
    
    <div class="bg-white rounded-lg shadow p-6">
        <p class="text-gray-500 text-sm">Top Performer</p>
        <h3 class="text-lg font-bold text-orange-600"><?php echo $topPerformer['name'] ?? 'N/A'; ?></h3>
        <p class="text-xs text-gray-500 mt-1"><?php echo formatCurrency($topPerformer['sales'] ?? 0); ?></p>
    </div>
</div>

<!-- Area Performance -->
<div class="bg-white rounded-lg shadow p-6 mb-6">
    <h3 class="text-lg font-bold text-gray-800 mb-4">Area-wise Performance</h3>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <?php 
        $areaData->data_seek(0);
        while ($areaItem = $areaData->fetch_assoc()): 
            $percentage = $totals['invoiced_value'] > 0 ? ($areaItem['sales'] / $totals['invoiced_value']) * 100 : 0;
        ?>
            <div class="border border-gray-300 rounded-lg p-4 hover:shadow-md transition">
                <div class="flex justify-between items-start mb-2">
                    <h4 class="font-bold text-gray-900 text-lg"><?php echo $areaItem['area']; ?></h4>
                    <span class="bg-blue-100 text-blue-700 px-2 py-1 rounded text-xs font-semibold">
                        <?php echo $areaItem['booker_count']; ?> bookers
                    </span>
                </div>
                <div class="space-y-2">
                    <div>
                        <p class="text-2xl font-bold text-green-600"><?php echo formatCurrency($areaItem['sales']); ?></p>
                        <p class="text-xs text-gray-500"><?php echo $areaItem['bookings']; ?> bookings</p>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2">
                        <div class="bg-green-500 h-2 rounded-full" style="width: <?php echo $percentage; ?>%"></div>
                    </div>
                    <p class="text-xs text-gray-600"><?php echo number_format($percentage, 1); ?>% of total sales</p>
                </div>
            </div>
        <?php endwhile; ?>
    </div>
</div>

<!-- Booker Performance Table -->
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
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Bookings</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Invoiced</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Conversion %</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Sales Value</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Avg Booking</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Customers</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Commission</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php if ($bookers->num_rows > 0): ?>
                    <?php 
                    $rank = 1;
                    while ($booker = $bookers->fetch_assoc()): 
                        $conversionRate = $booker['total_bookings'] > 0 ? 
                                         (($booker['invoiced_bookings'] / $booker['total_bookings']) * 100) : 0;
                    ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 text-sm font-bold text-gray-900"><?php echo $rank++; ?></td>
                            <td class="px-6 py-4">
                                <div class="text-sm font-medium text-gray-900">
                                    <a href="../bookers/view.php?id=<?php echo $booker['id']; ?>" 
                                       class="text-blue-600 hover:text-blue-800">
                                        <?php echo $booker['booker_name']; ?>
                                    </a>
                                </div>
                                <div class="text-xs text-gray-500"><?php echo $booker['booker_code']; ?> • <?php echo $booker['phone']; ?></div>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-600">
                                <?php echo $booker['area'] ?: 'N/A'; ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-right font-semibold">
                                <?php echo number_format($booker['total_bookings']); ?>
                                <div class="text-xs text-gray-500">
                                    <?php echo $booker['pending_bookings']; ?>P / <?php echo $booker['confirmed_bookings']; ?>C
                                </div>
                            </td>
                            <td class="px-6 py-4 text-sm text-right">
                                <span class="text-green-600 font-bold"><?php echo number_format($booker['invoiced_bookings']); ?></span>
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
                                <?php echo formatCurrency($booker['avg_booking_value']); ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-right">
                                <?php echo $booker['unique_customers']; ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-right font-bold text-green-600">
                                <?php echo formatCurrency($booker['earned_commission']); ?>
                                <div class="text-xs text-gray-500">
                                    @ <?php echo $booker['commission_percentage']; ?>%
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    
                    <!-- Totals Row -->
                    <tr class="bg-gray-100 font-bold">
                        <td colspan="3" class="px-6 py-4 text-sm">TOTAL</td>
                        <td class="px-6 py-4 text-sm text-right"><?php echo number_format($totals['total_bookings']); ?></td>
                        <td class="px-6 py-4 text-sm text-right text-green-600"><?php echo number_format($totals['invoiced_bookings']); ?></td>
                        <td class="px-6 py-4 text-sm text-right">
                            <?php echo number_format($totals['total_bookings'] > 0 ? ($totals['invoiced_bookings'] / $totals['total_bookings']) * 100 : 0, 1); ?>%
                        </td>
                        <td class="px-6 py-4 text-sm text-right text-blue-600">
                            <?php echo formatCurrency($totals['invoiced_value']); ?>
                        </td>
                        <td colspan="3"></td>
                    </tr>
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

<!-- Performance Insights -->
<div class="mt-6 bg-blue-50 border border-blue-200 rounded-lg p-6">
    <h3 class="text-lg font-bold text-blue-900 mb-3">📊 Performance Insights</h3>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm text-blue-800">
        <div>
            <p class="font-semibold mb-2">Overall Conversion Rate:</p>
            <p class="text-2xl font-bold text-blue-900">
                <?php echo $totals['total_bookings'] > 0 ? number_format(($totals['invoiced_bookings'] / $totals['total_bookings']) * 100, 1) : 0; ?>%
            </p>
            <p class="text-xs mt-1">Bookings converted to sales</p>
        </div>
        <div>
            <p class="font-semibold mb-2">Avg Bookings per Booker:</p>
            <p class="text-2xl font-bold text-blue-900">
                <?php echo $totals['active_bookers'] > 0 ? number_format($totals['total_bookings'] / $totals['active_bookers'], 1) : 0; ?>
            </p>
            <p class="text-xs mt-1">Per person in period</p>
        </div>
        <div>
            <p class="font-semibold mb-2">Customer Reach:</p>
            <p class="text-2xl font-bold text-blue-900">
                <?php echo number_format($totals['total_customers']); ?>
            </p>
            <p class="text-xs mt-1">Unique customers served</p>
        </div>
    </div>
</div>

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