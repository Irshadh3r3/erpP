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
    <p class="text-gray-600">Generate business insights and reports</p>
</div>

<!-- Report Categories -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
    
    <!-- Sales Reports -->
    <div class="bg-white rounded-lg shadow hover:shadow-lg transition">
        <div class="p-6 border-b bg-blue-50">
            <div class="flex items-center">
                <div class="bg-blue-600 p-3 rounded-lg">
                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                    </svg>
                </div>
                <div class="ml-4">
                    <h3 class="text-lg font-bold text-gray-800">Sales Reports</h3>
                    <p class="text-sm text-gray-600">Analyze sales performance</p>
                </div>
            </div>
        </div>
        <div class="p-6">
            <ul class="space-y-3">
                <li>
                    <a href="sales_by_date.php" class="flex items-center justify-between text-gray-700 hover:text-blue-600 transition">
                        <span>Sales by Date Range</span>
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                        </svg>
                    </a>
                </li>
                <li>
                    <a href="sales_by_booker.php" class="flex items-center justify-between text-gray-700 hover:text-blue-600 transition">
                        <span>Sales by Booker</span>
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                        </svg>
                    </a>
                </li>
                <li>
                    <a href="sales_by_product.php" class="flex items-center justify-between text-gray-700 hover:text-blue-600 transition">
                        <span>Sales by Product</span>
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                        </svg>
                    </a>
                </li>
                <li>
                    <a href="sales_by_customer.php" class="flex items-center justify-between text-gray-700 hover:text-blue-600 transition">
                        <span>Sales by Customer</span>
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                        </svg>
                    </a>
                </li>
            </ul>
        </div>
    </div>

    <!-- Inventory Reports -->
    <div class="bg-white rounded-lg shadow hover:shadow-lg transition">
        <div class="p-6 border-b bg-green-50">
            <div class="flex items-center">
                <div class="bg-green-600 p-3 rounded-lg">
                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                    </svg>
                </div>
                <div class="ml-4">
                    <h3 class="text-lg font-bold text-gray-800">Inventory Reports</h3>
                    <p class="text-sm text-gray-600">Stock analysis</p>
                </div>
            </div>
        </div>
        <div class="p-6">
            <ul class="space-y-3">
                <li>
                    <a href="stock_summary.php" class="flex items-center justify-between text-gray-700 hover:text-green-600 transition">
                        <span>Stock Summary</span>
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                        </svg>
                    </a>
                </li>
                <li>
                    <a href="low_stock.php" class="flex items-center justify-between text-gray-700 hover:text-green-600 transition">
                        <span>Low Stock Alert</span>
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                        </svg>
                    </a>
                </li>
                <li>
                    <a href="stock_movements.php" class="flex items-center justify-between text-gray-700 hover:text-green-600 transition">
                        <span>Stock Movements</span>
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                        </svg>
                    </a>
                </li>
                <li>
                    <a href="inventory_valuation.php" class="flex items-center justify-between text-gray-700 hover:text-green-600 transition">
                        <span>Inventory Valuation</span>
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                        </svg>
                    </a>
                </li>
            </ul>
        </div>
    </div>

    <!-- Financial Reports -->
    <div class="bg-white rounded-lg shadow hover:shadow-lg transition">
        <div class="p-6 border-b bg-purple-50">
            <div class="flex items-center">
                <div class="bg-purple-600 p-3 rounded-lg">
                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <div class="ml-4">
                    <h3 class="text-lg font-bold text-gray-800">Financial Reports</h3>
                    <p class="text-sm text-gray-600">Money matters</p>
                </div>
            </div>
        </div>
        <div class="p-6">
            <ul class="space-y-3">
                <li>
                    <a href="profit_loss.php" class="flex items-center justify-between text-gray-700 hover:text-purple-600 transition">
                        <span>Profit & Loss</span>
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                        </svg>
                    </a>
                </li>
                <li>
                    <a href="outstanding_payments.php" class="flex items-center justify-between text-gray-700 hover:text-purple-600 transition">
                        <span>Outstanding Payments</span>
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                        </svg>
                    </a>
                </li>
                <li>
                    <a href="payments_received.php" class="flex items-center justify-between text-gray-700 hover:text-purple-600 transition">
                        <span>Payments Received</span>
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                        </svg>
                    </a>
                </li>
                <li>
                    <a href="commission_report.php" class="flex items-center justify-between text-gray-700 hover:text-purple-600 transition">
                        <span>Commission Report</span>
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                        </svg>
                    </a>
                </li>
            </ul>
        </div>
    </div>

    <!-- Booker Performance -->
    <div class="bg-white rounded-lg shadow hover:shadow-lg transition">
        <div class="p-6 border-b bg-orange-50">
            <div class="flex items-center">
                <div class="bg-orange-600 p-3 rounded-lg">
                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                    </svg>
                </div>
                <div class="ml-4">
                    <h3 class="text-lg font-bold text-gray-800">Booker Reports</h3>
                    <p class="text-sm text-gray-600">Performance tracking</p>
                </div>
            </div>
        </div>
        <div class="p-6">
            <ul class="space-y-3">
                <li>
                    <a href="booker_performance.php" class="flex items-center justify-between text-gray-700 hover:text-orange-600 transition">
                        <span>Booker Performance</span>
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                        </svg>
                    </a>
                </li>
                <li>
                    <a href="booker_comparison.php" class="flex items-center justify-between text-gray-700 hover:text-orange-600 transition">
                        <span>Booker Comparison</span>
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                        </svg>
                    </a>
                </li>
                <li>
                    <a href="area_wise_sales.php" class="flex items-center justify-between text-gray-700 hover:text-orange-600 transition">
                        <span>Area-wise Sales</span>
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                        </svg>
                    </a>
                </li>
                <li>
                    <a href="booking_conversion.php" class="flex items-center justify-between text-gray-700 hover:text-orange-600 transition">
                        <span>Booking Conversion Rate</span>
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                        </svg>
                    </a>
                </li>
            </ul>
        </div>
    </div>

    <!-- Purchase Reports -->
    <div class="bg-white rounded-lg shadow hover:shadow-lg transition">
        <div class="p-6 border-b bg-indigo-50">
            <div class="flex items-center">
                <div class="bg-indigo-600 p-3 rounded-lg">
                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path>
                    </svg>
                </div>
                <div class="ml-4">
                    <h3 class="text-lg font-bold text-gray-800">Purchase Reports</h3>
                    <p class="text-sm text-gray-600">Supplier analysis</p>
                </div>
            </div>
        </div>
        <div class="p-6">
            <ul class="space-y-3">
                <li>
                    <a href="purchases_by_date.php" class="flex items-center justify-between text-gray-700 hover:text-indigo-600 transition">
                        <span>Purchases by Date</span>
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                        </svg>
                    </a>
                </li>
                <li>
                    <a href="purchases_by_supplier.php" class="flex items-center justify-between text-gray-700 hover:text-indigo-600 transition">
                        <span>Purchases by Supplier</span>
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                        </svg>
                    </a>
                </li>
                <li>
                    <a href="supplier_payments.php" class="flex items-center justify-between text-gray-700 hover:text-indigo-600 transition">
                        <span>Supplier Payments</span>
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                        </svg>
                    </a>
                </li>
            </ul>
        </div>
    </div>

    <!-- Quick Summary -->
    <div class="bg-white rounded-lg shadow hover:shadow-lg transition">
        <div class="p-6 border-b bg-pink-50">
            <div class="flex items-center">
                <div class="bg-pink-600 p-3 rounded-lg">
                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                    </svg>
                </div>
                <div class="ml-4">
                    <h3 class="text-lg font-bold text-gray-800">Quick Reports</h3>
                    <p class="text-sm text-gray-600">Instant insights</p>
                </div>
            </div>
        </div>
        <div class="p-6">
            <ul class="space-y-3">
                <li>
                    <a href="daily_summary.php" class="flex items-center justify-between text-gray-700 hover:text-pink-600 transition">
                        <span>Daily Summary</span>
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                        </svg>
                    </a>
                </li>
                <li>
                    <a href="monthly_summary.php" class="flex items-center justify-between text-gray-700 hover:text-pink-600 transition">
                        <span>Monthly Summary</span>
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                        </svg>
                    </a>
                </li>
                <li>
                    <a href="top_products.php" class="flex items-center justify-between text-gray-700 hover:text-pink-600 transition">
                        <span>Top Selling Products</span>
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                        </svg>
                    </a>
                </li>
                <li>
                    <a href="top_customers.php" class="flex items-center justify-between text-gray-700 hover:text-pink-600 transition">
                        <span>Top Customers</span>
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                        </svg>
                    </a>
                </li>
            </ul>
        </div>
    </div>

</div>

<?php
include '../../includes/footer.php';
?>