<?php
// src/utils/JWT.php
class JWT {
    private static $secretKey;
    private static $algorithm = 'HS256';

    public static function init() {
        $config = include __DIR__ . '/../config/jwt.php';
        self::$secretKey = $config['secret_key'];
    }

    public static function generate($payload, $expiry = 3600) {
        self::init();
        
        $header = json_encode(['typ' => 'JWT', 'alg' => self::$algorithm]);
        $payload['iat'] = time(); // issued at
        $payload['exp'] = time() + $expiry; // expiration
        $payload['jti'] = bin2hex(random_bytes(16)); // unique token ID
        
        $base64UrlHeader = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
        $base64UrlPayload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode(json_encode($payload)));
        
        $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, self::$secretKey, true);
        $base64UrlSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
        
        return $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
    }

    public static function validate($token) {
        self::init();
        
        $parts = explode('.', $token);
        if (count($parts) != 3) {
            return false;
        }

        $payload = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $parts[1])), true);
        
        // ກວດສອບ expiration
        if ($payload['exp'] < time()) {
            return false;
        }

        // ກວດສອບ signature
        $signature = hash_hmac('sha256', $parts[0] . "." . $parts[1], self::$secretKey, true);
        $base64UrlSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
        
        return hash_equals($base64UrlSignature, $parts[2]) ? $payload : false;
    }
}
?>