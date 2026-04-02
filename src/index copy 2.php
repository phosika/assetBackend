<?php
// src/index.php
require_once 'utils/Response.php';
require_once 'controllers/AuthController.php';
require_once 'controllers/UserController.php';

// ຈັດການ CORS ສຳລັບ preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    exit(0);
}

$request = $_SERVER['REQUEST_URI'];
$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($request, PHP_URL_PATH);
$path = str_replace('/index.php', '', $path);
$segments = explode('/', trim($path, '/'));

$resource = $segments[0] ?? '';
$action = $segments[1] ?? '';

// ເພີ່ມ rate limiting (ພື້ນຖານ)
session_start();
$key = $_SERVER['REMOTE_ADDR'] . ':' . $resource;
$limit = 100; // 100 requests
$timeWindow = 3600; // ຕໍ່ 1 ຊົ່ວໂມງ

if (isset($_SESSION[$key])) {
    if ($_SESSION[$key]['count'] >= $limit && time() - $_SESSION[$key]['time'] < $timeWindow) {
        Response::error('Too many requests', 429);
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
switch ($resource) {
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
                } else {
                    Response::notFound('User endpoint not found');
                }
                break;
            default:
                Response::error('Method not allowed', 405);
        }
        break;

    case 'users':
        $userController = new UserController();
        switch ($method) {
            case 'GET':
                if ($action === '') {
                    $userController->getAllUsers();
                } else {
                    Response::notFound('Users endpoint not found');
                }
                break;
            default:
                Response::error('Method not allowed', 405);
        }
        break;

    default:
        // API Info
        Response::success([
            'name' => 'Secure API',
            'version' => '1.0.0',
            'endpoints' => [
                'POST /auth/register' => 'Register new user',
                'POST /auth/login' => 'Login user',
                'POST /auth/refresh' => 'Refresh access token',
                'POST /auth/logout' => 'Logout user',
                'GET /user/profile' => 'Get user profile (Auth required)',
                'PUT /user/profile' => 'Update user profile (Auth required)',
                'POST /user/change-password' => 'Change password (Auth required)',
                'GET /users' => 'Get all users (Admin only)'
            ]
        ], 200, 'API is running');
}
?>