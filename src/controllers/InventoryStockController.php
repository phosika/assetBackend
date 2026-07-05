<?php
// src/controllers/InventoryStockController.php
require_once __DIR__ . '/../models/InventoryStock.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Validator.php';

class InventoryStockController
{
    private $inventoryStockModel;

    public function __construct($db)
    {
        $this->inventoryStockModel = new InventoryStock($db);
    }

    private function getRequestData()
    {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (strpos($contentType, 'application/json') !== false) {
            $input = file_get_contents('php://input');
            return json_decode($input, true) ?? [];
        }
        return $_POST;
    }

    private function handleImageUpload($file)
    {
        if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'message' => 'No file uploaded or upload error'];
        }

        if ($file['size'] > 5 * 1024 * 1024) {
            return ['success' => false, 'message' => 'Image file size too large. Max 5MB'];
        }

        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mimeType, $allowedTypes)) {
            return ['success' => false, 'message' => 'Invalid image format. Allowed: JPEG, PNG, GIF, WEBP'];
        }

        $uploadDir = __DIR__ . '/../../uploads/assets';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'stock_' . time() . '_' . uniqid() . '.' . $extension;
        $targetPath = $uploadDir . '/' . $filename;
        $dbPath = 'uploads/assets/' . $filename;

        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
            chmod($targetPath, 0644);
            return ['success' => true, 'path' => $dbPath];
        }

        return ['success' => false, 'message' => 'Failed to save uploaded file'];
    }

    /**
     * GET /inventory-stocks
     */
    public function listStocks($page = 1, $limit = 10)
    {
        try {
            $currentUser = AuthMiddleware::check();
            if (!$currentUser) return;

            $filters = [
                'search' => $_GET['search'] ?? '',
                'barcode' => $_GET['barcode'] ?? '',
                'product_id' => $_GET['product_id'] ?? null,
                'category_id' => $_GET['category_id'] ?? null,
                'sub_category_id' => $_GET['sub_category_id'] ?? null,
                'status' => $_GET['status'] ?? ''
            ];

            $result = $this->inventoryStockModel->list($page, $limit, $filters);
            return Response::json($result, 200);
        } catch (Exception $e) {
            error_log("InventoryStockController::listStocks error: " . $e->getMessage());
            return Response::json(['message' => 'An error occurred'], 500);
        }
    }

    /**
     * GET /inventory-stocks/{id}
     */
    public function getStock($id)
    {
        try {
            $currentUser = AuthMiddleware::check();
            if (!$currentUser) return;

            if (empty($id) || !is_numeric($id)) {
                return Response::json(['message' => 'Invalid ID'], 400);
            }

            $item = $this->inventoryStockModel->findById((int)$id);
            if (!$item) {
                return Response::json(['message' => 'Inventory record not found'], 404);
            }

            return Response::json($item, 200);
        } catch (Exception $e) {
            error_log("InventoryStockController::getStock error: " . $e->getMessage());
            return Response::json(['message' => 'An error occurred'], 500);
        }
    }

    /**
     * GET /inventory-stocks/barcode/{barcode}
     * Retrieve first available stock item by barcode (scan)
     */
    public function getStockByBarcode($barcode)
    {
        try {
            $currentUser = AuthMiddleware::check();
            if (!$currentUser) return;

            if (empty($barcode)) {
                return Response::json(['message' => 'Barcode is required'], 400);
            }

            $item = $this->inventoryStockModel->findByBarcode($barcode);
            if (!$item) {
                return Response::json(['message' => 'No available stock item found for this barcode'], 404);
            }

            return Response::json($item, 200);
        } catch (Exception $e) {
            error_log("InventoryStockController::getStockByBarcode error: " . $e->getMessage());
            return Response::json(['message' => 'An error occurred'], 500);
        }
    }

    /**
     * POST /inventory-stocks
     */
    public function createStock()
    {
        try {
            $currentUser = AuthMiddleware::authenticate(['admin', 'manager', 'warehouse_staff']);
            if (!$currentUser) return;

            $data = $this->getRequestData();

            $rules = [
                'serial_number' => 'required|string|max:100',
                'product_id' => 'required|numeric',
                'purchase_id' => 'numeric',
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

            // Check unique serial number
            if ($this->inventoryStockModel->exists($data['serial_number'])) {
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
            $newId = $this->inventoryStockModel->create($data);

            if (!$newId) {
                return Response::json(['message' => 'Failed to create inventory stock record'], 500);
            }

            $newRecord = $this->inventoryStockModel->findById($newId);
            return Response::json([
                'message' => 'Inventory stock record created successfully',
                'data' => $newRecord
            ], 201);

        } catch (Exception $e) {
            error_log("InventoryStockController::createStock error: " . $e->getMessage());
            return Response::json(['message' => 'An error occurred: ' . $e->getMessage()], 500);
        }
    }

    /**
     * PUT/POST /inventory-stocks/{id}
     */
    public function updateStock($id)
    {
        try {
            $currentUser = AuthMiddleware::authenticate(['admin', 'manager', 'warehouse_staff']);
            if (!$currentUser) return;

            if (empty($id) || !is_numeric($id)) {
                return Response::json(['message' => 'Invalid ID'], 400);
            }

            $item = $this->inventoryStockModel->findById((int)$id);
            if (!$item) {
                return Response::json(['message' => 'Inventory record not found'], 404);
            }

            $data = $this->getRequestData();

            $rules = [
                'serial_number' => 'string|max:100',
                'product_id' => 'numeric',
                'purchase_id' => 'numeric',
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
                if ($this->inventoryStockModel->exists($data['serial_number'], $id)) {
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

            $success = $this->inventoryStockModel->update((int)$id, $data);
            if (!$success) {
                return Response::json(['message' => 'Failed to update record or no changes made'], 400);
            }

            $updatedRecord = $this->inventoryStockModel->findById((int)$id);
            return Response::json([
                'message' => 'Inventory stock record updated successfully',
                'data' => $updatedRecord
            ], 200);

        } catch (Exception $e) {
            error_log("InventoryStockController::updateStock error: " . $e->getMessage());
            return Response::json(['message' => 'An error occurred: ' . $e->getMessage()], 500);
        }
    }

    /**
     * DELETE /inventory-stocks/{id}
     */
    public function deleteStock($id)
    {
        try {
            $currentUser = AuthMiddleware::authenticate(['admin', 'manager']);
            if (!$currentUser) return;

            if (empty($id) || !is_numeric($id)) {
                return Response::json(['message' => 'Invalid ID'], 400);
            }

            $item = $this->inventoryStockModel->findById((int)$id);
            if (!$item) {
                return Response::json(['message' => 'Inventory record not found'], 404);
            }

            $success = $this->inventoryStockModel->delete((int)$id);
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

            return Response::json(['message' => 'Inventory stock record deleted successfully'], 200);
        } catch (Exception $e) {
            error_log("InventoryStockController::deleteStock error: " . $e->getMessage());
            return Response::json(['message' => 'An error occurred'], 500);
        }
    }

    /**
     * PATCH /inventory-stocks/{id}/status
     */
    public function updateStatus($id)
    {
        try {
            $currentUser = AuthMiddleware::authenticate(['admin', 'manager', 'cashier', 'warehouse_staff']);
            if (!$currentUser) return;

            if (empty($id) || !is_numeric($id)) {
                return Response::json(['message' => 'Invalid ID'], 400);
            }

            $item = $this->inventoryStockModel->findById((int)$id);
            if (!$item) {
                return Response::json(['message' => 'Inventory record not found'], 404);
            }

            $data = $this->getRequestData();
            if (!isset($data['status'])) {
                return Response::json(['message' => 'Status field is required'], 400);
            }

            $allowedStatus = ['available', 'sold', 'reserved', 'damaged'];
            if (!in_array($data['status'], $allowedStatus)) {
                return Response::json(['message' => 'Invalid status. Must be: ' . implode(', ', $allowedStatus)], 400);
            }

            $success = $this->inventoryStockModel->updateStatus((int)$id, $data['status']);
            if (!$success) {
                return Response::json(['message' => 'Failed to update status'], 500);
            }

            return Response::json([
                'message' => 'Status updated successfully',
                'id' => (int)$id,
                'status' => $data['status']
            ], 200);

        } catch (Exception $e) {
            error_log("InventoryStockController::updateStatus error: " . $e->getMessage());
            return Response::json(['message' => 'An error occurred'], 500);
        }
    }
}
?>
