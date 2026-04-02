<?php
// src/models/AssetDocument.php
require_once __DIR__ . '/../config/database.php';

class AssetDocument {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * ດຶງເອກະສານທັງໝົດຂອງຊັບສິນ
     */
    public function getByAssetId($assetId) {
        $stmt = $this->db->prepare(
            "SELECT d.*, CONCAT(u.first_name, ' ', u.last_name) as uploaded_by_name
             FROM asset_documents d
             LEFT JOIN users u ON d.uploaded_by = u.id
             WHERE d.asset_id = ?
             ORDER BY d.uploaded_at DESC"
        );
        $stmt->execute([$assetId]);
        return $stmt->fetchAll();
    }

    /**
     * ດຶງເອກະສານຕາມ ID
     */
    public function getById($id) {
        $stmt = $this->db->prepare(
            "SELECT d.*, CONCAT(u.first_name, ' ', u.last_name) as uploaded_by_name
             FROM asset_documents d
             LEFT JOIN users u ON d.uploaded_by = u.id
             WHERE d.id = ?"
        );
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    /**
     * ເພີ່ມເອກະສານໃໝ່
     */
    public function create($data, $uploadedBy) {
        $sql = "INSERT INTO asset_documents (
            asset_id, document_name, document_type, file_path, file_size, 
            mime_type, expiry_date, is_confidential, uploaded_by, uploaded_at, notes
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)";
        
        $stmt = $this->db->prepare($sql);
        
        try {
            $stmt->execute([
                $data['asset_id'],
                $data['document_name'],
                $data['document_type'],
                $data['file_path'],
                $data['file_size'],
                $data['mime_type'],
                $data['expiry_date'] ?? null,
                $data['is_confidential'] ?? 0,
                $uploadedBy,
                $data['notes'] ?? null
            ]);
            
            return [
                'success' => true,
                'document_id' => $this->db->lastInsertId(),
                'message' => 'Document uploaded successfully'
            ];
        } catch (PDOException $e) {
            error_log("Upload document failed: " . $e->getMessage());
            return ['success' => false, 'message' => 'Upload failed: ' . $e->getMessage()];
        }
    }

    /**
     * ລຶບເອກະສານ
     */
    public function delete($id) {
        try {
            // ດຶງຂໍ້ມູນເອກະສານກ່ອນລຶບ
            $doc = $this->getById($id);
            if ($doc && file_exists($doc['file_path'])) {
                unlink($doc['file_path']); // ລຶບໄຟລ໌ຈິງ
            }

            $stmt = $this->db->prepare("DELETE FROM asset_documents WHERE id = ?");
            $stmt->execute([$id]);
            
            return ['success' => true, 'message' => 'Document deleted successfully'];
        } catch (PDOException $e) {
            error_log("Delete document failed: " . $e->getMessage());
            return ['success' => false, 'message' => 'Delete failed: ' . $e->getMessage()];
        }
    }
}
?>