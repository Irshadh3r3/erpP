<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';

requireLogin();

$conn = getDBConnection();
$pageTitle = 'Purchase Order Details';

$purchaseId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($purchaseId <= 0) {
    $_SESSION['error_message'] = 'Invalid purchase ID';
    header('Location: list.php');
    exit;
}

// Get purchase details
$purchaseQuery = "SELECT p.*, 
              s.name as supplier_name,
              s.supplier_code,
              s.phone as supplier_phone,
              s.address as supplier_address,
              s.contact_person,
              u.full_name as created_by
              FROM purchases p
              JOIN suppliers s ON p.supplier_id = s.id
              LEFT JOIN users u ON p.user_id = u.id
              WHERE p.id = $purchaseId";
$purchaseResult = $conn->query($purchaseQuery);

if ($purchaseResult->num_rows === 0) {
    $_SESSION['error_message'] = 'Purchase order not found';
    header('Location: list.php');
    exit;
}

$purchase = $purchaseResult->fetch_assoc();

// Get purchase items
$itemsQuery = "SELECT pi.*, p.name as product_name, p.sku, p.unit 
               FROM purchase_items pi
               JOIN products p ON pi.product_id = p.id
               WHERE pi.purchase_id = $purchaseId";
$items = $conn->query($itemsQuery);

include '../../includes/header.php';
?>

<style>
@media print {
    @page {
        size: A4 landscape;
        margin: 10mm;
    }
    body * {
        visibility: hidden;
    }
    #printSection, #printSection * {
        visibility: visible;
    }
    #printSection {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
    }
    .no-print {
        display: none !important;
    }
    .purchase-container {
        max-width: none;
        width: 100%;
        margin: 0;
        padding: 12px 25px;
        border: 1px solid #000;
        box-sizing: border-box;
    }
    .purchase-header {
        padding-bottom: 6px !important;
        margin-bottom: 8px !important;
    }
    .company-name {
        font-size: 18px !important;
        line-height: 1.1 !important;
    }
    .company-details {
        font-size: 9px !important;
        line-height: 1.3 !important;
    }
    .purchase-title {
        font-size: 12px !important;
        margin-bottom: 6px !important;
    }
    .info-section {
        font-size: 10px !important;
        margin-bottom: 6px !important;
        line-height: 1.3 !important;
    }
    .purchase-table {
        font-size: 10px !important;
        margin: 8px 0 !important;
    }
    .purchase-table th,
    .purchase-table td {
        padding: 4px 6px !important;
    }
    .total-box {
        padding: 6px 10px !important;
        margin-top: 8px !important;
    }
    .total-left {
        font-size: 9px !important;
    }
    .total-right {
        font-size: 10px !important;
    }
    .purchase-footer {
        font-size: 9px !important;
        padding-top: 6px !important;
        margin-top: 8px !important;
    }
}

.purchase-container {
    max-width: 900px;
    margin: 20px auto;
    background: #fff;
    padding: 30px 40px;
    border: 2px solid #000;
}
.purchase-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    border-bottom: 2px solid #000;
    padding-bottom: 10px;
    margin-bottom: 15px;
}
.company-name {
    font-size: 28px;
    font-weight: 700;
    text-transform: uppercase;
    line-height: 1.2;
}
.company-details {
    text-align: right;
    font-size: 12px;
    line-height: 1.5;
}
.purchase-title {
    font-size: 16px;
    font-weight: 700;
    margin-bottom: 10px;
    text-transform: uppercase;
}
.info-section {
    margin-bottom: 10px;
    font-size: 13px;
    line-height: 1.5;
}
.purchase-table {
    width: 100%;
    border-collapse: collapse;
    margin: 15px 0;
    font-size: 13px;
}
.purchase-table th {
    background: #f5f5f5;
    border: 1px solid #000;
    padding: 8px;
    text-align: left;
    font-weight: 700;
}
.purchase-table td {
    border: 1px solid #000;
    padding: 8px;
}
.total-box {
    background: #e0e0e0;
    padding: 10px 15px;
    margin-top: 10px;
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    font-weight: 700;
}
.total-left {
    font-size: 11px;
    font-weight: normal;
}
.total-right {
    text-align: right;
    font-size: 13px;
}
.purchase-footer {
    border-top: 1px solid #000;
    margin-top: 15px;
    padding-top: 10px;
    font-size: 12px;
    display: flex;
    justify-content: space-between;
}
</style>

<!-- Print Button (No Print) -->
<div class="no-print mb-4 text-center">
    <div class="bg-yellow-100 border border-yellow-400 text-yellow-800 px-4 py-2 rounded mb-3 inline-block">
        <strong>⚠️ Important:</strong> Please set your printer to <strong>Landscape</strong> orientation before printing!
    </div>
    <br>
    <a href="list.php" class="inline-block bg-gray-500 hover:bg-gray-600 text-white px-6 py-2 rounded-lg font-semibold mr-2">
        ← Back to List
    </a>
    <?php if (canEdit($conn, $_SESSION['role'], 'purchases')): ?>
        <a href="edit.php?id=<?php echo $purchaseId; ?>" class="inline-block bg-yellow-600 hover:bg-yellow-700 text-white px-6 py-2 rounded-lg font-semibold mr-2">✏️ Edit</a>
    <?php endif; ?>
    <?php if (canDelete($conn, $_SESSION['role'], 'purchases')): ?>
        <a href="delete.php?id=<?php echo $purchaseId; ?>" onclick="return confirm('Are you sure you want to delete this purchase? This will adjust inventory.');" class="inline-block bg-red-600 hover:bg-red-700 text-white px-6 py-2 rounded-lg font-semibold mr-2">🗑️ Delete</a>
    <?php endif; ?>
    <button onclick="printPurchase()" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg font-semibold">
        🖨️ Print Purchase Order (Landscape)
    </button>
</div>

<!-- Purchase Container -->
<div class="purchase-container" id="printSection">
    <!-- Header -->
    <div class="purchase-header">
        <div class="company-name">
            <?php echo strtoupper(APP_NAME); ?><br>
            <span style="font-size: 14px; font-weight: 400;">Distribution & Wholesale</span>
        </div>
        <div class="company-details">
            <strong>Your Company Address Here</strong><br>
            City, Postal Code<br>
            TAX ID: XXXX-XXXX-XXXX<br>
            Phone: +92-XXX-XXXXXXX<br>
            Email: info@company.com
        </div>
    </div>

    <!-- Purchase Title & Info -->
    <div class="purchase-title">PURCHASE ORDER</div>
    
    <div class="info-section">
        <strong>PURCHASE #:</strong> <?php echo $purchase['purchase_number']; ?>
        &nbsp;&nbsp;<strong>DATE:</strong> <?php echo formatDate($purchase['purchase_date']); ?>
        &nbsp;&nbsp;<strong>STATUS:</strong> 
        <?php
        $statusColors = [
            'paid' => 'color: #059669;',
            'partial' => 'color: #d97706;',
            'unpaid' => 'color: #dc2626;'
        ];
        $statusColor = $statusColors[$purchase['payment_status']] ?? 'color: #666;';
        ?>
        <span style="<?php echo $statusColor; ?> font-weight: bold;">
            <?php echo strtoupper($purchase['payment_status']); ?>
        </span>
    </div>

    <div class="info-section">
        <strong>SUPPLIER:</strong> <?php echo htmlspecialchars($purchase['supplier_name']); ?> (<?php echo $purchase['supplier_code']; ?>)<br>
        <?php if ($purchase['contact_person']): ?>
            <strong>CONTACT:</strong> <?php echo htmlspecialchars($purchase['contact_person']); ?>
            &nbsp;&nbsp;
        <?php endif; ?>
        <?php if ($purchase['supplier_phone']): ?>
            <strong>PHONE:</strong> <?php echo htmlspecialchars($purchase['supplier_phone']); ?><br>
        <?php endif; ?>
        <?php if ($purchase['supplier_address']): ?>
            <strong>ADDRESS:</strong> <?php echo htmlspecialchars($purchase['supplier_address']); ?>
        <?php endif; ?>
    </div>

    <!-- Products Table -->
    <table class="purchase-table">
        <thead>
            <tr>
                <th style="width: 50%;">DESCRIPTION</th>
                <th style="width: 15%; text-align: center;">QTY</th>
                <th style="width: 17%; text-align: right;">UNIT PRICE</th>
                <th style="width: 18%; text-align: right;">AMOUNT</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $items->data_seek(0);
            while ($item = $items->fetch_assoc()): 
            ?>
                <tr>
                    <td>
                        <strong><?php echo htmlspecialchars($item['product_name']); ?></strong><br>
                        <small style="color: #666;">SKU: <?php echo $item['sku']; ?></small>
                    </td>
                    <td style="text-align: center;"><?php echo $item['quantity']; ?> <?php echo $item['unit']; ?></td>
                    <td style="text-align: right;"><?php echo number_format($item['unit_price'], 2); ?> Rs.</td>
                    <td style="text-align: right;"><strong><?php echo number_format($item['subtotal'], 2); ?> Rs.</strong></td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>

    <!-- Total Box -->
    <div class="total-box">
        <div class="total-left" id="printDateTime"></div>
        <div class="total-right">
            <div>NET AMOUNT: <?php echo number_format($purchase['subtotal'], 2); ?> Rs.</div>
            <?php if ($purchase['discount'] > 0): ?>
                <div>DISCOUNT: -<?php echo number_format($purchase['discount'], 2); ?> Rs.</div>
            <?php endif; ?>
            <?php if ($purchase['tax'] > 0): ?>
                <div>TAX: <?php echo number_format($purchase['tax'], 2); ?> Rs.</div>
            <?php endif; ?>
            <div style="font-size: 14px; margin-top: 4px; border-top: 1px solid #000; padding-top: 4px;">
                <strong>TOTAL: <?php echo number_format($purchase['total_amount'], 2); ?> Rs.</strong>
            </div>
            <div style="margin-top: 8px; font-size: 11px;">
                <div>PAID: <?php echo number_format($purchase['paid_amount'], 2); ?> Rs.</div>
                <?php if ($purchase['total_amount'] - $purchase['paid_amount'] > 0): ?>
                    <div style="color: #c00;">BALANCE DUE: <?php echo number_format($purchase['total_amount'] - $purchase['paid_amount'], 2); ?> Rs.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Notes -->
    <?php if ($purchase['notes']): ?>
        <div style="margin-top: 10px; font-size: 11px; padding: 8px; background: #f9f9f9; border: 1px solid #ddd;">
            <strong>NOTES:</strong> <?php echo nl2br(htmlspecialchars($purchase['notes'])); ?>
        </div>
    <?php endif; ?>

    <!-- Footer -->
    <div class="purchase-footer">
        <div>
            <strong>Created by:</strong> <?php echo $purchase['created_by'] ?? 'System'; ?><br>
            <small>Date: <?php echo formatDate($purchase['created_at']); ?></small>
        </div>
        <div style="text-align: right;">
            <strong>EMAIL:</strong> info@company.com<br>
            <strong>WEBSITE:</strong> www.company.com
        </div>
    </div>

    <div style="text-align: center; margin-top: 10px; font-size: 9px; color: #666;">
        This is a computer-generated purchase order.
    </div>
</div>

<script>
function printPurchase() {
    const now = new Date();
    const formatted = now.toLocaleString('en-PK', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: false
    });
    document.getElementById("printDateTime").textContent = "Print Date: " + formatted;
    window.print();
}
</script>

<?php
include '../../includes/footer.php';
?>