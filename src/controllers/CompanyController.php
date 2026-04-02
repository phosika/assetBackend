<?php
// src/controllers/CompanyController.php

require_once __DIR__ . '/../models/Company.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Validator.php';

class CompanyController {
    private $companyModel;
    private $validator;

    public function __construct() {
        $this->companyModel = new Company();
        $this->validator = new Validator();
    }

    /**
     * GET /companies - ດຶງຂໍ້ມູນບໍລິສັດທັງໝົດ
     */
    public function getAllCompanies() {
        try {
            $userId = AuthMiddleware::authenticate(['super_admin', 'asset_admin']);
            
            // ຮັບພາຣາມິເຕີຈາກ query string
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
            $search = isset($_GET['search']) ? $_GET['search'] : '';
            $status = isset($_GET['status']) ? $_GET['status'] : '';
            $sort_by = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'created_at';
            $sort_order = isset($_GET['sort_order']) ? strtoupper($_GET['sort_order']) : 'DESC';

            $filters = [
                'search' => $search,
                'status' => $status,
                'page' => $page,
                'limit' => $limit,
                'sort_by' => $sort_by,
                'sort_order' => $sort_order
            ];

            $result = $this->companyModel->getAllCompanies($filters);

            Response::success([
                'companies' => $result['data'],
                'pagination' => [
                    'current_page' => $result['current_page'],
                    'per_page' => $result['per_page'],
                    'total' => $result['total'],
                    'total_pages' => $result['last_page'],
                    'from' => $result['from'],
                    'to' => $result['to']
                ],
                'filters' => $filters
            ], 'Companies retrieved successfully');
        } catch (Exception $e) {
            Response::error('Failed to retrieve companies: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /companies/dropdown - ດຶງຂໍ້ມູນບໍລິສັດແບບຫຍໍ້ສຳລັບ dropdown
     */
    public function getCompaniesForDropdown() {
        try {
            AuthMiddleware::authenticate();

            $exclude_id = isset($_GET['exclude_id']) ? (int)$_GET['exclude_id'] : 0;
            $search = isset($_GET['search']) ? $_GET['search'] : '';

            $companies = $this->companyModel->getCompaniesForDropdown($exclude_id, $search);
            
            Response::success($companies, 'Companies retrieved successfully');
        } catch (Exception $e) {
            Response::error('Failed to retrieve companies: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /companies/parents - ດຶງລາຍຊື່ບໍລິສັດແມ່
     */
    public function getParentCompanies() {
        try {
            AuthMiddleware::authenticate();

            $exclude_id = isset($_GET['exclude_id']) ? (int)$_GET['exclude_id'] : 0;
            $companies = $this->companyModel->getParentCompanies($exclude_id);
            
            Response::success($companies, 'Parent companies retrieved successfully');
        } catch (Exception $e) {
            Response::error('Failed to retrieve parent companies: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /companies/stats - ສະຖິຕິບໍລິສັດ
     */
    public function getCompanyStats() {
        try {
            AuthMiddleware::authenticate(['super_admin', 'asset_admin']);

            $stats = $this->companyModel->getCompanyStats();
            
            Response::success($stats, 'Company statistics retrieved successfully');
        } catch (Exception $e) {
            Response::error('Failed to retrieve statistics: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /companies/{id} - ດຶງຂໍ້ມູນບໍລິສັດຕາມ ID
     */
    public function getCompanyById($id) {
        try {
            AuthMiddleware::authenticate(['super_admin', 'asset_admin']);

            $company = $this->companyModel->getById($id);

            if (!$company) {
                Response::notFound('Company not found');
            }

            Response::success($company, 'Company retrieved successfully');
        } catch (Exception $e) {
            Response::error('Failed to retrieve company: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST /companies - ສ້າງບໍລິສັດໃໝ່
     */
    public function createCompany() {
        try {
            $userId = AuthMiddleware::authenticate(['super_admin', 'asset_admin']);
            $currentUser = $this->companyModel->getById($userId); // ຈະຕ້ອງແກ້ໄຂເພາະ company model ບໍ່ມີ getById ສຳລັບ user
            
            $data = json_decode(file_get_contents('php://input'), true);

            // ກວດສອບຂໍ້ມູນ
            $validationRules = [
                'company_code' => 'required|string|max:50',
                'company_name' => 'required|string|max:255',
                'parent_company_id' => 'integer',
                'address' => 'string',
                'phone' => 'string|max:20',
                'email' => 'email|max:100',
                'tax_id' => 'string|max:50',
                'status' => 'integer|in:0,1'
            ];

            $this->validator->validate($data, $validationRules);

            if ($this->validator->fails()) {
                Response::error($this->validator->errors(), 422);
            }

            $result = $this->companyModel->create($data);

            if ($result['success']) {
                // ບັນທຶກການສ້າງ
                // ຕ້ອງໃຊ້ User model ເພື່ອ log activity
                // $userModel = new User();
                // $userModel->logActivity($userId, 'company_created', 'Company created: ' . $data['company_name']);
                
                $company = $this->companyModel->getById($result['company_id']);
                Response::success($company, 'Company created successfully', 201);
            } else {
                Response::error($result['message'], 400);
            }
        } catch (Exception $e) {
            Response::error('Failed to create company: ' . $e->getMessage(), 500);
        }
    }

    /**
     * PUT /companies/{id} - ອັບເດດຂໍ້ມູນບໍລິສັດ
     */
    public function updateCompany($id) {
        try {
            $userId = AuthMiddleware::authenticate(['super_admin', 'asset_admin']);
            
            $company = $this->companyModel->getById($id);
            if (!$company) {
                Response::notFound('Company not found');
            }

            $data = json_decode(file_get_contents('php://input'), true);

            // ກວດສອບຂໍ້ມູນ
            $validationRules = [
                'company_code' => 'string|max:50',
                'company_name' => 'string|max:255',
                'parent_company_id' => 'integer',
                'address' => 'string',
                'phone' => 'string|max:20',
                'email' => 'email|max:100',
                'tax_id' => 'string|max:50',
                'status' => 'integer|in:0,1'
            ];

            $this->validator->validate($data, $validationRules);

            if ($this->validator->fails()) {
                Response::error($this->validator->errors(), 422);
            }

            $result = $this->companyModel->update($id, $data);

            if ($result['success']) {
                // ບັນທຶກການອັບເດດ
                // $userModel = new User();
                // $userModel->logActivity($userId, 'company_updated', 'Company updated: ' . $company['company_name']);
                
                $updatedCompany = $this->companyModel->getById($id);
                Response::success($updatedCompany, $result['message']);
            } else {
                Response::error($result['message'], 400);
            }
        } catch (Exception $e) {
            Response::error('Failed to update company: ' . $e->getMessage(), 500);
        }
    }

    /**
     * PATCH /companies/{id}/status - ອັບເດດສະຖານະບໍລິສັດ
     */
    public function updateCompanyStatus($id) {
        try {
            $userId = AuthMiddleware::authenticate(['super_admin', 'asset_admin']);
            
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['status'])) {
                Response::error('Status is required', 400);
            }

            $status = (int)$data['status'];
            if (!in_array($status, [0, 1])) {
                Response::error('Invalid status value. Allowed: 0, 1', 400);
            }

            $company = $this->companyModel->getById($id);
            if (!$company) {
                Response::notFound('Company not found');
            }

            $result = $this->companyModel->updateStatus($id, $status);

            if ($result['success']) {
                // ບັນທຶກການປ່ຽນສະຖານະ
                // $userModel = new User();
                // $userModel->logActivity($userId, 'company_status_changed', 'Company status changed to ' . $status);
                
                Response::success(null, $result['message']);
            } else {
                Response::error($result['message'], 400);
            }
        } catch (Exception $e) {
            Response::error('Failed to update company status: ' . $e->getMessage(), 500);
        }
    }

    /**
     * DELETE /companies/{id} - ລຶບບໍລິສັດ (soft delete)
     */
    public function deleteCompany($id) {
        try {
            $userId = AuthMiddleware::authenticate(['super_admin']);
            
            $company = $this->companyModel->getById($id);
            if (!$company) {
                Response::notFound('Company not found');
            }

            $result = $this->companyModel->softDelete($id);

            if ($result['success']) {
                // ບັນທຶກການລຶບ
                // $userModel = new User();
                // $userModel->logActivity($userId, 'company_deleted', 'Company deleted: ' . $company['company_name']);
                
                Response::success(null, $result['message']);
            } else {
                Response::error($result['message'], 400);
            }
        } catch (Exception $e) {
            Response::error('Failed to delete company: ' . $e->getMessage(), 500);
        }
    }

    /**
     * DELETE /companies/{id}/permanent - ລຶບບໍລິສັດແບບຖາວອນ (Super Admin only)
     */
    public function deleteCompanyPermanently($id) {
        try {
            $userId = AuthMiddleware::authenticate(['super_admin']);
            
            $company = $this->companyModel->getById($id);
            if (!$company) {
                Response::notFound('Company not found');
            }

            $result = $this->companyModel->deletePermanently($id);

            if ($result['success']) {
                // ບັນທຶກການລຶບ
                // $userModel = new User();
                // $userModel->logActivity($userId, 'company_permanently_deleted', 'Company permanently deleted: ' . $company['company_name']);
                
                Response::success(null, $result['message']);
            } else {
                Response::error($result['message'], 400);
            }
        } catch (Exception $e) {
            Response::error('Failed to permanently delete company: ' . $e->getMessage(), 500);
        }
    }
}
?>