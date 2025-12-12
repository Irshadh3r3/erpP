<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';

requireLogin();
if (!hasRole('admin')) {
    $_SESSION['error_message'] = 'Access denied';
    header('Location: /distributor-erp/index.php');
    exit;
}

$conn = getDBConnection();
$pageTitle = 'Settings';

$errors = [];
success: $success = '';

// Load current settings
$settings = [];
$settingKeys = ['app_name','currency_symbol','currency_code','invoice_prefix','decimal_precision','enable_fractional_reorder','default_volume_unit','use_tomselect'];
$settingKeys = ['app_name','currency_symbol','currency_code','invoice_prefix','decimal_precision','enable_fractional_reorder','default_volume_unit','use_tomselect','company_name','company_email','company_phone','company_address','invoice_footer','timezone'];
foreach ($settingKeys as $k) {
    $settings[$k] = getSetting($conn, $k, '');
}

// Current logged-in user details for account management
$currentUser = null;
if (isset($_SESSION['user_id'])) {
    $uid = (int)$_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT username, full_name, email FROM users WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $row = $res->fetch_assoc()) $currentUser = $row;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formType = $_POST['form_type'] ?? 'settings';

    if ($formType === 'settings') {
        // gather fields
        $app_name = clean($_POST['app_name'] ?? '');
        $currency_symbol = clean($_POST['currency_symbol'] ?? '');
        $currency_code = clean($_POST['currency_code'] ?? '');
        $invoice_prefix = clean($_POST['invoice_prefix'] ?? '');
        $decimal_precision = (int)($_POST['decimal_precision'] ?? 2);
        $enable_fractional_reorder = isset($_POST['enable_fractional_reorder']) ? '1' : '0';
        $default_volume_unit = in_array($_POST['default_volume_unit'] ?? 'ml', ['ml','ltr']) ? $_POST['default_volume_unit'] : 'ml';
        $use_tomselect = isset($_POST['use_tomselect']) ? '1' : '0';

        // Basic validation
        if (empty($app_name)) $errors[] = 'Application name is required.';
        if ($decimal_precision < 0 || $decimal_precision > 6) $errors[] = 'Decimal precision must be between 0 and 6.';

        if (empty($errors)) {
            setSetting($conn, 'app_name', $app_name);
            setSetting($conn, 'currency_symbol', $currency_symbol);
            setSetting($conn, 'currency_code', $currency_code);
            setSetting($conn, 'invoice_prefix', $invoice_prefix);
            setSetting($conn, 'decimal_precision', (string)$decimal_precision);
            setSetting($conn, 'enable_fractional_reorder', $enable_fractional_reorder);
            setSetting($conn, 'default_volume_unit', $default_volume_unit);
            setSetting($conn, 'use_tomselect', $use_tomselect);

            // Save additional company/contact settings
            setSetting($conn, 'company_name', clean($_POST['company_name'] ?? ''));
            setSetting($conn, 'company_email', clean($_POST['company_email'] ?? ''));
            setSetting($conn, 'company_phone', clean($_POST['company_phone'] ?? ''));
            setSetting($conn, 'company_address', clean($_POST['company_address'] ?? ''));
            setSetting($conn, 'invoice_footer', clean($_POST['invoice_footer'] ?? ''));
            setSetting($conn, 'timezone', clean($_POST['timezone'] ?? ''));

            $_SESSION['success_message'] = 'Settings saved successfully.';
            header('Location: index.php');
            exit;
        }

    } elseif ($formType === 'account') {
        // account updater
        $account_username = isset($_POST['account_username']) ? clean($_POST['account_username']) : '';
        $account_fullname = isset($_POST['account_fullname']) ? clean($_POST['account_fullname']) : '';
        $account_email = isset($_POST['account_email']) ? clean($_POST['account_email']) : '';
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (!empty($account_username) || !empty($account_fullname) || !empty($account_email) || !empty($new_password)) {
            $userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
            if ($userId > 0) {
                // Fetch current user
                $stmtU = $conn->prepare("SELECT username, password FROM users WHERE id = ? LIMIT 1");
                $stmtU->bind_param('i', $userId);
                $stmtU->execute();
                $resU = $stmtU->get_result();
                if ($resU && $rowU = $resU->fetch_assoc()) {
                    // username uniqueness check
                    if (!empty($account_username) && $account_username !== $rowU['username']) {
                        $stmtCheck = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ? LIMIT 1");
                        $stmtCheck->bind_param('si', $account_username, $userId);
                        $stmtCheck->execute();
                        $ch = $stmtCheck->get_result();
                        if ($ch && $ch->num_rows > 0) {
                            $errors[] = 'Username already taken by another user.';
                        } else {
                            $q = $conn->prepare("UPDATE users SET username = ? WHERE id = ?");
                            $q->bind_param('si', $account_username, $userId);
                            $q->execute();
                            $_SESSION['username'] = $account_username;
                        }
                    }

                    // update full name and email
                    if (!empty($account_fullname)) {
                        $q = $conn->prepare("UPDATE users SET full_name = ? WHERE id = ?");
                        $q->bind_param('si', $account_fullname, $userId);
                        $q->execute();
                        $_SESSION['full_name'] = $account_fullname;
                    }
                    if (!empty($account_email)) {
                        $q = $conn->prepare("UPDATE users SET email = ? WHERE id = ?");
                        $q->bind_param('si', $account_email, $userId);
                        $q->execute();
                    }

                    // change password if requested
                    if (!empty($new_password)) {
                        if (empty($current_password)) {
                            $errors[] = 'Current password is required to change your password.';
                        } else if (!password_verify($current_password, $rowU['password'])) {
                            $errors[] = 'Current password is incorrect.';
                        } else if ($new_password !== $confirm_password) {
                            $errors[] = 'New password and confirmation do not match.';
                        } else {
                            $newHash = password_hash($new_password, PASSWORD_BCRYPT);
                            $q = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                            $q->bind_param('si', $newHash, $userId);
                            $q->execute();
                        }
                    }
                }
            }
        }

        if (empty($errors)) {
            $_SESSION['success_message'] = 'Account updated successfully.';
            header('Location: index.php');
            exit;
        }
    }
}

include '../../includes/header.php';
?>

<div class="mb-6">
    <h1 class="text-3xl font-bold text-gray-800">Settings</h1>
    <p class="text-gray-600">Manage application settings</p>
    
    <!-- Settings Navigation -->
    <div class="mt-4 flex flex-wrap gap-2">
        <a href="index.php" class="px-4 py-2 bg-blue-600 text-white rounded font-semibold hover:bg-blue-700 transition">
            General Settings
        </a>
        <a href="permissions.php" class="px-4 py-2 bg-gray-400 text-white rounded font-semibold hover:bg-gray-500 transition">
            Role Permissions
        </a>
        <a href="backup.php" class="px-4 py-2 bg-gray-400 text-white rounded font-semibold hover:bg-gray-500 transition">
            Database Sync
        </a>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
        <ul class="list-disc list-inside">
            <?php foreach ($errors as $error): ?>
                <li><?php echo $error; ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form method="POST" action="" class="bg-white rounded-lg shadow p-6 grid grid-cols-1 gap-6 max-w-3xl">
    <input type="hidden" name="form_type" value="settings">
    <div>
        <label class="block text-gray-700 font-semibold mb-2">Application Name</label>
        <input type="text" name="app_name" value="<?php echo htmlspecialchars($settings['app_name']); ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
        <p class="text-xs text-gray-500 mt-1">Note: the constant APP_NAME in config/database.php will not change automatically; use this setting for runtime-readable labels.</p>
    </div>

    <div class="grid grid-cols-2 gap-4">
        <div>
            <label class="block text-gray-700 font-semibold mb-2">Currency Symbol</label>
            <input type="text" name="currency_symbol" value="<?php echo htmlspecialchars($settings['currency_symbol']); ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
        </div>
        <div>
            <label class="block text-gray-700 font-semibold mb-2">Currency Code</label>
            <input type="text" name="currency_code" value="<?php echo htmlspecialchars($settings['currency_code']); ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
        </div>
    </div>

    <!-- Company / Contact Info -->
    <div>
        <h3 class="text-lg font-semibold mb-2">Company / Contact</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-gray-700 font-semibold mb-2">Company Name</label>
                <input type="text" name="company_name" value="<?php echo htmlspecialchars($settings['company_name']); ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
            </div>
            <div>
                <label class="block text-gray-700 font-semibold mb-2">Company Email</label>
                <input type="email" name="company_email" value="<?php echo htmlspecialchars($settings['company_email']); ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
            </div>
            <div>
                <label class="block text-gray-700 font-semibold mb-2">Company Phone</label>
                <input type="text" name="company_phone" value="<?php echo htmlspecialchars($settings['company_phone']); ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
            </div>
            <div>
                <label class="block text-gray-700 font-semibold mb-2">Timezone</label>
                <input type="text" name="timezone" value="<?php echo htmlspecialchars($settings['timezone']); ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
            </div>
            <div class="md:col-span-2">
                <label class="block text-gray-700 font-semibold mb-2">Company Address</label>
                <textarea name="company_address" rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-lg"><?php echo htmlspecialchars($settings['company_address']); ?></textarea>
            </div>
            <div class="md:col-span-2">
                <label class="block text-gray-700 font-semibold mb-2">Invoice Footer</label>
                <textarea name="invoice_footer" rows="2" class="w-full px-4 py-2 border border-gray-300 rounded-lg"><?php echo htmlspecialchars($settings['invoice_footer']); ?></textarea>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-2 gap-4">
        <div>
            <label class="block text-gray-700 font-semibold mb-2">Invoice Prefix</label>
            <input type="text" name="invoice_prefix" value="<?php echo htmlspecialchars($settings['invoice_prefix']); ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
        </div>
        <div>
            <label class="block text-gray-700 font-semibold mb-2">Decimal Precision</label>
            <input type="number" name="decimal_precision" min="0" max="6" value="<?php echo htmlspecialchars($settings['decimal_precision'] !== '' ? $settings['decimal_precision'] : '2'); ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
        </div>
    </div>

    <div class="grid grid-cols-2 gap-4 items-center">
        <div>
            <label class="inline-flex items-center">
                <input type="checkbox" name="enable_fractional_reorder" value="1" <?php echo $settings['enable_fractional_reorder'] === '1' ? 'checked' : ''; ?> class="mr-2">
                <span class="text-gray-700">Enable fractional reorder levels</span>
            </label>
            <p class="text-xs text-gray-500 mt-1">Allow decimal values for reorder level (e.g., 0.5 L)</p>
        </div>
        <div>
            <label class="block text-gray-700 font-semibold mb-2">Default volume unit</label>
            <select name="default_volume_unit" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                <option value="ml" <?php echo ($settings['default_volume_unit'] === 'ml') ? 'selected' : ''; ?>>Milliliters (ml)</option>
                <option value="ltr" <?php echo ($settings['default_volume_unit'] === 'ltr') ? 'selected' : ''; ?>>Liters (ltr)</option>
            </select>
        </div>
    </div>

    <div>
        <label class="inline-flex items-center">
            <input type="checkbox" name="use_tomselect" value="1" <?php echo $settings['use_tomselect'] === '1' ? 'checked' : ''; ?> class="mr-2">
            <span class="text-gray-700">Use enhanced selects (Tom Select)</span>
        </label>
        <p class="text-xs text-gray-500 mt-1">Enable or disable the searchable/scrollable selects across the app (useful if CDN blocked).</p>
    </div>

    <div class="flex justify-end gap-4">
        <a href="../index.php" class="px-6 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 font-semibold transition">Back</a>
        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg font-semibold transition">Save Settings</button>
    </div>
</form>

<!-- Account Management -->
<form method="POST" action="" class="mt-6 bg-white rounded-lg shadow p-6 grid grid-cols-1 gap-6 max-w-3xl">
    <input type="hidden" name="form_type" value="account">
    <h2 class="text-xl font-bold">My Account</h2>
    <p class="text-sm text-gray-600">Modify your account username, name, email or change password below.</p>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label class="block text-gray-700 font-semibold mb-2">Username</label>
            <input type="text" name="account_username" value="<?php echo htmlspecialchars($currentUser['username'] ?? ''); ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
        </div>
        <div>
            <label class="block text-gray-700 font-semibold mb-2">Full Name</label>
            <input type="text" name="account_fullname" value="<?php echo htmlspecialchars($currentUser['full_name'] ?? ''); ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
        </div>
        <div>
            <label class="block text-gray-700 font-semibold mb-2">Email</label>
            <input type="email" name="account_email" value="<?php echo htmlspecialchars($currentUser['email'] ?? ''); ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
        </div>
    </div>

    <div>
        <h3 class="text-lg font-semibold">Change Password</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-2">
            <div>
                <label class="block text-gray-700 font-semibold mb-2">Current Password</label>
                <input type="password" name="current_password" class="w-full px-4 py-2 border border-gray-300 rounded-lg" placeholder="Enter current password">
            </div>
            <div>
                <label class="block text-gray-700 font-semibold mb-2">New Password</label>
                <input type="password" name="new_password" class="w-full px-4 py-2 border border-gray-300 rounded-lg" placeholder="New password">
            </div>
            <div>
                <label class="block text-gray-700 font-semibold mb-2">Confirm Password</label>
                <input type="password" name="confirm_password" class="w-full px-4 py-2 border border-gray-300 rounded-lg" placeholder="Confirm new password">
            </div>
        </div>
    </div>

    <div class="flex justify-end gap-4">
        <a href="../index.php" class="px-6 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 font-semibold transition">Cancel</a>
        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg font-semibold transition">Save Account</button>
    </div>
</form>

<?php include '../../includes/footer.php'; ?>
