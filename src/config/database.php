<?php
// src/config/database.php

class Database {
    private static $instance = null;
    private $conn;

    private function __construct() {
        try {
            $host = getenv('DB_HOST') ?: 'db';
            $dbname = getenv('DB_NAME') ?: 'asset_db';
            $user = getenv('DB_USER') ?: 'root';
            $pass = getenv('DB_PASSWORD') ?: 'My_root_passw0rd@!2o26';

            // $host = getenv('DB_HOST');
            // $dbname = getenv('DB_NAME');
            // $user = getenv('DB_USER');
            // $pass = getenv('DB_PASSWORD');


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

        // src/config/database.php
    // ເພີ່ມຟັງຊັນນີ້ເຂົ້າໄປໃນ Database class

    public function beginTransaction() {
        try {
            return $this->conn->beginTransaction();
        } catch (Exception $e) {
            error_log("Begin transaction error: " . $e->getMessage());
            throw $e;
        }
    }

    public function commit() {
        try {
            return $this->conn->commit();
        } catch (Exception $e) {
            error_log("Commit transaction error: " . $e->getMessage());
            throw $e;
        }
    }

    public function rollBack() {
        try {
            return $this->conn->rollBack();
        } catch (Exception $e) {
            error_log("Rollback transaction error: " . $e->getMessage());
            throw $e;
        }
    }

    public function inTransaction() {
        try {
            return $this->conn->inTransaction();
        } catch (Exception $e) {
            error_log("Check transaction error: " . $e->getMessage());
            return false;
        }
    }
}

?>