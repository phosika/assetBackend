<?php
// src/index.php - API Router
if (isset($_GET['dump_path'])) {
    header('Content-Type: text/plain');
    echo "FILE: " . __FILE__ . "\n";
    echo "DIR: " . __DIR__ . "\n";
    exit;
}

// ຕ້ອງແນ່ໃຈວ່າບໍ່ມີ output ກ່ອນ headers
ob_start();

// ເລີ່ມ session ຖ້າຍັງບໍ່ໄດ້ເລີ່ມ
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ============================================
// ຕັ້ງຄ່າ Error Reporting
// ============================================
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');

// Register global error and exception handlers for Hostinger diagnostics
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        $msg = "[" . date('Y-m-d H:i:s') . "] FATAL ERROR: " . $error['message'] . " in " . $error['file'] . " on line " . $error['line'] . "\n\n";
        @file_put_contents(__DIR__ . '/../php_error_log.txt', $msg, FILE_APPEND);
    }
});

set_exception_handler(function($e) {
    $msg = "[" . date('Y-m-d H:i:s') . "] UNCAUGHT EXCEPTION: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine() . "\n" . $e->getTraceAsString() . "\n\n";
    @file_put_contents(__DIR__ . '/../php_error_log.txt', $msg, FILE_APPEND);
    
    if (ob_get_length()) {
        ob_end_clean();
    }
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'status' => 'error',
        'message' => 'Internal Server Error',
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
});

// ============================================
// ຕັ້ງ CORS headers
// ============================================
$allowedOrigins = [
    'http://localhost:5173',
    'http://localhost:3000',
    'http://localhost:8080',
    'https://suvinhome.d-ict.edu.la',
    'http://suvinhome.d-ict.edu.la'
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowedOrigins)) {
    header('Access-Control-Allow-Origin: ' . $origin);
} else {
    header('Access-Control-Allow-Origin: https://suvinhome.d-ict.edu.la');
}
header('Access-Control-Allow-Credentials: true');

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Accept, Origin');
header('Access-Control-Max-Age: 3600');

// ຈັດການ preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// ກໍານົດ base path
$basePath = __DIR__;

// ============================================
// ໂຫຼດຟັງຊັນຕ່າງໆ
// ============================================
require_once $basePath . '/utils/Response.php';
require_once $basePath . '/utils/Validator.php';
require_once $basePath . '/utils/FileUploader.php';
require_once $basePath . '/utils/JWT.php';
require_once $basePath . '/middleware/AuthMiddleware.php';
require_once $basePath . '/controllers/AuthController.php';
require_once $basePath . '/controllers/UserController.php';
require_once $basePath . '/models/User.php';
require_once $basePath . '/models/PasswordReset.php';
require_once $basePath . '/models/Category.php';
require_once $basePath . '/models/SubCategory.php';
require_once $basePath . '/models/Product.php';
require_once $basePath . '/models/InventoryStock.php';
require_once $basePath . '/models/Purchase.php';
require_once $basePath . '/models/Sale.php';
require_once $basePath . '/models/Supplier.php';
require_once $basePath . '/models/Customer.php';
require_once $basePath . '/controllers/CustomerController.php';
require_once $basePath . '/controllers/CategoryController.php';
require_once $basePath . '/controllers/SupplierController.php';
require_once $basePath . '/controllers/SubCategoryController.php';
require_once $basePath . '/controllers/ProductController.php';
require_once $basePath . '/controllers/InventoryStockController.php';
require_once $basePath . '/controllers/PurchaseController.php';
require_once $basePath . '/controllers/SaleController.php';

// ============================================
// ກຳນົດຄ່າ Database Connection
// ============================================
try {
    require_once $basePath . '/config/database.php';
    
    // ກວດສອບວ່າມີຟັງຊັນ getDBConnection ຫຼືບໍ່
    if (function_exists('getDBConnection')) {
        $db = getDBConnection();
    } else {
        // ຖ້າບໍ່ມີ, ໃຊ້ Database::getConnection()
        $db = Database::getConnection();
    }
    
    // ທົດສອບການເຊື່ອມຕໍ່
    $db->query("SELECT 1");
    
} catch (Throwable $e) {
    error_log("Database connection failed: " . $e->getMessage());
    ob_end_clean();
    Response::json([
        'status' => 'error',
        'message' => 'Database connection failed',
        'error' => $e->getMessage()
    ], 500);
    exit();
}

// ============================================
// Initialize JWT
// ============================================
if (class_exists('JWT')) {
    try {
        JWT::init();
    } catch (Exception $e) {
        error_log("JWT init failed: " . $e->getMessage());
    }
}

// ============================================
// ກຳນົດ Request Path
// ============================================
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

// Log request
error_log("API Request: {$method} /{$path}");

// ============================================
// Rate Limiting
// ============================================
$rateLimitEnabled = false; // ປິດໄວ້ກ່ອນ ຖ້າຢາກໃຊ້ສາມາດເປີດໄດ້
if ($rateLimitEnabled) {
    $key = $_SERVER['REMOTE_ADDR'] . ':' . $resource;
    $limit = 100;
    $timeWindow = 3600;

    if (!isset($_SESSION['rate_limit'])) {
        $_SESSION['rate_limit'] = [];
    }

    if (isset($_SESSION['rate_limit'][$key])) {
        if ($_SESSION['rate_limit'][$key]['count'] >= $limit && time() - $_SESSION['rate_limit'][$key]['time'] < $timeWindow) {
            ob_end_clean();
            Response::json([
                'status' => 'error',
                'message' => 'Too many requests'
            ], 429);
            exit();
        }
        
        if (time() - $_SESSION['rate_limit'][$key]['time'] > $timeWindow) {
            $_SESSION['rate_limit'][$key] = ['count' => 1, 'time' => time()];
        } else {
            $_SESSION['rate_limit'][$key]['count']++;
        }
    } else {
        $_SESSION['rate_limit'][$key] = ['count' => 1, 'time' => time()];
    }
}

// ============================================
// Routing
// ============================================
try {
    switch ($resource) {
        // ==================== PRODUCT MANAGEMENT ROUTES ====================
        case 'suppliers':
            $supplierController = new SupplierController($db);
            $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
            $limit = isset($_GET['limit']) ? min(1000000, max(1, (int)$_GET['limit'])) : 10;
            
            if ($method === 'GET') {
                if ($action === 'dropdown') {
                    $supplierController->getSupplierDropdown();
                } elseif (is_numeric($action)) {
                    $supplierController->getSupplier((int)$action);
                } else {
                    $supplierController->listSuppliers($page, $limit);
                }
            } elseif ($method === 'POST') {
                $supplierController->createSupplier();
            } elseif ($method === 'PUT' && is_numeric($action)) {
                $supplierController->updateSupplier((int)$action);
            } elseif ($method === 'DELETE' && is_numeric($action)) {
                $supplierController->deleteSupplier((int)$action);
            } else {
                Response::json(['message' => 'Endpoint not found'], 404);
            }
            break;

        case 'categories':
            $categoryController = new CategoryController($db);
            $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
            $limit = isset($_GET['limit']) ? min(1000000, max(1, (int)$_GET['limit'])) : 10;
            
            if ($method === 'GET') {
                if ($action === 'dropdown') {
                    $categoryController->getCategoryDropdown();
                } elseif (is_numeric($action)) {
                    $categoryController->getCategory((int)$action);
                } else {
                    $categoryController->listCategories($page, $limit);
                }
            } elseif ($method === 'POST') {
                $categoryController->createCategory();
            } elseif ($method === 'PUT' && is_numeric($action)) {
                $categoryController->updateCategory((int)$action);
            } elseif ($method === 'DELETE' && is_numeric($action)) {
                $categoryController->deleteCategory((int)$action);
            } else {
                Response::json(['message' => 'Endpoint not found'], 404);
            }
            break;

        case 'sub-categories':
            $subCategoryController = new SubCategoryController($db);
            $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
            $limit = isset($_GET['limit']) ? min(1000000, max(1, (int)$_GET['limit'])) : 10;

            if ($method === 'GET') {
                if ($action === 'dropdown') {
                    $subCategoryController->getSubCategoryDropdown();
                } elseif (is_numeric($action)) {
                    $subCategoryController->getSubCategory((int)$action);
                } else {
                    $subCategoryController->listSubCategories($page, $limit);
                }
            } elseif ($method === 'POST') {
                $subCategoryController->createSubCategory();
            } elseif ($method === 'PUT' && is_numeric($action)) {
                $subCategoryController->updateSubCategory((int)$action);
            } elseif ($method === 'DELETE' && is_numeric($action)) {
                $subCategoryController->deleteSubCategory((int)$action);
            } else {
                Response::json(['message' => 'Endpoint not found'], 404);
            }
            break;

        case 'products':
            $productController = new ProductController($db);
            $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
            $limit = isset($_GET['limit']) ? min(1000000, max(1, (int)$_GET['limit'])) : 10;

            if ($method === 'GET') {
                if ($action === 'barcode' && !empty($id)) {
                    $productController->getProductByBarcode($id);
                } elseif (is_numeric($action)) {
                    $productController->getProduct((int)$action);
                } else {
                    $productController->listProducts($page, $limit);
                }
            } elseif ($method === 'POST') {
                if ($action === 'upload-image') {
                    $productController->uploadProductImage();
                } else {
                    $productController->createProduct();
                }
            } elseif ($method === 'PUT' && is_numeric($action)) {
                $productController->updateProduct((int)$action);
            } elseif ($method === 'DELETE' && is_numeric($action)) {
                $productController->deleteProduct((int)$action);
            } else {
                Response::json(['message' => 'Endpoint not found'], 404);
            }
            break;

        case 'inventory-stocks':
            $stockController = new InventoryStockController($db);
            $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
            $limit = isset($_GET['limit']) ? min(1000000, max(1, (int)$_GET['limit'])) : 10;

            if ($method === 'GET') {
                if ($action === 'barcode' && !empty($id)) {
                    $stockController->getStockByBarcode($id);
                } elseif (is_numeric($action)) {
                    $stockController->getStock((int)$action);
                } else {
                    $stockController->listStocks($page, $limit);
                }
            } elseif ($method === 'POST') {
                if (is_numeric($action)) {
                    if ($id === 'status') {
                        $stockController->updateStatus((int)$action);
                    } else {
                        $stockController->updateStock((int)$action);
                    }
                } else {
                    $stockController->createStock();
                }
            } elseif ($method === 'PUT' && is_numeric($action)) {
                $stockController->updateStock((int)$action);
            } elseif ($method === 'PATCH' && is_numeric($action) && $id === 'status') {
                $stockController->updateStatus((int)$action);
            } elseif ($method === 'DELETE' && is_numeric($action)) {
                $stockController->deleteStock((int)$action);
            } else {
                Response::json(['message' => 'Endpoint not found'], 404);
            }
            break;

        case 'purchases':
            $purchaseController = new PurchaseController($db);
            $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
            $limit = isset($_GET['limit']) ? min(1000000, max(1, (int)$_GET['limit'])) : 10;

            if ($method === 'GET') {
                if (is_numeric($action)) {
                    $purchaseController->getPurchase((int)$action);
                } else {
                    $purchaseController->listPurchases($page, $limit);
                }
            } elseif ($method === 'POST') {
                if (is_numeric($action)) {
                    if ($id === 'status') {
                        $purchaseController->updateStatus((int)$action);
                    } elseif ($id === 'receive') {
                        $purchaseController->receiveItems((int)$action);
                    } else {
                        Response::json(['message' => 'Endpoint not found'], 404);
                    }
                } else {
                    $purchaseController->createPurchase();
                }
            } else {
                Response::json(['message' => 'Endpoint not found'], 404);
            }
            break;

        case 'sales':
            $saleController = new SaleController($db);
            $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
            $limit = isset($_GET['limit']) ? min(1000000, max(1, (int)$_GET['limit'])) : 10;

            if ($method === 'GET') {
                if (is_numeric($action)) {
                    $saleController->getSale((int)$action);
                } else {
                    $saleController->listSales($page, $limit);
                }
            } elseif ($method === 'POST') {
                if (is_numeric($action) && $id === 'cancel') {
                    $saleController->cancelSale((int)$action);
                } else {
                    $saleController->createSale();
                }
            } else {
                Response::json(['message' => 'Endpoint not found'], 404);
            }
            break;

        case 'customers':
            $customerController = new CustomerController($db);
            $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
            $limit = isset($_GET['limit']) ? min(1000000, max(1, (int)$_GET['limit'])) : 10;

            if ($method === 'GET') {
                if ($action === 'dropdown') {
                    $customerController->getCustomerDropdown();
                } elseif (is_numeric($action)) {
                    $customerController->getCustomer((int)$action);
                } else {
                    $customerController->listCustomers($page, $limit);
                }
            } elseif ($method === 'POST') {
                $customerController->createCustomer();
            } elseif ($method === 'PUT' && is_numeric($action)) {
                $customerController->updateCustomer((int)$action);
            } elseif ($method === 'DELETE' && is_numeric($action)) {
                $customerController->deleteCustomer((int)$action);
            } else {
                Response::json(['message' => 'Endpoint not found'], 404);
            }
            break;

        // ==================== AUTH ROUTES ====================
        case 'auth':
            $authController = new AuthController($db);
            
            if ($method === 'POST') {
                switch ($action) {
                    case 'register':
                        $authController->register();
                        break;
                    case 'login':
                        $authController->login();
                        break;
                    case 'refresh':
                        $authController->refreshToken();
                        break;
                    case 'logout':
                        $authController->logout();
                        break;
                    case 'forgot-password':
                        $authController->forgotPassword();
                        break;
                    case 'reset-password':
                        $authController->resetPassword();
                        break;
                    default:
                        Response::json([
                            'status' => 'error',
                            'message' => 'Auth endpoint not found: ' . $action
                        ], 404);
                }
            } elseif ($method === 'GET') {
                switch ($action) {
                    case 'verify':
                        $authController->verifyToken();
                        break;
                    case 'me':
                        $authController->me();
                        break;
                    default:
                        Response::json([
                            'status' => 'error',
                            'message' => 'Auth endpoint not found: ' . $action
                        ], 404);
                }
            } else {
                Response::json([
                    'status' => 'error',
                    'message' => 'Method not allowed'
                ], 405);
            }
            break;

        // ==================== USER ROUTES ====================
        case 'user':
            $userController = new UserController($db);
            
            // GET /user/profile - ດຶງໂປຣຟາຍ
            if ($method === 'GET' && $action === 'profile') {
                if (!empty($id) && is_numeric($id)) {
                    // ຖ້າມີ ID, ໃຊ້ getProfileById (Admin)
                    $userController->getProfileById((int)$id);
                } else {
                    // ຖ້າບໍ່ມີ ID, ໃຊ້ myProfile (ດຶງຂອງຕົນເອງ)
                    $userController->myProfile();
                }
            }

            if ($method === 'POST') {
                switch ($action) {
                    case 'change-password':
                        $userController->changePassword();
                        break;
                    case 'profile': // Assuming you want to allow updates via POST
                        $userController->updateProfile($id);
                        break;
                    default:
                        Response::json(['message' => 'User endpoint action not found'], 404);
                }
            } elseif ($method === 'GET') {
                switch ($action) {
                    case 'profile':
                        $userController->getProfile($id);
                        break;
                    default:
                        Response::json(['message' => 'User endpoint action not found'], 404);
                }
            }
            // POST /user/avatar - ອັບໂຫຼດຮູບ
            elseif ($method === 'POST' && $action === 'avatar') {
                $file = $_FILES['avatar'] ?? null;
                if ($file && isset($file['error']) && $file['error'] === UPLOAD_ERR_OK) {
                    $userController->uploadAvatar(null, $file);
                } else {
                    Response::json([
                        'status' => 'error',
                        'message' => 'Avatar file required or upload error'
                    ], 400);
                }
            }
            // PUT /user/profile - ອັບເດດໂປຣຟາຍ
            elseif ($method === 'PUT' && $action === 'profile') {
                $userController->updateProfile(null);
            }
            break;

        // ==================== USERS ROUTES (Admin) ====================
        case 'users':
            $userController = new UserController($db);
            
            if ($method === 'GET') {
                if ($action === 'dropdown') {
                    // GET /users/dropdown - ດຶງຜູ້ໃຊ້ສຳລັບ dropdown
                    $userController->getUserDropdown();
                } else {
                    // GET /users - ດຶງຜູ້ໃຊ້ທັງໝົດ (ມີ pagination)
                    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
                    $limit = isset($_GET['limit']) ? min(100, max(1, (int)$_GET['limit'])) : 10;
                    $userController->listUsers($page, $limit);
                }
            } elseif ($method === 'DELETE' && is_numeric($action)) {
                // DELETE /users/{id} - ລຶບຜູ້ໃຊ້
                $userController->deleteUser((int)$action);
            } elseif ($method === 'POST' && is_numeric($action) && $id === 'reset-password') {
                // POST /users/{id}/reset-password - Reset password for another user (Admin only)
                $userController->resetPasswordForUser((int)$action);
            } else {
                Response::json([
                    'status' => 'error',
                    'message' => 'Users endpoint not found'
                ], 404);
            }
            break;

        // ==================== API DOCUMENTATION ====================
        default:
            $response = [
                'status' => 'success',
                'data' => [
                    'name' => 'API',
                    'version' => '2.0.0',
                    'status' => 'running',
                    'timestamp' => date('Y-m-d H:i:s'),
                    'endpoints' => [
                        'Authentication' => [
                            'POST /auth/register' => 'Register new user',
                            'POST /auth/login' => 'Login user',
                            'POST /auth/refresh' => 'Refresh access token',
                            'POST /auth/logout' => 'Logout user',
                            'GET /auth/verify' => 'Verify token validity',
                            'GET /auth/me' => 'Get current user info',
                            'POST /auth/forgot-password' => 'Request password reset',
                            'POST /auth/reset-password' => 'Reset password'
                        ],
                        'User' => [
                            'GET /user/profile' => 'Get current user profile',
                            'PUT /user/profile' => 'Update current user profile',
                            'POST /user/avatar' => 'Upload profile avatar',
                            'POST /user/change-password' => 'Change password'
                        ],
                        'Admin' => [
                            'GET /users' => 'Get all users (pagination)',
                            'GET /users/dropdown' => 'Get users for dropdown',
                            'DELETE /users/{id}' => 'Delete user by ID'
                        ]
                    ]
                ],
                'message' => 'API is running',
                'timestamp' => time()
            ];
            
            ob_end_clean();
            echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            exit();
    }
} catch (Exception $e) {
    error_log("API Error: " . $e->getMessage() . " in " . $e->getFile() . " line " . $e->getLine());
    ob_end_clean();
    Response::json([
        'status' => 'error',
        'message' => 'Internal Server Error',
        'error' => $e->getMessage()
    ], 500);
}

// ຖ້າມີ output ທີ່ຍັງຄ້າງຢູ່
if (ob_get_level()) {
    ob_end_flush();
}
?>