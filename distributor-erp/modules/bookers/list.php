<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';

requireLogin();

$conn = getDBConnection();
$pageTitle = 'Bookers';

// Search
$search = isset($_GET['search']) ? clean($_GET['search']) : '';

// Build query
$where = "b.is_active = 1";
if (!empty($search)) {
    $where .= " AND (b.name LIKE '%$search%' OR b.booker_code LIKE '%$search%' OR b.phone LIKE '%$search%' OR b.area LIKE '%$search%')";
}

// Get bookers with stats
$query = "SELECT b.*, 
          COUNT(DISTINCT bk.id) as total_bookings,
          COUNT(DISTINCT CASE WHEN bk.status = 'invoiced' THEN bk.id END) as completed_bookings,
          COALESCE(SUM(CASE WHEN bk.status = 'invoiced' THEN bk.total_amount END), 0) as total_sales
          FROM bookers b
          LEFT JOIN bookings bk ON b.id = bk.booker_id
          WHERE $where
          GROUP BY b.id
          ORDER BY b.created_at DESC";
$bookers = $conn->query($query);

include '../../includes/header.php';
?>

<!-- Page Header -->
<div class="mb-6 flex items-center justify-between">
    <div>
        <h1 class="text-3xl font-bold text-gray-800">Bookers</h1>
        <p class="text-gray-600">Manage your sales bookers and their performance</p>
    </div>
    <a href="add.php" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-semibold transition">
        + Add New Booker
    </a>
</div>

<!-- Search -->
<div class="bg-white rounded-lg shadow p-6 mb-6">
    <form method="GET" action="" class="flex gap-4">
        <input type="text" 
               name="search" 
               value="<?php echo $search; ?>"
               placeholder="Search by name, code, phone, or area..." 
               class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg font-semibold transition">
            Search
        </button>
        <a href="list.php" class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-6 py-2 rounded-lg font-semibold transition">
            Reset
        </a>
    </form>
</div>

<!-- Bookers Grid -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
    <?php if ($bookers->num_rows > 0): ?>
        <?php while ($booker = $bookers->fetch_assoc()): ?>
            <div class="bg-white rounded-lg shadow hover:shadow-lg transition">
                <div class="p-6">
                    <div class="flex items-start justify-between mb-4">
                        <div>
                            <h3 class="text-xl font-bold text-gray-800"><?php echo $booker['name']; ?></h3>
                            <p class="text-sm text-gray-500"><?php echo $booker['booker_code']; ?></p>
                        </div>
                        <span class="bg-blue-100 text-blue-700 text-xs px-2 py-1 rounded">
                            <?php echo $booker['commission_percentage']; ?>% Commission
                        </span>
                    </div>
                    
                    <div class="space-y-2 mb-4">
                        <div class="flex items-center text-sm text-gray-600">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                            </svg>
                            <?php echo $booker['phone']; ?>
                        </div>
                        <?php if ($booker['area']): ?>
                            <div class="flex items-center text-sm text-gray-600">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                </svg>
                                <?php echo $booker['area']; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Stats -->
                    <div class="grid grid-cols-3 gap-4 py-4 border-t border-b mb-4">
                        <div class="text-center">
                            <p class="text-2xl font-bold text-gray-800"><?php echo $booker['total_bookings']; ?></p>
                            <p class="text-xs text-gray-500">Total Bookings</p>
                        </div>
                        <div class="text-center">
                            <p class="text-2xl font-bold text-green-600"><?php echo $booker['completed_bookings']; ?></p>
                            <p class="text-xs text-gray-500">Invoiced</p>
                        </div>
                        <div class="text-center">
                            <p class="text-lg font-bold text-blue-600"><?php echo formatCurrency($booker['total_sales']); ?></p>
                            <p class="text-xs text-gray-500">Total Sales</p>
                        </div>
                    </div>
                    
                    <!-- Actions -->
                    <div class="flex gap-2">
                        <a href="view.php?id=<?php echo $booker['id']; ?>" 
                           class="flex-1 text-center bg-blue-50 hover:bg-blue-100 text-blue-600 px-4 py-2 rounded-lg font-semibold transition">
                            View Details
                        </a>
                        <a href="edit.php?id=<?php echo $booker['id']; ?>" 
                           class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-lg transition">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                            </svg>
                        </a>
                    </div>
                </div>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div class="col-span-3 bg-white rounded-lg shadow p-12 text-center">
            <svg class="w-16 h-16 mx-auto text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
            </svg>
            <p class="text-gray-500 text-lg mb-4">No bookers found</p>
            <a href="add.php" class="text-blue-600 hover:text-blue-700 font-semibold">Add your first booker</a>
        </div>
    <?php endif; ?>
</div>

<?php
include '../../includes/footer.php';
?>