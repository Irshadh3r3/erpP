<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';

requireLogin();

$conn = getDBConnection();
$pageTitle = 'Sales by Customer Report';

// Date filters
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-01');
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-t');
$area = isset($_GET['area']) ? clean($_GET['area']) : '';
$sort_by = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'revenue';
$min_amount = isset($_GET['min_amount']) ? (float)$_GET['min_amount'] : 0;

// Build query
$where = "s.sale_date BETWEEN '$date_from' AND '$date_to'";
if (!empty($area)) {
    $where .= " AND c.area = '$area'";
}
if ($min_amount > 0) {
    $having = "HAVING total_sales >= $min_amount";
} else {
    $having = "";
}

// Sort field
$sortField = match($sort_by) {
    'orders' => 'total_orders',
    'frequency' => 'avg_order_value',
    default => 'total_sales'
};

// Get customer sales data
$query = "SELECT 
          c.id,
          c.customer_code,
          c.name as customer_name,
          c.phone,
          c.area,
          c.credit_limit,
          c.current_balance,
          COUNT(DISTINCT s.id) as total_orders,
          SUM(s.total_amount) as total_sales,
          SUM(s.paid_amount) as total_paid,
          SUM(s.total_amount - s.paid_amount) as outstanding,
          AVG(s.total_amount) as avg_order_value,
          MIN(s.sale_date) as first_order_date,
          MAX(s.sale_date) as last_order_date,
          DATEDIFF('$date_to', MAX(s.sale_date)) as days_since_last_order
          FROM customers c
          JOIN sales s ON c.id = s.customer_id
          WHERE $where
          GROUP BY c.id
          $having
          ORDER BY $sortField DESC";

$customers = $conn->query($query);

// Get areas for filter
$areasQuery = "SELECT DISTINCT area FROM customers WHERE area IS NOT NULL AND area != '' ORDER BY area ASC";
$areas = $conn->query($areasQuery);

// Get overall totals
$totalsQuery = "SELECT 
                COUNT(DISTINCT c.id) as total_customers,
                COUNT(DISTINCT s.id) as total_orders,
                SUM(s.total_amount) as total_sales,
                SUM(s.paid_amount) as total_paid,
                SUM(s.total_amount - s.paid_amount) as total_outstanding,
                AVG(s.total_amount) as avg_order_value
                FROM customers c
                JOIN sales s ON c.id = s.customer_id
                WHERE $where";
$totals = $conn->query($totalsQuery)->fetch_assoc();

// Customer segmentation
$segmentQuery = "SELECT 
                 CASE 
                   WHEN total_sales >= 100000 THEN 'Premium'
                   WHEN total_sales >= 50000 THEN 'Gold'
                   WHEN total_sales >= 10000 THEN 'Silver'
                   ELSE 'Bronze'
                 END as segment,
                 COUNT(*) as customer_count,
                 SUM(total_sales) as segment_sales
                 FROM (
                   SELECT c.id, SUM(s.total_amount) as total_sales
                   FROM customers c
                   JOIN sales s ON c.id = s.customer_id
                   WHERE $where
                   GROUP BY c.id
                 ) as customer_totals
                 GROUP BY segment
                 ORDER BY FIELD(segment, 'Premium', 'Gold', 'Silver', 'Bronze')";
$segments = $conn->query($segmentQuery);

// New vs Repeat customers
$customerTypeQuery = "SELECT 
                      SUM(CASE WHEN order_count = 1 THEN 1 ELSE 0 END) as new_customers,
                      SUM(CASE WHEN order_count > 1 THEN 1 ELSE 0 END) as repeat_customers
                      FROM (
                        SELECT c.id, COUNT(s.id) as order_count
                        FROM customers c
                        JOIN sales s ON c.id = s.customer_id
                        WHERE $where
                        GROUP BY c.id
                      ) as customer_orders";
$customerTypes = $conn->query($customerTypeQuery)->fetch_assoc();

include '../../includes/header.php';
?>

<!-- Page Header -->
<div class="mb-6">
    <h1 class="text-3xl font-bold text-gray-800">Sales by Customer Report</h1>
    <p class="text-gray-600">Customer performance and lifetime value analysis</p>
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
        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">Sort By</label>
            <select name="sort_by" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="revenue" <?php echo $sort_by === 'revenue' ? 'selected' : ''; ?>>Total Sales</option>
                <option value="orders" <?php echo $sort_by === 'orders' ? 'selected' : ''; ?>>Order Count</option>
                <option value="frequency" <?php echo $sort_by === 'frequency' ? 'selected' : ''; ?>>Avg Order Value</option>
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
<div class="grid grid-cols-1 md:grid-cols-5 gap-6 mb-6">
    <div class="bg-white rounded-lg shadow p-6">
        <p class="text-gray-500 text-sm">Active Customers</p>
        <h3 class="text-3xl font-bold text-gray-800"><?php echo number_format($totals['total_customers']); ?></h3>
        <p class="text-xs text-gray-500 mt-1">Made purchases</p>
    </div>
    
    <div class="bg-white rounded-lg shadow p-6">
        <p class="text-gray-500 text-sm">Total Orders</p>
        <h3 class="text-3xl font-bold text-blue-600"><?php echo number_format($totals['total_orders']); ?></h3>
        <p class="text-xs text-gray-500 mt-1">
            <?php echo $totals['total_customers'] > 0 ? number_format($totals['total_orders'] / $totals['total_customers'], 1) : 0; ?> per customer
        </p>
    </div>
    
    <div class="bg-white rounded-lg shadow p-6">
        <p class="text-gray-500 text-sm">Total Sales</p>
        <h3 class="text-2xl font-bold text-green-600"><?php echo formatCurrency($totals['total_sales']); ?></h3>
        <p class="text-xs text-gray-500 mt-1">Gross revenue</p>
    </div>
    
    <div class="bg-white rounded-lg shadow p-6">
        <p class="text-gray-500 text-sm">Avg Order Value</p>
        <h3 class="text-2xl font-bold text-purple-600"><?php echo formatCurrency($totals['avg_order_value']); ?></h3>
        <p class="text-xs text-gray-500 mt-1">Per transaction</p>
    </div>
    
    <div class="bg-white rounded-lg shadow p-6">
        <p class="text-gray-500 text-sm">Outstanding</p>
        <h3 class="text-2xl font-bold text-orange-600"><?php echo formatCurrency($totals['total_outstanding']); ?></h3>
        <p class="text-xs text-gray-500 mt-1">Receivables</p>
    </div>
</div>

<!-- Customer Segmentation & Type Analysis -->
<div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
    <!-- Customer Segmentation -->
    <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-bold text-gray-800 mb-4">Customer Segmentation</h3>
        <div class="space-y-3">
            <?php 
            $segments->data_seek(0);
            $segmentColors = [
                'Premium' => 'bg-purple-100 text-purple-700 border-purple-300',
                'Gold' => 'bg-yellow-100 text-yellow-700 border-yellow-300',
                'Silver' => 'bg-gray-100 text-gray-700 border-gray-300',
                'Bronze' => 'bg-orange-100 text-orange-700 border-orange-300'
            ];
            while ($segment = $segments->fetch_assoc()): 
                $colorClass = $segmentColors[$segment['segment']];
                $percentage = $totals['total_sales'] > 0 ? ($segment['segment_sales'] / $totals['total_sales']) * 100 : 0;
            ?>
                <div class="<?php echo $colorClass; ?> border-2 rounded-lg p-4">
                    <div class="flex justify-between items-center mb-2">
                        <span class="font-bold text-lg"><?php echo $segment['segment']; ?></span>
                        <span class="font-semibold"><?php echo $segment['customer_count']; ?> customers</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <div class="flex-1 mr-3">
                            <div class="w-full bg-white rounded-full h-2">
                                <div class="bg-current h-2 rounded-full opacity-50" style="width: <?php echo $percentage; ?>%"></div>
                            </div>
                        </div>
                        <span class="font-bold"><?php echo formatCurrency($segment['segment_sales']); ?></span>
                    </div>
                    <p class="text-xs mt-1"><?php echo number_format($percentage, 1); ?>% of total sales</p>
                </div>
            <?php endwhile; ?>
        </div>
    </div>

    <!-- New vs Repeat Customers -->
    <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-bold text-gray-800 mb-4">Customer Loyalty Analysis</h3>
        <div class="space-y-4">
            <div class="bg-green-50 border-2 border-green-300 rounded-lg p-4">
                <div class="flex justify-between items-center mb-2">
                    <span class="font-bold text-green-700">Repeat Customers</span>
                    <span class="text-2xl font-bold text-green-700"><?php echo $customerTypes['repeat_customers']; ?></span>
                </div>
                <p class="text-sm text-green-600">
                    <?php 
                    $repeatPercent = $totals['total_customers'] > 0 ? 
                                    ($customerTypes['repeat_customers'] / $totals['total_customers']) * 100 : 0;
                    echo number_format($repeatPercent, 1); 
                    ?>% made multiple purchases
                </p>
            </div>
            
            <div class="bg-blue-50 border-2 border-blue-300 rounded-lg p-4">
                <div class="flex justify-between items-center mb-2">
                    <span class="font-bold text-blue-700">New Customers</span>
                    <span class="text-2xl font-bold text-blue-700"><?php echo $customerTypes['new_customers']; ?></span>
                </div>
                <p class="text-sm text-blue-600">
                    <?php 
                    $newPercent = $totals['total_customers'] > 0 ? 
                                 ($customerTypes['new_customers'] / $totals['total_customers']) * 100 : 0;
                    echo number_format($newPercent, 1); 
                    ?>% first-time buyers
                </p>
            </div>

            <div class="bg-gray-50 border border-gray-300 rounded-lg p-3">
                <p class="text-sm text-gray-700">
                    <strong>Retention Rate:</strong> 
                    <span class="text-lg font-bold text-purple-600"><?php echo number_format($repeatPercent, 1); ?>%</span>
                </p>
                <p class="text-xs text-gray-600 mt-1">
                    <?php echo $repeatPercent >= 60 ? 'Excellent' : ($repeatPercent >= 40 ? 'Good' : 'Needs Improvement'); ?> - 
                    Focus on converting new to repeat customers
                </p>
            </div>
        </div>
    </div>
</div>

<!-- Customer Details Table -->
<div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="p-6 border-b">
        <h3 class="text-lg font-bold text-gray-800">Customer Sales Details</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">#</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Customer</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Area</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Orders</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total Sales</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Avg Order</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Outstanding</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Last Order</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Segment</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php if ($customers->num_rows > 0): ?>
                    <?php 
                    $rank = 1;
                    while ($customer = $customers->fetch_assoc()): 
                        // Determine segment
                        $segment = 'Bronze';
                        $segmentColor = 'bg-orange-100 text-orange-700';
                        if ($customer['total_sales'] >= 100000) {
                            $segment = 'Premium';
                            $segmentColor = 'bg-purple-100 text-purple-700';
                        } elseif ($customer['total_sales'] >= 50000) {
                            $segment = 'Gold';
                            $segmentColor = 'bg-yellow-100 text-yellow-700';
                        } elseif ($customer['total_sales'] >= 10000) {
                            $segment = 'Silver';
                            $segmentColor = 'bg-gray-100 text-gray-700';
                        }
                        
                        // Last order warning
                        $daysColor = $customer['days_since_last_order'] > 30 ? 'text-red-600' : 
                                    ($customer['days_since_last_order'] > 14 ? 'text-orange-600' : 'text-green-600');
                    ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 text-sm font-bold text-gray-900"><?php echo $rank++; ?></td>
                            <td class="px-6 py-4">
                                <div class="text-sm font-medium text-gray-900">
                                    <a href="../customers/view.php?id=<?php echo $customer['id']; ?>" 
                                       class="text-blue-600 hover:text-blue-800">
                                        <?php echo $customer['customer_name']; ?>
                                    </a>
                                </div>
                                <div class="text-xs text-gray-500"><?php echo $customer['customer_code']; ?> • <?php echo $customer['phone']; ?></div>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-600">
                                <?php echo $customer['area'] ?: 'N/A'; ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-right font-semibold">
                                <?php echo number_format($customer['total_orders']); ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-right font-bold text-blue-600">
                                <?php echo formatCurrency($customer['total_sales']); ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-right">
                                <?php echo formatCurrency($customer['avg_order_value']); ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-right">
                                <span class="<?php echo $customer['outstanding'] > 0 ? 'text-red-600 font-semibold' : 'text-gray-600'; ?>">
                                    <?php echo formatCurrency($customer['outstanding']); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm text-right">
                                <div><?php echo formatDate($customer['last_order_date']); ?></div>
                                <div class="text-xs <?php echo $daysColor; ?>">
                                    <?php echo $customer['days_since_last_order']; ?> days ago
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <span class="<?php echo $segmentColor; ?> px-2 py-1 rounded text-xs font-semibold">
                                    <?php echo $segment; ?>
                                </span>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="9" class="px-6 py-8 text-center text-gray-500">
                            No customer data found for the selected period
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Insights -->
<div class="mt-6 bg-blue-50 border border-blue-200 rounded-lg p-6">
    <h3 class="text-lg font-bold text-blue-900 mb-3">📊 Customer Insights</h3>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm text-blue-800">
        <div>
            <p class="font-semibold mb-2">Average Customer Value:</p>
            <p class="text-2xl font-bold text-blue-900">
                <?php echo formatCurrency($totals['total_customers'] > 0 ? $totals['total_sales'] / $totals['total_customers'] : 0); ?>
            </p>
            <p class="text-xs mt-1">Revenue per customer in period</p>
        </div>
        <div>
            <p class="font-semibold mb-2">Purchase Frequency:</p>
            <p class="text-2xl font-bold text-blue-900">
                <?php echo $totals['total_customers'] > 0 ? number_format($totals['total_orders'] / $totals['total_customers'], 1) : 0; ?>
            </p>
            <p class="text-xs mt-1">Orders per customer</p>
        </div>
        <div>
            <p class="font-semibold mb-2">Collection Rate:</p>
            <p class="text-2xl font-bold text-blue-900">
                <?php echo $totals['total_sales'] > 0 ? number_format(($totals['total_paid'] / $totals['total_sales']) * 100, 1) : 0; ?>%
            </p>
            <p class="text-xs mt-1">Payments received vs sales</p>
        </div>
    </div>
</div>


<?php
include '../../includes/footer.php';
?>