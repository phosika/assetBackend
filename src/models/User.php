<?php
// src/models/User.php
require_once __DIR__ . '/../config/database.php';

class User
{
    private $db;
    private $table = 'users';

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * ດຶງຂໍ້ມູນຜູ້ໃຊ້ຕາມ ID
     * @param int $id ລະຫັດຜູ້ໃຊ້
     * @param bool $includeSensitive ລວມຂໍ້ມູນທີ່ອ່ອນໄຫວ (password)
     * @return array|false
     */
    public function findById($id, $includeSensitive = false)
    {
        // Force $id to be an integer
        $id = (int)$id;

        if ($includeSensitive) {
            $sql = "SELECT * FROM users WHERE id = :id";
        } else {
            $sql = "SELECT id, full_name, email, phone, avatar, role, created_at FROM users WHERE id = :id";
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * ດຶງຂໍ້ມູນຜູ້ໃຊ້ຕາມ Email (ສຳຄັນສຳລັບ Login)
     * @param string $email ອີເມວຜູ້ໃຊ້
     * @return array|false
     */
    public function findByEmail($email)
    {
        try {
            if (empty($email)) {
                return false;
            }

            $stmt = $this->db->prepare("SELECT * FROM " . $this->table . " WHERE email = :email LIMIT 1");
            $stmt->bindParam(':email', $email);
            $stmt->execute();

            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$result) {
                return false;
            }

            return $result;
        } catch (PDOException $e) {
            error_log("findByEmail error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * ດຶງຂໍ້ມູນຜູ້ໃຊ້ຕາມ Username
     * @param string $username ຊື່ຜູ້ໃຊ້
     * @return array|false
     */
    public function findByUsername($username)
    {
        try {
            if (empty($username)) {
                return false;
            }

            $stmt = $this->db->prepare("SELECT * FROM " . $this->table . " WHERE username = :username LIMIT 1");
            $stmt->bindParam(':username', $username);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("findByUsername error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * ດຶງຂໍ້ມູນຜູ້ໃຊ້ຕາມ Username ຫຼື Email
     * @param string $username ຊື່ຜູ້ໃຊ້
     * @param string $email ອີເມວຜູ້ໃຊ້
     * @return array|false
     */
    public function findByUsernameOrEmail($username, $email)
    {
        try {
            $stmt = $this->db->prepare("SELECT * FROM " . $this->table . " WHERE username = :username OR email = :email LIMIT 1");
            $stmt->bindParam(':username', $username);
            $stmt->bindParam(':email', $email);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("findByUsernameOrEmail error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * ອັບເດດຂໍ້ມູນຜູ້ໃຊ້
     * @param int $id ລະຫັດຜູ້ໃຊ້
     * @param array $data ຂໍ້ມູນທີ່ຕ້ອງການອັບເດດ
     * @return bool
     */
    public function update($id, $data)
    {
        try {
            if (empty($id) || !is_numeric($id) || empty($data)) {
                return false;
            }

            // ເອົາຂໍ້ມູນທີ່ບໍ່ຄວນອັບເດດອອກ
            unset($data['id'], $data['created_at']);

            // ຈັດການເລື່ອງ Password (ຖ້າມີ)
            if (isset($data['password'])) {
                $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
            }

            // ສ້າງ Query dynamically
            $fields = "";
            $params = [];

            foreach ($data as $key => $value) {
                $fields .= $key . " = :" . $key . ", ";
                $params[':' . $key] = $value;
            }
            $fields = rtrim($fields, ", ");

            $sql = "UPDATE " . $this->table . " SET " . $fields . " WHERE id = :id";
            $params[':id'] = (int)$id;

            $stmt = $this->db->prepare($sql);
            return $stmt->execute($params);
            
        } catch (PDOException $e) {
            error_log("Update error: " . $e->getMessage());
            return false;
        }
    }
    /**
     * ລຶບຜູ້ໃຊ້
     * @param int $id ລະຫັດຜູ້ໃຊ້
     * @return bool
     */
    public function delete($id)
    {
        try {
            if (empty($id) || !is_numeric($id)) {
                return false;
            }

            // ກວດສອບວ່າຜູ້ໃຊ້ມີຢູ່ກ່ອນລຶບ
            $checkStmt = $this->db->prepare("SELECT id FROM " . $this->table . " WHERE id = :id");
            $checkStmt->bindParam(':id', $id, PDO::PARAM_INT);
            $checkStmt->execute();

            if ($checkStmt->rowCount() === 0) {
                return false;
            }

            $stmt = $this->db->prepare("DELETE FROM " . $this->table . " WHERE id = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Delete error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * ສ້າງຜູ້ໃຊ້ໃໝ່
     * @param array $data ຂໍ້ມູນຜູ້ໃຊ້
     * @return int|false ລະຫັດຜູ້ໃຊ້ທີ່ສ້າງ ຫຼື false ຖ້າລົ້ມເຫຼວ
     */
 
    public function create($data) {
        try {
            // 1. ກວດສອບຂໍ້ມູນທີ່ຈຳເປັນ
            if (!isset($data['email']) || empty($data['email'])) {
                error_log("Create error: Email is required");
                return false;
            }
            if (!isset($data['password']) || empty($data['password'])) {
                error_log("Create error: Password is required");
                return false;
            }

            // 2. ກວດສອບວ່າ email ມີຢູ່ແລ້ວບໍ່ (ໃຊ້ exists ໃໝ່)
            if ($this->exists($data['email'])) {
                error_log("Create error: Email already exists");
                return false;
            }

            // 3. Hash password
            $passwordHash = password_hash($data['password'], PASSWORD_DEFAULT);
            if (!$passwordHash) {
                error_log("Create error: Password hashing failed");
                return false;
            }

            // 4. ກຽມຂໍ້ມູນ
            $fullName = isset($data['full_name']) ? trim($data['full_name']) : null;
            $phone = isset($data['phone']) ? trim($data['phone']) : null;
            $role = isset($data['role']) ? $data['role'] : 'user';
            $avatar = isset($data['avatar']) ? $data['avatar'] : null;
            
            // ປ່ຽນ status ເປັນ is_active (tinyint)
            // ຖ້າສົ່ງ status ມາເປັນ 'active' ຫຼື 1, ຈະເປັນ 1, ອື່ນໆ 0
            $isActive = 1; // ຄ່າເລີ່ມຕົ້ນ
            if (isset($data['status'])) {
                if (is_string($data['status'])) {
                    $isActive = ($data['status'] === 'active' || $data['status'] === '1' || $data['status'] === 'true') ? 1 : 0;
                } else {
                    $isActive = (int)$data['status'] ? 1 : 0;
                }
            }

            // 5. ສ້າງ SQL - ໃຊ້ is_active ແທນ status
            $sql = "INSERT INTO " . $this->table . " 
                    (full_name, email, password, phone, role, avatar, is_active, created_at) 
                    VALUES 
                    (:full_name, :email, :password, :phone, :role, :avatar, :is_active, NOW())";

            $stmt = $this->db->prepare($sql);
            if (!$stmt) {
                error_log("Create error: Prepare failed: " . print_r($this->db->errorInfo(), true));
                return false;
            }

            // 6. Bind parameters
            $stmt->bindParam(':full_name', $fullName);
            $stmt->bindParam(':email', $data['email']);
            $stmt->bindParam(':password', $passwordHash);
            $stmt->bindParam(':phone', $phone);
            $stmt->bindParam(':role', $role);
            $stmt->bindParam(':avatar', $avatar);
            $stmt->bindParam(':is_active', $isActive, PDO::PARAM_INT);

            // 7. Execute
            if ($stmt->execute()) {
                $lastId = $this->db->lastInsertId();
                if ($lastId && $lastId > 0) {
                    error_log("Create success: User ID " . $lastId . " created");
                    return (int)$lastId;
                } else {
                    error_log("Create error: LastInsertId returned " . $lastId);
                    return false;
                }
            } else {
                error_log("Create error: Execute failed: " . print_r($stmt->errorInfo(), true));
                return false;
            }

        } catch (PDOException $e) {
            error_log("Create PDO Error: " . $e->getMessage());
            error_log("SQL State: " . $e->getCode());
            return false;
        } catch (Exception $e) {
            error_log("Create Exception: " . $e->getMessage());
            return false;
        }
    }

    /**
     * ກວດສອບວ່າ username ຫຼື email ມີຢູ່ແລ້ວຫຼືບໍ່
     * @param string $username ຊື່ຜູ້ໃຊ້
     * @param string $email ອີເມວຜູ້ໃຊ້
     * @param int|null $excludeId ລະຫັດຜູ້ໃຊ້ທີ່ຕ້ອງການຍົກເວັ້ນ
     * @return bool
     */
    public function exists($email, $excludeId = null) {
        try {
            $sql = "SELECT id FROM " . $this->table . " WHERE email = :email";
            $params = [':email' => $email];

            if ($excludeId) {
                $sql .= " AND id != :exclude_id";
                $params[':exclude_id'] = $excludeId;
            }

            $stmt = $this->db->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->execute();

            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log("Exists error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * ດຶງລາຍຊື່ຜູ້ໃຊ້ທັງໝົດ
     * @param int|null $limit ຈຳນວນທີ່ຕ້ອງການ
     * @param int|null $offset ຕຳແໜ່ງເລີ່ມຕົ້ນ
     * @return array
     */
    public function getAll($limit = null, $offset = null)
    {
        try {
            $sql = "SELECT id, email, full_name, phone, role, is_active, created_at FROM " . $this->table;
            $sql .= " ORDER BY id DESC";

            if ($limit !== null) {
                $sql .= " LIMIT :limit";
                if ($offset !== null) {
                    $sql .= " OFFSET :offset";
                }
            }

            $stmt = $this->db->prepare($sql);

            if ($limit !== null) {
                $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
                if ($offset !== null) {
                    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
                }
            }

            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("GetAll error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * ນັບຈຳນວນຜູ້ໃຊ້ທັງໝົດ
     * @return int
     */
    public function count()
    {
        try {
            $stmt = $this->db->query("SELECT COUNT(*) as total FROM " . $this->table);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return (int)($result['total'] ?? 0);
        } catch (PDOException $e) {
            error_log("Count error: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * ນັບຈຳນວນ Admin
     * @return int
     */
    public function countAdmins()
    {
        try {
            $stmt = $this->db->prepare("SELECT COUNT(*) as total FROM " . $this->table . " WHERE role = 'admin'");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return (int)($result['total'] ?? 0);
        } catch (PDOException $e) {
            error_log("CountAdmins error: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * ນັບຈຳນວນຜູ້ໃຊ້ຕາມສະຖານະ
     * @param string $status ສະຖານະຜູ້ໃຊ້
     * @return int
     */
    public function countByStatus($status)
    {
        try {
            $stmt = $this->db->prepare("SELECT COUNT(*) as total FROM " . $this->table . " WHERE status = :status");
            $stmt->bindParam(':status', $status);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return (int)($result['total'] ?? 0);
        } catch (PDOException $e) {
            error_log("CountByStatus error: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * ອັບເດດສະຖານະຜູ້ໃຊ້
     * @param int $id ລະຫັດຜູ້ໃຊ້
     * @param string $status ສະຖານະໃໝ່
     * @return bool
     */
    public function updateStatus($id, $status)
    {
        try {
            if (empty($id) || !is_numeric($id)) {
                return false;
            }

            $allowedStatus = ['active', 'suspended', 'inactive'];
            if (!in_array($status, $allowedStatus)) {
                return false;
            }

            $stmt = $this->db->prepare("UPDATE " . $this->table . " SET status = :status WHERE id = :id");
            $stmt->bindParam(':status', $status);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("UpdateStatus error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * ຊອກຫາຜູ້ໃຊ້ຕາມຄຳຄົ້ນຫາ
     * @param string $keyword ຄຳຄົ້ນຫາ
     * @param int|null $limit ຈຳນວນທີ່ຕ້ອງການ
     * @return array
     */
    public function search($keyword, $limit = null)
    {
        try {
            if (empty($keyword)) {
                return [];
            }

            $searchTerm = '%' . $keyword . '%';
            $sql = "SELECT id, email, full_name, phone, role, is_active, created_at 
                    FROM " . $this->table . " 
                    WHERE email LIKE :keyword 
                    OR full_name LIKE :keyword
                    ORDER BY id DESC";

            if ($limit !== null) {
                $sql .= " LIMIT :limit";
            }

            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':keyword', $searchTerm);

            if ($limit !== null) {
                $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            }

            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Search error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * ດຶງຜູ້ໃຊ້ສຳລັບ dropdown
     * @return array
     */
    public function getForDropdown()
    {
        try {
            $stmt = $this->db->query("SELECT id, username, email, full_name, role FROM " . $this->table . " WHERE status = 'active' ORDER BY username ASC");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("GetForDropdown error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * ກວດສອບລະຫັດຜ່ານ
     * @param int $id ລະຫັດຜູ້ໃຊ້
     * @param string $password ລະຫັດຜ່ານທີ່ຕ້ອງການກວດສອບ
     * @return bool
     */
    public function verifyPassword($id, $password)
    {
        try {
            if (empty($id) || !is_numeric($id) || empty($password)) {
                return false;
            }

            $stmt = $this->db->prepare("SELECT password_hash FROM " . $this->table . " WHERE id = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();

            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$result) {
                return false;
            }

            return password_verify($password, $result['password_hash']);
        } catch (PDOException $e) {
            error_log("VerifyPassword error: " . $e->getMessage());
            return false;
        }
    }
}
