<?php
// src/models/Department.php
require_once __DIR__ . '/../config/database.php';

class Department {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    // ດຶງຂໍ້ມູນພະແນກທັງໝົດ
    public function getAllDepartments($companyId = null) {
        $sql = "SELECT d.*, 
                       parent.department_name as parent_department_name,
                       CONCAT(manager.first_name, ' ', manager.last_name) as manager_name
                FROM departments d
                LEFT JOIN departments parent ON d.parent_department_id = parent.id
                LEFT JOIN users manager ON d.manager_id = manager.id
                WHERE d.status = 1";
        
        $params = [];
        if ($companyId) {
            $sql .= " AND d.company_id = ?";
            $params[] = $companyId;
        }
        
        $sql .= " ORDER BY d.department_name";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    // ດຶງຂໍ້ມູນພະແນກຕາມ ID
    public function getDepartmentById($id) {
        $stmt = $this->db->prepare(
            "SELECT d.*, 
                    parent.department_name as parent_department_name,
                    CONCAT(manager.first_name, ' ', manager.last_name) as manager_name
             FROM departments d
             LEFT JOIN departments parent ON d.parent_department_id = parent.id
             LEFT JOIN users manager ON d.manager_id = manager.id
             WHERE d.id = ?"
        );
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    // ດຶງພະແນກຍ່ອຍ
    public function getChildDepartments($parentId) {
        $stmt = $this->db->prepare(
            "SELECT * FROM departments 
             WHERE parent_department_id = ? AND status = 1
             ORDER BY department_name"
        );
        $stmt->execute([$parentId]);
        return $stmt->fetchAll();
    }

    // ດຶງພະແນກທີ່ມີຜູ້ຈັດການຕາມ ID
    public function getDepartmentsByManager($managerId) {
        $stmt = $this->db->prepare(
            "SELECT * FROM departments 
             WHERE manager_id = ? AND status = 1
             ORDER BY department_name"
        );
        $stmt->execute([$managerId]);
        return $stmt->fetchAll();
    }
}
?>