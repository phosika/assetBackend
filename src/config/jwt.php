<?php
// src/config/jwt.php
// Load .env values using the shared config loader
require_once __DIR__ . '/config.php';

$jwtSecret = Config::get('JWT_SECRET');
if (!$jwtSecret) {
    die('JWT_SECRET not configured in .env file. Please set a secure JWT secret key.');
}

return [
    'secret_key' => 'your-super-secret-key-change-this-in-production',
    'algorithm' => 'HS256',
    'expiry' => 3600, // 1 ຊົ່ວໂມງ
    'refresh_expiry' => 86400, // 24 ຊົ່ວໂມງ
];
?>