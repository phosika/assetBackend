<?php
 

require_once __DIR__ . '/../models/InventoryStock.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../utils/Response.php';

class InventoryStockController {
    private $stockModel;
    private $userModel;
 
    public function __construct() {
        $this->stockModel = new InventoryStock();
        $this->userModel = new User();
 
    }

    /**
     * ດຶງຂໍ້ມູນຜູ້ໃຊ້ປັດຈຸບັນ
     */
    private function getCurrentUser() {
        try {
            $userPayload = AuthMiddleware::authenticate();
            if (!$userPayload) {
                return null;
            }
            $userId = $userPayload['user_id'];
            $user = $this->userModel->getById($userId);
            return $user;
        } catch (Exception $e) {
            error_log("Auth error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * ກວດສອບສິດຜູ້ເບິ່ງແຍງລະບົບ
     */
    private function checkAdminPermission($user) {
        if (!$user || !in_array($user['role'] ?? '', ['super_admin', 'asset_admin'])) {
            Response::forbidden('You do not have permission to perform this action');
        }
    }

    // ==================== STOCK ENDPOINTS ====================

    /**
     * GET /inventory/stock - ດຶງຂໍ້ມູນສະຕ໋ອກທັງໝົດ
     */
    public function getAllStock() {
        try {
            // ກວດສອບການຢືນຢັນຕົວຕົນ (ບໍ່ບັງຄັບ)
            // $currentUser = $this->getCurrentUser();
            
            // ກະກຽມ filters
            $filters = [
                'search' => isset($_GET['search']) ? trim($_GET['search']) : '',
                'warehouse_id' => isset($_GET['warehouse_id']) && $_GET['warehouse_id'] !== '' ? (int)$_GET['warehouse_id'] : null,
                'item_id' => isset($_GET['item_id']) && $_GET['item_id'] !== '' ? (int)$_GET['item_id'] : null,
                'low_stock' => isset($_GET['low_stock']) && ($_GET['low_stock'] === 'true' || $_GET['low_stock'] === '1'),
                'out_of_stock' => isset($_GET['out_of_stock']) && ($_GET['out_of_stock'] === 'true' || $_GET['out_of_stock'] === '1'),
                'overstock' => isset($_GET['overstock']) && ($_GET['overstock'] === 'true' || $_GET['overstock'] === '1'),
                'page' => isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1,
                'limit' => isset($_GET['limit']) ? (int)$_GET['limit'] : 20,
                'sort_by' => isset($_GET['sort_by']) ? $_GET['sort_by'] : 's.current_quantity',
                'sort_order' => isset($_GET['sort_order']) ? strtoupper($_GET['sort_order']) : 'DESC'
            ];

            // ຈຳກັດ limit ສູງສຸດ
            if ($filters['limit'] > 100) {
                $filters['limit'] = 100;
            }

            error_log("Fetching stock with filters: " . json_encode($filters));
            
            $result = $this->stockModel->getAllStock($filters);
            
            error_log("Stock result count: " . count($result['data']));

            // ກວດສອບວ່າມີ error ບໍ
            if (isset($result['error'])) {
                Response::error('Database error: ' . $result['error'], 500);
                return;
            }

            Response::success([
                'stock' => $result['data'],
                'pagination' => [
                    'current_page' => $result['current_page'],
                    'per_page' => $result['per_page'],
                    'total' => $result['total'],
                    'total_pages' => $result['last_page']
                ]
            ], 'Stock retrieved successfully');
            
        } catch (Exception $e) {
            error_log("Error in getAllStock: " . $e->getMessage());
            Response::error('Failed to retrieve stock: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /inventory/stock/stats - ດຶງສະຖິຕິສະຕ໋ອກ
     */
    public function getStockStats() {
        try {
            // $currentUser = $this->getCurrentUser();
            
            $stats = $this->stockModel->getStockStats();
            
            Response::success($stats, 'Stock statistics retrieved successfully');
        } catch (Exception $e) {
            error_log("Error in getStockStats: " . $e->getMessage());
            Response::error('Failed to retrieve statistics: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /inventory/stock/movements - ດຶງປະຫວັດການເຄື່ອນໄຫວ
     */
    public function getMovements() {
        try {
            // $currentUser = $this->getCurrentUser();

            $filters = [
                'item_id' => isset($_GET['item_id']) ? (int)$_GET['item_id'] : null,
                'movement_type' => $_GET['movement_type'] ?? null,
                'from_date' => $_GET['from_date'] ?? null,
                'to_date' => $_GET['to_date'] ?? null,
                'page' => isset($_GET['page']) ? (int)$_GET['page'] : 1,
                'limit' => isset($_GET['limit']) ? (int)$_GET['limit'] : 50
            ];

            $movements = $this->stockModel->getMovements($filters);

            Response::success([
                'movements' => $movements,
                'total' => count($movements)
            ], 'Movements retrieved successfully');
        } catch (Exception $e) {
            error_log("Error in getMovements: " . $e->getMessage());
            Response::error('Failed to retrieve movements: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /inventory/stock/counts - ດຶງປະຫວັດການນັບສະຕ໋ອກ
     */
    public function getStockCounts() {
        try {
            // $currentUser = $this->getCurrentUser();

            $filters = [
                'item_id' => isset($_GET['item_id']) ? (int)$_GET['item_id'] : null,
                'from_date' => $_GET['from_date'] ?? null,
                'to_date' => $_GET['to_date'] ?? null,
                'page' => isset($_GET['page']) ? (int)$_GET['page'] : 1,
                'limit' => isset($_GET['limit']) ? (int)$_GET['limit'] : 50
            ];

            $result = $this->stockModel->getStockCounts($filters);

            Response::success([
                'counts' => $result['data'],
                'pagination' => [
                    'current_page' => $result['current_page'],
                    'per_page' => $result['per_page'],
                    'total' => $result['total'],
                    'total_pages' => $result['last_page']
                ]
            ], 'Stock counts retrieved successfully');
        } catch (Exception $e) {
            error_log("Error in getStockCounts: " . $e->getMessage());
            Response::error('Failed to retrieve stock counts: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /inventory/stock/counts/summary - ດຶງລາຍງານສະຫຼຸບການນັບ
     */
    public function getStockCountSummary() {
        try {
            // $currentUser = $this->getCurrentUser();

            $fromDate = $_GET['from_date'] ?? null;
            $toDate = $_GET['to_date'] ?? null;

            $summary = $this->stockModel->getStockCountSummary($fromDate, $toDate);

            Response::success($summary, 'Stock count summary retrieved successfully');
        } catch (Exception $e) {
            error_log("Error in getStockCountSummary: " . $e->getMessage());
            Response::error('Failed to retrieve stock count summary: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /inventory/stock/by-item/{itemId} - ດຶງສະຕ໋ອກຕາມສິນຄ້າ
     */
    public function getStockByItem($itemId) {
        try {
            error_log("=== getStockByItem ===");
            error_log("Item ID: $itemId");
            
            // ກວດສອບ authentication
            $userId = AuthMiddleware::authenticate();
            
            // ດຶງ warehouse_id ຈາກ query parameter
            $warehouseId = isset($_GET['warehouse_id']) ? (int)$_GET['warehouse_id'] : null;
            error_log("Warehouse ID: " . ($warehouseId ?? 'ALL'));
            
            // ດຶງຂໍ້ມູນສະຕ໋ອກ
            $stock = $this->stockModel->getStockByItem($itemId, $warehouseId);
            
            if ($stock) {
                Response::success($stock, 200, 'Stock retrieved successfully');
            } else {
                Response::error('Stock not found', 404);
            }
            
        } catch (Exception $e) {
            error_log("Error in getStockByItem: " . $e->getMessage());
            Response::error('Failed to retrieve stock: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /inventory/stock/{id} - ດຶງຂໍ້ມູນສະຕ໋ອກຕາມ ID
     */
    public function getStockById($id) {
        try {
            // $currentUser = $this->getCurrentUser();

            if (!is_numeric($id)) {
                Response::error('Invalid stock ID', 400);
                return;
            }

            $stock = $this->stockModel->getStockById($id);

            if (!$stock) {
                Response::notFound('Stock not found');
                return;
            }

            Response::success($stock, 'Stock retrieved successfully');
        } catch (Exception $e) {
            error_log("Error in getStockById: " . $e->getMessage());
            Response::error('Failed to retrieve stock: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST /inventory/stock/adjust - ປັບປຸງສະຕ໋ອກ
     */
    public function adjustStock() {
        try {
            $currentUser = $this->getCurrentUser();
            $this->checkAdminPermission($currentUser);
            
            $data = json_decode(file_get_contents('php://input'), true);

            if (!isset($data['item_id']) || !isset($data['quantity'])) {
                Response::error('Item ID and quantity are required', 400);
                return;
            }

            $type = $data['type'] ?? ($data['quantity'] > 0 ? 'add' : 'subtract');
            $quantity = abs($data['quantity']);
            
            $reference = [
                'warehouse_id' => $data['warehouse_id'] ?? 1,
                'type' => $data['reference_type'] ?? 'adjustment',
                'id' => $data['reference_id'] ?? null,
                'notes' => $data['notes'] ?? null
            ];

            $result = $this->stockModel->adjustStock(
                $data['item_id'],
                $quantity,
                $type,
                $reference,
                $currentUser['id'] ?? null
            );

            if ($result['success']) {
                Response::success($result, $result['message']);
            } else {
                Response::error($result['message'], 400);
            }
        } catch (Exception $e) {
            error_log("Error in adjustStock: " . $e->getMessage());
            Response::error('Failed to adjust stock: ' . $e->getMessage(), 500);
        }
    }

    public function loanStock() {
        try {
            $currentUser = $this->getCurrentUser();
            if (!$currentUser) {
                Response::unauthorized('Authentication required');
                return;
            }

            $data = json_decode(file_get_contents('php://input'), true);
            if (!isset($data['item_id']) || !isset($data['quantity'])) {
                Response::error('Item ID and quantity are required', 400);
                return;
            }

            $quantity = abs($data['quantity']);
            if ($quantity <= 0) {
                Response::error('Quantity must be greater than zero', 400);
                return;
            }

            $warehouseId = $data['warehouse_id'] ?? 1;
            $stockRows = $this->stockModel->getStockByItemId($data['item_id'], $warehouseId);
            $stockRow = is_array($stockRows) && count($stockRows) ? $stockRows[0] : null;

            if (!$stockRow || $stockRow['current_quantity'] < $quantity) {
                Response::error('Insufficient stock for loan', 400);
                return;
            }

            $reference = [
                'warehouse_id' => $warehouseId,
                'type' => 'loan',
                'id' => $data['reference_id'] ?? null,
                'notes' => $data['notes'] ?? null
            ];

            $result = $this->stockModel->adjustStock(
                $data['item_id'],
                $quantity,
                'subtract',
                $reference,
                $currentUser['id']
            );

            if ($result['success']) {
                Response::success($result, 'Stock loaned successfully');
            } else {
                Response::error($result['message'], 400);
            }
        } catch (Exception $e) {
            error_log("Error in loanStock: " . $e->getMessage());
            Response::error('Failed to loan stock: ' . $e->getMessage(), 500);
        }
    }

    public function returnStock() {
        try {
            $currentUser = $this->getCurrentUser();
            if (!$currentUser) {
                Response::unauthorized('Authentication required');
                return;
            }

            $data = json_decode(file_get_contents('php://input'), true);
            if (!isset($data['item_id']) || !isset($data['quantity'])) {
                Response::error('Item ID and quantity are required', 400);
                return;
            }

            $quantity = abs($data['quantity']);
            if ($quantity <= 0) {
                Response::error('Quantity must be greater than zero', 400);
                return;
            }

            $warehouseId = $data['warehouse_id'] ?? 1;
            $reference = [
                'warehouse_id' => $warehouseId,
                'type' => 'return',
                'id' => $data['reference_id'] ?? null,
                'notes' => $data['notes'] ?? null
            ];

            $result = $this->stockModel->adjustStock(
                $data['item_id'],
                $quantity,
                'add',
                $reference,
                $currentUser['id']
            );

            if ($result['success']) {
                Response::success($result, 'Stock returned successfully');
            } else {
                Response::error($result['message'], 400);
            }
        } catch (Exception $e) {
            error_log("Error in returnStock: " . $e->getMessage());
            Response::error('Failed to return stock: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /inventory/stock/loan-history - ດຶງປະຫວັດການຢືມ-ຄືນສະຕ໋ອກ
     */
    public function getLoanHistory() {
        try {
            $currentUser = $this->getCurrentUser();
            if (!$currentUser) {
                Response::unauthorized('Authentication required');
                return;
            }

            $filters = [];
            
            // ກວດສອບ parameters ຈາກ query string
            if (!empty($_GET['user_id'])) {
                $filters['user_id'] = $_GET['user_id'];
            }
            
            if (!empty($_GET['item_id'])) {
                $filters['item_id'] = $_GET['item_id'];
            }
            
            if (!empty($_GET['start_date'])) {
                $filters['start_date'] = $_GET['start_date'];
            }
            
            if (!empty($_GET['end_date'])) {
                $filters['end_date'] = $_GET['end_date'];
            }
            
            if (!empty($_GET['movement_type'])) {
                $filters['movement_type'] = $_GET['movement_type'];
            }

            $history = $this->stockModel->getLoanHistory($filters);
            
            Response::success($history, 'Loan history retrieved successfully');
        } catch (Exception $e) {
            error_log("Error in getLoanHistory: " . $e->getMessage());
            Response::error('Failed to retrieve loan history: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST /inventory/stock/transfer - ໂອນສະຕ໋ອກ
     */
    public function transferStock() {
        try {
            $currentUser = $this->getCurrentUser();
            $this->checkAdminPermission($currentUser);
            
            $data = json_decode(file_get_contents('php://input'), true);

            $required = ['item_id', 'from_warehouse', 'to_warehouse', 'quantity'];
            foreach ($required as $field) {
                if (!isset($data[$field])) {
                    Response::error("{$field} is required", 400);
                    return;
                }
            }

            $result = $this->stockModel->transferStock(
                $data['item_id'],
                $data['from_warehouse'],
                $data['to_warehouse'],
                $data['quantity'],
                $data['notes'] ?? null,
                $currentUser['id'] ?? null
            );

            if ($result['success']) {
                Response::success(null, $result['message']);
            } else {
                Response::error($result['message'], 400);
            }
        } catch (Exception $e) {
            error_log("Error in transferStock: " . $e->getMessage());
            Response::error('Failed to transfer stock: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST /inventory/stock/count - ບັນທຶກການນັບສະຕ໋ອກ
     */
    public function recordStockCount() {
        try {
            $currentUser = $this->getCurrentUser();
            $this->checkAdminPermission($currentUser);
            
            $data = json_decode(file_get_contents('php://input'), true);

            if (!isset($data['item_id']) || !isset($data['actual_quantity'])) {
                Response::error('Item ID and actual quantity are required', 400);
                return;
            }

            $result = $this->stockModel->recordStockCount(
                $data['item_id'],
                $data['actual_quantity'],
                $data['notes'] ?? null,
                $currentUser['id'] ?? null
            );

            if ($result['success']) {
                Response::success([
                    'difference' => $result['difference']
                ], $result['message']);
            } else {
                Response::error($result['message'], 400);
            }
        } catch (Exception $e) {
            error_log("Error in recordStockCount: " . $e->getMessage());
            Response::error('Failed to record stock count: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST /inventory/stock/batch-count - ບັນທຶກການນັບຫຼາຍລາຍການ
     */
    public function recordBatchStockCount() {
        try {
            $currentUser = $this->getCurrentUser();
            $this->checkAdminPermission($currentUser);
            
            $data = json_decode(file_get_contents('php://input'), true);

            if (!isset($data['counts']) || !is_array($data['counts'])) {
                Response::error('Counts array is required', 400);
                return;
            }

            $result = $this->stockModel->recordBatchStockCount(
                $data['counts'],
                $currentUser['id'] ?? null
            );

            if ($result['success']) {
                Response::success([
                    'results' => $result['results']
                ], $result['message']);
            } else {
                Response::error($result['message'], 400);
            }
        } catch (Exception $e) {
            error_log("Error in recordBatchStockCount: " . $e->getMessage());
            Response::error('Failed to record batch stock count: ' . $e->getMessage(), 500);
        }
    }
}
?>