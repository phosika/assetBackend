<?php
// src/utils/JWT.php
class JWT {
    private static $secretKey;
    private static $algorithm = 'HS256';
    private static $defaultExpiry = 3600; // 1 ຊົ່ວໂມງ

    public static function init() {
        // ກວດສອບວ່າ config file ມີຢູ່ບໍ່
        $configPath = __DIR__ . '/../config/jwt.php';
        if (file_exists($configPath)) {
            $config = include $configPath;
            self::$secretKey = $config['secret_key'] ?? 'your-secret-key-change-this';
        } else {
            // ໃຊ້ default secret key ຖ້າບໍ່ມີ config
            self::$secretKey = 'your-secret-key-change-this-in-production';
        }
    }

    // Method ຫຼັກສຳລັບສ້າງ token (ໃຊ້ຊື່ create ເພື່ອໃຫ້ກົງກັບ AuthController)
    public static function create($payload, $expiry = null) {
        self::init();
        
        if ($expiry === null) {
            $expiry = self::$defaultExpiry;
        }
        
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

    // ຮອງຮັບຊື່ເກົ່າ generate ເພື່ອຄວາມເຂົ້າກັນໄດ້
    public static function generate($payload, $expiry = 3600) {
        return self::create($payload, $expiry);
    }

    public static function validate($token) {
        self::init();
        
        if (empty($token)) {
            return false;
        }
        
        $parts = explode('.', $token);
        if (count($parts) != 3) {
            return false;
        }

        // ກວດສອບ payload
        $payloadJson = base64_decode(str_replace(['-', '_'], ['+', '/'], $parts[1]));
        if ($payloadJson === false) {
            return false;
        }
        
        $payload = json_decode($payloadJson, true);
        if ($payload === null) {
            return false;
        }
        
        // ກວດສອບ expiration
        if (!isset($payload['exp']) || $payload['exp'] < time()) {
            return false;
        }

        // ກວດສອບ signature
        $signature = hash_hmac('sha256', $parts[0] . "." . $parts[1], self::$secretKey, true);
        $base64UrlSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
        
        if (!hash_equals($base64UrlSignature, $parts[2])) {
            return false;
        }
        
        // ກຳຈັດຂໍ້ມູນທີ່ບໍ່ຕ້ອງການອອກ
        unset($payload['iat'], $payload['exp'], $payload['jti']);
        
        return $payload;
    }

    // ດຶງເວລາໝົດອາຍຸຂອງ token
    public static function getExpiryTime() {
        return time() + self::$defaultExpiry;
    }

    // ດຶງຂໍ້ມູນຈາກ token ໂດຍບໍ່ຕ້ອງກວດສອບ signature (ສຳລັບ debugging)
    public static function decode($token) {
        $parts = explode('.', $token);
        if (count($parts) != 3) {
            return null;
        }
        
        $payloadJson = base64_decode(str_replace(['-', '_'], ['+', '/'], $parts[1]));
        return json_decode($payloadJson, true);
    }

    // ກວດສອບວ່າ token ໝົດອາຍຸຫຼືຍັງ
    public static function isExpired($token) {
        $payload = self::decode($token);
        if (!$payload || !isset($payload['exp'])) {
            return true;
        }
        return $payload['exp'] < time();
    }

    // ດຶງຂໍ້ມູນຜູ້ໃຊ້ຈາກ token
    public static function getUserData($token) {
        $payload = self::validate($token);
        if (!$payload) {
            return null;
        }
        return $payload;
    }
}
?>