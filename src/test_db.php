<?php
require_once __DIR__ . '/config/database.php';

try {
    $db = Database::getInstance();
    echo "Connection successful\n";
    $stmt = $db->query("SHOW DATABASES");
    $databases = $stmt->fetchAll(PDO::FETCH_ASSOC);
    print_r($databases);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>