<?php
// /models/Role.php

class Role {
    private $db;
    private $table = 'roles';
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    // ດຶງຂໍ້ມູນບົດບາດທັງໝົດ
    public function getAll($page = 1, $limit = 20, $search = '', $status = '') {
        $offset = ($page - 1) * $limit;
        $params = [];
        
        $sql = "SELECT r.*, 
                (SELECT COUNT(*) FROM users WHERE role = r.name AND status = 1) as user_count
                FROM {$this->table} r WHERE 1=1";
        
        if (!empty($search)) {
            $sql .= " AND (r.name LIKE :search OR r.description LIKE :search)";
            $params[':search'] = "%{$search}%";
        }
        
        if (!empty($status) && $status != 'all') {
            $sql .= " AND r.status = :status";
            $params[':status'] = $status;
        }
        
        $sql .= " ORDER BY r.created_at DESC LIMIT :limit OFFSET :offset";
        $params[':limit'] = (int)$limit;
        $params[':offset'] = (int)$offset;
        
        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => &$val) {
            if ($key == ':limit' || $key == ':offset') {
                $stmt->bindValue($key, $val, PDO::PARAM_INT);
            } else {
                $stmt->bindValue($key, $val, PDO::PARAM_STR);
            }
        }
        $stmt->execute();
        $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // ດຶງຈຳນວນທັງໝົດ
        $countSql = "SELECT COUNT(*) as total FROM {$this->table} r WHERE 1=1";
        $countParams = [];
        
        if (!empty($search)) {
            $countSql .= " AND (r.name LIKE :search OR r.description LIKE :search)";
            $countParams[':search'] = "%{$search}%";
        }
        if (!empty($status) && $status != 'all') {
            $countSql .= " AND r.status = :status";
            $countParams[':status'] = $status;
        }
        
        $countStmt = $this->db->prepare($countSql);
        foreach ($countParams as $key => $val) {
            $countStmt->bindValue($key, $val, PDO::PARAM_STR);
        }
        $countStmt->execute();
        $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        return [
            'data' => $roles,
            'total' => $total,
            'page' => $page,
            'per_page' => $limit,
            'total_pages' => ceil($total / $limit)
        ];
    }
    
    // ດຶງຂໍ້ມູນສຳລັບ dropdown
    public function getForDropdown() {
        $sql = "SELECT id, name FROM {$this->table} WHERE status = 'active' ORDER BY name";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // ດຶງສະຖິຕິ
    public function getStats() {
        $sql = "SELECT 
                    COUNT(*) as total_roles,
                    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_roles,
                    SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive_roles
                FROM {$this->table}";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // ຄົ້ນຫາ
    public function search($keyword) {
        $sql = "SELECT * FROM {$this->table} 
                WHERE name LIKE :keyword OR description LIKE :keyword 
                ORDER BY name LIMIT 20";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':keyword', "%{$keyword}%", PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // ກວດສອບຊື່ຊ້ຳ
    public function isNameExists($name, $excludeId = null) {
        $sql = "SELECT COUNT(*) as count FROM {$this->table} WHERE name = :name";
        $params = [':name' => $name];
        
        if ($excludeId) {
            $sql .= " AND id != :id";
            $params[':id'] = $excludeId;
        }
        
        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val, PDO::PARAM_STR);
        }
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'] > 0;
    }
    
    // ດຶງຂໍ້ມູນບົດບາດຕາມ ID
    public function getById($id) {
        $sql = "SELECT r.*, 
                (SELECT COUNT(*) FROM users WHERE role = r.name AND status = 1) as user_count
                FROM {$this->table} r WHERE r.id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // ສ້າງບົດບາດໃໝ່
    public function create($data) {
        $sql = "INSERT INTO {$this->table} (name, description, status) 
                VALUES (:name, :description, :status)";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':name', $data['name'], PDO::PARAM_STR);
        $stmt->bindValue(':description', $data['description'] ?? '', PDO::PARAM_STR);
        $stmt->bindValue(':status', $data['status'] ?? 'active', PDO::PARAM_STR);
        
        if ($stmt->execute()) {
            return $this->db->lastInsertId();
        }
        return false;
    }
    
    // ອັບເດດບົດບາດ
    public function update($id, $data) {
        $sql = "UPDATE {$this->table} SET name = :name, description = :description, 
                status = :status WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':name', $data['name'], PDO::PARAM_STR);
        $stmt->bindValue(':description', $data['description'] ?? '', PDO::PARAM_STR);
        $stmt->bindValue(':status', $data['status'] ?? 'active', PDO::PARAM_STR);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        
        return $stmt->execute();
    }
    
    // ປ່ຽນສະຖານະ
    public function updateStatus($id, $status) {
        $sql = "UPDATE {$this->table} SET status = :status WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':status', $status, PDO::PARAM_STR);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }
    
    // ຄັດລອກບົດບາດ
    public function duplicate($id) {
        $original = $this->getById($id);
        if (!$original) {
            return false;
        }
        
        $newName = $original['name'] . '_copy_' . time();
        $sql = "INSERT INTO {$this->table} (name, description, status) 
                VALUES (:name, :description, :status)";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':name', $newName, PDO::PARAM_STR);
        $stmt->bindValue(':description', $original['description'] . ' (Copied)', PDO::PARAM_STR);
        $stmt->bindValue(':status', $original['status'], PDO::PARAM_STR);
        
        if ($stmt->execute()) {
            $newId = $this->db->lastInsertId();
            $this->duplicatePermissions($id, $newId);
            return $newId;
        }
        return false;
    }
    
    // ຄັດລອກສິດທິ
    private function duplicatePermissions($oldRoleId, $newRoleId) {
        $sql = "INSERT INTO role_permissions (role_id, page_key, permission_key)
                SELECT :newRoleId, page_key, permission_key
                FROM role_permissions WHERE role_id = :oldRoleId";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':newRoleId', $newRoleId, PDO::PARAM_INT);
        $stmt->bindValue(':oldRoleId', $oldRoleId, PDO::PARAM_INT);
        return $stmt->execute();
    }
    
    // ດຶງສິດທິ
    public function getPermissions($roleId) {
        $sql = "SELECT page_key, permission_key FROM role_permissions WHERE role_id = :role_id";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':role_id', $roleId, PDO::PARAM_INT);
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $permissions = [];
        foreach ($results as $row) {
            if (!isset($permissions[$row['page_key']])) {
                $permissions[$row['page_key']] = [];
            }
            $permissions[$row['page_key']][] = $row['permission_key'];
        }
        return $permissions;
    }
    
    // ບັນທຶກສິດທິ
    public function savePermissions($roleId, $permissions) {
        // ເລີ່ມ transaction
        $this->db->beginTransaction();
        
        try {
            // ລຶບສິດທິເກົ່າ
            $deleteSql = "DELETE FROM role_permissions WHERE role_id = :role_id";
            $deleteStmt = $this->db->prepare($deleteSql);
            $deleteStmt->bindValue(':role_id', $roleId, PDO::PARAM_INT);
            $deleteStmt->execute();
            
            // ເພີ່ມສິດທິໃໝ່
            if (!empty($permissions) && is_array($permissions)) {
                $insertSql = "INSERT INTO role_permissions (role_id, page_key, permission_key) 
                             VALUES (:role_id, :page_key, :permission_key)";
                $insertStmt = $this->db->prepare($insertSql);
                
                foreach ($permissions as $pageKey => $permKeys) {
                    if (is_array($permKeys)) {
                        foreach ($permKeys as $permKey) {
                            $insertStmt->bindValue(':role_id', $roleId, PDO::PARAM_INT);
                            $insertStmt->bindValue(':page_key', $pageKey, PDO::PARAM_STR);
                            $insertStmt->bindValue(':permission_key', $permKey, PDO::PARAM_STR);
                            $insertStmt->execute();
                        }
                    }
                }
            }
            
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Save permissions error: " . $e->getMessage());
            return false;
        }
    }
    
    // ລຶບບົດບາດ
    public function delete($id) {
        // ກວດສອບວ່າມີຜູ້ໃຊ້ທີ່ໃຊ້ບົດບາດນີ້ຫຼືບໍ່
        $role = $this->getById($id);
        if ($role) {
            $checkSql = "SELECT COUNT(*) as count FROM users WHERE role = :role AND status = 1";
            $checkStmt = $this->db->prepare($checkSql);
            $checkStmt->bindValue(':role', $role['name'], PDO::PARAM_STR);
            $checkStmt->execute();
            $userCount = $checkStmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            if ($userCount > 0) {
                return ['error' => 'Cannot delete role with existing users', 'user_count' => $userCount];
            }
        }
        
        $sql = "DELETE FROM {$this->table} WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        
        if ($stmt->execute()) {
            return ['success' => true];
        }
        return ['error' => 'Delete failed'];
    }
}
?>