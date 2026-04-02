<?php
// src/models/Company.php
require_once __DIR__ . '/../config/database.php';

class Company {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * ດຶງຂໍ້ມູນບໍລິສັດທັງໝົດແບບ Paginated
     */
    public function getAllCompanies($filters = []) {
        $sql = "SELECT c.*, 
                       parent.company_name as parent_company_name
                FROM companies c
                LEFT JOIN companies parent ON c.parent_company_id = parent.id
                WHERE 1=1";
        $params = [];

        // ກັ່ນຕອງຕາມສະຖານະ
        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            if ($filters['status'] === 'active') {
                $sql .= " AND c.status = 1";
            } elseif ($filters['status'] === 'inactive') {
                $sql .= " AND c.status = 0";
            }
        }

        // ຄົ້ນຫາຕາມຄຳສຳຄັນ
        if (!empty($filters['search'])) {
            $sql .= " AND (c.company_code LIKE ? OR c.company_name LIKE ? OR c.email LIKE ? OR c.phone LIKE ? OR c.tax_id LIKE ?)";
            $searchTerm = "%{$filters['search']}%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        // ການຈັດຮຽງ
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortOrder = isset($filters['sort_order']) && strtoupper($filters['sort_order']) === 'ASC' ? 'ASC' : 'DESC';
        $sql .= " ORDER BY c.{$sortBy} {$sortOrder}";

        // ການແບ່ງໜ້າ
        $page = isset($filters['page']) ? (int)$filters['page'] : 1;
        $limit = isset($filters['limit']) ? (int)$filters['limit'] : 20;
        $offset = ($page - 1) * $limit;
        
        $sql .= " LIMIT {$limit} OFFSET {$offset}";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $companies = $stmt->fetchAll();

        // ເພີ່ມ status_text
        foreach ($companies as &$company) {
            $company['status_text'] = $this->getStatusText($company['status']);
        }

        // ນັບຈຳນວນທັງໝົດສຳລັບ pagination
        $countSql = "SELECT COUNT(*) as total FROM companies c WHERE 1=1";
        $countParams = [];

        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            if ($filters['status'] === 'active') {
                $countSql .= " AND c.status = 1";
            } elseif ($filters['status'] === 'inactive') {
                $countSql .= " AND c.status = 0";
            }
        }

        if (!empty($filters['search'])) {
            $countSql .= " AND (c.company_code LIKE ? OR c.company_name LIKE ? OR c.email LIKE ? OR c.phone LIKE ? OR c.tax_id LIKE ?)";
            $searchTerm = "%{$filters['search']}%";
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
            'data' => $companies,
            'total' => $total,
            'current_page' => $page,
            'per_page' => $limit,
            'last_page' => $total > 0 ? ceil($total / $limit) : 1,
            'from' => $offset + 1,
            'to' => min($offset + $limit, $total)
        ];
    }

    /**
     * ດຶງຂໍ້ມູນບໍລິສັດຕາມ ID
     */
    public function getById($id) {
        $stmt = $this->db->prepare(
            "SELECT c.*, 
                    parent.company_name as parent_company_name
             FROM companies c
             LEFT JOIN companies parent ON c.parent_company_id = parent.id
             WHERE c.id = ?"
        );
        $stmt->execute([$id]);
        $company = $stmt->fetch();
        
        if ($company) {
            $company['status_text'] = $this->getStatusText($company['status']);
        }
        
        return $company;
    }

    /**
     * ດຶງຂໍ້ມູນບໍລິສັດຕາມ company_code
     */
    public function getByCompanyCode($companyCode) {
        $stmt = $this->db->prepare(
            "SELECT * FROM companies WHERE company_code = ?"
        );
        $stmt->execute([$companyCode]);
        return $stmt->fetch();
    }

    /**
     * ດຶງຂໍ້ມູນບໍລິສັດແບບຫຍໍ້ສຳລັບ dropdown
     */
    public function getCompaniesForDropdown($excludeId = null, $search = '') {
        $sql = "SELECT id, company_code, company_name, 
                       CONCAT(company_code, ' - ', company_name) as display_name
                FROM companies 
                WHERE status = 1";
        $params = [];

        if ($excludeId) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }

        if (!empty($search)) {
            $sql .= " AND (company_code LIKE ? OR company_name LIKE ?)";
            $searchTerm = "%{$search}%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        $sql .= " ORDER BY company_name LIMIT 100";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * ດຶງລາຍຊື່ບໍລິສັດແມ່ (parent companies)
     */
    public function getParentCompanies($excludeId = null) {
        $sql = "SELECT id, company_code, company_name 
                FROM companies 
                WHERE status = 1 AND (parent_company_id IS NULL OR parent_company_id = 0)";
        $params = [];

        if ($excludeId) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }

        $sql .= " ORDER BY company_name";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * ສ້າງບໍລິສັດໃໝ່
     */
    public function create($data) {
        // ກວດສອບຂໍ້ມູນຊ້ຳ
        $stmt = $this->db->prepare("SELECT id FROM companies WHERE company_code = ? OR company_name = ?");
        $stmt->execute([$data['company_code'], $data['company_name']]);
        
        if ($stmt->fetch()) {
            return ['success' => false, 'message' => 'Company code or name already exists'];
        }

        // ກວດສອບ parent_company_id ຖ້າມີ
        if (!empty($data['parent_company_id'])) {
            $parentExists = $this->getById($data['parent_company_id']);
            if (!$parentExists) {
                return ['success' => false, 'message' => 'Parent company not found'];
            }
        }

        $sql = "INSERT INTO companies (
            company_code, company_name, parent_company_id, address, phone, email, tax_id, status, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        
        $stmt = $this->db->prepare($sql);
        
        try {
            $stmt->execute([
                $data['company_code'],
                $data['company_name'],
                $data['parent_company_id'] ?? null,
                $data['address'] ?? null,
                $data['phone'] ?? null,
                $data['email'] ?? null,
                $data['tax_id'] ?? null,
                $data['status'] ?? 1
            ]);
            
            return [
                'success' => true,
                'company_id' => $this->db->lastInsertId(),
                'message' => 'Company created successfully'
            ];
        } catch (PDOException $e) {
            error_log("Create company failed: " . $e->getMessage());
            return ['success' => false, 'message' => 'Creation failed: ' . $e->getMessage()];
        }
    }

    /**
     * ອັບເດດຂໍ້ມູນບໍລິສັດ
     */
    public function update($id, $data) {
        // ກວດສອບວ່າມີບໍລິສັດບໍ
        $company = $this->getById($id);
        if (!$company) {
            return ['success' => false, 'message' => 'Company not found'];
        }

        // ກວດສອບຂໍ້ມູນຊ້ຳ (ຍົກເວັ້ນ ID ປັດຈຸບັນ)
        if (isset($data['company_code']) || isset($data['company_name'])) {
            $checkSql = "SELECT id FROM companies WHERE (company_code = ? OR company_name = ?) AND id != ?";
            $checkStmt = $this->db->prepare($checkSql);
            $checkStmt->execute([
                $data['company_code'] ?? $company['company_code'],
                $data['company_name'] ?? $company['company_name'],
                $id
            ]);
            
            if ($checkStmt->fetch()) {
                return ['success' => false, 'message' => 'Company code or name already exists'];
            }
        }

        // ກວດສອບ parent_company_id ຖ້າມີການປ່ຽນແປງ
        if (isset($data['parent_company_id']) && !empty($data['parent_company_id'])) {
            if ($data['parent_company_id'] == $id) {
                return ['success' => false, 'message' => 'Company cannot be its own parent'];
            }
            
            $parentExists = $this->getById($data['parent_company_id']);
            if (!$parentExists) {
                return ['success' => false, 'message' => 'Parent company not found'];
            }
        }

        $fields = [];
        $params = [];

        $allowedFields = ['company_code', 'company_name', 'parent_company_id', 'address', 'phone', 'email', 'tax_id', 'status'];

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
        $sql = "UPDATE companies SET " . implode(', ', $fields) . " WHERE id = ?";
        
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            
            return ['success' => true, 'message' => 'Company updated successfully'];
        } catch (PDOException $e) {
            error_log("Update company failed: " . $e->getMessage());
            return ['success' => false, 'message' => 'Update failed: ' . $e->getMessage()];
        }
    }

    /**
     * ອັບເດດສະຖານະບໍລິສັດ
     */
    public function updateStatus($id, $status) {
        try {
            $sql = "UPDATE companies SET status = ? WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$status, $id]);
            
            if ($stmt->rowCount() > 0) {
                return ['success' => true, 'message' => 'Status updated successfully'];
            } else {
                return ['success' => false, 'message' => 'Company not found or no changes made'];
            }
        } catch (PDOException $e) {
            error_log("Update status failed: " . $e->getMessage());
            return ['success' => false, 'message' => 'Update failed: ' . $e->getMessage()];
        }
    }

    /**
     * ລຶບບໍລິສັດ (soft delete)
     */
    public function softDelete($id) {
        try {
            // ກວດສອບວ່າມີບໍລິສັດຍ່ອຍບໍ
            $childStmt = $this->db->prepare("SELECT COUNT(*) as count FROM companies WHERE parent_company_id = ?");
            $childStmt->execute([$id]);
            $childCount = $childStmt->fetch()['count'];
            
            if ($childCount > 0) {
                return ['success' => false, 'message' => 'Cannot delete company with child companies'];
            }

            // ກວດສອບວ່າມີພະແນກທີ່ຂຶ້ນກັບບໍລິສັດນີ້ບໍ
            $deptStmt = $this->db->prepare("SELECT COUNT(*) as count FROM departments WHERE company_id = ?");
            $deptStmt->execute([$id]);
            $deptCount = $deptStmt->fetch()['count'];
            
            if ($deptCount > 0) {
                return ['success' => false, 'message' => 'Cannot delete company with existing departments'];
            }

            $stmt = $this->db->prepare("UPDATE companies SET status = 0 WHERE id = ?");
            $stmt->execute([$id]);
            
            return ['success' => true, 'message' => 'Company deleted successfully'];
        } catch (PDOException $e) {
            error_log("Soft delete failed: " . $e->getMessage());
            return ['success' => false, 'message' => 'Delete failed: ' . $e->getMessage()];
        }
    }

    /**
     * ລຶບບໍລິສັດແບບຖາວອນ (Admin only)
     */
    public function deletePermanently($id) {
        try {
            // ກວດສອບວ່າມີບໍລິສັດຍ່ອຍບໍ
            $childStmt = $this->db->prepare("SELECT COUNT(*) as count FROM companies WHERE parent_company_id = ?");
            $childStmt->execute([$id]);
            $childCount = $childStmt->fetch()['count'];
            
            if ($childCount > 0) {
                return ['success' => false, 'message' => 'Cannot delete company with child companies'];
            }

            // ກວດສອບວ່າມີພະແນກທີ່ຂຶ້ນກັບບໍລິສັດນີ້ບໍ
            $deptStmt = $this->db->prepare("SELECT COUNT(*) as count FROM departments WHERE company_id = ?");
            $deptStmt->execute([$id]);
            $deptCount = $deptStmt->fetch()['count'];
            
            if ($deptCount > 0) {
                return ['success' => false, 'message' => 'Cannot delete company with existing departments'];
            }

            $this->db->beginTransaction();

            // ລຶບຂໍ້ມູນທີ່ກ່ຽວຂ້ອງຖ້າມີ
            // ຕົວຢ່າງ: ລຶບ company_logs ຖ້າມີ
            // $stmt = $this->db->prepare("DELETE FROM company_logs WHERE company_id = ?");
            // $stmt->execute([$id]);

            // ລຶບບໍລິສັດ
            $stmt = $this->db->prepare("DELETE FROM companies WHERE id = ?");
            $stmt->execute([$id]);
            
            $this->db->commit();
            
            return ['success' => true, 'message' => 'Company permanently deleted successfully'];
        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("Permanent delete failed: " . $e->getMessage());
            return ['success' => false, 'message' => 'Delete failed: ' . $e->getMessage()];
        }
    }

    /**
     * ດຶງສະຖິຕິບໍລິສັດ
     */
    public function getCompanyStats() {
        $stats = [];

        // ຈຳນວນບໍລິສັດທັງໝົດ
        $stmt = $this->db->query("SELECT COUNT(*) as total FROM companies");
        $stats['total_companies'] = $stmt->fetch()['total'];

        // ຈຳນວນບໍລິສັດແຕ່ລະສະຖານະ
        $stmt = $this->db->query("SELECT status, COUNT(*) as count FROM companies GROUP BY status");
        $statusStats = [];
        while ($row = $stmt->fetch()) {
            $statusStats[$row['status']] = $row['count'];
        }
        $stats['active_companies'] = $statusStats[1] ?? 0;
        $stats['inactive_companies'] = $statusStats[0] ?? 0;

        // ຈຳນວນບໍລິສັດແມ່ ແລະ ບໍລິສັດຍ່ອຍ
        $parentStmt = $this->db->query("SELECT COUNT(*) as count FROM companies WHERE parent_company_id IS NULL OR parent_company_id = 0");
        $stats['parent_companies'] = $parentStmt->fetch()['count'];
        
        $childStmt = $this->db->query("SELECT COUNT(*) as count FROM companies WHERE parent_company_id IS NOT NULL AND parent_company_id > 0");
        $stats['child_companies'] = $childStmt->fetch()['count'];

        return $stats;
    }

    /**
     * ກວດສອບວ່າ column ມີຢູ່ໃນຕາຕະລາງບໍ
     */
    private function columnExists($columnName) {
        try {
            $stmt = $this->db->prepare("SHOW COLUMNS FROM companies LIKE ?");
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