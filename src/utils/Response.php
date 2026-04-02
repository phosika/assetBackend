<?php
// src/utils/Response.php
class Response {
    public static function json($data, $status = 200, $message = '') {
        http_response_code($status);
        header('Content-Type: application/json');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');
        
        $response = [
            'status' => $status >= 200 && $status < 300 ? 'success' : 'error',
            'data' => $data,
            'message' => $message,
            'timestamp' => time()
        ];
        
        echo json_encode($response);
        exit;
    }

    public static function success($data = null, $message = 'Success') {
        self::json($data, 200, $message);
    }

    public static function error($message = 'Error', $status = 400, $data = null) {
        self::json($data, $status, $message);
    }

    public static function unauthorized($message = 'Unauthorized') {
        self::json(null, 401, $message);
    }

    public static function forbidden($message = 'Forbidden') {
        self::json(null, 403, $message);
    }

    public static function notFound($message = 'Resource not found') {
        self::json(null, 404, $message);
    }
}
?>