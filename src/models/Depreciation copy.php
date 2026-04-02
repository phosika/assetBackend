<?php
// BACKEND/src/models/Depreciation.php

require_once __DIR__ . '/../config/database.php';

class Depreciation {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * ຄິດໄລ່ຄ່າເສື່ອມລາຄາແບບເສັ້ນຊື່ (Straight Line)
     */
    public function calculateStraightLine($cost, $salvageValue, $usefulLifeYears) {
        if ($usefulLifeYears <= 0) return 0;
        return ($cost - $salvageValue) / $usefulLifeYears;
    }
    
    /**
     * ຄິດໄລ່ຄ່າເສື່ອມລາຄາແບບຫຼຸດລົງ (Declining Balance)
     */
    public function calculateDecliningBalance($bookValue, $rate) {
        return $bookValue * ($rate / 100);
    }
    
    /**
     * ຄິດໄລ່ຄ່າເສື່ອມລາຄາແບບຜົນລວມປີ (Sum of Years)
     */
    public function calculateSumOfYears($cost, $salvageValue, $usefulLifeYears, $currentYear) {
        $sumOfYears = ($usefulLifeYears * ($usefulLifeYears + 1)) / 2;
        $remainingLife = $usefulLifeYears - $currentYear + 1;
        return ($cost - $salvageValue) * ($remainingLife / $sumOfYears);
    }
    
    /**
     * ດຶງມາດຕະຖານການເສື່ອມລາຄາຕາມປະເພດຊັບສິນ
     */
    public function getStandardByAsset($asset) {
        $sql = "SELECT ds.* 
                FROM depreciation_standard ds 
                WHERE ds.is_active = 1 
                AND ds.effective_from <= CURDATE()
                AND (ds.effective_to IS NULL OR ds.effective_to >= CURDATE())
                AND (
                    (ds.asset_category_id = ? AND ds.asset_category_id IS NOT NULL)
                    OR (ds.asset_type = ? AND ds.asset_type IS NOT NULL)
                    OR (ds.asset_type IS NULL AND ds.asset_category_id IS NULL)
                )
                ORDER BY 
                    CASE 
                        WHEN ds.asset_category_id = ? THEN 1
                        WHEN ds.asset_type = ? THEN 2
                        ELSE 3
                    END
                LIMIT 1";
        
        $categoryId = $asset['category_id'] ?? null;
        $assetType = $this->getAssetType($asset);
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$categoryId, $assetType, $categoryId, $assetType]);
        return $stmt->fetch();
    }
    
    /**
     * ກຳນົດປະເພດຊັບສິນອັດຕະໂນມັດ
     */
    private function getAssetType($asset) {
        $keywords = [
            'computer' => 'ຄອມພິວເຕີ ແລະ ອຸປະກອນ IT',
            'laptop' => 'ຄອມພິວເຕີ ແລະ ອຸປະກອນ IT',
            'notebook' => 'ຄອມພິວເຕີ ແລະ ອຸປະກອນ IT',
            'printer' => 'ຄອມພິວເຕີ ແລະ ອຸປະກອນ IT',
            'machine' => 'ເຄື່ອງຈັກ ແລະ ອຸປະກອນການຜະລິດ',
            'vehicle' => 'ຍານພາຫະນະ',
            'car' => 'ຍານພາຫະນະ',
            'desk' => 'ເຟີນີເຈີ ແລະ ເຄື່ອງເຟີນີເຈີ',
            'chair' => 'ເຟີນີເຈີ ແລະ ເຄື່ອງເຟີນີເຈີ',
            'building' => 'ອາຄານ ແລະ ສິ່ງກໍ່ສ້າງ',
            'software' => 'ຊອບແວ ແລະ ລິຂະສິດ'
        ];
        
        $assetName = strtolower($asset['asset_name'] ?? '');
        foreach ($keywords as $keyword => $type) {
            if (strpos($assetName, $keyword) !== false) {
                return $type;
            }
        }
        return null;
    }
    
    /**
     * ຄິດໄລ່ຄ່າເສື່ອມລາຄາສຳລັບຊັບສິນດຽວ
     */
    public function calculateAssetDepreciation($assetId, $calculationDate = null) {
        if (!$calculationDate) {
            $calculationDate = date('Y-m-d');
        }
        
        $asset = $this->getAssetWithDetails($assetId);
        if (!$asset) {
            return ['success' => false, 'message' => 'Asset not found'];
        }
        
        // ກວດສອບວ່າຕ້ອງຄິດໄລ່ຫຼືບໍ່
        if ($asset['depreciation_method'] === 'none') {
            return ['success' => false, 'message' => 'Asset does not require depreciation'];
        }
        
        // ດຶງມາດຕະຖານ
        $standard = $this->getStandardByAsset($asset);
        
        // ກຳນົດຄ່າຕ່າງໆ
        $usefulLifeYears = $asset['useful_life_years'] ?? ($standard['useful_life_years'] ?? 5);
        $depreciationMethod = $asset['depreciation_method'] ?? ($standard['depreciation_method'] ?? 'straight_line');
        $salvageValue = $asset['salvage_value'] ?? ($asset['purchase_cost'] * (($standard['salvage_value_percent'] ?? 0) / 100));
        $cost = $asset['purchase_cost'];
        
        // ຄິດໄລ່ຕາມວິທີການ
        $depreciationAmount = 0;
        $currentValue = $asset['current_value'] ?? $cost;
        
        switch ($depreciationMethod) {
            case 'straight_line':
                $annualDepreciation = $this->calculateStraightLine($cost, $salvageValue, $usefulLifeYears);
                $depreciationAmount = $annualDepreciation / 12; // ຄິດໄລ່ລາຍເດືອນ
                break;
                
            case 'declining_balance':
                $rate = $asset['depreciation_rate'] ?? ($standard['depreciation_rate'] ?? 20);
                $depreciationAmount = $this->calculateDecliningBalance($currentValue, $rate) / 12;
                break;
                
            case 'sum_of_years':
                $purchaseYear = date('Y', strtotime($asset['purchase_date']));
                $currentYear = date('Y', strtotime($calculationDate));
                $yearNumber = $currentYear - $purchaseYear + 1;
                $depreciationAmount = $this->calculateSumOfYears($cost, $salvageValue, $usefulLifeYears, $yearNumber) / 12;
                break;
                
            default:
                $depreciationAmount = 0;
        }
        
        // ກວດສອບບໍ່ໃຫ້ຄ່າເສື່ອມລາຄາຕໍ່າກວ່າມູນຄ່າຊາກ
        if ($currentValue - $depreciationAmount < $salvageValue) {
            $depreciationAmount = $currentValue - $salvageValue;
        }
        
        // ບັນທຶກຜົນການຄິດໄລ່
        $result = $this->saveDepreciationRecord([
            'asset_id' => $assetId,
            'period_start' => date('Y-m-01', strtotime($calculationDate)),
            'period_end' => date('Y-m-t', strtotime($calculationDate)),
            'period_year' => date('Y', strtotime($calculationDate)),
            'period_month' => date('m', strtotime($calculationDate)),
            'opening_value' => $currentValue,
            'depreciation_amount' => $depreciationAmount,
            'accumulated_depreciation' => ($asset['accumulated_depreciation'] ?? 0) + $depreciationAmount,
            'closing_value' => $currentValue - $depreciationAmount,
            'depreciation_method' => $depreciationMethod,
            'depreciation_rate' => $asset['depreciation_rate'] ?? $standard['depreciation_rate'] ?? null,
            'useful_life_years' => $usefulLifeYears,
            'useful_life_months' => $usefulLifeYears * 12,
            'notes' => "Calculated using {$depreciationMethod} method"
        ]);
        
        if ($result['success']) {
            // ອັບເດດມູນຄ່າປັດຈຸບັນຂອງຊັບສິນ
            $this->updateAssetValue($assetId, $currentValue - $depreciationAmount, $depreciationAmount);
        }
        
        return $result;
    }
    
    /**
     * ຄິດໄລ່ຄ່າເສື່ອມລາຄາທັງໝົດ
     */
    public function calculateAllDepreciation($calculationDate = null) {
        if (!$calculationDate) {
            $calculationDate = date('Y-m-d');
        }
        
        // ກວດສອບວ່າຄິດໄລ່ແລ້ວຫຼືຍັງ
        if ($this->isCalculatedForPeriod($calculationDate)) {
            return ['success' => false, 'message' => 'Depreciation already calculated for this period'];
        }
        
        // ສ້າງ log
        $logId = $this->createCalculationLog($calculationDate);
        
        try {
            // ດຶງຊັບສິນທີ່ຕ້ອງຄິດໄລ່
            $assets = $this->getAssetsForDepreciation();
            $totalDepreciation = 0;
            $processedCount = 0;
            
            foreach ($assets as $asset) {
                $result = $this->calculateAssetDepreciation($asset['id'], $calculationDate);
                if ($result['success']) {
                    $totalDepreciation += $result['depreciation_amount'] ?? 0;
                    $processedCount++;
                }
            }
            
            // ອັບເດດ log
            $this->updateCalculationLog($logId, 'completed', $processedCount, $totalDepreciation);
            
            return [
                'success' => true,
                'message' => "Calculated depreciation for {$processedCount} assets",
                'total_depreciation' => $totalDepreciation,
                'asset_count' => $processedCount
            ];
            
        } catch (Exception $e) {
            $this->updateCalculationLog($logId, 'failed', 0, 0, $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * ດຶງປະຫວັດການເສື່ອມລາຄາຂອງຊັບສິນ
     */
    public function getDepreciationHistory($assetId, $limit = 12) {
        $sql = "SELECT * FROM asset_depreciation 
                WHERE asset_id = ? 
                ORDER BY period_start DESC 
                LIMIT ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$assetId, $limit]);
        return $stmt->fetchAll();
    }
    
    /**
     * ດຶງລາຍງານສະຫຼຸບການເສື່ອມລາຄາ
     */
    public function getDepreciationReport($year, $month = null) {
        $sql = "SELECT 
                    a.id,
                    a.asset_code,
                    a.asset_name,
                    a.purchase_cost,
                    a.purchase_date,
                    a.depreciation_method,
                    ad.*
                FROM assets a
                LEFT JOIN asset_depreciation ad ON a.id = ad.asset_id 
                    AND ad.period_year = ? 
                    " . ($month ? "AND ad.period_month = ?" : "") . "
                WHERE a.is_active = 1 
                    AND a.status != 'sold'
                    AND a.depreciation_method != 'none'
                ORDER BY a.asset_code";
        
        $params = [$year];
        if ($month) $params[] = $month;
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    /**
     * ຟັງຊັນຊ່ວຍເຫຼືອອື່ນໆ
     */
    private function getAssetWithDetails($assetId) {
        $stmt = $this->db->prepare("SELECT * FROM assets WHERE id = ?");
        $stmt->execute([$assetId]);
        return $stmt->fetch();
    }
    
    private function getAssetsForDepreciation() {
        $stmt = $this->db->prepare(
            "SELECT * FROM assets 
             WHERE is_active = 1 
             AND status != 'sold' 
             AND depreciation_method != 'none'
             AND purchase_date IS NOT NULL"
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    private function saveDepreciationRecord($data) {
        $sql = "INSERT INTO asset_depreciation (
            asset_id, period_start, period_end, period_year, period_month,
            opening_value, depreciation_amount, accumulated_depreciation,
            closing_value, depreciation_method, depreciation_rate,
            useful_life_years, useful_life_months, notes
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['asset_id'],
                $data['period_start'],
                $data['period_end'],
                $data['period_year'],
                $data['period_month'],
                $data['opening_value'],
                $data['depreciation_amount'],
                $data['accumulated_depreciation'],
                $data['closing_value'],
                $data['depreciation_method'],
                $data['depreciation_rate'],
                $data['useful_life_years'],
                $data['useful_life_months'],
                $data['notes']
            ]);
            
            return [
                'success' => true,
                'depreciation_amount' => $data['depreciation_amount'],
                'record_id' => $this->db->lastInsertId()
            ];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    private function updateAssetValue($assetId, $newValue, $depreciationAmount) {
        $sql = "UPDATE assets 
                SET current_value = ?,
                    accumulated_depreciation = COALESCE(accumulated_depreciation, 0) + ?,
                    last_depreciation_date = CURDATE()
                WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$newValue, $depreciationAmount, $assetId]);
    }
    
    private function isCalculatedForPeriod($date) {
        $year = date('Y', strtotime($date));
        $month = date('m', strtotime($date));
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) as count FROM asset_depreciation 
             WHERE period_year = ? AND period_month = ?"
        );
        $stmt->execute([$year, $month]);
        $result = $stmt->fetch();
        return $result['count'] > 0;
    }
    
    private function createCalculationLog($date) {
        $sql = "INSERT INTO depreciation_calculation_log (calculation_date, status) VALUES (?, 'processing')";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$date]);
        return $this->db->lastInsertId();
    }
    
    private function updateCalculationLog($logId, $status, $assetCount, $totalDepreciation, $error = null) {
        $sql = "UPDATE depreciation_calculation_log 
                SET status = ?, asset_count = ?, total_depreciation = ?, 
                    error_message = ?, processed_at = NOW()
                WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$status, $assetCount, $totalDepreciation, $error, $logId]);
    }

    /**
     * ຄິດໄລ່ແບບ preview (ບໍ່ບັນທຶກ)
     */
    public function calculatePreview($assetId, $calculationDate) {
        $asset = $this->getAssetWithDetails($assetId);
        if (!$asset) {
            return ['error' => 'Asset not found'];
        }
        
        $standard = $this->getStandardByAsset($asset);
        
        $usefulLifeYears = $asset['useful_life_years'] ?? ($standard['useful_life_years'] ?? 5);
        $depreciationMethod = $asset['depreciation_method'] ?? ($standard['depreciation_method'] ?? 'straight_line');
        $salvageValue = $asset['salvage_value'] ?? ($asset['purchase_cost'] * (($standard['salvage_value_percent'] ?? 0) / 100));
        $cost = $asset['purchase_cost'];
        $currentValue = $asset['current_value'] ?? $cost;
        
        // ຄຳນວນຕາມວິທີການ
        $depreciationAmount = 0;
        
        switch ($depreciationMethod) {
            case 'straight_line':
                $annualDepreciation = $this->calculateStraightLine($cost, $salvageValue, $usefulLifeYears);
                $depreciationAmount = $annualDepreciation / 12;
                break;
            case 'declining_balance':
                $rate = $asset['depreciation_rate'] ?? ($standard['depreciation_rate'] ?? 20);
                $depreciationAmount = $this->calculateDecliningBalance($currentValue, $rate) / 12;
                break;
            case 'sum_of_years':
                $purchaseYear = date('Y', strtotime($asset['purchase_date']));
                $currentYear = date('Y', strtotime($calculationDate));
                $yearNumber = $currentYear - $purchaseYear + 1;
                $depreciationAmount = $this->calculateSumOfYears($cost, $salvageValue, $usefulLifeYears, $yearNumber) / 12;
                break;
            default:
                $depreciationAmount = 0;
        }
        
        // ກວດສອບບໍ່ໃຫ້ຕໍ່າກວ່າມູນຄ່າຊາກ
        if ($currentValue - $depreciationAmount < $salvageValue) {
            $depreciationAmount = $currentValue - $salvageValue;
        }
        
        return [
            'asset_id' => $assetId,
            'asset_name' => $asset['asset_name'],
            'asset_code' => $asset['asset_code'],
            'purchase_cost' => $cost,
            'current_value' => $currentValue,
            'salvage_value' => $salvageValue,
            'useful_life_years' => $usefulLifeYears,
            'depreciation_method' => $depreciationMethod,
            'monthly_depreciation' => round($depreciationAmount, 2),
            'annual_depreciation' => round($depreciationAmount * 12, 2),
            'remaining_value' => round($currentValue - $depreciationAmount, 2),
            'depreciation_rate' => $asset['depreciation_rate'] ?? ($standard['depreciation_rate'] ?? null),
            'calculation_date' => $calculationDate
        ];
    }

    /**
     * ສ້າງມາດຕະຖານໃໝ່
     */
    public function createStandard($data, $createdBy) {
        $sql = "INSERT INTO depreciation_standard (
            asset_category_id, asset_type, useful_life_years, depreciation_method,
            depreciation_rate, salvage_value_percent, effective_from, effective_to,
            description, created_by, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['asset_category_id'] ?? null,
                $data['asset_type'] ?? null,
                $data['useful_life_years'],
                $data['depreciation_method'] ?? 'straight_line',
                $data['depreciation_rate'] ?? null,
                $data['salvage_value_percent'] ?? 0,
                $data['effective_from'] ?? date('Y-m-d'),
                $data['effective_to'] ?? null,
                $data['description'] ?? null,
                $createdBy
            ]);
            
            $id = $this->db->lastInsertId();
            
            return [
                'success' => true,
                'data' => $this->getStandardById($id),
                'message' => 'Depreciation standard created successfully'
            ];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * ອັບເດດມາດຕະຖານ
     */
    public function updateStandard($id, $data, $updatedBy) {
        $fields = [];
        $params = [];
        
        $allowedFields = [
            'asset_category_id', 'asset_type', 'useful_life_years', 'depreciation_method',
            'depreciation_rate', 'salvage_value_percent', 'effective_from', 'effective_to',
            'description', 'is_active'
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
        
        $fields[] = "updated_by = ?";
        $fields[] = "updated_at = NOW()";
        $params[] = $updatedBy;
        $params[] = $id;
        
        $sql = "UPDATE depreciation_standard SET " . implode(', ', $fields) . " WHERE id = ?";
        
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            
            return [
                'success' => true,
                'data' => $this->getStandardById($id),
                'message' => 'Depreciation standard updated successfully'
            ];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * ລຶບມາດຕະຖານ (soft delete)
     */
    public function deleteStandard($id) {
        $sql = "UPDATE depreciation_standard SET is_active = 0 WHERE id = ?";
        
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id]);
            
            return ['success' => true, 'message' => 'Depreciation standard deleted successfully'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * ດຶງມາດຕະຖານຕາມ ID
     */
    public function getStandardById($id) {
        $sql = "SELECT * FROM depreciation_standard WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

}
?>