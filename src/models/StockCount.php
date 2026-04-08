<?php
// src/models/StockCount.php
require_once __DIR__ . '/../config/database.php';

class StockCount {
    private $db;
    private $table = 'stock_count_sessions';
    private $detailsTable = 'stock_count_details';
 
  
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    

    /**
     * ບັນທຶກຜົນການນັບ
     */
    public function recordCount($sessionId, $itemId, $countedQuantity, $countedBy = null) {
        try {
            error_log("=== StockCount::recordCount ===");
            error_log("Session ID: $sessionId, Item ID: $itemId, Counted: $countedQuantity, Counted By: " . ($countedBy ?? 'NULL'));
            
            // ກວດສອບວ່າ session ມີຢູ່ ແລະ status ເປັນ in_progress
            $stmt = $this->db->prepare("SELECT id, status FROM {$this->table} WHERE id = ?");
            $stmt->execute([$sessionId]);
            $session = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$session) {
                error_log("Session not found: $sessionId");
                return ['success' => false, 'message' => 'Stock count session not found'];
            }
            
            error_log("Session status: " . $session['status']);
            
            if ($session['status'] !== 'in_progress') {
                error_log("Invalid session status: " . $session['status']);
                return ['success' => false, 'message' => 'Cannot record count. Session status is ' . $session['status']];
            }
            
            // ກວດສອບວ່າ $countedBy ຖືກຕ້ອງ
            if ($countedBy !== null) {
                // ກວດສອບວ່າ user ມີຢູ່ໃນ database ບໍ
                $stmt = $this->db->prepare("SELECT id FROM users WHERE id = ?");
                $stmt->execute([$countedBy]);
                if (!$stmt->fetch()) {
                    error_log("User $countedBy not found, setting to NULL");
                    $countedBy = null;
                }
            }
            
            // ດຶງຂໍ້ມູນເກົ່າ
            $stmt = $this->db->prepare("SELECT id, expected_quantity, counted_quantity FROM {$this->detailsTable} WHERE session_id = ? AND item_id = ?");
            $stmt->execute([$sessionId, $itemId]);
            $detail = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$detail) {
                error_log("Item not found in session: Session $sessionId, Item $itemId");
                return ['success' => false, 'message' => 'Item not found in this session'];
            }
            
            error_log("Found detail: " . json_encode($detail));
            
            $expectedQty = (float)$detail['expected_quantity'];
            $countedQty = (float)$countedQuantity;
            $variance = $countedQty - $expectedQty;
            $variancePercent = $expectedQty > 0 ? ($variance / $expectedQty) * 100 : 0;
            
            error_log("Expected: $expectedQty, Counted: $countedQty, Variance: $variance");
            
            // ອັບເດດຂໍ້ມູນ - ຖ້າ $countedBy ເປັນ null ຈະຕັ້ງເປັນ NULL
            $sql = "UPDATE {$this->detailsTable} 
                    SET counted_quantity = ?,
                        variance = ?,
                        variance_percent = ?,
                        status = 'counted',
                        counted_by = ?,
                        counted_at = NOW()
                    WHERE session_id = ? AND item_id = ?";
            
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute([$countedQty, $variance, $variancePercent, $countedBy, $sessionId, $itemId]);
            
            if ($result) {
                $rowCount = $stmt->rowCount();
                error_log("Update result: $result, Rows affected: $rowCount");
                
                if ($rowCount > 0) {
                    return [
                        'success' => true,
                        'variance' => $variance,
                        'variance_percent' => $variancePercent,
                        'message' => 'Count recorded successfully'
                    ];
                } else {
                    return [
                        'success' => false,
                        'message' => 'No changes made. Item may already be counted.'
                    ];
                }
            } else {
                error_log("Update failed");
                return [
                    'success' => false,
                    'message' => 'Failed to record count'
                ];
            }
            
        } catch (Exception $e) {
            error_log("Error recording count: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            return [
                'success' => false,
                'message' => 'Failed to record count: ' . $e->getMessage()
            ];
        }
    }
        

 

    /**
     * ສຳເລັດການນັບ
     * @param int $sessionId
     * @param int $completedBy
     * @param bool $adjustStock - ຖ້າ true ຈະປັບສະຕ໋ອກ, ຖ້າ false ຈະປິດໃບນັບຢ່າງດຽວ
     */
    public function completeSession($sessionId, $completedBy, $adjustStock = true) {
        try {
            error_log("=== StockCount::completeSession ===");
            error_log("Session ID: $sessionId, Completed By: $completedBy, Adjust Stock: " . ($adjustStock ? 'true' : 'false'));
            
            // ກວດສອບ ແລະ ແປງ $completedBy ໃຫ້ເປັນ integer
            if (is_array($completedBy)) {
                $completedBy = $completedBy['id'] ?? $completedBy[0] ?? null;
            } elseif (is_object($completedBy)) {
                $completedBy = $completedBy->id ?? null;
            } else {
                $completedBy = $completedBy ? (int)$completedBy : null;
            }
            
            // ກວດສອບວ່າ completed_by ມີຢູ່ໃນ users table ບໍ
            if ($completedBy) {
                $stmt = $this->db->prepare("SELECT id FROM users WHERE id = ?");
                $stmt->execute([$completedBy]);
                if (!$stmt->fetch()) {
                    error_log("User $completedBy not found, setting to NULL");
                    $completedBy = null;
                }
            }
            
            error_log("Final completed_by value: " . ($completedBy ?? 'NULL'));
            
            // ກວດສອບວ່າ session ມີຢູ່
            $stmt = $this->db->prepare("SELECT id, status, warehouse_id FROM {$this->table} WHERE id = ?");
            $stmt->execute([$sessionId]);
            $session = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$session) {
                return ['success' => false, 'message' => 'Stock count session not found'];
            }
            
            if ($session['status'] !== 'in_progress') {
                return ['success' => false, 'message' => 'Cannot complete count. Session status is ' . $session['status']];
            }
            
            $adjustments = [];
            $totalVariance = 0;
            
            // ຖ້າຕ້ອງການປັບສະຕ໋ອກ
            if ($adjustStock) {
                // ດຶງຂໍ້ມູນສິນຄ້າທີ່ນັບແລ້ວ
                $stmt = $this->db->prepare("
                    SELECT * FROM {$this->detailsTable} 
                    WHERE session_id = ? AND status = 'counted'
                ");
                $stmt->execute([$sessionId]);
                $countedItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
                error_log("Found " . count($countedItems) . " counted items");
                
                foreach ($countedItems as $item) {
                    $itemId = $item['item_id'];
                    $expectedQty = (float)$item['expected_quantity'];
                    $countedQty = (float)$item['counted_quantity'];
                    $variance = $countedQty - $expectedQty;
                    $totalVariance += $variance;
                    
                    if ($variance != 0) {
                        // ປັບສະຕ໋ອກ
                        $adjustmentResult = $this->updateStockQuantity(
                            $itemId,
                            $session['warehouse_id'],
                            $variance,
                            $expectedQty,
                            $countedQty,
                            $sessionId,
                            $completedBy ?? 1
                        );
                        $adjustments[] = $adjustmentResult;
                    }
                }
            } else {
                error_log("Skipping stock adjustment (adjust_stock = false)");
            }
            
            // ອັບເດດສະຖານະ session - ຖ້າ $completedBy ເປັນ null ຈະຕັ້ງເປັນ NULL
            $sql = "UPDATE {$this->table} 
                    SET status = 'completed',
                        end_date = NOW(),
                        completed_by = ?,
                        completed_at = NOW()
                    WHERE id = ?";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$completedBy, $sessionId]);
            
            $message = $adjustStock 
                ? 'Stock count completed and stock adjusted successfully'
                : 'Stock count closed successfully (no stock adjustment)';
            
            return [
                'success' => true,
                'message' => $message,
                'adjustments_made' => count($adjustments),
                'total_variance' => $totalVariance,
                'stock_adjusted' => $adjustStock
            ];
            
        } catch (Exception $e) {
            error_log("Error completing session: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            return [
                'success' => false,
                'message' => 'Failed to complete session: ' . $e->getMessage()
            ];
        }
    }

    /**
     * ອັບເດດຈຳນວນສະຕ໋ອກ
     */
    private function updateStockQuantity($itemId, $warehouseId, $variance, $expectedQty, $countedQty, $sessionId, $userId) {
        try {
            error_log("=== updateStockQuantity ===");
            error_log("Item: $itemId, Warehouse: $warehouseId, Variance: $variance");
            
            // ດຶງສະຕ໋ອກປັດຈຸບັນ
            $stmt = $this->db->prepare("
                SELECT id, quantity, available_quantity
                FROM inventory_stocks 
                WHERE item_id = ? AND warehouse_id = ? AND status = 'active'
            ");
            $stmt->execute([$itemId, $warehouseId]);
            $stock = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $oldQuantity = (float)($stock['quantity'] ?? 0);
            $newQuantity = $oldQuantity + $variance;
            
            // ກວດສອບບໍ່ໃຫ້ຕິດລົບ
            if ($newQuantity < 0) {
                $newQuantity = 0;
                error_log("Warning: Stock would become negative, set to 0");
            }
            
            if ($stock) {
                // ອັບເດດສະຕ໋ອກທີ່ມີຢູ່
                $sql = "UPDATE inventory_stocks 
                        SET quantity = ?,
                            available_quantity = ?,
                            last_count_quantity = ?,
                            last_count_date = NOW(),
                            updated_by = ?,
                            updated_at = NOW()
                        WHERE id = ?";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([$newQuantity, $newQuantity, $countedQty, $userId, $stock['id']]);
                error_log("Updated existing stock record ID: {$stock['id']}");
            } else {
                // ສ້າງໃໝ່ຖ້າບໍ່ມີ
                $sql = "INSERT INTO inventory_stocks (
                            item_id, warehouse_id, quantity, available_quantity,
                            last_count_quantity, last_count_date, status,
                            created_by, created_at
                        ) VALUES (?, ?, ?, ?, ?, NOW(), 'active', ?, NOW())";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([$itemId, $warehouseId, $newQuantity, $newQuantity, $countedQty, $userId]);
                error_log("Created new stock record");
            }
            
            // ບັນທຶກປະຫວັດການປັບສະຕ໋ອກ (ຖ້າມີຕາລາງ stock_adjustments)
            $this->logStockAdjustment($itemId, $warehouseId, $variance, $oldQuantity, $newQuantity, 
                                    $expectedQty, $countedQty, $sessionId, $userId);
            
            return [
                'success' => true,
                'item_id' => $itemId,
                'old_quantity' => $oldQuantity,
                'new_quantity' => $newQuantity,
                'variance' => $variance
            ];
            
        } catch (Exception $e) {
            error_log("Error updating stock: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            return [
                'success' => false,
                'message' => 'Failed to update stock: ' . $e->getMessage()
            ];
        }
    }

    /**
     * ບັນທຶກປະຫວັດການປັບສະຕ໋ອກ
     */
 
    private function logStockAdjustment($itemId, $warehouseId, $variance, $oldQuantity, $newQuantity, 
                                    $expectedQty, $countedQty, $sessionId, $userId) {
        try {
            // ກວດສອບວ່າມີຕາລາງ stock_adjustments ບໍ
            $stmt = $this->db->query("SHOW TABLES LIKE 'stock_adjustments'");
            if ($stmt->rowCount() == 0) {
                error_log("stock_adjustments table not found, skipping log");
                return;
            }
            
            // ກວດສອບວ່າຕາລາງມີຟີລດ໌ທີ່ຕ້ອງການບໍ
            $stmt = $this->db->query("SHOW COLUMNS FROM stock_adjustments");
            $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            // ຖ້າບໍ່ມີຟີລດ໌ທີ່ຈຳເປັນ, ຂ້າມໄປ
            $requiredColumns = ['adjustment_code', 'adjustment_type', 'item_id', 'warehouse_id'];
            foreach ($requiredColumns as $col) {
                if (!in_array($col, $columns)) {
                    error_log("Missing column '$col' in stock_adjustments, skipping log");
                    return;
                }
            }
            
            $adjustmentType = $variance > 0 ? 'increase' : ($variance < 0 ? 'decrease' : 'no_change');
            
            // ກວດສອບວ່າມີຟີລດ໌ reason ຫຼືບໍ່
            $hasReason = in_array('reason', $columns);
            $hasReasonDetail = in_array('reason_detail', $columns);
            
            if ($hasReason && $hasReasonDetail) {
                $sql = "INSERT INTO stock_adjustments (
                            adjustment_code, adjustment_type, reason, reason_detail,
                            item_id, warehouse_id, adjusted_quantity,
                            expected_quantity, counted_quantity, variance,
                            old_quantity, new_quantity,
                            reference_type, reference_id, count_session_id,
                            status, created_by, created_at
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
                
                $adjustmentCode = $this->generateAdjustmentCode();
                
                $stmt = $this->db->prepare($sql);
                $stmt->execute([
                    $adjustmentCode,
                    $adjustmentType,
                    'stock_count',
                    "Stock count adjustment from session #$sessionId",
                    $itemId,
                    $warehouseId,
                    abs($variance),
                    $expectedQty,
                    $countedQty,
                    $variance,
                    $oldQuantity,
                    $newQuantity,
                    'stock_count',
                    $sessionId,
                    $sessionId,
                    'approved',
                    $userId
                ]);
                
                error_log("Stock adjustment logged: $adjustmentCode for item $itemId");
            } else {
                // ສະບັບງ່າຍຖ້າບໍ່ມີຟີລດ໌ບາງອັນ
                error_log("Simple stock adjustment record for item $itemId: variance=$variance");
            }
            
        } catch (Exception $e) {
            error_log("Error logging stock adjustment: " . $e->getMessage());
            // ບໍ່ຕ້ອງ throw exception, ພຽງແຕ່ log ໄວ້
        }
    }

    /**
     * ກວດສອບສະຕ໋ອກປັດຈຸບັນກ່ອນປັບ
     */
    public function verifyCurrentStock($itemId, $warehouseId, $expectedQty) {
        try {
            $currentStock = $this->getCurrentStockQuantity($itemId, $warehouseId);
            
            if ($currentStock != $expectedQty) {
                error_log("Warning: Expected quantity ($expectedQty) doesn't match current stock ($currentStock)");
                return [
                    'matched' => false,
                    'current_stock' => $currentStock,
                    'expected_qty' => $expectedQty,
                    'difference' => $currentStock - $expectedQty
                ];
            }
            
            return [
                'matched' => true,
                'current_stock' => $currentStock,
                'expected_qty' => $expectedQty
            ];
            
        } catch (Exception $e) {
            error_log("Error verifying stock: " . $e->getMessage());
            return [
                'matched' => false,
                'error' => $e->getMessage()
            ];
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
     * ສ້າງເລກທີ່ການນັບສະຕ໋ອກ (ແກ້ໄຂບັນຫາຊ້ຳກັນ)
     */
    private function generateSessionCode() {
        $year = date('Y');
        $month = date('m');
        $prefix = 'STC';
        
        // ດຶງເລກທີ່ສູງສຸດໃນເດືອນນີ້
        $stmt = $this->db->prepare("
            SELECT session_code 
            FROM {$this->table} 
            WHERE session_code LIKE ? 
            ORDER BY session_code DESC 
            LIMIT 1
        ");
        $pattern = $prefix . '-' . $year . $month . '%';
        $stmt->execute([$pattern]);
        $lastCode = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($lastCode) {
            // ດຶງເລກທີ່ຈາກ session_code ລ່າສຸດ
            $lastNumber = (int) substr($lastCode['session_code'], -4);
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }
        
        // ສ້າງ session_code ໃໝ່
        $sessionCode = $prefix . '-' . $year . $month . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
        
        // ກວດສອບວ່າ code ນີ້ມີຢູ່ແລ້ວບໍ (ເຜີກໄວ້)
        $stmt = $this->db->prepare("SELECT id FROM {$this->table} WHERE session_code = ?");
        $stmt->execute([$sessionCode]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing) {
            // ຖ້າມີຢູ່ແລ້ວ, ໃຊ້ timestamp ແທນ
            $sessionCode = $prefix . '-' . $year . $month . date('His');
            error_log("Session code conflict, using timestamp: $sessionCode");
        }
        
        error_log("Generated session code: $sessionCode");
        return $sessionCode;
    }
        
 

    public function getSessions($filters = [], $page = 1, $limit = 20) {
        try {
            $sql = "SELECT s.*,
                        w.warehouse_name,
                        CONCAT(cu.first_name, ' ', cu.last_name) as created_by_name,
                        CONCAT(com.first_name, ' ', com.last_name) as completed_by_name
                    FROM {$this->table} s
                    LEFT JOIN warehouses w ON s.warehouse_id = w.id
                    LEFT JOIN users cu ON s.created_by = cu.id
                    LEFT JOIN users com ON s.completed_by = com.id
                    WHERE 1=1";
            $params = [];
            
            if (!empty($filters['status'])) {
                $sql .= " AND s.status = ?";
                $params[] = $filters['status'];
            }
            
            if (!empty($filters['warehouse_id'])) {
                $sql .= " AND s.warehouse_id = ?";
                $params[] = $filters['warehouse_id'];
            }
            
            if (!empty($filters['search'])) {
                $sql .= " AND (s.session_code LIKE ? OR s.session_name LIKE ?)";
                $searchTerm = "%{$filters['search']}%";
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }
            
            $sql .= " ORDER BY s.created_at DESC";
            
            $offset = ($page - 1) * $limit;
            $sql .= " LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // ນັບຈຳນວນທັງໝົດ
            $countSql = "SELECT COUNT(*) as total FROM {$this->table}";
            $countStmt = $this->db->prepare($countSql);
            $countStmt->execute();
            $total = $countStmt->fetch()['total'] ?? 0;
            
            error_log("=== getSessions result ===");
            error_log("Total count from DB: " . $total);
            error_log("Returning " . count($sessions) . " sessions");
            
            return [
                'data' => $sessions,
                'total' => $total,
                'current_page' => $page,
                'per_page' => $limit
            ];
            
        } catch (Exception $e) {
            error_log("Error in getSessions: " . $e->getMessage());
            return ['data' => [], 'total' => 0, 'current_page' => 1, 'per_page' => $limit];
        }
    }
    
 

    /**
     * ດຶງສະຖິຕິການນັບ
     */
    public function getStats() {
        try {
            $stats = [];
            
            // ຈຳນວນທັງໝົດ
            $stmt = $this->db->query("SELECT COUNT(*) as total FROM {$this->table}");
            $stats['total_sessions'] = $stmt->fetch()['total'];
            
            // ຈຳນວນຕາມສະຖານະ
            $stmt = $this->db->query("SELECT status, COUNT(*) as count FROM {$this->table} GROUP BY status");
            $statusStats = [];
            while ($row = $stmt->fetch()) {
                $statusStats[$row['status']] = $row['count'];
            }
            $stats['in_progress'] = $statusStats['in_progress'] ?? 0;
            $stats['completed'] = $statusStats['completed'] ?? 0;
            $stats['draft'] = $statusStats['draft'] ?? 0;
            $stats['cancelled'] = $statusStats['cancelled'] ?? 0;
            
            return $stats;
            
        } catch (Exception $e) {
            error_log("Error getting stats: " . $e->getMessage());
            return [
                'total_sessions' => 0,
                'in_progress' => 0,
                'completed' => 0,
                'draft' => 0,
                'cancelled' => 0
            ];
        }
    }

    /**
     * ດຶງຂໍ້ມູນ session ຕາມ ID
     */
    public function getById($id) {
        try {
            $sql = "SELECT * FROM {$this->table} WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting session by id: " . $e->getMessage());
            return null;
        }
    }


    /**
     * ເລີ່ມການນັບ
     */
    public function startSession($id, $userId) {
        try {
            error_log("=== StockCount::startSession ===");
            error_log("Session ID: $id, User ID: " . print_r($userId, true));
            
            // ກວດສອບ ແລະ ແປງ $userId ໃຫ້ເປັນ integer
            if (is_array($userId)) {
                error_log("UserId is array: " . json_encode($userId));
                $userId = $userId['id'] ?? $userId[0] ?? 1;
            } elseif (is_object($userId)) {
                error_log("UserId is object: " . print_r($userId, true));
                $userId = $userId->id ?? 1;
            }
            
            $userId = (int)$userId;
            
            if ($userId <= 0) {
                error_log("Invalid user ID, using default 1");
                $userId = 1;
            }
            
            error_log("Final user ID: $userId");
            
            // ກວດສອບວ່າ session ມີຢູ່
            $stmt = $this->db->prepare("SELECT id, status FROM {$this->table} WHERE id = ?");
            $stmt->execute([$id]);
            $session = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$session) {
                error_log("Session not found: $id");
                return [
                    'success' => false,
                    'message' => 'Stock count session not found'
                ];
            }
            
            error_log("Current status: " . $session['status']);
            
            // ກວດສອບວ່າມີສິນຄ້າໃນການນັບບໍ
            $stmt = $this->db->prepare("SELECT COUNT(*) as count FROM {$this->detailsTable} WHERE session_id = ?");
            $stmt->execute([$id]);
            $count = $stmt->fetch(PDO::FETCH_ASSOC);
            error_log("Items count: " . $count['count']);
            
            if ($count['count'] == 0) {
                return [
                    'success' => false,
                    'message' => 'Cannot start count. No items to count in this session'
                ];
            }
            
            // ອັບເດດສະຖານະ
            $sql = "UPDATE {$this->table} 
                    SET status = 'in_progress',
                        start_date = NOW(),
                        updated_at = NOW()
                    WHERE id = ? AND status = 'draft'";
            
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute([$id]);
            $rowCount = $stmt->rowCount();
            
            error_log("Update result: " . ($result ? 'true' : 'false') . ", Rows affected: $rowCount");
            
            if ($result && $rowCount > 0) {
                return [
                    'success' => true,
                    'message' => 'Stock count started successfully'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Failed to start stock count. Session may not be in draft status or no changes made.'
                ];
            }
            
        } catch (Exception $e) {
            error_log("Error starting session: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to start: ' . $e->getMessage()
            ];
        }
    }
    

    /**
     * ຍົກເລີກການນັບ
     */
    public function cancelSession($id, $userId) {
        try {
            error_log("=== StockCount::cancelSession ===");
            error_log("Session ID: $id, User ID: $userId");
            
            // ກວດສອບວ່າ session ມີຢູ່
            $stmt = $this->db->prepare("SELECT id, status FROM {$this->table} WHERE id = ?");
            $stmt->execute([$id]);
            $session = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$session) {
                return ['success' => false, 'message' => 'Stock count session not found'];
            }
            
            // ກວດສອບວ່າສາມາດຍົກເລີກໄດ້
            if (!in_array($session['status'], ['draft', 'in_progress'])) {
                return ['success' => false, 'message' => 'Cannot cancel. Session status is ' . $session['status']];
            }
            
            // ອັບເດດສະຖານະເປັນ cancelled
            $sql = "UPDATE {$this->table} 
                    SET status = 'cancelled',
                        updated_at = NOW(),
                        completed_by = ?,
                        completed_at = NOW()
                    WHERE id = ? AND status IN ('draft', 'in_progress')";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$userId, $id]);
            
            if ($stmt->rowCount() > 0) {
                error_log("Session $id cancelled successfully");
                return [
                    'success' => true,
                    'message' => 'Stock count cancelled successfully'
                ];
            } else {
                error_log("Failed to cancel session $id");
                return [
                    'success' => false,
                    'message' => 'Failed to cancel stock count. Session may already be completed.'
                ];
            }
            
        } catch (Exception $e) {
            error_log("Error canceling session: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to cancel: ' . $e->getMessage()
            ];
        }
    }
    // src/models/StockCount.php
    // ເພີ່ມຟັງຊັນນີ້ເຂົ້າໄປໃນ StockCount Model
 
 
    public function getCurrentStockQuantity($itemId, $warehouseId = null) {
        try {
            error_log("=== getCurrentStockQuantity ===");
            error_log("Item ID: $itemId, Warehouse ID: " . ($warehouseId ?? 'ALL'));
            
            // ກວດສອບວ່າຕາລາງ inventory_stocks ມີຢູ່ບໍ
            $checkTable = $this->db->query("SHOW TABLES LIKE 'inventory_stocks'");
            if ($checkTable->rowCount() == 0) {
                error_log("inventory_stocks table not found, returning 0");
                return 0;
            }
            
            $sql = "SELECT COALESCE(quantity, 0) as current_stock 
                    FROM inventory_stocks 
                    WHERE item_id = ? AND status = 'active'";
            $params = [$itemId];
            
            if ($warehouseId) {
                $sql .= " AND warehouse_id = ?";
                $params[] = $warehouseId;
            }
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $currentStock = (float)($result['current_stock'] ?? 0);
            error_log("Current stock for item $itemId: $currentStock");
            
            return $currentStock;
            
        } catch (Exception $e) {
            error_log("Error getting current stock: " . $e->getMessage());
            return 0;
        }
    }

 
 

// ແກ້ໄຂຟັງຊັນ createSession ໃນ StockCount.php

    public function createSession($data, $createdBy = null) {
        try {
            error_log("=== StockCount::createSession ===");
            error_log("Data received: " . json_encode($data));
            
            // ກວດສອບວ່າ $createdBy ມາຈາກໃສ
            if ($createdBy === null) {
                // ຖ້າບໍ່ມີ, ລອງດຶງຈາກ $_SESSION
                if (session_status() === PHP_SESSION_NONE) {
                    session_start();
                }
                
                // ລອງຫາຄ່າ user id ຈາກຫຼາຍແຫຼ່ງ
                if (isset($_SESSION['user_id'])) {
                    $createdBy = $_SESSION['user_id'];
                    error_log("Using user_id from session: " . $createdBy);
                } elseif (isset($_SESSION['user']['id'])) {
                    $createdBy = $_SESSION['user']['id'];
                    error_log("Using user.id from session: " . $createdBy);
                } elseif (isset($_SESSION['user']['user_id'])) {
                    $createdBy = $_SESSION['user']['user_id'];
                    error_log("Using user.user_id from session: " . $createdBy);
                } elseif (isset($_SESSION['user']['ID'])) {
                    $createdBy = $_SESSION['user']['ID'];
                    error_log("Using user.ID from session: " . $createdBy);
                } else {
                    // ຖ້າຫາບໍ່ເຫັນ, ໃຊ້ default user id 1
                    $createdBy = 1;
                    error_log("No user found in session, using default: 1");
                }
            }
            
            // ກວດສອບປະເພດຂໍ້ມູນ
            if (is_array($createdBy)) {
                error_log("createdBy is array: " . json_encode($createdBy));
                if (isset($createdBy['id'])) {
                    $createdBy = (int)$createdBy['id'];
                } elseif (isset($createdBy['user_id'])) {
                    $createdBy = (int)$createdBy['user_id'];
                } elseif (isset($createdBy[0])) {
                    $createdBy = (int)$createdBy[0];
                } else {
                    $createdBy = 1;
                }
            } elseif (is_object($createdBy)) {
                error_log("createdBy is object: " . print_r($createdBy, true));
                if (isset($createdBy->id)) {
                    $createdBy = (int)$createdBy->id;
                } elseif (isset($createdBy->user_id)) {
                    $createdBy = (int)$createdBy->user_id;
                } else {
                    $createdBy = 1;
                }
            } else {
                $createdBy = (int)$createdBy;
            }
            
            // ກວດສອບວ່າເປັນຄ່າທີ່ຖືກຕ້ອງ
            if ($createdBy <= 0) {
                error_log("Invalid createdBy after conversion: " . $createdBy . ", using default 1");
                $createdBy = 1;
            }
            
            error_log("Final createdBy value: " . $createdBy . " (type: " . gettype($createdBy) . ")");
            
            // ສ້າງ session code
            $sessionCode = $this->generateSessionCode();
            
            // ກວດສອບວ່າມີສິນຄ້າບໍ
            if (empty($data['items']) || !is_array($data['items'])) {
                error_log("No items provided");
                return [
                    'success' => false,
                    'message' => 'No items selected for stock count'
                ];
            }
            
            // Insert session
            $sql = "INSERT INTO {$this->table} (
                        session_code, session_name, count_type, warehouse_id, 
                        start_date, notes, created_by, created_at, status
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), 'draft')";
            
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute([
                $sessionCode,
                $data['session_name'] ?? 'Stock Count ' . date('Y-m-d'),
                $data['count_type'] ?? 'full',
                !empty($data['warehouse_id']) ? (int)$data['warehouse_id'] : null,
                $data['start_date'] ?? date('Y-m-d H:i:s'),
                $data['notes'] ?? null,
                $createdBy
            ]);
            
            if (!$result) {
                $error = $stmt->errorInfo();
                error_log("SQL Error: " . print_r($error, true));
                return [
                    'success' => false,
                    'message' => 'Database error: ' . ($error[2] ?? 'Unknown error')
                ];
            }
            
            $sessionId = $this->db->lastInsertId();
            error_log("Session created with ID: $sessionId, Code: $sessionCode");
            
            // ເພີ່ມສິນຄ້າ
            $itemsAdded = $this->addItemsToSession($sessionId, $data['items'], $createdBy);
            
            if (!$itemsAdded['success']) {
                // ຖ້າເພີ່ມສິນຄ້າບໍ່ສຳເລັດ, ລຶບ session
                $this->db->prepare("DELETE FROM {$this->table} WHERE id = ?")->execute([$sessionId]);
                return $itemsAdded;
            }
            
            return [
                'success' => true,
                'session_id' => $sessionId,
                'session_code' => $sessionCode,
                'message' => 'Stock count session created successfully',
                'items_added' => $itemsAdded['added_count'] ?? 0
            ];
            
        } catch (Exception $e) {
            error_log("Error creating session: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            return [
                'success' => false,
                'message' => 'Failed to create session: ' . $e->getMessage()
            ];
        }
    }

    public function addItemsToSession($sessionId, $items, $createdBy = null) {
        try {
            error_log("=== StockCount::addItemsToSession ===");
            error_log("Session ID: $sessionId");
            error_log("Items: " . json_encode($items));
            
            if (!is_array($items)) {
                error_log("Items is not an array: " . gettype($items));
                return [
                    'success' => false,
                    'message' => 'Invalid items data'
                ];
            }
            
            // ດຶງຂໍ້ມູນ session ເພື່ອເອົາ warehouse_id
            $stmt = $this->db->prepare("SELECT warehouse_id FROM {$this->table} WHERE id = ?");
            $stmt->execute([$sessionId]);
            $session = $stmt->fetch(PDO::FETCH_ASSOC);
            $warehouseId = $session['warehouse_id'] ?? null;
            error_log("Warehouse ID: " . ($warehouseId ?? 'NULL'));
            
            $addedCount = 0;
            $errors = [];
            
            foreach ($items as $item) {
                if (!isset($item['item_id']) || empty($item['item_id'])) {
                    error_log("Missing item_id in item: " . json_encode($item));
                    $errors[] = "Missing item_id";
                    continue;
                }
                
                $itemId = (int)$item['item_id'];
                
                // ດຶງຈຳນວນສະຕ໋ອກຕົວຈິງ
                $currentStock = $this->getCurrentStockQuantity($itemId, $warehouseId);
                error_log("Item $itemId: Current stock = $currentStock");
                
                // ກວດສອບວ່າສິນຄ້ານີ້ມີແລ້ວໃນ session ບໍ
                $checkStmt = $this->db->prepare("SELECT id FROM {$this->detailsTable} WHERE session_id = ? AND item_id = ?");
                $checkStmt->execute([$sessionId, $itemId]);
                if ($checkStmt->fetch()) {
                    error_log("Item $itemId already exists in session");
                    continue;
                }
                
                // ບໍ່ຕ້ອງລະບຸ created_at ເພາະມັນຈະຖືກຕັ້ງອັດຕະໂນມັດ
                $sql = "INSERT INTO {$this->detailsTable} (
                            session_id, item_id, expected_quantity, notes, status
                        ) VALUES (?, ?, ?, ?, 'pending')";
                
                $stmt = $this->db->prepare($sql);
                $result = $stmt->execute([
                    $sessionId,
                    $itemId,
                    $currentStock,
                    $item['notes'] ?? null
                ]);
                
                if ($result) {
                    $addedCount++;
                    error_log("Added item $itemId to session");
                } else {
                    $error = $stmt->errorInfo();
                    error_log("Failed to add item $itemId: " . print_r($error, true));
                    $errors[] = "Failed to add item ID: $itemId - " . ($error[2] ?? 'Unknown error');
                }
            }
            
            error_log("Added $addedCount items to session");
            
            if ($addedCount === 0 && count($errors) > 0) {
                return [
                    'success' => false,
                    'message' => 'Failed to add items: ' . implode(', ', $errors)
                ];
            }
            
            return [
                'success' => true,
                'message' => $addedCount . ' items added to session',
                'added_count' => $addedCount
            ];
            
        } catch (Exception $e) {
            error_log("Error adding items: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            return [
                'success' => false,
                'message' => 'Failed to add items: ' . $e->getMessage()
            ];
        }
    }

    public function checkCodeExists($code) {
        try {
            $stmt = $this->db->prepare("SELECT COUNT(*) as count FROM {$this->table} WHERE session_code = ?");
            $stmt->execute([$code]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return ($result['count'] ?? 0) > 0;
        } catch (Exception $e) {
            error_log("Error checking code exists: " . $e->getMessage());
            return false;
        }
    }

 

    // ລຶບຟັງຊັນ getSessionDetails ເກົ່າ ແລະ ເພີ່ມອັນໃໝ່ນີ້

    public function getSessionDetails($sessionId) {
        try {
            // ໃຊ້ PDO ໂດຍກົງ
            $db = $this->db;
            
            // ກວດສອບ session
            $stmt = $db->prepare("SELECT id, session_code, session_name, status FROM stock_count_sessions WHERE id = ?");
            $stmt->execute([$sessionId]);
            $session = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$session) {
                return ['details' => [], 'stats' => [], 'session' => null];
            }
            
            // ດຶງຂໍ້ມູນລາຍລະອຽດ - ໃຊ້ຊື່ຕາລາງໂດຍກົງ
            $sql = "SELECT 
                        d.id,
                        d.session_id,
                        d.item_id,
                        d.expected_quantity,
                        d.counted_quantity,
                        d.variance,
                        d.variance_percent,
                        d.status,
                        d.counted_by,
                        d.counted_at,
                        d.notes,
                        i.item_code,
                        i.item_name,
                        i.barcode
                    FROM stock_count_details d
                    LEFT JOIN inventory_items i ON d.item_id = i.id
                    WHERE d.session_id = " . (int)$sessionId;
            
            $stmt = $db->query($sql);
            $details = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // ຄຳນວນສະຖິຕິ
            $stats = [
                'total_items' => count($details),
                'counted_items' => 0,
                'items_with_variance' => 0,
                'total_variance' => 0,
                'completed_percent' => 0
            ];
            
            foreach ($details as &$detail) {
                $detail['expected_quantity'] = (float)$detail['expected_quantity'];
                $detail['counted_quantity'] = (float)$detail['counted_quantity'];
                $detail['variance'] = (float)$detail['variance'];
                
                if ($detail['status'] === 'counted') {
                    $stats['counted_items']++;
                    if ($detail['variance'] != 0) {
                        $stats['items_with_variance']++;
                        $stats['total_variance'] += $detail['variance'];
                    }
                }
            }
            
            if ($stats['total_items'] > 0) {
                $stats['completed_percent'] = round(($stats['counted_items'] / $stats['total_items']) * 100);
            }
            
            return [
                'details' => $details,
                'stats' => $stats,
                'session' => $session
            ];
            
        } catch (Exception $e) {
            error_log("Error in getSessionDetails: " . $e->getMessage());
            return ['details' => [], 'stats' => [], 'session' => null];
        }
    }


    public function getSessionDetailsDirect($sessionId) {
        try {
            error_log("=== StockCount::getSessionDetailsDirect ===");
            error_log("Session ID: $sessionId");
            
            // ດຶງຂໍ້ມູນໂດຍກົງໂດຍບໍ່ມີ JOIN
            $sql = "SELECT * FROM {$this->detailsTable} WHERE session_id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$sessionId]);
            $details = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            error_log("Direct query found " . count($details) . " records");
            
            return [
                'details' => $details,
                'stats' => ['total_items' => count($details)]
            ];
            
        } catch (Exception $e) {
            error_log("Error in direct query: " . $e->getMessage());
            return ['details' => [], 'stats' => []];
        }
    }

}