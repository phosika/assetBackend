<?php
// BACKEND/src/controllers/DashboardController.php

require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Asset.php';
require_once __DIR__ . '/../models/PurchaseOrder.php';
require_once __DIR__ . '/../models/SalesOrder.php';
require_once __DIR__ . '/../models/InventoryStock.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../utils/Response.php';

class DashboardController {
    private $userModel;
    private $assetModel;
    private $purchaseModel;
    private $salesModel;
    private $inventoryModel;

    public function __construct() {
        $this->userModel = new User();
        $this->assetModel = new Asset();
        $this->purchaseModel = new PurchaseOrder();
        $this->salesModel = new SalesOrder();
        $this->inventoryModel = new InventoryStock();
    }

    /**
     * GET /dashboard/summary - ດຶງຂໍ້ມູນສະຫຼຸບສຳລັບໜ້າຫຼັກ
     */
    public function getDashboardSummary() {
        try {
            $user = AuthMiddleware::authenticate();
            
            // ດຶງຂໍ້ມູນຕ່າງໆ
            $totalAssets = $this->assetModel->getTotalAssetsCount();
            $totalUsers = $this->userModel->getTotalUsersCount();
            $totalPurchaseValue = $this->purchaseModel->getTotalPurchaseValue();
            $totalSalesValue = $this->salesModel->getTotalSalesValue();
            $lowStockItems = $this->inventoryModel->getLowStockCount();
            
            // ດຶງຂໍ້ມູນສະຖິຕິຕາມສະຖານະ
            $assetStats = $this->assetModel->getAssetStats(
                $user['role'],
                $user['department_id'] ?? null,
                $user['user_id']
            );
            
            $summary = [
                'total_assets' => $totalAssets,
                'total_users' => $totalUsers,
                'total_purchase_value' => $totalPurchaseValue,
                'total_sales_value' => $totalSalesValue,
                'low_stock_items' => $lowStockItems,
                'assets_by_status' => $assetStats['status_stats'] ?? [],
                'assets_by_condition' => $assetStats['condition_stats'] ?? []
            ];
            
            Response::success($summary, 'Dashboard summary retrieved successfully');
            
        } catch (Exception $e) {
            error_log("Error in getDashboardSummary: " . $e->getMessage());
            Response::error('Failed to retrieve dashboard summary: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /dashboard/recent-activities - ດຶງກິດຈະກຳລ່າສຸດ
     */
    public function getRecentActivities() {
        try {
            AuthMiddleware::authenticate();
            
            $activities = [];
            
            // ດຶງກິດຈະກຳລ່າສຸດຈາກຕາຕະລາງຕ່າງໆ
            $recentAssets = $this->assetModel->getRecentAssets(5);
            $recentPurchases = $this->purchaseModel->getRecentPurchases(5);
            $recentSales = $this->salesModel->getRecentSales(5);
            
            foreach ($recentAssets as $asset) {
                $activities[] = [
                    'type' => 'asset',
                    'action' => 'created',
                    'title' => 'ເພີ່ມຊັບສິນໃໝ່',
                    'description' => $asset['asset_name'] . ' (' . $asset['asset_code'] . ')',
                    'timestamp' => $asset['created_at'],
                    'user' => $asset['created_by_name'] ?? 'System'
                ];
            }
            
            foreach ($recentPurchases as $purchase) {
                $activities[] = [
                    'type' => 'purchase',
                    'action' => 'created',
                    'title' => 'ສ້າງໃບສັ່ງຊື້ໃໝ່',
                    'description' => 'PO #' . $purchase['po_number'] . ' - ' . number_format($purchase['total_amount']) . ' ກີບ',
                    'timestamp' => $purchase['created_at'],
                    'user' => $purchase['created_by_name'] ?? 'System'
                ];
            }
            
            foreach ($recentSales as $sale) {
                $activities[] = [
                    'type' => 'sale',
                    'action' => 'created',
                    'title' => 'ສ້າງໃບຂາຍໃໝ່',
                    'description' => 'SO #' . $sale['so_number'] . ' - ' . number_format($sale['total_amount']) . ' ກີບ',
                    'timestamp' => $sale['created_at'],
                    'user' => $sale['created_by_name'] ?? 'System'
                ];
            }
            
            // ຈັດຮຽງຕາມເວລາ
            usort($activities, function($a, $b) {
                return strtotime($b['timestamp']) - strtotime($a['timestamp']);
            });
            
            // ຕັດເອົາສູງສຸດ 20 ລາຍການ
            $activities = array_slice($activities, 0, 20);
            
            Response::success($activities, 'Recent activities retrieved successfully');
            
        } catch (Exception $e) {
            error_log("Error in getRecentActivities: " . $e->getMessage());
            Response::error('Failed to retrieve recent activities: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /dashboard/charts - ດຶງຂໍ້ມູນສຳລັບກຣາຟ
     */
    public function getChartData() {
        try {
            AuthMiddleware::authenticate();
            
            $year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
            
            // ດຶງຂໍ້ມູນການຊື້-ຂາຍລາຍເດືອນ
            $monthlyPurchaseData = $this->purchaseModel->getMonthlyPurchaseData($year);
            $monthlySalesData = $this->salesModel->getMonthlySalesData($year);
            
            // ດຶງຂໍ້ມູນຊັບສິນຕາມໝວດໝູ່
            $assetsByCategory = $this->assetModel->getAssetsByCategory();
            
            // ດຶງຂໍ້ມູນຊັບສິນຕາມສະຖານະ
            $assetsByStatus = $this->assetModel->getAssetsByStatus();
            
            $chartData = [
                'monthly_purchase' => $monthlyPurchaseData,
                'monthly_sales' => $monthlySalesData,
                'assets_by_category' => $assetsByCategory,
                'assets_by_status' => $assetsByStatus,
                'year' => $year
            ];
            
            Response::success($chartData, 'Chart data retrieved successfully');
            
        } catch (Exception $e) {
            error_log("Error in getChartData: " . $e->getMessage());
            Response::error('Failed to retrieve chart data: ' . $e->getMessage(), 500);
        }
    }
}
?>