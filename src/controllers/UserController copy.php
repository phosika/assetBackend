<?php
// src/controllers/UserController.php
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../utils/Response.php';

class UserController {
    private $userModel;

    public function __construct() {
        $this->userModel = new User();
    }

    // GET /user/profile - ດຶງຂໍ້ມູນຜູ້ໃຊ້ທີ່ກຳລັງເຂົ້າສູ່ລະບົບ
    public function getProfile() {
        $userId = AuthMiddleware::authenticate();
        $user = $this->userModel->getById($userId);

        if (!$user) {
            Response::notFound('User not found');
        }

        Response::success($user, 'User profile retrieved successfully');
    }

    // PUT /user/profile - ອັບເດດຂໍ້ມູນຜູ້ໃຊ້
    public function updateProfile() {
        $userId = AuthMiddleware::authenticate();
        $data = json_decode(file_get_contents('php://input'), true);

        // ບໍ່ອະນຸຍາດໃຫ້ປ່ຽນບາງຟິວ
        unset($data['id'], $data['employee_code'], $data['role'], $data['status'], $data['password_hash']);

        $result = $this->userModel->update($userId, $data);

        if ($result['success']) {
            $user = $this->userModel->getById($userId);
            Response::success($user, $result['message']);
        } else {
            Response::error($result['message'], 400);
        }
    }

    // POST /user/change-password - ປ່ຽນລະຫັດຜ່ານ
    public function changePassword() {
        $userId = AuthMiddleware::authenticate();
        $data = json_decode(file_get_contents('php://input'), true);

        if (empty($data['old_password']) || empty($data['new_password'])) {
            Response::error('Old password and new password required', 400);
        }

        $result = $this->userModel->changePassword($userId, $data['old_password'], $data['new_password']);

        if ($result['success']) {
            Response::success(null, $result['message']);
        } else {
            Response::error($result['message'], 400);
        }
    }

    // GET /users - ດຶງຂໍ້ມູນຜູ້ໃຊ້ທັງໝົດ (Admin/Manager only)
    public function getAllUsers() {
        $userId = AuthMiddleware::authenticate(['admin', 'manager']);
        
        $user = $this->userModel->getById($userId);
        $filters = [];

        // ຖ້າເປັນ manager, ເບິ່ງໄດ້ສະເພາະພະແນກຕົນເອງ
        if ($user['role'] === 'manager') {
            $filters['department_id'] = $user['department_id'];
        }

        // ຮັບຕົວກັ່ນຕອງຈາກ query params
        if (isset($_GET['status'])) {
            $filters['status'] = $_GET['status'];
        }
        if (isset($_GET['role'])) {
            $filters['role'] = $_GET['role'];
        }
        if (isset($_GET['department_id']) && $user['role'] === 'admin') {
            $filters['department_id'] = $_GET['department_id'];
        }

        $users = $this->userModel->getAllUsers($filters);
        Response::success($users, 'Users retrieved successfully');
    }

    // GET /users/search?q=keyword - ຄົ້ນຫາຜູ້ໃຊ້
    public function searchUsers() {
        AuthMiddleware::authenticate(['admin', 'manager']);
        
        if (empty($_GET['q'])) {
            Response::error('Search keyword required', 400);
        }

        $users = $this->userModel->searchUsers($_GET['q']);
        Response::success($users, 'Search results');
    }

    // GET /users/{id} - ດຶງຂໍ້ມູນຜູ້ໃຊ້ຕາມ ID
    public function getUserById($id) {
        $userId = AuthMiddleware::authenticate(['admin', 'manager']);
        $currentUser = $this->userModel->getById($userId);

        $user = $this->userModel->getById($id);

        if (!$user) {
            Response::notFound('User not found');
        }

        // ກວດສອບສິດ: manager ເບິ່ງໄດ້ສະເພາະພະແນກຕົນເອງ
        if ($currentUser['role'] === 'manager' && $user['department_id'] != $currentUser['department_id']) {
            Response::forbidden('You can only view users in your department');
        }

        Response::success($user, 'User retrieved successfully');
    }

    // PUT /users/{id}/status - ອັບເດດສະຖານະ (Admin only)
    public function updateUserStatus($id) {
        AuthMiddleware::authenticate('admin');
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data['status'])) {
            Response::error('Status required', 400);
        }

        $result = $this->userModel->updateStatus($id, $data['status']);

        if ($result['success']) {
            Response::success(null, $result['message']);
        } else {
            Response::error($result['message'], 400);
        }
    }

    // PUT /users/{id}/role - ອັບເດດບົດບາດ (Admin only)
    public function updateUserRole($id) {
        AuthMiddleware::authenticate('admin');
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data['role'])) {
            Response::error('Role required', 400);
        }

        $result = $this->userModel->updateRole($id, $data['role']);

        if ($result['success']) {
            Response::success(null, $result['message']);
        } else {
            Response::error($result['message'], 400);
        }
    }

    // GET /users/by-department/{departmentId} - ດຶງຜູ້ໃຊ້ຕາມພະແນກ
    public function getUsersByDepartment($departmentId) {
        AuthMiddleware::authenticate(['admin', 'manager']);
        
        $users = $this->userModel->getUsersByDepartment($departmentId);
        Response::success($users, 'Users retrieved successfully');
    }
}
?>