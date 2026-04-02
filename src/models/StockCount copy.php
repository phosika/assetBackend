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
    public function recordCount($sessionId, $itemId, $countedQuantity, $countedBy) {
        try {
            error_log("=== StockCount::recordCount ===");
            error_log("Session ID: $sessionId, Item ID: $itemId, Counted: $countedQuantity, Counted By: $countedBy");
            
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
    

 
    // src/models/StockCount.php

    /**
     * ສຳເລັດການນັບ ແລະ ປັບສະຕ໋ອກ
     */
    public function completeSession($sessionId, $completedBy, $adjustStock = true) {
        try {
            error_log("=== StockCount::completeSession START ===");
            error_log("Session ID: $sessionId, Completed By: $completedBy");
            
            // ກວດສອບວ່າ session ມີຢູ່
            $stmt = $this->db->prepare("SELECT id, status, warehouse_id FROM {$this->table} WHERE id = ?");
            $stmt->execute([$sessionId]);
            $session = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$session) {
                error_log("Session not found: $sessionId");
                return ['success' => false, 'message' => 'Stock count session not found'];
            }
            
            error_log("Session status: " . $session['status']);
            
            if ($session['status'] !== 'in_progress') {
                error_log("Invalid session status: " . $session['status']);
                return ['success' => false, 'message' => 'Cannot complete count. Session status is ' . $session['status']];
            }
            
            $warehouseId = $session['warehouse_id'];
            
            // ດຶງຂໍ້ມູນສິນຄ້າທີ່ນັບແລ້ວ
            $stmt = $this->db->prepare("
                SELECT * FROM {$this->detailsTable} 
                WHERE session_id = ? AND status = 'counted'
            ");
            $stmt->execute([$sessionId]);
            $countedItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
            error_log("Found " . count($countedItems) . " counted items");
            
            $adjustments = [];
            $totalVariance = 0;
            
            // ປະມວນຜົນການປັບສະຕ໋ອກ
            if ($adjustStock && !empty($countedItems)) {
                error_log("Processing stock adjustments...");
                
                foreach ($countedItems as $item) {
                    $itemId = $item['item_id'];
                    $expectedQty = (float)$item['expected_quantity'];
                    $countedQty = (float)$item['counted_quantity'];
                    $variance = $countedQty - $expectedQty;
                    $totalVariance += $variance;
                    
                    error_log("Item ID: $itemId, Expected: $expectedQty, Counted: $countedQty, Variance: $variance");
                    
                    if ($variance != 0) {
                        // ປັບສະຕ໋ອກ
                        $adjustmentResult = $this->updateStockQuantity(
                            $itemId, 
                            $warehouseId, 
                            $variance, 
                            $expectedQty, 
                            $countedQty,
                            $sessionId,
                            $completedBy
                        );
                        
                        if ($adjustmentResult['success']) {
                            $adjustments[] = $adjustmentResult;
                            error_log("Stock updated for item $itemId: " . $adjustmentResult['message']);
                        } else {
                            error_log("Failed to update stock for item $itemId: " . $adjustmentResult['message']);
                            // ບໍ່ throw exception ແຕ່ log ໄວ້
                        }
                    } else {
                        error_log("No variance for item $itemId, skipping adjustment");
                    }
                }
            }
            
            // ອັບເດດສະຖານະ session
            $sql = "UPDATE {$this->table} 
                    SET status = 'completed',
                        end_date = NOW(),
                        completed_by = ?,
                        completed_at = NOW()
                    WHERE id = ?";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$completedBy, $sessionId]);
            error_log("Session status updated to completed");
            
            return [
                'success' => true,
                'message' => 'Stock count completed successfully',
                'total_items' => count($countedItems),
                'adjustments_made' => count($adjustments),
                'total_variance' => $totalVariance,
                'adjustments' => $adjustments
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
            error_log("Item: $itemId, Variance: $variance");
            
            // ກວດສອບວ່າມີສະຕ໋ອກຢູ່ແລ້ວບໍ
            $stmt = $this->db->prepare("
                SELECT id, current_quantity, available_quantity 
                FROM inventory_stock 
                WHERE item_id = ? AND warehouse_id = ?
            ");
            $stmt->execute([$itemId, $warehouseId]);
            $stock = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $oldQuantity = $stock['current_quantity'] ?? 0;
            $newQuantity = $oldQuantity + $variance;
            
            // ກວດສອບບໍ່ໃຫ້ຕິດລົບ
            if ($newQuantity < 0) {
                error_log("Warning: Stock would become negative ($newQuantity), setting to 0");
                $newQuantity = 0;
            }
            
            error_log("Stock update: $oldQuantity -> $newQuantity (variance: $variance)");
            
            if ($stock) {
                // ອັບເດດສະຕ໋ອກທີ່ມີຢູ່
                $sql = "UPDATE inventory_stock 
                        SET current_quantity = ?,
                            available_quantity = ?,
                            last_count_date = NOW(),
                            last_count_quantity = ?,
                            updated_by = ?,
                            updated_at = NOW()
                        WHERE id = ?";
                
                $stmt = $this->db->prepare($sql);
                $stmt->execute([
                    $newQuantity,
                    $newQuantity,
                    $countedQty,
                    $userId,
                    $stock['id']
                ]);
            } else {
                // ສ້າງສະຕ໋ອກໃໝ່
                $sql = "INSERT INTO inventory_stock (
                            item_id, warehouse_id, current_quantity, available_quantity,
                            last_count_date, last_count_quantity, created_by, created_at
                        ) VALUES (?, ?, ?, ?, NOW(), ?, ?, NOW())";
                
                $stmt = $this->db->prepare($sql);
                $stmt->execute([
                    $itemId,
                    $warehouseId,
                    $newQuantity,
                    $newQuantity,
                    $countedQty,
                    $userId
                ]);
            }
            
            // ບັນທຶກປະຫວັດການປັບສະຕ໋ອກ
            $this->logStockAdjustmentHistory(
                $itemId, 
                $warehouseId, 
                $variance, 
                $oldQuantity, 
                $newQuantity, 
                $expectedQty, 
                $countedQty, 
                $sessionId, 
                $userId
            );
            
            return [
                'success' => true,
                'item_id' => $itemId,
                'old_quantity' => $oldQuantity,
                'new_quantity' => $newQuantity,
                'variance' => $variance,
                'message' => "Stock updated from $oldQuantity to $newQuantity"
            ];
            
        } catch (Exception $e) {
            error_log("Error updating stock quantity: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to update stock: ' . $e->getMessage()
            ];
        }
    }

    /**
     * ບັນທຶກປະຫວັດການປັບສະຕ໋ອກ
     */
    private function logStockAdjustmentHistory($itemId, $warehouseId, $variance, $oldQuantity, $newQuantity, $expectedQty, $countedQty, $sessionId, $userId) {
        try {
            // ກວດສອບວ່າມີຕາລາງ stock_adjustment_history ບໍ
            $stmt = $this->db->query("SHOW TABLES LIKE 'stock_adjustment_history'");
            if ($stmt->rowCount() == 0) {
                error_log("stock_adjustment_history table does not exist, skipping log");
                return;
            }
            
            $adjustmentType = $variance > 0 ? 'increase' : ($variance < 0 ? 'decrease' : 'no_change');
            
            $sql = "INSERT INTO stock_adjustment_history (
                        item_id, warehouse_id, adjustment_type, quantity_before,
                        quantity_after, adjusted_quantity, reason, reason_detail,
                        expected_quantity, counted_quantity, variance, reference_id,
                        created_by, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $itemId,
                $warehouseId,
                $adjustmentType,
                $oldQuantity,
                $newQuantity,
                abs($variance),
                'stock_count',
                "Stock count adjustment from session #$sessionId",
                $expectedQty,
                $countedQty,
                $variance,
                $sessionId,
                $userId
            ]);
            
            error_log("Stock adjustment logged for item $itemId");
            
        } catch (Exception $e) {
            error_log("Error logging stock adjustment: " . $e->getMessage());
            // ບໍ່ throw exception ເພາະເປັນ optional log
        }
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
        
    /**
     * ດຶງຂໍ້ມູນການນັບ
     */
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
            
            return [
                'data' => $sessions,
                'total' => $total,
                'current_page' => $page,
                'per_page' => $limit
            ];
            
        } catch (Exception $e) {
            return ['data' => [], 'total' => 0, 'current_page' => 1, 'per_page' => $limit];
        }
    }
    
    /**
     * ດຶງລາຍລະອຽດການນັບ
     */
    public function getSessionDetails($sessionId) {
        try {
            $sql = "SELECT d.*,
                           i.item_code,
                           i.item_name,
                           i.barcode,
                           CONCAT(c.first_name, ' ', c.last_name) as counted_by_name,
                           CONCAT(v.first_name, ' ', v.last_name) as verified_by_name
                    FROM {$this->detailsTable} d
                    LEFT JOIN inventory_items i ON d.item_id = i.id
                    LEFT JOIN users c ON d.counted_by = c.id
                    LEFT JOIN users v ON d.verified_by = v.id
                    WHERE d.session_id = ?
                    ORDER BY d.id";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$sessionId]);
            $details = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // ຄຳນວນສະຖິຕິ
            $stats = [
                'total_items' => count($details),
                'counted_items' => 0,
                'items_with_variance' => 0,
                'total_variance' => 0
            ];
            
            foreach ($details as $detail) {
                if ($detail['status'] === 'counted') {
                    $stats['counted_items']++;
                    if ($detail['variance'] != 0) {
                        $stats['items_with_variance']++;
                        $stats['total_variance'] += $detail['variance'];
                    }
                }
            }
            
            return [
                'details' => $details,
                'stats' => $stats
            ];
            
        } catch (Exception $e) {
            return ['details' => [], 'stats' => []];
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
            error_log("Session ID: $id, User ID: $userId");
            
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
            
            $sql = "SELECT COALESCE(current_quantity, 0) as current_stock 
                    FROM inventory_stock 
                    WHERE item_id = ?";
            $params = [$itemId];
            
            if ($warehouseId) {
                $sql .= " AND warehouse_id = ?";
                $params[] = $warehouseId;
            }
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $currentStock = $result['current_stock'] ?? 0;
            error_log("Current stock for item $itemId: $currentStock");
            
            return $currentStock;
            
        } catch (Exception $e) {
            error_log("Error getting current stock: " . $e->getMessage());
            return 0;
        }
    }

 
    public function createSession($data, $createdBy) {
        try {
            error_log("=== StockCount::createSession ===");
            error_log("Data: " . json_encode($data));
            
            $sessionCode = $this->generateSessionCode();
            
            $sql = "INSERT INTO {$this->table} (
                        session_code, session_name, count_type, warehouse_id, 
                        start_date, notes, created_by, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $sessionCode,
                $data['session_name'] ?? 'Stock Count ' . date('Y-m-d'),
                $data['count_type'] ?? 'full',
                $data['warehouse_id'] ?? null,
                $data['start_date'] ?? date('Y-m-d H:i:s'),
                $data['notes'] ?? null,
                $createdBy
            ]);
            
            $sessionId = $this->db->lastInsertId();
            error_log("Session created with ID: $sessionId");
            
            // ເພີ່ມສິນຄ້າເຂົ້າໃນການນັບ
            if (!empty($data['items'])) {
                error_log("Items to add: " . json_encode($data['items']));
                $itemsAdded = $this->addItemsToSession($sessionId, $data['items']);
                error_log("Add items result: " . json_encode($itemsAdded));
            } else {
                error_log("No items to add");
            }
            
            return [
                'success' => true,
                'session_id' => $sessionId,
                'session_code' => $sessionCode,
                'message' => 'Stock count session created successfully'
            ];
            
        } catch (Exception $e) {
            error_log("Error creating session: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to create session: ' . $e->getMessage()
            ];
        }
    }

    public function addItemsToSession($sessionId, $items) {
        try {
            error_log("=== StockCount::addItemsToSession ===");
            error_log("Session ID: $sessionId");
            error_log("Items: " . json_encode($items));
            
            // ດຶງຂໍ້ມູນ session ເພື່ອເອົາ warehouse_id
            $stmt = $this->db->prepare("SELECT warehouse_id FROM {$this->table} WHERE id = ?");
            $stmt->execute([$sessionId]);
            $session = $stmt->fetch(PDO::FETCH_ASSOC);
            $warehouseId = $session['warehouse_id'] ?? null;
            error_log("Warehouse ID: " . ($warehouseId ?? 'NULL'));
            
            $addedCount = 0;
            
            foreach ($items as $item) {
                // ດຶງຈຳນວນສະຕ໋ອກຕົວຈິງ
                $currentStock = $this->getCurrentStockQuantity($item['item_id'], $warehouseId);
                error_log("Item {$item['item_id']}: Current stock = $currentStock");
                
                $sql = "INSERT INTO {$this->detailsTable} (
                            session_id, item_id, expected_quantity, notes
                        ) VALUES (?, ?, ?, ?)";
                
                $stmt = $this->db->prepare($sql);
                $stmt->execute([
                    $sessionId,
                    $item['item_id'],
                    $currentStock,  // ໃຊ້ສະຕ໋ອກຕົວຈິງ
                    $item['notes'] ?? null
                ]);
                $addedCount++;
            }
            
            error_log("Added $addedCount items to session");
            
            return [
                'success' => true,
                'message' => $addedCount . ' items added to session'
            ];
            
        } catch (Exception $e) {
            error_log("Error adding items: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to add items: ' . $e->getMessage()
            ];
        }
    }



}