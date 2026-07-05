<?php
// src/controllers/CategoryController.php
require_once __DIR__ . '/../models/Category.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Validator.php';

class CategoryController
{
    private $categoryModel;

    public function __construct($db)
    {
        $this->categoryModel = new Category($db);
    }

    /**
     * GET /categories
     */
    public function listCategories($page = 1, $limit = 10)
    {
        try {
            // Check auth
            $currentUser = AuthMiddleware::check();
            if (!$currentUser) return;

            $result = $this->categoryModel->list($page, $limit);
            return Response::json($result, 200);
        } catch (Exception $e) {
            error_log("CategoryController::listCategories error: " . $e->getMessage());
            return Response::json(['message' => 'An error occurred'], 500);
        }
    }

    /**
     * GET /categories/dropdown
     */
    public function getCategoryDropdown()
    {
        try {
            // Check auth
            $currentUser = AuthMiddleware::check();
            if (!$currentUser) return;

            $categories = $this->categoryModel->all(true); // Active only
            return Response::json($categories, 200);
        } catch (Exception $e) {
            error_log("CategoryController::getCategoryDropdown error: " . $e->getMessage());
            return Response::json(['message' => 'An error occurred'], 500);
        }
    }

    /**
     * GET /categories/{id}
     */
    public function getCategory($id)
    {
        try {
            // Check auth
            $currentUser = AuthMiddleware::check();
            if (!$currentUser) return;

            if (empty($id) || !is_numeric($id)) {
                return Response::json(['message' => 'Invalid ID'], 400);
            }

            $category = $this->categoryModel->findById((int)$id);
            if (!$category) {
                return Response::json(['message' => 'Category not found'], 404);
            }

            return Response::json($category, 200);
        } catch (Exception $e) {
            error_log("CategoryController::getCategory error: " . $e->getMessage());
            return Response::json(['message' => 'An error occurred'], 500);
        }
    }

    /**
     * POST /categories
     */
    public function createCategory()
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
                'name' => 'required|string|max:100',
                'description' => 'string',
                'is_active' => 'numeric'
            ];

            $errors = Validator::validate($data, $rules);
            if (!empty($errors)) {
                return Response::json(['errors' => $errors], 422);
            }

            // Check duplicate
            if ($this->categoryModel->exists($data['name'])) {
                return Response::json(['message' => 'Category name already exists'], 409);
            }

            $data['created_by'] = $currentUser['id'];
            $newId = $this->categoryModel->create($data);

            if (!$newId) {
                return Response::json(['message' => 'Failed to create category'], 500);
            }

            $category = $this->categoryModel->findById($newId);
            return Response::json([
                'message' => 'Category created successfully',
                'data' => $category
            ], 201);

        } catch (Exception $e) {
            error_log("CategoryController::createCategory error: " . $e->getMessage());
            return Response::json(['message' => 'An error occurred'], 500);
        }
    }

    /**
     * PUT /categories/{id}
     */
    public function updateCategory($id)
    {
        try {
            // Check auth (Admin or Manager only)
            $currentUser = AuthMiddleware::authenticate(['admin', 'manager']);
            if (!$currentUser) return;

            if (empty($id) || !is_numeric($id)) {
                return Response::json(['message' => 'Invalid ID'], 400);
            }

            $category = $this->categoryModel->findById((int)$id);
            if (!$category) {
                return Response::json(['message' => 'Category not found'], 404);
            }

            $input = file_get_contents('php://input');
            $data = json_decode($input, true);

            if (!$data) {
                return Response::json(['message' => 'Invalid JSON input'], 400);
            }

            // Validation rules
            $rules = [
                'name' => 'string|max:100',
                'description' => 'string',
                'is_active' => 'numeric'
            ];

            $errors = Validator::validate($data, $rules);
            if (!empty($errors)) {
                return Response::json(['errors' => $errors], 422);
            }

            // Check duplicate name if changing name
            if (isset($data['name']) && $data['name'] !== $category['name']) {
                if ($this->categoryModel->exists($data['name'], $id)) {
                    return Response::json(['message' => 'Category name already exists'], 409);
                }
            }

            $success = $this->categoryModel->update((int)$id, $data);
            if (!$success) {
                return Response::json(['message' => 'Failed to update category or no changes made'], 400);
            }

            $updatedCategory = $this->categoryModel->findById((int)$id);
            return Response::json([
                'message' => 'Category updated successfully',
                'data' => $updatedCategory
            ], 200);

        } catch (Exception $e) {
            error_log("CategoryController::updateCategory error: " . $e->getMessage());
            return Response::json(['message' => 'An error occurred'], 500);
        }
    }

    /**
     * DELETE /categories/{id}
     */
    public function deleteCategory($id)
    {
        try {
            // Check auth (Admin or Manager only)
            $currentUser = AuthMiddleware::authenticate(['admin', 'manager']);
            if (!$currentUser) return;

            if (empty($id) || !is_numeric($id)) {
                return Response::json(['message' => 'Invalid ID'], 400);
            }

            $category = $this->categoryModel->findById((int)$id);
            if (!$category) {
                return Response::json(['message' => 'Category not found'], 404);
            }

            $success = $this->categoryModel->delete((int)$id);
            if (!$success) {
                return Response::json(['message' => 'Failed to delete category'], 500);
            }

            return Response::json(['message' => 'Category deleted successfully'], 200);
        } catch (Exception $e) {
            error_log("CategoryController::deleteCategory error: " . $e->getMessage());
            return Response::json(['message' => 'An error occurred'], 500);
        }
    }
}
?>
