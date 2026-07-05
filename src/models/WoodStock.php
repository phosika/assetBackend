<?php
// src/models/WoodStock.php
require_once __DIR__ . '/../config/database.php';

class WoodStock
{
    private $db;
    private $table = 'wood_stocks';

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Get paginated list of wood stocks with search and filter criteria
     */
    public function list($page = 1, $limit = 10, $filters = [])
    {
        try {
            $offset = ($page - 1) * $limit;
            $where = [];
            $params = [];

            // Search by Serial Number
            if (!empty($filters['search'])) {
                $where[] = "w.serial_number LIKE :search";
                $params[':search'] = '%' . trim($filters['search']) . '%';
            }

            // Filter by Sub Category ID
            if (!empty($filters['sub_category_id'])) {
                $where[] = "w.sub_category_id = :sub_category_id";
                $params[':sub_category_id'] = (int)$filters['sub_category_id'];
            }

            // Filter by Category ID (via join)
            if (!empty($filters['category_id'])) {
                $where[] = "s.category_id = :category_id";
                $params[':category_id'] = (int)$filters['category_id'];
            }

            // Filter by Status
            if (!empty($filters['status'])) {
                $where[] = "w.status = :status";
                $params[':status'] = trim($filters['status']);
            }

            // Construct WHERE clause
            $whereSql = "";
            if (!empty($where)) {
                $whereSql = " WHERE " . implode(" AND ", $where);
            }

            // Get total count
            $countSql = "SELECT COUNT(*) 
                         FROM " . $this->table . " w
                         LEFT JOIN sub_categories s ON w.sub_category_id = s.id" . $whereSql;
            
            $countStmt = $this->db->prepare($countSql);
            $countStmt->execute($params);
            $total = $countStmt->fetchColumn();

            // Get records with category and subcategory names
            $sql = "SELECT w.*, s.name as sub_category_name, s.category_id, c.name as category_name, u.full_name as creator_name
                    FROM " . $this->table . " w
                    LEFT JOIN sub_categories s ON w.sub_category_id = s.id
                    LEFT JOIN categories c ON s.category_id = c.id
                    LEFT JOIN users u ON w.created_by = u.id"
                    . $whereSql . "
                    ORDER BY w.id DESC
                    LIMIT :limit OFFSET :offset";

            $stmt = $this->db->prepare($sql);
            
            // Bind search/filter params
            foreach ($params as $key => $val) {
                $stmt->bindValue($key, $val, is_int($val) ? PDO::PARAM_INT : PDO::PARAM_STR);
            }
            
            // Bind pagination params
            $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
            
            $stmt->execute();
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Cast float and int types for clean response JSON
            foreach ($items as &$item) {
                $item['id'] = (int)$item['id'];
                $item['sub_category_id'] = (int)$item['sub_category_id'];
                $item['category_id'] = (int)$item['category_id'];
                $item['purchase_id'] = $item['purchase_id'] !== null ? (int)$item['purchase_id'] : null;
                $item['width_inch'] = (float)$item['width_inch'];
                $item['length_ft'] = (float)$item['length_ft'];
                $item['cubic_ft'] = (float)$item['cubic_ft'];
                $item['buy_rate'] = (float)$item['buy_rate'];
                $item['buy_price'] = (float)$item['buy_price'];
                $item['sell_rate'] = (float)$item['sell_rate'];
                $item['sell_price'] = (float)$item['sell_price'];
                $item['qty'] = (int)$item['qty'];
                $item['created_by'] = $item['created_by'] !== null ? (int)$item['created_by'] : null;
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
            error_log("WoodStock::list error: " . $e->getMessage());
            return ['items' => [], 'pagination' => ['total' => 0, 'page' => $page, 'limit' => $limit, 'pages' => 0]];
        }
    }

    /**
     * Find specific wood stock details by ID
     */
    public function findById($id)
    {
        try {
            $sql = "SELECT w.*, s.name as sub_category_name, s.category_id, c.name as category_name, u.full_name as creator_name
                    FROM " . $this->table . " w
                    LEFT JOIN sub_categories s ON w.sub_category_id = s.id
                    LEFT JOIN categories c ON s.category_id = c.id
                    LEFT JOIN users u ON w.created_by = u.id
                    WHERE w.id = :id LIMIT 1";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':id' => (int)$id]);
            $item = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($item) {
                $item['id'] = (int)$item['id'];
                $item['sub_category_id'] = (int)$item['sub_category_id'];
                $item['category_id'] = (int)$item['category_id'];
                $item['purchase_id'] = $item['purchase_id'] !== null ? (int)$item['purchase_id'] : null;
                $item['width_inch'] = (float)$item['width_inch'];
                $item['length_ft'] = (float)$item['length_ft'];
                $item['cubic_ft'] = (float)$item['cubic_ft'];
                $item['buy_rate'] = (float)$item['buy_rate'];
                $item['buy_price'] = (float)$item['buy_price'];
                $item['sell_rate'] = (float)$item['sell_rate'];
                $item['sell_price'] = (float)$item['sell_price'];
                $item['qty'] = (int)$item['qty'];
                $item['created_by'] = $item['created_by'] !== null ? (int)$item['created_by'] : null;
            }

            return $item;
        } catch (PDOException $e) {
            error_log("WoodStock::findById error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if a serial number is already in use
     */
    public function exists($serialNumber, $excludeId = null)
    {
        try {
            $sql = "SELECT id FROM " . $this->table . " WHERE serial_number = :serial_number";
            $params = [':serial_number' => trim($serialNumber)];
            
            if ($excludeId !== null) {
                $sql .= " AND id != :exclude_id";
                $params[':exclude_id'] = (int)$excludeId;
            }
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log("WoodStock::exists error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Helper to perform auto-calculations for Cubic Feet and Prices
     */
    private function calculateFields(&$data)
    {
        // 1. Calculate Cubic Ft if width and length are provided and cubic_ft is missing/empty
        $width = isset($data['width_inch']) ? (float)$data['width_inch'] : 0.0;
        $length = isset($data['length_ft']) ? (float)$data['length_ft'] : 0.0;
        
        if (empty($data['cubic_ft']) || (float)$data['cubic_ft'] == 0.0) {
            if ($width > 0 && $length > 0) {
                $data['cubic_ft'] = ($width * $length) / 144.0;
            } else {
                $data['cubic_ft'] = 0.0;
            }
        }
        
        $cubic_ft = (float)$data['cubic_ft'];

        // 2. Auto-calculate Buy Price
        if (isset($data['buy_rate'])) {
            $data['buy_price'] = $cubic_ft * (float)$data['buy_rate'];
        }

        // 3. Auto-calculate Sell Price
        if (isset($data['sell_rate'])) {
            $data['sell_price'] = $cubic_ft * (float)$data['sell_rate'];
        }
    }

    /**
     * Create new wood stock record
     */
    public function create($data)
    {
        try {
            $this->calculateFields($data);

            $sql = "INSERT INTO " . $this->table . " (
                        serial_number, sub_category_id, purchase_id, width_inch, length_ft, 
                        cubic_ft, buy_rate, buy_price, sell_rate, sell_price, 
                        status, notes, qty, image, created_by, created_at, updated_at
                    ) VALUES (
                        :serial_number, :sub_category_id, :purchase_id, :width_inch, :length_ft, 
                        :cubic_ft, :buy_rate, :buy_price, :sell_rate, :sell_price, 
                        :status, :notes, :qty, :image, :created_by, NOW(), NOW()
                    )";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':serial_number' => trim($data['serial_number']),
                ':sub_category_id' => (int)$data['sub_category_id'],
                ':purchase_id' => !empty($data['purchase_id']) ? (int)$data['purchase_id'] : null,
                ':width_inch' => isset($data['width_inch']) ? (float)$data['width_inch'] : 0.0,
                ':length_ft' => isset($data['length_ft']) ? (float)$data['length_ft'] : 0.0,
                ':cubic_ft' => (float)$data['cubic_ft'],
                ':buy_rate' => isset($data['buy_rate']) ? (float)$data['buy_rate'] : 0.0,
                ':buy_price' => isset($data['buy_price']) ? (float)$data['buy_price'] : 0.0,
                ':sell_rate' => isset($data['sell_rate']) ? (float)$data['sell_rate'] : 0.0,
                ':sell_price' => isset($data['sell_price']) ? (float)$data['sell_price'] : 0.0,
                ':status' => !empty($data['status']) ? trim($data['status']) : 'available',
                ':notes' => $data['notes'] ?? '',
                ':qty' => isset($data['qty']) ? (int)$data['qty'] : 1,
                ':image' => $data['image'] ?? null,
                ':created_by' => $data['created_by'] ?? null
            ]);

            return $this->db->lastInsertId();
        } catch (PDOException $e) {
            error_log("WoodStock::create error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update existing wood stock record
     */
    public function update($id, $data)
    {
        try {
            // First fetch the current record to fill in missing fields for recalculation
            $current = $this->findById($id);
            if (!$current) return false;

            // Merge current data with modifications for recalculation
            $calcData = array_merge($current, $data);
            $this->calculateFields($calcData);

            // Apply calculated fields back to our update payload
            if (isset($data['width_inch']) || isset($data['length_ft']) || isset($data['cubic_ft'])) {
                $data['cubic_ft'] = $calcData['cubic_ft'];
            }
            if (isset($data['buy_rate']) || isset($data['cubic_ft']) || isset($data['width_inch']) || isset($data['length_ft'])) {
                $data['buy_price'] = $calcData['buy_price'];
            }
            if (isset($data['sell_rate']) || isset($data['cubic_ft']) || isset($data['width_inch']) || isset($data['length_ft'])) {
                $data['sell_price'] = $calcData['sell_price'];
            }

            $fields = "";
            $params = [':id' => (int)$id];

            $updatableFields = [
                'serial_number', 'sub_category_id', 'purchase_id', 'width_inch', 'length_ft',
                'cubic_ft', 'buy_rate', 'buy_price', 'sell_rate', 'sell_price',
                'status', 'notes', 'qty', 'image'
            ];

            foreach ($updatableFields as $field) {
                if (isset($data[$field])) {
                    $fields .= "$field = :$field, ";
                    if ($field === 'serial_number') {
                        $params[':serial_number'] = trim($data[$field]);
                    } elseif ($field === 'sub_category_id' || $field === 'purchase_id' || $field === 'qty') {
                        $params[":$field"] = $data[$field] !== null ? (int)$data[$field] : null;
                    } elseif ($field === 'width_inch' || $field === 'length_ft' || $field === 'cubic_ft' || $field === 'buy_rate' || $field === 'buy_price' || $field === 'sell_rate' || $field === 'sell_price') {
                        $params[":$field"] = (float)$data[$field];
                    } else {
                        $params[":$field"] = $data[$field];
                    }
                }
            }

            if (empty($fields)) {
                return false;
            }

            $fields = rtrim($fields, ", ");
            $sql = "UPDATE " . $this->table . " SET " . $fields . ", updated_at = NOW() WHERE id = :id";

            $stmt = $this->db->prepare($sql);
            return $stmt->execute($params);
        } catch (PDOException $e) {
            error_log("WoodStock::update error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete wood stock record
     */
    public function delete($id)
    {
        try {
            $stmt = $this->db->prepare("DELETE FROM " . $this->table . " WHERE id = :id");
            return $stmt->execute([':id' => (int)$id]);
        } catch (PDOException $e) {
            error_log("WoodStock::delete error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Quick status update helper
     */
    public function updateStatus($id, $status)
    {
        try {
            $stmt = $this->db->prepare("UPDATE " . $this->table . " SET status = :status, updated_at = NOW() WHERE id = :id");
            return $stmt->execute([
                ':id' => (int)$id,
                ':status' => trim($status)
            ]);
        } catch (PDOException $e) {
            error_log("WoodStock::updateStatus error: " . $e->getMessage());
            return false;
        }
    }
}
?>
