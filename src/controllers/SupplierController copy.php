<?php
// src/controllers/SupplierController.php

require_once __DIR__ . '/../models/Supplier.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Validator.php';

class SupplierController {
    private $supplierModel;
    private $userModel;
    private $validator;

    public function __construct() {
        $this->supplierModel = new Supplier();
        $this->userModel = new User();
        $this->validator = new Validator();
    }

    /**
     * ດຶງຂໍ້ມູນຜູ້ໃຊ້ປັດຈຸບັນ
     */
    private function getCurrentUser() {
        $userId = AuthMiddleware::authenticate();
        $user = $this->userModel->getById($userId);
        
        if (!$user) {
            Response::unauthorized('User not found');
        }
        
        return $user;
    }

    /**
     * ກວດສອບສິດ admin
     */
    private function checkAdminPermission($user) {
        if (!in_array($user['role'], ['super_admin', 'asset_admin'])) {
            Response::forbidden('You do not have permission to perform this action');
        }
    }

    /**
     * GET /suppliers - ດຶງຂໍ້ມູນຜູ້ສະໜອງທັງໝົດ
     */
    public function getAllSuppliers() {
        try {
            $currentUser = $this->getCurrentUser();
            
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
            $search = isset($_GET['search']) ? $_GET['search'] : '';
            $supplier_code = isset($_GET['supplier_code']) ? $_GET['supplier_code'] : '';
            $supplier_name = isset($_GET['supplier_name']) ? $_GET['supplier_name'] : '';
            $status = isset($_GET['status']) ? $_GET['status'] : '';
            $phone = isset($_GET['phone']) ? $_GET['phone'] : '';
            $email = isset($_GET['email']) ? $_GET['email'] : '';
            $sort_by = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'created_at';
            $sort_order = isset($_GET['sort_order']) ? strtoupper($_GET['sort_order']) : 'DESC';

            $filters = [
                'search' => $search,
                'supplier_code' => $supplier_code,
                'supplier_name' => $supplier_name,
                'status' => $status,
                'phone' => $phone,
                'email' => $email,
                'page' => $page,
                'limit' => $limit,
                'sort_by' => $sort_by,
                'sort_order' => $sort_order
            ];

            $result = $this->supplierModel->getAllSuppliers($filters);

            Response::success([
                'suppliers' => $result['data'],
                'pagination' => [
                    'current_page' => $result['current_page'],
                    'per_page' => $result['per_page'],
                    'total' => $result['total'],
                    'total_pages' => $result['last_page'],
                    'from' => $result['from'],
                    'to' => $result['to']
                ],
                'filters' => $filters
            ], 'Suppliers retrieved successfully');
        } catch (Exception $e) {
            error_log("Error in getAllSuppliers: " . $e->getMessage());
            Response::error('Failed to retrieve suppliers: ' . $e->getMessage(), 500);
        }
    }

        /**
     * GET /suppliers/dropdown - ດຶງຂໍ້ມູນຜູ້ສະໜອງສຳລັບ dropdown
     */
        public function getSuppliersDropdown() {
            try {
                // ບໍ່ຕ້ອງການ authentication ສຳລັບ dropdown
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
    }

    /**
     * GET /suppliers/dropdown - ດຶງຂໍ້ມູນຜູ້ສະໜອງສຳລັບ dropdown
     */
    public function getSuppliersDropdown() {
        try {
            $this->getCurrentUser();
            
            $suppliers = $this->supplierModel->getActiveSuppliers();
            
            Response::success($suppliers, 'Suppliers retrieved successfully');
        } catch (Exception $e) {
            Response::error('Failed to retrieve suppliers: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /suppliers/stats - ສະຖິຕິຜູ້ສະໜອງ
     */
    public function getSupplierStats() {
        try {
            $currentUser = $this->getCurrentUser();
            
            $stats = $this->supplierModel->getSupplierStats();
            
            Response::success($stats, 'Supplier statistics retrieved successfully');
        } catch (Exception $e) {
            Response::error('Failed to retrieve statistics: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /suppliers/search - ຄົ້ນຫາຜູ້ສະໜອງ
     */
    public function searchSuppliers() {
        try {
            $currentUser = $this->getCurrentUser();
            
            $keyword = isset($_GET['q']) ? $_GET['q'] : '';
            
            if (empty($keyword)) {
                Response::error('Search keyword is required', 400);
            }

            $suppliers = $this->supplierModel->searchSuppliers($keyword);
            
            Response::success([
                'suppliers' => $suppliers,
                'total' => count($suppliers),
                'keyword' => $keyword
            ], 'Search completed successfully');
        } catch (Exception $e) {
            Response::error('Failed to search suppliers: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /suppliers/{id} - ດຶງຂໍ້ມູນຜູ້ສະໜອງຕາມ ID
     */
    public function getSupplierById($id) {
        try {
            $currentUser = $this->getCurrentUser();

            $supplier = $this->supplierModel->getById($id);

            if (!$supplier) {
                Response::notFound('Supplier not found');
            }

            Response::success($supplier, 'Supplier retrieved successfully');
        } catch (Exception $e) {
            Response::error('Failed to retrieve supplier: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /suppliers/by-code/{code} - ດຶງຜູ້ສະໜອງຕາມລະຫັດ
     */
    public function getSupplierByCode($code) {
        try {
            $currentUser = $this->getCurrentUser();

            $supplier = $this->supplierModel->getByCode($code);

            if (!$supplier) {
                Response::notFound('Supplier not found');
            }

            Response::success($supplier, 'Supplier retrieved successfully');
        } catch (Exception $e) {
            Response::error('Failed to retrieve supplier: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST /suppliers - ສ້າງຜູ້ສະໜອງໃໝ່
     */
    public function createSupplier() {
        try {
            $currentUser = $this->getCurrentUser();
            $this->checkAdminPermission($currentUser);
            
            $data = json_decode(file_get_contents('php://input'), true);

            // ກວດສອບຂໍ້ມູນທີ່ຈຳເປັນ
            if (empty($data['supplier_name'])) {
                Response::error('Supplier name is required', 400);
            }

            // ກວດສອບຮູບແບບອີເມລ
            // if (!empty($data['email']) && !$this->validator->validateEmail($data['email'])) {
            //     Response::error('Invalid email format', 400);
            // }

            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                Response::error('Invalid email format', 400);
            }

            $result = $this->supplierModel->create($data, $currentUser['id']);

            if ($result['success']) {
                $supplier = $this->supplierModel->getById($result['supplier_id']);
                Response::success($supplier, 'Supplier created successfully', 201);
            } else {
                Response::error($result['message'], 400);
            }
        } catch (Exception $e) {
            Response::error('Failed to create supplier: ' . $e->getMessage(), 500);
        }
    }

    /**
     * PUT /suppliers/{id} - ອັບເດດຂໍ້ມູນຜູ້ສະໜອງ
     */
    public function updateSupplier($id) {
        try {
            $currentUser = $this->getCurrentUser();
            $this->checkAdminPermission($currentUser);
            
            $supplier = $this->supplierModel->getById($id);
            if (!$supplier) {
                Response::notFound('Supplier not found');
            }

            $data = json_decode(file_get_contents('php://input'), true);

            // ກວດສອບຮູບແບບອີເມລ
            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                Response::error('Invalid email format', 400);
            }

            $result = $this->supplierModel->update($id, $data, $currentUser['id']);

            if ($result['success']) {
                $this->userModel->logActivity($currentUser['id'], 'supplier_updated', 
                    'Supplier updated: ' . ($data['supplier_name'] ?? $supplier['supplier_name']));
                
                $updatedSupplier = $this->supplierModel->getById($id);
                Response::success($updatedSupplier, $result['message']);
            } else {
                Response::error($result['message'], 400);
            }
        } catch (Exception $e) {
            Response::error('Failed to update supplier: ' . $e->getMessage(), 500);
        }
    }

    /**
     * PATCH /suppliers/{id}/status - ອັບເດດສະຖານະ
     */
    public function updateSupplierStatus($id) {
        try {
            $currentUser = $this->getCurrentUser();
            $this->checkAdminPermission($currentUser);
            
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['status'])) {
                Response::error('Status is required', 400);
            }

            $result = $this->supplierModel->updateStatus($id, $data['status'], $currentUser['id']);

            if ($result['success']) {
                $this->userModel->logActivity($currentUser['id'], 'supplier_status_changed', 
                    'Supplier status changed to ' . $data['status']);
                Response::success(null, $result['message']);
            } else {
                Response::error($result['message'], 400);
            }
        } catch (Exception $e) {
            Response::error('Failed to update supplier status: ' . $e->getMessage(), 500);
        }
    }

    /**
     * DELETE /suppliers/{id} - ລຶບຜູ້ສະໜອງ
     */
    public function deleteSupplier($id) {
        try {
            $currentUser = $this->getCurrentUser();
            $this->checkAdminPermission($currentUser);
            
            $supplier = $this->supplierModel->getById($id);
            if (!$supplier) {
                Response::notFound('Supplier not found');
            }

            $result = $this->supplierModel->delete($id);

            if ($result['success']) {
                $this->userModel->logActivity($currentUser['id'], 'supplier_deleted', 
                    'Supplier deleted: ' . $supplier['supplier_name']);
                Response::success(null, $result['message']);
            } else {
                Response::error($result['message'], 400);
            }
        } catch (Exception $e) {
            Response::error('Failed to delete supplier: ' . $e->getMessage(), 500);
        }
    }
}
?>