<?php
// src/controllers/CustomerController.php
require_once __DIR__ . '/../models/Customer.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Validator.php';

class CustomerController
{
    private $customerModel;

    public function __construct($db)
    {
        $this->customerModel = new Customer($db);
    }

    /**
     * GET /customers
     */
    public function listCustomers($page = 1, $limit = 10)
    {
        try {
            $currentUser = AuthMiddleware::check();
            if (!$currentUser) return;

            $search = $_GET['search'] ?? '';

            $result = $this->customerModel->list($page, $limit, $search);
            return Response::json($result, 200);
        } catch (Exception $e) {
            error_log("CustomerController::listCustomers error: " . $e->getMessage());
            return Response::json(['message' => 'An error occurred'], 500);
        }
    }

    /**
     * GET /customers/dropdown
     */
    public function getCustomerDropdown()
    {
        try {
            $currentUser = AuthMiddleware::check();
            if (!$currentUser) return;

            $customers = $this->customerModel->all(true); // Active only
            return Response::json($customers, 200);
        } catch (Exception $e) {
            error_log("CustomerController::getCustomerDropdown error: " . $e->getMessage());
            return Response::json(['message' => 'An error occurred'], 500);
        }
    }

    /**
     * GET /customers/{id}
     */
    public function getCustomer($id)
    {
        try {
            $currentUser = AuthMiddleware::check();
            if (!$currentUser) return;

            $customer = $this->customerModel->findById($id);
            if (!$customer) {
                return Response::json(['message' => 'Customer not found'], 404);
            }
            return Response::json($customer, 200);
        } catch (Exception $e) {
            error_log("CustomerController::getCustomer error: " . $e->getMessage());
            return Response::json(['message' => 'An error occurred'], 500);
        }
    }

    /**
     * POST /customers
     */
    public function createCustomer()
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
            $customerId = $this->customerModel->create($data);

            if ($customerId) {
                $newCustomer = $this->customerModel->findById($customerId);
                return Response::json([
                    'message' => 'Customer created successfully',
                    'customer' => $newCustomer
                ], 201);
            } else {
                return Response::json(['message' => 'Failed to create customer'], 500);
            }
        } catch (Exception $e) {
            error_log("CustomerController::createCustomer error: " . $e->getMessage());
            return Response::json(['message' => 'An error occurred'], 500);
        }
    }

    /**
     * PUT /customers/{id}
     */
    public function updateCustomer($id)
    {
        try {
            $currentUser = AuthMiddleware::check();
            if (!$currentUser) return;

            $customer = $this->customerModel->findById($id);
            if (!$customer) {
                return Response::json(['message' => 'Customer not found'], 404);
            }

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

            $success = $this->customerModel->update($id, $data);
            if ($success) {
                $updatedCustomer = $this->customerModel->findById($id);
                return Response::json([
                    'message' => 'Customer updated successfully',
                    'customer' => $updatedCustomer
                ], 200);
            } else {
                return Response::json(['message' => 'Failed to update customer'], 500);
            }
        } catch (Exception $e) {
            error_log("CustomerController::updateCustomer error: " . $e->getMessage());
            return Response::json(['message' => 'An error occurred'], 500);
        }
    }

    /**
     * DELETE /customers/{id}
     */
    public function deleteCustomer($id)
    {
        try {
            $currentUser = AuthMiddleware::check();
            if (!$currentUser) return;

            $customer = $this->customerModel->findById($id);
            if (!$customer) {
                return Response::json(['message' => 'Customer not found'], 404);
            }

            $success = $this->customerModel->delete($id);
            if ($success) {
                return Response::json(['message' => 'Customer deleted successfully'], 200);
            } else {
                return Response::json(['message' => 'Failed to delete customer'], 500);
            }
        } catch (Exception $e) {
            error_log("CustomerController::deleteCustomer error: " . $e->getMessage());
            return Response::json(['message' => 'An error occurred'], 500);
        }
    }
}
?>
