<?php
// /var/www/html/controllers/BarcodeController.php

require_once __DIR__ . '/../models/Barcode.php';
require_once __DIR__ . '/../models/InventoryItem.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../utils/Response.php';

class BarcodeController {
    private $barcodeModel;
    private $itemModel;
    private $userModel;

    public function __construct() {
        $this->barcodeModel = new Barcode();
        $this->itemModel = new InventoryItem();
        $this->userModel = new User();
    }

    private function getCurrentUser() {
        try {
            $payload = AuthMiddleware::authenticate();
            return $this->userModel->getById($payload['user_id']);
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * GET /barcodes - ດຶງຂໍ້ມູນ barcode ທັງໝົດ
     */
    public function getAllBarcodes() {
        try {
            $user = $this->getCurrentUser();
            $companyId = $user['company_id'] ?? null;

            $filters = [
                'search' => $_GET['search'] ?? '',
                'limit' => isset($_GET['limit']) ? (int)$_GET['limit'] : 100
            ];

            $barcodes = $this->barcodeModel->getAllBarcodes($filters, $companyId);

            Response::success($barcodes, 'ດຶງຂໍ້ມູນສຳເລັດ');

        } catch (Exception $e) {
            error_log("Error in getAllBarcodes: " . $e->getMessage());
            Response::error('ດຶງຂໍ້ມູນບໍ່ສຳເລັດ: ' . $e->getMessage(), 500);
        }
    }

 
    /**
     * GET /barcodes/items - ດຶງຂໍ້ມູນສິນຄ້າສຳລັບສ້າງ barcode
     */
    public function getItemsForBarcode() {
        try {
            require_once __DIR__ . '/../models/InventoryItem.php';
            $itemModel = new InventoryItem();
            
            $search = $_GET['search'] ?? '';
            $items = $itemModel->getItemsForDropdown($search);
            
            // ຮັບປະກັນວ່າສົ່ງຄືນເປັນ array ສະເໝີ
            if (!$items || !is_array($items)) {
                $items = [];
            }
            
            Response::success($items, 'ດຶງຂໍ້ມູນສຳເລັດ');

        } catch (Exception $e) {
            error_log("Error in getItemsForBarcode: " . $e->getMessage());
            // ສົ່ງຄືນ array ເປົ່າ ແທນທີ່ຈະສົ່ງ error
            Response::success([], 'ດຶງຂໍ້ມູນສຳເລັດ (ບໍ່ມີຂໍ້ມູນ)');
        }
    }

    /**
     * POST /barcodes - ສ້າງ barcode ໃໝ່
     */
    public function createBarcode() {
        try {
            $user = $this->getCurrentUser();
            $companyId = $user['company_id'] ?? null;

            $data = json_decode(file_get_contents('php://input'), true);

            if (empty($data['barcode_number']) || empty($data['item_id'])) {
                Response::error('ກະລຸນາປ້ອນຂໍ້ມູນໃຫ້ຄົບ', 400);
                return;
            }

            $result = $this->barcodeModel->createBarcode($data, $user['id'] ?? null, $companyId);

            if ($result['success']) {
                Response::success(['id' => $result['id']], $result['message'], 201);
            } else {
                Response::error($result['message'], 400);
            }

        } catch (Exception $e) {
            error_log("Error in createBarcode: " . $e->getMessage());
            Response::error('ສ້າງ barcode ບໍ່ສຳເລັດ: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST /barcodes/generate-from-item/{id} - ສ້າງ barcode ຈາກສິນຄ້າ
     */
    public function generateFromItem($itemId) {
        try {
            $user = $this->getCurrentUser();
            $companyId = $user['company_id'] ?? null;

            if (!is_numeric($itemId)) {
                Response::error('ID ບໍ່ຖືກຕ້ອງ', 400);
                return;
            }

            $result = $this->barcodeModel->generateBarcodeFromItem($itemId, $user['id'] ?? null, $companyId);

            if ($result['success']) {
                Response::success(['id' => $result['id']], $result['message'], 201);
            } else {
                Response::error($result['message'], 400);
            }

        } catch (Exception $e) {
            error_log("Error in generateFromItem: " . $e->getMessage());
            Response::error('ສ້າງ barcode ບໍ່ສຳເລັດ: ' . $e->getMessage(), 500);
        }
    }


    /**
     * POST /barcodes/{id}/print - ອັບເດດສະຖານະການພິມ
     */
    public function updatePrintStatus($id) {
        try {
            $user = $this->getCurrentUser();

            if (!is_numeric($id)) {
                Response::error('ID ບໍ່ຖືກຕ້ອງ', 400);
                return;
            }

            $data = json_decode(file_get_contents('php://input'), true);
            $printed = $data['printed'] ?? true;

            $result = $this->barcodeModel->updatePrintStatus($id, $printed);

            if ($result['success']) {
                Response::success(null, $result['message']);
            } else {
                Response::error($result['message'], 400);
            }

        } catch (Exception $e) {
            error_log("Error in updatePrintStatus: " . $e->getMessage());
            Response::error('ອັບເດດສະຖານະບໍ່ສຳເລັດ: ' . $e->getMessage(), 500);
        }
    }
}
?>