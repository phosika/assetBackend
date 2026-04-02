<?php
// BACKEND/src/controllers/DepreciationController.php

require_once __DIR__ . '/../models/Depreciation.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../utils/Response.php';

class DepreciationController {
    private $depreciationModel;
    private $db;
    
    public function __construct() {
        $this->depreciationModel = new Depreciation();
        $this->db = Database::getInstance();
    }
    
    /**
     * GET /depreciation/standards - ດຶງມາດຕະຖານການເສື່ອມລາຄາ
     */
    public function getStandards() {
        try {
            // ໃຊ້ AuthMiddleware ແບບດຽວກັບ Controllers ອື່ນ
            $user = AuthMiddleware::authenticate();
            
            // ກວດສອບສິດ
            $allowedRoles = ['super_admin', 'asset_admin', 'accountant', 'department_head', 'manager'];
            if (!in_array($user['role'], $allowedRoles)) {
                Response::forbidden('You do not have permission to view depreciation standards');
                return;
            }
            
            $sql = "SELECT * FROM depreciation_standard WHERE is_active = 1 ORDER BY asset_type";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $standards = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            Response::success($standards, 'Depreciation standards retrieved successfully');
            
        } catch (Exception $e) {
            error_log("Error in getStandards: " . $e->getMessage());
            Response::error('Failed to retrieve standards: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * GET /depreciation/history/all - ດຶງປະຫວັດການເສື່ອມລາຄາທັງໝົດ
     */
    public function getAllHistory() {
        try {
            $user = AuthMiddleware::authenticate();
            
            $allowedRoles = ['super_admin', 'asset_admin', 'accountant', 'department_head', 'manager'];
            if (!in_array($user['role'], $allowedRoles)) {
                Response::forbidden('You do not have permission to view depreciation history');
                return;
            }
            
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
            
            // ກວດສອບວ່າຕາຕະລາງມີຢູ່ບໍ
            $checkTable = $this->db->prepare("SHOW TABLES LIKE 'depreciation_calculation_log'");
            $checkTable->execute();
            $tableExists = $checkTable->rowCount() > 0;
            
            if (!$tableExists) {
                Response::success([], 'No depreciation history found');
                return;
            }
            
            $sql = "SELECT * FROM depreciation_calculation_log ORDER BY created_at DESC LIMIT ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$limit]);
            $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            Response::success($history, 'Depreciation history retrieved successfully');
            
        } catch (Exception $e) {
            error_log("Error in getAllHistory: " . $e->getMessage());
            Response::error('Failed to retrieve history: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * GET /depreciation/history/{assetId} - ດຶງປະຫວັດການເສື່ອມລາຄາຂອງຊັບສິນ
     */
    public function getHistory($assetId) {
        try {
            $user = AuthMiddleware::authenticate();
            
            $allowedRoles = ['super_admin', 'asset_admin', 'accountant', 'department_head', 'manager'];
            if (!in_array($user['role'], $allowedRoles)) {
                Response::forbidden('You do not have permission to view depreciation history');
                return;
            }
            
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 12;
            
            $checkTable = $this->db->prepare("SHOW TABLES LIKE 'asset_depreciation'");
            $checkTable->execute();
            $tableExists = $checkTable->rowCount() > 0;
            
            if (!$tableExists) {
                Response::success([], 'No depreciation history found');
                return;
            }
            
            $sql = "SELECT * FROM asset_depreciation WHERE asset_id = ? ORDER BY period_start DESC LIMIT ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$assetId, $limit]);
            $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            Response::success($history, 'Depreciation history retrieved successfully');
            
        } catch (Exception $e) {
            error_log("Error in getHistory: " . $e->getMessage());
            Response::error('Failed to retrieve history: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * GET /depreciation/report - ດຶງລາຍງານການເສື່ອມລາຄາ
     */
    public function getReport() {
        try {
            $user = AuthMiddleware::authenticate();
            
            $allowedRoles = ['super_admin', 'asset_admin', 'accountant', 'department_head', 'manager'];
            if (!in_array($user['role'], $allowedRoles)) {
                Response::forbidden('You do not have permission to view depreciation report');
                return;
            }
            
            $year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
            $month = isset($_GET['month']) ? (int)$_GET['month'] : null;
            $assetId = isset($_GET['asset_id']) ? (int)$_GET['asset_id'] : null;
            $categoryId = isset($_GET['category_id']) ? (int)$_GET['category_id'] : null;
            
            // ດຶງຂໍ້ມູນຊັບສິນ
            $sql = "SELECT a.id, a.asset_code, a.asset_name, a.purchase_cost, 
                           a.current_value, a.depreciation_method, a.useful_life_years,
                           a.depreciation_rate, a.salvage_value
                    FROM assets a
                    WHERE a.is_active = 1 
                      AND a.status != 'sold'
                      AND a.depreciation_method IS NOT NULL
                      AND a.depreciation_method != 'none'";
            $params = [];
            
            if ($assetId) {
                $sql .= " AND a.id = ?";
                $params[] = $assetId;
            }
            
            if ($categoryId) {
                $sql .= " AND a.category_id = ?";
                $params[] = $categoryId;
            }
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $assets = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // ຄິດໄລ່ຄ່າເສື່ອມລາຄາ
            $report = [];
            foreach ($assets as $asset) {
                $cost = (float)($asset['purchase_cost'] ?? 0);
                $salvage = (float)($asset['salvage_value'] ?? ($cost * 0.1));
                $years = (int)($asset['useful_life_years'] ?? 5);
                $currentValue = (float)($asset['current_value'] ?? $cost);
                
                if ($years > 0 && $cost > 0) {
                    $annualDepreciation = ($cost - $salvage) / $years;
                    $monthlyDepreciation = $annualDepreciation / 12;
                    
                    $report[] = [
                        'id' => $asset['id'],
                        'asset_code' => $asset['asset_code'],
                        'asset_name' => $asset['asset_name'],
                        'purchase_cost' => $cost,
                        'current_value' => $currentValue,
                        'depreciation_method' => $asset['depreciation_method'],
                        'useful_life_years' => $years,
                        'monthly_depreciation' => round($monthlyDepreciation, 2),
                        'annual_depreciation' => round($annualDepreciation, 2),
                        'salvage_value' => $salvage
                    ];
                }
            }
            
            Response::success($report, 'Depreciation report retrieved successfully');
            
        } catch (Exception $e) {
            error_log("Error in getReport: " . $e->getMessage());
            Response::error('Failed to retrieve report: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * GET /depreciation/calculate-asset/{assetId} - ຄິດໄລ່ຄ່າເສື່ອມລາຄາສຳລັບຊັບສິນດຽວ (ສະແດງຜົນ)
     */
    public function calculateAssetPreview($assetId) {
        try {
            $user = AuthMiddleware::authenticate();
            
            $allowedRoles = ['super_admin', 'asset_admin', 'accountant', 'department_head', 'manager'];
            if (!in_array($user['role'], $allowedRoles)) {
                Response::forbidden('You do not have permission to calculate depreciation');
                return;
            }
            
            $calculationDate = isset($_GET['calculation_date']) ? $_GET['calculation_date'] : date('Y-m-d');
            
            // ດຶງຂໍ້ມູນຊັບສິນ
            $stmt = $this->db->prepare("SELECT * FROM assets WHERE id = ?");
            $stmt->execute([$assetId]);
            $asset = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$asset) {
                Response::notFound('Asset not found');
                return;
            }
            
            $cost = (float)($asset['purchase_cost'] ?? 0);
            $salvage = (float)($asset['salvage_value'] ?? ($cost * 0.1));
            $years = (int)($asset['useful_life_years'] ?? 5);
            $currentValue = (float)($asset['current_value'] ?? $cost);
            
            $annualDepreciation = ($cost - $salvage) / $years;
            $monthlyDepreciation = $annualDepreciation / 12;
            
            $calculation = [
                'asset_id' => $assetId,
                'asset_name' => $asset['asset_name'],
                'asset_code' => $asset['asset_code'],
                'purchase_cost' => $cost,
                'current_value' => $currentValue,
                'salvage_value' => $salvage,
                'useful_life_years' => $years,
                'depreciation_method' => $asset['depreciation_method'] ?? 'straight_line',
                'monthly_depreciation' => round($monthlyDepreciation, 2),
                'annual_depreciation' => round($annualDepreciation, 2),
                'remaining_value' => round($currentValue - $monthlyDepreciation, 2),
                'calculation_date' => $calculationDate
            ];
            
            Response::success([
                'asset' => $asset,
                'calculation' => $calculation,
                'calculation_date' => $calculationDate
            ], 'Depreciation calculation preview');
            
        } catch (Exception $e) {
            error_log("Error in calculateAssetPreview: " . $e->getMessage());
            Response::error('Failed to calculate preview: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * POST /depreciation/calculate-all - ຄິດໄລ່ຄ່າເສື່ອມລາຄາທັງໝົດ
     */
    public function calculateAll() {
        try {
            $user = AuthMiddleware::authenticate();
            
            $allowedRoles = ['super_admin', 'asset_admin', 'accountant', 'department_head', 'manager'];
            if (!in_array($user['role'], $allowedRoles)) {
                Response::forbidden('You do not have permission to calculate depreciation');
                return;
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            $calculationDate = isset($input['calculation_date']) ? $input['calculation_date'] : date('Y-m-d');
            
            // ດຶງຊັບສິນທັງໝົດທີ່ຕ້ອງຄິດໄລ່
            $stmt = $this->db->prepare("SELECT id FROM assets WHERE is_active = 1 AND status != 'sold' AND depreciation_method IS NOT NULL AND depreciation_method != 'none'");
            $stmt->execute();
            $assets = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $assetCount = count($assets);
            $totalDepreciation = 0;
            
            // ຄິດໄລ່ແຕ່ລະຊັບສິນ
            foreach ($assets as $asset) {
                $result = $this->calculateAssetDepreciationLogic($asset['id'], $calculationDate);
                if ($result['success']) {
                    $totalDepreciation += $result['depreciation_amount'];
                }
            }
            
            Response::success([
                'success' => true,
                'message' => "Calculated depreciation for {$assetCount} assets",
                'asset_count' => $assetCount,
                'total_depreciation' => $totalDepreciation,
                'calculation_date' => $calculationDate
            ], 'Depreciation calculated successfully');
            
        } catch (Exception $e) {
            error_log("Error in calculateAll: " . $e->getMessage());
            Response::error('Failed to calculate depreciation: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * POST /depreciation/calculate-asset/{assetId} - ຄິດໄລ່ ແລະ ບັນທຶກສຳລັບຊັບສິນດຽວ
     */
    public function calculateAsset($assetId) {
        try {
            $user = AuthMiddleware::authenticate();
            
            $allowedRoles = ['super_admin', 'asset_admin', 'accountant', 'department_head', 'manager'];
            if (!in_array($user['role'], $allowedRoles)) {
                Response::forbidden('You do not have permission to calculate depreciation');
                return;
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            $calculationDate = isset($input['calculation_date']) ? $input['calculation_date'] : date('Y-m-d');
            
            $result = $this->calculateAssetDepreciationLogic($assetId, $calculationDate);
            
            if ($result['success']) {
                Response::success($result, $result['message']);
            } else {
                Response::error($result['message'], 400);
            }
            
        } catch (Exception $e) {
            error_log("Error in calculateAsset: " . $e->getMessage());
            Response::error('Failed to calculate depreciation: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * ຟັງຊັນຊ່ວຍສຳລັບການຄິດໄລ່
     */
    private function calculateAssetDepreciationLogic($assetId, $calculationDate) {
        $stmt = $this->db->prepare("SELECT * FROM assets WHERE id = ?");
        $stmt->execute([$assetId]);
        $asset = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$asset) {
            return ['success' => false, 'message' => 'Asset not found'];
        }
        
        $cost = (float)($asset['purchase_cost'] ?? 0);
        $salvage = (float)($asset['salvage_value'] ?? ($cost * 0.1));
        $years = (int)($asset['useful_life_years'] ?? 5);
        
        if ($years <= 0 || $cost <= 0) {
            return ['success' => false, 'message' => 'Invalid asset for depreciation calculation'];
        }
        
        $annualDepreciation = ($cost - $salvage) / $years;
        $monthlyDepreciation = $annualDepreciation / 12;
        
        return [
            'success' => true,
            'message' => 'Depreciation calculated successfully',
            'asset_id' => $assetId,
            'calculation_date' => $calculationDate,
            'depreciation_amount' => round($monthlyDepreciation, 2),
            'annual_depreciation' => round($annualDepreciation, 2)
        ];
    }
    
    /**
     * POST /depreciation/standards - ສ້າງມາດຕະຖານໃໝ່
     */
    public function createStandard() {
        try {
            $user = AuthMiddleware::authenticate();
            
            $allowedRoles = ['super_admin', 'asset_admin', 'accountant', 'department_head', 'manager'];
            if (!in_array($user['role'], $allowedRoles)) {
                Response::forbidden('You do not have permission to create depreciation standards');
                return;
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (empty($data['asset_type']) && empty($data['asset_category_id'])) {
                Response::error('Either asset_type or asset_category_id is required', 400);
                return;
            }
            if (empty($data['useful_life_years'])) {
                Response::error('useful_life_years is required', 400);
                return;
            }
            
            $sql = "INSERT INTO depreciation_standard (
                asset_category_id, asset_type, useful_life_years, depreciation_method,
                depreciation_rate, salvage_value_percent, effective_from, effective_to,
                description, created_by, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute([
                $data['asset_category_id'] ?? null,
                $data['asset_type'] ?? null,
                $data['useful_life_years'],
                $data['depreciation_method'] ?? 'straight_line',
                $data['depreciation_rate'] ?? null,
                $data['salvage_value_percent'] ?? 0,
                $data['effective_from'] ?? date('Y-m-d'),
                $data['effective_to'] ?? null,
                $data['description'] ?? null,
                $user['user_id']
            ]);
            
            if ($result) {
                $id = $this->db->lastInsertId();
                Response::success(['id' => $id], 'Depreciation standard created successfully', 201);
            } else {
                Response::error('Failed to create standard', 400);
            }
            
        } catch (Exception $e) {
            error_log("Error in createStandard: " . $e->getMessage());
            Response::error('Failed to create standard: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * PUT /depreciation/standards/{id} - ອັບເດດມາດຕະຖານ
     */
    public function updateStandard($id) {
        try {
            $user = AuthMiddleware::authenticate();
            
            $allowedRoles = ['super_admin', 'asset_admin', 'accountant', 'department_head', 'manager'];
            if (!in_array($user['role'], $allowedRoles)) {
                Response::forbidden('You do not have permission to update depreciation standards');
                return;
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            
            $fields = [];
            $params = [];
            
            $allowedFields = ['asset_category_id', 'asset_type', 'useful_life_years', 
                              'depreciation_method', 'depreciation_rate', 'salvage_value_percent',
                              'effective_from', 'effective_to', 'description', 'is_active'];
            
            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $fields[] = "{$field} = ?";
                    $params[] = $data[$field];
                }
            }
            
            if (empty($fields)) {
                Response::error('No data to update', 400);
                return;
            }
            
            $fields[] = "updated_by = ?";
            $fields[] = "updated_at = NOW()";
            $params[] = $user['user_id'];
            $params[] = $id;
            
            $sql = "UPDATE depreciation_standard SET " . implode(', ', $fields) . " WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute($params);
            
            if ($result) {
                Response::success(null, 'Depreciation standard updated successfully');
            } else {
                Response::error('Failed to update standard', 400);
            }
            
        } catch (Exception $e) {
            error_log("Error in updateStandard: " . $e->getMessage());
            Response::error('Failed to update standard: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * DELETE /depreciation/standards/{id} - ລຶບມາດຕະຖານ
     */
    public function deleteStandard($id) {
        try {
            $user = AuthMiddleware::authenticate();
            
            $allowedRoles = ['super_admin', 'asset_admin', 'accountant', 'department_head', 'manager'];
            if (!in_array($user['role'], $allowedRoles)) {
                Response::forbidden('You do not have permission to delete depreciation standards');
                return;
            }
            
            $sql = "UPDATE depreciation_standard SET is_active = 0 WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute([$id]);
            
            if ($result) {
                Response::success(null, 'Depreciation standard deleted successfully');
            } else {
                Response::error('Failed to delete standard', 400);
            }
            
        } catch (Exception $e) {
            error_log("Error in deleteStandard: " . $e->getMessage());
            Response::error('Failed to delete standard: ' . $e->getMessage(), 500);
        }
    }
}
?>