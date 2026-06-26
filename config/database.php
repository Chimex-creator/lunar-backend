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
        $this->host = $_ENV['DB_HOST'] ?? getenv('DB_HOST') ?? $_ENV['MYSQLHOST'] ?? getenv('MYSQLHOST') ?? $_ENV['MYSQL_HOST'] ?? getenv('MYSQL_HOST') ?? 'localhost';
        $this->port = $_ENV['DB_PORT'] ?? getenv('DB_PORT') ?? $_ENV['MYSQLPORT'] ?? getenv('MYSQLPORT') ?? $_ENV['MYSQL_PORT'] ?? getenv('MYSQL_PORT') ?? '3306';
        $this->db_name = $_ENV['DB_NAME'] ?? getenv('DB_NAME') ?? $_ENV['MYSQLDATABASE'] ?? getenv('MYSQLDATABASE') ?? $_ENV['MYSQL_DATABASE'] ?? getenv('MYSQL_DATABASE') ?? 'barbing_db';
        $this->username = $_ENV['DB_USER'] ?? getenv('DB_USER') ?? $_ENV['MYSQLUSER'] ?? getenv('MYSQLUSER') ?? $_ENV['MYSQL_USER'] ?? getenv('MYSQL_USER') ?? 'root';
        $this->password = $_ENV['DB_PASSWORD'] ?? getenv('DB_PASSWORD') ?? $_ENV['MYSQLPASSWORD'] ?? getenv('MYSQLPASSWORD') ?? $_ENV['MYSQL_PASSWORD'] ?? getenv('MYSQL_PASSWORD') ?? '';
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