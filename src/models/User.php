<?php

namespace App\Models;

class User extends Database {
    protected $table = 'users';
    
    public function __construct() {
        parent::__construct();
        $this->table = 'users';
    }
    
    // Find user by email
    public function findByEmail($email) {
        $sql = "SELECT * FROM {$this->table} WHERE email = :email";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute(['email' => $email]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }
    
    // Find user by ID
    public function findById($id) {
        $sql = "SELECT id, name, email, phone, role, loyalty_points, created_at 
                FROM {$this->table} WHERE id = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute(['id' => $id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }
    
    // Add user to barbers table
    public function addToBarbers($user_id) {
        $sql = "INSERT INTO barbers (user_id, bio, specialties, commission_rate) 
                VALUES (:user_id, '', '', 50.00)";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute(['user_id' => $user_id]);
    }
    
    // Update loyalty points
    public function addLoyaltyPoints($user_id, $points) {
        $sql = "UPDATE {$this->table} 
                SET loyalty_points = loyalty_points + :points 
                WHERE id = :user_id";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([
            'points' => $points,
            'user_id' => $user_id
        ]);
    }
}   