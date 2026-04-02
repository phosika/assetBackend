<?php
// src/models/CategoryAttribute.php
require_once __DIR__ . '/../config/database.php';

class CategoryAttribute {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * ດຶງຄຸນສົມບັດຂອງໝວດໝູ່
     */
    public function getByCategoryId($categoryId) {
        $stmt = $this->db->prepare(
            "SELECT * FROM category_attributes 
             WHERE category_id = ? 
             ORDER BY sort_order ASC, attribute_name ASC"
        );
        $stmt->execute([$categoryId]);
        return $stmt->fetchAll();
    }

    /**
     * ດຶງຄຸນສົມບັດຕາມ ID
     */
    public function getById($id) {
        $stmt = $this->db->prepare("SELECT * FROM category_attributes WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    /**
     * ສ້າງຄຸນສົມບັດໃໝ່
     */
    public function create($data) {
        $sql = "INSERT INTO category_attributes (
            category_id, attribute_name, attribute_type, is_required, options, sort_order
        ) VALUES (?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->db->prepare($sql);
        
        try {
            $stmt->execute([
                $data['category_id'],
                $data['attribute_name'],
                $data['attribute_type'] ?? 'text',
                $data['is_required'] ?? 0,
                $data['options'] ?? null,
                $data['sort_order'] ?? 0
            ]);
            
            return [
                'success' => true,
                'attribute_id' => $this->db->lastInsertId(),
                'message' => 'Attribute created successfully'
            ];
        } catch (PDOException $e) {
            error_log("Create attribute failed: " . $e->getMessage());
            return ['success' => false, 'message' => 'Creation failed: ' . $e->getMessage()];
        }
    }

    /**
     * ອັບເດດຄຸນສົມບັດ
     */
    public function update($id, $data) {
        $fields = [];
        $params = [];

        $allowedFields = ['attribute_name', 'attribute_type', 'is_required', 'options', 'sort_order'];

        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $fields[] = "{$field} = ?";
                $params[] = $data[$field];
            }
        }

        if (empty($fields)) {
            return ['success' => false, 'message' => 'No data to update'];
        }

        $params[] = $id;
        $sql = "UPDATE category_attributes SET " . implode(', ', $fields) . " WHERE id = ?";
        
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            
            return ['success' => true, 'message' => 'Attribute updated successfully'];
        } catch (PDOException $e) {
            error_log("Update attribute failed: " . $e->getMessage());
            return ['success' => false, 'message' => 'Update failed: ' . $e->getMessage()];
        }
    }

    /**
     * ລຶບຄຸນສົມບັດ
     */
    public function delete($id) {
        try {
            $stmt = $this->db->prepare("DELETE FROM category_attributes WHERE id = ?");
            $stmt->execute([$id]);
            
            return ['success' => true, 'message' => 'Attribute deleted successfully'];
        } catch (PDOException $e) {
            error_log("Delete attribute failed: " . $e->getMessage());
            return ['success' => false, 'message' => 'Delete failed: ' . $e->getMessage()];
        }
    }
}
?>