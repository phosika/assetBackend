<?php
// /home/phosika/Fixasset/assetapplication/BACKEND/src/controllers/PurchaseController.php

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

    public function __construct() {
        $this->purchaseModel = new PurchaseOrder();
        $this->supplierModel = new Supplier();
        $this->itemModel = new InventoryItem();
        $this->userModel = new User();
    }

    private function getCurrentUser() {
        try {
            $userId = AuthMiddleware::authenticate();
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
     * GET /purchases/pending - ດຶງໃບສັ່ງຊື້ທີ່ລໍຖ້າ
     */
    public function getPendingOrders() {
        try {
            $_GET['status'] = 'pending';
            $this->getAllPurchaseOrders();
        } catch (Exception $e) {
            Response::error('ດຶງຂໍ້ມູນບໍ່ສຳເລັດ: ' . $e->getMessage(), 500);
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
     * POST /purchases - ສ້າງໃບສັ່ງຊື້ໃໝ່
     */
    public function createPurchaseOrder() {
        try {
            $user = $this->getCurrentUser();
            $this->checkPermission($user);

            $data = json_decode(file_get_contents('php://input'), true);

            // ກວດສອບຂໍ້ມູນທີ່ຈຳເປັນ
            $required = ['po_number', 'supplier_id', 'order_date', 'items'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    Response::error("ກະລຸນາປ້ອນຂໍ້ມູນ {$field} ໃຫ້ຄົບ", 400);
                    return;
                }
            }

            if (!is_array($data['items']) || count($data['items']) === 0) {
                Response::error('ກະລຸນາເພີ່ມລາຍການສິນຄ້າຢ່າງໜ້ອຍ 1 ລາຍການ', 400);
                return;
            }

            $result = $this->purchaseModel->createPurchaseOrder($data, $user['id'] ?? null);

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
            Response::error('ສ້າງໃບສັ່ງຊື້ບໍ່ສຳເລັດ: ' . $e->getMessage(), 500);
        }
    }

    /**
     * PUT /purchases/{id} - ອັບເດດໃບສັ່ງຊື້
     */
    public function updatePurchaseOrder($id) {
        try {
            $user = $this->getCurrentUser();
            $this->checkPermission($user);

            if (!is_numeric($id)) {
                Response::error('ID ບໍ່ຖືກຕ້ອງ', 400);
                return;
            }

            $data = json_decode(file_get_contents('php://input'), true);

            $result = $this->purchaseModel->updatePurchaseOrder($id, $data, $user['id'] ?? null);

            if ($result['success']) {
                Response::success(null, $result['message']);
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
    public function receivePurchaseOrder($id) {
        try {
            $user = $this->getCurrentUser();
            $this->checkPermission($user);

            if (!is_numeric($id)) {
                Response::error('ID ບໍ່ຖືກຕ້ອງ', 400);
                return;
            }

            $result = $this->purchaseModel->receivePurchaseOrder($id, $user['id'] ?? null);

            if ($result['success']) {
                Response::success(null, $result['message']);
            } else {
                Response::error($result['message'], 400);
            }

        } catch (Exception $e) {
            error_log("Error in receivePurchaseOrder: " . $e->getMessage());
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
     * POST /purchases/{id}/invoice - ອັບເດດໃບເກັບເງິນ
     */
    public function updateInvoice($id) {
        try {
            $user = $this->getCurrentUser();
            $this->checkPermission($user);

            if (!is_numeric($id)) {
                Response::error('ID ບໍ່ຖືກຕ້ອງ', 400);
                return;
            }

            $data = json_decode(file_get_contents('php://input'), true);

            if (empty($data['invoice_number'])) {
                Response::error('ກະລຸນາລະບຸເລກທີໃບເກັບເງິນ', 400);
                return;
            }

            $result = $this->purchaseModel->updateInvoice(
                $id,
                $data['invoice_number'],
                $data['invoice_file_path'] ?? null
            );

            if ($result['success']) {
                Response::success(null, $result['message']);
            } else {
                Response::error($result['message'], 400);
            }

        } catch (Exception $e) {
            error_log("Error in updateInvoice: " . $e->getMessage());
            Response::error('ອັບເດດໃບເກັບເງິນບໍ່ສຳເລັດ: ' . $e->getMessage(), 500);
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


    /**
     * POST /purchases/{id}/partial-receive - ຮັບສິນຄ້າບາງສ່ວນ
     */
    public function partialReceivePurchaseOrder($id) {
        try {
            $user = $this->getCurrentUser();
            $this->checkPermission($user);

            if (!is_numeric($id)) {
                Response::error('ID ບໍ່ຖືກຕ້ອງ', 400);
                return;
            }

            $data = json_decode(file_get_contents('php://input'), true);

            if (empty($data['items']) || !is_array($data['items'])) {
                Response::error('ກະລຸນາລະບຸລາຍການທີ່ຕ້ອງການຮັບ', 400);
                return;
            }

            $result = $this->purchaseModel->partialReceivePurchaseOrder($id, $data['items'], $user['id'] ?? null);

            if ($result['success']) {
                Response::success(null, $result['message']);
            } else {
                Response::error($result['message'], 400);
            }

        } catch (Exception $e) {
            error_log("Error in partialReceivePurchaseOrder: " . $e->getMessage());
            Response::error('ຮັບສິນຄ້າບໍ່ສຳເລັດ: ' . $e->getMessage(), 500);
        }
    }

    /**
     * ສ້າງເລກທີ່ໃບສັ່ງຊື້ອັດຕະໂນມັດ
     */
    private function generatePurchaseOrderNumber() {
        $year = date('Y');
        $prefix = 'PU';
        
        try {
            $db = Database::getInstance();
            
            // ນັບຈຳນວນໃບສັ່ງຊື້ໃນປີປັດຈຸບັນ
            $stmt = $db->prepare("
                SELECT COUNT(*) as count 
                FROM purchase_orders 
                WHERE po_number LIKE ?
            ");
            
            $pattern = "PU-{$year}-%";
            $stmt->execute([$pattern]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $count = ($result && isset($result['count'])) ? (int)$result['count'] : 0;
            $nextNumber = $count + 1;
            
            // ສ້າງເລກທີ່: PU-2026-001
            $poNumber = $prefix . '-' . $year . '-' . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
            
            error_log("Generated PO Number: {$poNumber} (Count: {$count})");
            
            return $poNumber;
            
        } catch (Exception $e) {
            error_log("Error generating PO number: " . $e->getMessage());
            // Fallback: generate based on timestamp
            return $prefix . '-' . $year . '-' . date('d') . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
        }
    }
}
?>