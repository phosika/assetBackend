<?php
// ເປີດໃຊ້ error reporting ໃນ development
if (file_exists(__DIR__ . '/.env')) {
    require_once __DIR__ . '/vendor/autoload.php';
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->load();
    
    if ($_ENV['APP_ENV'] === 'development') {
        error_reporting(E_ALL);
        ini_set('display_errors', 1);
    }
}

// ໂຫຼດ CORS middleware
require_once __DIR__ . '/middleware/cors.php';

// ກຳນົດເສັ້ນທາງ API
$request_uri = $_SERVER['REQUEST_URI'];
$request_method = $_SERVER['REQUEST_METHOD'];

// ລຶບ query string ອອກ
$request_uri = strtok($request_uri, '?');

// ລຶບ base path ອອກ (ຖ້າມີ)
$base_path = '/';
if (strpos($request_uri, $base_path) === 0) {
    $request_uri = substr($request_uri, strlen($base_path) - 1);
}

// ແບ່ງເສັ້ນທາງ
$path_parts = explode('/', trim($request_uri, '/'));

// ກວດສອບວ່າເປັນ API route ຫຼືບໍ່
if (isset($path_parts[0]) && $path_parts[0] === 'api') {
    $api_version = $path_parts[1] ?? 'v1';
    $resource = $path_parts[2] ?? '';
    $action = $path_parts[3] ?? '';
    
    // ກຳນົດເສັ້ນທາງ
    switch ($resource) {
        case 'auth':
            switch ($action) {
                case 'login':
                    require_once 'api/auth/login.php';
                    break;
                case 'register':
                    require_once 'api/auth/register.php';
                    break;
                case 'me':
                    require_once 'api/auth/me.php';
                    break;
                case 'logout':
                    require_once 'api/auth/logout.php';
                    break;
                case 'forgot-password':
                    require_once 'api/auth/forgot-password.php';
                    break;
                case 'reset-password':
                    require_once 'api/auth/reset-password.php';
                    break;
                default:
                    http_response_code(404);
                    echo json_encode([
                        'success' => false,
                        'message' => 'Auth endpoint not found'
                    ]);
                    break;
            }
            break;
            
        default:
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'API resource not found'
            ]);
            break;
    }
} else {
    // ສຳລັບ route ທີ່ບໍ່ແມ່ນ API, ສົ່ງຄືນ welcome message
    echo json_encode([
        'success' => true,
        'message' => 'Asset Management System API',
        'version' => '1.0.0',
        'endpoints' => [
            'auth' => [
                'login' => '/api/v1/auth/login',
                'register' => '/api/v1/auth/register',
                'me' => '/api/v1/auth/me',
                'logout' => '/api/v1/auth/logout'
            ]
        ]
    ]);
}
?>
