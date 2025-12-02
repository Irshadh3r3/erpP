<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';

requireLogin();

$conn = getDBConnection();
$pageTitle = 'Edit Booker';

$errors = [];
$bookerId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($bookerId <= 0) {
    $_SESSION['error_message'] = 'Invalid booker ID';
    header('Location: list.php');
    exit;
}

// Get booker details
$bookerQuery = "SELECT * FROM bookers WHERE id = $bookerId";
$bookerResult = $conn->query($bookerQuery);

if ($bookerResult->num_rows === 0) {
    $_SESSION['error_message'] = 'Booker not found';
    header('Location: list.php');
    exit;
}

$booker = $bookerResult->fetch_assoc();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $booker_code = clean($_POST['booker_code']);
    $name = clean($_POST['name']);
    $phone = clean($_POST['phone']);
    $email = clean($_POST['email']);
    $address = clean($_POST['address']);
    $area = clean($_POST['area']);
    $commission_percentage = (float)$_POST['commission_percentage'];
    $cnic = clean($_POST['cnic']);
    $joining_date = clean($_POST['joining_date']);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    // Validation
    if (empty($booker_code)) {
        $errors[] = 'Booker code is required';
    }
    if (empty($name)) {
        $errors[] = 'Name is required';
    }
    if (empty($phone)) {
        $errors[] = 'Phone is required';
    }
    if ($commission_percentage < 0 || $commission_percentage > 100) {
        $errors[] = 'Commission percentage must be between 0 and 100';
    }
    
    // Check if booker code already exists (excluding current booker)
    $checkQuery = "SELECT id FROM bookers WHERE booker_code = '$booker_code' AND id != $bookerId";
    $checkResult = $conn->query($checkQuery);
    if ($checkResult->num_rows > 0) {
        $errors[] = 'Booker code already exists';
    }
    
    // If no errors, update booker
    if (empty($errors)) {
        $stmt = $conn->prepare("UPDATE bookers SET booker_code = ?, name = ?, phone = ?, email = ?, address = ?, area = ?, commission_percentage = ?, cnic = ?, joining_date = ?, is_active = ? WHERE id = ?");
        $stmt->bind_param("ssssssdssii", $booker_code, $name, $phone, $email, $address, $area, $commission_percentage, $cnic, $joining_date, $is_active, $bookerId);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = 'Booker updated successfully!';
            header('Location: view.php?id=' . $bookerId);
            exit;
        } else {
            $errors[] = 'Error updating booker: ' . $conn->error;
        }
    }
} else {
    // Pre-fill form with existing data
    $_POST = $booker;
}

include '../../includes/header.php';
?>

<!-- Page Header -->
<div class="mb-6">
    <h1 class="text-3xl font-bold text-gray-800">Edit Booker</h1>
    <p class="text-gray-600">Update booker information</p>
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

<!-- Edit Booker Form -->
<form method="POST" action="" class="bg-white rounded-lg shadow p-6">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Booker Code -->
        <div>
            <label for="booker_code" class="block text-gray-700 font-semibold mb-2">Booker Code <span class="text-red-500">*</span></label>
            <input type="text" 
                   id="booker_code" 
                   name="booker_code" 
                   value="<?php echo $_POST['booker_code']; ?>"
                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                   required>
        </div>

        <!-- Name -->
        <div>
            <label for="name" class="block text-gray-700 font-semibold mb-2">Full Name <span class="text-red-500">*</span></label>
            <input type="text" 
                   id="name" 
                   name="name" 
                   value="<?php echo $_POST['name']; ?>"
                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                   required>
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

        <!-- Area -->
        <div>
            <label for="area" class="block text-gray-700 font-semibold mb-2">Area/Territory</label>
            <input type="text" 
                   id="area" 
                   name="area" 
                   value="<?php echo $_POST['area']; ?>"
                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>

        <!-- Commission Percentage -->
        <div>
            <label for="commission_percentage" class="block text-gray-700 font-semibold mb-2">Commission %</label>
            <input type="number" 
                   id="commission_percentage" 
                   name="commission_percentage" 
                   value="<?php echo $_POST['commission_percentage']; ?>"
                   step="0.01"
                   min="0"
                   max="100"
                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>

        <!-- CNIC -->
        <div>
            <label for="cnic" class="block text-gray-700 font-semibold mb-2">CNIC</label>
            <input type="text" 
                   id="cnic" 
                   name="cnic" 
                   value="<?php echo $_POST['cnic']; ?>"
                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>

        <!-- Joining Date -->
        <div>
            <label for="joining_date" class="block text-gray-700 font-semibold mb-2">Joining Date</label>
            <input type="date" 
                   id="joining_date" 
                   name="joining_date" 
                   value="<?php echo $_POST['joining_date']; ?>"
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
            <p class="text-xs text-gray-500 mt-1">Uncheck to deactivate this booker</p>
        </div>
    </div>

    <!-- Form Actions -->
    <div class="flex items-center justify-end gap-4 mt-6 pt-6 border-t">
        <a href="view.php?id=<?php echo $bookerId; ?>" class="px-6 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 font-semibold transition">
            Cancel
        </a>
        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg font-semibold transition">
            Update Booker
        </button>
    </div>
</form>

<?php
include '../../includes/footer.php';
?>