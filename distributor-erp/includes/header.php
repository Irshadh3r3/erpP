<?php
if (!isset($_SESSION['user_id'])) {
    header('Location: auth/login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' - ' : ''; ?><?php echo APP_NAME; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .dropdown:hover .dropdown-menu {
            display: block;
        }
    </style>
</head>
<body class="bg-gray-100">
    <!-- Top Navigation Bar -->
    <nav class="bg-blue-600 text-white shadow-lg">
        <div class="container mx-auto px-4">
            <div class="flex items-center justify-between py-3">
                <div class="flex items-center space-x-4">
                    <a href="/distributor-erp/index.php" class="text-2xl font-bold"><?php echo APP_NAME; ?></a>
                </div>
                
                <div class="flex items-center space-x-4">
                    <span class="text-sm">Welcome, <strong><?php echo $_SESSION['full_name']; ?></strong></span>
                    <span class="text-xs bg-blue-500 px-2 py-1 rounded"><?php echo strtoupper($_SESSION['role']); ?></span>
                    <a href="/distributor-erp/auth/logout.php" class="bg-red-500 hover:bg-red-600 px-4 py-2 rounded text-sm font-semibold transition">
                        Logout
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Sidebar and Main Content Container -->
    <div class="flex">
        <!-- Sidebar -->
        <aside class="w-64 bg-white shadow-md min-h-screen">
            <div class="p-4">
                <ul class="space-y-2">
                    <!-- Dashboard -->
                    <li>
                        <a href="/distributor-erp/index.php" class="flex items-center space-x-3 px-4 py-3 rounded hover:bg-blue-50 text-gray-700 hover:text-blue-600 transition">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                            </svg>
                            <span>Dashboard</span>
                        </a>
                    </li>

                    <!-- Products -->
                    <li class="dropdown relative">
                        <a href="/distributor-erp/modules/products/list.php" class="flex items-center space-x-3 px-4 py-3 rounded hover:bg-blue-50 text-gray-700 hover:text-blue-600 transition">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                            </svg>
                            <span>Products</span>
                        </a>
                    </li>

                    <!-- Customers -->
                    <li>
                        <a href="/distributor-erp/modules/customers/list.php" class="flex items-center space-x-3 px-4 py-3 rounded hover:bg-blue-50 text-gray-700 hover:text-blue-600 transition">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                            </svg>
                            <span>Customers</span>
                        </a>
                    </li>

                    <!-- Bookers -->
                    <li>
                        <a href="/distributor-erp/modules/bookers/list.php" class="flex items-center space-x-3 px-4 py-3 rounded hover:bg-blue-50 text-gray-700 hover:text-blue-600 transition">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                            </svg>
                            <span>Bookers</span>
                        </a>
                    </li>

                    <!-- Bookings/Orders -->
                    <li>
                        <a href="/distributor-erp/modules/bookings/list.php" class="flex items-center space-x-3 px-4 py-3 rounded hover:bg-blue-50 text-gray-700 hover:text-blue-600 transition">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            <span>Orders</span>
                        </a>
                    </li>
                    <!-- Bookings/Orders -->
                    <li>
                        <a href="/distributor-erp/modules/payments/record_payment.php" class="flex items-center space-x-3 px-4 py-3 rounded hover:bg-blue-50 text-gray-700 hover:text-blue-600 transition">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            <span>Payments</span>
                        </a>
                    </li>

                    <!-- Suppliers -->
                    <li>
                        <a href="/distributor-erp/modules/suppliers/list.php" class="flex items-center space-x-3 px-4 py-3 rounded hover:bg-blue-50 text-gray-700 hover:text-blue-600 transition">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                            </svg>
                            <span>Suppliers</span>
                        </a>
                    </li>

                    <!-- Sales -->
                    <li>
                        <a href="/distributor-erp/modules/sales/list.php" class="flex items-center space-x-3 px-4 py-3 rounded hover:bg-blue-50 text-gray-700 hover:text-blue-600 transition">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path>
                            </svg>
                            <span>Sales</span>
                        </a>
                    </li>

                    <!-- Purchases -->
                    <li>
                        <a href="/distributor-erp/modules/purchases/list.php" class="flex items-center space-x-3 px-4 py-3 rounded hover:bg-blue-50 text-gray-700 hover:text-blue-600 transition">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path>
                            </svg>
                            <span>Purchases</span>
                        </a>
                    </li>

                    <!-- Reports -->
                    <li>
                        <a href="/distributor-erp/modules/reports/index.php" class="flex items-center space-x-3 px-4 py-3 rounded hover:bg-blue-50 text-gray-700 hover:text-blue-600 transition">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            <span>Reports</span>
                        </a>
                    </li>

                    <?php if ($_SESSION['role'] === 'admin'): ?>
                    <!-- Settings (Admin Only) -->
                    <li class="mt-4 pt-4 border-t">
                        <a href="/distributor-erp/modules/categories/list.php" class="flex items-center space-x-3 px-4 py-3 rounded hover:bg-blue-50 text-gray-700 hover:text-blue-600 transition">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
                            </svg>
                            <span>Categories</span>
                        </a>
                    </li>
                    
                    <li>
                        <a href="/distributor-erp/modules/settings/index.php" class="flex items-center space-x-3 px-4 py-3 rounded hover:bg-blue-50 text-gray-700 hover:text-blue-600 transition">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            </svg>
                            <span>Settings</span>
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
        </aside>

        <!-- Main Content Area -->
        <main class="flex-1 p-6">
            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                    <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                    <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
                </div>
            <?php endif; ?>