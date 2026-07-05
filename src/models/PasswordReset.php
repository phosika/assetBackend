<?php
// src/models/PasswordReset.php

class PasswordReset
{
    private $db;
    private $table = 'password_resets';

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * ສ້າງ reset token ໃໝ່
     */
    public function create($email, $token, $expiresIn = 3600)
    {
        try {
            $expiresAt = date('Y-m-d H:i:s', time() + $expiresIn);
            $sql = "INSERT INTO {$this->table} (email, token, expires_at) VALUES (:email, :token, :expires_at)";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':token', $token);
            $stmt->bindParam(':expires_at', $expiresAt);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("PasswordReset create error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * ກວດສອບ token ວ່າຖືກຕ້ອງ ແລະ ຍັງບໍ່ໝົດອາຍຸ
     */
    public function verify($token)
    {
        try {
            $sql = "SELECT * FROM {$this->table} 
                    WHERE token = :token 
                    AND used = 0 
                    AND expires_at > NOW() 
                    LIMIT 1";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':token', $token);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("PasswordReset verify error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * ໝາຍວ່າ token ຖືກໃຊ້ແລ້ວ
     */
    public function markAsUsed($id)
    {
        try {
            $sql = "UPDATE {$this->table} SET used = 1 WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':id', $id);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("PasswordReset markAsUsed error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * ລຶບ tokens ເກົ່າທີ່ໝົດອາຍຸ
     */
    public function cleanExpired()
    {
        try {
            $sql = "DELETE FROM {$this->table} WHERE expires_at < NOW()";
            return $this->db->exec($sql);
        } catch (PDOException $e) {
            error_log("PasswordReset cleanExpired error: " . $e->getMessage());
            return false;
        }
    }
}