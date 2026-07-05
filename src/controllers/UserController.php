<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// src/controllers/UserController.php
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Validator.php';
require_once __DIR__ . '/../utils/FileUploader.php';

class UserController {
    private $userModel;

    public function __construct($db) {
        $this->userModel = new User($db);
    }

    /**
     * ດຶງຂໍ້ມູນໂປຣຟາຍ
     * GET /user/profile
     */
    public function getProfile($userId = null) {
        try {
            // 1. ກວດສອບ Authentication
            $currentUser = AuthMiddleware::check();

            // 2. ເອົາ ID ຈາກ Token  (Override ເມື່ອ Client ສົ່ງ Parameter ມາ)
            $userId = $currentUser['id'] ?? null;
            
            if (!$userId) {
                return Response::json(['message' => 'User ID not found in token'], 400);
            }

            // 3. ເກັບ权限 (Admin ເຫັນໄດ້, User ເຫັນໄດ້ ແຕ່ໂຕເອງ)
            if ($currentUser['role'] !== 'admin') {
                return Response::json(['message' => 'Permission denied'], 403);
            }

            // 4. ດຶງ User (Query 1 ເທື່ອ)
            $user = $this->userModel->findById((int)$userId);
            
            if (!$user) {
                return Response::json(['message' => 'User not found in database'], 404);
            }

            // 5. ກ່ອນ Return -> ລຶບ Password
            unset($user['password'], $user['password_hash']);
            
            return Response::json($user, 200);
            
        } catch (Exception $e) {
            error_log("getProfile error: " . $e->getMessage());
            return Response::json(['message' => 'An error occurred'], 500);
        }
    }

    /**
     * ດຶງຂໍ້ມູນໂປຣຟາຍຂອງຕົນເອງ
     * GET /user/profile
     */
    public function myProfile() {
        try {
            // ກວດສອບສິດ ແລະ ດຶງຂໍ້ມູນຜູ້ໃຊ້ຈາກ Token
            $currentUser = AuthMiddleware::check();
            
            if (!$currentUser) {
                return Response::json(['message' => 'Authentication required'], 401);
            }

            // ກວດສອບວ່າມີ ID ຈາກ Token
            if (empty($currentUser['id'])) {
                return Response::json(['message' => 'Invalid user data'], 400);
            }

            // ດຶງຂໍ້ມູນຜູ້ໃຊ້
            $user = $this->userModel->findById((int)$currentUser['id']);
            
            if (!$user) {
                return Response::json(['message' => 'User not found'], 404);
            }

            // ກຳຈັດຂໍ້ມູນທີ່ອ່ອນໄຫວ
            unset($user['password'], $user['password_hash']);
            
            return Response::json($user, 200);
            
        } catch (Exception $e) {
            error_log("myProfile error: " . $e->getMessage());
            return Response::json(['message' => 'An error occurred: ' . $e->getMessage()], 500);
        }
    }

    /**
     * ດຶງຂໍ້ມູນຜູ້ໃຊ້ຕາມ ID (ສຳລັບ Admin)
     * GET /user/profile/{id}
     */
    public function getProfileById($userId) {
        try {
            // ກວດສອບສິດການເຂົ້າເຖິງ (ຕ້ອງເປັນ Admin)
            $currentUser = AuthMiddleware::checkAdmin();
            
            if (empty($userId) || !is_numeric($userId)) {
                return Response::json(['message' => 'Invalid user ID'], 400);
            }

            $user = $this->userModel->findById((int)$userId);
            
            if (!$user) {
                return Response::json(['message' => 'User not found'], 404);
            }

            // ກຳຈັດຂໍ້ມູນທີ່ອ່ອນໄຫວ
            unset($user['password'], $user['password_hash']);
            
            return Response::json($user, 200);
            
        } catch (Exception $e) {
            error_log("getProfileById error: " . $e->getMessage());
            return Response::json(['message' => 'An error occurred: ' . $e->getMessage()], 500);
        }
    }
 
    // ອັບເດດຂໍ້ມູນໂປຣຟາຍ
    public function updateProfile($userId = null) {
        try {
            // ກວດສອບສິດ ແລະ ດຶງຂໍ້ມູນຜູ້ໃຊ້ຈາກ Token
            $currentUser = AuthMiddleware::check();
            
            if (!$currentUser) {
                return Response::json(['message' => 'Authentication required'], 401);
            }

            // ຖ້າບໍ່ມີການສົ່ງ userId ມາ, ໃຊ້ ID ຈາກ Token
            if (empty($userId) || !is_numeric($userId)) {
                $userId = $currentUser['id'];
            }

            // ກວດສອບວ່າຜູ້ໃຊ້ມີສິດອັບເດດຂໍ້ມູນນີ້ບໍ່
            if ($userId != $currentUser['id'] && $currentUser['role'] !== 'admin') {
                return Response::json(['message' => 'You do not have permission to update this profile'], 403);
            }

            // ຮັບຂໍ້ມູນຈາກ request body
            $data = json_decode(file_get_contents('php://input'), true);

            if (!$data) {
                return Response::json(['message' => 'Invalid JSON input'], 400);
            }

            // ເອົາຂໍ້ມູນທີ່ບໍ່ຕ້ອງການອອກ
            unset($data['id'], $data['password'], $data['password_hash'], $data['created_at']);

            if (empty($data)) {
                return Response::json(['message' => 'No data to update'], 400);
            }

            // ກວດສອບວ່າຜູ້ໃຊ້ມີຢູ່ຈິງຫຼືບໍ່
            $currentUserData = $this->userModel->findById((int)$userId);
            if (!$currentUserData) {
                return Response::json(['message' => 'User not found'], 404);
            }

            // ອັບເດດຂໍ້ມູນ
            $updated = $this->userModel->update((int)$userId, $data);

            if ($updated) {
                $updatedUser = $this->userModel->findById((int)$userId);
                unset($updatedUser['password'], $updatedUser['password_hash']);
                
                return Response::json([
                    'message' => 'Profile updated successfully',
                    'user' => $updatedUser
                ], 200);
            } else {
                return Response::json(['message' => 'Failed to update profile or no changes made'], 400);
            }
            
        } catch (Exception $e) {
            return Response::json(['message' => 'An error occurred: ' . $e->getMessage()], 500);
        }
    }

 // ອັບໂຫຼດຮູບໂປຣຟາຍ
    public function uploadAvatar($userId = null, $file = null) {
        try {
            // ກວດສອບສິດ ແລະ ດຶງຂໍ້ມູນຜູ້ໃຊ້ຈາກ Token
            $currentUser = AuthMiddleware::check();
            
            if (!$currentUser) {
                return Response::json(['message' => 'Authentication required'], 401);
            }

            // ຖ້າບໍ່ມີການສົ່ງ userId ມາ, ໃຊ້ ID ຈາກ Token
            if (empty($userId) || !is_numeric($userId)) {
                $userId = $currentUser['id'];
            }

            // ກວດສອບວ່າຜູ້ໃຊ້ມີສິດອັບເດດຂໍ້ມູນນີ້ບໍ່
            if ($userId != $currentUser['id'] && $currentUser['role'] !== 'admin') {
                return Response::json(['message' => 'You do not have permission to update this profile'], 403);
            }

            // ກວດສອບໄຟລ໌
            if (!$file || !isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
                return Response::json(['message' => 'No file uploaded or upload error'], 400);
            }

            // ກວດສອບຂະໜາດໄຟລ໌ (ສູງສຸດ 5MB)
            if ($file['size'] > 5 * 1024 * 1024) {
                return Response::json(['message' => 'File size too large. Maximum 5MB'], 400);
            }

            // ກວດສອບປະເພດໄຟລ໌
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            
            if (!in_array($mimeType, $allowedTypes)) {
                return Response::json(['message' => 'Invalid file type. Allowed: JPEG, PNG, GIF, WEBP'], 400);
            }

            // ກວດສອບວ່າຜູ້ໃຊ້ມີຢູ່ຈິງຫຼືບໍ່
            $currentUserData = $this->userModel->findById((int)$userId);
            if (!$currentUserData) {
                return Response::json(['message' => 'User not found'], 404);
            }

            $uploader = new FileUploader();
            $uploadResult = $uploader->upload($file, 'uploads/avatars/');

            if ($uploadResult['success']) {
                // ລຶບຮູບເກົ່າຖ້າມີ
                if (isset($currentUserData['avatar']) && !empty($currentUserData['avatar'])) {
                    $oldAvatar = __DIR__ . '/../' . $currentUserData['avatar'];
                    if (file_exists($oldAvatar)) {
                        unlink($oldAvatar);
                    }
                }

                $this->userModel->update((int)$userId, ['avatar' => $uploadResult['path']]);
                return Response::json([
                    'message' => 'Avatar updated successfully',
                    'path' => $uploadResult['path']
                ], 200);
            }

            return Response::json(['message' => $uploadResult['error']], 400);
            
        } catch (Exception $e) {
            return Response::json(['message' => 'An error occurred: ' . $e->getMessage()], 500);
        }
    }

    public function deleteUser($userId) {
        try {
            // ໃຊ້ shortcut ທີ່ເພີ່ມໃໝ່
            AuthMiddleware::checkAdmin();

            // ກວດສອບ $userId
            if (empty($userId) || !is_numeric($userId)) {
                return Response::json(['message' => 'Invalid user ID'], 400);
            }

            // ກວດສອບວ່າຜູ້ໃຊ້ມີຢູ່ຈິງຫຼືບໍ່
            $user = $this->userModel->findById((int)$userId);
            if (!$user) {
                return Response::json(['message' => 'User not found'], 404);
            }

            // ຫ້າມລຶບຕົວເອງ
            $currentUser = AuthMiddleware::getCurrentUser();
            if ($currentUser && isset($currentUser['id']) && $currentUser['id'] == $userId) {
                return Response::json(['message' => 'Cannot delete your own account'], 403);
            }

            // ຫ້າມລຶບ Admin ຄົນສຸດທ້າຍ
            if ($user['role'] === 'admin') {
                $adminCount = $this->userModel->countAdmins();
                if ($adminCount <= 1) {
                    return Response::json(['message' => 'Cannot delete the last admin user'], 403);
                }
            }

            if ($this->userModel->delete((int)$userId)) {
                return Response::json(['message' => 'User deleted successfully'], 200);
            } else {
                return Response::json(['message' => 'Failed to delete user'], 400);
            }
        } catch (Exception $e) {
            return Response::json(['message' => 'An error occurred: ' . $e->getMessage()], 500);
        }
    }

    // ດຶງລາຍຊື່ຜູ້ໃຊ້ທັງໝົດ (ສຳລັບ Admin)
    public function listUsers($page = 1, $limit = 10) {
        try {
            AuthMiddleware::checkAdmin();

            $offset = ($page - 1) * $limit;
            $users = $this->userModel->getAll($limit, $offset);
            $total = $this->userModel->count();

            // ກຳຈັດຂໍ້ມູນທີ່ອ່ອນໄຫວ
            foreach ($users as &$user) {
                unset($user['password'], $user['password_hash']);
            }

            return Response::json([
                'data' => $users,
                'pagination' => [
                    'current_page' => (int)$page,
                    'per_page' => (int)$limit,
                    'total' => (int)$total,
                    'total_pages' => ceil($total / $limit)
                ]
            ], 200);
        } catch (Exception $e) {
            return Response::json(['message' => 'An error occurred: ' . $e->getMessage()], 500);
        }
    }

    /**
     * ປ່ຽນລະຫັດຜ່ານ
     * POST /user/change-password
     */
    public function changePassword() {
        try {
            // ກວດສອບສິດການເຂົ້າເຖິງ
            $user = AuthMiddleware::check();
                if (!$user) {
                    return Response::json(['message' => 'Authentication required'], 401);
                }

                // ປັບປຸງການຮັບຂໍ້ມູນ
                $input = file_get_contents('php://input');
                if (empty($input)) {
                    return Response::json(['message' => 'Empty request body'], 400);
                }

                $data = json_decode($input, true);
                
                // ກວດສອບຄວາມຖືກຕ້ອງຂອງ JSON
                if (json_last_error() !== JSON_ERROR_NONE) {
                    return Response::json(['message' => 'Invalid JSON input: ' . json_last_error_msg()], 400);
                }
            
            // Validation ຂໍ້ມູນ
            $rules = [
                'current_password' => 'required|string|min:6',
                'new_password' => 'required|string|min:8|max:255',
                'new_password_confirmation' => 'required|string|same:new_password'
            ];
            
            // ກວດສອບວ່າ Validator ມີ method validate ບໍ່
            if (method_exists('Validator', 'validate')) {
                $errors = Validator::validate($data, $rules);
            } else {
                // ຖ້າບໍ່ມີ Validator, ກວດສອບແບບງ່າຍໆ
                $errors = [];
                if (empty($data['current_password'])) {
                    $errors['current_password'][] = 'Current password is required';
                }
                if (strlen($data['new_password'] ?? '') < 8) {
                    $errors['new_password'][] = 'New password must be at least 8 characters';
                }
                if (($data['new_password'] ?? '') !== ($data['new_password_confirmation'] ?? '')) {
                    $errors['new_password_confirmation'][] = 'Password confirmation does not match';
                }
            }
            
            if (!empty($errors)) {
                return Response::json(['errors' => $errors], 422);
            }
            
            // ດຶງຂໍ້ມູນຜູ້ໃຊ້ປັດຈຸບັນ (ລວມລະຫັດຜ່ານ)
            $currentUser = $this->userModel->findById($user['id'], true);
            
            if (!$currentUser) {
                return Response::json(['message' => 'User not found'], 404);
            }
            
            // ກວດສອບລະຫັດຜ່ານປັດຈຸບັນ
            $passwordField = isset($currentUser['password_hash']) ? 'password_hash' : 'password';
            
            if (!isset($currentUser[$passwordField]) || !password_verify($data['current_password'], $currentUser[$passwordField])) {
                return Response::json(['message' => 'Current password is incorrect'], 401);
            }
            
            // ອັບເດດລະຫັດຜ່ານໃໝ່
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

    /**
     * ດຶງຂໍ້ມູນຜູ້ໃຊ້ສຳລັບ dropdown
     * GET /users/dropdown
     */
    public function getUserDropdown() {
        try {
            // ກວດສອບສິດການເຂົ້າເຖິງ (ຕ້ອງເປັນ Admin)
            AuthMiddleware::checkAdmin();
            
            // ດຶງຂໍ້ມູນຜູ້ໃຊ້ທັງໝົດ
            $users = $this->userModel->getAll();
            
            // ປັບຮູບແບບຂໍ້ມູນສຳລັບ dropdown
            $dropdown = array_map(function($user) {
                return [
                    'id' => $user['id'],
                    'name' => $user['full_name'] ?? $user['username'],
                    'username' => $user['username'],
                    'email' => $user['email'],
                    'role' => $user['role'] ?? 'user'
                ];
            }, $users);
            
            return Response::json([
                'success' => true,
                'data' => $dropdown,
                'total' => count($dropdown)
            ], 200);
            
        } catch (Exception $e) {
            return Response::json([
                'success' => false,
                'message' => 'An error occurred: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * ຣີເຊັດລະຫັດຜ່ານຂອງຜູ້ໃຊ້ງານອື່ນ (ສຳລັບ Admin)
     * POST /users/{id}/reset-password
     */
    public function resetPasswordForUser($userId) {
        try {
            // ກວດສອບສິດການເຂົ້າເຖິງ (ຕ້ອງເປັນ Admin)
            AuthMiddleware::checkAdmin();

            if (empty($userId) || !is_numeric($userId)) {
                return Response::json(['message' => 'Invalid user ID'], 400);
            }

            $data = json_decode(file_get_contents('php://input'), true);
            if (empty($data['password']) || strlen($data['password']) < 8) {
                return Response::json(['message' => 'Password must be at least 8 characters'], 400);
            }

            $user = $this->userModel->findById((int)$userId);
            if (!$user) {
                return Response::json(['message' => 'User not found'], 404);
            }

            $updated = $this->userModel->update((int)$userId, [
                'password' => $data['password']
            ]);

            if ($updated) {
                return Response::json(['message' => 'User password reset successfully'], 200);
            }

            return Response::json(['message' => 'Failed to reset password'], 400);
        } catch (Exception $e) {
            return Response::json(['message' => 'An error occurred: ' . $e->getMessage()], 500);
        }
    }
}