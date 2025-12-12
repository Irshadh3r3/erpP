<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';

requireLogin();

$conn = getDBConnection();
$pageTitle = 'Record Payment';

$errors = [];
$invoiceId = isset($_GET['invoice_id']) ? (int)$_GET['invoice_id'] : 0;

// Get invoice details if specified
$invoice = null;
if ($invoiceId > 0) {
    $invoiceQuery = "SELECT s.*, 
                     c.name as customer_name,
                     c.customer_code,
                     (s.total_amount - s.paid_amount) as balance_due
                     FROM sales s
                     JOIN customers c ON s.customer_id = c.id
                     WHERE s.id = ?";
    $stmtInv = $conn->prepare($invoiceQuery);
    $stmtInv->bind_param('i', $invoiceId);
    $stmtInv->execute();
    $invoiceResult = $stmtInv->get_result();

    if ($invoiceResult->num_rows > 0) {
        $invoice = $invoiceResult->fetch_assoc();
    }
}

// Get customers with outstanding balances
$customersQuery = "SELECT c.id, c.customer_code, c.name, c.phone,
                   COUNT(s.id) as outstanding_invoices,
                   SUM(s.total_amount - s.paid_amount) as total_outstanding
                   FROM customers c
                   JOIN sales s ON c.id = s.customer_id
                   WHERE s.payment_status != 'paid'
                   AND c.is_active = 1
                   GROUP BY c.id
                   ORDER BY c.name ASC";
$customers = $conn->query($customersQuery);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $invoice_id = (int)($_POST['invoice_id'] ?? 0);
    $payment_amount = (float)($_POST['payment_amount'] ?? 0);
    $payment_date = clean($_POST['payment_date'] ?? '');
    $payment_method = clean($_POST['payment_method'] ?? '');
    $reference_number = clean($_POST['reference_number'] ?? '');
    $notes = clean($_POST['notes'] ?? '');

    // Validation
    if ($invoice_id <= 0) {
        $errors[] = 'Please select an invoice';
    }
    if ($payment_amount <= 0) {
        $errors[] = 'Payment amount must be greater than 0';
    }
    if (empty($payment_date)) {
        $errors[] = 'Payment date is required';
    }

    // Get invoice details
    $invoiceCheckStmt = $conn->prepare("SELECT *, (total_amount - paid_amount) as balance FROM sales WHERE id = ?");
    $invoiceCheckStmt->bind_param('i', $invoice_id);
    $invoiceCheckStmt->execute();
    $invoiceCheck = $invoiceCheckStmt->get_result();

    if ($invoiceCheck->num_rows === 0) {
        $errors[] = 'Invoice not found';
    } else {
        $invoiceData = $invoiceCheck->fetch_assoc();

        if ($payment_amount > $invoiceData['balance']) {
            $errors[] = 'Payment amount cannot exceed balance due of ' . formatCurrency($invoiceData['balance']);
        }
    }

    // If no errors, record payment
    if (empty($errors)) {
        $conn->begin_transaction();

        try {
            // Insert payment record
            $stmt = $conn->prepare("INSERT INTO payments (invoice_id, payment_date, payment_amount, payment_method, reference_number, notes, user_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
            $stmt->bind_param('isdsssi', $invoice_id, $payment_date, $payment_amount, $payment_method, $reference_number, $notes, $_SESSION['user_id']);
            $stmt->execute();

            // Update invoice paid amount
            $new_paid_amount = $invoiceData['paid_amount'] + $payment_amount;
            $new_balance = $invoiceData['total_amount'] - $new_paid_amount;

            // Determine new payment status
            if ($new_balance <= 0) {
                $new_status = 'paid';
            } elseif ($new_paid_amount > 0) {
                $new_status = 'partial';
            } else {
                $new_status = 'unpaid';
            }

            $updateStmt = $conn->prepare("UPDATE sales SET paid_amount = ?, payment_status = ? WHERE id = ?");
            $updateStmt->bind_param('dsi', $new_paid_amount, $new_status, $invoice_id);
            $updateStmt->execute();

            // Update customer balance
            $conn->query("UPDATE customers SET current_balance = (
                            SELECT COALESCE(SUM(total_amount - paid_amount),0) 
                            FROM sales 
                            WHERE customer_id = {$invoiceData['customer_id']}
                         ) WHERE id = {$invoiceData['customer_id']}");

            $conn->commit();

            $_SESSION['success_message'] = 'Payment recorded successfully!';
            header('Location: payment_list.php');
            exit;

        } catch (Exception $e) {
            $conn->rollback();
            $errors[] = 'Error recording payment: ' . $e->getMessage();
        }
    }
}

include '../../includes/header.php';
?>

<!-- Professional Payment UI -->
<div class="max-w-5xl mx-auto">
  <div class="flex flex-col lg:flex-row gap-8">
    <!-- Main Form Card -->
    <div class="flex-1">
      <div class="bg-white rounded-2xl shadow-xl p-8 border border-blue-100">
        <div class="mb-8 flex items-center gap-4">
          <div class="bg-blue-100 text-blue-600 rounded-full p-3">
            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
          </div>
          <div>
            <h1 class="text-2xl font-bold text-gray-800">Record Payment</h1>
            <p class="text-gray-500">Apply a payment to a customer invoice</p>
          </div>
        </div>
        <?php if (!empty($errors)): ?>
          <div class="bg-red-50 border border-red-300 text-red-700 px-4 py-3 rounded-lg mb-6">
            <ul class="list-disc list-inside">
              <?php foreach ($errors as $error): ?>
                <li><?php echo $error; ?></li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>
        <form method="POST" action="" id="paymentForm" class="space-y-7">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <label for="customer_select" class="block text-gray-700 font-semibold mb-2">Customer <span class="text-red-500">*</span></label>
              <select id="customer_select" onchange="loadCustomerInvoices(this.value)" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">-- Select Customer --</option>
                <?php $customers->data_seek(0); while ($customer = $customers->fetch_assoc()): ?>
                  <option value="<?php echo $customer['id']; ?>" <?php echo ($invoice && $invoice['customer_id'] == $customer['id']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($customer['name']); ?> (<?php echo htmlspecialchars($customer['customer_code']); ?>) - <?php echo formatCurrency($customer['total_outstanding']); ?> outstanding
                  </option>
                <?php endwhile; ?>
              </select>
            </div>
            <div>
              <label for="invoice_id" class="block text-gray-700 font-semibold mb-2">Invoice <span class="text-red-500">*</span></label>
              <select id="invoice_id" name="invoice_id" onchange="updateInvoiceDetails()" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                <option value="">-- Select Invoice --</option>
                <?php if ($invoice): ?>
                  <option value="<?php echo $invoice['id']; ?>" selected data-total="<?php echo $invoice['total_amount']; ?>" data-paid="<?php echo $invoice['paid_amount']; ?>" data-balance="<?php echo $invoice['balance_due']; ?>" data-invoice-num="<?php echo $invoice['invoice_number']; ?>" data-date="<?php echo $invoice['sale_date']; ?>">
                    <?php echo $invoice['invoice_number']; ?> - <?php echo formatCurrency($invoice['balance_due']); ?> due
                  </option>
                <?php endif; ?>
              </select>
            </div>
          </div>
          <div id="invoice_details" class="mt-6 mb-4 p-5 rounded-xl border border-blue-200 bg-blue-50 shadow-sm" style="<?php echo $invoice ? '' : 'display: none;'; ?>">
            <div class="grid grid-cols-2 gap-4 text-sm">
              <div>
                <p class="text-gray-500">Invoice Number</p>
                <p class="font-semibold" id="detail_invoice_num"><?php echo $invoice['invoice_number'] ?? ''; ?></p>
              </div>
              <div>
                <p class="text-gray-500">Invoice Date</p>
                <p class="font-semibold" id="detail_date"><?php echo $invoice ? formatDate($invoice['sale_date']) : ''; ?></p>
              </div>
              <div>
                <p class="text-gray-500">Total Amount</p>
                <p class="font-semibold" id="detail_total"><?php echo $invoice ? formatCurrency($invoice['total_amount']) : ''; ?></p>
              </div>
              <div>
                <p class="text-gray-500">Previously Paid</p>
                <p class="font-semibold text-green-600" id="detail_paid"><?php echo $invoice ? formatCurrency($invoice['paid_amount']) : ''; ?></p>
              </div>
              <div class="col-span-2">
                <p class="text-gray-500">Balance Due</p>
                <p class="text-2xl font-bold text-red-600" id="detail_balance"><?php echo $invoice ? formatCurrency($invoice['balance_due']) : ''; ?></p>
              </div>
            </div>
          </div>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <label for="payment_amount" class="block text-gray-700 font-semibold mb-2">Payment Amount <span class="text-red-500">*</span></label>
              <div class="flex gap-2">
                <input type="number" id="payment_amount" name="payment_amount" value="<?php echo $invoice['balance_due'] ?? ''; ?>" step="0.01" min="0.01" class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="0.00" required>
                <button type="button" onclick="setFullBalance()" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg font-semibold transition">Full</button>
              </div>
            </div>
            <div>
              <label for="payment_date" class="block text-gray-700 font-semibold mb-2">Payment Date <span class="text-red-500">*</span></label>
              <input type="date" id="payment_date" name="payment_date" value="<?php echo date('Y-m-d'); ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
            </div>
          </div>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <label for="payment_method" class="block text-gray-700 font-semibold mb-2">Payment Method <span class="text-red-500">*</span></label>
              <select id="payment_method" name="payment_method" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                <option value="cash">Cash</option>
                <option value="bank_transfer">Bank Transfer</option>
                <option value="cheque">Cheque</option>
                <option value="credit_card">Credit Card</option>
                <option value="online">Online Payment</option>
              </select>
            </div>
            <div>
              <label for="reference_number" class="block text-gray-700 font-semibold mb-2">Reference Number</label>
              <input type="text" id="reference_number" name="reference_number" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Cheque #, Transaction ID, etc.">
              <p class="text-xs text-gray-500 mt-1">Optional: Cheque number, transaction ID, or other reference</p>
            </div>
          </div>
          <div>
            <label for="notes" class="block text-gray-700 font-semibold mb-2">Notes</label>
            <textarea id="notes" name="notes" rows="2" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Any additional notes..."></textarea>
          </div>
          <div class="flex items-center justify-end gap-4 pt-8 border-t mt-6">
            <a href="payment_list.php" class="px-6 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 font-semibold transition">Cancel</a>
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-8 py-2 rounded-lg font-bold text-lg transition">Record Payment</button>
          </div>
        </form>
      </div>
    </div>
    <!-- Sidebar Card -->
    <div class="w-full lg:w-80 flex-shrink-0">
      <div class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-2xl shadow-lg p-7 border border-blue-200 sticky top-6">
        <h3 class="text-lg font-bold text-blue-900 mb-4 flex items-center gap-2"><svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2z"/></svg> Quick Stats</h3>
        <?php
        $statsQuery = "SELECT COUNT(*) as unpaid_invoices, SUM(total_amount - paid_amount) as total_outstanding FROM sales WHERE payment_status != 'paid'";
        $stats = $conn->query($statsQuery)->fetch_assoc();
        ?>
        <div class="space-y-4">
          <div class="p-4 bg-red-100 rounded-xl border border-red-200">
            <p class="text-sm text-gray-600 mb-1">Total Outstanding</p>
            <p class="text-2xl font-bold text-red-600"><?php echo formatCurrency($stats['total_outstanding']); ?></p>
            <p class="text-xs text-gray-500 mt-1"><?php echo $stats['unpaid_invoices']; ?> invoices</p>
          </div>
          <?php
          $todayQuery = "SELECT COALESCE(SUM(payment_amount), 0) as today_collections FROM payments WHERE DATE(payment_date) = CURDATE()";
          $today = $conn->query($todayQuery)->fetch_assoc();
          ?>
          <div class="p-4 bg-green-100 rounded-xl border border-green-200">
            <p class="text-sm text-gray-600 mb-1">Today's Collections</p>
            <p class="text-2xl font-bold text-green-600"><?php echo formatCurrency($today['today_collections']); ?></p>
          </div>
        </div>
        <div class="mt-8 pt-6 border-t">
          <h4 class="text-sm font-bold text-blue-700 mb-3">Quick Links</h4>
          <div class="space-y-2">
            <a href="payment_list.php" class="block text-sm text-blue-600 hover:text-blue-800">→ View All Payments</a>
            <a href="../reports/outstanding_payments.php" class="block text-sm text-blue-600 hover:text-blue-800">→ Outstanding Report</a>
            <a href="../sales/list.php?status=unpaid" class="block text-sm text-blue-600 hover:text-blue-800">→ Unpaid Invoices</a>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
function loadCustomerInvoices(customerId) {
    const invoiceSelect = document.getElementById('invoice_id');
    invoiceSelect.innerHTML = '<option value="">Loading...</option>';

    if (!customerId) {
        invoiceSelect.innerHTML = '<option value="">-- Select Invoice --</option>';
        document.getElementById('invoice_details').style.display = 'none';
        return;
    }

    fetch('get_customer_invoices.php?customer_id=' + encodeURIComponent(customerId))
        .then(res => res.json())
        .then(data => {
            invoiceSelect.innerHTML = '<option value="">-- Select Invoice --</option>';
            data.forEach(inv => {
                const opt = document.createElement('option');
                opt.value = inv.id;
                opt.textContent = inv.invoice_number + ' - ' + inv.balance_due_formatted + ' due';
                opt.setAttribute('data-total', inv.total_amount);
                opt.setAttribute('data-paid', inv.paid_amount);
                opt.setAttribute('data-balance', inv.balance_due);
                opt.setAttribute('data-invoice-num', inv.invoice_number);
                opt.setAttribute('data-date', inv.sale_date);
                invoiceSelect.appendChild(opt);
            });
        })
        .catch(err => {
            console.error(err);
            invoiceSelect.innerHTML = '<option value="">-- Select Invoice --</option>';
        });
}

function updateInvoiceDetails() {
    const select = document.getElementById('invoice_id');
    const selectedOption = select.options[select.selectedIndex];

    if (!selectedOption || !selectedOption.value) {
        document.getElementById('invoice_details').style.display = 'none';
        return;
    }

    const total = selectedOption.getAttribute('data-total');
    const paid = selectedOption.getAttribute('data-paid');
    const balance = selectedOption.getAttribute('data-balance');
    const invoiceNum = selectedOption.getAttribute('data-invoice-num');
    const date = selectedOption.getAttribute('data-date');

    document.getElementById('detail_invoice_num').textContent = invoiceNum;
    document.getElementById('detail_date').textContent = formatDate(date);
    document.getElementById('detail_total').textContent = formatCurrency(total);
    document.getElementById('detail_paid').textContent = formatCurrency(paid);
    document.getElementById('detail_balance').textContent = formatCurrency(balance);
    document.getElementById('payment_amount').value = parseFloat(balance).toFixed(2);

    document.getElementById('invoice_details').style.display = 'block';
}

function setFullBalance() {
    const select = document.getElementById('invoice_id');
    const selectedOption = select.options[select.selectedIndex];

    if (selectedOption && selectedOption.value) {
        const balance = selectedOption.getAttribute('data-balance');
        document.getElementById('payment_amount').value = parseFloat(balance).toFixed(2);
    }
}

function formatCurrency(amount) {
    if (amount === null || amount === undefined || amount === '') return '';
    return 'Rs. ' + parseFloat(amount).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
}

function formatDate(dateString) {
    if (!dateString) return '';
    const date = new Date(dateString);
    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    return date.getDate() + ' ' + months[date.getMonth()] + ' ' + date.getFullYear();
}
</script>

<?php
include '../../includes/footer.php';
?>