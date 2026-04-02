<?php
// src/controllers/DepartmentController.php

require_once __DIR__ . '/../models/Department.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Validator.php';

class DepartmentController {
    private $departmentModel;
    private $userModel;
    private $validator;

    public function __construct() {
        $this->departmentModel = new Department();
        $this->userModel = new User();
        $this->validator = new Validator();
    }

    /**
     * ດຶງຂໍ້ມູນຜູ້ໃຊ້ປັດຈຸບັນ ແລະ ສິດຂອງເຂົາ
     */
    private function getCurrentUserWithPermissions() {
        $payload = AuthMiddleware::authenticate();
        $userId = $payload['user_id'];
        $user = $this->userModel->getById($userId);
        
        if (!$user) {
            Response::unauthorized('User not found');
        }
        
        return [
            'id' => $user['id'],
            'role' => $user['role'],
            'department_id' => $user['department_id']
        ];
    }

    /**
     * GET /departments - ດຶງຂໍ້ມູນພະແນກທັງໝົດ
     */
    public function getAllDepartments() {
        try {
            $currentUser = $this->getCurrentUserWithPermissions();

             // Debug: ສະແດງຂໍ້ມູນຜູ້ໃຊ້
                error_log("========== getAllDepartments ==========");
                error_log("Current User: " . json_encode($currentUser));
        
            
            // ກວດສອບສິດ - ອະນຸຍາດໃຫ້ super_admin, department_head, manager
            if (!in_array($currentUser['role'], ['super_admin', 'asset_admin', 'department_head', 'manager'])) {
                Response::forbidden('You do not have permission to view departments');
            }

            // ຮັບພາຣາມິເຕີຈາກ query string
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
            $search = isset($_GET['search']) ? $_GET['search'] : '';
            $status = isset($_GET['status']) ? $_GET['status'] : '';
            $company_id = isset($_GET['company_id']) ? (int)$_GET['company_id'] : 0;
            $sort_by = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'created_at';
            $sort_order = isset($_GET['sort_order']) ? strtoupper($_GET['sort_order']) : 'DESC';

            $filters = [
                'search' => $search,
                'status' => $status,
                'company_id' => $company_id,
                'page' => $page,
                'limit' => $limit,
                'sort_by' => $sort_by,
                'sort_order' => $sort_order
            ];

            $result = $this->departmentModel->getAllDepartments(
                $filters, 
                $currentUser['role'], 
                $currentUser['department_id']
            );

            error_log("Result data count: " . count($result['data']));
            error_log("Result total: " . $result['total']);
            error_log("======================================");

            Response::success([
                'departments' => $result['data'],
                'pagination' => [
                    'current_page' => $result['current_page'],
                    'per_page' => $result['per_page'],
                    'total' => $result['total'],
                    'total_pages' => $result['last_page'],
                    'from' => $result['from'],
                    'to' => $result['to']
                ],
                'filters' => $filters
            ], 'Departments retrieved successfully');
        } catch (Exception $e) {
            Response::error('Failed to retrieve departments: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /departments/dropdown - ດຶງຂໍ້ມູນພະແນກແບບຫຍໍ້ສຳລັບ dropdown
     */
    public function getDepartmentsForDropdown() {
        try {
            $currentUser = $this->getCurrentUserWithPermissions();
            
            $company_id = isset($_GET['company_id']) ? (int)$_GET['company_id'] : 0;
            $exclude_id = isset($_GET['exclude_id']) ? (int)$_GET['exclude_id'] : 0;

            $departments = $this->departmentModel->getDepartmentsForDropdown(
                $company_id, 
                $exclude_id,
                $currentUser['role'],
                $currentUser['department_id']
            );
            
            Response::success($departments, 'Departments retrieved successfully');
        } catch (Exception $e) {
            Response::error('Failed to retrieve departments: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /departments/parents - ດຶງລາຍຊື່ພະແນກແມ່
     */
    public function getParentDepartments() {
        try {
            AuthMiddleware::authenticate(['super_admin', 'asset_admin']);
            
            $company_id = isset($_GET['company_id']) ? (int)$_GET['company_id'] : 0;
            $exclude_id = isset($_GET['exclude_id']) ? (int)$_GET['exclude_id'] : 0;

            $departments = $this->departmentModel->getParentDepartments($company_id, $exclude_id);
            
            Response::success($departments, 'Parent departments retrieved successfully');
        } catch (Exception $e) {
            Response::error('Failed to retrieve parent departments: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /departments/managers - ດຶງລາຍຊື່ຜູ້ຈັດການທີ່ສາມາດເປັນຫົວໜ້າພະແນກໄດ້
     */
    public function getAvailableManagers() {
        try {
            AuthMiddleware::authenticate(['super_admin', 'asset_admin']);
            
            $department_id = isset($_GET['department_id']) ? (int)$_GET['department_id'] : 0;

            $managers = $this->departmentModel->getAvailableManagers($department_id);
            
            Response::success($managers, 'Available managers retrieved successfully');
        } catch (Exception $e) {
            Response::error('Failed to retrieve managers: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /departments/stats - ສະຖິຕິພະແນກ
     */
    public function getDepartmentStats() {
        try {
            $currentUser = $this->getCurrentUserWithPermissions();

            $stats = $this->departmentModel->getDepartmentStats(
                $currentUser['role'],
                $currentUser['department_id']
            );
            
            Response::success($stats, 'Department statistics retrieved successfully');
        } catch (Exception $e) {
            Response::error('Failed to retrieve statistics: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /departments/company/{companyId} - ດຶງພະແນກຕາມບໍລິສັດ
     */
    public function getDepartmentsByCompany($companyId) {
        try {
            $currentUser = $this->getCurrentUserWithPermissions();

            $departments = $this->departmentModel->getByCompanyId(
                $companyId,
                $currentUser['role'],
                $currentUser['department_id']
            );
            
            Response::success([
                'departments' => $departments,
                'total' => count($departments),
                'company_id' => $companyId
            ], 'Departments retrieved successfully');
        } catch (Exception $e) {
            Response::error('Failed to retrieve departments: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /departments/{id} - ດຶງຂໍ້ມູນພະແນກຕາມ ID
     */
    public function getDepartmentById($id) {
        try {
            $currentUser = $this->getCurrentUserWithPermissions();

            $department = $this->departmentModel->getById($id);

            if (!$department) {
                Response::notFound('Department not found');
            }

            // ກວດສອບສິດການເບິ່ງ
            if (in_array($currentUser['role'], ['department_head', 'manager'])) {
                if ($department['id'] != $currentUser['department_id'] && 
                    $department['parent_department_id'] != $currentUser['department_id']) {
                    Response::forbidden('You do not have permission to view this department');
                }
            }

            Response::success($department, 'Department retrieved successfully');
        } catch (Exception $e) {
            Response::error('Failed to retrieve department: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST /departments - ສ້າງພະແນກໃໝ່
     */
    public function createDepartment() {
        try {
            $currentUser = $this->getCurrentUserWithPermissions();
            
            // ກວດສອບສິດ - ສະເພາະ super_admin ເທົ່ານັ້ນທີ່ສ້າງພະແນກໄດ້
            if (!in_array($currentUser['role'], ['super_admin', 'asset_admin'])) {
                Response::forbidden('Only super admin can create departments');
            }
            
            $data = json_decode(file_get_contents('php://input'), true);

            // ກວດສອບຂໍ້ມູນ
            $validationRules = [
                'department_code' => 'required|string|max:50',
                'department_name' => 'required|string|max:255',
                'company_id' => 'integer',
                'parent_department_id' => 'integer',
                'manager_id' => 'integer',
                'status' => 'integer|in:0,1'
            ];

            $this->validator->validate($data, $validationRules);

            if ($this->validator->fails()) {
                Response::error($this->validator->errors(), 422);
            }

            $result = $this->departmentModel->create($data);

            if ($result['success']) {
                // ບັນທຶກການສ້າງ
                $this->userModel->logActivity($currentUser['id'], 'department_created', 
                    'Department created: ' . $data['department_name']);
                
                $department = $this->departmentModel->getById($result['department_id']);
                Response::success($department, 'Department created successfully', 201);
            } else {
                Response::error($result['message'], 400);
            }
        } catch (Exception $e) {
            Response::error('Failed to create department: ' . $e->getMessage(), 500);
        }
    }

    /**
     * PUT /departments/{id} - ອັບເດດຂໍ້ມູນພະແນກ
     */
    public function updateDepartment($id) {
        try {
            $currentUser = $this->getCurrentUserWithPermissions();
            
            $department = $this->departmentModel->getById($id);
            if (!$department) {
                Response::notFound('Department not found');
            }

            $data = json_decode(file_get_contents('php://input'), true);

            // ກວດສອບຂໍ້ມູນ
            $validationRules = [
                'department_code' => 'string|max:50',
                'department_name' => 'string|max:255',
                'company_id' => 'integer',
                'parent_department_id' => 'integer',
                'manager_id' => 'integer',
                'status' => 'integer|in:0,1'
            ];

            $this->validator->validate($data, $validationRules);

            if ($this->validator->fails()) {
                Response::error($this->validator->errors(), 422);
            }

            $result = $this->departmentModel->update(
                $id, 
                $data, 
                $currentUser['role'], 
                $currentUser['department_id']
            );

            if ($result['success']) {
                // ບັນທຶກການອັບເດດ
                $this->userModel->logActivity($currentUser['id'], 'department_updated', 
                    'Department updated: ' . $department['department_name']);
                
                $updatedDepartment = $this->departmentModel->getById($id);
                Response::success($updatedDepartment, $result['message']);
            } else {
                Response::error($result['message'], 400);
            }
        } catch (Exception $e) {
            Response::error('Failed to update department: ' . $e->getMessage(), 500);
        }
    }

    /**
     * PATCH /departments/{id}/status - ອັບເດດສະຖານະພະແນກ
     */
    public function updateDepartmentStatus($id) {
        try {
            $currentUser = $this->getCurrentUserWithPermissions();
            
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['status'])) {
                Response::error('Status is required', 400);
            }

            $status = (int)$data['status'];
            if (!in_array($status, [0, 1])) {
                Response::error('Invalid status value. Allowed: 0, 1', 400);
            }

            $result = $this->departmentModel->updateStatus(
                $id, 
                $status, 
                $currentUser['role'], 
                $currentUser['department_id']
            );

            if ($result['success']) {
                // ບັນທຶກການປ່ຽນສະຖານະ
                $this->userModel->logActivity($currentUser['id'], 'department_status_changed', 
                    'Department status changed to ' . $status);
                
                Response::success(null, $result['message']);
            } else {
                Response::error($result['message'], 400);
            }
        } catch (Exception $e) {
            Response::error('Failed to update department status: ' . $e->getMessage(), 500);
        }
    }

    /**
     * PATCH /departments/{id}/manager - ອັບເດດຜູ້ຈັດການພະແນກ
     */
    public function updateDepartmentManager($id) {
        try {
            $currentUser = $this->getCurrentUserWithPermissions();
            
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['manager_id'])) {
                Response::error('Manager ID is required', 400);
            }

            $managerId = $data['manager_id'] ? (int)$data['manager_id'] : null;

            $result = $this->departmentModel->updateManager(
                $id, 
                $managerId, 
                $currentUser['role'], 
                $currentUser['department_id']
            );

            if ($result['success']) {
                // ບັນທຶກການປ່ຽນຜູ້ຈັດການ
                $this->userModel->logActivity($currentUser['id'], 'department_manager_changed', 
                    'Department manager updated');
                
                Response::success(null, $result['message']);
            } else {
                Response::error($result['message'], 400);
            }
        } catch (Exception $e) {
            Response::error('Failed to update department manager: ' . $e->getMessage(), 500);
        }
    }

    /**
     * DELETE /departments/{id} - ລຶບພະແນກ (soft delete)
     */
    public function deleteDepartment($id) {
        try {
            $currentUser = $this->getCurrentUserWithPermissions();
            
            $department = $this->departmentModel->getById($id);
            if (!$department) {
                Response::notFound('Department not found');
            }

            $result = $this->departmentModel->softDelete(
                $id, 
                $currentUser['role'], 
                $currentUser['department_id']
            );

            if ($result['success']) {
                // ບັນທຶກການລຶບ
                $this->userModel->logActivity($currentUser['id'], 'department_deleted', 
                    'Department deleted: ' . $department['department_name']);
                
                Response::success(null, $result['message']);
            } else {
                Response::error($result['message'], 400);
            }
        } catch (Exception $e) {
            Response::error('Failed to delete department: ' . $e->getMessage(), 500);
        }
    }
}
?>