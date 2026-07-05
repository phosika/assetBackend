<?php
// src/controllers/SupplierController.php
require_once __DIR__ . '/../models/Supplier.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Validator.php';

class SupplierController
{
    private $supplierModel;

    public function __construct($db)
    {
        $this->supplierModel = new Supplier($db);
    }

    /**
     * GET /suppliers
     */
    public function listSuppliers($page = 1, $limit = 10)
    {
        try {
            $currentUser = AuthMiddleware::check();
            if (!$currentUser) return;

            $result = $this->supplierModel->list($page, $limit);
            return Response::json($result, 200);
        } catch (Exception $e) {
            error_log("SupplierController::listSuppliers error: " . $e->getMessage());
            return Response::json(['message' => 'An error occurred'], 500);
        }
    }

    /**
     * GET /suppliers/dropdown
     */
    public function getSupplierDropdown()
    {
        try {
            $currentUser = AuthMiddleware::check();
            if (!$currentUser) return;

            $suppliers = $this->supplierModel->all(true); // Active only
            return Response::json($suppliers, 200);
        } catch (Exception $e) {
            error_log("SupplierController::getSupplierDropdown error: " . $e->getMessage());
            return Response::json(['message' => 'An error occurred'], 500);
        }
    }

    /**
     * GET /suppliers/{id}
     */
    public function getSupplier($id)
    {
        try {
            $currentUser = AuthMiddleware::check();
            if (!$currentUser) return;

            $supplier = $this->supplierModel->findById($id);
            if (!$supplier) {
                return Response::json(['message' => 'Supplier not found'], 404);
            }
            return Response::json($supplier, 200);
        } catch (Exception $e) {
            error_log("SupplierController::getSupplier error: " . $e->getMessage());
            return Response::json(['message' => 'An error occurred'], 500);
        }
    }

    /**
     * POST /suppliers
     */
    public function createSupplier()
    {
        try {
            $currentUser = AuthMiddleware::check();
            if (!$currentUser) return;

            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data) {
                return Response::json(['message' => 'Invalid JSON input'], 400);
            }

            $rules = [
                'name' => 'required|string|max:255',
                'phone' => 'string|max:50',
                'address' => 'string'
            ];

            $errors = Validator::validate($data, $rules);
            if (!empty($errors)) {
                return Response::json(['errors' => $errors], 422);
            }

            $data['created_by'] = $currentUser['id'];
            $supplierId = $this->supplierModel->create($data);

            if ($supplierId) {
                $newSupplier = $this->supplierModel->findById($supplierId);
                return Response::json([
                    'message' => 'Supplier created successfully',
                    'supplier' => $newSupplier
                ], 201);
            }

            return Response::json(['message' => 'Failed to create supplier'], 400);
        } catch (Exception $e) {
            error_log("SupplierController::createSupplier error: " . $e->getMessage());
            return Response::json(['message' => 'An error occurred'], 500);
        }
    }

    /**
     * PUT /suppliers/{id}
     */
    public function updateSupplier($id)
    {
        try {
            $currentUser = AuthMiddleware::check();
            if (!$currentUser) return;

            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data) {
                return Response::json(['message' => 'Invalid JSON input'], 400);
            }

            $rules = [
                'name' => 'string|max:255',
                'phone' => 'string|max:50',
                'address' => 'string'
            ];

            $errors = Validator::validate($data, $rules);
            if (!empty($errors)) {
                return Response::json(['errors' => $errors], 422);
            }

            $supplier = $this->supplierModel->findById($id);
            if (!$supplier) {
                return Response::json(['message' => 'Supplier not found'], 404);
            }

            $updated = $this->supplierModel->update($id, $data);

            if ($updated) {
                $updatedSupplier = $this->supplierModel->findById($id);
                return Response::json([
                    'message' => 'Supplier updated successfully',
                    'supplier' => $updatedSupplier
                ], 200);
            }

            return Response::json(['message' => 'Failed to update supplier or no changes made'], 400);
        } catch (Exception $e) {
            error_log("SupplierController::updateSupplier error: " . $e->getMessage());
            return Response::json(['message' => 'An error occurred'], 500);
        }
    }

    /**
     * DELETE /suppliers/{id}
     */
    public function deleteSupplier($id)
    {
        try {
            $currentUser = AuthMiddleware::checkAdmin();
            if (!$currentUser) return;

            $supplier = $this->supplierModel->findById($id);
            if (!$supplier) {
                return Response::json(['message' => 'Supplier not found'], 404);
            }

            $deleted = $this->supplierModel->delete($id);

            if ($deleted) {
                return Response::json(['message' => 'Supplier deleted successfully'], 200);
            }

            return Response::json(['message' => 'Failed to delete supplier'], 400);
        } catch (Exception $e) {
            error_log("SupplierController::deleteSupplier error: " . $e->getMessage());
            return Response::json(['message' => 'An error occurred'], 500);
        }
    }
}
