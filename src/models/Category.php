<?php
// src/models/Category.php
require_once __DIR__ . '/../config/database.php';

class Category
{
    private $db;
    private $table = 'categories';

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Get all categories (simple list, e.g. for dropdown)
     */
    public function all($activeOnly = false)
    {
        try {
            $sql = "SELECT id, name, description, is_active, created_at FROM " . $this->table;
            if ($activeOnly) {
                $sql .= " WHERE is_active = 1";
            }
            $sql .= " ORDER BY name ASC";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Category::all error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get paginated categories
     */
    public function list($page = 1, $limit = 10)
    {
        try {
            $offset = ($page - 1) * $limit;
            
            // Get total count
            $countSql = "SELECT COUNT(*) FROM " . $this->table;
            $total = $this->db->query($countSql)->fetchColumn();
            
            // Get records
            $sql = "SELECT id, name, description, is_active, created_at 
                    FROM " . $this->table . " 
                    ORDER BY id DESC 
                    LIMIT :limit OFFSET :offset";
            
            $stmt = $this->db->prepare($sql);
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
            error_log("Category::list error: " . $e->getMessage());
            return ['items' => [], 'pagination' => ['total' => 0, 'page' => $page, 'limit' => $limit, 'pages' => 0]];
        }
    }

    /**
     * Find category by ID
     */
    public function findById($id)
    {
        try {
            $stmt = $this->db->prepare("SELECT * FROM " . $this->table . " WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => (int)$id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Category::findById error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if category name exists
     */
    public function exists($name, $excludeId = null)
    {
        try {
            $sql = "SELECT id FROM " . $this->table . " WHERE name = :name";
            $params = [':name' => trim($name)];
            
            if ($excludeId !== null) {
                $sql .= " AND id != :exclude_id";
                $params[':exclude_id'] = (int)$excludeId;
            }
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log("Category::exists error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Create category
     */
    public function create($data)
    {
        try {
            $stmt = $this->db->prepare("INSERT INTO " . $this->table . " 
                (name, description, is_active, created_by, created_at) 
                VALUES (:name, :description, :is_active, :created_by, NOW())");
            
            $stmt->execute([
                ':name' => trim($data['name']),
                ':description' => $data['description'] ?? '',
                ':is_active' => isset($data['is_active']) ? (int)$data['is_active'] : 1,
                ':created_by' => $data['created_by'] ?? null
            ]);
            
            return $this->db->lastInsertId();
        } catch (PDOException $e) {
            error_log("Category::create error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update category
     */
    public function update($id, $data)
    {
        try {
            $fields = "";
            $params = [':id' => (int)$id];
            
            // Allow update of name, description, is_active
            if (isset($data['name'])) {
                $fields .= "name = :name, ";
                $params[':name'] = trim($data['name']);
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
            error_log("Category::update error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete category
     */
    public function delete($id)
    {
        try {
            $stmt = $this->db->prepare("DELETE FROM " . $this->table . " WHERE id = :id");
            return $stmt->execute([':id' => (int)$id]);
        } catch (PDOException $e) {
            error_log("Category::delete error: " . $e->getMessage());
            return false;
        }
    }
}
?>
