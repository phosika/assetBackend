<?php
// src/index.php - ແກ້ໄຂບັນຫາ

// ຕ້ອງແນ່ໃຈວ່າບໍ່ມີ output ກ່ອນ headers
ob_start();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ສ້າງ session ທົດສອບສຳລັບ development (ຖ້າບໍ່ມີ user ເຂົ້າສູ່ລະບົບ)
if (!isset($_SESSION['user_id']) && !isset($_SESSION['user'])) {
    // ນີ້ສຳລັບການທົດສອບເທົ່ານັ້ນ
    $_SESSION['user_id'] = 1;
    $_SESSION['user'] = ['id' => 1, 'name' => 'Admin', 'email' => 'admin@example.com'];
    error_log("Development session created for testing");
}

// ຕັ້ງ CORS headers
header('Access-Control-Allow-Origin: http://localhost:5173');
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Accept, Origin');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Max-Age: 3600');

// ຈັດການ preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// ກໍານົດ base path
$basePath = __DIR__;

// ຫຼັງຈາກນັ້ນ ໃຊ້ສຳລັບ require
require_once $basePath . '/utils/Response.php';
require_once $basePath . '/utils/Validator.php';
require_once $basePath . '/utils/FileUploader.php';
require_once $basePath . '/middleware/AuthMiddleware.php';
require_once $basePath . '/controllers/AuthController.php';
require_once $basePath . '/controllers/UserController.php';
require_once $basePath . '/controllers/DepreciationController.php';
require_once $basePath . '/controllers/CompanyController.php';
require_once $basePath . '/controllers/DepartmentController.php';
require_once $basePath . '/controllers/AssetCategoryController.php';
require_once $basePath . '/controllers/AssetController.php';
require_once $basePath . '/controllers/SupplierController.php';
require_once $basePath . '/controllers/InventoryItemController.php';
require_once $basePath . '/controllers/InventoryStockController.php';
require_once $basePath . '/controllers/PurchaseController.php';
require_once $basePath . '/controllers/SalesController.php';
require_once $basePath . '/controllers/CustomerController.php';
require_once $basePath . '/controllers/BarcodeController.php';
require_once $basePath . '/controllers/AssetSyncController.php';
require_once $basePath . '/controllers/WarehouseController.php';
require_once $basePath . '/controllers/ExchangeRateController.php';
require_once $basePath . '/controllers/DashboardController.php';
require_once $basePath . '/controllers/StockCountController.php';
require_once $basePath . '/controllers/StockAdjustmentController.php';
require_once $basePath . '/controllers/AssetSyncController.php';
require_once $basePath . '/controllers/RoleController.php';

// Models
require_once $basePath . '/models/User.php';
require_once $basePath . '/models/Department.php';
require_once $basePath . '/models/Company.php';
require_once $basePath . '/models/AssetCategory.php';
require_once $basePath . '/models/Asset.php';
require_once $basePath . '/models/Supplier.php';
require_once $basePath . '/models/InventoryItem.php';
require_once $basePath . '/models/InventoryStock.php';
require_once $basePath . '/models/PurchaseOrder.php';
require_once $basePath . '/models/SalesOrder.php';
require_once $basePath . '/models/Customer.php';
require_once $basePath . '/models/Barcode.php';
require_once $basePath . '/models/AssetSyncLog.php';

$request = $_SERVER['REQUEST_URI'];
$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($request, PHP_URL_PATH);

// ແກ້ໄຂການອ່ານ path
$script_name = $_SERVER['SCRIPT_NAME'];
$path = str_replace($script_name, '', $path);
$path = ltrim($path, '/');

// ຂ້າມ /api prefix
if (strpos($path, 'api/') === 0) {
    $path = substr($path, 4);
}

$segments = explode('/', trim($path, '/'));
$resource = $segments[0] ?? '';
$action = $segments[1] ?? '';
$id = $segments[2] ?? '';
$subAction = $segments[3] ?? '';

// ເລີ່ມ session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Rate limiting
$key = $_SERVER['REMOTE_ADDR'] . ':' . $resource;
$limit = 100;
$timeWindow = 3600;

if (isset($_SESSION[$key])) {
    if ($_SESSION[$key]['count'] >= $limit && time() - $_SESSION[$key]['time'] < $timeWindow) {
        ob_end_clean();
        Response::error('Too many requests', 429);
        exit();
    }
    
    if (time() - $_SESSION[$key]['time'] > $timeWindow) {
        $_SESSION[$key] = ['count' => 1, 'time' => time()];
    } else {
        $_SESSION[$key]['count']++;
    }
} else {
    $_SESSION[$key] = ['count' => 1, 'time' => time()];
}

// Routing
try {
    switch ($resource) {
        // ==================== AUTH ROUTES ====================
        case 'auth':
            $authController = new AuthController();
            if ($method === 'POST') {
                switch ($action) {
                    case 'register':
                        $authController->register();
                        break;
                    case 'login':
                        $authController->login();
                        break;
                    case 'refresh':
                        $authController->refresh();
                        break;
                    case 'logout':
                        $authController->logout();
                        break;
                    default:
                        Response::notFound('Auth endpoint not found');
                }
            } else {
                Response::error('Method not allowed', 405);
            }
            break;

        // ==================== USER PROFILE ROUTES ====================
        case 'user':
            $userController = new UserController();
            if ($method === 'GET' && $action === 'profile') {
                $userController->getProfile();
            } elseif ($method === 'PUT' && $action === 'profile') {
                $userController->updateProfile();
            } elseif ($method === 'POST' && $action === 'change-password') {
                $userController->changePassword();
            } elseif ($method === 'POST' && $action === 'profile-image') {
                $userController->uploadProfileImage();
            } else {
                Response::notFound('User endpoint not found');
            }
            break;

        // ==================== USERS MANAGEMENT ROUTES ====================
        case 'users':
            $userController = new UserController();
            
            if ($method === 'GET' && $action === '' && $id === '') {
                $userController->getAllUsers();
            } elseif ($method === 'GET' && $action === 'dropdown') {
                $userController->getUsersForDropdown();
            } elseif ($method === 'GET' && $action === 'export') {
                $userController->exportUsers();
            } elseif ($method === 'GET' && $action === 'stats') {
                $userController->getUserStats();
            } elseif ($method === 'GET' && $action === 'search') {
                $userController->searchUsers();
            } elseif ($method === 'GET' && $action === 'managers') {
                $userController->getManagers();
            } elseif ($method === 'GET' && $action === 'by-department' && !empty($id)) {
                $userController->getUsersByDepartment($id);
            } elseif ($method === 'GET' && $action === 'activities' && !empty($id)) {
                $userController->getUserActivities($id);
            } elseif ($method === 'GET' && !empty($action) && is_numeric($action)) {
                $userController->getUserById($action);
            } elseif ($method === 'POST' && $action === '') {
                $userController->createUser();
            } elseif ($method === 'PUT' && !empty($action) && is_numeric($action)) {
                $userController->updateUser($action);
            } elseif ($method === 'DELETE' && !empty($action) && is_numeric($action)) {
                $userController->deleteUser($action);
            } elseif ($method === 'PATCH' && !empty($action) && is_numeric($action) && $id === 'status') {
                $userController->updateUserStatus($action);
            } elseif ($method === 'PATCH' && !empty($action) && is_numeric($action) && $id === 'role') {
                $userController->updateUserRole($action);
            } else {
                Response::notFound('Users endpoint not found');
            }
            break;

        // ==================== COMPANY MANAGEMENT ROUTES ====================
        case 'companies':
            $companyController = new CompanyController();
            
            if ($method === 'GET' && $action === '' && $id === '') {
                $companyController->getAllCompanies();
            } elseif ($method === 'GET' && $action === 'dropdown') {
                $companyController->getCompaniesForDropdown();
            } elseif ($method === 'GET' && $action === 'parents') {
                $companyController->getParentCompanies();
            } elseif ($method === 'GET' && $action === 'stats') {
                $companyController->getCompanyStats();
            } elseif ($method === 'GET' && !empty($action) && is_numeric($action)) {
                $companyController->getCompanyById($action);
            } elseif ($method === 'POST' && $action === '') {
                $companyController->createCompany();
            } elseif ($method === 'PUT' && !empty($action) && is_numeric($action)) {
                $companyController->updateCompany($action);
            } elseif ($method === 'PATCH' && !empty($action) && is_numeric($action) && $id === 'status') {
                $companyController->updateCompanyStatus($action);
            } elseif ($method === 'DELETE' && !empty($action) && is_numeric($action) && $id !== 'permanent') {
                $companyController->deleteCompany($action);
            } elseif ($method === 'DELETE' && !empty($action) && is_numeric($action) && $id === 'permanent') {
                $companyController->deleteCompanyPermanently($action);
            } else {
                Response::notFound('Companies endpoint not found');
            }
            break;

        // ==================== DEPARTMENT MANAGEMENT ROUTES ====================
        case 'departments':
            $departmentController = new DepartmentController();
            
            if ($method === 'GET' && $action === '' && $id === '') {
                $departmentController->getAllDepartments();
            } elseif ($method === 'GET' && $action === 'dropdown') {
                $departmentController->getDepartmentsForDropdown();
            } elseif ($method === 'GET' && $action === 'parents') {
                $departmentController->getParentDepartments();
            } elseif ($method === 'GET' && $action === 'available-managers') {
                $departmentController->getAvailableManagers();
            } elseif ($method === 'GET' && $action === 'stats') {
                $departmentController->getDepartmentStats();
            } elseif ($method === 'GET' && $action === 'company' && !empty($id)) {
                $departmentController->getDepartmentsByCompany($id);
            } elseif ($method === 'GET' && $action === 'check-manager' && !empty($id) && !empty($subAction)) {
                $departmentController->checkManagerEligibility($id, $subAction);
            } elseif ($method === 'GET' && !empty($action) && is_numeric($action)) {
                $departmentController->getDepartmentById($action);
            } elseif ($method === 'POST' && $action === '') {
                $departmentController->createDepartment();
            } elseif ($method === 'PUT' && !empty($action) && is_numeric($action)) {
                $departmentController->updateDepartment($action);
            } elseif ($method === 'PATCH' && !empty($action) && is_numeric($action) && $id === 'status') {
                $departmentController->updateDepartmentStatus($action);
            } elseif ($method === 'PATCH' && !empty($action) && is_numeric($action) && $id === 'manager') {
                $departmentController->updateDepartmentManager($action);
            } elseif ($method === 'DELETE' && !empty($action) && is_numeric($action)) {
                $departmentController->deleteDepartment($action);
            } else {
                Response::notFound('Departments endpoint not found');
            }
            break;

        // ==================== ASSET CATEGORIES MANAGEMENT ROUTES ====================
        case 'asset-categories':
            $categoryController = new AssetCategoryController();
            
            if ($method === 'GET' && $action === '' && $id === '') {
                $categoryController->getAllCategories();
            } elseif ($method === 'GET' && $action === 'tree') {
                $categoryController->getCategoryTree();
            } elseif ($method === 'GET' && $action === 'dropdown') {
                $categoryController->getCategoriesForDropdown();
            } elseif ($method === 'GET' && $action === 'stats') {
                $categoryController->getCategoryStats();
            } elseif ($method === 'GET' && $action === 'by-level' && !empty($id)) {
                $categoryController->getCategoriesByLevel($id);
            } elseif ($method === 'GET' && $action === 'by-level-parent') {
                $categoryController->getCategoriesByTargetLevel();
            } elseif ($method === 'GET' && !empty($action) && is_numeric($action)) {
                $categoryController->getCategoryById($action);
            } elseif ($method === 'POST' && $action === '') {
                $categoryController->createCategory();
            } elseif ($method === 'PUT' && !empty($action) && is_numeric($action)) {
                $categoryController->updateCategory($action);
            } elseif ($method === 'PATCH' && !empty($action) && is_numeric($action) && $id === 'status') {
                $categoryController->updateCategoryStatus($action);
            } elseif ($method === 'DELETE' && !empty($action) && is_numeric($action)) {
                $categoryController->deleteCategory($action);
            } else {
                Response::notFound('Asset categories endpoint not found');
            }
            break;

        // ==================== ASSETS ROUTES ====================
        case 'assets':
            $assetController = new AssetController();
            
            if ($method === 'GET' && $action === '' && $id === '') {
                $assetController->getAllAssets();
            } elseif ($method === 'GET' && $action === 'stats') {
                $assetController->getAssetStats();
            } elseif ($method === 'GET' && $action === 'search') {
                $assetController->searchAssets();
            } elseif ($method === 'GET' && $action === 'by-user' && !empty($id)) {
                $assetController->getAssetsByUser($id);
            } elseif ($method === 'GET' && $action === 'by-department' && !empty($id)) {
                $assetController->getAssetsByDepartment($id);
            } elseif ($method === 'GET' && $action === 'by-barcode' && !empty($id)) {
                $assetController->getAssetByBarcode($id);
            } elseif ($method === 'GET' && $action === 'by-rfid' && !empty($id)) {
                $assetController->getAssetByRFID($id);
            } elseif ($method === 'GET' && $action === 'by-serial' && !empty($id)) {
                $assetController->getAssetBySerial($id);
            } elseif ($method === 'GET' && !empty($action) && is_numeric($action) && $id === '') {
                $assetController->getAssetById($action);
            } elseif ($method === 'GET' && !empty($action) && is_numeric($action) && $id === 'documents') {
                $assetController->getAssetDocuments($action);
            } elseif ($method === 'GET' && !empty($action) && is_numeric($action) && $id === 'images') {
                $assetController->getAssetImages($action);
            } elseif ($method === 'POST' && $action === '') {
                $assetController->createAsset();
            } elseif ($method === 'POST' && !empty($action) && is_numeric($action) && $id === 'documents') {
                $assetController->uploadDocument($action);
            } elseif ($method === 'POST' && !empty($action) && is_numeric($action) && $id === 'images') {
                $assetController->uploadImage($action);
            } elseif ($method === 'POST' && $action === 'images' && $id === 'reorder') {
                $assetController->reorderImages();
            } elseif ($method === 'POST' && !empty($action) && is_numeric($action) && $id === 'barcode') {
                $assetController->generateBarcode($action);
            } elseif ($method === 'POST' && !empty($action) && is_numeric($action) && $id === 'verify') {
                $assetController->verifyAsset($action);
            } elseif ($method === 'PUT' && !empty($action) && is_numeric($action)) {
                $assetController->updateAsset($action);
            } elseif ($method === 'PATCH' && !empty($action) && is_numeric($action) && $id === 'status') {
                $assetController->updateAssetStatus($action);
            } elseif ($method === 'PATCH' && !empty($action) && is_numeric($action) && $id === 'condition') {
                $assetController->updateAssetCondition($action);
            } elseif ($method === 'PATCH' && !empty($action) && is_numeric($action) && $id === 'user') {
                $assetController->updateAssetUser($action);
            } elseif ($method === 'PATCH' && !empty($action) && is_numeric($action) && $id === 'location') {
                $assetController->updateAssetLocation($action);
            } elseif ($method === 'PATCH' && !empty($action) && is_numeric($action) && $id === 'warranty') {
                $assetController->updateAssetWarranty($action);
            } elseif ($method === 'DELETE' && !empty($action) && is_numeric($action) && $id === '') {
                $assetController->deleteAsset($action);
            } elseif ($method === 'DELETE' && $action === 'documents' && !empty($id)) {
                $assetController->deleteDocument($id);
            } elseif ($method === 'DELETE' && $action === 'images' && !empty($id)) {
                $assetController->deleteImage($id);
            } else {
                Response::notFound('Assets endpoint not found');
            }
            break;

        // ==================== DEPRECIATION ROUTES ====================
        case 'depreciation':
            $depreciationController = new DepreciationController();
            
            // GET /depreciation/standards - ດຶງມາດຕະຖານ
            if ($method === 'GET' && $action === 'standards') {
                $depreciationController->getStandards();
            }
            // GET /depreciation/history/all - ດຶງປະຫວັດທັງໝົດ
            elseif ($method === 'GET' && $action === 'history' && $id === 'all') {
                $depreciationController->getAllHistory();
            }
            // GET /depreciation/history/{assetId} - ດຶງປະຫວັດຕາມຊັບສິນ
            elseif ($method === 'GET' && $action === 'history' && !empty($id) && $id !== 'all') {
                $depreciationController->getHistory($id);
            }
            // GET /depreciation/report - ດຶງລາຍງານ
            elseif ($method === 'GET' && $action === 'report') {
                $depreciationController->getReport();
            }
            // GET /depreciation/calculate-asset/{assetId} - ຄິດໄລ່ລ່ວງໜ້າ
            elseif ($method === 'GET' && $action === 'calculate-asset' && !empty($id)) {
                $depreciationController->calculateAssetPreview($id);
            }
            // POST /depreciation/calculate-all - ຄິດໄລ່ທັງໝົດ
            elseif ($method === 'POST' && $action === 'calculate-all') {
                $depreciationController->calculateAll();
            }
            // POST /depreciation/calculate-asset/{assetId} - ຄິດໄລ່ສຳລັບຊັບສິນດຽວ
            elseif ($method === 'POST' && $action === 'calculate-asset' && !empty($id)) {
                $depreciationController->calculateAsset($id);
            }
            // POST /depreciation/standards - ສ້າງມາດຕະຖານ
            elseif ($method === 'POST' && $action === 'standards') {
                $depreciationController->createStandard();
            }
            // PUT /depreciation/standards/{id} - ອັບເດດມາດຕະຖານ
            elseif ($method === 'PUT' && $action === 'standards' && !empty($id)) {
                $depreciationController->updateStandard($id);
            }
            // DELETE /depreciation/standards/{id} - ລຶບມາດຕະຖານ
            elseif ($method === 'DELETE' && $action === 'standards' && !empty($id)) {
                $depreciationController->deleteStandard($id);
            }
            else {
                Response::notFound('Depreciation endpoint not found');
            }
            break;

        // ==================== INVENTORY ITEMS ROUTES ====================
        case 'inventory-items':
            $itemController = new InventoryItemController();

            $action = $segments[1] ?? '';
            $id = $segments[2] ?? '';

            if ($method === 'GET' && $action === 'latest-code') {
                $itemController->getLatestCode();
            }
            elseif ($method === 'GET' && $action === '' && $id === '') {
                $itemController->getAllItems();
            } elseif ($method === 'GET' && $action === 'dropdown') {
                $itemController->getItemsDropdown();
            } elseif ($method === 'GET' && $action === 'stats') {
                $itemController->getItemStats();
            } elseif ($method === 'GET' && $action === 'search') {
                $itemController->searchItems();
            } elseif ($method === 'GET' && $action === 'low-stock') {
                $itemController->getLowStockItems();
            } elseif ($method === 'GET' && $action === 'by-barcode' && !empty($id)) {
                $itemController->getItemByBarcode($id);
            } elseif ($method === 'GET' && $action === 'by-code' && !empty($id)) {
                $itemController->getItemByCode($id);
            } elseif ($method === 'GET' && !empty($action) && is_numeric($action)) {
                $itemController->getItemById($action);
            } elseif ($method === 'POST' && $action === '') {
                $itemController->createItem();
            } elseif ($method === 'POST' && !empty($action) && is_numeric($action) && $id === 'barcode-image') {
                $itemController->uploadBarcodeImage($action);
            } elseif ($method === 'PUT' && !empty($action) && is_numeric($action)) {
                $itemController->updateItem($action);
            } elseif ($method === 'PATCH' && !empty($action) && is_numeric($action) && $id === 'status') {
                $itemController->updateItemStatus($action);
            } elseif ($method === 'PATCH' && !empty($action) && is_numeric($action) && $id === 'price') {
                $itemController->updateItemPrice($action);
            } elseif ($method === 'DELETE' && !empty($action) && is_numeric($action) && $id !== 'hard') {
                $itemController->deleteItem($action);
            } elseif ($method === 'DELETE' && !empty($action) && is_numeric($action) && $id === 'hard') {
                $itemController->hardDeleteItem($action);
            }else {
                Response::notFound('Inventory items endpoint not found');
            }
            break;

        // ==================== INVENTORY STOCK ROUTES ====================
        case 'inventory':
            if ($action === 'stock') {
                $stockController = new InventoryStockController();
                $thirdSegment = $segments[2] ?? '';
                $fourthSegment = $segments[3] ?? '';
                
                if ($method === 'GET' && $thirdSegment === '' && $fourthSegment === '') {
                    $stockController->getAllStock();
                } elseif ($method === 'GET' && $thirdSegment === 'stats') {
                    $stockController->getStockStats();
                } elseif ($method === 'GET' && $thirdSegment === 'movements') {
                    $stockController->getMovements();
                } elseif ($method === 'GET' && $thirdSegment === 'counts') {
                    if ($fourthSegment === 'summary') {
                        $stockController->getStockCountSummary();
                    } else {
                        $stockController->getStockCounts();
                    }
                } 
                        // GET /inventory/stock/counts - ດຶງປະຫວັດການນັບ
                elseif ($method === 'GET' && $thirdSegment === 'counts') {
                    $stockController->getStockCounts();
                }
                elseif ($method === 'GET' && $thirdSegment === 'by-item' && !empty($fourthSegment)) {
                    $stockController->getStockByItem($fourthSegment);
                } elseif ($method === 'GET' && !empty($thirdSegment) && is_numeric($thirdSegment)) {
                    $stockController->getStockById($thirdSegment);
                } elseif ($method === 'POST' && $thirdSegment === 'adjust') {
                    $stockController->adjustStock();
                } elseif ($method === 'POST' && $thirdSegment === 'loan') {
                    $stockController->loanStock();
                } elseif ($method === 'POST' && $thirdSegment === 'return') {
                    $stockController->returnStock();
                } elseif ($method === 'GET' && $thirdSegment === 'loan-history') {
                    $stockController->getLoanHistory();
                } elseif ($method === 'POST' && $thirdSegment === 'transfer') {
                    $stockController->transferStock();
                } elseif ($method === 'POST' && $thirdSegment === 'count') {
                    $stockController->recordStockCount();
                } elseif ($method === 'POST' && $thirdSegment === 'batch-count') {
                    $stockController->recordBatchStockCount();
                } else {
                    Response::notFound('Inventory stock endpoint not found');
                }
            } else {
                Response::notFound('Inventory endpoint not found');
            }
            break;

        // ==================== WAREHOUSE ROUTES ====================
        case 'warehouses':
            $warehouseController = new WarehouseController();
            
            if ($method === 'GET' && $action === '' && $id === '') {
                $warehouseController->getAllWarehouses();
            } elseif ($method === 'GET' && $action === 'dropdown') {
                $warehouseController->getWarehousesForDropdown();
            } elseif ($method === 'GET' && $action === 'stats') {
                $warehouseController->getWarehouseStats();
            } elseif ($method === 'GET' && $action === 'by-code' && !empty($id)) {
                $warehouseController->getWarehouseByCode($id);
            } elseif ($method === 'GET' && !empty($action) && is_numeric($action)) {
                $warehouseController->getWarehouseById($action);
            } elseif ($method === 'POST' && $action === '') {
                $warehouseController->createWarehouse();
            } elseif ($method === 'PUT' && !empty($action) && is_numeric($action)) {
                $warehouseController->updateWarehouse($action);
            } elseif (($method === 'POST' || $method === 'PATCH') && !empty($action) && is_numeric($action) && $id === 'toggle-status') {
                $warehouseController->toggleWarehouseStatus($action);
            } elseif ($method === 'DELETE' && !empty($action) && is_numeric($action) && $id !== 'permanent') {
                $warehouseController->deleteWarehouse($action);
            } elseif ($method === 'DELETE' && !empty($action) && is_numeric($action) && $id === 'permanent') {
                $warehouseController->permanentDeleteWarehouse($action);
            } else {
                Response::notFound('Warehouse endpoint not found');
            }
            break;

        // ==================== PURCHASE ROUTES ====================
        case 'purchases':
            $purchaseController = new PurchaseController();
            
            if ($method === 'GET' && $action === '' && $id === '') {
                $purchaseController->getAllPurchaseOrders();
            } elseif ($method === 'GET' && $action === 'stats') {
                $purchaseController->getPurchaseStats();
            } elseif ($method === 'GET' && $action === 'items') {
                $purchaseController->getPurchaseItems();
            } elseif ($method === 'GET' && $action === 'by-number' && !empty($id)) {
                $purchaseController->getPurchaseOrderByNumber($id);
            } elseif ($method === 'GET' && $action === 'pending') {
                $purchaseController->getPendingOrders();
            } elseif ($method === 'GET' && !empty($action) && is_numeric($action)) {
                $purchaseController->getPurchaseOrderById($action);
            } elseif ($method === 'POST' && $action === '') {
                $purchaseController->createPurchaseOrder();
            } elseif ($method === 'POST' && !empty($action) && is_numeric($action) && $id === 'receive') {
                $purchaseController->receivePurchaseOrder($action);
            } elseif ($method === 'POST' && !empty($action) && is_numeric($action) && $id === 'approve') {
                $purchaseController->approvePurchaseOrder($action);
            } elseif ($method === 'POST' && !empty($action) && is_numeric($action) && $id === 'status') {
                $purchaseController->updatePurchaseStatus($action);
            } elseif ($method === 'POST' && !empty($action) && is_numeric($action) && $id === 'payment') {
                $purchaseController->updatePaymentStatus($action);
            } elseif ($method === 'PUT' && !empty($action) && is_numeric($action)) {
                $purchaseController->updatePurchaseOrder($action);
            } elseif ($method === 'DELETE' && !empty($action) && is_numeric($action)) {
                $purchaseController->deletePurchaseOrder($action);
            } else {
                Response::notFound('Purchase endpoint not found');
            }
            break;

        // ==================== SALES ROUTES ====================
        case 'sales':
            $salesController = new SalesController();
            
            if ($method === 'GET' && $action === '' && $id === '') {
                $salesController->getAllSalesOrders();
            } elseif ($method === 'GET' && $action === 'stats') {
                $salesController->getSalesStats();
            } elseif ($method === 'GET' && $action === 'items') {
                $salesController->getSalesItems();
            } elseif ($method === 'GET' && $action === 'customers') {
                $salesController->getCustomers();
            } elseif ($method === 'GET' && !empty($action) && is_numeric($action)) {
                $salesController->getSalesOrderById($action);
            } elseif ($method === 'POST' && $action === '') {
                $salesController->createSalesOrder();
            } elseif ($method === 'POST' && !empty($action) && is_numeric($action) && $id === 'status') {
                $salesController->updateStatus($action);
            } elseif ($method === 'POST' && !empty($action) && is_numeric($action) && $id === 'payment') {
                $salesController->updatePaymentStatus($action);
            } elseif ($method === 'POST' && !empty($action) && is_numeric($action) && $id === 'sync-asset') {
                $salesController->updateSyncAssetStatus($action);
            } elseif ($method === 'PATCH' && !empty($action) && is_numeric($action) && $id === 'status') {
                $salesController->updateStatus($action);
            } elseif ($method === 'PATCH' && !empty($action) && is_numeric($action) && $id === 'payment') {
                $salesController->updatePaymentStatus($action);
            } elseif ($method === 'PUT' && !empty($action) && is_numeric($action)) {
                $salesController->updateSalesOrder($action);
            } elseif ($method === 'DELETE' && !empty($action) && is_numeric($action)) {
                $salesController->deleteSalesOrder($action);
            } else {
                Response::notFound('Sales endpoint not found');
            }
            break;

        // ==================== SUPPLIER ROUTES ====================
        case 'suppliers':
            $supplierController = new SupplierController();
            
            if ($method === 'GET' && $action === '' && $id === '') {
                $supplierController->getAllSuppliers();
            } elseif ($method === 'GET' && $action === 'dropdown') {
                $supplierController->getSuppliersDropdown();
            } elseif ($method === 'GET' && $action === 'stats') {
                $supplierController->getSupplierStats();
            } elseif ($method === 'GET' && $action === 'search') {
                $supplierController->searchSuppliers();
            } elseif ($method === 'GET' && !empty($action) && is_numeric($action)) {
                $supplierController->getSupplierById($action);
            } elseif ($method === 'POST' && $action === '') {
                $supplierController->createSupplier();
            } elseif ($method === 'PUT' && !empty($action) && is_numeric($action)) {
                $supplierController->updateSupplier($action);
            } elseif ($method === 'PATCH' && !empty($action) && is_numeric($action) && $id === 'status') {
                $supplierController->toggleSupplierStatus($action);
            } elseif ($method === 'DELETE' && !empty($action) && is_numeric($action)) {
                $supplierController->deleteSupplier($action);
            } else {
                Response::notFound('Supplier endpoint not found');
            }
            break;

        // ==================== CUSTOMER ROUTES ====================
        case 'customers':
            $customerController = new CustomerController();
            
            if ($method === 'GET' && $action === '' && $id === '') {
                $customerController->getAllCustomers();
            } elseif ($method === 'GET' && $action === 'dropdown') {
                $customerController->getCustomersDropdown();
            } elseif ($method === 'GET' && $action === 'filters') {
                $customerController->getFilterData();
            } elseif ($method === 'GET' && !empty($action) && is_numeric($action)) {
                $customerController->getCustomerById($action);
            } elseif ($method === 'POST' && $action === '') {
                $customerController->createCustomer();
            } elseif ($method === 'PUT' && !empty($action) && is_numeric($action)) {
                $customerController->updateCustomer($action);
            } elseif ($method === 'DELETE' && !empty($action) && is_numeric($action)) {
                $customerController->deleteCustomer($action);
            } else {
                Response::notFound('Customer endpoint not found');
            }
            break;

        // ==================== BARCODE ROUTES ====================
        case 'barcode':
            $assetController = new AssetController();
            
            if ($method === 'POST' && $action === 'scan') {
                $assetController->recordScan();
            } elseif ($method === 'GET' && $action === 'scans') {
                $assetController->getScanHistory();
            } else {
                Response::notFound('Barcode endpoint not found');
            }
            break;

        case 'barcodes':
            $barcodeController = new BarcodeController();
            
            if ($method === 'GET' && $action === 'items') {
                $barcodeController->getItemsForBarcode();
            } elseif ($method === 'GET' && $action === '' && $id === '') {
                $barcodeController->getAllBarcodes();
            } elseif ($method === 'POST' && $action === '') {
                $barcodeController->createBarcode();
            } elseif ($method === 'POST' && !empty($action) && is_numeric($action) && $id === 'print') {
                $barcodeController->updatePrintStatus($action);
            } elseif ($method === 'POST' && $action === 'generate-from-item' && !empty($id)) {
                $barcodeController->generateFromItem($id);
            } elseif ($method === 'PATCH' && !empty($action) && is_numeric($action) && $id === 'print') {
                $barcodeController->updatePrintStatus($action);
            } else {
                Response::notFound('Barcode endpoint not found');
            }
            break;

 
        // ==================== ASSET SYNC ROUTES ====================
        case 'asset':
            if ($method === 'POST' && $action === 'sync-from-sales') {
                require_once __DIR__ . '/controllers/AssetSyncController.php';
                $assetSyncController = new AssetSyncController();
                $assetSyncController->syncFromSales();
            } else {
                Response::notFound('Asset sync endpoint not found');
            }
            break;

        // ==================== PURCHASE ORDER DETAILS ROUTES ====================
        case 'purchase-order-details':
            $purchaseController = new PurchaseController();
            if ($method === 'GET') {
                $purchaseController->getPurchaseOrderDetails();
            } else {
                Response::notFound('Purchase order details endpoint not found');
            }
            break;

        // ==================== EXCHANGE RATES ROUTES ====================
        case 'exchange-rates':
            $exchangeRateController = new ExchangeRateController();
            if ($method === 'GET' && $action === '' && $id === '') {
                $exchangeRateController->getRates();
            } elseif ($method === 'GET' && $action === 'convert') {
                $exchangeRateController->convertCurrency();
            } elseif ($method === 'POST' && $action === '') {
                $exchangeRateController->saveRate();
            } else {
                Response::notFound('Exchange rate endpoint not found');
            }
            break;

        // ==================== DASHBOARD ROUTES ====================
        case 'dashboard':
            $dashboardController = new DashboardController();
            
            if ($method === 'GET' && $action === 'summary') {
                $dashboardController->getDashboardSummary();
            } elseif ($method === 'GET' && $action === 'recent-activities') {
                $dashboardController->getRecentActivities();
            } elseif ($method === 'GET' && $action === 'charts') {
                $dashboardController->getChartData();
            } else {
                Response::notFound('Dashboard endpoint not found');
            }
            break;

 
        // ==================== STOCK COUNT ROUTES ====================
        case 'stock-counts':
            $stockCountController = new StockCountController();
            // ແກ້ໄຂສ່ວນ parsing segments ຕອນຕົ້ນຂອງ index.php
            $segments = explode('/', trim($path, '/'));
            $resource = $segments[0] ?? '';
            $action = $segments[1] ?? '';
            $id = $segments[2] ?? '';
            $subAction = $segments[3] ?? '';
            $subSubAction = $segments[4] ?? '';

            // ເພີ່ມ log ເພື່ອ debugging
            error_log("=== ROUTE DEBUG ===");
            error_log("Full path: " . $path);
            error_log("Segments: " . json_encode($segments));
            error_log("Resource: $resource, Action: $action, ID: $id, SubAction: $subAction");
      
            error_log("Stock count route - Method: $method, Action: $action, ID: $id, SubAction: $subAction");

            error_log("Stock count route - Method: $method, Action: $action, ID: $id, SubAction: $subAction, SubSubAction: $subSubAction");


                 // GET /stock-counts/{id}/items
            if ($method === 'GET' && !empty($action) && is_numeric($action) && $id === 'items') {
                error_log("Calling getStockCountItems with ID: " . $action);
                $stockCountController->getStockCountItems($action);
            }
            
            // GET /stock-counts/{id}/items - ຕ້ອງມາກ່ອນ /stock-counts/{id}
            if ($method === 'GET' && !empty($action) && is_numeric($action) && $id === 'items') {
                error_log("✅ Matched: GET /stock-counts/{$action}/items");
                $stockCountController->getStockCountItems($action);
            }
            // GET /stock-counts - ດຶງລາຍການການນັບທັງໝົດ
            elseif ($method === 'GET' && $action === '' && $id === '') {
                error_log("✅ Matched: GET /stock-counts");
                $stockCountController->getStockCounts();
            }
            // GET /stock-counts/stats - ດຶງສະຖິຕິ
            elseif ($method === 'GET' && $action === 'stats') {
                error_log("✅ Matched: GET /stock-counts/stats");
                $stockCountController->getStockCountStats();
            }
            // GET /stock-counts/check-code - ກວດສອບລະຫັດຊ້ຳ
            elseif ($method === 'GET' && $action === 'check-code') {
                error_log("✅ Matched: GET /stock-counts/check-code");
                $stockCountController->checkSessionCode();
            }
            // GET /stock-counts/{id} - ດຶງຂໍ້ມູນການນັບຕາມ ID
            elseif ($method === 'GET' && !empty($action) && is_numeric($action) && $id === '') {
                error_log("✅ Matched: GET /stock-counts/{$action}");
                $stockCountController->getStockCountById($action);
            }
            // POST /stock-counts - ສ້າງການນັບໃໝ່
            elseif ($method === 'POST' && $action === '' && $id === '') {
                error_log("✅ Matched: POST /stock-counts");
                $stockCountController->createStockCount();
            }
            // POST /stock-counts/{id}/start - ເລີ່ມການນັບ
            elseif ($method === 'POST' && !empty($action) && is_numeric($action) && $id === 'start') {
                error_log("✅ Matched: POST /stock-counts/{$action}/start");
                $stockCountController->startStockCount($action);
            }
            // POST /stock-counts/{id}/complete - ສຳເລັດການນັບ
            elseif ($method === 'POST' && !empty($action) && is_numeric($action) && $id === 'complete') {
                error_log("✅ Matched: POST /stock-counts/{$action}/complete");
                $stockCountController->completeStockCount($action);
            }
            // POST /stock-counts/{id}/cancel - ຍົກເລີກການນັບ
            elseif ($method === 'POST' && !empty($action) && is_numeric($action) && $id === 'cancel') {
                error_log("✅ Matched: POST /stock-counts/{$action}/cancel");
                $stockCountController->cancelStockCount($action);
            }
            // POST /stock-counts/{id}/count - ບັນທຶກການນັບ (ສະບັບງ່າຍ)
            elseif ($method === 'POST' && !empty($action) && is_numeric($action) && $id === 'count') {
                error_log("✅ Matched: POST /stock-counts/{$action}/count");
                $stockCountController->recordSimpleCount($action);
            }
            else {
                Response::notFound('Stock count endpoint not found');
            }
            break;

        // ==================== STOCK ADJUSTMENT ROUTES ====================
        case 'stock-adjustments':
            $stockAdjustmentController = new StockAdjustmentController();
            
            if ($method === 'GET' && $action === '' && $id === '') {
                $stockAdjustmentController->getAdjustments();
            } elseif ($method === 'GET' && $action === 'stats') {
                $stockAdjustmentController->getAdjustmentStats();
            } elseif ($method === 'POST' && $action === '') {
                $stockAdjustmentController->createAdjustment();
            } elseif ($method === 'POST' && !empty($action) && is_numeric($action) && $id === 'approve') {
                $stockAdjustmentController->approveAdjustment($action);
            } else {
                Response::notFound('Stock adjustment endpoint not found');
            }
            break;

        // ==================== TEST ROUTE ====================
        case 'test-update':
            if ($method === 'PUT' && !empty($action) && is_numeric($action)) {
                header('Content-Type: application/json');
                echo json_encode(['status' => 'success', 'message' => 'Test update works!', 'id' => $action]);
                exit;
            } else {
                Response::notFound('Test endpoint not found');
            }
            break;

        // ==================== ROLES MANAGEMENT ROUTES ====================
        case 'roles':
            $roleController = new RoleController();
            
            // GET /api/roles - ດຶງຂໍ້ມູນບົດບາດທັງໝົດ
            if ($method === 'GET' && $action === '' && $id === '') {
                $roleController->getAll();
            }
            // GET /api/roles/dropdown - ດຶງຂໍ້ມູນບົດບາດສຳລັບ dropdown
            elseif ($method === 'GET' && $action === 'dropdown') {
                $roleController->getForDropdown();
            }
            // GET /api/roles/stats - ດຶງສະຖິຕິບົດບາດ
            elseif ($method === 'GET' && $action === 'stats') {
                $roleController->getStats();
            }
            // GET /api/roles/search - ຄົ້ນຫາບົດບາດ
            elseif ($method === 'GET' && $action === 'search') {
                $roleController->search();
            }
            // GET /api/roles/{id} - ດຶງຂໍ້ມູນບົດບາດຕາມ ID
            elseif ($method === 'GET' && !empty($action) && is_numeric($action) && $id === '') {
                $roleController->getById($action);
            }
            // GET /api/roles/{id}/permissions - ດຶງສິດທິຂອງບົດບາດ
            elseif ($method === 'GET' && !empty($action) && is_numeric($action) && $id === 'permissions') {
                $roleController->getPermissions($action);
            }
            // POST /api/roles - ສ້າງບົດບາດໃໝ່
            elseif ($method === 'POST' && $action === '' && $id === '') {
                $roleController->create();
            }
            // POST /api/roles/{id}/duplicate - ຄັດລອກບົດບາດ
            elseif ($method === 'POST' && !empty($action) && is_numeric($action) && $id === 'duplicate') {
                $roleController->duplicate($action);
            }
            // POST /api/roles/{id}/permissions - ບັນທຶກສິດທິຂອງບົດບາດ
            elseif ($method === 'POST' && !empty($action) && is_numeric($action) && $id === 'permissions') {
                $roleController->savePermissions($action);
            }
            // PUT /api/roles/{id} - ອັບເດດບົດບາດ
            elseif ($method === 'PUT' && !empty($action) && is_numeric($action) && $id === '') {
                $roleController->update($action);
            }
            // PATCH /api/roles/{id}/status - ປ່ຽນສະຖານະບົດບາດ
            elseif ($method === 'PATCH' && !empty($action) && is_numeric($action) && $id === 'status') {
                $roleController->updateStatus($action);
            }
            // DELETE /api/roles/{id} - ລຶບບົດບາດ
            elseif ($method === 'DELETE' && !empty($action) && is_numeric($action) && $id === '') {
                $roleController->delete($action);
            }
            else {
                Response::notFound('Roles endpoint not found');
            }
            break;

        

        // ==================== DEFAULT - API INFO ====================
        default:
            $response = [
                'name' => 'Asset Management API',
                'version' => '2.0.0',
                'endpoints' => [
                    'POST /auth/register' => 'Register new user',
                    'POST /auth/login' => 'Login user',
                    'POST /auth/refresh' => 'Refresh access token',
                    'POST /auth/logout' => 'Logout user',
                    'GET /user/profile' => 'Get current user profile',
                    'PUT /user/profile' => 'Update current user profile',
                    'POST /user/change-password' => 'Change password',
                    'POST /user/profile-image' => 'Upload profile image',
                    'GET /users' => 'Get all users',
                    'GET /users/dropdown' => 'Get users for dropdown',
                    'POST /users' => 'Create new user',
                    'GET /companies' => 'Get all companies',
                    'GET /companies/dropdown' => 'Get companies for dropdown',
                    'GET /departments' => 'Get all departments',
                    'GET /departments/dropdown' => 'Get departments for dropdown',
                    'GET /asset-categories' => 'Get all categories',
                    'GET /asset-categories/tree' => 'Get category tree',
                    'GET /asset-categories/dropdown' => 'Get categories for dropdown',
                    'GET /assets' => 'Get all assets',
                    'GET /assets/stats' => 'Get asset statistics',
                    'POST /assets' => 'Create new asset',
                    'GET /depreciation/standards' => 'Get depreciation standards',
                    'POST /depreciation/calculate-all' => 'Calculate all depreciation',
                    'GET /inventory/stock' => 'Get inventory stock',
                    'POST /inventory/stock/adjust' => 'Adjust stock',
                    'GET /purchases' => 'Get purchase orders',
                    'POST /purchases' => 'Create purchase order',
                    'GET /sales' => 'Get sales orders',
                    'POST /sales' => 'Create sales order',
                    'GET /suppliers' => 'Get suppliers',
                    'POST /suppliers' => 'Create supplier',
                    'GET /customers' => 'Get customers',
                    'POST /customers' => 'Create customer',
                    'GET /dashboard/summary' => 'Get dashboard summary'
                ],
                'documentation' => '/api/docs',
                'timestamp' => date('Y-m-d H:i:s')
            ];
            
            ob_end_clean();
            Response::success($response, 200, 'API is running');
    }
} catch (Exception $e) {
    ob_end_clean();
    Response::error('Internal Server Error: ' . $e->getMessage(), 500);
}

ob_end_flush();
?>