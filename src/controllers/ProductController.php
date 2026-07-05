<?php
// src/controllers/ProductController.php
require_once __DIR__ . '/../models/Product.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Validator.php';
require_once __DIR__ . '/../utils/FileUploader.php';
require_once __DIR__ . '/../models/InventoryStock.php';

class ProductController
{
    private $db;
    private $productModel;

    public function __construct($db)
    {
        $this->db = $db;
        $this->productModel = new Product($db);
    }

    /**
     * GET /products
     */
    public function listProducts($page = 1, $limit = 10)
    {
        try {
            $currentUser = AuthMiddleware::check();
            if (!$currentUser) return;

            $filters = [
                'search' => $_GET['search'] ?? '',
                'category_id' => $_GET['category_id'] ?? null,
                'sub_category_id' => $_GET['sub_category_id'] ?? null,
                'low_stock' => isset($_GET['low_stock']) && $_GET['low_stock'] == '1' ? true : false
            ];

            $result = $this->productModel->list($page, $limit, $filters);
            return Response::json($result, 200);
        } catch (Exception $e) {
            error_log("ProductController::listProducts error: " . $e->getMessage());
            return Response::json(['message' => 'An error occurred'], 500);
        }
    }

    /**
     * GET /products/{id}
     */
    public function getProduct($id)
    {
        try {
            $currentUser = AuthMiddleware::check();
            if (!$currentUser) return;

            if (empty($id) || !is_numeric($id)) {
                return Response::json(['message' => 'Invalid ID'], 400);
            }

            $product = $this->productModel->findById((int)$id);
            if (!$product) {
                return Response::json(['message' => 'Product not found'], 404);
            }

            return Response::json($product, 200);
        } catch (Exception $e) {
            error_log("ProductController::getProduct error: " . $e->getMessage());
            return Response::json(['message' => 'An error occurred'], 500);
        }
    }

    /**
     * GET /products/barcode/{barcode}
     * Scan search endpoint
     */
    public function getProductByBarcode($barcode)
    {
        try {
            $currentUser = AuthMiddleware::check();
            if (!$currentUser) return;

            if (empty($barcode)) {
                return Response::json(['message' => 'Barcode is required'], 400);
            }

            $product = $this->productModel->findByBarcode($barcode);
            if (!$product) {
                return Response::json(['message' => 'Product not found for this barcode'], 404);
            }

            return Response::json($product, 200);
        } catch (Exception $e) {
            error_log("ProductController::getProductByBarcode error: " . $e->getMessage());
            return Response::json(['message' => 'An error occurred'], 500);
        }
    }

    /**
     * POST /products
     */
    public function createProduct()
    {
        try {
            // Require admin or manager
            $currentUser = AuthMiddleware::authenticate(['admin', 'manager']);
            if (!$currentUser) return;

            $input = file_get_contents('php://input');
            $data = json_decode($input, true);

            if (!$data) {
                return Response::json(['message' => 'Invalid JSON input'], 400);
            }

            // Validation rules
            $rules = [
                'name' => 'required|string|max:255',
                'barcode' => 'string|max:100',
                'category_id' => 'required|numeric',
                'sub_category_id' => 'required|numeric',
                'width_inch' => 'numeric',
                'length_ft' => 'numeric',
                'buy_rate' => 'numeric',
                'sell_rate' => 'numeric',
                'min_stock' => 'numeric',
                'max_stock' => 'numeric',
                'initial_stock' => 'numeric'
            ];

            $errors = Validator::validate($data, $rules);
            if (!empty($errors)) {
                return Response::json(['errors' => $errors], 422);
            }

            // Check duplicate barcode if provided manually
            if (!empty($data['barcode'])) {
                if ($this->productModel->barcodeExists($data['barcode'])) {
                    return Response::json(['message' => 'Barcode already exists in database'], 409);
                }
            }

            $data['created_by'] = $currentUser['id'];
            $newId = $this->productModel->create($data);

            if (!$newId) {
                return Response::json(['message' => 'Failed to create product master record'], 500);
            }

            // Create initial stock items in inventory_stocks if specified
            $initialStock = isset($data['initial_stock']) ? (int)$data['initial_stock'] : 0;
            if ($initialStock > 0) {
                $stockModel = new InventoryStock($this->db);
                $product = $this->productModel->findById($newId);
                for ($q = 0; $q < $initialStock; $q++) {
                    $serial = 'ST-ADJ-' . $product['barcode'] . '-' . mt_rand(1000, 9999) . '-' . ($q + 1);
                    $stockModel->create([
                        'serial_number' => $serial,
                        'product_id' => $newId,
                        'barcode' => $product['barcode'],
                        'sub_category_id' => $product['sub_category_id'],
                        'buy_rate' => $product['buy_rate'],
                        'sell_rate' => $product['sell_rate'],
                        'status' => 'available',
                        'qty' => 1,
                        'created_by' => $currentUser['id']
                    ]);
                }
            }

            $newRecord = $this->productModel->findById($newId);
            return Response::json([
                'message' => 'Product master record created successfully',
                'data' => $newRecord
            ], 201);

        } catch (Exception $e) {
            error_log("ProductController::createProduct error: " . $e->getMessage());
            return Response::json(['message' => 'An error occurred'], 500);
        }
    }

    /**
     * PUT /products/{id}
     */
    public function updateProduct($id)
    {
        try {
            // Require admin or manager
            $currentUser = AuthMiddleware::authenticate(['admin', 'manager']);
            if (!$currentUser) return;

            if (empty($id) || !is_numeric($id)) {
                return Response::json(['message' => 'Invalid ID'], 400);
            }

            $product = $this->productModel->findById((int)$id);
            if (!$product) {
                return Response::json(['message' => 'Product not found'], 404);
            }

            $input = file_get_contents('php://input');
            $data = json_decode($input, true);

            if (!$data) {
                return Response::json(['message' => 'Invalid JSON input'], 400);
            }

            $rules = [
                'name' => 'string|max:255',
                'barcode' => 'string|max:100',
                'category_id' => 'numeric',
                'sub_category_id' => 'numeric',
                'width_inch' => 'numeric',
                'length_ft' => 'numeric',
                'buy_rate' => 'numeric',
                'sell_rate' => 'numeric',
                'min_stock' => 'numeric',
                'max_stock' => 'numeric',
                'initial_stock' => 'numeric'
            ];

            $errors = Validator::validate($data, $rules);
            if (!empty($errors)) {
                return Response::json(['errors' => $errors], 422);
            }

            // Check duplicate barcode if changed
            if (isset($data['barcode']) && $data['barcode'] !== $product['barcode']) {
                if ($this->productModel->barcodeExists($data['barcode'], $id)) {
                    return Response::json(['message' => 'Barcode already exists in database'], 409);
                }
            }

            $success = $this->productModel->update((int)$id, $data);
            
            // Check if initial_stock was provided for sync
            if (isset($data['initial_stock'])) {
                $stockModel = new InventoryStock($this->db);
                $initialStock = (int)$data['initial_stock'];
                
                // Count current available stock
                $stmtCount = $this->db->prepare("SELECT COUNT(*) FROM inventory_stocks WHERE product_id = :product_id AND status = 'available'");
                $stmtCount->execute([':product_id' => (int)$id]);
                $currentAvailable = (int)$stmtCount->fetchColumn();
                
                if ($initialStock > $currentAvailable) {
                    // Generate additional available stock items
                    $diff = $initialStock - $currentAvailable;
                    $product = $this->productModel->findById((int)$id);
                    for ($q = 0; $q < $diff; $q++) {
                        $serial = 'ST-ADJ-' . $product['barcode'] . '-' . mt_rand(1000, 9999) . '-' . ($currentAvailable + $q + 1);
                        $stockModel->create([
                            'serial_number' => $serial,
                            'product_id' => (int)$id,
                            'barcode' => $product['barcode'],
                            'sub_category_id' => $product['sub_category_id'],
                            'buy_rate' => $product['buy_rate'],
                            'sell_rate' => $product['sell_rate'],
                            'status' => 'available',
                            'qty' => 1,
                            'created_by' => $currentUser['id']
                        ]);
                    }
                } elseif ($initialStock < $currentAvailable) {
                    // Delete excess available stock items
                    $diff = $currentAvailable - $initialStock;
                    $stmtDelete = $this->db->prepare("DELETE FROM inventory_stocks WHERE product_id = :product_id AND status = 'available' ORDER BY id DESC LIMIT :limit");
                    $stmtDelete->bindValue(':product_id', (int)$id, PDO::PARAM_INT);
                    $stmtDelete->bindValue(':limit', (int)$diff, PDO::PARAM_INT);
                    $stmtDelete->execute();
                }
                
                $success = true; // Mark as successful since stock was adjusted
            }

            if (!$success) {
                return Response::json(['message' => 'Failed to update product or no changes made'], 400);
            }

            $updatedRecord = $this->productModel->findById((int)$id);
            return Response::json([
                'message' => 'Product updated successfully',
                'data' => $updatedRecord
            ], 200);

        } catch (Exception $e) {
            error_log("ProductController::updateProduct error: " . $e->getMessage());
            return Response::json(['message' => 'An error occurred'], 500);
        }
    }

    /**
     * DELETE /products/{id}
     */
    public function deleteProduct($id)
    {
        try {
            // Require admin or manager
            $currentUser = AuthMiddleware::authenticate(['admin', 'manager']);
            if (!$currentUser) return;

            if (empty($id) || !is_numeric($id)) {
                return Response::json(['message' => 'Invalid ID'], 400);
            }

            $product = $this->productModel->findById((int)$id);
            if (!$product) {
                return Response::json(['message' => 'Product not found'], 404);
            }

            $success = $this->productModel->delete((int)$id);
            if (!$success) {
                return Response::json(['message' => 'Failed to delete product'], 500);
            }

            return Response::json(['message' => 'Product master record deleted successfully'], 200);
        } catch (Exception $e) {
            error_log("ProductController::deleteProduct error: " . $e->getMessage());
            return Response::json([
                'message' => 'Cannot delete product: ' . $e->getMessage()
            ], 400);
        }
    }

    /**
     * POST /products/upload-image
     */
    public function uploadProductImage()
    {
        try {
            // Require admin or manager
            $currentUser = AuthMiddleware::authenticate(['admin', 'manager']);
            if (!$currentUser) return;

            $file = $_FILES['image'] ?? null;
            if (!$file || !isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
                return Response::json(['message' => 'No file uploaded or upload error'], 400);
            }

            // Limit product images to 5MB
            if ($file['size'] > 5 * 1024 * 1024) {
                return Response::json(['message' => 'File size too large. Maximum 5MB'], 400);
            }

            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            
            if (!in_array($mimeType, $allowedTypes)) {
                return Response::json(['message' => 'Invalid file type. Allowed: JPEG, PNG, GIF, WEBP'], 400);
            }

            $uploader = new FileUploader();
            // Upload to products subfolder
            $uploadResult = $uploader->upload($file, 'uploads/products/');

            if ($uploadResult['success']) {
                return Response::json([
                    'status' => 'success',
                    'message' => 'Product image uploaded successfully',
                    'url' => $uploadResult['url'],
                    'path' => $uploadResult['path']
                ], 200);
            }

            return Response::json(['message' => 'Upload failed: ' . ($uploadResult['message'] ?? 'Unknown error')], 400);
        } catch (Exception $e) {
            error_log("ProductController::uploadProductImage error: " . $e->getMessage());
            return Response::json(['message' => 'An error occurred: ' . $e->getMessage()], 500);
        }
    }
}
?>
