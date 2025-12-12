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
$pageTitle = 'Role Permissions';

$errors = [];
$success = false;

// Get selected role (default to cashier)
$selected_role = isset($_GET['role']) ? $_GET['role'] : 'cashier';
if (!in_array($selected_role, ['admin', 'manager', 'cashier', 'inventory'])) {
    $selected_role = 'cashier';
}

// Handle permission updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($_POST as $key => $value) {
        // Parse format: "module_permission_role"
        if (preg_match('/^perm_(.+?)_(.+?)_(.+?)$/', $key, $matches)) {
            $module = $matches[1];
            $permission = $matches[2];
            $role = $matches[3];
            
            $can_access = isset($_POST[$key]) ? 1 : 0;
            
            if (!updateRolePermission($conn, $role, $module, $permission, $can_access)) {
                $errors[] = "Error updating permission for $module ($permission)";
            }
        }
    }
    
    if (empty($errors)) {
        $_SESSION['success_message'] = 'Permissions updated successfully';
        header('Location: permissions.php?role=' . urlencode($selected_role));
        exit;
    }
}

// Get all permissions for selected role
$permissions = getRolePermissions($conn, $selected_role);

// All possible modules and permissions
$all_modules = ['products', 'categories', 'customers', 'suppliers', 'bookings', 'purchases', 'sales', 'payments', 'bookers', 'reports', 'users', 'settings'];
$all_permissions = ['view', 'add', 'edit', 'delete', 'export'];

include '../../includes/header.php';
?>

<div class="mb-6">
    <h1 class="text-3xl font-bold text-gray-800 mb-4">Role Permissions Management</h1>
    
    <!-- Role Selector -->
    <div class="bg-white shadow-md rounded-lg p-4 mb-6">
        <form method="get" class="flex items-end gap-4">
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Select Role</label>
                <select name="role" onchange="this.form.submit();" class="px-4 py-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="admin" <?php echo $selected_role === 'admin' ? 'selected' : ''; ?>>Admin</option>
                    <option value="manager" <?php echo $selected_role === 'manager' ? 'selected' : ''; ?>>Manager</option>
                    <option value="cashier" <?php echo $selected_role === 'cashier' ? 'selected' : ''; ?>>Cashier</option>
                    <option value="inventory" <?php echo $selected_role === 'inventory' ? 'selected' : ''; ?>>Inventory</option>
                </select>
            </div>
            <p class="text-gray-600 text-sm">
                <strong><?php echo htmlspecialchars($selected_role); ?></strong> role permissions
                <?php if ($selected_role === 'admin'): ?>
                    <span class="text-red-600 ml-2">(Admin has full access)</span>
                <?php endif; ?>
            </p>
        </form>
    </div>
</div>

<?php if (!empty($errors)): ?>
<div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
    <ul class="list-disc pl-5">
        <?php foreach ($errors as $error): ?>
        <li><?php echo htmlspecialchars($error); ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<?php if ($selected_role === 'admin'): ?>
<div class="bg-blue-50 border border-blue-200 rounded-lg p-6 mb-6">
    <h3 class="text-lg font-semibold text-blue-900 mb-2">Admin Role</h3>
    <p class="text-blue-800">Admin users have full access to all modules and features. Their permissions cannot be modified.</p>
</div>
<?php else: ?>

<form method="POST" class="bg-white shadow-md rounded-lg overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-100 border-b">
                <tr>
                    <th class="px-6 py-3 text-left text-sm font-semibold text-gray-700">Module</th>
                    <th class="px-6 py-3 text-center text-sm font-semibold text-gray-700">View</th>
                    <th class="px-6 py-3 text-center text-sm font-semibold text-gray-700">Add</th>
                    <th class="px-6 py-3 text-center text-sm font-semibold text-gray-700">Edit</th>
                    <th class="px-6 py-3 text-center text-sm font-semibold text-gray-700">Delete</th>
                    <th class="px-6 py-3 text-center text-sm font-semibold text-gray-700">Export</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($all_modules as $module): ?>
                <tr class="border-b hover:bg-gray-50">
                    <td class="px-6 py-4 text-sm font-semibold text-gray-800">
                        <?php echo ucfirst(str_replace('_', ' ', $module)); ?>
                    </td>
                    
                    <?php foreach ($all_permissions as $perm): ?>
                    <td class="px-6 py-4 text-center">
                        <div class="flex justify-center">
                            <label class="flex items-center">
                                <input type="checkbox" 
                                       name="perm_<?php echo $module; ?>_<?php echo $perm; ?>_<?php echo $selected_role; ?>"
                                       <?php echo isset($permissions[$module][$perm]) && $permissions[$module][$perm] ? 'checked' : ''; ?>
                                       class="w-5 h-5 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 cursor-pointer">
                            </label>
                        </div>
                    </td>
                    <?php endforeach; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <div class="p-6 bg-gray-50 border-t flex gap-4">
        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded font-semibold transition">
            Save Permissions
        </button>
        <a href="../../index.php" class="bg-gray-400 hover:bg-gray-500 text-white px-6 py-2 rounded font-semibold transition">
            Cancel
        </a>
    </div>
</form>

<?php endif; ?>

<!-- Reference Table: Default Permissions -->
<div class="mt-8 bg-white shadow-md rounded-lg p-6">
    <h2 class="text-xl font-bold text-gray-800 mb-4">Default Role Permissions Reference</h2>
    
    <div class="space-y-4">
        <div class="border-l-4 border-red-500 pl-4">
            <h3 class="font-semibold text-red-700">Admin</h3>
            <p class="text-gray-600 text-sm">Full access to all modules and features.</p>
        </div>
        
        <div class="border-l-4 border-blue-500 pl-4">
            <h3 class="font-semibold text-blue-700">Manager</h3>
            <p class="text-gray-600 text-sm">Can manage products, customers, bookings, purchases, sales, payments, reports. Cannot access users and settings.</p>
        </div>
        
        <div class="border-l-4 border-green-500 pl-4">
            <h3 class="font-semibold text-green-700">Cashier</h3>
            <p class="text-gray-600 text-sm">Can view products/customers, create sales/payments, add quick customers. Limited to sales processing.</p>
        </div>
        
        <div class="border-l-4 border-yellow-500 pl-4">
            <h3 class="font-semibold text-yellow-700">Inventory</h3>
            <p class="text-gray-600 text-sm">Can manage products, categories, suppliers, purchases. Cannot modify sales or user accounts.</p>
        </div>
    </div>
</div>

</main>
</div>
<?php include '../../includes/footer.php'; ?>
