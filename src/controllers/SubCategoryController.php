<?php
// src/controllers/SubCategoryController.php
require_once __DIR__ . '/../models/SubCategory.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Validator.php';

class SubCategoryController
{
    private $subCategoryModel;

    public function __construct($db)
    {
        $this->subCategoryModel = new SubCategory($db);
    }

    /**
     * GET /sub-categories
     */
    public function listSubCategories($page = 1, $limit = 10)
    {
        try {
            // Check auth
            $currentUser = AuthMiddleware::check();
            if (!$currentUser) return;

            $categoryId = isset($_GET['category_id']) && is_numeric($_GET['category_id']) ? (int)$_GET['category_id'] : null;

            $result = $this->subCategoryModel->list($page, $limit, $categoryId);
            return Response::json($result, 200);
        } catch (Exception $e) {
            error_log("SubCategoryController::listSubCategories error: " . $e->getMessage());
            return Response::json(['message' => 'An error occurred'], 500);
        }
    }

    /**
     * GET /sub-categories/dropdown
     */
    public function getSubCategoryDropdown()
    {
        try {
            // Check auth
            $currentUser = AuthMiddleware::check();
            if (!$currentUser) return;

            $categoryId = isset($_GET['category_id']) && is_numeric($_GET['category_id']) ? (int)$_GET['category_id'] : null;

            $subCategories = $this->subCategoryModel->all($categoryId, true); // Active only
            return Response::json($subCategories, 200);
        } catch (Exception $e) {
            error_log("SubCategoryController::getSubCategoryDropdown error: " . $e->getMessage());
            return Response::json(['message' => 'An error occurred'], 500);
        }
    }

    /**
     * GET /sub-categories/{id}
     */
    public function getSubCategory($id)
    {
        try {
            // Check auth
            $currentUser = AuthMiddleware::check();
            if (!$currentUser) return;

            if (empty($id) || !is_numeric($id)) {
                return Response::json(['message' => 'Invalid ID'], 400);
            }

            $subCategory = $this->subCategoryModel->findById((int)$id);
            if (!$subCategory) {
                return Response::json(['message' => 'Subcategory not found'], 404);
            }

            return Response::json($subCategory, 200);
        } catch (Exception $e) {
            error_log("SubCategoryController::getSubCategory error: " . $e->getMessage());
            return Response::json(['message' => 'An error occurred'], 500);
        }
    }

    /**
     * POST /sub-categories
     */
    public function createSubCategory()
    {
        try {
            // Check auth (Admin or Manager only)
            $currentUser = AuthMiddleware::authenticate(['admin', 'manager']);
            if (!$currentUser) return;

            $input = file_get_contents('php://input');
            $data = json_decode($input, true);

            if (!$data) {
                return Response::json(['message' => 'Invalid JSON input'], 400);
            }

            // Validation rules
            $rules = [
                'category_id' => 'required|numeric',
                'name' => 'required|string|max:100',
                'arrival_date' => 'string',
                'description' => 'string',
                'is_active' => 'numeric'
            ];

            $errors = Validator::validate($data, $rules);
            if (!empty($errors)) {
                return Response::json(['errors' => $errors], 422);
            }

            // Check duplicate under this category
            if ($this->subCategoryModel->exists($data['name'], $data['category_id'])) {
                return Response::json(['message' => 'Subcategory name already exists in this category'], 409);
            }

            $data['created_by'] = $currentUser['id'];
            $newId = $this->subCategoryModel->create($data);

            if (!$newId) {
                return Response::json(['message' => 'Failed to create subcategory'], 500);
            }

            $subCategory = $this->subCategoryModel->findById($newId);
            return Response::json([
                'message' => 'Subcategory created successfully',
                'data' => $subCategory
            ], 201);

        } catch (Exception $e) {
            error_log("SubCategoryController::createSubCategory error: " . $e->getMessage());
            return Response::json(['message' => 'An error occurred'], 500);
        }
    }

    /**
     * PUT /sub-categories/{id}
     */
    public function updateSubCategory($id)
    {
        try {
            // Check auth (Admin or Manager only)
            $currentUser = AuthMiddleware::authenticate(['admin', 'manager']);
            if (!$currentUser) return;

            if (empty($id) || !is_numeric($id)) {
                return Response::json(['message' => 'Invalid ID'], 400);
            }

            $subCategory = $this->subCategoryModel->findById((int)$id);
            if (!$subCategory) {
                return Response::json(['message' => 'Subcategory not found'], 404);
            }

            $input = file_get_contents('php://input');
            $data = json_decode($input, true);

            if (!$data) {
                return Response::json(['message' => 'Invalid JSON input'], 400);
            }

            // Validation rules
            $rules = [
                'category_id' => 'numeric',
                'name' => 'string|max:100',
                'arrival_date' => 'string',
                'description' => 'string',
                'is_active' => 'numeric'
            ];

            $errors = Validator::validate($data, $rules);
            if (!empty($errors)) {
                return Response::json(['errors' => $errors], 422);
            }

            $categoryId = $data['category_id'] ?? $subCategory['category_id'];
            $name = $data['name'] ?? $subCategory['name'];

            // Check duplicate name if category or name is changing
            if ($name !== $subCategory['name'] || (int)$categoryId !== (int)$subCategory['category_id']) {
                if ($this->subCategoryModel->exists($name, $categoryId, $id)) {
                    return Response::json(['message' => 'Subcategory name already exists in this category'], 409);
                }
            }

            $success = $this->subCategoryModel->update((int)$id, $data);
            if (!$success) {
                return Response::json(['message' => 'Failed to update subcategory or no changes made'], 400);
            }

            $updatedSubCategory = $this->subCategoryModel->findById((int)$id);
            return Response::json([
                'message' => 'Subcategory updated successfully',
                'data' => $updatedSubCategory
            ], 200);

        } catch (Exception $e) {
            error_log("SubCategoryController::updateSubCategory error: " . $e->getMessage());
            return Response::json(['message' => 'An error occurred'], 500);
        }
    }

    /**
     * DELETE /sub-categories/{id}
     */
    public function deleteSubCategory($id)
    {
        try {
            // Check auth (Admin or Manager only)
            $currentUser = AuthMiddleware::authenticate(['admin', 'manager']);
            if (!$currentUser) return;

            if (empty($id) || !is_numeric($id)) {
                return Response::json(['message' => 'Invalid ID'], 400);
            }

            $subCategory = $this->subCategoryModel->findById((int)$id);
            if (!$subCategory) {
                return Response::json(['message' => 'Subcategory not found'], 404);
            }

            $success = $this->subCategoryModel->delete((int)$id);
            if (!$success) {
                return Response::json(['message' => 'Failed to delete subcategory'], 500);
            }

            return Response::json(['message' => 'Subcategory deleted successfully'], 200);
        } catch (Exception $e) {
            error_log("SubCategoryController::deleteSubCategory error: " . $e->getMessage());
            return Response::json(['message' => 'An error occurred'], 500);
        }
    }
}
?>
