<?php
// /home/phosika/Fixasset/assetapplication/BACKEND/src/models/Supplier.php

require_once __DIR__ . '/../config/database.php';

class Supplier {
    private $db;
    private $table = 'suppliers';

    public function __construct() {
        $this->db = Database::getInstance();
        if (!$this->db) {
            error_log("Database connection failed in Supplier model");
            throw new Exception("Database connection failed");
        }
    }

 
    /**
     * ດຶງຂໍ້ມູນຜູ້ສະໜອງທັງໝົດ (ຮອງຮັບການຄົ້ນຫາ ແລະ ແບ່ງໜ້າ)
     */
    public function getAllSuppliers($filters = []) {
        try {
            // ສ້າງ SQL ພື້ນຖານ
            $sql = "SELECT s.*,
                           CONCAT(u.first_name, ' ', u.last_name) as created_by_name,
                           CONCAT(up.first_name, ' ', up.last_name) as updated_by_name
                    FROM {$this->table} s
                    LEFT JOIN users u ON s.created_by = u.id
                    LEFT JOIN users up ON s.updated_by = up.id
                    WHERE 1=1";
            
            $params = [];
            $countSql = "SELECT COUNT(*) as total FROM {$this->table} s WHERE 1=1";
            $countParams = [];

            // ຄົ້ນຫາ
            if (!empty($filters['search'])) {
                $sql .= " AND (s.supplier_code LIKE ? OR s.supplier_name LIKE ? OR s.contact_person LIKE ? OR s.phone LIKE ? OR s.email LIKE ?)";
                $searchTerm = "%{$filters['search']}%";
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                
                $countSql .= " AND (supplier_code LIKE ? OR supplier_name LIKE ? OR contact_person LIKE ? OR phone LIKE ? OR email LIKE ?)";
                $countParams[] = $searchTerm;
                $countParams[] = $searchTerm;
                $countParams[] = $searchTerm;
                $countParams[] = $searchTerm;
                $countParams[] = $searchTerm;
            }

            // ກັ່ນຕອງຕາມສະຖານະ
            if (isset($filters['status']) && $filters['status'] !== '') {
                $sql .= " AND s.status = ?";
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
            $sql .= " ORDER BY s.supplier_code ASC";

            // ແບ່ງໜ້າ
            $page = isset($filters['page']) ? max(1, (int)$filters['page']) : 1;
            $limit = isset($filters['limit']) ? (int)$filters['limit'] : 20;
            $offset = ($page - 1) * $limit;
            
            $sql .= " LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;

            // ດຳເນີນການຄົ້ນຫາ
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'data' => $suppliers,
                'total' => $total,
                'current_page' => $page,
                'per_page' => $limit,
                'last_page' => $total > 0 ? ceil($total / $limit) : 1
            ];

        } catch (Exception $e) {
            error_log("Error in getAllSuppliers: " . $e->getMessage());
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
     * ດຶງຂໍ້ມູນໃບສັ່ງຊື້ຕາມ ID ພ້ອມລາຍການສິນຄ້າ
     */
    public function getPurchaseOrderById($id) {
        try {
            $sql = "SELECT po.*,
                        s.supplier_name,
                        s.supplier_code,
                        s.contact_person,
                        s.phone as supplier_phone,
                        s.email as supplier_email,
                        s.address as supplier_address,
                        s.tax_id as supplier_tax_id,
                        CONCAT(u.first_name, ' ', u.last_name) as created_by_name,
                        CONCAT(au.first_name, ' ', au.last_name) as approved_by_name
                    FROM {$this->table} po
                    LEFT JOIN suppliers s ON po.supplier_id = s.id
                    LEFT JOIN users u ON po.created_by = u.id
                    LEFT JOIN users au ON po.approved_by = au.id
                    WHERE po.id = ?";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id]);
            $purchaseOrder = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($purchaseOrder) {
                // ສຳຄັນ: ດຶງລາຍການສິນຄ້າຈາກ purchase_order_details
                $itemsSql = "SELECT pod.*, 
                                    i.item_code, 
                                    i.item_name,
                                    i.barcode,
                                    i.unit
                            FROM {$this->detailsTable} pod
                            LEFT JOIN inventory_items i ON pod.item_id = i.id
                            WHERE pod.po_id = ?
                            ORDER BY pod.id ASC";
                $itemsStmt = $this->db->prepare($itemsSql);
                $itemsStmt->execute([$id]);
                $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
                
                // ສົ່ງ items ກັບໄປໃນ response
                $purchaseOrder['items'] = $items;
                
                // ຄຳນວນຍອດລວມທີ່ຮັບແລ້ວ
                $receivedTotal = 0;
                foreach ($items as $item) {
                    $receivedTotal += ($item['received_quantity'] ?? 0) * $item['unit_price'];
                }
                $purchaseOrder['received_amount'] = $receivedTotal;
                
                error_log("Found PO ID {$id} with " . count($items) . " items");
            } else {
                error_log("No PO found with ID: {$id}");
            }

            return $purchaseOrder ?: null;

        } catch (Exception $e) {
            error_log("Error in getPurchaseOrderById: " . $e->getMessage());
            return null;
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
                        payment_terms,
                        status
                    FROM {$this->table} 
                    WHERE 1=1";
            
            $params = [];

            if ($activeOnly) {
                $sql .= " AND status = 1";
            }

            if (!empty($search)) {
                $sql .= " AND (supplier_code LIKE ? OR supplier_name LIKE ? OR contact_person LIKE ? OR phone LIKE ? OR email LIKE ?)";
                $searchTerm = "%{$search}%";
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }
            
            $sql .= " ORDER BY supplier_code ASC LIMIT 50";
            
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
     * ດຶງຂໍ້ມູນຜູ້ສະໜອງຕາມ ID
     */
    public function getSupplierById($id) {
        try {
            $sql = "SELECT * FROM {$this->table} WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id]);
            
            return $stmt->fetch(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            error_log("Error in getSupplierById: " . $e->getMessage());
            return null;
        }
    }

    /**
     * ສ້າງຜູ້ສະໜອງໃໝ່
     */
    public function createSupplier($data, $createdBy = null) {
        try {
            // ກວດສອບວ່າລະຫັດຜູ້ສະໜອງຊໍ້າກັນບໍ
            $checkSql = "SELECT id FROM {$this->table} WHERE supplier_code = ?";
            $checkStmt = $this->db->prepare($checkSql);
            $checkStmt->execute([$data['supplier_code']]);
            
            if ($checkStmt->fetch()) {
                return [
                    'success' => false,
                    'message' => 'ລະຫັດຜູ້ສະໜອງນີ້ມີໃນລະບົບແລ້ວ'
                ];
            }

            $sql = "INSERT INTO {$this->table} (
                        supplier_code,
                        supplier_name,
                        contact_person,
                        phone,
                        email,
                        address,
                        tax_id,
                        payment_terms,
                        status,
                        created_at,
                        created_by
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['supplier_code'],
                $data['supplier_name'],
                $data['contact_person'] ?? null,
                $data['phone'] ?? null,
                $data['email'] ?? null,
                $data['address'] ?? null,
                $data['tax_id'] ?? null,
                $data['payment_terms'] ?? null,
                $data['status'] ?? 1,
                $createdBy
            ]);

            $newId = $this->db->lastInsertId();

            return [
                'success' => true,
                'message' => 'ສ້າງຜູ້ສະໜອງສຳເລັດ',
                'id' => $newId
            ];

        } catch (Exception $e) {
            error_log("Error in createSupplier: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'ສ້າງຜູ້ສະໜອງບໍ່ສຳເລັດ: ' . $e->getMessage()
            ];
        }
    }

    /**
     * ອັບເດດຜູ້ສະໜອງ
     */
    public function updateSupplier($id, $data, $updatedBy = null) {
        try {
            $sql = "UPDATE {$this->table} SET 
                        supplier_name = ?,
                        contact_person = ?,
                        phone = ?,
                        email = ?,
                        address = ?,
                        tax_id = ?,
                        payment_terms = ?,
                        updated_at = NOW(),
                        updated_by = ?
                    WHERE id = ?";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['supplier_name'],
                $data['contact_person'] ?? null,
                $data['phone'] ?? null,
                $data['email'] ?? null,
                $data['address'] ?? null,
                $data['tax_id'] ?? null,
                $data['payment_terms'] ?? null,
                $updatedBy,
                $id
            ]);

            return [
                'success' => true,
                'message' => 'ອັບເດດຜູ້ສະໜອງສຳເລັດ'
            ];

        } catch (Exception $e) {
            error_log("Error in updateSupplier: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'ອັບເດດຜູ້ສະໜອງບໍ່ສຳເລັດ: ' . $e->getMessage()
            ];
        }
    }

    /**
     * ລຶບຜູ້ສະໜອງ (soft delete)
     */
    public function deleteSupplier($id) {
        try {
            $sql = "UPDATE {$this->table} SET status = 0, deleted_at = NOW() WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id]);

            return [
                'success' => true,
                'message' => 'ລຶບຜູ້ສະໜອງສຳເລັດ'
            ];

        } catch (Exception $e) {
            error_log("Error in deleteSupplier: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'ລຶບຜູ້ສະໜອງບໍ່ສຳເລັດ: ' . $e->getMessage()
            ];
        }
    }

    /**
     * ປ່ຽນສະຖານະຜູ້ສະໜອງ
     */
    public function toggleStatus($id, $status, $updatedBy = null) {
        try {
            $sql = "UPDATE {$this->table} 
                    SET status = ?, 
                        updated_at = NOW(), 
                        updated_by = ? 
                    WHERE id = ?";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$status, $updatedBy, $id]);

            return [
                'success' => true,
                'message' => $status ? 'ເປີດໃຊ້ງານຜູ້ສະໜອງສຳເລັດ' : 'ປິດໃຊ້ງານຜູ້ສະໜອງສຳເລັດ'
            ];

        } catch (Exception $e) {
            error_log("Error in toggleStatus: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'ປ່ຽນສະຖານະຜູ້ສະໜອງບໍ່ສຳເລັດ: ' . $e->getMessage()
            ];
        }
    }

    /**
     * ດຶງສະຖິຕິຜູ້ສະໜອງ
     */
    public function getSupplierStats() {
        try {
            $stats = [];

            // ຈຳນວນຜູ້ສະໜອງທັງໝົດ
            $totalSql = "SELECT COUNT(*) as total FROM {$this->table}";
            $totalStmt = $this->db->query($totalSql);
            $stats['total_suppliers'] = $totalStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

            // ຜູ້ສະໜອງທີ່ເປີດໃຊ້ງານ
            $activeSql = "SELECT COUNT(*) as active FROM {$this->table} WHERE status = 1";
            $activeStmt = $this->db->query($activeSql);
            $stats['active_suppliers'] = $activeStmt->fetch(PDO::FETCH_ASSOC)['active'] ?? 0;

            // ຜູ້ສະໜອງທີ່ປິດໃຊ້ງານ
            $inactiveSql = "SELECT COUNT(*) as inactive FROM {$this->table} WHERE status = 0";
            $inactiveStmt = $this->db->query($inactiveSql);
            $stats['inactive_suppliers'] = $inactiveStmt->fetch(PDO::FETCH_ASSOC)['inactive'] ?? 0;

            return $stats;

        } catch (Exception $e) {
            error_log("Error in getSupplierStats: " . $e->getMessage());
            return [
                'total_suppliers' => 0,
                'active_suppliers' => 0,
                'inactive_suppliers' => 0
            ];
        }
    }
}
?>