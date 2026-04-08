<?php
// /home/phosika/Fixasset/assetapplication/BACKEND/src/controllers/SalesController.php

require_once __DIR__ . '/../models/SalesOrder.php';
require_once __DIR__ . '/../models/Customer.php';
require_once __DIR__ . '/../models/InventoryItem.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../utils/Response.php';

class SalesController {
    private $salesModel;
    private $customerModel;
    private $itemModel;
    private $userModel;

    public function __construct() {
        $this->salesModel = new SalesOrder();
        $this->customerModel = new Customer();
        $this->itemModel = new InventoryItem();
        $this->userModel = new User();
    }

    private function getCurrentUser() {
        try {
            $payload = AuthMiddleware::authenticate();
            $userId = $payload['user_id'] ?? $payload['id'] ?? null;
            if (!$userId) {
                return null;
            }
            return $this->userModel->getById($userId);
        } catch (Exception $e) {
            return null;
        }
    }

    private function checkPermission($user, $allowedRoles = ['super_admin', 'asset_admin', 'sales_manager', 'branch_manager']) {
        if (!$user || !in_array($user['role'] ?? '', $allowedRoles)) {
            Response::forbidden('ທ່ານບໍ່ມີສິດດຳເນີນການນີ້');
        }
    }

    /**
     * ດຶງຂໍ້ມູນບໍລິສັດ ແລະ ສາຂາຂອງຜູ້ໃຊ້
     */
    private function getUserCompanyAndBranch($user) {
        $companyId = null;
        $branchId = null;
        
        switch ($user['role']) {
            case 'super_admin':
                // ເຫັນທຸກຢ່າງ
                break;
                
            case 'company_manager':
            case 'asset_admin':
            case 'sales_manager':
                // ເຫັນສະເພາະຂໍ້ມູນຂອງບໍລິສັດຕົນເອງ
                $companyId = $user['company_id'] ?? null;
                break;
                
            case 'branch_manager':
                // ເຫັນສະເພາະຂໍ້ມູນຂອງສາຂາຕົນເອງ
                $branchId = $user['branch_id'] ?? null;
                $companyId = $user['company_id'] ?? null;
                break;
                
            default:
                // ພະນັກງານທົ່ວໄປ ເຫັນສະເພາະສາຂາຕົນເອງ
                $branchId = $user['branch_id'] ?? null;
        }
        
        return [$companyId, $branchId];
    }

    /**
     * GET /sales - ດຶງຂໍ້ມູນໃບຂາຍທັງໝົດ
     */
    // public function getAllSalesOrders() {
    //     try {
    //         $user = $this->getCurrentUser();
    //         list($companyId, $branchId) = $this->getUserCompanyAndBranch($user);
            
    //         $filters = [
    //             'search' => $_GET['search'] ?? '',
    //             'status' => $_GET['status'] ?? '',
    //             'payment_status' => $_GET['payment_status'] ?? '',
    //             'customer_id' => isset($_GET['customer_id']) ? (int)$_GET['customer_id'] : null,
    //             'from_date' => $_GET['from_date'] ?? null,
    //             'to_date' => $_GET['to_date'] ?? null,
    //             'page' => isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1,
    //             'limit' => isset($_GET['limit']) ? min(100, (int)$_GET['limit']) : 20,
    //             'sort_by' => $_GET['sort_by'] ?? 'so.created_at',
    //             'sort_order' => $_GET['sort_order'] ?? 'DESC'
    //         ];

    //         $result = $this->salesModel->getAllSalesOrders($filters, $companyId, $branchId);
            
    //         // ເພີ່ມ total_items ໃນແຕ່ລະລາຍການ
    //         foreach ($result['data'] as &$order) {
    //             $order['total_items'] = isset($order['items']) ? count($order['items']) : 0;
    //         }

    //         Response::success([
    //             'sales_orders' => $result['data'],
    //             'pagination' => [
    //                 'current_page' => $result['current_page'],
    //                 'per_page' => $result['per_page'],
    //                 'total' => $result['total'],
    //                 'total_pages' => $result['last_page']
    //             ]
    //         ], 'ດຶງຂໍ້ມູນໃບຂາຍສຳເລັດ');

    //     } catch (Exception $e) {
    //         error_log("Error in getAllSalesOrders: " . $e->getMessage());
    //         Response::error('ດຶງຂໍ້ມູນບໍ່ສຳເລັດ: ' . $e->getMessage(), 500);
    //     }
    // }


    public function getAllSalesOrders() {
        try {
            $user = $this->getCurrentUser();
            list($companyId, $branchId) = $this->getUserCompanyAndBranch($user);
            
            $filters = [
                'search' => $_GET['search'] ?? '',
                'status' => $_GET['status'] ?? '',
                'payment_status' => $_GET['payment_status'] ?? '',
                'customer_id' => isset($_GET['customer_id']) ? (int)$_GET['customer_id'] : null,
                'from_date' => $_GET['from_date'] ?? null,
                'to_date' => $_GET['to_date'] ?? null,
                'page' => isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1,
                'limit' => isset($_GET['limit']) ? min(100, (int)$_GET['limit']) : 20,
                'sort_by' => $_GET['sort_by'] ?? 'so.created_at',
                'sort_order' => $_GET['sort_order'] ?? 'DESC'
            ];

            error_log("Search filters: " . json_encode($filters));

            $result = $this->salesModel->getAllSalesOrders($filters, $companyId, $branchId);

            // ໃຫ້ແນ່ໃຈວ່າ $result ມີຂໍ້ມູນຄົບ
            Response::success([
                'sales_orders' => $result['data'] ?? [],
                'pagination' => [
                    'current_page' => $result['current_page'] ?? 1,
                    'per_page' => $result['per_page'] ?? 20,
                    'total' => $result['total'] ?? 0,
                    'total_pages' => $result['last_page'] ?? 1
                ]
            ], 'ດຶງຂໍ້ມູນໃບຂາຍສຳເລັດ');

        } catch (Exception $e) {
            error_log("Error in getAllSalesOrders: " . $e->getMessage());
            Response::error('ດຶງຂໍ້ມູນບໍ່ສຳເລັດ: ' . $e->getMessage(), 500);
        }
    }


    /**
     * GET /sales/stats - ດຶງສະຖິຕິການຂາຍ
     */
    public function getSalesStats() {
        try {
            $user = $this->getCurrentUser();
            list($companyId, $branchId) = $this->getUserCompanyAndBranch($user);

            $stats = $this->salesModel->getSalesStats($companyId, $branchId);
            Response::success($stats, 'ດຶງສະຖິຕິສຳເລັດ');

        } catch (Exception $e) {
            error_log("Error in getSalesStats: " . $e->getMessage());
            Response::error('ດຶງສະຖິຕິບໍ່ສຳເລັດ: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /sales/{id} - ດຶງຂໍ້ມູນໃບຂາຍຕາມ ID
     */
    public function getSalesOrderById($id) {
        try {
            $user = $this->getCurrentUser();

            if (!is_numeric($id)) {
                Response::error('ID ບໍ່ຖືກຕ້ອງ', 400);
                return;
            }

            $salesOrder = $this->salesModel->getSalesOrderById($id);

            if (!$salesOrder) {
                Response::notFound('ບໍ່ພົບໃບຂາຍ');
                return;
            }

            // ກວດສອບສິດການເຂົ້າເຖິງ
            list($companyId, $branchId) = $this->getUserCompanyAndBranch($user);
            
            if ($companyId && $salesOrder['company_id'] != $companyId) {
                Response::forbidden('ທ່ານບໍ່ມີສິດເຂົ້າເຖິງຂໍ້ມູນນີ້');
                return;
            }

            if ($branchId && $salesOrder['branch_id'] != $branchId) {
                Response::forbidden('ທ່ານບໍ່ມີສິດເຂົ້າເຖິງຂໍ້ມູນນີ້');
                return;
            }

            Response::success($salesOrder, 'ດຶງຂໍ້ມູນສຳເລັດ');

        } catch (Exception $e) {
            error_log("Error in getSalesOrderById: " . $e->getMessage());
            Response::error('ດຶງຂໍ້ມູນບໍ່ສຳເລັດ: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /sales/customers - ດຶງຂໍ້ມູນລູກຄ້າສຳລັບ dropdown
     */
    public function getCustomers() {
        try {
            $user = $this->getCurrentUser();
            list($companyId, $branchId) = $this->getUserCompanyAndBranch($user);
            
            $search = $_GET['search'] ?? '';
            $customers = $this->customerModel->getCustomersForDropdown($companyId, $search);
            
            Response::success($customers, 'ດຶງຂໍ້ມູນສຳເລັດ');

        } catch (Exception $e) {
            error_log("Error in getCustomers: " . $e->getMessage());
            Response::error('ດຶງຂໍ້ມູນບໍ່ສຳເລັດ: ' . $e->getMessage(), 500);
        }
    }

 
    /**
     * GET /sales/items - ດຶງລາຍການສິນຄ້າສຳລັບ dropdown
     */
    public function getSalesItems() {
        try {
            require_once __DIR__ . '/../models/InventoryItem.php';
            $itemModel = new InventoryItem();
            
            $search = $_GET['search'] ?? '';
            
            // ໃຊ້ getItemsWithStockForSale ຖ້າຕ້ອງການກວດສອບ stock
            // $items = $itemModel->getItemsWithStockForSale($search);
            
            // ຫຼື ໃຊ້ getItemsForDropdown ຖ້າບໍ່ຕ້ອງການກວດສອບ stock
            $items = $itemModel->getItemsForDropdown($search);
            
            Response::success($items, 'ດຶງຂໍ້ມູນສຳເລັດ');

        } catch (Exception $e) {
            error_log("Error in getSalesItems: " . $e->getMessage());
            Response::error('ດຶງຂໍ້ມູນບໍ່ສຳເລັດ: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST /sales - ສ້າງໃບຂາຍໃໝ່
     */
    // public function createSalesOrder() {
    //     try {
    //         $user = $this->getCurrentUser();
    //         $this->checkPermission($user);

    //         $data = json_decode(file_get_contents('php://input'), true);

    //         // ກວດສອບຂໍ້ມູນທີ່ຈຳເປັນ
    //         $required = ['so_number', 'customer_id', 'sale_date', 'items'];
    //         foreach ($required as $field) {
    //             if (empty($data[$field])) {
    //                 Response::error("ກະລຸນາປ້ອນຂໍ້ມູນ {$field} ໃຫ້ຄົບ", 400);
    //                 return;
    //             }
    //         }

    //         if (!is_array($data['items']) || count($data['items']) === 0) {
    //             Response::error('ກະລຸນາເພີ່ມລາຍການສິນຄ້າຢ່າງໜ້ອຍ 1 ລາຍການ', 400);
    //             return;
    //         }

    //         // ກຳນົດ company_id ແລະ branch_id ຕາມບົດບາດ
    //         $companyId = $user['company_id'] ?? null;
    //         $branchId = $user['branch_id'] ?? null;

    //         // ຖ້າເປັນ super_admin ສາມາດເລືອກໄດ້
    //         if ($user['role'] === 'super_admin') {
    //             $companyId = $data['company_id'] ?? $companyId;
    //             $branchId = $data['branch_id'] ?? $branchId;
    //         }

    //         $result = $this->salesModel->createSalesOrder($data, $user['id'] ?? null, $companyId, $branchId);

    //         if ($result['success']) {
    //             Response::success([
    //                 'id' => $result['id'],
    //                 'so_number' => $result['so_number']
    //             ], $result['message'], 201);
    //         } else {
    //             Response::error($result['message'], 400);
    //         }

    //     } catch (Exception $e) {
    //         error_log("Error in createSalesOrder: " . $e->getMessage());
    //         Response::error('ສ້າງໃບຂາຍບໍ່ສຳເລັດ: ' . $e->getMessage(), 500);
    //     }
    // }

    /**
     * ສ້າງໃບຂາຍໃໝ່
     */
    public function createSalesOrder() {
        try {
            // ກວດສອບ authentication
            $payload = AuthMiddleware::authenticate();
            $userId = $payload['user_id'] ?? $payload['id'] ?? null;
            
            if (!$userId) {
                Response::error('Unauthorized', 401);
                return;
            }
            
            // ດຶງຂໍ້ມູນຜູ້ໃຊ້ເພື່ອເອົາ company_id ແລະ branch_id
            $db = Database::getInstance();
            $stmt = $db->prepare("SELECT company_id, branch_id, role FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                Response::error('User not found', 404);
                return;
            }
            
            $companyId = $user['company_id'] ?? null;
            $branchId = $user['branch_id'] ?? null;
            
            // ຖ້າເປັນ super_admin ຫຼື admin, ສາມາດເລືອກຈາກ request ໄດ້
            if ($user['role'] === 'super_admin' || $user['role'] === 'asset_admin') {
                $input = json_decode(file_get_contents('php://input'), true);
                $companyId = $input['company_id'] ?? $companyId;
                $branchId = $input['branch_id'] ?? $branchId;
            } else {
                // ຖ້າບໍ່ແມ່ນ admin, ຮັບຂໍ້ມູນປົກກະຕິ
                $input = json_decode(file_get_contents('php://input'), true);
            }
            
            if (!$companyId) {
                Response::error('No company associated with this user. Please contact administrator.', 400);
                return;
            }
            
            if (!$input) {
                Response::error('Invalid input data', 400);
                return;
            }
            
            // ກວດສອບຂໍ້ມູນທີ່ຈຳເປັນ
            if (empty($input['so_number'])) {
                // ຖ້າບໍ່ມີເລກທີ່, ສ້າງອັດຕະໂນມັດ
                $input['so_number'] = $this->generateSalesOrderNumber();
            }
            
            // ກວດສອບວ່າເລກທີ່ຖືກຕ້ອງຕາມຮູບແບບ
            if (!preg_match('/^SA-\d{4}-\d{3}$/', $input['so_number'])) {
                Response::error('Invalid sales order number format. Expected: SA-YYYY-XXX', 400);
                return;
            }
            
            // ເພີ່ມ company_id ແລະ branch_id ເຂົ້າໄປໃນຂໍ້ມູນ
            $input['company_id'] = $companyId;
            $input['branch_id'] = $branchId;
            
            error_log("Creating sales order with company_id: {$companyId}, branch_id: {$branchId}");
            
            // ສ້າງໃບຂາຍ
            $salesOrder = new SalesOrder();
            $result = $salesOrder->createSalesOrder($input, $userId, $companyId, $branchId);
            
            if ($result['success']) {
                Response::success([
                    'id' => $result['id'],
                    'so_number' => $result['so_number']
                ], $result['message'], 201);
            } else {
                Response::error($result['message'], 400);
            }
            
        } catch (Exception $e) {
            error_log("Error in createSalesOrder: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            Response::error('Server error: ' . $e->getMessage(), 500);
        }
    }

    /**
     * ສ້າງເລກທີ່ໃບຂາຍອັດຕະໂນມັດ
     */
    private function generateSalesOrderNumber() {
        $year = date('Y');
        $prefix = 'SA';
        
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM sales_orders WHERE so_number LIKE ? AND YEAR(created_at) = ?");
        $stmt->execute(["SA-{$year}-%", $year]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $count = ($result ? (int)$result['count'] : 0) + 1;
        
        return $prefix . '-' . $year . '-' . str_pad($count, 3, '0', STR_PAD_LEFT);
    }

    /**
     * PATCH /sales/{id}/payment - ອັບເດດສະຖານະການຊຳລະ
     */
    public function updatePaymentStatus($id) {
        try {
            $user = $this->getCurrentUser();
            $this->checkPermission($user);

            if (!is_numeric($id)) {
                Response::error('ID ບໍ່ຖືກຕ້ອງ', 400);
                return;
            }

            // ກວດສອບວ່າມີສິດແກ້ໄຂບໍ
            $salesOrder = $this->salesModel->getSalesOrderById($id);
            if (!$salesOrder) {
                Response::notFound('ບໍ່ພົບໃບຂາຍ');
                return;
            }

            list($companyId, $branchId) = $this->getUserCompanyAndBranch($user);
            
            if ($companyId && $salesOrder['company_id'] != $companyId) {
                Response::forbidden('ທ່ານບໍ່ມີສິດແກ້ໄຂຂໍ້ມູນນີ້');
                return;
            }

            $data = json_decode(file_get_contents('php://input'), true);

            if (empty($data['payment_status'])) {
                Response::error('ກະລຸນາລະບຸສະຖານະການຊຳລະ', 400);
                return;
            }

            $allowedStatus = ['unpaid', 'partial', 'paid'];
            if (!in_array($data['payment_status'], $allowedStatus)) {
                Response::error('ສະຖານະການຊຳລະບໍ່ຖືກຕ້ອງ', 400);
                return;
            }

            $paymentDate = $data['payment_status'] === 'paid' ? date('Y-m-d H:i:s') : null;

            $result = $this->salesModel->updatePaymentStatus(
                $id,
                $data['payment_status'],
                $data['payment_method'] ?? null,
                $paymentDate
            );

            if ($result['success']) {
                Response::success(null, $result['message']);
            } else {
                Response::error($result['message'], 400);
            }

        } catch (Exception $e) {
            error_log("Error in updatePaymentStatus: " . $e->getMessage());
            Response::error('ອັບເດດສະຖານະການຊຳລະບໍ່ສຳເລັດ: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST /sales/{id}/status - ອັບເດດສະຖານະໃບຂາຍ
     */
    public function updateStatus($id) {
        try {

            error_log("=== updateStatus called ===");
            error_log("ID received: " . $id);
            $user = $this->getCurrentUser();
            $this->checkPermission($user, ['super_admin', 'asset_admin', 'sales_manager']);

            if (!is_numeric($id)) {
                Response::error('ID ບໍ່ຖືກຕ້ອງ', 400);
                return;
            }

            // ກວດສອບວ່າໃບຂາຍມີຢູ່ບໍ
            $salesOrder = $this->salesModel->getSalesOrderById($id);
            
            error_log("Sales order found: " . json_encode($salesOrder));

            // ກວດສອບວ່າໃບຂາຍມີຢູ່ບໍ
            $salesOrder = $this->salesModel->getSalesOrderById($id);
            
            if (!$salesOrder) {
                error_log("Sales order not found with ID: " . $id);
                Response::notFound('ບໍ່ພົບໃບຂາຍ');
                return;
            }

            error_log("Found sales order: " . json_encode($salesOrder));

            $data = json_decode(file_get_contents('php://input'), true);
            error_log("Update status data: " . json_encode($data));

            if (empty($data['status'])) {
                Response::error('ກະລຸນາລະບຸສະຖານະ', 400);
                return;
            }

            $allowedStatus = ['pending', 'confirmed', 'shipped', 'delivered', 'cancelled'];
            if (!in_array($data['status'], $allowedStatus)) {
                Response::error('ສະຖານະບໍ່ຖືກຕ້ອງ', 400);
                return;
            }

            $result = $this->salesModel->updateStatus(
                $id,
                $data['status'],
                $user['id'] ?? null
            );

            if ($result['success']) {
                Response::success(null, $result['message']);
            } else {
                Response::error($result['message'], 400);
            }

        } catch (Exception $e) {
            error_log("Error in updateStatus: " . $e->getMessage());
            Response::error('ອັບເດດສະຖານະບໍ່ສຳເລັດ: ' . $e->getMessage(), 500);
        }
    }

 
 
    /**
     * ອັບເດດສະຖານະການ sync asset
     */
    public function updateSyncAssetStatus($id) {
        try {
            // ກວດສອບ authentication
            $payload = AuthMiddleware::authenticate();
            $userId = $payload['user_id'] ?? $payload['id'] ?? null;
            
            if (!$userId) {
                Response::error('Unauthorized', 401);
                return;
            }
            
            // ຮັບຂໍ້ມູນ
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!$input) {
                Response::error('Invalid input data', 400);
                return;
            }
            
            $syncedToAsset = isset($input['synced_to_asset']) ? (int)$input['synced_to_asset'] : 1;
            $syncedAt = isset($input['synced_at']) ? $input['synced_at'] : date('Y-m-d H:i:s');
            
            // ກວດສອບຮູບແບບວັນທີ
            $dateTime = DateTime::createFromFormat('Y-m-d H:i:s', $syncedAt);
            if (!$dateTime) {
                // ຖ້າຮູບແບບບໍ່ຖືກ, ໃຊ້ວັນທີປັດຈຸບັນ
                $syncedAt = date('Y-m-d H:i:s');
                error_log("Invalid date format, using current time: $syncedAt");
            }
            
            error_log("Updating sync status for sales order $id: synced_to_asset=$syncedToAsset, synced_at=$syncedAt");
            
            // ອັບເດດຂໍ້ມູນ
            $salesOrder = new SalesOrder();
            $result = $salesOrder->updateSyncAssetStatus($id, $syncedToAsset, $syncedAt);
            
            if ($result['success']) {
                Response::success(null, 200, $result['message']);
            } else {
                Response::error($result['message'], 400);
            }
            
        } catch (Exception $e) {
            error_log("Error in updateSyncAssetStatus: " . $e->getMessage());
            Response::error('Server error: ' . $e->getMessage(), 500);
        }
    }

}
?>