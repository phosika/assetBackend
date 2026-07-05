<?php
// src/middleware/AuthMiddleware.php

require_once __DIR__ . '/../utils/JWT.php';
require_once __DIR__ . '/../utils/Response.php';

class AuthMiddleware {
    
    private static $currentUser = null;
    
    public static function authenticate($requiredRole = null) {
        try {
            $headers = getallheaders();
            
            $authHeader = null;
            foreach ($headers as $key => $value) {
                if (strtolower($key) === 'authorization') {
                    $authHeader = $value;
                    break;
                }
            }
            
            if (!$authHeader) {
                Response::json(['message' => 'No authorization token provided'], 401);
                return false;
            }

            if (strpos($authHeader, 'Bearer ') !== 0) {
                Response::json(['message' => 'Invalid authorization format. Use Bearer token'], 401);
                return false;
            }

            $token = substr($authHeader, 7);
            
            if (empty($token)) {
                Response::json(['message' => 'Token is empty'], 401);
                return false;
            }

            // ກວດສອບ token
            $payload = JWT::validate($token);
            
            // Log ສຳລັບ debugging
            error_log("AuthMiddleware: JWT payload: " . print_r($payload, true));
            
            if (!$payload) {
                Response::json(['message' => 'Invalid or expired token'], 401);
                return false;
            }

            // ກວດສອບວ່າ payload ມີ id
            if (!isset($payload['id']) || empty($payload['id'])) {
                error_log("AuthMiddleware: Token missing ID - payload: " . print_r($payload, true));
                Response::json(['message' => 'Invalid token: missing user ID'], 401);
                return false;
            }

            // ສ້າງຂໍ້ມູນຜູ້ໃຊ້
            $userData = [
                'id' => (int)$payload['id'], // ແນ່ໃຈວ່າເປັນ integer
                'username' => $payload['username'] ?? '',
                'email' => $payload['email'] ?? '',
                'role' => $payload['role'] ?? 'user'
            ];

            error_log("AuthMiddleware: User authenticated - ID: {$userData['id']}, Role: {$userData['role']}");

            self::$currentUser = $userData;

            // ກວດສອບ Role
            if ($requiredRole) {
                if (is_array($requiredRole)) {
                    if (!in_array($userData['role'], $requiredRole)) {
                        Response::json(['message' => 'Insufficient permissions. Required roles: ' . implode(', ', $requiredRole)], 403);
                        return false;
                    }
                } else {
                    if ($userData['role'] !== $requiredRole) {
                        Response::json(['message' => 'Insufficient permissions. Required role: ' . $requiredRole], 403);
                        return false;
                    }
                }
            }
            
            return $userData;
            
        } catch (Exception $e) {
            error_log("AuthMiddleware error: " . $e->getMessage());
            Response::json(['message' => 'Authentication error: ' . $e->getMessage()], 500);
            return false;
        }
    }

    public static function getCurrentUser() {
        return self::$currentUser;
    }

    public static function isOwner($userId) {
        $currentUser = self::getCurrentUser();
        if (!$currentUser) {
            return false;
        }
        return isset($currentUser['id']) && (int)$currentUser['id'] === (int)$userId;
    }

    public static function isAdmin() {
        $currentUser = self::getCurrentUser();
        if (!$currentUser) {
            return false;
        }
        return isset($currentUser['role']) && $currentUser['role'] === 'admin';
    }

    public static function checkAdmin() {
        return self::authenticate('admin');
    }

    public static function check() {
        return self::authenticate();
    }
}
?>