<?php

namespace App\Models;

class Barber extends Database {
    protected $table = 'barbers';
    
    public function __construct() {
        parent::__construct();
        $this->table = 'barbers';
    }
    
    // Get all barbers with user details
    public function getAllWithDetails() {
        $sql = "SELECT b.*, u.name, u.email, u.phone, u.loyalty_points 
                FROM barbers b 
                JOIN users u ON b.user_id = u.id 
                WHERE b.is_active = 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    // Get single barber with details
    public function getBarberWithDetails($id) {
        $sql = "SELECT b.*, u.name, u.email, u.phone 
                FROM barbers b 
                JOIN users u ON b.user_id = u.id 
                WHERE b.user_id = :id AND b.is_active = 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute(['id' => $id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }
    
    // Check if barber is available at specific time
    public function checkAvailability($barber_id, $date, $time) {
        $sql = "SELECT COUNT(*) as count FROM appointments 
                WHERE barber_id = :barber_id 
                AND appointment_date = :date 
                AND appointment_time = :time 
                AND status NOT IN ('cancelled', 'completed')";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            'barber_id' => $barber_id,
            'date' => $date,
            'time' => $time
        ]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result['count'] == 0;
    }
}