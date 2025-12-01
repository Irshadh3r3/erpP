<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';

requireLogin();

$conn = getDBConnection();
$pageTitle = 'Edit Customer';

$errors = [];
$customerId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($customerId <= 0) {
    $_SESSION['error_message'] = 'Invalid customer ID';
    header('Location: list.php');
    exit;
}

// Get customer details
$customerQuery = "SELECT * FROM customers WHERE id = $customerId";
$customerResult = $conn->query($customerQuery);

if ($customerResult->num_rows === 0) {
    $_SESSION['error_message'] = 'Customer not found';
    header('Location: list.php');
    exit;
}

$customer = $customerResult->fetch_assoc();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customer_code = clean($_POST['customer_code']);
    $name = clean($_POST['name']);
    $business_name = clean($_POST['business_name']);
    $contact_person = clean($_POST['contact_person']);
    $phone = clean($_POST['phone']);
    $email = clean($_POST['email']);
    $address = clean($_POST['address']);
    $city = clean($_POST['city']);
    $area = clean($_POST['area']);
    $credit_limit = (float)$_POST['credit_limit'];
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    // Validation
    if (empty($customer_code)) {
        $errors[] = 'Customer code is required';
    }
    if (empty($name)) {
        $errors[] = 'Customer name is required';
    }
    if (empty($phone)) {
        $errors[] = 'Phone number is required';
    }
    if ($credit_limit < 0) {
        $errors[] = 'Credit limit cannot be negative';
    }
    
    // Check if customer code already exists (excluding current customer)
    $checkQuery = "SELECT id FROM customers WHERE customer_code = '$customer_code' AND id != $customerId";
    $checkResult = $conn->query($checkQuery);
    if ($checkResult->num_rows > 0) {
        $errors[] = 'Customer code already exists';
    }
    
    // If no errors, update customer
    if (empty($errors)) {
        $stmt = $conn->prepare("UPDATE customers SET customer_code = ?, name = ?, business_name = ?, contact_person = ?, phone = ?, email = ?, address = ?, city = ?, area = ?, credit_limit = ?, is_active = ? WHERE id = ?");
        $stmt->bind_param("sssssssssdii", $customer_code, $name, $business_name, $contact_person, $phone, $email, $address, $city, $area, $credit_limit, $is_active, $customerId);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = 'Customer updated successfully!';
            header('Location: view.php?id=' . $customerId);
            exit;
        } else {
            $errors[] = 'Error updating customer: ' . $conn->error;
        }
    }
} else {
    // Pre-fill form with existing data
    $_POST = $customer;
}

include '../../includes/header.php';
?>

<!-- Page Header -->
<div class="mb-6">
    <h1 class="text-3xl font-bold text-gray-800">Edit Customer</h1>
    <p class="text-gray-600">Update customer information</p>
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

<!-- Edit Customer Form -->
<form method="POST" action="" class="bg-white rounded-lg shadow p-6">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Customer Code -->
        <div>
            <label for="customer_code" class="block text-gray-700 font-semibold mb-2">Customer Code <span class="text-red-500">*</span></label>
            <input type="text" 
                   id="customer_code" 
                   name="customer_code" 
                   value="<?php echo $_POST['customer_code']; ?>"
                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                   required>
        </div>

        <!-- Customer Name -->
        <div>
            <label for="name" class="block text-gray-700 font-semibold mb-2">Customer Name <span class="text-red-500">*</span></label>
            <input type="text" 
                   id="name" 
                   name="name" 
                   value="<?php echo $_POST['name']; ?>"
                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                   required>
        </div>

        <!-- Business Name -->
        <div>
            <label for="business_name" class="block text-gray-700 font-semibold mb-2">Business Name</label>
            <input type="text" 
                   id="business_name" 
                   name="business_name" 
                   value="<?php echo $_POST['business_name']; ?>"
                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>

        <!-- Contact Person -->
        <div>
            <label for="contact_person" class="block text-gray-700 font-semibold mb-2">Contact Person</label>
            <input type="text" 
                   id="contact_person" 
                   name="contact_person" 
                   value="<?php echo $_POST['contact_person']; ?>"
                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>

        <!-- Phone -->
        <div>
            <label for="phone" class="block text-gray-700 font-semibold mb-2">Phone <span class="text-red-500">*</span></label>
            <input type="text" 
                   id="phone" 
                   name="phone" 
                   value="<?php echo $_POST['phone']; ?>"
                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                   required>
        </div>

        <!-- Email -->
        <div>
            <label for="email" class="block text-gray-700 font-semibold mb-2">Email</label>
            <input type="email" 
                   id="email" 
                   name="email" 
                   value="<?php echo $_POST['email']; ?>"
                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>

        <!-- City -->
        <div>
            <label for="city" class="block text-gray-700 font-semibold mb-2">City</label>
            <input type="text" 
                   id="city" 
                   name="city" 
                   value="<?php echo $_POST['city']; ?>"
                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>

        <!-- Area -->
        <div>
            <label for="area" class="block text-gray-700 font-semibold mb-2">Area</label>
            <input type="text" 
                   id="area" 
                   name="area" 
                   value="<?php echo $_POST['area']; ?>"
                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>

        <!-- Credit Limit -->
        <div>
            <label for="credit_limit" class="block text-gray-700 font-semibold mb-2">Credit Limit</label>
            <input type="number" 
                   id="credit_limit" 
                   name="credit_limit" 
                   value="<?php echo $_POST['credit_limit']; ?>"
                   step="0.01"
                   min="0"
                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>

        <!-- Address -->
        <div class="md:col-span-2">
            <label for="address" class="block text-gray-700 font-semibold mb-2">Address</label>
            <textarea id="address" 
                      name="address" 
                      rows="3"
                      class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"><?php echo $_POST['address']; ?></textarea>
        </div>

        <!-- Active Status -->
        <div class="md:col-span-2">
            <label class="flex items-center">
                <input type="checkbox" 
                       name="is_active" 
                       <?php echo $_POST['is_active'] == 1 ? 'checked' : ''; ?>
                       class="w-5 h-5 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                <span class="ml-2 text-gray-700 font-semibold">Active</span>
            </label>
            <p class="text-xs text-gray-500 mt-1">Uncheck to deactivate this customer</p>
        </div>
    </div>

    <!-- Form Actions -->
    <div class="flex items-center justify-end gap-4 mt-6 pt-6 border-t">
        <a href="view.php?id=<?php echo $customerId; ?>" class="px-6 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 font-semibold transition">
            Cancel
        </a>
        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg font-semibold transition">
            Update Customer
        </button>
    </div>
</form>

<?php
include '../../includes/footer.php';
?>