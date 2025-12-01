<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';

requireLogin();

$conn = getDBConnection();
$pageTitle = 'Reports';

include '../../includes/header.php';
?>

<!-- Page Header -->
<div class="mb-6">
    <h1 class="text-3xl font-bold text-gray-800">Reports & Analytics</h1>
    <p class="text-gray-600">Generate comprehensive business reports</p>
</div>

<!-- Reports Grid -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-6">
    
    <!-- Sales Reports -->
    <div class="bg-white rounded-lg shadow hover:shadow-lg transition p-6">
        <div class="flex items-center mb-4">
            <div class="bg-blue-100 p-3 rounded-full mr-4">
                <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                </svg>
            </div>
            <div>
                <h3 class="text-xl font-bold text-gray-800">Sales Reports</h3>
                <p class="text-sm text-gray-600">Analyze sales performance</p>
            </div>
        </div>
        <ul class="space-y-2">
            <li>
                <a href="sales_summary.php" class="text-blue-600 hover:text-blue-800 hover:underline">
                    → Sales Summary Report
                </a>
            </li>
            <li>
                <a href="sales_by_customer.php" class="text-blue-600 hover:text-blue-800 hover:underline">
                    → Sales by Customer
                </a>
            </li>
            <li>
                <a href="sales_by_product.php" class="text-blue-600 hover:text-blue-800 hover:underline">
                    → Sales by Product
                </a>
            </li>
            <li>
                <a href="sales_by_booker.php" class="text-blue-600 hover:text-blue-800 hover:underline">
                    → Sales by Booker
                </a>
            </li>
        </ul>
    </div>

    <!-- Inventory Reports -->
    <div class="bg-white rounded-lg shadow hover:shadow-lg transition p-6">
        <div class="flex items-center mb-4">
            <div class="bg-green-100 p-3 rounded-full mr-4">
                <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                </svg>
            </div>
            <div>
                <h3 class="text-xl font-bold text-gray-800">Inventory Reports</h3>
                <p class="text-sm text-gray-600">Stock & inventory analysis</p>
            </div>
        </div>
        <ul class="space-y-2">
            <li>
                <a href="stock_summary.php" class="text-blue-600 hover:text-blue-800 hover:underline">
                    → Stock Summary
                </a>
            </li>
            <li>
                <a href="low_stock.php" class="text-blue-600 hover:text-blue-800 hover:underline">
                    → Low Stock Items
                </a>
            </li>
            <li>
                <a href="stock_movement.php" class="text-blue-600 hover:text-blue-800 hover:underline">
                    → Stock Movement Report
                </a>
            </li>
            <li>
                <a href="inventory_value.php" class="text-blue-600 hover:text-blue-800 hover:underline">
                    → Inventory Valuation
                </a>
            </li>
        </ul>
    </div>

    <!-- Purchase Reports -->
    <div class="bg-white rounded-lg shadow hover:shadow-lg transition p-6">
        <div class="flex items-center mb-4">
            <div class="bg-purple-100 p-3 rounded-full mr-4">
                <svg class="w-8 h-8 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path>
                </svg>
            </div>
            <div>
                <h3 class="text-xl font-bold text-gray-800">Purchase Reports</h3>
                <p class="text-sm text-gray-600">Supplier & purchase analysis</p>
            </div>
        </div>
        <ul class="space-y-2">
            <li>
                <a href="purchase_summary.php" class="text-blue-600 hover:text-blue-800 hover:underline">
                    → Purchase Summary
                </a>
            </li>
            <li>
                <a href="purchase_by_supplier.php" class="text-blue-600 hover:text-blue-800 hover:underline">
                    → Purchases by Supplier
                </a>
            </li>
            <li>
                <a href="purchase_by_product.php" class="text-blue-600 hover:text-blue-800 hover:underline">
                    → Purchases by Product
                </a>
            </li>
        </ul>
    </div>

    <!-- Booker Performance -->
    <div class="bg-white rounded-lg shadow hover:shadow-lg transition p-6">
        <div class="flex items-center mb-4">
            <div class="bg-orange-100 p-3 rounded-full mr-4">
                <svg class="w-8 h-8 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                </svg>
            </div>
            <div>
                <h3 class="text-xl font-bold text-gray-800">Booker Performance</h3>
                <p class="text-sm text-gray-600">Sales team analysis</p>
            </div>
        </div>
        <ul class="space-y-2">
            <li>
                <a href="booker_performance.php" class="text-blue-600 hover:text-blue-800 hover:underline">
                    → Booker Performance Report
                </a>
            </li>
            <li>
                <a href="booker_commission.php" class="text-blue-600 hover:text-blue-800 hover:underline">
                    → Commission Report
                </a>
            </li>
            <li>
                <a href="booking_status.php" class="text-blue-600 hover:text-blue-800 hover:underline">
                    → Booking Status Report
                </a>
            </li>
        </ul>
    </div>

    <!-- Financial Reports -->
    <div class="bg-white rounded-lg shadow hover:shadow-lg transition p-6">
        <div class="flex items-center mb-4">
            <div class="bg-red-100 p-3 rounded-full mr-4">
                <svg class="w-8 h-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
            <div>
                <h3 class="text-xl font-bold text-gray-800">Financial Reports</h3>
                <p class="text-sm text-gray-600">Profit & payment analysis</p>
            </div>
        </div>
        <ul class="space-y-2">
            <li>
                <a href="profit_loss.php" class="text-blue-600 hover:text-blue-800 hover:underline">
                    → Profit & Loss Report
                </a>
            </li>
            <li>
                <a href="outstanding_payments.php" class="text-blue-600 hover:text-blue-800 hover:underline">
                    → Outstanding Payments
                </a>
            </li>
            <li>
                <a href="payment_summary.php" class="text-blue-600 hover:text-blue-800 hover:underline">
                    → Payment Summary
                </a>
            </li>
        </ul>
    </div>

    <!-- Customer Reports -->
    <div class="bg-white rounded-lg shadow hover:shadow-lg transition p-6">
        <div class="flex items-center mb-4">
            <div class="bg-indigo-100 p-3 rounded-full mr-4">
                <svg class="w-8 h-8 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                </svg>
            </div>
            <div>
                <h3 class="text-xl font-bold text-gray-800">Customer Reports</h3>
                <p class="text-sm text-gray-600">Customer insights</p>
            </div>
        </div>
        <ul class="space-y-2">
            <li>
                <a href="top_customers.php" class="text-blue-600 hover:text-blue-800 hover:underline">
                    → Top Customers
                </a>
            </li>
            <li>
                <a href="customer_ledger.php" class="text-blue-600 hover:text-blue-800 hover:underline">
                    → Customer Ledger
                </a>
            </li>
            <li>
                <a href="customer_outstanding.php" class="text-blue-600 hover:text-blue-800 hover:underline">
                    → Customer Outstanding
                </a>
            </li>
        </ul>
    </div>

</div>

<!-- Quick Stats -->
<div class="mt-8 bg-gradient-to-r from-blue-500 to-purple-600 rounded-lg shadow p-6 text-white">
    <h2 class="text-2xl font-bold mb-4">Quick Business Overview</h2>
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <?php
        // Quick stats
        $todaySales = getTodaySales($conn);
        $lowStock = getLowStockCount($conn);
        $pendingPayments = getPendingPayments($conn);
        
        $totalProducts = $conn->query("SELECT COUNT(*) as c FROM products WHERE is_active = 1")->fetch_assoc()['c'];
        ?>
        <div class="bg-white bg-opacity-20 rounded p-4">
            <p class="text-sm opacity-90">Today's Sales</p>
            <p class="text-2xl font-bold"><?php echo formatCurrency($todaySales); ?></p>
        </div>
        <div class="bg-white bg-opacity-20 rounded p-4">
            <p class="text-sm opacity-90">Low Stock Items</p>
            <p class="text-2xl font-bold"><?php echo $lowStock; ?></p>
        </div>
        <div class="bg-white bg-opacity-20 rounded p-4">
            <p class="text-sm opacity-90">Pending Payments</p>
            <p class="text-2xl font-bold"><?php echo formatCurrency($pendingPayments); ?></p>
        </div>
        <div class="bg-white bg-opacity-20 rounded p-4">
            <p class="text-sm opacity-90">Total Products</p>
            <p class="text-2xl font-bold"><?php echo $totalProducts; ?></p>
        </div>
    </div>
</div>

<?php
include '../../includes/footer.php';
?>