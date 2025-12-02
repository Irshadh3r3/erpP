<?php
// distributor-erp/modules/payments/payment_receipt.php
require_once '../../config/database.php';
require_once '../../includes/functions.php';

requireLogin();

$conn = getDBConnection();

$paymentId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($paymentId <= 0) {
    die("Invalid Payment ID");
}

// Get payment details
$query = "SELECT 
          p.*,
          s.invoice_number,
          s.sale_date,
          s.total_amount as invoice_total,
          s.paid_amount as total_paid,
          (s.total_amount - s.paid_amount) as remaining_balance,
          c.name as customer_name,
          c.customer_code,
          c.phone as customer_phone,
          c.address as customer_address,
          c.business_name,
          u.full_name as recorded_by
          FROM payments p
          JOIN sales s ON p.invoice_id = s.id
          JOIN customers c ON s.customer_id = c.id
          LEFT JOIN users u ON p.user_id = u.id
          WHERE p.id = $paymentId";

$result = $conn->query($query);

if ($result->num_rows === 0) {
    die("Payment not found");
}

$payment = $result->fetch_assoc();

// Calculate payment number (for this invoice)
$paymentNumberQuery = "SELECT COUNT(*) as payment_number 
                       FROM payments 
                       WHERE invoice_id = {$payment['invoice_id']} 
                       AND id <= $paymentId";
$paymentNumber = $conn->query($paymentNumberQuery)->fetch_assoc()['payment_number'];

// Format dates
$paymentDate = date("d M Y", strtotime($payment['payment_date']));
$receiptDate = date("d M Y H:i", strtotime($payment['created_at']));

// Short ID for display
$shortId = substr(str_pad($paymentId, 6, '0', STR_PAD_LEFT), -6);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RECEIPT - <?= $payment['invoice_number']; ?></title>
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
            --success-light: #d5f4e6;
            --success-dark: #27ae60;
        }
        
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
            padding: 5mm;
        }
        
        .receipt-card {
            width: 197mm;
            height: auto;
            min-height: 125mm;
            margin: 0 auto;
            padding: 3mm;
            background: white;
            border: 0.2mm solid #000;
            page-break-inside: avoid;
        }
        
        /* Header Section */
        .receipt-header {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2mm;
            border-bottom: 0.5mm solid #333;
            padding-bottom: 2mm;
            margin-bottom: 2mm;
        }
        
        .company-info h1 {
            font-size: 11px;
            color: #2c3e50;
            font-weight: 700;
            margin-bottom: 0.5mm;
        }
        
        .company-info p {
            font-size: 7px;
            color: #666;
        }
        
        .receipt-meta {
            text-align: right;
        }
        
        .receipt-id {
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
        
        .receipt-date {
            font-size: 8px;
            margin-top: 1mm;
            color: #666;
        }
        
        /* Customer Section */
        .customer-section {
            margin-bottom: 3mm;
            padding-bottom: 2mm;
            border-bottom: 0.3mm dashed #ddd;
        }
        
        .customer-info h3 {
            font-size: 8px;
            color: #2c3e50;
            margin-bottom: 1mm;
            font-weight: 600;
        }
        
        .customer-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2mm;
            font-size: 8px;
        }
        
        .customer-left p,
        .customer-right p {
            margin-bottom: 0.5mm;
        }
        
        .label {
            color: #666;
            font-weight: 500;
            display: inline-block;
            width: 35px;
        }
        
        /* Amount Highlight */
        .amount-section {
            background: linear-gradient(135deg, var(--success-light) 0%, #a3e4d7 100%);
            padding: 3mm;
            border-radius: 2mm;
            border: 0.3mm solid var(--success-dark);
            text-align: center;
            margin: 3mm 0;
        }
        
        .amount-label {
            font-size: 8px;
            color: var(--success-dark);
            font-weight: 600;
            margin-bottom: 1mm;
        }
        
        .amount-value {
            font-family: 'Roboto Mono', monospace;
            font-size: 18px;
            font-weight: 700;
            color: var(--success-dark);
        }
        
        /* Payment Details */
        .payment-section {
            margin-bottom: 3mm;
            padding-bottom: 2mm;
            border-bottom: 0.3mm dashed #ddd;
        }
        
        .payment-section h3 {
            font-size: 8px;
            color: #2c3e50;
            margin-bottom: 2mm;
            font-weight: 600;
        }
        
        .detail-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 1mm;
            font-size: 8px;
        }
        
        .detail-row {
            padding: 1mm;
            border-bottom: 0.1mm dashed #eee;
        }
        
        .detail-row:last-child {
            border-bottom: none;
        }
        
        .detail-label {
            color: var(--text-light);
            font-weight: 500;
        }
        
        .detail-value {
            font-weight: 600;
            text-align: right;
            font-family: 'Roboto Mono', monospace;
        }
        
        /* Balance Summary */
        .balance-section {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2mm;
            margin-bottom: 3mm;
        }
        
        .balance-box {
            padding: 2mm;
            border-radius: 1mm;
            text-align: center;
        }
        
        .balance-total {
            background: #f8f9fa;
            border: 0.2mm solid #e9ecef;
        }
        
        .balance-remaining {
            background: #fff9e6;
            border: 0.2mm solid #ffd54f;
        }
        
        .balance-label {
            font-size: 7px;
            color: var(--text-light);
            margin-bottom: 1mm;
        }
        
        .balance-value {
            font-family: 'Roboto Mono', monospace;
            font-size: 10px;
            font-weight: 700;
            color: #2c3e50;
        }
        
        /* Status Badge */
        .status-badge {
            text-align: center;
            margin: 2mm 0 3mm 0;
        }
        
        .badge {
            display: inline-block;
            padding: 1mm 3mm;
            border-radius: 2mm;
            font-size: 8px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .badge-paid {
            background: var(--success-light);
            color: var(--success-dark);
            border: 0.2mm solid var(--success-dark);
        }
        
        .badge-partial {
            background: #fff3cd;
            color: #856404;
            border: 0.2mm solid #ffeaa7;
        }
        
        /* Payment Notes */
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
        
        /* Footer Section */
        .receipt-footer {
            margin-top: 3mm;
            padding-top: 2mm;
            border-top: 0.3mm dashed #ddd;
            font-size: 7px;
            color: #666;
            text-align: center;
        }
        
        .signature-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 3mm;
            margin: 2mm 0;
        }
        
        .signature-box {
            text-align: center;
        }
        
        .signature-line {
            width: 50mm;
            height: 0.5mm;
            background: #000;
            margin: 8mm auto 1mm;
        }
        
        .signature-label {
            font-size: 7px;
            color: var(--text-light);
        }
        
        .stamp-box {
            border: 0.2mm dashed #ddd;
            padding: 2mm;
            text-align: center;
            color: #999;
            font-size: 8px;
            margin: 2mm auto;
            max-width: 30mm;
            border-radius: 1mm;
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
            
            .receipt-card {
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
            
            /* Force page break after each receipt when printing multiple */
            .receipt-card {
                page-break-after: always;
            }
            
            /* Remove page break after last receipt */
            .receipt-card:last-child {
                page-break-after: auto;
            }
        }
    </style>
</head>
<body>



<!-- Receipt Card -->
<div class="receipt-card">
    <!-- Header -->
    <div class="receipt-header">
        <div class="company-info">
            <h1>DISTRIBUTOR ERP</h1>
            <p>Wholesale Distribution System</p>
            <p style="font-size: 7px; margin-top: 1mm;">Tax ID: XXXX-XXXX-XXXX</p>
        </div>
        <div class="receipt-meta">
            <div class="receipt-id">REC-<?= $shortId; ?></div>
            <div class="receipt-date"><?= $receiptDate; ?></div>
        </div>
    </div>
    
    <!-- Customer Info -->
    <div class="customer-section">
        <h3>PAYMENT RECEIPT</h3>
        <div class="customer-details">
            <div class="customer-left">
                <p><span class="label">Receipt:</span> PR-<?= str_pad($paymentId, 6, '0', STR_PAD_LEFT); ?></p>
                <p><span class="label">Invoice:</span> <?= $payment['invoice_number']; ?></p>
                <p><span class="label">Customer:</span> <?= htmlspecialchars($payment['customer_name']); ?></p>
                <?php if ($payment['business_name']): ?>
                <p><span class="label">Business:</span> <?= htmlspecialchars($payment['business_name']); ?></p>
                <?php endif; ?>
            </div>
            <div class="customer-right">
                <p><span class="label">Code:</span> <?= $payment['customer_code']; ?></p>
                <p><span class="label">Phone:</span> <?= htmlspecialchars($payment['customer_phone']); ?></p>
                <p><span class="label">Payment #:</span> <?= $paymentNumber; ?></p>
                <p><span class="label">Date:</span> <?= $paymentDate; ?></p>
            </div>
        </div>
    </div>
    
    <!-- Amount Paid -->
    <div class="amount-section">
        <div class="amount-label">AMOUNT PAID</div>
        <div class="amount-value"><?= formatCurrency($payment['payment_amount']); ?></div>
    </div>
    
    <!-- Payment Details -->
    <div class="payment-section">
        <h3>PAYMENT DETAILS</h3>
        <div class="detail-grid">
            <div class="detail-row">
                <span class="detail-label">Payment Method:</span>
                <span class="detail-value"><?= ucfirst(str_replace('_', ' ', $payment['payment_method'])); ?></span>
            </div>
            <?php if ($payment['reference_number']): ?>
            <div class="detail-row">
                <span class="detail-label">Reference No:</span>
                <span class="detail-value"><?= htmlspecialchars($payment['reference_number']); ?></span>
            </div>
            <?php endif; ?>
            <div class="detail-row">
                <span class="detail-label">Recorded By:</span>
                <span class="detail-value"><?= $payment['recorded_by'] ?? 'System'; ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Invoice Date:</span>
                <span class="detail-value"><?= date("d M Y", strtotime($payment['sale_date'])); ?></span>
            </div>
        </div>
    </div>
    
    <!-- Balance Summary -->
    <div class="balance-section">
        <div class="balance-box balance-total">
            <div class="balance-label">INVOICE TOTAL</div>
            <div class="balance-value"><?= formatCurrency($payment['invoice_total']); ?></div>
        </div>
        <div class="balance-box balance-remaining">
            <div class="balance-label">REMAINING BALANCE</div>
            <div class="balance-value"><?= formatCurrency($payment['remaining_balance']); ?></div>
        </div>
    </div>
    
    <!-- Status Badge -->
    <div class="status-badge">
        <?php if ($payment['remaining_balance'] <= 0): ?>
            <div class="badge badge-paid">
                <i class="fas fa-check-circle"></i> INVOICE FULLY PAID
            </div>
        <?php else: ?>
            <div class="badge badge-partial">
                <i class="fas fa-clock"></i> PARTIAL PAYMENT
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Payment Notes -->
    <?php if ($payment['notes']): ?>
    <div class="notes-section">
        <h4>NOTES:</h4>
        <p><?= nl2br(htmlspecialchars($payment['notes'])); ?></p>
    </div>
    <?php endif; ?>
    
    <!-- Footer -->
    <div class="receipt-footer">
        <div class="signature-grid">
            <div class="signature-box">
                <div class="signature-line"></div>
                <div class="signature-label">Customer Signature</div>
            </div>
            <div class="signature-box">
                <div class="signature-line"></div>
                <div class="signature-label">Authorized Signature</div>
            </div>
        </div>
        
        <div class="stamp-box">
            COMPANY STAMP
        </div>
        
        <div style="margin-top: 2mm;">
            <strong>Thank you for your payment!</strong><br>
            This is a computer generated receipt. Valid without signature.<br>
            For queries: +92-323-2968868
        </div>
    </div>
</div>

<!-- Action Buttons (Visible in browser, hidden when printing) -->
<div class="action-buttons">
    <button class="btn btn-primary" onclick="window.print()">
        <i class="fas fa-print"></i> Print Receipt
    </button>
</div>

<script>
function printTwoPerPage() {
    // Clone the receipt card
    const originalReceipt = document.querySelector('.receipt-card');
    const clone = originalReceipt.cloneNode(true);
    
    // Insert the clone after the original
    originalReceipt.parentNode.insertBefore(clone, originalReceipt.nextSibling);
    
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