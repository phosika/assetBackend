<?php
// src/models/InventoryItem.php
require_once __DIR__ . '/../config/database.php';

class InventoryItem {
    private $db;
    private $table = 'inventory_items';  // ເພີ່ມບັນທັດນີ້
    private $allowedBarcodeTypes = ['code128', 'code39', 'ean13', 'ean8', 'upc', 'qr'];

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * ດຶງຂໍ້ມູນ Inventory Items ທັງໝົດແບບ Paginated
     */
    public function getAllItems($filters = []) {
        $sql = "SELECT i.*,
                       c.category_name,
                       s.supplier_name,
                       creator.first_name as created_by_name,
                       updater.first_name as updated_by_name
                FROM inventory_items i
                LEFT JOIN asset_categories c ON i.category_id = c.id
                LEFT JOIN suppliers s ON i.supplier_id = s.id
                LEFT JOIN users creator ON i.created_by = creator.id
                LEFT JOIN users updater ON i.updated_by = updater.id
                WHERE 1=1";
        $params = [];

        // ກັ່ນຕອງຕາມລະຫັດສິນຄ້າ
        if (!empty($filters['item_code'])) {
            $sql .= " AND i.item_code LIKE ?";
            $params[] = '%' . $filters['item_code'] . '%';
        }

        // ກັ່ນຕອງຕາມ barcode
        if (!empty($filters['barcode'])) {
            $sql .= " AND i.barcode LIKE ?";
            $params[] = '%' . $filters['barcode'] . '%';
        }

        // ກັ່ນຕອງຕາມຊື່ສິນຄ້າ
        if (!empty($filters['item_name'])) {
            $sql .= " AND (i.item_name LIKE ? OR i.item_name_en LIKE ?)";
            $params[] = '%' . $filters['item_name'] . '%';
            $params[] = '%' . $filters['item_name'] . '%';
        }

        // ກັ່ນຕອງຕາມໝວດໝູ່
        if (!empty($filters['category_id'])) {
            $sql .= " AND i.category_id = ?";
            $params[] = $filters['category_id'];
        }

        // ກັ່ນຕອງຕາມຜູ້ສະໜອງ
        if (!empty($filters['supplier_id'])) {
            $sql .= " AND i.supplier_id = ?";
            $params[] = $filters['supplier_id'];
        }

        // ກັ່ນຕອງຕາມສະຖານະ
        if (isset($filters['is_active'])) {
            $sql .= " AND i.is_active = ?";
            $params[] = $filters['is_active'];
        }

        // ກັ່ນຕອງລາຄາ
        if (!empty($filters['min_price'])) {
            $sql .= " AND i.selling_price >= ?";
            $params[] = $filters['min_price'];
        }
        if (!empty($filters['max_price'])) {
            $sql .= " AND i.selling_price <= ?";
            $params[] = $filters['max_price'];
        }

        // ກັ່ນຕອງສິນຄ້າໃກ້ໝົດສະຕ໋ອກ
        if (!empty($filters['low_stock'])) {
            $sql .= " AND i.reorder_point IS NOT NULL AND i.reorder_point > 0";
        }

        // ຄົ້ນຫາຕາມຄຳສຳຄັນ
        if (!empty($filters['search'])) {
            $sql .= " AND (i.item_code LIKE ? OR i.barcode LIKE ? OR i.item_name LIKE ? 
                       OR i.item_name_en LIKE ? OR i.brand LIKE ? OR i.model LIKE ? 
                       OR i.specification LIKE ?)";
            $searchTerm = "%{$filters['search']}%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        // ການຈັດຮຽງ
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortOrder = isset($filters['sort_order']) && strtoupper($filters['sort_order']) === 'ASC' ? 'ASC' : 'DESC';
        $sql .= " ORDER BY i.{$sortBy} {$sortOrder}";

        // ການແບ່ງໜ້າ
        $page = isset($filters['page']) ? (int)$filters['page'] : 1;
        $limit = isset($filters['limit']) ? (int)$filters['limit'] : 20;
        $offset = ($page - 1) * $limit;
        
        $sql .= " LIMIT {$limit} OFFSET {$offset}";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $items = $stmt->fetchAll();

        // ນັບຈຳນວນທັງໝົດສຳລັບ pagination
        $countSql = "SELECT COUNT(*) as total FROM inventory_items i WHERE 1=1";
        $countParams = [];

        if (!empty($filters['category_id'])) {
            $countSql .= " AND i.category_id = ?";
            $countParams[] = $filters['category_id'];
        }

        if (!empty($filters['supplier_id'])) {
            $countSql .= " AND i.supplier_id = ?";
            $countParams[] = $filters['supplier_id'];
        }

        if (isset($filters['is_active'])) {
            $countSql .= " AND i.is_active = ?";
            $countParams[] = $filters['is_active'];
        }

        if (!empty($filters['search'])) {
            $countSql .= " AND (i.item_code LIKE ? OR i.barcode LIKE ? OR i.item_name LIKE ? 
                               OR i.item_name_en LIKE ? OR i.brand LIKE ? OR i.model LIKE ?)";
            $searchTerm = "%{$filters['search']}%";
            $countParams[] = $searchTerm;
            $countParams[] = $searchTerm;
            $countParams[] = $searchTerm;
            $countParams[] = $searchTerm;
            $countParams[] = $searchTerm;
            $countParams[] = $searchTerm;
        }

        $countStmt = $this->db->prepare($countSql);
        $countStmt->execute($countParams);
        $totalResult = $countStmt->fetch();
        $total = $totalResult ? (int)$totalResult['total'] : 0;

        return [
            'data' => $items,
            'total' => $total,
            'current_page' => $page,
            'per_page' => $limit,
            'last_page' => $total > 0 ? ceil($total / $limit) : 1,
            'from' => $offset + 1,
            'to' => min($offset + $limit, $total)
        ];
    }

    /**
     * ດຶງຂໍ້ມູນ Inventory Item ຕາມ ID
     */
    public function getById($id) {
        $stmt = $this->db->prepare(
            "SELECT i.*,
                    c.category_name,
                    s.supplier_name,
                    creator.first_name as created_by_name,
                    updater.first_name as updated_by_name
             FROM inventory_items i
             LEFT JOIN asset_categories c ON i.category_id = c.id
             LEFT JOIN suppliers s ON i.supplier_id = s.id
             LEFT JOIN users creator ON i.created_by = creator.id
             LEFT JOIN users updater ON i.updated_by = updater.id
             WHERE i.id = ?"
        );
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    /**
     * ດຶງຂໍ້ມູນ Inventory Item ຕາມລະຫັດ
     */
    public function getByCode($itemCode) {
        $stmt = $this->db->prepare("SELECT * FROM inventory_items WHERE item_code = ?");
        $stmt->execute([$itemCode]);
        return $stmt->fetch();
    }

 
    /**
     * ຄົ້ນຫາສິນຄ້າດ້ວຍ Barcode
     */
    public function getByBarcode($barcode) {
        try {
            $sql = "SELECT * FROM inventory_items WHERE barcode = ? AND deleted_at IS NULL";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$barcode]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting item by barcode: " . $e->getMessage());
            return null;
        }
    }

    /**
     * ດຶງຂໍ້ມູນສິນຄ້າທີ່ໃກ້ໝົດສະຕ໋ອກ
     */
    public function getLowStockItems() {
        $stmt = $this->db->prepare(
            "SELECT i.*, 
                    c.category_name,
                    s.supplier_name
             FROM inventory_items i
             LEFT JOIN asset_categories c ON i.category_id = c.id
             LEFT JOIN suppliers s ON i.supplier_id = s.id
             WHERE i.reorder_point IS NOT NULL 
               AND i.reorder_point > 0
               AND i.reorder_point >= i.minimum_stock
             ORDER BY (i.reorder_point - i.minimum_stock) ASC"
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * ດຶງຂໍ້ມູນສິນຄ້າທັງໝົດສຳລັບ dropdown
     */
    // public function getItemsForDropdown() {
    //     $stmt = $this->db->prepare(
    //         "SELECT id, item_code, barcode, item_name, item_name_en, selling_price 
    //          FROM inventory_items 
    //          WHERE is_active = 1 
    //          ORDER BY item_name ASC"
    //     );
    //     $stmt->execute();
    //     return $stmt->fetchAll();
    // }

 
    /**
     * ດຶງຂໍ້ມູນສິນຄ້າສຳລັບ dropdown
     */
    public function getItemsForDropdown($search = '') {
        try {
            $sql = "SELECT id, item_code, item_name, selling_price as unit_price 
                    FROM {$this->table} 
                    WHERE is_active = 1";  // ປ່ຽນຈາກ status ເປັນ is_active
            
            $params = [];
            
            if (!empty($search)) {
                $sql .= " AND (item_code LIKE ? OR item_name LIKE ?)";
                $params[] = "%$search%";
                $params[] = "%$search%";
            }
            
            $sql .= " ORDER BY item_name ASC LIMIT 50";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

                   // ຮັບປະກັນວ່າສົ່ງຄືນເປັນ array
            return is_array($result) ? $result : [];
            
        } catch (Exception $e) {
            error_log("Error in getItemsForDropdown: " . $e->getMessage());
            return [];
        }
    }


    /**
     * ສ້າງ Inventory Item ໃໝ່
     */
    public function create($data, $createdBy) {
        // ກວດສອບຂໍ້ມູນຊ້ຳ
        if (!empty($data['item_code'])) {
            $stmt = $this->db->prepare("SELECT id FROM inventory_items WHERE item_code = ?");
            $stmt->execute([$data['item_code']]);
            if ($stmt->fetch()) {
                return ['success' => false, 'message' => 'Item code already exists'];
            }
        }

        if (!empty($data['barcode'])) {
            $stmt = $this->db->prepare("SELECT id FROM inventory_items WHERE barcode = ?");
            $stmt->execute([$data['barcode']]);
            if ($stmt->fetch()) {
                return ['success' => false, 'message' => 'Barcode already exists'];
            }
        }

        // ສ້າງ item code ອັດຕະໂນມັດຖ້າບໍ່ມີ
        if (empty($data['item_code'])) {
            $data['item_code'] = $this->generateItemCode();
        }

        // ສ້າງ barcode ອັດຕະໂນມັດຖ້າບໍ່ມີ
        if (empty($data['barcode'])) {
            $data['barcode'] = $this->generateBarcode();
        }

        $fields = [];
        $placeholders = [];
        $values = [];

        $allowedFields = [
            'item_code', 'barcode', 'item_name', 'item_name_en', 'category_id',
            'description', 'brand', 'model', 'specification', 'purchase_price',
            'selling_price', 'supplier_id', 'reorder_point', 'minimum_stock',
            'maximum_stock', 'barcode_type', 'barcode_image_path', 'is_active'
        ];

        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $fields[] = $field;
                $placeholders[] = '?';
                $values[] = $data[$field];
            }
        }

        // ເພີ່ມ created_by ແລະ created_at
        $fields[] = 'created_by';
        $placeholders[] = '?';
        $values[] = $createdBy;

        $sql = "INSERT INTO inventory_items (" . implode(', ', $fields) . ", created_at) 
                VALUES (" . implode(', ', $placeholders) . ", NOW())";
        
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($values);
            
            $itemId = $this->db->lastInsertId();

            return [
                'success' => true,
                'item_id' => $itemId,
                'message' => 'Inventory item created successfully'
            ];
        } catch (PDOException $e) {
            error_log("Create inventory item failed: " . $e->getMessage());
            return ['success' => false, 'message' => 'Creation failed: ' . $e->getMessage()];
        }
    }

    /**
     * ອັບເດດຂໍ້ມູນ Inventory Item
     */
    public function update($id, $data, $updatedBy) {
        // ກວດສອບວ່າມີສິນຄ້າບໍ
        $item = $this->getById($id);
        if (!$item) {
            return ['success' => false, 'message' => 'Inventory item not found'];
        }

        // ກວດສອບຂໍ້ມູນຊ້ຳ (ຍົກເວັ້ນ ID ປັດຈຸບັນ)
        if (!empty($data['item_code']) && $data['item_code'] !== $item['item_code']) {
            $checkSql = "SELECT id FROM inventory_items WHERE item_code = ? AND id != ?";
            $checkStmt = $this->db->prepare($checkSql);
            $checkStmt->execute([$data['item_code'], $id]);
            if ($checkStmt->fetch()) {
                return ['success' => false, 'message' => 'Item code already exists'];
            }
        }

        if (!empty($data['barcode']) && $data['barcode'] !== $item['barcode']) {
            $checkSql = "SELECT id FROM inventory_items WHERE barcode = ? AND id != ?";
            $checkStmt = $this->db->prepare($checkSql);
            $checkStmt->execute([$data['barcode'], $id]);
            if ($checkStmt->fetch()) {
                return ['success' => false, 'message' => 'Barcode already exists'];
            }
        }

        $fields = [];
        $params = [];

        $allowedFields = [
            'item_code', 'barcode', 'item_name', 'item_name_en', 'category_id',
            'description', 'brand', 'model', 'specification', 'purchase_price',
            'selling_price', 'supplier_id', 'reorder_point', 'minimum_stock',
            'maximum_stock', 'barcode_type', 'barcode_image_path', 'is_active'
        ];

        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $fields[] = "{$field} = ?";
                $params[] = $data[$field];
            }
        }

        if (empty($fields)) {
            return ['success' => false, 'message' => 'No data to update'];
        }

        // ເພີ່ມ updated_by ແລະ updated_at
        $fields[] = "updated_by = ?";
        $fields[] = "updated_at = NOW()";
        $params[] = $updatedBy;
        $params[] = $id;

        $sql = "UPDATE inventory_items SET " . implode(', ', $fields) . " WHERE id = ?";
        
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            
            return ['success' => true, 'message' => 'Inventory item updated successfully'];
        } catch (PDOException $e) {
            error_log("Update inventory item failed: " . $e->getMessage());
            return ['success' => false, 'message' => 'Update failed: ' . $e->getMessage()];
        }
    }

    /**
     * ອັບເດດສະຖານະສິນຄ້າ
     */
    public function updateStatus($id, $isActive, $updatedBy) {
        try {
            $stmt = $this->db->prepare(
                "UPDATE inventory_items SET is_active = ?, updated_by = ?, updated_at = NOW() WHERE id = ?"
            );
            $stmt->execute([$isActive, $updatedBy, $id]);
            
            return ['success' => true, 'message' => 'Status updated successfully'];
        } catch (PDOException $e) {
            error_log("Update status failed: " . $e->getMessage());
            return ['success' => false, 'message' => 'Update failed: ' . $e->getMessage()];
        }
    }

    /**
     * ອັບເດດລາຄາຂາຍ
     */
    public function updateSellingPrice($id, $price, $updatedBy) {
        try {
            $stmt = $this->db->prepare(
                "UPDATE inventory_items SET selling_price = ?, updated_by = ?, updated_at = NOW() WHERE id = ?"
            );
            $stmt->execute([$price, $updatedBy, $id]);
            
            return ['success' => true, 'message' => 'Selling price updated successfully'];
        } catch (PDOException $e) {
            error_log("Update price failed: " . $e->getMessage());
            return ['success' => false, 'message' => 'Update failed: ' . $e->getMessage()];
        }
    }

    /**
     * ລຶບສິນຄ້າ (soft delete)
     */
    public function softDelete($id) {
        try {
            $stmt = $this->db->prepare("UPDATE inventory_items SET is_active = 0 WHERE id = ?");
            $stmt->execute([$id]);
            
            return ['success' => true, 'message' => 'Item deactivated successfully'];
        } catch (PDOException $e) {
            error_log("Soft delete failed: " . $e->getMessage());
            return ['success' => false, 'message' => 'Delete failed: ' . $e->getMessage()];
        }
    }

    /**
     * ລຶບສິນຄ້າແບບຖາວອນ
     */
    public function hardDelete($id) {
        // ກວດສອບວ່າມີການໃຊ້ງານຢູ່ບໍ (ໃນ purchase_orders, sales_orders, etc.)
        $checkSql = "SELECT COUNT(*) as count FROM purchase_order_items WHERE item_id = ?";
        $checkStmt = $this->db->prepare($checkSql);
        $checkStmt->execute([$id]);
        $result = $checkStmt->fetch();
        
        if ($result && $result['count'] > 0) {
            return ['success' => false, 'message' => 'Cannot delete item with existing transactions'];
        }

        try {
            $stmt = $this->db->prepare("DELETE FROM inventory_items WHERE id = ?");
            $stmt->execute([$id]);
            
            return ['success' => true, 'message' => 'Item deleted permanently'];
        } catch (PDOException $e) {
            error_log("Hard delete failed: " . $e->getMessage());
            return ['success' => false, 'message' => 'Delete failed: ' . $e->getMessage()];
        }
    }

    /**
     * ສ້າງລະຫັດສິນຄ້າອັດຕະໂນມັດ
     */
    private function generateItemCode() {
        $prefix = 'ITEM';
        $year = date('Y');
        $month = date('m');
        
        $stmt = $this->db->query("SELECT COUNT(*) as count FROM inventory_items WHERE YEAR(created_at) = YEAR(NOW())");
        $count = $stmt->fetch()['count'] + 1;
        
        return $prefix . $year . $month . str_pad($count, 5, '0', STR_PAD_LEFT);
    }

    /**
     * ສ້າງ barcode ອັດຕະໂນມັດ
     */
    private function generateBarcode() {
        $prefix = 'BC';
        $timestamp = time();
        $random = rand(1000, 9999);
        
        return $prefix . $timestamp . $random;
    }

    /**
     * ດຶງສະຖິຕິສິນຄ້າ
     */
    public function getItemStats() {
        $stats = [];

        // ຈຳນວນສິນຄ້າທັງໝົດ
        $stmt = $this->db->query("SELECT COUNT(*) as total FROM inventory_items");
        $stats['total_items'] = $stmt->fetch()['total'];

        // ຈຳນວນສິນຄ້າທີ່ເປີດໃຊ້ງານ
        $stmt = $this->db->query("SELECT COUNT(*) as active FROM inventory_items WHERE is_active = 1");
        $stats['active_items'] = $stmt->fetch()['active'];

        // ມູນຄ່າສິນຄ້າທັງໝົດ (ຕົ້ນທຶນ)
        $stmt = $this->db->query("SELECT SUM(purchase_price) as total_cost FROM inventory_items WHERE is_active = 1");
        $stats['total_cost'] = $stmt->fetch()['total_cost'] ?? 0;

        // ມູນຄ່າສິນຄ້າທັງໝົດ (ລາຄາຂາຍ)
        $stmt = $this->db->query("SELECT SUM(selling_price) as total_value FROM inventory_items WHERE is_active = 1");
        $stats['total_value'] = $stmt->fetch()['total_value'] ?? 0;

        // ຈຳນວນສິນຄ້າໃກ້ໝົດສະຕ໋ອກ
        $stmt = $this->db->query(
            "SELECT COUNT(*) as low_stock FROM inventory_items 
             WHERE is_active = 1 AND reorder_point IS NOT NULL 
             AND reorder_point > 0 AND reorder_point >= minimum_stock"
        );
        $stats['low_stock_items'] = $stmt->fetch()['low_stock'];

        // ສິນຄ້າຕາມໝວດໝູ່
        $categoryStmt = $this->db->query(
            "SELECT c.category_name, COUNT(*) as count 
             FROM inventory_items i
             LEFT JOIN asset_categories c ON i.category_id = c.id
             WHERE i.is_active = 1
             GROUP BY i.category_id"
        );
        $categoryStats = [];
        while ($row = $categoryStmt->fetch()) {
            $categoryStats[] = [
                'category' => $row['category_name'],
                'count' => $row['count']
            ];
        }
        $stats['by_category'] = $categoryStats;

        return $stats;
    }

    /**
     * ຄົ້ນຫາສິນຄ້າ
     */
    public function searchItems($keyword) {
        $sql = "SELECT i.*,
                       c.category_name,
                       s.supplier_name
                FROM inventory_items i
                LEFT JOIN asset_categories c ON i.category_id = c.id
                LEFT JOIN suppliers s ON i.supplier_id = s.id
                WHERE i.is_active = 1 
                  AND (i.item_code LIKE ? OR i.barcode LIKE ? OR i.item_name LIKE ? 
                       OR i.item_name_en LIKE ? OR i.brand LIKE ? OR i.model LIKE ?)
                ORDER BY i.item_name ASC
                LIMIT 50";
        
        $searchTerm = "%{$keyword}%";
        $params = [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm];

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * ອັບເດດ barcode image path
     */
    public function updateBarcodeImage($id, $imagePath) {
        try {
            $stmt = $this->db->prepare(
                "UPDATE inventory_items SET barcode_image_path = ? WHERE id = ?"
            );
            $stmt->execute([$imagePath, $id]);
            
            return ['success' => true, 'message' => 'Barcode image updated successfully'];
        } catch (PDOException $e) {
            error_log("Update barcode image failed: " . $e->getMessage());
            return ['success' => false, 'message' => 'Update failed: ' . $e->getMessage()];
        }
    }


    /**
     * ດຶງຂໍ້ມູນສິນຄ້າສຳລັບການຂາຍ ພ້ອມຈຳນວນຄົງເຫຼືອ
     */
    public function getItemsWithStockForSale($search = '') {
        try {
            $sql = "SELECT 
                        ii.id,
                        ii.item_code,
                        ii.barcode,
                        ii.item_name,
                        ii.selling_price as unit_price,
                        COALESCE(is.total_quantity, 0) as stock_quantity
                    FROM {$this->table} ii
                    LEFT JOIN (
                        SELECT 
                            item_id, 
                            SUM(quantity) as total_quantity 
                        FROM inventory_stock 
                        GROUP BY item_id
                    ) is ON ii.id = is.item_id
                    WHERE ii.is_active = 1";  // ປ່ຽນຈາກ status ເປັນ is_active
            
            $params = [];
            
            // ກັ່ນຕອງສະເພາະສິນຄ້າທີ່ມີ stock > 0
            $sql .= " AND COALESCE(is.total_quantity, 0) > 0";
            
            if (!empty($search)) {
                $sql .= " AND (ii.item_code LIKE ? OR ii.item_name LIKE ? OR ii.barcode LIKE ?)";
                $params[] = "%$search%";
                $params[] = "%$search%";
                $params[] = "%$search%";
            }
            
            $sql .= " ORDER BY ii.item_name ASC LIMIT 100";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("Error in getItemsWithStockForSale: " . $e->getMessage());
            return [];
        }
    }

    // ເພີ່ມໃນ InventoryItem.php
public function getLatestItemCode() {
    try {
        $stmt = $this->db->query("SELECT item_code FROM inventory_items ORDER BY id DESC LIMIT 1");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ? $result['item_code'] : 'ITEM00000';
    } catch (Exception $e) {
        return 'ITEM00000';
    }
}

}
?>