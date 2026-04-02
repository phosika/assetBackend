<?php
// src/models/Department.php
require_once __DIR__ . '/../config/database.php';

class Department {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * ດຶງຂໍ້ມູນພະແນກທັງໝົດແບບ Paginated ພ້ອມການກັ່ນຕອງ
     */
    public function getAllDepartments($filters = [], $userRole = null, $userDepartmentId = null) {
        $sql = "SELECT d.*, 
                       c.company_name,
                       parent.department_name as parent_department_name,
                       CONCAT(manager.first_name, ' ', manager.last_name) as manager_name
                FROM departments d
                LEFT JOIN companies c ON d.company_id = c.id
                LEFT JOIN departments parent ON d.parent_department_id = parent.id
                LEFT JOIN users manager ON d.manager_id = manager.id
                WHERE 1=1";
        $params = [];

        // ກວດສອບສິດຕາມບົດບາດ
        if ($userRole === 'department_head' || $userRole === 'manager') {
            // ສະແດງສະເພາະພະແນກທີ່ຜູ້ໃຊ້ສັງກັດ ແລະ ພະແນກຍ່ອຍ
            $sql .= " AND (d.id = ? OR d.parent_department_id = ?)";
            $params[] = $userDepartmentId;
            $params[] = $userDepartmentId;
        }

        // ກັ່ນຕອງຕາມບໍລິສັດ
        if (!empty($filters['company_id'])) {
            $sql .= " AND d.company_id = ?";
            $params[] = $filters['company_id'];
        }

        // ກັ່ນຕອງຕາມສະຖານະ
        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            if ($filters['status'] === 'active') {
                $sql .= " AND d.status = 1";
            } elseif ($filters['status'] === 'inactive') {
                $sql .= " AND d.status = 0";
            }
        }

        // ຄົ້ນຫາຕາມຄຳສຳຄັນ
        if (!empty($filters['search'])) {
            $sql .= " AND (d.department_code LIKE ? OR d.department_name LIKE ?)";
            $searchTerm = "%{$filters['search']}%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        // ການຈັດຮຽງ
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortOrder = isset($filters['sort_order']) && strtoupper($filters['sort_order']) === 'ASC' ? 'ASC' : 'DESC';
        $sql .= " ORDER BY d.{$sortBy} {$sortOrder}";

        // ການແບ່ງໜ້າ
        $page = isset($filters['page']) ? (int)$filters['page'] : 1;
        $limit = isset($filters['limit']) ? (int)$filters['limit'] : 20;
        $offset = ($page - 1) * $limit;
        
        $sql .= " LIMIT {$limit} OFFSET {$offset}";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $departments = $stmt->fetchAll();

        // ເພີ່ມ status_text
        foreach ($departments as &$department) {
            $department['status_text'] = $this->getStatusText($department['status']);
        }

        // ນັບຈຳນວນທັງໝົດສຳລັບ pagination
        $countSql = "SELECT COUNT(*) as total FROM departments d WHERE 1=1";
        $countParams = [];

        if ($userRole === 'department_head' || $userRole === 'manager') {
            $countSql .= " AND (d.id = ? OR d.parent_department_id = ?)";
            $countParams[] = $userDepartmentId;
            $countParams[] = $userDepartmentId;
        }

        if (!empty($filters['company_id'])) {
            $countSql .= " AND d.company_id = ?";
            $countParams[] = $filters['company_id'];
        }

        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            if ($filters['status'] === 'active') {
                $countSql .= " AND d.status = 1";
            } elseif ($filters['status'] === 'inactive') {
                $countSql .= " AND d.status = 0";
            }
        }

        if (!empty($filters['search'])) {
            $countSql .= " AND (d.department_code LIKE ? OR d.department_name LIKE ?)";
            $searchTerm = "%{$filters['search']}%";
            $countParams[] = $searchTerm;
            $countParams[] = $searchTerm;
        }

        $countStmt = $this->db->prepare($countSql);
        $countStmt->execute($countParams);
        $totalResult = $countStmt->fetch();
        $total = $totalResult ? (int)$totalResult['total'] : 0;

        return [
            'data' => $departments,
            'total' => $total,
            'current_page' => $page,
            'per_page' => $limit,
            'last_page' => $total > 0 ? ceil($total / $limit) : 1,
            'from' => $offset + 1,
            'to' => min($offset + $limit, $total)
        ];
    }

    /**
     * ດຶງຂໍ້ມູນພະແນກຕາມ ID
     */
    public function getById($id) {
        $stmt = $this->db->prepare(
            "SELECT d.*, 
                    c.company_name,
                    parent.department_name as parent_department_name,
                    CONCAT(manager.first_name, ' ', manager.last_name) as manager_name,
                    manager.email as manager_email,
                    manager.phone as manager_phone
             FROM departments d
             LEFT JOIN companies c ON d.company_id = c.id
             LEFT JOIN departments parent ON d.parent_department_id = parent.id
             LEFT JOIN users manager ON d.manager_id = manager.id
             WHERE d.id = ?"
        );
        $stmt->execute([$id]);
        $department = $stmt->fetch();
        
        if ($department) {
            $department['status_text'] = $this->getStatusText($department['status']);
        }
        
        return $department;
    }

    /**
     * ດຶງຂໍ້ມູນພະແນກຕາມບໍລິສັດ
     */
    public function getByCompanyId($companyId, $userRole = null, $userDepartmentId = null) {
        $sql = "SELECT d.*, 
                       parent.department_name as parent_department_name,
                       CONCAT(manager.first_name, ' ', manager.last_name) as manager_name
                FROM departments d
                LEFT JOIN departments parent ON d.parent_department_id = parent.id
                LEFT JOIN users manager ON d.manager_id = manager.id
                WHERE d.company_id = ? AND d.status = 1";
        $params = [$companyId];

        if ($userRole === 'department_head' || $userRole === 'manager') {
            $sql .= " AND (d.id = ? OR d.parent_department_id = ?)";
            $params[] = $userDepartmentId;
            $params[] = $userDepartmentId;
        }

        $sql .= " ORDER BY d.department_name";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * ດຶງຂໍ້ມູນພະແນກແບບຫຍໍ້ສຳລັບ dropdown
     */
    public function getDepartmentsForDropdown($companyId = null, $excludeId = null, $userRole = null, $userDepartmentId = null) {
        $sql = "SELECT d.id, d.department_code, d.department_name, 
                       CONCAT(d.department_code, ' - ', d.department_name) as display_name,
                       c.company_name
                FROM departments d
                LEFT JOIN companies c ON d.company_id = c.id
                WHERE d.status = 1";
        $params = [];

        if ($companyId) {
            $sql .= " AND d.company_id = ?";
            $params[] = $companyId;
        }

        if ($excludeId) {
            $sql .= " AND d.id != ?";
            $params[] = $excludeId;
        }

        if ($userRole === 'department_head' || $userRole === 'manager') {
            $sql .= " AND (d.id = ? OR d.parent_department_id = ?)";
            $params[] = $userDepartmentId;
            $params[] = $userDepartmentId;
        }

        $sql .= " ORDER BY d.department_name LIMIT 100";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * ດຶງລາຍຊື່ພະແນກແມ່ (parent departments)
     */
    public function getParentDepartments($companyId = null, $excludeId = null) {
        $sql = "SELECT d.id, d.department_code, d.department_name 
                FROM departments d 
                WHERE d.status = 1 AND (d.parent_department_id IS NULL OR d.parent_department_id = 0)";
        $params = [];

        if ($companyId) {
            $sql .= " AND d.company_id = ?";
            $params[] = $companyId;
        }

        if ($excludeId) {
            $sql .= " AND d.id != ?";
            $params[] = $excludeId;
        }

        $sql .= " ORDER BY d.department_name";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

 
    /**
     * ດຶງຂໍ້ມູນພະແນກຕາມຜູ້ຈັດການ
     */
    public function getDepartmentByManagerId($managerId) {
        $stmt = $this->db->prepare(
            "SELECT id, department_name FROM departments WHERE manager_id = ?"
        );
        $stmt->execute([$managerId]);
        return $stmt->fetch();
    }


    /**
     * ດຶງລາຍຊື່ຜູ້ທີ່ສາມາດເປັນຫົວໜ້າພະແນກໄດ້ (ຕາມບໍລິສັດ ແລະ ພະແນກ)
     * ນີ້ແມ່ນ method ທີ່ຖືກຕ້ອງ - ໃຊ້ອັນດຽວ
     */
    public function getAvailableManagers($companyId = null, $departmentId = null, $excludeUserId = null) {
        $sql = "SELECT u.id, u.employee_code, 
                       CONCAT(u.first_name, ' ', u.last_name) as full_name,
                       u.email, u.phone,
                       d.department_name,
                       d.company_id,
                       c.company_name,
                       CASE 
                           WHEN u.id = ? THEN 1 
                           ELSE 0 
                       END as is_current_manager
                FROM users u
                LEFT JOIN departments d ON u.department_id = d.id
                LEFT JOIN companies c ON d.company_id = c.id
                WHERE u.status = 1 
                AND (u.role IN ('super_admin', 'asset_admin', 'department_head', 'manager'))";
        
        $params = [$excludeUserId];

        if ($companyId) {
            $sql .= " AND (d.company_id = ? OR u.role = 'super_admin')";
            $params[] = $companyId;
        }

        if ($departmentId) {
            // ສະແດງຜູ້ທີ່ຢູ່ໃນພະແນກດຽວກັນ ຫຼື ພະແນກແມ່
            $sql .= " AND (u.department_id = ? OR u.department_id IN (
                        SELECT id FROM departments WHERE parent_department_id = ?
                    ) OR u.role = 'super_admin')";
            $params[] = $departmentId;
            $params[] = $departmentId;
        }

        $sql .= " ORDER BY is_current_manager DESC, u.first_name, u.last_name LIMIT 100";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        // ເພີ່ມຂໍ້ມູນການເປັນຫົວໜ້າພະແນກອື່ນ
        $managers = $stmt->fetchAll();
        foreach ($managers as &$manager) {
            $otherDept = $this->getDepartmentByManagerId($manager['id']);
            if ($otherDept && $otherDept['id'] != $departmentId) {
                $manager['managing_other_dept'] = true;
                $manager['other_dept_name'] = $otherDept['department_name'];
            } else {
                $manager['managing_other_dept'] = false;
            }
        }
        
        return $managers;
    }

    /**
     * ກວດສອບວ່າຜູ້ໃຊ້ສາມາດເປັນຫົວໜ້າພະແນກນີ້ໄດ້ບໍ
     */
    public function canUserBeManager($userId, $departmentId) {
        // ດຶງຂໍ້ມູນຜູ້ໃຊ້ ແລະ ພະແນກ
        $userStmt = $this->db->prepare("SELECT role, department_id FROM users WHERE id = ?");
        $userStmt->execute([$userId]);
        $user = $userStmt->fetch();

        $deptStmt = $this->db->prepare("SELECT company_id FROM departments WHERE id = ?");
        $deptStmt->execute([$departmentId]);
        $department = $deptStmt->fetch();

        if (!$user || !$department) {
            return false;
        }

        // super_admin ສາມາດເປັນຫົວໜ້າພະແນກໄດ້ທຸກບ່ອນ
        if ($user['role'] === 'super_admin') {
            return true;
        }

        // ກວດສອບວ່າຜູ້ໃຊ້ຢູ່ໃນພະແນກດຽວກັນ ຫຼື ພະແນກຍ່ອຍ
        if ($user['department_id'] == $departmentId) {
            return true;
        }

        // ກວດສອບວ່າເປັນພະແນກຍ່ອຍຂອງຜູ້ໃຊ້ບໍ
        $childStmt = $this->db->prepare("SELECT id FROM departments WHERE parent_department_id = ?");
        $childStmt->execute([$user['department_id']]);
        while ($child = $childStmt->fetch()) {
            if ($child['id'] == $departmentId) {
                return true;
            }
        }

        return false;
    }



    /**
     * ດຶງສະຖິຕິພະແນກ
     */
    public function getDepartmentStats($userRole = null, $userDepartmentId = null) {
        $stats = [];

        // ຈຳນວນພະແນກທັງໝົດ
        $sql = "SELECT COUNT(*) as total FROM departments d WHERE 1=1";
        $params = [];

        if ($userRole === 'department_head' || $userRole === 'manager') {
            $sql .= " AND (d.id = ? OR d.parent_department_id = ?)";
            $params[] = $userDepartmentId;
            $params[] = $userDepartmentId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $stats['total_departments'] = $stmt->fetch()['total'];

        // ຈຳນວນພະແນກແຕ່ລະສະຖານະ
        $sql = "SELECT status, COUNT(*) as count FROM departments d WHERE 1=1";
        if ($userRole === 'department_head' || $userRole === 'manager') {
            $sql .= " AND (d.id = ? OR d.parent_department_id = ?)";
        }
        $sql .= " GROUP BY status";

        $stmt = $this->db->prepare($sql);
        if ($userRole === 'department_head' || $userRole === 'manager') {
            $stmt->execute([$userDepartmentId, $userDepartmentId]);
        } else {
            $stmt->execute();
        }

        $statusStats = [];
        while ($row = $stmt->fetch()) {
            $statusStats[$row['status']] = $row['count'];
        }
        $stats['active_departments'] = $statusStats[1] ?? 0;
        $stats['inactive_departments'] = $statusStats[0] ?? 0;

        // ຈຳນວນພະແນກແມ່ ແລະ ພະແນກຍ່ອຍ
        $sql = "SELECT 
                    SUM(CASE WHEN parent_department_id IS NULL OR parent_department_id = 0 THEN 1 ELSE 0 END) as parent_count,
                    SUM(CASE WHEN parent_department_id IS NOT NULL AND parent_department_id > 0 THEN 1 ELSE 0 END) as child_count
                FROM departments d WHERE 1=1";
        
        if ($userRole === 'department_head' || $userRole === 'manager') {
            $sql .= " AND (d.id = ? OR d.parent_department_id = ?)";
        }

        $stmt = $this->db->prepare($sql);
        if ($userRole === 'department_head' || $userRole === 'manager') {
            $stmt->execute([$userDepartmentId, $userDepartmentId]);
        } else {
            $stmt->execute();
        }

        $counts = $stmt->fetch();
        $stats['parent_departments'] = $counts['parent_count'] ?? 0;
        $stats['child_departments'] = $counts['child_count'] ?? 0;

        return $stats;
    }

    /**
     * ສ້າງພະແນກໃໝ່
     */
    public function create($data) {
        // ກວດສອບຂໍ້ມູນຊ້ຳ
        $stmt = $this->db->prepare("SELECT id FROM departments WHERE department_code = ?");
        $stmt->execute([$data['department_code']]);
        
        if ($stmt->fetch()) {
            return ['success' => false, 'message' => 'Department code already exists'];
        }

        // ກວດສອບ parent_department_id ຖ້າມີ
        if (!empty($data['parent_department_id'])) {
            $parentExists = $this->getById($data['parent_department_id']);
            if (!$parentExists) {
                return ['success' => false, 'message' => 'Parent department not found'];
            }
        }

        // ກວດສອບ manager_id ຖ້າມີ
        if (!empty($data['manager_id'])) {
            $managerExists = $this->checkManagerExists($data['manager_id']);
            if (!$managerExists) {
                return ['success' => false, 'message' => 'Manager not found or not eligible'];
            }
        }

        $sql = "INSERT INTO departments (
            department_code, department_name, company_id, parent_department_id, manager_id, status, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, NOW())";
        
        $stmt = $this->db->prepare($sql);
        
        try {
            $stmt->execute([
                $data['department_code'],
                $data['department_name'],
                $data['company_id'] ?? null,
                $data['parent_department_id'] ?? null,
                $data['manager_id'] ?? null,
                $data['status'] ?? 1
            ]);
            
            return [
                'success' => true,
                'department_id' => $this->db->lastInsertId(),
                'message' => 'Department created successfully'
            ];
        } catch (PDOException $e) {
            error_log("Create department failed: " . $e->getMessage());
            return ['success' => false, 'message' => 'Creation failed: ' . $e->getMessage()];
        }
    }

    /**
     * ອັບເດດຂໍ້ມູນພະແນກ
     */
    public function update($id, $data, $userRole = null, $userDepartmentId = null) {
        // ກວດສອບວ່າມີພະແນກບໍ
        $department = $this->getById($id);
        if (!$department) {
            return ['success' => false, 'message' => 'Department not found'];
        }

        // ກວດສອບສິດການແກ້ໄຂ
        if ($userRole === 'department_head' || $userRole === 'manager') {
            if ($department['id'] != $userDepartmentId && $department['parent_department_id'] != $userDepartmentId) {
                return ['success' => false, 'message' => 'You do not have permission to update this department'];
            }
        }

        // ກວດສອບຂໍ້ມູນຊ້ຳ (ຍົກເວັ້ນ ID ປັດຈຸບັນ)
        if (isset($data['department_code'])) {
            $checkSql = "SELECT id FROM departments WHERE department_code = ? AND id != ?";
            $checkStmt = $this->db->prepare($checkSql);
            $checkStmt->execute([$data['department_code'], $id]);
            
            if ($checkStmt->fetch()) {
                return ['success' => false, 'message' => 'Department code already exists'];
            }
        }

        // ກວດສອບ parent_department_id ຖ້າມີການປ່ຽນແປງ
        if (isset($data['parent_department_id']) && !empty($data['parent_department_id'])) {
            if ($data['parent_department_id'] == $id) {
                return ['success' => false, 'message' => 'Department cannot be its own parent'];
            }
            
            $parentExists = $this->getById($data['parent_department_id']);
            if (!$parentExists) {
                return ['success' => false, 'message' => 'Parent department not found'];
            }
        }

        // ກວດສອບ manager_id ຖ້າມີການປ່ຽນແປງ
        if (isset($data['manager_id']) && !empty($data['manager_id'])) {
            $managerExists = $this->checkManagerExists($data['manager_id']);
            if (!$managerExists) {
                return ['success' => false, 'message' => 'Manager not found or not eligible'];
            }
        }

        $fields = [];
        $params = [];

        $allowedFields = ['department_code', 'department_name', 'company_id', 'parent_department_id', 'manager_id', 'status'];

        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $fields[] = "{$field} = ?";
                $params[] = $data[$field];
            }
        }

        if (empty($fields)) {
            return ['success' => false, 'message' => 'No data to update'];
        }

        // ກວດສອບວ່າມີ column updated_at ບໍ
        if ($this->columnExists('updated_at')) {
            $fields[] = "updated_at = NOW()";
        }

        $params[] = $id;
        $sql = "UPDATE departments SET " . implode(', ', $fields) . " WHERE id = ?";
        
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            
            return ['success' => true, 'message' => 'Department updated successfully'];
        } catch (PDOException $e) {
            error_log("Update department failed: " . $e->getMessage());
            return ['success' => false, 'message' => 'Update failed: ' . $e->getMessage()];
        }
    }

    /**
     * ອັບເດດສະຖານະພະແນກ
     */
    public function updateStatus($id, $status, $userRole = null, $userDepartmentId = null) {
        // ກວດສອບວ່າມີພະແນກບໍ
        $department = $this->getById($id);
        if (!$department) {
            return ['success' => false, 'message' => 'Department not found'];
        }

        // ກວດສອບສິດການປ່ຽນສະຖານະ
        if ($userRole === 'department_head' || $userRole === 'manager') {
            if ($department['id'] != $userDepartmentId && $department['parent_department_id'] != $userDepartmentId) {
                return ['success' => false, 'message' => 'You do not have permission to update this department status'];
            }
        }

        try {
            $sql = "UPDATE departments SET status = ? WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$status, $id]);
            
            if ($stmt->rowCount() > 0) {
                return ['success' => true, 'message' => 'Status updated successfully'];
            } else {
                return ['success' => false, 'message' => 'Department not found or no changes made'];
            }
        } catch (PDOException $e) {
            error_log("Update status failed: " . $e->getMessage());
            return ['success' => false, 'message' => 'Update failed: ' . $e->getMessage()];
        }
    }

    /**
     * ອັບເດດຜູ້ຈັດການພະແນກ
     */
    public function updateManager($id, $managerId, $userRole = null, $userDepartmentId = null) {
        // ກວດສອບວ່າມີພະແນກບໍ
        $department = $this->getById($id);
        if (!$department) {
            return ['success' => false, 'message' => 'Department not found'];
        }

        // ກວດສອບສິດການປ່ຽນຜູ້ຈັດການ
        if ($userRole === 'department_head' || $userRole === 'manager') {
            if ($department['id'] != $userDepartmentId) {
                return ['success' => false, 'message' => 'You can only update manager for your own department'];
            }
        }

        // ກວດສອບ manager ຖ້າມີການປ່ຽນແປງ
        if (!empty($managerId)) {
            $managerExists = $this->checkManagerExists($managerId);
            if (!$managerExists) {
                return ['success' => false, 'message' => 'Manager not found or not eligible'];
            }
        }

        try {
            $sql = "UPDATE departments SET manager_id = ? WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$managerId ?: null, $id]);
            
            return ['success' => true, 'message' => 'Department manager updated successfully'];
        } catch (PDOException $e) {
            error_log("Update manager failed: " . $e->getMessage());
            return ['success' => false, 'message' => 'Update failed: ' . $e->getMessage()];
        }
    }

    /**
     * ລຶບພະແນກ (soft delete)
     */
    public function softDelete($id, $userRole = null, $userDepartmentId = null) {
        // ກວດສອບວ່າມີພະແນກບໍ
        $department = $this->getById($id);
        if (!$department) {
            return ['success' => false, 'message' => 'Department not found'];
        }

        // ກວດສອບສິດການລຶບ
        if ($userRole === 'department_head' || $userRole === 'manager') {
            if ($department['id'] != $userDepartmentId) {
                return ['success' => false, 'message' => 'You can only delete your own department'];
            }
        }

        // ກວດສອບວ່າມີພະແນກຍ່ອຍບໍ
        $childStmt = $this->db->prepare("SELECT COUNT(*) as count FROM departments WHERE parent_department_id = ?");
        $childStmt->execute([$id]);
        $childCount = $childStmt->fetch()['count'];
        
        if ($childCount > 0) {
            return ['success' => false, 'message' => 'Cannot delete department with child departments'];
        }

        // ກວດສອບວ່າມີຜູ້ໃຊ້ທີ່ຂຶ້ນກັບພະແນກນີ້ບໍ
        $userStmt = $this->db->prepare("SELECT COUNT(*) as count FROM users WHERE department_id = ? AND status = 1");
        $userStmt->execute([$id]);
        $userCount = $userStmt->fetch()['count'];
        
        if ($userCount > 0) {
            return ['success' => false, 'message' => 'Cannot delete department with active users'];
        }

        try {
            $stmt = $this->db->prepare("UPDATE departments SET status = 0 WHERE id = ?");
            $stmt->execute([$id]);
            
            return ['success' => true, 'message' => 'Department deleted successfully'];
        } catch (PDOException $e) {
            error_log("Soft delete failed: " . $e->getMessage());
            return ['success' => false, 'message' => 'Delete failed: ' . $e->getMessage()];
        }
    }

    /**
     * ກວດສອບວ່າຜູ້ໃຊ້ສາມາດເປັນຜູ້ຈັດການໄດ້ບໍ
     */
    private function checkManagerExists($managerId) {
        $stmt = $this->db->prepare(
            "SELECT id FROM users WHERE id = ? AND status = 1 AND (role = 'manager' OR role = 'department_head' OR role = 'super_admin')"
        );
        $stmt->execute([$managerId]);
        return $stmt->fetch() ? true : false;
    }

    /**
     * ກວດສອບວ່າ column ມີຢູ່ໃນຕາຕະລາງບໍ
     */
    private function columnExists($columnName) {
        try {
            $stmt = $this->db->prepare("SHOW COLUMNS FROM departments LIKE ?");
            $stmt->execute([$columnName]);
            return $stmt->fetch() ? true : false;
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * ແປງ status ເປັນຂໍ້ຄວາມ
     */
    private function getStatusText($status) {
        return $status == 1 ? 'active' : 'inactive';
    }
}
?>