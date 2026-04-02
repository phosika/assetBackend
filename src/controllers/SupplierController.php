<?php
// /home/phosika/Fixasset/assetapplication/BACKEND/src/controllers/SupplierController.php

require_once __DIR__ . '/../models/Supplier.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../utils/Response.php';

class SupplierController {
    private $supplierModel;
    private $userModel;

    public function __construct() {
        $this->supplierModel = new Supplier();
        $this->userModel = new User();
    }

    private function getCurrentUser() {
        try {
            $payload = AuthMiddleware::authenticate();
            $userId = $payload['user_id'] ?? null;
            if (!$userId) {
                throw new Exception('Invalid token payload');
            }
            return $this->userModel->getById($userId);
        } catch (Exception $e) {
            return null;
        }
    }
 
  /**
     * GET /suppliers - ດຶງຂໍ້ມູນຜູ້ສະໜອງທັງໝົດ
     */
    public function getAllSuppliers() {
        try {
            $filters = [
                'search' => $_GET['search'] ?? '',
                'status' => isset($_GET['status']) ? (int)$_GET['status'] : null,
                'page' => isset($_GET['page']) ? (int)$_GET['page'] : 1,
                'limit' => isset($_GET['limit']) ? (int)$_GET['limit'] : 20
            ];

            error_log("Fetching suppliers with filters: " . json_encode($filters));
            
            $result = $this->supplierModel->getAllSuppliers($filters);
            
            error_log("Found " . count($result['data']) . " suppliers");
            
            Response::success([
                'suppliers' => $result['data'],
                'pagination' => [
                    'current_page' => $result['current_page'],
                    'per_page' => $result['per_page'],
                    'total' => $result['total'],
                    'total_pages' => $result['last_page']
                ]
            ], 'ດຶງຂໍ້ມູນຜູ້ສະໜອງສຳເລັດ');
            
        } catch (Exception $e) {
            error_log("Error in getAllSuppliers: " . $e->getMessage());
            Response::error('ດຶງຂໍ້ມູນບໍ່ສຳເລັດ: ' . $e->getMessage(), 500);
        }
    }


    /**
     * GET /suppliers/dropdown - ດຶງຂໍ້ມູນຜູ້ສະໜອງສຳລັບ dropdown
     */
    public function getSuppliersDropdown() {
        try {
            $activeOnly = !isset($_GET['all']) || $_GET['all'] !== 'true';
            $search = $_GET['search'] ?? '';
            
            error_log("Fetching suppliers dropdown - activeOnly: " . ($activeOnly ? 'true' : 'false') . ", search: " . $search);
            
            $suppliers = $this->supplierModel->getSuppliersForDropdown($activeOnly, $search);
            
            error_log("Found " . count($suppliers) . " suppliers");
            
            Response::success($suppliers, 'ດຶງຂໍ້ມູນຜູ້ສະໜອງສຳເລັດ');
            
        } catch (Exception $e) {
            error_log("Error in getSuppliersDropdown: " . $e->getMessage());
            Response::error('ດຶງຂໍ້ມູນຜູ້ສະໜອງບໍ່ສຳເລັດ: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /suppliers/{id} - ດຶງຂໍ້ມູນຜູ້ສະໜອງຕາມ ID
     */
    public function getSupplierById($id) {
        try {
            // TODO: Implement getSupplierById method
            Response::success(null, 'ດຶງຂໍ້ມູນສຳເລັດ');
        } catch (Exception $e) {
            Response::error('ດຶງຂໍ້ມູນບໍ່ສຳເລັດ: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST /suppliers - ສ້າງຜູ້ສະໜອງໃໝ່
     */
    public function createSupplier() {
        try {
            $user = $this->getCurrentUser();
            
            $data = json_decode(file_get_contents('php://input'), true);

            // ກວດສອບຂໍ້ມູນທີ່ຈຳເປັນ
            $required = ['supplier_code', 'supplier_name'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    Response::error("ກະລຸນາປ້ອນຂໍ້ມູນ {$field} ໃຫ້ຄົບ", 400);
                    return;
                }
            }

            // TODO: Implement create supplier logic
            
            Response::success(['id' => 1], 'ສ້າງຜູ້ສະໜອງສຳເລັດ', 201);

        } catch (Exception $e) {
            error_log("Error in createSupplier: " . $e->getMessage());
            Response::error('ສ້າງຜູ້ສະໜອງບໍ່ສຳເລັດ: ' . $e->getMessage(), 500);
        }
    }

    /**
     * PUT /suppliers/{id} - ອັບເດດຜູ້ສະໜອງ
     */
    public function updateSupplier($id) {
        try {
            // TODO: Implement updateSupplier method
            Response::success(null, 'ອັບເດດຜູ້ສະໜອງສຳເລັດ');
        } catch (Exception $e) {
            Response::error('ອັບເດດຂໍ້ມູນບໍ່ສຳເລັດ: ' . $e->getMessage(), 500);
        }
    }

    /**
     * DELETE /suppliers/{id} - ລຶບຜູ້ສະໜອງ
     */
    public function deleteSupplier($id) {
        try {
            // TODO: Implement deleteSupplier method
            Response::success(null, 'ລຶບຜູ້ສະໜອງສຳເລັດ');
        } catch (Exception $e) {
            Response::error('ລຶບຂໍ້ມູນບໍ່ສຳເລັດ: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /suppliers/search - ຄົ້ນຫາຜູ້ສະໜອງ
     */
    public function searchSuppliers() {
        try {
            // TODO: Implement searchSuppliers method
            Response::success([], 'ຄົ້ນຫາຜູ້ສະໜອງສຳເລັດ');
        } catch (Exception $e) {
            Response::error('ຄົ້ນຫາຂໍ້ມູນບໍ່ສຳເລັດ: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /suppliers/stats - ດຶງສະຖິຕິຜູ້ສະໜອງ
     */
    public function getSupplierStats() {
        try {
            // TODO: Implement getSupplierStats method
            Response::success([
                'total_suppliers' => 0,
                'active_suppliers' => 0,
                'inactive_suppliers' => 0
            ], 'ດຶງສະຖິຕິສຳເລັດ');
        } catch (Exception $e) {
            Response::error('ດຶງສະຖິຕິບໍ່ສຳເລັດ: ' . $e->getMessage(), 500);
        }
    }

    /**
     * PATCH /suppliers/{id}/status - ປ່ຽນສະຖານະຜູ້ສະໜອງ
     */
    public function toggleSupplierStatus($id) {
        try {
            // TODO: Implement toggleSupplierStatus method
            Response::success(null, 'ປ່ຽນສະຖານະສຳເລັດ');
        } catch (Exception $e) {
            Response::error('ປ່ຽນສະຖານະບໍ່ສຳເລັດ: ' . $e->getMessage(), 500);
        }
    }
}
?>