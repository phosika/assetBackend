<?php
// src/models/Barcode.php
require_once __DIR__ . '/../config/database.php';

class Barcode {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * ສ້າງ barcode ໃໝ່
     */
    public function generate($data, $generatedBy) {
        // ກວດສອບວ່າ barcode ມີແລ້ວບໍ
        $checkStmt = $this->db->prepare("SELECT id FROM barcode_generator WHERE barcode = ?");
        $checkStmt->execute([$data['barcode']]);
        if ($checkStmt->fetch()) {
            return ['success' => false, 'message' => 'Barcode already exists'];
        }

        $sql = "INSERT INTO barcode_generator (
            barcode, barcode_type, reference_type, reference_id, generated_for,
            file_path, generated_by, generated_at, print_count
        ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), 0)";
        
        $stmt = $this->db->prepare($sql);
        
        try {
            $stmt->execute([
                $data['barcode'],
                $data['barcode_type'] ?? 'code128',
                $data['reference_type'],
                $data['reference_id'],
                $data['generated_for'] ?? null,
                $data['file_path'] ?? null,
                $generatedBy
            ]);
            
            return [
                'success' => true,
                'barcode_id' => $this->db->lastInsertId(),
                'message' => 'Barcode generated successfully'
            ];
        } catch (PDOException $e) {
            error_log("Generate barcode failed: " . $e->getMessage());
            return ['success' => false, 'message' => 'Generation failed: ' . $e->getMessage()];
        }
    }

    /**
     * ບັນທຶກການສະແກນ barcode
     */
    public function recordScan($data, $scannedBy) {
        $sql = "INSERT INTO barcode_scans (
            barcode, scan_type, reference_type, reference_id, stock_id, item_id,
            quantity, scan_location, scanned_by, scan_time, is_valid, error_message, notes
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?)";
        
        $stmt = $this->db->prepare($sql);
        
        try {
            $stmt->execute([
                $data['barcode'],
                $data['scan_type'] ?? 'scan',
                $data['reference_type'] ?? null,
                $data['reference_id'] ?? null,
                $data['stock_id'] ?? null,
                $data['item_id'] ?? null,
                $data['quantity'] ?? 1,
                $data['scan_location'] ?? null,
                $scannedBy,
                $data['is_valid'] ?? 1,
                $data['error_message'] ?? null,
                $data['notes'] ?? null
            ]);
            
            // ອັບເດດການນັບການພິມ
            $updateStmt = $this->db->prepare(
                "UPDATE barcode_generator SET print_count = print_count + 1, last_printed_at = NOW() 
                 WHERE barcode = ?"
            );
            $updateStmt->execute([$data['barcode']]);
            
            return [
                'success' => true,
                'scan_id' => $this->db->lastInsertId(),
                'message' => 'Scan recorded successfully'
            ];
        } catch (PDOException $e) {
            error_log("Record scan failed: " . $e->getMessage());
            return ['success' => false, 'message' => 'Record failed: ' . $e->getMessage()];
        }
    }

    /**
     * ດຶງປະຫວັດການສະແກນ
     */
    public function getScanHistory($barcode = null, $referenceType = null, $referenceId = null) {
        $sql = "SELECT s.*, CONCAT(u.first_name, ' ', u.last_name) as scanned_by_name
                FROM barcode_scans s
                LEFT JOIN users u ON s.scanned_by = u.id
                WHERE 1=1";
        $params = [];

        if ($barcode) {
            $sql .= " AND s.barcode = ?";
            $params[] = $barcode;
        }

        if ($referenceType) {
            $sql .= " AND s.reference_type = ?";
            $params[] = $referenceType;
        }

        if ($referenceId) {
            $sql .= " AND s.reference_id = ?";
            $params[] = $referenceId;
        }

        $sql .= " ORDER BY s.scan_time DESC LIMIT 100";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * ອັບເດດຈຳນວນການພິມ
     */
    public function incrementPrintCount($barcode) {
        $stmt = $this->db->prepare(
            "UPDATE barcode_generator SET print_count = print_count + 1, last_printed_at = NOW() 
             WHERE barcode = ?"
        );
        $stmt->execute([$barcode]);
        
        return ['success' => true, 'message' => 'Print count updated'];
    }



    /**
     * ດຶງຂໍ້ມູນ barcode ທັງໝົດ
     */
    public function getAllBarcodes($filters = [], $companyId = null) {
        try {
            $sql = "SELECT b.*, 
                           i.item_name,
                           i.item_code,
                           comp.company_name
                    FROM {$this->table} b
                    LEFT JOIN inventory_items i ON b.item_id = i.id
                    LEFT JOIN companies comp ON b.company_id = comp.id
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
                        format, width, height, 
                        created_by, company_id, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['item_id'],
                $data['barcode_number'],
                $data['barcode_type'],
                $data['format'] ?? 'CODE128',
                $data['width'] ?? 2,
                $data['height'] ?? 60,
                $createdBy,
                $companyId
            ]);

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
                'format' => 'CODE128',
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


}
?>