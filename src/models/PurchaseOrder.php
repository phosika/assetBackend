<?php
// /home/phosika/Fixasset/assetapplication/BACKEND/src/models/PurchaseOrder.php

require_once __DIR__ . '/../config/database.php';

class PurchaseOrder {
    private $db;
    private $table = 'purchase_orders';
    private $detailsTable = 'purchase_order_details';

    public function __construct() {
        $this->db = Database::getInstance();
        if (!$this->db) {
            error_log("Database connection failed in PurchaseOrder model");
            throw new Exception("Database connection failed");
        }
    }

    /**
     * ດຶງຂໍ້ມູນໃບສັ່ງຊື້ທັງໝົດ
     */
    public function getAllPurchaseOrders($filters = []) {
        try {
            $sql = "SELECT po.*,
                           s.supplier_name,
                           s.supplier_code,
                           s.contact_person,
                           s.phone as supplier_phone,
                           CONCAT(u.first_name, ' ', u.last_name) as created_by_name,
                           CONCAT(au.first_name, ' ', au.last_name) as approved_by_name,
                           (SELECT COUNT(*) FROM {$this->detailsTable} WHERE po_id = po.id) as total_items,
                           (SELECT SUM(quantity) FROM {$this->detailsTable} WHERE po_id = po.id) as total_quantity
                    FROM {$this->table} po
                    LEFT JOIN suppliers s ON po.supplier_id = s.id
                    LEFT JOIN users u ON po.created_by = u.id
                    LEFT JOIN users au ON po.approved_by = au.id
                    WHERE 1=1";
            
            $params = [];
            $countSql = "SELECT COUNT(*) as total FROM {$this->table} po WHERE 1=1";
            $countParams = [];

            // ຄົ້ນຫາ
            if (!empty($filters['search'])) {
                $sql .= " AND (po.po_number LIKE ? OR s.supplier_name LIKE ?)";
                $searchTerm = "%{$filters['search']}%";
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                
                $countSql .= " AND (po.po_number LIKE ? OR supplier_id IN (SELECT id FROM suppliers WHERE supplier_name LIKE ?))";
                $countParams[] = $searchTerm;
                $countParams[] = $searchTerm;
            }

            // ກັ່ນຕອງຕາມສະຖານະ
            if (!empty($filters['status'])) {
                $sql .= " AND po.status = ?";
                $params[] = $filters['status'];
                
                $countSql .= " AND status = ?";
                $countParams[] = $filters['status'];
            }

            // ກັ່ນຕອງຕາມສະຖານະການຊຳລະ
            if (!empty($filters['payment_status'])) {
                $sql .= " AND po.payment_status = ?";
                $params[] = $filters['payment_status'];
                
                $countSql .= " AND payment_status = ?";
                $countParams[] = $filters['payment_status'];
            }

            // ກັ່ນຕອງຕາມຜູ້ສະໜອງ
            if (!empty($filters['supplier_id'])) {
                $sql .= " AND po.supplier_id = ?";
                $params[] = $filters['supplier_id'];
                
                $countSql .= " AND supplier_id = ?";
                $countParams[] = $filters['supplier_id'];
            }

            // ກັ່ນຕອງຕາມຊ່ວງວັນທີ
            if (!empty($filters['from_date'])) {
                $sql .= " AND DATE(po.order_date) >= ?";
                $params[] = $filters['from_date'];
                
                $countSql .= " AND DATE(order_date) >= ?";
                $countParams[] = $filters['from_date'];
            }

            if (!empty($filters['to_date'])) {
                $sql .= " AND DATE(po.order_date) <= ?";
                $params[] = $filters['to_date'];
                
                $countSql .= " AND DATE(order_date) <= ?";
                $countParams[] = $filters['to_date'];
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
            $sortBy = $filters['sort_by'] ?? 'po.created_at';
            $sortOrder = $filters['sort_order'] ?? 'DESC';
            
            $allowedSortColumns = ['po.po_number', 'po.order_date', 'po.total_amount', 'po.status', 'po.payment_status', 'po.created_at'];
            if (!in_array($sortBy, $allowedSortColumns)) {
                $sortBy = 'po.created_at';
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

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $purchaseOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'data' => $purchaseOrders,
                'total' => $total,
                'current_page' => $page,
                'per_page' => $limit,
                'last_page' => $total > 0 ? ceil($total / $limit) : 1
            ];

        } catch (Exception $e) {
            error_log("Error in getAllPurchaseOrders: " . $e->getMessage());
            return [
                'data' => [],
                'total' => 0,
                'current_page' => 1,
                'per_page' => 20,
                'last_page' => 1
            ];
        }
    }

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
                $itemsSql = "SELECT pod.*, 
                                    i.item_code, 
                                    i.item_name,
                                    i.barcode
                            FROM {$this->detailsTable} pod
                            LEFT JOIN inventory_items i ON pod.item_id = i.id
                            WHERE pod.po_id = ?
                            ORDER BY pod.id ASC";
                $itemsStmt = $this->db->prepare($itemsSql);
                $itemsStmt->execute([$id]);
                $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
                
                $purchaseOrder['items'] = $items;
                
                $receivedTotal = 0;
                foreach ($items as $item) {
                    $receivedTotal += ($item['received_quantity'] ?? 0) * $item['unit_price'];
                }
                $purchaseOrder['received_amount'] = $receivedTotal;
                
                error_log("Found PO ID {$id} with " . count($items) . " items");
            }

            return $purchaseOrder ?: null;

        } catch (Exception $e) {
            error_log("Error in getPurchaseOrderById: " . $e->getMessage());
            return null;
        }
    }

    /**
     * ດຶງຂໍ້ມູນໃບສັ່ງຊື້ຕາມເລກທີ PO
     */
    public function getPurchaseOrderByNumber($poNumber) {
        try {
            $sql = "SELECT * FROM {$this->table} WHERE po_number = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$poNumber]);
            
            return $stmt->fetch(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            error_log("Error in getPurchaseOrderByNumber: " . $e->getMessage());
            return null;
        }
    }

    /**
     * ສ້າງໃບສັ່ງຊື້ໃໝ່
     */
    public function createPurchaseOrder($data, $createdBy = null) {
        try {
            $checkSql = "SELECT id FROM {$this->table} WHERE po_number = ?";
            $checkStmt = $this->db->prepare($checkSql);
            $checkStmt->execute([$data['po_number']]);
            
            if ($checkStmt->fetch()) {
                return [
                    'success' => false,
                    'message' => 'ເລກທີໃບສັ່ງຊື້ນີ້ມີໃນລະບົບແລ້ວ'
                ];
            }

            $this->db->beginTransaction();

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

            $sql = "INSERT INTO {$this->table} (
                        po_number,
                        supplier_id,
                        order_date,
                        expected_delivery,
                        subtotal,
                        discount,
                        tax,
                        total_amount,
                        currency_code,
                        exchange_rate,
                        payment_status,
                        status,
                        notes,
                        created_by,
                        created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['po_number'],
                $data['supplier_id'],
                $data['order_date'],
                $data['expected_delivery'] ?? null,
                $subtotal,
                $discount,
                $tax,
                $totalAmount,
                $data['currency_code'] ?? 'LAK',
                $data['exchange_rate'] ?? 1,
                $data['payment_status'] ?? 'unpaid',
                $data['status'] ?? 'draft',
                $data['notes'] ?? null,
                $createdBy
            ]);

            $poId = $this->db->lastInsertId();

            if (!empty($data['items']) && is_array($data['items'])) {
                $itemSql = "INSERT INTO {$this->detailsTable} (
                                po_id, item_id, quantity, received_quantity, unit_price, 
                                discount, total_price, warranty_period, notes, created_at
                            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
                $itemStmt = $this->db->prepare($itemSql);

                foreach ($data['items'] as $item) {
                    $itemDiscount = $item['discount'] ?? 0;
                    $itemTotal = $item['quantity'] * $item['unit_price'];
                    if ($itemDiscount > 0) {
                        $itemTotal = $itemTotal - ($itemTotal * $itemDiscount / 100);
                    }
                    
                    $itemStmt->execute([
                        $poId,
                        $item['item_id'],
                        $item['quantity'],
                        0,
                        $item['unit_price'],
                        $itemDiscount,
                        $itemTotal,
                        $item['warranty_period'] ?? null,
                        $item['notes'] ?? null
                    ]);
                }
            }

            $this->db->commit();

            return [
                'success' => true,
                'message' => 'ສ້າງໃບສັ່ງຊື້ສຳເລັດ',
                'id' => $poId,
                'po_number' => $data['po_number']
            ];

        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error in createPurchaseOrder: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            
            return [
                'success' => false,
                'message' => 'ສ້າງໃບສັ່ງຊື້ບໍ່ສຳເລັດ: ' . $e->getMessage()
            ];
        }
    }

    /**
     * ຮັບສິນຄ້າ ແລະ ອັບເດດສະຕ໋ອກໃນ inventory_stocks
     */
    public function receivePurchaseOrder($id, $data, $userId) {
        try {
            error_log("=== Starting receivePurchaseOrder ===");
            error_log("PO ID: $id");
            error_log("User ID: $userId");
            
            if (!$this->db) {
                throw new Exception("Database connection not available");
            }
            
            $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->db->beginTransaction();
            error_log("Transaction started successfully");
            
            // ກວດສອບ PO
            $stmt = $this->db->prepare("SELECT id, status, po_number, supplier_id FROM purchase_orders WHERE id = ?");
            $stmt->execute([$id]);
            $po = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$po) {
                throw new Exception('Purchase order not found');
            }
            
            error_log("PO Status: " . $po['status']);
            
            if ($po['status'] !== 'approved') {
                throw new Exception('Cannot receive items. Order must be approved first. Current status: ' . $po['status']);
            }
            
            if (empty($data['items']) || !is_array($data['items'])) {
                throw new Exception('No items to receive');
            }
            
            // ດຶງ warehouse_id (ຖ້າມີສົ່ງມາ)
            $warehouseId = $data['warehouse_id'] ?? null;
            
            foreach ($data['items'] as $item) {
                if (!isset($item['detail_id']) || !isset($item['received_quantity'])) {
                    throw new Exception("Invalid item data: missing detail_id or received_quantity");
                }
                
                if ($item['received_quantity'] <= 0) {
                    error_log("Skipping item detail {$item['detail_id']} - quantity is zero");
                    continue;
                }
                
                // ດຶງຂໍ້ມູນລາຍການສິນຄ້າ
                $stmt = $this->db->prepare("
                    SELECT pod.*, i.item_code, i.item_name
                    FROM purchase_order_details pod
                    JOIN inventory_items i ON pod.item_id = i.id
                    WHERE pod.id = ? AND pod.po_id = ?
                ");
                $stmt->execute([$item['detail_id'], $id]);
                $detail = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$detail) {
                    throw new Exception("Item detail not found with ID: " . $item['detail_id']);
                }
                
                // ອັບເດດ received_quantity
                $stmt = $this->db->prepare("
                    UPDATE purchase_order_details 
                    SET received_quantity = ?,
                        received_at = NOW()
                    WHERE id = ? AND po_id = ?
                ");
                $stmt->execute([
                    $item['received_quantity'], 
                    $item['detail_id'], 
                    $id
                ]);
                
                // ອັບເດດສະຕ໋ອກ
                $this->updateOrCreateStock(
                    $detail['item_id'],
                    $item['received_quantity'],
                    $detail['unit_price'],
                    $userId,
                    $po['po_number'],
                    $warehouseId
                );
                
                error_log("Updated detail ID: {$item['detail_id']} with quantity: {$item['received_quantity']}");
            }
            
            // ອັບເດດສະຖານະ PO
            $stmt = $this->db->prepare("
                UPDATE purchase_orders 
                SET status = 'received',
                    delivery_date = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$id]);
            
            $this->db->commit();
            error_log("Transaction committed successfully");
            
            return [
                'success' => true,
                'message' => 'ຮັບສິນຄ້າສຳເລັດ'
            ];
            
        } catch (Exception $e) {
            if ($this->db && $this->db->inTransaction()) {
                $this->db->rollBack();
                error_log("Transaction rolled back");
            }
            
            error_log("Error receiving purchase order: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            
            return [
                'success' => false,
                'message' => 'ຮັບສິນຄ້າບໍ່ສຳເລັດ: ' . $e->getMessage()
            ];
        }
    }

    /**
     * ອັບເດດ ຫຼື ສ້າງສະຕ໋ອກສິນຄ້າໃໝ່ໃນ inventory_stocks
     */
 
    private function updateOrCreateStock($itemId, $quantity, $unitPrice, $userId, $referenceNumber, $warehouseId = null) {
        try {
            error_log("=== Updating stock for item_id: {$itemId}, quantity: {$quantity} ===");
            
            // ບັງຄັບໃຫ້ເປັນ integer
            $userId = (int)$userId;
            $itemId = (int)$itemId;
            $quantity = (float)$quantity;
            $unitPrice = (float)$unitPrice;
            
            // ຖ້າບໍ່ມີ warehouse_id, ໃຊ້ຄ່າເລີ່ມຕົ້ນ
            if (!$warehouseId) {
                $stmt = $this->db->prepare("SELECT id FROM warehouses WHERE is_active = 1 LIMIT 1");
                $stmt->execute();
                $defaultWarehouse = $stmt->fetch(PDO::FETCH_ASSOC);
                $warehouseId = $defaultWarehouse ? (int)$defaultWarehouse['id'] : null;
            }
            
            // ຊອກຫາ stock ທີ່ມີຢູ່
            $sql = "SELECT id, quantity, available_quantity 
                    FROM inventory_stocks 
                    WHERE item_id = ? AND status = 'active'";
            $params = [$itemId];
            
            if ($warehouseId) {
                $sql .= " AND warehouse_id = ?";
                $params[] = $warehouseId;
            } else {
                $sql .= " AND warehouse_id IS NULL";
            }
            
            $sql .= " LIMIT 1";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $existingStock = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existingStock) {
                // ອັບເດດສະຕ໋ອກເກົ່າ
                $newQuantity = $existingStock['quantity'] + $quantity;
                $newAvailable = ($existingStock['available_quantity'] ?? $existingStock['quantity']) + $quantity;
                
                $stmt = $this->db->prepare("
                    UPDATE inventory_stocks 
                    SET quantity = ?,
                        available_quantity = ?,
                        updated_by = ?,
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([
                    $newQuantity,
                    $newAvailable,
                    $userId,
                    $existingStock['id']
                ]);
                
                error_log("Updated existing stock: old={$existingStock['quantity']}, new={$newQuantity}");
            } else {
                // ສ້າງສະຕ໋ອກໃໝ່
                $stmt = $this->db->prepare("
                    INSERT INTO inventory_stocks (
                        item_id,
                        warehouse_id,
                        quantity,
                        reserved_quantity,
                        available_quantity,
                        status,
                        created_by,
                        updated_by,
                        created_at,
                        updated_at
                    ) VALUES (?, ?, ?, 0, ?, 'active', ?, ?, NOW(), NOW())
                ");
                
                $stmt->execute([
                    $itemId,
                    $warehouseId,
                    $quantity,
                    $quantity,
                    $userId,
                    $userId
                ]);
                
                $stockId = $this->db->lastInsertId();
                error_log("Created new stock for item {$itemId}, stock_id: {$stockId}, quantity: {$quantity}");
            }
            
            return true;
            
        } catch (Exception $e) {
            error_log("Error updating inventory stock: " . $e->getMessage());
            throw new Exception("Failed to update inventory stock: " . $e->getMessage());
        }
    }

    /**
     * ອັບເດດສະຖານະໃບສັ່ງຊື້
     */
    public function updateStatus($id, $status, $approvedBy = null) {
        try {
            error_log("=== MODEL: updateStatus ===");
            error_log("PO ID: $id");
            error_log("New status: $status");
            
            $checkSql = "SELECT id, status FROM {$this->table} WHERE id = ?";
            $checkStmt = $this->db->prepare($checkSql);
            $checkStmt->execute([$id]);
            $currentPO = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$currentPO) {
                return [
                    'success' => false,
                    'message' => 'ບໍ່ພົບໃບສັ່ງຊື້'
                ];
            }
            
            if ($status === 'approved') {
                $sql = "UPDATE {$this->table} 
                        SET status = ?,
                            approved_by = ?,
                            approved_at = NOW(),
                            updated_at = NOW()
                        WHERE id = ?";
                $params = [$status, $approvedBy, $id];
            } else {
                $sql = "UPDATE {$this->table} 
                        SET status = ?,
                            updated_at = NOW()
                        WHERE id = ?";
                $params = [$status, $id];
            }
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            
            return [
                'success' => true,
                'message' => 'ອັບເດດສະຖານະສຳເລັດ'
            ];

        } catch (Exception $e) {
            error_log("Error in updateStatus: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'ອັບເດດສະຖານະບໍ່ສຳເລັດ: ' . $e->getMessage()
            ];
        }
    }

    /**
     * ອັບເດດສະຖານະການຊຳລະເງິນ
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
     * ດຶງສະຖິຕິການຊື້
     */
    public function getPurchaseStats() {
        try {
            $stats = [];

            $totalSql = "SELECT 
                            COUNT(*) as total_orders, 
                            COALESCE(SUM(total_amount), 0) as total_amount,
                            COALESCE(SUM(subtotal), 0) as total_subtotal,
                            COALESCE(SUM(discount), 0) as total_discount,
                            COALESCE(SUM(tax), 0) as total_tax
                         FROM {$this->table}";
            $totalStmt = $this->db->query($totalSql);
            $stats = $totalStmt->fetch(PDO::FETCH_ASSOC);

            $statusSql = "SELECT status, COUNT(*) as count 
                         FROM {$this->table} 
                         GROUP BY status";
            $statusStmt = $this->db->query($statusSql);
            $statusCounts = $statusStmt->fetchAll(PDO::FETCH_ASSOC);

            $stats['by_status'] = [];
            foreach ($statusCounts as $status) {
                $stats['by_status'][$status['status']] = $status['count'];
            }

            $paymentSql = "SELECT payment_status, COUNT(*) as count 
                          FROM {$this->table} 
                          GROUP BY payment_status";
            $paymentStmt = $this->db->query($paymentSql);
            $paymentCounts = $paymentStmt->fetchAll(PDO::FETCH_ASSOC);

            $stats['by_payment_status'] = [];
            foreach ($paymentCounts as $payment) {
                $stats['by_payment_status'][$payment['payment_status']] = $payment['count'];
            }

            $monthlySql = "SELECT 
                              DATE_FORMAT(order_date, '%Y-%m') as month,
                              COUNT(*) as order_count,
                              COALESCE(SUM(total_amount), 0) as total_amount
                           FROM {$this->table}
                           WHERE order_date >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
                           GROUP BY DATE_FORMAT(order_date, '%Y-%m')
                           ORDER BY month DESC";
            $monthlyStmt = $this->db->query($monthlySql);
            $stats['monthly'] = $monthlyStmt->fetchAll(PDO::FETCH_ASSOC);

            return $stats;

        } catch (Exception $e) {
            error_log("Error in getPurchaseStats: " . $e->getMessage());
            return [
                'total_orders' => 0,
                'total_amount' => 0,
                'total_subtotal' => 0,
                'total_discount' => 0,
                'total_tax' => 0,
                'by_status' => [],
                'by_payment_status' => [],
                'monthly' => []
            ];
        }
    }

    /**
     * ລຶບໃບສັ່ງຊື້
     */
    public function deletePurchaseOrder($id) {
        try {
            $checkSql = "SELECT status FROM {$this->table} WHERE id = ?";
            $checkStmt = $this->db->prepare($checkSql);
            $checkStmt->execute([$id]);
            $po = $checkStmt->fetch(PDO::FETCH_ASSOC);

            if (!$po) {
                return [
                    'success' => false,
                    'message' => 'ບໍ່ພົບໃບສັ່ງຊື້'
                ];
            }

            if ($po['status'] !== 'draft' && $po['status'] !== 'cancelled') {
                return [
                    'success' => false,
                    'message' => 'ບໍ່ສາມາດລຶບໃບສັ່ງຊື້ທີ່ບໍ່ແມ່ນຮ່າງ ຫຼື ຍົກເລີກໄດ້'
                ];
            }

            $this->db->beginTransaction();

            $deleteDetailsSql = "DELETE FROM {$this->detailsTable} WHERE po_id = ?";
            $deleteDetailsStmt = $this->db->prepare($deleteDetailsSql);
            $deleteDetailsStmt->execute([$id]);

            $deleteSql = "DELETE FROM {$this->table} WHERE id = ?";
            $deleteStmt = $this->db->prepare($deleteSql);
            $deleteStmt->execute([$id]);

            $this->db->commit();

            return [
                'success' => true,
                'message' => 'ລຶບໃບສັ່ງຊື້ສຳເລັດ'
            ];

        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error in deletePurchaseOrder: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'ລຶບໃບສັ່ງຊື້ບໍ່ສຳເລັດ: ' . $e->getMessage()
            ];
        }
    }

    /**
     * ອັບເດດໃບສັ່ງຊື້
     */
    public function updatePurchaseOrder($id, $data, $updatedBy = null) {
        try {
            error_log("=== MODEL: updatePurchaseOrder ===");
            error_log("ID: " . $id);
            
            $existingPO = $this->getPurchaseOrderById($id);
            
            if (!$existingPO) {
                return [
                    'success' => false,
                    'message' => 'ບໍ່ພົບໃບສັ່ງຊື້'
                ];
            }

            if ($existingPO['status'] !== 'draft') {
                return [
                    'success' => false,
                    'message' => 'ບໍ່ສາມາດແກ້ໄຂໃບສັ່ງຊື້ທີ່ບໍ່ແມ່ນຮ່າງໄດ້'
                ];
            }

            if (empty($data['items']) || !is_array($data['items'])) {
                return [
                    'success' => false,
                    'message' => 'ຕ້ອງມີລາຍການສິນຄ້າຢ່າງໜ້ອຍ 1 ລາຍການ'
                ];
            }

            $this->db->beginTransaction();

            $subtotal = 0;
            if (!empty($data['items']) && is_array($data['items'])) {
                foreach ($data['items'] as $index => $item) {
                    $itemTotal = $item['quantity'] * $item['unit_price'];
                    $itemDiscount = $item['discount'] ?? 0;
                    if ($itemDiscount > 0) {
                        $itemTotal = $itemTotal - ($itemTotal * $itemDiscount / 100);
                    }
                    $subtotal += $itemTotal;
                }
            }

            $discount = $data['discount'] ?? 0;
            $tax = $data['tax'] ?? 0;
            $totalAmount = $subtotal - $discount + $tax;

            $sql = "UPDATE {$this->table} 
                    SET expected_delivery = ?,
                        subtotal = ?,
                        discount = ?,
                        tax = ?,
                        total_amount = ?,
                        notes = ?
                    WHERE id = ?";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['expected_delivery'] ?? null,
                $subtotal,
                $discount,
                $tax,
                $totalAmount,
                $data['notes'] ?? null,
                $id
            ]);

            $deleteItemsSql = "DELETE FROM {$this->detailsTable} WHERE po_id = ?";
            $deleteItemsStmt = $this->db->prepare($deleteItemsSql);
            $deleteItemsStmt->execute([$id]);

            if (!empty($data['items']) && is_array($data['items'])) {
                $itemSql = "INSERT INTO {$this->detailsTable} (
                                po_id, item_id, quantity, received_quantity, unit_price, 
                                discount, total_price, warranty_period, notes
                            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $itemStmt = $this->db->prepare($itemSql);

                foreach ($data['items'] as $item) {
                    $itemDiscount = $item['discount'] ?? 0;
                    $itemSubtotal = $item['quantity'] * $item['unit_price'];
                    $discountAmount = $itemSubtotal * ($itemDiscount / 100);
                    $itemTotal = $itemSubtotal - $discountAmount;
                    
                    $itemStmt->execute([
                        $id,
                        $item['item_id'],
                        $item['quantity'],
                        0,
                        $item['unit_price'],
                        $itemDiscount,
                        $itemTotal,
                        $item['warranty_period'] ?? null,
                        $item['notes'] ?? null
                    ]);
                }
            }

            $this->db->commit();

            return [
                'success' => true,
                'message' => 'ອັບເດດໃບສັ່ງຊື້ສຳເລັດ'
            ];

        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error in updatePurchaseOrder: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'ອັບເດດໃບສັ່ງຊື້ບໍ່ສຳເລັດ: ' . $e->getMessage()
            ];
        }
    }

    /**
     * ສ້າງເລກທີ່ໃບສັ່ງຊື້ອັດຕະໂນມັດ
     */
    public function generatePONumber() {
        $year = date('Y');
        $prefix = 'PU';
        
        $stmt = $this->db->prepare("SELECT COUNT(*) as count FROM {$this->table} WHERE po_number LIKE ? AND YEAR(created_at) = ?");
        $stmt->execute(["PU-{$year}-%", $year]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $count = ($result ? (int)$result['count'] : 0) + 1;
        
        return $prefix . '-' . $year . '-' . str_pad($count, 3, '0', STR_PAD_LEFT);
    }
}
?>