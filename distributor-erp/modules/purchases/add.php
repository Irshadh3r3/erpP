<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';

requireLogin();

$conn = getDBConnection();
$pageTitle = 'Create Purchase Order';

$errors = [];
$supplierId = isset($_GET['supplier_id']) ? (int)$_GET['supplier_id'] : 0;

// Get suppliers
$suppliersQuery = "SELECT * FROM suppliers WHERE is_active = 1 ORDER BY name ASC";
$suppliers = $conn->query($suppliersQuery);

// Get products
$productsQuery = "SELECT * FROM products WHERE is_active = 1 ORDER BY name ASC";
$products = $conn->query($productsQuery);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $supplier_id = (int)$_POST['supplier_id'];
    $purchase_date = clean($_POST['purchase_date']);
    $discount = (float)$_POST['discount'];
    $tax = (float)$_POST['tax'];
    $paid_amount = (float)$_POST['paid_amount'];
    $payment_status = clean($_POST['payment_status']);
    $notes = clean($_POST['notes']);
    
    $product_ids = $_POST['product_id'] ?? [];
    $quantities = $_POST['quantity'] ?? [];
    $unit_prices = $_POST['unit_price'] ?? [];
    
    // Validation
    if ($supplier_id <= 0) {
        $errors[] = 'Please select a supplier';
    }
    if (empty($purchase_date)) {
        $errors[] = 'Purchase date is required';
    }
    if (empty($product_ids)) {
        $errors[] = 'Please add at least one product';
    }
    
    // Calculate total
    $subtotal = 0;
    $valid_items = [];
    
    foreach ($product_ids as $index => $product_id) {
        if (!empty($product_id) && !empty($quantities[$index]) && $quantities[$index] > 0) {
            $item_subtotal = $quantities[$index] * $unit_prices[$index];
            $subtotal += $item_subtotal;
            $valid_items[] = [
                'product_id' => $product_id,
                'quantity' => $quantities[$index],
                'unit_price' => $unit_prices[$index],
                'subtotal' => $item_subtotal
            ];
        }
    }
    
    if (empty($valid_items)) {
        $errors[] = 'Please add valid products with quantities';
    }
    
    $total_amount = $subtotal - $discount + $tax;
    
    if ($paid_amount > $total_amount) {
        $errors[] = 'Paid amount cannot exceed total amount';
    }
    
    // If no errors, insert purchase
    if (empty($errors)) {
        $conn->begin_transaction();
        
        try {
            // Generate purchase number
            $purchase_number = generatePurchaseNumber($conn);
            
            // Insert purchase
            $stmt = $conn->prepare("INSERT INTO purchases (purchase_number, supplier_id, purchase_date, subtotal, discount, tax, total_amount, paid_amount, payment_status, notes, user_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sisdddddssi", $purchase_number, $supplier_id, $purchase_date, $subtotal, $discount, $tax, $total_amount, $paid_amount, $payment_status, $notes, $_SESSION['user_id']);
            $stmt->execute();
            
            $purchase_id = $conn->insert_id;
            
            // Insert purchase items and update stock
            $stmt = $conn->prepare("INSERT INTO purchase_items (purchase_id, product_id, quantity, unit_price, subtotal) VALUES (?, ?, ?, ?, ?)");
            
            foreach ($valid_items as $item) {
                // allow fractional quantities (quantity can be decimal)
                $stmt->bind_param("iiddd", $purchase_id, $item['product_id'], $item['quantity'], $item['unit_price'], $item['subtotal']);
                $stmt->execute();
                
                // Update product stock
                updateProductStock($conn, $item['product_id'], $item['quantity'], 'purchase', $purchase_id, 'purchase', $_SESSION['user_id']);
            }
            
            $conn->commit();
            
            $_SESSION['success_message'] = 'Purchase order created successfully!';
            header('Location: print_invoice.php?id=' . $purchase_id);
            exit;
            
        } catch (Exception $e) {
            $conn->rollback();
            $errors[] = 'Error creating purchase order: ' . $e->getMessage();
        }
    }
}

include '../../includes/header.php';
?>

<!-- Page Header -->
<div class="mb-6">
    <h1 class="text-3xl font-bold text-gray-800">Create Purchase Order</h1>
    <p class="text-gray-600">Purchase products from supplier and update inventory</p>
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

<!-- Purchase Form -->
<form method="POST" action="" id="purchaseForm" class="space-y-6">
    <!-- Supplier & Date Info -->
    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-xl font-bold text-gray-800 mb-4">Supplier & Date Information</h2>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Supplier -->
            <div>
                <label for="supplier_id" class="block text-gray-700 font-semibold mb-2">Supplier <span class="text-red-500">*</span></label>
                <select id="supplier_id" 
                        name="supplier_id" 
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                        required>
                    <option value="">Select Supplier</option>
                    <?php 
                    $suppliers->data_seek(0);
                    while ($supplier = $suppliers->fetch_assoc()): 
                    ?>
                        <option value="<?php echo $supplier['id']; ?>" <?php echo $supplierId == $supplier['id'] ? 'selected' : ''; ?>>
                            <?php echo $supplier['name']; ?> (<?php echo $supplier['supplier_code']; ?>)
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <!-- Purchase Date -->
            <div>
                <label for="purchase_date" class="block text-gray-700 font-semibold mb-2">Purchase Date <span class="text-red-500">*</span></label>
                <input type="date" 
                       id="purchase_date" 
                       name="purchase_date" 
                       value="<?php echo date('Y-m-d'); ?>"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                       required>
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
                        <span>Subtotal:</span>
                        <span id="subtotalAmount">Rs. 0.00</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Payment Info -->
    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-xl font-bold text-gray-800 mb-4">Payment Information</h2>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Discount -->
            <div>
                <label for="discount" class="block text-gray-700 font-semibold mb-2">Discount</label>
                <input type="number" 
                       id="discount" 
                       name="discount" 
                       value="0"
                       step="0.01"
                       min="0"
                       onchange="calculateTotal()"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>

            <!-- Tax -->
            <div>
                <label for="tax" class="block text-gray-700 font-semibold mb-2">Tax</label>
                <input type="number" 
                       id="tax" 
                       name="tax" 
                       value="0"
                       step="0.01"
                       min="0"
                       onchange="calculateTotal()"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>

            <!-- Payment Status -->
            <div>
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
            <div>
                <label for="paid_amount" class="block text-gray-700 font-semibold mb-2">Paid Amount</label>
                <input type="number" 
                       id="paid_amount" 
                       name="paid_amount" 
                       value="0"
                       step="0.01"
                       min="0"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>

            <!-- Total Display -->
            <div class="md:col-span-2">
                <div class="p-4 bg-blue-50 rounded-lg">
                    <div class="flex justify-between items-center text-2xl font-bold text-blue-600">
                        <span>Total Amount:</span>
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
                  placeholder="Any additional notes or remarks..."></textarea>
    </div>

    <!-- Form Actions -->
    <div class="flex items-center justify-end gap-4">
        <a href="list.php" class="px-6 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 font-semibold transition">
            Cancel
        </a>
        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg font-semibold transition">
            Create Purchase Order
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
            <select name="product_id[]" class="product-select"
                    id="product_${rowCounter}"
                    onchange="updatePrice(${rowCounter})" 
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">Select Product</option>
                ${products.map(p => `<option value="${p.id}" data-price="${p.purchase_price}" data-variety="${p.variety ?? ''}">${p.name}${p.variety ? ' - ' + p.variety : ''} (${p.sku})</option>`).join('')}
            </select>
            </select>
            <div id="variety_${rowCounter}" class="text-xs text-gray-500 mt-1"></div>
        </div>
        <div class="col-span-2">
                 <input type="number" 
                   name="quantity[]" 
                   id="qty_${rowCounter}"
                   onchange="calculateRow(${rowCounter})"
                     min="0" 
                     step="0.01"
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

    // initialize Tom Select for the newly added select
    setTimeout(function () {
        const sel = row.querySelector('.product-select');
        if (typeof TomSelect !== 'undefined' && sel && !sel._tomSelectInitialized) {
            new TomSelect(sel, { create: false, allowEmptyOption: true, dropdownParent: 'body' });
            sel._tomSelectInitialized = true;
        }
    }, 0);
}

function updatePrice(rowId) {
    const select = document.getElementById('product_' + rowId);
    const priceInput = document.getElementById('price_' + rowId);
    const option = select.options[select.selectedIndex];
    
    if (option.value) {
        priceInput.value = option.getAttribute('data-price');
        // show variety if present
        const varietyEl = document.getElementById('variety_' + rowId);
        if (varietyEl) varietyEl.textContent = option.getAttribute('data-variety') || '';
        calculateRow(rowId);
    } else {
        priceInput.value = '';
        const varietyEl = document.getElementById('variety_' + rowId);
        if (varietyEl) varietyEl.textContent = '';
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
    let subtotal = 0;
    const container = document.getElementById('productsContainer');
    const rows = container.getElementsByClassName('grid');
    
    for (let row of rows) {
        const inputs = row.querySelectorAll('input[type="number"]');
        if (inputs.length >= 2) {
            const qty = parseFloat(inputs[0].value) || 0;
            const price = parseFloat(inputs[1].value) || 0;
            subtotal += qty * price;
        }
    }
    
    const discount = parseFloat(document.getElementById('discount').value) || 0;
    const tax = parseFloat(document.getElementById('tax').value) || 0;
    const total = subtotal - discount + tax;
    
    document.getElementById('subtotalAmount').textContent = 'Rs. ' + subtotal.toFixed(2);
    document.getElementById('totalAmount').textContent = 'Rs. ' + total.toFixed(2);
}

function updatePaidAmount() {
    const status = document.getElementById('payment_status').value;
    const discount = parseFloat(document.getElementById('discount').value) || 0;
    const tax = parseFloat(document.getElementById('tax').value) || 0;
    
    let subtotal = 0;
    const container = document.getElementById('productsContainer');
    const rows = container.getElementsByClassName('grid');
    
    for (let row of rows) {
        const inputs = row.querySelectorAll('input[type="number"]');
        if (inputs.length >= 2) {
            const qty = parseFloat(inputs[0].value) || 0;
            const price = parseFloat(inputs[1].value) || 0;
            subtotal += qty * price;
        }
    }
    
    const total = subtotal - discount + tax;
    
    if (status === 'paid') {
        document.getElementById('paid_amount').value = total.toFixed(2);
    } else if (status === 'unpaid') {
        document.getElementById('paid_amount').value = '0.00';
    }
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