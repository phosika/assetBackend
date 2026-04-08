<?php
// test_model_direct.php
require_once 'config/database.php';
require_once 'models/StockCount.php';

$stockCount = new StockCount();
$sessionId = 16;

echo "=== Testing StockCount Model ===\n";

// ທົດສອບ getById
$session = $stockCount->getById($sessionId);
echo "\n1. getById($sessionId):\n";
print_r($session);

// ທົດສອບ query ໂດຍກົງ
$db = Database::getInstance();
$stmt = $db->prepare("SELECT * FROM stock_count_details WHERE session_id = ?");
$stmt->execute([$sessionId]);
$details = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "\n2. Direct query from stock_count_details:\n";
echo "Found " . count($details) . " records\n";
print_r($details);

// ທົດສອບ query ທີ່ມີ JOIN
$sql = "SELECT 
            d.*,
            i.item_code,
            i.item_name,
            i.barcode
        FROM stock_count_details d
        LEFT JOIN inventory_items i ON d.item_id = i.id
        WHERE d.session_id = ?";
$stmt = $db->prepare($sql);
$stmt->execute([$sessionId]);
$joinedDetails = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "\n3. Query with JOIN:\n";
echo "Found " . count($joinedDetails) . " records\n";
print_r($joinedDetails);

// ທົດສອບ method getSessionDetails
$result = $stockCount->getSessionDetails($sessionId);
echo "\n4. getSessionDetails result:\n";
echo "Details count: " . count($result['details']) . "\n";
echo "Stats: " . json_encode($result['stats']) . "\n";