<?php
// distributor-erp/modules/payments/get_customer_invoices.php
require_once '../../config/database.php';
require_once '../../includes/functions.php';

requireLogin();

header('Content-Type: application/json');

$conn = getDBConnection();
$customer_id = isset($_GET['customer_id']) ? (int)$_GET['customer_id'] : 0;

if ($customer_id <= 0) {
    echo json_encode([]);
    exit;
}

$query = "SELECT 
          id,
          invoice_number,
          sale_date,
          total_amount,
          paid_amount,
          (total_amount - paid_amount) as balance_due,
          payment_status
          FROM sales
          WHERE customer_id = $customer_id
          AND payment_status != 'paid'
          ORDER BY sale_date ASC";

$result = $conn->query($query);
$invoices = [];

while ($row = $result->fetch_assoc()) {
    $invoices[] = $row;
}

echo json_encode($invoices);
?>