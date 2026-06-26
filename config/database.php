<?php

namespace Config;

class Database {
    private $host;
    private $db_name;
    private $username;
    private $password;
    private $port;
    private $conn;

    public function __construct() {
        // Load environment variables from .env file
        $this->host = $_ENV['DB_HOST'] ?? getenv('DB_HOST') ?? $_ENV['MYSQLHOST'] ?? getenv('MYSQLHOST') ?? 'localhost';
        $this->port = $_ENV['DB_PORT'] ?? getenv('DB_PORT') ?? $_ENV['MYSQLPORT'] ?? getenv('MYSQLPORT') ?? '3306';
        $this->db_name = $_ENV['DB_NAME'] ?? getenv('DB_NAME') ?? $_ENV['MYSQLDATABASE'] ?? getenv('MYSQLDATABASE') ?? 'barbing_db';
        $this->username = $_ENV['DB_USER'] ?? getenv('DB_USER') ?? $_ENV['MYSQLUSER'] ?? getenv('MYSQLUSER') ?? 'root';
        $this->password = $_ENV['DB_PASSWORD'] ?? getenv('DB_PASSWORD') ?? $_ENV['MYSQLPASSWORD'] ?? getenv('MYSQLPASSWORD') ?? '';
    }

    public function getConnection() {
        $this->conn = null;

        try {
            $this->conn = new \PDO(
                "mysql:host=" . $this->host . ";port=" . $this->port . ";dbname=" . $this->db_name,
                $this->username,
                $this->password
            );
            // Set PDO to throw exceptions on errors
            $this->conn->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            // Use UTF-8 for character set
            $this->conn->exec("set names utf8");
        } catch(\PDOException $exception) {
            error_log("Connection error: " . $exception->getMessage());
            echo "Connection error: " . $exception->getMessage();
        }

        return $this->conn;
    }
}