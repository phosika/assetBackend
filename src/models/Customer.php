<?php
// /home/phosika/Fixasset/assetapplication/BACKEND/src/models/Customer.php

require_once __DIR__ . '/../config/database.php';

class Customer {
    private $db;
    private $table = 'customers';

    public function __construct() {
        $this->db = Database::getInstance();
        if (!$this->db) {
            error_log("Database connection failed in Customer model");
            throw new Exception("Database connection failed");
        }
    }

    /**
     * ດຶງຂໍ້ມູນລູກຄ້າທັງໝົດ
     */
    public function getAllCustomers($filters = [], $companyId = null, $branchId = null, $departmentId = null) {
        try {
            $sql = "SELECT c.*,
                           comp.company_name,
                           b.branch_name,
                           d.department_name,
                           CONCAT(u.first_name, ' ', u.last_name) as user_name,
                           CONCAT(cu.first_name, ' ', cu.last_name) as created_by_name
                    FROM {$this->table} c
                    LEFT JOIN companies comp ON c.company_id = comp.id
                    LEFT JOIN branches b ON c.branch_id = b.id
                    LEFT JOIN departments d ON c.department_id = d.id
                    LEFT JOIN users u ON c.user_id = u.id
                    LEFT JOIN users cu ON c.created_by = cu.id
                    WHERE 1=1";
            
            $params = [];
            $countSql = "SELECT COUNT(*) as total FROM {$this->table} c WHERE 1=1";
            $countParams = [];

            // ກັ່ນຕອງຕາມບໍລິສັດ
            if ($companyId) {
                $sql .= " AND c.company_id = ?";
                $params[] = $companyId;
                $countSql .= " AND company_id = ?";
                $countParams[] = $companyId;
            }

            // ກັ່ນຕອງຕາມສາຂາ
            if ($branchId) {
                $sql .= " AND c.branch_id = ?";
                $params[] = $branchId;
                $countSql .= " AND branch_id = ?";
                $countParams[] = $branchId;
            }

            // ກັ່ນຕອງຕາມພະແນກ
            if ($departmentId) {
                $sql .= " AND c.department_id = ?";
                $params[] = $departmentId;
                $countSql .= " AND department_id = ?";
                $countParams[] = $departmentId;
            }

            // ຄົ້ນຫາ
            if (!empty($filters['search'])) {
                $sql .= " AND (c.customer_code LIKE ? OR c.customer_name LIKE ? OR c.phone LIKE ?)";
                $searchTerm = "%{$filters['search']}%";
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                
                $countSql .= " AND (customer_code LIKE ? OR customer_name LIKE ? OR phone LIKE ?)";
                $countParams[] = $searchTerm;
                $countParams[] = $searchTerm;
                $countParams[] = $searchTerm;
            }

            // ກັ່ນຕອງຕາມສະຖານະ
            if (isset($filters['status'])) {
                $sql .= " AND c.status = ?";
                $params[] = $filters['status'];
                $countSql .= " AND status = ?";
                $countParams[] = $filters['status'];
            }

            // ນັບຈຳນວນທັງໝົດ
            $countStmt = $this->db->prepare($countSql);
            if (!empty($countParams)) {
                $countStmt->execute($countParams);
            } else {
                $countStmt->execute();
            }
            $totalResult = $countStmt->fetch(PDO::FETCH_ASSOC);
            $total = $totalResult ? (int)$totalResult['total'] : 0;

            // ຈັດລຽງ
            $sortBy = $filters['sort_by'] ?? 'c.created_at';
            $sortOrder = $filters['sort_order'] ?? 'DESC';
            
            $sql .= " ORDER BY {$sortBy} {$sortOrder}";

            // ແບ່ງໜ້າ
            $page = isset($filters['page']) ? max(1, (int)$filters['page']) : 1;
            $limit = isset($filters['limit']) ? (int)$filters['limit'] : 20;
            $offset = ($page - 1) * $limit;
            
            $sql .= " LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'data' => $customers,
                'total' => $total,
                'current_page' => $page,
                'per_page' => $limit,
                'last_page' => $total > 0 ? ceil($total / $limit) : 1
            ];

        } catch (Exception $e) {
            error_log("Error in getAllCustomers: " . $e->getMessage());
            return [
                'data' => [],
                'total' => 0,
                'current_page' => 1,
                'per_page' => 20,
                'last_page' => 1
            ];
        }
    }

    /**
     * ດຶງຂໍ້ມູນລູກຄ້າຕາມ ID
     */
    public function getCustomerById($id) {
        try {
            $sql = "SELECT c.*,
                           comp.company_name,
                           b.branch_name,
                           d.department_name,
                           CONCAT(u.first_name, ' ', u.last_name) as user_name,
                           CONCAT(cu.first_name, ' ', cu.last_name) as created_by_name
                    FROM {$this->table} c
                    LEFT JOIN companies comp ON c.company_id = comp.id
                    LEFT JOIN branches b ON c.branch_id = b.id
                    LEFT JOIN departments d ON c.department_id = d.id
                    LEFT JOIN users u ON c.user_id = u.id
                    LEFT JOIN users cu ON c.created_by = cu.id
                    WHERE c.id = ?";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            error_log("Error in getCustomerById: " . $e->getMessage());
            return null;
        }
    }

    /**
     * ດຶງຂໍ້ມູນລູກຄ້າສຳລັບ dropdown
     */
    public function getCustomersForDropdown($companyId = null, $branchId = null, $search = '') {
        try {
            $sql = "SELECT c.id, c.customer_code, c.customer_name, c.phone,
                           comp.company_name, b.branch_name
                    FROM {$this->table} c
                    LEFT JOIN companies comp ON c.company_id = comp.id
                    LEFT JOIN branches b ON c.branch_id = b.id
                    WHERE c.status = 1";
            
            $params = [];

            if ($companyId) {
                $sql .= " AND c.company_id = ?";
                $params[] = $companyId;
            }

            if ($branchId) {
                $sql .= " AND c.branch_id = ?";
                $params[] = $branchId;
            }
            
            if (!empty($search)) {
                $sql .= " AND (c.customer_code LIKE ? OR c.customer_name LIKE ?)";
                $params[] = "%$search%";
                $params[] = "%$search%";
            }
            
            $sql .= " ORDER BY c.customer_name ASC LIMIT 50";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("Error in getCustomersForDropdown: " . $e->getMessage());
            return [];
        }
    }

    /**
     * ສ້າງລູກຄ້າໃໝ່
     */
    public function createCustomer($data, $createdBy = null) {
        try {
            // ກວດສອບວ່າລະຫັດລູກຄ້າຊໍ້າກັນບໍ
            $checkSql = "SELECT id FROM {$this->table} WHERE customer_code = ?";
            $checkStmt = $this->db->prepare($checkSql);
            $checkStmt->execute([$data['customer_code']]);
            
            if ($checkStmt->fetch()) {
                return [
                    'success' => false,
                    'message' => 'ລະຫັດລູກຄ້ານີ້ມີໃນລະບົບແລ້ວ'
                ];
            }

            $sql = "INSERT INTO {$this->table} (
                        customer_code, company_id, branch_id, department_id, user_id,
                        customer_name, contact_person, phone, email,
                        address, tax_id, payment_terms, credit_limit, status,
                        created_by, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['customer_code'],
                $data['company_id'] ?? null,
                $data['branch_id'] ?? null,
                $data['department_id'] ?? null,
                $data['user_id'] ?? null,
                $data['customer_name'],
                $data['contact_person'] ?? null,
                $data['phone'] ?? null,
                $data['email'] ?? null,
                $data['address'] ?? null,
                $data['tax_id'] ?? null,
                $data['payment_terms'] ?? null,
                $data['credit_limit'] ?? 0,
                $data['status'] ?? 1,
                $createdBy
            ]);

            return [
                'success' => true,
                'message' => 'ສ້າງລູກຄ້າສຳເລັດ',
                'id' => $this->db->lastInsertId()
            ];

        } catch (Exception $e) {
            error_log("Error in createCustomer: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'ສ້າງລູກຄ້າບໍ່ສຳເລັດ: ' . $e->getMessage()
            ];
        }
    }

    /**
     * ອັບເດດລູກຄ້າ
     */
    public function updateCustomer($id, $data) {
        try {
            // ກວດສອບວ່າລູກຄ້າມີຢູ່
            $existing = $this->getCustomerById($id);
            if (!$existing) {
                return [
                    'success' => false,
                    'message' => 'ບໍ່ພົບຂໍ້ມູນລູກຄ້າ'
                ];
            }

            // ກວດສອບວ່າລະຫັດລູກຄ້າຊໍ້າກັນບໍ (ຖ້າມີການປ່ຽນແປງ)
            if ($data['customer_code'] !== $existing['customer_code']) {
                $checkSql = "SELECT id FROM {$this->table} WHERE customer_code = ? AND id != ?";
                $checkStmt = $this->db->prepare($checkSql);
                $checkStmt->execute([$data['customer_code'], $id]);
                
                if ($checkStmt->fetch()) {
                    return [
                        'success' => false,
                        'message' => 'ລະຫັດລູກຄ້ານີ້ມີໃນລະບົບແລ້ວ'
                    ];
                }
            }

            $sql = "UPDATE {$this->table} 
                    SET customer_code = ?,
                        company_id = ?,
                        branch_id = ?,
                        department_id = ?,
                        user_id = ?,
                        customer_name = ?,
                        contact_person = ?,
                        phone = ?,
                        email = ?,
                        address = ?,
                        tax_id = ?,
                        payment_terms = ?,
                        credit_limit = ?,
                        status = ?,
                        updated_at = NOW()
                    WHERE id = ?";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['customer_code'],
                $data['company_id'] ?? null,
                $data['branch_id'] ?? null,
                $data['department_id'] ?? null,
                $data['user_id'] ?? null,
                $data['customer_name'],
                $data['contact_person'] ?? null,
                $data['phone'] ?? null,
                $data['email'] ?? null,
                $data['address'] ?? null,
                $data['tax_id'] ?? null,
                $data['payment_terms'] ?? null,
                $data['credit_limit'] ?? 0,
                $data['status'] ?? 1,
                $id
            ]);

            return [
                'success' => true,
                'message' => 'ອັບເດດລູກຄ້າສຳເລັດ'
            ];

        } catch (Exception $e) {
            error_log("Error in updateCustomer: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'ອັບເດດລູກຄ້າບໍ່ສຳເລັດ: ' . $e->getMessage()
            ];
        }
    }
}
?>