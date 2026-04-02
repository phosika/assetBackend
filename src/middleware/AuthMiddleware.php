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
            if (is_array($requiredRole)) {
                if (!in_array($payload['role'], $requiredRole)) {
                    Response::forbidden('Insufficient permissions');
                }
            } else {
                if ($payload['role'] !== $requiredRole) {
                    Response::forbidden('Insufficient permissions');
                }
            }
        }

        return $payload;
    }


    

}
?>