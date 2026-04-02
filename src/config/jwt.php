<?php
// src/config/jwt.php
// Load .env values using the shared config loader
require_once __DIR__ . '/config.php';

$jwtSecret = Config::get('JWT_SECRET');
if (!$jwtSecret) {
    die('JWT_SECRET not configured in .env file. Please set a secure JWT secret key.');
}

return [
    'secret_key' => $jwtSecret,
    'algorithm' => 'HS256',
    'access_token_expiry' => 86400, // 24 ຊົ່ວໂມງ (ວິນາທີ) - ເພີ່ມເວລາເພື່ອ debug
    'refresh_token_expiry' => 604800, // 7 ວັນ
    'issuer' => 'myapi.local',
    'audience' => 'myapi-client'
];
?>