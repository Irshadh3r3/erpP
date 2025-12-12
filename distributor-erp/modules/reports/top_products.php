<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';

requireLogin();

$conn = getDBConnection();
$pageTitle = 'Top Performers Report';

// Date filters
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-01');
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-t');
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;

// Top Products by Revenue
$topProductsQuery = "SELECT 
                     p.id,
                     p.sku,
                     p.name,
                     p.unit,
                     c.name as category,
                     SUM(si.quantity) as total_quantity,
                     SUM(si.subtotal) as total_revenue,
                     COUNT(DISTINCT si.sale_id) as times_sold,
                     COUNT(DISTINCT s.customer_id) as unique_customers,
                     AVG(si.unit_price) as avg_price,
                     SUM(si.quantity * p.purchase_price) as total_cost,
                     SUM(si.subtotal - (si.quantity * p.purchase_price)) as total_profit
                     FROM sales_items si
                     JOIN sales s ON si.sale_id = s.id
                     JOIN products p ON si.product_id = p.id
                     LEFT JOIN categories c ON p.category_id = c.id
                     WHERE s.sale_date BETWEEN '$date_from' AND '$date_to'
                     GROUP BY p.id
                     ORDER BY total_revenue DESC
                     LIMIT $limit";
$topProducts = $conn->query($topProductsQuery);

// Top Customers by Spending
$topCustomersQuery = "SELECT 
                      c.id,
                      c.customer_code,
                      c.name,
                      c.phone,
                      c.area,
                      COUNT(s.id) as total_orders,
                      SUM(s.total_amount) as total_spent,
                      SUM(s.paid_amount) as total_paid,
                      AVG(s.total_amount) as avg_order_value,
                      MIN(s.sale_date) as first_purchase,
                      MAX(s.sale_date) as last_purchase,
                      DATEDIFF(MAX(s.sale_date), MIN(s.sale_date)) as customer_lifetime_days
                      FROM customers c
                      JOIN sales s ON c.id = s.customer_id
                      WHERE s.sale_date BETWEEN '$date_from' AND '$date_to'
                      GROUP BY c.id
                      ORDER BY total_spent DESC
                      LIMIT $limit";
$topCustomers = $conn->query($topCustomersQuery);

// Top Categories
$topCategoriesQuery = "SELECT 
                       c.name as category,
                       COUNT(DISTINCT si.product_id) as products,
                       SUM(si.quantity) as units_sold,
                       SUM(si.subtotal) as revenue,
                       COUNT(DISTINCT s.customer_id) as customers
                       FROM sales_items si
                       JOIN sales s ON si.sale_id = s.id
                       JOIN products p ON si.product_id = p.id
                       LEFT JOIN categories c ON p.category_id = c.id
                       WHERE s.sale_date BETWEEN '$date_from' AND '$date_to'
                       GROUP BY p.category_id
                       ORDER BY revenue DESC";
$topCategories = $conn->query($topCategoriesQuery);

// Top Bookers
$topBookersQuery = "SELECT 
                    bk.id,
                    bk.booker_code,
                    bk.name,
                    bk.area,
                    bk.commission_percentage,
                    COUNT(DISTINCT b.id) as bookings,
                    SUM(CASE WHEN b.status = 'invoiced' THEN b.total_amount ELSE 0 END) as sales,
                    SUM(CASE WHEN b.status = 'invoiced' THEN (b.total_amount * bk.commission_percentage / 100) ELSE 0 END) as commission_earned,
                    COUNT(DISTINCT b.customer_id) as customers_served
                    FROM bookers bk
                    LEFT JOIN bookings b ON bk.id = b.booker_id
                    WHERE b.booking_date BETWEEN '$date_from' AND '$date_to'
                    GROUP BY bk.id
                    ORDER BY sales DESC
                    LIMIT $limit";
$topBookers = $conn->query($topBookersQuery);

// Get totals
$totalsQuery = "SELECT 
                SUM(total_amount) as total_sales,
                COUNT(DISTINCT customer_id) as total_customers,
                COUNT(*) as total_invoices
                FROM sales
                WHERE sale_date BETWEEN '$date_from' AND '$date_to'";
$totals = $conn->query($totalsQuery)->fetch_assoc();

// Product totals
$productTotalsQuery = "SELECT 
                       COUNT(DISTINCT si.product_id) as products_sold,
                       SUM(si.quantity) as units_sold
                       FROM sales_items si
                       JOIN sales s ON si.sale_id = s.id
                       WHERE s.sale_date BETWEEN '$date_from' AND '$date_to'";
$productTotals = $conn->query($productTotalsQuery)->fetch_assoc();

include '../../includes/header.php';
?>

<!-- Page Header -->
<div class="mb-6">
    <h1 class="text-3xl font-bold text-gray-800">Top Performers Report</h1>
    <p class="text-gray-600">Best performing products, customers, and team members</p>
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
            <label class="block text-sm font-semibold text-gray-700 mb-2">Show Top</label>
            <select name="limit" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="10" <?php echo $limit == 10 ? 'selected' : ''; ?>>Top 10</option>
                <option value="20" <?php echo $limit == 20 ? 'selected' : ''; ?>>Top 20</option>
                <option value="50" <?php echo $limit == 50 ? 'selected' : ''; ?>>Top 50</option>
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
    <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-lg shadow-lg p-6 text-white">
        <p class="text-blue-100 text-sm uppercase">Total Sales</p>
        <h3 class="text-3xl font-bold mt-2"><?php echo formatCurrency($totals['total_sales']); ?></h3>
        <p class="text-blue-100 text-sm mt-1"><?php echo number_format($totals['total_invoices']); ?> invoices</p>
    </div>
    
    <div class="bg-gradient-to-br from-green-500 to-green-600 rounded-lg shadow-lg p-6 text-white">
        <p class="text-green-100 text-sm uppercase">Products Sold</p>
        <h3 class="text-3xl font-bold mt-2"><?php echo number_format($productTotals['products_sold']); ?></h3>
        <p class="text-green-100 text-sm mt-1"><?php echo number_format($productTotals['units_sold']); ?> units</p>
    </div>
    
    <div class="bg-gradient-to-br from-purple-500 to-purple-600 rounded-lg shadow-lg p-6 text-white">
        <p class="text-purple-100 text-sm uppercase">Active Customers</p>
        <h3 class="text-3xl font-bold mt-2"><?php echo number_format($totals['total_customers']); ?></h3>
        <p class="text-purple-100 text-sm mt-1">Made purchases</p>
    </div>
    
    <div class="bg-gradient-to-br from-orange-500 to-orange-600 rounded-lg shadow-lg p-6 text-white">
        <p class="text-orange-100 text-sm uppercase">Avg Invoice</p>
        <h3 class="text-3xl font-bold mt-2">
            <?php echo formatCurrency($totals['total_invoices'] > 0 ? $totals['total_sales'] / $totals['total_invoices'] : 0); ?>
        </h3>
        <p class="text-orange-100 text-sm mt-1">Per transaction</p>
    </div>
</div>

<!-- Top Products -->
<div class="bg-white rounded-lg shadow overflow-hidden mb-6">
    <div class="p-6 border-b bg-gradient-to-r from-blue-50 to-purple-50">
        <h3 class="text-xl font-bold text-gray-800">🏆 Top <?php echo $limit; ?> Products by Revenue</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Rank</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Product</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Category</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Qty Sold</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Revenue</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Profit</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Margin %</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Times Sold</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Customers</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php 
                $rank = 1;
                $topProducts->data_seek(0);
                while ($product = $topProducts->fetch_assoc()): 
                    $margin = $product['total_revenue'] > 0 ? ($product['total_profit'] / $product['total_revenue']) * 100 : 0;
                    $medalColor = $rank == 1 ? 'bg-yellow-100 text-yellow-700' : 
                                 ($rank == 2 ? 'bg-gray-100 text-gray-700' : 
                                 ($rank == 3 ? 'bg-orange-100 text-orange-700' : 'bg-blue-50 text-blue-600'));
                ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4">
                            <span class="<?php echo $medalColor; ?> px-3 py-1 rounded-full text-lg font-bold">
                                <?php echo $rank <= 3 ? ['🥇', '🥈', '🥉'][$rank-1] : $rank; ?>
                            </span>
                        </td>
                        <td class="px-6 py-4">
                            <div class="text-sm font-bold text-gray-900"><?php echo $product['name']; ?></div>
                            <div class="text-xs text-gray-500"><?php echo $product['sku']; ?></div>
                        </td>
                        <td class="px-6 py-4 text-sm"><?php echo $product['category'] ?: 'N/A'; ?></td>
                        <td class="px-6 py-4 text-sm text-right font-semibold">
                            <?php echo number_format($product['total_quantity']); ?> <?php echo $product['unit']; ?>
                        </td>
                        <td class="px-6 py-4 text-sm text-right font-bold text-blue-600">
                            <?php echo formatCurrency($product['total_revenue']); ?>
                        </td>
                        <td class="px-6 py-4 text-sm text-right font-semibold text-green-600">
                            <?php echo formatCurrency($product['total_profit']); ?>
                        </td>
                        <td class="px-6 py-4 text-sm text-right">
                            <span class="<?php echo $margin > 20 ? 'text-green-600' : ($margin > 10 ? 'text-yellow-600' : 'text-red-600'); ?> font-bold">
                                <?php echo number_format($margin, 1); ?>%
                            </span>
                        </td>
                        <td class="px-6 py-4 text-sm text-right"><?php echo $product['times_sold']; ?></td>
                        <td class="px-6 py-4 text-sm text-right"><?php echo $product['unique_customers']; ?></td>
                    </tr>
                <?php 
                $rank++;
                endwhile; 
                ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Top Customers -->
<div class="bg-white rounded-lg shadow overflow-hidden mb-6">
    <div class="p-6 border-b bg-gradient-to-r from-green-50 to-blue-50">
        <h3 class="text-xl font-bold text-gray-800">👥 Top <?php echo $limit; ?> Customers by Spending</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Rank</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Customer</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Area</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Orders</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total Spent</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Avg Order</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Paid</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Last Purchase</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php 
                $rank = 1;
                $topCustomers->data_seek(0);
                while ($customer = $topCustomers->fetch_assoc()): 
                    $medalColor = $rank == 1 ? 'bg-yellow-100 text-yellow-700' : 
                                 ($rank == 2 ? 'bg-gray-100 text-gray-700' : 
                                 ($rank == 3 ? 'bg-orange-100 text-orange-700' : 'bg-green-50 text-green-600'));
                    $paymentRate = $customer['total_spent'] > 0 ? ($customer['total_paid'] / $customer['total_spent']) * 100 : 0;
                ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4">
                            <span class="<?php echo $medalColor; ?> px-3 py-1 rounded-full text-lg font-bold">
                                <?php echo $rank <= 3 ? ['🥇', '🥈', '🥉'][$rank-1] : $rank; ?>
                            </span>
                        </td>
                        <td class="px-6 py-4">
                            <div class="text-sm font-bold text-gray-900"><?php echo $customer['name']; ?></div>
                            <div class="text-xs text-gray-500"><?php echo $customer['customer_code']; ?> • <?php echo $customer['phone']; ?></div>
                        </td>
                        <td class="px-6 py-4 text-sm"><?php echo $customer['area'] ?: 'N/A'; ?></td>
                        <td class="px-6 py-4 text-sm text-right font-semibold"><?php echo number_format($customer['total_orders']); ?></td>
                        <td class="px-6 py-4 text-sm text-right font-bold text-green-600">
                            <?php echo formatCurrency($customer['total_spent']); ?>
                        </td>
                        <td class="px-6 py-4 text-sm text-right"><?php echo formatCurrency($customer['avg_order_value']); ?></td>
                        <td class="px-6 py-4 text-sm text-right">
                            <span class="<?php echo $paymentRate >= 90 ? 'text-green-600' : 'text-orange-600'; ?> font-semibold">
                                <?php echo number_format($paymentRate, 0); ?>%
                            </span>
                        </td>
                        <td class="px-6 py-4 text-sm"><?php echo formatDate($customer['last_purchase']); ?></td>
                    </tr>
                <?php 
                $rank++;
                endwhile; 
                ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Top Categories & Bookers -->
<div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
    <!-- Top Categories -->
    <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-bold text-gray-800 mb-4">📦 Top Categories</h3>
        <div class="space-y-3">
            <?php 
            $catRank = 1;
            $maxCatRevenue = 0;
            $catData = [];
            while ($cat = $topCategories->fetch_assoc()) {
                $catData[] = $cat;
                if ($cat['revenue'] > $maxCatRevenue) $maxCatRevenue = $cat['revenue'];
            }
            foreach ($catData as $category): 
                $percentage = $maxCatRevenue > 0 ? ($category['revenue'] / $maxCatRevenue) * 100 : 0;
            ?>
                <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition">
                    <div class="flex justify-between items-start mb-2">
                        <div class="flex items-center">
                            <span class="font-bold text-gray-400 text-lg mr-2"><?php echo $catRank++; ?>.</span>
                            <div>
                                <h4 class="font-bold text-gray-900"><?php echo $category['category'] ?: 'Uncategorized'; ?></h4>
                                <p class="text-xs text-gray-500"><?php echo $category['products']; ?> products • <?php echo number_format($category['units_sold']); ?> units</p>
                            </div>
                        </div>
                        <span class="text-lg font-bold text-blue-600"><?php echo formatCurrency($category['revenue']); ?></span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2">
                        <div class="bg-blue-500 h-2 rounded-full" style="width: <?php echo $percentage; ?>%"></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Top Bookers -->
    <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-bold text-gray-800 mb-4">⭐ Top Bookers</h3>
        <div class="space-y-3">
            <?php 
            $bkRank = 1;
            while ($booker = $topBookers->fetch_assoc()): 
            ?>
                <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition">
                    <div class="flex justify-between items-start mb-2">
                        <div class="flex items-center">
                            <span class="font-bold text-gray-400 text-lg mr-2"><?php echo $bkRank++; ?>.</span>
                            <div>
                                <h4 class="font-bold text-gray-900"><?php echo $booker['name']; ?></h4>
                                <p class="text-xs text-gray-500">
                                    <?php echo $booker['bookings']; ?> bookings • <?php echo $booker['customers_served']; ?> customers • <?php echo $booker['area'] ?: 'N/A'; ?>
                                </p>
                            </div>
                        </div>
                        <div class="text-right">
                            <span class="text-lg font-bold text-green-600"><?php echo formatCurrency($booker['sales']); ?></span>
                            <p class="text-xs text-purple-600 font-semibold"><?php echo formatCurrency($booker['commission_earned']); ?> comm</p>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    </div>
</div>

<!-- Performance Highlights -->
<div class="bg-gradient-to-r from-blue-50 to-purple-50 border-2 border-blue-200 rounded-lg p-6">
    <h3 class="text-xl font-bold text-gray-900 mb-4">🌟 Performance Highlights</h3>
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <?php 
        // Get top product
        $topProducts->data_seek(0);
        $topProd = $topProducts->fetch_assoc();
        
        // Get top customer
        $topCustomers->data_seek(0);
        $topCust = $topCustomers->fetch_assoc();
        
        // Get top booker
        $topBookers->data_seek(0);
        $topBkr = $topBookers->fetch_assoc();
        ?>
        <div class="bg-white rounded-lg p-4 shadow">
            <p class="text-sm text-gray-600 mb-1">🏆 Best Product</p>
            <p class="font-bold text-gray-900"><?php echo $topProd['name']; ?></p>
            <p class="text-lg font-bold text-blue-600"><?php echo formatCurrency($topProd['total_revenue']); ?></p>
        </div>
        <div class="bg-white rounded-lg p-4 shadow">
            <p class="text-sm text-gray-600 mb-1">👤 Best Customer</p>
            <p class="font-bold text-gray-900"><?php echo $topCust['name']; ?></p>
            <p class="text-lg font-bold text-green-600"><?php echo formatCurrency($topCust['total_spent']); ?></p>
        </div>
        <div class="bg-white rounded-lg p-4 shadow">
            <p class="text-sm text-gray-600 mb-1">⭐ Best Booker</p>
            <p class="font-bold text-gray-900"><?php echo $topBkr['name']; ?></p>
            <p class="text-lg font-bold text-purple-600"><?php echo formatCurrency($topBkr['sales']); ?></p>
        </div>
        <div class="bg-white rounded-lg p-4 shadow">
            <p class="text-sm text-gray-600 mb-1">📊 Period Total</p>
            <p class="font-bold text-gray-900">
                <?php echo date('M d', strtotime($date_from)); ?> - <?php echo date('M d', strtotime($date_to)); ?>
            </p>
            <p class="text-lg font-bold text-orange-600"><?php echo formatCurrency($totals['total_sales']); ?></p>
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