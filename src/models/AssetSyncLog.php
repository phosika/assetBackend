<?php
// src/models/AssetSyncLog.php
require_once __DIR__ . '/../config/database.php';

class AssetSyncLog {
    private $db;
    private $table = 'asset_sync_log';
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * ສ້າງບັນທຶກການ sync ໃໝ່
     */
    public function create($data) {
        try {
            $sql = "INSERT INTO {$this->table} (
                        source_type, source_id, source_number, 
                        customer_id, customer_name, total_amount, 
                        sale_date, items_data, notes, synced_by, synced_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['source_type'],
                $data['source_id'],
                $data['source_number'],
                $data['customer_id'],
                $data['customer_name'],
                $data['total_amount'],
                $data['sale_date'],
                $data['items_data'],
                $data['notes'],
                $data['synced_by']
            ]);
            
            return [
                'success' => true,
                'sync_id' => $this->db->lastInsertId()
            ];
            
        } catch (Exception $e) {
            error_log("Error creating asset sync log: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to create sync log: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * ດຶງຂໍ້ມູນ sync log ທັງໝົດ
     */
    public function getAll($limit = 50, $offset = 0) {
        try {
            $sql = "SELECT * FROM {$this->table} ORDER BY synced_at DESC LIMIT ? OFFSET ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$limit, $offset]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting sync logs: " . $e->getMessage());
            return [];
        }
    }
}