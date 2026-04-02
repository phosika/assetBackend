<?php
require_once __DIR__ . '/../config/database.php';

class Warehouse {
    private $db;
    private $table = 'warehouses';

    public function __construct() {
        $this->db = Database::getInstance();
        if (!$this->db) {
            error_log("Database connection failed in Warehouse model");
            throw new Exception("Database connection failed");
        }
    }

    /**
     * ດຶງຂໍ້ມູນສາງທັງໝົດ
     */
    public function getAllWarehouses($filters = []) {
        try {
            $sql = "SELECT w.*,
                           CONCAT(u.first_name, ' ', u.last_name) as manager_name,
                           u.email as manager_email,
                           u.phone as manager_phone
                    FROM {$this->table} w
                    LEFT JOIN users u ON w.manager_id = u.id
                    WHERE 1=1";
            
            $params = [];
            $countSql = "SELECT COUNT(*) as total FROM {$this->table} w WHERE 1=1";
            $countParams = [];

            // ຄົ້ນຫາ
            if (!empty($filters['search'])) {
                $sql .= " AND (w.warehouse_code LIKE ? OR w.warehouse_name LIKE ? OR w.location LIKE ?)";
                $searchTerm = "%{$filters['search']}%";
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                
                $countSql .= " AND (warehouse_code LIKE ? OR warehouse_name LIKE ? OR location LIKE ?)";
                $countParams[] = $searchTerm;
                $countParams[] = $searchTerm;
                $countParams[] = $searchTerm;
            }

            // ກັ່ນຕອງຕາມສະຖານະ
            if (isset($filters['is_active']) && $filters['is_active'] !== '') {
                $sql .= " AND w.is_active = ?";
                $params[] = $filters['is_active'];
                
                $countSql .= " AND is_active = ?";
                $countParams[] = $filters['is_active'];
            }

            // ກັ່ນຕອງຕາມຜູ້ຈັດການ
            if (!empty($filters['manager_id'])) {
                $sql .= " AND w.manager_id = ?";
                $params[] = $filters['manager_id'];
                
                $countSql .= " AND manager_id = ?";
                $countParams[] = $filters['manager_id'];
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
            $sortBy = $filters['sort_by'] ?? 'w.warehouse_code';
            $sortOrder = $filters['sort_order'] ?? 'ASC';
            
            $allowedSortColumns = ['w.warehouse_code', 'w.warehouse_name', 'w.location', 'w.created_at'];
            if (!in_array($sortBy, $allowedSortColumns)) {
                $sortBy = 'w.warehouse_code';
            }
            $sortOrder = strtoupper($sortOrder) === 'DESC' ? 'DESC' : 'ASC';
            
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
            $warehouses = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'data' => $warehouses,
                'total' => $total,
                'current_page' => $page,
                'per_page' => $limit,
                'last_page' => $total > 0 ? ceil($total / $limit) : 1
            ];

        } catch (Exception $e) {
            error_log("Error in getAllWarehouses: " . $e->getMessage());
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
     * ດຶງຂໍ້ມູນສາງສຳລັບ dropdown
     */
    public function getWarehousesForDropdown($activeOnly = true) {
        try {
            $sql = "SELECT id, warehouse_code, warehouse_name, location 
                    FROM {$this->table}";
            
            if ($activeOnly) {
                $sql .= " WHERE is_active = 1";
            }
            
            $sql .= " ORDER BY warehouse_code ASC";
            
            $stmt = $this->db->query($sql);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("Error in getWarehousesForDropdown: " . $e->getMessage());
            return [];
        }
    }

    /**
     * ດຶງຂໍ້ມູນສາງຕາມ ID
     */
    public function getWarehouseById($id) {
        try {
            $sql = "SELECT w.*,
                           CONCAT(u.first_name, ' ', u.last_name) as manager_name,
                           u.email as manager_email,
                           u.phone as manager_phone,
                           u.username as manager_username
                    FROM {$this->table} w
                    LEFT JOIN users u ON w.manager_id = u.id
                    WHERE w.id = ?";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id]);
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ?: null;
            
        } catch (Exception $e) {
            error_log("Error in getWarehouseById: " . $e->getMessage());
            return null;
        }
    }

    /**
     * ດຶງຂໍ້ມູນສາງຕາມລະຫັດ
     */
    public function getWarehouseByCode($code) {
        try {
            $sql = "SELECT * FROM {$this->table} WHERE warehouse_code = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$code]);
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("Error in getWarehouseByCode: " . $e->getMessage());
            return null;
        }
    }

    /**
     * ສ້າງສາງໃໝ່
     */
    public function createWarehouse($data, $createdBy = null) {
        try {
            // ກວດສອບວ່າລະຫັດສາງຊໍ້າກັນບໍ
            $checkSql = "SELECT id FROM {$this->table} WHERE warehouse_code = ?";
            $checkStmt = $this->db->prepare($checkSql);
            $checkStmt->execute([$data['warehouse_code']]);
            
            if ($checkStmt->fetch()) {
                return [
                    'success' => false,
                    'message' => 'ລະຫັດສາງນີ້ມີໃນລະບົບແລ້ວ'
                ];
            }

            $sql = "INSERT INTO {$this->table} (
                        warehouse_code, 
                        warehouse_name, 
                        location, 
                        manager_id,
                        is_active,
                        created_at,
                        created_by
                    ) VALUES (?, ?, ?, ?, ?, NOW(), ?)";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['warehouse_code'],
                $data['warehouse_name'],
                $data['location'] ?? null,
                $data['manager_id'] ?? null,
                $data['is_active'] ?? 1,
                $createdBy
            ]);

            $newId = $this->db->lastInsertId();

            return [
                'success' => true,
                'message' => 'ສ້າງສາງສຳເລັດ',
                'id' => $newId
            ];

        } catch (Exception $e) {
            error_log("Error in createWarehouse: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'ສ້າງສາງບໍ່ສຳເລັດ: ' . $e->getMessage()
            ];
        }
    }

    /**
     * ອັບເດດຂໍ້ມູນສາງ
     */
    public function updateWarehouse($id, $data, $updatedBy = null) {
        try {
            // ກວດສອບວ່າມີສາງນີ້ບໍ
            $checkSql = "SELECT id FROM {$this->table} WHERE id = ?";
            $checkStmt = $this->db->prepare($checkSql);
            $checkStmt->execute([$id]);
            
            if (!$checkStmt->fetch()) {
                return [
                    'success' => false,
                    'message' => 'ບໍ່ພົບຂໍ້ມູນສາງ'
                ];
            }

            // ກວດສອບວ່າລະຫັດສາງຊໍ້າກັນບໍ (ຖ້າມີການປ່ຽນລະຫັດ)
            if (isset($data['warehouse_code'])) {
                $checkCodeSql = "SELECT id FROM {$this->table} 
                                WHERE warehouse_code = ? AND id != ?";
                $checkCodeStmt = $this->db->prepare($checkCodeSql);
                $checkCodeStmt->execute([$data['warehouse_code'], $id]);
                
                if ($checkCodeStmt->fetch()) {
                    return [
                        'success' => false,
                        'message' => 'ລະຫັດສາງນີ້ມີໃນລະບົບແລ້ວ'
                    ];
                }
            }

            $sql = "UPDATE {$this->table} SET 
                        updated_at = NOW(),
                        updated_by = ?";
            $params = [$updatedBy];

            if (isset($data['warehouse_code'])) {
                $sql .= ", warehouse_code = ?";
                $params[] = $data['warehouse_code'];
            }

            if (isset($data['warehouse_name'])) {
                $sql .= ", warehouse_name = ?";
                $params[] = $data['warehouse_name'];
            }

            if (array_key_exists('location', $data)) {
                $sql .= ", location = ?";
                $params[] = $data['location'];
            }

            if (array_key_exists('manager_id', $data)) {
                $sql .= ", manager_id = ?";
                $params[] = $data['manager_id'];
            }

            if (isset($data['is_active'])) {
                $sql .= ", is_active = ?";
                $params[] = $data['is_active'];
            }

            $sql .= " WHERE id = ?";
            $params[] = $id;

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            return [
                'success' => true,
                'message' => 'ອັບເດດຂໍ້ມູນສາງສຳເລັດ'
            ];

        } catch (Exception $e) {
            error_log("Error in updateWarehouse: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'ອັບເດດຂໍ້ມູນສາງບໍ່ສຳເລັດ: ' . $e->getMessage()
            ];
        }
    }

    /**
     * ລຶບສາງ (soft delete)
     */
    public function deleteWarehouse($id, $deletedBy = null) {
        try {
            // ກວດສອບວ່າມີສິນຄ້າຢູ່ໃນສາງນີ້ບໍ
            $checkStockSql = "SELECT COUNT(*) as total FROM inventory_stock WHERE warehouse_id = ?";
            $checkStockStmt = $this->db->prepare($checkStockSql);
            $checkStockStmt->execute([$id]);
            $stockCount = $checkStockStmt->fetch(PDO::FETCH_ASSOC);

            if ($stockCount && $stockCount['total'] > 0) {
                return [
                    'success' => false,
                    'message' => 'ບໍ່ສາມາດລຶບສາງໄດ້ ເພາະຍັງມີສິນຄ້າຢູ່ໃນສາງນີ້'
                ];
            }

            // Soft delete (set is_active = 0)
            $sql = "UPDATE {$this->table} 
                    SET is_active = 0, 
                        updated_at = NOW(), 
                        updated_by = ? 
                    WHERE id = ?";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$deletedBy, $id]);

            return [
                'success' => true,
                'message' => 'ລຶບສາງສຳເລັດ'
            ];

        } catch (Exception $e) {
            error_log("Error in deleteWarehouse: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'ລຶບສາງບໍ່ສຳເລັດ: ' . $e->getMessage()
            ];
        }
    }

    /**
     * ລຶບສາງແບບຖາວອນ
     */
    public function permanentDeleteWarehouse($id) {
        try {
            // ກວດສອບວ່າມີສິນຄ້າຢູ່ໃນສາງນີ້ບໍ
            $checkStockSql = "SELECT COUNT(*) as total FROM inventory_stock WHERE warehouse_id = ?";
            $checkStockStmt = $this->db->prepare($checkStockSql);
            $checkStockStmt->execute([$id]);
            $stockCount = $checkStockStmt->fetch(PDO::FETCH_ASSOC);

            if ($stockCount && $stockCount['total'] > 0) {
                return [
                    'success' => false,
                    'message' => 'ບໍ່ສາມາດລຶບສາງໄດ້ ເພາະຍັງມີສິນຄ້າຢູ່ໃນສາງນີ້'
                ];
            }

            $sql = "DELETE FROM {$this->table} WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id]);

            return [
                'success' => true,
                'message' => 'ລຶບສາງແບບຖາວອນສຳເລັດ'
            ];

        } catch (Exception $e) {
            error_log("Error in permanentDeleteWarehouse: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'ລຶບສາງບໍ່ສຳເລັດ: ' . $e->getMessage()
            ];
        }
    }


    /**
     * ປ່ຽນສະຖານະສາງ
     */
    public function toggleStatus($id, $isActive, $updatedBy = null) {
        try {
            error_log("WarehouseModel::toggleStatus - ID: $id, isActive: $isActive, updatedBy: $updatedBy");
            
            // ກວດສອບວ່າມີສາງນີ້ບໍ
            $checkSql = "SELECT id, warehouse_name, is_active FROM {$this->table} WHERE id = ?";
            $checkStmt = $this->db->prepare($checkSql);
            $checkStmt->execute([$id]);
            $warehouse = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$warehouse) {
                error_log("Warehouse not found with ID: $id");
                return [
                    'success' => false,
                    'message' => 'ບໍ່ພົບຂໍ້ມູນສາງ'
                ];
            }

            error_log("Current status: " . $warehouse['is_active'] . " -> New status: " . $isActive);

            $sql = "UPDATE {$this->table} 
                    SET is_active = ?, 
                        updated_at = NOW(), 
                        updated_by = ? 
                    WHERE id = ?";
            
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute([$isActive, $updatedBy, $id]);

            if ($result) {
                $message = $isActive ? 'ເປີດໃຊ້ງານສາງສຳເລັດ' : 'ປິດໃຊ້ງານສາງສຳເລັດ';
                error_log("Toggle successful: " . $message);
                
                return [
                    'success' => true,
                    'message' => $message
                ];
            } else {
                error_log("Toggle failed - no rows affected");
                return [
                    'success' => false,
                    'message' => 'ບໍ່ສາມາດປ່ຽນສະຖານະສາງໄດ້'
                ];
            }

        } catch (Exception $e) {
            error_log("Error in toggleStatus: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'ປ່ຽນສະຖານະສາງບໍ່ສຳເລັດ: ' . $e->getMessage()
            ];
        }
    }
    /**
     * ດຶງສະຖິຕິສາງ
     */
    public function getWarehouseStats() {
        try {
            $stats = [];

            // ຈຳນວນສາງທັງໝົດ
            $totalSql = "SELECT COUNT(*) as total FROM {$this->table}";
            $totalStmt = $this->db->query($totalSql);
            $stats['total_warehouses'] = $totalStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

            // ສາງທີ່ເປີດໃຊ້ງານ
            $activeSql = "SELECT COUNT(*) as active FROM {$this->table} WHERE is_active = 1";
            $activeStmt = $this->db->query($activeSql);
            $stats['active_warehouses'] = $activeStmt->fetch(PDO::FETCH_ASSOC)['active'] ?? 0;

            // ສາງທີ່ປິດໃຊ້ງານ
            $inactiveSql = "SELECT COUNT(*) as inactive FROM {$this->table} WHERE is_active = 0";
            $inactiveStmt = $this->db->query($inactiveSql);
            $stats['inactive_warehouses'] = $inactiveStmt->fetch(PDO::FETCH_ASSOC)['inactive'] ?? 0;

            // ຈຳນວນສິນຄ້າທັງໝົດໃນແຕ່ລະສາງ
            $stockSql = "SELECT 
                            w.id,
                            w.warehouse_name,
                            COUNT(DISTINCT s.item_id) as total_items,
                            COALESCE(SUM(s.current_quantity), 0) as total_quantity
                        FROM {$this->table} w
                        LEFT JOIN inventory_stock s ON w.id = s.warehouse_id
                        WHERE w.is_active = 1
                        GROUP BY w.id, w.warehouse_name";
            $stockStmt = $this->db->query($stockSql);
            $stats['warehouse_stock'] = $stockStmt->fetchAll(PDO::FETCH_ASSOC);

            return $stats;

        } catch (Exception $e) {
            error_log("Error in getWarehouseStats: " . $e->getMessage());
            return [
                'total_warehouses' => 0,
                'active_warehouses' => 0,
                'inactive_warehouses' => 0,
                'warehouse_stock' => []
            ];
        }
    }
}
?>