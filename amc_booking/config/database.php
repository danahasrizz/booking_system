<?php
/**
 * Database Connection
 * Uses PDO with prepared statements to prevent SQL injection
 */

class Database {
    private $host = 'localhost';
    private $db_name = 'amc_booking';
    private $username = 'root';
    private $password = '';
    private $conn;

    public function getConnection() {
        $this->conn = null;
        
        try {
            $dsn = "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4";
            
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ];
            
            $this->conn = new PDO($dsn, $this->username, $this->password, $options);
            
        } catch(PDOException $e) {
            error_log("Database Error: " . $e->getMessage());
            die("Connection failed. Please try again later.");
        }
        
        return $this->conn;
    }
}