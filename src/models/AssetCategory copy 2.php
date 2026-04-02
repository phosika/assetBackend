<?php
// src/models/AssetCategory.php
require_once __DIR__ . '/../config/database.php';

class AssetCategory {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * ດຶງຂໍ້ມູນໝວດໝູ່ຊັບສິນທັງໝົດແບບ Paginated
     */
    public function getAllCategories($filters = [], $userRole = null) {
        $sql = "SELECT ac.*, 
                       parent.category_name as parent_category_name,
                       CONCAT(creator.first_name, ' ', creator.last_name) as created_by_name
                FROM asset_categories ac
                LEFT JOIN asset_categories parent ON ac.parent_id = parent.id
                LEFT JOIN users creator ON ac.created_by = creator.id
                WHERE 1=1";
        $params = [];

        // ກັ່ນຕອງຕາມສະຖານະ
        if (!empty($filters['is_active']) && $filters['is_active'] !== 'all') {
            $sql .= " AND ac.is_active = ?";
            $params[] = $filters['is_active'] === 'active' ? 1 : 0;
        }

        // ກັ່ນຕອງຕາມລະດັບ
        if (!empty($filters['level'])) {
            $sql .= " AND ac.level = ?";
            $params[] = $filters['level'];
        }

        // ກັ່ນຕອງຕາມພໍ່ແມ່
        if (isset($filters['parent_id'])) {
            if ($filters['parent_id'] === 'null') {
                $sql .= " AND ac.parent_id IS NULL";
            } else {
                $sql .= " AND ac.parent_id = ?";
                $params[] = $filters['parent_id'];
            }
        }

        // ຄົ້ນຫາຕາມຄຳສຳຄັນ
        if (!empty($filters['search'])) {
            $sql .= " AND (ac.category_code LIKE ? OR ac.category_name LIKE ? OR ac.description LIKE ?)";
            $searchTerm = "%{$filters['search']}%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        // ການຈັດຮຽງ
        $sortBy = $filters['sort_by'] ?? 'sort_order';
        $sortOrder = isset($filters['sort_order']) && strtoupper($filters['sort_order']) === 'ASC' ? 'ASC' : 'ASC';
        $sql .= " ORDER BY ac.{$sortBy} {$sortOrder}, ac.category_name ASC";

        // ການແບ່ງໜ້າ
        $page = isset($filters['page']) ? (int)$filters['page'] : 1;
        $limit = isset($filters['limit']) ? (int)$filters['limit'] : 20;
        $offset = ($page - 1) * $limit;
        
        $sql .= " LIMIT {$limit} OFFSET {$offset}";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $categories = $stmt->fetchAll();

        // ນັບຈຳນວນທັງໝົດສຳລັບ pagination
        $countSql = "SELECT COUNT(*) as total FROM asset_categories ac WHERE 1=1";
        $countParams = [];

        if (!empty($filters['is_active']) && $filters['is_active'] !== 'all') {
            $countSql .= " AND ac.is_active = ?";
            $countParams[] = $filters['is_active'] === 'active' ? 1 : 0;
        }

        if (!empty($filters['level'])) {
            $countSql .= " AND ac.level = ?";
            $countParams[] = $filters['level'];
        }

        if (isset($filters['parent_id'])) {
            if ($filters['parent_id'] === 'null') {
                $countSql .= " AND ac.parent_id IS NULL";
            } else {
                $countSql .= " AND ac.parent_id = ?";
                $countParams[] = $filters['parent_id'];
            }
        }

        if (!empty($filters['search'])) {
            $countSql .= " AND (ac.category_code LIKE ? OR ac.category_name LIKE ? OR ac.description LIKE ?)";
            $searchTerm = "%{$filters['search']}%";
            $countParams[] = $searchTerm;
            $countParams[] = $searchTerm;
            $countParams[] = $searchTerm;
        }

        $countStmt = $this->db->prepare($countSql);
        $countStmt->execute($countParams);
        $totalResult = $countStmt->fetch();
        $total = $totalResult ? (int)$totalResult['total'] : 0;

        return [
            'data' => $categories,
            'total' => $total,
            'current_page' => $page,
            'per_page' => $limit,
            'last_page' => $total > 0 ? ceil($total / $limit) : 1,
            'from' => $offset + 1,
            'to' => min($offset + $limit, $total)
        ];
    }

    /**
     * ດຶງຂໍ້ມູນໝວດໝູ່ຊັບສິນແບບເປັນລຳດັບຊັ້ນ (Tree)
     */
    public function getCategoryTree($parentId = null, $level = 0) {
        $sql = "SELECT ac.*, 
                       (SELECT COUNT(*) FROM asset_categories WHERE parent_id = ac.id) as child_count
                FROM asset_categories ac
                WHERE ac.is_active = 1";
        $params = [];

        if ($parentId === null) {
            $sql .= " AND ac.parent_id IS NULL";
        } else {
            $sql .= " AND ac.parent_id = ?";
            $params[] = $parentId;
        }

        $sql .= " ORDER BY ac.sort_order ASC, ac.category_name ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $categories = $stmt->fetchAll();

        foreach ($categories as &$category) {
            if ($category['child_count'] > 0) {
                $category['children'] = $this->getCategoryTree($category['id'], $level + 1);
            } else {
                $category['children'] = [];
            }
            $category['level'] = $level;
        }

        return $categories;
    }



    /**
     * ດຶງຂໍ້ມູນໝວດໝູ່ຊັບສິນຕາມ ID
     */
    public function getById($id) {
        $stmt = $this->db->prepare(
            "SELECT ac.*, 
                    parent.category_name as parent_category_name,
                    parent.category_code as parent_category_code,
                    CONCAT(creator.first_name, ' ', creator.last_name) as created_by_name
             FROM asset_categories ac
             LEFT JOIN asset_categories parent ON ac.parent_id = parent.id
             LEFT JOIN users creator ON ac.created_by = creator.id
             WHERE ac.id = ?"
        );
        $stmt->execute([$id]);
        return $stmt->fetch();
    }


    /**
     * ດຶງ path ທີ່ສວຍງາມສຳລັບສະແດງຜົນ
     */
    public function getPrettyPath($id) {
        $category = $this->getById($id);
        if (!$category) {
            return '';
        }
        
        // ແຍກ path ດ້ວຍ '/'
        $pathParts = explode('/', $category['path']);
        
        // ຖ້າຕ້ອງການສະແດງແບບມີລູກສອນ
        return implode(' → ', $pathParts);
    }


    /**
     * ດຶງຂໍ້ມູນໝວດໝູ່ຊັບສິນຕາມລະຫັດ
     */
    public function getByCode($categoryCode) {
        $stmt = $this->db->prepare(
            "SELECT * FROM asset_categories WHERE category_code = ?"
        );
        $stmt->execute([$categoryCode]);
        return $stmt->fetch();
    }

    /**
     * ດຶງຂໍ້ມູນໝວດໝູ່ຊັບສິນແບບຫຍໍ້ສຳລັບ dropdown
     */
    public function getCategoriesForDropdown($parentId = null, $excludeId = null) {
        $sql = "SELECT ac.id, ac.category_code, ac.category_name, ac.level,
                       CONCAT(ac.category_code, ' - ', ac.category_name) as display_name,
                       parent.category_name as parent_category_name
                FROM asset_categories ac
                LEFT JOIN asset_categories parent ON ac.parent_id = parent.id
                WHERE ac.is_active = 1";
        $params = [];

        if ($parentId !== null) {
            if ($parentId === 'null') {
                $sql .= " AND ac.parent_id IS NULL";
            } else {
                $sql .= " AND ac.parent_id = ?";
                $params[] = $parentId;
            }
        }

        if ($excludeId) {
            $sql .= " AND ac.id != ?";
            $params[] = $excludeId;
        }

        $sql .= " ORDER BY ac.level ASC, ac.sort_order ASC, ac.category_name ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * ດຶງລາຍຊື່ໝວດໝູ່ຊັບສິນຕາມລະດັບ
     */
    public function getCategoriesByLevel($level) {
        $stmt = $this->db->prepare(
            "SELECT * FROM asset_categories 
             WHERE level = ? AND is_active = 1 
             ORDER BY sort_order ASC, category_name ASC"
        );
        $stmt->execute([$level]);
        return $stmt->fetchAll();
    }

    /**
     * ດຶງສະຖິຕິໝວດໝູ່ຊັບສິນ
     */
    public function getCategoryStats() {
        $stats = [];

        // ຈຳນວນໝວດໝູ່ທັງໝົດ
        $stmt = $this->db->query("SELECT COUNT(*) as total FROM asset_categories");
        $stats['total_categories'] = $stmt->fetch()['total'];

        // ຈຳນວນແຕ່ລະລະດັບ
        $stmt = $this->db->query("SELECT level, COUNT(*) as count FROM asset_categories GROUP BY level ORDER BY level");
        $levelStats = [];
        while ($row = $stmt->fetch()) {
            $levelStats["level_{$row['level']}"] = $row['count'];
        }
        $stats['level_stats'] = $levelStats;

        // ຈຳນວນໝວດໝູ່ແມ່ ແລະ ໝວດໝູ່ຍ່ອຍ
        $parentStmt = $this->db->query("SELECT COUNT(*) as count FROM asset_categories WHERE parent_id IS NULL");
        $stats['parent_categories'] = $parentStmt->fetch()['count'];
        
        $childStmt = $this->db->query("SELECT COUNT(*) as count FROM asset_categories WHERE parent_id IS NOT NULL");
        $stats['child_categories'] = $childStmt->fetch()['count'];

        // ຈຳນວນທີ່ເປີດໃຊ້ງານ / ປິດໃຊ້ງານ
        $activeStmt = $this->db->query("SELECT COUNT(*) as count FROM asset_categories WHERE is_active = 1");
        $stats['active_categories'] = $activeStmt->fetch()['count'];
        
        $inactiveStmt = $this->db->query("SELECT COUNT(*) as count FROM asset_categories WHERE is_active = 0");
        $stats['inactive_categories'] = $inactiveStmt->fetch()['count'];

        return $stats;
    }

    /**
     * ສ້າງໝວດໝູ່ຊັບສິນໃໝ່
     */
    public function create($data, $createdBy) {
        // ກວດສອບຂໍ້ມູນຊ້ຳ
        $stmt = $this->db->prepare("SELECT id FROM asset_categories WHERE category_code = ?");
        $stmt->execute([$data['category_code']]);
        
        if ($stmt->fetch()) {
            return ['success' => false, 'message' => 'Category code already exists'];
        }

        // ຄຳນວນລະດັບ (level) ແລະ ສ້າງ path
        $level = 1;
        $path = $data['category_name']; // ໃຊ້ຊື່ໝວດໝູ່
        
        if (!empty($data['parent_id'])) {
            // ດຶງຂໍ້ມູນພໍ່
            $parentStmt = $this->db->prepare("SELECT level, path FROM asset_categories WHERE id = ?");
            $parentStmt->execute([$data['parent_id']]);
            $parent = $parentStmt->fetch();
            
            if ($parent) {
                $level = $parent['level'] + 1;
                $path = $parent['path'] . '/' . $data['category_name'];
            } else {
                return ['success' => false, 'message' => 'Parent category not found'];
            }
        }

        // ໃຊ້ INSERT ແບບງ່າຍໆ ໂດຍບໍ່ໃຊ້ transactions ຖ້າມີ trigger
        $sql = "INSERT INTO asset_categories (
            category_code, category_name, description, parent_id, level, path,
            depreciation_method, useful_life_years, depreciation_rate,
            is_active, sort_order, created_by, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        
        $stmt = $this->db->prepare($sql);
        
        try {
            $result = $stmt->execute([
                $data['category_code'],
                $data['category_name'],
                $data['description'] ?? null,
                $data['parent_id'] ?? null,
                $level,
                $path,
                $data['depreciation_method'] ?? 'straight_line',
                $data['useful_life_years'] ?? null,
                $data['depreciation_rate'] ?? null,
                $data['is_active'] ?? 1,
                $data['sort_order'] ?? 0,
                $createdBy
            ]);
            
            if ($result) {
                return [
                    'success' => true,
                    'category_id' => $this->db->lastInsertId(),
                    'message' => 'Asset category created successfully'
                ];
            } else {
                return ['success' => false, 'message' => 'Creation failed'];
            }
        } catch (PDOException $e) {
            error_log("Create asset category failed: " . $e->getMessage());
            return ['success' => false, 'message' => 'Creation failed: ' . $e->getMessage()];
        }
    }

    /**
     * ອັບເດດຂໍ້ມູນໝວດໝູ່ຊັບສິນ
     */
    public function update($id, $data) {
        // ກວດສອບວ່າມີໝວດໝູ່ບໍ
        $category = $this->getById($id);
        if (!$category) {
            return ['success' => false, 'message' => 'Asset category not found'];
        }

        // ກວດສອບຂໍ້ມູນຊ້ຳ (ຍົກເວັ້ນ ID ປັດຈຸບັນ)
        if (isset($data['category_code'])) {
            $checkSql = "SELECT id FROM asset_categories WHERE category_code = ? AND id != ?";
            $checkStmt = $this->db->prepare($checkSql);
            $checkStmt->execute([$data['category_code'], $id]);
            
            if ($checkStmt->fetch()) {
                return ['success' => false, 'message' => 'Category code already exists'];
            }
        }

        // ກວດສອບ parent_id ຖ້າມີການປ່ຽນແປງ
        if (isset($data['parent_id']) && !empty($data['parent_id'])) {
            if ($data['parent_id'] == $id) {
                return ['success' => false, 'message' => 'Category cannot be its own parent'];
            }
            
            // ກວດສອບວ່າບໍ່ໃຫ້ເອົາລູກມາເປັນພໍ່
            $childStmt = $this->db->prepare("SELECT id FROM asset_categories WHERE parent_id = ?");
            $childStmt->execute([$id]);
            while ($child = $childStmt->fetch()) {
                if ($child['id'] == $data['parent_id']) {
                    return ['success' => false, 'message' => 'Cannot set a child category as parent'];
                }
            }
        }

        $fields = [];
        $params = [];

        $allowedFields = [
            'category_code', 'category_name', 'description', 'parent_id',
            'depreciation_method', 'useful_life_years', 'depreciation_rate',
            'is_active', 'sort_order'
        ];

        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $fields[] = "{$field} = ?";
                $params[] = $data[$field];
            }
        }

        // ຖ້າມີການປ່ຽນ parent_id ຫຼື category_name, ຕ້ອງອັບເດດ level ແລະ path ນຳ
        $needsPathUpdate = isset($data['parent_id']) || isset($data['category_name']);
        
        if ($needsPathUpdate) {
            // ຄຳນວນ level ແລະ path ໃໝ່
            if (empty($data['parent_id'])) {
                // ຖ້າບໍ່ມີພໍ່ (ກາຍເປັນລະດັບ 1)
                $fields[] = "level = 1";
                $newPath = $data['category_name'] ?? $category['category_name'];
                $fields[] = "path = ?";
                $params[] = $newPath;
            } else {
                // ມີພໍ່ ດຶງຂໍ້ມູນພໍ່ມາ
                $parentStmt = $this->db->prepare("SELECT level, path FROM asset_categories WHERE id = ?");
                $parentStmt->execute([$data['parent_id']]);
                $parent = $parentStmt->fetch();
                
                if ($parent) {
                    $newLevel = $parent['level'] + 1;
                    $categoryName = $data['category_name'] ?? $category['category_name'];
                    $newPath = $parent['path'] . '/' . $categoryName;
                    
                    $fields[] = "level = ?";
                    $fields[] = "path = ?";
                    $params[] = $newLevel;
                    $params[] = $newPath;
                }
            }
        }

        if (empty($fields)) {
            return ['success' => false, 'message' => 'No data to update'];
        }

        // ກວດສອບວ່າມີ column updated_at ບໍ
        if ($this->columnExists('updated_at')) {
            $fields[] = "updated_at = NOW()";
        }

        $params[] = $id;
        $sql = "UPDATE asset_categories SET " . implode(', ', $fields) . " WHERE id = ?";
        
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            
            // ອັບເດດລູກຫຼານທັງໝົດ (ແຍກອອກໄປອີກ statement)
            if ($needsPathUpdate) {
                $this->updateChildrenPathsAfterCommit($id);
            }
            
            return ['success' => true, 'message' => 'Asset category updated successfully'];
        } catch (PDOException $e) {
            error_log("Update asset category failed: " . $e->getMessage());
            return ['success' => false, 'message' => 'Update failed: ' . $e->getMessage()];
        }
    }

    /**
     * ອັບເດດສະຖານະໝວດໝູ່ຊັບສິນ
     */
    public function updateStatus($id, $isActive) {
        try {
            $stmt = $this->db->prepare("UPDATE asset_categories SET is_active = ? WHERE id = ?");
            $stmt->execute([$isActive, $id]);
            
            // ຖ້າປິດໝວດໝູ່, ຄວນປິດໝວດໝູ່ຍ່ອຍນຳ
            if ($isActive == 0) {
                $this->updateChildCategoriesStatus($id, 0);
            }
            
            return ['success' => true, 'message' => 'Status updated successfully'];
        } catch (PDOException $e) {
            error_log("Update status failed: " . $e->getMessage());
            return ['success' => false, 'message' => 'Update failed: ' . $e->getMessage()];
        }
    }

    /**
     * ອັບເດດສະຖານະໝວດໝູ່ຍ່ອຍທັງໝົດ
     */
    private function updateChildCategoriesStatus($parentId, $isActive) {
        $stmt = $this->db->prepare("UPDATE asset_categories SET is_active = ? WHERE parent_id = ?");
        $stmt->execute([$isActive, $parentId]);
        
        // ດຶງລາຍຊື່ລູກເພື່ອອັບເດດຕໍ່ໄປ
        $childStmt = $this->db->prepare("SELECT id FROM asset_categories WHERE parent_id = ?");
        $childStmt->execute([$parentId]);
        while ($child = $childStmt->fetch()) {
            $this->updateChildCategoriesStatus($child['id'], $isActive);
        }
    }

    /**
     * ລຶບໝວດໝູ່ຊັບສິນ (soft delete)
     */
    public function softDelete($id) {
        // ກວດສອບວ່າມີໝວດໝູ່ຍ່ອຍບໍ
        $childStmt = $this->db->prepare("SELECT COUNT(*) as count FROM asset_categories WHERE parent_id = ?");
        $childStmt->execute([$id]);
        $childCount = $childStmt->fetch()['count'];
        
        if ($childCount > 0) {
            return ['success' => false, 'message' => 'Cannot delete category with child categories'];
        }

        // ກວດສອບວ່າມີຊັບສິນທີ່ໃຊ້ໝວດໝູ່ນີ້ບໍ
        $assetStmt = $this->db->prepare("SELECT COUNT(*) as count FROM assets WHERE category_id = ?");
        $assetStmt->execute([$id]);
        $assetCount = $assetStmt->fetch()['count'];
        
        if ($assetCount > 0) {
            return ['success' => false, 'message' => 'Cannot delete category that has assets'];
        }

        try {
            $stmt = $this->db->prepare("DELETE FROM asset_categories WHERE id = ?");
            $stmt->execute([$id]);
            
            return ['success' => true, 'message' => 'Asset category deleted successfully'];
        } catch (PDOException $e) {
            error_log("Delete failed: " . $e->getMessage());
            return ['success' => false, 'message' => 'Delete failed: ' . $e->getMessage()];
        }
    }

    /**
     * ກວດສອບວ່າ column ມີຢູ່ໃນຕາຕະລາງບໍ
     */
    private function columnExists($columnName) {
        try {
            $stmt = $this->db->prepare("SHOW COLUMNS FROM asset_categories LIKE ?");
            $stmt->execute([$columnName]);
            return $stmt->fetch() ? true : false;
        } catch (PDOException $e) {
            return false;
        }
    }


    /**
     * ອັບເດດ path ຂອງລູກຫຼານທັງໝົດ
     */
    private function updateChildrenPaths($parentId, $parentPath, $parentLevel) {
        // ດຶງລູກທັງໝົດ
        $childStmt = $this->db->prepare("SELECT id, category_name FROM asset_categories WHERE parent_id = ?");
        $childStmt->execute([$parentId]);
        $children = $childStmt->fetchAll();
        
        foreach ($children as $child) {
            $newLevel = $parentLevel + 1;
            $newPath = $parentPath . '/' . $child['category_name'];
            
            // ອັບເດດລູກ
            $updateStmt = $this->db->prepare("UPDATE asset_categories SET level = ?, path = ? WHERE id = ?");
            $updateStmt->execute([$newLevel, $newPath, $child['id']]);
            
            // ອັບເດດຫຼານ (recursive)
            $this->updateChildrenPaths($child['id'], $newPath, $newLevel);
        }
    }


    /**
     * ອັບເດດ path ຂອງລູກຫຼານທັງໝົດ (ແຍກອອກໄປ)
     */
    private function updateChildrenPathsAfterCommit($parentId) {
        try {
            // ດຶງຂໍ້ມູນພໍ່
            $parentStmt = $this->db->prepare("SELECT id, path, level FROM asset_categories WHERE id = ?");
            $parentStmt->execute([$parentId]);
            $parent = $parentStmt->fetch();
            
            if (!$parent) {
                return;
            }
            
            // ດຶງລູກທັງໝົດ
            $childStmt = $this->db->prepare("SELECT id, category_name FROM asset_categories WHERE parent_id = ?");
            $childStmt->execute([$parentId]);
            $children = $childStmt->fetchAll();
            
            foreach ($children as $child) {
                $newLevel = $parent['level'] + 1;
                $newPath = $parent['path'] . '/' . $child['category_name'];
                
                // ອັບເດດລູກ
                $updateStmt = $this->db->prepare("UPDATE asset_categories SET level = ?, path = ? WHERE id = ?");
                $updateStmt->execute([$newLevel, $newPath, $child['id']]);
                
                // ອັບເດດຫຼານ (recursive)
                $this->updateChildrenPathsAfterCommit($child['id']);
            }
        } catch (PDOException $e) {
            error_log("Update children paths failed: " . $e->getMessage());
        }
    }

}
?>