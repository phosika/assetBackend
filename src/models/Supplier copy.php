<?php
// src/models/Supplier.php
require_once __DIR__ . '/../config/database.php';

class Supplier {
    private $db;
    private $allowedStatus = ['active', 'inactive'];

    // public function __construct() {
    //     $this->db = Database::getInstance();
    // }


        public function __construct() {
        $this->db = Database::getInstance();
        if (!$this->db) {
            error_log("Database connection failed in Supplier model");
            throw new Exception("Database connection failed");
        }
    }

    /**
     * ດຶງຂໍ້ມູນຜູ້ສະໜອງສຳລັບ dropdown
     */
        public function getSuppliersForDropdown($activeOnly = true, $search = '') {
            try {
                $sql = "SELECT 
                            id, 
                            supplier_code, 
                            supplier_name, 
                            contact_person, 
                            phone, 
                            email,
                            address,
                            tax_id,
                            payment_terms
                        FROM {$this->table} 
                        WHERE 1=1";
                
                $params = [];

                if ($activeOnly) {
                    $sql .= " AND status = 1";
                }

                if (!empty($search)) {
                    $sql .= " AND (supplier_code LIKE ? OR supplier_name LIKE ? OR contact_person LIKE ? OR phone LIKE ?)";
                    $searchTerm = "%{$search}%";
                    $params[] = $searchTerm;
                    $params[] = $searchTerm;
                    $params[] = $searchTerm;
                    $params[] = $searchTerm;
                }
                
                $sql .= " ORDER BY supplier_code ASC";
                
                $stmt = $this->db->prepare($sql);
                $stmt->execute($params);
                $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                error_log("getSuppliersForDropdown returned " . count($result) . " records");
                return $result;
                
            } catch (Exception $e) {
                error_log("Error in getSuppliersForDropdown: " . $e->getMessage());
                return [];
            }
        }
    

    /**
     * ດຶງຂໍ້ມູນຜູ້ສະໜອງທັງໝົດແບບ Paginated
     */
    public function getAllSuppliers($filters = []) {
        // ລຶບ JOIN ກັບ users ສຳລັບ created_by ອອກ ເພາະບໍ່ມີຖັນນີ້
        // ແຕ່ຍັງ JOIN ກັບ updated_by ໄດ້ ເພາະມີຖັນນີ້
        $sql = "SELECT s.*,
                       updater.first_name as updated_by_name
                FROM suppliers s
                LEFT JOIN users updater ON s.updated_by = updater.id
                WHERE 1=1";
        $params = [];

        // ກັ່ນຕອງຕາມລະຫັດຜູ້ສະໜອງ
        if (!empty($filters['supplier_code'])) {
            $sql .= " AND s.supplier_code LIKE ?";
            $params[] = '%' . $filters['supplier_code'] . '%';
        }

        // ກັ່ນຕອງຕາມຊື່ຜູ້ສະໜອງ
        if (!empty($filters['supplier_name'])) {
            $sql .= " AND s.supplier_name LIKE ?";
            $params[] = '%' . $filters['supplier_name'] . '%';
        }

        // ກັ່ນຕອງຕາມສະຖານະ
        if (!empty($filters['status'])) {
            $sql .= " AND s.status = ?";
            $params[] = $filters['status'];
        }

        // ກັ່ນຕອງຕາມເບີໂທ
        if (!empty($filters['phone'])) {
            $sql .= " AND s.phone LIKE ?";
            $params[] = '%' . $filters['phone'] . '%';
        }

        // ກັ່ນຕອງຕາມອີເມລ
        if (!empty($filters['email'])) {
            $sql .= " AND s.email LIKE ?";
            $params[] = '%' . $filters['email'] . '%';
        }

        // ຄົ້ນຫາຕາມຄຳສຳຄັນ (ຫຼາຍຊ່ອງ)
        if (!empty($filters['search'])) {
            $sql .= " AND (s.supplier_code LIKE ? OR s.supplier_name LIKE ? 
                       OR s.contact_person LIKE ? OR s.phone LIKE ? 
                       OR s.email LIKE ? OR s.tax_id LIKE ?)";
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
        $sql .= " ORDER BY s.{$sortBy} {$sortOrder}";

        // ການແບ່ງໜ້າ
        $page = isset($filters['page']) ? (int)$filters['page'] : 1;
        $limit = isset($filters['limit']) ? (int)$filters['limit'] : 20;
        $offset = ($page - 1) * $limit;
        
        $sql .= " LIMIT {$limit} OFFSET {$offset}";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $suppliers = $stmt->fetchAll();

        // ນັບຈຳນວນທັງໝົດສຳລັບ pagination
        $countSql = "SELECT COUNT(*) as total FROM suppliers s WHERE 1=1";
        $countParams = [];

        if (!empty($filters['supplier_code'])) {
            $countSql .= " AND s.supplier_code LIKE ?";
            $countParams[] = '%' . $filters['supplier_code'] . '%';
        }

        if (!empty($filters['supplier_name'])) {
            $countSql .= " AND s.supplier_name LIKE ?";
            $countParams[] = '%' . $filters['supplier_name'] . '%';
        }

        if (!empty($filters['status'])) {
            $countSql .= " AND s.status = ?";
            $countParams[] = $filters['status'];
        }

        if (!empty($filters['search'])) {
            $countSql .= " AND (s.supplier_code LIKE ? OR s.supplier_name LIKE ? 
                               OR s.contact_person LIKE ? OR s.phone LIKE ? 
                               OR s.email LIKE ? OR s.tax_id LIKE ?)";
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
            'data' => $suppliers,
            'total' => $total,
            'current_page' => $page,
            'per_page' => $limit,
            'last_page' => $total > 0 ? ceil($total / $limit) : 1,
            'from' => $offset + 1,
            'to' => min($offset + $limit, $total)
        ];
    }

    /**
     * ດຶງຂໍ້ມູນຜູ້ສະໜອງຕາມ ID
     */
    public function getById($id) {
        $stmt = $this->db->prepare(
            "SELECT s.*,
                    updater.first_name as updated_by_name
             FROM suppliers s
             LEFT JOIN users updater ON s.updated_by = updater.id
             WHERE s.id = ?"
        );
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    /**
     * ດຶງຂໍ້ມູນຜູ້ສະໜອງຕາມລະຫັດ
     */
    public function getByCode($supplierCode) {
        $stmt = $this->db->prepare("SELECT * FROM suppliers WHERE supplier_code = ?");
        $stmt->execute([$supplierCode]);
        return $stmt->fetch();
    }

    /**
     * ດຶງຂໍ້ມູນຜູ້ສະໜອງຕາມອີເມລ
     */
    public function getByEmail($email) {
        $stmt = $this->db->prepare("SELECT * FROM suppliers WHERE email = ?");
        $stmt->execute([$email]);
        return $stmt->fetch();
    }

    /**
     * ດຶງຂໍ້ມູນຜູ້ສະໜອງຕາມເບີໂທ
     */
    public function getByPhone($phone) {
        $stmt = $this->db->prepare("SELECT * FROM suppliers WHERE phone = ?");
        $stmt->execute([$phone]);
        return $stmt->fetch();
    }

    /**
     * ດຶງຂໍ້ມູນຜູ້ສະໜອງທີ່ຍັງໃຊ້ງານຢູ່ (ສຳລັບ dropdown)
     */
    public function getActiveSuppliers() {
        $stmt = $this->db->prepare(
            "SELECT id, supplier_code, supplier_name, contact_person, phone, email 
             FROM suppliers 
             WHERE status = 'active' 
             ORDER BY supplier_name ASC"
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * ສ້າງຜູ້ສະໜອງໃໝ່ (ບໍ່ມີ created_by)
     */
    public function create($data) {
        // ກວດສອບຂໍ້ມູນຊ້ຳ
        if (!empty($data['supplier_code'])) {
            $stmt = $this->db->prepare("SELECT id FROM suppliers WHERE supplier_code = ?");
            $stmt->execute([$data['supplier_code']]);
            if ($stmt->fetch()) {
                return ['success' => false, 'message' => 'Supplier code already exists'];
            }
        }

        if (!empty($data['email'])) {
            $stmt = $this->db->prepare("SELECT id FROM suppliers WHERE email = ?");
            $stmt->execute([$data['email']]);
            if ($stmt->fetch()) {
                return ['success' => false, 'message' => 'Email already exists'];
            }
        }

        if (!empty($data['phone'])) {
            $stmt = $this->db->prepare("SELECT id FROM suppliers WHERE phone = ?");
            $stmt->execute([$data['phone']]);
            if ($stmt->fetch()) {
                return ['success' => false, 'message' => 'Phone number already exists'];
            }
        }

        // ສ້າງ supplier code ອັດຕະໂນມັດຖ້າບໍ່ມີ
        if (empty($data['supplier_code'])) {
            $data['supplier_code'] = $this->generateSupplierCode();
        }

        $fields = [];
        $placeholders = [];
        $values = [];

        $allowedFields = [
            'supplier_code', 'supplier_name', 'contact_person', 'phone',
            'email', 'address', 'tax_id', 'payment_terms', 'status'
        ];

        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $fields[] = $field;
                $placeholders[] = '?';
                $values[] = $data[$field];
            }
        }

        // ບໍ່ມີ created_by, ໃຊ້ພຽງ created_at
        $sql = "INSERT INTO suppliers (" . implode(', ', $fields) . ", created_at) 
                VALUES (" . implode(', ', $placeholders) . ", NOW())";
        
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($values);
            
            $supplierId = $this->db->lastInsertId();

            return [
                'success' => true,
                'supplier_id' => $supplierId,
                'message' => 'Supplier created successfully'
            ];
        } catch (PDOException $e) {
            error_log("Create supplier failed: " . $e->getMessage());
            return ['success' => false, 'message' => 'Creation failed: ' . $e->getMessage()];
        }
    }

    /**
     * ອັບເດດຂໍ້ມູນຜູ້ສະໜອງ
     */
    public function update($id, $data, $updatedBy) {
        // ກວດສອບວ່າມີຜູ້ສະໜອງບໍ
        $supplier = $this->getById($id);
        if (!$supplier) {
            return ['success' => false, 'message' => 'Supplier not found'];
        }

        // ກວດສອບຂໍ້ມູນຊ້ຳ (ຍົກເວັ້ນ ID ປັດຈຸບັນ)
        if (!empty($data['supplier_code']) && $data['supplier_code'] !== $supplier['supplier_code']) {
            $checkSql = "SELECT id FROM suppliers WHERE supplier_code = ? AND id != ?";
            $checkStmt = $this->db->prepare($checkSql);
            $checkStmt->execute([$data['supplier_code'], $id]);
            if ($checkStmt->fetch()) {
                return ['success' => false, 'message' => 'Supplier code already exists'];
            }
        }

        if (!empty($data['email']) && $data['email'] !== $supplier['email']) {
            $checkSql = "SELECT id FROM suppliers WHERE email = ? AND id != ?";
            $checkStmt = $this->db->prepare($checkSql);
            $checkStmt->execute([$data['email'], $id]);
            if ($checkStmt->fetch()) {
                return ['success' => false, 'message' => 'Email already exists'];
            }
        }

        if (!empty($data['phone']) && $data['phone'] !== $supplier['phone']) {
            $checkSql = "SELECT id FROM suppliers WHERE phone = ? AND id != ?";
            $checkStmt = $this->db->prepare($checkSql);
            $checkStmt->execute([$data['phone'], $id]);
            if ($checkStmt->fetch()) {
                return ['success' => false, 'message' => 'Phone number already exists'];
            }
        }

        $fields = [];
        $params = [];

        $allowedFields = [
            'supplier_code', 'supplier_name', 'contact_person', 'phone',
            'email', 'address', 'tax_id', 'payment_terms', 'status'
        ];

        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $fields[] = "{$field} = ?";
                $params[] = $data[$field];
            }
        }

        if (empty($fields)) {
            return ['success' => false, 'message' => 'No data to update'];
        }

        // ເພີ່ມ updated_by ແລະ updated_at
        $fields[] = "updated_by = ?";
        $fields[] = "updated_at = NOW()";
        $params[] = $updatedBy;
        $params[] = $id;

        $sql = "UPDATE suppliers SET " . implode(', ', $fields) . " WHERE id = ?";
        
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            
            return ['success' => true, 'message' => 'Supplier updated successfully'];
        } catch (PDOException $e) {
            error_log("Update supplier failed: " . $e->getMessage());
            return ['success' => false, 'message' => 'Update failed: ' . $e->getMessage()];
        }
    }

    /**
     * ອັບເດດສະຖານະຜູ້ສະໜອງ
     */
    public function updateStatus($id, $status, $updatedBy) {
        if (!in_array($status, $this->allowedStatus)) {
            return ['success' => false, 'message' => 'Invalid status value'];
        }

        try {
            $stmt = $this->db->prepare(
                "UPDATE suppliers SET status = ?, updated_by = ?, updated_at = NOW() WHERE id = ?"
            );
            $stmt->execute([$status, $updatedBy, $id]);
            
            return ['success' => true, 'message' => 'Status updated successfully'];
        } catch (PDOException $e) {
            error_log("Update status failed: " . $e->getMessage());
            return ['success' => false, 'message' => 'Update failed: ' . $e->getMessage()];
        }
    }

    /**
     * ລຶບຜູ້ສະໜອງ (soft delete - ຖ້າມີ is_active field)
     * ຖ້າບໍ່ມີ, ໃຊ້ການລຶບແບບຖາວອນ
     */
    public function delete($id) {
        // ກວດສອບວ່າມີການໃຊ້ງານຢູ່ບໍ (ເຊັ່ນ: ມີລາຍການຊື້)
        $checkSql = "SELECT COUNT(*) as count FROM purchase_orders WHERE supplier_id = ?";
        $checkStmt = $this->db->prepare($checkSql);
        $checkStmt->execute([$id]);
        $result = $checkStmt->fetch();
        
        if ($result && $result['count'] > 0) {
            return ['success' => false, 'message' => 'Cannot delete supplier with existing purchase orders'];
        }

        try {
            $stmt = $this->db->prepare("DELETE FROM suppliers WHERE id = ?");
            $stmt->execute([$id]);
            
            return ['success' => true, 'message' => 'Supplier deleted successfully'];
        } catch (PDOException $e) {
            error_log("Delete supplier failed: " . $e->getMessage());
            return ['success' => false, 'message' => 'Delete failed: ' . $e->getMessage()];
        }
    }

    /**
     * ສ້າງລະຫັດຜູ້ສະໜອງອັດຕະໂນມັດ
     */
    private function generateSupplierCode() {
        $prefix = 'SUP';
        $year = date('Y');
        $month = date('m');
        
        $stmt = $this->db->query("SELECT COUNT(*) as count FROM suppliers WHERE YEAR(created_at) = YEAR(NOW())");
        $count = $stmt->fetch()['count'] + 1;
        
        return $prefix . $year . $month . str_pad($count, 4, '0', STR_PAD_LEFT);
    }

    /**
     * ດຶງສະຖິຕິຜູ້ສະໜອງ
     */
    public function getSupplierStats() {
        $stats = [];

        // ຈຳນວນຜູ້ສະໜອງທັງໝົດ
        $stmt = $this->db->query("SELECT COUNT(*) as total FROM suppliers");
        $stats['total_suppliers'] = $stmt->fetch()['total'];

        // ຈຳນວນຜູ້ສະໜອງແຕ່ລະສະຖານະ
        $statusStmt = $this->db->query("SELECT status, COUNT(*) as count FROM suppliers GROUP BY status");
        $statusStats = [];
        while ($row = $statusStmt->fetch()) {
            $statusStats[$row['status']] = $row['count'];
        }
        $stats['status_stats'] = $statusStats;

        // ຜູ້ສະໜອງໃໝ່ປະຈຳເດືອນ
        $monthStmt = $this->db->query(
            "SELECT COUNT(*) as count FROM suppliers 
             WHERE MONTH(created_at) = MONTH(NOW()) 
             AND YEAR(created_at) = YEAR(NOW())"
        );
        $stats['new_this_month'] = $monthStmt->fetch()['count'];

        return $stats;
    }

    /**
     * ຊອກຫາຜູ້ສະໜອງ
     */
    public function searchSuppliers($keyword) {
        $sql = "SELECT s.*,
                       updater.first_name as updated_by_name
                FROM suppliers s
                LEFT JOIN users updater ON s.updated_by = updater.id
                WHERE s.supplier_code LIKE ? OR s.supplier_name LIKE ? 
                   OR s.contact_person LIKE ? OR s.phone LIKE ? 
                   OR s.email LIKE ? OR s.tax_id LIKE ?
                ORDER BY s.supplier_name ASC
                LIMIT 50";
        
        $searchTerm = "%{$keyword}%";
        $params = [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm];

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
?>