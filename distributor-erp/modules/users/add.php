<?php
session_start();
require '../../config/database.php';
require '../../includes/functions.php';

// Admin-only access
if ($_SESSION['role'] !== 'admin') {
    $_SESSION['error_message'] = 'Access denied. Admin privileges required.';
    header('Location: ../../index.php');
    exit;
}

$conn = getDBConnection();
$pageTitle = 'Add User';

$errors = [];
$form_data = [
    'username' => '',
    'full_name' => '',
    'email' => '',
    'password' => '',
    'role' => 'cashier',
    'is_active' => 1
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form_data = [
        'username' => trim($_POST['username'] ?? ''),
        'full_name' => trim($_POST['full_name'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'password' => $_POST['password'] ?? '',
        'role' => $_POST['role'] ?? 'cashier',
        'is_active' => isset($_POST['is_active']) ? 1 : 0
    ];

    // Validation
    if (empty($form_data['username'])) $errors[] = 'Username is required';
    if (empty($form_data['full_name'])) $errors[] = 'Full name is required';
    if (empty($form_data['email'])) $errors[] = 'Email is required';
    if (empty($form_data['password'])) $errors[] = 'Password is required';
    if (!filter_var($form_data['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email format';
    if (strlen($form_data['password']) < 6) $errors[] = 'Password must be at least 6 characters';
    if (!in_array($form_data['role'], ['admin', 'manager', 'cashier', 'inventory'])) $errors[] = 'Invalid role';

    // Check username uniqueness
    if (!$errors) {
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->bind_param('s', $form_data['username']);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $errors[] = 'Username already exists';
        }
    }

    // Check email uniqueness
    if (!$errors) {
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param('s', $form_data['email']);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $errors[] = 'Email already exists';
        }
    }

    // Insert user
    if (!$errors) {
        $hashed_password = password_hash($form_data['password'], PASSWORD_BCRYPT);
        $stmt = $conn->prepare("INSERT INTO users (username, full_name, email, password, role, is_active, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param('sssssi', $form_data['username'], $form_data['full_name'], $form_data['email'], $hashed_password, $form_data['role'], $form_data['is_active']);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = 'User created successfully';
            header('Location: list.php');
            exit;
        } else {
            $errors[] = 'Error creating user: ' . $conn->error;
        }
    }
}

include '../../includes/header.php';
?>

<div class="mb-6">
    <h1 class="text-3xl font-bold text-gray-800 mb-2">Add New User</h1>
    <a href="list.php" class="text-blue-600 hover:text-blue-800">← Back to Users</a>
</div>

<div class="bg-white shadow-md rounded-lg p-6 max-w-2xl">
    <?php if (!empty($errors)): ?>
    <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
        <ul class="list-disc pl-5">
            <?php foreach ($errors as $error): ?>
            <li><?php echo htmlspecialchars($error); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <form method="POST" class="space-y-4">
        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">Username *</label>
            <input type="text" name="username" value="<?php echo htmlspecialchars($form_data['username']); ?>" required
                   class="w-full px-4 py-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>

        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">Full Name *</label>
            <input type="text" name="full_name" value="<?php echo htmlspecialchars($form_data['full_name']); ?>" required
                   class="w-full px-4 py-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>

        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">Email *</label>
            <input type="email" name="email" value="<?php echo htmlspecialchars($form_data['email']); ?>" required
                   class="w-full px-4 py-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>

        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">Password *</label>
            <input type="password" name="password" required
                   class="w-full px-4 py-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500"
                   placeholder="Min 6 characters">
        </div>

        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">Role *</label>
            <select name="role" required class="w-full px-4 py-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="admin" <?php echo $form_data['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                <option value="manager" <?php echo $form_data['role'] === 'manager' ? 'selected' : ''; ?>>Manager</option>
                <option value="cashier" <?php echo $form_data['role'] === 'cashier' ? 'selected' : ''; ?>>Cashier</option>
                <option value="inventory" <?php echo $form_data['role'] === 'inventory' ? 'selected' : ''; ?>>Inventory</option>
            </select>
        </div>

        <div class="flex items-center space-x-2">
            <input type="checkbox" name="is_active" id="is_active" <?php echo $form_data['is_active'] ? 'checked' : ''; ?>
                   class="w-4 h-4 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500">
            <label for="is_active" class="text-sm font-semibold text-gray-700">Active</label>
        </div>

        <div class="flex space-x-4 pt-4">
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded font-semibold transition">
                Create User
            </button>
            <a href="list.php" class="bg-gray-400 hover:bg-gray-500 text-white px-6 py-2 rounded font-semibold transition">
                Cancel
            </a>
        </div>
    </form>
</div>

</main>
</div>
<?php include '../../includes/footer.php'; ?>
