<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';

requireLogin();

$conn = getDBConnection();
$pageTitle = 'Booker Comparison Report';

// Date filters
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-01');
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-t');
$area = isset($_GET['area']) ? clean($_GET['area']) : '';
$metric = isset($_GET['metric']) ? clean($_GET['metric']) : 'sales';

// Build query
$where = "b.booking_date BETWEEN '$date_from' AND '$date_to' AND b.status = 'invoiced'";
if (!empty($area)) {
    $where .= " AND bk.area = '$area'";
}

// Get booker comparison data
$query = "SELECT 
          bk.id,
          bk.booker_code,
          bk.name as booker_name,
          bk.area,
          bk.commission_percentage,
          bk.joining_date,
          COUNT(DISTINCT b.id) as total_bookings,
          COUNT(DISTINCT b.customer_id) as unique_customers,
          SUM(b.total_amount) as total_sales,
          AVG(b.total_amount) as avg_booking_value,
          SUM(b.total_amount * bk.commission_percentage / 100) as commission_earned,
          COUNT(DISTINCT DATE(b.booking_date)) as active_days,
          DATEDIFF('$date_to', '$date_from') + 1 as period_days
          FROM bookers bk
          LEFT JOIN bookings b ON bk.id = b.booker_id AND $where
          WHERE bk.is_active = 1
          GROUP BY bk.id
          HAVING total_bookings > 0
          ORDER BY total_sales DESC";

$bookers = $conn->query($query);

// Get areas for filter
$areasQuery = "SELECT DISTINCT area FROM bookers WHERE area IS NOT NULL AND area != '' ORDER BY area ASC";
$areas = $conn->query($areasQuery);

// Calculate team average
$teamAvgQuery = "SELECT 
                 AVG(bookings) as avg_bookings,
                 AVG(sales) as avg_sales,
                 AVG(customers) as avg_customers
                 FROM (
                   SELECT 
                     COUNT(DISTINCT b.id) as bookings,
                     SUM(b.total_amount) as sales,
                     COUNT(DISTINCT b.customer_id) as customers
                   FROM bookers bk
                   LEFT JOIN bookings b ON bk.id = b.booker_id AND $where
                   WHERE bk.is_active = 1
                   GROUP BY bk.id
                   HAVING bookings > 0
                 ) as booker_stats";
$teamAvg = $conn->query($teamAvgQuery)->fetch_assoc();

// Get top performer
$topPerformer = "SELECT 
                 bk.name,
                 SUM(b.total_amount) as sales
                 FROM bookers bk
                 LEFT JOIN bookings b ON bk.id = b.booker_id AND $where
                 GROUP BY bk.id
                 ORDER BY sales DESC
                 LIMIT 1";
$topBkr = $conn->query($topPerformer)->fetch_assoc();

include '../../includes/header.php';
?>

<!-- Page Header -->
<div class="mb-6">
    <h1 class="text-3xl font-bold text-gray-800">Booker Comparison Report</h1>
    <p class="text-gray-600">Compare booker performance side-by-side</p>
</div>

<!-- Filters -->
<div class="bg-white rounded-lg shadow p-6 mb-6">
    <form method="GET" action="" class="grid grid-cols-1 md:grid-cols-4 gap-4">
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

<!-- Team Averages -->
<div class="bg-gradient-to-r from-blue-50 to-purple-50 border-2 border-blue-200 rounded-lg p-6 mb-6">
    <h3 class="text-lg font-bold text-blue-900 mb-4">📊 Team Averages (Benchmark)</h3>
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-white rounded-lg p-4 shadow">
            <p class="text-sm text-gray-600 mb-1">Avg Bookings</p>
            <p class="text-2xl font-bold text-blue-600"><?php echo number_format($teamAvg['avg_bookings'], 1); ?></p>
        </div>
        <div class="bg-white rounded-lg p-4 shadow">
            <p class="text-sm text-gray-600 mb-1">Avg Sales</p>
            <p class="text-2xl font-bold text-green-600"><?php echo formatCurrency($teamAvg['avg_sales']); ?></p>
        </div>
        <div class="bg-white rounded-lg p-4 shadow">
            <p class="text-sm text-gray-600 mb-1">Avg Customers</p>
            <p class="text-2xl font-bold text-purple-600"><?php echo number_format($teamAvg['avg_customers'], 1); ?></p>
        </div>
        <div class="bg-white rounded-lg p-4 shadow">
            <p class="text-sm text-gray-600 mb-1">Top Performer</p>
            <p class="text-lg font-bold text-orange-600"><?php echo $topBkr['name']; ?></p>
            <p class="text-xs text-gray-500"><?php echo formatCurrency($topBkr['sales']); ?></p>
        </div>
    </div>
</div>

<!-- Comparison Table -->
<div class="bg-white rounded-lg shadow overflow-hidden mb-6">
    <div class="p-6 border-b">
        <h3 class="text-lg font-bold text-gray-800">Detailed Performance Comparison</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Rank</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Booker</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Area</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Bookings</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Sales</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Avg/Booking</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Customers</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Sales/Day</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Commission</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">vs Avg</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php 
                $rank = 1;
                $bookers->data_seek(0);
                while ($booker = $bookers->fetch_assoc()): 
                    $salesPerDay = $booker['active_days'] > 0 ? $booker['total_sales'] / $booker['active_days'] : 0;
                    $vsAvg = $teamAvg['avg_sales'] > 0 ? (($booker['total_sales'] - $teamAvg['avg_sales']) / $teamAvg['avg_sales']) * 100 : 0;
                    $performance = $vsAvg >= 20 ? 'Excellent' : ($vsAvg >= 0 ? 'Above Avg' : ($vsAvg >= -20 ? 'Below Avg' : 'Needs Improvement'));
                    $perfColor = $vsAvg >= 20 ? 'text-green-600' : ($vsAvg >= 0 ? 'text-blue-600' : ($vsAvg >= -20 ? 'text-yellow-600' : 'text-red-600'));
                ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 text-sm font-bold"><?php echo $rank++; ?></td>
                        <td class="px-6 py-4">
                            <div class="text-sm font-medium text-gray-900"><?php echo $booker['booker_name']; ?></div>
                            <div class="text-xs text-gray-500"><?php echo $booker['booker_code']; ?> • Joined <?php echo date('M Y', strtotime($booker['joining_date'])); ?></div>
                        </td>
                        <td class="px-6 py-4 text-sm"><?php echo $booker['area'] ?: 'N/A'; ?></td>
                        <td class="px-6 py-4 text-sm text-right font-semibold">
                            <?php echo $booker['total_bookings']; ?>
                            <div class="text-xs <?php echo $booker['total_bookings'] >= $teamAvg['avg_bookings'] ? 'text-green-600' : 'text-gray-500'; ?>">
                                <?php echo $booker['total_bookings'] >= $teamAvg['avg_bookings'] ? '↑' : '↓'; ?> 
                                <?php echo number_format(abs($booker['total_bookings'] - $teamAvg['avg_bookings']), 1); ?>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-sm text-right font-bold text-green-600">
                            <?php echo formatCurrency($booker['total_sales']); ?>
                        </td>
                        <td class="px-6 py-4 text-sm text-right">
                            <?php echo formatCurrency($booker['avg_booking_value']); ?>
                        </td>
                        <td class="px-6 py-4 text-sm text-right">
                            <?php echo $booker['unique_customers']; ?>
                        </td>
                        <td class="px-6 py-4 text-sm text-right">
                            <?php echo formatCurrency($salesPerDay); ?>
                            <div class="text-xs text-gray-500"><?php echo $booker['active_days']; ?> days</div>
                        </td>
                        <td class="px-6 py-4 text-sm text-right font-semibold text-purple-600">
                            <?php echo formatCurrency($booker['commission_earned']); ?>
                        </td>
                        <td class="px-6 py-4">
                            <div class="<?php echo $perfColor; ?> font-bold text-sm">
                                <?php echo $vsAvg >= 0 ? '+' : ''; ?><?php echo number_format($vsAvg, 1); ?>%
                            </div>
                            <div class="text-xs text-gray-600"><?php echo $performance; ?></div>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Performance Distribution -->
<div class="bg-white rounded-lg shadow p-6">
    <h3 class="text-lg font-bold text-gray-800 mb-4">Performance Distribution</h3>
    <div class="grid grid-cols-4 gap-4">
        <?php
        $bookers->data_seek(0);
        $excellent = $aboveAvg = $belowAvg = $needsImprovement = 0;
        while ($b = $bookers->fetch_assoc()) {
            $vsAvg = $teamAvg['avg_sales'] > 0 ? (($b['total_sales'] - $teamAvg['avg_sales']) / $teamAvg['avg_sales']) * 100 : 0;
            if ($vsAvg >= 20) $excellent++;
            elseif ($vsAvg >= 0) $aboveAvg++;
            elseif ($vsAvg >= -20) $belowAvg++;
            else $needsImprovement++;
        }
        ?>
        <div class="bg-green-50 border-2 border-green-300 rounded-lg p-4 text-center">
            <p class="text-green-700 font-semibold uppercase text-sm">Excellent</p>
            <p class="text-4xl font-bold text-green-600 my-2"><?php echo $excellent; ?></p>
            <p class="text-xs text-green-600">+20% vs avg</p>
        </div>
        <div class="bg-blue-50 border-2 border-blue-300 rounded-lg p-4 text-center">
            <p class="text-blue-700 font-semibold uppercase text-sm">Above Avg</p>
            <p class="text-4xl font-bold text-blue-600 my-2"><?php echo $aboveAvg; ?></p>
            <p class="text-xs text-blue-600">0-20% vs avg</p>
        </div>
        <div class="bg-yellow-50 border-2 border-yellow-300 rounded-lg p-4 text-center">
            <p class="text-yellow-700 font-semibold uppercase text-sm">Below Avg</p>
            <p class="text-4xl font-bold text-yellow-600 my-2"><?php echo $belowAvg; ?></p>
            <p class="text-xs text-yellow-600">0 to -20% vs avg</p>
        </div>
        <div class="bg-red-50 border-2 border-red-300 rounded-lg p-4 text-center">
            <p class="text-red-700 font-semibold uppercase text-sm">Needs Focus</p>
            <p class="text-4xl font-bold text-red-600 my-2"><?php echo $needsImprovement; ?></p>
            <p class="text-xs text-red-600">-20%+ vs avg</p>
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