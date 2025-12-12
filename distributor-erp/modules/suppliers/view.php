<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';

requireLogin();

$conn = getDBConnection();
$pageTitle = 'Supplier Details';

$supplierId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($supplierId <= 0) {
    $_SESSION['error_message'] = 'Invalid supplier ID';
    header('Location: list.php');
    exit;
}

// Get supplier details
$supplierQuery = "SELECT * FROM suppliers WHERE id = $supplierId";
$supplierResult = $conn->query($supplierQuery);

if ($supplierResult->num_rows === 0) {
    $_SESSION['error_message'] = 'Supplier not found';
    header('Location: list.php');
    exit;
}

$supplier = $supplierResult->fetch_assoc();

// Get supplier stats
$statsQuery = "SELECT 
               COUNT(*) as total_purchases,
               COALESCE(SUM(total_amount), 0) as total_amount,
               COALESCE(SUM(paid_amount), 0) as total_paid,
               COALESCE(SUM(total_amount - paid_amount), 0) as outstanding_balance
               FROM purchases
               WHERE supplier_id = $supplierId";
$stats = $conn->query($statsQuery)->fetch_assoc();

// Get recent purchases
$purchasesQuery = "SELECT *
                   FROM purchases
                   WHERE supplier_id = $supplierId
                   ORDER BY created_at DESC
                   LIMIT 10";
$recentPurchases = $conn->query($purchasesQuery);

include '../../includes/header.php';
?>

<!-- Page Header -->
<div class="mb-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-bold text-gray-800"><?php echo $supplier['name']; ?></h1>
            <p class="text-gray-600"><?php echo $supplier['supplier_code']; ?></p>
        </div>
        <div class="flex gap-2">
            <a href="list.php" class="px-6 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 font-semibold transition">
                Back to List
            </a>
            <a href="edit.php?id=<?php echo $supplier['id']; ?>" 
               class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg font-semibold transition">
                Edit Supplier
            </a>
            <a href="../purchases/add.php?supplier_id=<?php echo $supplier['id']; ?>" 
               class="bg-green-600 hover:bg-green-700 text-white px-6 py-2 rounded-lg font-semibold transition">
                + New Purchase
            </a>
        </div>
    </div>
</div>

<!-- Supplier Info Card -->
<div class="bg-white rounded-lg shadow p-6 mb-6">
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
        <div>
            <p class="text-sm text-gray-500 mb-1">Contact Person</p>
            <p class="font-semibold"><?php echo $supplier['contact_person'] ?: 'N/A'; ?></p>
        </div>
        <div>
            <p class="text-sm text-gray-500 mb-1">Phone</p>
            <p class="font-semibold"><?php echo $supplier['phone']; ?></p>
        </div>
        <div>
            <p class="text-sm text-gray-500 mb-1">Email</p>
            <p class="font-semibold"><?php echo $supplier['email'] ?: 'N/A'; ?></p>
        </div>
        <div>
            <p class="text-sm text-gray-500 mb-1">City</p>
            <p class="font-semibold"><?php echo $supplier['city'] ?: 'N/A'; ?></p>
        </div>
    </div>
    
    <?php if ($supplier['address']): ?>
        <div class="mt-4 pt-4 border-t">
            <p class="text-sm text-gray-500 mb-1">Address</p>
            <p class="font-semibold"><?php echo $supplier['address']; ?></p>
        </div>
    <?php endif; ?>
</div>

<!-- Stats Cards -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-gray-500 text-sm">Total Purchases</p>
                <h3 class="text-3xl font-bold text-gray-800 mt-1"><?php echo $stats['total_purchases']; ?></h3>
            </div>
            <div class="bg-blue-100 p-3 rounded-full">
                <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path>
                </svg>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-gray-500 text-sm">Total Amount</p>
                <h3 class="text-2xl font-bold text-gray-800 mt-1"><?php echo formatCurrency($stats['total_amount']); ?></h3>
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

<!-- Recent Purchases -->
<div class="bg-white rounded-lg shadow">
    <div class="p-6 border-b">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-bold text-gray-800">Recent Purchases</h2>
            <a href="../purchases/list.php?supplier_id=<?php echo $supplier['id']; ?>" class="text-blue-600 hover:text-blue-700 text-sm font-semibold">View All</a>
        </div>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Purchase #</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Paid</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php if ($recentPurchases->num_rows > 0): ?>
                    <?php while ($purchase = $recentPurchases->fetch_assoc()): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4">
                                <a href="../purchases/view.php?id=<?php echo $purchase['id']; ?>" class="text-blue-600 hover:text-blue-800 font-mono text-sm">
                                    <?php echo $purchase['purchase_number']; ?>
                                </a>
                            </td>
                            <td class="px-6 py-4 text-sm"><?php echo formatDate($purchase['purchase_date']); ?></td>
                            <td class="px-6 py-4 text-right font-semibold"><?php echo formatCurrency($purchase['total_amount']); ?></td>
                            <td class="px-6 py-4 text-right text-green-600 font-semibold"><?php echo formatCurrency($purchase['paid_amount']); ?></td>
                            <td class="px-6 py-4">
                                <?php
                                $statusColors = [
                                    'paid' => 'bg-green-100 text-green-700',
                                    'partial' => 'bg-yellow-100 text-yellow-700',
                                    'unpaid' => 'bg-red-100 text-red-700'
                                ];
                                $statusClass = $statusColors[$purchase['payment_status']] ?? 'bg-gray-100 text-gray-700';
                                ?>
                                <span class="<?php echo $statusClass; ?> px-2 py-1 rounded text-xs font-semibold">
                                    <?php echo ucfirst($purchase['payment_status']); ?>
                                </span>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" class="px-6 py-8 text-center text-gray-500">No purchases yet</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
include '../../includes/footer.php';
?>