<?php
// /controllers/RoleController.php

require_once __DIR__ . '/../models/Role.php';

class RoleController {
    private $roleModel;
    
    public function __construct() {
        global $db;
        $this->roleModel = new Role($db);
    }
    
    // GET /api/roles - ດຶງຂໍ້ມູນບົດບາດທັງໝົດ
    public function getAll() {
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
        $search = isset($_GET['search']) ? $_GET['search'] : '';
        $status = isset($_GET['status']) ? $_GET['status'] : '';
        
        $result = $this->roleModel->getAll($page, $limit, $search, $status);
        
        Response::success([
            'roles' => $result['data'],
            'pagination' => [
                'current_page' => $result['page'],
                'per_page' => $result['per_page'],
                'total' => $result['total'],
                'total_pages' => $result['total_pages']
            ]
        ], 200, 'Roles retrieved successfully');
    }
    
    // GET /api/roles/dropdown - ດຶງຂໍ້ມູນສຳລັບ dropdown
    public function getForDropdown() {
        $roles = $this->roleModel->getForDropdown();
        Response::success($roles, 200, 'Roles retrieved successfully');
    }
    
    // GET /api/roles/stats - ດຶງສະຖິຕິ
    public function getStats() {
        $stats = $this->roleModel->getStats();
        Response::success($stats, 200, 'Stats retrieved successfully');
    }
    
    // GET /api/roles/search - ຄົ້ນຫາ
    public function search() {
        $keyword = isset($_GET['q']) ? $_GET['q'] : '';
        $roles = $this->roleModel->search($keyword);
        Response::success($roles, 200, 'Search completed');
    }
    
    // GET /api/roles/{id} - ດຶງຂໍ້ມູນຕາມ ID
    public function getById($id) {
        $role = $this->roleModel->getById($id);
        
        if (!$role) {
            Response::error('Role not found', 404);
            return;
        }
        
        Response::success($role, 200, 'Role retrieved successfully');
    }
    
    // GET /api/roles/{id}/permissions - ດຶງສິດທິ
    public function getPermissions($id) {
        // ກວດສອບວ່າບົດບາດມີຢູ່ບໍ່
        $role = $this->roleModel->getById($id);
        if (!$role) {
            Response::error('Role not found', 404);
            return;
        }
        
        $permissions = $this->roleModel->getPermissions($id);
        Response::success($permissions, 200, 'Permissions retrieved successfully');
    }
    
    // POST /api/roles - ສ້າງບົດບາດໃໝ່
    public function create() {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data['name'])) {
            Response::error('Role name is required', 400);
            return;
        }
        
        // ກວດສອບຊື່ຊ້ຳ
        if ($this->roleModel->isNameExists($data['name'])) {
            Response::error('Role name already exists', 400);
            return;
        }
        
        $id = $this->roleModel->create($data);
        
        if ($id) {
            $newRole = $this->roleModel->getById($id);
            Response::success($newRole, 201, 'Role created successfully');
        } else {
            Response::error('Failed to create role', 500);
        }
    }
    
    // PUT /api/roles/{id} - ອັບເດດບົດບາດ
    public function update($id) {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data['name'])) {
            Response::error('Role name is required', 400);
            return;
        }
        
        // ກວດສອບວ່າບົດບາດມີຢູ່ບໍ່
        $role = $this->roleModel->getById($id);
        if (!$role) {
            Response::error('Role not found', 404);
            return;
        }
        
        // ກວດສອບຊື່ຊ້ຳ (ຍົກເວັ້ນຕົວມັນເອງ)
        if ($this->roleModel->isNameExists($data['name'], $id)) {
            Response::error('Role name already exists', 400);
            return;
        }
        
        $updated = $this->roleModel->update($id, $data);
        
        if ($updated) {
            $updatedRole = $this->roleModel->getById($id);
            Response::success($updatedRole, 200, 'Role updated successfully');
        } else {
            Response::error('Failed to update role', 500);
        }
    }
    
    // PATCH /api/roles/{id}/status - ປ່ຽນສະຖານະ
    public function updateStatus($id) {
        $data = json_decode(file_get_contents('php://input'), true);
        $status = $data['status'] ?? '';
        
        if (!in_array($status, ['active', 'inactive'])) {
            Response::error('Invalid status. Must be active or inactive', 400);
            return;
        }
        
        // ກວດສອບວ່າບົດບາດມີຢູ່ບໍ່
        $role = $this->roleModel->getById($id);
        if (!$role) {
            Response::error('Role not found', 404);
            return;
        }
        
        $updated = $this->roleModel->updateStatus($id, $status);
        
        if ($updated) {
            Response::success(null, 200, 'Role status updated successfully');
        } else {
            Response::error('Failed to update status', 500);
        }
    }
    
    // POST /api/roles/{id}/duplicate - ຄັດລອກບົດບາດ
    public function duplicate($id) {
        // ກວດສອບວ່າບົດບາດມີຢູ່ບໍ່
        $role = $this->roleModel->getById($id);
        if (!$role) {
            Response::error('Role not found', 404);
            return;
        }
        
        $newId = $this->roleModel->duplicate($id);
        
        if ($newId) {
            $newRole = $this->roleModel->getById($newId);
            Response::success($newRole, 201, 'Role duplicated successfully');
        } else {
            Response::error('Failed to duplicate role', 500);
        }
    }
    
    // POST /api/roles/{id}/permissions - ບັນທຶກສິດທິ
    public function savePermissions($id) {
        $data = json_decode(file_get_contents('php://input'), true);
        $permissions = $data['permissions'] ?? [];
        
        // ກວດສອບວ່າບົດບາດມີຢູ່ບໍ່
        $role = $this->roleModel->getById($id);
        if (!$role) {
            Response::error('Role not found', 404);
            return;
        }
        
        $saved = $this->roleModel->savePermissions($id, $permissions);
        
        if ($saved) {
            Response::success(null, 200, 'Permissions saved successfully');
        } else {
            Response::error('Failed to save permissions', 500);
        }
    }
    
    // DELETE /api/roles/{id} - ລຶບບົດບາດ
    public function delete($id) {
        // ກວດສອບວ່າບົດບາດມີຢູ່ບໍ່
        $role = $this->roleModel->getById($id);
        if (!$role) {
            Response::error('Role not found', 404);
            return;
        }
        
        $result = $this->roleModel->delete($id);
        
        if (isset($result['error'])) {
            Response::error($result['error'], 400, ['user_count' => $result['user_count'] ?? 0]);
            return;
        }
        
        Response::success(null, 200, 'Role deleted successfully');
    }
}
?>