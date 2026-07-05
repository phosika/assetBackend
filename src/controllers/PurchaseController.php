<?php
// src/controllers/PurchaseController.php
require_once __DIR__ . '/../models/Purchase.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Validator.php';

class PurchaseController
{
    private $purchaseModel;

    public function __construct($db)
    {
        $this->purchaseModel = new Purchase($db);
    }

    /**
     * GET /purchases
     */
    public function listPurchases($page = 1, $limit = 10)
    {
        try {
            $currentUser = AuthMiddleware::check();
            if (!$currentUser) return;

            $filters = [
                'search' => $_GET['search'] ?? '',
                'status' => $_GET['status'] ?? ''
            ];

            $result = $this->purchaseModel->list($page, $limit, $filters);
            return Response::json($result, 200);
        } catch (Exception $e) {
            error_log("PurchaseController::listPurchases error: " . $e->getMessage());
            return Response::json(['message' => 'An error occurred'], 500);
        }
    }

    /**
     * GET /purchases/{id}
     */
    public function getPurchase($id)
    {
        try {
            $currentUser = AuthMiddleware::check();
            if (!$currentUser) return;

            if (empty($id) || !is_numeric($id)) {
                return Response::json(['message' => 'Invalid ID'], 400);
            }

            $purchase = $this->purchaseModel->findById((int)$id);
            if (!$purchase) {
                return Response::json(['message' => 'Purchase invoice record not found'], 404);
            }

            return Response::json($purchase, 200);
        } catch (Exception $e) {
            error_log("PurchaseController::getPurchase error: " . $e->getMessage());
            return Response::json(['message' => 'An error occurred'], 500);
        }
    }

    /**
     * POST /purchases
     * Log supplier purchase and add items to inventory stock
     */
    public function createPurchase()
    {
        try {
            // Require admin, manager, or warehouse_staff
            $currentUser = AuthMiddleware::authenticate(['admin', 'manager', 'warehouse_staff']);
            if (!$currentUser) return;

            $input = file_get_contents('php://input');
            $data = json_decode($input, true);

            if (!$data) {
                return Response::json(['message' => 'Invalid JSON input'], 400);
            }

            // Validate header fields
            $rules = [
                'purchase_no' => 'string|max:100',
                'supplier_id' => 'numeric',
                'total_pieces' => 'required|numeric',
                'total_cft' => 'required|numeric',
                'total_amount' => 'required|numeric',
                'paid_amount' => 'numeric',
                'due_amount' => 'numeric',
                'purchase_date' => 'string',
                'status' => 'string|in:pending,completed,cancelled'
            ];

            $errors = Validator::validate($data, $rules);
            if (!empty($errors)) {
                return Response::json(['errors' => $errors], 422);
            }

            // Ensure items array is provided
            if (empty($data['items']) || !is_array($data['items'])) {
                return Response::json(['message' => 'At least one purchase item is required'], 400);
            }

            // Validate each item
            $itemRules = [
                'product_id' => 'required|numeric',
                'serial_number' => 'string|max:100',
                'barcode' => 'string|max:100',
                'qty' => 'required|numeric',
                'width_inch' => 'numeric',
                'length_ft' => 'numeric',
                'cubic_ft' => 'numeric',
                'rate_cft' => 'numeric'
            ];

            foreach ($data['items'] as $index => $item) {
                $itemErrors = Validator::validate($item, $itemRules);
                if (!empty($itemErrors)) {
                    return Response::json([
                        'message' => "Validation failed at item index $index",
                        'errors' => $itemErrors
                    ], 422);
                }
            }

            $data['created_by'] = $currentUser['id'];
            $newId = $this->purchaseModel->create($data);

            if (!$newId) {
                return Response::json(['message' => 'Failed to process purchase transaction'], 500);
            }

            $purchase = $this->purchaseModel->findById($newId);
            return Response::json([
                'message' => 'Purchase transaction processed successfully',
                'data' => $purchase
            ], 201);

        } catch (Exception $e) {
            error_log("PurchaseController::createPurchase error: " . $e->getMessage());
            return Response::json(['message' => 'An error occurred: ' . $e->getMessage()], 500);
        }
    }

    /**
     * POST /purchases/{id}/status
     */
    public function updateStatus($id)
    {
        try {
            // Require admin or manager
            $currentUser = AuthMiddleware::authenticate(['admin', 'manager']);
            if (!$currentUser) return;

            if (empty($id) || !is_numeric($id)) {
                return Response::json(['message' => 'Invalid ID'], 400);
            }

            $input = file_get_contents('php://input');
            $data = json_decode($input, true);

            if (!$data || !isset($data['status'])) {
                return Response::json(['message' => 'Status is required'], 400);
            }

            $status = trim($data['status']);
            $allowedStatuses = ['pending_approval', 'pending', 'completed', 'cancelled'];
            if (!in_array($status, $allowedStatuses)) {
                return Response::json(['message' => 'Invalid status value'], 400);
            }

            $updated = $this->purchaseModel->updateStatus((int)$id, $status);
            if (!$updated) {
                return Response::json(['message' => 'Failed to update purchase status'], 500);
            }

            return Response::json(['message' => 'Purchase status updated successfully'], 200);
        } catch (Exception $e) {
            error_log("PurchaseController::updateStatus error: " . $e->getMessage());
            return Response::json(['message' => 'An error occurred: ' . $e->getMessage()], 500);
        }
    }

    /**
     * POST /purchases/{id}/receive
     */
    public function receiveItems($id)
    {
        try {
            // Require admin, manager, or warehouse_staff / stocker
            $currentUser = AuthMiddleware::authenticate(['admin', 'manager', 'warehouse_staff', 'stocker']);
            if (!$currentUser) return;

            if (empty($id) || !is_numeric($id)) {
                return Response::json(['message' => 'Invalid ID'], 400);
            }

            $input = file_get_contents('php://input');
            $data = json_decode($input, true);

            if (!$data || empty($data['items']) || !is_array($data['items'])) {
                return Response::json(['message' => 'Items array is required'], 400);
            }

            $updated = $this->purchaseModel->receiveItems((int)$id, $data['items'], $currentUser['id']);
            if (!$updated) {
                return Response::json(['message' => 'Failed to receive purchase items'], 500);
            }

            return Response::json(['message' => 'Purchase items received and inventory stock updated successfully'], 200);
        } catch (Exception $e) {
            error_log("PurchaseController::receiveItems error: " . $e->getMessage());
            return Response::json(['message' => 'An error occurred: ' . $e->getMessage()], 500);
        }
    }
}
?>
