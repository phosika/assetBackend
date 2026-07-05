<?php
// src/models/Product.php
require_once __DIR__ . '/../config/database.php';

class Product
{
    private $db;
    private $table = 'products';

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Helper to auto-generate a unique 13-digit EAN-style barcode starting with 885
     */
    public function generateUniqueBarcode()
    {
        do {
            // Generate 10 random digits appended to '885'
            $barcode = '885' . str_pad(mt_rand(1, 9999999999), 10, '0', STR_PAD_LEFT);
            
            // Check uniqueness in database
            $stmt = $this->db->prepare("SELECT id FROM " . $this->table . " WHERE barcode = :barcode LIMIT 1");
            $stmt->execute([':barcode' => $barcode]);
            $exists = $stmt->rowCount() > 0;
        } while ($exists);

        return $barcode;
    }

    /**
     * Get paginated products with filter and search criteria
     */
    public function list($page = 1, $limit = 10, $filters = [])
    {
        try {
            $offset = ($page - 1) * $limit;
            $where = [];
            $params = [];

            // Search by Name or Barcode
            if (!empty($filters['search'])) {
                $where[] = "(p.name LIKE :search OR p.barcode LIKE :search)";
                $params[':search'] = '%' . trim($filters['search']) . '%';
            }

            // Filter by Category ID
            if (!empty($filters['category_id'])) {
                $where[] = "p.category_id = :category_id";
                $params[':category_id'] = (int)$filters['category_id'];
            }

            // Filter by Sub Category ID
            if (!empty($filters['sub_category_id'])) {
                $where[] = "p.sub_category_id = :sub_category_id";
                $params[':sub_category_id'] = (int)$filters['sub_category_id'];
            }

            // Filter by Stock Level Alert (Min Stock warning)
            if (isset($filters['low_stock']) && $filters['low_stock'] === true) {
                // Joins to check current active stock count
                $where[] = "(SELECT COUNT(*) FROM inventory_stocks i WHERE i.product_id = p.id AND i.status = 'available') < p.min_stock";
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

            // Get records with category and subcategory names, and count available stock
            $sql = "SELECT p.*, c.name as category_name, s.name as sub_category_name,
                           (SELECT COUNT(*) FROM inventory_stocks i WHERE i.product_id = p.id AND i.status = 'available') as available_stock
                    FROM " . $this->table . " p
                    LEFT JOIN categories c ON p.category_id = c.id
                    LEFT JOIN sub_categories s ON p.sub_category_id = s.id"
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

            // Typecasting
            foreach ($items as &$item) {
                $item['id'] = (int)$item['id'];
                $item['category_id'] = (int)$item['category_id'];
                $item['sub_category_id'] = (int)$item['sub_category_id'];
                $item['width_inch'] = (float)$item['width_inch'];
                $item['length_ft'] = (float)$item['length_ft'];
                $item['buy_rate'] = (float)$item['buy_rate'];
                $item['sell_rate'] = (float)$item['sell_rate'];
                $item['min_stock'] = (int)$item['min_stock'];
                $item['max_stock'] = (int)$item['max_stock'];
                $item['is_active'] = (int)$item['is_active'];
                $item['available_stock'] = (int)$item['available_stock'];
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
            error_log("Product::list error: " . $e->getMessage());
            return ['items' => [], 'pagination' => ['total' => 0, 'page' => $page, 'limit' => $limit, 'pages' => 0]];
        }
    }

    /**
     * Find by ID
     */
    public function findById($id)
    {
        try {
            $sql = "SELECT p.*, c.name as category_name, s.name as sub_category_name,
                           (SELECT COUNT(*) FROM inventory_stocks i WHERE i.product_id = p.id AND i.status = 'available') as available_stock
                    FROM " . $this->table . " p
                    LEFT JOIN categories c ON p.category_id = c.id
                    LEFT JOIN sub_categories s ON p.sub_category_id = s.id
                    WHERE p.id = :id LIMIT 1";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':id' => (int)$id]);
            $item = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($item) {
                $item['id'] = (int)$item['id'];
                $item['category_id'] = (int)$item['category_id'];
                $item['sub_category_id'] = (int)$item['sub_category_id'];
                $item['width_inch'] = (float)$item['width_inch'];
                $item['length_ft'] = (float)$item['length_ft'];
                $item['buy_rate'] = (float)$item['buy_rate'];
                $item['sell_rate'] = (float)$item['sell_rate'];
                $item['min_stock'] = (int)$item['min_stock'];
                $item['max_stock'] = (int)$item['max_stock'];
                $item['is_active'] = (int)$item['is_active'];
                $item['available_stock'] = (int)$item['available_stock'];
                $item['created_by'] = $item['created_by'] !== null ? (int)$item['created_by'] : null;
            }

            return $item;
        } catch (PDOException $e) {
            error_log("Product::findById error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Find product by Barcode (scan)
     */
    public function findByBarcode($barcode)
    {
        try {
            $sql = "SELECT p.*, c.name as category_name, s.name as sub_category_name,
                           (SELECT COUNT(*) FROM inventory_stocks i WHERE i.product_id = p.id AND i.status = 'available') as available_stock
                    FROM " . $this->table . " p
                    LEFT JOIN categories c ON p.category_id = c.id
                    LEFT JOIN sub_categories s ON p.sub_category_id = s.id
                    WHERE p.barcode = :barcode LIMIT 1";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':barcode' => trim($barcode)]);
            $item = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($item) {
                $item['id'] = (int)$item['id'];
                $item['category_id'] = (int)$item['category_id'];
                $item['sub_category_id'] = (int)$item['sub_category_id'];
                $item['width_inch'] = (float)$item['width_inch'];
                $item['length_ft'] = (float)$item['length_ft'];
                $item['buy_rate'] = (float)$item['buy_rate'];
                $item['sell_rate'] = (float)$item['sell_rate'];
                $item['min_stock'] = (int)$item['min_stock'];
                $item['max_stock'] = (int)$item['max_stock'];
                $item['is_active'] = (int)$item['is_active'];
                $item['available_stock'] = (int)$item['available_stock'];
                $item['created_by'] = $item['created_by'] !== null ? (int)$item['created_by'] : null;
            }

            return $item;
        } catch (PDOException $e) {
            error_log("Product::findByBarcode error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if barcode exists (for duplicate checking)
     */
    public function barcodeExists($barcode, $excludeId = null)
    {
        try {
            $sql = "SELECT id FROM " . $this->table . " WHERE barcode = :barcode";
            $params = [':barcode' => trim($barcode)];
            
            if ($excludeId !== null) {
                $sql .= " AND id != :exclude_id";
                $params[':exclude_id'] = (int)$excludeId;
            }
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log("Product::barcodeExists error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Create product
     */
    public function create($data)
    {
        try {
            // Generate barcode if not provided or empty
            if (empty($data['barcode'])) {
                $data['barcode'] = $this->generateUniqueBarcode();
            }

            // Auto-seed default subcategory if it doesn't exist to prevent foreign key errors
            $subCatId = isset($data['sub_category_id']) ? (int)$data['sub_category_id'] : 1;
            $stmtSubCheck = $this->db->prepare("SELECT id FROM sub_categories WHERE id = :sub_cat_id LIMIT 1");
            $stmtSubCheck->execute([':sub_cat_id' => $subCatId]);
            if ($stmtSubCheck->rowCount() === 0) {
                $stmtInsertSub = $this->db->prepare("INSERT INTO sub_categories (id, category_id, name, created_by) VALUES (:id, :category_id, :name, :created_by)");
                $stmtInsertSub->execute([
                    ':id' => $subCatId,
                    ':category_id' => (int)$data['category_id'],
                    ':name' => 'ທົ່ວໄປ',
                    ':created_by' => $data['created_by'] ?? null
                ]);
            }

            $sql = "INSERT INTO " . $this->table . " (
                        barcode, name, description, category_id, sub_category_id, 
                        width_inch, length_ft, buy_rate, sell_rate, min_stock, max_stock, 
                        image, is_active, created_by, created_at, updated_at
                    ) VALUES (
                        :barcode, :name, :description, :category_id, :sub_category_id, 
                        :width_inch, :length_ft, :buy_rate, :sell_rate, :min_stock, :max_stock, 
                        :image, :is_active, :created_by, NOW(), NOW()
                    )";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':barcode' => trim($data['barcode']),
                ':name' => trim($data['name']),
                ':description' => $data['description'] ?? '',
                ':category_id' => (int)$data['category_id'],
                ':sub_category_id' => $subCatId,
                ':width_inch' => isset($data['width_inch']) ? (float)$data['width_inch'] : 0.0,
                ':length_ft' => isset($data['length_ft']) ? (float)$data['length_ft'] : 0.0,
                ':buy_rate' => isset($data['buy_rate']) ? (float)$data['buy_rate'] : 0.0,
                ':sell_rate' => isset($data['sell_rate']) ? (float)$data['sell_rate'] : 0.0,
                ':min_stock' => isset($data['min_stock']) ? (int)$data['min_stock'] : 5,
                ':max_stock' => isset($data['max_stock']) ? (int)$data['max_stock'] : 100,
                ':image' => $data['image'] ?? null,
                ':is_active' => isset($data['is_active']) ? (int)$data['is_active'] : 1,
                ':created_by' => $data['created_by'] ?? null
            ]);

            return $this->db->lastInsertId();
        } catch (PDOException $e) {
            error_log("Product::create error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update product
     */
    public function update($id, $data)
    {
        try {
            $fields = "";
            $params = [':id' => (int)$id];

            $updatableFields = [
                'barcode', 'name', 'description', 'category_id', 'sub_category_id',
                'width_inch', 'length_ft', 'buy_rate', 'sell_rate', 'min_stock', 'max_stock',
                'image', 'is_active'
            ];

            foreach ($updatableFields as $field) {
                if (isset($data[$field])) {
                    $fields .= "$field = :$field, ";
                    if ($field === 'barcode' || $field === 'name' || $field === 'description' || $field === 'image') {
                        $params[":$field"] = $data[$field] !== null ? trim($data[$field]) : null;
                    } elseif ($field === 'category_id' || $field === 'sub_category_id' || $field === 'min_stock' || $field === 'max_stock' || $field === 'is_active') {
                        $params[":$field"] = (int)$data[$field];
                    } else {
                        $params[":$field"] = (float)$data[$field];
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
            error_log("Product::update error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete product (only if no stock referencing it)
     */
    public function delete($id)
    {
        try {
            // Check if product is referenced in inventory_stocks
            $stmt = $this->db->prepare("SELECT id FROM inventory_stocks WHERE product_id = :product_id LIMIT 1");
            $stmt->execute([':product_id' => (int)$id]);
            if ($stmt->rowCount() > 0) {
                throw new Exception("Product cannot be deleted because it has items in inventory stocks.");
            }

            $stmt = $this->db->prepare("DELETE FROM " . $this->table . " WHERE id = :id");
            return $stmt->execute([':id' => (int)$id]);
        } catch (Exception $e) {
            error_log("Product::delete error: " . $e->getMessage());
            throw $e;
        }
    }
}
?>
