<?php
// src/controllers/AssetSyncController.php
require_once __DIR__ . '/../models/AssetSyncLog.php';
require_once __DIR__ . '/../models/Asset.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../utils/Response.php';


class AssetSyncController {
    private $assetSyncLog;
    private $assetModel;
    
    public function __construct() {
        $this->assetSyncLog = new AssetSyncLog();
        $this->assetModel = new Asset();
    }
    
    
    /**
     * ຮັບຂໍ້ມູນການຂາຍຈາກ Frontend ແລະ ບັນທຶກໄວ້
     */
    public function syncFromSales() {
        try {
            // ກວດສອບ authentication - ໃຊ້ authenticate() ຂອງ AuthMiddleware
            $userId = AuthMiddleware::authenticate();
            
            error_log("AssetSyncController: User authenticated - ID: " . $userId);
            
            // ດຶງຂໍ້ມູນຜູ້ໃຊ້
            $userModel = new User();
            $user = $userModel->getById($userId);
            
            if (!$user) {
                Response::error('User not found', 404);
                return;
            }
            
            // ຮັບຂໍ້ມູນ JSON
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!$input) {
                error_log("AssetSyncController: Invalid input data");
                Response::error('Invalid input data', 400);
                return;
            }
            
            error_log("AssetSyncController: Input data: " . json_encode($input));
            
            if (empty($input['source_id']) || empty($input['source_number'])) {
                error_log("AssetSyncController: Missing source_id or source_number");
                Response::error('Missing required fields: source_id or source_number', 400);
                return;
            }
            
            // 1. ບັນທຶກໃນ asset_sync_log
            $logResult = $this->assetSyncLog->create([
                'source_type' => $input['source_type'] ?? 'sales_order',
                'source_id' => $input['source_id'],
                'source_number' => $input['source_number'],
                'customer_id' => $input['customer_id'] ?? null,
                'customer_name' => $input['customer_name'] ?? null,
                'total_amount' => $input['total_amount'] ?? 0,
                'sale_date' => $input['sale_date'] ?? date('Y-m-d'),
                'items_data' => json_encode($input['items'] ?? []),
                'notes' => $input['notes'] ?? null,
                'synced_by' => $userId
            ]);
            
            error_log("AssetSyncController: Log result: " . json_encode($logResult));
            
            if (!$logResult['success']) {
                Response::error($logResult['message'], 500);
                return;
            }
            
            // 2. ສ້າງ assets ສຳລັບສິນຄ້າທີ່ຂາຍ
            $assetResult = $this->assetModel->createFromSales([
                'source_type' => $input['source_type'] ?? 'sales_order',
                'source_id' => $input['source_id'],
                'source_number' => $input['source_number'],
                'customer_id' => $input['customer_id'] ?? null,
                'customer_name' => $input['customer_name'] ?? null,
                'sale_date' => $input['sale_date'] ?? date('Y-m-d'),
                'items' => $input['items'] ?? [],
                'company_id' => $input['company_id'] ?? 1,
                'department_id' => $input['department_id'] ?? 1
            ], $userId);
            
            error_log("AssetSyncController: Asset creation result: " . json_encode($assetResult));
            
            if ($assetResult['success']) {
                Response::success([
                    'sync_id' => $logResult['sync_id'],
                    'assets_created' => count($assetResult['assets']),
                    'assets' => $assetResult['assets'],
                    'synced_at' => date('Y-m-d H:i:s')
                ], 200, 'Data synced to asset system successfully');
            } else {
                Response::error($assetResult['message'], 500);
            }
            
        } catch (Exception $e) {
            error_log("AssetSyncController Error: " . $e->getMessage());
            error_log("AssetSyncController Stack trace: " . $e->getTraceAsString());
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