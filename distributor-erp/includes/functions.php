<?php
// Helper Functions for ERP System

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Require login
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: /distributor-erp/auth/login.php');
        exit;
    }
}

// Check user role
function hasRole($role) {
    return isset($_SESSION['role']) && $_SESSION['role'] === $role;
}

// Sanitize input
function clean($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Format currency
function formatCurrency($amount) {
    return 'Rs. ' . number_format($amount, 2);
}

// Format date
function formatDate($date) {
    return date('d M Y', strtotime($date));
}

// Generate unique invoice number
function generateInvoiceNumber($conn) {
    $prefix = 'INV-' . date('Ymd') . '-';
    $query = "SELECT invoice_number FROM sales WHERE invoice_number LIKE '{$prefix}%' ORDER BY id DESC LIMIT 1";
    $result = $conn->query($query);
    
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $lastNumber = intval(substr($row['invoice_number'], strlen($prefix)));
        $newNumber = $lastNumber + 1;
    } else {
        $newNumber = 1;
    }
    
    return $prefix . str_pad($newNumber, 4, '0', STR_PAD_LEFT);
}

// Generate unique purchase number
function generatePurchaseNumber($conn) {
    $prefix = 'PO-' . date('Ymd') . '-';
    $query = "SELECT purchase_number FROM purchases WHERE purchase_number LIKE '{$prefix}%' ORDER BY id DESC LIMIT 1";
    $result = $conn->query($query);
    
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $lastNumber = intval(substr($row['purchase_number'], strlen($prefix)));
        $newNumber = $lastNumber + 1;
    } else {
        $newNumber = 1;
    }
    
    return $prefix . str_pad($newNumber, 4, '0', STR_PAD_LEFT);
}

// Get low stock products count
function getLowStockCount($conn) {
    $query = "SELECT COUNT(*) as count FROM products WHERE stock_quantity <= reorder_level AND is_active = 1";
    $result = $conn->query($query);
    $row = $result->fetch_assoc();
    return $row['count'];
}

// Get today's sales total
function getTodaySales($conn) {
    $today = date('Y-m-d');
    $query = "SELECT COALESCE(SUM(total_amount), 0) as total FROM sales WHERE DATE(created_at) = '$today'";
    $result = $conn->query($query);
    $row = $result->fetch_assoc();
    return $row['total'];
}

// Get pending payments total
function getPendingPayments($conn) {
    $query = "SELECT COALESCE(SUM(total_amount - paid_amount), 0) as total FROM sales WHERE payment_status != 'paid'";
    $result = $conn->query($query);
    $row = $result->fetch_assoc();
    return $row['total'];
}

// Update product stock
function updateProductStock($conn, $productId, $quantity, $movementType, $referenceId = null, $referenceType = null, $userId = null) {
    // Update product stock (use prepared statements and float-safe types)
    $qty = (float)$quantity;
    $pid = (int)$productId;
    if ($movementType === 'sale') {
        $stmtUpdate = $conn->prepare("UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ?");
        $stmtUpdate->bind_param("di", $qty, $pid);
        $stmtUpdate->execute();
    } else if ($movementType === 'purchase') {
        $stmtUpdate = $conn->prepare("UPDATE products SET stock_quantity = stock_quantity + ? WHERE id = ?");
        $stmtUpdate->bind_param("di", $qty, $pid);
        $stmtUpdate->execute();
    }
    
    // Record stock movement
    $refId = $referenceId !== null ? (int)$referenceId : null;
    $uid = $userId !== null ? (int)$userId : null;
    $stmt = $conn->prepare("INSERT INTO stock_movements (product_id, movement_type, quantity, reference_id, reference_type, user_id) VALUES (?, ?, ?, ?, ?, ?)");
    // product_id (i), movement_type (s), quantity (d), reference_id (i), reference_type (s), user_id (i)
    $stmt->bind_param("isdisi", $pid, $movementType, $qty, $refId, $referenceType, $uid);
    $stmt->execute();
}

// Show alert message
function showAlert($message, $type = 'success') {
    $alertClass = $type === 'success' ? 'bg-green-100 border-green-400 text-green-700' : 'bg-red-100 border-red-400 text-red-700';
    return "<div class='$alertClass border px-4 py-3 rounded relative mb-4' role='alert'>
                <span class='block sm:inline'>$message</span>
            </div>";
}

// Generate unique booking number
function generateBookingNumber($conn) {
    $prefix = 'BK-' . date('Ymd') . '-';
    $query = "SELECT booking_number FROM bookings WHERE booking_number LIKE '{$prefix}%' ORDER BY id DESC LIMIT 1";
    $result = $conn->query($query);
    
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $lastNumber = intval(substr($row['booking_number'], strlen($prefix)));
        $newNumber = $lastNumber + 1;
    } else {
        $newNumber = 1;
    }
    
    return $prefix . str_pad($newNumber, 4, '0', STR_PAD_LEFT);
}

// Generate unique booker code
function generateBookerCode($conn) {
    $prefix = 'BKR-';
    $query = "SELECT booker_code FROM bookers WHERE booker_code LIKE '{$prefix}%' ORDER BY id DESC LIMIT 1";
    $result = $conn->query($query);
    
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $lastNumber = intval(substr($row['booker_code'], strlen($prefix)));
        $newNumber = $lastNumber + 1;
    } else {
        $newNumber = 1;
    }
    
    return $prefix . str_pad($newNumber, 4, '0', STR_PAD_LEFT);
}

// Get booker performance stats
function getBookerStats($conn, $bookerId, $startDate = null, $endDate = null) {
    $dateCondition = '';
    if ($startDate && $endDate) {
        $dateCondition = "AND s.sale_date BETWEEN '$startDate' AND '$endDate'";
    } else {
        // Default to current month
        $startDate = date('Y-m-01');
        $endDate = date('Y-m-t');
        $dateCondition = "AND s.sale_date BETWEEN '$startDate' AND '$endDate'";
    }
    
    $query = "SELECT 
                COUNT(DISTINCT b.id) as total_bookings,
                COUNT(DISTINCT CASE WHEN b.status = 'invoiced' THEN b.id END) as completed_bookings,
                COALESCE(SUM(CASE WHEN b.status = 'invoiced' THEN b.total_amount END), 0) as total_sales,
                COALESCE(SUM(CASE WHEN b.status = 'invoiced' THEN (b.total_amount * bk.commission_percentage / 100) END), 0) as total_commission
              FROM bookings b
              JOIN bookers bk ON b.booker_id = bk.id
              LEFT JOIN sales s ON b.invoice_id = s.id
              WHERE b.booker_id = $bookerId $dateCondition";
    
    $result = $conn->query($query);
    return $result->fetch_assoc();
}



// Pagination helper
function paginate($conn, $table, $perPage = 20, $page = 1, $conditions = "") {
    $offset = ($page - 1) * $perPage;
    $where = $conditions ? "WHERE $conditions" : "";
    
    // Get total count
    $countQuery = "SELECT COUNT(*) as total FROM $table $where";
    $countResult = $conn->query($countQuery);
    $totalRecords = $countResult->fetch_assoc()['total'];
    $totalPages = ceil($totalRecords / $perPage);
    
    return [
        'total_records' => $totalRecords,
        'total_pages' => $totalPages,
        'current_page' => $page,
        'per_page' => $perPage,
        'offset' => $offset
    ];
}

// Settings helpers (key-value store in 'settings' table)
function getSetting($conn, $name, $default = null) {
    $stmt = $conn->prepare("SELECT value FROM settings WHERE name = ? LIMIT 1");
    $stmt->bind_param('s', $name);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $row = $res->fetch_assoc()) {
        return $row['value'];
    }
    return $default;
}

function setSetting($conn, $name, $value) {
    // Use INSERT ... ON DUPLICATE KEY UPDATE to upsert
    $stmt = $conn->prepare("INSERT INTO settings (name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)");
    $stmt->bind_param('ss', $name, $value);
    return $stmt->execute();
}

// Role-based permissions
function hasPermission($conn, $role, $module, $permission) {
    // Admin always has all permissions
    if ($role === 'admin') {
        return true;
    }
    
    $stmt = $conn->prepare("SELECT can_access FROM role_permissions WHERE role = ? AND module = ? AND permission = ? LIMIT 1");
    $stmt->bind_param('sss', $role, $module, $permission);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $row = $result->fetch_assoc()) {
        return (bool)$row['can_access'];
    }
    
    return false;
}

function canAccessModule($conn, $role, $module) {
    return hasPermission($conn, $role, $module, 'view');
}

function canAdd($conn, $role, $module) {
    return hasPermission($conn, $role, $module, 'add');
}

function canEdit($conn, $role, $module) {
    return hasPermission($conn, $role, $module, 'edit');
}

function canDelete($conn, $role, $module) {
    return hasPermission($conn, $role, $module, 'delete');
}

function canExport($conn, $role, $module) {
    return hasPermission($conn, $role, $module, 'export');
}

// Require specific permission (redirect if denied)
function requirePermission($conn, $role, $module, $permission) {
    if (!hasPermission($conn, $role, $module, $permission)) {
        $_SESSION['error_message'] = 'Access denied. You do not have permission to access this module.';
        header('Location: ../../index.php');
        exit;
    }
}

// Get all permissions for a role
function getRolePermissions($conn, $role) {
    $stmt = $conn->prepare("SELECT module, permission, can_access FROM role_permissions WHERE role = ? ORDER BY module, permission");
    $stmt->bind_param('s', $role);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $permissions = [];
    while ($row = $result->fetch_assoc()) {
        if (!isset($permissions[$row['module']])) {
            $permissions[$row['module']] = [];
        }
        $permissions[$row['module']][$row['permission']] = (bool)$row['can_access'];
    }
    
    return $permissions;
}

// Update role permissions
function updateRolePermission($conn, $role, $module, $permission, $can_access) {
    $can_access = $can_access ? 1 : 0;
    $stmt = $conn->prepare("INSERT INTO role_permissions (role, module, permission, can_access) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE can_access = VALUES(can_access)");
    $stmt->bind_param('sssi', $role, $module, $permission, $can_access);
    return $stmt->execute();
}

/**
 * Log transaction for audit trail
 */
function logTransaction($conn, $userId, $type, $referenceId, $description) {
    $stmt = $conn->prepare("INSERT INTO transaction_logs 
                          (user_id, transaction_type, reference_id, description, ip_address, user_agent, created_at) 
                          VALUES (?, ?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("isisss", 
        $userId,
        $type,
        $referenceId,
        $description,
        $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
        $_SERVER['HTTP_USER_AGENT'] ?? ''
    );
    $stmt->execute();
    $stmt->close();
}

?>