<?php
// src/models/Asset.php
require_once __DIR__ . '/../config/database.php';

class Asset {
    private $db;
    private $allowedStatus = ['available', 'in_use', 'maintenance', 'disposed', 'lost', 'reserved','sold'];
    private $allowedCondition = ['new', 'good', 'fair', 'poor', 'damaged', 'obsolete'];

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * ດຶງຂໍ້ມູນຊັບສິນທັງໝົດແບບ Paginated
     */
    public function getAllAssets($filters = [], $userRole = null, $userDepartmentId = null, $userId = null) {
        $sql = "SELECT a.*,
                       c1.category_name as category_level1_name,
                       c2.category_name as category_level2_name,
                       c3.category_name as category_level3_name,
                       cat.category_name as category_name,
                       comp.company_name,
                       dept.department_name,
                       u.first_name as current_user_first_name,
                       u.last_name as current_user_last_name,
                       loc.location_name,
                       sup.supplier_name,
                       creator.first_name as created_by_name,
                       updater.first_name as updated_by_name
                FROM assets a
                LEFT JOIN asset_categories c1 ON a.category_level1_id = c1.id
                LEFT JOIN asset_categories c2 ON a.category_level2_id = c2.id
                LEFT JOIN asset_categories c3 ON a.category_level3_id = c3.id
                LEFT JOIN asset_categories cat ON a.category_id = cat.id
                LEFT JOIN companies comp ON a.company_id = comp.id
                LEFT JOIN departments dept ON a.department_id = dept.id
                LEFT JOIN users u ON a.current_user_id = u.id
                LEFT JOIN locations loc ON a.location_id = loc.id
                LEFT JOIN suppliers sup ON a.supplier_id = sup.id
                LEFT JOIN users creator ON a.created_by = creator.id
                LEFT JOIN users updater ON a.updated_by = updater.id
                WHERE 1=1";
        $params = [];

        // ກວດສອບສິດຕາມບົດບາດ
        if ($userRole === 'employee') {
            // ພະນັກງານເບິ່ງໄດ້ສະເພາະຊັບສິນທີ່ຕົນເອງຖືຄອງ
            $sql .= " AND a.current_user_id = ?";
            $params[] = $userId;
        } elseif ($userRole === 'department_head' || $userRole === 'manager') {
            // ຫົວໜ້າພະແນກເບິ່ງໄດ້ສະເພາະຊັບສິນໃນພະແນກຕົນເອງ
            $sql .= " AND a.department_id = ?";
            $params[] = $userDepartmentId;
        }

        // ກັ່ນຕອງຕາມບໍລິສັດ
        if (!empty($filters['company_id'])) {
            $sql .= " AND a.company_id = ?";
            $params[] = $filters['company_id'];
        }

        // ກັ່ນຕອງຕາມພະແນກ
        if (!empty($filters['department_id'])) {
            $sql .= " AND a.department_id = ?";
            $params[] = $filters['department_id'];
        }

        // ກັ່ນຕອງຕາມໝວດໝູ່
        if (!empty($filters['category_id'])) {
            $sql .= " AND a.category_id = ?";
            $params[] = $filters['category_id'];
        }

        // ກັ່ນຕອງຕາມສະຖານະ
        if (!empty($filters['status'])) {
            $sql .= " AND a.status = ?";
            $params[] = $filters['status'];
        }

        // ກັ່ນຕອງຕາມສະພາບ
        if (!empty($filters['asset_condition'])) {
            $sql .= " AND a.asset_condition = ?";
            $params[] = $filters['asset_condition'];
        }

        // ກັ່ນຕອງຕາມຜູ້ຖືຄອງ
        if (!empty($filters['current_user_id'])) {
            $sql .= " AND a.current_user_id = ?";
            $params[] = $filters['current_user_id'];
        }

        // ກັ່ນຕອງຕາມສະຖານທີ່
        if (!empty($filters['location_id'])) {
            $sql .= " AND a.location_id = ?";
            $params[] = $filters['location_id'];
        }

        // ກັ່ນຕອງຕາມການຮັບປະກັນ
        if (!empty($filters['has_warranty'])) {
            $sql .= " AND a.has_warranty = ?";
            $params[] = $filters['has_warranty'];
        }

        // ຄົ້ນຫາຕາມຄຳສຳຄັນ
        if (!empty($filters['search'])) {
            $sql .= " AND (a.asset_code LIKE ? OR a.asset_name LIKE ? OR a.asset_name_en LIKE ? 
                       OR a.serial_number LIKE ? OR a.barcode LIKE ? OR a.rfid_tag LIKE ? 
                       OR a.model LIKE ? OR a.brand LIKE ?)";
            $searchTerm = "%{$filters['search']}%";
            $params[] = $searchTerm;
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
        $sql .= " ORDER BY a.{$sortBy} {$sortOrder}";

        // ການແບ່ງໜ້າ
        $page = isset($filters['page']) ? (int)$filters['page'] : 1;
        $limit = isset($filters['limit']) ? (int)$filters['limit'] : 20;
        $offset = ($page - 1) * $limit;
        
        $sql .= " LIMIT {$limit} OFFSET {$offset}";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $assets = $stmt->fetchAll();

        // ນັບຈຳນວນທັງໝົດສຳລັບ pagination
        $countSql = "SELECT COUNT(*) as total FROM assets a WHERE 1=1";
        $countParams = [];

        if ($userRole === 'employee') {
            $countSql .= " AND a.current_user_id = ?";
            $countParams[] = $userId;
        } elseif ($userRole === 'department_head' || $userRole === 'manager') {
            $countSql .= " AND a.department_id = ?";
            $countParams[] = $userDepartmentId;
        }

        // ເພີ່ມເງື່ອນໄຂການກັ່ນຕອງອື່ນໆ
        if (!empty($filters['company_id'])) {
            $countSql .= " AND a.company_id = ?";
            $countParams[] = $filters['company_id'];
        }

        if (!empty($filters['department_id'])) {
            $countSql .= " AND a.department_id = ?";
            $countParams[] = $filters['department_id'];
        }

        if (!empty($filters['category_id'])) {
            $countSql .= " AND a.category_id = ?";
            $countParams[] = $filters['category_id'];
        }

        if (!empty($filters['status'])) {
            $countSql .= " AND a.status = ?";
            $countParams[] = $filters['status'];
        }

        if (!empty($filters['search'])) {
            $countSql .= " AND (a.asset_code LIKE ? OR a.asset_name LIKE ? OR a.asset_name_en LIKE ? 
                               OR a.serial_number LIKE ? OR a.barcode LIKE ? OR a.rfid_tag LIKE ?)";
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
            'data' => $assets,
            'total' => $total,
            'current_page' => $page,
            'per_page' => $limit,
            'last_page' => $total > 0 ? ceil($total / $limit) : 1,
            'from' => $offset + 1,
            'to' => min($offset + $limit, $total)
        ];
    }

    /**
     * ດຶງຂໍ້ມູນຊັບສິນຕາມ ID
     */
    public function getById($id) {
        $stmt = $this->db->prepare(
            "SELECT a.*,
                    c1.category_name as category_level1_name,
                    c2.category_name as category_level2_name,
                    c3.category_name as category_level3_name,
                    cat.category_name as category_name,
                    comp.company_name,
                    dept.department_name,
                    u.first_name as current_user_first_name,
                    u.last_name as current_user_last_name,
                    loc.location_name,
                    sup.supplier_name,
                    creator.first_name as created_by_name,
                    updater.first_name as updated_by_name,
                    verifier.first_name as verified_by_name
             FROM assets a
             LEFT JOIN asset_categories c1 ON a.category_level1_id = c1.id
             LEFT JOIN asset_categories c2 ON a.category_level2_id = c2.id
             LEFT JOIN asset_categories c3 ON a.category_level3_id = c3.id
             LEFT JOIN asset_categories cat ON a.category_id = cat.id
             LEFT JOIN companies comp ON a.company_id = comp.id
             LEFT JOIN departments dept ON a.department_id = dept.id
             LEFT JOIN users u ON a.current_user_id = u.id
             LEFT JOIN locations loc ON a.location_id = loc.id
             LEFT JOIN suppliers sup ON a.supplier_id = sup.id
             LEFT JOIN users creator ON a.created_by = creator.id
             LEFT JOIN users updater ON a.updated_by = updater.id
             LEFT JOIN users verifier ON a.verified_by = verifier.id
             WHERE a.id = ?"
        );
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    /**
     * ດຶງຂໍ້ມູນຊັບສິນຕາມລະຫັດ
     */
    public function getByCode($assetCode) {
        $stmt = $this->db->prepare("SELECT * FROM assets WHERE asset_code = ?");
        $stmt->execute([$assetCode]);
        return $stmt->fetch();
    }

    /**
     * ດຶງຂໍ້ມູນຊັບສິນຕາມ barcode
     */
    public function getByBarcode($barcode) {
        $stmt = $this->db->prepare("SELECT * FROM assets WHERE barcode = ?");
        $stmt->execute([$barcode]);
        return $stmt->fetch();
    }

    /**
     * ດຶງຂໍ້ມູນຊັບສິນຕາມ RFID
     */
    public function getByRFID($rfidTag) {
        $stmt = $this->db->prepare("SELECT * FROM assets WHERE rfid_tag = ?");
        $stmt->execute([$rfidTag]);
        return $stmt->fetch();
    }

    /**
     * ດຶງຂໍ້ມູນຊັບສິນຕາມ serial number
     */
    public function getBySerialNumber($serialNumber) {
        $stmt = $this->db->prepare("SELECT * FROM assets WHERE serial_number = ?");
        $stmt->execute([$serialNumber]);
        return $stmt->fetch();
    }

    /**
     * ດຶງຂໍ້ມູນຊັບສິນຕາມຜູ້ຖືຄອງ
     */
    public function getByUserId($userId) {
        $stmt = $this->db->prepare(
            "SELECT a.*, 
                    cat.category_name
             FROM assets a
             LEFT JOIN asset_categories cat ON a.category_id = cat.id
             WHERE a.current_user_id = ? AND a.status = 'in_use'
             ORDER BY a.created_at DESC"
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    /**
     * ດຶງຂໍ້ມູນຊັບສິນຕາມພະແນກ
     */
    public function getByDepartmentId($departmentId) {
        $stmt = $this->db->prepare(
            "SELECT a.*, 
                    cat.category_name,
                    u.first_name as user_first_name,
                    u.last_name as user_last_name
             FROM assets a
             LEFT JOIN asset_categories cat ON a.category_id = cat.id
             LEFT JOIN users u ON a.current_user_id = u.id
             WHERE a.department_id = ?
             ORDER BY a.created_at DESC"
        );
        $stmt->execute([$departmentId]);
        return $stmt->fetchAll();
    }

    /**
     * ສ້າງຊັບສິນໃໝ່
     */
    public function create($data, $createdBy) {
        // ກວດສອບຂໍ້ມູນຊ້ຳ
        if (!empty($data['asset_code'])) {
            $stmt = $this->db->prepare("SELECT id FROM assets WHERE asset_code = ?");
            $stmt->execute([$data['asset_code']]);
            if ($stmt->fetch()) {
                return ['success' => false, 'message' => 'Asset code already exists'];
            }
        }

        if (!empty($data['barcode'])) {
            $stmt = $this->db->prepare("SELECT id FROM assets WHERE barcode = ?");
            $stmt->execute([$data['barcode']]);
            if ($stmt->fetch()) {
                return ['success' => false, 'message' => 'Barcode already exists'];
            }
        }

        if (!empty($data['rfid_tag'])) {
            $stmt = $this->db->prepare("SELECT id FROM assets WHERE rfid_tag = ?");
            $stmt->execute([$data['rfid_tag']]);
            if ($stmt->fetch()) {
                return ['success' => false, 'message' => 'RFID tag already exists'];
            }
        }

        if (!empty($data['serial_number'])) {
            $stmt = $this->db->prepare("SELECT id FROM assets WHERE serial_number = ?");
            $stmt->execute([$data['serial_number']]);
            if ($stmt->fetch()) {
                return ['success' => false, 'message' => 'Serial number already exists'];
            }
        }

        // ກວດສອບຄວາມຖືກຕ້ອງຂອງຄ່າ
        if (!empty($data['status']) && !in_array($data['status'], $this->allowedStatus)) {
            return ['success' => false, 'message' => 'Invalid status value'];
        }

        if (!empty($data['asset_condition']) && !in_array($data['asset_condition'], $this->allowedCondition)) {
            return ['success' => false, 'message' => 'Invalid condition value'];
        }

        // ສ້າງ asset code ອັດຕະໂນມັດຖ້າບໍ່ມີ
        if (empty($data['asset_code'])) {
            $data['asset_code'] = $this->generateAssetCode();
        }

        // ຄຳນວນ depreciation ຖ້າມີ
        if (!empty($data['purchase_cost']) && !empty($data['salvage_value']) && !empty($data['useful_life_years'])) {
            $data['depreciation_rate'] = $this->calculateDepreciationRate($data);
        }

        $fields = [];
        $placeholders = [];
        $values = [];

        $allowedFields = [
            'asset_code', 'asset_name', 'asset_name_en', 'old_asset_code',
            'category_level1_id', 'category_level2_id', 'category_level3_id', 'category_id',
            'description', 'brand', 'model', 'serial_number', 'manufacturing_year',
            'country_of_origin', 'color', 'size_dimensions', 'weight',
            'purchase_date', 'purchase_cost', 'purchase_cost_usd', 'exchange_rate',
            'supplier_id', 'purchase_invoice_no', 'purchase_order_no', 'payment_status',
            'warranty_provider', 'warranty_expiry', 'warranty_terms',
            'insurance_policy_no', 'insurance_expiry', 'insurance_provider',
            'company_id', 'department_id', 'current_user_id', 'location_id',
            'building', 'floor', 'room', 'exact_location', 'gps_coordinates',
            'status', 'asset_condition', 'condition_notes',
            'last_maintenance_date', 'next_maintenance_date', 'maintenance_frequency_days',
            'current_value', 'salvage_value', 'accumulated_depreciation',
            'depreciation_start_date', 'depreciation_end_date', 'last_depreciation_date',
            'depreciation_method', 'useful_life_years', 'useful_life_months', 'depreciation_rate',
            'has_warranty', 'warranty_document_path',
            'has_manual', 'manual_document_path',
            'has_invoice', 'invoice_document_path',
            'has_certificate', 'certificate_document_path',
            'asset_image_path', 'additional_documents',
            'qr_code', 'qr_code_image_path',
            'barcode', 'barcode_image_path', 'rfid_tag',
            'asset_label_printed', 'last_printed_date',
            'is_active', 'notes', 'custom_fields'
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

        $sql = "INSERT INTO assets (" . implode(', ', $fields) . ", created_at) 
                VALUES (" . implode(', ', $placeholders) . ", NOW())";
        
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($values);
            
            $assetId = $this->db->lastInsertId();

            // ສ້າງ barcode ອັດຕະໂນມັດຖ້າບໍ່ມີ
            if (empty($data['barcode'])) {
                $this->generateBarcode($assetId, $data['asset_code']);
            }

            // ສ້າງ QR code ອັດຕະໂນມັດ
            $this->generateQRCode($assetId, $data['asset_code']);

            return [
                'success' => true,
                'asset_id' => $assetId,
                'message' => 'Asset created successfully'
            ];
        } catch (PDOException $e) {
            error_log("Create asset failed: " . $e->getMessage());
            return ['success' => false, 'message' => 'Creation failed: ' . $e->getMessage()];
        }
    }

    /**
     * ອັບເດດຂໍ້ມູນຊັບສິນ
     */
    public function update($id, $data, $updatedBy) {
        // ກວດສອບວ່າມີຊັບສິນບໍ
        $asset = $this->getById($id);
        if (!$asset) {
            return ['success' => false, 'message' => 'Asset not found'];
        }

        // ກວດສອບຂໍ້ມູນຊ້ຳ (ຍົກເວັ້ນ ID ປັດຈຸບັນ)
        if (!empty($data['asset_code'])) {
            $checkSql = "SELECT id FROM assets WHERE asset_code = ? AND id != ?";
            $checkStmt = $this->db->prepare($checkSql);
            $checkStmt->execute([$data['asset_code'], $id]);
            if ($checkStmt->fetch()) {
                return ['success' => false, 'message' => 'Asset code already exists'];
            }
        }

        if (!empty($data['barcode'])) {
            $checkSql = "SELECT id FROM assets WHERE barcode = ? AND id != ?";
            $checkStmt = $this->db->prepare($checkSql);
            $checkStmt->execute([$data['barcode'], $id]);
            if ($checkStmt->fetch()) {
                return ['success' => false, 'message' => 'Barcode already exists'];
            }
        }

        if (!empty($data['rfid_tag'])) {
            $checkSql = "SELECT id FROM assets WHERE rfid_tag = ? AND id != ?";
            $checkStmt = $this->db->prepare($checkSql);
            $checkStmt->execute([$data['rfid_tag'], $id]);
            if ($checkStmt->fetch()) {
                return ['success' => false, 'message' => 'RFID tag already exists'];
            }
        }

        if (!empty($data['serial_number'])) {
            $checkSql = "SELECT id FROM assets WHERE serial_number = ? AND id != ?";
            $checkStmt = $this->db->prepare($checkSql);
            $checkStmt->execute([$data['serial_number'], $id]);
            if ($checkStmt->fetch()) {
                return ['success' => false, 'message' => 'Serial number already exists'];
            }
        }

        $fields = [];
        $params = [];

        $allowedFields = [
            'asset_code', 'asset_name', 'asset_name_en', 'old_asset_code',
            'category_level1_id', 'category_level2_id', 'category_level3_id', 'category_id',
            'description', 'brand', 'model', 'serial_number', 'manufacturing_year',
            'country_of_origin', 'color', 'size_dimensions', 'weight',
            'purchase_date', 'purchase_cost', 'purchase_cost_usd', 'exchange_rate',
            'supplier_id', 'purchase_invoice_no', 'purchase_order_no', 'payment_status',
            'warranty_provider', 'warranty_expiry', 'warranty_terms',
            'insurance_policy_no', 'insurance_expiry', 'insurance_provider',
            'company_id', 'department_id', 'current_user_id', 'location_id',
            'building', 'floor', 'room', 'exact_location', 'gps_coordinates',
            'status', 'asset_condition', 'condition_notes',
            'last_maintenance_date', 'next_maintenance_date', 'maintenance_frequency_days',
            'current_value', 'salvage_value', 'accumulated_depreciation',
            'depreciation_start_date', 'depreciation_end_date', 'last_depreciation_date',
            'depreciation_method', 'useful_life_years', 'useful_life_months', 'depreciation_rate',
            'has_warranty', 'warranty_document_path',
            'has_manual', 'manual_document_path',
            'has_invoice', 'invoice_document_path',
            'has_certificate', 'certificate_document_path',
            'asset_image_path', 'additional_documents',
            'qr_code', 'qr_code_image_path',
            'barcode', 'barcode_image_path', 'rfid_tag',
            'asset_label_printed', 'last_printed_date',
            'is_active', 'notes', 'custom_fields'
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

        $sql = "UPDATE assets SET " . implode(', ', $fields) . " WHERE id = ?";
        
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            
            return ['success' => true, 'message' => 'Asset updated successfully'];
        } catch (PDOException $e) {
            error_log("Update asset failed: " . $e->getMessage());
            return ['success' => false, 'message' => 'Update failed: ' . $e->getMessage()];
        }
    }

    /**
     * ອັບເດດສະຖານະຊັບສິນ
     */
    public function updateStatus($id, $status, $updatedBy) {
        if (!in_array($status, $this->allowedStatus)) {
            return ['success' => false, 'message' => 'Invalid status value'];
        }

        try {
            $stmt = $this->db->prepare(
                "UPDATE assets SET status = ?, updated_by = ?, updated_at = NOW() WHERE id = ?"
            );
            $stmt->execute([$status, $updatedBy, $id]);
            
            return ['success' => true, 'message' => 'Status updated successfully'];
        } catch (PDOException $e) {
            error_log("Update status failed: " . $e->getMessage());
            return ['success' => false, 'message' => 'Update failed: ' . $e->getMessage()];
        }
    }

    /**
     * ອັບເດດສະພາບຊັບສິນ
     */
    public function updateCondition($id, $condition, $notes, $updatedBy) {
        if (!in_array($condition, $this->allowedCondition)) {
            return ['success' => false, 'message' => 'Invalid condition value'];
        }

        try {
            $stmt = $this->db->prepare(
                "UPDATE assets SET asset_condition = ?, condition_notes = ?, updated_by = ?, updated_at = NOW() WHERE id = ?"
            );
            $stmt->execute([$condition, $notes, $updatedBy, $id]);
            
            return ['success' => true, 'message' => 'Condition updated successfully'];
        } catch (PDOException $e) {
            error_log("Update condition failed: " . $e->getMessage());
            return ['success' => false, 'message' => 'Update failed: ' . $e->getMessage()];
        }
    }

    /**
     * ອັບເດດຜູ້ຖືຄອງຊັບສິນ
     */
    public function updateCurrentUser($id, $userId, $updatedBy) {
        try {
            $stmt = $this->db->prepare(
                "UPDATE assets SET current_user_id = ?, updated_by = ?, updated_at = NOW() WHERE id = ?"
            );
            $stmt->execute([$userId, $updatedBy, $id]);
            
            return ['success' => true, 'message' => 'Current user updated successfully'];
        } catch (PDOException $e) {
            error_log("Update current user failed: " . $e->getMessage());
            return ['success' => false, 'message' => 'Update failed: ' . $e->getMessage()];
        }
    }

    /**
     * ອັບເດດສະຖານທີ່ຊັບສິນ
     */
    public function updateLocation($id, $data, $updatedBy) {
        $fields = [];
        $params = [];

        $locationFields = ['location_id', 'building', 'floor', 'room', 'exact_location', 'gps_coordinates'];
        
        foreach ($locationFields as $field) {
            if (isset($data[$field])) {
                $fields[] = "{$field} = ?";
                $params[] = $data[$field];
            }
        }

        if (empty($fields)) {
            return ['success' => false, 'message' => 'No location data to update'];
        }

        $fields[] = "updated_by = ?";
        $fields[] = "updated_at = NOW()";
        $params[] = $updatedBy;
        $params[] = $id;

        $sql = "UPDATE assets SET " . implode(', ', $fields) . " WHERE id = ?";
        
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            
            return ['success' => true, 'message' => 'Location updated successfully'];
        } catch (PDOException $e) {
            error_log("Update location failed: " . $e->getMessage());
            return ['success' => false, 'message' => 'Update failed: ' . $e->getMessage()];
        }
    }

    /**
     * ອັບເດດການຮັບປະກັນ
     */
    public function updateWarranty($id, $data, $updatedBy) {
        $fields = [];
        $params = [];

        $warrantyFields = ['warranty_provider', 'warranty_expiry', 'warranty_terms', 'has_warranty'];
        
        foreach ($warrantyFields as $field) {
            if (isset($data[$field])) {
                $fields[] = "{$field} = ?";
                $params[] = $data[$field];
            }
        }

        if (empty($fields)) {
            return ['success' => false, 'message' => 'No warranty data to update'];
        }

        $fields[] = "updated_by = ?";
        $fields[] = "updated_at = NOW()";
        $params[] = $updatedBy;
        $params[] = $id;

        $sql = "UPDATE assets SET " . implode(', ', $fields) . " WHERE id = ?";
        
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            
            return ['success' => true, 'message' => 'Warranty updated successfully'];
        } catch (PDOException $e) {
            error_log("Update warranty failed: " . $e->getMessage());
            return ['success' => false, 'message' => 'Update failed: ' . $e->getMessage()];
        }
    }

    /**
     * ກວດສອບຊັບສິນ
     */
    public function verify($id, $verifiedBy, $notes = null) {
        try {
            $stmt = $this->db->prepare(
                "UPDATE assets SET verified_by = ?, verified_at = NOW(), verification_notes = ? WHERE id = ?"
            );
            $stmt->execute([$verifiedBy, $notes, $id]);
            
            return ['success' => true, 'message' => 'Asset verified successfully'];
        } catch (PDOException $e) {
            error_log("Verify asset failed: " . $e->getMessage());
            return ['success' => false, 'message' => 'Verification failed: ' . $e->getMessage()];
        }
    }

    /**
     * ລຶບຊັບສິນ (soft delete)
     */
    public function softDelete($id) {
        try {
            $stmt = $this->db->prepare("UPDATE assets SET is_active = 0 WHERE id = ?");
            $stmt->execute([$id]);
            
            return ['success' => true, 'message' => 'Asset deactivated successfully'];
        } catch (PDOException $e) {
            error_log("Soft delete failed: " . $e->getMessage());
            return ['success' => false, 'message' => 'Delete failed: ' . $e->getMessage()];
        }
    }

    /**
     * ສ້າງລະຫັດຊັບສິນອັດຕະໂນມັດ
     */
    // private function generateAssetCode() {
    //     $prefix = 'AST';
    //     $year = date('Y');
    //     $month = date('m');
        
    //     $stmt = $this->db->query("SELECT COUNT(*) as count FROM assets WHERE YEAR(created_at) = YEAR(NOW())");
    //     $count = $stmt->fetch()['count'] + 1;
        
    //     return $prefix . $year . $month . str_pad($count, 5, '0', STR_PAD_LEFT);
    // }


    /**
     * ສ້າງລະຫັດຊັບສິນອັດຕະໂນມັດ (ມີຢູ່ແລ້ວ)
     */
    private function generateAssetCode() {
        $prefix = 'AST';
        $year = date('Y');
        $month = date('m');
        
        $stmt = $this->db->query("SELECT COUNT(*) as count FROM assets WHERE YEAR(created_at) = YEAR(NOW())");
        $count = $stmt->fetch()['count'] + 1;
        
        return $prefix . $year . $month . str_pad($count, 5, '0', STR_PAD_LEFT);
    }

    /**
     * ສ້າງ barcode ອັດຕະໂນມັດ
     */
    private function generateBarcode($assetId, $assetCode) {
        // ສ້າງ barcode ແບບງ່າຍໆ
        $barcode = 'BC' . date('Ymd') . str_pad($assetId, 8, '0', STR_PAD_LEFT);
        
        $stmt = $this->db->prepare("UPDATE assets SET barcode = ? WHERE id = ?");
        $stmt->execute([$barcode, $assetId]);
        
        // TODO: ສ້າງ barcode image ຈິງໆ ໂດຍໃຊ້ library ເຊັ່ນ barcode generator
    }

    /**
     * ສ້າງ QR code ອັດຕະໂນມັດ
     */
    private function generateQRCode($assetId, $assetCode) {
        // ສ້າງ QR code ແບບງ່າຍໆ
        $qrCode = 'QR' . date('Ymd') . str_pad($assetId, 8, '0', STR_PAD_LEFT);
        
        $stmt = $this->db->prepare("UPDATE assets SET qr_code = ? WHERE id = ?");
        $stmt->execute([$qrCode, $assetId]);
        
        // TODO: ສ້າງ QR code image ຈິງໆ ໂດຍໃຊ້ library ເຊັ່ນ QR code generator
    }

    /**
     * ຄຳນວນອັດຕາເສື່ອມລາຄາ
     */
    private function calculateDepreciationRate($data) {
        if ($data['depreciation_method'] === 'straight_line') {
            return (1 / $data['useful_life_years']) * 100;
        }
        return null;
    }

    /**
     * ດຶງສະຖິຕິຊັບສິນ
     */
    public function getAssetStats($userRole = null, $userDepartmentId = null, $userId = null) {
        $stats = [];

        // ຈຳນວນຊັບສິນທັງໝົດ
        $sql = "SELECT COUNT(*) as total FROM assets a WHERE 1=1";
        $params = [];

        if ($userRole === 'employee') {
            $sql .= " AND a.current_user_id = ?";
            $params[] = $userId;
        } elseif ($userRole === 'department_head' || $userRole === 'manager') {
            $sql .= " AND a.department_id = ?";
            $params[] = $userDepartmentId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $stats['total_assets'] = $stmt->fetch()['total'];

        // ຈຳນວນຊັບສິນແຕ່ລະສະຖານະ
        $statusSql = "SELECT status, COUNT(*) as count FROM assets a WHERE 1=1";
        if ($userRole === 'employee') {
            $statusSql .= " AND a.current_user_id = ?";
        } elseif ($userRole === 'department_head' || $userRole === 'manager') {
            $statusSql .= " AND a.department_id = ?";
        }
        $statusSql .= " GROUP BY status";

        $statusStmt = $this->db->prepare($statusSql);
        $statusStmt->execute($params);
        
        $statusStats = [];
        while ($row = $statusStmt->fetch()) {
            $statusStats[$row['status']] = $row['count'];
        }
        $stats['status_stats'] = $statusStats;

        // ຈຳນວນຊັບສິນແຕ່ລະສະພາບ
        $conditionSql = "SELECT asset_condition, COUNT(*) as count FROM assets a WHERE 1=1";
        if ($userRole === 'employee') {
            $conditionSql .= " AND a.current_user_id = ?";
        } elseif ($userRole === 'department_head' || $userRole === 'manager') {
            $conditionSql .= " AND a.department_id = ?";
        }
        $conditionSql .= " GROUP BY asset_condition";

        $conditionStmt = $this->db->prepare($conditionSql);
        $conditionStmt->execute($params);
        
        $conditionStats = [];
        while ($row = $conditionStmt->fetch()) {
            $conditionStats[$row['asset_condition']] = $row['count'];
        }
        $stats['condition_stats'] = $conditionStats;

        // ມູນຄ່າຊັບສິນທັງໝົດ
        $valueSql = "SELECT SUM(purchase_cost) as total_cost, SUM(current_value) as total_current_value 
                     FROM assets a WHERE 1=1";
        if ($userRole === 'employee') {
            $valueSql .= " AND a.current_user_id = ?";
        } elseif ($userRole === 'department_head' || $userRole === 'manager') {
            $valueSql .= " AND a.department_id = ?";
        }

        $valueStmt = $this->db->prepare($valueSql);
        $valueStmt->execute($params);
        $valueStats = $valueStmt->fetch();
        $stats['total_purchase_cost'] = $valueStats['total_cost'] ?? 0;
        $stats['total_current_value'] = $valueStats['total_current_value'] ?? 0;

        return $stats;
    }

    /**
     * ຊອກຫາຊັບສິນ
     */
    public function searchAssets($keyword, $userRole = null, $userDepartmentId = null, $userId = null) {
        $sql = "SELECT a.*,
                       cat.category_name,
                       dept.department_name,
                       u.first_name as user_first_name,
                       u.last_name as user_last_name
                FROM assets a
                LEFT JOIN asset_categories cat ON a.category_id = cat.id
                LEFT JOIN departments dept ON a.department_id = dept.id
                LEFT JOIN users u ON a.current_user_id = u.id
                WHERE (a.asset_code LIKE ? OR a.asset_name LIKE ? OR a.asset_name_en LIKE ? 
                      OR a.serial_number LIKE ? OR a.barcode LIKE ? OR a.rfid_tag LIKE ?
                      OR a.model LIKE ? OR a.brand LIKE ?)";
        $params = [];

        $searchTerm = "%{$keyword}%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;

        if ($userRole === 'employee') {
            $sql .= " AND a.current_user_id = ?";
            $params[] = $userId;
        } elseif ($userRole === 'department_head' || $userRole === 'manager') {
            $sql .= " AND a.department_id = ?";
            $params[] = $userDepartmentId;
        }

        $sql .= " ORDER BY a.created_at DESC LIMIT 50";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

 
    
    /**
     * ຮັບຂໍ້ມູນການຂາຍຈາກ Frontend ແລະ ບັນທຶກໄວ້
     */
    public function syncFromSales() {
        try {
            // ກວດສອບ authentication
            $auth = new AuthMiddleware();
            $user = $auth->validate();
            
            if (!$user) {
                Response::error('Unauthorized', 401);
                return;
            }
            
            // ຮັບຂໍ້ມູນ JSON
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!$input) {
                Response::error('Invalid input data', 400);
                return;
            }
            
            if (empty($input['source_id']) || empty($input['source_number'])) {
                Response::error('Missing required fields: source_id or source_number', 400);
                return;
            }
            
            // 1. ບັນທຶກໃນ asset_sync_log
            $logResult = $this->assetSyncLog->create([
                'source_type' => $input['source_type'] ?? 'sales_order',
                'source_id' => $input['source_id'],
                'source_number' => $input['source_number'],
                'customer_id' => $input['customer_id'] ?? null,
                'customer_name' => $input['customer_name'] ?? null,
                'total_amount' => $input['total_amount'] ?? 0,
                'sale_date' => $input['sale_date'] ?? date('Y-m-d'),
                'items_data' => json_encode($input['items'] ?? []),
                'notes' => $input['notes'] ?? null,
                'synced_by' => $user['id']
            ]);
            
            if (!$logResult['success']) {
                Response::error($logResult['message'], 500);
                return;
            }
            
            // 2. ສ້າງ assets ສຳລັບສິນຄ້າທີ່ຂາຍ
            $assetResult = $this->assetModel->createFromSales([
                'source_type' => $input['source_type'] ?? 'sales_order',
                'source_id' => $input['source_id'],
                'source_number' => $input['source_number'],
                'customer_id' => $input['customer_id'] ?? null,
                'customer_name' => $input['customer_name'] ?? null,
                'sale_date' => $input['sale_date'] ?? date('Y-m-d'),
                'items' => $input['items'] ?? [],
                'company_id' => $input['company_id'] ?? 1,
                'department_id' => $input['department_id'] ?? 1
            ], $user['id']);
            
            if ($assetResult['success']) {
                Response::success([
                    'sync_id' => $logResult['sync_id'],
                    'assets_created' => count($assetResult['assets']),
                    'assets' => $assetResult['assets'],
                    'synced_at' => date('Y-m-d H:i:s')
                ], 200, 'Data synced to asset system successfully');
            } else {
                Response::error($assetResult['message'], 500);
            }
            
        } catch (Exception $e) {
            error_log("Error in syncFromSales: " . $e->getMessage());
            Response::error('Server error: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * ດຶງຂໍ້ມູນຊັບສິນທີ່ຂາຍແລ້ວ
     */
    public function getSoldAssets() {
        try {
            $auth = new AuthMiddleware();
            $user = $auth->validate();
            
            if (!$user) {
                Response::error('Unauthorized', 401);
                return;
            }
            
            $filters = [
                'asset_code' => $_GET['asset_code'] ?? null,
                'asset_name' => $_GET['asset_name'] ?? null,
                'customer_id' => $_GET['customer_id'] ?? null,
                'from_date' => $_GET['from_date'] ?? null,
                'to_date' => $_GET['to_date'] ?? null,
                'search' => $_GET['search'] ?? null
            ];
            
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
            
            $result = $this->assetModel->getSoldAssets($filters, $page, $limit);
            
            Response::success($result, 200, 'Sold assets retrieved successfully');
            
        } catch (Exception $e) {
            error_log("Error in getSoldAssets: " . $e->getMessage());
            Response::error('Server error: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * ດຶງສະຖິຕິຊັບສິນທີ່ຂາຍແລ້ວ
     */
    public function getSoldAssetsStats() {
        try {
            $auth = new AuthMiddleware();
            $user = $auth->validate();
            
            if (!$user) {
                Response::error('Unauthorized', 401);
                return;
            }
            
            $stats = $this->assetModel->getSoldAssetsStats();
            
            Response::success($stats, 200, 'Sold assets stats retrieved successfully');
            
        } catch (Exception $e) {
            error_log("Error in getSoldAssetsStats: " . $e->getMessage());
            Response::error('Server error: ' . $e->getMessage(), 500);
        }
    }


    /**
     * ດຶງ category ID ຕາມຊື່ສິນຄ້າ (ຈາກຖານຂໍ້ມູນ)
     */
    private function getCategoryIdByItemName($itemName) {
        try {
            // ຊອກຫາ category ທີ່ມີຊື່ກົງກັນ
            $stmt = $this->db->prepare("SELECT id FROM asset_categories WHERE category_name LIKE ? LIMIT 1");
            $stmt->execute(["%$itemName%"]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result) {
                return $result['id'];
            }
            
            // ຖ້າບໍ່ພົບ, ໃຊ້ default ຮາດແວ (id=1)
            return 1;
            
        } catch (Exception $e) {
            error_log("Error getting category by item name: " . $e->getMessage());
            return 1;
        }
    }



    /**
     * ສ້າງຊັບສິນຈາກການຂາຍ
     */
    public function createFromSales($data, $createdBy) {
        try {
            error_log("=== Asset::createFromSales called ===");
            error_log("Data: " . json_encode($data));
            error_log("Created by: " . $createdBy);
            
            // ກວດສອບວ່າ created_by ມີໃນ users table ບໍ
            $stmt = $this->db->prepare("SELECT id FROM users WHERE id = ? AND status = 1");
            $stmt->execute([$createdBy]);
            $userExists = $stmt->fetch();
            
            if (!$userExists) {
                error_log("Created by user $createdBy not found, using default user 6");
                $createdBy = 6; // ໃຊ້ Phosika (id=6) ເປັນ default
            }
            
            // ກວດສອບ customer_id ວ່າມີໃນ users table ບໍ
            $customerId = $data['customer_id'];
            $validUserId = null;
            
            if ($customerId) {
                $stmt = $this->db->prepare("SELECT id FROM users WHERE id = ? AND status = 1");
                $stmt->execute([$customerId]);
                $userExists = $stmt->fetch();
                if ($userExists) {
                    $validUserId = $customerId;
                    error_log("Customer ID $customerId found in users table");
                } else {
                    // ຖ້າບໍ່ມີ, ໃຊ້ created_by (id=6) ແທນ
                    $validUserId = $createdBy;
                    error_log("Customer ID $customerId not found in users, using created_by: $createdBy");
                }
            } else {
                $validUserId = $createdBy;
                error_log("No customer ID, using created_by: $createdBy");
            }
            
            $this->db->beginTransaction();
            
            $assets = [];
            
            foreach ($data['items'] as $item) {
                // ດຶງ category ຕາມຊື່ສິນຄ້າ
                $categoryId = $this->getCategoryIdByItemName($item['item_name']);
                error_log("Category ID for '{$item['item_name']}': $categoryId");
                
                // ສ້າງລະຫັດຊັບສິນ
                $assetCode = $this->generateSoldAssetCode();
                error_log("Generated asset code: " . $assetCode);
                
                $assetData = [
                    'asset_code' => $assetCode,
                    'asset_name' => $item['item_name'],
                    'asset_name_en' => $item['item_name_en'] ?? $item['item_name'],
                    'description' => "ສິນຄ້າທີ່ຂາຍອອກ: {$item['item_name']}",
                    'category_id' => $categoryId,
                    'category_level1_id' => $categoryId,
                    'purchase_date' => $data['sale_date'],
                    'purchase_cost' => (float)$item['total_price'],
                    'current_value' => (float)$item['total_price'],
                    'salvage_value' => 0,
                    'depreciation_method' => 'none',
                    'status' => 'sold',
                    'asset_condition' => 'good',
                    'company_id' => $data['company_id'] ?? 1,
                    'department_id' => $data['department_id'] ?? 1,
                    'current_user_id' => $validUserId, // ໃຊ້ user ID ທີ່ຖືກຕ້ອງ (6 ຫຼື ອື່ນໆ)
                    'notes' => "ຂາຍອອກຕາມໃບຂາຍ {$data['source_number']} - ລູກຄ້າ: {$data['customer_name']}",
                    'is_active' => 1,
                    'custom_fields' => json_encode([
                        'source_type' => $data['source_type'],
                        'source_id' => $data['source_id'],
                        'source_number' => $data['source_number'],
                        'item_id' => $item['item_id'],
                        'item_code' => $item['item_code'],
                        'quantity' => $item['quantity'],
                        'unit_price' => (float)$item['unit_price'],
                        'customer_name' => $data['customer_name'],
                        'customer_id' => $data['customer_id'],
                        'sale_date' => $data['sale_date']
                    ])
                ];
                
                error_log("Asset data to create: " . json_encode($assetData));
                
                // ໃຊ້ຟັງຊັນ create ທີ່ມີຢູ່ແລ້ວ
                $result = $this->create($assetData, $createdBy);
                
                error_log("Create result: " . json_encode($result));
                
                if (!$result['success']) {
                    throw new Exception($result['message']);
                }
                
                $assets[] = [
                    'id' => $result['asset_id'],
                    'asset_code' => $assetCode,
                    'asset_name' => $item['item_name'],
                    'quantity' => $item['quantity'],
                    'total_price' => (float)$item['total_price']
                ];
            }
            
            $this->db->commit();
            
            error_log("Assets created successfully: " . count($assets) . " assets");
            
            return [
                'success' => true,
                'assets' => $assets,
                'message' => 'Created ' . count($assets) . ' assets from sales'
            ];
            
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error creating assets from sales: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            return [
                'success' => false,
                'message' => 'Failed to create assets: ' . $e->getMessage()
            ];
        }
    }
    /**
     * ດຶງ category ເລີ່ມຕົ້ນສຳລັບສິນຄ້າທີ່ຂາຍ
     */
    private function getDefaultCategoryId() {
        try {
            // ຊອກຫາ category ທີ່ມີຊື່ "ສິນຄ້າຂາຍ"
            $stmt = $this->db->prepare("SELECT id FROM asset_categories WHERE category_name = ? LIMIT 1");
            $stmt->execute(['ສິນຄ້າຂາຍ']);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result) {
                return $result['id'];
            }
            
            // ຖ້າບໍ່ມີ, ຊອກຫາ category ທີ່ມີ id = 1
            $stmt = $this->db->prepare("SELECT id FROM asset_categories WHERE id = 1 LIMIT 1");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result) {
                return $result['id'];
            }
            
            // ຖ້າຍັງບໍ່ມີ, ສ້າງໃໝ່
            $stmt = $this->db->prepare("INSERT INTO asset_categories (category_name, category_name_en, level, created_at) VALUES (?, ?, 1, NOW())");
            $stmt->execute(['ສິນຄ້າຂາຍ', 'Sold Products']);
            return $this->db->lastInsertId();
            
        } catch (Exception $e) {
            error_log("Error getting default category: " . $e->getMessage());
            return 1; // ສົ່ງຄືນ ID 1 ເປັນ default
        }
    }
 

    /**
     * ສ້າງລະຫັດຊັບສິນສຳລັບສິນຄ້າທີ່ຂາຍ (ໃຊ້ຮູບແບບ AST ດຽວກັນ ແລະ ເພີ່ມ S ຕໍ່ທ້າຍ)
     */
    private function generateSoldAssetCode() {
        // ໃຊ້ຮູບແບບ AST ຄືກັນ ແຕ່ເພີ່ມ S ຕໍ່ທ້າຍເພື່ອບອກວ່າເປັນສິນຄ້າຂາຍ
        $prefix = 'AST';
        $year = date('Y');
        $month = date('m');
        $suffix = 'S'; // S = Sold
        
        // ນັບຈຳນວນຊັບສິນທັງໝົດໃນປີປັດຈຸບັນ
        $stmt = $this->db->prepare("SELECT COUNT(*) as count FROM assets WHERE asset_code LIKE 'AST{$year}%'");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $count = ($result ? (int)$result['count'] : 0) + 1;
        
        // ສ້າງລະຫັດ: AST + ປີ + ເດືອນ + ລຳດັບ 5 ຫຼັກ + S
        return $prefix . $year . $month . str_pad($count, 5, '0', STR_PAD_LEFT) . $suffix;
    }


}

?>