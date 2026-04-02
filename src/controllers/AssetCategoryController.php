<?php
// src/controllers/AssetCategoryController.php

require_once __DIR__ . '/../models/AssetCategory.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Validator.php';

class AssetCategoryController {
    private $categoryModel;
    private $userModel;
    private $validator;

    public function __construct() {
        $this->categoryModel = new AssetCategory();
        $this->userModel = new User();
        $this->validator = new Validator();
    }

    /**
     * ດຶງຂໍ້ມູນຜູ້ໃຊ້ປັດຈຸບັນ
     */
    private function getCurrentUser() {
        $payload = AuthMiddleware::authenticate();
        $userId = $payload['user_id'];
        $user = $this->userModel->getById($userId);
        
        if (!$user) {
            Response::unauthorized('User not found');
        }
        
        return $user;
    }

    /**
     * GET /asset-categories - ດຶງຂໍ້ມູນໝວດໝູ່ຊັບສິນທັງໝົດ
     */
    public function getAllCategories() {
        try {
            $currentUser = $this->getCurrentUser();
            
            // ຮັບພາຣາມິເຕີຈາກ query string
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
            $search = isset($_GET['search']) ? $_GET['search'] : '';
            $is_active = isset($_GET['is_active']) ? $_GET['is_active'] : 'all';
            $level = isset($_GET['level']) ? (int)$_GET['level'] : 0;
            $parent_id = isset($_GET['parent_id']) ? $_GET['parent_id'] : null;
            $sort_by = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'sort_order';
            $sort_order = isset($_GET['sort_order']) ? strtoupper($_GET['sort_order']) : 'ASC';

            $filters = [
                'search' => $search,
                'is_active' => $is_active,
                'level' => $level,
                'parent_id' => $parent_id,
                'page' => $page,
                'limit' => $limit,
                'sort_by' => $sort_by,
                'sort_order' => $sort_order
            ];

            $result = $this->categoryModel->getAllCategories($filters, $currentUser['role']);

            // ເພີ່ມ pretty_path ໃຫ້ກັບທຸກລາຍການ
            foreach ($result['data'] as &$category) {
                $category['pretty_path'] = $this->categoryModel->getPrettyPath($category['id']);
            }

            Response::success([
                'categories' => $result['data'],
                'pagination' => [
                    'current_page' => $result['current_page'],
                    'per_page' => $result['per_page'],
                    'total' => $result['total'],
                    'total_pages' => $result['last_page'],
                    'from' => $result['from'],
                    'to' => $result['to']
                ],
                'filters' => $filters
            ], 'Asset categories retrieved successfully');
        } catch (Exception $e) {
            Response::error('Failed to retrieve categories: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /asset-categories/tree - ດຶງຂໍ້ມູນໝວດໝູ່ຊັບສິນແບບຕົ້ນໄມ້
     */
    public function getCategoryTree() {
        try {
            $this->getCurrentUser();
            
            $parent_id = isset($_GET['parent_id']) ? ($_GET['parent_id'] === 'null' ? null : (int)$_GET['parent_id']) : null;
            
            $tree = $this->categoryModel->getCategoryTree($parent_id);
            
            Response::success($tree, 'Category tree retrieved successfully');
        } catch (Exception $e) {
            Response::error('Failed to retrieve category tree: ' . $e->getMessage(), 500);
        }
    }

/**
 * GET /asset-categories/dropdown - ດຶງຂໍ້ມູນໝວດໝູ່ຊັບສິນແບບຫຍໍ້ສຳລັບ dropdown
 */
public function getCategoriesForDropdown() {
    try {
        $this->getCurrentUser();
        
        $parent_id = isset($_GET['parent_id']) ? $_GET['parent_id'] : null;
        $exclude_id = isset($_GET['exclude_id']) ? (int)$_GET['exclude_id'] : 0;
        $level = isset($_GET['level']) ? (int)$_GET['level'] : 0;

        if ($level > 0) {
            // ຖ້າລະບຸ level, ໃຫ້ດຶງໝວດໝູ່ທີ່ມີ level ຕ່ຳກວ່າ
            $categories = $this->categoryModel->getCategoriesByMaxLevel($level - 1, $exclude_id);
        } else {
            // ປົກກະຕິດຶງຕາມ parent_id
            $categories = $this->categoryModel->getCategoriesForDropdown($parent_id, $exclude_id);
        }
        
        Response::success($categories, 'Categories retrieved successfully');
    } catch (Exception $e) {
        Response::error('Failed to retrieve categories: ' . $e->getMessage(), 500);
    }
}

    /**
     * ດຶງລາຍຊື່ໝວດໝູ່ຊັບສິນຕາມລະດັບ (ສຳລັບ dropdown)
     */
    public function getCategoriesByLevel($level, $excludeId = null) {
        $sql = "SELECT id, category_code, category_name, level,
                    CONCAT(category_code, ' - ', category_name) as display_name
                FROM asset_categories 
                WHERE is_active = 1 AND level = ?";
        $params = [$level];

        if ($excludeId) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }

        $sql .= " ORDER BY sort_order ASC, category_name ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * GET /asset-categories/stats - ສະຖິຕິໝວດໝູ່ຊັບສິນ
     */
    public function getCategoryStats() {
        try {
            $this->getCurrentUser();

            $stats = $this->categoryModel->getCategoryStats();
            
            Response::success($stats, 'Category statistics retrieved successfully');
        } catch (Exception $e) {
            Response::error('Failed to retrieve statistics: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /asset-categories/{id} - ດຶງຂໍ້ມູນໝວດໝູ່ຊັບສິນຕາມ ID
     */
    public function getCategoryById($id) {
        try {
            $this->getCurrentUser();

            $category = $this->categoryModel->getById($id);

            if (!$category) {
                Response::notFound('Asset category not found');
            }

            // ເພີ່ມ pretty_path ເຂົ້າໄປໃນ response
            $category['pretty_path'] = $this->categoryModel->getPrettyPath($id);

            Response::success($category, 'Asset category retrieved successfully');
        } catch (Exception $e) {
            Response::error('Failed to retrieve category: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST /asset-categories - ສ້າງໝວດໝູ່ຊັບສິນໃໝ່
     */
    public function createCategory() {
        try {
            $currentUser = $this->getCurrentUser();
            
            $data = json_decode(file_get_contents('php://input'), true);

            // ກວດສອບຂໍ້ມູນ
            $validationRules = [
                'category_code' => 'required|string|max:50',
                'category_name' => 'required|string|max:200',
                'description' => 'string',
                'parent_id' => 'integer',
                'depreciation_method' => 'in:straight_line,declining_balance,none',
                'useful_life_years' => 'integer|min:1|max:100',
                'depreciation_rate' => 'numeric|min:0|max:100',
                'is_active' => 'integer|in:0,1',
                'sort_order' => 'integer'
            ];

            $this->validator->validate($data, $validationRules);

            if ($this->validator->fails()) {
                Response::error($this->validator->errors(), 422);
            }

            $result = $this->categoryModel->create($data, $currentUser['id']);

            if ($result['success']) {
                // ບັນທຶກການສ້າງ
                $this->userModel->logActivity($currentUser['id'], 'asset_category_created', 
                    'Asset category created: ' . $data['category_name']);
                
                $category = $this->categoryModel->getById($result['category_id']);
                Response::success($category, 'Asset category created successfully', 201);
            } else {
                Response::error($result['message'], 400);
            }
        } catch (Exception $e) {
            Response::error('Failed to create asset category: ' . $e->getMessage(), 500);
        }
    }

    /**
     * PUT /asset-categories/{id} - ອັບເດດຂໍ້ມູນໝວດໝູ່ຊັບສິນ
     */
    public function updateCategory($id) {
        try {
            $currentUser = $this->getCurrentUser();
            
            $category = $this->categoryModel->getById($id);
            if (!$category) {
                Response::notFound('Asset category not found');
            }

            $data = json_decode(file_get_contents('php://input'), true);

            // ກວດສອບຂໍ້ມູນ
            $validationRules = [
                'category_code' => 'string|max:50',
                'category_name' => 'string|max:200',
                'description' => 'string',
                'parent_id' => 'integer',
                'depreciation_method' => 'in:straight_line,declining_balance,none',
                'useful_life_years' => 'integer|min:1|max:100',
                'depreciation_rate' => 'numeric|min:0|max:100',
                'is_active' => 'integer|in:0,1',
                'sort_order' => 'integer'
            ];

            $this->validator->validate($data, $validationRules);

            if ($this->validator->fails()) {
                Response::error($this->validator->errors(), 422);
            }

            $result = $this->categoryModel->update($id, $data);

            if ($result['success']) {
                // ບັນທຶກການອັບເດດ
                $this->userModel->logActivity($currentUser['id'], 'asset_category_updated', 
                    'Asset category updated: ' . ($data['category_name'] ?? $category['category_name']));
                
                $updatedCategory = $this->categoryModel->getById($id);
                Response::success($updatedCategory, $result['message']);
            } else {
                Response::error($result['message'], 400);
            }
        } catch (Exception $e) {
            Response::error('Failed to update asset category: ' . $e->getMessage(), 500);
        }
    }

    /**
     * PATCH /asset-categories/{id}/status - ອັບເດດສະຖານະໝວດໝູ່ຊັບສິນ
     */
    public function updateCategoryStatus($id) {
        try {
            $currentUser = $this->getCurrentUser();
            
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['is_active'])) {
                Response::error('is_active is required', 400);
            }

            $isActive = (int)$data['is_active'];
            if (!in_array($isActive, [0, 1])) {
                Response::error('Invalid status value. Allowed: 0, 1', 400);
            }

            $category = $this->categoryModel->getById($id);
            if (!$category) {
                Response::notFound('Asset category not found');
            }

            $result = $this->categoryModel->updateStatus($id, $isActive);

            if ($result['success']) {
                // ບັນທຶກການປ່ຽນສະຖານະ
                $this->userModel->logActivity($currentUser['id'], 'asset_category_status_changed', 
                    'Asset category status changed to ' . ($isActive ? 'active' : 'inactive'));
                
                Response::success(null, $result['message']);
            } else {
                Response::error($result['message'], 400);
            }
        } catch (Exception $e) {
            Response::error('Failed to update category status: ' . $e->getMessage(), 500);
        }
    }

    /**
     * DELETE /asset-categories/{id} - ລຶບໝວດໝູ່ຊັບສິນ
     */
    public function deleteCategory($id) {
        try {
            $currentUser = $this->getCurrentUser();
            
            $category = $this->categoryModel->getById($id);
            if (!$category) {
                Response::notFound('Asset category not found');
            }

            $result = $this->categoryModel->softDelete($id);

            if ($result['success']) {
                // ບັນທຶກການລຶບ
                $this->userModel->logActivity($currentUser['id'], 'asset_category_deleted', 
                    'Asset category deleted: ' . $category['category_name']);
                
                Response::success(null, $result['message']);
            } else {
                Response::error($result['message'], 400);
            }
        } catch (Exception $e) {
            Response::error('Failed to delete asset category: ' . $e->getMessage(), 500);
        }
    }


    /**
     * GET /asset-categories/by-level-parent - ດຶງຂໍ້ມູນໝວດໝູ່ຊັບສິນສຳລັບເປັນພໍ່ຕາມລະດັບທີ່ຈະເພີ່ມ
     */
    public function getCategoriesByTargetLevel() {
        try {
            $this->getCurrentUser();
            
            $target_level = isset($_GET['target_level']) ? (int)$_GET['target_level'] : 2;
            $exclude_id = isset($_GET['exclude_id']) ? (int)$_GET['exclude_id'] : 0;
            
            // ລະດັບຂອງພໍ່ = target_level - 1
            $parent_level = $target_level - 1;
            
            $categories = $this->categoryModel->getCategoriesByLevel($parent_level, $exclude_id);
            
            Response::success($categories, 'Categories retrieved successfully');
        } catch (Exception $e) {
            Response::error('Failed to retrieve categories: ' . $e->getMessage(), 500);
        }
    }

}
?>