<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';

requireLogin();

$conn = getDBConnection();

$saleId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($saleId <= 0) {
    die("Invalid Invoice ID");
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
    die("Invoice not found");
}

$sale = $saleResult->fetch_assoc();

// Get sale items
$itemsQuery = "SELECT si.*, p.name as product_name, p.variety, p.sku, p.unit, cat.name as category_name
               FROM sales_items si
               JOIN products p ON si.product_id = p.id
               LEFT JOIN categories cat ON p.category_id = cat.id
               WHERE si.sale_id = $saleId";
$items = $conn->query($itemsQuery);

// Get related booking if exists
$bookingQuery = "SELECT b.*, bk.name as booker_name, bk.booker_code, bk.commission_percentage 
                 FROM bookings b
                 JOIN bookers bk ON b.booker_id = bk.id
                 WHERE b.invoice_id = $saleId";
$bookingResult = $conn->query($bookingQuery);
$booking = $bookingResult->num_rows > 0 ? $bookingResult->fetch_assoc() : null;

// Get payments for this invoice
$paymentsQuery = "SELECT * FROM payments WHERE invoice_id = $saleId ORDER BY payment_date";
$paymentsResult = $conn->query($paymentsQuery);
$payments = [];
$totalPaid = 0;
while ($payment = $paymentsResult->fetch_assoc()) {
    $payments[] = $payment;
    $totalPaid += $payment['payment_amount'];
}

// Calculate commission if booking exists
$commission = 0;
if ($booking) {
    $commission = ($sale['total_amount'] * $booking['commission_percentage']) / 100;
}

// Calculate balance
$balanceDue = $sale['total_amount'] - $totalPaid;

// Generate short ID
$shortId = substr($sale['invoice_number'], -6);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>INVOICE - <?= $sale['invoice_number']; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Roboto+Mono:wght@400;500;700&family=Roboto:wght@300;400;500&display=swap');
        
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #3498db;
            --accent-color: #e74c3c;
            --success-color: #27ae60;
            --light-bg: #f8f9fa;
            --border-color: #e0e0e0;
            --text-light: #666;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Roboto', sans-serif;
            font-size: 9px;
            background: #fff;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
            line-height: 1.3;
            padding: 5mm;
        }
        
        .invoice-card {
            width: 197mm;
            height: auto;
            min-height: 105mm;
            margin: 0 auto;
            padding: 3mm;
            background: white;
            border: 0.5mm solid #000;
            page-break-inside: avoid;
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
        
        /* Print Styles */
        @media print {
            body {
                margin: 0;
                padding: 0;
                background: white;
            }
            
            .invoice-card {
                border: none;
                box-shadow: none;
                margin: 0;
                padding: 3mm;
            }
            
            /* Print 2 per page */
            @page {
                size: A4;
                margin: 5mm;
            }
            
            /* Force page break after each invoice when printing multiple */
            .invoice-card {
                page-break-after: always;
            }
            
            /* Remove page break after last invoice */
            .invoice-card:last-child {
                page-break-after: auto;
            }
        }
        
        /* Action buttons for print preview */
        .action-buttons {
            text-align: center;
            margin: 5mm auto;
            width: 95mm;
            display: flex;
            justify-content: center;
            gap: 2mm;
        }
        
        .btn {
            padding: 2mm 4mm;
            border: none;
            border-radius: 1mm;
            font-size: 9px;
            font-weight: 500;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 1mm;
        }
        
        .btn-primary {
            background: #2c3e50;
            color: white;
        }
        
        .btn-secondary {
            background: #3498db;
            color: white;
        }
        
        @media print {
            .action-buttons {
                display: none;
            }
            * {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                border-color: #000 !important;
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
    </style>
</head>
<body>

<!-- Action Buttons (Visible in browser, hidden when printing) -->


<!-- Invoice Card -->
<div class="invoice-card">
    <!-- Header -->
    <div class="invoice-header">
        <div class="company-info">
            <h1>Khan Traders</h1>
            <p>Wholesale Distribution System</p>
            <p style="font-size: 7px; margin-top: 1mm;">Saif Khan : +92-323-2968868 </p>
        </div>
        <div class="invoice-meta">
            <div class="invoice-id"><?= $sale['invoice_number']; ?></div>
            <div class="invoice-date"><?= date("d M Y", strtotime($sale['sale_date'])); ?></div>
        </div>
    </div>
    
    <!-- Customer & Booker Info -->
    <div class="customer-section">
        <div class="customer-info">
            <h3>CUSTOMER</h3>
            <div class="customer-details">
                <p><span class="label">Name:</span> <?= htmlspecialchars($sale['customer_name']); ?></p>
                <?php if ($sale['business_name']): ?>
                <p><span class="label">Business:</span> <?= htmlspecialchars($sale['business_name']); ?></p>
                <?php endif; ?>
                <p><span class="label">Phone:</span> <?= htmlspecialchars($sale['customer_phone']); ?></p>
                <p><span class="label">Addr:</span> <?= htmlspecialchars(substr($booking['customer_address'], 0, 30)) . (strlen($booking['customer_address']) > 30 ? '...' : ''); ?></p>
            </div>
        </div>
        <div class="booking-info">
            <h3>INVOICE INFO</h3>
            <div class="booking-details">
                <p><span class="label">By:</span> <?= htmlspecialchars($sale['created_by']); ?></p>
                <p><span class="label">Method:</span> <?= ucfirst($sale['payment_method']); ?></p>
                <?php if ($booking): ?>
                <p><span class="label">Booker:</span> <?= $booking['booker_name']; ?></p>
                <!-- <p><span class="label">Code:</span> <?= $booking['booker_code']; ?></p> -->
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <?php if ($sale['customer_address']): ?>
    <div class="customer-details" style="margin-bottom: 2mm;">
        <p><span class="label">Address:</span> <?= htmlspecialchars(substr($sale['customer_address'], 0, 40)) . (strlen($sale['customer_address']) > 40 ? '...' : ''); ?></p>
    </div>
    <?php endif; ?>
    
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
        <div class="payment-status-box status-<?= $sale['payment_status']; ?>">
            <strong>PAYMENT STATUS:</strong><br>
            <?php 
            switch($sale['payment_status']) {
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
                    'Paid on: ' . date("d/m/Y", strtotime($sale['sale_date'])) : 
                    'Due: ' . date("d M Y", strtotime($sale['sale_date'] . ' +7 days')); 
                ?>
            </span>
        </div>
        
        <div class="totals-box">
            <div class="total-row">
                <span>Subtotal:</span>
                <span><?= formatCurrency($sale['subtotal']); ?></span>
            </div>
            <?php if ($sale['discount'] > 0): ?>
            <div class="total-row">
                <span>Discount:</span>
                <span>-<?= formatCurrency($sale['discount']); ?></span>
            </div>
            <?php endif; ?>
            <?php if ($sale['tax'] > 0): ?>
            <div class="total-row">
                <span>Tax:</span>
                <span><?= formatCurrency($sale['tax']); ?></span>
            </div>
            <?php endif; ?>
            <div class="total-row grand-total">
                <span>TOTAL:</span>
                <span><?= formatCurrency($sale['total_amount']); ?></span>
            </div>
            <!-- <div class="total-row">
                <span>Paid:</span>
                <span><?= formatCurrency($totalPaid); ?></span>
            </div> -->
            <div class="total-row balance-due">
                <span>BALANCE DUE:</span>
                <span><?= formatCurrency($balanceDue); ?></span>
            </div>
        </div>
    </div>
    
    <!-- Notes Section -->
    <?php if ($sale['notes']): ?>
    <div style="margin-top: 2mm; padding: 2mm; background: #f9f9f9; border-radius: 1mm; border: 0.2mm dashed #ddd; font-size: 8px;">
        <strong>NOTES:</strong> <?= nl2br(htmlspecialchars($sale['notes'])); ?>
    </div>
    <?php endif; ?>
    
    <!-- Footer -->
    <!-- <div class="invoice-footer">
        <div class="footer-grid">
            <?php if ($booking): ?>
            <div class="commission-box">
                <span class="commission-label">Commission (<?= $booking['commission_percentage']; ?>%):</span>
                <span class="commission-value"><?= formatCurrency($commission); ?></span>
                <div style="clear: both;"></div>
            </div>
            <?php else: ?>
            <div></div>
            <?php endif; ?>
            <div class="barcode">
                <?= $sale['invoice_number']; ?>
            </div>
        </div>
        <div style="margin-top: 2mm;">
            <strong>Thank you for your business!</strong> 
            </div>
    </div> -->
</div>
<div class="action-buttons">
    <button class="btn btn-primary" onclick="window.print()">
        <i class="fas fa-print"></i> Print Invoice
    </button>
</div>
<script>
function printTwoPerPage() {
    // Clone the invoice card
    const originalInvoice = document.querySelector('.invoice-card');
    const clone = originalInvoice.cloneNode(true);
    
    // Insert the clone after the original
    originalInvoice.parentNode.insertBefore(clone, originalInvoice.nextSibling);
    
    // Print the page
    window.print();
    
    // Remove the clone after printing (optional)
    setTimeout(() => {
        if (clone && clone.parentNode) {
            clone.parentNode.removeChild(clone);
        }
    }, 100);
}

// Auto-print if URL has print parameter
if (window.location.search.includes('autoprint=true')) {
    window.print();
}

// Auto-close after printing if requested
if (window.location.search.includes('autoclose=true')) {
    window.onafterprint = function() {
        setTimeout(() => {
            window.close();
        }, 500);
    };
}
</script>

</body>
</html>