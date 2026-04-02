<?php
// src/index.php - ແກ້ໄຂບັນຫາ headers

// ຕ້ອງແນ່ໃຈວ່າບໍ່ມີ output ກ່ອນ headers
ob_start(); // ເລີ່ມ output buffering

// ຕັ້ງ CORS headers ກ່ອນອື່ນໝົດ
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, PATCH, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Max-Age: 3600');

// ຈັດການ CORS ສຳລັບ preflight requests
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
require_once __DIR__ . '/models/User.php';
require_once __DIR__ . '/models/Department.php';
require_once __DIR__ . '/models/Company.php';
require_once __DIR__ . '/models/AssetCategory.php';
require_once __DIR__ . '/models/Asset.php';
require_once __DIR__ . '/models/Supplier.php';



$request = $_SERVER['REQUEST_URI'];
$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($request, PHP_URL_PATH);

// ແກ້ໄຂການອ່ານ path ໃຫ້ຖືກຕ້ອງ
$script_name = $_SERVER['SCRIPT_NAME'];
$path = str_replace($script_name, '', $path);
$path = ltrim($path, '/');
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
            }    // GET /departments/available-managers - ດຶງລາຍຊື່ຜູ້ທີ່ສາມາດເປັນຫົວໜ້າພະແນກໄດ້
            elseif ($method === 'GET' && $action === 'available-managers') {
                $departmentController->getAvailableManagers();
            }
            // GET /departments/check-manager/{userId}/{departmentId} - ກວດສອບຄວາມເໝາະສົມຂອງຜູ້ຈັດການ
            elseif ($method === 'GET' && $action === 'check-manager' && !empty($id) && !empty($segments[3])) {
                $departmentController->checkManagerEligibility($id, $segments[3]);
            }
            // GET /users/managers
            if ($method === 'GET' && $action === 'managers') {
                $userController->getManagers();
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
        // GET /departments/managers
        elseif ($method === 'GET' && $action === 'managers') {
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

        // ==================== ASSET_CATEGORIES MANAGEMENT ROUTES ====================

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
        }elseif ($method === 'GET' && $action === 'by-level-parent') {
            $categoryController->getCategoriesByTargetLevel();
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
        // GET /assets/{id}
        elseif ($method === 'GET' && !empty($action) && is_numeric($action)) {
            $assetController->getAssetById($action);
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

         // ==================== SUPPLIER ROUTES ====================
    case 'suppliers':

        $supplierController = new SupplierController();
        
        // GET /suppliers - ດຶງຂໍ້ມູນຜູ້ສະໜອງທັງໝົດ
        if ($method === 'GET' && !$id) {
            $supplierController->getAllSuppliers();
        }
        // GET /suppliers/dropdown - ດຶງຂໍ້ມູນສຳລັບ dropdown
        elseif ($method === 'GET' && $id === 'dropdown') {
            $supplierController->getSuppliersDropdown();
        }
        // GET /suppliers/stats - ດຶງສະຖິຕິ
        elseif ($method === 'GET' && $id === 'stats') {
            $supplierController->getSupplierStats();
        }
        // GET /suppliers/search - ຄົ້ນຫາຜູ້ສະໜອງ
        elseif ($method === 'GET' && $id === 'search') {
            $supplierController->searchSuppliers();
        }
        // GET /suppliers/by-code/{code} - ດຶງຕາມລະຫັດ
        elseif ($method === 'GET' && $id === 'by-code' && $action) {
            $supplierController->getSupplierByCode($action);
        }
        // GET /suppliers/{id} - ດຶງຕາມ ID
        elseif ($method === 'GET' && $id && is_numeric($id)) {
            $supplierController->getSupplierById($id);
        }
        // POST /suppliers - ສ້າງໃໝ່
        elseif ($method === 'POST' && !$id) {
            $supplierController->createSupplier();
        }
        // PUT /suppliers/{id} - ອັບເດດ
        elseif ($method === 'PUT' && $id && is_numeric($id)) {
            $supplierController->updateSupplier($id);
        }
        // PATCH /suppliers/{id}/status - ອັບເດດສະຖານະ
        elseif ($method === 'PATCH' && $id && is_numeric($id) && $action === 'status') {
            $supplierController->updateSupplierStatus($id);
        }
        // DELETE /suppliers/{id} - ລຶບ
        elseif ($method === 'DELETE' && $id && is_numeric($id)) {
            $supplierController->deleteSupplier($id);
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
            Response::notFound('Barcode endpoint not found');
        }
        break;



        // ==================== DEFAULT - API INFO ====================
        default:
            // API Info
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
                    'GET /users' => 'Get all users (paginated, filterable)',
                    'GET /users/dropdown' => 'Get users for dropdown',
                    'GET /users/export' => 'Export users data',
                    'GET /users/stats' => 'Get user statistics',
                    'GET /users/search' => 'Advanced user search',
                    'GET /users/by-department/{id}' => 'Get users by department',
                    'GET /users/activities/{id}' => 'Get user activities',
                    'GET /users/{id}' => 'Get user by ID',
                    'POST /users' => 'Create new user',
                    'PUT /users/{id}' => 'Update user',
                    'DELETE /users/{id}' => 'Delete user',
                    'PATCH /users/{id}/status' => 'Update user status',
                    'PATCH /users/{id}/role' => 'Update user role'
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