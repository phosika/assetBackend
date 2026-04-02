<?php

require_once __DIR__ . '/../models/Warehouse.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../utils/Response.php';

class WarehouseController {
    private $warehouseModel;
    private $userModel;

    public function __construct() {
        $this->warehouseModel = new Warehouse();
        $this->userModel = new User();
    }

    /**
     * ດຶງຂໍ້ມູນຜູ້ໃຊ້ປັດຈຸບັນ
     */
    private function getCurrentUser() {
        try {
            $payload = AuthMiddleware::authenticate();
            if (!$payload) {
                return null;
            }
            return $this->userModel->getById($payload['user_id']);
        } catch (Exception $e) {
            error_log("Auth error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * ກວດສອບສິດຜູ້ເບິ່ງແຍງລະບົບ
     */
    private function checkAdminPermission($user) {
        if (!$user || !in_array($user['role'] ?? '', ['super_admin', 'asset_admin', 'inventory_manager'])) {
            Response::forbidden('ທ່ານບໍ່ມີສິດດຳເນີນການນີ້');
        }
    }

    /**
     * GET /warehouses - ດຶງຂໍ້ມູນສາງທັງໝົດ
     */
    public function getAllWarehouses() {
        try {
            $currentUser = $this->getCurrentUser();
            
            $filters = [
                'search' => $_GET['search'] ?? '',
                'is_active' => isset($_GET['is_active']) && $_GET['is_active'] !== '' ? (int)$_GET['is_active'] : null,
                'manager_id' => isset($_GET['manager_id']) ? (int)$_GET['manager_id'] : null,
                'page' => isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1,
                'limit' => isset($_GET['limit']) ? min(100, (int)$_GET['limit']) : 20,
                'sort_by' => $_GET['sort_by'] ?? 'w.warehouse_code',
                'sort_order' => $_GET['sort_order'] ?? 'ASC'
            ];

            error_log("Fetching warehouses with filters: " . json_encode($filters));
            
            $result = $this->warehouseModel->getAllWarehouses($filters);
            
            Response::success([
                'warehouses' => $result['data'],
                'pagination' => [
                    'current_page' => $result['current_page'],
                    'per_page' => $result['per_page'],
                    'total' => $result['total'],
                    'total_pages' => $result['last_page']
                ]
            ], 'ດຶງຂໍ້ມູນສາງສຳເລັດ');
            
        } catch (Exception $e) {
            error_log("Error in getAllWarehouses: " . $e->getMessage());
            Response::error('ດຶງຂໍ້ມູນສາງບໍ່ສຳເລັດ: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /warehouses/dropdown - ດຶງຂໍ້ມູນສາງສຳລັບ dropdown
     */
    public function getWarehousesForDropdown() {
        try {
            $activeOnly = !isset($_GET['all']) || $_GET['all'] !== 'true';
            $warehouses = $this->warehouseModel->getWarehousesForDropdown($activeOnly);
            
            Response::success($warehouses, 'ດຶງຂໍ້ມູນສາງສຳເລັດ');
            
        } catch (Exception $e) {
            error_log("Error in getWarehousesForDropdown: " . $e->getMessage());
            Response::error('ດຶງຂໍ້ມູນສາງບໍ່ສຳເລັດ: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /warehouses/stats - ດຶງສະຖິຕິສາງ
     */
    public function getWarehouseStats() {
        try {
            $stats = $this->warehouseModel->getWarehouseStats();
            Response::success($stats, 'ດຶງສະຖິຕິສາງສຳເລັດ');
            
        } catch (Exception $e) {
            error_log("Error in getWarehouseStats: " . $e->getMessage());
            Response::error('ດຶງສະຖິຕິສາງບໍ່ສຳເລັດ: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /warehouses/{id} - ດຶງຂໍ້ມູນສາງຕາມ ID
     */
    public function getWarehouseById($id) {
        try {
            if (!is_numeric($id)) {
                Response::error('ID ບໍ່ຖືກຕ້ອງ', 400);
                return;
            }

            $warehouse = $this->warehouseModel->getWarehouseById($id);

            if (!$warehouse) {
                Response::notFound('ບໍ່ພົບຂໍ້ມູນສາງ');
                return;
            }

            Response::success($warehouse, 'ດຶງຂໍ້ມູນສາງສຳເລັດ');
            
        } catch (Exception $e) {
            error_log("Error in getWarehouseById: " . $e->getMessage());
            Response::error('ດຶງຂໍ້ມູນສາງບໍ່ສຳເລັດ: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /warehouses/by-code/{code} - ດຶງຂໍ້ມູນສາງຕາມລະຫັດ
     */
    public function getWarehouseByCode($code) {
        try {
            if (empty($code)) {
                Response::error('ລະຫັດສາງບໍ່ຖືກຕ້ອງ', 400);
                return;
            }

            $warehouse = $this->warehouseModel->getWarehouseByCode($code);

            if (!$warehouse) {
                Response::notFound('ບໍ່ພົບຂໍ້ມູນສາງ');
                return;
            }

            Response::success($warehouse, 'ດຶງຂໍ້ມູນສາງສຳເລັດ');
            
        } catch (Exception $e) {
            error_log("Error in getWarehouseByCode: " . $e->getMessage());
            Response::error('ດຶງຂໍ້ມູນສາງບໍ່ສຳເລັດ: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST /warehouses - ສ້າງສາງໃໝ່
     */
    public function createWarehouse() {
        try {
            $currentUser = $this->getCurrentUser();
            $this->checkAdminPermission($currentUser);
            
            $data = json_decode(file_get_contents('php://input'), true);

            // ກວດສອບຂໍ້ມູນທີ່ຈຳເປັນ
            $required = ['warehouse_code', 'warehouse_name'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    Response::error("ກະລຸນາປ້ອນຂໍ້ມູນ {$field} ໃຫ້ຄົບ", 400);
                    return;
                }
            }

            $result = $this->warehouseModel->createWarehouse($data, $currentUser['id'] ?? null);

            if ($result['success']) {
                Response::success(['id' => $result['id']], $result['message'], 201);
            } else {
                Response::error($result['message'], 400);
            }
            
        } catch (Exception $e) {
            error_log("Error in createWarehouse: " . $e->getMessage());
            Response::error('ສ້າງສາງບໍ່ສຳເລັດ: ' . $e->getMessage(), 500);
        }
    }

    /**
     * PUT /warehouses/{id} - ອັບເດດຂໍ້ມູນສາງ
     */
    public function updateWarehouse($id) {
        try {
            $currentUser = $this->getCurrentUser();
            $this->checkAdminPermission($currentUser);
            
            if (!is_numeric($id)) {
                Response::error('ID ບໍ່ຖືກຕ້ອງ', 400);
                return;
            }

            $data = json_decode(file_get_contents('php://input'), true);

            $result = $this->warehouseModel->updateWarehouse($id, $data, $currentUser['id'] ?? null);

            if ($result['success']) {
                Response::success(null, $result['message']);
            } else {
                Response::error($result['message'], 400);
            }
            
        } catch (Exception $e) {
            error_log("Error in updateWarehouse: " . $e->getMessage());
            Response::error('ອັບເດດຂໍ້ມູນສາງບໍ່ສຳເລັດ: ' . $e->getMessage(), 500);
        }
    }

    /**
     * DELETE /warehouses/{id} - ລຶບສາງ (soft delete)
     */
    public function deleteWarehouse($id) {
        try {
            $currentUser = $this->getCurrentUser();
            $this->checkAdminPermission($currentUser);
            
            if (!is_numeric($id)) {
                Response::error('ID ບໍ່ຖືກຕ້ອງ', 400);
                return;
            }

            $result = $this->warehouseModel->deleteWarehouse($id, $currentUser['id'] ?? null);

            if ($result['success']) {
                Response::success(null, $result['message']);
            } else {
                Response::error($result['message'], 400);
            }
            
        } catch (Exception $e) {
            error_log("Error in deleteWarehouse: " . $e->getMessage());
            Response::error('ລຶບສາງບໍ່ສຳເລັດ: ' . $e->getMessage(), 500);
        }
    }

    /**
     * DELETE /warehouses/{id}/permanent - ລຶບສາງແບບຖາວອນ
     */
    public function permanentDeleteWarehouse($id) {
        try {
            $currentUser = $this->getCurrentUser();
            $this->checkAdminPermission($currentUser);
            
            if (!is_numeric($id)) {
                Response::error('ID ບໍ່ຖືກຕ້ອງ', 400);
                return;
            }

            $result = $this->warehouseModel->permanentDeleteWarehouse($id);

            if ($result['success']) {
                Response::success(null, $result['message']);
            } else {
                Response::error($result['message'], 400);
            }
            
        } catch (Exception $e) {
            error_log("Error in permanentDeleteWarehouse: " . $e->getMessage());
            Response::error('ລຶບສາງບໍ່ສຳເລັດ: ' . $e->getMessage(), 500);
        }
    }

 

    /**
     * POST/PATCH /warehouses/{id}/toggle-status - ປ່ຽນສະຖານະສາງ
     * ຮັບທັງ POST ແລະ PATCH
     */
    public function toggleWarehouseStatus($id) {
        try {
            $currentUser = $this->getCurrentUser();
            $this->checkAdminPermission($currentUser);
            
            if (!is_numeric($id)) {
                Response::error('ID ບໍ່ຖືກຕ້ອງ', 400);
                return;
            }

            error_log("========== TOGGLE WAREHOUSE STATUS ==========");
            error_log("Method: " . $_SERVER['REQUEST_METHOD']);
            error_log("ID: " . $id);
            
            // ຮັບຂໍ້ມູນຈາກ request
            $input = file_get_contents('php://input');
            error_log("Raw input: " . $input);
            
            $data = json_decode($input, true);
            error_log("Decoded data: " . json_encode($data));
            
            // ກວດສອບວ່າມີ is_active ບໍ
            if (!isset($data['is_active'])) {
                Response::error('ກະລຸນາລະບຸສະຖານະ is_active', 400);
                return;
            }

            $isActive = (int)$data['is_active'];
            error_log("Setting is_active to: " . $isActive);

            $result = $this->warehouseModel->toggleStatus($id, $isActive, $currentUser['id'] ?? null);

            error_log("Toggle result: " . json_encode($result));
            error_log("==============================================");

            if ($result['success']) {
                Response::success(null, $result['message']);
            } else {
                Response::error($result['message'], 400);
            }
            
        } catch (Exception $e) {
            error_log("Error in toggleWarehouseStatus: " . $e->getMessage());
            Response::error('ປ່ຽນສະຖານະສາງບໍ່ສຳເລັດ: ' . $e->getMessage(), 500);
        }
    }
}
?>