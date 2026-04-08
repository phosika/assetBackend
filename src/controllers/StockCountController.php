<?php
// src/controllers/StockCountController.php
require_once __DIR__ . '/../models/StockCount.php';
require_once __DIR__ . '/../models/StockAdjustment.php';
require_once __DIR__ . '/../models/InventoryItem.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../utils/Response.php';

class StockCountController {
    private $stockCountModel;
    private $stockAdjustmentModel;
    private $inventoryItemModel;
    
    public function __construct() {
        $this->stockCountModel = new StockCount();
        $this->stockAdjustmentModel = new StockAdjustment();
        $this->inventoryItemModel = new InventoryItem();
    }
    

    
public function checkSessionCode() {
    try {
        $code = $_GET['code'] ?? '';
        
        if (empty($code)) {
            Response::error('Session code is required', 400);
            return;
        }
        
        $stockCountModel = new StockCount();
        $exists = $stockCountModel->checkCodeExists($code);
        
        Response::success(['exists' => $exists], 200, 'Code check completed');
    } catch (Exception $e) {
        error_log("Error checking code: " . $e->getMessage());
        Response::error('Failed to check code', 500);
    }
}


 

    public function getStockCounts() {
        try {
            $userId = AuthMiddleware::authenticate();
            
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
            $filters = [
                'status' => $_GET['status'] ?? null,
                'warehouse_id' => $_GET['warehouse_id'] ?? null,
                'search' => $_GET['search'] ?? null
            ];
            
            $result = $this->stockCountModel->getSessions($filters, $page, $limit);
            
            // Debug log
            error_log("=== getStockCounts result ===");
            error_log("Total: " . ($result['total'] ?? 0));
            error_log("Data count: " . count($result['data'] ?? []));
            
            Response::success($result, 200, 'Stock counts retrieved successfully');
            
        } catch (Exception $e) {
            error_log("Error in getStockCounts: " . $e->getMessage());
            Response::error('Failed to retrieve stock counts: ' . $e->getMessage(), 500);
        }
    }
    
    public function createStockCount() {
        try {
            $userId = AuthMiddleware::authenticate();
            $input = json_decode(file_get_contents('php://input'), true);
            
            error_log("=== CREATE STOCK COUNT ===");
            error_log("Input data: " . json_encode($input));
            
            $result = $this->stockCountModel->createSession($input, $userId);
            
            if ($result['success']) {
                Response::success($result, 201, $result['message']);
            } else {
                Response::error($result['message'], 400);
            }
            
        } catch (Exception $e) {
            error_log("Error creating stock count: " . $e->getMessage());
            Response::error('Failed to create stock count: ' . $e->getMessage(), 500);
        }
    }


    // In StockCount.php, modify the createSession method
    public function createSession($data, $createdBy) {
        try {
            $this->db->beginTransaction();
            
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
            
            // ເພີ່ມສິນຄ້າເຂົ້າໃນການນັບ
            if (!empty($data['items'])) {
                $itemsAdded = $this->addItemsToSession($sessionId, $data['items']);
                if (!$itemsAdded['success']) {
                    throw new Exception($itemsAdded['message']);
                }
            }
            
            $this->db->commit();
            
            return [
                'success' => true,
                'session_id' => $sessionId,
                'session_code' => $sessionCode,
                'message' => 'Stock count session created successfully'
            ];
            
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error creating stock count session: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to create session: ' . $e->getMessage()
            ];
        }
    }

    
    /**
     * POST /stock-counts/{id}/items - ເພີ່ມສິນຄ້າເຂົ້າການນັບ
     */
    public function addItemsToCount($id) {
        try {
            $userId = AuthMiddleware::authenticate();
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (empty($input['items'])) {
                Response::error('No items provided', 400);
                return;
            }
            
            $result = $this->stockCountModel->addItemsToSession($id, $input['items']);
            
            if ($result['success']) {
                Response::success($result, 200, $result['message']);
            } else {
                Response::error($result['message'], 400);
            }
            
        } catch (Exception $e) {
            Response::error('Failed to add items: ' . $e->getMessage(), 500);
        }
    }
    

    /**
     * POST /stock-counts/{id}/items/{itemId}/count - ບັນທຶກຜົນການນັບ
     */
    public function recordCount($sessionId, $itemId) {
        try {
            error_log("=== recordCount ===");
            error_log("Session ID: $sessionId, Item ID: $itemId");
            
            // ຮັບຂໍ້ມູນຈາກ request body
            $input = json_decode(file_get_contents('php://input'), true);
            $countedQuantity = $input['counted_quantity'] ?? 0;
            
            error_log("Counted quantity: $countedQuantity");
            
            // ເອົາ user ID ຈາກ session
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            
            $userId = null;
            
            // ລອງດຶງຈາກຫຼາຍແຫຼ່ງ
            if (isset($_SESSION['user_id'])) {
                $userId = (int)$_SESSION['user_id'];
            } elseif (isset($_SESSION['user']['id'])) {
                $userId = (int)$_SESSION['user']['id'];
            } elseif (isset($_SESSION['user']['user_id'])) {
                $userId = (int)$_SESSION['user']['user_id'];
            }
            
            // ຖ້າຍັງບໍ່ມີ, ລອງດຶງຈາກ database (ເອົາ user ທຳອິດ)
            if (!$userId) {
                $db = Database::getInstance();
                $stmt = $db->query("SELECT id FROM users LIMIT 1");
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($user) {
                    $userId = (int)$user['id'];
                    error_log("Using first user from database: $userId");
                } else {
                    // ຖ້າບໍ່ມີ user ໃນ database ເລີຍ, ໃຊ້ NULL
                    $userId = null;
                    error_log("No user found, using NULL");
                }
            }
            
            error_log("User ID for counted_by: " . ($userId ?? 'NULL'));
            
            $stockCountModel = new StockCount();
            
            // ແກ້ໄຂໃຫ້ຮັບ $userId ເປັນ parameter
            $result = $stockCountModel->recordCount($sessionId, $itemId, $countedQuantity, $userId);
            
            if ($result['success']) {
                Response::success($result, 200, 'Count recorded successfully');
            } else {
                Response::error($result['message'], 400);
            }
        } catch (Exception $e) {
            error_log("Error recording count: " . $e->getMessage());
            Response::error('Failed to record count: ' . $e->getMessage(), 500);
        }
    }
    
 

    public function recordSimpleCount($sessionId) {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['item_id']) || !isset($data['counted_quantity'])) {
                Response::error('Item ID and counted quantity are required', 400);
                return;
            }
            
            $userId = $_SESSION['user_id'] ?? $_SESSION['user']['id'] ?? 1;
            
            $stockCountModel = new StockCount();
            $result = $stockCountModel->recordCount(
                $sessionId,
                $data['item_id'],
                $data['counted_quantity'],
                $userId
            );
            
            if ($result['success']) {
                Response::success($result, 200, 'Count recorded successfully');
            } else {
                Response::error($result['message'], 400);
            }
        } catch (Exception $e) {
            error_log("Error recording count: " . $e->getMessage());
            Response::error('Failed to record count', 500);
        }
    }

    /**
     * POST /stock-counts/{id}/complete - ສຳເລັດການນັບ
     */
    public function completeStockCount($sessionId) {
        try {
            error_log("=== completeStockCount ===");
            error_log("Session ID: $sessionId");
            
            // ຮັບຂໍ້ມູນຈາກ request body
            $input = json_decode(file_get_contents('php://input'), true);
            $adjustStock = isset($input['adjust_stock']) ? (bool)$input['adjust_stock'] : true;
            
            error_log("Adjust stock: " . ($adjustStock ? 'true' : 'false'));
            
            // ເອົາ user ID ຈາກ session
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            
            $userId = null;
            
            // ລອງດຶງຈາກຫຼາຍແຫຼ່ງ
            if (isset($_SESSION['user_id'])) {
                $userId = (int)$_SESSION['user_id'];
            } elseif (isset($_SESSION['user']['id'])) {
                $userId = (int)$_SESSION['user']['id'];
            } elseif (isset($_SESSION['user']['user_id'])) {
                $userId = (int)$_SESSION['user']['user_id'];
            }
            
            // ກວດສອບວ່າ user ມີຢູ່ໃນ database ບໍ
            $db = Database::getInstance();
            
            if ($userId) {
                $stmt = $db->prepare("SELECT id FROM users WHERE id = ?");
                $stmt->execute([$userId]);
                $userExists = $stmt->fetch();
                
                if (!$userExists) {
                    error_log("User $userId not found, finding another user");
                    $userId = null;
                }
            }
            
            // ຖ້າຍັງບໍ່ມີ, ດຶງ user ທຳອິດ
            if (!$userId) {
                $stmt = $db->query("SELECT id FROM users LIMIT 1");
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($user) {
                    $userId = (int)$user['id'];
                    error_log("Using first user from database: $userId");
                } else {
                    error_log("No user found in database!");
                    $userId = null;
                }
            }
            
            error_log("Final user ID for completed_by: " . ($userId ?? 'NULL'));
            
            $stockCountModel = new StockCount();
            $result = $stockCountModel->completeSession($sessionId, $userId, $adjustStock);
            
            if ($result['success']) {
                Response::success($result, 200, $result['message']);
            } else {
                Response::error($result['message'], 400);
            }
        } catch (Exception $e) {
            error_log("Error completing stock count: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            Response::error('Failed to complete stock count: ' . $e->getMessage(), 500);
        }
    }
        
    /**
     * GET /stock-counts/{id}/details - ດຶງລາຍລະອຽດການນັບ
     */
    public function getStockCountDetails($id) {
        try {
            $userId = AuthMiddleware::authenticate();
            
            $result = $this->stockCountModel->getSessionDetails($id);
            Response::success($result, 200, 'Stock count details retrieved successfully');
            
        } catch (Exception $e) {
            Response::error('Failed to retrieve details: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /stock-counts/stats - ດຶງສະຖິຕິການນັບ
     */
    public function getStockCountStats() {
        try {
            $userId = AuthMiddleware::authenticate();
            
            // ດຶງຂໍ້ມູນສະຖິຕິຈາກ model
            $stats = $this->stockCountModel->getStats();
            
            Response::success($stats, 200, 'Stock count statistics retrieved successfully');
            
        } catch (Exception $e) {
            Response::error('Failed to retrieve stats: ' . $e->getMessage(), 500);
        }
    }

// src/controllers/StockCountController.php

    /**
     * POST /stock-counts/{id}/start - ເລີ່ມການນັບ
     */
    public function startStockCount($sessionId) {
        try {
            error_log("=== startStockCount ===");
            error_log("Session ID: $sessionId");
            
            // ເອົາ user ID ຈາກ session
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            
            $userId = $_SESSION['user_id'] ?? $_SESSION['user']['id'] ?? 1;
            
            // ຖ້າເປັນ array, ແປງເປັນ integer
            if (is_array($userId)) {
                $userId = $userId['id'] ?? $userId[0] ?? 1;
            }
            
            $userId = (int)$userId;
            error_log("User ID: $userId");
            
            $stockCountModel = new StockCount();
            $result = $stockCountModel->startSession($sessionId, $userId);
            
            if ($result['success']) {
                Response::success(null, 200, $result['message']);
            } else {
                Response::error($result['message'], 400);
            }
        } catch (Exception $e) {
            error_log("Error starting stock count: " . $e->getMessage());
            Response::error('Failed to start stock count: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST /stock-counts/{id}/cancel - ຍົກເລີກການນັບ
     */
    public function cancelStockCount($id) {
        try {
            error_log("=== CANCEL STOCK COUNT ===");
            error_log("Session ID: " . $id);
            
            $userId = AuthMiddleware::authenticate();
            
            // ກວດສອບວ່າມີ session ບໍ
            $session = $this->stockCountModel->getById($id);
            if (!$session) {
                Response::error('Stock count session not found', 404);
                return;
            }
            
            // ກວດສອບວ່າສາມາດຍົກເລີກໄດ້ (draft ຫຼື in_progress)
            if (!in_array($session['status'], ['draft', 'in_progress'])) {
                Response::error('Cannot cancel. Session status is ' . $session['status'], 400);
                return;
            }
            
            $result = $this->stockCountModel->cancelSession($id, $userId);
            
            if ($result['success']) {
                Response::success(null, 200, $result['message']);
            } else {
                Response::error($result['message'], 400);
            }
            
        } catch (Exception $e) {
            error_log("Error canceling stock count: " . $e->getMessage());
            Response::error('Failed to cancel stock count: ' . $e->getMessage(), 500);
        }
    }

    // ເພີ່ມ method ນີ້ໃນ StockCountController.php

    public function getStockCountItems($sessionId) {
        try {
            error_log("=== StockCountController::getStockCountItems START ===");
            error_log("Session ID parameter: " . print_r($sessionId, true));
            
            $stockCountModel = new StockCount();
            
            // ທົດສອບກ່ອນວ່າ session ມີຢູ່ບໍ
            $session = $stockCountModel->getById($sessionId);
            error_log("Session found: " . json_encode($session));
            
            if (!$session) {
                error_log("Session not found for ID: $sessionId");
                Response::error('Session not found', 404);
                return;
            }
            
            $result = $stockCountModel->getSessionDetails($sessionId);
            
            error_log("Result from getSessionDetails: " . json_encode([
                'details_count' => count($result['details']),
                'stats' => $result['stats']
            ]));
            
            Response::success([
                'items' => $result['details'],
                'stats' => $result['stats'],
                'session' => $result['session']
            ], 200, 'Items retrieved successfully');
            
        } catch (Exception $e) {
            error_log("Error in getStockCountItems: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            Response::error('Failed to get items: ' . $e->getMessage(), 500);
        }
    }

    public function completeSession($sessionId, $completedBy, $adjustStock = true) {
        try {
            error_log("=== StockCount::completeSession ===");
            error_log("Session ID: $sessionId, Completed By: $completedBy, Adjust Stock: " . ($adjustStock ? 'true' : 'false'));
            
            // ກວດສອບ ແລະ ແປງ $completedBy ໃຫ້ເປັນ integer
            if (is_array($completedBy)) {
                $completedBy = $completedBy['id'] ?? $completedBy[0] ?? 1;
            } elseif (is_object($completedBy)) {
                $completedBy = $completedBy->id ?? 1;
            }
            
            $completedBy = (int)$completedBy;
            
            if ($completedBy <= 0) {
                error_log("Invalid completed_by, using default 1");
                $completedBy = 1;
            }
            
            error_log("Final completed_by value: $completedBy");
            
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
                            $completedBy
                        );
                        $adjustments[] = $adjustmentResult;
                    }
                }
            } else {
                error_log("Skipping stock adjustment (adjust_stock = false)");
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
}