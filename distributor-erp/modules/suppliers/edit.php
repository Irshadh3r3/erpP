<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';

requireLogin();

$conn = getDBConnection();
$pageTitle = 'Edit Supplier';

$errors = [];
$supplierId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($supplierId <= 0) {
    $_SESSION['error_message'] = 'Invalid supplier ID';
    header('Location: list.php');
    exit;
}

// Get supplier details
$supplierQuery = "SELECT * FROM suppliers WHERE id = $supplierId";
$supplierResult = $conn->query($supplierQuery);

if ($supplierResult->num_rows === 0) {
    $_SESSION['error_message'] = 'Supplier not found';
    header('Location: list.php');
    exit;
}

$supplier = $supplierResult->fetch_assoc();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $supplier_code = clean($_POST['supplier_code']);
    $name = clean($_POST['name']);
    $contact_person = clean($_POST['contact_person']);
    $phone = clean($_POST['phone']);
    $email = clean($_POST['email']);
    $address = clean($_POST['address']);
    $city = clean($_POST['city']);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    // Validation
    if (empty($supplier_code)) {
        $errors[] = 'Supplier code is required';
    }
    if (empty($name)) {
        $errors[] = 'Supplier name is required';
    }
    if (empty($phone)) {
        $errors[] = 'Phone number is required';
    }
    
    // Check if supplier code already exists (excluding current supplier)
    $checkQuery = "SELECT id FROM suppliers WHERE supplier_code = '$supplier_code' AND id != $supplierId";
    $checkResult = $conn->query($checkQuery);
    if ($checkResult->num_rows > 0) {
        $errors[] = 'Supplier code already exists';
    }
    
    // If no errors, update supplier
    if (empty($errors)) {
        $stmt = $conn->prepare("UPDATE suppliers SET supplier_code = ?, name = ?, contact_person = ?, phone = ?, email = ?, address = ?, city = ?, is_active = ? WHERE id = ?");
        $stmt->bind_param("sssssssii", $supplier_code, $name, $contact_person, $phone, $email, $address, $city, $is_active, $supplierId);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = 'Supplier updated successfully!';
            header('Location: view.php?id=' . $supplierId);
            exit;
        } else {
            $errors[] = 'Error updating supplier: ' . $conn->error;
        }
    }
} else {
    // Pre-fill form with existing data
    $_POST = $supplier;
}

include '../../includes/header.php';
?>

<!-- Page Header -->
<div class="mb-6">
    <h1 class="text-3xl font-bold text-gray-800">Edit Supplier</h1>
    <p class="text-gray-600">Update supplier information</p>
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

<!-- Edit Supplier Form -->
<form method="POST" action="" class="bg-white rounded-lg shadow p-6">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Supplier Code -->
        <div>
            <label for="supplier_code" class="block text-gray-700 font-semibold mb-2">Supplier Code <span class="text-red-500">*</span></label>
            <input type="text" 
                   id="supplier_code" 
                   name="supplier_code" 
                   value="<?php echo $_POST['supplier_code']; ?>"
                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                   required>
        </div>

        <!-- Supplier Name -->
        <div>
            <label for="name" class="block text-gray-700 font-semibold mb-2">Supplier Name <span class="text-red-500">*</span></label>
            <input type="text" 
                   id="name" 
                   name="name" 
                   value="<?php echo $_POST['name']; ?>"
                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                   required>
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
            <p class="text-xs text-gray-500 mt-1">Uncheck to deactivate this supplier</p>
        </div>
    </div>

    <!-- Form Actions -->
    <div class="flex items-center justify-end gap-4 mt-6 pt-6 border-t">
        <a href="view.php?id=<?php echo $supplierId; ?>" class="px-6 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 font-semibold transition">
            Cancel
        </a>
        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg font-semibold transition">
            Update Supplier
        </button>
    </div>
</form>

<?php
include '../../includes/footer.php';
?>