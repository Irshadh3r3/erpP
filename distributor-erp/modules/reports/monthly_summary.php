<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';

requireLogin();

$conn = getDBConnection();
$pageTitle = 'Monthly Summary Report';

// Month/Year filter - defaults to current month
$report_month = isset($_GET['report_month']) ? $_GET['report_month'] : date('Y-m');
$month_start = $report_month . '-01';
$month_end = date('Y-m-t', strtotime($month_start));

// Get month name for display
$month_name = date('F Y', strtotime($month_start));

// Sales Summary
$salesQuery = "SELECT 
               COUNT(*) as total_invoices,
               SUM(subtotal) as subtotal,
               SUM(discount) as discount,
               SUM(tax) as tax,
               SUM(total_amount) as total_sales,
               SUM(paid_amount) as collected,
               SUM(total_amount - paid_amount) as outstanding,
               COUNT(DISTINCT customer_id) as unique_customers,
               AVG(total_amount) as avg_invoice,
               COUNT(CASE WHEN payment_status = 'paid' THEN 1 END) as paid_invoices,
               COUNT(CASE WHEN payment_status = 'partial' THEN 1 END) as partial_invoices,
               COUNT(CASE WHEN payment_status = 'unpaid' THEN 1 END) as unpaid_invoices
               FROM sales
               WHERE sale_date BETWEEN '$month_start' AND '$month_end'";
$sales = $conn->query($salesQuery)->fetch_assoc();

// Purchases Summary
$purchasesQuery = "SELECT 
                   COUNT(*) as total_purchases,
                   SUM(total_amount) as total_spent,
                   SUM(paid_amount) as paid_amount,
                   COUNT(DISTINCT supplier_id) as suppliers_used,
                   AVG(total_amount) as avg_purchase
                   FROM purchases
                   WHERE purchase_date BETWEEN '$month_start' AND '$month_end'";
$purchases = $conn->query($purchasesQuery)->fetch_assoc();

// Payments Summary
$paymentsQuery = "SELECT 
                  COUNT(*) as payment_count,
                  SUM(payment_amount) as total_collected
                  FROM payments
                  WHERE payment_date BETWEEN '$month_start' AND '$month_end'";
$payments = $conn->query($paymentsQuery)->fetch_assoc();

// Bookings Summary
$bookingsQuery = "SELECT 
                  COUNT(*) as total_bookings,
                  SUM(total_amount) as booking_value,
                  COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending,
                  COUNT(CASE WHEN status = 'confirmed' THEN 1 END) as confirmed,
                  COUNT(CASE WHEN status = 'invoiced' THEN 1 END) as invoiced,
                  COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled,
                  COUNT(DISTINCT booker_id) as active_bookers,
                  COUNT(DISTINCT customer_id) as customers_reached
                  FROM bookings
                  WHERE booking_date BETWEEN '$month_start' AND '$month_end'";
$bookings = $conn->query($bookingsQuery)->fetch_assoc();

// Calculate profit
$grossProfit = ($sales['total_sales'] ?? 0) - ($purchases['total_spent'] ?? 0);
$profitMargin = $sales['total_sales'] > 0 ? ($grossProfit / $sales['total_sales']) * 100 : 0;

// Top 10 Products
$topProducts = "SELECT 
                p.name,
                p.sku,
                c.name as category,
                SUM(si.quantity) as quantity,
                SUM(si.subtotal) as revenue
                FROM sales_items si
                JOIN sales s ON si.sale_id = s.id
                JOIN products p ON si.product_id = p.id
                LEFT JOIN categories c ON p.category_id = c.id
                WHERE s.sale_date BETWEEN '$month_start' AND '$month_end'
                GROUP BY p.id
                ORDER BY revenue DESC
                LIMIT 10";
$topProds = $conn->query($topProducts);

// Top 10 Customers
$topCustomers = "SELECT 
                 c.name,
                 c.customer_code,
                 COUNT(s.id) as orders,
                 SUM(s.total_amount) as spent,
                 SUM(s.paid_amount) as paid
                 FROM customers c
                 JOIN sales s ON c.id = s.customer_id
                 WHERE s.sale_date BETWEEN '$month_start' AND '$month_end'
                 GROUP BY c.id
                 ORDER BY spent DESC
                 LIMIT 10";
$topCusts = $conn->query($topCustomers);

// Top Bookers
$topBookers = "SELECT 
               bk.name,
               bk.booker_code,
               COUNT(b.id) as bookings,
               SUM(CASE WHEN b.status = 'invoiced' THEN b.total_amount ELSE 0 END) as sales
               FROM bookers bk
               LEFT JOIN bookings b ON bk.id = b.booker_id
               WHERE b.booking_date BETWEEN '$month_start' AND '$month_end'
               GROUP BY bk.id
               ORDER BY sales DESC
               LIMIT 10";
$topBkrs = $conn->query($topBookers);

// Daily breakdown
$dailyBreakdown = "SELECT 
                   DATE(sale_date) as date,
                   COUNT(*) as invoices,
                   SUM(total_amount) as sales
                   FROM sales
                   WHERE sale_date BETWEEN '$month_start' AND '$month_end'
                   GROUP BY DATE(sale_date)
                   ORDER BY date ASC";
$daily = $conn->query($dailyBreakdown);

// Week-wise summary
$weeklyQuery = "SELECT 
                WEEK(sale_date) as week_num,
                COUNT(*) as invoices,
                SUM(total_amount) as sales,
                MIN(sale_date) as week_start,
                MAX(sale_date) as week_end
                FROM sales
                WHERE sale_date BETWEEN '$month_start' AND '$month_end'
                GROUP BY WEEK(sale_date)
                ORDER BY week_num ASC";
$weekly = $conn->query($weeklyQuery);

// Previous month comparison
$prev_month_start = date('Y-m-01', strtotime($month_start . ' -1 month'));
$prev_month_end = date('Y-m-t', strtotime($prev_month_start));
$prevMonthQuery = "SELECT SUM(total_amount) as total FROM sales WHERE sale_date BETWEEN '$prev_month_start' AND '$prev_month_end'";
$prevMonth = $conn->query($prevMonthQuery)->fetch_assoc();
$prevMonthSales = $prevMonth['total'] ?? 0;
$salesGrowth = $prevMonthSales > 0 ? ((($sales['total_sales'] ?? 0) - $prevMonthSales) / $prevMonthSales) * 100 : 0;

// Stock status at month end
$stockQuery = "SELECT 
               COUNT(*) as total_products,
               SUM(stock_quantity) as total_units,
               SUM(stock_quantity * purchase_price) as stock_value,
               SUM(CASE WHEN stock_quantity = 0 THEN 1 ELSE 0 END) as out_of_stock,
               SUM(CASE WHEN stock_quantity <= reorder_level THEN 1 ELSE 0 END) as low_stock
               FROM products
               WHERE is_active = 1";
$stock = $conn->query($stockQuery)->fetch_assoc();

include '../../includes/header.php';
?>

<!-- Page Header -->
<div class="mb-6">
    <h1 class="text-3xl font-bold text-gray-800">Monthly Summary Report</h1>
    <p class="text-gray-600">Comprehensive business overview for <?php echo $month_name; ?></p>
</div>

<!-- Month Selector -->
<div class="bg-white rounded-lg shadow p-6 mb-6">
    <form method="GET" action="" class="flex items-center gap-4">
        <div class="flex-1">
            <label class="block text-sm font-semibold text-gray-700 mb-2">Select Month</label>
            <input type="month" 
                   name="report_month" 
                   value="<?php echo $report_month; ?>"
                   max="<?php echo date('Y-m'); ?>"
                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
        <div class="flex gap-2 items-end">
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg font-semibold transition">
                View Report
            </button>
            <button type="button" 
                    onclick="window.location.href='?report_month=<?php echo date('Y-m'); ?>'" 
                    class="bg-green-600 hover:bg-green-700 text-white px-6 py-2 rounded-lg font-semibold transition">
                Current Month
            </button>
        </div>
    </form>
</div>

<!-- Executive Summary -->
<div class="bg-gradient-to-r from-blue-600 to-purple-600 rounded-lg shadow-lg p-8 mb-6 text-white">
    <h2 class="text-2xl font-bold mb-6">Executive Summary - <?php echo $month_name; ?></h2>
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
        <div>
            <p class="text-blue-100 text-sm uppercase mb-2">Total Revenue</p>
            <h3 class="text-4xl font-bold"><?php echo formatCurrency($sales['total_sales']); ?></h3>
            <div class="flex items-center mt-2">
                <span class="<?php echo $salesGrowth >= 0 ? 'text-green-300' : 'text-red-300'; ?> text-sm font-semibold">
                    <?php echo $salesGrowth >= 0 ? '↑' : '↓'; ?> <?php echo abs(number_format($salesGrowth, 1)); ?>%
                </span>
                <span class="ml-2 text-blue-100 text-sm">vs last month</span>
            </div>
        </div>
        <div>
            <p class="text-blue-100 text-sm uppercase mb-2">Gross Profit</p>
            <h3 class="text-4xl font-bold"><?php echo formatCurrency($grossProfit); ?></h3>
            <p class="text-blue-100 text-sm mt-2"><?php echo number_format($profitMargin, 1); ?>% margin</p>
        </div>
        <div>
            <p class="text-blue-100 text-sm uppercase mb-2">Total Invoices</p>
            <h3 class="text-4xl font-bold"><?php echo number_format($sales['total_invoices']); ?></h3>
            <p class="text-blue-100 text-sm mt-2"><?php echo $sales['unique_customers']; ?> customers</p>
        </div>
        <div>
            <p class="text-blue-100 text-sm uppercase mb-2">Collection Rate</p>
            <h3 class="text-4xl font-bold">
                <?php echo $sales['total_sales'] > 0 ? number_format(($sales['collected'] / $sales['total_sales']) * 100, 1) : 0; ?>%
            </h3>
            <p class="text-blue-100 text-sm mt-2"><?php echo formatCurrency($sales['collected']); ?> collected</p>
        </div>
    </div>
</div>

<!-- Key Metrics Grid -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
    <!-- Sales -->
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-bold text-gray-800">Sales</h3>
            <div class="bg-blue-100 p-3 rounded-full">
                <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
        </div>
        <div class="space-y-2 text-sm">
            <div class="flex justify-between">
                <span class="text-gray-600">Total Sales:</span>
                <span class="font-bold text-blue-600"><?php echo formatCurrency($sales['total_sales']); ?></span>
            </div>
            <div class="flex justify-between">
                <span class="text-gray-600">Invoices:</span>
                <span class="font-semibold"><?php echo number_format($sales['total_invoices']); ?></span>
            </div>
            <div class="flex justify-between">
                <span class="text-gray-600">Avg Invoice:</span>
                <span class="font-semibold"><?php echo formatCurrency($sales['avg_invoice']); ?></span>
            </div>
            <div class="flex justify-between">
                <span class="text-gray-600">Customers:</span>
                <span class="font-semibold"><?php echo $sales['unique_customers']; ?></span>
            </div>
        </div>
    </div>

    <!-- Purchases -->
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-bold text-gray-800">Purchases</h3>
            <div class="bg-red-100 p-3 rounded-full">
                <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path>
                </svg>
            </div>
        </div>
        <div class="space-y-2 text-sm">
            <div class="flex justify-between">
                <span class="text-gray-600">Total Spent:</span>
                <span class="font-bold text-red-600"><?php echo formatCurrency($purchases['total_spent']); ?></span>
            </div>
            <div class="flex justify-between">
                <span class="text-gray-600">Orders:</span>
                <span class="font-semibold"><?php echo number_format($purchases['total_purchases']); ?></span>
            </div>
            <div class="flex justify-between">
                <span class="text-gray-600">Avg Order:</span>
                <span class="font-semibold"><?php echo formatCurrency($purchases['avg_purchase']); ?></span>
            </div>
            <div class="flex justify-between">
                <span class="text-gray-600">Suppliers:</span>
                <span class="font-semibold"><?php echo $purchases['suppliers_used']; ?></span>
            </div>
        </div>
    </div>

    <!-- Bookings -->
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-bold text-gray-800">Bookings</h3>
            <div class="bg-purple-100 p-3 rounded-full">
                <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
            </div>
        </div>
        <div class="space-y-2 text-sm">
            <div class="flex justify-between">
                <span class="text-gray-600">Total Value:</span>
                <span class="font-bold text-purple-600"><?php echo formatCurrency($bookings['booking_value']); ?></span>
            </div>
            <div class="flex justify-between">
                <span class="text-gray-600">Bookings:</span>
                <span class="font-semibold"><?php echo number_format($bookings['total_bookings']); ?></span>
            </div>
            <div class="flex justify-between">
                <span class="text-gray-600">Invoiced:</span>
                <span class="font-semibold text-green-600"><?php echo $bookings['invoiced']; ?></span>
            </div>
            <div class="flex justify-between">
                <span class="text-gray-600">Conversion:</span>
                <span class="font-semibold">
                    <?php echo $bookings['total_bookings'] > 0 ? number_format(($bookings['invoiced'] / $bookings['total_bookings']) * 100, 1) : 0; ?>%
                </span>
            </div>
        </div>
    </div>

    <!-- Inventory -->
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-bold text-gray-800">Inventory</h3>
            <div class="bg-green-100 p-3 rounded-full">
                <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                </svg>
            </div>
        </div>
        <div class="space-y-2 text-sm">
            <div class="flex justify-between">
                <span class="text-gray-600">Stock Value:</span>
                <span class="font-bold text-green-600"><?php echo formatCurrency($stock['stock_value']); ?></span>
            </div>
            <div class="flex justify-between">
                <span class="text-gray-600">Products:</span>
                <span class="font-semibold"><?php echo number_format($stock['total_products']); ?></span>
            </div>
            <div class="flex justify-between">
                <span class="text-gray-600">Total Units:</span>
                <span class="font-semibold"><?php echo number_format($stock['total_units']); ?></span>
            </div>
            <div class="flex justify-between">
                <span class="text-gray-600">Low Stock:</span>
                <span class="font-semibold text-red-600"><?php echo $stock['low_stock']; ?></span>
            </div>
        </div>
    </div>
</div>

<!-- Weekly Trend -->
<div class="bg-white rounded-lg shadow p-6 mb-6">
    <h3 class="text-lg font-bold text-gray-800 mb-4">Weekly Sales Trend</h3>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Week</th>
                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Invoices</th>
                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Sales</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Trend</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php 
                $weekNum = 1;
                $maxSales = 0;
                $weeklyData = [];
                while ($w = $weekly->fetch_assoc()) {
                    $weeklyData[] = $w;
                    if ($w['sales'] > $maxSales) $maxSales = $w['sales'];
                }
                foreach ($weeklyData as $week): 
                    $percentage = $maxSales > 0 ? ($week['sales'] / $maxSales) * 100 : 0;
                ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-sm font-medium">
                            Week <?php echo $weekNum++; ?>
                            <div class="text-xs text-gray-500">
                                <?php echo date('M d', strtotime($week['week_start'])); ?> - <?php echo date('M d', strtotime($week['week_end'])); ?>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-sm text-right"><?php echo $week['invoices']; ?></td>
                        <td class="px-4 py-3 text-sm text-right font-bold text-blue-600">
                            <?php echo formatCurrency($week['sales']); ?>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center">
                                <div class="w-full bg-gray-200 rounded-full h-3 mr-2">
                                    <div class="bg-blue-500 h-3 rounded-full" style="width: <?php echo $percentage; ?>%"></div>
                                </div>
                                <span class="text-xs text-gray-600"><?php echo number_format($percentage, 0); ?>%</span>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Top Performers -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
    <!-- Top Products -->
    <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-bold text-gray-800 mb-4">🏆 Top 10 Products</h3>
        <div class="space-y-2">
            <?php 
            $rank = 1;
            while ($prod = $topProds->fetch_assoc()): 
            ?>
                <div class="flex items-center justify-between p-2 border-b hover:bg-gray-50">
                    <div class="flex items-center flex-1">
                        <span class="font-bold text-gray-400 text-sm mr-2"><?php echo $rank++; ?>.</span>
                        <div class="flex-1">
                            <p class="font-semibold text-sm text-gray-900"><?php echo $prod['name']; ?></p>
                            <p class="text-xs text-gray-500"><?php echo $prod['quantity']; ?> units</p>
                        </div>
                    </div>
                    <span class="font-bold text-blue-600 text-sm"><?php echo formatCurrency($prod['revenue']); ?></span>
                </div>
            <?php endwhile; ?>
        </div>
    </div>

    <!-- Top Customers -->
    <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-bold text-gray-800 mb-4">👥 Top 10 Customers</h3>
        <div class="space-y-2">
            <?php 
            $rank = 1;
            while ($cust = $topCusts->fetch_assoc()): 
            ?>
                <div class="flex items-center justify-between p-2 border-b hover:bg-gray-50">
                    <div class="flex items-center flex-1">
                        <span class="font-bold text-gray-400 text-sm mr-2"><?php echo $rank++; ?>.</span>
                        <div class="flex-1">
                            <p class="font-semibold text-sm text-gray-900"><?php echo $cust['name']; ?></p>
                            <p class="text-xs text-gray-500"><?php echo $cust['orders']; ?> orders</p>
                        </div>
                    </div>
                    <span class="font-bold text-green-600 text-sm"><?php echo formatCurrency($cust['spent']); ?></span>
                </div>
            <?php endwhile; ?>
        </div>
    </div>

    <!-- Top Bookers -->
    <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-bold text-gray-800 mb-4">⭐ Top 10 Bookers</h3>
        <div class="space-y-2">
            <?php 
            $rank = 1;
            while ($bkr = $topBkrs->fetch_assoc()): 
            ?>
                <div class="flex items-center justify-between p-2 border-b hover:bg-gray-50">
                    <div class="flex items-center flex-1">
                        <span class="font-bold text-gray-400 text-sm mr-2"><?php echo $rank++; ?>.</span>
                        <div class="flex-1">
                            <p class="font-semibold text-sm text-gray-900"><?php echo $bkr['name']; ?></p>
                            <p class="text-xs text-gray-500"><?php echo $bkr['bookings']; ?> bookings</p>
                        </div>
                    </div>
                    <span class="font-bold text-purple-600 text-sm"><?php echo formatCurrency($bkr['sales']); ?></span>
                </div>
            <?php endwhile; ?>
        </div>
    </div>
</div>

<!-- Key Insights -->
<div class="bg-gradient-to-r from-green-50 to-blue-50 border-2 border-green-200 rounded-lg p-6">
    <h3 class="text-xl font-bold text-gray-900 mb-4">📊 Key Insights for <?php echo $month_name; ?></h3>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div>
            <h4 class="font-semibold text-gray-700 mb-2">Financial Performance</h4>
            <ul class="space-y-1 text-sm text-gray-600">
                <li>• Total Revenue: <strong class="text-blue-600"><?php echo formatCurrency($sales['total_sales']); ?></strong></li>
                <li>• Gross Profit: <strong class="text-green-600"><?php echo formatCurrency($grossProfit); ?></strong></li>
                <li>• Profit Margin: <strong><?php echo number_format($profitMargin, 1); ?>%</strong></li>
                <li>• Growth vs Last Month: <strong class="<?php echo $salesGrowth >= 0 ? 'text-green-600' : 'text-red-600'; ?>"><?php echo number_format($salesGrowth, 1); ?>%</strong></li>
            </ul>
        </div>
        <div>
            <h4 class="font-semibold text-gray-700 mb-2">Operations</h4>
            <ul class="space-y-1 text-sm text-gray-600">
                <li>• Total Invoices: <strong><?php echo number_format($sales['total_invoices']); ?></strong></li>
                <li>• Booking Conversion: <strong><?php echo $bookings['total_bookings'] > 0 ? number_format(($bookings['invoiced'] / $bookings['total_bookings']) * 100, 1) : 0; ?>%</strong></li>
                <li>• Active Bookers: <strong><?php echo $bookings['active_bookers']; ?></strong></li>
                <li>• Unique Customers: <strong><?php echo $sales['unique_customers']; ?></strong></li>
            </ul>
        </div>
        <div>
            <h4 class="font-semibold text-gray-700 mb-2">Cash Flow</h4>
            <ul class="space-y-1 text-sm text-gray-600">
                <li>• Total Collected: <strong class="text-green-600"><?php echo formatCurrency($sales['collected']); ?></strong></li>
                <li>• Collection Rate: <strong><?php echo $sales['total_sales'] > 0 ? number_format(($sales['collected'] / $sales['total_sales']) * 100, 1) : 0; ?>%</strong></li>
                <li>• Outstanding: <strong class="text-red-600"><?php echo formatCurrency($sales['outstanding']); ?></strong></li>
                <li>• Purchases: <strong><?php echo formatCurrency($purchases['total_spent']); ?></strong></li>
            </ul>
        </div>
    </div>
</div>


<?php
include '../../includes/footer.php';
?>