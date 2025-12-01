<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

requireLogin();

$conn = getDBConnection();
$pageTitle = 'Dashboard';

// Get statistics
$todaySales = getTodaySales($conn);
$pendingPayments = getPendingPayments($conn);
$lowStockCount = getLowStockCount($conn);

// Get total products count
$productsQuery = "SELECT COUNT(*) as count FROM products WHERE is_active = 1";
$productsResult = $conn->query($productsQuery);
$totalProducts = $productsResult->fetch_assoc()['count'];

// Get total customers count
$customersQuery = "SELECT COUNT(*) as count FROM customers WHERE is_active = 1";
$customersResult = $conn->query($customersQuery);
$totalCustomers = $customersResult->fetch_assoc()['count'];

// Get recent sales (last 5)
$recentSalesQuery = "SELECT s.*, c.name as customer_name 
                     FROM sales s 
                     JOIN customers c ON s.customer_id = c.id 
                     ORDER BY s.created_at DESC 
                     LIMIT 5";
$recentSales = $conn->query($recentSalesQuery);

// Get low stock products
$lowStockQuery = "SELECT * FROM products 
                  WHERE stock_quantity <= reorder_level 
                  AND is_active = 1 
                  ORDER BY stock_quantity ASC 
                  LIMIT 5";
$lowStockProducts = $conn->query($lowStockQuery);

include 'includes/header.php';
?>

<!-- Dashboard Header -->
<div class="mb-6">
    <h1 class="text-3xl font-bold text-gray-800">Dashboard</h1>
    <p class="text-gray-600">Welcome back, <?php echo $_SESSION['full_name']; ?>!</p>
</div>

<!-- Statistics Cards -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    <!-- Today's Sales -->
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-gray-500 text-sm">Today's Sales</p>
                <h3 class="text-2xl font-bold text-gray-800"><?php echo formatCurrency($todaySales); ?></h3>
            </div>
            <div class="bg-green-100 p-3 rounded-full">
                <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
        </div>
    </div>

    <!-- Total Products -->
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-gray-500 text-sm">Total Products</p>
                <h3 class="text-2xl font-bold text-gray-800"><?php echo number_format($totalProducts); ?></h3>
            </div>
            <div class="bg-blue-100 p-3 rounded-full">
                <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                </svg>
            </div>
        </div>
    </div>

    <!-- Total Customers -->
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-gray-500 text-sm">Total Customers</p>
                <h3 class="text-2xl font-bold text-gray-800"><?php echo number_format($totalCustomers); ?></h3>
            </div>
            <div class="bg-purple-100 p-3 rounded-full">
                <svg class="w-8 h-8 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                </svg>
            </div>
        </div>
    </div>

    <!-- Pending Payments -->
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-gray-500 text-sm">Pending Payments</p>
                <h3 class="text-2xl font-bold text-gray-800"><?php echo formatCurrency($pendingPayments); ?></h3>
            </div>
            <div class="bg-red-100 p-3 rounded-full">
                <svg class="w-8 h-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
        </div>
    </div>
</div>

<!-- Recent Activity Section -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <!-- Recent Sales -->
    <div class="bg-white rounded-lg shadow">
        <div class="p-6 border-b">
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-bold text-gray-800">Recent Sales</h2>
                <a href="modules/sales/list.php" class="text-blue-600 hover:text-blue-700 text-sm font-semibold">View All</a>
            </div>
        </div>
        <div class="p-6">
            <?php if ($recentSales->num_rows > 0): ?>
                <div class="space-y-4">
                    <?php while ($sale = $recentSales->fetch_assoc()): ?>
                        <div class="flex items-center justify-between pb-3 border-b">
                            <div>
                                <p class="font-semibold text-gray-800"><?php echo $sale['invoice_number']; ?></p>
                                <p class="text-sm text-gray-600"><?php echo $sale['customer_name']; ?></p>
                                <p class="text-xs text-gray-500"><?php echo formatDate($sale['sale_date']); ?></p>
                            </div>
                            <div class="text-right">
                                <p class="font-bold text-gray-800"><?php echo formatCurrency($sale['total_amount']); ?></p>
                                <span class="text-xs px-2 py-1 rounded <?php echo $sale['payment_status'] === 'paid' ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700'; ?>">
                                    <?php echo ucfirst($sale['payment_status']); ?>
                                </span>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <p class="text-gray-500 text-center py-8">No sales recorded yet</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Low Stock Alert -->
    <div class="bg-white rounded-lg shadow">
        <div class="p-6 border-b">
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-bold text-gray-800">Low Stock Alert</h2>
                <span class="bg-red-100 text-red-700 px-3 py-1 rounded-full text-sm font-semibold"><?php echo $lowStockCount; ?> Items</span>
            </div>
        </div>
        <div class="p-6">
            <?php if ($lowStockProducts->num_rows > 0): ?>
                <div class="space-y-4">
                    <?php while ($product = $lowStockProducts->fetch_assoc()): ?>
                        <div class="flex items-center justify-between pb-3 border-b">
                            <div>
                                <p class="font-semibold text-gray-800"><?php echo $product['name']; ?></p>
                                <p class="text-sm text-gray-600">SKU: <?php echo $product['sku']; ?></p>
                            </div>
                            <div class="text-right">
                                <p class="text-lg font-bold text-red-600"><?php echo $product['stock_quantity']; ?> <?php echo $product['unit']; ?></p>
                                <p class="text-xs text-gray-500">Reorder: <?php echo $product['reorder_level']; ?></p>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <p class="text-gray-500 text-center py-8">All products are well stocked!</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
include 'includes/footer.php';
?>