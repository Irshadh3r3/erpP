<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';

requireLogin();

$conn = getDBConnection();
$pageTitle = 'Create Booking';

$errors = [];
$bookerId = isset($_GET['booker_id']) ? (int)$_GET['booker_id'] : 0;

// Get bookers
$bookersQuery = "SELECT * FROM bookers WHERE is_active = 1 ORDER BY name ASC";
$bookers = $conn->query($bookersQuery);

// Get products
$productsQuery = "SELECT * FROM products WHERE is_active = 1 AND stock_quantity > 0 ORDER BY name ASC";
$products = $conn->query($productsQuery);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $booker_id = (int)$_POST['booker_id'];
    $customer_name = clean($_POST['customer_name']);
    $customer_phone = clean($_POST['customer_phone']);
    $customer_address = clean($_POST['customer_address']);
    $booking_date = clean($_POST['booking_date']);
    $delivery_date = clean($_POST['delivery_date']);
    $notes = clean($_POST['notes']);
    
    $product_ids = $_POST['product_id'] ?? [];
    $quantities = $_POST['quantity'] ?? [];
    $unit_prices = $_POST['unit_price'] ?? [];
    
    // Validation
    if ($booker_id <= 0) {
        $errors[] = 'Please select a booker';
    }
    if (empty($customer_name)) {
        $errors[] = 'Customer name is required';
    }
    if (empty($booking_date)) {
        $errors[] = 'Booking date is required';
    }
    if (empty($product_ids)) {
        $errors[] = 'Please add at least one product';
    }
    
    // Calculate total
    $total_amount = 0;
    $valid_items = [];
    
    foreach ($product_ids as $index => $product_id) {
        if (!empty($product_id) && !empty($quantities[$index]) && $quantities[$index] > 0) {
            $subtotal = $quantities[$index] * $unit_prices[$index];
            $total_amount += $subtotal;
            $valid_items[] = [
                'product_id' => $product_id,
                'quantity' => $quantities[$index],
                'unit_price' => $unit_prices[$index],
                'subtotal' => $subtotal
            ];
        }
    }
    
    if (empty($valid_items)) {
        $errors[] = 'Please add valid products with quantities';
    }
    
    // If no errors, insert booking
    if (empty($errors)) {
        $conn->begin_transaction();
        
        try {
            // Generate booking number
            $booking_number = generateBookingNumber($conn);
            
            // Insert booking
            $stmt = $conn->prepare("INSERT INTO bookings (booking_number, booker_id, customer_name, customer_phone, customer_address, booking_date, delivery_date, total_amount, notes, user_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sisssssdsi", $booking_number, $booker_id, $customer_name, $customer_phone, $customer_address, $booking_date, $delivery_date, $total_amount, $notes, $_SESSION['user_id']);
            $stmt->execute();
            
            $booking_id = $conn->insert_id;
            
            // Insert booking items
            $stmt = $conn->prepare("INSERT INTO booking_items (booking_id, product_id, quantity, unit_price, subtotal) VALUES (?, ?, ?, ?, ?)");
            
            foreach ($valid_items as $item) {
                $stmt->bind_param("iiidd", $booking_id, $item['product_id'], $item['quantity'], $item['unit_price'], $item['subtotal']);
                $stmt->execute();
            }
            
            $conn->commit();
            
            $_SESSION['success_message'] = 'Booking created successfully!';
            header('Location: /distributor-erp/modules/bookings/view.php?id=' . $booking_id);
            exit;
            
        } catch (Exception $e) {
            $conn->rollback();
            $errors[] = 'Error creating booking: ' . $e->getMessage();
        }
    }
}

include '../../includes/header.php';
?>

<!-- Page Header -->
<div class="mb-6">
    <h1 class="text-3xl font-bold text-gray-800">Create New Booking</h1>
    <p class="text-gray-600">Book an order for a customer</p>
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

<!-- Booking Form -->
<form method="POST" action="" id="bookingForm" class="space-y-6">
    <!-- Customer & Booker Info -->
    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-xl font-bold text-gray-800 mb-4">Customer & Booker Information</h2>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Booker -->
            <div>
                <label for="booker_id" class="block text-gray-700 font-semibold mb-2">Booker <span class="text-red-500">*</span></label>
                <select id="booker_id" 
                        name="booker_id" 
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                        required>
                    <option value="">Select Booker</option>
                    <?php 
                    $bookers->data_seek(0);
                    while ($booker = $bookers->fetch_assoc()): 
                    ?>
                        <option value="<?php echo $booker['id']; ?>" <?php echo $bookerId == $booker['id'] ? 'selected' : ''; ?>>
                            <?php echo $booker['name']; ?> (<?php echo $booker['area'] ?? 'No area'; ?>)
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <!-- Customer Name -->
            <div>
                <label for="customer_name" class="block text-gray-700 font-semibold mb-2">Customer Name <span class="text-red-500">*</span></label>
                <input type="text" 
                       id="customer_name" 
                       name="customer_name" 
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                       placeholder="Enter customer name"
                       required>
            </div>

            <!-- Customer Phone -->
            <div>
                <label for="customer_phone" class="block text-gray-700 font-semibold mb-2">Customer Phone</label>
                <input type="text" 
                       id="customer_phone" 
                       name="customer_phone" 
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                       placeholder="03XX-XXXXXXX">
            </div>

            <!-- Booking Date -->
            <div>
                <label for="booking_date" class="block text-gray-700 font-semibold mb-2">Booking Date <span class="text-red-500">*</span></label>
                <input type="date" 
                       id="booking_date" 
                       name="booking_date" 
                       value="<?php echo date('Y-m-d'); ?>"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                       required>
            </div>

            <!-- Delivery Date -->
            <div>
                <label for="delivery_date" class="block text-gray-700 font-semibold mb-2">Expected Delivery</label>
                <input type="date" 
                       id="delivery_date" 
                       name="delivery_date" 
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>

            <!-- Customer Address -->
            <div class="md:col-span-2">
                <label for="customer_address" class="block text-gray-700 font-semibold mb-2">Customer Address</label>
                <textarea id="customer_address" 
                          name="customer_address" 
                          rows="2"
                          class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                          placeholder="Enter delivery address"></textarea>
            </div>
        </div>
    </div>

    <!-- Products -->
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-xl font-bold text-gray-800">Products</h2>
            <button type="button" 
                    onclick="addProductRow()" 
                    class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg font-semibold transition">
                + Add Product
            </button>
        </div>

        <div id="productsContainer">
            <!-- Product rows will be added here -->
        </div>

        <div class="mt-4 pt-4 border-t">
            <div class="flex justify-end">
                <div class="w-64">
                    <div class="flex justify-between text-lg font-bold">
                        <span>Total:</span>
                        <span id="totalAmount">Rs. 0.00</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Notes -->
    <div class="bg-white rounded-lg shadow p-6">
        <label for="notes" class="block text-gray-700 font-semibold mb-2">Notes</label>
        <textarea id="notes" 
                  name="notes" 
                  rows="3"
                  class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                  placeholder="Any special instructions or notes..."></textarea>
    </div>

    <!-- Form Actions -->
    <div class="flex items-center justify-end gap-4">
        <a href="list.php" class="px-6 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 font-semibold transition">
            Cancel
        </a>
        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg font-semibold transition">
            Create Booking
        </button>
    </div>
</form>

<script>
// Products data
const products = <?php echo json_encode($products->fetch_all(MYSQLI_ASSOC)); ?>;
let rowCounter = 0;

function addProductRow() {
    rowCounter++;
    const container = document.getElementById('productsContainer');
    const row = document.createElement('div');
    row.className = 'grid grid-cols-12 gap-4 mb-4 items-start';
    row.id = 'row_' + rowCounter;
    
    row.innerHTML = `
        <div class="col-span-5">
            <select name="product_id[]" 
                    id="product_${rowCounter}"
                    onchange="updatePrice(${rowCounter})" 
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">Select Product</option>
                ${products.map(p => `<option value="${p.id}" data-price="${p.selling_price}" data-stock="${p.stock_quantity}">${p.name} (Stock: ${p.stock_quantity})</option>`).join('')}
            </select>
        </div>
        <div class="col-span-2">
            <input type="number" 
                   name="quantity[]" 
                   id="qty_${rowCounter}"
                   onchange="calculateRow(${rowCounter})"
                   min="1" 
                   placeholder="Qty" 
                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
        <div class="col-span-2">
            <input type="number" 
                   name="unit_price[]" 
                   id="price_${rowCounter}"
                   onchange="calculateRow(${rowCounter})"
                   step="0.01" 
                   placeholder="Price" 
                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
        <div class="col-span-2">
            <input type="text" 
                   id="subtotal_${rowCounter}"
                   readonly 
                   placeholder="Subtotal" 
                   class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-gray-50">
        </div>
        <div class="col-span-1">
            <button type="button" 
                    onclick="removeRow(${rowCounter})" 
                    class="w-full px-4 py-2 bg-red-500 hover:bg-red-600 text-white rounded-lg transition">
                ×
            </button>
        </div>
    `;
    
    container.appendChild(row);
}

function updatePrice(rowId) {
    const select = document.getElementById('product_' + rowId);
    const priceInput = document.getElementById('price_' + rowId);
    const option = select.options[select.selectedIndex];
    
    if (option.value) {
        priceInput.value = option.getAttribute('data-price');
        calculateRow(rowId);
    } else {
        priceInput.value = '';
    }
}

function calculateRow(rowId) {
    const qty = parseFloat(document.getElementById('qty_' + rowId).value) || 0;
    const price = parseFloat(document.getElementById('price_' + rowId).value) || 0;
    const subtotal = qty * price;
    
    document.getElementById('subtotal_' + rowId).value = 'Rs. ' + subtotal.toFixed(2);
    calculateTotal();
}

function calculateTotal() {
    let total = 0;
    const container = document.getElementById('productsContainer');
    const rows = container.getElementsByClassName('grid');
    
    for (let row of rows) {
        const inputs = row.querySelectorAll('input[type="number"]');
        if (inputs.length >= 2) {
            const qty = parseFloat(inputs[0].value) || 0;
            const price = parseFloat(inputs[1].value) || 0;
            total += qty * price;
        }
    }
    
    document.getElementById('totalAmount').textContent = 'Rs. ' + total.toFixed(2);
}

function removeRow(rowId) {
    const row = document.getElementById('row_' + rowId);
    if (row) {
        row.remove();
        calculateTotal();
    }
}

// Add initial row
addProductRow();
</script>

<?php
include '../../includes/footer.php';
?>