<?php
// Helper Functions for ERP System

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Require login
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: auth/login.php');
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
    // Update product stock
    if ($movementType === 'sale') {
        $query = "UPDATE products SET stock_quantity = stock_quantity - $quantity WHERE id = $productId";
    } else if ($movementType === 'purchase') {
        $query = "UPDATE products SET stock_quantity = stock_quantity + $quantity WHERE id = $productId";
    }
    
    $conn->query($query);
    
    // Record stock movement
    $stmt = $conn->prepare("INSERT INTO stock_movements (product_id, movement_type, quantity, reference_id, reference_type, user_id) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isiisi", $productId, $movementType, $quantity, $referenceId, $referenceType, $userId);
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
?>