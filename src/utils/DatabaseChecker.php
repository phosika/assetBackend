// src/utils/DatabaseChecker.php

<?php
class DatabaseChecker {
    public static function checkConnection($db) {
        try {
            // ກວດສອບການເຊື່ອມຕໍ່
            $stmt = $db->query("SELECT 1");
            return true;
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            return false;
        }
    }
    
    public static function checkTable($db, $table) {
        try {
            $stmt = $db->query("SHOW TABLES LIKE '$table'");
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log("Table check failed: " . $e->getMessage());
            return false;
        }
    }
}