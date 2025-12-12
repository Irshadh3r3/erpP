# Role-Based Access Control (RBAC) System

## Overview
The ERP system now includes a comprehensive role-based access control system that allows admins to manage which modules and features each user role can access.

## Roles & Default Permissions

### 1. Admin
- **Access Level**: Full access to all modules and features
- **Cannot be modified**: Admin role permissions are fixed and always full access
- **Can manage**: Everything - users, settings, all business modules

### 2. Manager  
- **Purpose**: Supervise business operations without system administration access
- **Can view & manage**: 
  - Products (full CRUD)
  - Categories (full CRUD)
  - Customers (full CRUD)
  - Suppliers (full CRUD)
  - Bookings (full CRUD)
  - Purchases (full CRUD)
  - Sales (full CRUD)
  - Payments (full CRUD)
  - Bookers (full CRUD)
  - Reports (view & export)
- **Cannot access**: Users management, Settings/Admin panel
- **Ideal for**: Team leads, operations managers

### 3. Cashier
- **Purpose**: Process sales and payments only
- **Can access**:
  - Products (view only)
  - Categories (view only)
  - Customers (view & add quick customers)
  - Sales (create & edit, cannot delete)
  - Payments (create & view)
  - Reports (view only, no export)
- **Cannot access**: Inventory, purchasing, user management, admin settings
- **Ideal for**: Point-of-sale operators, billing staff

### 4. Inventory
- **Purpose**: Manage stock, purchases, and product catalog
- **Can access**:
  - Products (add, edit, view; cannot delete)
  - Categories (add, edit, view; cannot delete)
  - Suppliers (full CRUD)
  - Purchases (full CRUD)
  - Sales (view only)
  - Payments (view only)
  - Reports (view & export)
- **Cannot access**: User management, admin settings, cannot delete products/categories
- **Ideal for**: Stock managers, warehouse staff, supply chain

## Permission Matrix

| Module | Permission | Admin | Manager | Cashier | Inventory |
|--------|-----------|-------|---------|---------|-----------|
| **Products** | View | ✓ | ✓ | ✓ | ✓ |
| | Add | ✓ | ✓ | ✗ | ✓ |
| | Edit | ✓ | ✓ | ✗ | ✓ |
| | Delete | ✓ | ✓ | ✗ | ✗ |
| **Categories** | View | ✓ | ✓ | ✓ | ✓ |
| | Add | ✓ | ✓ | ✗ | ✓ |
| | Edit | ✓ | ✓ | ✗ | ✓ |
| | Delete | ✓ | ✓ | ✗ | ✗ |
| **Customers** | View | ✓ | ✓ | ✓ | ✓ |
| | Add | ✓ | ✓ | ✓ | ✗ |
| | Edit | ✓ | ✓ | ✗ | ✗ |
| | Delete | ✓ | ✓ | ✗ | ✗ |
| **Suppliers** | View | ✓ | ✓ | ✗ | ✓ |
| | Add | ✓ | ✓ | ✗ | ✓ |
| | Edit | ✓ | ✓ | ✗ | ✓ |
| | Delete | ✓ | ✓ | ✗ | ✗ |
| **Sales** | View | ✓ | ✓ | ✓ | ✓ |
| | Add | ✓ | ✓ | ✓ | ✗ |
| | Edit | ✓ | ✓ | ✓ | ✗ |
| | Delete | ✓ | ✓ | ✗ | ✗ |
| **Purchases** | View | ✓ | ✓ | ✗ | ✓ |
| | Add | ✓ | ✓ | ✗ | ✓ |
| | Edit | ✓ | ✓ | ✗ | ✓ |
| | Delete | ✓ | ✓ | ✗ | ✗ |
| **Payments** | View | ✓ | ✓ | ✓ | ✓ |
| | Add | ✓ | ✓ | ✓ | ✗ |
| | Edit | ✓ | ✓ | ✗ | ✗ |
| | Delete | ✓ | ✓ | ✗ | ✗ |
| **Bookings** | View | ✓ | ✓ | ✗ | ✗ |
| | Add | ✓ | ✓ | ✗ | ✗ |
| | Edit | ✓ | ✓ | ✗ | ✗ |
| | Delete | ✓ | ✓ | ✗ | ✗ |
| **Reports** | View | ✓ | ✓ | ✓ | ✓ |
| | Export | ✓ | ✓ | ✗ | ✓ |
| **Users** | View | ✓ | ✗ | ✗ | ✗ |
| | Add | ✓ | ✗ | ✗ | ✗ |
| | Edit | ✓ | ✗ | ✗ | ✗ |
| | Delete | ✓ | ✗ | ✗ | ✗ |
| **Settings** | View | ✓ | ✗ | ✗ | ✗ |
| | Edit | ✓ | ✗ | ✗ | ✗ |

## Managing Permissions

### For Admins:
1. Go to **Settings** → **Role Permissions**
2. Select a role (Manager, Cashier, or Inventory)
3. Check/uncheck permissions for each module
4. Click "Save Permissions"

### Adding Permission Checks to Module Pages:
```php
// At the top of any module page
require '../../config/database.php';
require '../../includes/functions.php';

$conn = getDBConnection();

// Check if user can view this module
requirePermission($conn, $_SESSION['role'], 'products', 'view');

// Or use conditional checks
if (canAdd($conn, $_SESSION['role'], 'products')) {
    // Show add button
}
```

### Available Permission Helper Functions:
- `hasPermission($conn, $role, $module, $permission)` - Check specific permission
- `canAccessModule($conn, $role, $module)` - Can user view/access module?
- `canAdd($conn, $role, $module)` - Can user add to this module?
- `canEdit($conn, $role, $module)` - Can user edit in this module?
- `canDelete($conn, $role, $module)` - Can user delete from this module?
- `canExport($conn, $role, $module)` - Can user export from this module?
- `requirePermission($conn, $role, $module, $permission)` - Require permission or redirect
- `getRolePermissions($conn, $role)` - Get all permissions for a role
- `updateRolePermission($conn, $role, $module, $permission, $can_access)` - Update a permission

## Database Tables

### role_permissions Table
```sql
CREATE TABLE role_permissions (
  id INT PRIMARY KEY AUTO_INCREMENT,
  role ENUM('admin','manager','cashier','inventory') NOT NULL,
  module VARCHAR(50) NOT NULL,
  permission VARCHAR(50) NOT NULL,
  can_access TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY role_module_permission (role, module, permission)
);
```

## Migration File
The migration file `2025-12-03_add_role_permissions_system.sql` contains:
- Role permissions table creation
- All default permissions for each role

## Implementation Notes
1. Admin role always has full access - permissions cannot be modified for admin
2. Permissions are checked in the database, not hardcoded in PHP
3. Admins can customize permissions for Manager, Cashier, and Inventory roles
4. Each module page can independently check permissions before displaying content
5. The permissions system uses prepared statements for SQL injection prevention
