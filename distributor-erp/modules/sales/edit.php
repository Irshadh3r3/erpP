<?php
// Sales edit has been consolidated into Bookings. Redirect to bookings list.
require_once '../../includes/functions.php';
requireLogin();
header('Location: ../bookings/list.php');
exit;
?>
<div class="mb-6">
    <h1 class="text-3xl font-bold text-gray-800 mb-2">Edit Sale</h1>
    <a href="list.php" class="text-blue-600 hover:text-blue-800">← Back to Sales</a>
</div>

<?php if (!empty($errors)): ?>
<div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
    <ul class="list-disc pl-5">
        <?php foreach ($errors as $error): ?>
        <li><?php echo htmlspecialchars($error); ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<form method="POST" class="bg-white shadow-md rounded-lg p-6">
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">Invoice Number</label>
            <input type="text" readonly value="<?php echo htmlspecialchars($sale['invoice_number']); ?>" 
                   class="w-full px-4 py-2 bg-gray-100 border border-gray-300 rounded">
        </div>
        
        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">Customer *</label>
            <select name="customer_id" required class="w-full px-4 py-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">-- Select Customer --</option>
                <?php while ($customer = $customers->fetch_assoc()): ?>
                <option value="<?php echo $customer['id']; ?>" <?php echo $sale['customer_id'] == $customer['id'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($customer['name']); ?> (<?php echo htmlspecialchars($customer['customer_code']); ?>)
                </option>
                <?php endwhile; ?>
            </select>
        </div>
        
        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">Sale Date *</label>
            <input type="date" name="sale_date" value="<?php echo $sale['sale_date']; ?>" required 
                   class="w-full px-4 py-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
    </div>
    
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">Payment Method</label>
            <select name="payment_method" class="w-full px-4 py-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="cash" <?php echo $sale['payment_method'] === 'cash' ? 'selected' : ''; ?>>Cash</option>
                <option value="credit" <?php echo $sale['payment_method'] === 'credit' ? 'selected' : ''; ?>>Credit</option>
                <option value="bank_transfer" <?php echo $sale['payment_method'] === 'bank_transfer' ? 'selected' : ''; ?>>Bank Transfer</option>
                <option value="cheque" <?php echo $sale['payment_method'] === 'cheque' ? 'selected' : ''; ?>>Cheque</option>
            </select>
        </div>
        
        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">Status</label>
            <input type="text" readonly value="<?php echo ucfirst($sale['payment_status']); ?>" 
                   class="w-full px-4 py-2 bg-gray-100 border border-gray-300 rounded">
        </div>
    </div>
    
    <div>
        <label class="block text-sm font-semibold text-gray-700 mb-1">Notes</label>
        <textarea name="notes" rows="3" class="w-full px-4 py-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500"><?php echo htmlspecialchars($sale['notes']); ?></textarea>
    </div>
    
    <!-- Products Section -->
    <div class="mt-8">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">Sale Items *</h3>
        
        <div class="overflow-x-auto mb-4">
            <table class="w-full border-collapse">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="border px-4 py-2 text-left text-sm font-semibold">Product</th>
                        <th class="border px-4 py-2 text-center text-sm font-semibold">Quantity</th>
                        <th class="border px-4 py-2 text-center text-sm font-semibold">Unit Price</th>
                        <th class="border px-4 py-2 text-center text-sm font-semibold">Subtotal</th>
                        <th class="border px-4 py-2 text-center text-sm font-semibold">Action</th>
                    </tr>
                </thead>
                <tbody id="items-table">
                    <?php foreach ($sale_items as $item): ?>
                    <tr class="item-row">
                        <td class="border px-4 py-2">
                            <select name="product_id[]" class="product-select w-full px-2 py-1 border border-gray-300 rounded" onchange="calculateRow(this)">
                                <option value="">-- Select Product --</option>
                                <?php 
                                $products->data_seek(0);
                                while ($product = $products->fetch_assoc()): ?>
                                <option value="<?php echo $product['id']; ?>" data-price="<?php echo $product['selling_price']; ?>" <?php echo $item['product_id'] == $product['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($product['name']); ?> - <?php echo htmlspecialchars($product['variety']); ?>
                                </option>
                                <?php endwhile; ?>
                            </select>
                        </td>
                        <td class="border px-4 py-2">
                            <input type="number" name="quantity[]" step="0.001" value="<?php echo $item['quantity']; ?>" class="quantity w-full px-2 py-1 border border-gray-300 rounded text-center" onchange="calculateRow(this)">
                        </td>
                        <td class="border px-4 py-2">
                            <input type="number" name="unit_price[]" step="0.01" value="<?php echo $item['unit_price']; ?>" class="unit-price w-full px-2 py-1 border border-gray-300 rounded text-center" onchange="calculateRow(this)">
                        </td>
                        <td class="border px-4 py-2 text-center">
                            <span class="row-subtotal font-semibold"><?php echo number_format($item['subtotal'], 2); ?></span>
                        </td>
                        <td class="border px-4 py-2 text-center">
                            <button type="button" onclick="removeRow(this)" class="text-red-600 hover:text-red-800 font-semibold">Remove</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <button type="button" onclick="addRow()" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded font-semibold transition mb-4">
            + Add Item
        </button>
    </div>
    
    <!-- Totals -->
    <div class="mt-6 border-t pt-6">
        <div class="flex justify-end mb-4">
            <div class="w-64">
                <div class="flex justify-between py-2 border-b">
                    <span class="font-semibold">Subtotal:</span>
                    <span id="total-subtotal" class="font-semibold"><?php echo number_format($sale['subtotal'], 2); ?></span>
                </div>
                <div class="flex justify-between py-2 text-lg font-bold text-blue-600">
                    <span>Total Amount:</span>
                    <span id="total-amount"><?php echo number_format($sale['total_amount'], 2); ?></span>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Form Actions -->
    <div class="flex gap-4 pt-6 border-t">
        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded font-semibold transition">
            Update Sale
        </button>
        <a href="list.php" class="bg-gray-400 hover:bg-gray-500 text-white px-6 py-2 rounded font-semibold transition">
            Cancel
        </a>
    </div>
</form>

<script>
function calculateRow(input) {
    const row = input.closest('tr');
    const quantity = parseFloat(row.querySelector('.quantity').value) || 0;
    const unitPrice = parseFloat(row.querySelector('.unit-price').value) || 0;
    const subtotal = quantity * unitPrice;
    
    row.querySelector('.row-subtotal').textContent = subtotal.toFixed(2);
    calculateTotal();
}

function calculateTotal() {
    let total = 0;
    document.querySelectorAll('.row-subtotal').forEach(el => {
        total += parseFloat(el.textContent) || 0;
    });
    
    document.getElementById('total-subtotal').textContent = total.toFixed(2);
    document.getElementById('total-amount').textContent = total.toFixed(2);
}

function addRow() {
    const table = document.getElementById('items-table');
    const firstRow = table.querySelector('.item-row');
    const newRow = firstRow.cloneNode(true);
    
    newRow.querySelectorAll('input').forEach(input => input.value = '');
    newRow.querySelectorAll('select').forEach(select => select.selectedIndex = 0);
    newRow.querySelector('.row-subtotal').textContent = '0.00';
    
    table.appendChild(newRow);
    
    if (typeof TomSelect !== 'undefined') {
        setTimeout(() => {
            newRow.querySelectorAll('.product-select').forEach(el => {
                if (!el._tomSelectInitialized) {
                    new TomSelect(el, {
                        create: false,
                        allowEmptyOption: true,
                        dropdownParent: 'body',
                        hideSelected: true
                    });
                    el._tomSelectInitialized = true;
                }
            });
        }, 100);
    }
}

function removeRow(button) {
    const row = button.closest('tr');
    if (document.querySelectorAll('.item-row').length > 1) {
        row.remove();
        calculateTotal();
    } else {
        alert('You must have at least one item');
    }
}

document.addEventListener('DOMContentLoaded', calculateTotal);
</script>

</main>
</div>
<?php include '../../includes/footer.php'; ?>
