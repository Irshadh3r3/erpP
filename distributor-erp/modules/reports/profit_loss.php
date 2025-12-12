<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';

requireLogin();

$conn = getDBConnection();
$pageTitle = 'Profit & Loss Report';

// Date filters
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-01');
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-t');

// REVENUE - Sales
$salesQuery = "SELECT 
               COUNT(*) as invoice_count,
               SUM(subtotal) as gross_sales,
               SUM(discount) as total_discount,
               SUM(tax) as total_tax,
               SUM(total_amount) as net_sales
               FROM sales
               WHERE sale_date BETWEEN '$date_from' AND '$date_to'";
$salesData = $conn->query($salesQuery)->fetch_assoc();

// COST OF GOODS SOLD - Calculate from sales items
$cogsQuery = "SELECT 
              SUM(si.quantity * p.purchase_price) as total_cogs
              FROM sales_items si
              JOIN sales s ON si.sale_id = s.id
              JOIN products p ON si.product_id = p.id
              WHERE s.sale_date BETWEEN '$date_from' AND '$date_to'";
$cogsData = $conn->query($cogsQuery)->fetch_assoc();

// EXPENSES - Purchases in period
$purchasesQuery = "SELECT 
                   COUNT(*) as purchase_count,
                   SUM(total_amount) as total_purchases
                   FROM purchases
                   WHERE purchase_date BETWEEN '$date_from' AND '$date_to'";
$purchasesData = $conn->query($purchasesQuery)->fetch_assoc();

// Commission Expenses
$commissionQuery = "SELECT 
                    SUM(b.total_amount * bk.commission_percentage / 100) as total_commission
                    FROM bookings b
                    JOIN bookers bk ON b.booker_id = bk.id
                    WHERE b.status = 'invoiced' 
                    AND b.booking_date BETWEEN '$date_from' AND '$date_to'";
$commissionData = $conn->query($commissionQuery)->fetch_assoc();

// Calculate Gross Profit
$grossSales = $salesData['gross_sales'] ?? 0;
$discounts = $salesData['total_discount'] ?? 0;
$netSales = $salesData['net_sales'] ?? 0;
$cogs = $cogsData['total_cogs'] ?? 0;
$grossProfit = $netSales - $cogs;
$grossProfitMargin = $netSales > 0 ? ($grossProfit / $netSales) * 100 : 0;

// Operating Expenses
$commissionExpense = $commissionData['total_commission'] ?? 0;
$totalOperatingExpenses = $commissionExpense;

// Net Profit
$netProfit = $grossProfit - $totalOperatingExpenses;
$netProfitMargin = $netSales > 0 ? ($netProfit / $netSales) * 100 : 0;

// Category-wise Profit Analysis
$categoryProfitQuery = "SELECT 
                        c.name as category,
                        COUNT(DISTINCT si.product_id) as products,
                        SUM(si.quantity) as units_sold,
                        SUM(si.subtotal) as revenue,
                        SUM(si.quantity * p.purchase_price) as cost,
                        SUM(si.subtotal - (si.quantity * p.purchase_price)) as profit,
                        SUM(si.subtotal - (si.quantity * p.purchase_price)) / SUM(si.subtotal) * 100 as margin
                        FROM sales_items si
                        JOIN sales s ON si.sale_id = s.id
                        JOIN products p ON si.product_id = p.id
                        LEFT JOIN categories c ON p.category_id = c.id
                        WHERE s.sale_date BETWEEN '$date_from' AND '$date_to'
                        GROUP BY p.category_id
                        ORDER BY profit DESC";
$categoryProfit = $conn->query($categoryProfitQuery);

// Top Profitable Products
$topProfitableQuery = "SELECT 
                       p.name as product_name,
                       p.sku,
                       SUM(si.quantity) as units_sold,
                       SUM(si.subtotal) as revenue,
                       SUM(si.quantity * p.purchase_price) as cost,
                       SUM(si.subtotal - (si.quantity * p.purchase_price)) as profit,
                       (SUM(si.subtotal - (si.quantity * p.purchase_price)) / SUM(si.subtotal)) * 100 as margin
                       FROM sales_items si
                       JOIN sales s ON si.sale_id = s.id
                       JOIN products p ON si.product_id = p.id
                       WHERE s.sale_date BETWEEN '$date_from' AND '$date_to'
                       GROUP BY p.id
                       ORDER BY profit DESC
                       LIMIT 10";
$topProfitable = $conn->query($topProfitableQuery);

// Monthly Trend (if viewing more than 1 month)
$monthlyTrendQuery = "SELECT 
                      DATE_FORMAT(s.sale_date, '%Y-%m') as month,
                      SUM(s.total_amount) as revenue,
                      SUM(si.quantity * p.purchase_price) as cost,
                      SUM(s.total_amount) - SUM(si.quantity * p.purchase_price) as profit
                      FROM sales s
                      JOIN sales_items si ON s.id = si.sale_id
                      JOIN products p ON si.product_id = p.id
                      WHERE s.sale_date BETWEEN '$date_from' AND '$date_to'
                      GROUP BY DATE_FORMAT(s.sale_date, '%Y-%m')
                      ORDER BY month ASC";
$monthlyTrend = $conn->query($monthlyTrendQuery);

include '../../includes/header.php';
?>

<!-- Page Header -->
<div class="mb-6">
    <h1 class="text-3xl font-bold text-gray-800">Profit & Loss Statement</h1>
    <p class="text-gray-600">Financial performance analysis</p>
</div>

<!-- Filters -->
<div class="bg-white rounded-lg shadow p-6 mb-6">
    <form method="GET" action="" class="grid grid-cols-1 md:grid-cols-3 gap-4">
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
        <div class="flex items-end gap-2">
            <button type="submit" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-semibold transition">
                Generate Report
            </button>
        </div>
    </form>
</div>

<!-- Summary Cards -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
    <div class="bg-white rounded-lg shadow p-6">
        <p class="text-gray-500 text-sm">Net Sales</p>
        <h3 class="text-2xl font-bold text-blue-600"><?php echo formatCurrency($netSales); ?></h3>
        <p class="text-xs text-gray-500 mt-1"><?php echo $salesData['invoice_count']; ?> invoices</p>
    </div>
    
    <div class="bg-white rounded-lg shadow p-6">
        <p class="text-gray-500 text-sm">Gross Profit</p>
        <h3 class="text-2xl font-bold text-green-600"><?php echo formatCurrency($grossProfit); ?></h3>
        <p class="text-xs text-gray-500 mt-1"><?php echo number_format($grossProfitMargin, 1); ?>% margin</p>
    </div>
    
    <div class="bg-white rounded-lg shadow p-6">
        <p class="text-gray-500 text-sm">Operating Expenses</p>
        <h3 class="text-2xl font-bold text-red-600"><?php echo formatCurrency($totalOperatingExpenses); ?></h3>
        <p class="text-xs text-gray-500 mt-1">Commissions & costs</p>
    </div>
    
    <div class="bg-white rounded-lg shadow p-6">
        <p class="text-gray-500 text-sm">Net Profit</p>
        <h3 class="text-2xl font-bold <?php echo $netProfit >= 0 ? 'text-green-600' : 'text-red-600'; ?>">
            <?php echo formatCurrency($netProfit); ?>
        </h3>
        <p class="text-xs text-gray-500 mt-1"><?php echo number_format($netProfitMargin, 1); ?>% margin</p>
    </div>
</div>

<!-- P&L Statement -->
<div class="bg-white rounded-lg shadow overflow-hidden mb-6">
    <div class="p-6 border-b">
        <h3 class="text-lg font-bold text-gray-800">Profit & Loss Statement</h3>
        <p class="text-sm text-gray-600">Period: <?php echo formatDate($date_from); ?> to <?php echo formatDate($date_to); ?></p>
    </div>
    <div class="p-6">
        <table class="w-full">
            <tbody>
                <!-- REVENUE SECTION -->
                <tr class="border-b-2 border-gray-300">
                    <td colspan="2" class="py-3">
                        <h4 class="text-lg font-bold text-gray-800">REVENUE</h4>
                    </td>
                </tr>
                <tr class="border-b">
                    <td class="py-2 pl-6">Gross Sales</td>
                    <td class="py-2 text-right font-semibold"><?php echo formatCurrency($grossSales); ?></td>
                </tr>
                <tr class="border-b">
                    <td class="py-2 pl-6 text-red-600">Less: Discounts</td>
                    <td class="py-2 text-right text-red-600">-<?php echo formatCurrency($discounts); ?></td>
                </tr>
                <tr class="border-b-2 border-gray-400 bg-blue-50">
                    <td class="py-3 pl-6 font-bold">Net Sales</td>
                    <td class="py-3 text-right font-bold text-blue-600 text-lg">
                        <?php echo formatCurrency($netSales); ?>
                    </td>
                </tr>
                
                <!-- COST OF GOODS SOLD -->
                <tr class="border-b-2 border-gray-300">
                    <td colspan="2" class="py-3 pt-6">
                        <h4 class="text-lg font-bold text-gray-800">COST OF GOODS SOLD</h4>
                    </td>
                </tr>
                <tr class="border-b">
                    <td class="py-2 pl-6">Cost of Products Sold</td>
                    <td class="py-2 text-right font-semibold text-red-600"><?php echo formatCurrency($cogs); ?></td>
                </tr>
                <tr class="border-b-2 border-gray-400 bg-green-50">
                    <td class="py-3 pl-6 font-bold">Gross Profit</td>
                    <td class="py-3 text-right font-bold text-green-600 text-lg">
                        <?php echo formatCurrency($grossProfit); ?>
                        <span class="text-sm ml-2">(<?php echo number_format($grossProfitMargin, 1); ?>%)</span>
                    </td>
                </tr>
                
                <!-- OPERATING EXPENSES -->
                <tr class="border-b-2 border-gray-300">
                    <td colspan="2" class="py-3 pt-6">
                        <h4 class="text-lg font-bold text-gray-800">OPERATING EXPENSES</h4>
                    </td>
                </tr>
                <tr class="border-b">
                    <td class="py-2 pl-6">Booker Commissions</td>
                    <td class="py-2 text-right font-semibold text-red-600"><?php echo formatCurrency($commissionExpense); ?></td>
                </tr>
                <tr class="border-b">
                    <td class="py-2 pl-6 italic text-gray-500">Other Operating Expenses</td>
                    <td class="py-2 text-right text-gray-500">-</td>
                </tr>
                <tr class="border-b-2 border-gray-400">
                    <td class="py-3 pl-6 font-bold">Total Operating Expenses</td>
                    <td class="py-3 text-right font-bold text-red-600">
                        <?php echo formatCurrency($totalOperatingExpenses); ?>
                    </td>
                </tr>
                
                <!-- NET PROFIT -->
                <tr class="border-b-2 border-gray-500 <?php echo $netProfit >= 0 ? 'bg-green-100' : 'bg-red-100'; ?>">
                    <td class="py-4 pl-6 font-bold text-lg">NET PROFIT</td>
                    <td class="py-4 text-right font-bold text-xl <?php echo $netProfit >= 0 ? 'text-green-700' : 'text-red-700'; ?>">
                        <?php echo formatCurrency($netProfit); ?>
                        <span class="text-sm ml-2">(<?php echo number_format($netProfitMargin, 1); ?>%)</span>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Category-wise Profit Analysis -->
<div class="bg-white rounded-lg shadow overflow-hidden mb-6">
    <div class="p-6 border-b">
        <h3 class="text-lg font-bold text-gray-800">Profit Analysis by Category</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Category</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Products</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Units Sold</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Revenue</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Cost</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Profit</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Margin %</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php if ($categoryProfit->num_rows > 0): ?>
                    <?php while ($cat = $categoryProfit->fetch_assoc()): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 text-sm font-medium text-gray-900">
                                <?php echo $cat['category'] ?: 'Uncategorized'; ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-right"><?php echo $cat['products']; ?></td>
                            <td class="px-6 py-4 text-sm text-right"><?php echo number_format($cat['units_sold']); ?></td>
                            <td class="px-6 py-4 text-sm text-right font-semibold"><?php echo formatCurrency($cat['revenue']); ?></td>
                            <td class="px-6 py-4 text-sm text-right text-red-600"><?php echo formatCurrency($cat['cost']); ?></td>
                            <td class="px-6 py-4 text-sm text-right font-bold text-green-600"><?php echo formatCurrency($cat['profit']); ?></td>
                            <td class="px-6 py-4 text-sm text-right">
                                <span class="<?php echo $cat['margin'] > 20 ? 'text-green-600' : ($cat['margin'] > 10 ? 'text-yellow-600' : 'text-red-600'); ?> font-semibold">
                                    <?php echo number_format($cat['margin'], 1); ?>%
                                </span>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="px-6 py-8 text-center text-gray-500">
                            No category data available
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Top 10 Most Profitable Products -->
<div class="bg-white rounded-lg shadow overflow-hidden mb-6">
    <div class="p-6 border-b">
        <h3 class="text-lg font-bold text-gray-800">Top 10 Most Profitable Products</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">#</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Product</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Units Sold</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Revenue</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Cost</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Profit</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Margin %</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php if ($topProfitable->num_rows > 0): ?>
                    <?php 
                    $rank = 1;
                    while ($prod = $topProfitable->fetch_assoc()): 
                    ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 text-sm font-bold text-gray-900"><?php echo $rank++; ?></td>
                            <td class="px-6 py-4">
                                <div class="text-sm font-medium text-gray-900"><?php echo $prod['product_name']; ?></div>
                                <div class="text-xs text-gray-500">SKU: <?php echo $prod['sku']; ?></div>
                            </td>
                            <td class="px-6 py-4 text-sm text-right"><?php echo number_format($prod['units_sold']); ?></td>
                            <td class="px-6 py-4 text-sm text-right font-semibold"><?php echo formatCurrency($prod['revenue']); ?></td>
                            <td class="px-6 py-4 text-sm text-right text-red-600"><?php echo formatCurrency($prod['cost']); ?></td>
                            <td class="px-6 py-4 text-sm text-right font-bold text-green-600"><?php echo formatCurrency($prod['profit']); ?></td>
                            <td class="px-6 py-4 text-sm text-right">
                                <span class="<?php echo $prod['margin'] > 20 ? 'text-green-600' : ($prod['margin'] > 10 ? 'text-yellow-600' : 'text-red-600'); ?> font-semibold">
                                    <?php echo number_format($prod['margin'], 1); ?>%
                                </span>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="px-6 py-8 text-center text-gray-500">
                            No product data available
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Key Insights -->
<div class="bg-blue-50 border border-blue-200 rounded-lg p-6">
    <h3 class="text-lg font-bold text-blue-900 mb-3">📊 Financial Insights</h3>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
        <div>
            <p class="text-blue-700 font-semibold">Gross Profit Margin:</p>
            <p class="text-blue-900 text-xl font-bold">
                <?php echo number_format($grossProfitMargin, 2); ?>%
            </p>
            <p class="text-xs text-blue-600 mt-1">
                <?php echo $grossProfitMargin > 30 ? 'Excellent' : ($grossProfitMargin > 20 ? 'Good' : 'Needs Improvement'); ?>
            </p>
        </div>
        <div>
            <p class="text-blue-700 font-semibold">Net Profit Margin:</p>
            <p class="text-blue-900 text-xl font-bold">
                <?php echo number_format($netProfitMargin, 2); ?>%
            </p>
            <p class="text-xs text-blue-600 mt-1">
                After operating expenses
            </p>
        </div>
        <div>
            <p class="text-blue-700 font-semibold">Operating Expense Ratio:</p>
            <p class="text-blue-900 text-xl font-bold">
                <?php echo $netSales > 0 ? number_format(($totalOperatingExpenses / $netSales) * 100, 2) : 0; ?>%
            </p>
            <p class="text-xs text-blue-600 mt-1">
                Of net sales
            </p>
        </div>
    </div>
</div>


<?php
include '../../includes/footer.php';
?>