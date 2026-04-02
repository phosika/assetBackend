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
                // ໃຊ້ po_id ແທນ purchase_order_id
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
                
                // ຄຳນວນຍອດລວມທີ່ຮັບແລ້ວ
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
            // ກວດສອບວ່າເລກທີ PO ຊໍ້າກັນບໍ
            $checkSql = "SELECT id FROM {$this->table} WHERE po_number = ?";
            $checkStmt = $this->db->prepare($checkSql);
            $checkStmt->execute([$data['po_number']]);
            
            if ($checkStmt->fetch()) {
                return [
                    'success' => false,
                    'message' => 'ເລກທີໃບສັ່ງຊື້ນີ້ມີໃນລະບົບແລ້ວ'
                ];
            }

            // ເລີ່ມ transaction
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

            // ສ້າງໃບສັ່ງຊື້
            $sql = "INSERT INTO {$this->table} (
                        po_number,
                        supplier_id,
                        order_date,
                        expected_delivery,
                        subtotal,
                        discount,
                        tax,
                        total_amount,
                        payment_status,
                        status,
                        notes,
                        created_by,
                        created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            
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
                $data['payment_status'] ?? 'unpaid',
                $data['status'] ?? 'draft',
                $data['notes'] ?? null,
                $createdBy
            ]);

            $poId = $this->db->lastInsertId();

            // ເພີ່ມລາຍການສິນຄ້າໃສ່ purchase_order_details
            if (!empty($data['items']) && is_array($data['items'])) {
                $itemSql = "INSERT INTO {$this->detailsTable} (
                                po_id, item_id, quantity, received_quantity, unit_price, 
                                discount, total_price, warranty_period, notes
                            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
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
                        0, // received_quantity ເລີ່ມຕົ້ນເປັນ 0
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
            return [
                'success' => false,
                'message' => 'ສ້າງໃບສັ່ງຊື້ບໍ່ສຳເລັດ: ' . $e->getMessage()
            ];
        }
    }



    public function receivePurchaseOrder($id, $data, $userId) {
        try {
            error_log("=== Starting receivePurchaseOrder ===");
            error_log("PO ID: $id");
            error_log("User ID: $userId");
            
            // ກວດສອບການເຊື່ອມຕໍ່ຖານຂໍ້ມູນ
            if (!$this->db) {
                throw new Exception("Database connection not available");
            }
            
            // ກວດສອບ PDO attributes
            $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // ກວດສອບວ່າ PDO ຮອງຮັບ transaction
            if (!$this->db->beginTransaction()) {
                throw new Exception("Cannot begin transaction - " . $this->db->errorInfo()[2]);
            }
            
            error_log("Transaction started successfully");
            
            // ກວດສອບວ່າ PO ມີຢູ່ ແລະ ສະຖານະເປັນ 'approved'
            $stmt = $this->db->prepare("SELECT id, status FROM purchase_orders WHERE id = ?");
            $stmt->execute([$id]);
            $po = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$po) {
                throw new Exception('Purchase order not found');
            }
            
            error_log("PO Status: " . $po['status']);
            
            if ($po['status'] !== 'approved') {
                throw new Exception('Cannot receive items. Order must be approved first. Current status: ' . $po['status']);
            }
            
            // ອັບເດດລາຍການສິນຄ້າ
            if (empty($data['items']) || !is_array($data['items'])) {
                throw new Exception('No items to receive');
            }
            
            foreach ($data['items'] as $item) {
                if (!isset($item['detail_id']) || !isset($item['received_quantity'])) {
                    throw new Exception("Invalid item data: missing detail_id or received_quantity");
                }
                
                $stmt = $this->db->prepare("
                    UPDATE purchase_order_details 
                    SET received_quantity = ?
                    WHERE id = ? AND po_id = ?
                ");
                $result = $stmt->execute([
                    $item['received_quantity'], 
                    $item['detail_id'], 
                    $id
                ]);
                
                if (!$result) {
                    throw new Exception("Failed to update item detail ID: " . $item['detail_id']);
                }
                error_log("Updated detail ID: {$item['detail_id']} with quantity: {$item['received_quantity']}");
            }
            
            // ອັບເດດສະຖານະ PO ເປັນ 'received'
            $stmt = $this->db->prepare("
                UPDATE purchase_orders 
                SET status = 'received',
                    delivery_date = NOW()
                WHERE id = ?
            ");
            $result = $stmt->execute([$id]);
            
            if (!$result) {
                throw new Exception("Failed to update purchase order status");
            }
            
            error_log("PO status updated to received");
            
            // Commit transaction
            if (!$this->db->commit()) {
                throw new Exception("Failed to commit transaction");
            }
            
            error_log("Transaction committed successfully");
            
            return [
                'success' => true,
                'message' => 'ຮັບສິນຄ້າສຳເລັດ'
            ];
            
        } catch (Exception $e) {
            // Rollback transaction ຖ້າມີ
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
     * ອັບເດດສະຖານະໃບສັ່ງຊື້
     */
    public function updateStatus($id, $status, $approvedBy = null) {
        try {
            error_log("=== MODEL: updateStatus ===");
            error_log("PO ID: $id");
            error_log("New status: $status");
            error_log("Approved by: " . ($approvedBy ?? 'null'));
            
            // ກວດສອບວ່າ PO ມີຢູ່ບໍ
            $checkSql = "SELECT id, status FROM {$this->table} WHERE id = ?";
            $checkStmt = $this->db->prepare($checkSql);
            $checkStmt->execute([$id]);
            $currentPO = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$currentPO) {
                error_log("PO not found with ID: $id");
                return [
                    'success' => false,
                    'message' => 'ບໍ່ພົບໃບສັ່ງຊື້'
                ];
            }
            
            error_log("Current status in DB: " . $currentPO['status']);
            
            // ກຽມ SQL ສຳລັບອັບເດດ
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
            
            error_log("SQL: " . $sql);
            error_log("Params: " . json_encode($params));
            
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute($params);

            if (!$result) {
                $error = $stmt->errorInfo();
                error_log("SQL Error: " . json_encode($error));
                return [
                    'success' => false,
                    'message' => 'ອັບເດດສະຖານະບໍ່ສຳເລັດ: ' . $error[2]
                ];
            }
            
            // ກວດສອບວ່າອັບເດດໄດ້ຈັກແຖວ
            $rowCount = $stmt->rowCount();
            error_log("Rows affected: $rowCount");
            
            if ($rowCount === 0) {
                error_log("No rows updated - status might already be $status");
                return [
                    'success' => true,
                    'message' => 'ສະຖານະເປັນ ' . $status . ' ແລ້ວ'
                ];
            }
            
            // ດຶງຂໍ້ມູນຫຼັງອັບເດດ
            $verifyStmt = $this->db->prepare("SELECT status FROM {$this->table} WHERE id = ?");
            $verifyStmt->execute([$id]);
            $newStatus = $verifyStmt->fetch(PDO::FETCH_ASSOC);
            error_log("New status after update: " . ($newStatus['status'] ?? 'unknown'));
            
            return [
                'success' => true,
                'message' => 'ອັບເດດສະຖານະສຳເລັດ'
            ];

        } catch (Exception $e) {
            error_log("Error in updateStatus: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
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

            // ຍອດຊື້ທັງໝົດ
            $totalSql = "SELECT 
                            COUNT(*) as total_orders, 
                            COALESCE(SUM(total_amount), 0) as total_amount,
                            COALESCE(SUM(subtotal), 0) as total_subtotal,
                            COALESCE(SUM(discount), 0) as total_discount,
                            COALESCE(SUM(tax), 0) as total_tax
                         FROM {$this->table}";
            $totalStmt = $this->db->query($totalSql);
            $stats = $totalStmt->fetch(PDO::FETCH_ASSOC);

            // ນັບຕາມສະຖານະ
            $statusSql = "SELECT status, COUNT(*) as count 
                         FROM {$this->table} 
                         GROUP BY status";
            $statusStmt = $this->db->query($statusSql);
            $statusCounts = $statusStmt->fetchAll(PDO::FETCH_ASSOC);

            $stats['by_status'] = [];
            foreach ($statusCounts as $status) {
                $stats['by_status'][$status['status']] = $status['count'];
            }

            // ນັບຕາມສະຖານະການຊຳລະ
            $paymentSql = "SELECT payment_status, COUNT(*) as count 
                          FROM {$this->table} 
                          GROUP BY payment_status";
            $paymentStmt = $this->db->query($paymentSql);
            $paymentCounts = $paymentStmt->fetchAll(PDO::FETCH_ASSOC);

            $stats['by_payment_status'] = [];
            foreach ($paymentCounts as $payment) {
                $stats['by_payment_status'][$payment['payment_status']] = $payment['count'];
            }

            // ຍອດຊື້ຕາມເດືອນ
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
            // ກວດສອບວ່າສາມາດລຶບໄດ້ບໍ (ຕ້ອງເປັນ draft ເທົ່ານັ້ນ)
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

            // ເລີ່ມ transaction
            $this->db->beginTransaction();

            // ລຶບລາຍການສິນຄ້າກ່ອນ
            $deleteDetailsSql = "DELETE FROM {$this->detailsTable} WHERE po_id = ?";
            $deleteDetailsStmt = $this->db->prepare($deleteDetailsSql);
            $deleteDetailsStmt->execute([$id]);

            // ລຶບໃບສັ່ງຊື້
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
     * ອັບເດດໃບເກັບເງິນ (invoice)
     */
    public function updateInvoice($id, $invoiceNumber, $invoiceFilePath = null) {
        try {
            $sql = "UPDATE {$this->table} 
                    SET invoice_number = ?,
                        invoice_file_path = COALESCE(?, invoice_file_path)
                    WHERE id = ?";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$invoiceNumber, $invoiceFilePath, $id]);

            return [
                'success' => true,
                'message' => 'ອັບເດດໃບເກັບເງິນສຳເລັດ'
            ];

        } catch (Exception $e) {
            error_log("Error in updateInvoice: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'ອັບເດດໃບເກັບເງິນບໍ່ສຳເລັດ: ' . $e->getMessage()
            ];
        }
    }

        /**
         * ອັບເດດໃບສັ່ງຊື້
         */
    /**
     * ອັບເດດໃບສັ່ງຊື້ (ຮອງຮັບການເພີ່ມ/ລຶບລາຍການ)
     */
    public function updatePurchaseOrder($id, $data, $updatedBy = null) {
        try {
            error_log("=== MODEL: updatePurchaseOrder ===");
            error_log("ID: " . $id);
            error_log("Items count: " . count($data['items']));
            
            // ກວດສອບວ່າມີໃບສັ່ງຊື້ນີ້ບໍ
            $existingPO = $this->getPurchaseOrderById($id);
            
            if (!$existingPO) {
                error_log("Update failed: Purchase order not found with ID: " . $id);
                return [
                    'success' => false,
                    'message' => 'ບໍ່ພົບໃບສັ່ງຊື້'
                ];
            }

            // ກວດສອບວ່າສາມາດແກ້ໄຂໄດ້ບໍ (ຕ້ອງເປັນ draft ເທົ່ານັ້ນ)
            if ($existingPO['status'] !== 'draft') {
                error_log("Cannot update: status is not draft");
                return [
                    'success' => false,
                    'message' => 'ບໍ່ສາມາດແກ້ໄຂໃບສັ່ງຊື້ທີ່ບໍ່ແມ່ນຮ່າງໄດ້'
                ];
            }

            // ກວດສອບຂໍ້ມູນເຂົ້າ
            if (empty($data['items']) || !is_array($data['items'])) {
                return [
                    'success' => false,
                    'message' => 'ຕ້ອງມີລາຍການສິນຄ້າຢ່າງໜ້ອຍ 1 ລາຍການ'
                ];
            }

            // ກວດສອບແຕ່ລະລາຍການສິນຄ້າ
            foreach ($data['items'] as $index => $item) {
                if (empty($item['item_id']) || !is_numeric($item['item_id'])) {
                    return [
                        'success' => false,
                        'message' => "ລາຍການທີ່ " . ($index + 1) . ": ຕ້ອງລະບຸລະຫັດສິນຄ້າ"
                    ];
                }

                if (empty($item['quantity']) || !is_numeric($item['quantity']) || $item['quantity'] <= 0) {
                    return [
                        'success' => false,
                        'message' => "ລາຍການທີ່ " . ($index + 1) . ": ຈຳນວນຕ້ອງເປັນຕົວເລກບວກ"
                    ];
                }

                if (empty($item['unit_price']) || !is_numeric($item['unit_price']) || $item['unit_price'] < 0) {
                    return [
                        'success' => false,
                        'message' => "ລາຍການທີ່ " . ($index + 1) . ": ລາຄາຕໍ່ຫນ່ວຍຕ້ອງເປັນຕົວເລກບໍ່ຕົວລົບ"
                    ];
                }

                if (isset($item['discount']) && (!is_numeric($item['discount']) || $item['discount'] < 0 || $item['discount'] > 100)) {
                    return [
                        'success' => false,
                        'message' => "ລາຍການທີ່ " . ($index + 1) . ": ສ່ວນຫຼຸດຕ້ອງເປັນເປີເຊັນລະຫວ່າງ 0-100"
                    ];
                }

                // ກວດສອບວ່າສິນຄ້າມີໃນຖານຂໍ້ມູນ
                $checkItemSql = "SELECT id FROM inventory_items WHERE id = ?";
                $checkItemStmt = $this->db->prepare($checkItemSql);
                $checkItemStmt->execute([$item['item_id']]);
                if (!$checkItemStmt->fetch()) {
                    return [
                        'success' => false,
                        'message' => "ລາຍການທີ່ " . ($index + 1) . ": ບໍ່ພົບສິນຄ້າລະຫັດ " . $item['item_id']
                    ];
                }
            }

            // ກວດສອບ tax
            if (isset($data['tax']) && (!is_numeric($data['tax']) || $data['tax'] < 0)) {
                return [
                    'success' => false,
                    'message' => 'ອາກອນຕ້ອງເປັນຕົວເລກບໍ່ຕົວລົບ'
                ];
            }

            // ເລີ່ມ transaction
            $this->db->beginTransaction();

            // ຄຳນວນຍອດລວມ (ສອດຄ່ອງກັບການສ້າງ)
            $subtotal = 0;
            if (!empty($data['items']) && is_array($data['items'])) {
                foreach ($data['items'] as $index => $item) {
                    $itemTotal = $item['quantity'] * $item['unit_price'];
                    $itemDiscount = $item['discount'] ?? 0;
                    if ($itemDiscount > 0) {
                        $itemTotal = $itemTotal - ($itemTotal * $itemDiscount / 100);
                    }
                    $subtotal += $itemTotal;
                    
                    error_log("Item {$index}: ID={$item['item_id']}, Qty={$item['quantity']}, Price={$item['unit_price']}, ItemTotal={$itemTotal}");
                }
            }

            $discount = $data['discount'] ?? 0;
            $tax = $data['tax'] ?? 0;
            $totalAmount = $subtotal - $discount + $tax;

            error_log("Calculated - Subtotal: $subtotal, Discount: $discount, Tax: $tax, Total: $totalAmount");

            // ອັບເດດໃບສັ່ງຊື້
            $sql = "UPDATE {$this->table} 
                    SET expected_delivery = ?,
                        subtotal = ?,
                        discount = ?,
                        tax = ?,
                        total_amount = ?,
                        notes = ?
                    WHERE id = ?";
            
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute([
                $data['expected_delivery'] ?? null,
                $subtotal,
                $discount,
                $tax,
                $totalAmount,
                $data['notes'] ?? null,
                $id
            ]);

            if (!$result) {
                throw new Exception("Failed to update purchase order");
            }

            error_log("Purchase order header updated");

            // ສຳຄັນ: ລຶບລາຍການສິນຄ້າເກົ່າທັງໝົດອອກ
            $deleteItemsSql = "DELETE FROM {$this->detailsTable} WHERE po_id = ?";
            $deleteItemsStmt = $this->db->prepare($deleteItemsSql);
            $deleteResult = $deleteItemsStmt->execute([$id]);
            
            if (!$deleteResult) {
                throw new Exception("Failed to delete old items");
            }

            error_log("Old items deleted successfully");

            // ເພີ່ມລາຍການສິນຄ້າໃໝ່ທັງໝົດ
            if (!empty($data['items']) && is_array($data['items'])) {
                $itemSql = "INSERT INTO {$this->detailsTable} (
                                po_id, item_id, quantity, received_quantity, unit_price, 
                                discount, total_price, warranty_period, notes
                            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $itemStmt = $this->db->prepare($itemSql);

                $insertCount = 0;
                foreach ($data['items'] as $item) {
                    $itemDiscount = $item['discount'] ?? 0;
                    $itemSubtotal = $item['quantity'] * $item['unit_price'];
                    $discountAmount = $itemSubtotal * ($itemDiscount / 100);
                    $itemTotal = $itemSubtotal - $discountAmount;
                    
                    $itemResult = $itemStmt->execute([
                        $id,
                        $item['item_id'],
                        $item['quantity'],
                        0, // received_quantity
                        $item['unit_price'],
                        $itemDiscount,
                        $itemTotal,
                        $item['warranty_period'] ?? null,
                        $item['notes'] ?? null
                    ]);
                    
                    if (!$itemResult) {
                        throw new Exception("Failed to insert item: " . json_encode($item));
                    }
                    $insertCount++;
                    error_log("Item inserted: {$item['item_id']}");
                }
                
                error_log("Total items inserted: $insertCount");
            } else {
                error_log("No items to insert");
            }

            // ກວດສອບວ່າຂໍ້ມູນຖືກບັນທຶກຈິງບໍ
            $verifySql = "SELECT COUNT(*) as count FROM {$this->detailsTable} WHERE po_id = ?";
            $verifyStmt = $this->db->prepare($verifySql);
            $verifyStmt->execute([$id]);
            $verifyResult = $verifyStmt->fetch(PDO::FETCH_ASSOC);
            
            error_log("After update - Items count in DB: " . $verifyResult['count']);

            $this->db->commit();
            error_log("Transaction committed successfully");

            return [
                'success' => true,
                'message' => 'ອັບເດດໃບສັ່ງຊື້ສຳເລັດ'
            ];

        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error in updatePurchaseOrder: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
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