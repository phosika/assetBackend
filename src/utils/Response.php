<?php
// src/utils/Response.php

class Response {
    /**
     * ສົ່ງ JSON Response
     */
    public static function json($data, $statusCode = 200) {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        
        // ຖ້າ $data ມີຮູບແບບຢູ່ແລ້ວ
        if (isset($data['status']) && isset($data['data'])) {
            echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            exit;
        }
        
        // ຫໍ່ຂໍ້ມູນ
        $response = [
            'status' => $statusCode >= 200 && $statusCode < 300 ? 'success' : 'error',
            'data' => $data,
            'message' => $data['message'] ?? '',
            'timestamp' => time()
        ];
        
        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
    
    /**
     * ສົ່ງຜົນສຳເລັດ
     */
    public static function success($data, $statusCode = 200, $message = '') {
        $response = [
            'status' => 'success',
            'data' => $data,
            'message' => $message,
            'timestamp' => time()
        ];
        
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
    
    /**
     * ສົ່ງຂໍ້ຜິດພາດ
     */
    public static function error($message, $statusCode = 400, $data = null) {
        $response = [
            'status' => 'error',
            'message' => $message,
            'timestamp' => time()
        ];
        
        if ($data !== null) {
            $response['data'] = $data;
        }
        
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
    
    /**
     * ສົ່ງ 404 Not Found
     */
    public static function notFound($message = 'Resource not found') {
        self::error($message, 404);
    }
    
    /**
     * ສົ່ງ 401 Unauthorized
     */
    public static function unauthorized($message = 'Unauthorized') {
        self::error($message, 401);
    }
    
    /**
     * ສົ່ງ 403 Forbidden
     */
    public static function forbidden($message = 'Forbidden') {
        self::error($message, 403);
    }
}
?>