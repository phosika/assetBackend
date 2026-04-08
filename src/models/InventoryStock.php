<?php

require_once __DIR__ . '/../config/database.php';

class InventoryStock {
    private $db;
    private $table = 'inventory_stocks'; // ປ່ຽນຈາກ 'inventory_stock' ເປັນ 'inventory_stocks'

    public function __construct() {
        $this->db = Database::getInstance();
        if (!$this->db) {
            error_log("Database connection failed in InventoryStock model");
            throw new Exception("Database connection failed");
        }
    }

    /**
     * ດຶງຂໍ້ມູນສະຕ໋ອກທັງໝົດ ພ້ອມການກັ່ນຕອງ ແລະ ການແບ່ງໜ້າ
     */
    public function getAllStock($filters = []) {
        try {
            if (!$this->db) {
                throw new Exception("Database connection not available");
            }

            // ສ້າງ SQL ພື້ນຖານ - ໃຊ້ quantity ແທນ current_quantity
            $sql = "SELECT 
                        s.id,
                        s.item_id,
                        s.warehouse_id,
                        s.quantity as current_quantity,
                        s.reserved_quantity,
                        (s.quantity - COALESCE(s.reserved_quantity, 0)) as available_quantity,
                        s.location as shelf_location,
                        s.batch_number,
                        s.expiry_date,
                        s.status,
                        s.created_at,
                        s.updated_at,
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
                        w.warehouse_name
                    FROM {$this->table} s
                    INNER JOIN inventory_items i ON s.item_id = i.id
                    LEFT JOIN asset_categories c ON i.category_id = c.id
                    LEFT JOIN suppliers sup ON i.supplier_id = sup.id
                    LEFT JOIN warehouses w ON s.warehouse_id = w.id
                    WHERE s.status = 'active'";
            
            $params = [];
            $countSql = "SELECT COUNT(*) as total 
                        FROM {$this->table} s 
                        INNER JOIN inventory_items i ON s.item_id = i.id
                        WHERE s.status = 'active'";
            $countParams = [];

            // ເພີ່ມເງື່ອນໄຂການຄົ້ນຫາ
            if (!empty($filters['search'])) {
                $sql .= " AND (i.item_code LIKE ? OR i.item_name LIKE ? OR i.barcode LIKE ?)";
                $searchTerm = "%{$filters['search']}%";
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                
                $countSql .= " AND (i.item_code LIKE ? OR i.item_name LIKE ? OR i.barcode LIKE ?)";
                $countParams[] = $searchTerm;
                $countParams[] = $searchTerm;
                $countParams[] = $searchTerm;
            }

            if (!empty($filters['warehouse_id'])) {
                $sql .= " AND s.warehouse_id = ?";
                $params[] = $filters['warehouse_id'];
                
                $countSql .= " AND s.warehouse_id = ?";
                $countParams[] = $filters['warehouse_id'];
            }

            if (!empty($filters['item_id'])) {
                $sql .= " AND s.item_id = ?";
                $params[] = $filters['item_id'];
                
                $countSql .= " AND s.item_id = ?";
                $countParams[] = $filters['item_id'];
            }

            // ເພີ່ມເງື່ອນໄຂສະຖານະສະຕ໋ອກ
            if (!empty($filters['low_stock']) && $filters['low_stock'] === true) {
                $sql .= " AND i.reorder_point IS NOT NULL AND s.quantity <= i.reorder_point AND s.quantity > 0";
                $countSql .= " AND i.reorder_point IS NOT NULL AND s.quantity <= i.reorder_point AND s.quantity > 0";
            }

            if (!empty($filters['out_of_stock']) && $filters['out_of_stock'] === true) {
                $sql .= " AND s.quantity <= 0";
                $countSql .= " AND s.quantity <= 0";
            }

            if (!empty($filters['overstock']) && $filters['overstock'] === true) {
                $sql .= " AND i.maximum_stock IS NOT NULL AND s.quantity >= i.maximum_stock";
                $countSql .= " AND i.maximum_stock IS NOT NULL AND s.quantity >= i.maximum_stock";
            }

            // ນັບຈຳນວນທັງໝົດ
            $countStmt = $this->db->prepare($countSql);
            if (!empty($countParams)) {
                $countStmt->execute($countParams);
            } else {
                $countStmt->execute();
            }
            
            $totalResult = $countStmt->fetch(PDO::FETCH_ASSOC);
            $total = $totalResult ? (int)$totalResult['total'] : 0;

            // ເພີ່ມການຈັດລຽງ
            $sortBy = $filters['sort_by'] ?? 's.quantity';
            $sortOrder = $filters['sort_order'] ?? 'DESC';
            
            $allowedSortColumns = [
                's.quantity', 'i.item_code', 'i.item_name', 
                's.reserved_quantity', 'i.purchase_price', 'i.selling_price'
            ];
            
            if (!in_array($sortBy, $allowedSortColumns)) {
                $sortBy = 's.quantity';
            }
            
            $sortOrder = strtoupper($sortOrder) === 'ASC' ? 'ASC' : 'DESC';
            $sql .= " ORDER BY {$sortBy} {$sortOrder}";

            // ເພີ່ມ LIMIT ແລະ OFFSET
            $page = isset($filters['page']) ? max(1, (int)$filters['page']) : 1;
            $limit = isset($filters['limit']) ? (int)$filters['limit'] : 20;
            $offset = ($page - 1) * $limit;
            
            $sql .= " LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;

            error_log("SQL Query: " . $sql);
            error_log("Params: " . json_encode($params));

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $stock = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'data' => $stock,
                'total' => $total,
                'current_page' => $page,
                'per_page' => $limit,
                'last_page' => $total > 0 ? ceil($total / $limit) : 1
            ];
            
        } catch (PDOException $e) {
            error_log("PDO Error in getAllStock: " . $e->getMessage());
            return [
                'data' => [],
                'total' => 0,
                'current_page' => 1,
                'per_page' => 20,
                'last_page' => 1,
                'error' => $e->getMessage()
            ];
        } catch (Exception $e) {
            error_log("Error in getAllStock: " . $e->getMessage());
            return [
                'data' => [],
                'total' => 0,
                'current_page' => 1,
                'per_page' => 20,
                'last_page' => 1,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * ດຶງສະຖິຕິສະຕ໋ອກ
     */
    public function getStockStats() {
        try {
            if (!$this->db) {
                throw new Exception("Database connection not available");
            }

            $stats = [];

            // ມູນຄ່າລວມ (ຕົ້ນທຶນ) - ໃຊ້ quantity ແທນ current_quantity
            $costSql = "SELECT COALESCE(SUM(s.quantity * i.purchase_price), 0) as total_cost
                        FROM {$this->table} s
                        INNER JOIN inventory_items i ON s.item_id = i.id
                        WHERE s.status = 'active'";
            $costStmt = $this->db->query($costSql);
            $costResult = $costStmt->fetch(PDO::FETCH_ASSOC);
            $stats['total_cost'] = $costResult ? (float)$costResult['total_cost'] : 0;

            // ມູນຄ່າລວມ (ຂາຍ)
            $valueSql = "SELECT COALESCE(SUM(s.quantity * i.selling_price), 0) as total_value
                         FROM {$this->table} s
                         INNER JOIN inventory_items i ON s.item_id = i.id
                         WHERE s.status = 'active'";
            $valueStmt = $this->db->query($valueSql);
            $valueResult = $valueStmt->fetch(PDO::FETCH_ASSOC);
            $stats['total_value'] = $valueResult ? (float)$valueResult['total_value'] : 0;

            // ຈຳນວນລາຍການທັງໝົດ
            $countSql = "SELECT COUNT(*) as total_items FROM {$this->table} WHERE status = 'active'";
            $countStmt = $this->db->query($countSql);
            $countResult = $countStmt->fetch(PDO::FETCH_ASSOC);
            $stats['total_items'] = $countResult ? (int)$countResult['total_items'] : 0;

            // ນັບສິນຄ້າທີ່ໝົດສະຕ໋ອກ
            $outSql = "SELECT COUNT(*) as out_of_stock FROM {$this->table} WHERE quantity <= 0 AND status = 'active'";
            $outStmt = $this->db->query($outSql);
            $outResult = $outStmt->fetch(PDO::FETCH_ASSOC);
            $stats['out_of_stock'] = $outResult ? (int)$outResult['out_of_stock'] : 0;

            // ນັບສິນຄ້າທີ່ຕໍ່າກວ່າ reorder point
            $lowSql = "SELECT COUNT(*) as low_stock 
                       FROM {$this->table} s
                       INNER JOIN inventory_items i ON s.item_id = i.id
                       WHERE s.status = 'active'
                         AND s.quantity > 0 
                         AND i.reorder_point IS NOT NULL 
                         AND s.quantity <= i.reorder_point";
            $lowStmt = $this->db->query($lowSql);
            $lowResult = $lowStmt->fetch(PDO::FETCH_ASSOC);
            $stats['low_stock'] = $lowResult ? (int)$lowResult['low_stock'] : 0;

            // ນັບສິນຄ້າທີ່ເກີນ maximum stock
            $overSql = "SELECT COUNT(*) as overstock 
                        FROM {$this->table} s
                        INNER JOIN inventory_items i ON s.item_id = i.id
                        WHERE s.status = 'active'
                          AND i.maximum_stock IS NOT NULL 
                          AND s.quantity >= i.maximum_stock";
            $overStmt = $this->db->query($overSql);
            $overResult = $overStmt->fetch(PDO::FETCH_ASSOC);
            $stats['overstock'] = $overResult ? (int)$overResult['overstock'] : 0;

            return $stats;
            
        } catch (Exception $e) {
            error_log("Error in getStockStats: " . $e->getMessage());
            return [
                'total_cost' => 0,
                'total_value' => 0,
                'total_items' => 0,
                'low_stock' => 0,
                'overstock' => 0,
                'out_of_stock' => 0
            ];
        }
    }

    /**
     * ປັບປຸງສະຕ໋ອກ (ໃຊ້ສຳລັບການຊື້ເຂົ້າ)
     */
    public function addStock($itemId, $quantity, $warehouseId = 1, $createdBy = null, $location = null, $batchNumber = null, $expiryDate = null) {
        try {
            // ກວດສອບວ່າມີ record ຂອງສິນຄ້ານີ້ແລ້ວບໍ
            $sql = "SELECT id, quantity FROM {$this->table} WHERE item_id = ? AND warehouse_id = ? AND status = 'active'";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$itemId, $warehouseId]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                // ອັບເດດຖ້າມີແລ້ວ
                $newQuantity = $existing['quantity'] + $quantity;
                
                $sql = "UPDATE {$this->table} 
                        SET quantity = ?,
                            updated_at = NOW(),
                            updated_by = ?
                        WHERE id = ?";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([$newQuantity, $createdBy, $existing['id']]);
                
                return [
                    'success' => true,
                    'message' => 'ເພີ່ມສະຕ໋ອກສຳເລັດ',
                    'stock_id' => $existing['id']
                ];
            } else {
                // ສ້າງໃໝ່ຖ້າຍັງບໍ່ມີ
                $sql = "INSERT INTO {$this->table} 
                        (item_id, warehouse_id, quantity, reserved_quantity, location, batch_number, expiry_date, status, created_at, created_by) 
                        VALUES (?, ?, ?, 0, ?, ?, ?, 'active', NOW(), ?)";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([$itemId, $warehouseId, $quantity, $location, $batchNumber, $expiryDate, $createdBy]);
                
                return [
                    'success' => true,
                    'message' => 'ເພີ່ມສະຕ໋ອກສຳເລັດ',
                    'stock_id' => $this->db->lastInsertId()
                ];
            }

        } catch (Exception $e) {
            error_log("Error in addStock: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'ເພີ່ມສະຕ໋ອກບໍ່ສຳເລັດ: ' . $e->getMessage()
            ];
        }
    }

    // ເພີ່ມຟັງຊັນສຳລັບຮັບສິນຄ້າຈາກ Purchase Order
    public function receivePurchaseOrder($poId, $items, $userId) {
        try {
            $this->db->beginTransaction();
            $results = [];
            
            foreach ($items as $item) {
                $itemId = $item['item_id'];
                $quantity = $item['received_quantity'];
                $warehouseId = $item['warehouse_id'] ?? 1;
                
                if (!is_numeric($itemId) || !is_numeric($quantity)) {
                    error_log("Invalid item data: " . json_encode($item));
                    continue;
                }
                
                // ເພີ່ມສະຕ໋ອກ
                $result = $this->addStock($itemId, $quantity, $warehouseId, $userId);
                $results[] = $result;
            }
            
            $this->db->commit();
            
            $success = true;
            foreach ($results as $result) {
                if (!$result['success']) {
                    $success = false;
                    break;
                }
            }
            
            return [
                'success' => $success,
                'message' => $success ? 'Stock updated successfully' : 'Some items failed to update',
                'results' => $results
            ];
            
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error receiving purchase order: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // ເພີ່ມຟັງຊັນນີ້ໃນ InventoryStock.php

    public function getStockCounts($itemId = null, $limit = 50) {
        try {
            $sql = "SELECT 
                        sc.id,
                        sc.session_id,
                        sc.item_id,
                        sc.expected_quantity,
                        sc.counted_quantity,
                        sc.variance,
                        sc.variance_percent,
                        sc.status,
                        sc.counted_by,
                        sc.counted_at,
                        sc.notes,
                        i.item_code,
                        i.item_name,
                        i.barcode,
                        CONCAT(u.first_name, ' ', u.last_name) as counted_by_name
                    FROM stock_count_details sc
                    LEFT JOIN inventory_items i ON sc.item_id = i.id
                    LEFT JOIN users u ON sc.counted_by = u.id
                    WHERE 1=1";
            
            $params = [];
            
            if ($itemId) {
                $sql .= " AND sc.item_id = ?";
                $params[] = $itemId;
            }
            
            $sql .= " ORDER BY sc.counted_at DESC, sc.created_at DESC LIMIT ?";
            $params[] = $limit;
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $counts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return [
                'success' => true,
                'data' => $counts
            ];
            
        } catch (Exception $e) {
            error_log("Error getting stock counts: " . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => []
            ];
        }
    }

    public function getStockCountHistory($itemId = null, $limit = 50) {
        try {
            $sql = "SELECT 
                        sc.id,
                        sc.session_id,
                        sc.item_id,
                        sc.expected_quantity,
                        sc.counted_quantity,
                        sc.variance,
                        sc.variance_percent,
                        sc.status,
                        sc.counted_by,
                        sc.counted_at,
                        sc.notes,
                        sc.created_at,
                        i.item_code,
                        i.item_name,
                        i.barcode,
                        CONCAT(u.first_name, ' ', u.last_name) as counted_by_name
                    FROM stock_count_details sc
                    LEFT JOIN inventory_items i ON sc.item_id = i.id
                    LEFT JOIN users u ON sc.counted_by = u.id
                    WHERE sc.status = 'counted'";
            
            $params = [];
            
            if ($itemId) {
                $sql .= " AND sc.item_id = ?";
                $params[] = $itemId;
            }
            
            $sql .= " ORDER BY sc.counted_at DESC, sc.created_at DESC LIMIT ?";
            $params[] = $limit;
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $counts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return $counts;
            
        } catch (Exception $e) {
            error_log("Error getting stock count history: " . $e->getMessage());
            return [];
        }
    }

// ເພີ່ມ method ນີ້ໃນ InventoryStock.php

    /**
     * ຕັດສະຕ໋ອກເມື່ອມີການຂາຍ
     */
    public function deductStock($itemId, $quantity, $referenceId, $referenceType, $notes = '', $createdBy = null) {
        try {
            // ຊອກຫາສະຕ໋ອກທີ່ມີຢູ່ (ໃຊ້ warehouse ທຳອິດທີ່ມີສິນຄ້າ)
            $stmt = $this->db->prepare("
                SELECT id, quantity, available_quantity 
                FROM inventory_stocks 
                WHERE item_id = ? AND status = 'active' AND quantity >= ?
                ORDER BY id ASC
                LIMIT 1
            ");
            $stmt->execute([$itemId, $quantity]);
            $stock = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$stock) {
                return [
                    'success' => false,
                    'message' => "ສິນຄ້າມີຈຳນວນບໍ່ພຽງພໍ (ຕ້ອງການ: {$quantity})"
                ];
            }
            
            // ຕັດສະຕ໋ອກ
            $newQuantity = $stock['quantity'] - $quantity;
            $newAvailable = ($stock['available_quantity'] ?? $stock['quantity']) - $quantity;
            
            $stmt = $this->db->prepare("
                UPDATE inventory_stocks 
                SET quantity = ?,
                    available_quantity = ?,
                    updated_by = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$newQuantity, $newAvailable, $createdBy, $stock['id']]);
            
            // ບັນທຶກປະຫວັດການເຄື່ອນໄຫວ
            $this->logStockMovement($itemId, $quantity, $referenceId, $referenceType, 'deduct', $notes, $createdBy);
            
            return [
                'success' => true,
                'message' => 'ຕັດສະຕ໋ອກສຳເລັດ'
            ];
            
        } catch (Exception $e) {
            error_log("Error in deductStock: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'ຕັດສະຕ໋ອກບໍ່ສຳເລັດ: ' . $e->getMessage()
            ];
        }
    }

    /**
     * ຄືນສະຕ໋ອກເມື່ອຍົກເລີກການຂາຍ
     */
    public function restoreStock($itemId, $quantity, $referenceId, $referenceType, $notes = '', $createdBy = null) {
        try {
            // ຊອກຫາສະຕ໋ອກທີ່ມີຢູ່
            $stmt = $this->db->prepare("
                SELECT id, quantity, available_quantity 
                FROM inventory_stocks 
                WHERE item_id = ? AND status = 'active'
                ORDER BY id ASC
                LIMIT 1
            ");
            $stmt->execute([$itemId]);
            $stock = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$stock) {
                // ຖ້າບໍ່ມີສະຕ໋ອກ, ສ້າງໃໝ່
                $stmt = $this->db->prepare("
                    INSERT INTO inventory_stocks (
                        item_id, quantity, available_quantity, status, created_by, updated_by, created_at, updated_at
                    ) VALUES (?, ?, ?, 'active', ?, ?, NOW(), NOW())
                ");
                $stmt->execute([$itemId, $quantity, $quantity, $createdBy, $createdBy]);
            } else {
                // ຄືນສະຕ໋ອກ
                $newQuantity = $stock['quantity'] + $quantity;
                $newAvailable = ($stock['available_quantity'] ?? $stock['quantity']) + $quantity;
                
                $stmt = $this->db->prepare("
                    UPDATE inventory_stocks 
                    SET quantity = ?,
                        available_quantity = ?,
                        updated_by = ?,
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$newQuantity, $newAvailable, $createdBy, $stock['id']]);
            }
            
            // ບັນທຶກປະຫວັດການເຄື່ອນໄຫວ
            $this->logStockMovement($itemId, $quantity, $referenceId, $referenceType, 'restore', $notes, $createdBy);
            
            return [
                'success' => true,
                'message' => 'ຄືນສະຕ໋ອກສຳເລັດ'
            ];
            
        } catch (Exception $e) {
            error_log("Error in restoreStock: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'ຄືນສະຕ໋ອກບໍ່ສຳເລັດ: ' . $e->getMessage()
            ];
        }
    }

    /**
     * ບັນທຶກປະຫວັດການເຄື່ອນໄຫວສະຕ໋ອກ
     */
    private function logStockMovement($itemId, $quantity, $referenceId, $referenceType, $movementType, $notes, $createdBy) {
        try {
            // ກວດສອບວ່າມີຕາຕະລາງ stock_movements ບໍ
            $stmt = $this->db->query("SHOW TABLES LIKE 'stock_movements'");
            if ($stmt->rowCount() > 0) {
                $sql = "INSERT INTO stock_movements (
                            item_id, quantity, movement_type, reference_type, reference_id, 
                            notes, created_by, created_at
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
                
                $stmt = $this->db->prepare($sql);
                $stmt->execute([
                    $itemId,
                    $quantity,
                    $movementType,
                    $referenceType,
                    $referenceId,
                    $notes,
                    $createdBy
                ]);
            }
        } catch (Exception $e) {
            error_log("Error logging stock movement: " . $e->getMessage());
        }
    }
}
?>