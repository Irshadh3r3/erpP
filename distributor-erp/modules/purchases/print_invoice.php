<?php
// distributor-erp/modules/purchases/print_invoice.php
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

// Check permission - adjust based on your actual function signature
// If hasPermission() needs more arguments, you might need to:
// 1. Check the actual function signature in functions.php
// 2. Or use a simpler check

$conn = getDBConnection();

$purchaseId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($purchaseId <= 0) {
    die("Invalid Purchase ID");
}

// Get purchase details
$query = "SELECT p.*, 
                 s.name as supplier_name,
                 s.supplier_code,
                 s.contact_person,
                 s.phone as supplier_phone,
                 s.email as supplier_email,
                 s.address as supplier_address,
                 u.full_name as created_by
          FROM purchases p
          JOIN suppliers s ON p.supplier_id = s.id
          LEFT JOIN users u ON p.user_id = u.id
          WHERE p.id = $purchaseId";

$purchase = $conn->query($query)->fetch_assoc();

if (!$purchase) {
    die("Purchase not found");
}

// Get purchase items
$itemsQuery = "SELECT pi.*, pr.name as product_name, pr.sku, pr.unit, pr.variety
               FROM purchase_items pi
               JOIN products pr ON pi.product_id = pr.id
               WHERE pi.purchase_id = $purchaseId";

$items = $conn->query($itemsQuery);

// Calculate totals
$subtotal = $purchase['subtotal'];
$discount = $purchase['discount'] ?? 0;
$tax = $purchase['tax'] ?? 0;
$totalAmount = $purchase['total_amount'];
$paidAmount = $purchase['paid_amount'] ?? 0;
$balanceDue = $totalAmount - $paidAmount;

// Payment status
$paymentStatus = $purchase['payment_status'] ?? 'unpaid';
$statusClass = '';
switch($paymentStatus) {
    case 'paid': $statusClass = 'status-paid'; break;
    case 'partial': $statusClass = 'status-partial'; break;
    default: $statusClass = 'status-unpaid';
}

// Generate short ID
$shortId = substr($purchase['purchase_number'], -6);
?>

<!DOCTYPE html>
<html lang="en"><head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PURCHASE - <?= $purchase['purchase_number']; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Roboto+Mono:wght@400;500;700&family=Roboto:wght@300;400;500&display=swap');
        
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #3498db;
            --success-color: #27ae60;
            --warning-color: #f39c12;
            --accent-color: #e74c3c;
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
            min-height: 125mm;
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
        
        /* Supplier Section */
        .supplier-section {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 4mm;
            margin-bottom: 3mm;
            padding: 2mm;
            border: 0.2mm solid #333;
            border-radius: 1mm;
        }
        
        .supplier-info h3,
        .purchase-info h3 {
            font-size: 9px;
            color: #2c3e50;
            margin-bottom: 1mm;
            font-weight: 600;
        }
        
        .supplier-details p,
        .purchase-details p {
            font-size: 9px;
            margin-bottom: 1.5mm;
            line-height: 1.5;
        }
        
        .label {
            color: #666;
            font-weight: 500;
            display: inline-block;
            width: 45px;
        }
        
        /* Items Table */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            border: 0.3mm solid #333;
            font-size: 8px;
            margin-bottom: 3mm;
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
            font-size: 9px;
            font-weight: 500;
            color: #333;
            margin-bottom: 0.3mm;
        }
        
        .product-sku {

            font-size: 9px;
            color: #666;
            font-family: 'Roboto Mono', monospace;
        }
        
        .qty-cell,
        .price-cell,
        .amount-cell {
            font-size: 9px;
            text-align: center;
            font-family: 'Roboto Mono', monospace;
        }
        
        .amount-cell {
            font-weight: 600;
            color: #2c3e50;
        }
        
        /* Summary Section */
        .summary-section {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 3mm;
            margin-top: 2mm;
        }
        
        .payment-status-box {
            padding: 3mm;
            border-radius: 1mm;
            font-size: 10bpx;
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
        
        .btn-close {
            background: #95a5a6;
            color: white;
        }
        
        @media print {
            .action-buttons {
                display: none;
            }
            
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
        
        /* Notes Section */
        .notes-section {
            margin-top: 2mm;
            padding: 2mm;
            background: #f9f9f9;
            border-radius: 1mm;
            border: 0.2mm dashed #ddd;
            font-size: 8px;
        }
        
        .notes-section h4 {
            color: #2c3e50;
            margin-bottom: 1mm;
            font-size: 8px;
            font-weight: 600;
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
            .supplier-section {
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



<!-- Invoice Card -->
<div class="invoice-card">
    <!-- Header -->
    <div class="invoice-header">
        <div class="company-info">
            <h1>Khans Trader</h1>
            <p>Wholesale Distribution System</p>
            <p style="font-size: 7px; margin-top: 1mm;">+92-323-2968868</p>
        </div>
        <div class="invoice-meta">
            <div class="invoice-id"><?= $purchase['purchase_number']; ?></div>
            <div class="invoice-date"><?= date("d M Y", strtotime($purchase['purchase_date'])); ?></div>
        </div>
    </div>
    
    <!-- Supplier & Purchase Info -->
    <div class="supplier-section">
        <div class="supplier-info">
            <h3>SUPPLIER</h3>
            <div class="supplier-details">
                <p><span class="label">Name:</span> <?= htmlspecialchars($purchase['supplier_name']); ?></p>
                <?php if ($purchase['contact_person']): ?>
                <p><span class="label">Contact:</span> <?= htmlspecialchars($purchase['contact_person']); ?></p>
                <?php endif; ?>
                <p><span class="label">Phone:</span> <?= htmlspecialchars($purchase['supplier_phone']); ?></p>
                <p><span class="label">Code:</span> <?= $purchase['supplier_code']; ?></p>
            </div>
        </div>
        <div class="purchase-info">
            <h3>PURCHASE INFO</h3>
            <div class="purchase-details">
                <p><span class="label">Created By:</span> <?= htmlspecialchars($purchase['created_by']); ?></p>
                <p><span class="label">Status:</span> <?= ucfirst($paymentStatus); ?></p>
                <p><span class="label">Payment:</span> <?= ucfirst(str_replace('_', ' ', $paymentStatus)); ?></p>
                <?php if ($purchase['supplier_email']): ?>
                <p><span class="label">Email:</span> <?= htmlspecialchars($purchase['supplier_email']); ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <?php if ($purchase['supplier_address']): ?>
    <div class="supplier-details" style="margin-bottom: 2mm;">
        <p><span class="label">Address:</span> <?= htmlspecialchars(substr($purchase['supplier_address'], 0, 40)) . (strlen($purchase['supplier_address']) > 40 ? '...' : ''); ?></p>
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
                    <div class="product-name"><?= htmlspecialchars($row['product_name']); ?><?= $row['variety'] ? ' - ' . htmlspecialchars($row['variety']) : ''; ?></div>
                    <div class="product-sku">SKU: <?= htmlspecialchars($row['sku']); ?></div>
                </td>
                <td class="qty-cell"><?= $row['quantity']; ?> <?= htmlspecialchars($row['unit']); ?></td>
                <td class="price-cell"><?= formatCurrency($row['unit_price']); ?></td>
                <td class="amount-cell"><?= formatCurrency($row['subtotal']); ?></td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
    
    <!-- Summary Section -->
    <div class="summary-section">
        <div class="payment-status-box <?= $statusClass; ?>">
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
                    'Paid on: ' . date("d/m/Y", strtotime($purchase['purchase_date'])) : 
                    'Due: ' . date("d M Y", strtotime($purchase['purchase_date'] . ' +30 days')); 
                ?>
            </span>
        </div>
        
        <div class="totals-box">
            <div class="total-row">
                <span>Subtotal:</span>
                <span><?= formatCurrency($subtotal); ?></span>
            </div>
            <?php if ($discount > 0): ?>
            <div class="total-row">
                <span>Discount:</span>
                <span>-<?= formatCurrency($discount); ?></span>
            </div>
            <?php endif; ?>
            <?php if ($tax > 0): ?>
            <div class="total-row">
                <span>Tax:</span>
                <span><?= formatCurrency($tax); ?></span>
            </div>
            <?php endif; ?>
            <div class="total-row grand-total">
                <span>TOTAL:</span>
                <span><?= formatCurrency($totalAmount); ?></span>
            </div>
            <!-- <div class="total-row">
                <span>Paid:</span>
                <span><?= formatCurrency($paidAmount); ?></span>
            </div> -->
            <div class="total-row balance-due">
                <span>BALANCE DUE:</span>
                <span><?= formatCurrency($balanceDue); ?></span>
            </div>
        </div>
    </div>
    
    <!-- Notes Section -->
    <?php if ($purchase['notes']): ?>
    <div class="notes-section">
        <h4>NOTES:</h4>
        <p><?= nl2br(htmlspecialchars($purchase['notes'])); ?></p>
    </div>
    <?php endif; ?>
    
    <!-- Footer -->
    <div class="invoice-footer">
        <div class="footer-grid">
            <div></div>
            <!-- <div class="barcode">
                <?= $purchase['purchase_number']; ?>
            </div> -->
        </div>
        <div style="margin-top: 2mm;">
            <strong>PURCHASE ORDER</strong>
        </div>
    </div>
</div>

<!-- Action Buttons (Visible in browser, hidden when printing) -->
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