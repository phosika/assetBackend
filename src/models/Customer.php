<?php
// src/models/Customer.php
require_once __DIR__ . '/../config/database.php';

class Customer
{
    private $db;
    private $table = 'customers';

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Get all customers (dropdown / non-paginated list)
     */
    public function all($activeOnly = false)
    {
        try {
            $sql = "SELECT id, name, phone, address, total_purchase, total_paid, total_due, is_active FROM " . $this->table;
            if ($activeOnly) {
                $sql .= " WHERE is_active = 1";
            }
            $sql .= " ORDER BY name ASC";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Customer::all error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get paginated and filtered customers
     */
    public function list($page = 1, $limit = 10, $search = '')
    {
        try {
            $offset = ($page - 1) * $limit;
            $params = [];
            
            // Build query where clause
            $whereClause = "";
            if (!empty($search)) {
                $whereClause = " WHERE name LIKE :search OR phone LIKE :search";
                $params[':search'] = '%' . $search . '%';
            }
            
            // Get total count
            $countSql = "SELECT COUNT(*) FROM " . $this->table . $whereClause;
            $countStmt = $this->db->prepare($countSql);
            $countStmt->execute($params);
            $total = $countStmt->fetchColumn();
            
            // Get records
            $sql = "SELECT id, name, phone, address, total_purchase, total_paid, total_due, is_active, created_at 
                    FROM " . $this->table . $whereClause . " 
                    ORDER BY id DESC 
                    LIMIT :limit OFFSET :offset";
            
            $stmt = $this->db->prepare($sql);
            
            // Bind search param if any
            if (!empty($search)) {
                $stmt->bindValue(':search', '%' . $search . '%', PDO::PARAM_STR);
            }
            $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
            $stmt->execute();
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return [
                'items' => $items,
                'pagination' => [
                    'total' => (int)$total,
                    'page' => (int)$page,
                    'limit' => (int)$limit,
                    'pages' => ceil($total / $limit)
                ]
            ];
        } catch (PDOException $e) {
            error_log("Customer::list error: " . $e->getMessage());
            return ['items' => [], 'pagination' => ['total' => 0, 'page' => $page, 'limit' => $limit, 'pages' => 0]];
        }
    }

    /**
     * Find customer by ID
     */
    public function findById($id)
    {
        try {
            $stmt = $this->db->prepare("SELECT * FROM " . $this->table . " WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => (int)$id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Customer::findById error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Create customer
     */
    public function create($data)
    {
        try {
            $stmt = $this->db->prepare("INSERT INTO " . $this->table . " 
                (name, phone, address, total_purchase, total_paid, total_due, is_active, created_by, created_at, updated_at) 
                VALUES (:name, :phone, :address, :total_purchase, :total_paid, :total_due, :is_active, :created_by, NOW(), NOW())");
            
            $stmt->execute([
                ':name' => $data['name'],
                ':phone' => $data['phone'] ?? null,
                ':address' => $data['address'] ?? null,
                ':total_purchase' => $data['total_purchase'] ?? 0.00,
                ':total_paid' => $data['total_paid'] ?? 0.00,
                ':total_due' => $data['total_due'] ?? 0.00,
                ':is_active' => isset($data['is_active']) ? (int)$data['is_active'] : 1,
                ':created_by' => $data['created_by'] ?? null
            ]);
            
            return $this->db->lastInsertId();
        } catch (PDOException $e) {
            error_log("Customer::create error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update customer
     */
    public function update($id, $data)
    {
        try {
            $stmt = $this->db->prepare("UPDATE " . $this->table . " 
                SET name = :name, phone = :phone, address = :address, 
                    is_active = :is_active, updated_at = NOW() 
                WHERE id = :id");
            
            return $stmt->execute([
                ':id' => (int)$id,
                ':name' => $data['name'],
                ':phone' => $data['phone'] ?? null,
                ':address' => $data['address'] ?? null,
                ':is_active' => isset($data['is_active']) ? (int)$data['is_active'] : 1
            ]);
        } catch (PDOException $e) {
            error_log("Customer::update error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete customer
     */
    public function delete($id)
    {
        try {
            $stmt = $this->db->prepare("DELETE FROM " . $this->table . " WHERE id = :id");
            return $stmt->execute([':id' => (int)$id]);
        } catch (PDOException $e) {
            error_log("Customer::delete error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update customer transaction totals (e.g. on checkout or repayment)
     */
    public function updateTotals($id, $purchaseAmount, $paidAmount, $dueAmount)
    {
        try {
            $stmt = $this->db->prepare("UPDATE " . $this->table . " 
                SET total_purchase = total_purchase + :purchase_amount,
                    total_paid = total_paid + :paid_amount,
                    total_due = total_due + :due_amount,
                    updated_at = NOW()
                WHERE id = :id");
            
            return $stmt->execute([
                ':id' => (int)$id,
                ':purchase_amount' => (float)$purchaseAmount,
                ':paid_amount' => (float)$paidAmount,
                ':due_amount' => (float)$dueAmount
            ]);
        } catch (PDOException $e) {
            error_log("Customer::updateTotals error: " . $e->getMessage());
            return false;
        }
    }
}
