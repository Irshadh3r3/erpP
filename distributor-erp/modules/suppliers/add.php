<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';

requireLogin();

$conn = getDBConnection();
$pageTitle = 'Add Supplier';

$errors = [];

// Auto-generate supplier code
$prefix = 'SUP-';
$query = "SELECT supplier_code FROM suppliers WHERE supplier_code LIKE '{$prefix}%' ORDER BY id DESC LIMIT 1";
$result = $conn->query($query);

if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $lastNumber = intval(substr($row['supplier_code'], strlen($prefix)));
    $newNumber = $lastNumber + 1;
} else {
    $newNumber = 1;
}

$autoCode = $prefix . str_pad($newNumber, 4, '0', STR_PAD_LEFT);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $supplier_code = !empty(clean($_POST['supplier_code'])) ? clean($_POST['supplier_code']) : $autoCode;
    $name = clean($_POST['name']);
    $contact_person = clean($_POST['contact_person']);
    $phone = clean($_POST['phone']);
    $email = clean($_POST['email']);
    $address = clean($_POST['address']);
    $city = clean($_POST['city']);
    
    // Validation
    if (empty($name)) {
        $errors[] = 'Supplier name is required';
    }
    if (empty($phone)) {
        $errors[] = 'Phone number is required';
    }
    
    // Check if supplier code already exists
    $checkQuery = "SELECT id FROM suppliers WHERE supplier_code = '$supplier_code'";
    $checkResult = $conn->query($checkQuery);
    if ($checkResult->num_rows > 0) {
        $errors[] = 'Supplier code already exists';
    }
    
    // If no errors, insert supplier
    if (empty($errors)) {
        $stmt = $conn->prepare("INSERT INTO suppliers (supplier_code, name, contact_person, phone, email, address, city) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssss", $supplier_code, $name, $contact_person, $phone, $email, $address, $city);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = 'Supplier added successfully!';
            header('Location: list.php');
            exit;
        } else {
            $errors[] = 'Error adding supplier: ' . $conn->error;
        }
    }
}

include '../../includes/header.php';
?>

<!-- Page Header -->
<div class="mb-6">
    <h1 class="text-3xl font-bold text-gray-800">Add New Supplier</h1>
    <p class="text-gray-600">Register a new supplier in the system</p>
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

<!-- Add Supplier Form -->
<form method="POST" action="" class="bg-white rounded-lg shadow p-6">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Supplier Code -->
        <div>
            <label for="supplier_code" class="block text-gray-700 font-semibold mb-2">Supplier Code</label>
            <input type="text" 
                   id="supplier_code" 
                   name="supplier_code" 
                   value="<?php echo isset($_POST['supplier_code']) && !empty($_POST['supplier_code']) ? $_POST['supplier_code'] : $autoCode; ?>"
                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                   placeholder="Auto-generated">
            <p class="text-xs text-gray-500 mt-1">Leave blank for auto-generation</p>
        </div>

        <!-- Supplier Name -->
        <div>
            <label for="name" class="block text-gray-700 font-semibold mb-2">Supplier Name <span class="text-red-500">*</span></label>
            <input type="text" 
                   id="name" 
                   name="name" 
                   value="<?php echo isset($_POST['name']) ? $_POST['name'] : ''; ?>"
                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                   placeholder="Enter supplier/company name"
                   required>
        </div>

        <!-- Contact Person -->
        <div>
            <label for="contact_person" class="block text-gray-700 font-semibold mb-2">Contact Person</label>
            <input type="text" 
                   id="contact_person" 
                   name="contact_person" 
                   value="<?php echo isset($_POST['contact_person']) ? $_POST['contact_person'] : ''; ?>"
                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                   placeholder="Name of contact person">
        </div>

        <!-- Phone -->
        <div>
            <label for="phone" class="block text-gray-700 font-semibold mb-2">Phone <span class="text-red-500">*</span></label>
            <input type="text" 
                   id="phone" 
                   name="phone" 
                   value="<?php echo isset($_POST['phone']) ? $_POST['phone'] : ''; ?>"
                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                   placeholder="03XX-XXXXXXX"
                   required>
        </div>

        <!-- Email -->
        <div>
            <label for="email" class="block text-gray-700 font-semibold mb-2">Email</label>
            <input type="email" 
                   id="email" 
                   name="email" 
                   value="<?php echo isset($_POST['email']) ? $_POST['email'] : ''; ?>"
                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                   placeholder="supplier@example.com">
        </div>

        <!-- City -->
        <div>
            <label for="city" class="block text-gray-700 font-semibold mb-2">City</label>
            <input type="text" 
                   id="city" 
                   name="city" 
                   value="<?php echo isset($_POST['city']) ? $_POST['city'] : 'Karachi'; ?>"
                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                   placeholder="e.g., Karachi">
        </div>

        <!-- Address -->
        <div class="md:col-span-2">
            <label for="address" class="block text-gray-700 font-semibold mb-2">Address</label>
            <textarea id="address" 
                      name="address" 
                      rows="3"
                      class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                      placeholder="Enter complete address..."><?php echo isset($_POST['address']) ? $_POST['address'] : ''; ?></textarea>
        </div>
    </div>

    <!-- Form Actions -->
    <div class="flex items-center justify-end gap-4 mt-6 pt-6 border-t">
        <a href="list.php" class="px-6 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 font-semibold transition">
            Cancel
        </a>
        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg font-semibold transition">
            Add Supplier
        </button>
    </div>
</form>

<?php
include '../../includes/footer.php';
?>