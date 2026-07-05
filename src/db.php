<?php
// src/db.php
class Database {
    private static $instance = null;
    private $conn;

    private function __construct() {
        try {
            $host = getenv('DB_HOST') ?: 'db';
            $dbname = getenv('DB_NAME') ?: 'suvinhome_db';
            $user = getenv('DB_USER') ?: 'root';
            $pass = getenv('DB_PASSWORD') ?: 'My_root_passw0rd@!2o26';

            $this->conn = new PDO(
                "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
                $user,
                $pass,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch (PDOException $e) {
            die("Connection failed: " . $e->getMessage());
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance->conn;
    }
}
?>
