<?php
// src/models/CategoryInheritance.php
require_once __DIR__ . '/../config/database.php';

class CategoryInheritance {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * ດຶງການສືບທອດຂອງໝວດໝູ່
     */
    public function getByCategoryId($categoryId) {
        $stmt = $this->db->prepare(
            "SELECT ci.*, 
                    ca.attribute_name, ca.attribute_type, ca.options
             FROM category_inheritance ci
             LEFT JOIN category_attributes ca ON ci.attribute_name = ca.attribute_name 
                 AND ci.inherited_from_id = ca.category_id
             WHERE ci.category_id = ?"
        );
        $stmt->execute([$categoryId]);
        return $stmt->fetchAll();
    }

    /**
     * ສ້າງການສືບທອດໃໝ່
     */
    public function create($data) {
        $sql = "INSERT INTO category_inheritance (
            category_id, inherited_from_id, attribute_name, is_overridden
        ) VALUES (?, ?, ?, ?)";
        
        $stmt = $this->db->prepare($sql);
        
        try {
            $stmt->execute([
                $data['category_id'],
                $data['inherited_from_id'],
                $data['attribute_name'],
                $data['is_overridden'] ?? 0
            ]);
            
            return [
                'success' => true,
                'inheritance_id' => $this->db->lastInsertId(),
                'message' => 'Inheritance created successfully'
            ];
        } catch (PDOException $e) {
            error_log("Create inheritance failed: " . $e->getMessage());
            return ['success' => false, 'message' => 'Creation failed: ' . $e->getMessage()];
        }
    }

    /**
     * ອັບເດດການສືບທອດ
     */
    public function update($id, $isOverridden) {
        $stmt = $this->db->prepare("UPDATE category_inheritance SET is_overridden = ? WHERE id = ?");
        
        try {
            $stmt->execute([$isOverridden, $id]);
            return ['success' => true, 'message' => 'Inheritance updated successfully'];
        } catch (PDOException $e) {
            error_log("Update inheritance failed: " . $e->getMessage());
            return ['success' => false, 'message' => 'Update failed: ' . $e->getMessage()];
        }
    }

    /**
     * ລຶບການສືບທອດ
     */
    public function delete($id) {
        try {
            $stmt = $this->db->prepare("DELETE FROM category_inheritance WHERE id = ?");
            $stmt->execute([$id]);
            
            return ['success' => true, 'message' => 'Inheritance deleted successfully'];
        } catch (PDOException $e) {
            error_log("Delete inheritance failed: " . $e->getMessage());
            return ['success' => false, 'message' => 'Delete failed: ' . $e->getMessage()];
        }
    }
}
?>