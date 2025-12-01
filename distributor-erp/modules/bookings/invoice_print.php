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

$query = "SELECT b.*, 
                 bk.name AS booker_name,
                 bk.booker_code,
                 bk.phone AS booker_phone,
                 bk.commission_percentage,
                 u.full_name AS created_by
          FROM bookings b
          JOIN bookers bk ON b.booker_id = bk.id
          LEFT JOIN users u ON b.user_id = u.id
          WHERE b.id = $bookingId";

$booking = $conn->query($query)->fetch_assoc();

$itemsQuery = "SELECT bi.*, p.name AS product_name, p.sku, p.unit
               FROM booking_items bi
               JOIN products p ON bi.product_id = p.id
               WHERE bi.booking_id = $bookingId";

$items = $conn->query($itemsQuery);

$commission = ($booking['total_amount'] * $booking['commission_percentage']) / 100;
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Invoice - <?= $booking['booking_number']; ?></title>

<style>
body {
    font-family: Arial, sans-serif;
    margin: 0;
    background: #ffffff;
    -webkit-print-color-adjust: exact;
}

.container {
    width: 850px;
    margin: 25px auto;
    padding: 30px;
    border: 2px solid #000;
}

.header-title {
    font-size: 32px;
    font-weight: 700;
}

.sub-title {
    font-size: 14px;
    color: #444;
    margin-top: -5px;
}

.company-box {
    text-align: right;
    font-size: 13px;
    line-height: 1.5;
}

.section-title {
    font-weight: bold;
    margin-top: 30px;
    font-size: 15px;
    border-bottom: 1px solid #333;
    padding-bottom: 6px;
}

table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 15px;
}

th {
    background: #f2f2f2;
    font-size: 14px;
    padding: 10px;
    border: 1px solid black;
    font-weight: bold;
}

td {
    padding: 10px;
    border: 1px solid black;
    font-size: 14px;
}

.totals-box {
    background: #e5e5e5;
    padding: 12px;
    float: right;
    width: 260px;
    margin-top: 15px;
    border: 1px solid #ddd;
}

.total-row {
    font-size: 15px;
    font-weight: bold;
}

.balance-due {
    color: red;
    font-weight: bold;
}

.commission-box {
    background: #ffe9b3;
    padding: 15px;
    border-radius: 5px;
    border: 1px solid #e4c878;
    font-size: 15px;
    margin-top: 25px;
}

.footer {
    margin-top: 40px;
    font-size: 13px;
    text-align: center;
    color: #444;
}

@media print {
    .print-btn { display: none; }
}
.print-btn {
    text-align: center;
    margin-top: 20px;
}
.print-btn button {
    background: black;
    color: #fff;
    padding: 10px 20px;
    border: none;
    cursor: pointer;
}
</style>
</head>

<body>

<div class="container">

    <table width="100%">
        <tr>
            <td>
                <div class="header-title">DISTRIBUTOR ERP</div>
                <div class="sub-title">DISTRIBUTION & WHOLESALE</div>
            </td>

            <td class="company-box">
                <strong>Your Company Address Here</strong><br>
                City, Postal Code <br>
                TAX ID: XXXX-XXXX-XXXX <br>
                Phone: +92-XXX-XXXXXXX <br>
                Email: info@company.com
            </td>
        </tr>
    </table>

    <hr>

    <h3 class="section-title">INVOICE / TAX RECEIPT</h3>

    <p><strong>CUSTOMER DETAILS:</strong> <?= $booking['customer_name']; ?><br>
    <strong>PHONE:</strong> <?= $booking['customer_phone']; ?><br>
    <strong>ADDRESS:</strong> <?= $booking['customer_address']; ?><br>
    <strong>INVOICE #:</strong> <?= $booking['booking_number']; ?><br>
    <strong>DATE:</strong> <?= date("d M Y", strtotime($booking['created_at'])); ?><br>
    <strong>BOOKER:</strong> <?= $booking['booker_name']; ?> (<?= $booking['booker_code']; ?>)</p>

    <table>
        <tr>
            <th>Description</th>
            <th>Qty</th>
            <th>Unit Price</th>
            <th>Amount</th>
        </tr>

        <?php while($row = $items->fetch_assoc()): ?>
        <tr>
            <td>
                <?= $row['product_name']; ?><br>
                <small>SKU: <?= $row['sku']; ?></small>
            </td>
            <td><?= $row['quantity'] . ' ' . $row['unit']; ?></td>
            <td><?= formatCurrency($row['unit_price']); ?></td>
            <td><strong><?= formatCurrency($row['subtotal']); ?></strong></td>
        </tr>
        <?php endwhile; ?>
    </table>

    <!-- TOTAL BOX -->
    <div class="totals-box">
        NET AMOUNT: <strong><?= formatCurrency($booking['total_amount']); ?></strong><br>
        <span class="total-row">TOTAL: <?= formatCurrency($booking['total_amount']); ?></span><br>
        PAID: <strong>0.00</strong><br>
        <span class="balance-due">BALANCE DUE: <?= formatCurrency($booking['total_amount']); ?></span>
    </div>

    <div style="clear: both;"></div>

    <!-- COMMISSION BOX -->
    <div class="commission-box">
        <strong>BOOKER COMMISSION (<?= $booking['commission_percentage']; ?>%):</strong>
        <?= formatCurrency($commission); ?>
    </div>

    <div class="footer">
        THANK YOU FOR YOUR BUSINESS! <br>
        For any queries, contact us. <br><br>
        This is a computer-generated invoice. No signature required.
    </div>

    <div class="print-btn">
        <button onclick="window.print()">Print Invoice</button>
    </div>

</div>

</body>
</html>
