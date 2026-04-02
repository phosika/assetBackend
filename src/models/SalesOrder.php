<?php
// /home/phosika/Fixasset/assetapplication/BACKEND/src/models/SalesOrder.php

require_once __DIR__ . '/../config/database.php';

class SalesOrder {
    private $db;
    private $table = 'sales_orders';
    private $detailsTable = 'sales_order_details';

    public function __construct() {
        $this->db = Database::getInstance();
        if (!$this->db) {
            error_log("Database connection failed in SalesOrder model");
            throw new Exception("Database connection failed");
        }
    }

 



public function getAllSalesOrders($filters = [], $companyId = null, $branchId = null) {
    try {
        // ກຳນົດ SQL ເລີ່ມຕົ້ນ (ສຳຄັນທີ່ສຸດ!)
        $sql = "SELECT so.*,
                       c.customer_name,
                       c.customer_code,
                       b.branch_name,
                       b.branch_code,
                       comp.company_name,
                       CONCAT(u.first_name, ' ', u.last_name) as created_by_name
                FROM {$this->table} so
                LEFT JOIN customers c ON so.customer_id = c.id
                LEFT JOIN branches b ON so.branch_id = b.id
                LEFT JOIN companies comp ON so.company_id = comp.id
                LEFT JOIN users u ON so.created_by = u.id
                WHERE 1=1";
        
        $params = [];
        
        // ກັ່ນຕອງຕາມບໍລິສັດ
        if ($companyId) {
            $sql .= " AND so.company_id = ?";
            $params[] = $companyId;
        }

        // ກັ່ນຕອງຕາມສາຂາ
        if ($branchId) {
            $sql .= " AND so.branch_id = ?";
            $params[] = $branchId;
        }

        // ຄົ້ນຫາ
        if (!empty($filters['search'])) {
            $sql .= " AND (so.so_number LIKE ? OR c.customer_name LIKE ?)";
            $searchTerm = "%{$filters['search']}%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            error_log("Search term: " . $filters['search']);
        }

        // ກັ່ນຕອງຕາມສະຖານະ
        if (!empty($filters['status'])) {
            $sql .= " AND so.status = ?";
            $params[] = $filters['status'];
        }

        // ກັ່ນຕອງຕາມສະຖານະການຊຳລະ
        if (!empty($filters['payment_status'])) {
            $sql .= " AND so.payment_status = ?";
            $params[] = $filters['payment_status'];
        }

        // ກັ່ນຕອງຕາມຊ່ວງວັນທີ
        if (!empty($filters['from_date'])) {
            $sql .= " AND DATE(so.sale_date) >= ?";
            $params[] = $filters['from_date'];
        }

        if (!empty($filters['to_date'])) {
            $sql .= " AND DATE(so.sale_date) <= ?";
            $params[] = $filters['to_date'];
        }

        // ນັບຈຳນວນທັງໝົດ (ຕ້ອງມາກ່ອນ LIMIT)
        $countSql = "SELECT COUNT(*) as total FROM ({$sql}) as temp";
        $countStmt = $this->db->prepare($countSql);
        
        // ສຳເນົາ params ສຳລັບ count
        $countParams = $params;
        $countStmt->execute($countParams);
        $totalResult = $countStmt->fetch(PDO::FETCH_ASSOC);
        $total = $totalResult ? (int)$totalResult['total'] : 0;

        // ຈັດລຽງ
        $sortBy = $filters['sort_by'] ?? 'so.created_at';
        $sortOrder = $filters['sort_order'] ?? 'DESC';
        $allowedSortColumns = ['so.so_number', 'so.sale_date', 'so.total_amount', 'so.status', 'so.payment_status', 'so.created_at'];
        if (!in_array($sortBy, $allowedSortColumns)) {
            $sortBy = 'so.created_at';
        }
        $sortOrder = strtoupper($sortOrder) === 'ASC' ? 'ASC' : 'DESC';
        $sql .= " ORDER BY {$sortBy} {$sortOrder}";

        // ແບ່ງໜ້າ
        $page = isset($filters['page']) ? max(1, (int)$filters['page']) : 1;
        $limit = isset($filters['limit']) ? (int)$filters['limit'] : 20;
        $offset = ($page - 1) * $limit;
        
        $sql .= " LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;

        error_log("Final SQL: " . $sql);
        error_log("Params: " . json_encode($params));

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $salesOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        error_log("Found " . count($salesOrders) . " sales orders");

        // ເພີ່ມ items ໃຫ້ແຕ່ລະໃບຂາຍ
        foreach ($salesOrders as &$order) {
            $itemsSql = "SELECT sod.*, i.item_code, i.item_name
                        FROM {$this->detailsTable} sod
                        LEFT JOIN inventory_items i ON sod.item_id = i.id
                        WHERE sod.so_id = ?";
            $itemsStmt = $this->db->prepare($itemsSql);
            $itemsStmt->execute([$order['id']]);
            $order['items'] = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
            $order['total_items'] = count($order['items']);
        }

        return [
            'data' => $salesOrders,
            'total' => $total,
            'current_page' => $page,
            'per_page' => $limit,
            'last_page' => $total > 0 ? ceil($total / $limit) : 1
        ];

    } catch (Exception $e) {
        error_log("Error in getAllSalesOrders: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
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
     * ດຶງຂໍ້ມູນໃບຂາຍຕາມ ID
     */
    public function getSalesOrderById($id) {
        try {
            error_log("Getting sales order by ID: " . $id);
            
       $sql = "SELECT so.*,
                       c.customer_name,
                       c.customer_code,
                       c.contact_person,
                       c.phone as customer_phone,
                       c.email as customer_email,
                       c.address as customer_address,
                       b.branch_name,
                       b.branch_code,
                       comp.company_name,
                       CONCAT(u.first_name, ' ', u.last_name) as created_by_name,
                       CONCAT(au.first_name, ' ', au.last_name) as approved_by_name
                FROM {$this->table} so
                LEFT JOIN customers c ON so.customer_id = c.id
                LEFT JOIN branches b ON so.branch_id = b.id
                LEFT JOIN companies comp ON so.company_id = comp.id
                LEFT JOIN users u ON so.created_by = u.id
                LEFT JOIN users au ON so.approved_by = au.id
                WHERE so.id = ?";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            error_log("Sales order found: " . ($result ? 'yes' : 'no'));
            
            if ($result) {
                // ດຶງລາຍການສິນຄ້າ
                $itemsSql = "SELECT sod.*, 
                                    i.item_code, 
                                    i.item_name
                            FROM {$this->detailsTable} sod
                            LEFT JOIN inventory_items i ON sod.item_id = i.id
                            WHERE sod.so_id = ?";
                $itemsStmt = $this->db->prepare($itemsSql);
                $itemsStmt->execute([$id]);
                $result['items'] = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
            }
            
            return $result ?: null;

        } catch (Exception $e) {
            error_log("Error in getSalesOrderById: " . $e->getMessage());
            return null;
        }
    }

    /**
     * ດຶງສະຖິຕິການຂາຍ
     */
    public function getSalesStats($companyId = null, $branchId = null) {
        try {
            $stats = [];

            $condition = "";
            $params = [];

            if ($branchId) {
                $condition .= " AND branch_id = ?";
                $params[] = $branchId;
            } elseif ($companyId) {
                $condition .= " AND company_id = ?";
                $params[] = $companyId;
            }

            // ຍອດຂາຍມື້ນີ້
            $todaySql = "SELECT COALESCE(SUM(total_amount), 0) as total 
                        FROM {$this->table} 
                        WHERE DATE(sale_date) = CURDATE()
                        AND status != 'cancelled'
                        $condition";
            $todayStmt = $this->db->prepare($todaySql);
            $todayStmt->execute($params);
            $stats['today_sales'] = $todayStmt->fetchColumn();

            // ຍອດຂາຍປະຈຳເດືອນ
            $monthSql = "SELECT COALESCE(SUM(total_amount), 0) as total 
                        FROM {$this->table} 
                        WHERE MONTH(sale_date) = MONTH(CURDATE())
                        AND YEAR(sale_date) = YEAR(CURDATE())
                        AND status != 'cancelled'
                        $condition";
            $monthStmt = $this->db->prepare($monthSql);
            $monthStmt->execute($params);
            $stats['month_sales'] = $monthStmt->fetchColumn();

            // ຍອດຄ້າງຊຳລະ
            $pendingSql = "SELECT COALESCE(SUM(total_amount), 0) as total 
                          FROM {$this->table} 
                          WHERE payment_status IN ('unpaid', 'partial')
                          AND status != 'cancelled'
                          $condition";
            $pendingStmt = $this->db->prepare($pendingSql);
            $pendingStmt->execute($params);
            $stats['pending_payment'] = $pendingStmt->fetchColumn();

            return $stats;

        } catch (Exception $e) {
            error_log("Error in getSalesStats: " . $e->getMessage());
            return [
                'today_sales' => 0,
                'month_sales' => 0,
                'pending_payment' => 0
            ];
        }
    }

    /**
     * ສ້າງໃບຂາຍໃໝ່
     */
    public function createSalesOrder($data, $createdBy = null, $companyId = null, $branchId = null) {
        try {
            // ກວດສອບວ່າເລກທີ SO ຊໍ້າກັນບໍ
            $checkSql = "SELECT id FROM {$this->table} WHERE so_number = ?";
            $checkStmt = $this->db->prepare($checkSql);
            $checkStmt->execute([$data['so_number']]);
            
            if ($checkStmt->fetch()) {
                return [
                    'success' => false,
                    'message' => 'ເລກທີໃບຂາຍນີ້ມີໃນລະບົບແລ້ວ'
                ];
            }

            // ເລີ່ມ transaction ທີ່ນີ້
            $this->db->beginTransaction();

            // ຄຳນວນຍອດລວມ
            $subtotal = 0;
            if (!empty($data['items']) && is_array($data['items'])) {
                foreach ($data['items'] as $item) {
                    $itemTotal = $item['quantity'] * $item['unit_price'];
                    if (!empty($item['discount'])) {
                        $itemTotal = $itemTotal - ($itemTotal * $item['discount'] / 100);
                    }
                    $subtotal += $itemTotal;
                }
            }

            $discount = $data['discount'] ?? 0;
            $tax = $data['tax'] ?? 0;
            $totalAmount = $subtotal - $discount + $tax;

            // ສ້າງໃບຂາຍ
            $sql = "INSERT INTO {$this->table} (
                        so_number, customer_id, sale_date, company_id, branch_id,
                        subtotal, discount, tax, total_amount,
                        payment_status, status, payment_method, notes, created_by, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['so_number'],
                $data['customer_id'],
                $data['sale_date'],
                $companyId,
                $branchId,
                $subtotal,
                $discount,
                $tax,
                $totalAmount,
                $data['payment_status'] ?? 'unpaid',
                $data['status'] ?? 'pending',
                $data['payment_method'] ?? null,
                $data['notes'] ?? null,
                $createdBy
            ]);

            $soId = $this->db->lastInsertId();

            // ເພີ່ມລາຍການສິນຄ້າ
            if (!empty($data['items']) && is_array($data['items'])) {
                $itemSql = "INSERT INTO {$this->detailsTable} (
                                so_id, item_id, quantity, unit_price, 
                                discount, total_price, notes
                            ) VALUES (?, ?, ?, ?, ?, ?, ?)";
                $itemStmt = $this->db->prepare($itemSql);

                foreach ($data['items'] as $item) {
                    $itemDiscount = $item['discount'] ?? 0;
                    $itemTotal = $item['quantity'] * $item['unit_price'];
                    if ($itemDiscount > 0) {
                        $itemTotal = $itemTotal - ($itemTotal * $itemDiscount / 100);
                    }
                    
                    $itemStmt->execute([
                        $soId,
                        $item['item_id'],
                        $item['quantity'],
                        $item['unit_price'],
                        $itemDiscount,
                        $itemTotal,
                        $item['notes'] ?? null
                    ]);
                }
                
                // ຕັດສະຕ໋ອກ - ຖ້າມີ error ຈະ throw exception ແລະ rollback
                $this->deductStockForItems($data['items'], $soId, $createdBy);
            }

            $this->db->commit();

            return [
                'success' => true,
                'message' => 'ສ້າງໃບຂາຍສຳເລັດ',
                'id' => $soId,
                'so_number' => $data['so_number']
            ];

        } catch (Exception $e) {
            // ຖ້າມີ error, rollback transaction
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log("Error in createSalesOrder: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'ສ້າງໃບຂາຍບໍ່ສຳເລັດ: ' . $e->getMessage()
            ];
        }
    }

    /**
     * ອັບເດດສະຖານະການຊຳລະ
     */
    public function updatePaymentStatus($id, $paymentStatus, $paymentMethod = null, $paymentDate = null) {
        try {
            $sql = "UPDATE {$this->table} 
                    SET payment_status = ?,
                        payment_method = ?,
                        payment_date = ?,
                        updated_at = NOW()
                    WHERE id = ?";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$paymentStatus, $paymentMethod, $paymentDate, $id]);

            return [
                'success' => true,
                'message' => 'ອັບເດດສະຖານະການຊຳລະສຳເລັດ'
            ];

        } catch (Exception $e) {
            error_log("Error in updatePaymentStatus: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'ອັບເດດສະຖານະການຊຳລະບໍ່ສຳເລັດ: ' . $e->getMessage()
            ];
        }
    }
 
 
    /**
     * ອັບເດດສະຖານະໃບຂາຍ
     */
    public function updateStatus($id, $status, $approvedBy = null) {
        try {
            $this->db->beginTransaction();
            
            // ດຶງຂໍ້ມູນໃບຂາຍເດີມກ່ອນ
            $oldStatus = $this->getSalesOrderById($id);
            if (!$oldStatus) {
                return [
                    'success' => false,
                    'message' => 'ບໍ່ພົບໃບຂາຍ'
                ];
            }
            
            // ຖ້າຍົກເລີກ ແລະ ກ່ອນໜ້ານີ້ບໍ່ແມ່ນຍົກເລີກ, ຄືນສະຕ໋ອກ
            if ($status === 'cancelled' && $oldStatus['status'] !== 'cancelled') {
                if (!empty($oldStatus['items'])) {
                    $this->restoreStockForItems($oldStatus['items'], $id, $approvedBy);
                }
            }
            
            // ອັບເດດສະຖານະ
            $sql = "UPDATE {$this->table} 
                    SET status = ?,
                        approved_by = CASE WHEN ? IN ('confirmed', 'delivered', 'cancelled') THEN ? ELSE approved_by END,
                        approved_at = CASE WHEN ? IN ('confirmed', 'delivered', 'cancelled') THEN NOW() ELSE approved_at END,
                        updated_at = NOW()
                    WHERE id = ?";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$status, $status, $approvedBy, $status, $id]);

            $this->db->commit();

            return [
                'success' => true,
                'message' => 'ອັບເດດສະຖານະສຳເລັດ'
            ];

        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error in updateStatus: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'ອັບເດດສະຖານະບໍ່ສຳເລັດ: ' . $e->getMessage()
            ];
        }
    }

    private function updateStock($itemId, $quantity, $operation = 'subtract') {
        // TODO: Implement stock update logic
        // ສາມາດເຊື່ອມຕໍ່ກັບ InventoryStock model ເພື່ອຕັດສະຕ໋ອກ
        return true;
    }

    /**
     * ຕັດສະຕ໋ອກເມື່ອສ້າງໃບຂາຍ
     */
    private function deductStockForItems($items, $soId, $createdBy = null) {
        require_once __DIR__ . '/InventoryStock.php';
        $stockModel = new InventoryStock();
        
        foreach ($items as $item) {
            $result = $stockModel->deductStock(
                $item['item_id'],
                $item['quantity'],
                $soId,
                'sales',
                "ຕັດສະຕ໋ອກຈາກໃບຂາຍ #$soId",
                $createdBy
            );
            
            error_log("Deduct stock for item {$item['item_id']}: " . json_encode($result));
            
            if (!$result['success']) {
                throw new Exception($result['message']);
            }
        }
        
        return true;
    }
    
    /**
     * ຄືນສະຕ໋ອກເມື່ອຍົກເລີກໃບຂາຍ
     */
    private function restoreStockForItems($items, $soId, $createdBy = null) {
        require_once __DIR__ . '/InventoryStock.php';
        $stockModel = new InventoryStock();
        
        foreach ($items as $item) {
            $result = $stockModel->restoreStock(
                $item['item_id'],
                $item['quantity'],
                $soId,
                'sales_cancelled',
                "ຄືນສະຕ໋ອກຈາກການຍົກເລີກໃບຂາຍ #$soId",
                $createdBy
            );
            
            if (!$result['success']) {
                throw new Exception($result['message']);
            }
        }
        
        return true;
    }

 
    /**
     * ອັບເດດສະຖານະການ sync asset
     */
    public function updateSyncAssetStatus($id, $syncedToAsset = 1, $syncedAt = null) {
        try {
            // ກວດສອບຮູບແບບວັນທີ
            if ($syncedAt) {
                // ກວດສອບວ່າເປັນຮູບແບບ YYYY-MM-DD HH:MM:SS ບໍ
                $dateTime = DateTime::createFromFormat('Y-m-d H:i:s', $syncedAt);
                if (!$dateTime) {
                    // ຖ້າບໍ່ຖືກ, ໃຊ້ວັນທີປັດຈຸບັນ
                    $syncedAt = date('Y-m-d H:i:s');
                    error_log("Invalid synced_at format, using current time: $syncedAt");
                }
            } else {
                $syncedAt = date('Y-m-d H:i:s');
            }
            
            $sql = "UPDATE {$this->table} 
                    SET synced_to_asset = ?,
                        synced_at = ?,
                        updated_at = NOW()
                    WHERE id = ?";
            
            error_log("SQL: $sql, Params: [$syncedToAsset, $syncedAt, $id]");
            
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute([$syncedToAsset, $syncedAt, $id]);
            
            if ($result && $stmt->rowCount() > 0) {
                error_log("Sync status updated successfully for sales order $id");
                return [
                    'success' => true,
                    'message' => 'Sync status updated successfully'
                ];
            } else {
                error_log("No rows affected for sales order $id");
                return [
                    'success' => false,
                    'message' => 'No rows affected. Sales order may not exist or already synced.'
                ];
            }
            
        } catch (Exception $e) {
            error_log("Error in updateSyncAssetStatus: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Update failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * ສ້າງເລກທີ່ໃບຂາຍອັດຕະໂນມັດ
     */
    public function generateSONumber() {
        $year = date('Y');
        $prefix = 'SA';
        
        $stmt = $this->db->prepare("SELECT COUNT(*) as count FROM {$this->table} WHERE so_number LIKE ? AND YEAR(created_at) = ?");
        $stmt->execute(["SA-{$year}-%", $year]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $count = ($result ? (int)$result['count'] : 0) + 1;
        
        return $prefix . '-' . $year . '-' . str_pad($count, 3, '0', STR_PAD_LEFT);
    }

}
    ?>