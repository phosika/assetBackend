<?php
// src/models/AssetImage.php
require_once __DIR__ . '/../config/database.php';

class AssetImage {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * ດຶງຮູບພາບທັງໝົດຂອງຊັບສິນ
     */
    public function getByAssetId($assetId) {
        $stmt = $this->db->prepare(
            "SELECT i.*, CONCAT(u.first_name, ' ', u.last_name) as uploaded_by_name
             FROM asset_images i
             LEFT JOIN users u ON i.uploaded_by = u.id
             WHERE i.asset_id = ?
             ORDER BY i.sort_order ASC, i.uploaded_at DESC"
        );
        $stmt->execute([$assetId]);
        return $stmt->fetchAll();
    }

    /**
     * ດຶງຮູບພາບຕາມ ID
     */
    public function getById($id) {
        $stmt = $this->db->prepare(
            "SELECT i.*, CONCAT(u.first_name, ' ', u.last_name) as uploaded_by_name
             FROM asset_images i
             LEFT JOIN users u ON i.uploaded_by = u.id
             WHERE i.id = ?"
        );
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    /**
     * ເພີ່ມຮູບພາບໃໝ່
     */
    public function create($data, $uploadedBy) {
        // ກວດສອບວ່າເປັນຮູບຫຼັກບໍ
        if (!empty($data['is_primary'])) {
            // ຍົກເລີກການເປັນຮູບຫຼັກຂອງຮູບອື່ນ
            $this->resetPrimaryImage($data['asset_id']);
        }

        $sql = "INSERT INTO asset_images (
            asset_id, image_path, image_type, description, sort_order, uploaded_by, uploaded_at
        ) VALUES (?, ?, ?, ?, ?, ?, NOW())";
        
        $stmt = $this->db->prepare($sql);
        
        try {
            $stmt->execute([
                $data['asset_id'],
                $data['image_path'],
                $data['image_type'] ?? 'general',
                $data['description'] ?? null,
                $data['sort_order'] ?? 0,
                $uploadedBy
            ]);
            
            $imageId = $this->db->lastInsertId();

            // ຖ້າເປັນຮູບຫຼັກ, ອັບເດດ asset_image_path ໃນຕາຕະລາງ assets
            if (!empty($data['is_primary'])) {
                $this->updateAssetPrimaryImage($data['asset_id'], $data['image_path']);
            }

            return [
                'success' => true,
                'image_id' => $imageId,
                'message' => 'Image uploaded successfully'
            ];
        } catch (PDOException $e) {
            error_log("Upload image failed: " . $e->getMessage());
            return ['success' => false, 'message' => 'Upload failed: ' . $e->getMessage()];
        }
    }

    /**
     * ຕັ້ງຮູບຫຼັກ
     */
    public function setPrimaryImage($id, $assetId) {
        try {
            $this->db->beginTransaction();

            // ຍົກເລີກການເປັນຮູບຫຼັກຂອງຮູບອື່ນ
            $resetStmt = $this->db->prepare("UPDATE asset_images SET is_primary = 0 WHERE asset_id = ?");
            $resetStmt->execute([$assetId]);

            // ຕັ້ງຮູບນີ້ເປັນຮູບຫຼັກ
            $setStmt = $this->db->prepare("UPDATE asset_images SET is_primary = 1 WHERE id = ?");
            $setStmt->execute([$id]);

            // ດຶງ path ຮູບເພື່ອອັບເດດໃນຕາຕະລາງ assets
            $image = $this->getById($id);
            if ($image) {
                $updateAssetStmt = $this->db->prepare("UPDATE assets SET asset_image_path = ? WHERE id = ?");
                $updateAssetStmt->execute([$image['image_path'], $assetId]);
            }

            $this->db->commit();
            
            return ['success' => true, 'message' => 'Primary image set successfully'];
        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("Set primary image failed: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to set primary image'];
        }
    }

    /**
     * ລຶບຮູບພາບ
     */
    public function delete($id) {
        try {
            // ດຶງຂໍ້ມູນຮູບກ່ອນລຶບ
            $image = $this->getById($id);
            if ($image && file_exists($image['image_path'])) {
                unlink($image['image_path']); // ລຶບໄຟລ໌ຈິງ
            }

            // ຖ້າເປັນຮູບຫຼັກ, ລ້າງ asset_image_path ໃນຕາຕະລາງ assets
            if ($image && $image['is_primary']) {
                $updateAssetStmt = $this->db->prepare("UPDATE assets SET asset_image_path = NULL WHERE id = ?");
                $updateAssetStmt->execute([$image['asset_id']]);
            }

            $stmt = $this->db->prepare("DELETE FROM asset_images WHERE id = ?");
            $stmt->execute([$id]);
            
            return ['success' => true, 'message' => 'Image deleted successfully'];
        } catch (PDOException $e) {
            error_log("Delete image failed: " . $e->getMessage());
            return ['success' => false, 'message' => 'Delete failed: ' . $e->getMessage()];
        }
    }

    /**
     * ຈັດລຽງລຳດັບຮູບ
     */
    public function reorderImages($assetId, $imageIds) {
        try {
            foreach ($imageIds as $index => $imageId) {
                $stmt = $this->db->prepare("UPDATE asset_images SET sort_order = ? WHERE id = ? AND asset_id = ?");
                $stmt->execute([$index + 1, $imageId, $assetId]);
            }
            
            return ['success' => true, 'message' => 'Images reordered successfully'];
        } catch (PDOException $e) {
            error_log("Reorder images failed: " . $e->getMessage());
            return ['success' => false, 'message' => 'Reorder failed: ' . $e->getMessage()];
        }
    }

    /**
     * ຍົກເລີກການເປັນຮູບຫຼັກຂອງຮູບທັງໝົດ
     */
    private function resetPrimaryImage($assetId) {
        $stmt = $this->db->prepare("UPDATE asset_images SET is_primary = 0 WHERE asset_id = ?");
        $stmt->execute([$assetId]);
    }

    /**
     * ອັບເດດ asset_image_path ໃນຕາຕະລາງ assets
     */
    private function updateAssetPrimaryImage($assetId, $imagePath) {
        $stmt = $this->db->prepare("UPDATE assets SET asset_image_path = ? WHERE id = ?");
        $stmt->execute([$imagePath, $assetId]);
    }
}
?>