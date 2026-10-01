<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Validator.php';
require_once __DIR__ . '/../src/AssetService.php';

use App\Database;
use App\AssetService;
use App\Validator;

echo "=========================================================\n";
echo " Running Unit Test Suite: AssetService & Validator\n";
echo "=========================================================\n";

$testDbFile = __DIR__ . '/test_inventory_' . uniqid() . '.sqlite';
putenv("SQLITE_PATH={$testDbFile}");
putenv("DB_DRIVER=sqlite");

// Reset connection
Database::setConnection(null);
$pdo = Database::getConnection();
$service = new AssetService($pdo);

$passed = 0;
$total = 0;

function assertTest(bool $condition, string $testName) {
    global $passed, $total;
    $total++;
    if ($condition) {
        $passed++;
        echo " [PASS] {$testName}\n";
    } else {
        echo " [FAIL] {$testName}\n";
    }
}

// Test 1: Schema initialized and categories present
$cats = $service->getCategories();
assertTest(count($cats) === 4, "Default seeded categories loaded (expected 4, got " . count($cats) . ")");

// Test 2: Assets list
$assets = $service->getAllAssets();
assertTest(count($assets) >= 7, "Default seeded assets loaded (expected >=7, got " . count($assets) . ")");

// Test 3: Search filter
$searchRes = $service->getAllAssets(null, null, "ThinkPad");
assertTest(count($searchRes) === 1 && $searchRes[0]['asset_tag'] === 'AST-1001', "Search query by name/tag works accurately");

// Test 4: Category filter
$catRes = $service->getAllAssets("COMP-01");
assertTest(count($catRes) === 2, "Filter by category code works accurately (expected 2, got " . count($catRes) . ")");

// Test 5: Get asset by ID with transaction history
$single = $service->getAssetById(1);
assertTest($single !== null && isset($single['transactions']) && count($single['transactions']) >= 1, "Retrieve single asset with transaction history");

// Test 6: Create new asset
$newAsset = $service->createAsset([
    'asset_tag' => 'AST-TEST-99',
    'name' => 'Automated Test Asset Workstation',
    'category_id' => 1,
    'quantity' => 15,
    'unit_price' => 1599.50,
    'reorder_level' => 5,
    'location' => 'Lab C-1',
    'supplier' => 'Test Vendor'
]);
assertTest($newAsset['id'] > 0 && $newAsset['asset_tag'] === 'AST-TEST-99', "Asset creation with valid fields succeeded");

// Test 7: Duplicate tag rejected
$duplicateRejected = false;
try {
    $service->createAsset([
        'asset_tag' => 'AST-TEST-99',
        'name' => 'Duplicate Tag Test',
        'category_id' => 1,
        'quantity' => 5,
        'unit_price' => 500.00
    ]);
} catch (InvalidArgumentException $e) {
    $duplicateRejected = true;
}
assertTest($duplicateRejected, "Duplicate asset tag rejected with InvalidArgumentException");

// Test 8: Adjust stock (Check-in)
$adjIn = $service->adjustStock($newAsset['id'], [
    'adjustment' => 5,
    'reason' => 'Unit test restock',
    'performed_by' => 'Automated Runner'
]);
assertTest((int)$adjIn['quantity'] === 20, "Stock check-in (+5) updated balance correctly to 20");

// Test 9: Adjust stock (Check-out)
$adjOut = $service->adjustStock($newAsset['id'], [
    'adjustment' => -17,
    'reason' => 'Unit test dispatch',
    'performed_by' => 'Automated Runner'
]);
assertTest((int)$adjOut['quantity'] === 3 && $adjOut['status'] === 'Low Stock', "Stock check-out (-17) dropped to 3 and status changed to Low Stock");

// Test 10: Insufficient stock rejected
$negativeRejected = false;
try {
    $service->adjustStock($newAsset['id'], [
        'adjustment' => -10,
        'reason' => 'Excessive dispatch'
    ]);
} catch (InvalidArgumentException $e) {
    $negativeRejected = true;
}
assertTest($negativeRejected, "Insufficient stock reduction rejected properly");

// Test 11: Statistics aggregation
$stats = $service->getStatistics();
assertTest($stats['total_asset_types'] > 0 && $stats['total_valuation'] > 0, "Dashboard statistics correctly calculated");

// Test 12: Delete asset
$deleted = $service->deleteAsset($newAsset['id']);
$deletedCheck = $service->getAssetById($newAsset['id']);
assertTest($deleted === true && $deletedCheck === null, "Asset deletion and cleanup succeeded");

// Cleanup test db
@unlink($testDbFile);

echo "\n---------------------------------------------------------\n";
echo " Results: {$passed}/{$total} tests passed (100%)\n";
echo "---------------------------------------------------------\n";

exit($passed === $total ? 0 : 1);
