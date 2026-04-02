<?php
// src/controllers/AssetController.php

require_once __DIR__ . '/../models/Asset.php';
require_once __DIR__ . '/../models/AssetDocument.php';
require_once __DIR__ . '/../models/AssetImage.php';
require_once __DIR__ . '/../models/Barcode.php';
require_once __DIR__ . '/../models/CategoryAttribute.php';
require_once __DIR__ . '/../models/CategoryInheritance.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Validator.php';
require_once __DIR__ . '/../utils/FileUploader.php';

class AssetController {
    private $assetModel;
    private $documentModel;
    private $imageModel;
    private $barcodeModel;
    private $categoryAttributeModel;
    private $categoryInheritanceModel;
    private $userModel;
    private $validator;
    private $fileUploader;

    public function __construct() {
        $this->assetModel = new Asset();
        $this->documentModel = new AssetDocument();
        $this->imageModel = new AssetImage();
        $this->barcodeModel = new Barcode();
        $this->categoryAttributeModel = new CategoryAttribute();
        $this->categoryInheritanceModel = new CategoryInheritance();
        $this->userModel = new User();
        $this->validator = new Validator();
        $this->fileUploader = new FileUploader('uploads/assets');
    }

    /**
     * ດຶງຂໍ້ມູນຜູ້ໃຊ້ປັດຈຸບັນ
     */
    private function getCurrentUser() {
        $payload = AuthMiddleware::authenticate();
        $userId = is_array($payload) ? ($payload['user_id'] ?? null) : $payload;

        if (!$userId) {
            Response::unauthorized('Invalid authentication payload');
        }

        $user = $this->userModel->getById($userId);
        
        if (!$user) {
            Response::unauthorized('User not found');
        }
        
        return $user;
    }

    /**
     * GET /assets - ດຶງຂໍ້ມູນຊັບສິນທັງໝົດ
     */
    public function getAllAssets() {
        try {
            $currentUser = $this->getCurrentUser();
            
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
            $search = isset($_GET['search']) ? $_GET['search'] : '';
            $company_id = isset($_GET['company_id']) ? (int)$_GET['company_id'] : 0;
            $department_id = isset($_GET['department_id']) ? (int)$_GET['department_id'] : 0;
            $category_id = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;
            $status = isset($_GET['status']) ? $_GET['status'] : '';
            $condition = isset($_GET['condition']) ? $_GET['condition'] : '';
            $user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
            $sort_by = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'created_at';
            $sort_order = isset($_GET['sort_order']) ? strtoupper($_GET['sort_order']) : 'DESC';

            $filters = [
                'search' => $search,
                'company_id' => $company_id,
                'department_id' => $department_id,
                'category_id' => $category_id,
                'status' => $status,
                'asset_condition' => $condition,
                'current_user_id' => $user_id,
                'page' => $page,
                'limit' => $limit,
                'sort_by' => $sort_by,
                'sort_order' => $sort_order
            ];

            $result = $this->assetModel->getAllAssets(
                $filters, 
                $currentUser['role'], 
                $currentUser['department_id'],
                $currentUser['id']
            );

            Response::success([
                'assets' => $result['data'],
                'pagination' => [
                    'current_page' => $result['current_page'],
                    'per_page' => $result['per_page'],
                    'total' => $result['total'],
                    'total_pages' => $result['last_page'],
                    'from' => $result['from'],
                    'to' => $result['to']
                ],
                'filters' => $filters
            ], 'Assets retrieved successfully');
        } catch (Exception $e) {
            error_log("Error in getAllAssets: " . $e->getMessage());
            Response::error('Failed to retrieve assets: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /assets/stats - ສະຖິຕິຊັບສິນ
     */
    public function getAssetStats() {
        try {
            $currentUser = $this->getCurrentUser();

            $stats = $this->assetModel->getAssetStats(
                $currentUser['role'],
                $currentUser['department_id'],
                $currentUser['id']
            );
            
            Response::success($stats, 'Asset statistics retrieved successfully');
        } catch (Exception $e) {
            Response::error('Failed to retrieve statistics: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /assets/search - ຄົ້ນຫາຊັບສິນ
     */
    public function searchAssets() {
        try {
            $currentUser = $this->getCurrentUser();
            
            $keyword = isset($_GET['q']) ? $_GET['q'] : '';
            
            if (empty($keyword)) {
                Response::error('Search keyword is required', 400);
            }

            $assets = $this->assetModel->searchAssets(
                $keyword,
                $currentUser['role'],
                $currentUser['department_id'],
                $currentUser['id']
            );
            
            Response::success([
                'assets' => $assets,
                'total' => count($assets),
                'keyword' => $keyword
            ], 'Search completed successfully');
        } catch (Exception $e) {
            Response::error('Failed to search assets: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /assets/by-user/{userId} - ດຶງຊັບສິນຕາມຜູ້ຖືຄອງ
     */
    public function getAssetsByUser($userId) {
        try {
            $currentUser = $this->getCurrentUser();
            
            // ກວດສອບສິດ
            if ($currentUser['role'] === 'employee' && $currentUser['id'] != $userId) {
                Response::forbidden('You can only view your own assets');
            }

            $assets = $this->assetModel->getByUserId($userId);
            
            Response::success([
                'assets' => $assets,
                'total' => count($assets),
                'user_id' => $userId
            ], 'Assets retrieved successfully');
        } catch (Exception $e) {
            Response::error('Failed to retrieve assets: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /assets/by-department/{departmentId} - ດຶງຊັບສິນຕາມພະແນກ
     */
    public function getAssetsByDepartment($departmentId) {
        try {
            $currentUser = $this->getCurrentUser();
            
            // ກວດສອບສິດ
            if (($currentUser['role'] === 'department_head' || $currentUser['role'] === 'manager') 
                && $currentUser['department_id'] != $departmentId) {
                Response::forbidden('You can only view assets in your department');
            }

            $assets = $this->assetModel->getByDepartmentId($departmentId);
            
            Response::success([
                'assets' => $assets,
                'total' => count($assets),
                'department_id' => $departmentId
            ], 'Assets retrieved successfully');
        } catch (Exception $e) {
            Response::error('Failed to retrieve assets: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /assets/{id} - ດຶງຂໍ້ມູນຊັບສິນຕາມ ID
     */
    public function getAssetById($id) {
        try {
            $currentUser = $this->getCurrentUser();

            $asset = $this->assetModel->getById($id);

            if (!$asset) {
                Response::notFound('Asset not found');
            }

            Response::success($asset, 'Asset retrieved successfully');
        } catch (Exception $e) {
            Response::error('Failed to retrieve asset: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /assets/by-barcode/{barcode} - ດຶງຊັບສິນຕາມ barcode
     */
    public function getAssetByBarcode($barcode) {
        try {
            $currentUser = $this->getCurrentUser();

            $asset = $this->assetModel->getByBarcode($barcode);

            if (!$asset) {
                Response::notFound('Asset not found');
            }

            // ກວດສອບສິດ (ຄືກັບ getAssetById)
            if ($currentUser['role'] === 'employee' && $asset['current_user_id'] != $currentUser['id']) {
                Response::forbidden('You can only view your own assets');
            }

            if (($currentUser['role'] === 'department_head' || $currentUser['role'] === 'manager') 
                && $asset['department_id'] != $currentUser['department_id']) {
                Response::forbidden('You can only view assets in your department');
            }

            Response::success($asset, 'Asset retrieved successfully');
        } catch (Exception $e) {
            Response::error('Failed to retrieve asset: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /assets/by-rfid/{rfid} - ດຶງຊັບສິນຕາມ RFID
     */
    public function getAssetByRFID($rfid) {
        try {
            $currentUser = $this->getCurrentUser();

            $asset = $this->assetModel->getByRFID($rfid);

            if (!$asset) {
                Response::notFound('Asset not found');
            }

            // ກວດສອບສິດ (ຄືກັບ getAssetById)
            if ($currentUser['role'] === 'employee' && $asset['current_user_id'] != $currentUser['id']) {
                Response::forbidden('You can only view your own assets');
            }

            if (($currentUser['role'] === 'department_head' || $currentUser['role'] === 'manager') 
                && $asset['department_id'] != $currentUser['department_id']) {
                Response::forbidden('You can only view assets in your department');
            }

            Response::success($asset, 'Asset retrieved successfully');
        } catch (Exception $e) {
            Response::error('Failed to retrieve asset: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /assets/by-serial/{serial} - ດຶງຊັບສິນຕາມ serial number
     */
    public function getAssetBySerial($serial) {
        try {
            $currentUser = $this->getCurrentUser();

            $asset = $this->assetModel->getBySerialNumber($serial);

            if (!$asset) {
                Response::notFound('Asset not found');
            }

            // ກວດສອບສິດ (ຄືກັບ getAssetById)
            if ($currentUser['role'] === 'employee' && $asset['current_user_id'] != $currentUser['id']) {
                Response::forbidden('You can only view your own assets');
            }

            if (($currentUser['role'] === 'department_head' || $currentUser['role'] === 'manager') 
                && $asset['department_id'] != $currentUser['department_id']) {
                Response::forbidden('You can only view assets in your department');
            }

            Response::success($asset, 'Asset retrieved successfully');
        } catch (Exception $e) {
            Response::error('Failed to retrieve asset: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST /assets - ສ້າງຊັບສິນໃໝ່
     */
    public function createAsset() {
        try {
            $currentUser = $this->getCurrentUser();
            
            // ກວດສອບສິດ
            if (!in_array($currentUser['role'], ['super_admin', 'asset_admin'])) {
                Response::forbidden('You do not have permission to create assets');
            }
            
            $data = json_decode(file_get_contents('php://input'), true);

            // ກວດສອບຂໍ້ມູນທີ່ຈຳເປັນ
            if (empty($data['asset_name'])) {
                Response::error('Asset name is required', 400);
            }

            if (empty($data['category_id'])) {
                Response::error('Category ID is required', 400);
            }

            $result = $this->assetModel->create($data, $currentUser['id']);

            if ($result['success']) {
                $asset = $this->assetModel->getById($result['asset_id']);
                Response::success($asset, 'Asset created successfully', 201);
            } else {
                Response::error($result['message'], 400);
            }
        } catch (Exception $e) {
            Response::error('Failed to create asset: ' . $e->getMessage(), 500);
        }
    }

    /**
     * PUT /assets/{id} - ອັບເດດຂໍ້ມູນຊັບສິນ
     */
    public function updateAsset($id) {
        try {
            $currentUser = $this->getCurrentUser();
            
            // ກວດສອບສິດ
            if (!in_array($currentUser['role'], ['super_admin', 'asset_admin'])) {
                Response::forbidden('You do not have permission to update assets');
            }
            
            $asset = $this->assetModel->getById($id);
            if (!$asset) {
                Response::notFound('Asset not found');
            }

            $data = json_decode(file_get_contents('php://input'), true);

            $result = $this->assetModel->update($id, $data, $currentUser['id']);

            if ($result['success']) {
                $this->userModel->logActivity($currentUser['id'], 'asset_updated', 
                    'Asset updated: ' . ($data['asset_name'] ?? $asset['asset_name']));
                
                $updatedAsset = $this->assetModel->getById($id);
                Response::success($updatedAsset, $result['message']);
            } else {
                Response::error($result['message'], 400);
            }
        } catch (Exception $e) {
            Response::error('Failed to update asset: ' . $e->getMessage(), 500);
        }
    }

    /**
     * PATCH /assets/{id}/status - ອັບເດດສະຖານະ
     */
    public function updateAssetStatus($id) {
        try {
            $currentUser = $this->getCurrentUser();
            
            if (!in_array($currentUser['role'], ['super_admin', 'asset_admin'])) {
                Response::forbidden('You do not have permission to update asset status');
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['status'])) {
                Response::error('Status is required', 400);
            }

            $result = $this->assetModel->updateStatus($id, $data['status'], $currentUser['id']);

            if ($result['success']) {
                $this->userModel->logActivity($currentUser['id'], 'asset_status_changed', 
                    'Asset status changed to ' . $data['status']);
                Response::success(null, $result['message']);
            } else {
                Response::error($result['message'], 400);
            }
        } catch (Exception $e) {
            Response::error('Failed to update asset status: ' . $e->getMessage(), 500);
        }
    }

    /**
     * PATCH /assets/{id}/condition - ອັບເດດສະພາບ
     */
    public function updateAssetCondition($id) {
        try {
            $currentUser = $this->getCurrentUser();
            
            if (!in_array($currentUser['role'], ['super_admin', 'asset_admin'])) {
                Response::forbidden('You do not have permission to update asset condition');
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['condition'])) {
                Response::error('Condition is required', 400);
            }

            $notes = $data['notes'] ?? null;

            $result = $this->assetModel->updateCondition($id, $data['condition'], $notes, $currentUser['id']);

            if ($result['success']) {
                $this->userModel->logActivity($currentUser['id'], 'asset_condition_changed', 
                    'Asset condition changed to ' . $data['condition']);
                Response::success(null, $result['message']);
            } else {
                Response::error($result['message'], 400);
            }
        } catch (Exception $e) {
            Response::error('Failed to update asset condition: ' . $e->getMessage(), 500);
        }
    }

    /**
     * PATCH /assets/{id}/user - ອັບເດດຜູ້ຖືຄອງ
     */
    public function updateAssetUser($id) {
        try {
            $currentUser = $this->getCurrentUser();
            
            if (!in_array($currentUser['role'], ['super_admin', 'asset_admin'])) {
                Response::forbidden('You do not have permission to change asset user');
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['user_id'])) {
                Response::error('User ID is required', 400);
            }

            $result = $this->assetModel->updateCurrentUser($id, $data['user_id'], $currentUser['id']);

            if ($result['success']) {
                $this->userModel->logActivity($currentUser['id'], 'asset_user_changed', 
                    'Asset user changed to user ID: ' . $data['user_id']);
                Response::success(null, $result['message']);
            } else {
                Response::error($result['message'], 400);
            }
        } catch (Exception $e) {
            Response::error('Failed to update asset user: ' . $e->getMessage(), 500);
        }
    }

    /**
     * PATCH /assets/{id}/location - ອັບເດດສະຖານທີ່
     */
    public function updateAssetLocation($id) {
        try {
            $currentUser = $this->getCurrentUser();
            
            if (!in_array($currentUser['role'], ['super_admin', 'asset_admin'])) {
                Response::forbidden('You do not have permission to update asset location');
            }
            
            $data = json_decode(file_get_contents('php://input'), true);

            $result = $this->assetModel->updateLocation($id, $data, $currentUser['id']);

            if ($result['success']) {
                $this->userModel->logActivity($currentUser['id'], 'asset_location_updated', 
                    'Asset location updated');
                Response::success(null, $result['message']);
            } else {
                Response::error($result['message'], 400);
            }
        } catch (Exception $e) {
            Response::error('Failed to update asset location: ' . $e->getMessage(), 500);
        }
    }

    /**
     * PATCH /assets/{id}/warranty - ອັບເດດການຮັບປະກັນ
     */
    public function updateAssetWarranty($id) {
        try {
            $currentUser = $this->getCurrentUser();
            
            if (!in_array($currentUser['role'], ['super_admin', 'asset_admin'])) {
                Response::forbidden('You do not have permission to update asset warranty');
            }
            
            $data = json_decode(file_get_contents('php://input'), true);

            $result = $this->assetModel->updateWarranty($id, $data, $currentUser['id']);

            if ($result['success']) {
                $this->userModel->logActivity($currentUser['id'], 'asset_warranty_updated', 
                    'Asset warranty updated');
                Response::success(null, $result['message']);
            } else {
                Response::error($result['message'], 400);
            }
        } catch (Exception $e) {
            Response::error('Failed to update asset warranty: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST /assets/{id}/verify - ກວດສອບຊັບສິນ
     */
    public function verifyAsset($id) {
        try {
            $currentUser = $this->getCurrentUser();
            
            if (!in_array($currentUser['role'], ['super_admin', 'asset_admin'])) {
                Response::forbidden('You do not have permission to verify assets');
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            $notes = $data['notes'] ?? null;

            $result = $this->assetModel->verify($id, $currentUser['id'], $notes);

            if ($result['success']) {
                $this->userModel->logActivity($currentUser['id'], 'asset_verified', 
                    'Asset verified');
                Response::success(null, $result['message']);
            } else {
                Response::error($result['message'], 400);
            }
        } catch (Exception $e) {
            Response::error('Failed to verify asset: ' . $e->getMessage(), 500);
        }
    }

    /**
     * DELETE /assets/{id} - ລຶບຊັບສິນ (soft delete)
     */
    public function deleteAsset($id) {
        try {
            $currentUser = $this->getCurrentUser();
            
            if (!in_array($currentUser['role'], ['super_admin', 'asset_admin'])) {
                Response::forbidden('You do not have permission to delete assets');
            }
            
            $asset = $this->assetModel->getById($id);
            if (!$asset) {
                Response::notFound('Asset not found');
            }

            $result = $this->assetModel->softDelete($id);

            if ($result['success']) {
                $this->userModel->logActivity($currentUser['id'], 'asset_deleted', 
                    'Asset deleted: ' . $asset['asset_name']);
                Response::success(null, $result['message']);
            } else {
                Response::error($result['message'], 400);
            }
        } catch (Exception $e) {
            Response::error('Failed to delete asset: ' . $e->getMessage(), 500);
        }
    }

    // ==================== DOCUMENTS ====================

    /**
     * GET /assets/{assetId}/documents - ດຶງເອກະສານຂອງຊັບສິນ
     */
    public function getAssetDocuments($assetId) {
        try {
            $this->getCurrentUser();
            
            $documents = $this->documentModel->getByAssetId($assetId);
            
            Response::success($documents, 'Documents retrieved successfully');
        } catch (Exception $e) {
            Response::error('Failed to retrieve documents: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST /assets/{assetId}/documents - ອັບໂຫຼດເອກະສານ
     */
    public function uploadDocument($assetId) {
        try {
            $currentUser = $this->getCurrentUser();
            
            if (!in_array($currentUser['role'], ['super_admin', 'asset_admin'])) {
                Response::forbidden('You do not have permission to upload documents');
            }

            if (!isset($_FILES['document'])) {
                Response::error('No document file uploaded', 400);
            }

            $file = $_FILES['document'];
            $uploadResult = $this->fileUploader->upload($file, [
                'allowed_types' => ['application/pdf', 'image/jpeg', 'image/png', 
                                    'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                                    'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
                'max_size' => 20 * 1024 * 1024, // 20MB
                'prefix' => 'asset_' . $assetId . '_doc'
            ]);

            if (!$uploadResult['success']) {
                Response::error($uploadResult['message'], 400);
            }

            $data = [
                'asset_id' => $assetId,
                'document_name' => $_POST['document_name'] ?? $file['name'],
                'document_type' => $_POST['document_type'] ?? 'other',
                'file_path' => $uploadResult['path'],
                'file_size' => $uploadResult['size'],
                'mime_type' => $uploadResult['mime'],
                'expiry_date' => $_POST['expiry_date'] ?? null,
                'is_confidential' => $_POST['is_confidential'] ?? 0,
                'notes' => $_POST['notes'] ?? null
            ];

            $result = $this->documentModel->create($data, $currentUser['id']);

            if ($result['success']) {
                Response::success([
                    'document_id' => $result['document_id'],
                    'file_url' => $uploadResult['url']
                ], 'Document uploaded successfully');
            } else {
                Response::error($result['message'], 400);
            }
        } catch (Exception $e) {
            Response::error('Failed to upload document: ' . $e->getMessage(), 500);
        }
    }

    /**
     * DELETE /assets/documents/{documentId} - ລຶບເອກະສານ
     */
    public function deleteDocument($documentId) {
        try {
            $currentUser = $this->getCurrentUser();
            
            if (!in_array($currentUser['role'], ['super_admin', 'asset_admin'])) {
                Response::forbidden('You do not have permission to delete documents');
            }

            $result = $this->documentModel->delete($documentId);

            if ($result['success']) {
                Response::success(null, 'Document deleted successfully');
            } else {
                Response::error($result['message'], 400);
            }
        } catch (Exception $e) {
            Response::error('Failed to delete document: ' . $e->getMessage(), 500);
        }
    }

    // ==================== IMAGES ====================

    /**
     * GET /assets/{assetId}/images - ດຶງຮູບພາບຂອງຊັບສິນ
     */
    public function getAssetImages($assetId) {
        try {
            $this->getCurrentUser();
            
            $images = $this->imageModel->getByAssetId($assetId);
            
            Response::success($images, 'Images retrieved successfully');
        } catch (Exception $e) {
            Response::error('Failed to retrieve images: ' . $e->getMessage(), 500);
        }
    }

    // ໃນ AssetController.php, ສຳລັບອັບໂຫຼດຮູບພາບ
    public function uploadImage($assetId) {
        try {
            $currentUser = $this->getCurrentUser();
            
            if (!in_array($currentUser['role'], ['super_admin', 'asset_admin'])) {
                Response::forbidden('You do not have permission to upload images');
            }

            if (!isset($_FILES['image'])) {
                Response::error('No image file uploaded', 400);
            }

            // ສ້າງ FileUploader ສຳລັບ assets (ຈະເກັບໃນ src/uploads/assets)
            $fileUploader = new FileUploader('assets');
            
            $file = $_FILES['image'];
            $uploadResult = $fileUploader->upload($file, [
                'allowed_types' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
                'max_size' => 10 * 1024 * 1024, // 10MB
                'prefix' => 'asset_' . $assetId
            ]);

            if (!$uploadResult['success']) {
                Response::error($uploadResult['message'], 400);
            }

            // URL ທີ່ໄດ້ຈະເປັນ: http://localhost:8080/src/uploads/assets/asset_1_20260304_123456_abc123.jpg
            Response::success([
                'image_url' => $uploadResult['url']
            ], 'Image uploaded successfully');
            
        } catch (Exception $e) {
            Response::error('Failed to upload image: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST /assets/images/{imageId}/primary - ຕັ້ງຮູບຫຼັກ
     */
    public function setPrimaryImage($imageId) {
        try {
            $currentUser = $this->getCurrentUser();
            
            if (!in_array($currentUser['role'], ['super_admin', 'asset_admin'])) {
                Response::forbidden('You do not have permission to set primary image');
            }

            $image = $this->imageModel->getById($imageId);
            if (!$image) {
                Response::notFound('Image not found');
            }

            $result = $this->imageModel->setPrimaryImage($imageId, $image['asset_id']);

            if ($result['success']) {
                Response::success(null, 'Primary image set successfully');
            } else {
                Response::error($result['message'], 400);
            }
        } catch (Exception $e) {
            Response::error('Failed to set primary image: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST /assets/images/reorder - ຈັດລຽງລຳດັບຮູບ
     */
    public function reorderImages() {
        try {
            $currentUser = $this->getCurrentUser();
            
            if (!in_array($currentUser['role'], ['super_admin', 'asset_admin'])) {
                Response::forbidden('You do not have permission to reorder images');
            }

            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['asset_id']) || !isset($data['image_ids'])) {
                Response::error('Asset ID and image IDs are required', 400);
            }

            $result = $this->imageModel->reorderImages($data['asset_id'], $data['image_ids']);

            if ($result['success']) {
                Response::success(null, 'Images reordered successfully');
            } else {
                Response::error($result['message'], 400);
            }
        } catch (Exception $e) {
            Response::error('Failed to reorder images: ' . $e->getMessage(), 500);
        }
    }

    /**
     * DELETE /assets/images/{imageId} - ລຶບຮູບພາບ
     */
    public function deleteImage($imageId) {
        try {
            $currentUser = $this->getCurrentUser();
            
            if (!in_array($currentUser['role'], ['super_admin', 'asset_admin'])) {
                Response::forbidden('You do not have permission to delete images');
            }

            $result = $this->imageModel->delete($imageId);

            if ($result['success']) {
                Response::success(null, 'Image deleted successfully');
            } else {
                Response::error($result['message'], 400);
            }
        } catch (Exception $e) {
            Response::error('Failed to delete image: ' . $e->getMessage(), 500);
        }
    }

    // ==================== BARCODE ====================

    /**
     * POST /assets/{assetId}/barcode - ສ້າງ barcode ໃຫ້ຊັບສິນ
     */
    public function generateBarcode($assetId) {
        try {
            $currentUser = $this->getCurrentUser();
            
            if (!in_array($currentUser['role'], ['super_admin', 'asset_admin'])) {
                Response::forbidden('You do not have permission to generate barcodes');
            }

            $asset = $this->assetModel->getById($assetId);
            if (!$asset) {
                Response::notFound('Asset not found');
            }

            $data = json_decode(file_get_contents('php://input'), true);
            
            $barcodeData = [
                'barcode' => $data['barcode'] ?? $asset['asset_code'],
                'barcode_type' => $data['barcode_type'] ?? 'code128',
                'reference_type' => 'asset',
                'reference_id' => $assetId,
                'generated_for' => $asset['asset_name']
            ];

            $result = $this->barcodeModel->generate($barcodeData, $currentUser['id']);

            if ($result['success']) {
                // ອັບເດດ barcode ໃນຕາຕະລາງ assets
                $this->assetModel->update($assetId, ['barcode' => $barcodeData['barcode']], $currentUser['id']);
                
                Response::success([
                    'barcode_id' => $result['barcode_id'],
                    'barcode' => $barcodeData['barcode']
                ], 'Barcode generated successfully');
            } else {
                Response::error($result['message'], 400);
            }
        } catch (Exception $e) {
            Response::error('Failed to generate barcode: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST /barcode/scan - ບັນທຶກການສະແກນ barcode
     */
    public function recordScan() {
        try {
            $currentUser = $this->getCurrentUser();
            
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['barcode'])) {
                Response::error('Barcode is required', 400);
            }

            $result = $this->barcodeModel->recordScan($data, $currentUser['id']);

            if ($result['success']) {
                Response::success([
                    'scan_id' => $result['scan_id']
                ], 'Scan recorded successfully');
            } else {
                Response::error($result['message'], 400);
            }
        } catch (Exception $e) {
            Response::error('Failed to record scan: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /barcode/scans - ດຶງປະຫວັດການສະແກນ
     */
    public function getScanHistory() {
        try {
            $currentUser = $this->getCurrentUser();
            
            $barcode = isset($_GET['barcode']) ? $_GET['barcode'] : null;
            $reference_type = isset($_GET['reference_type']) ? $_GET['reference_type'] : null;
            $reference_id = isset($_GET['reference_id']) ? (int)$_GET['reference_id'] : 0;

            $scans = $this->barcodeModel->getScanHistory($barcode, $reference_type, $reference_id);
            
            Response::success($scans, 'Scan history retrieved successfully');
        } catch (Exception $e) {
            Response::error('Failed to retrieve scan history: ' . $e->getMessage(), 500);
        }
    }
}
?>