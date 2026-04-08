<?php
// src/controllers/InventoryItemController.php

require_once __DIR__ . '/../models/InventoryItem.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Validator.php';
require_once __DIR__ . '/../utils/FileUploader.php';

class InventoryItemController {
    private $itemModel;
    private $userModel;
    private $validator;
    private $fileUploader;

    public function __construct() {
        $this->itemModel = new InventoryItem();
        $this->userModel = new User();
        $this->validator = new Validator();
        $this->fileUploader = new FileUploader('inventory/barcodes');
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
     * ກວດສອບສິດ admin
     */
    private function checkAdminPermission($user) {
        if (!in_array($user['role'], ['super_admin', 'asset_admin'])) {
            Response::forbidden('You do not have permission to perform this action');
        }
    }

    /**
     * GET /inventory-items - ດຶງຂໍ້ມູນສິນຄ້າທັງໝົດ
     */
    public function getAllItems() {
        try {
            $currentUser = $this->getCurrentUser();
            
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
            $search = isset($_GET['search']) ? $_GET['search'] : '';
            $category_id = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;
            $supplier_id = isset($_GET['supplier_id']) ? (int)$_GET['supplier_id'] : 0;
            $is_active = isset($_GET['is_active']) ? (int)$_GET['is_active'] : null;
            $low_stock = isset($_GET['low_stock']) ? (bool)$_GET['low_stock'] : false;
            $min_price = isset($_GET['min_price']) ? (float)$_GET['min_price'] : 0;
            $max_price = isset($_GET['max_price']) ? (float)$_GET['max_price'] : 0;
            $sort_by = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'created_at';
            $sort_order = isset($_GET['sort_order']) ? strtoupper($_GET['sort_order']) : 'DESC';

            $filters = [
                'search' => $search,
                'category_id' => $category_id,
                'supplier_id' => $supplier_id,
                'is_active' => $is_active,
                'low_stock' => $low_stock,
                'min_price' => $min_price,
                'max_price' => $max_price,
                'page' => $page,
                'limit' => $limit,
                'sort_by' => $sort_by,
                'sort_order' => $sort_order
            ];

            $result = $this->itemModel->getAllItems($filters);

            Response::success([
                'items' => $result['data'],
                'pagination' => [
                    'current_page' => $result['current_page'],
                    'per_page' => $result['per_page'],
                    'total' => $result['total'],
                    'total_pages' => $result['last_page'],
                    'from' => $result['from'],
                    'to' => $result['to']
                ],
                'filters' => $filters
            ], 'Inventory items retrieved successfully');
        } catch (Exception $e) {
            error_log("Error in getAllItems: " . $e->getMessage());
            Response::error('Failed to retrieve items: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /inventory-items/dropdown - ດຶງຂໍ້ມູນສິນຄ້າສຳລັບ dropdown
     */
    public function getItemsDropdown() {
        try {
            $this->getCurrentUser();
            
            $items = $this->itemModel->getItemsForDropdown();
            
            Response::success($items, 'Items retrieved successfully');
        } catch (Exception $e) {
            Response::error('Failed to retrieve items: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /inventory-items/stats - ດຶງສະຖິຕິສິນຄ້າ
     */
    public function getItemStats() {
        try {
            $currentUser = $this->getCurrentUser();
            
            $stats = $this->itemModel->getItemStats();
            
            Response::success($stats, 'Item statistics retrieved successfully');
        } catch (Exception $e) {
            Response::error('Failed to retrieve statistics: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /inventory-items/search - ຄົ້ນຫາສິນຄ້າ
     */
    public function searchItems() {
        try {
            $currentUser = $this->getCurrentUser();
            
            $keyword = isset($_GET['q']) ? $_GET['q'] : '';
            
            if (empty($keyword)) {
                Response::error('Search keyword is required', 400);
            }

            $items = $this->itemModel->searchItems($keyword);
            
            Response::success([
                'items' => $items,
                'total' => count($items),
                'keyword' => $keyword
            ], 'Search completed successfully');
        } catch (Exception $e) {
            Response::error('Failed to search items: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /inventory-items/low-stock - ດຶງສິນຄ້າໃກ້ໝົດສະຕ໋ອກ
     */
    public function getLowStockItems() {
        try {
            $currentUser = $this->getCurrentUser();
            
            $items = $this->itemModel->getLowStockItems();
            
            Response::success([
                'items' => $items,
                'total' => count($items)
            ], 'Low stock items retrieved successfully');
        } catch (Exception $e) {
            Response::error('Failed to retrieve low stock items: ' . $e->getMessage(), 500);
        }
    }


    /**
     * GET /inventory-items/by-barcode/{barcode}
     * ຄົ້ນຫາສິນຄ້າດ້ວຍ Barcode
     */
    public function getItemByBarcode($barcode) {
        try {
            AuthMiddleware::authenticate();
            
            $item = $this->inventoryItemModel->getByBarcode($barcode);
            
            if ($item) {
                Response::success($item, 200, 'Item found');
            } else {
                Response::error('Item not found', 404);
            }
        } catch (Exception $e) {
            Response::error('Failed to find item: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /inventory-items/{id} - ດຶງຂໍ້ມູນສິນຄ້າຕາມ ID
     */
    public function getItemById($id) {
        try {
            $currentUser = $this->getCurrentUser();

            $item = $this->itemModel->getById($id);

            if (!$item) {
                Response::notFound('Item not found');
            }

            Response::success($item, 'Item retrieved successfully');
        } catch (Exception $e) {
            Response::error('Failed to retrieve item: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /inventory-items/by-code/{code} - ດຶງສິນຄ້າຕາມລະຫັດ
     */
    public function getItemByCode($code) {
        try {
            $currentUser = $this->getCurrentUser();

            $item = $this->itemModel->getByCode($code);

            if (!$item) {
                Response::notFound('Item not found');
            }

            Response::success($item, 'Item retrieved successfully');
        } catch (Exception $e) {
            Response::error('Failed to retrieve item: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST /inventory-items - ສ້າງສິນຄ້າໃໝ່
     */
    public function createItem() {
        try {
            $currentUser = $this->getCurrentUser();
            $this->checkAdminPermission($currentUser);
            
            $data = json_decode(file_get_contents('php://input'), true);

            // ກວດສອບຂໍ້ມູນທີ່ຈຳເປັນ
            if (empty($data['item_name'])) {
                Response::error('Item name is required', 400);
            }

            if (empty($data['category_id'])) {
                Response::error('Category ID is required', 400);
            }

            $result = $this->itemModel->create($data, $currentUser['id']);

            if ($result['success']) {
                $item = $this->itemModel->getById($result['item_id']);
                Response::success($item, 'Item created successfully', 201);
            } else {
                Response::error($result['message'], 400);
            }
        } catch (Exception $e) {
            Response::error('Failed to create item: ' . $e->getMessage(), 500);
        }
    }

    /**
     * PUT /inventory-items/{id} - ອັບເດດຂໍ້ມູນສິນຄ້າ
     */
    public function updateItem($id) {
        try {
            $currentUser = $this->getCurrentUser();
            $this->checkAdminPermission($currentUser);
            
            $item = $this->itemModel->getById($id);
            if (!$item) {
                Response::notFound('Item not found');
            }

            $data = json_decode(file_get_contents('php://input'), true);

            $result = $this->itemModel->update($id, $data, $currentUser['id']);

            if ($result['success']) {
                $this->userModel->logActivity($currentUser['id'], 'item_updated', 
                    'Item updated: ' . ($data['item_name'] ?? $item['item_name']));
                
                $updatedItem = $this->itemModel->getById($id);
                Response::success($updatedItem, $result['message']);
            } else {
                Response::error($result['message'], 400);
            }
        } catch (Exception $e) {
            Response::error('Failed to update item: ' . $e->getMessage(), 500);
        }
    }

    /**
     * PATCH /inventory-items/{id}/status - ອັບເດດສະຖານະ
     */
    public function updateItemStatus($id) {
        try {
            $currentUser = $this->getCurrentUser();
            $this->checkAdminPermission($currentUser);
            
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['is_active'])) {
                Response::error('Status is required', 400);
            }

            $result = $this->itemModel->updateStatus($id, $data['is_active'], $currentUser['id']);

            if ($result['success']) {
                $this->userModel->logActivity($currentUser['id'], 'item_status_changed', 
                    'Item status changed to ' . ($data['is_active'] ? 'active' : 'inactive'));
                Response::success(null, $result['message']);
            } else {
                Response::error($result['message'], 400);
            }
        } catch (Exception $e) {
            Response::error('Failed to update item status: ' . $e->getMessage(), 500);
        }
    }

    /**
     * PATCH /inventory-items/{id}/price - ອັບເດດລາຄາຂາຍ
     */
    public function updateItemPrice($id) {
        try {
            $currentUser = $this->getCurrentUser();
            $this->checkAdminPermission($currentUser);
            
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['selling_price'])) {
                Response::error('Selling price is required', 400);
            }

            $result = $this->itemModel->updateSellingPrice($id, $data['selling_price'], $currentUser['id']);

            if ($result['success']) {
                $this->userModel->logActivity($currentUser['id'], 'item_price_updated', 
                    'Item price updated to ' . $data['selling_price']);
                Response::success(null, $result['message']);
            } else {
                Response::error($result['message'], 400);
            }
        } catch (Exception $e) {
            Response::error('Failed to update item price: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST /inventory-items/{id}/barcode-image - ອັບໂຫຼດຮູບ barcode
     */
    public function uploadBarcodeImage($id) {
        try {
            $currentUser = $this->getCurrentUser();
            $this->checkAdminPermission($currentUser);

            $item = $this->itemModel->getById($id);
            if (!$item) {
                Response::notFound('Item not found');
            }

            if (!isset($_FILES['barcode_image'])) {
                Response::error('No image file uploaded', 400);
            }

            $file = $_FILES['barcode_image'];
            $uploadResult = $this->fileUploader->upload($file, [
                'allowed_types' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
                'max_size' => 5 * 1024 * 1024, // 5MB
                'prefix' => 'barcode_' . $id
            ]);

            if (!$uploadResult['success']) {
                Response::error($uploadResult['message'], 400);
            }

            $result = $this->itemModel->updateBarcodeImage($id, $uploadResult['path']);

            if ($result['success']) {
                Response::success([
                    'image_url' => $uploadResult['url'],
                    'image_path' => $uploadResult['path']
                ], 'Barcode image uploaded successfully');
            } else {
                Response::error($result['message'], 400);
            }
        } catch (Exception $e) {
            Response::error('Failed to upload barcode image: ' . $e->getMessage(), 500);
        }
    }

    /**
     * DELETE /inventory-items/{id} - ລຶບສິນຄ້າ (soft delete)
     */
    public function deleteItem($id) {
        try {
            $currentUser = $this->getCurrentUser();
            $this->checkAdminPermission($currentUser);
            
            $item = $this->itemModel->getById($id);
            if (!$item) {
                Response::notFound('Item not found');
            }

            $result = $this->itemModel->softDelete($id);

            if ($result['success']) {
                $this->userModel->logActivity($currentUser['id'], 'item_deleted', 
                    'Item deleted: ' . $item['item_name']);
                Response::success(null, $result['message']);
            } else {
                Response::error($result['message'], 400);
            }
        } catch (Exception $e) {
            Response::error('Failed to delete item: ' . $e->getMessage(), 500);
        }
    }

    /**
     * DELETE /inventory-items/{id}/hard - ລຶບສິນຄ້າແບບຖາວອນ
     */
    public function hardDeleteItem($id) {
        try {
            $currentUser = $this->getCurrentUser();
            $this->checkAdminPermission($currentUser);
            
            $item = $this->itemModel->getById($id);
            if (!$item) {
                Response::notFound('Item not found');
            }

            $result = $this->itemModel->hardDelete($id);

            if ($result['success']) {
                $this->userModel->logActivity($currentUser['id'], 'item_permanently_deleted', 
                    'Item permanently deleted: ' . $item['item_name']);
                Response::success(null, $result['message']);
            } else {
                Response::error($result['message'], 400);
            }
        } catch (Exception $e) {
            Response::error('Failed to delete item: ' . $e->getMessage(), 500);
        }
    }

    // ເພີ່ມໃນ InventoryItemController.php
    public function getLatestCode() {
        try {
            $model = new InventoryItem();
            $latestCode = $model->getLatestItemCode();
            
            Response::success([
                'latest_code' => $latestCode
            ], 200, 'Latest code retrieved');
        } catch (Exception $e) {
            Response::error('Failed to get latest code', 500);
        }
    }
}
?>