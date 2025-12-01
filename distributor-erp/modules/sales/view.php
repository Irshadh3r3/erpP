<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';

requireLogin();

$conn = getDBConnection();
$pageTitle = 'Invoice Details';

$saleId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($saleId <= 0) {
    $_SESSION['error_message'] = 'Invalid invoice ID';
    header('Location: list.php');
    exit;
}

// Get sale details
$saleQuery = "SELECT s.*, 
              c.name as customer_name,
              c.customer_code,
              c.phone as customer_phone,
              c.address as customer_address,
              c.business_name,
              u.full_name as created_by
              FROM sales s
              JOIN customers c ON s.customer_id = c.id
              LEFT JOIN users u ON s.user_id = u.id
              WHERE s.id = $saleId";
$saleResult = $conn->query($saleQuery);

if ($saleResult->num_rows === 0) {
    $_SESSION['error_message'] = 'Invoice not found';
    header('Location: list.php');
    exit;
}

$sale = $saleResult->fetch_assoc();

// Get sale items
$itemsQuery = "SELECT si.*, p.name as product_name, p.sku, p.unit 
               FROM sales_items si
               JOIN products p ON si.product_id = p.id
               WHERE si.sale_id = $saleId";
$items = $conn->query($itemsQuery);

// Get related booking if exists
$bookingQuery = "SELECT b.*, bk.name as booker_name, bk.booker_code, bk.commission_percentage 
                 FROM bookings b
                 JOIN bookers bk ON b.booker_id = bk.id
                 WHERE b.invoice_id = $saleId";
$bookingResult = $conn->query($bookingQuery);
$booking = $bookingResult->num_rows > 0 ? $bookingResult->fetch_assoc() : null;

// Calculate commission if booking exists
$commission = 0;
if ($booking) {
    $commission = ($sale['total_amount'] * $booking['commission_percentage']) / 100;
}

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
    .invoice-container {
        max-width: none;
        width: 100%;
        margin: 0;
        padding: 12px 25px;
        border: 1px solid #000;
        box-sizing: border-box;
    }
    .invoice-header {
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
    .invoice-title {
        font-size: 12px !important;
        margin-bottom: 6px !important;
    }
    .info-section {
        font-size: 10px !important;
        margin-bottom: 6px !important;
        line-height: 1.3 !important;
    }
    .invoice-table {
        font-size: 10px !important;
        margin: 8px 0 !important;
    }
    .invoice-table th,
    .invoice-table td {
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
    .total-right > div {
        margin-bottom: 2px !important;
    }
    .invoice-footer {
        font-size: 9px !important;
        padding-top: 6px !important;
        margin-top: 8px !important;
    }
    .payment-info {
        margin-top: 8px !important;
        font-size: 9px !important;
    }
    .notes-section {
        margin-top: 6px !important;
        padding: 6px !important;
        font-size: 9px !important;
    }
    .final-note {
        margin-top: 8px !important;
        font-size: 8px !important;
    }
}

.invoice-container {
    max-width: 900px;
    margin: 20px auto;
    background: #fff;
    padding: 30px 40px;
    border: 2px solid #000;
}
.invoice-header {
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
.invoice-title {
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
.invoice-table {
    width: 100%;
    border-collapse: collapse;
    margin: 15px 0;
    font-size: 13px;
}
.invoice-table th {
    background: #f5f5f5;
    border: 1px solid #000;
    padding: 8px;
    text-align: left;
    font-weight: 700;
}
.invoice-table td {
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
.invoice-footer {
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
    <a href="list.php" class="inline-block bg-gray-500 hover:bg-gray-600 text-white px-6 py-2 rounded-lg font-semibold mr-2">
        ← Back to List
    </a>
    <button onclick="printInvoice()" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg font-semibold">
        🖨️ Print Invoice
    </button>
</div>

<!-- Invoice Container -->
<div class="invoice-container" id="printSection">
    <!-- Header -->
    <div class="invoice-header">
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

    <!-- Invoice Title & Customer Info -->
    <div class="invoice-title">INVOICE / TAX RECEIPT</div>
    
    <div class="info-section">
        <strong>CUSTOMER DETAILS:</strong> <?php echo htmlspecialchars($sale['customer_name']); ?><br>
        <?php if ($sale['business_name']): ?>
            <strong>BUSINESS:</strong> <?php echo htmlspecialchars($sale['business_name']); ?><br>
        <?php endif; ?>
        <?php if ($sale['customer_phone']): ?>
            <strong>PHONE:</strong> <?php echo htmlspecialchars($sale['customer_phone']); ?><br>
        <?php endif; ?>
        <?php if ($sale['customer_address']): ?>
            <strong>ADDRESS:</strong> <?php echo htmlspecialchars($sale['customer_address']); ?><br>
        <?php endif; ?>
        <strong>INVOICE #:</strong> <?php echo $sale['invoice_number']; ?><br>
        <strong>DATE:</strong> <?php echo formatDate($sale['sale_date']); ?>
        <?php if ($booking): ?>
            <br><strong>BOOKER:</strong> <?php echo $booking['booker_name']; ?> (<?php echo $booking['booker_code']; ?>)
        <?php endif; ?>
    </div>

    <!-- Products Table -->
    <table class="invoice-table">
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
            <div>NET AMOUNT: <?php echo number_format($sale['subtotal'], 2); ?> Rs.</div>
            <?php if ($sale['discount'] > 0): ?>
                <div>DISCOUNT: -<?php echo number_format($sale['discount'], 2); ?> Rs.</div>
            <?php endif; ?>
            <?php if ($sale['tax'] > 0): ?>
                <div>TAX: <?php echo number_format($sale['tax'], 2); ?> Rs.</div>
            <?php endif; ?>
            <div style="font-size: 16px; margin-top: 5px; border-top: 1px solid #000; padding-top: 5px;">
                <strong>TOTAL: <?php echo number_format($sale['total_amount'], 2); ?> Rs.</strong>
            </div>
            <div style="margin-top: 10px; font-size: 13px;">
                <div>PAID: <?php echo number_format($sale['paid_amount'], 2); ?> Rs.</div>
                <?php if ($sale['total_amount'] - $sale['paid_amount'] > 0): ?>
                    <div style="color: #c00;">BALANCE DUE: <?php echo number_format($sale['total_amount'] - $sale['paid_amount'], 2); ?> Rs.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Payment Info -->
    <div class="payment-info" style="margin-top: 15px; font-size: 13px;">
        <strong>PAYMENT METHOD:</strong> <?php echo ucfirst(str_replace('_', ' ', $sale['payment_method'])); ?> &nbsp;&nbsp;
        <strong>PAYMENT STATUS:</strong> 
        <span style="<?php echo $sale['payment_status'] === 'paid' ? 'color: green;' : 'color: red;'; ?>">
            <?php echo strtoupper($sale['payment_status']); ?>
        </span>
    </div>

    <?php if ($sale['notes']): ?>
        <div class="notes-section" style="margin-top: 15px; font-size: 12px; padding: 10px; background: #f9f9f9; border: 1px solid #ddd;">
            <strong>NOTES:</strong> <?php echo nl2br(htmlspecialchars($sale['notes'])); ?>
        </div>
    <?php endif; ?>

    <!-- Booker Commission (No Print) -->
    <?php if ($booking): ?>
        <div class="no-print" style="margin-top: 15px; padding: 10px; background: #fff3cd; border: 1px solid #ffc107;">
            <strong>BOOKER COMMISSION (<?php echo $booking['commission_percentage']; ?>%):</strong> 
            <?php echo formatCurrency($commission); ?>
        </div>
    <?php endif; ?>

    <!-- Footer -->
    <div class="invoice-footer">
        <div>
            <strong>THANK YOU FOR YOUR BUSINESS!</strong><br>
            <small>For any queries, contact us</small>
        </div>
        <div style="text-align: right;">
            <strong>EMAIL:</strong> info@company.com<br>
            <strong>WEBSITE:</strong> www.company.com
        </div>
    </div>

    <div class="final-note" style="text-align: center; margin-top: 15px; font-size: 11px; color: #666;">
        This is a computer-generated invoice. No signature required.
    </div>
</div>

<script>
function printInvoice() {
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