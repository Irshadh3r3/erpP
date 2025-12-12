<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';

requireLogin();

$conn = getDBConnection();
$pageTitle = 'Edit Purchase Order';

// Permission
requirePermission($conn, $_SESSION['role'], 'purchases', 'edit');

$purchase_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$purchase_id) {
    $_SESSION['error_message'] = 'Invalid purchase ID';
    header('Location: list.php');
    exit;
}

// Fetch purchase
$stmt = $conn->prepare("SELECT * FROM purchases WHERE id = ?");
$stmt->bind_param('i', $purchase_id);
$stmt->execute();
$purchase = $stmt->get_result()->fetch_assoc();
if (!$purchase) {
    $_SESSION['error_message'] = 'Purchase not found';
    header('Location: list.php');
    exit;
}

$errors = [];

// Suppliers & products
$suppliers = $conn->query("SELECT * FROM suppliers WHERE is_active = 1 ORDER BY name ASC");
$products = $conn->query("SELECT * FROM products WHERE is_active = 1 ORDER BY name ASC");

// Fetch existing items
$itemsQuery = "SELECT * FROM purchase_items WHERE purchase_id = ?";
$stmt = $conn->prepare($itemsQuery);
$stmt->bind_param('i', $purchase_id);
$stmt->execute();
$existing_items_result = $stmt->get_result();
$existing_items = [];
while ($row = $existing_items_result->fetch_assoc()) {
    $existing_items[] = $row;
}

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
    if ($supplier_id <= 0) $errors[] = 'Please select a supplier';
    if (empty($purchase_date)) $errors[] = 'Purchase date is required';
    if (empty($product_ids)) $errors[] = 'Please add at least one product';

    $subtotal = 0;
    $valid_items = [];
    foreach ($product_ids as $index => $pid) {
        $pid = (int)$pid;
        $qty = (float)($quantities[$index] ?? 0);
        $up = (float)($unit_prices[$index] ?? 0);
        if ($pid > 0 && $qty > 0) {
            $item_subtotal = $qty * $up;
            $subtotal += $item_subtotal;
            $valid_items[] = [
                'product_id' => $pid,
                'quantity' => $qty,
                'unit_price' => $up,
                'subtotal' => $item_subtotal
            ];
        }
    }

    if (empty($valid_items)) $errors[] = 'Please add valid products with quantities';

    $total_amount = $subtotal - $discount + $tax;
    if ($paid_amount > $total_amount) $errors[] = 'Paid amount cannot exceed total amount';

    if (empty($errors)) {
        $conn->begin_transaction();
        try {
            // Update purchase header
            $updateStmt = $conn->prepare("UPDATE purchases SET supplier_id = ?, purchase_date = ?, subtotal = ?, discount = ?, tax = ?, total_amount = ?, paid_amount = ?, payment_status = ?, notes = ?, user_id = ? WHERE id = ?");
            $updateStmt->bind_param('isdddddssii', $supplier_id, $purchase_date, $subtotal, $discount, $tax, $total_amount, $paid_amount, $payment_status, $notes, $_SESSION['user_id'], $purchase_id);
            if (!$updateStmt->execute()) throw new Exception('Error updating purchase: ' . $conn->error);

            // Build map of existing quantities by product
            $existing_map = [];
            foreach ($existing_items as $it) {
                $pid = (int)$it['product_id'];
                $existing_map[$pid] = ($existing_map[$pid] ?? 0) + (float)$it['quantity'];
            }

            // Delete old items
            $delStmt = $conn->prepare("DELETE FROM purchase_items WHERE purchase_id = ?");
            $delStmt->bind_param('i', $purchase_id);
            $delStmt->execute();

            // Insert new items and apply stock deltas
            $insStmt = $conn->prepare("INSERT INTO purchase_items (purchase_id, product_id, quantity, unit_price, subtotal) VALUES (?, ?, ?, ?, ?)");
            foreach ($valid_items as $item) {
                $insStmt->bind_param('iiddd', $purchase_id, $item['product_id'], $item['quantity'], $item['unit_price'], $item['subtotal']);
                if (!$insStmt->execute()) throw new Exception('Error inserting purchase item: ' . $conn->error);

                $pid = (int)$item['product_id'];
                $new_qty = (float)$item['quantity'];
                $old_qty = (float)($existing_map[$pid] ?? 0);
                $delta = $new_qty - $old_qty;
                if ($delta > 0) {
                    // increase stock by delta
                    updateProductStock($conn, $pid, $delta, 'purchase', $purchase_id, 'purchase', $_SESSION['user_id']);
                } else if ($delta < 0) {
                    // decrease stock by abs(delta)
                    updateProductStock($conn, $pid, abs($delta), 'sale', $purchase_id, 'purchase_edit', $_SESSION['user_id']);
                }
                unset($existing_map[$pid]);
            }

            // Any products remaining in existing_map were removed from purchase — subtract their quantities
            foreach ($existing_map as $pid => $old_qty) {
                updateProductStock($conn, $pid, (float)$old_qty, 'sale', $purchase_id, 'purchase_edit', $_SESSION['user_id']);
            }

            $conn->commit();
            $_SESSION['success_message'] = 'Purchase updated successfully';
            header('Location: view.php?id=' . $purchase_id);
            exit;

        } catch (Exception $e) {
            $conn->rollback();
            $errors[] = 'Error updating purchase: ' . $e->getMessage();
        }
    }
}

include '../../includes/header.php';
?>

<div class="mb-6">
    <h1 class="text-3xl font-bold text-gray-800">Edit Purchase Order</h1>
    <p class="text-gray-600">Modify purchase and adjust inventory accordingly</p>
</div>

<?php if (!empty($errors)): ?>
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
        <ul class="list-disc list-inside">
            <?php foreach ($errors as $error): ?>
                <li><?php echo htmlspecialchars($error); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<!-- For brevity, reuse the same form layout as add.php but prefill values -->
<form method="POST" action="" id="purchaseForm" class="space-y-6">
    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-xl font-bold text-gray-800 mb-4">Supplier & Date Information</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label for="supplier_id" class="block text-gray-700 font-semibold mb-2">Supplier <span class="text-red-500">*</span></label>
                <select id="supplier_id" name="supplier_id" class="w-full px-4 py-2 border border-gray-300 rounded-lg" required>
                    <option value="">Select Supplier</option>
                    <?php $suppliers->data_seek(0); while ($s = $suppliers->fetch_assoc()): ?>
                        <option value="<?php echo $s['id']; ?>" <?php echo $purchase['supplier_id'] == $s['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($s['name']); ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div>
                <label for="purchase_date" class="block text-gray-700 font-semibold mb-2">Purchase Date <span class="text-red-500">*</span></label>
                <input type="date" id="purchase_date" name="purchase_date" value="<?php echo htmlspecialchars($purchase['purchase_date']); ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg" required>
            </div>
        </div>
    </div>

    <!-- Products list: render existing_items as rows -->
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-xl font-bold text-gray-800">Products</h2>
            <button type="button" onclick="addProductRow()" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg">+ Add Product</button>
        </div>
        <div id="productsContainer">
            <?php foreach ($existing_items as $idx => $it): ?>
                <div class="product-row mb-3 grid grid-cols-12 gap-2 items-center">
                    <div class="col-span-5">
                        <select name="product_id[]" class="w-full px-3 py-2 border rounded" required>
                            <option value="">Select product</option>
                            <?php $products->data_seek(0); while ($p = $products->fetch_assoc()): ?>
                                <option value="<?php echo $p['id']; ?>" <?php echo $p['id'] == $it['product_id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($p['name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-span-2"><input type="number" step="0.001" name="quantity[]" value="<?php echo $it['quantity']; ?>" class="w-full px-3 py-2 border rounded" required></div>
                    <div class="col-span-2"><input type="number" step="0.01" name="unit_price[]" value="<?php echo $it['unit_price']; ?>" class="w-full px-3 py-2 border rounded" required></div>
                    <div class="col-span-2"><input type="text" readonly value="<?php echo formatCurrency($it['subtotal']); ?>" class="w-full px-3 py-2 border rounded bg-gray-50"></div>
                    <div class="col-span-1"><button type="button" onclick="this.closest('.product-row').remove()" class="text-red-600">Remove</button></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Payment Info -->
    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-xl font-bold text-gray-800 mb-4">Payment Information</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label class="block text-gray-700 font-semibold mb-2">Discount</label>
                <input type="number" name="discount" value="<?php echo $purchase['discount']; ?>" step="0.01" class="w-full px-4 py-2 border rounded">
            </div>
            <div>
                <label class="block text-gray-700 font-semibold mb-2">Tax</label>
                <input type="number" name="tax" value="<?php echo $purchase['tax']; ?>" step="0.01" class="w-full px-4 py-2 border rounded">
            </div>
            <div>
                <label class="block text-gray-700 font-semibold mb-2">Paid Amount</label>
                <input type="number" name="paid_amount" value="<?php echo $purchase['paid_amount']; ?>" step="0.01" class="w-full px-4 py-2 border rounded">
            </div>
            <div>
                <label class="block text-gray-700 font-semibold mb-2">Payment Status</label>
                <select name="payment_status" class="w-full px-4 py-2 border rounded">
                    <option value="unpaid" <?php echo $purchase['payment_status'] === 'unpaid' ? 'selected' : ''; ?>>Unpaid</option>
                    <option value="partial" <?php echo $purchase['payment_status'] === 'partial' ? 'selected' : ''; ?>>Partial</option>
                    <option value="paid" <?php echo $purchase['payment_status'] === 'paid' ? 'selected' : ''; ?>>Paid</option>
                </select>
            </div>
        </div>
        <div class="mt-4">
            <label class="block text-gray-700 font-semibold mb-2">Notes</label>
            <textarea name="notes" class="w-full px-3 py-2 border rounded"><?php echo htmlspecialchars($purchase['notes']); ?></textarea>
        </div>
    </div>

    <div class="flex justify-end">
        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded">Save Changes</button>
    </div>
</form>

<script>
function addProductRow() {
    const container = document.getElementById('productsContainer');
    const row = document.createElement('div');
    row.className = 'product-row mb-3 grid grid-cols-12 gap-2 items-center';
    row.innerHTML = `
        <div class="col-span-5">
            <select name="product_id[]" class="w-full px-3 py-2 border rounded" required>
                <option value="">Select product</option>
                <?php $products->data_seek(0); while ($p = $products->fetch_assoc()): ?>
                    <option value="<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['name']); ?></option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="col-span-2"><input type="number" step="0.001" name="quantity[]" value="1" class="w-full px-3 py-2 border rounded" required></div>
        <div class="col-span-2"><input type="number" step="0.01" name="unit_price[]" value="0" class="w-full px-3 py-2 border rounded" required></div>
        <div class="col-span-2"><input type="text" readonly value="0" class="w-full px-3 py-2 border rounded bg-gray-50"></div>
        <div class="col-span-1"><button type="button" onclick="this.closest('.product-row').remove()" class="text-red-600">Remove</button></div>
    `;
    container.appendChild(row);
}
</script>
