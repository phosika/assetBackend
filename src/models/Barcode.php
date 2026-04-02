<?php
// /var/www/html/models/Barcode.php

require_once __DIR__ . '/../config/database.php';

class Barcode {
    private $db;
    private $table = 'barcodes';  // ຕ້ອງມີບັນທັດນີ້!

    public function __construct() {
        $this->db = Database::getInstance();
        if (!$this->db) {
            error_log("Database connection failed in Barcode model");
            throw new Exception("Database connection failed");
        }
    }

    /**
     * ດຶງຂໍ້ມູນ barcode ທັງໝົດ
     */
    public function getAllBarcodes($filters = [], $companyId = null) {
        try {
            $sql = "SELECT b.*, i.item_name, i.item_code 
                    FROM {$this->table} b
                    LEFT JOIN inventory_items i ON b.item_id = i.id
                    WHERE 1=1";
            
            $params = [];

            if ($companyId) {
                $sql .= " AND b.company_id = ?";
                $params[] = $companyId;
            }

            if (!empty($filters['search'])) {
                $sql .= " AND (b.barcode_number LIKE ? OR i.item_name LIKE ? OR i.item_code LIKE ?)";
                $searchTerm = "%{$filters['search']}%";
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }

            $sql .= " ORDER BY b.created_at DESC";
            
            if (!empty($filters['limit'])) {
                $sql .= " LIMIT ?";
                $params[] = $filters['limit'];
            }

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            error_log("Error in getAllBarcodes: " . $e->getMessage());
            return [];
        }
    }

    /**
     * ສ້າງ barcode ໃໝ່
     */
    public function createBarcode($data, $createdBy = null, $companyId = null) {
        try {
            // ກວດສອບວ່າ barcode ຊໍ້າກັນບໍ
            $checkSql = "SELECT id FROM {$this->table} WHERE barcode_number = ?";
            $checkStmt = $this->db->prepare($checkSql);
            $checkStmt->execute([$data['barcode_number']]);
            
            if ($checkStmt->fetch()) {
                return [
                    'success' => false,
                    'message' => 'ເລກທີ barcode ນີ້ມີໃນລະບົບແລ້ວ'
                ];
            }

            $sql = "INSERT INTO {$this->table} (
                        item_id, barcode_number, barcode_type, 
                        width, height, created_by, company_id, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
            
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute([
                $data['item_id'],
                $data['barcode_number'],
                $data['barcode_type'] ?? 'CODE128',
                $data['width'] ?? 2,
                $data['height'] ?? 60,
                $createdBy,
                $companyId
            ]);

            if (!$result) {
                $error = $stmt->errorInfo();
                return [
                    'success' => false,
                    'message' => 'ບໍ່ສາມາດບັນທຶກຂໍ້ມູນ: ' . ($error[2] ?? 'Unknown error')
                ];
            }

            return [
                'success' => true,
                'message' => 'ສ້າງ barcode ສຳເລັດ',
                'id' => $this->db->lastInsertId()
            ];

        } catch (Exception $e) {
            error_log("Error in createBarcode: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'ສ້າງ barcode ບໍ່ສຳເລັດ: ' . $e->getMessage()
            ];
        }
    }

    /**
     * ອັບເດດສະຖານະການພິມ
     */
    public function updatePrintStatus($id, $printed = true) {
        try {
            $sql = "UPDATE {$this->table} 
                    SET printed = ?, 
                        printed_at = CASE WHEN ? = 1 THEN NOW() ELSE printed_at END,
                        updated_at = NOW()
                    WHERE id = ?";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$printed, $printed, $id]);

            return [
                'success' => true,
                'message' => 'ອັບເດດສະຖານະສຳເລັດ'
            ];

        } catch (Exception $e) {
            error_log("Error in updatePrintStatus: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'ອັບເດດສະຖານະບໍ່ສຳເລັດ'
            ];
        }
    }

    /**
     * ສ້າງ barcode ຈາກສິນຄ້າທີ່ມີຢູ່
     */
    public function generateBarcodeFromItem($itemId, $createdBy = null, $companyId = null) {
        try {
            // ດຶງຂໍ້ມູນສິນຄ້າ
            require_once __DIR__ . '/InventoryItem.php';
            $itemModel = new InventoryItem();
            $item = $itemModel->getItemById($itemId);
            
            if (!$item) {
                return [
                    'success' => false,
                    'message' => 'ບໍ່ພົບສິນຄ້າ'
                ];
            }

            // ສ້າງ barcode number ອັດຕະໂນມັດ
            $barcodeNumber = $item['item_code'] . '-' . date('YmdHis');
            
            $data = [
                'item_id' => $itemId,
                'barcode_number' => $barcodeNumber,
                'barcode_type' => 'CODE128',
                'width' => 2,
                'height' => 60
            ];
            
            return $this->createBarcode($data, $createdBy, $companyId);

        } catch (Exception $e) {
            error_log("Error in generateBarcodeFromItem: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'ສ້າງ barcode ບໍ່ສຳເລັດ: ' . $e->getMessage()
            ];
        }
    }
}
?>