<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';

requireLogin();

$conn = getDBConnection();
$pageTitle = 'Daily Summary Report';

// Date filter - defaults to today
$report_date = isset($_GET['report_date']) ? $_GET['report_date'] : date('Y-m-d');

// Get daily sales summary
$salesQuery = "SELECT 
               COUNT(*) as total_invoices,
               SUM(subtotal) as subtotal,
               SUM(discount) as discount,
               SUM(tax) as tax,
               SUM(total_amount) as total_sales,
               SUM(paid_amount) as collected,
               SUM(total_amount - paid_amount) as pending,
               COUNT(DISTINCT customer_id) as unique_customers,
               AVG(total_amount) as avg_invoice,
               COUNT(CASE WHEN payment_status = 'paid' THEN 1 END) as paid_invoices,
               COUNT(CASE WHEN payment_status = 'partial' THEN 1 END) as partial_invoices,
               COUNT(CASE WHEN payment_status = 'unpaid' THEN 1 END) as unpaid_invoices
               FROM sales
               WHERE DATE(sale_date) = '$report_date'";
$sales = $conn->query($salesQuery)->fetch_assoc();

// Get purchases summary
$purchasesQuery = "SELECT 
                   COUNT(*) as total_purchases,
                   SUM(total_amount) as total_spent,
                   SUM(paid_amount) as paid_amount,
                   COUNT(DISTINCT supplier_id) as suppliers_used
                   FROM purchases
                   WHERE DATE(purchase_date) = '$report_date'";
$purchases = $conn->query($purchasesQuery)->fetch_assoc();

// Get payments received
$paymentsQuery = "SELECT 
                  COUNT(*) as payment_count,
                  SUM(payment_amount) as total_collected,
                  COUNT(DISTINCT invoice_id) as invoices_paid
                  FROM payments
                  WHERE DATE(payment_date) = '$report_date'";
$payments = $conn->query($paymentsQuery)->fetch_assoc();

// Get bookings summary
$bookingsQuery = "SELECT 
                  COUNT(*) as total_bookings,
                  SUM(total_amount) as booking_value,
                  COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending,
                  COUNT(CASE WHEN status = 'confirmed' THEN 1 END) as confirmed,
                  COUNT(CASE WHEN status = 'invoiced' THEN 1 END) as invoiced,
                  COUNT(DISTINCT booker_id) as active_bookers
                  FROM bookings
                  WHERE DATE(booking_date) = '$report_date'";
$bookings = $conn->query($bookingsQuery)->fetch_assoc();

// Top selling products today
$topProducts = "SELECT 
                p.name,
                p.sku,
                SUM(si.quantity) as quantity,
                SUM(si.subtotal) as revenue
                FROM sales_items si
                JOIN sales s ON si.sale_id = s.id
                JOIN products p ON si.product_id = p.id
                WHERE DATE(s.sale_date) = '$report_date'
                GROUP BY p.id
                ORDER BY revenue DESC
                LIMIT 5";
$topProds = $conn->query($topProducts);

// Top customers today
$topCustomers = "SELECT 
                 c.name,
                 c.customer_code,
                 COUNT(s.id) as orders,
                 SUM(s.total_amount) as spent
                 FROM customers c
                 JOIN sales s ON c.id = s.customer_id
                 WHERE DATE(s.sale_date) = '$report_date'
                 GROUP BY c.id
                 ORDER BY spent DESC
                 LIMIT 5";
$topCusts = $conn->query($topCustomers);

// Hourly sales pattern (if any sales exist)
$hourlySales = "SELECT 
                HOUR(created_at) as hour,
                COUNT(*) as orders,
                SUM(total_amount) as amount
                FROM sales
                WHERE DATE(sale_date) = '$report_date'
                GROUP BY HOUR(created_at)
                ORDER BY hour ASC";
$hourly = $conn->query($hourlySales);

// Stock movements today
$stockMovements = "SELECT 
                   COUNT(*) as total_movements,
                   SUM(CASE WHEN movement_type IN ('sale') THEN ABS(quantity) ELSE 0 END) as sold,
                   SUM(CASE WHEN movement_type IN ('purchase') THEN quantity ELSE 0 END) as purchased
                   FROM stock_movements
                   WHERE DATE(created_at) = '$report_date'";
$stock = $conn->query($stockMovements)->fetch_assoc();

// Calculate net profit for the day
$netProfit = ($sales['total_sales'] ?? 0) - ($purchases['total_spent'] ?? 0);

// Compare with yesterday
$yesterday = date('Y-m-d', strtotime($report_date . ' -1 day'));
$yesterdayQuery = "SELECT SUM(total_amount) as total FROM sales WHERE DATE(sale_date) = '$yesterday'";
$yesterdayData = $conn->query($yesterdayQuery)->fetch_assoc();
$yesterdaySales = $yesterdayData['total'] ?? 0;
$salesChange = $yesterdaySales > 0 ? (($sales['total_sales'] - $yesterdaySales) / $yesterdaySales) * 100 : 0;

include '../../includes/header.php';
?>

<!-- Page Header -->
<div class="mb-6">
    <h1 class="text-3xl font-bold text-gray-800">Daily Summary Report</h1>
    <p class="text-gray-600">Complete business snapshot for <?php echo formatDate($report_date); ?></p>
</div>

<!-- Date Selector -->
<div class="bg-white rounded-lg shadow p-6 mb-6">
    <form method="GET" action="" class="flex items-center gap-4">
        <div class="flex-1">
            <label class="block text-sm font-semibold text-gray-700 mb-2">Select Date</label>
            <input type="date" 
                   name="report_date" 
                   value="<?php echo $report_date; ?>"
                   max="<?php echo date('Y-m-d'); ?>"
                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
        <div class="flex gap-2 items-end">
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg font-semibold transition">
                View Report
            </button>
            <button type="button" 
                    onclick="window.location.href='?report_date=<?php echo date('Y-m-d'); ?>'" 
                    class="bg-green-600 hover:bg-green-700 text-white px-6 py-2 rounded-lg font-semibold transition">
                Today
            </button>
        </div>
    </form>
</div>

<!-- Key Metrics -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
    <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-lg shadow-lg p-6 text-white">
        <p class="text-blue-100 text-sm font-semibold">TOTAL SALES</p>
        <h3 class="text-3xl font-bold mt-2"><?php echo formatCurrency($sales['total_sales']); ?></h3>
        <div class="flex items-center mt-2 text-sm">
            <?php if ($salesChange != 0): ?>
                <span class="<?php echo $salesChange > 0 ? 'text-green-300' : 'text-red-300'; ?>">
                    <?php echo $salesChange > 0 ? '↑' : '↓'; ?> <?php echo abs(number_format($salesChange, 1)); ?>%
                </span>
                <span class="ml-2 text-blue-100">vs yesterday</span>
            <?php else: ?>
                <span class="text-blue-100">No comparison data</span>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="bg-gradient-to-br from-green-500 to-green-600 rounded-lg shadow-lg p-6 text-white">
        <p class="text-green-100 text-sm font-semibold">CASH COLLECTED</p>
        <h3 class="text-3xl font-bold mt-2"><?php echo formatCurrency($payments['total_collected']); ?></h3>
        <p class="text-green-100 text-sm mt-2"><?php echo $payments['payment_count']; ?> payments received</p>
    </div>
    
    <div class="bg-gradient-to-br from-purple-500 to-purple-600 rounded-lg shadow-lg p-6 text-white">
        <p class="text-purple-100 text-sm font-semibold">NEW BOOKINGS</p>
        <h3 class="text-3xl font-bold mt-2"><?php echo $bookings['total_bookings']; ?></h3>
        <p class="text-purple-100 text-sm mt-2"><?php echo formatCurrency($bookings['booking_value']); ?> value</p>
    </div>
    
    <div class="bg-gradient-to-br from-orange-500 to-orange-600 rounded-lg shadow-lg p-6 text-white">
        <p class="text-orange-100 text-sm font-semibold">NET PROFIT</p>
        <h3 class="text-3xl font-bold mt-2"><?php echo formatCurrency($netProfit); ?></h3>
        <p class="text-orange-100 text-sm mt-2">Sales - Purchases</p>
    </div>
</div>

<!-- Sales Details -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
    <!-- Sales Breakdown -->
    <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-bold text-gray-800 mb-4">💰 Sales Breakdown</h3>
        <div class="space-y-3">
            <div class="flex justify-between items-center p-2 border-b">
                <span class="text-gray-600">Total Invoices</span>
                <span class="font-bold"><?php echo $sales['total_invoices']; ?></span>
            </div>
            <div class="flex justify-between items-center p-2 border-b">
                <span class="text-gray-600">Subtotal</span>
                <span class="font-semibold"><?php echo formatCurrency($sales['subtotal']); ?></span>
            </div>
            <div class="flex justify-between items-center p-2 border-b">
                <span class="text-gray-600 text-red-600">Discounts</span>
                <span class="font-semibold text-red-600">-<?php echo formatCurrency($sales['discount']); ?></span>
            </div>
            <div class="flex justify-between items-center p-2 border-b">
                <span class="text-gray-600">Tax</span>
                <span class="font-semibold"><?php echo formatCurrency($sales['tax']); ?></span>
            </div>
            <div class="flex justify-between items-center p-3 bg-blue-50 rounded font-bold">
                <span class="text-blue-900">Grand Total</span>
                <span class="text-blue-900 text-lg"><?php echo formatCurrency($sales['total_sales']); ?></span>
            </div>
            <div class="flex justify-between items-center p-2">
                <span class="text-gray-600">Avg Invoice</span>
                <span class="font-semibold text-purple-600"><?php echo formatCurrency($sales['avg_invoice']); ?></span>
            </div>
            <div class="flex justify-between items-center p-2">
                <span class="text-gray-600">Unique Customers</span>
                <span class="font-bold"><?php echo $sales['unique_customers']; ?></span>
            </div>
        </div>
    </div>

    <!-- Payment Status -->
    <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-bold text-gray-800 mb-4">💳 Payment Status</h3>
        <div class="space-y-3">
            <div class="bg-green-50 border-2 border-green-300 rounded-lg p-4">
                <div class="flex justify-between items-center mb-1">
                    <span class="font-semibold text-green-700">Paid</span>
                    <span class="text-2xl font-bold text-green-700"><?php echo $sales['paid_invoices']; ?></span>
                </div>
                <p class="text-xs text-green-600">Fully paid invoices</p>
            </div>
            
            <div class="bg-yellow-50 border-2 border-yellow-300 rounded-lg p-4">
                <div class="flex justify-between items-center mb-1">
                    <span class="font-semibold text-yellow-700">Partial</span>
                    <span class="text-2xl font-bold text-yellow-700"><?php echo $sales['partial_invoices']; ?></span>
                </div>
                <p class="text-xs text-yellow-600">Partially paid</p>
            </div>
            
            <div class="bg-red-50 border-2 border-red-300 rounded-lg p-4">
                <div class="flex justify-between items-center mb-1">
                    <span class="font-semibold text-red-700">Unpaid</span>
                    <span class="text-2xl font-bold text-red-700"><?php echo $sales['unpaid_invoices']; ?></span>
                </div>
                <p class="text-xs text-red-600">Payment pending</p>
            </div>

            <div class="border border-gray-300 rounded-lg p-3 mt-4">
                <div class="flex justify-between items-center">
                    <span class="text-gray-700 font-semibold">Collection Rate</span>
                    <span class="text-xl font-bold text-blue-600">
                        <?php echo $sales['total_sales'] > 0 ? number_format(($sales['collected'] / $sales['total_sales']) * 100, 1) : 0; ?>%
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- Other Activities -->
    <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-bold text-gray-800 mb-4">📊 Other Activities</h3>
        <div class="space-y-4">
            <!-- Purchases -->
            <div class="border border-gray-300 rounded-lg p-4">
                <h4 class="font-semibold text-gray-700 mb-2">Purchases Made</h4>
                <p class="text-2xl font-bold text-red-600"><?php echo formatCurrency($purchases['total_spent']); ?></p>
                <p class="text-xs text-gray-500 mt-1">
                    <?php echo $purchases['total_purchases']; ?> orders from <?php echo $purchases['suppliers_used']; ?> suppliers
                </p>
            </div>

            <!-- Bookings -->
            <div class="border border-gray-300 rounded-lg p-4">
                <h4 class="font-semibold text-gray-700 mb-2">Bookings Status</h4>
                <div class="grid grid-cols-3 gap-2 text-center text-xs mt-2">
                    <div>
                        <p class="font-bold text-yellow-600"><?php echo $bookings['pending']; ?></p>
                        <p class="text-gray-500">Pending</p>
                    </div>
                    <div>
                        <p class="font-bold text-blue-600"><?php echo $bookings['confirmed']; ?></p>
                        <p class="text-gray-500">Confirmed</p>
                    </div>
                    <div>
                        <p class="font-bold text-green-600"><?php echo $bookings['invoiced']; ?></p>
                        <p class="text-gray-500">Invoiced</p>
                    </div>
                </div>
            </div>

            <!-- Stock Movements -->
            <div class="border border-gray-300 rounded-lg p-4">
                <h4 class="font-semibold text-gray-700 mb-2">Stock Movements</h4>
                <div class="flex justify-between items-center mt-2">
                    <div class="text-center">
                        <p class="text-xl font-bold text-red-600"><?php echo $stock['sold']; ?></p>
                        <p class="text-xs text-gray-500">Units Sold</p>
                    </div>
                    <div class="text-gray-400">↔</div>
                    <div class="text-center">
                        <p class="text-xl font-bold text-green-600"><?php echo $stock['purchased']; ?></p>
                        <p class="text-xs text-gray-500">Units Purchased</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Top Performers -->
<div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
    <!-- Top Products -->
    <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-bold text-gray-800 mb-4">🏆 Top 5 Products</h3>
        <?php if ($topProds->num_rows > 0): ?>
            <div class="space-y-3">
                <?php 
                $rank = 1;
                while ($prod = $topProds->fetch_assoc()): 
                ?>
                    <div class="flex items-center justify-between p-3 border border-gray-200 rounded-lg hover:bg-gray-50">
                        <div class="flex items-center">
                            <span class="font-bold text-gray-400 mr-3">#<?php echo $rank++; ?></span>
                            <div>
                                <p class="font-semibold text-gray-900"><?php echo $prod['name']; ?></p>
                                <p class="text-xs text-gray-500"><?php echo $prod['sku']; ?> • <?php echo $prod['quantity']; ?> units</p>
                            </div>
                        </div>
                        <span class="font-bold text-blue-600"><?php echo formatCurrency($prod['revenue']); ?></span>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <p class="text-center text-gray-500 py-8">No product sales today</p>
        <?php endif; ?>
    </div>

    <!-- Top Customers -->
    <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-bold text-gray-800 mb-4">👥 Top 5 Customers</h3>
        <?php if ($topCusts->num_rows > 0): ?>
            <div class="space-y-3">
                <?php 
                $rank = 1;
                while ($cust = $topCusts->fetch_assoc()): 
                ?>
                    <div class="flex items-center justify-between p-3 border border-gray-200 rounded-lg hover:bg-gray-50">
                        <div class="flex items-center">
                            <span class="font-bold text-gray-400 mr-3">#<?php echo $rank++; ?></span>
                            <div>
                                <p class="font-semibold text-gray-900"><?php echo $cust['name']; ?></p>
                                <p class="text-xs text-gray-500"><?php echo $cust['customer_code']; ?> • <?php echo $cust['orders']; ?> orders</p>
                            </div>
                        </div>
                        <span class="font-bold text-green-600"><?php echo formatCurrency($cust['spent']); ?></span>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <p class="text-center text-gray-500 py-8">No customer purchases today</p>
        <?php endif; ?>
    </div>
</div>

<!-- Summary Box -->
<div class="bg-gradient-to-r from-blue-50 to-purple-50 border-2 border-blue-200 rounded-lg p-6">
    <h3 class="text-xl font-bold text-gray-900 mb-4">📋 Day Summary</h3>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-center">
        <div class="bg-white rounded-lg p-4 shadow">
            <p class="text-3xl font-bold text-blue-600"><?php echo $sales['total_invoices']; ?></p>
            <p class="text-sm text-gray-600 mt-1">Invoices</p>
        </div>
        <div class="bg-white rounded-lg p-4 shadow">
            <p class="text-3xl font-bold text-green-600"><?php echo formatCurrency($payments['total_collected']); ?></p>
            <p class="text-sm text-gray-600 mt-1">Collected</p>
        </div>
        <div class="bg-white rounded-lg p-4 shadow">
            <p class="text-3xl font-bold text-purple-600"><?php echo $bookings['total_bookings']; ?></p>
            <p class="text-sm text-gray-600 mt-1">Bookings</p>
        </div>
        <div class="bg-white rounded-lg p-4 shadow">
            <p class="text-3xl font-bold text-orange-600"><?php echo $sales['unique_customers']; ?></p>
            <p class="text-sm text-gray-600 mt-1">Customers</p>
        </div>
    </div>
</div>


<?php
include '../../includes/footer.php';
?>