<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';

requireLogin();

$conn = getDBConnection();
$pageTitle = 'Create Sale';

// Check permission
requirePermission($conn, $_SESSION['role'], 'sales', 'add');

// This page has been consolidated into the Bookings module.
require_once '../../includes/functions.php';
requireLogin();
header('Location: ../bookings/add.php');
exit;
?>
        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">Payment Method</label>
            <select name="payment_method" class="w-full px-4 py-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="cash">Cash</option>
                <option value="credit">Credit</option>
                <option value="bank_transfer">Bank Transfer</option>
                <option value="cheque">Cheque</option>
            </select>
        </div>
    </div>
    
    <div>
        <label class="block text-sm font-semibold text-gray-700 mb-1">Notes</label>
        <textarea name="notes" rows="3" class="w-full px-4 py-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
    </div>
    
    <!-- Products Section -->
    <div class="mt-8">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">Sale Items *</h3>
        
        <div class="overflow-x-auto mb-4">
            <table class="w-full border-collapse">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="border px-4 py-2 text-left text-sm font-semibold">Product</th>
                        <th class="border px-4 py-2 text-left text-sm font-semibold">Available Stock</th>
                        <th class="border px-4 py-2 text-center text-sm font-semibold">Quantity</th>
                        <th class="border px-4 py-2 text-center text-sm font-semibold">Unit Price</th>
                        <th class="border px-4 py-2 text-center text-sm font-semibold">Subtotal</th>
                        <th class="border px-4 py-2 text-center text-sm font-semibold">Action</th>
                    </tr>
                </thead>
                <tbody id="items-table">
                    <tr class="item-row">
                        <td class="border px-4 py-2">
                            <select name="product_id[]" class="product-select w-full px-2 py-1 border border-gray-300 rounded" onchange="updateProductInfo(this)">
                                <option value="">-- Select Product --</option>
                                <?php 
                                $products->data_seek(0);
                                while ($product = $products->fetch_assoc()): ?>
                                <option value="<?php echo $product['id']; ?>" data-price="<?php echo $product['selling_price']; ?>" data-stock="<?php echo $product['stock_quantity']; ?>" data-variety="<?php echo htmlspecialchars($product['variety']); ?>">
                                    <?php echo htmlspecialchars($product['name']); ?> - <?php echo htmlspecialchars($product['variety']); ?> (Stock: <?php echo $product['stock_quantity']; ?>)
                                </option>
                                <?php endwhile; ?>
                            </select>
                            <div id="variety_info" class="text-xs text-gray-500 mt-1"></div>
                        </td>
                        <td class="border px-4 py-2">
                            <input type="text" readonly class="available-stock w-full px-2 py-1 bg-gray-100 rounded" value="0">
                        </td>
                        <td class="border px-4 py-2">
                            <input type="number" name="quantity[]" step="0.001" class="quantity w-full px-2 py-1 border border-gray-300 rounded text-center" placeholder="0" onchange="calculateRow(this)">
                        </td>
                        <td class="border px-4 py-2">
                            <input type="number" name="unit_price[]" step="0.01" class="unit-price w-full px-2 py-1 border border-gray-300 rounded text-center" placeholder="0.00" onchange="calculateRow(this)">
                        </td>
                        <td class="border px-4 py-2 text-center">
                            <span class="row-subtotal font-semibold">0.00</span>
                        </td>
                        <td class="border px-4 py-2 text-center">
                            <button type="button" onclick="removeRow(this)" class="text-red-600 hover:text-red-800 font-semibold">Remove</button>
                        </td>
                    </tr>
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
                    <span id="total-subtotal" class="font-semibold">0.00</span>
                </div>
                <div class="flex justify-between py-2 text-lg font-bold text-blue-600">
                    <span>Total Amount:</span>
                    <span id="total-amount">0.00</span>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Form Actions -->
    <div class="flex gap-4 pt-6 border-t">
        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded font-semibold transition">
            Create Sale
        </button>
        <a href="list.php" class="bg-gray-400 hover:bg-gray-500 text-white px-6 py-2 rounded font-semibold transition">
            Cancel
        </a>
    </div>
</form>

<script>
function updateProductInfo(select) {
    const row = select.closest('tr');
    const option = select.options[select.selectedIndex];
    const variety = option.dataset.variety || '';
    const stock = option.dataset.stock || '0';
    const price = option.dataset.price || '0';
    
    row.querySelector('.available-stock').value = stock;
    row.querySelector('.unit-price').value = price;
    
    if (variety) {
        row.querySelector('#variety_info').textContent = `Variety: ${variety}`;
    }
    
    calculateRow(row.querySelector('.quantity'));
}

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
    
    // Clear input values
    newRow.querySelectorAll('input').forEach(input => input.value = '');
    newRow.querySelectorAll('select').forEach(select => select.selectedIndex = 0);
    
    table.appendChild(newRow);
    
    // Reinitialize Tom Select if loaded
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

// Initialize on page load
document.addEventListener('DOMContentLoaded', calculateTotal);
</script>

</main>
</div>
<?php include '../../includes/footer.php'; ?>
