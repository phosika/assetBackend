<?php
// /home/phosika/Fixasset/assetapplication/BACKEND/public/test-connection.php

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "========== TESTING SERVER CONNECTION ==========\n\n";

// ລາຍຊື່ URLs ທີ່ຈະທົດສອບ
$urls = [
    'http://localhost:8080/',
    'http://127.0.0.1:8080/',
    'http://localhost/',
    'http://127.0.0.1/',
];

foreach ($urls as $index => $url) {
    echo ($index + 1) . ". Testing: $url\n";
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_HEADER, true);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    
    curl_close($ch);
    
    echo "   HTTP Code: $httpCode\n";
    if ($error) {
        echo "   Error: $error\n";
    }
    if ($response) {
        echo "   Response: " . substr($response, 0, 100) . "...\n";
    }
    echo "\n";
}

// ທົດສອບ test-server.php
echo "5. Testing test-server.php:\n";
$ch = curl_init('http://localhost:8080/test-server.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "   HTTP Code: $httpCode\n";
echo "   Response: $response\n";
?>