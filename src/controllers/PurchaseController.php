<?php


require_once __DIR__ . '/../models/PurchaseOrder.php';
require_once __DIR__ . '/../models/Supplier.php';
require_once __DIR__ . '/../models/InventoryItem.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../utils/Response.php';

class PurchaseController {
    private $purchaseModel;
    private $supplierModel;
    private $itemModel;
    private $userModel;
    private $db;

    public function __construct() {
        $this->purchaseModel = new PurchaseOrder();
        $this->supplierModel = new Supplier();
        $this->itemModel = new InventoryItem();
        $this->userModel = new User();
        
        // ເຊື່ອມຕໍ່ database ໂດຍກົງສຳລັບການ query ພິເສດ
        require_once __DIR__ . '/../config/database.php';
        $this->db = Database::getInstance();
    }

    private function getCurrentUser() {
        try {
            $payload = AuthMiddleware::authenticate();
            $userId = $payload['user_id'] ?? $payload['id'] ?? null;
            if (!$userId) {
                return null;
            }
            return $this->userModel->getById($userId);
        } catch (Exception $e) {
            return null;
        }
    }

    private function checkPermission($user, $allowedRoles = ['super_admin', 'asset_admin', 'inventory_manager']) {
        if (!$user || !in_array($user['role'] ?? '', $allowedRoles)) {
            Response::forbidden('ທ່ານບໍ່ມີສິດດຳເນີນການນີ້');
        }
    }

    /**
     * GET /purchases - ດຶງຂໍ້ມູນໃບສັ່ງຊື້ທັງໝົດ
     */
    public function getAllPurchaseOrders() {
        try {
            $user = $this->getCurrentUser();
            
            $filters = [
                'search' => $_GET['search'] ?? '',
                'status' => $_GET['status'] ?? '',
                'payment_status' => $_GET['payment_status'] ?? '',
                'supplier_id' => isset($_GET['supplier_id']) ? (int)$_GET['supplier_id'] : null,
                'from_date' => $_GET['from_date'] ?? null,
                'to_date' => $_GET['to_date'] ?? null,
                'page' => isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1,
                'limit' => isset($_GET['limit']) ? min(100, (int)$_GET['limit']) : 20,
                'sort_by' => $_GET['sort_by'] ?? 'po.created_at',
                'sort_order' => $_GET['sort_order'] ?? 'DESC'
            ];

            $result = $this->purchaseModel->getAllPurchaseOrders($filters);

            Response::success([
                'purchase_orders' => $result['data'],
                'pagination' => [
                    'current_page' => $result['current_page'],
                    'per_page' => $result['per_page'],
                    'total' => $result['total'],
                    'total_pages' => $result['last_page']
                ]
            ], 'ດຶງຂໍ້ມູນໃບສັ່ງຊື້ສຳເລັດ');

        } catch (Exception $e) {
            error_log("Error in getAllPurchaseOrders: " . $e->getMessage());
            Response::error('ດຶງຂໍ້ມູນບໍ່ສຳເລັດ: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST /purchases/{id}/approve - ອະນຸມັດໃບສັ່ງຊື້
     */
    public function approvePurchaseOrder($id) {
        try {
            $user = $this->getCurrentUser();
            $this->checkPermission($user);

            if (!is_numeric($id)) {
                Response::error('ID ບໍ່ຖືກຕ້ອງ', 400);
                return;
            }

            // ຮັບຂໍ້ມູນຈາກ request
            $data = json_decode(file_get_contents('php://input'), true);
            $status = $data['status'] ?? 'approved';

            error_log("=== APPROVING PURCHASE ORDER ===");
            error_log("PO ID: " . $id);
            error_log("Status to set: " . $status);
            error_log("User ID: " . ($user['id'] ?? 'null'));

            // ກວດສອບວ່າໃບສັ່ງຊື້ມີຢູ່ ແລະ ສາມາດອະນຸມັດໄດ້
            $purchaseOrder = $this->purchaseModel->getPurchaseOrderById($id);
            
            if (!$purchaseOrder) {
                error_log("PO not found with ID: " . $id);
                Response::notFound('ບໍ່ພົບໃບສັ່ງຊື້');
                return;
            }

            error_log("Current PO status: " . $purchaseOrder['status']);

            if ($purchaseOrder['status'] !== 'draft') {
                error_log("Cannot approve: Current status is " . $purchaseOrder['status']);
                Response::error('ສາມາດອະນຸມັດໄດ້ສະເພາະໃບສັ່ງຊື້ທີ່ຢູ່ໃນສະຖານະຮ່າງເທົ່ານັ້ນ', 400);
                return;
            }

            // ອັບເດດສະຖານະ
            $result = $this->purchaseModel->updateStatus($id, $status, $user['id'] ?? null);

            error_log("Update result: " . json_encode($result));

            if ($result['success']) {
                // ດຶງຂໍ້ມູນໃໝ່ມາກວດສອບ
                $updatedPO = $this->purchaseModel->getPurchaseOrderById($id);
                error_log("Updated PO status: " . ($updatedPO['status'] ?? 'unknown'));
                
                Response::success([
                    'id' => $id,
                    'status' => $status,
                    'updated_status' => $updatedPO['status'] ?? null
                ], $result['message']);
            } else {
                Response::error($result['message'], 400);
            }

        } catch (Exception $e) {
            error_log("Error in approvePurchaseOrder: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            Response::error('ອະນຸມັດໃບສັ່ງຊື້ບໍ່ສຳເລັດ: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /purchases/stats - ດຶງສະຖິຕິ
     */
    public function getPurchaseStats() {
        try {
            $user = $this->getCurrentUser();
            $stats = $this->purchaseModel->getPurchaseStats();
            Response::success($stats, 'ດຶງສະຖິຕິສຳເລັດ');

        } catch (Exception $e) {
            error_log("Error in getPurchaseStats: " . $e->getMessage());
            Response::error('ດຶງສະຖິຕິບໍ່ສຳເລັດ: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /purchases/{id} - ດຶງຂໍ້ມູນໃບສັ່ງຊື້ຕາມ ID
     */
    public function getPurchaseOrderById($id) {
        try {
            $user = $this->getCurrentUser();

            if (!is_numeric($id)) {
                Response::error('ID ບໍ່ຖືກຕ້ອງ', 400);
                return;
            }

            error_log("Fetching purchase order with ID: " . $id);
            
            $purchaseOrder = $this->purchaseModel->getPurchaseOrderById($id);

            if (!$purchaseOrder) {
                error_log("Purchase order not found with ID: " . $id);
                Response::notFound('ບໍ່ພົບໃບສັ່ງຊື້');
                return;
            }

            error_log("Found purchase order: " . json_encode($purchaseOrder));
            Response::success($purchaseOrder, 'ດຶງຂໍ້ມູນສຳເລັດ');

        } catch (Exception $e) {
            error_log("Error in getPurchaseOrderById: " . $e->getMessage());
            Response::error('ດຶງຂໍ້ມູນບໍ່ສຳເລັດ: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /purchases/by-number/{poNumber} - ດຶງຂໍ້ມູນຕາມເລກທີ PO
     */
    public function getPurchaseOrderByNumber($poNumber) {
        try {
            $user = $this->getCurrentUser();

            if (empty($poNumber)) {
                Response::error('ເລກທີໃບສັ່ງຊື້ບໍ່ຖືກຕ້ອງ', 400);
                return;
            }

            $purchaseOrder = $this->purchaseModel->getPurchaseOrderByNumber($poNumber);

            if (!$purchaseOrder) {
                Response::notFound('ບໍ່ພົບໃບສັ່ງຊື້');
                return;
            }

            Response::success($purchaseOrder, 'ດຶງຂໍ້ມູນສຳເລັດ');

        } catch (Exception $e) {
            error_log("Error in getPurchaseOrderByNumber: " . $e->getMessage());
            Response::error('ດຶງຂໍ້ມູນບໍ່ສຳເລັດ: ' . $e->getMessage(), 500);
        }
    }

 
    /**
     * GET /purchase-order-details - ດຶງລາຍການສິນຄ້າຕາມ PO ID
     */
    public function getPurchaseOrderDetails() {
        try {
            $user = $this->getCurrentUser();
            
            $poId = $_GET['po_id'] ?? null;
            
            if (!$poId) {
                Response::error('ກະລຸນາລະບຸ po_id', 400);
                return;
            }
            
            error_log("Fetching purchase order details for PO ID: " . $poId);
            
            // ລຶບ i.unit ອອກ ເພາະບໍ່ມີ column ນີ້ໃນຕາຕະລາງ
            $sql = "SELECT pod.*, 
                        i.item_code, 
                        i.item_name
                    FROM purchase_order_details pod
                    LEFT JOIN inventory_items i ON pod.item_id = i.id
                    WHERE pod.po_id = ?
                    ORDER BY pod.id ASC";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$poId]);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            error_log("Found " . count($items) . " items for PO ID: " . $poId);
            
            Response::success($items, 'ດຶງຂໍ້ມູນສຳເລັດ');
            
        } catch (Exception $e) {
            error_log("Error in getPurchaseOrderDetails: " . $e->getMessage());
            Response::error('ດຶງຂໍ້ມູນບໍ່ສຳເລັດ: ' . $e->getMessage(), 500);
        }
    }


    // /**
    //  * POST /purchases - ສ້າງໃບສັ່ງຊື້ໃໝ່
    //  */
    // public function createPurchaseOrder() {
    //     try {
    //         $user = $this->getCurrentUser();
    //         $this->checkPermission($user);

    //         $data = json_decode(file_get_contents('php://input'), true);

    //         // ກວດສອບຂໍ້ມູນທີ່ຈຳເປັນ
    //         $required = ['po_number', 'supplier_id', 'order_date', 'items'];
    //         foreach ($required as $field) {
    //             if (empty($data[$field])) {
    //                 Response::error("ກະລຸນາປ້ອນຂໍ້ມູນ {$field} ໃຫ້ຄົບ", 400);
    //                 return;
    //             }
    //         }

    //         if (!is_array($data['items']) || count($data['items']) === 0) {
    //             Response::error('ກະລຸນາເພີ່ມລາຍການສິນຄ້າຢ່າງໜ້ອຍ 1 ລາຍການ', 400);
    //             return;
    //         }

    //         $result = $this->purchaseModel->createPurchaseOrder($data, $user['id'] ?? null);

    //         if ($result['success']) {
    //             Response::success([
    //                 'id' => $result['id'],
    //                 'po_number' => $result['po_number']
    //             ], $result['message'], 201);
    //         } else {
    //             Response::error($result['message'], 400);
    //         }

    //     } catch (Exception $e) {
    //         error_log("Error in createPurchaseOrder: " . $e->getMessage());
    //         Response::error('ສ້າງໃບສັ່ງຊື້ບໍ່ສຳເລັດ: ' . $e->getMessage(), 500);
    //     }
    // }

    /**
     * ສ້າງໃບສັ່ງຊື້ໃໝ່
     */
    // public function createPurchaseOrder() {
    //     try {
    //         // ກວດສອບ authentication
    //         $userId = AuthMiddleware::authenticate();
            
    //         if (!$userId) {
    //             Response::error('Unauthorized', 401);
    //             return;
    //         }
            
    //         // ຮັບຂໍ້ມູນ
    //         $input = json_decode(file_get_contents('php://input'), true);
            
    //         if (!$input) {
    //             Response::error('Invalid input data', 400);
    //             return;
    //         }
            
    //         // ກວດສອບຂໍ້ມູນທີ່ຈຳເປັນ
    //         if (empty($input['po_number'])) {
    //             // ຖ້າບໍ່ມີເລກທີ່, ສ້າງອັດຕະໂນມັດ
    //             $input['po_number'] = $this->generatePurchaseOrderNumber();
    //         }
            
    //         // ກວດສອບວ່າເລກທີ່ຖືກຕ້ອງຕາມຮູບແບບ ຫຼື ບໍ່
    //         if (!preg_match('/^PU-\d{4}-\d{3}$/', $input['po_number'])) {
    //             Response::error('Invalid purchase order number format. Expected: PU-YYYY-XXX', 400);
    //             return;
    //         }
            
    //         // ສ້າງໃບສັ່ງຊື້
    //         $purchaseOrder = new PurchaseOrder();
    //         $result = $purchaseOrder->createPurchaseOrder($input, $userId);
            
    //         if ($result['success']) {
    //             Response::success([
    //                 'id' => $result['id'],
    //                 'po_number' => $result['po_number']
    //             ], 201, $result['message']);
    //         } else {
    //             Response::error($result['message'], 400);
    //         }
            
    //     } catch (Exception $e) {
    //         error_log("Error in createPurchaseOrder: " . $e->getMessage());
    //         Response::error('Server error: ' . $e->getMessage(), 500);
    //     }
    // }

    /**
     * ສ້າງເລກທີ່ໃບສັ່ງຊື້ອັດຕະໂນມັດ
     */
    /**
     * ສ້າງເລກທີ່ໃບສັ່ງຊື້ອັດຕະໂນມັດ
     */
    private function generatePurchaseOrderNumber() {
        $year = date('Y');
        $prefix = 'PU';
        
        try {
            $db = Database::getInstance();
            
            // ວິທີທີ 1: ນັບໂດຍໃຊ້ po_number ໂດຍກົງ (ວິທີນີ້ງ່າຍທີ່ສຸດ)
            $stmt = $db->prepare("
                SELECT COUNT(*) as count 
                FROM purchase_orders 
                WHERE po_number LIKE ?
            ");
            
            $pattern = "{$prefix}-{$year}-%";
            $stmt->execute([$pattern]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $count = $result ? (int)$result['count'] : 0;
            
            // ເພີ່ມການປ້ອງກັນຊໍ້າກັນ
            $maxAttempts = 10;
            $attempt = 0;
            
            while ($attempt < $maxAttempts) {
                $nextNumber = $count + 1;
                $poNumber = $prefix . '-' . $year . '-' . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
                
                // ກວດສອບວ່າເລກທີ່ນີ້ມີໃນລະບົບແລ້ວບໍ
                $checkStmt = $db->prepare("SELECT id FROM purchase_orders WHERE po_number = ?");
                $checkStmt->execute([$poNumber]);
                $existing = $checkStmt->fetch();
                
                if (!$existing) {
                    // ຖ້າບໍ່ມີ, ສົ່ງຄືນເລກທີ່ນີ້
                    error_log("Generated unique PO number: {$poNumber} (attempt {$attempt})");
                    return $poNumber;
                }
                
                // ຖ້າມີ, ເພີ່ມ count ແລະ ລອງໃໝ່
                $count++;
                $attempt++;
                error_log("PO number {$poNumber} already exists, trying next number");
            }
            
            // ຖ້າລອງຫຼາຍເທື່ອແລ້ວຍັງຊໍ້າ, ໃຊ້ timestamp
            $fallbackNumber = $prefix . '-' . $year . '-' . date('dHi');
            error_log("Using fallback PO number: {$fallbackNumber}");
            return $fallbackNumber;
            
        } catch (Exception $e) {
            error_log("Error generating PO number: " . $e->getMessage());
            // Fallback: generate based on timestamp
            return $prefix . '-' . $year . '-' . date('dHi');
        }
    }

    /**
     * ສ້າງໃບສັ່ງຊື້ໃໝ່
     */
    public function createPurchaseOrder() {
        try {
            // ກວດສອບ authentication
            $payload = AuthMiddleware::authenticate();
            $userId = $payload['user_id'] ?? $payload['id'] ?? null;
            
            if (!$userId) {
                Response::error('Unauthorized', 401);
                return;
            }
            
            // ຮັບຂໍ້ມູນ
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!$input) {
                Response::error('Invalid input data', 400);
                return;
            }
            
            // ສ້າງເລກທີ່ໃບສັ່ງຊື້ອັດຕະໂນມັດ
            $input['po_number'] = $this->generatePurchaseOrderNumber();
            
            // ບັນທຶກເລກທີ່ທີ່ສ້າງໄດ້
            error_log("Creating PO with number: " . $input['po_number']);
            
            // ກວດສອບວ່າເລກທີ່ຖືກຕ້ອງຕາມຮູບແບບ ຫຼື ບໍ່
            if (!preg_match('/^PU-\d{4}-\d{3}$/', $input['po_number'])) {
                error_log("PO Number validation failed: " . $input['po_number']);
                Response::error('Invalid purchase order number format. Expected: PU-YYYY-XXX', 400);
                return;
            }
            
            // ສ້າງໃບສັ່ງຊື້
            $purchaseOrder = new PurchaseOrder();
            $result = $purchaseOrder->createPurchaseOrder($input, $userId);
            
            if ($result['success']) {
                Response::success([
                    'id' => $result['id'],
                    'po_number' => $result['po_number']
                ], $result['message'], 201);
            } else {
                Response::error($result['message'], 400);
            }
            
        } catch (Exception $e) {
            error_log("Error in createPurchaseOrder: " . $e->getMessage());
            Response::error('Server error: ' . $e->getMessage(), 500);
        }
    }
 
 
    public function updatePurchaseOrder($id) {
        try {
            $user = $this->getCurrentUser();
            $this->checkPermission($user);

            if (!is_numeric($id)) {
                Response::error('ID ບໍ່ຖືກຕ້ອງ', 400);
                return;
            }

            error_log("=== UPDATE PURCHASE ORDER ===");
            error_log("ID: " . $id);
            
            $data = json_decode(file_get_contents('php://input'), true);
            
            error_log("Received data: " . json_encode($data));

            if (empty($data['items']) || !is_array($data['items'])) {
                Response::error('ກະລຸນາລະບຸລາຍການສິນຄ້າ', 400);
                return;
            }

            $result = $this->purchaseModel->updatePurchaseOrder($id, $data, $user['id'] ?? null);
            
            error_log("Update result: " . json_encode($result));

            if ($result['success']) {
                Response::success($result['data'] ?? null, $result['message']);
            } else {
                Response::error($result['message'], 400);
            }

        } catch (Exception $e) {
            error_log("Error in updatePurchaseOrder: " . $e->getMessage());
            Response::error('ອັບເດດໃບສັ່ງຊື້ບໍ່ສຳເລັດ: ' . $e->getMessage(), 500);
        }
    }






    /**
     * POST /purchases/{id}/receive - ຮັບສິນຄ້າ
     */
    // public function receivePurchaseOrder($id) {
    //     try {
    //         $user = $this->getCurrentUser();
    //         $this->checkPermission($user);

    //         if (!is_numeric($id)) {
    //             Response::error('ID ບໍ່ຖືກຕ້ອງ', 400);
    //             return;
    //         }

    //         $result = $this->purchaseModel->receivePurchaseOrder($id, $user['id'] ?? null);

    //         if ($result['success']) {
    //             Response::success(null, $result['message']);
    //         } else {
    //             Response::error($result['message'], 400);
    //         }

    //     } catch (Exception $e) {
    //         error_log("Error in receivePurchaseOrder: " . $e->getMessage());
    //         Response::error('ຮັບສິນຄ້າບໍ່ສຳເລັດ: ' . $e->getMessage(), 500);
    //     }
    // }




    public function receivePurchaseOrder($id) {
        try {
            // ດຶງ user ID ຈາກ token ໃຫ້ຖືກຕ້ອງ
            $payload = AuthMiddleware::authenticate();
            
            // ສະກັດເອົາ user_id ຈາກ payload ທີ່ເປັນ Array ຫຼື Object
            if (is_array($payload)) {
                $userId = $payload['user_id'] ?? $payload['id'] ?? null;
            } else if (is_object($payload)) {
                $userId = $payload->user_id ?? $payload->id ?? null;
            } else {
                $userId = null;
            }
            
            // ບັງຄັບໃຫ້ເປັນ integer
            $userId = (int)$userId;
            
            if (!$userId || $userId <= 0) {
                error_log("Invalid user ID: " . json_encode($payload));
                Response::error('Unauthorized: Invalid user ID', 401);
                return;
            }
            
            error_log("=== RECEIVE PURCHASE ORDER ===");
            error_log("PO ID: $id");
            error_log("User ID (after cast): $userId");
            
            $input = json_decode(file_get_contents('php://input'), true);
            error_log("Input: " . json_encode($input));
            
            // ກວດສອບວ່າມີ items ບໍ
            if (empty($input['items'])) {
                Response::error('ກະລຸນາລະບຸລາຍການສິນຄ້າ', 400);
                return;
            }
            
            // ສ້າງ PurchaseOrder model ແລະ ເອີ້ນ receivePurchaseOrder
            $purchaseOrder = new PurchaseOrder();
            $result = $purchaseOrder->receivePurchaseOrder($id, $input, $userId);
            
            error_log("Receive result: " . json_encode($result));
            
            if ($result['success']) {
                Response::success($result, 200, $result['message']);
            } else {
                Response::error($result['message'], 400);
            }
            
        } catch (Exception $e) {
            error_log("Error receiving purchase order: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            Response::error('ຮັບສິນຄ້າບໍ່ສຳເລັດ: ' . $e->getMessage(), 500);
        }
    }


    /**
     * PATCH /purchases/{id}/status - ອັບເດດສະຖານະ
     */
    public function updatePurchaseStatus($id) {
        try {
            $user = $this->getCurrentUser();
            $this->checkPermission($user);

            if (!is_numeric($id)) {
                Response::error('ID ບໍ່ຖືກຕ້ອງ', 400);
                return;
            }

            $data = json_decode(file_get_contents('php://input'), true);

            if (empty($data['status'])) {
                Response::error('ກະລຸນາລະບຸສະຖານະ', 400);
                return;
            }

            $allowedStatus = ['draft', 'pending', 'approved', 'shipped', 'received', 'cancelled'];
            if (!in_array($data['status'], $allowedStatus)) {
                Response::error('ສະຖານະບໍ່ຖືກຕ້ອງ', 400);
                return;
            }

            $result = $this->purchaseModel->updateStatus(
                $id, 
                $data['status'],
                $data['status'] === 'approved' ? ($user['id'] ?? null) : null
            );

            if ($result['success']) {
                Response::success(null, $result['message']);
            } else {
                Response::error($result['message'], 400);
            }

        } catch (Exception $e) {
            error_log("Error in updatePurchaseStatus: " . $e->getMessage());
            Response::error('ອັບເດດສະຖານະບໍ່ສຳເລັດ: ' . $e->getMessage(), 500);
        }
    }

    /**
     * PATCH /purchases/{id}/payment - ອັບເດດສະຖານະການຊຳລະ
     */
    public function updatePaymentStatus($id) {
        try {
            $user = $this->getCurrentUser();
            $this->checkPermission($user);

            if (!is_numeric($id)) {
                Response::error('ID ບໍ່ຖືກຕ້ອງ', 400);
                return;
            }

            $data = json_decode(file_get_contents('php://input'), true);

            if (empty($data['payment_status'])) {
                Response::error('ກະລຸນາລະບຸສະຖານະການຊຳລະ', 400);
                return;
            }

            $allowedStatus = ['unpaid', 'partial', 'paid', 'overdue'];
            if (!in_array($data['payment_status'], $allowedStatus)) {
                Response::error('ສະຖານະການຊຳລະບໍ່ຖືກຕ້ອງ', 400);
                return;
            }

            $paymentDate = $data['payment_status'] === 'paid' ? date('Y-m-d H:i:s') : null;

            $result = $this->purchaseModel->updatePaymentStatus(
                $id,
                $data['payment_status'],
                $data['payment_method'] ?? null,
                $paymentDate
            );

            if ($result['success']) {
                Response::success(null, $result['message']);
            } else {
                Response::error($result['message'], 400);
            }

        } catch (Exception $e) {
            error_log("Error in updatePaymentStatus: " . $e->getMessage());
            Response::error('ອັບເດດສະຖານະການຊຳລະບໍ່ສຳເລັດ: ' . $e->getMessage(), 500);
        }
    }

    /**
     * DELETE /purchases/{id} - ລຶບໃບສັ່ງຊື້
     */
    public function deletePurchaseOrder($id) {
        try {
            $user = $this->getCurrentUser();
            $this->checkPermission($user);

            if (!is_numeric($id)) {
                Response::error('ID ບໍ່ຖືກຕ້ອງ', 400);
                return;
            }

            $result = $this->purchaseModel->deletePurchaseOrder($id);

            if ($result['success']) {
                Response::success(null, $result['message']);
            } else {
                Response::error($result['message'], 400);
            }

        } catch (Exception $e) {
            error_log("Error in deletePurchaseOrder: " . $e->getMessage());
            Response::error('ລຶບໃບສັ່ງຊື້ບໍ່ສຳເລັດ: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /purchases/items - ດຶງລາຍການສິນຄ້າສຳລັບໃບສັ່ງຊື້
     */
    public function getPurchaseItems() {
        try {
            $user = $this->getCurrentUser();
            
            $search = $_GET['search'] ?? '';
            $items = $this->itemModel->getItemsForDropdown($search);

            Response::success($items, 'ດຶງຂໍ້ມູນສຳເລັດ');

        } catch (Exception $e) {
            error_log("Error in getPurchaseItems: " . $e->getMessage());
            Response::error('ດຶງຂໍ້ມູນບໍ່ສຳເລັດ: ' . $e->getMessage(), 500);
        }
    }


    

}
?>