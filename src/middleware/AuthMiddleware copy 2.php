<?php
// src/middleware/AuthMiddleware.php
require_once __DIR__ . '/../utils/JWT.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../utils/Response.php';

class AuthMiddleware {
    public static function authenticate($requiredRole = null) {
        // ໄດ້ຮັບ token ຈາກ headers
        $headers = getallheaders();
        
        if (!isset($headers['Authorization'])) {
            Response::unauthorized('No authorization token provided');
        }

        $authHeader = $headers['Authorization'];
        $token = str_replace('Bearer ', '', $authHeader);

        // ກວດສອບ token
        $payload = JWT::validate($token);
        
        if (!$payload) {
            Response::unauthorized('Invalid or expired token');
        }

        // ກວດສອບບົດບາດ (Role-based access control)
        if ($requiredRole) {
            $userModel = new User();
            $user = $userModel->getById($payload['user_id']);
            
            if (!$user) {
                Response::unauthorized('User not found');
            }

            if ($requiredRole === 'super-admin' && $user['role'] !== 'asset-admin') {
                Response::forbidden('Admin access required');
            }

            if ($requiredRole === 'asset-admin' && !in_array($user['role'], ['super-admin', 'asset-admin'])) {
                Response::forbidden('Manager access required');
            }
        }

        return $payload['user_id'];
    }


    

}
?>