<?php
// src/models/Supplier.php
require_once __DIR__ . '/../config/database.php';

class Supplier
{
    private $db;
    private $table = 'suppliers';

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Get all suppliers (dropdown list)
     */
    public function all($activeOnly = false)
    {
        try {
            $sql = "SELECT id, name, phone, address, is_active FROM " . $this->table;
            if ($activeOnly) {
                $sql .= " WHERE is_active = 1";
            }
            $sql .= " ORDER BY name ASC";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Supplier::all error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get paginated suppliers
     */
    public function list($page = 1, $limit = 10)
    {
        try {
            $offset = ($page - 1) * $limit;
            
            // Get total count
            $countSql = "SELECT COUNT(*) FROM " . $this->table;
            $total = $this->db->query($countSql)->fetchColumn();
            
            // Get records
            $sql = "SELECT id, name, phone, address, is_active, created_at 
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
            error_log("Supplier::list error: " . $e->getMessage());
            return ['items' => [], 'pagination' => ['total' => 0, 'page' => $page, 'limit' => $limit, 'pages' => 0]];
        }
    }

    /**
     * Find supplier by ID
     */
    public function findById($id)
    {
        try {
            $stmt = $this->db->prepare("SELECT * FROM " . $this->table . " WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => (int)$id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Supplier::findById error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Create supplier
     */
    public function create($data)
    {
        try {
            $stmt = $this->db->prepare("INSERT INTO " . $this->table . " 
                (name, phone, address, is_active, created_by, created_at) 
                VALUES (:name, :phone, :address, :is_active, :created_by, NOW())");
            
            $stmt->execute([
                ':name' => trim($data['name']),
                ':phone' => $data['phone'] ?? null,
                ':address' => $data['address'] ?? null,
                ':is_active' => isset($data['is_active']) ? (int)$data['is_active'] : 1,
                ':created_by' => $data['created_by'] ?? null
            ]);
            
            return $this->db->lastInsertId();
        } catch (PDOException $e) {
            error_log("Supplier::create error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update supplier
     */
    public function update($id, $data)
    {
        try {
            $fields = "";
            $params = [':id' => (int)$id];
            
            if (isset($data['name'])) {
                $fields .= "name = :name, ";
                $params[':name'] = trim($data['name']);
            }
            if (isset($data['phone'])) {
                $fields .= "phone = :phone, ";
                $params[':phone'] = $data['phone'] !== '' ? trim($data['phone']) : null;
            }
            if (isset($data['address'])) {
                $fields .= "address = :address, ";
                $params[':address'] = $data['address'] !== '' ? trim($data['address']) : null;
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
            error_log("Supplier::update error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete supplier
     */
    public function delete($id)
    {
        try {
            $stmt = $this->db->prepare("DELETE FROM " . $this->table . " WHERE id = :id");
            return $stmt->execute([':id' => (int)$id]);
        } catch (PDOException $e) {
            error_log("Supplier::delete error: " . $e->getMessage());
            return false;
        }
    }
}
