<?php

namespace App\Models;

class Appointment extends Database {
    protected $table = 'appointments';
    
    public function __construct() {
        parent::__construct();
        $this->table = 'appointments';
    }
    
    // Get all appointments with customer, barber, and service details
    public function getAllWithDetails() {
        $sql = "SELECT a.*, 
                c.name as customer_name, c.email as customer_email, c.phone as customer_phone,
                b.name as barber_name, b.email as barber_email,
                s.name as service_name, s.duration_minutes
                FROM appointments a
                JOIN users c ON a.customer_id = c.id
                JOIN users b ON a.barber_id = b.id
                JOIN services s ON a.service_id = s.id
                ORDER BY a.appointment_date DESC, a.appointment_time DESC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    // Get appointments by customer
    public function getByCustomer($customer_id) {
        $sql = "SELECT a.*, 
                b.name as barber_name,
                s.name as service_name, s.duration_minutes, s.price
                FROM appointments a
                JOIN users b ON a.barber_id = b.id
                JOIN services s ON a.service_id = s.id
                WHERE a.customer_id = :customer_id
                ORDER BY a.appointment_date DESC, a.appointment_time DESC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute(['customer_id' => $customer_id]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    // Get appointments by barber
    public function getByBarber($barber_id) {
        $sql = "SELECT a.*, 
                c.name as customer_name, c.phone as customer_phone,
                s.name as service_name, s.duration_minutes
                FROM appointments a
                JOIN users c ON a.customer_id = c.id
                JOIN services s ON a.service_id = s.id
                WHERE a.barber_id = :barber_id
                ORDER BY a.appointment_date DESC, a.appointment_time DESC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute(['barber_id' => $barber_id]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    // Get single appointment with all details
    public function getWithDetails($id) {
        $sql = "SELECT a.*, 
                c.name as customer_name, c.email as customer_email, c.phone as customer_phone,
                b.name as barber_name, b.email as barber_email,
                s.name as service_name, s.duration_minutes, s.price
                FROM appointments a
                JOIN users c ON a.customer_id = c.id
                JOIN users b ON a.barber_id = b.id
                JOIN services s ON a.service_id = s.id
                WHERE a.id = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute(['id' => $id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }
}