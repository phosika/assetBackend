 
<?php
require_once __DIR__ . '/../models/StockAdjustment.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../utils/Response.php';

class StockAdjustmentController {
    private $adjustmentModel;
    
    public function __construct() {
        $this->adjustmentModel = new StockAdjustment();
    }
    
    /**
     * GET /stock-adjustments - ດຶງຂໍ້ມູນການປັບທັງໝົດ
     */
    public function getAdjustments() {
        try {
            $userId = AuthMiddleware::authenticate();
            
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
            $filters = [
                'status' => $_GET['status'] ?? null,
                'item_id' => $_GET['item_id'] ?? null,
                'adjustment_type' => $_GET['adjustment_type'] ?? null,
                'search' => $_GET['search'] ?? null
            ];
            
            $result = $this->adjustmentModel->getAllAdjustments($filters, $page, $limit);
            Response::success($result, 200, 'Adjustments retrieved successfully');
            
        } catch (Exception $e) {
            Response::error('Failed to retrieve adjustments: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * POST /stock-adjustments - ສ້າງການປັບໃໝ່
     */
    public function createAdjustment() {
        try {
            $userId = AuthMiddleware::authenticate();
            $input = json_decode(file_get_contents('php://input'), true);
            
            $result = $this->adjustmentModel->createAdjustment($input, $userId);
            
            if ($result['success']) {
                Response::success($result, 201, $result['message']);
            } else {
                Response::error($result['message'], 400);
            }
            
        } catch (Exception $e) {
            Response::error('Failed to create adjustment: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * POST /stock-adjustments/{id}/approve - ອະນຸມັດການປັບ
     */
    public function approveAdjustment($id) {
        try {
            $userId = AuthMiddleware::authenticate();
            
            $result = $this->adjustmentModel->approveAdjustment($id, $userId);
            
            if ($result['success']) {
                Response::success($result, 200, $result['message']);
            } else {
                Response::error($result['message'], 400);
            }
            
        } catch (Exception $e) {
            Response::error('Failed to approve adjustment: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * GET /stock-adjustments/stats - ດຶງສະຖິຕິ
     */
    public function getAdjustmentStats() {
        try {
            $userId = AuthMiddleware::authenticate();
            
            $stats = $this->adjustmentModel->getStats();
            Response::success($stats, 200, 'Statistics retrieved successfully');
            
        } catch (Exception $e) {
            Response::error('Failed to retrieve stats: ' . $e->getMessage(), 500);
        }
    }
}