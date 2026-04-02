<?php
// src/models/User.php
require_once __DIR__ . '/../config/database.php';

class User {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    // ລົງທະບຽນ
    public function register($data) {
        // ກວດສອບວ່າມີຜູ້ໃຊ້ແລ້ວບໍ
        $stmt = $this->db->prepare(
            "SELECT id FROM users WHERE email = ? OR username = ? OR employee_code = ?"
        );
        $stmt->execute([
            $data['email'], 
            $data['username'], 
            $data['employee_code'] ?? ''
        ]);
        
        if ($stmt->fetch()) {
            return ['success' => false, 'message' => 'Email, username or employee code already exists'];
        }

        // ສ້າງຜູ້ໃຊ້ໃໝ່
        $hashedPassword = password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 12]);
        
        $sql = "INSERT INTO users (
            employee_code, username, email, password_hash, 
            first_name, last_name, phone, position, department_id, role, status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->db->prepare($sql);
        
        try {
            $stmt->execute([
                $data['employee_code'],
                $data['username'],
                $data['email'],
                $hashedPassword,
                $data['first_name'],
                $data['last_name'],
                $data['phone'] ?? null,
                $data['position'] ?? null,
                $data['department_id'] ?? null,
                $data['role'] ?? 'user',
                $data['status'] ?? 'active'
            ]);
            
            return [
                'success' => true,
                'user_id' => $this->db->lastInsertId(),
                'message' => 'Registration successful'
            ];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Registration failed: ' . $e->getMessage()];
        }
    }

    // ເຂົ້າສູ່ລະບົບ
    public function login($email, $password, $ip, $userAgent) {
        // ສາມາດ login ດ້ວຍ email ຫຼື username
        $stmt = $this->db->prepare(
            "SELECT u.*, d.name as department_name 
             FROM users u
             LEFT JOIN departments d ON u.department_id = d.id
             WHERE u.email = ? OR u.username = ?"
        );
        $stmt->execute([$email, $email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            $this->logLoginAttempt(null, $ip, $userAgent, false);
            return ['success' => false, 'message' => 'Invalid credentials'];
        }

        if ($user['status'] !== 'active') {
            return ['success' => false, 'message' => 'Account is ' . $user['status']];
        }

        // ບັນທຶກການເຂົ້າສູ່ລະບົບ
        $this->logLoginAttempt($user['id'], $ip, $userAgent, true);
        
        // ອັບເດດ last_login
        $stmt = $this->db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
        $stmt->execute([$user['id']]);

        // ບໍ່ສົ່ງ password_hash ກັບໄປ
        unset($user['password_hash']);
        
        return [
            'success' => true,
            'user' => $user,
            'message' => 'Login successful'
        ];
    }

    // ດຶງຂໍ້ມູນຜູ້ໃຊ້ຕາມ ID
    public function getById($id) {
        $stmt = $this->db->prepare(
            "SELECT u.*, d.name as department_name 
             FROM users u
             LEFT JOIN departments d ON u.department_id = d.id
             WHERE u.id = ?"
        );
        $stmt->execute([$id]);
        $user = $stmt->fetch();
        
        if ($user) {
            unset($user['password_hash']);
        }
        
        return $user;
    }

    // ດຶງຂໍ້ມູນຜູ້ໃຊ້ຕາມ employee_code
    public function getByEmployeeCode($employeeCode) {
        $stmt = $this->db->prepare(
            "SELECT u.*, d.name as department_name 
             FROM users u
             LEFT JOIN departments d ON u.department_id = d.id
             WHERE u.employee_code = ?"
        );
        $stmt->execute([$employeeCode]);
        $user = $stmt->fetch();
        
        if ($user) {
            unset($user['password_hash']);
        }
        
        return $user;
    }

    // ອັບເດດຂໍ້ມູນຜູ້ໃຊ້
    public function update($id, $data) {
        $allowedFields = [
            'first_name', 'last_name', 'phone', 'position', 
            'department_id', 'email', 'username'
        ];
        
        $updates = [];
        $params = [];

        foreach ($allowedFields as $field) {
            if (isset($data[$field]) && $data[$field] !== '') {
                $updates[] = "$field = ?";
                $params[] = $data[$field];
            }
        }

        if (empty($updates)) {
            return ['success' => false, 'message' => 'No data to update'];
        }

        $params[] = $id;
        $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?";
        
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            
            return [
                'success' => true,
                'message' => 'User updated successfully'
            ];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Update failed: ' . $e->getMessage()];
        }
    }

    // ອັບເດດສະຖານະຜູ້ໃຊ້
    public function updateStatus($id, $status) {
        $allowedStatus = ['active', 'inactive', 'suspended'];
        
        if (!in_array($status, $allowedStatus)) {
            return ['success' => false, 'message' => 'Invalid status'];
        }

        $stmt = $this->db->prepare("UPDATE users SET status = ? WHERE id = ?");
        $stmt->execute([$status, $id]);

        return [
            'success' => true,
            'message' => "User status updated to $status"
        ];
    }

    // ອັບເດດບົດບາດຜູ້ໃຊ້
    public function updateRole($id, $role) {
        $allowedRoles = ['admin', 'manager', 'user', 'staff'];
        
        if (!in_array($role, $allowedRoles)) {
            return ['success' => false, 'message' => 'Invalid role'];
        }

        $stmt = $this->db->prepare("UPDATE users SET role = ? WHERE id = ?");
        $stmt->execute([$role, $id]);

        return [
            'success' => true,
            'message' => "User role updated to $role"
        ];
    }

    // ປ່ຽນລະຫັດຜ່ານ
    public function changePassword($userId, $oldPassword, $newPassword) {
        // ກວດສອບລະຫັດຜ່ານເກົ່າ
        $stmt = $this->db->prepare("SELECT password_hash FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        if (!password_verify($oldPassword, $user['password_hash'])) {
            return ['success' => false, 'message' => 'Current password is incorrect'];
        }

        // ກວດສອບຄວາມແຂງແຮງຂອງລະຫັດຜ່ານໃໝ່
        if (!$this->isStrongPassword($newPassword)) {
            return ['success' => false, 'message' => 'Password must be at least 8 characters and contain uppercase, lowercase, number, and special character'];
        }

        $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
        
        $stmt = $this->db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        $stmt->execute([$hashedPassword, $userId]);

        return ['success' => true, 'message' => 'Password changed successfully'];
    }

    // ດຶງລາຍຊື່ຜູ້ໃຊ້ທັງໝົດ (ສຳລັບ admin)
    public function getAllUsers($filters = []) {
        $sql = "SELECT u.*, d.name as department_name 
                FROM users u
                LEFT JOIN departments d ON u.department_id = d.id
                WHERE 1=1";
        $params = [];

        if (!empty($filters['status'])) {
            $sql .= " AND u.status = ?";
            $params[] = $filters['status'];
        }

        if (!empty($filters['role'])) {
            $sql .= " AND u.role = ?";
            $params[] = $filters['role'];
        }

        if (!empty($filters['department_id'])) {
            $sql .= " AND u.department_id = ?";
            $params[] = $filters['department_id'];
        }

        $sql .= " ORDER BY u.created_at DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $users = $stmt->fetchAll();

        // ລຶບ password_hash ອອກຈາກທຸກ user
        foreach ($users as &$user) {
            unset($user['password_hash']);
        }

        return $users;
    }

    // ດຶງລາຍຊື່ຜູ້ໃຊ້ຕາມພະແນກ
    public function getUsersByDepartment($departmentId) {
        $stmt = $this->db->prepare(
            "SELECT u.*, d.name as department_name 
             FROM users u
             LEFT JOIN departments d ON u.department_id = d.id
             WHERE u.department_id = ? AND u.status = 'active'
             ORDER BY u.first_name, u.last_name"
        );
        $stmt->execute([$departmentId]);
        $users = $stmt->fetchAll();

        foreach ($users as &$user) {
            unset($user['password_hash']);
        }

        return $users;
    }

    // ບັນທຶກການພະຍາຍາມເຂົ້າສູ່ລະບົບ
    private function logLoginAttempt($userId, $ip, $userAgent, $success) {
        $stmt = $this->db->prepare(
            "INSERT INTO login_history (user_id, ip_address, user_agent, success) 
             VALUES (?, ?, ?, ?)"
        );
        $stmt->execute([$userId, $ip, $userAgent, $success ? 1 : 0]);
    }

    // ກວດສອບຄວາມແຂງແຮງຂອງລະຫັດຜ່ານ
    private function isStrongPassword($password) {
        return strlen($password) >= 8 &&
               preg_match('/[A-Z]/', $password) &&
               preg_match('/[a-z]/', $password) &&
               preg_match('/[0-9]/', $password) &&
               preg_match('/[!@#$%^&*()\-_=+{};:,<.>]/', $password);
    }

    // ຄົ້ນຫາຜູ້ໃຊ້
    public function searchUsers($keyword) {
        $sql = "SELECT u.*, d.name as department_name 
                FROM users u
                LEFT JOIN departments d ON u.department_id = d.id
                WHERE u.first_name LIKE ? 
                   OR u.last_name LIKE ? 
                   OR u.username LIKE ? 
                   OR u.email LIKE ? 
                   OR u.employee_code LIKE ?
                   OR u.phone LIKE ?
                ORDER BY u.first_name, u.last_name
                LIMIT 50";
        
        $searchTerm = "%$keyword%";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
        
        $users = $stmt->fetchAll();
        foreach ($users as &$user) {
            unset($user['password_hash']);
        }

        return $users;
    }
}
?>