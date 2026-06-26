<?php

namespace App\Models;

class Service extends Database {
    protected $table = 'services';
    
    public function __construct() {
        parent::__construct();
        $this->table = 'services';
    }
    
    // Get all active services
    public function getAllActive() {
        $sql = "SELECT * FROM {$this->table} WHERE is_active = 1 ORDER BY name";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    // Get service by ID (check if active)
    public function getActiveById($id) {
        $sql = "SELECT * FROM {$this->table} WHERE id = :id AND is_active = 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute(['id' => $id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }
}