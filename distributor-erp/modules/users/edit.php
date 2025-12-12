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
$pageTitle = 'Edit User';

$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$user_id) {
    $_SESSION['error_message'] = 'Invalid user ID';
    header('Location: list.php');
    exit;
}

// Fetch user
$stmt = $conn->prepare("SELECT id, username, full_name, email, role, is_active FROM users WHERE id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    $_SESSION['error_message'] = 'User not found';
    header('Location: list.php');
    exit;
}

$errors = [];
$form_data = $user;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form_data = [
        'id' => $user_id,
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
    if (!filter_var($form_data['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email format';
    if ($form_data['password'] && strlen($form_data['password']) < 6) $errors[] = 'Password must be at least 6 characters';
    if (!in_array($form_data['role'], ['admin', 'manager', 'cashier', 'inventory'])) $errors[] = 'Invalid role';

    // Check username uniqueness (excluding current user)
    if (!$errors) {
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $stmt->bind_param('si', $form_data['username'], $user_id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $errors[] = 'Username already exists';
        }
    }

    // Check email uniqueness (excluding current user)
    if (!$errors) {
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->bind_param('si', $form_data['email'], $user_id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $errors[] = 'Email already exists';
        }
    }

    // Update user
    if (!$errors) {
        if ($form_data['password']) {
            // Update with password
            $hashed_password = password_hash($form_data['password'], PASSWORD_BCRYPT);
            $stmt = $conn->prepare("UPDATE users SET username = ?, full_name = ?, email = ?, password = ?, role = ?, is_active = ? WHERE id = ?");
            $stmt->bind_param('sssssii', $form_data['username'], $form_data['full_name'], $form_data['email'], $hashed_password, $form_data['role'], $form_data['is_active'], $user_id);
        } else {
            // Update without password
            $stmt = $conn->prepare("UPDATE users SET username = ?, full_name = ?, email = ?, role = ?, is_active = ? WHERE id = ?");
            $stmt->bind_param('ssssii', $form_data['username'], $form_data['full_name'], $form_data['email'], $form_data['role'], $form_data['is_active'], $user_id);
        }
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = 'User updated successfully';
            header('Location: list.php');
            exit;
        } else {
            $errors[] = 'Error updating user: ' . $conn->error;
        }
    }
}

include '../../includes/header.php';
?>

<div class="mb-6">
    <h1 class="text-3xl font-bold text-gray-800 mb-2">Edit User</h1>
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
            <label class="block text-sm font-semibold text-gray-700 mb-1">Password (leave blank to keep current)</label>
            <input type="password" name="password"
                   class="w-full px-4 py-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500"
                   placeholder="Leave blank to keep current password">
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
                Update User
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
