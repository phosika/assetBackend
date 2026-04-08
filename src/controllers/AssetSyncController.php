<?php
// src/controllers/AssetSyncController.php
require_once __DIR__ . '/../models/AssetSyncLog.php';
require_once __DIR__ . '/../models/Asset.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../utils/Response.php';


class AssetSyncController {
    private $assetModel;
    private $userModel;
    
    public function __construct() {
        $this->assetModel = new Asset();
        $this->userModel = new User();  // ຕ້ອງມີການສ້າງ User model
    }
    
    /**
     * POST /asset/sync-from-sales
     * ຮັບຂໍ້ມູນການຂາຍຈາກ Sales module ແລ້ວສ້າງເປັນຊັບສິນ
     */
    public function syncFromSales() {
        try {
            error_log("=== AssetSyncController::syncFromSales START ===");
            
            // ກວດສອບ authentication
            $payload = AuthMiddleware::authenticate();
            error_log("Auth payload: " . json_encode($payload));
            
            // ສະກັດ user_id
            if (is_array($payload)) {
                $userId = $payload['user_id'] ?? $payload['id'] ?? null;
            } else if (is_object($payload)) {
                $userId = $payload->user_id ?? $payload->id ?? null;
            } else {
                $userId = $payload;
            }
            
            if (!$userId) {
                Response::error('Unauthorized: Invalid user', 401);
                return;
            }
            
            error_log("User ID: " . $userId);
            
            // ກວດສອບວ່າ user ມີຢູ່
            $user = $this->userModel->getById($userId);
            if (!$user) {
                error_log("User not found: " . $userId);
                Response::error('User not found', 404);
                return;
            }
            
            // ຮັບຂໍ້ມູນ
            $input = json_decode(file_get_contents('php://input'), true);
            error_log("Input data: " . json_encode($input));
            
            if (!$input) {
                Response::error('Invalid input data', 400);
                return;
            }
            
            if (empty($input['source_id']) || empty($input['source_number'])) {
                Response::error('Missing required fields: source_id or source_number', 400);
                return;
            }
            
            if (empty($input['items']) || !is_array($input['items'])) {
                Response::error('No items to sync', 400);
                return;
            }
            
            // ສ້າງຊັບສິນ
            $result = $this->assetModel->createFromSales($input, $userId);
            
            error_log("Create result: " . json_encode($result));
            
            if ($result['success']) {
                Response::success([
                    'assets_created' => count($result['assets']),
                    'assets' => $result['assets'],
                    'synced_at' => date('Y-m-d H:i:s')
                ], 'Data synced to asset system successfully');
            } else {
                Response::error($result['message'], 500);
            }
            
        } catch (Exception $e) {
            error_log("Error in syncFromSales: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            Response::error('Server error: ' . $e->getMessage(), 500);
        }
    }
    
   
    /**
     * ດຶງຂໍ້ມູນຊັບສິນທີ່ຂາຍແລ້ວ
     */
    public function getSoldAssets() {
        try {
            // ກວດສອບ authentication
            $userId = AuthMiddleware::authenticate();
            
            $filters = [
                'asset_code' => $_GET['asset_code'] ?? null,
                'asset_name' => $_GET['asset_name'] ?? null,
                'customer_id' => $_GET['customer_id'] ?? null,
                'from_date' => $_GET['from_date'] ?? null,
                'to_date' => $_GET['to_date'] ?? null,
                'search' => $_GET['search'] ?? null
            ];
            
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
            
            $result = $this->assetModel->getSoldAssets($filters, $page, $limit);
            
            Response::success($result, 200, 'Sold assets retrieved successfully');
            
        } catch (Exception $e) {
            error_log("Error in getSoldAssets: " . $e->getMessage());
            Response::error('Server error: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * ດຶງສະຖິຕິຊັບສິນທີ່ຂາຍແລ້ວ
     */
    public function getSoldAssetsStats() {
        try {
            // ກວດສອບ authentication
            $userId = AuthMiddleware::authenticate();
            
            $stats = $this->assetModel->getSoldAssetsStats();
            
            Response::success($stats, 200, 'Sold assets stats retrieved successfully');
            
        } catch (Exception $e) {
            error_log("Error in getSoldAssetsStats: " . $e->getMessage());
            Response::error('Server error: ' . $e->getMessage(), 500);
        }
    }
}