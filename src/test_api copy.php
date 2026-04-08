<?php
// test_api.php
require_once 'config/database.php';
require_once 'models/StockCount.php';

header('Content-Type: application/json');

error_log("=== TEST API START ===");

$database = Database::getInstance();
if (!$database) {
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}
error_log("Database connected");

$stockCount = new StockCount();
$sessionId = 16;

// ທົດສອບຂັ້ນຕົ້ນ: ກວດສອບວ່າ session ມີຢູ່ບໍ
$stmt = $database->prepare("SELECT id, session_code, session_name FROM stock_count_sessions WHERE id = ?");
$stmt->execute([$sessionId]);
$session = $stmt->fetch(PDO::FETCH_ASSOC);
error_log("Session check: " . json_encode($session));

if (!$session) {
    echo json_encode(['error' => 'Session not found', 'session_id' => $sessionId]);
    exit;
}

// ທົດສອບຂັ້ນທີ 2: ດຶງຂໍ້ມູນຈາກ stock_count_details
$stmt = $database->prepare("SELECT * FROM stock_count_details WHERE session_id = ?");
$stmt->execute([$sessionId]);
$details = $stmt->fetchAll(PDO::FETCH_ASSOC);
error_log("Stock count details found: " . count($details));
error_log("Details: " . json_encode($details));

// ທົດສອບຂັ້ນທີ 3: JOIN ກັບ inventory_items
$sql = "SELECT 
            d.*,
            i.item_code,
            i.item_name,
            i.barcode
        FROM stock_count_details d
        LEFT JOIN inventory_items i ON d.item_id = i.id
        WHERE d.session_id = ?";
$stmt = $database->prepare($sql);
$stmt->execute([$sessionId]);
$joinedDetails = $stmt->fetchAll(PDO::FETCH_ASSOC);
error_log("Joined details count: " . count($joinedDetails));
error_log("Joined details: " . json_encode($joinedDetails));

echo json_encode([
    'success' => true,
    'session' => $session,
    'direct_details' => $details,
    'joined_details' => $joinedDetails,
    'details_count' => count($details)
], JSON_PRETTY_PRINT);