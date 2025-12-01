<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';

requireLogin();

$conn = getDBConnection();
$pageTitle = 'Customer Details';

$customerId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($customerId <= 0) {
    $_SESSION['error_message'] = 'Invalid customer ID';
    header('Location: list.php');
    exit;
}

// Get customer details
$customerQuery = "SELECT * FROM customers WHERE id = $customerId";
$customerResult = $conn->query($customerQuery);

if ($customerResult->num_rows === 0) {
    $_SESSION['error_message'] = 'Customer not found';
    header('Location: list.php');
    exit;
}

$customer = $customerResult->fetch_assoc();

// Get customer stats
$statsQuery = "SELECT 
               COUNT(*) as total_invoices,
               COALESCE(SUM(total_amount), 0) as total_sales,
               COALESCE(SUM(paid_amount), 0) as total_paid,
               COALESCE(SUM(total_amount - paid_amount), 0) as outstanding_balance
               FROM sales
               WHERE customer_id = $customerId";
$stats = $conn->query($statsQuery)->fetch_assoc();

// Get recent invoices
$invoicesQuery = "SELECT s.*
                  FROM sales s
                  WHERE s.customer_id = $customerId
                  ORDER BY s.created_at DESC
                  LIMIT 10";
$recentInvoices = $conn->query($invoicesQuery);

// Get bookings
$bookingsQuery = "SELECT b.*, bk.name as booker_name
                  FROM bookings b
                  LEFT JOIN bookers bk ON b.booker_id = bk.id
                  WHERE b.customer_id = $customerId OR b.customer_name = '{$customer['name']}'
                  ORDER BY b.created_at DESC
                  LIMIT 10";
$recentBookings = $conn->query($bookingsQuery);

include '../../includes/header.php';
?>

<!-- Page Header -->
<div class="mb-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-bold text-gray-800"><?php echo $customer['name']; ?></h1>
            <p class="text-gray-600"><?php echo $customer['customer_code']; ?></p>
        </div>
        <div class="flex gap-2">
            <a href="list.php" class="px-6 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 font-semibold transition">
                Back to List
            </a>
            <a href="edit.php?id=<?php echo $customer['id']; ?>" 
               class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg font-semibold transition">
                Edit Customer
            </a>
        </div>
    </div>
</div>

<!-- Customer Info Card -->
<div class="bg-white rounded-lg shadow p-6 mb-6">
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
        <div>
            <p class="text-sm text-gray-500 mb-1">Business Name</p>
            <p class="font-semibold"><?php echo $customer['business_name'] ?: 'N/A'; ?></p>
        </div>
        <div>
            <p class="text-sm text-gray-500 mb-1">Contact Person</p>
            <p class="font-semibold"><?php echo $customer['contact_person'] ?: 'N/A'; ?></p>
        </div>
        <div>
            <p class="text-sm text-gray-500 mb-1">Phone</p>
            <p class="font-semibold"><?php echo $customer['phone']; ?></p>
        </div>
        <div>
            <p class="text-sm text-gray-500 mb-1">Email</p>
            <p class="font-semibold"><?php echo $customer['email'] ?: 'N/A'; ?></p>
        </div>
        <div>
            <p class="text-sm text-gray-500 mb-1">City</p>
            <p class="font-semibold"><?php echo $customer['city'] ?: 'N/A'; ?></p>
        </div>
        <div>
            <p class="text-sm text-gray-500 mb-1">Area</p>
            <p class="font-semibold"><?php echo $customer['area'] ?: 'N/A'; ?></p>
        </div>
        <div>
            <p class="text-sm text-gray-500 mb-1">Credit Limit</p>
            <p class="font-semibold text-blue-600"><?php echo formatCurrency($customer['credit_limit']); ?></p>
        </div>
        <div>
            <p class="text-sm text-gray-500 mb-1">Member Since</p>
            <p class="font-semibold"><?php echo formatDate($customer['created_at']); ?></p>
        </div>
    </div>
    
    <?php if ($customer['address']): ?>
        <div class="mt-4 pt-4 border-t">
            <p class="text-sm text-gray-500 mb-1">Address</p>
            <p class="font-semibold"><?php echo $customer['address']; ?></p>
        </div>
    <?php endif; ?>
</div>

<!-- Stats Cards -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-gray-500 text-sm">Total Invoices</p>
                <h3 class="text-3xl font-bold text-gray-800 mt-1"><?php echo $stats['total_invoices']; ?></h3>
            </div>
            <div class="bg-blue-100 p-3 rounded-full">
                <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-gray-500 text-sm">Total Sales</p>
                <h3 class="text-2xl font-bold text-gray-800 mt-1"><?php echo formatCurrency($stats['total_sales']); ?></h3>
            </div>
            <div class="bg-green-100 p-3 rounded-full">
                <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-gray-500 text-sm">Amount Paid</p>
                <h3 class="text-2xl font-bold text-green-600 mt-1"><?php echo formatCurrency($stats['total_paid']); ?></h3>
            </div>
            <div class="bg-purple-100 p-3 rounded-full">
                <svg class="w-8 h-8 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-gray-500 text-sm">Outstanding Balance</p>
                <h3 class="text-2xl font-bold text-red-600 mt-1"><?php echo formatCurrency($stats['outstanding_balance']); ?></h3>
            </div>
            <div class="bg-red-100 p-3 rounded-full">
                <svg class="w-8 h-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
        </div>
    </div>
</div>

<!-- Recent Activity -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <!-- Recent Invoices -->
    <div class="bg-white rounded-lg shadow">
        <div class="p-6 border-b">
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-bold text-gray-800">Recent Invoices</h2>
                <a href="../sales/list.php?search=<?php echo $customer['customer_code']; ?>" class="text-blue-600 hover:text-blue-700 text-sm font-semibold">View All</a>
            </div>
        </div>
        <div class="p-6">
            <?php if ($recentInvoices->num_rows > 0): ?>
                <div class="space-y-4">
                    <?php while ($invoice = $recentInvoices->fetch_assoc()): ?>
                        <div class="flex items-center justify-between pb-3 border-b">
                            <div>
                                <p class="font-semibold text-gray-800"><?php echo $invoice['invoice_number']; ?></p>
                                <p class="text-xs text-gray-500"><?php echo formatDate($invoice['sale_date']); ?></p>
                            </div>
                            <div class="text-right">
                                <p class="font-bold text-gray-800"><?php echo formatCurrency($invoice['total_amount']); ?></p>
                                <span class="text-xs px-2 py-1 rounded <?php echo $invoice['payment_status'] === 'paid' ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700'; ?>">
                                    <?php echo ucfirst($invoice['payment_status']); ?>
                                </span>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <p class="text-gray-500 text-center py-8">No invoices yet</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Recent Bookings -->
    <div class="bg-white rounded-lg shadow">
        <div class="p-6 border-b">
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-bold text-gray-800">Recent Bookings</h2>
                <a href="../bookings/list.php?search=<?php echo $customer['name']; ?>" class="text-blue-600 hover:text-blue-700 text-sm font-semibold">View All</a>
            </div>
        </div>
        <div class="p-6">
            <?php if ($recentBookings->num_rows > 0): ?>
                <div class="space-y-4">
                    <?php while ($booking = $recentBookings->fetch_assoc()): ?>
                        <div class="flex items-center justify-between pb-3 border-b">
                            <div>
                                <p class="font-semibold text-gray-800"><?php echo $booking['booking_number']; ?></p>
                                <p class="text-xs text-gray-500">Booker: <?php echo $booking['booker_name']; ?></p>
                                <p class="text-xs text-gray-500"><?php echo formatDate($booking['booking_date']); ?></p>
                            </div>
                            <div class="text-right">
                                <p class="font-bold text-gray-800"><?php echo formatCurrency($booking['total_amount']); ?></p>
                                <?php
                                $statusColors = [
                                    'pending' => 'bg-yellow-100 text-yellow-700',
                                    'confirmed' => 'bg-blue-100 text-blue-700',
                                    'invoiced' => 'bg-green-100 text-green-700',
                                    'cancelled' => 'bg-red-100 text-red-700'
                                ];
                                $statusClass = $statusColors[$booking['status']] ?? 'bg-gray-100 text-gray-700';
                                ?>
                                <span class="text-xs px-2 py-1 rounded <?php echo $statusClass; ?>">
                                    <?php echo ucfirst($booking['status']); ?>
                                </span>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <p class="text-gray-500 text-center py-8">No bookings yet</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
include '../../includes/footer.php';
?>