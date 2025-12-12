<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';

requireLogin();

$conn = getDBConnection();
$pageTitle = 'Load Sheet Generator';

// Get filters
$booker_id = isset($_GET['booker_id']) ? (int)$_GET['booker_id'] : 0;
$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

// Get bookers for dropdown
$bookersQuery = "SELECT id, name, booker_code FROM bookers WHERE is_active = 1 ORDER BY name ASC";
$bookers = $conn->query($bookersQuery);

// Get load sheet data if booker and date are selected
$loadSheetData = null;
$bookerInfo = null;

if ($booker_id > 0) {
    // Get booker info
    $bookerQuery = "SELECT * FROM bookers WHERE id = $booker_id";
    $bookerInfo = $conn->query($bookerQuery)->fetch_assoc();
    
    // Get aggregated products for the booker on the selected date
    $loadSheetQuery = "
        SELECT 
            p.name as product_name,
            p.variety,
            p.sku,
            p.unit,
            SUM(bi.quantity) as total_quantity,
            COUNT(DISTINCT b.id) as booking_count
        FROM bookings b
        JOIN booking_items bi ON b.id = bi.booking_id
        JOIN products p ON bi.product_id = p.id
        WHERE b.booker_id = $booker_id
        AND DATE(b.booking_date) = '$date'
        AND b.status != 'cancelled'
        GROUP BY p.id, p.name, p.variety, p.sku, p.unit
        ORDER BY p.name ASC, p.variety ASC
    ";
    
    $loadSheetData = $conn->query($loadSheetQuery);
    
    // Get bookings summary
    $summaryQuery = "
        SELECT 
            COUNT(*) as total_bookings,
            SUM(total_amount) as total_amount
        FROM bookings
        WHERE booker_id = $booker_id
        AND DATE(booking_date) = '$date'
        AND status != 'cancelled'
    ";
    $summary = $conn->query($summaryQuery)->fetch_assoc();
}

// Check if this is a print request
$isPrint = isset($_GET['print']) && $_GET['print'] == '1';

if ($isPrint && $booker_id > 0):
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Load Sheet - <?php echo $bookerInfo['name']; ?> - <?php echo formatDate($date); ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            color: #333;
        }
        .header {    
            margin-bottom: 9px;
            border-bottom: 2px solid #333;
            padding-bottom: 0px;
            position: relative;
        }
        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 10px;
        }
        .document-title {
            font-size: 18px;
            font-weight: bold;
            text-align: left;
            width: 30%;
        }
        .company-name {
            font-size: 28px;
            font-weight: bold;
            text-align: center;
            width: 40%;
        }
        .phone-number {
            font-size: 14px;
            text-align: right;
            width: 30%;
            color: #666;
        }
        .info-section {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
        }
        .info-box {
            font-size: 11px;
            width: 48%;
        }
        .info-box h3 {
            font-size: 11px;
            background: #f0f0f0;
            padding: 8px;
            margin: 0 0 10px 0;
            border-left: 2px solid #333;
        }
        .info-row {
            display: flex;
            padding: 5px 0;
        }
        .info-label {
            font-weight: bold;
            width: 120px;
        }
        .info-value {
            flex: 1;
        }
        table {
            font-size: 12px;
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th {
            background: #333 !important;
            color: white !important;
            padding: 12px;
            text-align: left;
            font-size: 12px;
            text-transform: uppercase;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
        td {
            padding: 10px 12px;
            border-bottom: 1px solid #ddd;
        }
        tr:nth-child(even) {
            background: #f9f9f9;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .total-row {
            font-weight: bold;
            background: #f0f0f0 !important;
            font-size: 16px;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
        .footer {
            margin-top: 50px;
            padding-top: 20px;
            border-top: 2px solid #333;
        }
        .signature-section {
            display: flex;
            justify-content: space-between;
            margin-top: 60px;
        }
        .signature-box {
            width: 45%;
            text-align: center;
        }
        .signature-line {
            border-top: 2px solid #333;
            margin-top: 50px;
            padding-top: 10px;
        }
        @media print {
            body {
                margin: 0;
                padding: 15px;
            }
            .no-print {
                display: none;
            }
            th {
                background: #333 !important;
                color: white !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            tr:nth-child(even) {
                background: #f9f9f9 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .total-row {
                background: #f0f0f0 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .info-box h3 {
                background: #f0f0f0 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
        }
        @media print {
            /* Alternative method using borders for better browser compatibility */
            table.print-friendly th {
                border: 1px solid #000 !important;
                background-color: #333 !important;
                color: white !important;
            }
            table.print-friendly tr:nth-child(even) {
                background-color: #f9f9f9 !important;
            }
        }
        .variety-text {
            color: #666;
            font-size: 11px;
            font-style: italic;
        }
        .header-subtitle {
            text-align: center;
            font-size: 24px;
            font-weight: bold;
            margin-top: 15px;
            padding-top: 10px;
            border-top: 1px solid #ddd;
        }
        /* Add border to table header cells as backup */
        th {
            border: 1px solid #000;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-top">
            <div class="document-title">LOAD SHEET</div>
            <div class="company-name"><?php echo getSetting($conn, 'company_name', 'Khan Traders'); ?></div>
            <div class="phone-number">
                <?php echo getSetting($conn, 'company_phone', 'N/A'); ?>
            </div>
        </div>
    </div>

    <div class="info-section">
        <div class="info-box">
            <h3>Booker Information</h3>
            <div class="info-row">
                <div class="info-label">Booker Name:</div>
                <div class="info-value"><?php echo $bookerInfo['name']; ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Booker Code:</div>
                <div class="info-value"><?php echo $bookerInfo['booker_code']; ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Phone:</div>
                <div class="info-value"><?php echo $bookerInfo['phone']; ?></div>
            </div>
        </div>
        <div class="info-box">
            <h3>Load Sheet Details</h3>
            <div class="info-row">
                <div class="info-label">Date:</div>
                <div class="info-value"><?php echo formatDate($date); ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Total Bookings:</div>
                <div class="info-value"><?php echo $summary['total_bookings']; ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Print Time:</div>
                <div class="info-value"><?php echo date('d M Y, h:i A'); ?></div>
            </div>
        </div>
    </div>

    <table class="print-friendly">
        <thead>
            <tr>
                <th style="width: 8%; background-color: #333 !important; color: white !important; -webkit-print-color-adjust: exact;">#</th>
                <th style="width: 20%; background-color: #333 !important; color: white !important; -webkit-print-color-adjust: exact;">SKU</th>
                <th style="width: 37%; background-color: #333 !important; color: white !important; -webkit-print-color-adjust: exact;">Product Name</th>
                <th style="width: 20%; background-color: #333 !important; color: white !important; -webkit-print-color-adjust: exact;" class="text-center">Total Quantity</th>
                <th style="width: 15%; background-color: #333 !important; color: white !important; -webkit-print-color-adjust: exact;" class="text-center">Bookings</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $index = 1;
            $totalItems = 0;
            $loadSheetData->data_seek(0);
            $rowCounter = 0;
            while ($item = $loadSheetData->fetch_assoc()): 
                $totalItems++;
                $rowCounter++;
                $rowClass = $rowCounter % 2 == 0 ? 'style="background-color: #f9f9f9 !important; -webkit-print-color-adjust: exact;"' : '';
            ?>
                <tr <?php echo $rowClass; ?>>
                    <td class="text-center"><?php echo $index++; ?></td>
                    <td><?php echo $item['sku']; ?></td>
                    <td>
                        <strong>
    <?php 
        echo htmlspecialchars($item['product_name']);
        if (!empty($item['variety'])) {
            echo " - " . htmlspecialchars($item['variety']);
        }
    ?>
</strong>

                    </td>
                    <td class="text-center">
                        <strong><?php echo number_format($item['total_quantity'], 2); ?></strong> <?php echo $item['unit']; ?>
                    </td>
                    <td class="text-center"><?php echo $item['booking_count']; ?></td>
                </tr>
            <?php endwhile; ?>
            <tr class="total-row" style="background-color: #f0f0f0 !important; -webkit-print-color-adjust: exact;">
                <td colspan="2" class="text-right">TOTAL ITEMS:</td>
                <td><strong><?php echo $totalItems; ?> Products</strong></td>
                <td colspan="2"></td>
            </tr>
        </tbody>
    </table>

    <div class="footer">
        <div style="font-size: 12px; color: #666; margin-bottom: 20px;">
            <strong>Notes:</strong> Please verify all quantities before loading. Report any discrepancies immediately.
        </div>
        
        <div class="signature-section">
            <div class="signature-box">
                <div class="signature-line">
                    Prepared By
                </div>
            </div>
            <div class="signature-box">
                <div class="signature-line">
                    Booker Signature
                </div>
            </div>
        </div>
    </div>

    <script>
        window.onload = function() {
            // Set print settings before printing
            window.print();
            
            // Alternative: Show print dialog after a small delay
            // setTimeout(function() {
            //     window.print();
            // }, 100);
        }
        
        // Add event listener for after print to close window (optional)
        window.onafterprint = function() {
            // Optionally close the window after printing
            // window.close();
        };
    </script>
</body>
</html>
<?php
exit;
endif;

// Normal page view
include '../../includes/header.php';
?>

<!-- Page Header -->
<div class="mb-6">
    <h1 class="text-3xl font-bold text-gray-800">Load Sheet Generator</h1>
    <p class="text-gray-600">Generate consolidated product list for booker deliveries</p>
</div>

<!-- Filters -->
<div class="bg-white rounded-lg shadow p-6 mb-6">
    <form method="GET" action="" class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div>
            <label for="booker_id" class="block text-gray-700 font-semibold mb-2">Select Booker <span class="text-red-500">*</span></label>
            <select name="booker_id" 
                    id="booker_id"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                    required>
                <option value="">Choose Booker</option>
                <?php 
                $bookers->data_seek(0);
                while ($booker = $bookers->fetch_assoc()): 
                ?>
                    <option value="<?php echo $booker['id']; ?>" <?php echo $booker_id == $booker['id'] ? 'selected' : ''; ?>>
                        <?php echo $booker['name']; ?> (<?php echo $booker['booker_code']; ?>)
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
        
        <div>
            <label for="date" class="block text-gray-700 font-semibold mb-2">Date <span class="text-red-500">*</span></label>
            <input type="date" 
                   name="date" 
                   id="date"
                   value="<?php echo $date; ?>"
                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                   required>
        </div>
        
        <div class="flex items-end gap-2">
            <button type="submit" 
                    class="flex-1 bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg font-semibold transition">
                Generate Load Sheet
            </button>
        </div>
    </form>
</div>

<?php if ($booker_id > 0 && $bookerInfo): ?>
    <!-- Load Sheet Preview -->
    <div class="bg-white rounded-lg shadow mb-6">
        <!-- Header -->
        <div class="bg-gradient-to-r from-blue-600 to-blue-700 text-white p-6 rounded-t-lg">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-2xl font-bold">Load Sheet</h2>
                    <p class="text-blue-100 mt-1"><?php echo $bookerInfo['name']; ?> - <?php echo formatDate($date); ?></p>
                </div>
                <a href="?booker_id=<?php echo $booker_id; ?>&date=<?php echo $date; ?>&print=1" 
                   target="_blank"
                   class="bg-white text-blue-600 hover:bg-blue-50 px-6 py-3 rounded-lg font-semibold transition flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                    </svg>
                    Print Load Sheet
                </a>
            </div>
        </div>

        <!-- Summary Stats -->
        <div class="grid grid-cols-3 gap-4 p-6 border-b">
            <div class="text-center">
                <p class="text-gray-500 text-sm mb-1">Total Bookings</p>
                <p class="text-2xl font-bold text-blue-600"><?php echo $summary['total_bookings']; ?></p>
            </div>
            <div class="text-center">
                <p class="text-gray-500 text-sm mb-1">Total Items</p>
                <p class="text-2xl font-bold text-green-600"><?php echo $loadSheetData->num_rows; ?></p>
            </div>
            <div class="text-center">
                <p class="text-gray-500 text-sm mb-1">Total Value</p>
                <p class="text-2xl font-bold text-orange-600"><?php echo formatCurrency($summary['total_amount']); ?></p>
            </div>
        </div>

        <!-- Products Table -->
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50 border-b">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">#</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">SKU</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Product Name</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Total Quantity</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">In Bookings</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php 
                    if ($loadSheetData->num_rows > 0):
                        $index = 1;
                        $loadSheetData->data_seek(0);
                        while ($item = $loadSheetData->fetch_assoc()): 
                    ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 text-center"><?php echo $index++; ?></td>
                            <td class="px-6 py-4">
                                <span class="font-mono text-sm"><?php echo $item['sku']; ?></span>
                            </td>
                            <td class="px-6 py-4">
                                <div class="font-semibold"><?php echo $item['product_name']; ?></div>
                                <?php if (!empty($item['variety'])): ?>
                                    <div class="text-xs text-gray-500"><?php echo $item['variety']; ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <span class="text-lg font-bold text-blue-600">
                                    <?php echo number_format($item['total_quantity'], 2); ?>
                                </span>
                                <span class="text-sm text-gray-500"><?php echo $item['unit']; ?></span>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <span class="bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-sm font-semibold">
                                    <?php echo $item['booking_count']; ?> booking(s)
                                </span>
                            </td>
                        </tr>
                    <?php 
                        endwhile;
                    else:
                    ?>
                        <tr>
                            <td colspan="5" class="px-6 py-8 text-center text-gray-500">
                                No bookings found for this booker on <?php echo formatDate($date); ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php else: ?>
    <!-- Empty State -->
    <div class="bg-white rounded-lg shadow p-12 text-center">
        <svg class="w-24 h-24 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
        </svg>
        <h3 class="text-xl font-semibold text-gray-700 mb-2">No Load Sheet Generated</h3>
        <p class="text-gray-500">Select a booker and date above to generate a load sheet</p>
    </div>
<?php endif; ?>

<?php
include '../../includes/footer.php';
?>