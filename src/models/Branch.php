<?php
// /var/www/html/models/Branch.php

require_once __DIR__ . '/../config/database.php';

class Branch {
    private $db;
    private $table = 'branches';

    public function __construct() {
        $this->db = Database::getInstance();
        if (!$this->db) {
            error_log("Database connection failed in Branch model");
            throw new Exception("Database connection failed");
        }
    }

    /**
     * ດຶງຂໍ້ມູນສາຂາທັງໝົດ
     */
    public function getAllBranches($filters = []) {
        try {
            $sql = "SELECT b.*, c.company_name 
                    FROM {$this->table} b
                    LEFT JOIN companies c ON b.company_id = c.id
                    WHERE 1=1";
            
            $params = [];

            // ກັ່ນຕອງຕາມບໍລິສັດ
            if (!empty($filters['company_id'])) {
                $sql .= " AND b.company_id = ?";
                $params[] = $filters['company_id'];
            }

            // ກັ່ນຕອງຕາມສະຖານະ
            if (isset($filters['status'])) {
                $sql .= " AND b.status = ?";
                $params[] = $filters['status'];
            }

            $sql .= " ORDER BY b.branch_name ASC";

            // ຈຳກັດຈຳນວນ
            if (!empty($filters['limit'])) {
                $sql .= " LIMIT ?";
                $params[] = $filters['limit'];
            }

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'data' => $data,
                'total' => count($data)
            ];

        } catch (Exception $e) {
            error_log("Error in getAllBranches: " . $e->getMessage());
            return [
                'data' => [],
                'total' => 0
            ];
        }
    }

    /**
     * ດຶງຂໍ້ມູນສາຂາຕາມ ID
     */
    public function getBranchById($id) {
        try {
            $sql = "SELECT b.*, c.company_name 
                    FROM {$this->table} b
                    LEFT JOIN companies c ON b.company_id = c.id
                    WHERE b.id = ?";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            error_log("Error in getBranchById: " . $e->getMessage());
            return null;
        }
    }

    /**
     * ດຶງຂໍ້ມູນສາຂາສຳລັບ dropdown
     */
    public function getBranchesForDropdown($companyId = null) {
        try {
            $sql = "SELECT id, branch_code, branch_name 
                    FROM {$this->table} 
                    WHERE status = 1";
            
            $params = [];

            if ($companyId) {
                $sql .= " AND company_id = ?";
                $params[] = $companyId;
            }
            
            $sql .= " ORDER BY branch_name ASC";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("Error in getBranchesForDropdown: " . $e->getMessage());
            return [];
        }
    }

    public function updatePrintStatus($id, $printed = true) {
    try {
        // ກວດສອບວ່າມີ barcode ນີ້ບໍ
        $checkSql = "SELECT id FROM {$this->table} WHERE id = ?";
        $checkStmt = $this->db->prepare($checkSql);
        $checkStmt->execute([$id]);
        
        if (!$checkStmt->fetch()) {
            return [
                'success' => false,
                'message' => 'ບໍ່ພົບ barcode ນີ້'
            ];
        }
        
        $sql = "UPDATE {$this->table} 
                SET printed = ?, 
                    printed_at = CASE WHEN ? = 1 THEN NOW() ELSE printed_at END,
                    updated_at = NOW()
                WHERE id = ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$printed, $printed, $id]);

        return [
            'success' => true,
            'message' => 'ອັບເດດສະຖານະສຳເລັດ'
        ];

    } catch (Exception $e) {
        error_log("Error in updatePrintStatus: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'ອັບເດດສະຖານະບໍ່ສຳເລັດ: ' . $e->getMessage()
        ];
    }
}
}
?>