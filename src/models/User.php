<?php
// src/models/User.php
require_once __DIR__ . '/../config/database.php';

class User {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * ເຂົ້າສູ່ລະບົບ
     */
    public function login($email, $password, $ip, $userAgent) {
        // ສາມາດ login ດ້ວຍ email ຫຼື username
        $stmt = $this->db->prepare(
            "SELECT u.*, 
                    d.id as department_id,
                    d.department_code,
                    d.department_name,
                    d.parent_department_id,
                    d.manager_id as dept_manager_id,
                    d.company_id
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

        // ກວດສອບ status (1 = active, 0 = inactive, 2 = suspended)
        if ($user['status'] != 1) {
            $statusText = $this->getStatusText($user['status']);
            return ['success' => false, 'message' => 'Account is ' . $statusText];
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

    /**
     * ດຶງຂໍ້ມູນຜູ້ໃຊ້ຕາມ ID
     */
    public function getById($id) {
        $stmt = $this->db->prepare(
            "SELECT u.*, 
                    d.id as department_id,
                    d.department_code,
                    d.department_name,
                    d.parent_department_id,
                    d.manager_id as dept_manager_id,
                    d.company_id
             FROM users u
             LEFT JOIN departments d ON u.department_id = d.id
             WHERE u.id = ?"
        );
        $stmt->execute([$id]);
        $user = $stmt->fetch();
        
        if ($user) {
            unset($user['password_hash']);
            $user['status_text'] = $this->getStatusText($user['status']);
        }
        
        return $user;
    }

    /**
     * ບັນທຶກການລົງທະບຽນ (ໃຊ້ສຳລັບ register)
     */
    public function register($data) {
        // ກວດສອບຂໍ້ມູນຊ້ຳ
        $stmt = $this->db->prepare(
            "SELECT id FROM users WHERE email = ? OR username = ? OR employee_code = ?"
        );
        $stmt->execute([$data['email'], $data['username'], $data['employee_code']]);
        
        if ($stmt->fetch()) {
            return ['success' => false, 'message' => 'Email, username or employee code already exists'];
        }

        $hashedPassword = password_hash($data['password'], PASSWORD_BCRYPT);
        
        $sql = "INSERT INTO users (
            employee_code, username, email, password_hash, 
            first_name, last_name, phone, position, department_id, role, status, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        
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
                $data['role'] ?? 'employee',
                $data['status'] ?? 1
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

    /**
     * ອັບເດດຮູບໂປຣໄຟລ໌
     */
    public function updateProfileImage($userId, $imagePath) {
        $stmt = $this->db->prepare("UPDATE users SET profile_image = ?, updated_at = NOW() WHERE id = ?");
        
        try {
            $stmt->execute([$imagePath, $userId]);
            return ['success' => true, 'message' => 'Profile image updated successfully'];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Update failed: ' . $e->getMessage()];
        }
    }
 


/**
 * ດຶງຂໍ້ມູນຜູ້ໃຊ້ທັງໝົດແບບ Paginated ພ້ອມການກັ່ນຕອງ
 */
public function getAllUsers($filters = []) {
    $sql = "SELECT u.*, 
                   d.id as department_id,
                   d.department_code,
                   d.department_name,
                   d.parent_department_id,
                   d.manager_id as dept_manager_id,
                   d.company_id,
                   c.company_name
            FROM users u
            LEFT JOIN departments d ON u.department_id = d.id
            LEFT JOIN companies c ON d.company_id = c.id
            WHERE 1=1";
    $params = [];

    // ກັ່ນຕອງຕາມ status (1=active, 0=inactive, 2=suspended)
    if (!empty($filters['status']) && $filters['status'] !== 'all') {
        if ($filters['status'] === 'active') {
            $sql .= " AND u.status = 1";
        } elseif ($filters['status'] === 'inactive') {
            $sql .= " AND u.status = 0";
        } elseif ($filters['status'] === 'suspended') {
            $sql .= " AND u.status = 2";
        }
    }

    // ກັ່ນຕອງຕາມບົດບາດ
    if (!empty($filters['role']) && $filters['role'] !== 'all') {
        $sql .= " AND u.role = ?";
        $params[] = $filters['role'];
    }

    // ກັ່ນຕອງຕາມພະແນກ
    if (!empty($filters['department_id'])) {
        $sql .= " AND u.department_id = ?";
        $params[] = $filters['department_id'];
    }

    // ກັ່ນຕອງຕາມບໍລິສັດ
    if (!empty($filters['company_id'])) {
        $sql .= " AND d.company_id = ?";
        $params[] = $filters['company_id'];
    }

    // ຄົ້ນຫາຕາມຄຳສຳຄັນ
    if (!empty($filters['search'])) {
        $sql .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.username LIKE ? 
                   OR u.email LIKE ? OR u.employee_code LIKE ? OR u.phone LIKE ?)";
        $searchTerm = "%{$filters['search']}%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }

    // ການຈັດຮຽງ
    $sortBy = $filters['sort_by'] ?? 'created_at';
    $sortOrder = isset($filters['sort_order']) && strtoupper($filters['sort_order']) === 'ASC' ? 'ASC' : 'DESC';
    $sql .= " ORDER BY u.{$sortBy} {$sortOrder}";

    // ການແບ່ງໜ້າ
    $page = isset($filters['page']) ? (int)$filters['page'] : 1;
    $limit = isset($filters['limit']) ? (int)$filters['limit'] : 20;
    $offset = ($page - 1) * $limit;
    
    $sql .= " LIMIT {$limit} OFFSET {$offset}";

    $stmt = $this->db->prepare($sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll();

    // ລຶບ password_hash ອອກ
    foreach ($users as &$user) {
        unset($user['password_hash']);
        // ເພີ່ມ status_text
        $user['status_text'] = $this->getStatusText($user['status']);
    }

    // ນັບຈຳນວນທັງໝົດສຳລັບ pagination - ແກ້ໄຂສ່ວນນີ້
    $countSql = "SELECT COUNT(*) as total FROM users u 
                 LEFT JOIN departments d ON u.department_id = d.id 
                 LEFT JOIN companies c ON d.company_id = c.id 
                 WHERE 1=1";
    
    // ເພີ່ມເງື່ອນໄຂການກັ່ນຕອງຄືກັນກັບ query ຫຼັກ
    $countParams = [];
    
    if (!empty($filters['status']) && $filters['status'] !== 'all') {
        if ($filters['status'] === 'active') {
            $countSql .= " AND u.status = 1";
        } elseif ($filters['status'] === 'inactive') {
            $countSql .= " AND u.status = 0";
        } elseif ($filters['status'] === 'suspended') {
            $countSql .= " AND u.status = 2";
        }
    }

    if (!empty($filters['role']) && $filters['role'] !== 'all') {
        $countSql .= " AND u.role = ?";
        $countParams[] = $filters['role'];
    }

    if (!empty($filters['department_id'])) {
        $countSql .= " AND u.department_id = ?";
        $countParams[] = $filters['department_id'];
    }

    if (!empty($filters['company_id'])) {
        $countSql .= " AND d.company_id = ?";
        $countParams[] = $filters['company_id'];
    }

    if (!empty($filters['search'])) {
        $countSql .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.username LIKE ? 
                        OR u.email LIKE ? OR u.employee_code LIKE ? OR u.phone LIKE ?)";
        $searchTerm = "%{$filters['search']}%";
        $countParams[] = $searchTerm;
        $countParams[] = $searchTerm;
        $countParams[] = $searchTerm;
        $countParams[] = $searchTerm;
        $countParams[] = $searchTerm;
        $countParams[] = $searchTerm;
    }

    $countStmt = $this->db->prepare($countSql);
    $countStmt->execute($countParams);
    $totalResult = $countStmt->fetch();
    $total = $totalResult ? (int)$totalResult['total'] : 0;

    return [
        'data' => $users,
        'total' => $total,
        'current_page' => $page,
        'per_page' => $limit,
        'last_page' => $total > 0 ? ceil($total / $limit) : 1,
        'from' => $offset + 1,
        'to' => min($offset + $limit, $total)
    ];
}
    /**
     * ດຶງຂໍ້ມູນຜູ້ໃຊ້ຕາມ ID ພ້ອມຂໍ້ມູນພະແນກເຕັມ
     */
    public function getUserWithFullDepartment($id) {
        $stmt = $this->db->prepare(
            "SELECT u.*, 
                    d.id as department_id,
                    d.department_code,
                    d.department_name,
                    d.parent_department_id,
                    d.manager_id as dept_manager_id,
                    d.company_id,
                    c.company_name,
                    parent.department_name as parent_department_name,
                    manager.first_name as manager_first_name,
                    manager.last_name as manager_last_name
             FROM users u
             LEFT JOIN departments d ON u.department_id = d.id
             LEFT JOIN companies c ON d.company_id = c.id
             LEFT JOIN departments parent ON d.parent_department_id = parent.id
             LEFT JOIN users manager ON d.manager_id = manager.id
             WHERE u.id = ?"
        );
        $stmt->execute([$id]);
        $user = $stmt->fetch();
        
        if ($user) {
            unset($user['password_hash']);
            $user['status_text'] = $this->getStatusText($user['status']);
        }
        
        return $user;
    }

    /**
     * ດຶງຂໍ້ມູນຜູ້ໃຊ້ຕາມພະແນກ
     */
    public function getUsersByDepartment($departmentId) {
        $stmt = $this->db->prepare(
            "SELECT u.*, 
                    d.id as department_id,
                    d.department_code,
                    d.department_name,
                    d.parent_department_id,
                    d.manager_id as dept_manager_id,
                    d.company_id,
                    c.company_name
             FROM users u
             LEFT JOIN departments d ON u.department_id = d.id
             LEFT JOIN companies c ON d.company_id = c.id
             WHERE u.department_id = ? AND u.status = 1
             ORDER BY u.first_name, u.last_name"
        );
        $stmt->execute([$departmentId]);
        $users = $stmt->fetchAll();

        foreach ($users as &$user) {
            unset($user['password_hash']);
        }

        return $users;
    }

    /**
     * ດຶງຂໍ້ມູນຜູ້ໃຊ້ແບບຫຍໍ້ສຳລັບ dropdown
     */
    public function getUsersForDropdown($departmentId = null, $excludeId = null, $search = '') {
        $sql = "SELECT id, username, employee_code, first_name, last_name, email, 
                       CONCAT(first_name, ' ', last_name, ' (', employee_code, ')') as full_name
                FROM users 
                WHERE status = 1";
        $params = [];

        if ($departmentId) {
            $sql .= " AND department_id = ?";
            $params[] = $departmentId;
        }

        if ($excludeId) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }

        if (!empty($search)) {
            $sql .= " AND (first_name LIKE ? OR last_name LIKE ? OR employee_code LIKE ? OR email LIKE ?)";
            $searchTerm = "%{$search}%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        $sql .= " ORDER BY first_name, last_name LIMIT 100";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * ສ້າງຜູ້ໃຊ້ໃໝ່
     */
    public function create($data) {
        // ກວດສອບຂໍ້ມູນຊ້ຳ
        $stmt = $this->db->prepare(
            "SELECT id FROM users WHERE email = ? OR username = ? OR employee_code = ?"
        );
        $stmt->execute([$data['email'], $data['username'], $data['employee_code']]);
        
        if ($stmt->fetch()) {
            return ['success' => false, 'message' => 'Email, username or employee code already exists'];
        }

        $hashedPassword = password_hash($data['password'], PASSWORD_BCRYPT);
        
        $sql = "INSERT INTO users (
            employee_code, username, email, password_hash, 
            first_name, last_name, phone, position, department_id, role, status, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        
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
                $data['role'] ?? 'employee',
                $data['status'] ?? 1
            ]);
            
            return [
                'success' => true,
                'user_id' => $this->db->lastInsertId(),
                'message' => 'User created successfully'
            ];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Creation failed: ' . $e->getMessage()];
        }
    }

    /**
     * ອັບເດດຜູ້ໃຊ້
     */
    public function update($id, $data) {
        // ກວດສອບວ່າມີຜູ້ໃຊ້ບໍ
        $user = $this->getById($id);
        if (!$user) {
            return ['success' => false, 'message' => 'User not found'];
        }

        // ກວດສອບຂໍ້ມູນຊ້ຳ (ຍົກເວັ້ນ ID ປັດຈຸບັນ)
        if (isset($data['email']) || isset($data['username']) || isset($data['employee_code'])) {
            $checkSql = "SELECT id FROM users WHERE (email = ? OR username = ? OR employee_code = ?) AND id != ?";
            $checkStmt = $this->db->prepare($checkSql);
            $checkStmt->execute([
                $data['email'] ?? $user['email'],
                $data['username'] ?? $user['username'],
                $data['employee_code'] ?? $user['employee_code'],
                $id
            ]);
            
            if ($checkStmt->fetch()) {
                return ['success' => false, 'message' => 'Email, username or employee code already exists'];
            }
        }

        $fields = [];
        $params = [];

        $allowedFields = ['employee_code', 'username', 'email', 'first_name', 'last_name', 
                        'phone', 'position', 'department_id', 'role', 'status'];

        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $fields[] = "{$field} = ?";
                $params[] = $data[$field];
            }
        }

        if (empty($fields)) {
            return ['success' => false, 'message' => 'No data to update'];
        }

        $params[] = $id;
        // ບໍ່ໃຊ້ updated_at ເພາະບໍ່ມີໃນຕາຕະລາງ
        $sql = "UPDATE users SET " . implode(', ', $fields) . " WHERE id = ?";
        
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            
            return ['success' => true, 'message' => 'User updated successfully'];
        } catch (PDOException $e) {
            // Log error for debugging
            error_log("Update failed: " . $e->getMessage());
            return ['success' => false, 'message' => 'Update failed: ' . $e->getMessage()];
        }
    }

    /**
     * ດຶງສະຖິຕິຜູ້ໃຊ້
     */
    public function getUserStats() {
        $stats = [];

        // ຈຳນວນຜູ້ໃຊ້ທັງໝົດ
        $stmt = $this->db->query("SELECT COUNT(*) as total FROM users");
        $stats['total_users'] = $stmt->fetch()['total'];

        // ຈຳນວນຜູ້ໃຊ້ແຕ່ລະສະຖານະ
        $stmt = $this->db->query("SELECT status, COUNT(*) as count FROM users GROUP BY status");
        $statusStats = [];
        while ($row = $stmt->fetch()) {
            $statusStats[$row['status']] = $row['count'];
        }
        $stats['active_users'] = $statusStats[1] ?? 0;
        $stats['inactive_users'] = $statusStats[0] ?? 0;
        $stats['suspended_users'] = $statusStats[2] ?? 0;

        // ຈຳນວນຜູ້ໃຊ້ແຕ່ລະບົດບາດ
        $stmt = $this->db->query("SELECT role, COUNT(*) as count FROM users GROUP BY role");
        $roleStats = [];
        while ($row = $stmt->fetch()) {
            $roleStats[$row['role']] = $row['count'];
        }
        $stats['role_stats'] = $roleStats;

        return $stats;
    }

    /**
     * ບັນທຶກການເຄື່ອນໄຫວ
     */
    public function logActivity($userId, $action, $description) {
        $stmt = $this->db->prepare(
            "INSERT INTO user_activities (user_id, action, description, ip_address, created_at) 
             VALUES (?, ?, ?, ?, NOW())"
        );
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $stmt->execute([$userId, $action, $description, $ip]);
    }

    /**
     * ດຶງປະຫວັດການເຄື່ອນໄຫວລ່າສຸດ
     */
    public function getRecentActivities($userId, $limit = 10) {
        $stmt = $this->db->prepare(
            "SELECT * FROM user_activities WHERE user_id = ? ORDER BY created_at DESC LIMIT ?"
        );
        $stmt->execute([$userId, $limit]);
        return $stmt->fetchAll();
    }

    /**
     * ດຶງປະຫວັດການເຂົ້າສູ່ລະບົບ
     */
    public function getLoginHistory($userId, $limit = 5) {
        $stmt = $this->db->prepare(
            "SELECT * FROM login_history WHERE user_id = ? ORDER BY login_time DESC LIMIT ?"
        );
        $stmt->execute([$userId, $limit]);
        return $stmt->fetchAll();
    }

    /**
     * ດຶງຂໍ້ມູນຜູ້ໃຊ້ທັງໝົດສຳລັບ export
     */
    public function getAllUsersForExport($filters = []) {
        $sql = "SELECT u.*, 
                       d.department_name,
                       c.company_name
                FROM users u
                LEFT JOIN departments d ON u.department_id = d.id
                LEFT JOIN companies c ON d.company_id = c.id
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

        foreach ($users as &$user) {
            unset($user['password_hash']);
            $user['status_text'] = $this->getStatusText($user['status']);
        }

        return $users;
    }

    /**
     * ຄົ້ນຫາຜູ້ໃຊ້ແບບຫຼາຍເງື່ອນໄຂ
     */
    public function advancedSearch($params) {
        $sql = "SELECT u.*, d.department_name, c.company_name
                FROM users u
                LEFT JOIN departments d ON u.department_id = d.id
                LEFT JOIN companies c ON d.company_id = c.id
                WHERE 1=1";
        $queryParams = [];

        if (!empty($params['keyword'])) {
            $sql .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? 
                       OR u.employee_code LIKE ? OR u.phone LIKE ?)";
            $keyword = "%{$params['keyword']}%";
            $queryParams[] = $keyword;
            $queryParams[] = $keyword;
            $queryParams[] = $keyword;
            $queryParams[] = $keyword;
            $queryParams[] = $keyword;
        }

        if (!empty($params['first_name'])) {
            $sql .= " AND u.first_name LIKE ?";
            $queryParams[] = "%{$params['first_name']}%";
        }

        if (!empty($params['last_name'])) {
            $sql .= " AND u.last_name LIKE ?";
            $queryParams[] = "%{$params['last_name']}%";
        }

        if (!empty($params['email'])) {
            $sql .= " AND u.email LIKE ?";
            $queryParams[] = "%{$params['email']}%";
        }

        if (!empty($params['employee_code'])) {
            $sql .= " AND u.employee_code LIKE ?";
            $queryParams[] = "%{$params['employee_code']}%";
        }

        if (!empty($params['phone'])) {
            $sql .= " AND u.phone LIKE ?";
            $queryParams[] = "%{$params['phone']}%";
        }

        if (!empty($params['status'])) {
            if ($params['status'] === 'active') {
                $sql .= " AND u.status = 1";
            } elseif ($params['status'] === 'inactive') {
                $sql .= " AND u.status = 0";
            } elseif ($params['status'] === 'suspended') {
                $sql .= " AND u.status = 2";
            }
        }

        if (!empty($params['role'])) {
            $sql .= " AND u.role = ?";
            $queryParams[] = $params['role'];
        }

        if (!empty($params['department_id'])) {
            $sql .= " AND u.department_id = ?";
            $queryParams[] = $params['department_id'];
        }

        $sql .= " ORDER BY u.first_name, u.last_name LIMIT 500";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($queryParams);
        $users = $stmt->fetchAll();

        foreach ($users as &$user) {
            unset($user['password_hash']);
        }

        return $users;
    }

    /**
     * ແປງ status ເປັນຂໍ້ຄວາມ
     */
    private function getStatusText($status) {
        switch ($status) {
            case 1:
                return 'active';
            case 0:
                return 'inactive';
            case 2:
                return 'suspended';
            default:
                return 'unknown';
        }
    }


    /**
 * ຄົ້ນຫາຜູ້ໃຊ້ຕາມ email
 */
    public function findByEmail($email) {
        $stmt = $this->db->prepare("SELECT id, email FROM users WHERE email = ?");
        $stmt->execute([$email]);
        return $stmt->fetch();
    }

    /**
     * ຄົ້ນຫາຜູ້ໃຊ້ຕາມ username
     */
    public function findByUsername($username) {
        $stmt = $this->db->prepare("SELECT id, username FROM users WHERE username = ?");
        $stmt->execute([$username]);
        return $stmt->fetch();
    }

    /**
     * ຄົ້ນຫາຜູ້ໃຊ້ຕາມ employee_code
     */
    public function findByEmployeeCode($employeeCode) {
        $stmt = $this->db->prepare("SELECT id, employee_code FROM users WHERE employee_code = ?");
        $stmt->execute([$employeeCode]);
        return $stmt->fetch();
    }
    

    /**
     * ບັນທຶກການພະຍາຍາມເຂົ້າສູ່ລະບົບ
     */
    public function logLoginAttempt($userId, $ip, $userAgent, $success) {
        $stmt = $this->db->prepare(
            "INSERT INTO login_history (user_id, ip_address, user_agent, success, login_time) 
             VALUES (?, ?, ?, ?, NOW())"
        );
        $stmt->execute([$userId, $ip, $userAgent, $success ? 1 : 0]);
    }



    /**
     * ອັບເດດສະຖານະຜູ້ໃຊ້
     */
    public function updateStatus($id, $status) {
        try {
            // ກວດສອບວ່າ users ມີ column updated_at ບໍ
            $hasUpdatedAt = $this->columnExists('updated_at');
            
            if ($hasUpdatedAt) {
                $stmt = $this->db->prepare("UPDATE users SET status = ?, updated_at = NOW() WHERE id = ?");
            } else {
                $stmt = $this->db->prepare("UPDATE users SET status = ? WHERE id = ?");
            }
            
            $stmt->execute([$status, $id]);
            
            if ($stmt->rowCount() > 0) {
                return ['success' => true, 'message' => 'Status updated successfully'];
            } else {
                return ['success' => false, 'message' => 'User not found or no changes made'];
            }
        } catch (PDOException $e) {
            error_log("Update status failed: " . $e->getMessage());
            return ['success' => false, 'message' => 'Update failed: ' . $e->getMessage()];
        }
    }

    /**
     * ກວດສອບວ່າ column ມີຢູ່ໃນຕາຕະລາງບໍ
     */
    private function columnExists($columnName) {
        try {
            $stmt = $this->db->prepare("SHOW COLUMNS FROM users LIKE ?");
            $stmt->execute([$columnName]);
            return $stmt->fetch() ? true : false;
        } catch (PDOException $e) {
            return false;
        }
    }



    /**
     * ລຶບຜູ້ໃຊ້ແບບຖາວອນ (ລຶບຈາກ database ເລີຍ)
     */
    public function deletePermanently($id) {
        try {
            // ເລີ່ມ transaction
            $this->db->beginTransaction();
            
            // ລຶບຂໍ້ມູນທີ່ກ່ຽວຂ້ອງກ່ອນ (ຖ້າມີ foreign key constraints)
            
            // ລຶບ login_history
            $stmt = $this->db->prepare("DELETE FROM login_history WHERE user_id = ?");
            $stmt->execute([$id]);
            
            // ລຶບ user_activities
            $stmt = $this->db->prepare("DELETE FROM user_activities WHERE user_id = ?");
            $stmt->execute([$id]);
            
            // ລຶບ user
            $stmt = $this->db->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$id]);
            
            // commit transaction
            $this->db->commit();
            
            return ['success' => true, 'message' => 'User deleted permanently successfully'];
        } catch (PDOException $e) {
            // rollback ຖ້າມີ error
            $this->db->rollBack();
            error_log("Permanent delete failed: " . $e->getMessage());
            return ['success' => false, 'message' => 'Delete failed: ' . $e->getMessage()];
        }
    }
    

    /**
     * ລຶບຜູ້ໃຊ້ແບບ soft delete (ປ່ຽນ status ເປັນ 3 ຫຼື ອື່ນໆ)
     */
    public function softDelete($id) {
        try {
            $stmt = $this->db->prepare("UPDATE users SET status = 3, deleted_at = NOW() WHERE id = ?");
            $stmt->execute([$id]);
            
            return ['success' => true, 'message' => 'User deleted successfully'];
        } catch (PDOException $e) {
            error_log("Soft delete failed: " . $e->getMessage());
            return ['success' => false, 'message' => 'Delete failed: ' . $e->getMessage()];
        }
    }

    /**
     * ກູ້ຄືນຜູ້ໃຊ້ທີ່ຖືກ soft delete
     */
    public function restore($id) {
        try {
            $stmt = $this->db->prepare("UPDATE users SET status = 1, deleted_at = NULL WHERE id = ?");
            $stmt->execute([$id]);
            
            return ['success' => true, 'message' => 'User restored successfully'];
        } catch (PDOException $e) {
            error_log("Restore failed: " . $e->getMessage());
            return ['success' => false, 'message' => 'Restore failed: ' . $e->getMessage()];
        }
    }


    /**
     * ດຶງລາຍຊື່ຜູ້ທີ່ສາມາດເປັນຜູ້ຈັດການໄດ້
     */
    public function getManagers($companyId = null, $departmentId = null) {
        $sql = "SELECT u.id, u.employee_code, 
                    CONCAT(u.first_name, ' ', u.last_name) as full_name,
                    u.email, u.phone,
                    d.department_name,
                    d.company_id
                FROM users u
                LEFT JOIN departments d ON u.department_id = d.id
                WHERE u.status = 1 
                AND (u.role IN ('super_admin', 'asset_admin', 'department_head', 'manager'))";
        $params = [];

        if ($companyId) {
            $sql .= " AND (d.company_id = ? OR u.role = 'super_admin')";
            $params[] = $companyId;
        }

        if ($departmentId) {
            $sql .= " AND (u.department_id = ? OR u.department_id IN (
                        SELECT id FROM departments WHERE parent_department_id = ?
                    ) OR u.role = 'super_admin')";
            $params[] = $departmentId;
            $params[] = $departmentId;
        }

        $sql .= " ORDER BY u.first_name, u.last_name";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

 
}
?>