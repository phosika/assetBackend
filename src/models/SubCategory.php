<?php
// src/models/SubCategory.php
require_once __DIR__ . '/../config/database.php';

class SubCategory
{
    private $db;
    private $table = 'sub_categories';

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Get all subcategories, optionally filtered by Category ID or active status
     */
    public function all($categoryId = null, $activeOnly = false)
    {
        try {
            $sql = "SELECT s.id, s.category_id, s.name, s.arrival_date, s.description, s.is_active, s.created_at, c.name as category_name 
                    FROM " . $this->table . " s 
                    LEFT JOIN categories c ON s.category_id = c.id";
            
            $where = [];
            $params = [];
            
            if ($categoryId !== null) {
                $where[] = "s.category_id = :category_id";
                $params[':category_id'] = (int)$categoryId;
            }
            
            if ($activeOnly) {
                $where[] = "s.is_active = 1";
            }
            
            if (!empty($where)) {
                $sql .= " WHERE " . implode(' AND ', $where);
            }
            
            $sql .= " ORDER BY s.name ASC";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("SubCategory::all error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get paginated subcategories
     */
    public function list($page = 1, $limit = 10, $categoryId = null)
    {
        try {
            $offset = ($page - 1) * $limit;
            $where = "";
            $params = [];
            
            if ($categoryId !== null) {
                $where = " WHERE s.category_id = :category_id";
                $params[':category_id'] = (int)$categoryId;
            }
            
            // Get count
            $countSql = "SELECT COUNT(*) FROM " . $this->table . " s" . $where;
            $countStmt = $this->db->prepare($countSql);
            $countStmt->execute($params);
            $total = $countStmt->fetchColumn();
            
            // Get records
            $sql = "SELECT s.id, s.category_id, s.name, s.arrival_date, s.description, s.is_active, s.created_at, c.name as category_name 
                    FROM " . $this->table . " s 
                    LEFT JOIN categories c ON s.category_id = c.id" 
                    . $where . " 
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
            error_log("SubCategory::list error: " . $e->getMessage());
            return ['items' => [], 'pagination' => ['total' => 0, 'page' => $page, 'limit' => $limit, 'pages' => 0]];
        }
    }

    /**
     * Find by ID
     */
    public function findById($id)
    {
        try {
            $sql = "SELECT s.*, c.name as category_name 
                    FROM " . $this->table . " s 
                    LEFT JOIN categories c ON s.category_id = c.id 
                    WHERE s.id = :id LIMIT 1";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':id' => (int)$id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("SubCategory::findById error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Check name exists under a category
     */
    public function exists($name, $categoryId, $excludeId = null)
    {
        try {
            $sql = "SELECT id FROM " . $this->table . " WHERE name = :name AND category_id = :category_id";
            $params = [
                ':name' => trim($name),
                ':category_id' => (int)$categoryId
            ];
            
            if ($excludeId !== null) {
                $sql .= " AND id != :exclude_id";
                $params[':exclude_id'] = (int)$excludeId;
            }
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log("SubCategory::exists error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Create subcategory
     */
    public function create($data)
    {
        try {
            $stmt = $this->db->prepare("INSERT INTO " . $this->table . " 
                (category_id, name, arrival_date, description, is_active, created_by, created_at) 
                VALUES (:category_id, :name, :arrival_date, :description, :is_active, :created_by, NOW())");
            
            $stmt->execute([
                ':category_id' => (int)$data['category_id'],
                ':name' => trim($data['name']),
                ':arrival_date' => !empty($data['arrival_date']) ? $data['arrival_date'] : null,
                ':description' => $data['description'] ?? '',
                ':is_active' => isset($data['is_active']) ? (int)$data['is_active'] : 1,
                ':created_by' => $data['created_by'] ?? null
            ]);
            
            return $this->db->lastInsertId();
        } catch (PDOException $e) {
            error_log("SubCategory::create error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update subcategory
     */
    public function update($id, $data)
    {
        try {
            $fields = "";
            $params = [':id' => (int)$id];
            
            if (isset($data['category_id'])) {
                $fields .= "category_id = :category_id, ";
                $params[':category_id'] = (int)$data['category_id'];
            }
            if (isset($data['name'])) {
                $fields .= "name = :name, ";
                $params[':name'] = trim($data['name']);
            }
            if (isset($data['arrival_date'])) {
                $fields .= "arrival_date = :arrival_date, ";
                $params[':arrival_date'] = !empty($data['arrival_date']) ? $data['arrival_date'] : null;
            }
            if (isset($data['description'])) {
                $fields .= "description = :description, ";
                $params[':description'] = $data['description'];
            }
            if (isset($data['is_active'])) {
                $fields .= "is_active = :is_active, ";
                $params[':is_active'] = (int)$data['is_active'];
            }
            
            if (empty($fields)) {
                return false;
            }
            
            $fields = rtrim($fields, ", ");
            $sql = "UPDATE " . $this->table . " SET " . $fields . " WHERE id = :id";
            
            $stmt = $this->db->prepare($sql);
            return $stmt->execute($params);
        } catch (PDOException $e) {
            error_log("SubCategory::update error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete subcategory
     */
    public function delete($id)
    {
        try {
            $stmt = $this->db->prepare("DELETE FROM " . $this->table . " WHERE id = :id");
            return $stmt->execute([':id' => (int)$id]);
        } catch (PDOException $e) {
            error_log("SubCategory::delete error: " . $e->getMessage());
            return false;
        }
    }
}
?>
