<?php
// src/models/Sale.php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/Product.php';
require_once __DIR__ . '/InventoryStock.php';

class Sale
{
    private $db;
    private $table = 'sales';

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Get paginated sales list
     */
    public function list($page = 1, $limit = 10, $filters = [])
    {
        try {
            $offset = ($page - 1) * $limit;
            $where = [];
            $params = [];

            if (!empty($filters['search'])) {
                $where[] = "s.invoice_no LIKE :search";
                $params[':search'] = '%' . trim($filters['search']) . '%';
            }

            if (!empty($filters['status'])) {
                $where[] = "s.status = :status";
                $params[':status'] = trim($filters['status']);
            }

            if (!empty($filters['created_by'])) {
                $where[] = "s.created_by = :created_by";
                $params[':created_by'] = (int)$filters['created_by'];
            }

            $whereSql = "";
            if (!empty($where)) {
                $whereSql = " WHERE " . implode(" AND ", $where);
            }

            // Total count
            $countSql = "SELECT COUNT(*) FROM " . $this->table . " s" . $whereSql;
            $countStmt = $this->db->prepare($countSql);
            $countStmt->execute($params);
            $total = $countStmt->fetchColumn();

            // Records
            $sql = "SELECT s.*, c.name as customer_name, u.full_name as creator_name
                    FROM " . $this->table . " s
                    LEFT JOIN customers c ON s.customer_id = c.id
                    LEFT JOIN users u ON s.created_by = u.id"
                    . $whereSql . "
                    ORDER BY s.id DESC
                    LIMIT :limit OFFSET :offset";

            $stmt = $this->db->prepare($sql);
            foreach ($params as $key => $val) {
                $stmt->bindValue($key, $val, is_int($val) ? PDO::PARAM_INT : PDO::PARAM_STR);
            }
            $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
            $stmt->execute();
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Fetch items for each sale
            foreach ($items as &$sale) {
                $sqlItems = "SELECT si.*, pr.name as product_name, pr.category_id, pr.buy_rate as product_buy_rate, w.buy_price as stock_buy_price
                             FROM sale_items si 
                             LEFT JOIN products pr ON si.product_id = pr.id 
                             LEFT JOIN inventory_stocks w ON si.inventory_stock_id = w.id
                             WHERE si.sale_id = :sale_id";
                $stmtItems = $this->db->prepare($sqlItems);
                $stmtItems->execute([':sale_id' => (int)$sale['id']]);
                $sale['items'] = $stmtItems->fetchAll(PDO::FETCH_ASSOC);
            }

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
            error_log("Sale::list error: " . $e->getMessage());
            return ['items' => [], 'pagination' => ['total' => 0, 'page' => $page, 'limit' => $limit, 'pages' => 0]];
        }
    }

    /**
     * Find specific sale details and invoice items
     */
    public function findById($id)
    {
        try {
            $sql = "SELECT s.*, c.name as customer_name, c.phone as customer_phone, u.full_name as creator_name
                    FROM " . $this->table . " s
                    LEFT JOIN customers c ON s.customer_id = c.id
                    LEFT JOIN users u ON s.created_by = u.id
                    WHERE s.id = :id LIMIT 1";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':id' => (int)$id]);
            $sale = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$sale) return false;

            // Get sale items
            $sqlItems = "SELECT si.*, pr.name as product_name 
                         FROM sale_items si 
                         LEFT JOIN products pr ON si.product_id = pr.id 
                         WHERE si.sale_id = :sale_id";
            $stmtItems = $this->db->prepare($sqlItems);
            $stmtItems->execute([':sale_id' => (int)$id]);
            $sale['items'] = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

            return $sale;
        } catch (PDOException $e) {
            error_log("Sale::findById error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Create sale transaction and update stock level
     */
    public function create($data)
    {
        try {
            $this->db->beginTransaction();

            // Auto-generate invoice number if empty
            if (empty($data['invoice_no'])) {
                $data['invoice_no'] = 'INV-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));
            }

            // 1. Insert Sales Header
            $sqlHeader = "INSERT INTO " . $this->table . " (
                              invoice_no, customer_id, total_items, total_cft, 
                              subtotal, discount, grand_total, paid_amount, due_amount, 
                              payment_method, sale_date, status, notes, created_by, created_at, updated_at
                          ) VALUES (
                              :invoice_no, :customer_id, :total_items, :total_cft, 
                              :subtotal, :discount, :grand_total, :paid_amount, :due_amount, 
                              :payment_method, :sale_date, :status, :notes, :created_by, NOW(), NOW()
                          )";

            $stmtHeader = $this->db->prepare($sqlHeader);
            $stmtHeader->execute([
                ':invoice_no' => $data['invoice_no'],
                ':customer_id' => !empty($data['customer_id']) ? (int)$data['customer_id'] : null,
                ':total_items' => isset($data['total_items']) ? (int)$data['total_items'] : 0,
                ':total_cft' => isset($data['total_cft']) ? (float)$data['total_cft'] : 0.0000,
                ':subtotal' => isset($data['subtotal']) ? (float)$data['subtotal'] : 0.00,
                ':discount' => isset($data['discount']) ? (float)$data['discount'] : 0.00,
                ':grand_total' => isset($data['grand_total']) ? (float)$data['grand_total'] : 0.00,
                ':paid_amount' => isset($data['paid_amount']) ? (float)$data['paid_amount'] : 0.00,
                ':due_amount' => isset($data['due_amount']) ? (float)$data['due_amount'] : 0.00,
                ':payment_method' => $data['payment_method'] ?? 'cash',
                ':sale_date' => !empty($data['sale_date']) ? $data['sale_date'] : date('Y-m-d'),
                ':status' => $data['status'] ?? 'completed',
                ':notes' => $data['notes'] ?? '',
                ':created_by' => $data['created_by'] ?? null
            ]);

            $saleId = $this->db->lastInsertId();

            // 2. Insert Sale Items and Update Inventory Status
            if (!empty($data['items']) && is_array($data['items'])) {
                $stmtItem = $this->db->prepare("INSERT INTO sale_items (
                    sale_id, inventory_stock_id, product_id, serial, barcode, 
                    qty, width, length, cft, rate_cft, line_total
                ) VALUES (
                    :sale_id, :inventory_stock_id, :product_id, :serial, :barcode, 
                    :qty, :width, :length, :cft, :rate_cft, :line_total
                )");

                $stockModel = new InventoryStock($this->db);
                $productModel = new Product($this->db);

                foreach ($data['items'] as $item) {
                    $stockId = !empty($item['inventory_stock_id']) ? (int)$item['inventory_stock_id'] : null;
                    $barcode = !empty($item['barcode']) ? trim($item['barcode']) : null;
                    $productId = !empty($item['product_id']) ? (int)$item['product_id'] : null;
                    
                    $stockRecord = null;

                    // A. If stock ID is explicitly scanned
                    if ($stockId) {
                        $stockRecord = $stockModel->findById($stockId);
                        if (!$stockRecord || $stockRecord['status'] !== 'available') {
                            throw new Exception("Inventory item (ID: $stockId) is not available for sale.");
                        }
                    } 
                    // B. If barcode is scanned (scan search)
                    elseif ($barcode) {
                        $stockRecord = $stockModel->findByBarcode($barcode);
                        if (!$stockRecord) {
                            throw new Exception("No available stock found for barcode: $barcode");
                        }
                        $stockId = $stockRecord['id'];
                    }

                    if (!$stockRecord) {
                        throw new Exception("No valid stock item could be resolved for this line.");
                    }

                    $productId = (int)$stockRecord['product_id'];
                    $serial = $stockRecord['serial_number'];
                    $barcode = $stockRecord['barcode'];
                    $width = (float)$stockRecord['width_inch'];
                    $length = (float)$stockRecord['length_ft'];
                    $cft = (float)$stockRecord['cubic_ft'];
                    
                    // Use custom rate or default from stock/product
                    $rate = isset($item['rate_cft']) ? (float)$item['rate_cft'] : (float)$stockRecord['sell_rate'];
                    $qty = 1; // individual tracking is 1 qty per line
                    $lineTotal = $cft > 0 ? ($cft * $rate * $qty) : ($rate * $qty);

                    // Log sale item detail
                    $stmtItem->execute([
                        ':sale_id' => $saleId,
                        ':inventory_stock_id' => $stockId,
                        ':product_id' => $productId,
                        ':serial' => $serial,
                        ':barcode' => $barcode,
                        ':qty' => $qty,
                        ':width' => $width,
                        ':length' => $length,
                        ':cft' => $cft,
                        ':rate_cft' => $rate,
                        ':line_total' => $lineTotal
                    ]);

                    // Update stock status to 'sold'
                    $stockModel->updateStatus($stockId, 'sold');
                }
            }

            // 3. Update customer totals if customer_id is provided
            if (!empty($data['customer_id'])) {
                require_once __DIR__ . '/Customer.php';
                $customerModel = new Customer($this->db);
                
                $purchaseAmount = (float)$data['grand_total'];
                $paidAmount = (float)$data['paid_amount'];
                $dueAmount = (float)$data['due_amount'];
                
                $customerModel->updateTotals($data['customer_id'], $purchaseAmount, $paidAmount, $dueAmount);
            }

            $this->db->commit();
            return $saleId;
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Sale::create error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Cancel a sale transaction and revert item stock statuses to available
     */
    public function cancel($id, $cancelledBy)
    {
        try {
            $this->db->beginTransaction();

            // 1. Get sale details to verify and find items
            $sale = $this->findById($id);
            if (!$sale) {
                throw new Exception("Sale record not found.");
            }
            if ($sale['status'] === 'cancelled') {
                throw new Exception("Sale is already cancelled.");
            }

            // 2. Update status of the sale header to 'cancelled'
            $sqlHeader = "UPDATE " . $this->table . " 
                          SET status = 'cancelled', updated_at = NOW(), notes = CONCAT(notes, :notes)
                          WHERE id = :id";
            $stmtHeader = $this->db->prepare($sqlHeader);
            $stmtHeader->execute([
                ':id' => (int)$id,
                ':notes' => "\n[Cancelled by User ID: " . $cancelledBy . " on " . date('Y-m-d H:i:s') . "]"
            ]);

            // 3. Revert stock status for each sale item back to 'available'
            $stockModel = new InventoryStock($this->db);
            foreach ($sale['items'] as $item) {
                if (!empty($item['inventory_stock_id'])) {
                    $stockModel->updateStatus($item['inventory_stock_id'], 'available');
                }
            }

            // 4. Revert customer stats if customer_id was linked
            if (!empty($sale['customer_id'])) {
                require_once __DIR__ . '/Customer.php';
                $customerModel = new Customer($this->db);
                
                $purchaseAmount = -(float)$sale['grand_total'];
                $paidAmount = -(float)$sale['paid_amount'];
                $dueAmount = -(float)$sale['due_amount'];
                
                $customerModel->updateTotals($sale['customer_id'], $purchaseAmount, $paidAmount, $dueAmount);
            }

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Sale::cancel error: " . $e->getMessage());
            throw $e;
        }
    }
}
?>
