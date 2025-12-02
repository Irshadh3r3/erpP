<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';

requireLogin();

$conn = getDBConnection();
$pageTitle = 'Convert to Invoice';

$bookingId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$errors = [];

if ($bookingId <= 0) {
    $_SESSION['error_message'] = 'Invalid booking ID';
    header('Location: list.php');
    exit;
}

// Get booking details
$bookingQuery = "SELECT b.*, 
                 bk.name as booker_name,
                 bk.commission_percentage
                 FROM bookings b
                 JOIN bookers bk ON b.booker_id = bk.id
                 WHERE b.id = $bookingId";
$bookingResult = $conn->query($bookingQuery);

if ($bookingResult->num_rows === 0) {
    $_SESSION['error_message'] = 'Booking not found';
    header('Location: list.php');
    exit;
}

$booking = $bookingResult->fetch_assoc();

// Check if already invoiced
if ($booking['status'] === 'invoiced') {
    $_SESSION['error_message'] = 'This booking is already invoiced';
    header('Location: view.php?id=' . $bookingId);
    exit;
}

// Check if cancelled
if ($booking['status'] === 'cancelled') {
    $_SESSION['error_message'] = 'Cannot invoice a cancelled booking';
    header('Location: view.php?id=' . $bookingId);
    exit;
}

// Get booking items
$itemsQuery = "SELECT bi.*, p.name as product_name, p.stock_quantity 
               FROM booking_items bi
               JOIN products p ON bi.product_id = p.id
               WHERE bi.booking_id = $bookingId";
$items = $conn->query($itemsQuery);

// Get or create customer
$customerId = $booking['customer_id'];
if (!$customerId) {
    // Check if customer exists by name and phone
    $customerCheckQuery = "SELECT id FROM customers WHERE name = '{$booking['customer_name']}' AND phone = '{$booking['customer_phone']}' LIMIT 1";
    $customerCheck = $conn->query($customerCheckQuery);
    
    if ($customerCheck->num_rows > 0) {
        $customerId = $customerCheck->fetch_assoc()['id'];
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payment_method = clean($_POST['payment_method']);
    $payment_status = clean($_POST['payment_status']);
    $paid_amount = (float)$_POST['paid_amount'];
    $discount = (float)$_POST['discount'];
    $tax = (float)$_POST['tax'];
    $notes = clean($_POST['notes']);
    $create_customer = isset($_POST['create_customer']) ? 1 : 0;
    
    // Calculate amounts
    $subtotal = $booking['total_amount'];
    $total_amount = $subtotal - $discount + $tax;
    
    // Validation
    if ($paid_amount < 0) {
        $errors[] = 'Paid amount cannot be negative';
    }
    if ($paid_amount > $total_amount) {
        $errors[] = 'Paid amount cannot exceed total amount';
    }
    
    // Check stock availability
    $items->data_seek(0);
    $stockIssues = [];
    while ($item = $items->fetch_assoc()) {
        if ($item['quantity'] > $item['stock_quantity']) {
            $stockIssues[] = "{$item['product_name']} - Required: {$item['quantity']}, Available: {$item['stock_quantity']}";
        }
    }
    
    if (!empty($stockIssues)) {
        $errors[] = 'Insufficient stock for the following products:';
        $errors = array_merge($errors, $stockIssues);
    }
    
    // If no errors, create invoice
    if (empty($errors)) {
        $conn->begin_transaction();
        
        try {
            // Create customer if needed
            if ($create_customer && !$customerId) {
                $customer_code = 'CUST-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                $stmt = $conn->prepare("INSERT INTO customers (customer_code, name, phone, address, area) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("sssss", $customer_code, $booking['customer_name'], $booking['customer_phone'], $booking['customer_address'], $booking['customer_address']);
                $stmt->execute();
                $customerId = $conn->insert_id;
            }
            
            // Use a default customer if still no customer
            if (!$customerId) {
                // Create or get "Walk-in Customer"
                $walkinCheck = $conn->query("SELECT id FROM customers WHERE customer_code = 'WALK-IN' LIMIT 1");
                if ($walkinCheck->num_rows > 0) {
                    $customerId = $walkinCheck->fetch_assoc()['id'];
                } else {
                    $conn->query("INSERT INTO customers (customer_code, name, phone) VALUES ('WALK-IN', 'Walk-in Customer', 'N/A')");
                    $customerId = $conn->insert_id;
                }
            }
            
            // Generate invoice number
            $invoice_number = generateInvoiceNumber($conn);
            $sale_date = date('Y-m-d');
            
            // Insert sale/invoice
           // Insert sale/invoice
$stmt = $conn->prepare("INSERT INTO sales (invoice_number, customer_id, sale_date, subtotal, discount, tax, total_amount, paid_amount, payment_status, payment_method, notes, user_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param("sisdddddsssi", $invoice_number, $customerId, $sale_date, $subtotal, $discount, $tax, $total_amount, $paid_amount, $payment_status, $payment_method, $notes, $_SESSION['user_id']);
            $stmt->execute();
            
            $saleId = $conn->insert_id;
            
            // Insert sale items and update stock
            $items->data_seek(0);
            $stmt = $conn->prepare("INSERT INTO sales_items (sale_id, product_id, quantity, unit_price, subtotal) VALUES (?, ?, ?, ?, ?)");
            
            while ($item = $items->fetch_assoc()) {
                $stmt->bind_param("iiidd", $saleId, $item['product_id'], $item['quantity'], $item['unit_price'], $item['subtotal']);
                $stmt->execute();
                
                // Update product stock
                updateProductStock($conn, $item['product_id'], $item['quantity'], 'sale', $saleId, 'sale', $_SESSION['user_id']);
            }
            
            // Update booking status and link to invoice
            $updateBooking = $conn->prepare("UPDATE bookings SET status = 'invoiced', invoice_id = ? WHERE id = ?");
            $updateBooking->bind_param("ii", $saleId, $bookingId);
            $updateBooking->execute();
            
            $conn->commit();
            
            $_SESSION['success_message'] = 'Booking converted to invoice successfully!';
            header('Location: ../sales/invoice_print.php?id=' . $saleId);
            exit;
            
        } catch (Exception $e) {
            $conn->rollback();
            $errors[] = 'Error creating invoice: ' . $e->getMessage();
        }
    }
}

// Reset items pointer
$items->data_seek(0);

include '../../includes/header.php';
?>

<!-- Page Header -->
<div class="mb-6">
    <h1 class="text-3xl font-bold text-gray-800">Convert Booking to Invoice</h1>
    <p class="text-gray-600">Booking #<?php echo $booking['booking_number']; ?> - <?php echo $booking['customer_name']; ?></p>
</div>

<!-- Error Messages -->
<?php if (!empty($errors)): ?>
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
        <ul class="list-disc list-inside">
            <?php foreach ($errors as $error): ?>
                <li><?php echo $error; ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form method="POST" action="">
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
        <!-- Booking Summary -->
        <div class="lg:col-span-2 bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-bold text-gray-800 mb-4">Booking Summary</h3>
            
            <div class="mb-4 p-4 bg-blue-50 rounded-lg">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <p class="text-sm text-gray-600">Booker</p>
                        <p class="font-semibold"><?php echo $booking['booker_name']; ?></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Booking Date</p>
                        <p class="font-semibold"><?php echo formatDate($booking['booking_date']); ?></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Customer</p>
                        <p class="font-semibold"><?php echo $booking['customer_name']; ?></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Phone</p>
                        <p class="font-semibold"><?php echo $booking['customer_phone'] ?: 'N/A'; ?></p>
                    </div>
                </div>
            </div>
            
            <!-- Products Table -->
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50 border-b">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Product</th>
                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Stock</th>
                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Qty</th>
                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Price</th>
                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php 
                        $items->data_seek(0);
                        while ($item = $items->fetch_assoc()): 
                            $stockOk = $item['quantity'] <= $item['stock_quantity'];
                        ?>
                            <tr class="<?php echo !$stockOk ? 'bg-red-50' : ''; ?>">
                                <td class="px-4 py-2">
                                    <div class="font-semibold"><?php echo $item['product_name']; ?></div>
                                    <?php if (!$stockOk): ?>
                                        <div class="text-xs text-red-600">⚠ Insufficient stock!</div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-2 text-right">
                                    <span class="<?php echo !$stockOk ? 'text-red-600 font-bold' : 'text-gray-600'; ?>">
                                        <?php echo $item['stock_quantity']; ?>
                                    </span>
                                </td>
                                <td class="px-4 py-2 text-right font-semibold"><?php echo $item['quantity']; ?></td>
                                <td class="px-4 py-2 text-right"><?php echo formatCurrency($item['unit_price']); ?></td>
                                <td class="px-4 py-2 text-right font-semibold"><?php echo formatCurrency($item['subtotal']); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Invoice Settings -->
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-bold text-gray-800 mb-4">Invoice Settings</h3>
            
            <!-- Create Customer -->
            <?php if (!$customerId): ?>
                <div class="mb-4 p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
                    <label class="flex items-center">
                        <input type="checkbox" 
                               name="create_customer" 
                               checked
                               class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                        <span class="ml-2 text-sm font-semibold text-gray-700">Create customer record</span>
                    </label>
                    <p class="text-xs text-gray-600 mt-1">Customer doesn't exist. Check to create a new customer record.</p>
                </div>
            <?php endif; ?>
            
            <!-- Discount -->
            <div class="mb-4">
                <label for="discount" class="block text-gray-700 font-semibold mb-2">Discount</label>
                <input type="number" 
                       id="discount" 
                       name="discount" 
                       value="0"
                       step="0.01"
                       min="0"
                       onchange="calculateInvoiceTotal()"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>

            <!-- Tax -->
            <div class="mb-4">
                <label for="tax" class="block text-gray-700 font-semibold mb-2">Tax</label>
                <input type="number" 
                       id="tax" 
                       name="tax" 
                       value="0"
                       step="0.01"
                       min="0"
                       onchange="calculateInvoiceTotal()"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>

            <!-- Payment Method -->
            <div class="mb-4">
                <label for="payment_method" class="block text-gray-700 font-semibold mb-2">Payment Method</label>
                <select id="payment_method" 
                        name="payment_method" 
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="cash">Cash</option>
                    <option value="credit">Credit</option>
                    <option value="bank_transfer">Bank Transfer</option>
                    <option value="cheque">Cheque</option>
                </select>
            </div>

            <!-- Payment Status -->
            <div class="mb-4">
                <label for="payment_status" class="block text-gray-700 font-semibold mb-2">Payment Status</label>
                <select id="payment_status" 
                        name="payment_status" 
                        onchange="updatePaidAmount()"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="unpaid">Unpaid</option>
                    <option value="partial">Partial</option>
                    <option value="paid">Paid</option>
                </select>
            </div>

            <!-- Paid Amount -->
            <div class="mb-4">
                <label for="paid_amount" class="block text-gray-700 font-semibold mb-2">Paid Amount</label>
                <input type="number" 
                       id="paid_amount" 
                       name="paid_amount" 
                       value="0"
                       step="0.01"
                       min="0"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>

            <!-- Amount Summary -->
            <div class="p-4 bg-gray-50 rounded-lg space-y-2">
                <div class="flex justify-between text-sm">
                    <span class="text-gray-600">Subtotal:</span>
                    <span id="display_subtotal" class="font-semibold"><?php echo formatCurrency($booking['total_amount']); ?></span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-gray-600">Discount:</span>
                    <span id="display_discount" class="font-semibold text-red-600">Rs. 0.00</span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-gray-600">Tax:</span>
                    <span id="display_tax" class="font-semibold text-green-600">Rs. 0.00</span>
                </div>
                <div class="flex justify-between text-lg font-bold border-t pt-2">
                    <span>Total:</span>
                    <span id="display_total" class="text-blue-600"><?php echo formatCurrency($booking['total_amount']); ?></span>
                </div>
            </div>

            <!-- Notes -->
            <div class="mt-4">
                <label for="notes" class="block text-gray-700 font-semibold mb-2">Notes</label>
                <textarea id="notes" 
                          name="notes" 
                          rows="3"
                          class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"><?php echo $booking['notes']; ?></textarea>
            </div>
        </div>
    </div>

    <!-- Form Actions -->
    <div class="flex items-center justify-end gap-4">
        <a href="invoice_print.php?id=<?php echo $bookingId; ?>" 
           class="px-6 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 font-semibold transition">
            Cancel
        </a>
        <button type="submit" 
                class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-semibold transition">
            Create Invoice
        </button>
    </div>
</form>

<script>
const subtotal = <?php echo $booking['total_amount']; ?>;

function calculateInvoiceTotal() {
    const discount = parseFloat(document.getElementById('discount').value) || 0;
    const tax = parseFloat(document.getElementById('tax').value) || 0;
    const total = subtotal - discount + tax;
    
    document.getElementById('display_discount').textContent = 'Rs. ' + discount.toFixed(2);
    document.getElementById('display_tax').textContent = 'Rs. ' + tax.toFixed(2);
    document.getElementById('display_total').textContent = 'Rs. ' + total.toFixed(2);
}

function updatePaidAmount() {
    const status = document.getElementById('payment_status').value;
    const discount = parseFloat(document.getElementById('discount').value) || 0;
    const tax = parseFloat(document.getElementById('tax').value) || 0;
    const total = subtotal - discount + tax;
    
    if (status === 'paid') {
        document.getElementById('paid_amount').value = total.toFixed(2);
    } else if (status === 'unpaid') {
        document.getElementById('paid_amount').value = '0.00';
    }
}
</script>

<?php
include '../../includes/footer.php';
?>