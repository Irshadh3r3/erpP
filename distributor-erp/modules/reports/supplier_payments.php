<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';

requireLogin();

$conn = getDBConnection();
$pageTitle = 'Supplier Payments Report';

// Date filters
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-01');
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-t');
$supplier_id = isset($_GET['supplier_id']) ? (int)$_GET['supplier_id'] : 0;

// Build query
$where = "p.purchase_date BETWEEN '$date_from' AND '$date_to'";
if ($supplier_id > 0) {
    $where .= " AND p.supplier_id = $supplier_id";
}

// Get supplier payables
$query = "SELECT 
          s.id,
          s.supplier_code,
          s.name as supplier_name,
          s.contact_person,
          s.phone,
          s.city,
          s.current_balance,
          COUNT(p.id) as total_orders,
          SUM(p.total_amount) as total_purchases,
          SUM(p.paid_amount) as total_paid,
          SUM(p.total_amount - p.paid_amount) as outstanding,
          COUNT(CASE WHEN p.payment_status = 'paid' THEN 1 END) as paid_orders,
          COUNT(CASE WHEN p.payment_status = 'partial' THEN 1 END) as partial_orders,
          COUNT(CASE WHEN p.payment_status = 'unpaid' THEN 1 END) as unpaid_orders,
          MIN(p.purchase_date) as first_purchase,
          MAX(p.purchase_date) as last_purchase
          FROM suppliers s
          JOIN purchases p ON s.id = p.supplier_id
          WHERE $where
          GROUP BY s.id
          ORDER BY outstanding DESC";

$suppliers = $conn->query($query);

// Get suppliers for filter
$suppliersQuery = "SELECT id, name, supplier_code FROM suppliers WHERE is_active = 1 ORDER BY name ASC";
$suppliersFilter = $conn->query($suppliersQuery);

// Get overall totals
$totalsQuery = "SELECT 
                COUNT(DISTINCT s.id) as total_suppliers,
                COUNT(p.id) as total_orders,
                SUM(p.total_amount) as total_purchases,
                SUM(p.paid_amount) as total_paid,
                SUM(p.total_amount - p.paid_amount) as outstanding
                FROM suppliers s
                JOIN purchases p ON s.id = p.supplier_id
                WHERE $where";
$totals = $conn->query($totalsQuery)->fetch_assoc();

// Payment status summary
$statusQuery = "SELECT 
                payment_status,
                COUNT(*) as count,
                SUM(total_amount) as amount,
                SUM(paid_amount) as paid
                FROM purchases
                WHERE purchase_date BETWEEN '$date_from' AND '$date_to'
                GROUP BY payment_status";
$statusData = $conn->query($statusQuery);

include '../../includes/header.php';
?>

<!-- Page Header -->
<div class="mb-6">
    <h1 class="text-3xl font-bold text-gray-800">Supplier Payments Report</h1>
    <p class="text-gray-600">Track payments and outstanding balances to suppliers</p>
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
            <label class="block text-sm font-semibold text-gray-700 mb-2">Supplier</label>
            <select name="supplier_id" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="0">All Suppliers</option>
                <?php 
                $suppliersFilter->data_seek(0);
                while ($supp = $suppliersFilter->fetch_assoc()): 
                ?>
                    <option value="<?php echo $supp['id']; ?>" <?php echo $supplier_id == $supp['id'] ? 'selected' : ''; ?>>
                        <?php echo $supp['name']; ?> (<?php echo $supp['supplier_code']; ?>)
                    </option>
                <?php endwhile; ?>
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
    <div class="bg-white rounded-lg shadow p-6">
        <p class="text-gray-500 text-sm">Total Suppliers</p>
        <h3 class="text-3xl font-bold text-gray-800"><?php echo $totals['total_suppliers']; ?></h3>
        <p class="text-xs text-gray-500 mt-1">Active suppliers</p>
    </div>
    
    <div class="bg-white rounded-lg shadow p-6">
        <p class="text-gray-500 text-sm">Total Purchases</p>
        <h3 class="text-2xl font-bold text-red-600"><?php echo formatCurrency($totals['total_purchases']); ?></h3>
        <p class="text-xs text-gray-500 mt-1"><?php echo number_format($totals['total_orders']); ?> orders</p>
    </div>
    
    <div class="bg-white rounded-lg shadow p-6">
        <p class="text-gray-500 text-sm">Amount Paid</p>
        <h3 class="text-2xl font-bold text-green-600"><?php echo formatCurrency($totals['total_paid']); ?></h3>
        <p class="text-xs text-gray-500 mt-1">
            <?php echo $totals['total_purchases'] > 0 ? number_format(($totals['total_paid'] / $totals['total_purchases']) * 100, 1) : 0; ?>% paid
        </p>
    </div>
    
    <div class="bg-white rounded-lg shadow p-6">
        <p class="text-gray-500 text-sm">Outstanding</p>
        <h3 class="text-2xl font-bold text-orange-600"><?php echo formatCurrency($totals['outstanding']); ?></h3>
        <p class="text-xs text-gray-500 mt-1">Payables due</p>
    </div>
</div>

<!-- Payment Status Summary -->
<div class="bg-white rounded-lg shadow p-6 mb-6">
    <h3 class="text-lg font-bold text-gray-800 mb-4">Payment Status Summary</h3>
    <div class="grid grid-cols-3 gap-4">
        <?php 
        $statusData->data_seek(0);
        $statusColors = [
            'paid' => 'bg-green-100 text-green-700 border-green-300',
            'partial' => 'bg-yellow-100 text-yellow-700 border-yellow-300',
            'unpaid' => 'bg-red-100 text-red-700 border-red-300'
        ];
        while ($status = $statusData->fetch_assoc()): 
            $colorClass = $statusColors[$status['payment_status']];
            $paymentPercent = $status['amount'] > 0 ? ($status['paid'] / $status['amount']) * 100 : 0;
        ?>
            <div class="<?php echo $colorClass; ?> border-2 rounded-lg p-4">
                <p class="text-sm font-semibold uppercase mb-2"><?php echo $status['payment_status']; ?></p>
                <div class="space-y-2">
                    <div class="flex justify-between text-sm">
                        <span>Orders:</span>
                        <span class="font-bold"><?php echo $status['count']; ?></span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span>Total:</span>
                        <span class="font-bold"><?php echo formatCurrency($status['amount']); ?></span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span>Paid:</span>
                        <span class="font-bold"><?php echo formatCurrency($status['paid']); ?></span>
                    </div>
                    <div class="w-full bg-white rounded-full h-2 mt-2">
                        <div class="bg-current h-2 rounded-full opacity-60" style="width: <?php echo $paymentPercent; ?>%"></div>
                    </div>
                    <p class="text-xs text-center"><?php echo number_format($paymentPercent, 1); ?>% paid</p>
                </div>
            </div>
        <?php endwhile; ?>
    </div>
</div>

<!-- Supplier Details Table -->
<div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="p-6 border-b">
        <h3 class="text-lg font-bold text-gray-800">Supplier Payment Details</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">#</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Supplier</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Contact</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Orders</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total Purchases</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Amount Paid</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Outstanding</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Payment Rate</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Last Purchase</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php if ($suppliers->num_rows > 0): ?>
                    <?php 
                    $rank = 1;
                    while ($supplier = $suppliers->fetch_assoc()): 
                        $paymentRate = $supplier['total_purchases'] > 0 ? 
                                      ($supplier['total_paid'] / $supplier['total_purchases']) * 100 : 0;
                    ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 text-sm font-bold"><?php echo $rank++; ?></td>
                            <td class="px-6 py-4">
                                <div class="text-sm font-medium text-gray-900"><?php echo $supplier['supplier_name']; ?></div>
                                <div class="text-xs text-gray-500"><?php echo $supplier['supplier_code']; ?></div>
                            </td>
                            <td class="px-6 py-4 text-sm">
                                <div><?php echo $supplier['contact_person'] ?: 'N/A'; ?></div>
                                <div class="text-xs text-gray-500"><?php echo $supplier['phone']; ?></div>
                            </td>
                            <td class="px-6 py-4 text-sm text-right font-semibold">
                                <?php echo $supplier['total_orders']; ?>
                                <div class="text-xs text-gray-500">
                                    <?php echo $supplier['paid_orders']; ?>P / <?php echo $supplier['unpaid_orders']; ?>U
                                </div>
                            </td>
                            <td class="px-6 py-4 text-sm text-right font-bold text-red-600">
                                <?php echo formatCurrency($supplier['total_purchases']); ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-right font-bold text-green-600">
                                <?php echo formatCurrency($supplier['total_paid']); ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-right font-bold text-orange-600">
                                <?php echo formatCurrency($supplier['outstanding']); ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-right">
                                <div class="w-full bg-gray-200 rounded-full h-2 mb-1">
                                    <div class="bg-green-500 h-2 rounded-full" style="width: <?php echo $paymentRate; ?>%"></div>
                                </div>
                                <span class="text-xs font-semibold"><?php echo number_format($paymentRate, 1); ?>%</span>
                            </td>
                            <td class="px-6 py-4 text-sm"><?php echo formatDate($supplier['last_purchase']); ?></td>
                        </tr>
                    <?php endwhile; ?>
                    
                    <!-- Totals Row -->
                    <tr class="bg-gray-100 font-bold">
                        <td colspan="3" class="px-6 py-4 text-sm">TOTAL</td>
                        <td class="px-6 py-4 text-sm text-right"><?php echo number_format($totals['total_orders']); ?></td>
                        <td class="px-6 py-4 text-sm text-right text-red-600">
                            <?php echo formatCurrency($totals['total_purchases']); ?>
                        </td>
                        <td class="px-6 py-4 text-sm text-right text-green-600">
                            <?php echo formatCurrency($totals['total_paid']); ?>
                        </td>
                        <td class="px-6 py-4 text-sm text-right text-orange-600">
                            <?php echo formatCurrency($totals['outstanding']); ?>
                        </td>
                        <td colspan="2"></td>
                    </tr>
                <?php else: ?>
                    <tr>
                        <td colspan="9" class="px-6 py-8 text-center text-gray-500">
                            No supplier payment data found for the selected period
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
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