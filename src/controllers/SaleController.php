<?php
// src/controllers/SaleController.php
require_once __DIR__ . '/../models/Sale.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Validator.php';

class SaleController
{
    private $saleModel;

    public function __construct($db)
    {
        $this->saleModel = new Sale($db);
    }

    /**
     * GET /sales
     */
    public function listSales($page = 1, $limit = 10)
    {
        try {
            $currentUser = AuthMiddleware::check();
            if (!$currentUser) return;

            $filters = [
                'search' => $_GET['search'] ?? '',
                'status' => $_GET['status'] ?? ''
            ];

            // Cashiers can only view their own transactions
            if (strtolower($currentUser['role'] ?? '') === 'cashier') {
                $filters['created_by'] = $currentUser['id'];
            }

            $result = $this->saleModel->list($page, $limit, $filters);
            return Response::json($result, 200);
        } catch (Exception $e) {
            error_log("SaleController::listSales error: " . $e->getMessage());
            return Response::json(['message' => 'An error occurred'], 500);
        }
    }

    /**
     * GET /sales/{id}
     */
    public function getSale($id)
    {
        try {
            $currentUser = AuthMiddleware::check();
            if (!$currentUser) return;

            if (empty($id) || !is_numeric($id)) {
                return Response::json(['message' => 'Invalid ID'], 400);
            }

            $sale = $this->saleModel->findById((int)$id);
            if (!$sale) {
                return Response::json(['message' => 'Sales invoice record not found'], 404);
            }

            return Response::json($sale, 200);
        } catch (Exception $e) {
            error_log("SaleController::getSale error: " . $e->getMessage());
            return Response::json(['message' => 'An error occurred'], 500);
        }
    }

    /**
     * POST /sales
     * Create sales invoice, update inventory stock statuses
     */
    public function createSale()
    {
        try {
            // Require admin, manager, or cashier
            $currentUser = AuthMiddleware::authenticate(['admin', 'manager', 'cashier']);
            if (!$currentUser) return;

            $input = file_get_contents('php://input');
            $data = json_decode($input, true);

            if (!$data) {
                return Response::json(['message' => 'Invalid JSON input'], 400);
            }

            // Validate header fields
            $rules = [
                'invoice_no' => 'string|max:100',
                'customer_id' => 'numeric',
                'total_items' => 'required|numeric',
                'total_cft' => 'required|numeric',
                'subtotal' => 'required|numeric',
                'discount' => 'numeric',
                'grand_total' => 'required|numeric',
                'paid_amount' => 'numeric',
                'due_amount' => 'numeric',
                'payment_method' => 'string|in:cash,bank,nagad,bkash,credit',
                'sale_date' => 'string',
                'status' => 'string|in:pending,completed,cancelled'
            ];

            $errors = Validator::validate($data, $rules);
            if (!empty($errors)) {
                return Response::json(['errors' => $errors], 422);
            }

            // Ensure items array is provided
            if (empty($data['items']) || !is_array($data['items'])) {
                return Response::json(['message' => 'At least one sale item is required'], 400);
            }

            // Validate each item (requires stock ID OR product barcode)
            foreach ($data['items'] as $index => $item) {
                if (empty($item['inventory_stock_id']) && empty($item['barcode'])) {
                    return Response::json([
                        'message' => "Validation failed at item index $index: Either inventory_stock_id or barcode (scan) must be provided."
                    ], 422);
                }
                
                $itemRules = [
                    'inventory_stock_id' => 'numeric',
                    'product_id' => 'numeric',
                    'barcode' => 'string|max:100',
                    'rate_cft' => 'numeric'
                ];
                
                $itemErrors = Validator::validate($item, $itemRules);
                if (!empty($itemErrors)) {
                    return Response::json([
                        'message' => "Validation failed at item index $index",
                        'errors' => $itemErrors
                    ], 422);
                }
            }

            $data['created_by'] = $currentUser['id'];
            $newId = $this->saleModel->create($data);

            if (!$newId) {
                return Response::json(['message' => 'Failed to process sales invoice transaction'], 500);
            }

            $sale = $this->saleModel->findById($newId);
            return Response::json([
                'message' => 'Sales invoice transaction processed and stock allocated successfully',
                'data' => $sale
            ], 201);

        } catch (Exception $e) {
            error_log("SaleController::createSale error: " . $e->getMessage());
            return Response::json(['message' => 'An error occurred: ' . $e->getMessage()], 500);
        }
    }

    /**
     * POST /sales/{id}/cancel
     * Cancel sale, restore stock items, restrict to admin or manager
     */
    public function cancelSale($id)
    {
        try {
            // Require admin or manager
            $currentUser = AuthMiddleware::authenticate(['admin', 'manager']);
            if (!$currentUser) return;

            if (empty($id) || !is_numeric($id)) {
                return Response::json(['message' => 'Invalid sale ID'], 400);
            }

            $success = $this->saleModel->cancel((int)$id, $currentUser['id']);
            if (!$success) {
                return Response::json(['message' => 'Failed to cancel the sale transaction'], 500);
            }

            return Response::json([
                'message' => 'Sales transaction cancelled successfully and stock logs reverted to available status'
            ], 200);

        } catch (Exception $e) {
            error_log("SaleController::cancelSale error: " . $e->getMessage());
            return Response::json(['message' => 'An error occurred: ' . $e->getMessage()], 500);
        }
    }
}
?>
