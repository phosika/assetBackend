<?php
// src/controllers/WoodStockController.php
require_once __DIR__ . '/../models/WoodStock.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Validator.php';

class WoodStockController
{
    private $woodStockModel;

    public function __construct($db)
    {
        $this->woodStockModel = new WoodStock($db);
    }

    /**
     * Parse request data supporting both application/json and multipart/form-data
     */
    private function getRequestData()
    {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (strpos($contentType, 'application/json') !== false) {
            $input = file_get_contents('php://input');
            return json_decode($input, true) ?? [];
        }
        return $_POST;
    }

    /**
     * Handle product image upload
     */
    private function handleImageUpload($file)
    {
        if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'message' => 'No file uploaded or upload error'];
        }

        // Validate size (5MB max)
        if ($file['size'] > 5 * 1024 * 1024) {
            return ['success' => false, 'message' => 'Image file size too large. Max 5MB'];
        }

        // Validate type
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mimeType, $allowedTypes)) {
            return ['success' => false, 'message' => 'Invalid image format. Allowed: JPEG, PNG, GIF, WEBP'];
        }

        // Target path
        $uploadDir = __DIR__ . '/../../uploads/assets';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'product_' . time() . '_' . uniqid() . '.' . $extension;
        $targetPath = $uploadDir . '/' . $filename;
        $dbPath = 'uploads/assets/' . $filename;

        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
            chmod($targetPath, 0644);
            return ['success' => true, 'path' => $dbPath];
        }

        return ['success' => false, 'message' => 'Failed to save uploaded file'];
    }

    /**
     * GET /wood-stocks
     */
    public function listWoodStocks($page = 1, $limit = 10)
    {
        try {
            $currentUser = AuthMiddleware::check();
            if (!$currentUser) return;

            $filters = [
                'search' => $_GET['search'] ?? '',
                'category_id' => $_GET['category_id'] ?? null,
                'sub_category_id' => $_GET['sub_category_id'] ?? null,
                'status' => $_GET['status'] ?? ''
            ];

            $result = $this->woodStockModel->list($page, $limit, $filters);
            return Response::json($result, 200);
        } catch (Exception $e) {
            error_log("WoodStockController::listWoodStocks error: " . $e->getMessage());
            return Response::json(['message' => 'An error occurred'], 500);
        }
    }

    /**
     * GET /wood-stocks/{id}
     */
    public function getWoodStock($id)
    {
        try {
            $currentUser = AuthMiddleware::check();
            if (!$currentUser) return;

            if (empty($id) || !is_numeric($id)) {
                return Response::json(['message' => 'Invalid ID'], 400);
            }

            $item = $this->woodStockModel->findById((int)$id);
            if (!$item) {
                return Response::json(['message' => 'Product stock record not found'], 404);
            }

            return Response::json($item, 200);
        } catch (Exception $e) {
            error_log("WoodStockController::getWoodStock error: " . $e->getMessage());
            return Response::json(['message' => 'An error occurred'], 500);
        }
    }

    /**
     * POST /wood-stocks
     */
    public function createWoodStock()
    {
        try {
            // Require admin, manager, or warehouse_staff
            $currentUser = AuthMiddleware::authenticate(['admin', 'manager', 'warehouse_staff']);
            if (!$currentUser) return;

            $data = $this->getRequestData();

            // Validate fields
            $rules = [
                'serial_number' => 'required|string|max:100',
                'sub_category_id' => 'required|numeric',
                'width_inch' => 'numeric',
                'length_ft' => 'numeric',
                'cubic_ft' => 'numeric',
                'buy_rate' => 'numeric',
                'sell_rate' => 'numeric',
                'status' => 'string|in:available,sold,reserved,damaged',
                'qty' => 'numeric'
            ];

            $errors = Validator::validate($data, $rules);
            if (!empty($errors)) {
                return Response::json(['errors' => $errors], 422);
            }

            // Check if serial number already exists
            if ($this->woodStockModel->exists($data['serial_number'])) {
                return Response::json(['message' => 'Serial number already exists'], 409);
            }

            // Handle image upload if provided
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $uploadResult = $this->handleImageUpload($_FILES['image']);
                if ($uploadResult['success']) {
                    $data['image'] = $uploadResult['path'];
                } else {
                    return Response::json(['message' => $uploadResult['message']], 400);
                }
            }

            $data['created_by'] = $currentUser['id'];
            $newId = $this->woodStockModel->create($data);

            if (!$newId) {
                return Response::json(['message' => 'Failed to create product stock record'], 500);
            }

            $newRecord = $this->woodStockModel->findById($newId);
            return Response::json([
                'message' => 'Product stock record created successfully',
                'data' => $newRecord
            ], 201);

        } catch (Exception $e) {
            error_log("WoodStockController::createWoodStock error: " . $e->getMessage());
            return Response::json(['message' => 'An error occurred: ' . $e->getMessage()], 500);
        }
    }

    /**
     * PUT/POST /wood-stocks/{id}
     * Note: In PHP, PUT requests do not parse multipart/form-data files automatically.
     * To support image upload in updates, users can send a POST request to `/wood-stocks/{id}` with a method override or we just support POST to update.
     */
    public function updateWoodStock($id)
    {
        try {
            // Require admin, manager, or warehouse_staff
            $currentUser = AuthMiddleware::authenticate(['admin', 'manager', 'warehouse_staff']);
            if (!$currentUser) return;

            if (empty($id) || !is_numeric($id)) {
                return Response::json(['message' => 'Invalid ID'], 400);
            }

            $item = $this->woodStockModel->findById((int)$id);
            if (!$item) {
                return Response::json(['message' => 'Product stock record not found'], 404);
            }

            $data = $this->getRequestData();

            // Validate fields
            $rules = [
                'serial_number' => 'string|max:100',
                'sub_category_id' => 'numeric',
                'width_inch' => 'numeric',
                'length_ft' => 'numeric',
                'cubic_ft' => 'numeric',
                'buy_rate' => 'numeric',
                'sell_rate' => 'numeric',
                'status' => 'string|in:available,sold,reserved,damaged',
                'qty' => 'numeric'
            ];

            $errors = Validator::validate($data, $rules);
            if (!empty($errors)) {
                return Response::json(['errors' => $errors], 422);
            }

            // Check unique serial number if changed
            if (isset($data['serial_number']) && $data['serial_number'] !== $item['serial_number']) {
                if ($this->woodStockModel->exists($data['serial_number'], $id)) {
                    return Response::json(['message' => 'Serial number already exists'], 409);
                }
            }

            // Handle image upload if provided
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $uploadResult = $this->handleImageUpload($_FILES['image']);
                if ($uploadResult['success']) {
                    $data['image'] = $uploadResult['path'];
                    
                    // Delete old image file
                    if (!empty($item['image'])) {
                        $oldImagePath = __DIR__ . '/../../' . $item['image'];
                        if (file_exists($oldImagePath)) {
                            @unlink($oldImagePath);
                        }
                    }
                } else {
                    return Response::json(['message' => $uploadResult['message']], 400);
                }
            }

            $success = $this->woodStockModel->update((int)$id, $data);
            if (!$success) {
                return Response::json(['message' => 'Failed to update record or no changes made'], 400);
            }

            $updatedRecord = $this->woodStockModel->findById((int)$id);
            return Response::json([
                'message' => 'Product stock record updated successfully',
                'data' => $updatedRecord
            ], 200);

        } catch (Exception $e) {
            error_log("WoodStockController::updateWoodStock error: " . $e->getMessage());
            return Response::json(['message' => 'An error occurred: ' . $e->getMessage()], 500);
        }
    }

    /**
     * DELETE /wood-stocks/{id}
     */
    public function deleteWoodStock($id)
    {
        try {
            // Require admin or manager
            $currentUser = AuthMiddleware::authenticate(['admin', 'manager']);
            if (!$currentUser) return;

            if (empty($id) || !is_numeric($id)) {
                return Response::json(['message' => 'Invalid ID'], 400);
            }

            $item = $this->woodStockModel->findById((int)$id);
            if (!$item) {
                return Response::json(['message' => 'Product stock record not found'], 404);
            }

            $success = $this->woodStockModel->delete((int)$id);
            if (!$success) {
                return Response::json(['message' => 'Failed to delete record'], 500);
            }

            // Delete image file from disk
            if (!empty($item['image'])) {
                $imagePath = __DIR__ . '/../../' . $item['image'];
                if (file_exists($imagePath)) {
                    @unlink($imagePath);
                }
            }

            return Response::json(['message' => 'Product stock record deleted successfully'], 200);
        } catch (Exception $e) {
            error_log("WoodStockController::deleteWoodStock error: " . $e->getMessage());
            return Response::json(['message' => 'An error occurred'], 500);
        }
    }

    /**
     * PATCH /wood-stocks/{id}/status
     */
    public function updateStatus($id)
    {
        try {
            // Require logged in user with access (admin, manager, cashier, warehouse_staff)
            $currentUser = AuthMiddleware::authenticate(['admin', 'manager', 'cashier', 'warehouse_staff']);
            if (!$currentUser) return;

            if (empty($id) || !is_numeric($id)) {
                return Response::json(['message' => 'Invalid ID'], 400);
            }

            $item = $this->woodStockModel->findById((int)$id);
            if (!$item) {
                return Response::json(['message' => 'Product stock record not found'], 404);
            }

            $data = $this->getRequestData();
            if (!isset($data['status'])) {
                return Response::json(['message' => 'Status field is required'], 400);
            }

            $allowedStatus = ['available', 'sold', 'reserved', 'damaged'];
            if (!in_array($data['status'], $allowedStatus)) {
                return Response::json(['message' => 'Invalid status. Must be: ' . implode(', ', $allowedStatus)], 400);
            }

            $success = $this->woodStockModel->updateStatus((int)$id, $data['status']);
            if (!$success) {
                return Response::json(['message' => 'Failed to update status'], 500);
            }

            return Response::json([
                'message' => 'Status updated successfully',
                'id' => (int)$id,
                'status' => $data['status']
            ], 200);

        } catch (Exception $e) {
            error_log("WoodStockController::updateStatus error: " . $e->getMessage());
            return Response::json(['message' => 'An error occurred'], 500);
        }
    }
}
?>
