<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';
requireLogin();

$conn = getDBConnection();

$bookingId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($bookingId <= 0) {
    die("Invalid Invoice ID");
}

// Get booking data with related sales info
$query = "SELECT b.*, 
                 bk.name AS booker_name,
                 bk.booker_code,
                 bk.commission_percentage,
                 u.full_name AS created_by,
                 s.id AS sale_id,
                 s.invoice_number,
                 s.paid_amount,
                 s.payment_status,
                 s.sale_date,
                 s.total_amount AS sale_total,
                 s.payment_method
          FROM bookings b
          JOIN bookers bk ON b.booker_id = bk.id
          LEFT JOIN users u ON b.user_id = u.id
          LEFT JOIN sales s ON b.invoice_id = s.id
          WHERE b.id = $bookingId";

$booking = $conn->query($query)->fetch_assoc();

// Get booking items
$itemsQuery = "SELECT bi.*, p.name AS product_name, p.variety, p.sku, p.unit, c.name AS category_name
               FROM booking_items bi
               JOIN products p ON bi.product_id = p.id
               LEFT JOIN categories c ON p.category_id = c.id
               WHERE bi.booking_id = $bookingId";

$items = $conn->query($itemsQuery);

// Get payments for this invoice if it exists
$payments = [];
$totalPaid = 0;
if ($booking['sale_id']) {
    $paymentsQuery = "SELECT * FROM payments WHERE invoice_id = " . $booking['sale_id'] . " ORDER BY payment_date";
    $paymentsResult = $conn->query($paymentsQuery);
    while ($payment = $paymentsResult->fetch_assoc()) {
        $payments[] = $payment;
        $totalPaid += $payment['payment_amount'];
    }
}

// Calculate commission
$commission = ($booking['total_amount'] * $booking['commission_percentage']) / 100;

// Use sales data if available, otherwise use booking data
$invoiceNumber = $booking['invoice_number'] ?? $booking['booking_number'];
$invoiceDate = $booking['sale_date'] ?? $booking['booking_date'];
$totalAmount = $booking['sale_total'] ?? $booking['total_amount'];
$paidAmount = $booking['paid_amount'] ?? $totalPaid;
$paymentStatus = $booking['payment_status'] ?? 'unpaid';
$balanceDue = $totalAmount - $paidAmount;

// Get discount percentage from sales if available
$discountPercentage = 0;
$discountAmount = 0;
if ($booking['sale_id']) {
    $saleQuery = "SELECT discount, subtotal FROM sales WHERE id = " . $booking['sale_id'];
    $saleResult = $conn->query($saleQuery);
    if ($saleResult && $saleResult->num_rows > 0) {
        $saleData = $saleResult->fetch_assoc();
        $discountAmount = $saleData['discount'];
        if ($saleData['subtotal'] > 0) {
            $discountPercentage = ($discountAmount / $saleData['subtotal']) * 100;
        }
    }
}

// Generate a short ID for display
$shortId = substr($invoiceNumber, -6);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>INV-<?= $shortId; ?></title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Roboto+Mono:wght@400;500;700&family=Roboto:wght@300;400;500&display=swap');
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Roboto', sans-serif;
            font-size: 9px;
            background: #f5f5f5;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
            line-height: 1.3;
        }
        
        .invoice-card {
            width: 197mm;
            height: auto;
            min-height: 105mm;
            margin: 2mm auto;
            padding: 3mm;
            background: white;
            border-radius: 1mm;
            box-shadow: 0 1mm 2mm rgba(0,0,0,0.1);
            page-break-inside: avoid;
            border: 0.5mm solid #000;
        }
        
        /* Header Section */
        .invoice-header {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2mm;
            border-bottom: 0.5mm solid #333;
            padding-bottom: 2mm;
            margin-bottom: 2mm;
        }
        
        .company-info h1 {
            font-size: 19px;
            color: #2c3e50;
            font-weight: 700;
            margin-bottom: 0.5mm;
        }
        
        .company-info p {
            font-size: 7px;
            color: #666;
        }
        
        .invoice-meta {
            text-align: right;
        }
        
        .invoice-id {
            font-family: 'Roboto Mono', monospace;
            font-size: 10px;
            font-weight: 700;
            color: #2c3e50;
            background: #f8f9fa;
            padding: 1mm 2mm;
            border-radius: 1mm;
            display: inline-block;
            border: 0.2mm solid #e9ecef;
        }
        
        .invoice-date {
            font-size: 8px;
            margin-top: 1mm;
            color: #666;
        }
        
        /* Customer Section (retained from previous fix) */
.customer-section {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 2mm;
    margin-bottom: 3mm;
    padding: 2mm;
    border: 0.2mm solid #333;
    border-radius: 1mm;
}

/* Headers */
.customer-info h3,
.booker-info h3 {
    font-size: 8px;
    color: #2c3e50;
    margin-bottom: 0.5mm;
    font-weight: 600;
    line-height: 1;
}

/* Detail Containers */
.customer-details,
.booker-details {
    display: flex;
    flex-direction: column;
}

/* Paragraph for each line of data */
.customer-details p,
.booker-details p {
    font-size: 10px; /* Base font size for the entire line */
    margin: 0;
    padding: 0;
    line-height: 1.3;
    display: flex; /* Key: Makes the P tag a flexible container for label and value */
    align-items: flex-start;
    /* Removed overflow/text-overflow/white-space from P, let the child elements handle it */
}

/* Booker details slightly smaller if necessary */


/* The Label (Name, Phone, Addr) */
.label {
    /* Set color for the label text */
    color: #666; 
    font-weight: 500;
    
    /* Important: Set a fixed width to align all value texts */
    width: 20mm; /* Increased width significantly (from 20px) to accommodate "Name:" plus space */
    
    /* Ensure the label itself is always on one line */
    white-space: nowrap;
    flex-shrink: 0; /* Prevents the label from shrinking */
    
    /* ADDED: Add guaranteed spacing and the colon after the label text itself */
    padding-right: 1mm;
    
    /* Ensure text alignment within the fixed width */
    text-align: left; 
}

.label:not(.no-colon)::after {
    /* Adds the colon and a space after the label text */
    content: ":\00a0"; 
}


/* The Value Text (abc12, x, hello world) */
.value-text {
    /* Key: Takes up the remaining available space */
    flex: 1; 
    
    /* Set color for the value text */
    color: #000;
    
    /* Prevents the value text from wrapping */
    white-space: nowrap; 
    
    /* Truncates the text if it's too long */
    overflow: hidden; 
    text-overflow: ellipsis; 
}
        
        /* Items Table */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 8px;
            margin-bottom: 3mm;
        }
        
        .items-table {
            border: 0.3mm solid #333;
        }

        .items-table th {
            background: #2c3e50;
            color: white;
            padding: 1.2mm;
            text-align: left;
            font-weight: 500;
            border: 0.3mm solid #000;
        }

        .items-table td {
            padding: 1.2mm;
            border: 0.2mm solid #333;
            vertical-align: top;
        }        .items-table tr:nth-child(even) {
            background: #f8f9fa;
        }
        
        .product-cell {

            max-width: 35mm;
        }
        
        .product-name {
            font-size: 12px;
            font-weight: 500;
            color: #333;
            margin-bottom: -1mm;
        }
        
        .product-sku {
            font-size: 9px;
            font-size: 7px;
            color: #666;
            font-family: 'Roboto Mono', monospace;
        }
        
        .qty-cell,
        .price-cell,
        .amount-cell {
            font-size: 12px;
            text-align: center;
            font-family: 'Roboto Mono', monospace;
        }
        
        .amount-cell {
            font-weight: 600;
            color: #2c3e50;
        }
        
        /* Payment History */
        .payment-history {
            margin-top: 2mm;
            margin-bottom: 2mm;
            font-size: 8px;
        }
        
        .payment-history h4 {
            font-size: 8px;
            color: #2c3e50;
            margin-bottom: 1mm;
            font-weight: 600;
        }
        
        .payment-row {
            display: grid;
            grid-template-columns: 3fr 2fr 2fr 2fr;
            gap: 1mm;
            padding: 1mm;
            border-bottom: 0.1mm dashed #ddd;
        }
        
        .payment-row.header {
            font-weight: 600;
            background: #f8f9fa;
        }
        
        /* Summary Section */
        .summary-section {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 3mm;
            margin-top: 2mm;
            border-top: 0.3mm solid #333;
            padding-top: 2mm;
        }
        
        .payment-status-box {
            padding: 2mm;
            border-radius: 1mm;
            font-size: 11px;
        }
        
        .status-unpaid {
            background: #ffeaa7;
            border: 0.2mm solid #fdcb6e;
            color: #d63031;
        }
        
        .status-partial {
            background: #a3d9ff;
            border: 0.2mm solid #3498db;
            color: #2980b9;
        }
        
        .status-paid {
            background: #a3e4d7;
            border: 0.2mm solid #16a085;
            color: #16a085;
        }
        
        .totals-box {
            background: #f8f9fa;
            padding: 2mm;
            border-radius: 1mm;
            border: 0.2mm solid #e9ecef;
        }
        
        .total-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 1mm;
            padding-bottom: 1mm;
            border-bottom: 0.1mm dashed #dee2e6;
        }
        
        .grand-total {
            font-weight: 700;
            font-size: 9px;
            color: #2c3e50;
            border-bottom: 0.2mm solid #adb5bd;
            margin-top: 1mm;
        }
        
        .balance-due {
            color: #e74c3c;
            font-weight: 700;
        }
        
        /* Footer Section */
        .invoice-footer {
            margin-top: 3mm;
            padding-top: 2mm;
            border-top: 0.3mm dashed #ddd;
            font-size: 7px;
            color: #666;
            text-align: center;
        }
        
        .footer-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2mm;
            margin-bottom: 1mm;
        }
        
        .commission-box {
            background: #fff8e1;
            padding: 1.5mm;
            border-radius: 1mm;
            border: 0.2mm solid #ffd54f;
        }
        
        .commission-label {
            font-weight: 600;
            color: #b06b00;
        }
        
        .commission-value {
            font-family: 'Roboto Mono', monospace;
            font-weight: 700;
            float: right;
            color: #b06b00;
        }
        
        .barcode {
            font-family: 'Roboto Mono', monospace;
            font-size: 9px;
            letter-spacing: 1px;
            background: #f8f9fa;
            padding: 1mm;
            border-radius: 1mm;
            text-align: center;
            border: 0.2mm dashed #adb5bd;
        }
        
        /* Print Controls */
        .print-controls {
            text-align: center;
            padding: 3mm;
            background: white;
            margin: 2mm auto;
            width: 95mm;
            border-radius: 1mm;
            box-shadow: 0 1mm 2mm rgba(0,0,0,0.1);
        }
        
        .btn {
            padding: 2mm 4mm;
            border: none;
            border-radius: 1mm;
            font-size: 9px;
            font-weight: 500;
            cursor: pointer;
            margin: 0 1mm;
            transition: all 0.2s;
        }
        
        .btn-primary {
            background: #2c3e50;
            color: white;
        }
        
        .btn-primary:hover {
            background: #1a2530;
        }
        
        .btn-secondary {
            background: #3498db;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #2980b9;
        }
        
        /* Print Styles */
        @media print {
            .print-controls {
                display: none;
            }
            * {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                border-color: #000 !important;
            }
            body {
                background: white;
                margin: 0;
                padding: 0;
            }
            .invoice-card {
                border: 0.5mm solid #000 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .items-table {
                border: 0.3mm solid #333 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .items-table th {
                border: 0.3mm solid #000 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .items-table td {
                border: 0.2mm solid #333 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .customer-section {
                border: 0.2mm solid #333 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .summary-section {
                border-top: 0.3mm solid #333 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            }
            
            .invoice-card {
                box-shadow: none;
                border: 0.2mm solid #ccc;
                margin: 0;
                page-break-after: always;
            }
            
            /* Print 2 per page */
            @page {
                size: A4;
                margin: 5mm;
            }
            
            @page :first {
                margin-top: 5mm;
            }
        }
        
        /* Mobile View */
        @media screen and (max-width: 100mm) {
            .print-controls {
                width: 95mm;
            }
        }
    </style>
</head>
<body>

<div class="invoice-card">
    <!-- Header -->
    <div class="invoice-header">
        <div class="company-info">
            <h1>Khan Traders</h1>
            <p>Wholesale Distribution System</p>
            <p>Saif Khan : +92-323-2968868</p>
        </div>
        <div class="invoice-meta">
            <div class="invoice-id"><?= $invoiceNumber; ?></div>
            <div class="invoice-date"><?= date("d M Y", strtotime($invoiceDate)); ?></div>
        </div>
    </div>
    
    <!-- Customer & Booker Info -->
    <div class="customer-section">
        <div class="customer-info">
            <h3>CUSTOMER</h3>
            <div class="customer-details">
                <p><span class="label">Name:</span> <?= htmlspecialchars($booking['customer_name']); ?></p>
                <p><span class="label">Phone:</span> <?= htmlspecialchars($booking['customer_phone']); ?></p>
                <p><span class="label">Addr:</span> <?= htmlspecialchars(substr($booking['customer_address'], 0, 30)) . (strlen($booking['customer_address']) > 30 ? '...' : ''); ?></p>
            </div>
        </div>
        <div class="booker-info">
            <h3>BOOKER</h3>
            <div class="booker-details">
                <p><?= htmlspecialchars($booking['booker_name']); ?></p>
                <p>Code: <?= $booking['booker_code']; ?></p>
                <p>By: <?= htmlspecialchars($booking['created_by']); ?></p>
            </div>
        </div>
    </div>
    
    <!-- Items Table -->
    <table class="items-table">
        <thead>
            <tr>
                <th class="product-cell">PRODUCT</th>
                <th class="qty-cell">QTY</th>
                <th class="price-cell">PRICE</th>
                <th class="amount-cell">AMOUNT</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $items->data_seek(0);
            while($row = $items->fetch_assoc()): 
            ?>
            <tr>
                <td class="product-cell">
                    <div class="product-name"><?= htmlspecialchars($row['product_name']. (!empty($row['variety']) ? ' - ' . $row['variety'] : '')); ?></div>
                    <!-- <div class="product-sku">SKU: <?= htmlspecialchars($row['sku']); ?></div> -->
                </td>
                <td class="qty-cell"><?= $row['quantity']; ?> <?= htmlspecialchars($row['unit']); ?></td>
                <td class="price-cell"><?= formatCurrency($row['unit_price']); ?></td>
                <td class="amount-cell"><?= formatCurrency($row['subtotal']); ?></td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
    
    <!-- Payment History (if any) -->
    <?php if (!empty($payments)): ?>
    <div class="payment-history">
        <h4>PAYMENT HISTORY:</h4>
        <div class="payment-row header">
            <span>Date</span>
            <span>Method</span>
            <span>Ref No.</span>
            <span>Amount</span>
        </div>
        <?php foreach ($payments as $payment): ?>
        <div class="payment-row">
            <span><?= date("d/m/Y", strtotime($payment['payment_date'])); ?></span>
            <span><?= ucfirst($payment['payment_method']); ?></span>
            <span><?= $payment['reference_number'] ?: 'N/A'; ?></span>
            <span><?= formatCurrency($payment['payment_amount']); ?></span>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    
    <!-- Summary Section -->
    <div class="summary-section">
        <div class="payment-status-box status-<?= $paymentStatus; ?>">
            <strong>PAYMENT STATUS:</strong><br>
            <?php 
            switch($paymentStatus) {
                case 'paid':
                    echo 'PAID IN FULL ✓';
                    break;
                case 'partial':
                    echo 'PARTIALLY PAID';
                    break;
                default:
                    echo 'PENDING PAYMENT';
            }
            ?><br>
            <span style="font-size: 7px;">
                <?= $balanceDue <= 0 ? 
                    'Paid on: ' . date("d/m/Y", strtotime($invoiceDate)) : 
                    'Due: ' . date("d M Y", strtotime($invoiceDate . ' +7 days')); 
                ?>
            </span>
        </div>
        
        <div class="totals-box">
            <div class="total-row">
                <span>Subtotal:</span>
                <span><?= formatCurrency($totalAmount); ?></span>
            </div>
            <?php if ($discountAmount > 0): ?>
            <div class="total-row">
                <span>Discount (<?= number_format($discountPercentage, 2); ?>%):</span>
                <span>-<?= formatCurrency($discountAmount); ?></span>
            </div>
            <?php endif; ?>
            <!-- <div class="total-row">
                <span>Tax (0%):</span>
                <span><?= formatCurrency(0); ?></span>
            </div> -->
            <!-- <div class="total-row">
                <span>Paid:</span>
                <span><?= formatCurrency($paidAmount); ?></span>
            </div> -->
            <div class="total-row grand-total">
                <span>TOTAL:</span>
                <span><?= formatCurrency($totalAmount); ?></span>
            </div>
            <div class="total-row balance-due">
                <span>BALANCE DUE:</span>
                <span><?= formatCurrency($balanceDue); ?></span>
            </div>
        </div>
    </div>
    
    <!-- Footer -->
    <!-- <div class="invoice-footer">
        <div class="footer-grid">
            <div class="commission-box">
                <span class="commission-label">Commission (<?= $booking['commission_percentage']; ?>%):</span>
                <span class="commission-value"><?= formatCurrency($commission); ?></span>
                <div style="clear: both;"></div>
            </div>
            <div class="barcode">
                <?= $invoiceNumber; ?>
            </div>
        </div>
        <div style="margin-top: 2mm;">
            <strong>Thank you for your business!</strong>
        </div>
    </div> -->
</div>

<div class="print-controls">
    <button class="btn btn-primary" onclick="window.print()">🖨️ Print Invoice</button>
</div>

<script>
function printDuplex() {
    // Clone the invoice for duplex printing
    const invoice = document.querySelector('.invoice-card');
    const clone = invoice.cloneNode(true);
    
    // Add page break class
    clone.style.pageBreakAfter = 'always';
    
    // Insert after original
    invoice.parentNode.insertBefore(clone, invoice.nextSibling);
    
    // Trigger print
    window.print();
    
    // Remove clone after printing (with delay for print dialog)
    setTimeout(() => {
        if (clone && clone.parentNode) {
            clone.parentNode.removeChild(clone);
        }
    }, 1000);
}

function downloadPDF() {
    // This would typically use a server-side PDF generation library
    alert('PDF generation would be implemented server-side. For now, use "Print to PDF" in your browser.');
}

// Auto-add page breaks for multiple invoices
document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('multi')) {
        const count = parseInt(urlParams.get('multi')) || 2;
        const invoice = document.querySelector('.invoice-card');
        
        for (let i = 1; i < count; i++) {
            const clone = invoice.cloneNode(true);
            clone.style.pageBreakBefore = 'always';
            invoice.parentNode.insertBefore(clone, invoice.nextSibling);
        }
    }
});

// Auto-print if requested
if (window.location.search.includes('autoprint')) {
    window.print();
}
</script>

</body>
</html>