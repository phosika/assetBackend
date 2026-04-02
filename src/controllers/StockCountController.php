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
    
    /**
     * GET /stock-counts - ດຶງຂໍ້ມູນການນັບທັງໝົດ
     */
    // public function getStockCounts() {
    //     try {
    //         $userId = AuthMiddleware::authenticate();
            
    //         $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    //         $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
    //         $filters = [
    //             'status' => $_GET['status'] ?? null,
    //             'warehouse_id' => $_GET['warehouse_id'] ?? null,
    //             'search' => $_GET['search'] ?? null
    //         ];
            
    //         $result = $this->stockCountModel->getSessions($filters, $page, $limit);
    //         Response::success($result, 200, 'Stock counts retrieved successfully');
            
    //     } catch (Exception $e) {
    //         Response::error('Failed to retrieve stock counts: ' . $e->getMessage(), 500);
    //     }
    // }


 

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
            error_log("=== RECORD COUNT ===");
            error_log("Session ID: " . $sessionId);
            error_log("Item ID: " . $itemId);
            
            $userId = AuthMiddleware::authenticate();
            
            // ອ່ານຂໍ້ມູນຈາກ request body
            $input = json_decode(file_get_contents('php://input'), true);
            error_log("Request input: " . json_encode($input));
            
            if (!isset($input['counted_quantity'])) {
                error_log("Missing counted_quantity in request");
                Response::error('Counted quantity is required', 400);
                return;
            }
            
            $countedQuantity = $input['counted_quantity'];
            error_log("Counted quantity: " . $countedQuantity);
            
            $result = $this->stockCountModel->recordCount($sessionId, $itemId, $countedQuantity, $userId);
            error_log("Record count result: " . json_encode($result));
            
            if ($result['success']) {
                Response::success($result, 200, $result['message']);
            } else {
                Response::error($result['message'], 400);
            }
            
        } catch (Exception $e) {
            error_log("Error recording count: " . $e->getMessage());
            Response::error('Failed to record count: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * POST /stock-counts/{id}/complete - ສຳເລັດການນັບ
     */
    // public function completeStockCount($id) {
    //     try {
    //         $userId = AuthMiddleware::authenticate();
    //         $input = json_decode(file_get_contents('php://input'), true);
    //         $adjustStock = $input['adjust_stock'] ?? true;
            
    //         $result = $this->stockCountModel->completeSession($id, $userId, $adjustStock);
            
    //         if ($result['success']) {
    //             Response::success($result, 200, $result['message']);
    //         } else {
    //             Response::error($result['message'], 400);
    //         }
            
    //     } catch (Exception $e) {
    //         Response::error('Failed to complete stock count: ' . $e->getMessage(), 500);
    //     }
    // }


    // src/controllers/StockCountController.php

    /**
     * POST /stock-counts/{id}/complete - ສຳເລັດການນັບ
     */
    public function completeStockCount($id) {
        try {
            $userId = AuthMiddleware::authenticate();
            $input = json_decode(file_get_contents('php://input'), true);
            
            // ຮັບຄ່າ adjust_stock ຈາກ frontend (default = true ເພື່ອຄວາມເຂົ້າກັນໄດ້)
            $adjustStock = isset($input['adjust_stock']) ? (bool)$input['adjust_stock'] : true;
            
            error_log("Completing stock count $id, adjust_stock: " . ($adjustStock ? 'true' : 'false'));
            
            $result = $this->stockCountModel->completeSession($id, $userId, $adjustStock);
            
            if ($result['success']) {
                Response::success($result, 200, $result['message']);
            } else {
                Response::error($result['message'], 400);
            }
            
        } catch (Exception $e) {
            error_log("Error completing stock count: " . $e->getMessage());
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
    public function startStockCount($id) {
        try {
            error_log("=== START STOCK COUNT ===");
            error_log("Session ID: " . $id);
            
            $userId = AuthMiddleware::authenticate();
            error_log("User ID: " . $userId);
            
            // ກວດສອບວ່າມີ session ບໍ
            $session = $this->stockCountModel->getById($id);
            error_log("Session found: " . json_encode($session));
            
            if (!$session) {
                Response::error('Stock count session not found', 404);
                return;
            }
            
            // ກວດສອບວ່າສະຖານະເປັນ draft ບໍ
            if ($session['status'] !== 'draft') {
                error_log("Invalid status: " . $session['status']);
                Response::error('Cannot start count. Session status is ' . $session['status'], 400);
                return;
            }
            
            // ກວດສອບວ່າມີສິນຄ້າໃນການນັບບໍ
            $details = $this->stockCountModel->getSessionDetails($id);
            error_log("Items count: " . count($details['details']));
            
            if (count($details['details']) === 0) {
                Response::error('Cannot start count. No items to count in this session', 400);
                return;
            }
            
            // ອັບເດດສະຖານະ
            $result = $this->stockCountModel->startSession($id, $userId);
            error_log("Start result: " . json_encode($result));
            
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
}