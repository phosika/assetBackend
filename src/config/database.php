<?php
// src/config/database.php

class Database {
    private static $instance = null;
    private $conn;

    private function __construct() {
        try {
            // ໂຫຼດ .env file ຖ້າມີ (Load .env file if exists)
            $envPath = dirname(dirname(__DIR__)) . '/.env';
            if (file_exists($envPath)) {
                $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                foreach ($lines as $line) {
                    if (strpos(trim($line), '#') === 0) continue;
                    $parts = explode('=', $line, 2);
                    if (count($parts) === 2) {
                        $name = trim($parts[0]);
                        $value = trim($parts[1]);
                        $value = trim($value, "\"'");
                        $_ENV[$name] = $value;
                        $_SERVER[$name] = $value;
                        if (function_exists('putenv')) {
                            @putenv("{$name}={$value}");
                        }
                    }
                }
            }

            // ໃຊ້ environment variables ຫຼື default values (Use $_ENV/$_SERVER first, fallback to getenv/defaults)
            $host = $_ENV['DB_HOST'] ?? $_SERVER['DB_HOST'] ?? (getenv('DB_HOST') ?: 'localhost');
            $dbname = $_ENV['DB_NAME'] ?? $_SERVER['DB_NAME'] ?? (getenv('DB_NAME') ?: 'suvinhome_db');
            $user = $_ENV['DB_USER'] ?? $_SERVER['DB_USER'] ?? (getenv('DB_USER') ?: 'root');
            $pass = $_ENV['DB_PASSWORD'] ?? $_SERVER['DB_PASSWORD'] ?? (getenv('DB_PASSWORD') ?: '');

            // ສຳລັບ Docker ຫຼື production
            // $host = getenv('DB_HOST') ?: 'db';
            // $dbname = getenv('DB_NAME') ?: 'suvinhome_db';
            // $user = getenv('DB_USER') ?: 'root';
            // $pass = getenv('DB_PASSWORD') ?: 'My_root_passw0rd@!2o26';

            $this->conn = new PDO(
                "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
                $user,
                $pass,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
                ]
            );
        } catch (PDOException $e) {
            // ບໍ່ຄວນໃຊ້ die() ໃນ production, ໃຊ້ error_log ແທນ
            error_log("Database connection failed: " . $e->getMessage());
            throw new Exception("Database connection failed: " . $e->getMessage());
        }
    }

    // ດຶງ instance ຂອງ Database
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    // ດຶງ PDO connection
    public static function getConnection() {
        return self::getInstance()->conn;
    }

    // ເລີ່ມ Transaction
    public function beginTransaction() {
        try {
            return $this->conn->beginTransaction();
        } catch (Exception $e) {
            error_log("Begin transaction error: " . $e->getMessage());
            throw $e;
        }
    }

    // ຢືນຢັນ Transaction
    public function commit() {
        try {
            return $this->conn->commit();
        } catch (Exception $e) {
            error_log("Commit transaction error: " . $e->getMessage());
            throw $e;
        }
    }

    // ຍົກເລີກ Transaction
    public function rollBack() {
        try {
            return $this->conn->rollBack();
        } catch (Exception $e) {
            error_log("Rollback transaction error: " . $e->getMessage());
            throw $e;
        }
    }

    // ກວດສອບວ່າມີ Transaction ທີ່ກຳລັງເຮັດວຽກຢູ່ບໍ່
    public function inTransaction() {
        try {
            return $this->conn->inTransaction();
        } catch (Exception $e) {
            error_log("Check transaction error: " . $e->getMessage());
            return false;
        }
    }

    // ປິດການເຊື່ອມຕໍ່
    public function close() {
        $this->conn = null;
        self::$instance = null;
    }

    // ກວດສອບການເຊື່ອມຕໍ່
    public function isConnected() {
        try {
            $this->conn->query("SELECT 1");
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    // ດຶງຂໍ້ມູນສະຖານະການເຊື່ອມຕໍ່
    public function getConnectionInfo() {
        try {
            return [
                'status' => $this->isConnected() ? 'connected' : 'disconnected',
                'driver' => $this->conn->getAttribute(PDO::ATTR_DRIVER_NAME),
                'server_version' => $this->conn->getAttribute(PDO::ATTR_SERVER_VERSION),
                'client_version' => $this->conn->getAttribute(PDO::ATTR_CLIENT_VERSION),
                'connection_status' => $this->conn->getAttribute(PDO::ATTR_CONNECTION_STATUS)
            ];
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }
}

// ຟັງຊັນຊ່ວຍເຫຼືອສຳລັບການໃຊ້ງານງ່າຍໆ
function getDBConnection() {
    return Database::getConnection();
}

function db_begin_transaction() {
    return Database::getInstance()->beginTransaction();
}

function db_commit() {
    return Database::getInstance()->commit();
}

function db_rollback() {
    return Database::getInstance()->rollBack();
}

function db_in_transaction() {
    return Database::getInstance()->inTransaction();
}
?>