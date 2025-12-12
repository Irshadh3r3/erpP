<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';

requireLogin();

$conn = getDBConnection();
$pageTitle = 'Booking Conversion Report';

// Date filters
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-01');
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-t');
$booker_id = isset($_GET['booker_id']) ? (int)$_GET['booker_id'] : 0;
$status = isset($_GET['status']) ? clean($_GET['status']) : 'all';

// Build query
$where = "booking_date BETWEEN '$date_from' AND '$date_to'";
if ($booker_id > 0) {
    $where .= " AND booker_id = $booker_id";
}
if ($status !== 'all') {
    $where .= " AND status = '$status'";
}

// Get conversion funnel data
$funnelQuery = "SELECT 
                COUNT(*) as total_bookings,
                COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_count,
                COUNT(CASE WHEN status = 'confirmed' THEN 1 END) as confirmed_count,
                COUNT(CASE WHEN status = 'invoiced' THEN 1 END) as invoiced_count,
                COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled_count,
                SUM(total_amount) as total_value,
                SUM(CASE WHEN status = 'pending' THEN total_amount ELSE 0 END) as pending_value,
                SUM(CASE WHEN status = 'confirmed' THEN total_amount ELSE 0 END) as confirmed_value,
                SUM(CASE WHEN status = 'invoiced' THEN total_amount ELSE 0 END) as invoiced_value,
                SUM(CASE WHEN status = 'cancelled' THEN total_amount ELSE 0 END) as cancelled_value,
                AVG(CASE WHEN status = 'invoiced' THEN DATEDIFF(delivery_date, booking_date) END) as avg_conversion_days
                FROM bookings
                WHERE $where";
$funnel = $conn->query($funnelQuery)->fetch_assoc();

// Calculate conversion rates
$conversionRate = $funnel['total_bookings'] > 0 ? 
                 ($funnel['invoiced_count'] / $funnel['total_bookings']) * 100 : 0;
$cancellationRate = $funnel['total_bookings'] > 0 ? 
                   ($funnel['cancelled_count'] / $funnel['total_bookings']) * 100 : 0;

// Get bookers for filter
$bookersQuery = "SELECT id, name, booker_code FROM bookers WHERE is_active = 1 ORDER BY name ASC";
$bookers = $conn->query($bookersQuery);

// Booker-wise conversion
$bookerConversionQuery = "SELECT 
                          bk.id,
                          bk.booker_code,
                          bk.name,
                          bk.area,
                          COUNT(b.id) as total_bookings,
                          COUNT(CASE WHEN b.status = 'pending' THEN 1 END) as pending,
                          COUNT(CASE WHEN b.status = 'confirmed' THEN 1 END) as confirmed,
                          COUNT(CASE WHEN b.status = 'invoiced' THEN 1 END) as invoiced,
                          COUNT(CASE WHEN b.status = 'cancelled' THEN 1 END) as cancelled,
                          SUM(CASE WHEN b.status = 'invoiced' THEN b.total_amount ELSE 0 END) as sales_value,
                          AVG(CASE WHEN b.status = 'invoiced' THEN DATEDIFF(b.delivery_date, b.booking_date) END) as avg_days
                          FROM bookers bk
                          LEFT JOIN bookings b ON bk.id = b.booker_id AND $where
                          WHERE bk.is_active = 1
                          GROUP BY bk.id
                          HAVING total_bookings > 0
                          ORDER BY invoiced DESC";
$bookerConversion = $conn->query($bookerConversionQuery);

// Time-based conversion analysis
$timeConversionQuery = "SELECT 
                        DATE_FORMAT(booking_date, '%Y-%m') as month,
                        COUNT(*) as bookings,
                        COUNT(CASE WHEN status = 'invoiced' THEN 1 END) as converted,
                        COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled,
                        SUM(CASE WHEN status = 'invoiced' THEN total_amount ELSE 0 END) as sales
                        FROM bookings
                        WHERE booking_date BETWEEN DATE_SUB('$date_to', INTERVAL 6 MONTH) AND '$date_to'
                        GROUP BY DATE_FORMAT(booking_date, '%Y-%m')
                        ORDER BY month DESC
                        LIMIT 6";
$timeConversion = $conn->query($timeConversionQuery);

// Reason for cancellation (if you track it in notes)
$pendingBookings = "SELECT 
                    b.id,
                    b.booking_number,
                    b.booking_date,
                    b.customer_name,
                    b.total_amount,
                    b.status,
                    DATEDIFF(CURDATE(), b.booking_date) as days_pending,
                    bk.name as booker_name
                    FROM bookings b
                    JOIN bookers bk ON b.booker_id = bk.id
                    WHERE b.status IN ('pending', 'confirmed')
                    AND b.booking_date BETWEEN '$date_from' AND '$date_to'
                    ORDER BY b.booking_date ASC
                    LIMIT 20";
$pending = $conn->query($pendingBookings);

// Top converting bookers
$topConverters = "SELECT 
                  bk.name,
                  bk.booker_code,
                  COUNT(b.id) as bookings,
                  COUNT(CASE WHEN b.status = 'invoiced' THEN 1 END) as converted,
                  (COUNT(CASE WHEN b.status = 'invoiced' THEN 1 END) / COUNT(b.id)) * 100 as conversion_rate
                  FROM bookers bk
                  LEFT JOIN bookings b ON bk.id = b.booker_id AND $where
                  WHERE bk.is_active = 1
                  GROUP BY bk.id
                  HAVING bookings >= 5
                  ORDER BY conversion_rate DESC
                  LIMIT 5";
$topConv = $conn->query($topConverters);

include '../../includes/header.php';
?>

<!-- Page Header -->
<div class="mb-6">
    <h1 class="text-3xl font-bold text-gray-800">Booking Conversion Report</h1>
    <p class="text-gray-600">Track booking to sales conversion funnel and performance</p>
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
            <label class="block text-sm font-semibold text-gray-700 mb-2">Booker</label>
            <select name="booker_id" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="0">All Bookers</option>
                <?php 
                $bookers->data_seek(0);
                while ($bk = $bookers->fetch_assoc()): 
                ?>
                    <option value="<?php echo $bk['id']; ?>" <?php echo $booker_id == $bk['id'] ? 'selected' : ''; ?>>
                        <?php echo $bk['name']; ?>
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

<!-- Key Metrics -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
    <div class="bg-white rounded-lg shadow p-6">
        <p class="text-gray-500 text-sm">Total Bookings</p>
        <h3 class="text-3xl font-bold text-gray-800"><?php echo number_format($funnel['total_bookings']); ?></h3>
        <p class="text-xs text-gray-500 mt-1"><?php echo formatCurrency($funnel['total_value']); ?> value</p>
    </div>
    
    <div class="bg-white rounded-lg shadow p-6">
        <p class="text-gray-500 text-sm">Conversion Rate</p>
        <h3 class="text-3xl font-bold text-green-600"><?php echo number_format($conversionRate, 1); ?>%</h3>
        <p class="text-xs text-gray-500 mt-1"><?php echo $funnel['invoiced_count']; ?> converted to sales</p>
    </div>
    
    <div class="bg-white rounded-lg shadow p-6">
        <p class="text-gray-500 text-sm">Cancellation Rate</p>
        <h3 class="text-3xl font-bold text-red-600"><?php echo number_format($cancellationRate, 1); ?>%</h3>
        <p class="text-xs text-gray-500 mt-1"><?php echo $funnel['cancelled_count']; ?> cancelled</p>
    </div>
    
    <div class="bg-white rounded-lg shadow p-6">
        <p class="text-gray-500 text-sm">Avg Conversion Time</p>
        <h3 class="text-3xl font-bold text-purple-600"><?php echo round($funnel['avg_conversion_days'] ?? 0); ?></h3>
        <p class="text-xs text-gray-500 mt-1">Days to invoice</p>
    </div>
</div>

<!-- Conversion Funnel -->
<div class="bg-white rounded-lg shadow p-6 mb-6">
    <h3 class="text-lg font-bold text-gray-800 mb-6">📊 Conversion Funnel</h3>
    <div class="space-y-4">
        <!-- Total Bookings -->
        <div class="relative">
            <div class="bg-blue-500 rounded-lg p-6 text-white" style="width: 100%">
                <div class="flex justify-between items-center">
                    <div>
                        <p class="text-blue-100 text-sm uppercase">Total Bookings</p>
                        <p class="text-3xl font-bold"><?php echo number_format($funnel['total_bookings']); ?></p>
                    </div>
                    <div class="text-right">
                        <p class="text-2xl font-bold"><?php echo formatCurrency($funnel['total_value']); ?></p>
                        <p class="text-blue-100 text-sm">100%</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pending -->
        <?php 
        $pendingPercent = $funnel['total_bookings'] > 0 ? 
                         ($funnel['pending_count'] / $funnel['total_bookings']) * 100 : 0;
        ?>
        <div class="relative pl-8">
            <div class="bg-yellow-500 rounded-lg p-5 text-white" style="width: <?php echo $pendingPercent; ?>%">
                <div class="flex justify-between items-center">
                    <div>
                        <p class="text-yellow-100 text-sm uppercase">Pending</p>
                        <p class="text-2xl font-bold"><?php echo number_format($funnel['pending_count']); ?></p>
                    </div>
                    <div class="text-right">
                        <p class="text-xl font-bold"><?php echo formatCurrency($funnel['pending_value']); ?></p>
                        <p class="text-yellow-100 text-sm"><?php echo number_format($pendingPercent, 1); ?>%</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Confirmed -->
        <?php 
        $confirmedPercent = $funnel['total_bookings'] > 0 ? 
                           ($funnel['confirmed_count'] / $funnel['total_bookings']) * 100 : 0;
        ?>
        <div class="relative pl-16">
            <div class="bg-blue-600 rounded-lg p-5 text-white" style="width: <?php echo $confirmedPercent; ?>%">
                <div class="flex justify-between items-center">
                    <div>
                        <p class="text-blue-100 text-sm uppercase">Confirmed</p>
                        <p class="text-2xl font-bold"><?php echo number_format($funnel['confirmed_count']); ?></p>
                    </div>
                    <div class="text-right">
                        <p class="text-xl font-bold"><?php echo formatCurrency($funnel['confirmed_value']); ?></p>
                        <p class="text-blue-100 text-sm"><?php echo number_format($confirmedPercent, 1); ?>%</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Invoiced (Converted) -->
        <?php 
        $invoicedPercent = $funnel['total_bookings'] > 0 ? 
                          ($funnel['invoiced_count'] / $funnel['total_bookings']) * 100 : 0;
        ?>
        <div class="relative pl-24">
            <div class="bg-green-600 rounded-lg p-5 text-white" style="width: <?php echo $invoicedPercent; ?>%">
                <div class="flex justify-between items-center">
                    <div>
                        <p class="text-green-100 text-sm uppercase">Invoiced (Converted)</p>
                        <p class="text-2xl font-bold"><?php echo number_format($funnel['invoiced_count']); ?></p>
                    </div>
                    <div class="text-right">
                        <p class="text-xl font-bold"><?php echo formatCurrency($funnel['invoiced_value']); ?></p>
                        <p class="text-green-100 text-sm"><?php echo number_format($invoicedPercent, 1); ?>%</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Cancelled -->
        <?php 
        $cancelledPercent = $funnel['total_bookings'] > 0 ? 
                           ($funnel['cancelled_count'] / $funnel['total_bookings']) * 100 : 0;
        ?>
        <div class="relative pl-8">
            <div class="bg-red-500 rounded-lg p-4 text-white" style="width: <?php echo $cancelledPercent; ?>%">
                <div class="flex justify-between items-center">
                    <div>
                        <p class="text-red-100 text-sm uppercase">Cancelled</p>
                        <p class="text-xl font-bold"><?php echo number_format($funnel['cancelled_count']); ?></p>
                    </div>
                    <div class="text-right">
                        <p class="text-lg font-bold"><?php echo formatCurrency($funnel['cancelled_value']); ?></p>
                        <p class="text-red-100 text-sm"><?php echo number_format($cancelledPercent, 1); ?>%</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Monthly Trend -->
<div class="bg-white rounded-lg shadow p-6 mb-6">
    <h3 class="text-lg font-bold text-gray-800 mb-4">6-Month Conversion Trend</h3>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Month</th>
                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Bookings</th>
                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Converted</th>
                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Cancelled</th>
                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Rate</th>
                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Sales</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php while ($month = $timeConversion->fetch_assoc()): 
                    $monthRate = $month['bookings'] > 0 ? ($month['converted'] / $month['bookings']) * 100 : 0;
                ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-sm font-medium">
                            <?php echo date('F Y', strtotime($month['month'] . '-01')); ?>
                        </td>
                        <td class="px-4 py-3 text-sm text-right"><?php echo $month['bookings']; ?></td>
                        <td class="px-4 py-3 text-sm text-right font-semibold text-green-600">
                            <?php echo $month['converted']; ?>
                        </td>
                        <td class="px-4 py-3 text-sm text-right text-red-600">
                            <?php echo $month['cancelled']; ?>
                        </td>
                        <td class="px-4 py-3 text-sm text-right">
                            <span class="<?php echo $monthRate >= 70 ? 'text-green-600' : ($monthRate >= 50 ? 'text-yellow-600' : 'text-red-600'); ?> font-bold">
                                <?php echo number_format($monthRate, 1); ?>%
                            </span>
                        </td>
                        <td class="px-4 py-3 text-sm text-right font-bold text-blue-600">
                            <?php echo formatCurrency($month['sales']); ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Booker Performance -->
<div class="bg-white rounded-lg shadow overflow-hidden mb-6">
    <div class="p-6 border-b">
        <h3 class="text-lg font-bold text-gray-800">Booker-wise Conversion Performance</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Booker</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Area</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Pending</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Confirmed</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Invoiced</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Cancelled</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Rate</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Avg Days</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php 
                $bookerConversion->data_seek(0);
                while ($bk = $bookerConversion->fetch_assoc()): 
                    $bkRate = $bk['total_bookings'] > 0 ? ($bk['invoiced'] / $bk['total_bookings']) * 100 : 0;
                ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4">
                            <div class="text-sm font-medium text-gray-900"><?php echo $bk['name']; ?></div>
                            <div class="text-xs text-gray-500"><?php echo $bk['booker_code']; ?></div>
                        </td>
                        <td class="px-6 py-4 text-sm"><?php echo $bk['area'] ?: 'N/A'; ?></td>
                        <td class="px-6 py-4 text-sm text-right font-semibold"><?php echo $bk['total_bookings']; ?></td>
                        <td class="px-6 py-4 text-sm text-right text-yellow-600"><?php echo $bk['pending']; ?></td>
                        <td class="px-6 py-4 text-sm text-right text-blue-600"><?php echo $bk['confirmed']; ?></td>
                        <td class="px-6 py-4 text-sm text-right text-green-600 font-bold"><?php echo $bk['invoiced']; ?></td>
                        <td class="px-6 py-4 text-sm text-right text-red-600"><?php echo $bk['cancelled']; ?></td>
                        <td class="px-6 py-4 text-sm text-right">
                            <span class="<?php echo $bkRate >= 70 ? 'text-green-600' : ($bkRate >= 50 ? 'text-yellow-600' : 'text-red-600'); ?> font-bold">
                                <?php echo number_format($bkRate, 1); ?>%
                            </span>
                        </td>
                        <td class="px-6 py-4 text-sm text-right">
                            <?php echo $bk['avg_days'] ? round($bk['avg_days']) : '-'; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Top Converters & Pending Bookings -->
<div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
    <!-- Top Converters -->
    <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-bold text-gray-800 mb-4">🏆 Top 5 Converters</h3>
        <p class="text-xs text-gray-500 mb-4">Bookers with 5+ bookings</p>
        <div class="space-y-3">
            <?php 
            $rank = 1;
            while ($tc = $topConv->fetch_assoc()): 
            ?>
                <div class="flex items-center justify-between p-3 border border-gray-200 rounded-lg hover:bg-gray-50">
                    <div class="flex items-center">
                        <span class="font-bold text-gray-400 text-lg mr-3"><?php echo $rank++; ?></span>
                        <div>
                            <p class="font-semibold text-gray-900"><?php echo $tc['name']; ?></p>
                            <p class="text-xs text-gray-500"><?php echo $tc['converted']; ?> / <?php echo $tc['bookings']; ?> bookings</p>
                        </div>
                    </div>
                    <span class="text-2xl font-bold text-green-600"><?php echo number_format($tc['conversion_rate'], 1); ?>%</span>
                </div>
            <?php endwhile; ?>
        </div>
    </div>

    <!-- Pending Bookings Needing Attention -->
    <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-bold text-gray-800 mb-4">⏳ Pending Bookings (Oldest 20)</h3>
        <div class="space-y-2 max-h-96 overflow-y-auto">
            <?php while ($pb = $pending->fetch_assoc()): 
                $urgency = $pb['days_pending'] > 7 ? 'border-red-300 bg-red-50' : 
                          ($pb['days_pending'] > 3 ? 'border-yellow-300 bg-yellow-50' : 'border-gray-200');
            ?>
                <div class="flex items-center justify-between p-2 border <?php echo $urgency; ?> rounded">
                    <div class="flex-1">
                        <p class="font-semibold text-sm text-gray-900"><?php echo $pb['booking_number']; ?></p>
                        <p class="text-xs text-gray-600"><?php echo $pb['customer_name']; ?> • <?php echo $pb['booker_name']; ?></p>
                    </div>
                    <div class="text-right ml-2">
                        <p class="text-sm font-bold"><?php echo formatCurrency($pb['total_amount']); ?></p>
                        <p class="text-xs <?php echo $pb['days_pending'] > 7 ? 'text-red-600' : 'text-gray-500'; ?> font-semibold">
                            <?php echo $pb['days_pending']; ?> days
                        </p>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    </div>
</div>

<!-- Insights -->
<div class="bg-blue-50 border border-blue-200 rounded-lg p-6">
    <h3 class="text-lg font-bold text-blue-900 mb-3">💡 Conversion Insights</h3>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm text-blue-800">
        <div>
            <p class="font-semibold mb-2">Overall Performance:</p>
            <ul class="space-y-1">
                <li>• Conversion Rate: <strong class="<?php echo $conversionRate >= 70 ? 'text-green-600' : ($conversionRate >= 50 ? 'text-yellow-600' : 'text-red-600'); ?>"><?php echo number_format($conversionRate, 1); ?>%</strong></li>
                <li>• Target: <strong>70%+</strong> is excellent</li>
                <li>• Current Status: <strong><?php echo $conversionRate >= 70 ? 'Excellent' : ($conversionRate >= 50 ? 'Good' : 'Needs Improvement'); ?></strong></li>
            </ul>
        </div>
        <div>
            <p class="font-semibold mb-2">Action Items:</p>
            <ul class="space-y-1">
                <li>• Follow up on pending bookings > 3 days</li>
                <li>• Investigate cancelled bookings</li>
                <li>• Share best practices from top converters</li>
            </ul>
        </div>
        <div>
            <p class="font-semibold mb-2">Opportunities:</p>
            <ul class="space-y-1">
                <li>• Pending Value: <strong><?php echo formatCurrency($funnel['pending_value']); ?></strong></li>
                <li>• Confirmed Value: <strong><?php echo formatCurrency($funnel['confirmed_value']); ?></strong></li>
                <li>• Total Pipeline: <strong><?php echo formatCurrency($funnel['pending_value'] + $funnel['confirmed_value']); ?></strong></li>
            </ul>
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