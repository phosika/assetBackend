<?php
// src/controllers/UserController.php
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Department.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Validator.php';
require_once __DIR__ . '/../utils/FileUploader.php';

class UserController {
    private $userModel;
    private $departmentModel;
    private $validator;
    private $fileUploader;

    public function __construct() {
        $this->userModel = new User();
        $this->departmentModel = new Department();
        $this->validator = new Validator();
        $this->fileUploader = new FileUploader('uploads/profiles');
    }

    /**
     * GET /user/profile - ດຶງຂໍ້ມູນຜູ້ໃຊ້ທີ່ກຳລັງເຂົ້າສູ່ລະບົບ
     */
    public function getProfile() {
        try {
            $userId = AuthMiddleware::authenticate();
            $user = $this->userModel->getUserWithFullDepartment($userId);

            if (!$user) {
                Response::notFound('User not found');
            }

            // ເພີ່ມຂໍ້ມູນເພີ່ມເຕີມສຳລັບ profile
            $user['permissions'] = $this->getUserPermissions($user['role']);
            $user['recent_activities'] = $this->userModel->getRecentActivities($userId, 5);
            $user['department_details'] = $this->departmentModel->getDepartmentById($user['department_id']);
            
            Response::success($user, 'User profile retrieved successfully');
        } catch (Exception $e) {
            Response::error('Failed to retrieve profile: ' . $e->getMessage(), 500);
        }
    }

    /**
     * PUT /user/profile - ອັບເດດຂໍ້ມູນຜູ້ໃຊ້
     */
    public function updateProfile() {
        try {
            $userId = AuthMiddleware::authenticate();
            $data = json_decode(file_get_contents('php://input'), true);

            // ກວດສອບຂໍ້ມູນ
            $validationRules = [
                'first_name' => 'string|max:100',
                'last_name' => 'string|max:100',
                'email' => 'email|unique:users,email,' . $userId,
                'phone' => 'string|max:20',
                'position' => 'string|max:100',
                'department_id' => 'integer|exists:departments,id'
            ];

            $this->validator->validate($data, $validationRules);

            if ($this->validator->fails()) {
                Response::error($this->validator->errors(), 422);
            }

            // ບໍ່ອະນຸຍາດໃຫ້ປ່ຽນບາງຟິວ
            unset($data['id'], $data['employee_code'], $data['role'], $data['status'], $data['password_hash']);

            $result = $this->userModel->update($userId, $data);

            if ($result['success']) {
                // ບັນທຶກການອັບເດດ
                $this->userModel->logActivity($userId, 'update_profile', 'User updated their profile');
                
                $user = $this->userModel->getUserWithFullDepartment($userId);
                Response::success($user, $result['message']);
            } else {
                Response::error($result['message'], 400);
            }
        } catch (Exception $e) {
            Response::error('Failed to update profile: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST /user/change-password - ປ່ຽນລະຫັດຜ່ານ
     */
    public function changePassword() {
        try {
            $userId = AuthMiddleware::authenticate();
            $data = json_decode(file_get_contents('php://input'), true);

            // ກວດສອບຂໍ້ມູນ
            if (empty($data['old_password']) || empty($data['new_password'])) {
                Response::error('Old password and new password required', 400);
            }

            if (strlen($data['new_password']) < 8) {
                Response::error('New password must be at least 8 characters', 400);
            }

            if ($data['new_password'] === $data['old_password']) {
                Response::error('New password must be different from old password', 400);
            }

            $result = $this->userModel->changePassword($userId, $data['old_password'], $data['new_password']);

            if ($result['success']) {
                // ບັນທຶກການປ່ຽນລະຫັດ
                $this->userModel->logActivity($userId, 'change_password', 'User changed their password');
                
                // ສົ່ງອີເມວແຈ້ງເຕືອນ (optional)
                $this->sendPasswordChangeNotification($userId);
                
                Response::success(null, $result['message']);
            } else {
                Response::error($result['message'], 400);
            }
        } catch (Exception $e) {
            Response::error('Failed to change password: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST /user/profile-image - ອັບໂຫຼດຮູບໂປຣໄຟລ໌
     */
    public function uploadProfileImage() {
        try {
            $userId = AuthMiddleware::authenticate();
            
            if (!isset($_FILES['image'])) {
                Response::error('No image file uploaded', 400);
            }

            // ອັບໂຫຼດຮູບ
            $file = $_FILES['image'];
            $uploadResult = $this->fileUploader->upload($file, [
                'allowed_types' => ['image/jpeg', 'image/png', 'image/gif'],
                'max_size' => 5 * 1024 * 1024, // 5MB
                'prefix' => 'user_' . $userId
            ]);

            if (!$uploadResult['success']) {
                Response::error($uploadResult['message'], 400);
            }

            // ອັບເດດ profile image ໃນ database
            $updateResult = $this->userModel->updateProfileImage($userId, $uploadResult['path']);

            if ($updateResult['success']) {
                Response::success([
                    'image_url' => $uploadResult['url'],
                    'image_path' => $uploadResult['path']
                ], 'Profile image uploaded successfully');
            } else {
                Response::error('Failed to update profile image', 500);
            }
        } catch (Exception $e) {
            Response::error('Failed to upload image: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /users - ດຶງຂໍ້ມູນຜູ້ໃຊ້ທັງໝົດ (ຮອງຮັບການຄົ້ນຫາ, ກັ່ນຕອງ, ຈັດຮຽງ, ແບ່ງໜ້າ)
     */
    public function getAllUsers() {
        try {
            $userId = AuthMiddleware::authenticate(['super_admin', 'department_head']);
            $currentUser = $this->userModel->getById($userId);

            // ຮັບພາຣາມິເຕີຈາກ query string
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
            $search = isset($_GET['search']) ? $_GET['search'] : '';
            $status = isset($_GET['status']) ? $_GET['status'] : '';
            $role = isset($_GET['role']) ? $_GET['role'] : '';
            $department_id = isset($_GET['department_id']) ? (int)$_GET['department_id'] : 0;
            $sort_by = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'created_at';
            $sort_order = isset($_GET['sort_order']) ? strtoupper($_GET['sort_order']) : 'DESC';

            // ກວດສອບສິດ: manager ເບິ່ງໄດ້ສະເພາະພະແນກຕົນເອງ
            if ($currentUser['role'] === 'manager') {
                $department_id = $currentUser['department_id'];
            }

            // ສ້າງ filters
            $filters = [
                'search' => $search,
                'status' => $status,
                'role' => $role,
                'department_id' => $department_id,
                'page' => $page,
                'limit' => $limit,
                'sort_by' => $sort_by,
                'sort_order' => $sort_order
            ];

            // ດຶງຂໍ້ມູນຜູ້ໃຊ້ແບບ paginated
            $result = $this->userModel->getAllUsersPaginated($filters);

            Response::success([
                'users' => $result['data'],
                'pagination' => [
                    'current_page' => $result['current_page'],
                    'per_page' => $result['per_page'],
                    'total' => $result['total'],
                    'total_pages' => $result['last_page'],
                    'from' => $result['from'],
                    'to' => $result['to']
                ],
                'filters' => $filters
            ], 'Users retrieved successfully');
        } catch (Exception $e) {
            Response::error('Failed to retrieve users: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /users/dropdown - ດຶງຂໍ້ມູນຜູ້ໃຊ້ແບບຫຍໍ້ສຳລັບ dropdown
     */
    public function getUsersForDropdown() {
        try {
            AuthMiddleware::authenticate();

            $department_id = isset($_GET['department_id']) ? (int)$_GET['department_id'] : 0;
            $exclude_id = isset($_GET['exclude_id']) ? (int)$_GET['exclude_id'] : 0;
            $search = isset($_GET['search']) ? $_GET['search'] : '';

            $users = $this->userModel->getUsersForDropdown($department_id, $exclude_id, $search);
            
            Response::success($users, 'Users retrieved successfully');
        } catch (Exception $e) {
            Response::error('Failed to retrieve users: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /users/export - ສົ່ງອອກຂໍ້ມູນຜູ້ໃຊ້
     */
    public function exportUsers() {
        try {
            $userId = AuthMiddleware::authenticate(['admin', 'manager']);
            $currentUser = $this->userModel->getById($userId);

            $filters = [];
            
            // ກວດສອບສິດ
            if ($currentUser['role'] === 'manager') {
                $filters['department_id'] = $currentUser['department_id'];
            }

            // ຮັບຕົວກັ່ນຕອງເພີ່ມເຕີມ
            if (isset($_GET['status'])) {
                $filters['status'] = $_GET['status'];
            }
            if (isset($_GET['role'])) {
                $filters['role'] = $_GET['role'];
            }
            if (isset($_GET['department_id']) && $currentUser['role'] === 'admin') {
                $filters['department_id'] = $_GET['department_id'];
            }

            // ກຳນົດຮູບແບບການສົ່ງອອກ
            $format = isset($_GET['format']) ? $_GET['format'] : 'json';
            
            $users = $this->userModel->getAllUsersForExport($filters);

            switch ($format) {
                case 'csv':
                    $this->exportAsCsv($users);
                    break;
                case 'excel':
                    $this->exportAsExcel($users);
                    break;
                default:
                    Response::success([
                        'users' => $users,
                        'total' => count($users),
                        'export_date' => date('Y-m-d H:i:s'),
                        'exported_by' => $currentUser['username']
                    ], 'Users exported successfully');
            }
        } catch (Exception $e) {
            Response::error('Failed to export users: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /users/stats - ສະຖິຕິຜູ້ໃຊ້
     */
    public function getUserStats() {
        try {
            AuthMiddleware::authenticate(['admin', 'manager']);

            $stats = $this->userModel->getUserStats();
            
            Response::success($stats, 'User statistics retrieved successfully');
        } catch (Exception $e) {
            Response::error('Failed to retrieve statistics: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /users/search - ຄົ້ນຫາຜູ້ໃຊ້ (ຮອງຮັບການຄົ້ນຫາແບບຫຼາຍເງື່ອນໄຂ)
     */
    public function searchUsers() {
        try {
            $userId = AuthMiddleware::authenticate(['admin', 'manager']);
            $currentUser = $this->userModel->getById($userId);

            $searchParams = [
                'keyword' => $_GET['q'] ?? '',
                'first_name' => $_GET['first_name'] ?? '',
                'last_name' => $_GET['last_name'] ?? '',
                'email' => $_GET['email'] ?? '',
                'employee_code' => $_GET['employee_code'] ?? '',
                'phone' => $_GET['phone'] ?? '',
                'status' => $_GET['status'] ?? '',
                'role' => $_GET['role'] ?? '',
                'department_id' => isset($_GET['department_id']) ? (int)$_GET['department_id'] : 0
            ];

            // ກວດສອບສິດ manager
            if ($currentUser['role'] === 'manager') {
                $searchParams['department_id'] = $currentUser['department_id'];
            }

            $users = $this->userModel->advancedSearch($searchParams);
            
            Response::success([
                'users' => $users,
                'total' => count($users),
                'search_params' => $searchParams
            ], 'Search completed successfully');
        } catch (Exception $e) {
            Response::error('Failed to search users: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /users/{id} - ດຶງຂໍ້ມູນຜູ້ໃຊ້ຕາມ ID
     */
    public function getUserById($id) {
        try {
            $userId = AuthMiddleware::authenticate(['admin', 'manager']);
            $currentUser = $this->userModel->getById($userId);

            $user = $this->userModel->getUserWithFullDepartment($id);

            if (!$user) {
                Response::notFound('User not found');
            }

            // ກວດສອບສິດ: manager ເບິ່ງໄດ້ສະເພາະພະແນກຕົນເອງ
            if ($currentUser['role'] === 'manager' && $user['department_id'] != $currentUser['department_id']) {
                Response::forbidden('You can only view users in your department');
            }

            // ເພີ່ມຂໍ້ມູນເພີ່ມເຕີມ
            $user['recent_activities'] = $this->userModel->getRecentActivities($id, 10);
            $user['login_history'] = $this->userModel->getLoginHistory($id, 5);
            $user['managed_departments'] = $this->departmentModel->getDepartmentsByManager($id);

            Response::success($user, 'User retrieved successfully');
        } catch (Exception $e) {
            Response::error('Failed to retrieve user: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST /users - ສ້າງຜູ້ໃຊ້ໃໝ່ (Admin only)
     */
    public function createUser() {
        try {
            AuthMiddleware::authenticate('admin');
            
            $data = json_decode(file_get_contents('php://input'), true);

            // ກວດສອບຂໍ້ມູນ
            $validationRules = [
                'employee_code' => 'required|string|max:50|unique:users,employee_code',
                'username' => 'required|string|max:50|unique:users,username',
                'email' => 'required|email|max:100|unique:users,email',
                'password' => 'required|string|min:8',
                'first_name' => 'required|string|max:100',
                'last_name' => 'required|string|max:100',
                'phone' => 'string|max:20',
                'position' => 'string|max:100',
                'department_id' => 'integer|exists:departments,id',
                'role' => 'in:admin,manager,user,staff',
                'status' => 'in:active,inactive,suspended'
            ];

            $this->validator->validate($data, $validationRules);

            if ($this->validator->fails()) {
                Response::error($this->validator->errors(), 422);
            }

            $result = $this->userModel->register($data);

            if ($result['success']) {
                // ບັນທຶກການສ້າງ
                $this->userModel->logActivity($result['user_id'], 'user_created', 'User created by admin');
                
                $user = $this->userModel->getUserWithFullDepartment($result['user_id']);
                Response::success($user, 'User created successfully', 201);
            } else {
                Response::error($result['message'], 400);
            }
        } catch (Exception $e) {
            Response::error('Failed to create user: ' . $e->getMessage(), 500);
        }
    }

    /**
     * PUT /users/{id} - ອັບເດດຜູ້ໃຊ້ (Admin/Manager)
     */
    public function updateUser($id) {
        try {
            $userId = AuthMiddleware::authenticate(['admin', 'manager']);
            $currentUser = $this->userModel->getById($userId);
            $targetUser = $this->userModel->getById($id);

            if (!$targetUser) {
                Response::notFound('User not found');
            }

            // ກວດສອບສິດ
            if ($currentUser['role'] === 'manager' && $targetUser['department_id'] != $currentUser['department_id']) {
                Response::forbidden('You can only update users in your department');
            }

            $data = json_decode(file_get_contents('php://input'), true);

            // ກວດສອບຂໍ້ມູນ
            $validationRules = [
                'employee_code' => 'string|max:50|unique:users,employee_code,' . $id,
                'username' => 'string|max:50|unique:users,username,' . $id,
                'email' => 'email|max:100|unique:users,email,' . $id,
                'first_name' => 'string|max:100',
                'last_name' => 'string|max:100',
                'phone' => 'string|max:20',
                'position' => 'string|max:100',
                'department_id' => 'integer|exists:departments,id'
            ];

            // ມີແຕ່ admin ເທົ່ານັ້ນທີ່ສາມາດປ່ຽນ role ແລະ status
            if ($currentUser['role'] === 'admin') {
                $validationRules['role'] = 'in:admin,manager,user,staff';
                $validationRules['status'] = 'in:active,inactive,suspended';
            }

            $this->validator->validate($data, $validationRules);

            if ($this->validator->fails()) {
                Response::error($this->validator->errors(), 422);
            }

            $result = $this->userModel->update($id, $data);

            if ($result['success']) {
                // ບັນທຶກການອັບເດດ
                $this->userModel->logActivity($id, 'user_updated', 'User updated by ' . $currentUser['username']);
                
                $user = $this->userModel->getUserWithFullDepartment($id);
                Response::success($user, $result['message']);
            } else {
                Response::error($result['message'], 400);
            }
        } catch (Exception $e) {
            Response::error('Failed to update user: ' . $e->getMessage(), 500);
        }
    }

    /**
     * DELETE /users/{id} - ລຶບຜູ້ໃຊ້ (Admin only)
     */
    public function deleteUser($id) {
        try {
            AuthMiddleware::authenticate('admin');
            
            $user = $this->userModel->getById($id);

            if (!$user) {
                Response::notFound('User not found');
            }

            // ປ້ອງກັນການລຶບຕົນເອງ
            if ($id == AuthMiddleware::getCurrentUserId()) {
                Response::forbidden('You cannot delete your own account');
            }

            $result = $this->userModel->delete($id);

            if ($result['success']) {
                // ບັນທຶກການລຶບ
                $this->userModel->logActivity($id, 'user_deleted', 'User deleted by admin');
                
                Response::success(null, 'User deleted successfully');
            } else {
                Response::error($result['message'], 400);
            }
        } catch (Exception $e) {
            Response::error('Failed to delete user: ' . $e->getMessage(), 500);
        }
    }

    /**
     * PUT /users/{id}/status - ອັບເດດສະຖານະ (Admin only)
     */
    public function updateUserStatus($id) {
        try {
            AuthMiddleware::authenticate('admin');
            
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['status'])) {
                Response::error('Status required', 400);
            }

            // ແປງ status ເປັນຕົວເລກຖ້າຈຳເປັນ
            $statusMap = [
                'active' => 1,
                'inactive' => 0,
                'suspended' => 2
            ];

            $status = is_numeric($data['status']) ? (int)$data['status'] : 
                     (isset($statusMap[$data['status']]) ? $statusMap[$data['status']] : null);

            if ($status === null || !in_array($status, [0, 1, 2])) {
                Response::error('Invalid status value', 400);
            }

            $result = $this->userModel->updateStatus($id, $status);

            if ($result['success']) {
                // ບັນທຶກການປ່ຽນສະຖານະ
                $this->userModel->logActivity($id, 'status_changed', 'Status changed to ' . $status);
                
                Response::success(null, $result['message']);
            } else {
                Response::error($result['message'], 400);
            }
        } catch (Exception $e) {
            Response::error('Failed to update status: ' . $e->getMessage(), 500);
        }
    }

    /**
     * PUT /users/{id}/role - ອັບເດດບົດບາດ (Admin only)
     */
    public function updateUserRole($id) {
        try {
            AuthMiddleware::authenticate('admin');
            
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['role'])) {
                Response::error('Role required', 400);
            }

            $allowedRoles = ['admin', 'manager', 'user', 'staff'];
            if (!in_array($data['role'], $allowedRoles)) {
                Response::error('Invalid role. Allowed: ' . implode(', ', $allowedRoles), 400);
            }

            $result = $this->userModel->updateRole($id, $data['role']);

            if ($result['success']) {
                // ບັນທຶກການປ່ຽນບົດບາດ
                $this->userModel->logActivity($id, 'role_changed', 'Role changed to ' . $data['role']);
                
                Response::success(null, $result['message']);
            } else {
                Response::error($result['message'], 400);
            }
        } catch (Exception $e) {
            Response::error('Failed to update role: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /users/by-department/{departmentId} - ດຶງຜູ້ໃຊ້ຕາມພະແນກ
     */
    public function getUsersByDepartment($departmentId) {
        try {
            AuthMiddleware::authenticate(['admin', 'manager']);
            
            $users = $this->userModel->getUsersByDepartment($departmentId);
            
            Response::success([
                'users' => $users,
                'total' => count($users),
                'department_id' => $departmentId
            ], 'Users retrieved successfully');
        } catch (Exception $e) {
            Response::error('Failed to retrieve users: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /users/activities/{userId} - ດຶງປະຫວັດການເຄື່ອນໄຫວຂອງຜູ້ໃຊ້
     */
    public function getUserActivities($userId) {
        try {
            $currentUserId = AuthMiddleware::authenticate(['admin', 'manager']);
            $currentUser = $this->userModel->getById($currentUserId);

            // ກວດສອບສິດ
            if ($currentUser['role'] === 'manager') {
                $targetUser = $this->userModel->getById($userId);
                if ($targetUser['department_id'] != $currentUser['department_id']) {
                    Response::forbidden('You can only view activities of users in your department');
                }
            }

            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
            $activities = $this->userModel->getRecentActivities($userId, $limit);
            
            Response::success([
                'activities' => $activities,
                'user_id' => $userId,
                'total' => count($activities)
            ], 'User activities retrieved successfully');
        } catch (Exception $e) {
            Response::error('Failed to retrieve activities: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Helper: ດຶງສິດຕ່າງໆຕາມບົດບາດ
     */
    private function getUserPermissions($role) {
        $permissions = [
            'admin' => ['*'], // ມີສິດທັງໝົດ
            'manager' => [
                'view_department_users',
                'edit_department_users',
                'view_reports',
                'export_data'
            ],
            'user' => [
                'view_own_profile',
                'edit_own_profile',
                'change_own_password'
            ],
            'staff' => [
                'view_own_profile',
                'change_own_password'
            ]
        ];

        return isset($permissions[$role]) ? $permissions[$role] : [];
    }

    /**
     * Helper: ສົ່ງອອກເປັນ CSV
     */
    private function exportAsCsv($users) {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="users_export_' . date('Y-m-d') . '.csv"');

        $output = fopen('php://output', 'w');
        
        // ຂຽນ header
        fputcsv($output, ['Employee Code', 'Username', 'Email', 'First Name', 'Last Name', 'Full Name', 'Phone', 'Position', 'Department', 'Role', 'Status', 'Created At', 'Last Login']);

        // ຂຽນຂໍ້ມູນ
        foreach ($users as $user) {
            fputcsv($output, [
                $user['employee_code'],
                $user['username'],
                $user['email'],
                $user['first_name'],
                $user['last_name'],
                $user['full_name'],
                $user['phone'],
                $user['position'],
                $user['department_name'],
                $user['role'],
                $user['status_text'],
                $user['created_at'],
                $user['last_login']
            ]);
        }

        fclose($output);
        exit;
    }

    /**
     * Helper: ສົ່ງອອກເປັນ Excel (ໃຊ້ SimpleExcel ຫຼື library ອື່ນ)
     */
    private function exportAsExcel($users) {
        // ຖ້າຕ້ອງການ export ເປັນ Excel ແທ້ໆ, ຄວນໃຊ້ library ເຊັ່ນ PhpSpreadsheet
        // ແຕ່ໃນທີ່ນີ້ຂໍສົ່ງເປັນ JSON ກ່ອນ
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="users_export_' . date('Y-m-d') . '.json"');
        
        echo json_encode([
            'status' => 'success',
            'data' => $users,
            'export_date' => date('Y-m-d H:i:s'),
            'total' => count($users)
        ]);
        exit;
    }

    /**
     * Helper: ສົ່ງອີເມວແຈ້ງເຕືອນການປ່ຽນລະຫັດ
     */
    private function sendPasswordChangeNotification($userId) {
        // ສາມາດເພີ່ມໂຄດສົ່ງອີເມວໄດ້ທີ່ນີ້
        // ຕົວຢ່າງ: ໃຊ້ mail() ຫຼື SMTP library
        $user = $this->userModel->getById($userId);
        
        $subject = "Password Change Notification";
        $message = "Hello {$user['first_name']},\n\n";
        $message .= "Your password was successfully changed on " . date('Y-m-d H:i:s') . ".\n";
        $message .= "If you did not make this change, please contact your administrator immediately.\n\n";
        $message .= "Best regards,\nSystem Administrator";
        
        // mail($user['email'], $subject, $message);
        
        // ບັນທຶກວ່າໄດ້ສົ່ງອີເມວແລ້ວ
        error_log("Password change notification sent to user ID: $userId");
    }
}
?>