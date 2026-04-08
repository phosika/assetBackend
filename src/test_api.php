<?php
// test_route.php
require_once 'controllers/StockCountController.php';

$controller = new StockCountController();
$sessionId = 16;

echo "Testing getStockCountItems with ID: $sessionId\n";
$controller->getStockCountItems($sessionId);

?>