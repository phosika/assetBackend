<?php
// src/models/InventoryStock.php
require_once __DIR__ . '/../config/database.php';

class InventoryStock {
    private $db;
    private $movementTypes = ['in', 'out', 'adjust', 'transfer'];
    private $referenceTypes = ['purchase', 'sale', 'adjustment', 'transfer', 'initial'];

    public function __construct() {
        $this->db = Database::getInstance();
    }

    // ==================== STOCK MANAGEMENT ====================

    /**
     * ດຶງຂໍ້ມູນສະຕ໋ອກທັງໝົດ (ພ້ອມຂໍ້ມູນສິນຄ້າ)
     */
    public function getAllStock($filters = []) {
        $sql = "SELECT s.*,
                    i.id as item_id,
                    i.item_code,
                    i.item_name,
                    i.item_name_en,
                    i.barcode,
                    i.purchase_price,
                    i.selling_price,
                    i.reorder_point,
                    i.minimum_stock,
                    i.maximum_stock,
                    i.is_active,
                    c.category_name,
                    sup.supplier_name,
                    w.warehouse_name,
                    w.id as warehouse_id
                FROM inventory_stock s
                INNER JOIN inventory_items i ON s.item_id = i.id
                LEFT JOIN asset_categories c ON i.category_id = c.id
                LEFT JOIN suppliers sup ON i.supplier_id = sup.id
                LEFT JOIN warehouses w ON s.warehouse_id = w.id
                WHERE 1=1";
        $params = [];

        // ... ເງື່ອນໄຂອື່ນໆ ...

        error_log("Stock SQL: " . $sql);
        error_log("Stock params: " . json_encode($params));

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $stock = $stmt->fetchAll();
        
        error_log("Stock data count: " . count($stock));

        // ... ສ່ວນທີ່ເຫຼືອ ...
    }
    /**
     * ດຶງຂໍ້ມູນສະຕ໋ອກຕາມ ID
     */
    public function getStockById($id) {
        $sql = "SELECT s.*,
                       i.item_code,
                       i.item_name,
                       i.item_name_en,
                       i.barcode,
                       i.purchase_price,
                       i.selling_price,
                       i.reorder_point,
                       i.minimum_stock,
                       i.maximum_stock,
                       c.category_name,
                       sup.supplier_name,
                       w.warehouse_name
                FROM inventory_stock s
                INNER JOIN inventory_items i ON s.item_id = i.id
                LEFT JOIN asset_categories c ON i.category_id = c.id
                LEFT JOIN suppliers sup ON i.supplier_id = sup.id
                LEFT JOIN warehouses w ON s.warehouse_id = w.id
                WHERE s.id = ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    /**
     * ດຶງຂໍ້ມູນສະຕ໋ອກຕາມ Item ID
     */
    public function getStockByItemId($itemId, $warehouseId = null) {
        $sql = "SELECT s.*,
                       w.warehouse_name
                FROM inventory_stock s
                LEFT JOIN warehouses w ON s.warehouse_id = w.id
                WHERE s.item_id = ?";
        $params = [$itemId];

        if ($warehouseId) {
            $sql .= " AND s.warehouse_id = ?";
            $params[] = $warehouseId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * ສ້າງສະຕ໋ອກໃໝ່ (ເມື່ອເພີ່ມສິນຄ້າໃໝ່)
     */
    public function createStock($itemId, $warehouseId = null, $createdBy = null) {
        // ກວດສອບວ່າມີສະຕ໋ອກແລ້ວບໍ
        $checkSql = "SELECT id FROM inventory_stock WHERE item_id = ?";
        if ($warehouseId) {
            $checkSql .= " AND warehouse_id = ?";
        }
        
        $checkStmt = $this->db->prepare($checkSql);
        $checkParams = [$itemId];
        if ($warehouseId) {
            $checkParams[] = $warehouseId;
        }
        $checkStmt->execute($checkParams);
        
        if ($checkStmt->fetch()) {
            return ['success' => false, 'message' => 'Stock already exists for this item'];
        }

        $sql = "INSERT INTO inventory_stock (
                    item_id, warehouse_id, current_quantity, 
                    reserved_quantity, available_quantity, created_by, created_at
                ) VALUES (?, ?, 0, 0, 0, ?, NOW())";
        
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$itemId, $warehouseId, $createdBy]);
            
            $stockId = $this->db->lastInsertId();
            
            // ບັນທຶກ initial movement
            $this->addMovement([
                'item_id' => $itemId,
                'movement_type' => 'in',
                'quantity' => 0,
                'reference_type' => 'initial',
                'from_location' => null,
                'to_location' => $warehouseId,
                'notes' => 'Initial stock creation',
                'created_by' => $createdBy
            ]);

            return [
                'success' => true,
                'stock_id' => $stockId,
                'message' => 'Stock created successfully'
            ];
        } catch (PDOException $e) {
            error_log("Create stock failed: " . $e->getMessage());
            return ['success' => false, 'message' => 'Creation failed: ' . $e->getMessage()];
        }
    }

    /**
     * ປັບປຸງສະຕ໋ອກ (ບວກ/ລົບ)
     */
    public function adjustStock($itemId, $quantity, $type = 'add', $reference = [], $createdBy = null) {
        // ກວດສອບວ່າມີສະຕ໋ອກບໍ
        $stock = $this->getStockByItemId($itemId, $reference['warehouse_id'] ?? null);
        
        if (empty($stock)) {
            // ສ້າງສະຕ໋ອກໃໝ່ຖ້າບໍ່ມີ
            $result = $this->createStock($itemId, $reference['warehouse_id'] ?? null, $createdBy);
            if (!$result['success']) {
                return $result;
            }
            $stockId = $result['stock_id'];
            $currentStock = ['current_quantity' => 0, 'reserved_quantity' => 0];
        } else {
            $stockId = $stock[0]['id'];
            $currentStock = $this->getStockById($stockId);
        }

        // ຄຳນວນຈຳນວນໃໝ່
        $newQuantity = $currentStock['current_quantity'];
        
        if ($type === 'add') {
            $newQuantity += $quantity;
        } elseif ($type === 'subtract') {
            // ກວດສອບວ່າສະຕ໋ອກພໍບໍ
            if ($currentStock['current_quantity'] < $quantity) {
                return ['success' => false, 'message' => 'Insufficient stock'];
            }
            $newQuantity -= $quantity;
        } elseif ($type === 'set') {
            $newQuantity = $quantity;
        }

        // ຄຳນວນ available quantity (current - reserved)
        $availableQuantity = $newQuantity - ($currentStock['reserved_quantity'] ?? 0);

        // ອັບເດດສະຕ໋ອກ
        $updateSql = "UPDATE inventory_stock SET 
                      current_quantity = ?,
                      available_quantity = ?,
                      updated_by = ?,
                      updated_at = NOW()
                      WHERE id = ?";
        
        try {
            $updateStmt = $this->db->prepare($updateSql);
            $updateStmt->execute([$newQuantity, $availableQuantity, $createdBy, $stockId]);

            // ບັນທຶກ movement
            $movementType = $type === 'add' ? 'in' : ($type === 'subtract' ? 'out' : 'adjust');
            $this->addMovement([
                'item_id' => $itemId,
                'movement_type' => $movementType,
                'quantity' => $quantity,
                'reference_type' => $reference['type'] ?? 'adjustment',
                'reference_id' => $reference['id'] ?? null,
                'from_location' => $reference['from_location'] ?? null,
                'to_location' => $reference['to_location'] ?? null,
                'notes' => $reference['notes'] ?? null,
                'created_by' => $createdBy
            ]);

            return [
                'success' => true,
                'stock_id' => $stockId,
                'new_quantity' => $newQuantity,
                'message' => 'Stock adjusted successfully'
            ];
        } catch (PDOException $e) {
            error_log("Adjust stock failed: " . $e->getMessage());
            return ['success' => false, 'message' => 'Adjustment failed: ' . $e->getMessage()];
        }
    }

    // ==================== STOCK COUNTS ====================

    /**
     * ບັນທຶກການນັບສະຕ໋ອກ
     */
    public function recordStockCount($itemId, $actualQuantity, $notes = null, $countedBy = null) {
        try {
            // ເລີ່ມ transaction
            $this->db->beginTransaction();

            // ດຶງຂໍ້ມູນສະຕ໋ອກປັດຈຸບັນ
            $stock = $this->getStockByItemId($itemId);
            
            if (empty($stock)) {
                $this->db->rollBack();
                return ['success' => false, 'message' => 'Stock not found'];
            }

            $stockId = $stock[0]['id'];
            $currentStock = $this->getStockById($stockId);
            $systemQuantity = $currentStock['current_quantity'];
            $difference = $actualQuantity - $systemQuantity;

            // ບັນທຶກການນັບ
            $countSql = "INSERT INTO inventory_stock_counts (
                            item_id, count_date, system_quantity, actual_quantity,
                            difference, count_by, notes, created_at
                         ) VALUES (?, NOW(), ?, ?, ?, ?, ?, NOW())";
            
            $countStmt = $this->db->prepare($countSql);
            $countStmt->execute([$itemId, $systemQuantity, $actualQuantity, $difference, $countedBy, $notes]);

            // ຖ້າມີຜົນຕ່າງ, ປັບປຸງສະຕ໋ອກ
            if ($difference != 0) {
                $adjustResult = $this->adjustStock($itemId, $actualQuantity, 'set', [
                    'type' => 'adjustment',
                    'notes' => "Stock count adjustment: " . ($notes ?: "Difference: {$difference}")
                ], $countedBy);

                if (!$adjustResult['success']) {
                    $this->db->rollBack();
                    return $adjustResult;
                }
            }

            // ອັບເດດ last count ໃນ stock
            $updateSql = "UPDATE inventory_stock SET 
                          last_count_date = NOW(),
                          last_count_quantity = ?
                          WHERE id = ?";
            $updateStmt = $this->db->prepare($updateSql);
            $updateStmt->execute([$actualQuantity, $stockId]);

            $this->db->commit();

            return [
                'success' => true,
                'count_id' => $this->db->lastInsertId(),
                'difference' => $difference,
                'message' => $difference == 0 
                    ? 'Stock count recorded successfully (no difference)' 
                    : "Stock count recorded with difference of {$difference}"
            ];
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Record stock count failed: " . $e->getMessage());
            return ['success' => false, 'message' => 'Record failed: ' . $e->getMessage()];
        }
    }

    /**
     * ບັນທຶກການນັບສະຕ໋ອກຫຼາຍລາຍການ
     */
    public function recordBatchStockCount($counts, $countedBy = null) {
        try {
            $this->db->beginTransaction();
            
            $results = [];
            foreach ($counts as $count) {
                $result = $this->recordStockCount(
                    $count['item_id'],
                    $count['actual_quantity'],
                    $count['notes'] ?? 'Batch stock count',
                    $countedBy
                );
                
                $results[] = [
                    'item_id' => $count['item_id'],
                    'success' => $result['success'],
                    'difference' => $result['difference'] ?? 0,
                    'message' => $result['message'] ?? ''
                ];

                if (!$result['success']) {
                    $this->db->rollBack();
                    return [
                        'success' => false,
                        'message' => 'Batch count failed at item ID: ' . $count['item_id'],
                        'results' => $results
                    ];
                }
            }

            $this->db->commit();
            return [
                'success' => true,
                'message' => 'Batch stock count completed successfully',
                'results' => $results
            ];
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Batch stock count failed: " . $e->getMessage());
            return ['success' => false, 'message' => 'Batch count failed: ' . $e->getMessage()];
        }
    }

    /**
     * ດຶງປະຫວັດການນັບສະຕ໋ອກ
     */
    public function getStockCounts($filters = []) {
        $sql = "SELECT c.*,
                       i.item_code,
                       i.item_name,
                       u.first_name as counted_by_name
                FROM inventory_stock_counts c
                INNER JOIN inventory_items i ON c.item_id = i.id
                LEFT JOIN users u ON c.count_by = u.id
                WHERE 1=1";
        $params = [];

        if (!empty($filters['item_id'])) {
            $sql .= " AND c.item_id = ?";
            $params[] = $filters['item_id'];
        }

        if (!empty($filters['from_date'])) {
            $sql .= " AND DATE(c.count_date) >= ?";
            $params[] = $filters['from_date'];
        }

        if (!empty($filters['to_date'])) {
            $sql .= " AND DATE(c.count_date) <= ?";
            $params[] = $filters['to_date'];
        }

        $sql .= " ORDER BY c.count_date DESC";

        $page = $filters['page'] ?? 1;
        $limit = $filters['limit'] ?? 50;
        $offset = ($page - 1) * $limit;
        $sql .= " LIMIT {$limit} OFFSET {$offset}";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $counts = $stmt->fetchAll();

        // ນັບຈຳນວນທັງໝົດ
        $countSql = "SELECT COUNT(*) as total FROM inventory_stock_counts WHERE 1=1";
        $countParams = [];

        if (!empty($filters['item_id'])) {
            $countSql .= " AND item_id = ?";
            $countParams[] = $filters['item_id'];
        }

        $countStmt = $this->db->prepare($countSql);
        $countStmt->execute($countParams);
        $totalResult = $countStmt->fetch();
        $total = $totalResult ? (int)$totalResult['total'] : 0;

        return [
            'data' => $counts,
            'total' => $total,
            'current_page' => $page,
            'per_page' => $limit,
            'last_page' => $total > 0 ? ceil($total / $limit) : 1
        ];
    }

    /**
     * ດຶງລາຍງານສະຫຼຸບການນັບສະຕ໋ອກ
     */
    public function getStockCountSummary($fromDate = null, $toDate = null) {
        $sql = "SELECT 
                    DATE(c.count_date) as count_date,
                    COUNT(DISTINCT c.item_id) as items_counted,
                    SUM(CASE WHEN c.difference != 0 THEN 1 ELSE 0 END) as items_with_diff,
                    SUM(c.difference) as total_difference,
                    COUNT(*) as total_counts
                FROM inventory_stock_counts c
                WHERE 1=1";
        $params = [];

        if ($fromDate) {
            $sql .= " AND DATE(c.count_date) >= ?";
            $params[] = $fromDate;
        }

        if ($toDate) {
            $sql .= " AND DATE(c.count_date) <= ?";
            $params[] = $toDate;
        }

        $sql .= " GROUP BY DATE(c.count_date) ORDER BY count_date DESC LIMIT 30";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    // ==================== MOVEMENTS ====================

    /**
     * ເພີ່ມປະຫວັດການເຄື່ອນໄຫວ
     */
    private function addMovement($data) {
        $sql = "INSERT INTO inventory_movements (
                    item_id, movement_type, quantity, reference_type,
                    reference_id, from_location, to_location, notes, created_by, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['item_id'],
                $data['movement_type'],
                $data['quantity'],
                $data['reference_type'],
                $data['reference_id'],
                $data['from_location'],
                $data['to_location'],
                $data['notes'],
                $data['created_by']
            ]);
            return true;
        } catch (PDOException $e) {
            error_log("Add movement failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * ດຶງປະຫວັດການເຄື່ອນໄຫວ
     */
    public function getMovements($filters = []) {
        $sql = "SELECT m.*,
                       i.item_code,
                       i.item_name,
                       u.first_name as created_by_name
                FROM inventory_movements m
                INNER JOIN inventory_items i ON m.item_id = i.id
                LEFT JOIN users u ON m.created_by = u.id
                WHERE 1=1";
        $params = [];

        if (!empty($filters['item_id'])) {
            $sql .= " AND m.item_id = ?";
            $params[] = $filters['item_id'];
        }

        if (!empty($filters['movement_type'])) {
            $sql .= " AND m.movement_type = ?";
            $params[] = $filters['movement_type'];
        }

        if (!empty($filters['from_date'])) {
            $sql .= " AND DATE(m.created_at) >= ?";
            $params[] = $filters['from_date'];
        }

        if (!empty($filters['to_date'])) {
            $sql .= " AND DATE(m.created_at) <= ?";
            $params[] = $filters['to_date'];
        }

        $sql .= " ORDER BY m.created_at DESC";

        $page = $filters['page'] ?? 1;
        $limit = $filters['limit'] ?? 50;
        $offset = ($page - 1) * $limit;
        $sql .= " LIMIT {$limit} OFFSET {$offset}";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    // ==================== STATISTICS ====================

    /**
     * ດຶງສະຖິຕິສະຕ໋ອກ
     */
    public function getStockStats() {
        $stats = [];

        // ມູນຄ່າສະຕ໋ອກທັງໝົດ (ຕົ້ນທຶນ)
        $costSql = "SELECT SUM(s.current_quantity * i.purchase_price) as total_cost
                    FROM inventory_stock s
                    INNER JOIN inventory_items i ON s.item_id = i.id
                    WHERE s.current_quantity > 0";
        $costStmt = $this->db->query($costSql);
        $stats['total_cost'] = $costStmt->fetch()['total_cost'] ?? 0;

        // ມູນຄ່າສະຕ໋ອກທັງໝົດ (ລາຄາຂາຍ)
        $valueSql = "SELECT SUM(s.current_quantity * i.selling_price) as total_value
                     FROM inventory_stock s
                     INNER JOIN inventory_items i ON s.item_id = i.id
                     WHERE s.current_quantity > 0";
        $valueStmt = $this->db->query($valueSql);
        $stats['total_value'] = $valueStmt->fetch()['total_value'] ?? 0;

        // ຈຳນວນລາຍການສະຕ໋ອກ
        $countSql = "SELECT COUNT(*) as total_items FROM inventory_stock WHERE current_quantity > 0";
        $countStmt = $this->db->query($countSql);
        $stats['total_items'] = $countStmt->fetch()['total_items'] ?? 0;

        // ສະຕ໋ອກຕໍ່າ
        $lowStockSql = "SELECT COUNT(*) as low_stock
                        FROM inventory_stock s
                        INNER JOIN inventory_items i ON s.item_id = i.id
                        WHERE i.reorder_point IS NOT NULL 
                          AND s.current_quantity <= i.reorder_point
                          AND s.current_quantity > 0";
        $lowStockStmt = $this->db->query($lowStockSql);
        $stats['low_stock'] = $lowStockStmt->fetch()['low_stock'] ?? 0;

        // ສະຕ໋ອກເກີນ
        $overstockSql = "SELECT COUNT(*) as overstock
                         FROM inventory_stock s
                         INNER JOIN inventory_items i ON s.item_id = i.id
                         WHERE i.maximum_stock IS NOT NULL 
                           AND s.current_quantity >= i.maximum_stock";
        $overstockStmt = $this->db->query($overstockSql);
        $stats['overstock'] = $overstockStmt->fetch()['overstock'] ?? 0;

        // ສະຕ໋ອກໝົດ
        $outOfStockSql = "SELECT COUNT(*) as out_of_stock
                          FROM inventory_stock
                          WHERE current_quantity <= 0";
        $outOfStockStmt = $this->db->query($outOfStockSql);
        $stats['out_of_stock'] = $outOfStockStmt->fetch()['out_of_stock'] ?? 0;

        return $stats;
    }
}
?>