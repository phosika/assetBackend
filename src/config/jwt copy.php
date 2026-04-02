<?php
// src/config/jwt.php
// Load .env values using the shared config loader
require_once __DIR__ . '/config.php';

return [
    'secret_key' => Config::get('JWT_SECRET', 'your-256-bit-secret-key-here-change-this-in-production'),
    'algorithm' => 'HS256',
    'access_token_expiry' => 3600, // 1 ຊົ່ວໂມງ (ວິນາທີ)
    'refresh_token_expiry' => 604800, // 7 ວັນ
    'issuer' => 'myapi.local',
    'audience' => 'myapi-client'
];
?>