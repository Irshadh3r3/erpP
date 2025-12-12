<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';

requireLogin();

$conn = getDBConnection();
$pageTitle = 'Area-wise Sales Report';

// Date filters
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-01');
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-t');
$area = isset($_GET['area']) ? clean($_GET['area']) : '';

// Build query
$where = "b.booking_date BETWEEN '$date_from' AND '$date_to' AND b.status = 'invoiced'";
if (!empty($area)) {
    $where .= " AND bk.area = '$area'";
}

// Get area-wise sales data
$query = "SELECT 
          bk.area,
          COUNT(DISTINCT bk.id) as booker_count,
          COUNT(DISTINCT b.id) as total_bookings,
          COUNT(DISTINCT b.customer_id) as unique_customers,
          SUM(b.total_amount) as total_sales,
          AVG(b.total_amount) as avg_booking_value,
          SUM(b.total_amount * bk.commission_percentage / 100) as total_commission,
          MIN(b.booking_date) as first_booking,
          MAX(b.booking_date) as last_booking
          FROM bookings b
          JOIN bookers bk ON b.booker_id = bk.id
          WHERE $where AND bk.area IS NOT NULL AND bk.area != ''
          GROUP BY bk.area
          ORDER BY total_sales DESC";

$areas = $conn->query($query);

// Get areas for filter
$areasFilterQuery = "SELECT DISTINCT area FROM bookers WHERE area IS NOT NULL AND area != '' ORDER BY area ASC";
$areasFilter = $conn->query($areasFilterQuery);

// Get overall totals
$totalsQuery = "SELECT 
                COUNT(DISTINCT bk.area) as total_areas,
                COUNT(DISTINCT bk.id) as total_bookers,
                COUNT(DISTINCT b.id) as total_bookings,
                SUM(b.total_amount) as total_sales,
                COUNT(DISTINCT b.customer_id) as total_customers
                FROM bookings b
                JOIN bookers bk ON b.booker_id = bk.id
                WHERE $where AND bk.area IS NOT NULL AND bk.area != ''";
$totals = $conn->query($totalsQuery)->fetch_assoc();

// Top performing area
$topAreaQuery = "SELECT 
                 bk.area,
                 SUM(b.total_amount) as sales
                 FROM bookings b
                 JOIN bookers bk ON b.booker_id = bk.id
                 WHERE $where AND bk.area IS NOT NULL AND bk.area != ''
                 GROUP BY bk.area
                 ORDER BY sales DESC
                 LIMIT 1";
$topArea = $conn->query($topAreaQuery)->fetch_assoc();

// Get bookers by area (for selected area if any)
if (!empty($area)) {
    $bookersByAreaQuery = "SELECT 
                           bk.id,
                           bk.booker_code,
                           bk.name,
                           bk.phone,
                           COUNT(DISTINCT b.id) as bookings,
                           SUM(b.total_amount) as sales,
                           COUNT(DISTINCT b.customer_id) as customers
                           FROM bookers bk
                           LEFT JOIN bookings b ON bk.id = b.booker_id AND $where
                           WHERE bk.area = '$area' AND bk.is_active = 1
                           GROUP BY bk.id
                           ORDER BY sales DESC";
    $bookersByArea = $conn->query($bookersByAreaQuery);
    
    // Get customers by area
    $customersByAreaQuery = "SELECT 
                             c.id,
                             c.customer_code,
                             c.name,
                             c.phone,
                             COUNT(b.id) as bookings,
                             SUM(b.total_amount) as total_spent
                             FROM bookings b
                             JOIN customers c ON b.customer_id = c.id
                             JOIN bookers bk ON b.booker_id = bk.id
                             WHERE $where AND bk.area = '$area'
                             GROUP BY c.id
                             ORDER BY total_spent DESC
                             LIMIT 10";
    $customersByArea = $conn->query($customersByAreaQuery);
}

include '../../includes/header.php';
?>

<!-- Page Header -->
<div class="mb-6">
    <h1 class="text-3xl font-bold text-gray-800">Area-wise Sales Report</h1>
    <p class="text-gray-600">Geographic sales analysis and territory performance</p>
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
            <label class="block text-sm font-semibold text-gray-700 mb-2">Filter by Area</label>
            <select name="area" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">All Areas</option>
                <?php 
                $areasFilter->data_seek(0);
                while ($areaRow = $areasFilter->fetch_assoc()): 
                ?>
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
        </div>
    </form>
</div>

<!-- Summary Cards -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
    <div class="bg-white rounded-lg shadow p-6">
        <p class="text-gray-500 text-sm">Total Areas</p>
        <h3 class="text-3xl font-bold text-gray-800"><?php echo $totals['total_areas']; ?></h3>
        <p class="text-xs text-gray-500 mt-1">Territories covered</p>
    </div>
    
    <div class="bg-white rounded-lg shadow p-6">
        <p class="text-gray-500 text-sm">Total Bookers</p>
        <h3 class="text-3xl font-bold text-blue-600"><?php echo $totals['total_bookers']; ?></h3>
        <p class="text-xs text-gray-500 mt-1">Sales team</p>
    </div>
    
    <div class="bg-white rounded-lg shadow p-6">
        <p class="text-gray-500 text-sm">Total Sales</p>
        <h3 class="text-2xl font-bold text-green-600"><?php echo formatCurrency($totals['total_sales']); ?></h3>
        <p class="text-xs text-gray-500 mt-1"><?php echo number_format($totals['total_bookings']); ?> bookings</p>
    </div>
    
    <div class="bg-white rounded-lg shadow p-6">
        <p class="text-gray-500 text-sm">Top Area</p>
        <h3 class="text-lg font-bold text-purple-600"><?php echo $topArea['area'] ?? 'N/A'; ?></h3>
        <p class="text-xs text-gray-500 mt-1"><?php echo formatCurrency($topArea['sales'] ?? 0); ?></p>
    </div>
</div>

<!-- Area Performance Cards -->
<div class="bg-white rounded-lg shadow p-6 mb-6">
    <h3 class="text-lg font-bold text-gray-800 mb-4">Area Performance Overview</h3>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <?php 
        $areas->data_seek(0);
        while ($areaData = $areas->fetch_assoc()): 
            $percentage = $totals['total_sales'] > 0 ? ($areaData['total_sales'] / $totals['total_sales']) * 100 : 0;
            $salesPerBooker = $areaData['booker_count'] > 0 ? $areaData['total_sales'] / $areaData['booker_count'] : 0;
        ?>
            <div class="border-2 border-gray-200 rounded-lg p-5 hover:shadow-lg transition hover:border-blue-300">
                <div class="flex justify-between items-start mb-3">
                    <h4 class="text-xl font-bold text-gray-900"><?php echo $areaData['area']; ?></h4>
                    <span class="bg-blue-100 text-blue-700 px-2 py-1 rounded text-xs font-semibold">
                        <?php echo $areaData['booker_count']; ?> bookers
                    </span>
                </div>
                
                <div class="space-y-2 mb-3">
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">Total Sales:</span>
                        <span class="font-bold text-green-600"><?php echo formatCurrency($areaData['total_sales']); ?></span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">Bookings:</span>
                        <span class="font-semibold"><?php echo number_format($areaData['total_bookings']); ?></span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">Customers:</span>
                        <span class="font-semibold"><?php echo $areaData['unique_customers']; ?></span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">Avg Booking:</span>
                        <span class="font-semibold"><?php echo formatCurrency($areaData['avg_booking_value']); ?></span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">Per Booker:</span>
                        <span class="font-semibold text-purple-600"><?php echo formatCurrency($salesPerBooker); ?></span>
                    </div>
                </div>
                
                <div class="mb-2">
                    <div class="w-full bg-gray-200 rounded-full h-3">
                        <div class="bg-gradient-to-r from-blue-500 to-green-500 h-3 rounded-full" style="width: <?php echo $percentage; ?>%"></div>
                    </div>
                    <p class="text-xs text-gray-600 mt-1"><?php echo number_format($percentage, 1); ?>% of total sales</p>
                </div>
                
                <div class="pt-3 border-t">
                    <a href="?date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>&area=<?php echo urlencode($areaData['area']); ?>" 
                       class="text-blue-600 hover:text-blue-800 text-sm font-semibold">
                        View Details →
                    </a>
                </div>
            </div>
        <?php endwhile; ?>
    </div>
</div>

<!-- Detailed Area Breakdown (if area selected) -->
<?php if (!empty($area)): ?>
    <!-- Bookers in Area -->
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <h3 class="text-lg font-bold text-gray-800 mb-4">Bookers in <?php echo $area; ?></h3>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50 border-b">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">#</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Booker</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Contact</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Bookings</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Sales</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Customers</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Avg Booking</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php 
                    $rank = 1;
                    while ($booker = $bookersByArea->fetch_assoc()): 
                        $avgBooking = $booker['bookings'] > 0 ? $booker['sales'] / $booker['bookings'] : 0;
                    ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 text-sm font-bold"><?php echo $rank++; ?></td>
                            <td class="px-6 py-4">
                                <div class="text-sm font-medium text-gray-900"><?php echo $booker['name']; ?></div>
                                <div class="text-xs text-gray-500"><?php echo $booker['booker_code']; ?></div>
                            </td>
                            <td class="px-6 py-4 text-sm"><?php echo $booker['phone']; ?></td>
                            <td class="px-6 py-4 text-sm text-right font-semibold"><?php echo number_format($booker['bookings']); ?></td>
                            <td class="px-6 py-4 text-sm text-right font-bold text-green-600">
                                <?php echo formatCurrency($booker['sales']); ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-right"><?php echo $booker['customers']; ?></td>
                            <td class="px-6 py-4 text-sm text-right"><?php echo formatCurrency($avgBooking); ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Top Customers in Area -->
    <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-bold text-gray-800 mb-4">Top 10 Customers in <?php echo $area; ?></h3>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50 border-b">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">#</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Customer</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Contact</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Bookings</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total Spent</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Avg Order</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php 
                    $rank = 1;
                    while ($customer = $customersByArea->fetch_assoc()): 
                        $avgOrder = $customer['bookings'] > 0 ? $customer['total_spent'] / $customer['bookings'] : 0;
                    ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 text-sm font-bold"><?php echo $rank++; ?></td>
                            <td class="px-6 py-4">
                                <div class="text-sm font-medium text-gray-900"><?php echo $customer['name']; ?></div>
                                <div class="text-xs text-gray-500"><?php echo $customer['customer_code']; ?></div>
                            </td>
                            <td class="px-6 py-4 text-sm"><?php echo $customer['phone']; ?></td>
                            <td class="px-6 py-4 text-sm text-right font-semibold"><?php echo number_format($customer['bookings']); ?></td>
                            <td class="px-6 py-4 text-sm text-right font-bold text-blue-600">
                                <?php echo formatCurrency($customer['total_spent']); ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-right"><?php echo formatCurrency($avgOrder); ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<!-- Performance Insights -->
<div class="mt-6 bg-blue-50 border border-blue-200 rounded-lg p-6">
    <h3 class="text-lg font-bold text-blue-900 mb-3">📊 Territory Insights</h3>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm text-blue-800">
        <div>
            <p class="font-semibold mb-2">Avg Sales per Area:</p>
            <p class="text-2xl font-bold text-blue-900">
                <?php echo formatCurrency($totals['total_areas'] > 0 ? $totals['total_sales'] / $totals['total_areas'] : 0); ?>
            </p>
            <p class="text-xs mt-1">Territory performance</p>
        </div>
        <div>
            <p class="font-semibold mb-2">Avg Bookers per Area:</p>
            <p class="text-2xl font-bold text-blue-900">
                <?php echo $totals['total_areas'] > 0 ? number_format($totals['total_bookers'] / $totals['total_areas'], 1) : 0; ?>
            </p>
            <p class="text-xs mt-1">Team distribution</p>
        </div>
        <div>
            <p class="font-semibold mb-2">Customers per Area:</p>
            <p class="text-2xl font-bold text-blue-900">
                <?php echo $totals['total_areas'] > 0 ? number_format($totals['total_customers'] / $totals['total_areas'], 0) : 0; ?>
            </p>
            <p class="text-xs mt-1">Market coverage</p>
        </div>
    </div>
</div>


<?php
include '../../includes/footer.php';
?>