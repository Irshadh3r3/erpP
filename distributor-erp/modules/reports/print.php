<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';

requireLogin();

$conn = getDBConnection();
$pageTitle = 'Booker Daily Sales Report - Print';

// Get parameters
$booker_id = isset($_GET['booker_id']) ? (int)$_GET['booker_id'] : 0;
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-d');
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');

if ($booker_id == 0) {
    die('Please select a booker');
}

// Get booker details
$bookerQuery = "SELECT * FROM bookers WHERE id = $booker_id";
$booker = $conn->query($bookerQuery)->fetch_assoc();

if (!$booker) {
    die('Booker not found');
}

// Get invoices for this booker
$invoicesQuery = "SELECT 
                  s.id,
                  s.invoice_number,
                  s.sale_date,
                  s.total_amount,
                  s.paid_amount,
                  s.payment_status,
                  c.name as customer_name,
                  c.customer_code,
                  c.phone as customer_phone
                  FROM bookings b
                  JOIN sales s ON b.invoice_id = s.id
                  JOIN customers c ON s.customer_id = c.id
                  WHERE b.booker_id = $booker_id
                  AND b.status = 'invoiced'
                  AND s.sale_date BETWEEN '$date_from' AND '$date_to'
                  ORDER BY s.sale_date ASC, s.invoice_number ASC";
$invoices = $conn->query($invoicesQuery);

// Calculate totals
$total_amount = 0;
$total_received = 0;
$total_due = 0;
$invoice_data = [];

while ($inv = $invoices->fetch_assoc()) {
    $total_amount += $inv['total_amount'];
    $total_received += $inv['paid_amount'];
    $total_due += ($inv['total_amount'] - $inv['paid_amount']);
    $invoice_data[] = $inv;
}

// Get products sold by this booker
$productsQuery = "SELECT 
                  p.name as product_name,
                  p.variety,
                  p.sku,
                  p.unit,
                  SUM(si.quantity) as total_quantity
                  FROM bookings b
                  JOIN sales s ON b.invoice_id = s.id
                  JOIN sales_items si ON s.id = si.sale_id
                  JOIN products p ON si.product_id = p.id
                  WHERE b.booker_id = $booker_id
                  AND b.status = 'invoiced'
                  AND s.sale_date BETWEEN '$date_from' AND '$date_to'
                  GROUP BY p.id
                  ORDER BY total_quantity DESC";
$products = $conn->query($productsQuery);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booker Sales Report - <?php echo $booker['name']; ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Arial', sans-serif;
            padding: 20px;
            color: #333;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 3px solid #2563eb;
            padding-bottom: 20px;
        }
        
        .header h1 {
            font-size: 28px;
            color: #1e40af;
            margin-bottom: 5px;
        }
        
        .header h2 {
            font-size: 18px;
            color: #64748b;
            font-weight: normal;
        }
        
        .info-section {
            display: grid;
            grid-template-columns: 10fr 13fr 6FR;
            gap: 20px;
            margin-bottom: 30px;
            padding: 2px;
            background: #f8fafc;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
        }
        
        .info-box {
            padding: 10px;
        }
        
        .info-box label {
            font-weight: bold;
            color: #475569;
            display: block;
            margin-bottom: 5px;
            font-size: 12px;
        }
        
        .info-box .value {
            font-size: 16px;
            color: #1e293b;
        }
        
        .section-title {
            background: #2563eb;
            color: white;
            padding: 12px 15px;
            margin: -20px 0 6px 0;
            border-radius: 6px;
            font-size: 16px;
            font-weight: bold;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            font-size: 10px;
        }
        
        table thead {
            background: #f1f5f9;
        }
        
        table th {
            padding: 10px;
            text-align: left;
            font-weight: bold;
            border: 1px solid #cbd5e1;
            color: #334155;
        }
        
        table th.text-right {
            text-align: right;
        }
        
        table td {
            padding: 8px 10px;
            border: 1px solid #e2e8f0;
        }
        
        table td.text-right {
            text-align: right;
        }
        
        table tbody tr:nth-child(even) {
            background: #f8fafc;
        }
        
        table tbody tr:hover {
            background: #f1f5f9;
        }
        
        .totals-row {
            background: #dbeafe !important;
            font-weight: bold;
            font-size: 13px;
        }
        
        .totals-row td {
            border-top: 2px solid #2563eb;
            padding: 12px 10px;
        }
        
        .summary-boxes {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin: 20px 0;
        }
        
        .summary-box {
            background: #f8fafc;
            padding: 15px;
            border-radius: 8px;
            border: 2px solid #e2e8f0;
            text-align: center;
        }
        
        .summary-box .label {
            font-size: 12px;
            color: #64748b;
            margin-bottom: 8px;
            font-weight: 600;
        }
        
        .summary-box .amount {
            font-size: 20px;
            font-weight: bold;
        }
        
        .summary-box.total .amount {
            color: #2563eb;
        }
        
        .summary-box.received .amount {
            color: #16a34a;
        }
        
        .summary-box.due .amount {
            color: #dc2626;
        }
        
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 2px solid #e2e8f0;
            text-align: center;
            font-size: 11px;
            color: #64748b;
        }
        
        .page-break {
            page-break-before: always;
        }
        
        .status-badge {
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: bold;
        }
        
        .status-paid {
            background: #dcfce7;
            color: #166534;
        }
        
        .status-partial {
            background: #fef3c7;
            color: #92400e;
        }
        
        .status-unpaid {
            background: #fee2e2;
            color: #991b1b;
        }
        
        @media print {
            body {
                padding: 10px;
            }
            
            .no-print {
                display: none !important;
            }
            
            table {
                font-size: 11px;
            }
            
            .page-break {
                page-break-before: always;
            }
        }
        
        .print-button {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #2563eb;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: bold;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .print-button:hover {
            background: #1d4ed8;
        }
    </style>
</head>
<body>
    <!-- Print Button -->
    <button onclick="window.print()" class="print-button no-print">Print</button>
    
    <!-- Header -->
    <div class="header">
        <h1>KHAN TRADERS</h1>
        <h2>Booker Performance Report</h2>
    </div>
    
    <!-- Booker Info -->
    <div class="info-section">
        <div class="info-box">
            <label>BOOKER NAME:</label>
            <div class="value"><?php echo $booker['name']; ?></div>
        </div>
        <!-- <div class="info-box">
            <label>BOOKER CODE:</label>
            <div class="value"><?php echo $booker['booker_code']; ?></div>
        </div> -->
        <!-- <div class="info-box">
            <label>AREA:</label>
            <div class="value"><?php echo $booker['area'] ?: 'N/A'; ?></div>
        </div> -->
        <div class="info-box">
            <label>REPORT PERIOD:</label>
            <div class="value">
                <?php 
                if ($date_from === $date_to) {
                    echo date('d M Y', strtotime($date_from));
                } else {
                    echo date('d M Y', strtotime($date_from)) . ' - ' . date('d M Y', strtotime($date_to));
                }
                ?>
            </div>
        </div>
        <div class="info-box">
            <label>TOTAL INVOICES:</label>
            <div class="value"><?php echo count($invoice_data); ?></div>
        </div>
        <!-- <div class="info-box">
            <label>COMMISSION RATE:</label>
            <div class="value"><?php echo $booker['commission_percentage']; ?>%</div>
        </div> -->
    </div>
    
    <!-- Summary Boxes -->
    <!-- <div class="summary-boxes">
        <div class="summary-box total">
            <div class="label">TOTAL AMOUNT</div>
            <div class="amount">Rs. <?php echo number_format($total_amount, 2); ?></div>
        </div>
        <div class="summary-box received">
            <div class="label">AMOUNT RECEIVED</div>
            <div class="amount">Rs. <?php echo number_format($total_received, 2); ?></div>
        </div>
        <div class="summary-box due">
            <div class="label">AMOUNT DUE</div>
            <div class="amount">Rs. <?php echo number_format($total_due, 2); ?></div>
        </div>
    </div> -->
    
    <!-- Section 1: Invoices -->
    <div class="section-title">INVOICE DETAILS</div>
    
    <table>
        <thead>
            <tr>
                <th style="width: 40px;">S.No.</th>
                <th>Invoice #</th>
                <th>Date</th>
                <th>Customer Name</th>
                <!-- <th>Phone</th> -->
                <th class="text-right">Total Amount</th>
                <th class="text-right">Amount Received</th>
                <th class="text-right">Amount Due</th>
                <th style="width: 80px;">Status</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $sno = 1;
            foreach ($invoice_data as $invoice): 
                $due = $invoice['total_amount'] - $invoice['paid_amount'];
                $statusClass = $invoice['payment_status'] === 'paid' ? 'status-paid' : 
                              ($invoice['payment_status'] === 'partial' ? 'status-partial' : 'status-unpaid');
            ?>
                <tr>
                    <td><?php echo $sno++; ?></td>
                    <td><strong><?php echo $invoice['invoice_number']; ?></strong></td>
                    <td><?php echo date('d M Y', strtotime($invoice['sale_date'])); ?></td>
                    <td>
                        <strong><?php echo $invoice['customer_name']; ?></strong><br>
                        <small style="color: #64748b;"><?php echo $invoice['customer_code']; ?></small>
                    </td>
                    <!-- <td><?php echo $invoice['customer_phone']; ?></td> -->
                    <td class="text-right"><strong>Rs. <?php echo number_format($invoice['total_amount'], 2); ?></strong></td>
                    <td class="text-right" style="color: #16a34a;"><strong>Rs. <?php echo number_format($invoice['paid_amount'], 2); ?></strong></td>
                    <td class="text-right" style="color: #dc2626;"><strong>Rs. <?php echo number_format($due, 2); ?></strong></td>
                    <td>
                        <span class="status-badge <?php echo $statusClass; ?>">
                            <?php echo strtoupper($invoice['payment_status']); ?>
                        </span>
                    </td>
                </tr>
            <?php endforeach; ?>
            
            <!-- Totals Row -->
            <tr class="totals-row">
                <td colspan="4" style="text-align: right;">TOTAL:</td>
                <td class="text-right">Rs. <?php echo number_format($total_amount, 2); ?></td>
                <td class="text-right">Rs. <?php echo number_format($total_received, 2); ?></td>
                <td class="text-right">Rs. <?php echo number_format($total_due, 2); ?></td>
                
            </tr>
        </tbody>
    </table>
    
    <!-- Page Break -->
    <div class="page-break"></div>
    
    <!-- Header on Second Page -->
    <div class="header">
        <h1>PRODUCTS SOLD REPORT</h1>
        <h2><?php echo $booker['name']; ?> - <?php echo $date_from === $date_to ? date('d M Y', strtotime($date_from)) : date('d M Y', strtotime($date_from)) . ' to ' . date('d M Y', strtotime($date_to)); ?></h2>
    </div>
    
    <!-- Section 2: Products -->
    <div class="section-title"> PRODUCTS BREAKDOWN</div>
    
    <table>
        <thead>
            <tr>
                <th style="width: 40px;">S.No.</th>
                <!-- <th>Product SKU</th> -->
                <th>Product Name</th>
                <th>Unit</th>
                <th class="text-right" style="width: 150px;">Total Quantity Sold</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $sno = 1;
            $total_qty = 0;
            while ($product = $products->fetch_assoc()): 
                $total_qty += $product['total_quantity'];
            ?>
                <tr>
                    <td><?php echo $sno++; ?></td>
                    <!-- <td><strong><?php echo $product['sku']; ?></strong></td> -->
                    <td>
    <?php 
        echo htmlspecialchars($product['product_name']);
        if (!empty($product['variety'])) {
            echo " - " . htmlspecialchars($product['variety']);
        }
    ?>
</td>

                    <td><?php echo $product['unit']; ?></td>
                    <td class="text-right"><strong><?php echo number_format($product['total_quantity']); ?> <?php echo $product['unit']; ?></strong></td>
                </tr>
            <?php endwhile; ?>
            
            <!-- Totals Row -->
            <tr class="totals-row">
                <td colspan="3" style="text-align: right;">TOTAL UNITS SOLD:</td>
                <td class="text-right"><?php echo number_format($total_qty); ?></td>
            </tr>
        </tbody>
    </table>
    
    <!-- Commission Calculation -->
    <?php
    $commission_amount = ($total_received * $booker['commission_percentage']) / 100;
    ?>
    <!-- <div class="summary-boxes" style="margin-top: 30px;">
        <div class="summary-box">
            <div class="label">COMMISSION RATE</div>
            <div class="amount" style="color: #7c3aed;"><?php echo $booker['commission_percentage']; ?>%</div>
        </div>
        <div class="summary-box">
            <div class="label">AMOUNT COLLECTED</div>
            <div class="amount" style="color: #16a34a;">Rs. <?php echo number_format($total_received, 2); ?></div>
        </div>
        <div class="summary-box">
            <div class="label">COMMISSION EARNED</div>
            <div class="amount" style="color: #ea580c;">Rs. <?php echo number_format($commission_amount, 2); ?></div>
        </div>
    </div> -->
    
    <!-- Footer -->
    <!-- <div class="footer">
        <p><strong>Report Generated:</strong> <?php echo date('d M Y h:i A'); ?></p>
        <p>Distributor ERP System - Sales Performance Report</p>
        <p style="margin-top: 10px; font-size: 10px;">
            This is a computer-generated report. All amounts are in PKR (Pakistani Rupees).
        </p>
    </div> -->
</body>
</html>