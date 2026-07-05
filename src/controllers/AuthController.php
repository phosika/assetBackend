<?php
// src/controllers/AuthController.php

require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/JWT.php';
require_once __DIR__ . '/../utils/Validator.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../models/PasswordReset.php';

class AuthController
{
    private $userModel;
    private $passwordResetModel;

    public function __construct($db)
    {
        $this->userModel = new User($db);
        $this->passwordResetModel = new PasswordReset($db);
    }

    // ລົງທະບຽນ
    public function register()
    {
        try {
            // 1. ຮັບຂໍ້ມູນຈາກ Request
            $input = file_get_contents('php://input');
            error_log("=== REGISTER REQUEST START ===");
            error_log("Raw input: " . $input);

            $data = json_decode($input, true);

            if (!$data) {
                error_log("Invalid JSON input");
                return Response::json(['message' => 'Invalid JSON input'], 400);
            }

            error_log("Decoded data: " . print_r($data, true));

            // 2. ກຳນົດຄ່າເລີ່ມຕົ້ນ
            if (!isset($data['role'])) {
                $data['role'] = 'user';
            }
            if (!isset($data['status'])) {
                $data['status'] = 'active';
            }

            // 3. ກວດສອບຂໍ້ມູນ
            $rules = [
                'email' => 'required|email|max:100',
                'password' => 'required|string|min:8|max:255',
                'full_name' => 'string|max:100',
                'phone' => 'string|max:20',
                'role' => 'string|in:user,manager,cashier,warehouser,admin'
            ];

            $errors = Validator::validate($data, $rules);

            if (!empty($errors)) {
                error_log("Validation errors: " . print_r($errors, true));
                return Response::json(['errors' => $errors], 422);
            }

            // 4. ກວດສອບວ່າມີຜູ້ໃຊ້ຢູ່ແລ້ວບໍ່
            error_log("Checking if user exists: username={$data['full_name']}, email={$data['email']}");
            if ($this->userModel->exists($data['email'])) {
                error_log("User already exists");
                return Response::json([
                    'message' => 'Username or email already exists'
                ], 409);
            }

            // 5. ສ້າງຜູ້ໃຊ້ໃໝ່
            error_log("Creating user...");
            $userId = $this->userModel->create($data);
            error_log("Create result: " . ($userId ? "Success (ID: $userId)" : "Failed"));

            if (!$userId) {
                error_log("Registration failed - create returned false");
                return Response::json([
                    'message' => 'Registration failed. Please try again.'
                ], 500);
            }

            // 6. ດຶງຂໍ້ມູນຜູ້ໃຊ້ທີ່ສ້າງໃໝ່
            error_log("Fetching user by ID: $userId");
            $user = $this->userModel->findById($userId);

            if (!$user) {
                error_log("User created but not found");
                return Response::json([
                    'message' => 'User created but not found'
                ], 500);
            }

            error_log("User found: " . print_r($user, true));

            // 7. ສ້າງ JWT Token
            error_log("Creating JWT token...");
            $token = JWT::create([
                'id' => $user['id'],
                'email' => $user['email'],
                'role' => $user['role']
            ]);

            if (!$token) {
                error_log("Failed to generate token");
                return Response::json([
                    'message' => 'Failed to generate token'
                ], 500);
            }

            // 8. ລຶບຂໍ້ມູນທີ່ອ່ອນໄຫວ
            unset($user['password_hash']);

            // 9. ສົ່ງຜົນກັບຄືນ
            error_log("Registration successful for user: " . $user['email']);
            return Response::json([
                'message' => 'User registered successfully',
                'user' => $user,
                'token' => $token,
                'expires_in' => 3600
            ], 201);
        } catch (PDOException $e) {
            error_log("Registration PDO Error: " . $e->getMessage());
            error_log("PDO Error Code: " . $e->getCode());
            error_log("PDO Error Info: " . print_r($e->errorInfo, true));
            return Response::json([
                'message' => 'Database error: ' . $e->getMessage()
            ], 500);
        } catch (Exception $e) {
            error_log("Registration Error: " . $e->getMessage());
            error_log("Error File: " . $e->getFile());
            error_log("Error Line: " . $e->getLine());
            error_log("Error Trace: " . $e->getTraceAsString());
            return Response::json([
                'message' => 'An error occurred: ' . $e->getMessage()
            ], 500);
        }
    }

    // ເຂົ້າສູ່ລະບົບ
    public function login()
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);

            if (!$data) {
                return Response::json(['message' => 'Invalid JSON input'], 400);
            }

            $rules = [
                'email' => 'required|email',
                'password' => 'required|string|min:6'
            ];

            $errors = Validator::validate($data, $rules);

            if (!empty($errors)) {
                return Response::json(['errors' => $errors], 422);
            }

            $user = $this->userModel->findByEmail($data['email']);

            if (!$user) {
                return Response::json(['message' => 'Invalid email or password'], 401);
            }

            $passwordField = isset($user['password_hash']) ? 'password_hash' : 'password';

            if (!password_verify($data['password'], $user[$passwordField])) {
                return Response::json(['message' => 'Invalid email or password'], 401);
            }

            if (isset($user['status']) && $user['status'] === 'suspended') {
                return Response::json(['message' => 'Account has been suspended'], 403);
            }

            // ກວດສອບວ່າ user ມີ id
            if (!isset($user['id']) || empty($user['id'])) {
                return Response::json(['message' => 'Invalid user data'], 500);
            }

            // ກວດສອບ username
            $username = $user['username'] ?? $user['email'] ?? 'user_' . $user['id'];

            if (empty($user['username']) || $user['username'] === null) {
                $newUsername = explode('@', $user['email'])[0] ?? 'user_' . $user['id'];
                $this->userModel->update($user['id'], ['username' => $newUsername]);
                $username = $newUsername;
                $user['username'] = $newUsername;
            }

            // ສ້າງ payload ສຳລັບ JWT (ຕ້ອງມີ id ແນ່ນອນ)
            $payload = [
                'id' => (int)$user['id'], // ແນ່ໃຈວ່າເປັນ integer
                'username' => $username,
                'email' => $user['email'],
                'role' => $user['role'] ?? 'user'
            ];

            // ກວດສອບ payload ກ່ອນສ້າງ token
            error_log("Login: Creating token with payload: " . print_r($payload, true));

            $token = JWT::create($payload);

            if (!$token) {
                return Response::json(['message' => 'Failed to create token'], 500);
            }

            $userData = [
                'id' => (int)$user['id'],
                'email' => $user['email'],
                'full_name' => $user['full_name'] ?? null,
                'phone' => $user['phone'] ?? null,
                'avatar' => $user['avatar'] ?? null,
                'role' => $user['role'] ?? 'user',
                'created_at' => $user['created_at'] ?? null
            ];

            $expiresIn = method_exists('JWT', 'getExpiryTime') ? JWT::getExpiryTime() : time() + 3600;

            return Response::json([
                'message' => 'Login successful',
                'user' => $userData,
                'token' => $token,
                'expires_in' => $expiresIn
            ], 200);
        } catch (Exception $e) {
            error_log("Login error: " . $e->getMessage() . " in " . $e->getFile() . " line " . $e->getLine());
            return Response::json(['message' => 'An error occurred: ' . $e->getMessage()], 500);
        }
    }

    // ອອກຈາກລະບົບ
    public function logout()
    {
        try {
            AuthMiddleware::check();

            if (session_status() === PHP_SESSION_ACTIVE) {
                session_destroy();
            }

            $token = $this->getBearerToken();
            if ($token && method_exists('JWT', 'blacklist')) {
                // JWT::blacklist($token);
            }

            return Response::json(['message' => 'Logged out successfully'], 200);
        } catch (Exception $e) {
            return Response::json(['message' => 'Logout failed: ' . $e->getMessage()], 500);
        }
    }

    // ຟື້ນຟູລະຫັດຜ່ານ
    public function forgotPassword()
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!$data || !isset($data['email'])) {
                return Response::json(['message' => 'Email is required'], 400);
            }

            $rules = ['email' => 'required|email'];
            $errors = Validator::validate($data, $rules);
            
            if (!empty($errors)) {
                return Response::json(['errors' => $errors], 422);
            }

            $email = $data['email'];
            $user = $this->userModel->findByEmail($email);
            
            // ບໍ່ຄວນບອກວ່າມີຜູ້ໃຊ້ຫຼືບໍ່ ເພື່ອຄວາມປອດໄພ
            if (!$user) {
                return Response::json([
                    'message' => 'If the email exists, a reset link will be sent'
                ], 200);
            }

            // ສ້າງ token ທີ່ປອດໄພ
            $token = bin2hex(random_bytes(32));
            $expiresIn = 3600; // 1 ຊົ່ວໂມງ

            // ເກັບ token ໃສ່ຖານຂໍ້ມູນ
            $saved = $this->passwordResetModel->create($email, $token, $expiresIn);
            
            if (!$saved) {
                return Response::json(['message' => 'Failed to create reset token'], 500);
            }

            // ທີ່ນີ້ທ່ານສາມາດສົ່ງ Email ໃຫ້ຜູ້ໃຊ້
            // ແຕ່ສຳລັບການທົດສອບ, ໃຫ້ຄືນ token ກັບ Client
            // ຫຼື ສົ່ງຜ່ານ Email ແທ້ໆ

            return Response::json([
                'message' => 'If the email exists, a reset link will be sent',
                'token' => $token, // ຖ້າຢາກໃຫ້ Client ໄດ້ເຫັນ (ສຳລັບ dev)
                'expires_in' => $expiresIn
            ], 200);
            
        } catch (Exception $e) {
            error_log("Forgot password error: " . $e->getMessage());
            return Response::json(['message' => 'An error occurred'], 500);
        }
    }

    // ຣີເຊັດລະຫັດຜ່ານ
public function resetPassword()
{
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        
        // ກວດສອບຂໍ້ມູນ
        $rules = [
            'token' => 'required|string',
            'password' => 'required|string|min:8|max:255',
            'password_confirmation' => 'required|string|same:password'
        ];

        $errors = Validator::validate($data, $rules);
        if (!empty($errors)) {
            return Response::json(['errors' => $errors], 422);
        }

        $token = $data['token'];
        $newPassword = $data['password'];

        // ກວດສອບ token ວ່າຖືກຕ້ອງ ແລະ ຍັງບໍ່ໝົດອາຍຸ
        $resetRecord = $this->passwordResetModel->verify($token);
        if (!$resetRecord) {
            return Response::json([
                'message' => 'Invalid or expired token'
            ], 400);
        }

        $email = $resetRecord['email'];

        // ດຶງຂໍ້ມູນຜູ້ໃຊ້ຕາມ email
        $user = $this->userModel->findByEmail($email);
        if (!$user) {
            return Response::json(['message' => 'User not found'], 404);
        }

        // ອັບເດດລະຫັດຜ່ານ (ໃຊ້ຄລໍາ password)
        $updated = $this->userModel->update($user['id'], [
            'password' => $newPassword
        ]);

        if (!$updated) {
            error_log("Reset password update failed for user ID: " . $user['id']);
            return Response::json(['message' => 'Failed to reset password'], 500);
        }

        // ໝາຍວ່າ token ຖືກໃຊ້ແລ້ວ
        $this->passwordResetModel->markAsUsed($resetRecord['id']);

        // (ເພີ່ມເຕີມ) ລຶບ tokens ເກົ່າ
        $this->passwordResetModel->cleanExpired();

        return Response::json(['message' => 'Password reset successfully'], 200);
        
    } catch (Exception $e) {
        error_log("Reset password error: " . $e->getMessage());
        return Response::json(['message' => 'An error occurred: ' . $e->getMessage()], 500);
    }
}

    // ປ່ຽນລະຫັດຜ່ານ
    public function changePassword()
    {
        try {
            $user = AuthMiddleware::check();

            $data = json_decode(file_get_contents('php://input'), true);

            $rules = [
                'current_password' => 'required|string',
                'new_password' => 'required|string|min:8|max:255',
                'new_password_confirmation' => 'required|string|same:new_password'
            ];

            $errors = Validator::validate($data, $rules);

            if (!empty($errors)) {
                return Response::json(['errors' => $errors], 422);
            }

            $currentUser = $this->userModel->findById($user['id'], true);

            if (!$currentUser) {
                return Response::json(['message' => 'User not found'], 404);
            }

            $passwordField = isset($currentUser['password_hash']) ? 'password_hash' : 'password';

            if (!password_verify($data['current_password'], $currentUser[$passwordField])) {
                return Response::json(['message' => 'Current password is incorrect'], 401);
            }

            $updated = $this->userModel->update($user['id'], [
                'password' => $data['new_password']
            ]);

            if ($updated) {
                return Response::json(['message' => 'Password changed successfully'], 200);
            }

            return Response::json(['message' => 'Failed to change password'], 400);
        } catch (Exception $e) {
            return Response::json(['message' => 'An error occurred: ' . $e->getMessage()], 500);
        }
    }

    // ດຶງຂໍ້ມູນຜູ້ໃຊ້ປັດຈຸບັນ
    public function me()
    {
        try {
            $user = AuthMiddleware::check();

            $userData = $this->userModel->findById($user['id']);

            if (!$userData) {
                return Response::json(['message' => 'User not found'], 404);
            }

            unset($userData['password'], $userData['password_hash']);

            return Response::json($userData, 200);
        } catch (Exception $e) {
            return Response::json(['message' => 'An error occurred: ' . $e->getMessage()], 500);
        }
    }

    // ກວດສອບ token
    public function verifyToken()
    {
        try {
            $user = AuthMiddleware::check();

            return Response::json([
                'valid' => true,
                'user' => $user
            ], 200);
        } catch (Exception $e) {
            return Response::json([
                'valid' => false,
                'message' => 'Invalid or expired token'
            ], 401);
        }
    }

    // Helper: ດຶງ Bearer Token
    private function getBearerToken()
    {
        $headers = getallheaders();

        foreach ($headers as $key => $value) {
            if (strtolower($key) === 'authorization') {
                if (strpos($value, 'Bearer ') === 0) {
                    return substr($value, 7);
                }
            }
        }

        return null;
    }

    // Refresh token
    public function refreshToken()
    {
        try {
            $user = AuthMiddleware::check();

            if (!$user) {
                return Response::json(['message' => 'Authentication required'], 401);
            }

            if (!isset($user['id'])) {
                return Response::json(['message' => 'Invalid user data'], 400);
            }

            $payload = [
                'id' => $user['id'],
                'username' => $user['username'] ?? $user['email'] ?? 'user_' . $user['id'],
                'email' => $user['email'] ?? '',
                'role' => $user['role'] ?? 'user'
            ];

            $newToken = JWT::create($payload);

            if (!$newToken) {
                return Response::json(['message' => 'Failed to generate new token'], 500);
            }

            return Response::json([
                'message' => 'Token refreshed successfully',
                'token' => $newToken,
                'expires_in' => JWT::getExpiryTime()
            ], 200);
        } catch (Exception $e) {
            return Response::json([
                'message' => 'Failed to refresh token: ' . $e->getMessage()
            ], 500);
        }
    }
}
