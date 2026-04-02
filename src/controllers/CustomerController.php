<?php
// /home/phosika/Fixasset/assetapplication/BACKEND/src/controllers/CustomerController.php

require_once __DIR__ . '/../models/Customer.php';
require_once __DIR__ . '/../models/Company.php';
require_once __DIR__ . '/../models/Branch.php';
require_once __DIR__ . '/../models/Department.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../utils/Response.php';

class CustomerController {
    private $customerModel;
    private $companyModel;
    private $branchModel;
    private $departmentModel;
    private $userModel;

    public function __construct() {
        $this->customerModel = new Customer();
        $this->companyModel = new Company();
        $this->branchModel = new Branch();
        $this->departmentModel = new Department();
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
     * GET /customers - ດຶງຂໍ້ມູນລູກຄ້າທັງໝົດ
     */
    public function getAllCustomers() {
        try {
            $user = $this->getCurrentUser();
            
            // ກຳນົດການກັ່ນຕອງຕາມບົດບາດ
            $companyId = null;
            $branchId = null;
            $departmentId = null;
            
            if ($user['role'] === 'company_admin') {
                $companyId = $user['company_id'];
            } elseif ($user['role'] === 'branch_manager') {
                $branchId = $user['branch_id'];
            } elseif ($user['role'] === 'department_head') {
                $departmentId = $user['department_id'];
            }

            $filters = [
                'search' => $_GET['search'] ?? '',
                'status' => isset($_GET['status']) ? (int)$_GET['status'] : null,
                'page' => isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1,
                'limit' => isset($_GET['limit']) ? min(100, (int)$_GET['limit']) : 20,
                'sort_by' => $_GET['sort_by'] ?? 'c.created_at',
                'sort_order' => $_GET['sort_order'] ?? 'DESC'
            ];

            $result = $this->customerModel->getAllCustomers($filters, $companyId, $branchId, $departmentId);

            Response::success([
                'customers' => $result['data'],
                'pagination' => [
                    'current_page' => $result['current_page'],
                    'per_page' => $result['per_page'],
                    'total' => $result['total'],
                    'total_pages' => $result['last_page']
                ]
            ], 'ດຶງຂໍ້ມູນລູກຄ້າສຳເລັດ');

        } catch (Exception $e) {
            error_log("Error in getAllCustomers: " . $e->getMessage());
            Response::error('ດຶງຂໍ້ມູນບໍ່ສຳເລັດ: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /customers/{id} - ດຶງຂໍ້ມູນລູກຄ້າຕາມ ID
     */
    public function getCustomerById($id) {
        try {
            if (!is_numeric($id)) {
                Response::error('ID ບໍ່ຖືກຕ້ອງ', 400);
                return;
            }

            $customer = $this->customerModel->getCustomerById($id);

            if (!$customer) {
                Response::notFound('ບໍ່ພົບຂໍ້ມູນລູກຄ້າ');
                return;
            }

            Response::success($customer, 'ດຶງຂໍ້ມູນສຳເລັດ');

        } catch (Exception $e) {
            error_log("Error in getCustomerById: " . $e->getMessage());
            Response::error('ດຶງຂໍ້ມູນບໍ່ສຳເລັດ: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /customers/dropdown - ດຶງຂໍ້ມູນສຳລັບ dropdown
     */
    public function getCustomersDropdown() {
        try {
            $user = $this->getCurrentUser();
            
            $companyId = $_GET['company_id'] ?? ($user['company_id'] ?? null);
            $branchId = $_GET['branch_id'] ?? ($user['branch_id'] ?? null);
            $search = $_GET['search'] ?? '';
            
            $customers = $this->customerModel->getCustomersForDropdown($companyId, $branchId, $search);
            
            Response::success($customers, 'ດຶງຂໍ້ມູນສຳເລັດ');

        } catch (Exception $e) {
            error_log("Error in getCustomersDropdown: " . $e->getMessage());
            Response::error('ດຶງຂໍ້ມູນບໍ່ສຳເລັດ: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /customers/filters - ດຶງຂໍ້ມູນສຳລັບ filter dropdowns
     */
    public function getFilterData() {
        try {
            $companies = $this->companyModel->getAllCompanies(['limit' => 100])['data'] ?? [];
            $branches = $this->branchModel->getAllBranches(['limit' => 100])['data'] ?? [];
            $departments = $this->departmentModel->getAllDepartments(['limit' => 100])['data'] ?? [];
            $users = $this->userModel->getUsersForDropdown();

            Response::success([
                'companies' => $companies,
                'branches' => $branches,
                'departments' => $departments,
                'users' => $users
            ], 'ດຶງຂໍ້ມູນສຳເລັດ');

        } catch (Exception $e) {
            error_log("Error in getFilterData: " . $e->getMessage());
            Response::error('ດຶງຂໍ້ມູນບໍ່ສຳເລັດ: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST /customers - ສ້າງລູກຄ້າໃໝ່
     */
    public function createCustomer() {
        try {
            $user = $this->getCurrentUser();

            $data = json_decode(file_get_contents('php://input'), true);

            // ກວດສອບຂໍ້ມູນທີ່ຈຳເປັນ
            $required = ['customer_code', 'customer_name'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    Response::error("ກະລຸນາປ້ອນຂໍ້ມູນ {$field} ໃຫ້ຄົບ", 400);
                    return;
                }
            }

            $result = $this->customerModel->createCustomer($data, $user['id'] ?? null);

            if ($result['success']) {
                Response::success(['id' => $result['id']], $result['message'], 201);
            } else {
                Response::error($result['message'], 400);
            }

        } catch (Exception $e) {
            error_log("Error in createCustomer: " . $e->getMessage());
            Response::error('ສ້າງລູກຄ້າບໍ່ສຳເລັດ: ' . $e->getMessage(), 500);
        }
    }

    /**
     * PUT /customers/{id} - ອັບເດດລູກຄ້າ
     */
    public function updateCustomer($id) {
        try {
            $user = $this->getCurrentUser();

            if (!is_numeric($id)) {
                Response::error('ID ບໍ່ຖືກຕ້ອງ', 400);
                return;
            }

            $data = json_decode(file_get_contents('php://input'), true);

            $result = $this->customerModel->updateCustomer($id, $data);

            if ($result['success']) {
                Response::success(null, $result['message']);
            } else {
                Response::error($result['message'], 400);
            }

        } catch (Exception $e) {
            error_log("Error in updateCustomer: " . $e->getMessage());
            Response::error('ອັບເດດລູກຄ້າບໍ່ສຳເລັດ: ' . $e->getMessage(), 500);
        }
    }
}
?>