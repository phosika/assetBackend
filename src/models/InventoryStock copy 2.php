<?php
// /home/phosika/Fixasset/assetapplication/BACKEND/src/models/InventoryStock.php

require_once __DIR__ . '/../config/database.php';

class InventoryStock {
    private $db;
    private $table = 'inventory_stock';

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
            // ກວດສອບການເຊື່ອມຕໍ່
            if (!$this->db) {
                throw new Exception("Database connection not available");
            }

            // ສ້າງ SQL ພື້ນຖານ
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
                           w.id as warehouse_id,
                           COALESCE(s.shelf_location, '') as shelf_location,
                           COALESCE(s.last_count_date, NULL) as last_count_date,
                           COALESCE(s.last_count_quantity, 0) as last_count_quantity
                    FROM {$this->table} s
                    INNER JOIN inventory_items i ON s.item_id = i.id
                    LEFT JOIN asset_categories c ON i.category_id = c.id
                    LEFT JOIN suppliers sup ON i.supplier_id = sup.id
                    LEFT JOIN warehouses w ON s.warehouse_id = w.id
                    WHERE 1=1";
            
            $params = [];
            $countSql = "SELECT COUNT(*) as total 
                        FROM {$this->table} s 
                        INNER JOIN inventory_items i ON s.item_id = i.id
                        WHERE 1=1";
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
                $sql .= " AND i.reorder_point IS NOT NULL AND s.current_quantity <= i.reorder_point";
                $countSql .= " AND i.reorder_point IS NOT NULL AND s.current_quantity <= i.reorder_point";
            }

            if (!empty($filters['out_of_stock']) && $filters['out_of_stock'] === true) {
                $sql .= " AND s.current_quantity <= 0";
                $countSql .= " AND s.current_quantity <= 0";
            }

            if (!empty($filters['overstock']) && $filters['overstock'] === true) {
                $sql .= " AND i.maximum_stock IS NOT NULL AND s.current_quantity >= i.maximum_stock";
                $countSql .= " AND i.maximum_stock IS NOT NULL AND s.current_quantity >= i.maximum_stock";
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
            $sortBy = $filters['sort_by'] ?? 's.current_quantity';
            $sortOrder = $filters['sort_order'] ?? 'DESC';
            
            // ປ້ອງກັນ SQL injection ສຳລັບ sort
            $allowedSortColumns = [
                's.current_quantity', 'i.item_code', 'i.item_name', 
                's.reserved_quantity', 's.available_quantity', 
                'i.purchase_price', 'i.selling_price'
            ];
            
            if (!in_array($sortBy, $allowedSortColumns)) {
                $sortBy = 's.current_quantity';
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

            // ດຶງຂໍ້ມູນ
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $stock = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // ຄຳນວນ available_quantity ຖ້າບໍ່ມີ
            foreach ($stock as &$item) {
                if (!isset($item['available_quantity']) || $item['available_quantity'] === null) {
                    $item['available_quantity'] = $item['current_quantity'] - ($item['reserved_quantity'] ?? 0);
                }
            }

            return [
                'data' => $stock,
                'total' => $total,
                'current_page' => $page,
                'per_page' => $limit,
                'last_page' => $total > 0 ? ceil($total / $limit) : 1
            ];
            
        } catch (PDOException $e) {
            error_log("PDO Error in getAllStock: " . $e->getMessage());
            error_log("SQL State: " . $e->getCode());
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

            // ມູນຄ່າລວມ (ຕົ້ນທຶນ)
            $costSql = "SELECT COALESCE(SUM(s.current_quantity * i.purchase_price), 0) as total_cost
                        FROM {$this->table} s
                        INNER JOIN inventory_items i ON s.item_id = i.id";
            $costStmt = $this->db->query($costSql);
            $costResult = $costStmt->fetch(PDO::FETCH_ASSOC);
            $stats['total_cost'] = $costResult ? (float)$costResult['total_cost'] : 0;

            // ມູນຄ່າລວມ (ຂາຍ)
            $valueSql = "SELECT COALESCE(SUM(s.current_quantity * i.selling_price), 0) as total_value
                         FROM {$this->table} s
                         INNER JOIN inventory_items i ON s.item_id = i.id";
            $valueStmt = $this->db->query($valueSql);
            $valueResult = $valueStmt->fetch(PDO::FETCH_ASSOC);
            $stats['total_value'] = $valueResult ? (float)$valueResult['total_value'] : 0;

            // ຈຳນວນລາຍການທັງໝົດ
            $countSql = "SELECT COUNT(*) as total_items FROM {$this->table}";
            $countStmt = $this->db->query($countSql);
            $countResult = $countStmt->fetch(PDO::FETCH_ASSOC);
            $stats['total_items'] = $countResult ? (int)$countResult['total_items'] : 0;

            // ນັບສິນຄ້າທີ່ໝົດສະຕ໋ອກ
            $outSql = "SELECT COUNT(*) as out_of_stock FROM {$this->table} WHERE current_quantity <= 0";
            $outStmt = $this->db->query($outSql);
            $outResult = $outStmt->fetch(PDO::FETCH_ASSOC);
            $stats['out_of_stock'] = $outResult ? (int)$outResult['out_of_stock'] : 0;

            // ນັບສິນຄ້າທີ່ຕໍ່າກວ່າ reorder point
            $lowSql = "SELECT COUNT(*) as low_stock 
                       FROM {$this->table} s
                       INNER JOIN inventory_items i ON s.item_id = i.id
                       WHERE s.current_quantity > 0 
                         AND i.reorder_point IS NOT NULL 
                         AND s.current_quantity <= i.reorder_point";
            $lowStmt = $this->db->query($lowSql);
            $lowResult = $lowStmt->fetch(PDO::FETCH_ASSOC);
            $stats['low_stock'] = $lowResult ? (int)$lowResult['low_stock'] : 0;

            // ນັບສິນຄ້າທີ່ເກີນ maximum stock
            $overSql = "SELECT COUNT(*) as overstock 
                        FROM {$this->table} s
                        INNER JOIN inventory_items i ON s.item_id = i.id
                        WHERE i.maximum_stock IS NOT NULL 
                          AND s.current_quantity >= i.maximum_stock";
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
     * ດຶງຂໍ້ມູນສະຕ໋ອກຕາມ item_id
     */
    public function getStockByItemId($itemId, $warehouseId = null) {
        try {
            $sql = "SELECT s.*,
                           i.item_code,
                           i.item_name,
                           i.barcode,
                           w.warehouse_name
                    FROM {$this->table} s
                    INNER JOIN inventory_items i ON s.item_id = i.id
                    LEFT JOIN warehouses w ON s.warehouse_id = w.id
                    WHERE s.item_id = ?";
            $params = [$itemId];

            if ($warehouseId) {
                $sql .= " AND s.warehouse_id = ?";
                $params[] = $warehouseId;
            }

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("Error in getStockByItemId: " . $e->getMessage());
            return [];
        }
    }

    /**
     * ດຶງຂໍ້ມູນສະຕ໋ອກຕາມ ID
     */
    public function getStockById($id) {
        try {
            $sql = "SELECT s.*,
                           i.item_code,
                           i.item_name,
                           i.barcode,
                           i.purchase_price,
                           i.selling_price,
                           i.reorder_point,
                           w.warehouse_name
                    FROM {$this->table} s
                    INNER JOIN inventory_items i ON s.item_id = i.id
                    LEFT JOIN warehouses w ON s.warehouse_id = w.id
                    WHERE s.id = ?";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id]);
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ?: null;
            
        } catch (Exception $e) {
            error_log("Error in getStockById: " . $e->getMessage());
            return null;
        }
    }

    /**
     * ດຶງປະຫວັດການເຄື່ອນໄຫວ
     */
    public function getMovements($filters = []) {
        try {
            // ສ້າງ SQL ສຳລັບດຶງຂໍ້ມູນການເຄື່ອນໄຫວ
            // ຖ້າມີຕາຕະລາງ stock_movements ໃຫ້ໃຊ້ຕາຕະລາງນັ້ນ
            // ຖ້າບໍ່ມີ, ສົ່ງຄ່າເລີ່ມຕົ້ນກັບໄປ
            return [];
        } catch (Exception $e) {
            error_log("Error in getMovements: " . $e->getMessage());
            return [];
        }
    }

    /**
     * ດຶງປະຫວັດການນັບສະຕ໋ອກ
     */
    public function getStockCounts($filters = []) {
        try {
            // ຖ້າມີຕາຕະລາງ stock_counts ໃຫ້ໃຊ້ຕາຕະລາງນັ້ນ
            // ຖ້າບໍ່ມີ, ສົ່ງຄ່າເລີ່ມຕົ້ນກັບໄປ
            return [
                'data' => [],
                'total' => 0,
                'current_page' => 1,
                'per_page' => 20,
                'last_page' => 1
            ];
        } catch (Exception $e) {
            error_log("Error in getStockCounts: " . $e->getMessage());
            return [
                'data' => [],
                'total' => 0,
                'current_page' => 1,
                'per_page' => 20,
                'last_page' => 1
            ];
        }
    }

    /**
     * ປັບປຸງສະຕ໋ອກ
     */
    public function adjustStock($itemId, $quantity, $type = 'add', $reference = [], $createdBy = null) {
        try {
            $warehouseId = $reference['warehouse_id'] ?? 1; // ສາງເລີ່ມຕົ້ນ
            
            // ກວດສອບວ່າມີສະຕ໋ອກສຳລັບສິນຄ້ານີ້ບໍ
            $checkSql = "SELECT id, current_quantity FROM {$this->table} 
                        WHERE item_id = ? AND warehouse_id = ?";
            $checkStmt = $this->db->prepare($checkSql);
            $checkStmt->execute([$itemId, $warehouseId]);
            $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                // ອັບເດດສະຕ໋ອກທີ່ມີຢູ່
                $newQuantity = $type === 'add' 
                    ? $existing['current_quantity'] + $quantity
                    : $existing['current_quantity'] - $quantity;
                
                if ($newQuantity < 0) {
                    return [
                        'success' => false,
                        'message' => 'Insufficient stock'
                    ];
                }

                $updateSql = "UPDATE {$this->table} 
                             SET current_quantity = ?, 
                                 available_quantity = ? - reserved_quantity,
                                 updated_at = NOW(),
                                 updated_by = ?
                             WHERE id = ?";
                
                $updateStmt = $this->db->prepare($updateSql);
                $updateStmt->execute([
                    $newQuantity,
                    $newQuantity,
                    $createdBy,
                    $existing['id']
                ]);

            } else {
                // ສ້າງສະຕ໋ອກໃໝ່
                $newQuantity = $type === 'add' ? $quantity : 0;
                
                $insertSql = "INSERT INTO {$this->table} 
                             (item_id, warehouse_id, current_quantity, available_quantity, 
                              created_at, created_by)
                             VALUES (?, ?, ?, ?, NOW(), ?)";
                
                $insertStmt = $this->db->prepare($insertSql);
                $insertStmt->execute([
                    $itemId,
                    $warehouseId,
                    $newQuantity,
                    $newQuantity,
                    $createdBy
                ]);
            }

            return [
                'success' => true,
                'message' => 'Stock adjusted successfully'
            ];

        } catch (Exception $e) {
            error_log("Error in adjustStock: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to adjust stock: ' . $e->getMessage()
            ];
        }
    }

    /**
     * ໂອນສະຕ໋ອກ
     */
    public function transferStock($itemId, $fromWarehouse, $toWarehouse, $quantity, $notes = null, $createdBy = null) {
        try {
            // ເລີ່ມ transaction
            $this->db->beginTransaction();

            // ກວດສອບສະຕ໋ອກຕົ້ນທາງ
            $checkSql = "SELECT id, current_quantity FROM {$this->table} 
                        WHERE item_id = ? AND warehouse_id = ? FOR UPDATE";
            $checkStmt = $this->db->prepare($checkSql);
            $checkStmt->execute([$itemId, $fromWarehouse]);
            $fromStock = $checkStmt->fetch(PDO::FETCH_ASSOC);

            if (!$fromStock || $fromStock['current_quantity'] < $quantity) {
                $this->db->rollBack();
                return [
                    'success' => false,
                    'message' => 'Insufficient stock in source warehouse'
                ];
            }

            // ຕັດສະຕ໋ອກຕົ້ນທາງ
            $updateFromSql = "UPDATE {$this->table} 
                             SET current_quantity = current_quantity - ?,
                                 available_quantity = (current_quantity - ?) - reserved_quantity,
                                 updated_at = NOW(),
                                 updated_by = ?
                             WHERE id = ?";
            $updateFromStmt = $this->db->prepare($updateFromSql);
            $updateFromStmt->execute([$quantity, $quantity, $createdBy, $fromStock['id']]);

            // ກວດສອບສະຕ໋ອກປາຍທາງ
            $checkToSql = "SELECT id FROM {$this->table} 
                          WHERE item_id = ? AND warehouse_id = ?";
            $checkToStmt = $this->db->prepare($checkToSql);
            $checkToStmt->execute([$itemId, $toWarehouse]);
            $toStock = $checkToStmt->fetch(PDO::FETCH_ASSOC);

            if ($toStock) {
                // ເພີ່ມສະຕ໋ອກປາຍທາງ
                $updateToSql = "UPDATE {$this->table} 
                               SET current_quantity = current_quantity + ?,
                                   available_quantity = (current_quantity + ?) - reserved_quantity,
                                   updated_at = NOW(),
                                   updated_by = ?
                               WHERE id = ?";
                $updateToStmt = $this->db->prepare($updateToSql);
                $updateToStmt->execute([$quantity, $quantity, $createdBy, $toStock['id']]);
            } else {
                // ສ້າງສະຕ໋ອກໃໝ່ທີ່ປາຍທາງ
                $insertSql = "INSERT INTO {$this->table} 
                             (item_id, warehouse_id, current_quantity, available_quantity, 
                              created_at, created_by)
                             VALUES (?, ?, ?, ?, NOW(), ?)";
                $insertStmt = $this->db->prepare($insertSql);
                $insertStmt->execute([$itemId, $toWarehouse, $quantity, $quantity, $createdBy]);
            }

            $this->db->commit();
            
            return [
                'success' => true,
                'message' => 'Stock transferred successfully'
            ];

        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error in transferStock: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to transfer stock: ' . $e->getMessage()
            ];
        }
    }

    /**
     * ບັນທຶກການນັບສະຕ໋ອກ
     */
    public function recordStockCount($itemId, $actualQuantity, $notes = null, $countedBy = null) {
        try {
            // ກວດສອບສະຕ໋ອກປັດຈຸບັນ
            $checkSql = "SELECT id, current_quantity FROM {$this->table} 
                        WHERE item_id = ?";
            $checkStmt = $this->db->prepare($checkSql);
            $checkStmt->execute([$itemId]);
            $stock = $checkStmt->fetch(PDO::FETCH_ASSOC);

            if (!$stock) {
                return [
                    'success' => false,
                    'message' => 'Stock not found'
                ];
            }

            $difference = $actualQuantity - $stock['current_quantity'];

            // ອັບເດດສະຕ໋ອກ
            $updateSql = "UPDATE {$this->table} 
                         SET current_quantity = ?,
                             available_quantity = ? - reserved_quantity,
                             last_count_date = NOW(),
                             last_count_quantity = ?,
                             updated_at = NOW(),
                             updated_by = ?
                         WHERE id = ?";
            
            $updateStmt = $this->db->prepare($updateSql);
            $updateStmt->execute([
                $actualQuantity,
                $actualQuantity,
                $actualQuantity,
                $countedBy,
                $stock['id']
            ]);

            return [
                'success' => true,
                'message' => 'Stock count recorded successfully',
                'difference' => $difference
            ];

        } catch (Exception $e) {
            error_log("Error in recordStockCount: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to record stock count: ' . $e->getMessage(),
                'difference' => 0
            ];
        }
    }

    /**
     * ບັນທຶກການນັບຫຼາຍລາຍການ
     */
    public function recordBatchStockCount($counts, $countedBy = null) {
        try {
            $results = [];
            $success = true;

            $this->db->beginTransaction();

            foreach ($counts as $count) {
                $result = $this->recordStockCount(
                    $count['item_id'],
                    $count['actual_quantity'],
                    $count['notes'] ?? null,
                    $countedBy
                );
                
                $results[] = [
                    'item_id' => $count['item_id'],
                    'success' => $result['success'],
                    'difference' => $result['difference'] ?? 0
                ];

                if (!$result['success']) {
                    $success = false;
                }
            }

            if ($success) {
                $this->db->commit();
                return [
                    'success' => true,
                    'message' => 'Batch stock count completed successfully',
                    'results' => $results
                ];
            } else {
                $this->db->rollBack();
                return [
                    'success' => false,
                    'message' => 'Some counts failed',
                    'results' => $results
                ];
            }

        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error in recordBatchStockCount: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to record batch stock count: ' . $e->getMessage(),
                'results' => []
            ];
        }
    }

    /**
     * ດຶງສະຫຼຸບການນັບສະຕ໋ອກ
     */
    public function getStockCountSummary($fromDate = null, $toDate = null) {
        try {
            // ຖ້າມີຕາຕະລາງ stock_counts, ດຶງຂໍ້ມູນສະຫຼຸບ
            // ຖ້າບໍ່ມີ, ສົ່ງຄ່າເລີ່ມຕົ້ນ
            return [
                'total_counts' => 0,
                'items_counted' => 0,
                'items_with_difference' => 0,
                'total_difference' => 0,
                'counts_by_date' => []
            ];
        } catch (Exception $e) {
            error_log("Error in getStockCountSummary: " . $e->getMessage());
            return [
                'total_counts' => 0,
                'items_counted' => 0,
                'items_with_difference' => 0,
                'total_difference' => 0,
                'counts_by_date' => []
            ];
        }
    }
}
?>