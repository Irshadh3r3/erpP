<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';

requireLogin();

$conn = getDBConnection();
$pageTitle = 'Add Booker';

$errors = [];

// Auto-generate booker code
$autoCode = generateBookerCode($conn);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $booker_code = !empty(clean($_POST['booker_code'])) ? clean($_POST['booker_code']) : $autoCode;
    $name = clean($_POST['name']);
    $phone = clean($_POST['phone']);
    $email = clean($_POST['email']);
    $address = clean($_POST['address']);
    $area = clean($_POST['area']);
    $commission_percentage = (float)$_POST['commission_percentage'];
    $cnic = clean($_POST['cnic']);
    $joining_date = clean($_POST['joining_date']);
    
    // Validation
    if (empty($name)) {
        $errors[] = 'Name is required';
    }
    if (empty($phone)) {
        $errors[] = 'Phone is required';
    }
    if ($commission_percentage < 0 || $commission_percentage > 100) {
        $errors[] = 'Commission percentage must be between 0 and 100';
    }
    
    // Check if booker code already exists
    $checkQuery = "SELECT id FROM bookers WHERE booker_code = '$booker_code'";
    $checkResult = $conn->query($checkQuery);
    if ($checkResult->num_rows > 0) {
        $errors[] = 'Booker code already exists';
    }
    
    // If no errors, insert booker
    if (empty($errors)) {
        $stmt = $conn->prepare("INSERT INTO bookers (booker_code, name, phone, email, address, area, commission_percentage, cnic, joining_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssssdss", $booker_code, $name, $phone, $email, $address, $area, $commission_percentage, $cnic, $joining_date);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = 'Booker added successfully!';
            header('Location: list.php');
            exit;
        } else {
            $errors[] = 'Error adding booker: ' . $conn->error;
        }
    }
}

include '../../includes/header.php';
?>

<!-- Page Header -->
<div class="mb-6">
    <h1 class="text-3xl font-bold text-gray-800">Add New Booker</h1>
    <p class="text-gray-600">Register a new sales booker</p>
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

<!-- Add Booker Form -->
<form method="POST" action="" class="bg-white rounded-lg shadow p-6">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Booker Code -->
        <div>
            <label for="booker_code" class="block text-gray-700 font-semibold mb-2">Booker Code</label>
            <input type="text" 
                   id="booker_code" 
                   name="booker_code" 
                   value="<?php echo isset($_POST['booker_code']) && !empty($_POST['booker_code']) ? $_POST['booker_code'] : $autoCode; ?>"
                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                   placeholder="Auto-generated">
            <p class="text-xs text-gray-500 mt-1">Leave blank for auto-generation</p>
        </div>

        <!-- Name -->
        <div>
            <label for="name" class="block text-gray-700 font-semibold mb-2">Full Name <span class="text-red-500">*</span></label>
            <input type="text" 
                   id="name" 
                   name="name" 
                   value="<?php echo isset($_POST['name']) ? $_POST['name'] : ''; ?>"
                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                   placeholder="Enter booker name"
                   required>
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
                   placeholder="booker@example.com">
        </div>

        <!-- Area -->
        <div>
            <label for="area" class="block text-gray-700 font-semibold mb-2">Area/Territory</label>
            <input type="text" 
                   id="area" 
                   name="area" 
                   value="<?php echo isset($_POST['area']) ? $_POST['area'] : ''; ?>"
                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                   placeholder="e.g., Saddar, Clifton, etc.">
        </div>

        <!-- Commission Percentage -->
        <div>
            <label for="commission_percentage" class="block text-gray-700 font-semibold mb-2">Commission %</label>
            <input type="number" 
                   id="commission_percentage" 
                   name="commission_percentage" 
                   value="<?php echo isset($_POST['commission_percentage']) ? $_POST['commission_percentage'] : '2.5'; ?>"
                   step="0.01"
                   min="0"
                   max="100"
                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                   placeholder="2.5">
            <p class="text-xs text-gray-500 mt-1">Commission percentage on sales</p>
        </div>

        <!-- CNIC -->
        <div>
            <label for="cnic" class="block text-gray-700 font-semibold mb-2">CNIC</label>
            <input type="text" 
                   id="cnic" 
                   name="cnic" 
                   value="<?php echo isset($_POST['cnic']) ? $_POST['cnic'] : ''; ?>"
                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                   placeholder="XXXXX-XXXXXXX-X">
        </div>

        <!-- Joining Date -->
        <div>
            <label for="joining_date" class="block text-gray-700 font-semibold mb-2">Joining Date</label>
            <input type="date" 
                   id="joining_date" 
                   name="joining_date" 
                   value="<?php echo isset($_POST['joining_date']) ? $_POST['joining_date'] : date('Y-m-d'); ?>"
                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
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
            Add Booker
        </button>
    </div>
</form>

<?php
include '../../includes/footer.php';
?>