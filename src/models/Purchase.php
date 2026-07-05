<?php
// src/models/Purchase.php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/Product.php';
require_once __DIR__ . '/InventoryStock.php';

class Purchase
{
    private $db;
    private $table = 'purchases';

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Get paginated purchases list
     */
    public function list($page = 1, $limit = 10, $filters = [])
    {
        try {
            $offset = ($page - 1) * $limit;
            $where = [];
            $params = [];

            if (!empty($filters['search'])) {
                $where[] = "p.purchase_no LIKE :search";
                $params[':search'] = '%' . trim($filters['search']) . '%';
            }

            if (!empty($filters['status'])) {
                $where[] = "p.status = :status";
                $params[':status'] = trim($filters['status']);
            }

            $whereSql = "";
            if (!empty($where)) {
                $whereSql = " WHERE " . implode(" AND ", $where);
            }

            // Total count
            $countSql = "SELECT COUNT(*) FROM " . $this->table . " p" . $whereSql;
            $countStmt = $this->db->prepare($countSql);
            $countStmt->execute($params);
            $total = $countStmt->fetchColumn();

            // Records with Supplier name
            $sql = "SELECT p.*, s.name as supplier_name, u.full_name as creator_name
                    FROM " . $this->table . " p
                    LEFT JOIN suppliers s ON p.supplier_id = s.id
                    LEFT JOIN users u ON p.created_by = u.id"
                    . $whereSql . "
                    ORDER BY p.id DESC
                    LIMIT :limit OFFSET :offset";

            $stmt = $this->db->prepare($sql);
            foreach ($params as $key => $val) {
                $stmt->bindValue($key, $val, is_int($val) ? PDO::PARAM_INT : PDO::PARAM_STR);
            }
            $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
            $stmt->execute();
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'items' => $items,
                'pagination' => [
                    'total' => (int)$total,
                    'page' => (int)$page,
                    'limit' => (int)$limit,
                    'pages' => ceil($total / $limit)
                ]
            ];
        } catch (PDOException $e) {
            error_log("Purchase::list error: " . $e->getMessage());
            return ['items' => [], 'pagination' => ['total' => 0, 'page' => $page, 'limit' => $limit, 'pages' => 0]];
        }
    }

    /**
     * Find specific purchase invoice details along with its items
     */
    public function findById($id)
    {
        try {
            // Get purchase header
            $sql = "SELECT p.*, s.name as supplier_name, u.full_name as creator_name
                    FROM " . $this->table . " p
                    LEFT JOIN suppliers s ON p.supplier_id = s.id
                    LEFT JOIN users u ON p.created_by = u.id
                    WHERE p.id = :id LIMIT 1";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':id' => (int)$id]);
            $purchase = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$purchase) return false;

            // Get purchase items
            $sqlItems = "SELECT pi.*, pr.name as product_name, c.name as category_name, pr.category_id
                         FROM purchase_items pi 
                         LEFT JOIN products pr ON pi.product_id = pr.id 
                         LEFT JOIN categories c ON pr.category_id = c.id
                         WHERE pi.purchase_id = :purchase_id";
            $stmtItems = $this->db->prepare($sqlItems);
            $stmtItems->execute([':purchase_id' => (int)$id]);
            $purchase['items'] = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

            return $purchase;
        } catch (PDOException $e) {
            error_log("Purchase::findById error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Create a new purchase invoice (Logs purchase and automatically adds items to inventory stocks)
     */
    public function create($data)
    {
        try {
            $this->db->beginTransaction();

            // Auto-generate Purchase No if empty
            if (empty($data['purchase_no'])) {
                $data['purchase_no'] = 'PUR-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));
            }

            // 1. Insert Purchase Header
            $sqlHeader = "INSERT INTO " . $this->table . " (
                              purchase_no, supplier_id, total_pieces, total_cft, 
                              total_amount, paid_amount, due_amount, purchase_date, 
                              status, notes, created_by, created_at, updated_at
                          ) VALUES (
                              :purchase_no, :supplier_id, :total_pieces, :total_cft, 
                              :total_amount, :paid_amount, :due_amount, :purchase_date, 
                              :status, :notes, :created_by, NOW(), NOW()
                          )";

            $stmtHeader = $this->db->prepare($sqlHeader);
            $stmtHeader->execute([
                ':purchase_no' => $data['purchase_no'],
                ':supplier_id' => !empty($data['supplier_id']) ? (int)$data['supplier_id'] : null,
                ':total_pieces' => isset($data['total_pieces']) ? (int)$data['total_pieces'] : 0,
                ':total_cft' => isset($data['total_cft']) ? (float)$data['total_cft'] : 0.0000,
                ':total_amount' => isset($data['total_amount']) ? (float)$data['total_amount'] : 0.00,
                ':paid_amount' => isset($data['paid_amount']) ? (float)$data['paid_amount'] : 0.00,
                ':due_amount' => isset($data['due_amount']) ? (float)$data['due_amount'] : 0.00,
                ':purchase_date' => !empty($data['purchase_date']) ? $data['purchase_date'] : date('Y-m-d'),
                ':status' => $data['status'] ?? 'completed',
                ':notes' => $data['notes'] ?? '',
                ':created_by' => $data['created_by'] ?? null
            ]);

            $purchaseId = $this->db->lastInsertId();

            // 2. Insert Purchase Items & Populate Inventory Stocks
            if (!empty($data['items']) && is_array($data['items'])) {
                $stmtItem = $this->db->prepare("INSERT INTO purchase_items (
                    purchase_id, product_id, serial_number, barcode, qty, 
                    width_inch, length_ft, cubic_ft, rate_cft, line_total, received_qty
                ) VALUES (
                    :purchase_id, :product_id, :serial_number, :barcode, :qty, 
                    :width_inch, :length_ft, :cubic_ft, :rate_cft, :line_total, :received_qty
                )");

                $stockModel = new InventoryStock($this->db);
                $productModel = new Product($this->db);

                foreach ($data['items'] as $index => $item) {
                    $productId = (int)$item['product_id'];
                    $product = $productModel->findById($productId);
                    if (!$product) {
                        throw new Exception("Product ID $productId not found in system.");
                    }

                    // Auto-calculate CFT and line total if not provided
                    $width = isset($item['width_inch']) ? (float)$item['width_inch'] : $product['width_inch'];
                    $length = isset($item['length_ft']) ? (float)$item['length_ft'] : $product['length_ft'];
                    $qty = isset($item['qty']) ? (int)$item['qty'] : 1;
                    $rate = isset($item['rate_cft']) ? (float)$item['rate_cft'] : $product['buy_rate'];

                    $isWood = ($width > 0 && $length > 0);
                    if ($isWood) {
                        if (empty($item['cubic_ft']) || (float)$item['cubic_ft'] == 0.0) {
                            $cubic_ft = ($width * $length) / 144.0;
                        } else {
                            $cubic_ft = (float)$item['cubic_ft'];
                        }
                        $lineTotal = $cubic_ft * $rate * $qty;
                    } else {
                        $cubic_ft = 0.0;
                        $lineTotal = $rate * $qty;
                    }

                    // Generate unique serial number for the piece
                    $serialNumber = $item['serial_number'] ?? 'W-' . date('Ymd') . '-' . $purchaseId . '-' . ($index + 1);
                    $barcode = $item['barcode'] ?? $product['barcode'];

                    $isCompleted = isset($data['status']) && $data['status'] === 'completed';
                    $receivedQty = $isCompleted ? $qty : 0;

                    // Save purchase item detail
                    $stmtItem->execute([
                        ':purchase_id' => $purchaseId,
                        ':product_id' => $productId,
                        ':serial_number' => $serialNumber,
                        ':barcode' => $barcode,
                        ':qty' => $qty,
                        ':width_inch' => $width,
                        ':length_ft' => $length,
                        ':cubic_ft' => $cubic_ft,
                        ':rate_cft' => $rate,
                        ':line_total' => $lineTotal,
                        ':received_qty' => $receivedQty
                    ]);

                    // Add items into inventory stock ONLY IF completed
                    if ($isCompleted) {
                        for ($q = 0; $q < $qty; $q++) {
                            $stockSerial = ($qty > 1) ? $serialNumber . '-' . ($q + 1) : $serialNumber;
                            $stockModel->create([
                                'serial_number' => $stockSerial,
                                'product_id' => $productId,
                                'barcode' => $barcode,
                                'sub_category_id' => $product['sub_category_id'],
                                'purchase_id' => $purchaseId,
                                'width_inch' => $width,
                                'length_ft' => $length,
                                'cubic_ft' => $cubic_ft,
                                'buy_rate' => $rate,
                                'sell_rate' => $product['sell_rate'],
                                'status' => 'available',
                                'qty' => 1,
                                'created_by' => $data['created_by'] ?? null
                            ]);
                        }
                    }
                }
            }

            $this->db->commit();
            return $purchaseId;
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Purchase::create error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update the status of a purchase PO
     */
    public function updateStatus($id, $status)
    {
        try {
            $sql = "UPDATE purchases SET status = :status, updated_at = NOW() WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                ':status' => $status,
                ':id' => $id
            ]);
        } catch (PDOException $e) {
            error_log("Purchase::updateStatus error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Transactional receiving of purchase items (Updates received_qty and creates inventory stocks)
     */
    public function receiveItems($purchaseId, $items, $createdBy = null)
    {
        try {
            $this->db->beginTransaction();

            $stockModel = new InventoryStock($this->db);
            $productModel = new Product($this->db);

            foreach ($items as $item) {
                $itemId = (int)$item['item_id'];
                $qtyToReceive = (int)$item['qty_to_receive'];

                if ($qtyToReceive <= 0) continue;

                // 1. Fetch current purchase item detail
                $stmt = $this->db->prepare("SELECT * FROM purchase_items WHERE id = :id AND purchase_id = :purchase_id LIMIT 1");
                $stmt->execute([':id' => $itemId, ':purchase_id' => $purchaseId]);
                $purchaseItem = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$purchaseItem) {
                    throw new Exception("Purchase item ID $itemId not found.");
                }

                $newReceivedQty = (int)$purchaseItem['received_qty'] + $qtyToReceive;
                if ($newReceivedQty > (int)$purchaseItem['qty']) {
                    throw new Exception("Cannot receive more than ordered quantity for item ID $itemId.");
                }

                // 2. Update received_qty in purchase_items
                $stmtUpdate = $this->db->prepare("UPDATE purchase_items SET received_qty = :received_qty WHERE id = :id");
                $stmtUpdate->execute([':received_qty' => $newReceivedQty, ':id' => $itemId]);

                // 3. Create inventory stock entries
                $productId = (int)$purchaseItem['product_id'];
                $product = $productModel->findById($productId);
                if (!$product) {
                    throw new Exception("Product ID $productId not found.");
                }

                $serialNumber = $purchaseItem['serial_number'];

                for ($q = 0; $q < $qtyToReceive; $q++) {
                    // Generate a suffix to ensure unique serials for each piece received
                    $currentIdx = (int)$purchaseItem['received_qty'] + $q + 1;
                    $stockSerial = $serialNumber . '-R' . $currentIdx;

                    $stockModel->create([
                        'serial_number' => $stockSerial,
                        'product_id' => $productId,
                        'barcode' => $purchaseItem['barcode'],
                        'sub_category_id' => $product['sub_category_id'],
                        'purchase_id' => $purchaseId,
                        'width_inch' => (float)$purchaseItem['width_inch'],
                        'length_ft' => (float)$purchaseItem['length_ft'],
                        'cubic_ft' => (float)$purchaseItem['cubic_ft'],
                        'buy_rate' => (float)$purchaseItem['rate_cft'],
                        'sell_rate' => $product['sell_rate'],
                        'status' => 'available',
                        'qty' => 1,
                        'created_by' => $createdBy
                    ]);
                }
            }

            // 4. Update overall purchase status if all items are fully received
            $stmtCheck = $this->db->prepare("SELECT SUM(qty) as total_ordered, SUM(received_qty) as total_received FROM purchase_items WHERE purchase_id = :purchase_id");
            $stmtCheck->execute([':purchase_id' => $purchaseId]);
            $totals = $stmtCheck->fetch(PDO::FETCH_ASSOC);

            if ($totals && (int)$totals['total_received'] >= (int)$totals['total_ordered']) {
                $stmtStatus = $this->db->prepare("UPDATE purchases SET status = 'completed', updated_at = NOW() WHERE id = :purchase_id");
                $stmtStatus->execute([':purchase_id' => $purchaseId]);
            } else {
                // If it is partially received, update status to pending/partial
                $stmtStatus = $this->db->prepare("UPDATE purchases SET status = 'pending', updated_at = NOW() WHERE id = :purchase_id");
                $stmtStatus->execute([':purchase_id' => $purchaseId]);
            }

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Purchase::receiveItems error: " . $e->getMessage());
            throw $e;
        }
    }
}
?>
