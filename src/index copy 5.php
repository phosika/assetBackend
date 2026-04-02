<?php
// src/index.php - ແກ້ໄຂບັນຫາ headers

// ຕ້ອງແນ່ໃຈວ່າບໍ່ມີ output ກ່ອນ headers
ob_start(); // ເລີ່ມ output buffering

// ຕັ້ງ CORS headers ໃຫ້ຄົບຖ້ວນ ແລະ ຖືກຕ້ອງ
// ຕັ້ງ CORS headers ໃຫ້ຄົບຖ້ວນ ແລະ ຖືກຕ້ອງ (ເອົາອັນດຽວ)
header('Access-Control-Allow-Origin: http://localhost:5173');
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Accept, Origin');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Max-Age: 3600');

// ຈັດການ CORS ສຳລັບ preflight requests (OPTIONS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

 

// ຈາກນັ້ນຄ່ອຍ require ໄຟລ໌ຕ່າງໆ
require_once __DIR__ . '/utils/Response.php';
require_once __DIR__ . '/utils/Validator.php';
require_once __DIR__ . '/utils/FileUploader.php';
require_once __DIR__ . '/middleware/AuthMiddleware.php';
require_once __DIR__ . '/controllers/AuthController.php';
require_once __DIR__ . '/controllers/UserController.php';
require_once __DIR__ . '/controllers/CompanyController.php';
require_once __DIR__ . '/controllers/DepartmentController.php';
require_once __DIR__ . '/controllers/AssetCategoryController.php';
require_once __DIR__ . '/controllers/AssetController.php';
require_once __DIR__ . '/controllers/SupplierController.php';
require_once __DIR__ . '/controllers/InventoryItemController.php';
require_once __DIR__ . '/controllers/InventoryStockController.php'; // ເພີ່ມ InventoryStockController
require_once __DIR__ . '/controllers/PurchaseController.php';   // ເພີ່ມ PurchaseController
require_once __DIR__ . '/controllers/SalesController.php';      // ເພີ່ມ SalesController
require_once __DIR__ . '/controllers/CustomerController.php';   // ເພີ່ມ CustomerController
require_once __DIR__ . '/controllers/BarcodeController.php';
require_once __DIR__ . '/controllers/AssetSyncController.php'; // ເພີ່ມ AssetSyncController
require_once __DIR__ . '/models/User.php';
require_once __DIR__ . '/models/Department.php';
require_once __DIR__ . '/models/Company.php';
require_once __DIR__ . '/models/AssetCategory.php';
require_once __DIR__ . '/models/Asset.php';
require_once __DIR__ . '/models/Supplier.php';
require_once __DIR__ . '/models/InventoryItem.php'; // ເພີ່ມ InventoryItem model
require_once __DIR__ . '/models/InventoryStock.php'; // ເພີ່ມ InventoryStock model
require_once __DIR__ . '/models/PurchaseOrder.php';              // ເພີ່ມ PurchaseOrder model
require_once __DIR__ . '/models/SalesOrder.php';                 // ເພີ່ມ SalesOrder model
require_once __DIR__ . '/models/Customer.php';                   // ເພີ່ມ Customer model
require_once __DIR__ . '/models/Barcode.php';                    // ເພີ່ມ Barcode model
require_once __DIR__ . '/models/AssetSyncLog.php';              // ເພີ່ມ AssetSyncLog model


$request = $_SERVER['REQUEST_URI'];
$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($request, PHP_URL_PATH);

// ແກ້ໄຂການອ່ານ path ໃຫ້ຖືກຕ້ອງ
$script_name = $_SERVER['SCRIPT_NAME'];
$path = str_replace($script_name, '', $path);
$path = ltrim($path, '/');

// ຂ້າມ /api prefix ຖ້າມີ
if (strpos($path, 'api/') === 0) {
    $path = substr($path, 4);
}

$segments = explode('/', trim($path, '/'));

// ເລີ່ມ session ຖ້າຍັງບໍ່ທັນເລີ່ມ
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ເພີ່ມ rate limiting (ພື້ນຖານ)
$key = $_SERVER['REMOTE_ADDR'] . ':' . ($segments[0] ?? '');
$limit = 100; // 100 requests
$timeWindow = 3600; // ຕໍ່ 1 ຊົ່ວໂມງ

if (isset($_SESSION[$key])) {
    if ($_SESSION[$key]['count'] >= $limit && time() - $_SESSION[$key]['time'] < $timeWindow) {
        ob_end_clean(); // ລ້າງ buffer ກ່ອນສົ່ງ error
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

$resource = $segments[0] ?? '';
$action = $segments[1] ?? '';
$id = $segments[2] ?? '';
$subAction = $segments[3] ?? '';

// Routing
try {
    switch ($resource) {
        // ==================== AUTH ROUTES ====================
        case 'auth':
            $authController = new AuthController();
            switch ($method) {
                case 'POST':
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
                    break;
                default:
                    Response::error('Method not allowed', 405);
            }
            break;

        // ==================== USER PROFILE ROUTES ====================
        case 'user':
            $userController = new UserController();
            switch ($method) {
                case 'GET':
                    if ($action === 'profile') {
                        $userController->getProfile();
                    } else {
                        Response::notFound('User endpoint not found');
                    }
                    break;
                case 'PUT':
                    if ($action === 'profile') {
                        $userController->updateProfile();
                    } else {
                        Response::notFound('User endpoint not found');
                    }
                    break;
                case 'POST':
                    if ($action === 'change-password') {
                        $userController->changePassword();
                    } elseif ($action === 'profile-image') {
                        $userController->uploadProfileImage();
                    } else {
                        Response::notFound('User endpoint not found');
                    }
                    break;
                default:
                    Response::error('Method not allowed', 405);
            }
            break;

        // ==================== USERS MANAGEMENT ROUTES ====================
        case 'users':
            $userController = new UserController();
            
            // GET /users - ດຶງຂໍ້ມູນຜູ້ໃຊ້ທັງໝົດ
            if ($method === 'GET' && $action === '' && $id === '') {
                $userController->getAllUsers();
            }
            // GET /users/dropdown
            elseif ($method === 'GET' && $action === 'dropdown') {
                $userController->getUsersForDropdown();
            }
            // GET /users/export
            elseif ($method === 'GET' && $action === 'export') {
                $userController->exportUsers();
            }
            // GET /users/stats
            elseif ($method === 'GET' && $action === 'stats') {
                $userController->getUserStats();
            }
            // GET /users/search
            elseif ($method === 'GET' && $action === 'search') {
                $userController->searchUsers();
            }
            // GET /users/managers
            elseif ($method === 'GET' && $action === 'managers') {
                $userController->getManagers();
            }
            // GET /users/by-department/{departmentId}
            elseif ($method === 'GET' && $action === 'by-department' && !empty($id)) {
                $userController->getUsersByDepartment($id);
            }
            // GET /users/activities/{userId}
            elseif ($method === 'GET' && $action === 'activities' && !empty($id)) {
                $userController->getUserActivities($id);
            }
            // GET /users/{id}
            elseif ($method === 'GET' && !empty($action) && is_numeric($action)) {
                $userController->getUserById($action);
            }
            // POST /users - ສ້າງຜູ້ໃຊ້ໃໝ່
            elseif ($method === 'POST' && $action === '') {
                $userController->createUser();
            }
            // PUT /users/{id} - ອັບເດດຜູ້ໃຊ້
            elseif ($method === 'PUT' && !empty($action) && is_numeric($action)) {
                $userController->updateUser($action);
            }
            // DELETE /users/{id} - ລຶບຜູ້ໃຊ້
            elseif ($method === 'DELETE' && !empty($action) && is_numeric($action)) {
                $userController->deleteUser($action);
            }
            // PATCH /users/{id}/status - ອັບເດດສະຖານະ
            elseif ($method === 'PATCH' && !empty($action) && is_numeric($action) && $id === 'status') {
                $userController->updateUserStatus($action);
            }
            // PATCH /users/{id}/role - ອັບເດດບົດບາດ
            elseif ($method === 'PATCH' && !empty($action) && is_numeric($action) && $id === 'role') {
                $userController->updateUserRole($action);
            }
            else {
                Response::notFound('Users endpoint not found');
            }
            break;

        // ==================== COMPANY MANAGEMENT ROUTES ====================
        case 'companies':
            $companyController = new CompanyController();
            
            // GET /companies - ດຶງຂໍ້ມູນບໍລິສັດທັງໝົດ
            if ($method === 'GET' && $action === '' && $id === '') {
                $companyController->getAllCompanies();
            }
            // GET /companies/dropdown
            elseif ($method === 'GET' && $action === 'dropdown') {
                $companyController->getCompaniesForDropdown();
            }
            // GET /companies/parents
            elseif ($method === 'GET' && $action === 'parents') {
                $companyController->getParentCompanies();
            }
            // GET /companies/stats
            elseif ($method === 'GET' && $action === 'stats') {
                $companyController->getCompanyStats();
            }
            // GET /companies/{id}
            elseif ($method === 'GET' && !empty($action) && is_numeric($action)) {
                $companyController->getCompanyById($action);
            }
            // POST /companies - ສ້າງບໍລິສັດໃໝ່
            elseif ($method === 'POST' && $action === '') {
                $companyController->createCompany();
            }
            // PUT /companies/{id} - ອັບເດດບໍລິສັດ
            elseif ($method === 'PUT' && !empty($action) && is_numeric($action)) {
                $companyController->updateCompany($action);
            }
            // PATCH /companies/{id}/status - ອັບເດດສະຖານະ
            elseif ($method === 'PATCH' && !empty($action) && is_numeric($action) && $id === 'status') {
                $companyController->updateCompanyStatus($action);
            }
            // DELETE /companies/{id} - ລຶບບໍລິສັດ (soft delete)
            elseif ($method === 'DELETE' && !empty($action) && is_numeric($action) && $id !== 'permanent') {
                $companyController->deleteCompany($action);
            }
            // DELETE /companies/{id}/permanent - ລຶບບໍລິສັດແບບຖາວອນ
            elseif ($method === 'DELETE' && !empty($action) && is_numeric($action) && $id === 'permanent') {
                $companyController->deleteCompanyPermanently($action);
            }
            else {
                Response::notFound('Companies endpoint not found');
            }
            break;

        // ==================== DEPARTMENT MANAGEMENT ROUTES ====================
        case 'departments':
            $departmentController = new DepartmentController();
            
            // GET /departments - ດຶງຂໍ້ມູນພະແນກທັງໝົດ
            if ($method === 'GET' && $action === '' && $id === '') {
                $departmentController->getAllDepartments();
            }
            // GET /departments/dropdown
            elseif ($method === 'GET' && $action === 'dropdown') {
                $departmentController->getDepartmentsForDropdown();
            }
            // GET /departments/parents
            elseif ($method === 'GET' && $action === 'parents') {
                $departmentController->getParentDepartments();
            }
            // GET /departments/available-managers
            elseif ($method === 'GET' && $action === 'available-managers') {
                $departmentController->getAvailableManagers();
            }
            // GET /departments/stats
            elseif ($method === 'GET' && $action === 'stats') {
                $departmentController->getDepartmentStats();
            }
            // GET /departments/company/{companyId}
            elseif ($method === 'GET' && $action === 'company' && !empty($id)) {
                $departmentController->getDepartmentsByCompany($id);
            }
            // GET /departments/check-manager/{userId}/{departmentId}
            elseif ($method === 'GET' && $action === 'check-manager' && !empty($id) && !empty($subAction)) {
                $departmentController->checkManagerEligibility($id, $subAction);
            }
            // GET /departments/{id}
            elseif ($method === 'GET' && !empty($action) && is_numeric($action)) {
                $departmentController->getDepartmentById($action);
            }
            // POST /departments - ສ້າງພະແນກໃໝ່
            elseif ($method === 'POST' && $action === '') {
                $departmentController->createDepartment();
            }
            // PUT /departments/{id} - ອັບເດດພະແນກ
            elseif ($method === 'PUT' && !empty($action) && is_numeric($action)) {
                $departmentController->updateDepartment($action);
            }
            // PATCH /departments/{id}/status - ອັບເດດສະຖານະ
            elseif ($method === 'PATCH' && !empty($action) && is_numeric($action) && $id === 'status') {
                $departmentController->updateDepartmentStatus($action);
            }
            // PATCH /departments/{id}/manager - ອັບເດດຜູ້ຈັດການ
            elseif ($method === 'PATCH' && !empty($action) && is_numeric($action) && $id === 'manager') {
                $departmentController->updateDepartmentManager($action);
            }
            // DELETE /departments/{id} - ລຶບພະແນກ
            elseif ($method === 'DELETE' && !empty($action) && is_numeric($action)) {
                $departmentController->deleteDepartment($action);
            }
            else {
                Response::notFound('Departments endpoint not found');
            }
            break;

        // ==================== ASSET CATEGORIES MANAGEMENT ROUTES ====================
        case 'asset-categories':
            $categoryController = new AssetCategoryController();
            
            // GET /asset-categories - ດຶງຂໍ້ມູນທັງໝົດ
            if ($method === 'GET' && $action === '' && $id === '') {
                $categoryController->getAllCategories();
            }
            // GET /asset-categories/tree
            elseif ($method === 'GET' && $action === 'tree') {
                $categoryController->getCategoryTree();
            }
            // GET /asset-categories/dropdown
            elseif ($method === 'GET' && $action === 'dropdown') {
                $categoryController->getCategoriesForDropdown();
            }
            // GET /asset-categories/stats
            elseif ($method === 'GET' && $action === 'stats') {
                $categoryController->getCategoryStats();
            }
            // GET /asset-categories/by-level/{level}
            elseif ($method === 'GET' && $action === 'by-level' && !empty($id)) {
                $categoryController->getCategoriesByLevel($id);
            }
            // GET /asset-categories/by-level-parent
            elseif ($method === 'GET' && $action === 'by-level-parent') {
                $categoryController->getCategoriesByTargetLevel();
            }
            // GET /asset-categories/{id}
            elseif ($method === 'GET' && !empty($action) && is_numeric($action)) {
                $categoryController->getCategoryById($action);
            }
            // POST /asset-categories - ສ້າງໃໝ່
            elseif ($method === 'POST' && $action === '') {
                $categoryController->createCategory();
            }
            // PUT /asset-categories/{id} - ອັບເດດ
            elseif ($method === 'PUT' && !empty($action) && is_numeric($action)) {
                $categoryController->updateCategory($action);
            }
            // PATCH /asset-categories/{id}/status - ອັບເດດສະຖານະ
            elseif ($method === 'PATCH' && !empty($action) && is_numeric($action) && $id === 'status') {
                $categoryController->updateCategoryStatus($action);
            }
            // DELETE /asset-categories/{id} - ລຶບ
            elseif ($method === 'DELETE' && !empty($action) && is_numeric($action)) {
                $categoryController->deleteCategory($action);
            }
            else {
                Response::notFound('Asset categories endpoint not found');
            }
            break;

        // ==================== ASSETS ROUTES ====================
        case 'assets':
            $assetController = new AssetController();
            
            // GET /assets - ດຶງຂໍ້ມູນຊັບສິນທັງໝົດ
            if ($method === 'GET' && $action === '' && $id === '') {
                $assetController->getAllAssets();
            }
            // GET /assets/stats
            elseif ($method === 'GET' && $action === 'stats') {
                $assetController->getAssetStats();
            }
            // GET /assets/search
            elseif ($method === 'GET' && $action === 'search') {
                $assetController->searchAssets();
            }
            // GET /assets/by-user/{userId}
            elseif ($method === 'GET' && $action === 'by-user' && !empty($id)) {
                $assetController->getAssetsByUser($id);
            }
            // GET /assets/by-department/{departmentId}
            elseif ($method === 'GET' && $action === 'by-department' && !empty($id)) {
                $assetController->getAssetsByDepartment($id);
            }
            // GET /assets/by-barcode/{barcode}
            elseif ($method === 'GET' && $action === 'by-barcode' && !empty($id)) {
                $assetController->getAssetByBarcode($id);
            }
            // GET /assets/by-rfid/{rfid}
            elseif ($method === 'GET' && $action === 'by-rfid' && !empty($id)) {
                $assetController->getAssetByRFID($id);
            }
            // GET /assets/by-serial/{serial}
            elseif ($method === 'GET' && $action === 'by-serial' && !empty($id)) {
                $assetController->getAssetBySerial($id);
            }
            // GET /assets/{id}
            elseif ($method === 'GET' && !empty($action) && is_numeric($action)) {
                $assetController->getAssetById($action);
            }
            // GET /assets/{assetId}/documents
            elseif ($method === 'GET' && !empty($action) && is_numeric($action) && $id === 'documents') {
                $assetController->getAssetDocuments($action);
            }
            // GET /assets/{assetId}/images
            elseif ($method === 'GET' && !empty($action) && is_numeric($action) && $id === 'images') {
                $assetController->getAssetImages($action);
            }
            // POST /assets - ສ້າງຊັບສິນໃໝ່
            elseif ($method === 'POST' && $action === '') {
                $assetController->createAsset();
            }
            // POST /assets/{assetId}/documents - ອັບໂຫຼດເອກະສານ
            elseif ($method === 'POST' && !empty($action) && is_numeric($action) && $id === 'documents') {
                $assetController->uploadDocument($action);
            }
            // POST /assets/{assetId}/images - ອັບໂຫຼດຮູບພາບ
            elseif ($method === 'POST' && !empty($action) && is_numeric($action) && $id === 'images') {
                $assetController->uploadImage($action);
            }
            // POST /assets/images/reorder - ຈັດລຽງຮູບ
            elseif ($method === 'POST' && $action === 'images' && $id === 'reorder') {
                $assetController->reorderImages();
            }
            // POST /assets/{assetId}/barcode - ສ້າງ barcode
            elseif ($method === 'POST' && !empty($action) && is_numeric($action) && $id === 'barcode') {
                $assetController->generateBarcode($action);
            }
            // POST /assets/{id}/verify - ກວດສອບຊັບສິນ
            elseif ($method === 'POST' && !empty($action) && is_numeric($action) && $id === 'verify') {
                $assetController->verifyAsset($action);
            }
            // PUT /assets/{id} - ອັບເດດຊັບສິນ
            elseif ($method === 'PUT' && !empty($action) && is_numeric($action)) {
                $assetController->updateAsset($action);
            }
            // PATCH /assets/{id}/status - ອັບເດດສະຖານະ
            elseif ($method === 'PATCH' && !empty($action) && is_numeric($action) && $id === 'status') {
                $assetController->updateAssetStatus($action);
            }
            // PATCH /assets/{id}/condition - ອັບເດດສະພາບ
            elseif ($method === 'PATCH' && !empty($action) && is_numeric($action) && $id === 'condition') {
                $assetController->updateAssetCondition($action);
            }
            // PATCH /assets/{id}/user - ອັບເດດຜູ້ຖືຄອງ
            elseif ($method === 'PATCH' && !empty($action) && is_numeric($action) && $id === 'user') {
                $assetController->updateAssetUser($action);
            }
            // PATCH /assets/{id}/location - ອັບເດດສະຖານທີ່
            elseif ($method === 'PATCH' && !empty($action) && is_numeric($action) && $id === 'location') {
                $assetController->updateAssetLocation($action);
            }
            // PATCH /assets/{id}/warranty - ອັບເດດການຮັບປະກັນ
            elseif ($method === 'PATCH' && !empty($action) && is_numeric($action) && $id === 'warranty') {
                $assetController->updateAssetWarranty($action);
            }
            // DELETE /assets/{id} - ລຶບຊັບສິນ
            elseif ($method === 'DELETE' && !empty($action) && is_numeric($action)) {
                $assetController->deleteAsset($action);
            }
            // DELETE /assets/documents/{documentId} - ລຶບເອກະສານ
            elseif ($method === 'DELETE' && $action === 'documents' && !empty($id)) {
                $assetController->deleteDocument($id);
            }
            // DELETE /assets/images/{imageId} - ລຶບຮູບພາບ
            elseif ($method === 'DELETE' && $action === 'images' && !empty($id)) {
                $assetController->deleteImage($id);
            }
            else {
                Response::notFound('Assets endpoint not found');
            }
            break;
 

        // ==================== INVENTORY STOCK ROUTES ====================
        case 'inventory':
            // ຈັດການຕາມ sub-resource
            $subResource = $action ?? '';
            
            if ($subResource === 'stock') {
                // ກວດສອບວ່າໄຟລ໌ມີຢູ່ ແລະ ຮຽກໃຊ້
                $controllerPath = __DIR__ . '/controllers/InventoryStockController.php';
                if (!file_exists($controllerPath)) {
                    error_log("Controller file not found: " . $controllerPath);
                    Response::error('Inventory stock controller not found', 500);
                    break;
                }
                
                require_once $controllerPath;
                $stockController = new InventoryStockController();
                $thirdSegment = $segments[2] ?? '';
                $fourthSegment = $segments[3] ?? '';
                
                // GET /inventory/stock - ດຶງຂໍ້ມູນສະຕ໋ອກທັງໝົດ
                if ($method === 'GET' && $thirdSegment === '' && !$fourthSegment) {
                    $stockController->getAllStock();
                }
                // GET /inventory/stock/stats - ດຶງສະຖິຕິ
                elseif ($method === 'GET' && $thirdSegment === 'stats') {
                    $stockController->getStockStats();
                }
                // GET /inventory/stock/movements - ດຶງປະຫວັດການເຄື່ອນໄຫວ
                elseif ($method === 'GET' && $thirdSegment === 'movements') {
                    $stockController->getMovements();
                }
                // GET /inventory/stock/counts - ດຶງປະຫວັດການນັບສະຕ໋ອກ
                elseif ($method === 'GET' && $thirdSegment === 'counts') {
                    if ($fourthSegment === 'summary') {
                        $stockController->getStockCountSummary();
                    } else {
                        $stockController->getStockCounts();
                    }
                }
                // GET /inventory/stock/by-item/{itemId} - ດຶງສະຕ໋ອກຕາມ item
                elseif ($method === 'GET' && $thirdSegment === 'by-item' && !empty($fourthSegment)) {
                    $stockController->getStockByItem($fourthSegment);
                }
                // GET /inventory/stock/{id} - ດຶງສະຕ໋ອກຕາມ ID
                elseif ($method === 'GET' && !empty($thirdSegment) && is_numeric($thirdSegment)) {
                    $stockController->getStockById($thirdSegment);
                }
                // POST /inventory/stock/adjust - ປັບປຸງສະຕ໋ອກ
                elseif ($method === 'POST' && $thirdSegment === 'adjust') {
                    $stockController->adjustStock();
                }
                // POST /inventory/stock/transfer - ໂອນສະຕ໋ອກ
                elseif ($method === 'POST' && $thirdSegment === 'transfer') {
                    $stockController->transferStock();
                }
                // POST /inventory/stock/count - ບັນທຶກການນັບສະຕ໋ອກ
                elseif ($method === 'POST' && $thirdSegment === 'count') {
                    $stockController->recordStockCount();
                }
                // POST /inventory/stock/batch-count - ບັນທຶກການນັບຫຼາຍລາຍການ
                elseif ($method === 'POST' && $thirdSegment === 'batch-count') {
                    $stockController->recordBatchStockCount();
                }
                else {
                    Response::notFound('Inventory stock endpoint not found');
                }
                break;
            }
            break;

            // ==================== INVENTORY ITEMS ROUTES ====================
 
            case 'inventory-items':
            $itemController = new InventoryItemController();
            
            // GET /inventory-items - ດຶງຂໍ້ມູນສິນຄ້າທັງໝົດ
            if ($method === 'GET' && $action === '' && $id === '') {
                $itemController->getAllItems();
            }
            // GET /inventory-items/dropdown
            elseif ($method === 'GET' && $action === 'dropdown') {
                $itemController->getItemsDropdown();
            }
            // GET /inventory-items/stats
            elseif ($method === 'GET' && $action === 'stats') {
                $itemController->getItemStats();
            }
            // GET /inventory-items/search
            elseif ($method === 'GET' && $action === 'search') {
                $itemController->searchItems();
            }
            // GET /inventory-items/low-stock
            elseif ($method === 'GET' && $action === 'low-stock') {
                $itemController->getLowStockItems();
            }
            // GET /inventory-items/by-barcode/{barcode}
            elseif ($method === 'GET' && $action === 'by-barcode' && !empty($id)) {
                $itemController->getItemByBarcode($id);
            }
            // GET /inventory-items/by-code/{code}
            elseif ($method === 'GET' && $action === 'by-code' && !empty($id)) {
                $itemController->getItemByCode($id);
            }
            // GET /inventory-items/{id}
            elseif ($method === 'GET' && !empty($action) && is_numeric($action)) {
                $itemController->getItemById($action);
            }
            // POST /inventory-items - ສ້າງໃໝ່
            elseif ($method === 'POST' && $action === '') {
                $itemController->createItem();
            }
            // POST /inventory-items/{id}/barcode-image - ອັບໂຫຼດຮູບ barcode
            elseif ($method === 'POST' && !empty($action) && is_numeric($action) && $id === 'barcode-image') {
                $itemController->uploadBarcodeImage($action);
            }
            // PUT /inventory-items/{id} - ອັບເດດ
            elseif ($method === 'PUT' && !empty($action) && is_numeric($action)) {
                $itemController->updateItem($action);
            }
            // PATCH /inventory-items/{id}/status - ອັບເດດສະຖານະ
            elseif ($method === 'PATCH' && !empty($action) && is_numeric($action) && $id === 'status') {
                $itemController->updateItemStatus($action);
            }
            // PATCH /inventory-items/{id}/price - ອັບເດດລາຄາ
            elseif ($method === 'PATCH' && !empty($action) && is_numeric($action) && $id === 'price') {
                $itemController->updateItemPrice($action);
            }
            // DELETE /inventory-items/{id} - soft delete
            elseif ($method === 'DELETE' && !empty($action) && is_numeric($action) && $id !== 'hard') {
                $itemController->deleteItem($action);
            }
            // DELETE /inventory-items/{id}/hard - hard delete
            elseif ($method === 'DELETE' && !empty($action) && is_numeric($action) && $id === 'hard') {
                $itemController->hardDeleteItem($action);
            }
            else {
                Response::notFound('Inventory items endpoint not found');
            }
            break;
        
            // ເພີ່ມໃນສ່ວນ switch ($resource) ຂອງ /home/phosika/Fixasset/assetapplication/BACKEND/src/index.php

        // ==================== WAREHOUSE ROUTES ====================
        case 'warehouses':
            require_once __DIR__ . '/controllers/WarehouseController.php';
            $warehouseController = new WarehouseController();
            
            // GET /warehouses - ດຶງຂໍ້ມູນສາງທັງໝົດ
            if ($method === 'GET' && $action === '' && $id === '') {
                $warehouseController->getAllWarehouses();
            }
            // GET /warehouses/dropdown
            elseif ($method === 'GET' && $action === 'dropdown') {
                $warehouseController->getWarehousesForDropdown();
            }
            // GET /warehouses/stats
            elseif ($method === 'GET' && $action === 'stats') {
                $warehouseController->getWarehouseStats();
            }
            // GET /warehouses/by-code/{code}
            elseif ($method === 'GET' && $action === 'by-code' && !empty($id)) {
                $warehouseController->getWarehouseByCode($id);
            }
            // GET /warehouses/{id}
            elseif ($method === 'GET' && !empty($action) && is_numeric($action)) {
                $warehouseController->getWarehouseById($action);
            }
            // POST /warehouses - ສ້າງສາງໃໝ່
            elseif ($method === 'POST' && $action === '') {
                $warehouseController->createWarehouse();
            }
            // PUT /warehouses/{id} - ອັບເດດສາງ
            elseif ($method === 'PUT' && !empty($action) && is_numeric($action)) {
                $warehouseController->updateWarehouse($action);
            }
            // POST /warehouses/{id}/toggle-status - ປ່ຽນສະຖານະ (ໃຊ້ POST ແທນ PATCH)
            elseif ($method === 'POST' && !empty($action) && is_numeric($action) && $id === 'toggle-status') {
                $warehouseController->toggleWarehouseStatus($action);
            }
            // PATCH /warehouses/{id}/toggle-status - ເຜີກໄວ້ເພື່ອຄວາມເຂົ້າກັນໄດ້
            elseif ($method === 'PATCH' && !empty($action) && is_numeric($action) && $id === 'toggle-status') {
                $warehouseController->toggleWarehouseStatus($action);
            }
            // DELETE /warehouses/{id} - ລຶບສາງ (soft delete)
            elseif ($method === 'DELETE' && !empty($action) && is_numeric($action) && $id !== 'permanent') {
                $warehouseController->deleteWarehouse($action);
            }
            // DELETE /warehouses/{id}/permanent - ລຶບສາງແບບຖາວອນ
            elseif ($method === 'DELETE' && !empty($action) && is_numeric($action) && $id === 'permanent') {
                $warehouseController->permanentDeleteWarehouse($action);
            }
            else {
                Response::notFound('Warehouse endpoint not found');
            }
            break;

        // ເພີ່ມກ່ອນ case 'purchases'
        case 'purchase-order-details':
            require_once __DIR__ . '/controllers/PurchaseController.php';
            $purchaseController = new PurchaseController();
            
            if ($method === 'GET') {
                error_log("✓ Matched: GET /purchase-order-details");
                $purchaseController->getPurchaseOrderDetails();
            } else {
                Response::notFound('Purchase order details endpoint not found');
            }
            break;
 

        // ==================== PURCHASE ROUTES ====================
        case 'purchases':
            require_once __DIR__ . '/controllers/PurchaseController.php';
            $purchaseController = new PurchaseController();
            
            error_log("=== PURCHASES ROUTE DEBUG ===");
            error_log("Full URL: /$resource/$action/$id");
            error_log("Method: " . $method);
            error_log("Action: '" . $action . "'");
            error_log("ID: '" . $id . "'");
            error_log("is_numeric(action): " . (is_numeric($action) ? 'YES' : 'NO'));
            error_log("empty(action): " . (empty($action) ? 'YES' : 'NO'));
            
            // 1. PUT /purchases/{id} - ອັບເດດໃບສັ່ງຊື້ (ຕ້ອງມາກ່ອນ)
            if ($method === 'PUT' && !empty($action) && is_numeric($action)) {
                error_log("✓ MATCHED: PUT /purchases/{id} with ID: $action");
                $purchaseController->updatePurchaseOrder($action);
            }
            // 2. DELETE /purchases/{id} - ລຶບໃບສັ່ງຊື້
            elseif ($method === 'DELETE' && !empty($action) && is_numeric($action)) {
                error_log("✓ MATCHED: DELETE /purchases/{id} with ID: $action");
                $purchaseController->deletePurchaseOrder($action);
            }
            // 3. GET /purchases/{id} - ດຶງໃບສັ່ງຊື້ຕາມ ID
            elseif ($method === 'GET' && !empty($action) && is_numeric($action)) {
                error_log("✓ MATCHED: GET /purchases/{id} with ID: $action");
                $purchaseController->getPurchaseOrderById($action);
            }
            // 4. GET /purchases - ດຶງໃບສ�່ງຊື້ທັງໝົດ
            elseif ($method === 'GET' && $action === '' && $id === '') {
                error_log("✓ MATCHED: GET /purchases");
                $purchaseController->getAllPurchaseOrders();
            }
            // 5. GET /purchases/stats - ດຶງສະຖິຕິ
            elseif ($method === 'GET' && $action === 'stats') {
                error_log("✓ MATCHED: GET /purchases/stats");
                $purchaseController->getPurchaseStats();
            }
            // 6. GET /purchases/items - ດຶງລາຍການສິນຄ້າ
            elseif ($method === 'GET' && $action === 'items') {
                error_log("✓ MATCHED: GET /purchases/items");
                $purchaseController->getPurchaseItems();
            }
            // 7. GET /purchases/by-number/{poNumber} - ດຶງຕາມເລກທີ PO
            elseif ($method === 'GET' && $action === 'by-number' && !empty($id)) {
                error_log("✓ MATCHED: GET /purchases/by-number/{poNumber}");
                $purchaseController->getPurchaseOrderByNumber($id);
            }
            // 8. GET /purchases/pending - ດຶງໃບສັ່ງຊື້ທີ່ລໍຖ້າ
            elseif ($method === 'GET' && $action === 'pending') {
                error_log("✓ MATCHED: GET /purchases/pending");
                $purchaseController->getPendingOrders();
            }
            // 9. POST /purchases - ສ້າງໃບສັ່ງຊື້ໃໝ່
            elseif ($method === 'POST' && $action === '') {
                error_log("✓ MATCHED: POST /purchases");
                $purchaseController->createPurchaseOrder();
            }
            // ເພີ່ມກ່ອນ POST /purchases/{id}/receive
            elseif ($method === 'POST' && !empty($action) && is_numeric($action) && $id === 'approve') {
                error_log("✓ MATCHED: POST /purchases/{id}/approve");
                $purchaseController->approvePurchaseOrder($action);
            }
            // 10. POST /purchases/{id}/receive - ຮັບສິນຄ້າ
            elseif ($method === 'POST' && !empty($action) && is_numeric($action) && $id === 'receive') {
                error_log("✓ MATCHED: POST /purchases/{id}/receive");
                $purchaseController->receivePurchaseOrder($action);
            }
            // 11. POST /purchases/{id}/status - ອັບເດດສະຖານະ (ເພີ່ມເຂົ້າໄປ)
            elseif ($method === 'POST' && !empty($action) && is_numeric($action) && $id === 'status') {
                error_log("✓ MATCHED: POST /purchases/{id}/status");
                $purchaseController->updatePurchaseStatus($action);
            }
            // ເພີ່ມ POST /purchases/{id}/payment - ອັບເດດສະຖານະການຊຳລະ
            elseif ($method === 'POST' && !empty($action) && is_numeric($action) && $id === 'payment') {
                error_log("✓ MATCHED: POST /purchases/{id}/payment");
                $purchaseController->updatePaymentStatus($action);
            }
            // PATCH /purchases/{id}/payment - ເຜີກໄວ້ເພື່ອຄວາມເຂົ້າກັນໄດ້
            elseif ($method === 'PATCH' && !empty($action) && is_numeric($action) && $id === 'payment') {
                error_log("✓ MATCHED: PATCH /purchases/{id}/payment");
                $purchaseController->updatePaymentStatus($action);
            }
            else {
                error_log("✗ NO MATCH for: $method /$resource/$action/$id");
                Response::notFound('Purchase endpoint not found');
            }
            break;


        case 'test-update':
        require_once __DIR__ . '/controllers/PurchaseController.php';
        $purchaseController = new PurchaseController();
        
        if ($method === 'PUT' && !empty($action) && is_numeric($action)) {
            error_log("✓ TEST UPDATE: ID $action");
            header('Content-Type: application/json');
            echo json_encode(['status' => 'success', 'message' => 'Test update works!', 'id' => $action]);
            exit;
        }
        break;


    // ==================== SALES ROUTES ====================
    case 'sales':
        require_once __DIR__ . '/controllers/SalesController.php';
        $salesController = new SalesController();
        
        error_log("=== SALES ROUTE DEBUG ===");
        error_log("Full URL: /$resource/$action/$id");
        error_log("Method: " . $method);
        error_log("Action: '" . $action . "'");
        error_log("ID: '" . $id . "'");
        
        // GET /sales - ດຶງໃບຂາຍທັງໝົດ
        if ($method === 'GET' && $action === '' && $id === '') {
            error_log("✓ MATCHED: GET /sales");
            $salesController->getAllSalesOrders();
        }
        // GET /sales/stats - ດຶງສະຖິຕິ
        elseif ($method === 'GET' && $action === 'stats') {
            error_log("✓ MATCHED: GET /sales/stats");
            $salesController->getSalesStats();
        }
        // GET /sales/items - ດຶງລາຍການສິນຄ້າ
        elseif ($method === 'GET' && $action === 'items') {
            error_log("✓ MATCHED: GET /sales/items");
            $salesController->getSalesItems();
        }
        // GET /sales/customers - ດຶງຂໍ້ມູນລູກຄ້າ
        elseif ($method === 'GET' && $action === 'customers') {
            error_log("✓ MATCHED: GET /sales/customers");
            $salesController->getCustomers();
        }
        // GET /sales/{id} - ດຶງໃບຂາຍຕາມ ID
        elseif ($method === 'GET' && !empty($action) && is_numeric($action)) {
            error_log("✓ MATCHED: GET /sales/{id} with ID: $action");
            $salesController->getSalesOrderById($action);
        }
        // POST /sales - ສ້າງໃບຂາຍໃໝ່
        elseif ($method === 'POST' && $action === '') {
            error_log("✓ MATCHED: POST /sales");
            $salesController->createSalesOrder();
        }
        // POST /sales/{id}/status - ອັບເດດສະຖານະໃບຂາຍ
        elseif ($method === 'POST' && !empty($action) && is_numeric($action) && $id === 'status') {
            error_log("✓ MATCHED: POST /sales/{id}/status");
            $salesController->updateStatus($action);
        }
        // POST /sales/{id}/payment - ອັບເດດສະຖານະການຊຳລະ
        elseif ($method === 'POST' && !empty($action) && is_numeric($action) && $id === 'payment') {
            error_log("✓ MATCHED: POST /sales/{id}/payment");
            $salesController->updatePaymentStatus($action);
        }
        // POST /sales/{id}/sync-asset - ອັບເດດສະຖານະການ sync asset (ເພີ່ມໃໝ່)
        elseif ($method === 'POST' && !empty($action) && is_numeric($action) && $id === 'sync-asset') {
            error_log("✓ MATCHED: POST /sales/{id}/sync-asset");
            $salesController->updateSyncAssetStatus($action);
        }
        // PATCH /sales/{id}/payment - ຮອງຮັບເຜີກໄວ້
        elseif ($method === 'PATCH' && !empty($action) && is_numeric($action) && $id === 'payment') {
            error_log("✓ MATCHED: PATCH /sales/{id}/payment");
            $salesController->updatePaymentStatus($action);
        }
        // PATCH /sales/{id}/status - ຮອງຮັບເຜີກໄວ້
        elseif ($method === 'PATCH' && !empty($action) && is_numeric($action) && $id === 'status') {
            error_log("✓ MATCHED: PATCH /sales/{id}/status");
            $salesController->updateStatus($action);
        }
        // PATCH /sales/{id}/sync-asset - ຮອງຮັບເຜີກໄວ້ (ເພີ່ມໃໝ່)
        elseif ($method === 'POST' && !empty($action) && is_numeric($action) && $id === 'sync-asset') {
            error_log("✓ MATCHED: POST /sales/{id}/sync-asset");
            $salesController->updateSyncAssetStatus($action);
        }
        else {
            error_log("✗ NO MATCH for: $method /$resource/$action/$id");
            Response::notFound('Sales endpoint not found');
        }
        break;


        // ==================== ASSET SYNC ROUTES ====================
    case 'asset':
        // ຮັບຂໍ້ມູນການ sync ຈາກ sales
        if ($method === 'POST' && $action === 'sync-from-sales') {
            require_once __DIR__ . '/controllers/AssetSyncController.php';
            $assetSyncController = new AssetSyncController();
            $assetSyncController->syncFromSales();
        }
        else {
            Response::notFound('Asset sync endpoint not found');
        }
        break;
 

        // ==================== SUPPLIER ROUTES ====================
        case 'suppliers':
            require_once __DIR__ . '/controllers/SupplierController.php';
            $supplierController = new SupplierController();
            
            // GET /suppliers/dropdown - ດຶງຂໍ້ມູນສຳລັບ dropdown
            if ($method === 'GET' && $action === 'dropdown') {
                $supplierController->getSuppliersDropdown();
            }
            // GET /suppliers - ດຶງຂໍ້ມູນຜູ້ສະໜອງທັງໝົດ
            elseif ($method === 'GET' && $action === '') {
                $supplierController->getAllSuppliers();
            }
            // GET /suppliers/stats - ດຶງສະຖິຕິ
            elseif ($method === 'GET' && $action === 'stats') {
                $supplierController->getSupplierStats();
            }
            // GET /suppliers/search - ຄົ້ນຫາ
            elseif ($method === 'GET' && $action === 'search') {
                $supplierController->searchSuppliers();
            }
            // GET /suppliers/{id} - ດຶງຕາມ ID
            elseif ($method === 'GET' && !empty($action) && is_numeric($action)) {
                $supplierController->getSupplierById($action);
            }
            // POST /suppliers - ສ້າງໃໝ່
            elseif ($method === 'POST' && $action === '') {
                $supplierController->createSupplier();
            }
            // PUT /suppliers/{id} - ອັບເດດ
            elseif ($method === 'PUT' && !empty($action) && is_numeric($action)) {
                $supplierController->updateSupplier($action);
            }
            // DELETE /suppliers/{id} - ລຶບ
            elseif ($method === 'DELETE' && !empty($action) && is_numeric($action)) {
                $supplierController->deleteSupplier($action);
            }
            // PATCH /suppliers/{id}/status - ປ່ຽນສະຖານະ
            elseif ($method === 'PATCH' && !empty($action) && is_numeric($action) && $id === 'status') {
                $supplierController->toggleSupplierStatus($action);
            }
            else {
                Response::notFound('Supplier endpoint not found');
            }
            break;

 

        // ==================== BARCODE ROUTES ====================
        case 'barcode':
 
            $assetController = new AssetController();
      
            // POST /barcode/scan - ບັນທຶກການສະແກນ
            if ($method === 'POST' && $action === 'scan') {
                $assetController->recordScan();
            }
            // GET /barcode/scans - ດຶງປະຫວັດການສະແກນ
            elseif ($method === 'GET' && $action === 'scans') {
                $assetController->getScanHistory();
            }
            else {
                error_log("✗ NO MATCH for: $method /$resource/$action/$id");
                Response::notFound('Barcode endpoint not found');
            }
            break;

            // ==================== BARCODES ROUTES ====================
        case 'barcodes':
            require_once __DIR__ . '/controllers/BarcodeController.php';
            $barcodeController = new BarcodeController();
            
            // GET /barcodes/items - ດຶງຂໍ້ມູນສິນຄ້າສຳລັບ dropdown (ຕ້ອງມາກ່ອນ)
            if ($method === 'GET' && $action === 'items') {
                error_log("✓ MATCHED: GET /barcodes/items");
                $barcodeController->getItemsForBarcode();
            }
            // GET /barcodes - ດຶງຂໍ້ມູນທັງໝົດ
            elseif ($method === 'GET' && $action === '' && $id === '') {
                error_log("✓ MATCHED: GET /barcodes");
                $barcodeController->getAllBarcodes();
            }
            // POST /barcodes - ສ້າງ barcode ໃໝ່
            elseif ($method === 'POST' && $action === '') {
                error_log("✓ MATCHED: POST /barcodes");
                $barcodeController->createBarcode();
            }
                // POST /barcodes/{id}/print - ອັບເດດສະຖານະການພິມ (ເພີ່ມເຂົ້າໄປ)
            elseif ($method === 'POST' && !empty($action) && is_numeric($action) && $id === 'print') {
                error_log("✓ MATCHED: POST /barcodes/{id}/print");
                $barcodeController->updatePrintStatus($action);
            }
            // POST /barcodes/generate-from-item/{id} - ສ້າງ barcode ຈາກສິນຄ້າ
            elseif ($method === 'POST' && $action === 'generate-from-item' && !empty($id)) {
                error_log("✓ MATCHED: POST /barcodes/generate-from-item/{id}");
                $barcodeController->generateFromItem($id);
            }
            // PATCH /barcodes/{id}/print - ອັບເດດສະຖານະການພິມ
            elseif ($method === 'PATCH' && !empty($action) && is_numeric($action) && $id === 'print') {
                error_log("✓ MATCHED: PATCH /barcodes/{id}/print");
                $barcodeController->updatePrintStatus($action);
            }
            else {
                error_log("✗ NO MATCH for: $method /$resource/$action/$id");
                Response::notFound('Barcode endpoint not found');
            }
            break;

            

        // ==================== DASHBOARD ROUTES ====================
        case 'dashboard':
            $dashboardController = new DashboardController();
            
            // GET /dashboard/summary - ດຶງຂໍ້ມູນສະຫຼຸບສຳລັບໜ້າຫຼັກ
            if ($method === 'GET' && $action === 'summary') {
                $dashboardController->getDashboardSummary();
            }
            // GET /dashboard/recent-activities - ດຶງກິດຈະກຳລ່າສຸດ
            elseif ($method === 'GET' && $action === 'recent-activities') {
                $dashboardController->getRecentActivities();
            }
            // GET /dashboard/charts - ດຶງຂໍ້ມູນສຳລັບກຣາຟ
            elseif ($method === 'GET' && $action === 'charts') {
                $dashboardController->getChartData();
            }
            else {
                Response::notFound('Dashboard endpoint not found');
            }
            break;


        // ==================== CUSTOMER ROUTES ====================
        case 'customers':
            require_once __DIR__ . '/controllers/CustomerController.php';
            $customerController = new CustomerController();
            
            // GET /customers - ດຶງຂໍ້ມູນລູກຄ້າທັງໝົດ
            if ($method === 'GET' && $action === '' && $id === '') {
                $customerController->getAllCustomers();
            }
            // GET /customers/dropdown - ດຶງຂໍ້ມູນສຳລັບ dropdown
            elseif ($method === 'GET' && $action === 'dropdown') {
                $customerController->getCustomersDropdown();
            }
            // GET /customers/{id} - ດຶງຕາມ ID
            elseif ($method === 'GET' && !empty($action) && is_numeric($action)) {
                $customerController->getCustomerById($action);
            }
            // POST /customers - ສ້າງໃໝ່
            elseif ($method === 'POST' && $action === '') {
                $customerController->createCustomer();
            }
            // PUT /customers/{id} - ອັບເດດ
            elseif ($method === 'PUT' && !empty($action) && is_numeric($action)) {
                $customerController->updateCustomer($action);
            }
            // GET /customers/filters - ດຶງຂໍ້ມູນສຳລັບ filter dropdowns
            elseif ($method === 'GET' && $action === 'filters') {
                $customerController->getFilterData();
            }
            else {
                Response::notFound('Customer endpoint not found');
            }
            break;

        // ==================== SALES ROUTES ====================
        case 'sales':
            require_once __DIR__ . '/controllers/SalesController.php';
            $salesController = new SalesController();
            
            error_log("=== SALES ROUTE DEBUG ===");
            error_log("Full URL: /$resource/$action/$id");
            error_log("Method: " . $method);
            error_log("Action: '" . $action . "'");
            error_log("ID: '" . $id . "'");
            
            // GET /sales - ດຶງໃບຂາຍທັງໝົດ
            if ($method === 'GET' && $action === '' && $id === '') {
                error_log("✓ MATCHED: GET /sales");
                $salesController->getAllSalesOrders();
            }
            // GET /sales/stats - ດຶງສະຖິຕິ
            elseif ($method === 'GET' && $action === 'stats') {
                error_log("✓ MATCHED: GET /sales/stats");
                $salesController->getSalesStats();
            }
            // GET /sales/items - ດຶງລາຍການສິນຄ້າ
            elseif ($method === 'GET' && $action === 'items') {
                error_log("✓ MATCHED: GET /sales/items");
                $salesController->getSalesItems();
            }
            // GET /sales/customers - ດຶງຂໍ້ມູນລູກຄ້າ
            elseif ($method === 'GET' && $action === 'customers') {
                error_log("✓ MATCHED: GET /sales/customers");
                $salesController->getCustomers();
            }
            // GET /sales/{id} - ດຶງໃບຂາຍຕາມ ID
            elseif ($method === 'GET' && !empty($action) && is_numeric($action)) {
                error_log("✓ MATCHED: GET /sales/{id} with ID: $action");
                $salesController->getSalesOrderById($action);
            }
            // POST /sales - ສ້າງໃບຂາຍໃໝ່
            elseif ($method === 'POST' && $action === '') {
                error_log("✓ MATCHED: POST /sales");
                $salesController->createSalesOrder();
            }
            // PATCH /sales/{id}/payment - ອັບເດດສະຖານະການຊຳລະ
            elseif ($method === 'PATCH' && !empty($action) && is_numeric($action) && $id === 'payment') {
                error_log("✓ MATCHED: PATCH /sales/{id}/payment");
                $salesController->updatePaymentStatus($action);
            }
            // PATCH /sales/{id}/status - ອັບເດດສະຖານະໃບຂາຍ
            elseif ($method === 'POST' && !empty($action) && is_numeric($action) && $id === 'status') {
                error_log("✓ MATCHED: POST /sales/{id}/status");
                $salesController->updateStatus($action);
            }
            else {
                error_log("✗ NO MATCH for: $method /$resource/$action/$id");
                Response::notFound('Sales endpoint not found');
            }
            break;


        // ==================== STOCK COUNT ROUTES ====================
        case 'stock-counts':
            require_once __DIR__ . '/controllers/StockCountController.php';
            $stockCountController = new StockCountController();
            
            // ແຍກ URL ອອກເປັນສ່ວນ
            // ຕົວຢ່າງ: /stock-counts/6/items/6/count
            // $action = 6 (session id)
            // $id = items
            // $subAction = 6 (item id)
            // $subSubAction = count
            $action = $segments[1] ?? '';
            $id = $segments[2] ?? '';
            $subAction = $segments[3] ?? '';
            $subSubAction = $segments[4] ?? '';
            
            error_log("=== STOCK COUNT ROUTE DEBUG ===");
            error_log("Method: $method");
            error_log("Action: '$action'");
            error_log("ID: '$id'");
            error_log("SubAction: '$subAction'");
            error_log("SubSubAction: '$subSubAction'");
            
            // GET /stock-counts - ດຶງຂໍ້ມູນການນັບທັງໝົດ
            if ($method === 'GET' && $action === '' && $id === '') {
                error_log("✓ MATCHED: GET /stock-counts");
                $stockCountController->getStockCounts();
            }
            // GET /stock-counts/stats - ດຶງສະຖິຕິ
            elseif ($method === 'GET' && $action === 'stats' && $id === '') {
                error_log("✓ MATCHED: GET /stock-counts/stats");
                $stockCountController->getStockCountStats();
            }
            // POST /stock-counts - ສ້າງການນັບໃໝ່
            elseif ($method === 'POST' && $action === '' && $id === '') {
                error_log("✓ MATCHED: POST /stock-counts");
                $stockCountController->createStockCount();
            }
            // GET /stock-counts/{id}/details - ດຶງລາຍລະອຽດ
            elseif ($method === 'GET' && !empty($action) && is_numeric($action) && $id === 'details') {
                error_log("✓ MATCHED: GET /stock-counts/{$action}/details");
                $stockCountController->getStockCountDetails($action);
            }
            // POST /stock-counts/{id}/start - ເລີ່ມການນັບ
            elseif ($method === 'POST' && !empty($action) && is_numeric($action) && $id === 'start') {
                error_log("✓ MATCHED: POST /stock-counts/{$action}/start");
                $stockCountController->startStockCount($action);
            }
            // POST /stock-counts/{id}/complete - ສຳເລັດການນັບ
            elseif ($method === 'POST' && !empty($action) && is_numeric($action) && $id === 'complete') {
                error_log("✓ MATCHED: POST /stock-counts/{$action}/complete");
                $stockCountController->completeStockCount($action);
            }
            // POST /stock-counts/{id}/items - ເພີ່ມສິນຄ້າເຂົ້າການນັບ
            elseif ($method === 'POST' && !empty($action) && is_numeric($action) && $id === 'items' && $subAction === '') {
                error_log("✓ MATCHED: POST /stock-counts/{$action}/items");
                $stockCountController->addItemsToCount($action);
            }
            // POST /stock-counts/{id}/items/{itemId}/count - ບັນທຶກຜົນການນັບ
            // URL: /stock-counts/6/items/6/count
            // $action = 6 (session id), $id = 'items', $subAction = 6 (item id), $subSubAction = 'count'
            elseif ($method === 'POST' && !empty($action) && is_numeric($action) && $id === 'items' && !empty($subAction) && is_numeric($subAction) && $subSubAction === 'count') {
                error_log("✓ MATCHED: POST /stock-counts/{$action}/items/{$subAction}/count");
                $stockCountController->recordCount($action, $subAction);
            }
            // POST /stock-counts/{id}/cancel - ຍົກເລີກການນັບ
            elseif ($method === 'POST' && !empty($action) && is_numeric($action) && $id === 'cancel') {
                error_log("✓ MATCHED: POST /stock-counts/{$action}/cancel");
                $stockCountController->cancelStockCount($action);
            }
            else {
                error_log("✗ NO MATCH for: $method /stock-counts/$action/$id/$subAction/$subSubAction");
                Response::notFound("Stock count endpoint not found");
            }
            break;

        // ເພີ່ມໃນ case 'stock-adjustments':
        case 'stock-adjustments':
            require_once __DIR__ . '/controllers/StockAdjustmentController.php';
            $stockAdjustmentController = new StockAdjustmentController();
            
            // GET /stock-adjustments - ດຶງຂໍ້ມູນການປັບທັງໝົດ
            if ($method === 'GET' && $action === '' && $id === '') {
                $stockAdjustmentController->getAdjustments();
            }
            // GET /stock-adjustments/stats - ດຶງສະຖິຕິ
            elseif ($method === 'GET' && $action === 'stats') {
                $stockAdjustmentController->getAdjustmentStats();
            }
            // POST /stock-adjustments - ສ້າງການປັບໃໝ່
            elseif ($method === 'POST' && $action === '') {
                $stockAdjustmentController->createAdjustment();
            }
            // POST /stock-adjustments/{id}/approve - ອະນຸມັດການປັບ
            elseif ($method === 'POST' && !empty($action) && is_numeric($action) && $id === 'approve') {
                $stockAdjustmentController->approveAdjustment($action);
            }
            else {
                Response::notFound('Stock adjustment endpoint not found');
            }
            break;



        // ==================== DEFAULT - API INFO ====================
        default:
            // API Info
            $response = [
                'name' => 'Asset Management API',
                'version' => '2.0.0',
                'endpoints' => [
                    // Auth
                    'POST /auth/register' => 'Register new user',
                    'POST /auth/login' => 'Login user',
                    'POST /auth/refresh' => 'Refresh access token',
                    'POST /auth/logout' => 'Logout user',
                    
                    // User Profile
                    'GET /user/profile' => 'Get current user profile',
                    'PUT /user/profile' => 'Update current user profile',
                    'POST /user/change-password' => 'Change password',
                    'POST /user/profile-image' => 'Upload profile image',
                    
                    // Users Management
                    'GET /users' => 'Get all users (paginated, filterable)',
                    'GET /users/dropdown' => 'Get users for dropdown',
                    'GET /users/export' => 'Export users data',
                    'GET /users/stats' => 'Get user statistics',
                    'GET /users/search' => 'Advanced user search',
                    'GET /users/managers' => 'Get all managers',
                    'GET /users/by-department/{id}' => 'Get users by department',
                    'GET /users/activities/{id}' => 'Get user activities',
                    'GET /users/{id}' => 'Get user by ID',
                    'POST /users' => 'Create new user',
                    'PUT /users/{id}' => 'Update user',
                    'DELETE /users/{id}' => 'Delete user',
                    'PATCH /users/{id}/status' => 'Update user status',
                    'PATCH /users/{id}/role' => 'Update user role',
                    
                    // Companies
                    'GET /companies' => 'Get all companies',
                    'GET /companies/dropdown' => 'Get companies for dropdown',
                    'GET /companies/parents' => 'Get parent companies',
                    'GET /companies/stats' => 'Get company statistics',
                    'GET /companies/{id}' => 'Get company by ID',
                    'POST /companies' => 'Create new company',
                    'PUT /companies/{id}' => 'Update company',
                    'PATCH /companies/{id}/status' => 'Update company status',
                    'DELETE /companies/{id}' => 'Delete company (soft)',
                    'DELETE /companies/{id}/permanent' => 'Delete company permanently',
                    
                    // Departments
                    'GET /departments' => 'Get all departments',
                    'GET /departments/dropdown' => 'Get departments for dropdown',
                    'GET /departments/parents' => 'Get parent departments',
                    'GET /departments/available-managers' => 'Get available managers',
                    'GET /departments/stats' => 'Get department statistics',
                    'GET /departments/company/{companyId}' => 'Get departments by company',
                    'GET /departments/check-manager/{userId}/{deptId}' => 'Check manager eligibility',
                    'GET /departments/{id}' => 'Get department by ID',
                    'POST /departments' => 'Create new department',
                    'PUT /departments/{id}' => 'Update department',
                    'PATCH /departments/{id}/status' => 'Update department status',
                    'PATCH /departments/{id}/manager' => 'Update department manager',
                    'DELETE /departments/{id}' => 'Delete department',
                    
                    // Asset Categories
                    'GET /asset-categories' => 'Get all categories',
                    'GET /asset-categories/tree' => 'Get category tree',
                    'GET /asset-categories/dropdown' => 'Get categories for dropdown',
                    'GET /asset-categories/stats' => 'Get category statistics',
                    'GET /asset-categories/by-level/{level}' => 'Get categories by level',
                    'GET /asset-categories/{id}' => 'Get category by ID',
                    'POST /asset-categories' => 'Create new category',
                    'PUT /asset-categories/{id}' => 'Update category',
                    'PATCH /asset-categories/{id}/status' => 'Update category status',
                    'DELETE /asset-categories/{id}' => 'Delete category',
                    
                    // Assets
                    'GET /assets' => 'Get all assets (paginated, filterable)',
                    'GET /assets/stats' => 'Get asset statistics',
                    'GET /assets/search' => 'Search assets',
                    'GET /assets/by-user/{userId}' => 'Get assets by user',
                    'GET /assets/by-department/{deptId}' => 'Get assets by department',
                    'GET /assets/by-barcode/{barcode}' => 'Get asset by barcode',
                    'GET /assets/by-rfid/{rfid}' => 'Get asset by RFID',
                    'GET /assets/by-serial/{serial}' => 'Get asset by serial number',
                    'GET /assets/{id}' => 'Get asset by ID',
                    'GET /assets/{assetId}/documents' => 'Get asset documents',
                    'GET /assets/{assetId}/images' => 'Get asset images',
                    'POST /assets' => 'Create new asset',
                    'POST /assets/{assetId}/documents' => 'Upload document',
                    'POST /assets/{assetId}/images' => 'Upload image',
                    'POST /assets/images/reorder' => 'Reorder images',
                    'POST /assets/{assetId}/barcode' => 'Generate barcode',
                    'POST /assets/{id}/verify' => 'Verify asset',
                    'PUT /assets/{id}' => 'Update asset',
                    'PATCH /assets/{id}/status' => 'Update asset status',
                    'PATCH /assets/{id}/condition' => 'Update asset condition',
                    'PATCH /assets/{id}/user' => 'Update asset user',
                    'PATCH /assets/{id}/location' => 'Update asset location',
                    'PATCH /assets/{id}/warranty' => 'Update asset warranty',
                    'DELETE /assets/{id}' => 'Delete asset',
                    'DELETE /assets/documents/{docId}' => 'Delete document',
                    'DELETE /assets/images/{imageId}' => 'Delete image',
                    
                    // Inventory
                    'GET /inventory' => 'Get all inventory items',
                    'GET /inventory/low-stock' => 'Get low stock items',
                    'GET /inventory/out-of-stock' => 'Get out of stock items',
                    'GET /inventory/stats' => 'Get inventory statistics',
                    'GET /inventory/movements' => 'Get inventory movements',
                    'GET /inventory/by-product/{productId}' => 'Get inventory by product',
                    'GET /inventory/by-location/{locationId}' => 'Get inventory by location',
                    'GET /inventory/{id}' => 'Get inventory by ID',
                    'POST /inventory/adjust' => 'Adjust inventory',
                    'POST /inventory/transfer' => 'Transfer inventory',
                    'POST /inventory/count' => 'Record stock count',
                    
                    // Products
                    'GET /products' => 'Get all products',
                    'GET /products/dropdown' => 'Get products for dropdown',
                    'GET /products/categories' => 'Get product categories',
                    'GET /products/search' => 'Search products',
                    'GET /products/by-category/{categoryId}' => 'Get products by category',
                    'GET /products/{id}' => 'Get product by ID',
                    'POST /products' => 'Create new product',
                    'PUT /products/{id}' => 'Update product',
                    'DELETE /products/{id}' => 'Delete product',
                    'POST /products/{id}/image' => 'Upload product image',
                    
                    // Purchase Orders
                    'GET /purchases' => 'Get all purchase orders',
                    'GET /purchases/stats' => 'Get purchase statistics',
                    'GET /purchases/pending' => 'Get pending orders',
                    'GET /purchases/by-supplier/{supplierId}' => 'Get orders by supplier',
                    'GET /purchases/{id}' => 'Get purchase order by ID',
                    'POST /purchases' => 'Create purchase order',
                    'PUT /purchases/{id}' => 'Update purchase order',
                    'PATCH /purchases/{id}/status' => 'Update order status',
                    'POST /purchases/{id}/receive' => 'Receive items',
                    'DELETE /purchases/{id}' => 'Delete purchase order',
                    
                    // Sales Orders
                    'GET /sales' => 'Get all sales orders',
                    'GET /sales/stats' => 'Get sales statistics',
                    'GET /sales/today' => 'Get today\'s sales',
                    'GET /sales/by-customer/{customerId}' => 'Get sales by customer',
                    'GET /sales/{id}' => 'Get sales order by ID',
                    'POST /sales' => 'Create sales order',
                    'PUT /sales/{id}' => 'Update sales order',
                    'PATCH /sales/{id}/status' => 'Update order status',
                    'DELETE /sales/{id}' => 'Delete sales order',
                    
                    // Suppliers
                    'GET /suppliers' => 'Get all suppliers',
                    'GET /suppliers/dropdown' => 'Get suppliers for dropdown',
                    'GET /suppliers/stats' => 'Get supplier statistics',
                    'GET /suppliers/search' => 'Search suppliers',
                    'GET /suppliers/by-code/{code}' => 'Get supplier by code',
                    'GET /suppliers/{id}' => 'Get supplier by ID',
                    'POST /suppliers' => 'Create new supplier',
                    'PUT /suppliers/{id}' => 'Update supplier',
                    'PATCH /suppliers/{id}/status' => 'Update supplier status',
                    'DELETE /suppliers/{id}' => 'Delete supplier',
                    
                    // Barcode
                    'POST /barcode/scan' => 'Record barcode scan',
                    'GET /barcode/scans' => 'Get scan history',
                    
                    // Dashboard
                    'GET /dashboard/summary' => 'Get dashboard summary',
                    'GET /dashboard/recent-activities' => 'Get recent activities',
                    'GET /dashboard/charts' => 'Get chart data',
                ],
                'documentation' => '/api/docs',
                'timestamp' => date('Y-m-d H:i:s')
            ];
            
            // ລ້າງ buffer ກ່ອນສົ່ງ response
            ob_end_clean();
            Response::success($response, 200, 'API is running');
    }
} catch (Exception $e) {
    ob_end_clean();
    Response::error('Internal Server Error: ' . $e->getMessage(), 500);
}

// ສົ່ງ output buffer ຖ້າຍັງມີ
ob_end_flush();
?>