<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';

requireLogin();

$conn = getDBConnection();
$pageTitle = 'Commission Report';

// Date filters
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-01');
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-t');
$booker_id = isset($_GET['booker_id']) ? (int)$_GET['booker_id'] : 0;
$payment_status = isset($_GET['payment_status']) ? clean($_GET['payment_status']) : 'all';

// Build query
$where = "b.booking_date BETWEEN '$date_from' AND '$date_to' AND b.status = 'invoiced'";
if ($booker_id > 0) {
    $where .= " AND b.booker_id = $booker_id";
}

// Get commission details
$query = "SELECT 
          bk.id as booker_id,
          bk.booker_code,
          bk.name as booker_name,
          bk.phone,
          bk.area,
          bk.commission_percentage,
          COUNT(DISTINCT b.id) as total_bookings,
          SUM(b.total_amount) as total_sales,
          SUM(b.total_amount * bk.commission_percentage / 100) as total_commission,
          SUM(CASE WHEN s.payment_status = 'paid' 
              THEN (b.total_amount * bk.commission_percentage / 100) 
              ELSE 0 END) as payable_commission,
          SUM(CASE WHEN s.payment_status != 'paid' 
              THEN (b.total_amount * bk.commission_percentage / 100) 
              ELSE 0 END) as pending_commission,
          COUNT(DISTINCT CASE WHEN s.payment_status = 'paid' THEN b.id END) as paid_invoices,
          COUNT(DISTINCT CASE WHEN s.payment_status != 'paid' THEN b.id END) as unpaid_invoices
          FROM bookers bk
          LEFT JOIN bookings b ON bk.id = b.booker_id AND $where
          LEFT JOIN sales s ON b.invoice_id = s.id
          WHERE bk.is_active = 1 AND b.id IS NOT NULL
          GROUP BY bk.id
          ORDER BY total_commission DESC";

$bookers = $conn->query($query);

// Get bookers for filter
$bookersFilterQuery = "SELECT id, name, booker_code FROM bookers WHERE is_active = 1 ORDER BY name ASC";
$bookersFilter = $conn->query($bookersFilterQuery);

// Get overall totals
$totalsQuery = "SELECT 
                COUNT(DISTINCT b.id) as total_bookings,
                SUM(b.total_amount) as total_sales,
                SUM(b.total_amount * bk.commission_percentage / 100) as total_commission,
                SUM(CASE WHEN s.payment_status = 'paid' 
                    THEN (b.total_amount * bk.commission_percentage / 100) 
                    ELSE 0 END) as payable_commission,
                SUM(CASE WHEN s.payment_status != 'paid' 
                    THEN (b.total_amount * bk.commission_percentage / 100) 
                    ELSE 0 END) as pending_commission
                FROM bookings b
                JOIN bookers bk ON b.booker_id = bk.id
                LEFT JOIN sales s ON b.invoice_id = s.id
                WHERE $where";
$totals = $conn->query($totalsQuery)->fetch_assoc();

include '../../includes/header.php';
?>

<!-- Page Header -->
<div class="mb-6">
    <h1 class="text-3xl font-bold text-gray-800">Commission Report</h1>
    <p class="text-gray-600">Track and manage booker commissions</p>
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
            <label class="block text-sm font-semibold text-gray-700 mb-2">Booker</label>
            <select name="booker_id" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="0">All Bookers</option>
                <?php 
                $bookersFilter->data_seek(0);
                while ($bk = $bookersFilter->fetch_assoc()): 
                ?>
                    <option value="<?php echo $bk['id']; ?>" <?php echo $booker_id == $bk['id'] ? 'selected' : ''; ?>>
                        <?php echo $bk['name']; ?> (<?php echo $bk['booker_code']; ?>)
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="flex items-end gap-2">
            <button type="submit" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-semibold transition">
                Generate
            </button>
        </div>
    </form>
</div>

<!-- Summary Cards -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
    <div class="bg-white rounded-lg shadow p-6">
        <p class="text-gray-500 text-sm">Total Sales</p>
        <h3 class="text-2xl font-bold text-blue-600"><?php echo formatCurrency($totals['total_sales']); ?></h3>
        <p class="text-xs text-gray-500 mt-1"><?php echo number_format($totals['total_bookings']); ?> invoiced bookings</p>
    </div>
    
    <div class="bg-white rounded-lg shadow p-6">
        <p class="text-gray-500 text-sm">Total Commission</p>
        <h3 class="text-2xl font-bold text-purple-600"><?php echo formatCurrency($totals['total_commission']); ?></h3>
        <p class="text-xs text-gray-500 mt-1">Earned from sales</p>
    </div>
    
    <div class="bg-white rounded-lg shadow p-6">
        <p class="text-gray-500 text-sm">Payable Commission</p>
        <h3 class="text-2xl font-bold text-green-600"><?php echo formatCurrency($totals['payable_commission']); ?></h3>
        <p class="text-xs text-gray-500 mt-1">Fully paid invoices</p>
    </div>
    
    <div class="bg-white rounded-lg shadow p-6">
        <p class="text-gray-500 text-sm">Pending Commission</p>
        <h3 class="text-2xl font-bold text-orange-600"><?php echo formatCurrency($totals['pending_commission']); ?></h3>
        <p class="text-xs text-gray-500 mt-1">Awaiting payment collection</p>
    </div>
</div>

<!-- Commission Breakdown -->
<div class="bg-white rounded-lg shadow overflow-hidden mb-6">
    <div class="p-6 border-b">
        <h3 class="text-lg font-bold text-gray-800">Commission Breakdown by Booker</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Booker</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Area</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Rate</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Bookings</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total Sales</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total Commission</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Payable</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Pending</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Payment Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php if ($bookers->num_rows > 0): ?>
                    <?php while ($booker = $bookers->fetch_assoc()): ?>
                        <?php
                        $paymentPercent = $booker['total_commission'] > 0 ? 
                                         (($booker['payable_commission'] / $booker['total_commission']) * 100) : 0;
                        ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4">
                                <div class="text-sm font-medium text-gray-900">
                                    <a href="../bookers/view.php?id=<?php echo $booker['booker_id']; ?>" 
                                       class="text-blue-600 hover:text-blue-800">
                                        <?php echo $booker['booker_name']; ?>
                                    </a>
                                </div>
                                <div class="text-xs text-gray-500"><?php echo $booker['booker_code']; ?></div>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-600">
                                <?php echo $booker['area'] ?: 'N/A'; ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-right">
                                <span class="bg-purple-100 text-purple-700 px-2 py-1 rounded text-xs font-semibold">
                                    <?php echo $booker['commission_percentage']; ?>%
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm text-right font-semibold">
                                <?php echo number_format($booker['total_bookings']); ?>
                                <div class="text-xs text-gray-500">
                                    <?php echo $booker['paid_invoices']; ?> paid / <?php echo $booker['unpaid_invoices']; ?> unpaid
                                </div>
                            </td>
                            <td class="px-6 py-4 text-sm text-right font-bold text-blue-600">
                                <?php echo formatCurrency($booker['total_sales']); ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-right font-bold text-purple-600">
                                <?php echo formatCurrency($booker['total_commission']); ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-right font-bold text-green-600">
                                <?php echo formatCurrency($booker['payable_commission']); ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-right font-semibold text-orange-600">
                                <?php echo formatCurrency($booker['pending_commission']); ?>
                            </td>
                            <td class="px-6 py-4">
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <div class="bg-green-500 h-2 rounded-full" style="width: <?php echo $paymentPercent; ?>%"></div>
                                </div>
                                <p class="text-xs text-gray-600 mt-1"><?php echo number_format($paymentPercent, 1); ?>% collected</p>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    
                    <!-- Totals Row -->
                    <tr class="bg-gray-100 font-bold">
                        <td colspan="3" class="px-6 py-4 text-sm">TOTAL</td>
                        <td class="px-6 py-4 text-sm text-right"><?php echo number_format($totals['total_bookings']); ?></td>
                        <td class="px-6 py-4 text-sm text-right text-blue-600">
                            <?php echo formatCurrency($totals['total_sales']); ?>
                        </td>
                        <td class="px-6 py-4 text-sm text-right text-purple-600">
                            <?php echo formatCurrency($totals['total_commission']); ?>
                        </td>
                        <td class="px-6 py-4 text-sm text-right text-green-600">
                            <?php echo formatCurrency($totals['payable_commission']); ?>
                        </td>
                        <td class="px-6 py-4 text-sm text-right text-orange-600">
                            <?php echo formatCurrency($totals['pending_commission']); ?>
                        </td>
                        <td></td>
                    </tr>
                <?php else: ?>
                    <tr>
                        <td colspan="9" class="px-6 py-8 text-center text-gray-500">
                            No commission data found for the selected period
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Commission Payment Instructions -->
<div class="bg-blue-50 border border-blue-200 rounded-lg p-6">
    <h3 class="text-lg font-bold text-blue-900 mb-3">💡 Commission Payment Guidelines</h3>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm text-blue-800">
        <div>
            <p class="font-semibold mb-2">✓ Payable Commission:</p>
            <ul class="list-disc list-inside space-y-1 ml-2">
                <li>Invoices with payment status = "Paid"</li>
                <li>Full payment has been received from customer</li>
                <li>Commission can be disbursed to booker</li>
            </ul>
        </div>
        <div>
            <p class="font-semibold mb-2">⏳ Pending Commission:</p>
            <ul class="list-disc list-inside space-y-1 ml-2">
                <li>Invoices with payment status = "Partial" or "Unpaid"</li>
                <li>Awaiting customer payment collection</li>
                <li>Commission will be payable once collected</li>
            </ul>
        </div>
    </div>
    <div class="mt-4 p-3 bg-yellow-50 border border-yellow-300 rounded">
        <p class="text-sm text-yellow-800">
            <strong>Note:</strong> Commission is calculated on invoiced bookings only. Pending/confirmed bookings are not included until they're converted to invoices.
        </p>
    </div>
</div>


<?php
include '../../includes/footer.php';
?>