<?php
/**
 * Dashboard Controller
 * Displays key business metrics and recent activity
 */

require_once 'config/database.php';
require_once 'includes/functions.php';

// Security & Authentication
requireLogin();

$conn = getDBConnection();
$pageTitle = 'Dashboard | Business Analytics';
$currentUser = isset($_SESSION['full_name']) ? $_SESSION['full_name'] : 'User';

// Set timezone for consistent date calculations
date_default_timezone_set('Asia/Kolkata');

// Date calculations for reporting
$today = date('Y-m-d');
$thisMonthStart = date('Y-m-01');
$lastMonthStart = date('Y-m-01', strtotime('-1 month'));
$lastMonthEnd = date('Y-m-t', strtotime('-1 month'));

/**
 * Fetch Dashboard Statistics
 */
function getDashboardStats($conn, $dateRanges) {
    $stats = [];
    
    // Today's sales
    $stmtToday = $conn->prepare("SELECT COALESCE(SUM(total_amount), 0) as sales, COUNT(*) as invoices FROM sales WHERE DATE(created_at) = ?");
    $stmtToday->bind_param('s', $dateRanges['today']);
    $stmtToday->execute();
    $stats['today'] = $stmtToday->get_result()->fetch_assoc();
    $stmtToday->close();
    
    // Today's payments
    $stmtTodayPayments = $conn->prepare("SELECT COALESCE(SUM(payment_amount), 0) as amount, COUNT(*) as count FROM payments WHERE DATE(payment_date) = ?");
    $stmtTodayPayments->bind_param('s', $dateRanges['today']);
    $stmtTodayPayments->execute();
    $stats['today_payments'] = $stmtTodayPayments->get_result()->fetch_assoc();
    $stmtTodayPayments->close();
    
    // This month sales
    $stmtMonthSales = $conn->prepare("SELECT COALESCE(SUM(total_amount), 0) as sales, COALESCE(SUM(paid_amount), 0) as collected, COUNT(*) as invoices FROM sales WHERE sale_date >= ?");
    $stmtMonthSales->bind_param('s', $dateRanges['month_start']);
    $stmtMonthSales->execute();
    $stats['month'] = $stmtMonthSales->get_result()->fetch_assoc();
    $stmtMonthSales->close();
    
    // Last month sales
    $stmtLastMonth = $conn->prepare("SELECT COALESCE(SUM(total_amount), 0) as sales FROM sales WHERE sale_date BETWEEN ? AND ?");
    $stmtLastMonth->bind_param('ss', $dateRanges['last_month_start'], $dateRanges['last_month_end']);
    $stmtLastMonth->execute();
    $stats['last_month'] = $stmtLastMonth->get_result()->fetch_assoc();
    $stmtLastMonth->close();
    
    // Calculate growth percentage
    $lastMonthSales = (float)($stats['last_month']['sales'] ?? 0);
    $currentMonthSales = (float)($stats['month']['sales'] ?? 0);
    
    if ($lastMonthSales > 0) {
        $stats['growth_percentage'] = (($currentMonthSales - $lastMonthSales) / $lastMonthSales) * 100;
    } else {
        $stats['growth_percentage'] = $currentMonthSales > 0 ? 100 : 0;
    }
    
    return $stats;
}

/**
 * Fetch Outstanding Payments
 */
function getOutstandingStats($conn) {
    $stmt = $conn->prepare("SELECT SUM(total_amount - paid_amount) as total, COUNT(*) as count FROM sales WHERE payment_status != 'paid' AND total_amount > paid_amount");
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    return [
        'total' => $result['total'] ?? 0,
        'count' => $result['count'] ?? 0
    ];
}

/**
 * Fetch Recent Activity
 */
function getRecentActivity($conn) {
    $activity = [];
    
    // Recent Sales
    $stmtSales = $conn->prepare("SELECT s.*, c.name as customer_name FROM sales s JOIN customers c ON s.customer_id = c.id ORDER BY s.created_at DESC LIMIT 10");
    $stmtSales->execute();
    $activity['recent_sales'] = $stmtSales->get_result();
    $stmtSales->close();
    
    // Recent Payments
    $stmtPayments = $conn->prepare("SELECT p.*, s.invoice_number, c.name as customer_name FROM payments p JOIN sales s ON p.invoice_id = s.id JOIN customers c ON s.customer_id = c.id ORDER BY p.created_at DESC LIMIT 5");
    $stmtPayments->execute();
    $activity['recent_payments'] = $stmtPayments->get_result();
    $stmtPayments->close();
    
    return $activity;
}

/**
 * Fetch Top Performers
 */
function getTopPerformers($conn, $monthStart) {
    $performers = [];
    
    // Top Products
    $stmtProducts = $conn->prepare("SELECT p.name, p.sku, SUM(si.quantity) as qty, SUM(si.subtotal) as revenue FROM sales_items si JOIN sales s ON si.sale_id = s.id JOIN products p ON si.product_id = p.id WHERE s.sale_date >= ? GROUP BY p.id ORDER BY revenue DESC LIMIT 5");
    $stmtProducts->bind_param('s', $monthStart);
    $stmtProducts->execute();
    $performers['top_products'] = $stmtProducts->get_result();
    $stmtProducts->close();
    
    // Top Customers
    $stmtCustomers = $conn->prepare("SELECT c.name, c.customer_code, COUNT(s.id) as orders, SUM(s.total_amount) as total FROM customers c JOIN sales s ON c.id = s.customer_id WHERE s.sale_date >= ? GROUP BY c.id ORDER BY total DESC LIMIT 5");
    $stmtCustomers->bind_param('s', $monthStart);
    $stmtCustomers->execute();
    $performers['top_customers'] = $stmtCustomers->get_result();
    $stmtCustomers->close();
    
    return $performers;
}

/**
 * Fetch System Counts
 */
function getSystemCounts($conn) {
    $counts = [];
    
    $queries = [
        'products' => "SELECT COUNT(*) as c FROM products WHERE is_active = 1",
        'customers' => "SELECT COUNT(*) as c FROM customers WHERE is_active = 1",
        'bookers' => "SELECT COUNT(*) as c FROM bookers WHERE is_active = 1",
        'pending_bookings' => "SELECT COUNT(*) as c FROM bookings WHERE status = 'pending'",
        'confirmed_bookings' => "SELECT COUNT(*) as c FROM bookings WHERE status = 'confirmed'"
    ];
    
    foreach ($queries as $key => $query) {
        $result = $conn->query($query);
        if ($result) {
            $counts[$key] = $result->fetch_assoc()['c'];
        } else {
            $counts[$key] = 0;
        }
    }
    
    // Check if getLowStockCount function exists
    if (function_exists('getLowStockCount')) {
        $counts['low_stock'] = getLowStockCount($conn);
    } else {
        // Fallback if function doesn't exist
        $result = $conn->query("SELECT COUNT(*) as c FROM products WHERE quantity <= min_stock_level AND quantity > 0");
        $counts['low_stock'] = $result ? $result->fetch_assoc()['c'] : 0;
    }
    
    return $counts;
}

// Fetch all data
$dateRanges = [
    'today' => $today,
    'month_start' => $thisMonthStart,
    'last_month_start' => $lastMonthStart,
    'last_month_end' => $lastMonthEnd
];

$stats = getDashboardStats($conn, $dateRanges);
$outstanding = getOutstandingStats($conn);
$counts = getSystemCounts($conn);
$activity = getRecentActivity($conn);
$performers = getTopPerformers($conn, $thisMonthStart);

include 'includes/header.php';
?>

<!-- Page Header -->
<div class="mb-8">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Dashboard</h1>
            <p class="text-gray-600 mt-1">Welcome back, <?php echo htmlspecialchars($currentUser); ?>! <?php echo date('l, F j, Y'); ?></p>
        </div>
        <div class="flex items-center space-x-3">
            <button id="refreshDashboard" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                </svg>
                Refresh
            </button>
        </div>
    </div>
</div>

<!-- Key Metrics Grid -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    <!-- Sales Card -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden hover:shadow-md transition-shadow duration-200">
        <div class="p-6">
            <div class="flex items-center justify-between mb-4">
                <div class="p-3 bg-blue-50 rounded-lg">
                    <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                    </svg>
                </div>
                <span class="text-xs font-semibold px-2 py-1 rounded-full bg-blue-100 text-blue-800">Today</span>
            </div>
            <h3 class="text-2xl font-bold text-gray-900 mb-1"><?php echo isset($stats['today']['sales']) ? formatCurrency($stats['today']['sales']) : '₹0.00'; ?></h3>
            <p class="text-gray-600 text-sm mb-2">Sales Revenue</p>
            <div class="flex items-center text-sm">
                <span class="text-gray-500"><?php echo $stats['today']['invoices'] ?? 0; ?> invoices</span>
            </div>
        </div>
        <div class="px-6 py-3 bg-gray-50 border-t border-gray-100">
            <a href="modules/sales/list.php?filter=today" class="text-blue-600 hover:text-blue-700 text-sm font-medium inline-flex items-center">
                View details
                <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                </svg>
            </a>
        </div>
    </div>

    <!-- Collections Card -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden hover:shadow-md transition-shadow duration-200">
        <div class="p-6">
            <div class="flex items-center justify-between mb-4">
                <div class="p-3 bg-green-50 rounded-lg">
                    <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <span class="text-xs font-semibold px-2 py-1 rounded-full bg-green-100 text-green-800">Collected</span>
            </div>
            <h3 class="text-2xl font-bold text-gray-900 mb-1"><?php echo isset($stats['today_payments']['amount']) ? formatCurrency($stats['today_payments']['amount']) : '₹0.00'; ?></h3>
            <p class="text-gray-600 text-sm mb-2">Today's Collections</p>
            <div class="flex items-center text-sm">
                <span class="text-gray-500"><?php echo $stats['today_payments']['count'] ?? 0; ?> payments</span>
            </div>
        </div>
        <div class="px-6 py-3 bg-gray-50 border-t border-gray-100">
            <a href="modules/payments/payment_list.php?filter=today" class="text-green-600 hover:text-green-700 text-sm font-medium inline-flex items-center">
                View all payments
                <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                </svg>
            </a>
        </div>
    </div>

    <!-- Outstanding Card -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden hover:shadow-md transition-shadow duration-200">
        <div class="p-6">
            <div class="flex items-center justify-between mb-4">
                <div class="p-3 bg-red-50 rounded-lg">
                    <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.998-.833-2.732 0L4.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                    </svg>
                </div>
                <span class="text-xs font-semibold px-2 py-1 rounded-full bg-red-100 text-red-800">Due</span>
            </div>
            <h3 class="text-2xl font-bold text-gray-900 mb-1"><?php echo formatCurrency($outstanding['total']); ?></h3>
            <p class="text-gray-600 text-sm mb-2">Outstanding Amount</p>
            <div class="flex items-center text-sm">
                <span class="text-gray-500"><?php echo $outstanding['count']; ?> pending invoices</span>
            </div>
        </div>
        <div class="px-6 py-3 bg-gray-50 border-t border-gray-100">
            <a href="modules/reports/outstanding_payments.php" class="text-red-600 hover:text-red-700 text-sm font-medium inline-flex items-center">
                View report
                <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                </svg>
            </a>
        </div>
    </div>

    <!-- Inventory Card -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden hover:shadow-md transition-shadow duration-200">
        <div class="p-6">
            <div class="flex items-center justify-between mb-4">
                <div class="p-3 bg-orange-50 rounded-lg">
                    <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                    </svg>
                </div>
                <span class="text-xs font-semibold px-2 py-1 rounded-full bg-orange-100 text-orange-800">Alert</span>
            </div>
            <h3 class="text-2xl font-bold text-gray-900 mb-1"><?php echo $counts['low_stock']; ?></h3>
            <p class="text-gray-600 text-sm mb-2">Low Stock Items</p>
            <div class="flex items-center text-sm">
                <span class="text-gray-500">Total Products: <?php echo $counts['products']; ?></span>
            </div>
        </div>
        <div class="px-6 py-3 bg-gray-50 border-t border-gray-100">
            <a href="modules/inventory/low_stock.php" class="text-orange-600 hover:text-orange-700 text-sm font-medium inline-flex items-center">
                Review inventory
                <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                </svg>
            </a>
        </div>
    </div>
</div>

<!-- Monthly Performance & Quick Actions -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
    <!-- Monthly Performance -->
    <div class="lg:col-span-2 bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-xl font-bold text-gray-900">Monthly Performance</h2>
            <div class="flex items-center space-x-2">
                <span class="text-sm text-gray-600"><?php echo date('F Y'); ?></span>
                <?php if (isset($stats['growth_percentage'])): ?>
                <span class="text-sm font-semibold px-3 py-1 rounded-full <?php echo $stats['growth_percentage'] >= 0 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                    <?php echo $stats['growth_percentage'] >= 0 ? '↑' : '↓'; ?>
                    <?php echo number_format(abs($stats['growth_percentage']), 1); ?>%
                </span>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="grid grid-cols-3 gap-4 mb-6">
            <div class="text-center p-4 border border-gray-100 rounded-lg">
                <p class="text-sm text-gray-600 mb-1">Total Sales</p>
                <p class="text-2xl font-bold text-gray-900"><?php echo isset($stats['month']['sales']) ? formatCurrency($stats['month']['sales']) : '₹0.00'; ?></p>
            </div>
            <div class="text-center p-4 border border-gray-100 rounded-lg">
                <p class="text-sm text-gray-600 mb-1">Collected</p>
                <p class="text-2xl font-bold text-green-600"><?php echo isset($stats['month']['collected']) ? formatCurrency($stats['month']['collected']) : '₹0.00'; ?></p>
            </div>
            <div class="text-center p-4 border border-gray-100 rounded-lg">
                <p class="text-sm text-gray-600 mb-1">Invoices</p>
                <p class="text-2xl font-bold text-blue-600"><?php echo $stats['month']['invoices'] ?? 0; ?></p>
            </div>
        </div>
        
        <div class="border-t pt-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">System Overview</h3>
            <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                <div class="flex items-center p-3 bg-gray-50 rounded-lg">
                    <div class="p-2 bg-blue-100 rounded mr-3">
                        <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Products</p>
                        <p class="font-bold text-gray-900"><?php echo $counts['products']; ?></p>
                    </div>
                </div>
                
                <div class="flex items-center p-3 bg-gray-50 rounded-lg">
                    <div class="p-2 bg-green-100 rounded mr-3">
                        <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Customers</p>
                        <p class="font-bold text-gray-900"><?php echo $counts['customers']; ?></p>
                    </div>
                </div>
                
                <div class="flex items-center p-3 bg-gray-50 rounded-lg">
                    <div class="p-2 bg-purple-100 rounded mr-3">
                        <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Bookers</p>
                        <p class="font-bold text-gray-900"><?php echo $counts['bookers']; ?></p>
                    </div>
                </div>
                
                <div class="flex items-center p-3 bg-gray-50 rounded-lg">
                    <div class="p-2 bg-yellow-100 rounded mr-3">
                        <svg class="w-5 h-5 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Bookings</p>
                        <div class="flex items-center space-x-2">
                            <span class="font-bold text-gray-900"><?php echo $counts['pending_bookings']; ?></span>
                            <span class="text-xs text-gray-500">pending</span>
                            <span class="text-gray-300">|</span>
                            <span class="font-bold text-green-600"><?php echo $counts['confirmed_bookings']; ?></span>
                            <span class="text-xs text-gray-500">confirmed</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h2 class="text-xl font-bold text-gray-900 mb-6">Quick Actions</h2>
        <div class="space-y-3">
            <a href="modules/sales/add.php" class="flex items-center justify-between p-4 bg-blue-50 hover:bg-blue-100 border border-blue-100 rounded-lg transition-colors duration-150 group">
                <div class="flex items-center">
                    <div class="p-2 bg-blue-100 rounded-lg mr-3 group-hover:bg-blue-200">
                        <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                        </svg>
                    </div>
                    <div>
                        <p class="font-medium text-gray-900">New Sale</p>
                        <p class="text-sm text-gray-600">Create invoice</p>
                    </div>
                </div>
                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                </svg>
            </a>
            
            <a href="modules/payments/record_payment.php" class="flex items-center justify-between p-4 bg-green-50 hover:bg-green-100 border border-green-100 rounded-lg transition-colors duration-150 group">
                <div class="flex items-center">
                    <div class="p-2 bg-green-100 rounded-lg mr-3 group-hover:bg-green-200">
                        <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2z"></path>
                        </svg>
                    </div>
                    <div>
                        <p class="font-medium text-gray-900">Record Payment</p>
                        <p class="text-sm text-gray-600">Receive payment</p>
                    </div>
                </div>
                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                </svg>
            </a>
            
            <a href="modules/purchases/add.php" class="flex items-center justify-between p-4 bg-purple-50 hover:bg-purple-100 border border-purple-100 rounded-lg transition-colors duration-150 group">
                <div class="flex items-center">
                    <div class="p-2 bg-purple-100 rounded-lg mr-3 group-hover:bg-purple-200">
                        <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"></path>
                        </svg>
                    </div>
                    <div>
                        <p class="font-medium text-gray-900">New Purchase</p>
                        <p class="text-sm text-gray-600">Add inventory</p>
                    </div>
                </div>
                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                </svg>
            </a>
            
            <a href="modules/reports/outstanding_payments.php" class="flex items-center justify-between p-4 bg-red-50 hover:bg-red-100 border border-red-100 rounded-lg transition-colors duration-150 group">
                <div class="flex items-center">
                    <div class="p-2 bg-red-100 rounded-lg mr-3 group-hover:bg-red-200">
                        <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                        </svg>
                    </div>
                    <div>
                        <p class="font-medium text-gray-900">Outstanding Report</p>
                        <p class="text-sm text-gray-600">View due payments</p>
                    </div>
                </div>
                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                </svg>
            </a>
        </div>
    </div>
</div>

<!-- Recent Activity & Top Performers -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
    <!-- Recent Sales -->
    <div class="lg:col-span-2 bg-white rounded-xl shadow-sm border border-gray-200">
        <div class="p-6 border-b border-gray-200">
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-bold text-gray-900">Recent Sales</h2>
                <a href="modules/sales/list.php" class="text-sm font-medium text-blue-600 hover:text-blue-700 inline-flex items-center">
                    View all
                    <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                    </svg>
                </a>
            </div>
        </div>
        <div class="p-6">
            <?php if ($activity['recent_sales']->num_rows > 0): ?>
                <div class="space-y-4">
                    <?php while ($sale = $activity['recent_sales']->fetch_assoc()): ?>
                        <div class="flex items-center justify-between p-3 hover:bg-gray-50 rounded-lg transition-colors duration-150">
                            <div class="flex items-center">
                                <div class="mr-4">
                                    <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                                        <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                        </svg>
                                    </div>
                                </div>
                                <div>
                                    <p class="font-medium text-gray-900"><?php echo htmlspecialchars($sale['invoice_number'] ?? ''); ?></p>
                                    <p class="text-sm text-gray-600"><?php echo htmlspecialchars($sale['customer_name'] ?? ''); ?></p>
                                    <p class="text-xs text-gray-500"><?php echo isset($sale['sale_date']) ? formatDate($sale['sale_date']) : ''; ?></p>
                                </div>
                            </div>
                            <div class="text-right">
                                <p class="font-bold text-gray-900"><?php echo isset($sale['total_amount']) ? formatCurrency($sale['total_amount']) : '₹0.00'; ?></p>
                                <?php if (isset($sale['payment_status'])): ?>
                                <span class="text-xs font-medium px-2 py-1 rounded-full <?php echo $sale['payment_status'] === 'paid' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'; ?>">
                                    <?php echo ucfirst($sale['payment_status']); ?>
                                </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-8">
                    <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                    </div>
                    <p class="text-gray-600">No sales recorded yet</p>
                    <a href="modules/sales/add.php" class="mt-3 inline-flex items-center text-sm text-blue-600 hover:text-blue-700 font-medium">
                        Create your first sale
                        <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                        </svg>
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Top Performers -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
        <div class="p-6 border-b border-gray-200">
            <h2 class="text-xl font-bold text-gray-900">Top Performers</h2>
            <p class="text-sm text-gray-600 mt-1">This month's leaders</p>
        </div>
        <div class="p-6">
            <div class="space-y-6">
                <!-- Top Products -->
                <div>
                    <h3 class="text-sm font-semibold text-gray-900 mb-3">Top Products</h3>
                    <?php if ($performers['top_products']->num_rows > 0): ?>
                        <div class="space-y-3">
                            <?php 
                            $rank = 1;
                            while ($product = $performers['top_products']->fetch_assoc()): 
                            ?>
                                <div class="flex items-center justify-between p-2 hover:bg-gray-50 rounded">
                                    <div class="flex items-center">
                                        <span class="w-6 h-6 bg-blue-100 text-blue-800 text-xs font-bold rounded-full flex items-center justify-center mr-3">
                                            <?php echo $rank++; ?>
                                        </span>
                                        <div class="truncate">
                                            <p class="font-medium text-gray-900 truncate"><?php echo htmlspecialchars($product['name'] ?? ''); ?></p>
                                            <p class="text-xs text-gray-500">SKU: <?php echo htmlspecialchars($product['sku'] ?? ''); ?></p>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <p class="font-bold text-blue-600"><?php echo isset($product['revenue']) ? formatCurrency($product['revenue']) : '₹0.00'; ?></p>
                                        <p class="text-xs text-gray-500"><?php echo $product['qty'] ?? 0; ?> units</p>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-gray-500 text-sm text-center py-4">No product sales this month</p>
                    <?php endif; ?>
                </div>
                
                <div class="border-t pt-6">
                    <h3 class="text-sm font-semibold text-gray-900 mb-3">Top Customers</h3>
                    <?php if ($performers['top_customers']->num_rows > 0): ?>
                        <div class="space-y-3">
                            <?php 
                            $rank = 1;
                            while ($customer = $performers['top_customers']->fetch_assoc()): 
                            ?>
                                <div class="flex items-center justify-between p-2 hover:bg-gray-50 rounded">
                                    <div class="flex items-center">
                                        <span class="w-6 h-6 bg-green-100 text-green-800 text-xs font-bold rounded-full flex items-center justify-center mr-3">
                                            <?php echo $rank++; ?>
                                        </span>
                                        <div class="truncate">
                                            <p class="font-medium text-gray-900 truncate"><?php echo htmlspecialchars($customer['name'] ?? ''); ?></p>
                                            <p class="text-xs text-gray-500"><?php echo $customer['orders'] ?? 0; ?> orders</p>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <p class="font-bold text-green-600"><?php echo isset($customer['total']) ? formatCurrency($customer['total']) : '₹0.00'; ?></p>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-gray-500 text-sm text-center py-4">No customer sales this month</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Refresh dashboard button
    const refreshBtn = document.getElementById('refreshDashboard');
    if (refreshBtn) {
        refreshBtn.addEventListener('click', function() {
            this.classList.add('opacity-50', 'cursor-not-allowed');
            const originalText = this.innerHTML;
            this.innerHTML = '<svg class="animate-spin w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>Refreshing...';
            
            setTimeout(() => {
                location.reload();
            }, 1000);
        });
    }
});
</script>

<?php
include 'includes/footer.php';
?>