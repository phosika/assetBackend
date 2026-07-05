<?php
// generate_jwt_secret.php - ສ້າງ Secret Key ແບບສຸ່ມ

// ສ້າງ secret key ທີ່ປອດໄພ
$secretKey = bin2hex(random_bytes(32)); // 64 ຕົວອັກສອນ

echo "JWT Secret Key:\n";
echo "================\n\n";
echo $secretKey . "\n\n";
echo "================\n";
echo "ຄັດລອກ Key ນີ້ໄປໃສ່ໃນ src/config/jwt.php\n";
?>