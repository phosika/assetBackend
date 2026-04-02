
<?php
require_once __DIR__ . '/../config/database.php';

class StockAdjustment {
    private $db;
    private $table = 'stock_adjustments';
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    // /**
    //  * ດຶງຂໍ້ມູນການປັບສະຕ໋ອກທັງໝົດ
    //  */
    // public function getAllAdjustments($filters = [], $page = 1, $limit = 20) {
    //     try {
    //         $sql = "SELECT a.*,
    //                        i.item_code,
    //                        i.item_name,
    //                        w.warehouse_name,
    //                        CONCAT(c.first_name, ' ', c.last_name) as created_by_name,
    //                        CONCAT(ap.first_name, ' ', ap.last_name) as approved_by_name
    //                 FROM {$this->table} a
    //                 LEFT JOIN inventory_items i ON a.item_id = i.id
    //                 LEFT JOIN warehouses w ON a.warehouse_id = w.id
    //                 LEFT JOIN users c ON a.created_by = c.id
    //                 LEFT JOIN users ap ON a.approved_by = ap.id
    //                 WHERE 1=1";
    //         $params = [];
            
    //         if (!empty($filters['status'])) {
    //             $sql .= " AND a.status = ?";
    //             $params[] = $filters['status'];
    //         }
            
    //         if (!empty($filters['item_id'])) {
    //             $sql .= " AND a.item_id = ?";
    //             $params[] = $filters['item_id'];
    //         }
            
    //         if (!empty($filters['adjustment_type'])) {
    //             $sql .= " AND a.adjustment_type = ?";
    //             $params[] = $filters['adjustment_type'];
    //         }
            
    //         if (!empty($filters['search'])) {
    //             $sql .= " AND (a.adjustment_code LIKE ? OR i.item_name LIKE ? OR i.item_code LIKE ?)";
    //             $searchTerm = "%{$filters['search']}%";
    //             $params[] = $searchTerm;
    //             $params[] = $searchTerm;
    //             $params[] = $searchTerm;
    //         }
            
    //         $sql .= " ORDER BY a.created_at DESC";
            
    //         $offset = ($page - 1) * $limit;
    //         $sql .= " LIMIT ? OFFSET ?";
    //         $params[] = $limit;
    //         $params[] = $offset;
            
    //         $stmt = $this->db->prepare($sql);
    //         $stmt->execute($params);
    //         $adjustments = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
    //         // ນັບຈຳນວນທັງໝົດ
    //         $countSql = "SELECT COUNT(*) as total FROM {$this->table}";
    //         $countStmt = $this->db->prepare($countSql);
    //         $countStmt->execute();
    //         $total = $countStmt->fetch()['total'] ?? 0;
            
    //         return [
    //             'data' => $adjustments,
    //             'total' => $total,
    //             'current_page' => $page,
    //             'per_page' => $limit
    //         ];
            
    //     } catch (Exception $e) {
    //         error_log("Error getting adjustments: " . $e->getMessage());
    //         return ['data' => [], 'total' => 0, 'current_page' => 1, 'per_page' => $limit];
    //     }
    // }
    

    /**
     * ສ້າງການປັບສະຕ໋ອກ
     */
    public function createAdjustment($data, $createdBy) {
        try {
            error_log("=== StockAdjustment::createAdjustment ===");
            error_log("Data: " . json_encode($data));
            
            $adjustmentCode = $this->generateAdjustmentCode();
            
            $sql = "INSERT INTO stock_adjustments (
                        adjustment_code, adjustment_type, reason, reason_detail,
                        item_id, warehouse_id, adjusted_quantity,
                        notes, status, created_by, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            
            $params = [
                $adjustmentCode,
                $data['adjustment_type'],
                $data['reason'],
                $data['reason_detail'] ?? null,
                $data['item_id'],
                $data['warehouse_id'] ?? null,
                $data['adjusted_quantity'],
                $data['notes'] ?? null,
                $data['status'] ?? 'approved',  // ປ່ຽນເປັນ approved ເພື່ອປັບທັນທີ
                $createdBy
            ];
            
            error_log("SQL: $sql");
            error_log("Params: " . json_encode($params));
            
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute($params);
            
            if ($result) {
                $adjustmentId = $this->db->lastInsertId();
                error_log("Adjustment created with ID: $adjustmentId");
                
                // ປັບສະຕ໋ອກທັນທີ (ເພາະສະຖານະເປັນ approved)
                $this->applyAdjustment($adjustmentId);
                
                return [
                    'success' => true,
                    'adjustment_id' => $adjustmentId,
                    'adjustment_code' => $adjustmentCode,
                    'message' => 'Adjustment created and applied successfully'
                ];
            }
            
            return ['success' => false, 'message' => 'Failed to create adjustment'];
            
        } catch (Exception $e) {
            error_log("Error creating adjustment: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to create adjustment: ' . $e->getMessage()];
        }
    }

    /**
     * ນຳໃຊ້ການປັບສະຕ໋ອກ
     */
    private function applyAdjustment($adjustmentId) {
        try {
            $stmt = $this->db->prepare("SELECT * FROM stock_adjustments WHERE id = ?");
            $stmt->execute([$adjustmentId]);
            $adjustment = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$adjustment) {
                return false;
            }
            
            $itemId = $adjustment['item_id'];
            $warehouseId = $adjustment['warehouse_id'];
            $quantity = $adjustment['adjusted_quantity'];
            $type = $adjustment['adjustment_type'];
            
            // ດຶງສະຕ໋ອກປັດຈຸບັນ
            $stmt = $this->db->prepare("
                SELECT id, current_quantity 
                FROM inventory_stock 
                WHERE item_id = ? AND warehouse_id = ?
            ");
            $stmt->execute([$itemId, $warehouseId]);
            $stock = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $oldQuantity = $stock['current_quantity'] ?? 0;
            
            if ($type === 'increase') {
                $newQuantity = $oldQuantity + $quantity;
            } else {
                $newQuantity = max(0, $oldQuantity - $quantity);
            }
            
            if ($stock) {
                $sql = "UPDATE inventory_stock 
                        SET current_quantity = ?,
                            available_quantity = ?,
                            updated_at = NOW()
                        WHERE id = ?";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([$newQuantity, $newQuantity, $stock['id']]);
            } else {
                $sql = "INSERT INTO inventory_stock (
                            item_id, warehouse_id, current_quantity, available_quantity, created_at
                        ) VALUES (?, ?, ?, ?, NOW())";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([$itemId, $warehouseId, $newQuantity, $newQuantity]);
            }
            
            // ອັບເດດສະຖານະເປັນ approved ແລະ ເວລາອະນຸມັດ
            $sql = "UPDATE stock_adjustments 
                    SET status = 'approved',
                        approved_at = NOW()
                    WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$adjustmentId]);
            
            error_log("Applied adjustment $adjustmentId: $type $quantity, old: $oldQuantity, new: $newQuantity");
            return true;
            
        } catch (Exception $e) {
            error_log("Error applying adjustment: " . $e->getMessage());
            return false;
        }
    }

    /**
     * ສ້າງເລກທີ່ການປັບສະຕ໋ອກ
     */
    private function generateAdjustmentCode() {
        $year = date('Y');
        $month = date('m');
        $prefix = 'ADJ';
        
        $stmt = $this->db->prepare("SELECT COUNT(*) as count FROM stock_adjustments WHERE adjustment_code LIKE ?");
        $stmt->execute(["{$prefix}-{$year}{$month}%"]);
        $count = $stmt->fetch()['count'] + 1;
        
        return $prefix . '-' . $year . $month . str_pad($count, 4, '0', STR_PAD_LEFT);
    }

    /**
     * ດຶງສະຖິຕິການປັບສະຕ໋ອກ
     */
    public function getStats() {
        try {
            $stats = [];
            
            $stmt = $this->db->query("SELECT COUNT(*) as total FROM stock_adjustments");
            $stats['total'] = $stmt->fetch()['total'];
            
            $stmt = $this->db->query("SELECT status, COUNT(*) as count FROM stock_adjustments GROUP BY status");
            while ($row = $stmt->fetch()) {
                $stats[$row['status']] = $row['count'];
            }
            
            $stmt = $this->db->query("SELECT adjustment_type, COUNT(*) as count FROM stock_adjustments GROUP BY adjustment_type");
            while ($row = $stmt->fetch()) {
                $stats[$row['adjustment_type']] = $row['count'];
            }
            
            return $stats;
            
        } catch (Exception $e) {
            error_log("Error getting stats: " . $e->getMessage());
            return ['total' => 0, 'draft' => 0, 'approved' => 0, 'cancelled' => 0];
        }
    }

    /**
     * ດຶງຂໍ້ມູນການປັບສະຕ໋ອກທັງໝົດ
     */
    public function getAllAdjustments($filters = [], $page = 1, $limit = 20) {
        try {
            $sql = "SELECT a.*,
                        i.item_code,
                        i.item_name,
                        w.warehouse_name,
                        CONCAT(c.first_name, ' ', c.last_name) as created_by_name,
                        CONCAT(ap.first_name, ' ', ap.last_name) as approved_by_name
                    FROM stock_adjustments a
                    LEFT JOIN inventory_items i ON a.item_id = i.id
                    LEFT JOIN warehouses w ON a.warehouse_id = w.id
                    LEFT JOIN users c ON a.created_by = c.id
                    LEFT JOIN users ap ON a.approved_by = ap.id
                    WHERE 1=1";
            $params = [];
            
            if (!empty($filters['status'])) {
                $sql .= " AND a.status = ?";
                $params[] = $filters['status'];
            }
            
            if (!empty($filters['adjustment_type'])) {
                $sql .= " AND a.adjustment_type = ?";
                $params[] = $filters['adjustment_type'];
            }
            
            if (!empty($filters['item_id'])) {
                $sql .= " AND a.item_id = ?";
                $params[] = $filters['item_id'];
            }
            
            if (!empty($filters['search'])) {
                $sql .= " AND (a.adjustment_code LIKE ? OR i.item_name LIKE ? OR i.item_code LIKE ?)";
                $searchTerm = "%{$filters['search']}%";
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }
            
            $sql .= " ORDER BY a.created_at DESC";
            
            $offset = ($page - 1) * $limit;
            $sql .= " LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $adjustments = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // ນັບຈຳນວນທັງໝົດ
            $countSql = "SELECT COUNT(*) as total FROM stock_adjustments";
            $countStmt = $this->db->prepare($countSql);
            $countStmt->execute();
            $total = $countStmt->fetch()['total'] ?? 0;
            
            return [
                'data' => $adjustments,
                'total' => $total,
                'current_page' => $page,
                'per_page' => $limit
            ];
            
        } catch (Exception $e) {
            error_log("Error getting adjustments: " . $e->getMessage());
            return ['data' => [], 'total' => 0, 'current_page' => 1, 'per_page' => $limit];
        }
    }
    

    
    // /**
    //  * ດຶງສະຖິຕິການປັບສະຕ໋ອກ
    //  */
    // public function getStats() {
    //     try {
    //         $stats = [];
            
    //         $stmt = $this->db->query("SELECT COUNT(*) as total FROM {$this->table}");
    //         $stats['total'] = $stmt->fetch()['total'];
            
    //         $stmt = $this->db->query("SELECT status, COUNT(*) as count FROM {$this->table} GROUP BY status");
    //         while ($row = $stmt->fetch()) {
    //             $stats[$row['status']] = $row['count'];
    //         }
            
    //         $stmt = $this->db->query("SELECT adjustment_type, COUNT(*) as count FROM {$this->table} GROUP BY adjustment_type");
    //         while ($row = $stmt->fetch()) {
    //             $stats[$row['adjustment_type']] = $row['count'];
    //         }
            
    //         return $stats;
            
    //     } catch (Exception $e) {
    //         return ['total' => 0, 'draft' => 0, 'approved' => 0, 'cancelled' => 0];
    //     }
    // }
}