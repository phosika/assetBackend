<?php
// src/index.php - API Router

// ຕ້ອງແນ່ໃຈວ່າບໍ່ມີ output ກ່ອນ headers
ob_start();

// ເລີ່ມ session ຖ້າຍັງບໍ່ໄດ້ເລີ່ມ
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ============================================
// ສ່ວນນີ້ຄວນລຶບອອກເມື່ອຂຶ້ນ Production
// ============================================
// ສ້າງ session ທົດສອບສຳລັບ development (ຖ້າບໍ່ມີ user ເຂົ້າສູ່ລະບົບ)
// if (!isset($_SESSION['user_id']) && !isset($_SESSION['user'])) {
//     $_SESSION['user_id'] = 1;
//     $_SESSION['user'] = ['id' => 1, 'name' => 'Admin', 'email' => 'admin@example.com'];
//     error_log("Development session created for testing");
// }

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
    // $db->query("SELECT 1");
    
} catch (Exception $e) {
    error_log("Database connection failed: " . $e->getMessage());
    Response::error('Database connection failed', 500);
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

// ============================================
// Rate Limiting
// ============================================
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

// ==================== DEBUG ROUTE ====================
// if ($resource === 'debug') {
//     try {
//         $headers = getallheaders();
//         $authHeader = null;
//         foreach ($headers as $key => $value) {
//             if (strtolower($key) === 'authorization') {
//                 $authHeader = $value;
//                 break;
//             }
//         }
        
//         $response = [
//             'debug' => true,
//             'headers' => $headers,
//             'auth_header' => $authHeader,
//             'method' => $method,
//             'resource' => $resource,
//             'action' => $action,
//             'session' => $_SESSION,
//             'segments' => $segments
//         ];
        
//         if ($authHeader && $action === 'token') {
//             $token = str_replace('Bearer ', '', $authHeader);
//             $decoded = JWT::decode($token);
//             $validated = JWT::validate($token);
            
//             $response['token'] = [
//                 'raw' => substr($token, 0, 50) . '...',
//                 'decoded' => $decoded,
//                 'validated' => $validated,
//                 'has_id' => isset($decoded['id']),
//                 'id_value' => $decoded['id'] ?? null,
//                 'has_username' => isset($decoded['username']),
//                 'username_value' => $decoded['username'] ?? null,
//                 'has_email' => isset($decoded['email']),
//                 'email_value' => $decoded['email'] ?? null,
//                 'has_role' => isset($decoded['role']),
//                 'role_value' => $decoded['role'] ?? null,
//                 'exp' => $decoded['exp'] ?? null,
//                 'exp_formatted' => isset($decoded['exp']) ? date('Y-m-d H:i:s', $decoded['exp']) : null,
//                 'is_expired' => isset($decoded['exp']) ? $decoded['exp'] < time() : true
//             ];
//         }
        
//         // ທົດສອບການດຶງຂໍ້ມູນຜູ້ໃຊ້
//         if ($authHeader && $action === 'user') {
//             $token = str_replace('Bearer ', '', $authHeader);
//             $payload = JWT::validate($token);
            
//             if ($payload && isset($payload['id'])) {
//                 $user = $this->userModel->findById($payload['id']);
//                 $response['user_from_db'] = $user ? $user : 'User not found';
//             } else {
//                 $response['user_from_db'] = 'Invalid token or missing ID';
//             }
//         }
        
//         ob_end_clean();
//         Response::json($response, 200);
//         exit();
//     } catch (Exception $e) {
//         ob_end_clean();
//         Response::json(['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()], 500);
//         exit();
//     }
// }

// ============================================
// Routing
// ============================================
try {
    switch ($resource) {
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
                        Response::notFound('Auth endpoint not found');
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
                        Response::notFound('Auth endpoint not found');
                }
            } else {
                Response::error('Method not allowed', 405);
            }
            break;

        // ==================== USER ROUTES ====================
        case 'user':

            // Debug: ກວດສອບການເຊື່ອມຕໍ່ຖານຂໍ້ມູນ
            // try {
            //     $db->query("SELECT 1");
            //     error_log("Database connection is active in index.php");
            // } catch (Exception $e) {
            //     error_log("Database connection failed in index.php: " . $e->getMessage());
            //     Response::error('Database connection failed', 500);
            //     exit();
            // }


            $userController = new UserController($db);
            
            // GET /user/profile - ດຶງໂປຣຟາຍຂອງຕົນເອງ
            if ($method === 'GET' && $action === 'profile') {
                // ກວດສອບວ່າມີ ID ໃນ URL ບໍ່
                if (!empty($id) && is_numeric($id)) {
                    // ຖ້າມີ ID, ໃຊ້ getProfileById (Admin)
                    $userController->getProfileById((int)$id);
                } else {
                    // ຖ້າບໍ່ມີ ID, ໃຊ້ myProfile (ດຶງຂອງຕົນເອງ)
                    $userController->myProfile();
                }
            }
            // PUT /user/profile - ອັບເດດໂປຣຟາຍ
            elseif ($method === 'PUT' && $action === 'profile') {
                // ສົ່ງ null ເຂົ້າໄປ, ຈະໃຊ້ ID ຈາກ Token
                $userController->updateProfile(null);
            }
            // POST /user/avatar - ອັບໂຫຼດຮູບ
            elseif ($method === 'POST' && $action === 'avatar') {
                $file = $_FILES['avatar'] ?? null;
                if ($file) {
                    $userController->uploadAvatar(null, $file);
                } else {
                    Response::error('Avatar file required', 400);
                }
            }
            // POST /user/change-password - ປ່ຽນລະຫັດຜ່ານ
            elseif ($method === 'POST' && $action === 'change-password') {
                $userController->changePassword();
            } else {
                Response::notFound('User endpoint not found');
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
                    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
                    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
                    $userController->listUsers($page, $limit);
                }
            } elseif ($method === 'DELETE' && !empty($id) && is_numeric($id)) {
                // DELETE /users/{id} - ລຶບຜູ້ໃຊ້
                $userController->deleteUser((int)$id);
            } else {
                Response::notFound('Users endpoint not found');
            }
            break;

        // ==================== API DOCUMENTATION ====================
        default:
            $response = [
                'name' => 'Suvinhome',
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
                ],
                'documentation' => '/api/docs',
                'server_time' => date('Y-m-d H:i:s')
            ];
            
            ob_end_clean();
            Response::success($response, 200, 'API is running');
    }
} catch (Exception $e) {
    error_log("API Error: " . $e->getMessage() . " in " . $e->getFile() . " line " . $e->getLine());
    ob_end_clean();
    Response::error('Internal Server Error: ' . $e->getMessage(), 500);
}

// ຖ້າມີ output ທີ່ຍັງຄ້າງຢູ່
if (ob_get_level()) {
    ob_end_flush();
}
?>