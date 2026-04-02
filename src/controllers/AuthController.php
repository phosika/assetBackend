<?php
// src/controllers/AuthController.php
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../utils/JWT.php';
require_once __DIR__ . '/../utils/Response.php';

class AuthController {
    private $userModel;

    public function __construct() {
        $this->userModel = new User();
    }

    // POST /auth/register
    public function register() {
        $data = json_decode(file_get_contents('php://input'), true);

        // ກວດສອບຂໍ້ມູນທີ່ຈຳເປັນ
        $required = ['employee_code', 'username', 'email', 'password', 'first_name', 'last_name'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                Response::error("Missing required field: $field", 400);
            }
        }

        // ກວດສອບຮູບແບບອີເມວ
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            Response::error('Invalid email format', 400);
        }

        // ກວດສອບຄວາມຍາວລະຫັດຜ່ານ
        if (strlen($data['password']) < 8) {
            Response::error('Password must be at least 8 characters', 400);
        }

        $result = $this->userModel->register($data);

        if ($result['success']) {
            Response::success(['user_id' => $result['user_id']], $result['message']);
        } else {
            Response::error($result['message'], 400);
        }
    }

    // POST /auth/login
    public function login() {
        $data = json_decode(file_get_contents('php://input'), true);

        if (empty($data['email']) || empty($data['password'])) {
            Response::error('Email/Username and password required', 400);
        }

        $ip = $_SERVER['REMOTE_ADDR'];
        $userAgent = $_SERVER['HTTP_USER_AGENT'];

        $result = $this->userModel->login($data['email'], $data['password'], $ip, $userAgent);

        if (!$result['success']) {
            Response::error($result['message'], 401);
        }

        // ສ້າງ JWT tokens
        $config = include __DIR__ . '/../config/jwt.php';
        
        $accessToken = JWT::generate([
            'user_id' => $result['user']['id'],
            'employee_code' => $result['user']['employee_code'],
            'username' => $result['user']['username'],
            'email' => $result['user']['email'],
            'role' => $result['user']['role'],
            'department_id' => $result['user']['department_id']
        ], $config['access_token_expiry']);

        $refreshToken = JWT::generate([
            'user_id' => $result['user']['id'],
            'type' => 'refresh'
        ], $config['refresh_token_expiry']);

        Response::success([
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'token_type' => 'Bearer',
            'expires_in' => $config['access_token_expiry'],
            'user' => $result['user']
        ], 'Login successful');
    }

    // POST /auth/refresh
    public function refresh() {
        $data = json_decode(file_get_contents('php://input'), true);

        if (empty($data['refresh_token'])) {
            Response::error('Refresh token required', 400);
        }

        $payload = JWT::validate($data['refresh_token']);
        
        if (!$payload || !isset($payload['type']) || $payload['type'] !== 'refresh') {
            Response::unauthorized('Invalid refresh token');
        }

        $user = $this->userModel->getById($payload['user_id']);

        if (!$user) {
            Response::unauthorized('User not found');
        }

        if ($user['status'] !== 'active') {
            Response::forbidden('Account is ' . $user['status']);
        }

        $config = include __DIR__ . '/../config/jwt.php';
        
        $newAccessToken = JWT::generate([
            'user_id' => $user['id'],
            'employee_code' => $user['employee_code'],
            'username' => $user['username'],
            'email' => $user['email'],
            'role' => $user['role'],
            'department_id' => $user['department_id']
        ], $config['access_token_expiry']);

        Response::success([
            'access_token' => $newAccessToken,
            'token_type' => 'Bearer',
            'expires_in' => $config['access_token_expiry']
        ], 'Token refreshed successfully');
    }

    // POST /auth/logout
    public function logout() {
        // ສາມາດເພີ່ມ logic ສຳລັບ blacklist token ໄດ້ທີ່ນີ້
        Response::success(null, 'Logged out successfully');
    }
}
?>