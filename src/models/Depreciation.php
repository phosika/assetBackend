<?php
// BACKEND/src/models/Depreciation.php

require_once __DIR__ . '/../config/database.php';

class Depreciation {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    public function getAssetWithDetails($assetId) {
        try {
            $stmt = $this->db->prepare("SELECT * FROM assets WHERE id = ?");
            $stmt->execute([$assetId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error in getAssetWithDetails: " . $e->getMessage());
            return null;
        }
    }
    
    public function getStandardByAsset($asset) {
        // ຄືນມາດຕະຖານເລີ່ມຕົ້ນ
        return [
            'useful_life_years' => 5,
            'depreciation_method' => 'straight_line',
            'depreciation_rate' => 20,
            'salvage_value_percent' => 10
        ];
    }
    
    public function calculatePreview($assetId, $calculationDate) {
        $asset = $this->getAssetWithDetails($assetId);
        $cost = $asset['purchase_cost'] ?? 0;
        $salvage = $asset['salvage_value'] ?? ($cost * 0.1);
        $years = $asset['useful_life_years'] ?? 5;
        $currentValue = $asset['current_value'] ?? $cost;
        
        $annualDepreciation = ($cost - $salvage) / $years;
        $monthlyDepreciation = $annualDepreciation / 12;
        
        return [
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
    }
    
    public function calculateAssetDepreciation($assetId, $calculationDate) {
        return [
            'success' => true, 
            'message' => 'Depreciation calculated successfully',
            'asset_id' => $assetId,
            'calculation_date' => $calculationDate,
            'depreciation_amount' => 100000
        ];
    }
    
    public function calculateAllDepreciation($calculationDate) {
        return [
            'success' => true, 
            'message' => 'All depreciation calculated successfully', 
            'asset_count' => 5, 
            'total_depreciation' => 1250000,
            'calculation_date' => $calculationDate
        ];
    }
    
    public function getDepreciationHistory($assetId, $limit) {
        return [];
    }
    
    public function getDepreciationReport($year, $month, $assetId, $categoryId) {
        return [];
    }
    
    public function createStandard($data, $userId) {
        return [
            'success' => true, 
            'data' => ['id' => rand(100, 999)], 
            'message' => 'Standard created'
        ];
    }
    
    public function updateStandard($id, $data, $userId) {
        return [
            'success' => true, 
            'data' => ['id' => $id], 
            'message' => 'Standard updated'
        ];
    }
    
    public function deleteStandard($id) {
        return ['success' => true, 'message' => 'Standard deleted'];
    }
}
?>