<?php

function getAppointments() {
    try {
        $conn = new PDO("mysql:host=fdb1032.awardspace.net;dbname=4761973_barbing", "4761973_barbing", "Hello123###");
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Get token from header
        $headers = getallheaders();
        $token = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : null;
        
        if (!$token) {
            echo json_encode(['status' => 'error', 'message' => 'Please login']);
            return;
        }
        
        // Decode token
        $userData = json_decode(base64_decode($token), true);
        $user_id = $userData['user_id'] ?? null;
        $user_role = $userData['role'] ?? null;
        
        if (!$user_id) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid token']);
            return;
        }
        
        // Get appointments based on role
        if ($user_role == 'admin') {
            $stmt = $conn->prepare("
                SELECT a.*, u.name as customer_name, b.name as barber_name, s.name as service_name
                FROM appointments a
                JOIN users u ON a.customer_id = u.id
                JOIN users b ON a.barber_id = b.id
                JOIN services s ON a.service_id = s.id
                ORDER BY a.appointment_date DESC
            ");
            $stmt->execute();
        } elseif ($user_role == 'barber') {
            $stmt = $conn->prepare("
                SELECT a.*, u.name as customer_name, s.name as service_name
                FROM appointments a
                JOIN users u ON a.customer_id = u.id
                JOIN services s ON a.service_id = s.id
                WHERE a.barber_id = ?
                ORDER BY a.appointment_date DESC
            ");
            $stmt->execute([$user_id]);
        } else {
            $stmt = $conn->prepare("
                SELECT a.*, b.name as barber_name, s.name as service_name
                FROM appointments a
                JOIN users b ON a.barber_id = b.id
                JOIN services s ON a.service_id = s.id
                WHERE a.customer_id = ?
                ORDER BY a.appointment_date DESC
            ");
            $stmt->execute([$user_id]);
        }
        
        $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Appointments retrieved',
            'data' => $appointments
        ]);
    } catch(PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}

function createAppointment($data) {
    try {
        $conn = new PDO("mysql:host=fdb1032.awardspace.net;dbname=4761973_barbing", "4761973_barbing", "Hello123###");
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Get service price
        $stmt = $conn->prepare("SELECT price FROM services WHERE id = ?");
        $stmt->execute([$data['service_id']]);
        $service = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$service) {
            echo json_encode(['status' => 'error', 'message' => 'Service not found']);
            return;
        }
        
        // Check if slot is available
        $check = $conn->prepare("
            SELECT COUNT(*) as count FROM appointments 
            WHERE barber_id = ? AND appointment_date = ? AND appointment_time = ?
            AND status NOT IN ('cancelled')
        ");
        $check->execute([
            $data['barber_id'],
            $data['appointment_date'],
            $data['appointment_time']
        ]);
        $result = $check->fetch(PDO::FETCH_ASSOC);
        
        if ($result['count'] > 0) {
            echo json_encode(['status' => 'error', 'message' => 'Time slot not available']);
            return;
        }
        
        // Create appointment
        $stmt = $conn->prepare("
            INSERT INTO appointments (customer_id, barber_id, service_id, appointment_date, appointment_time, total_price, notes, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')
        ");
        
        $stmt->execute([
            $data['customer_id'],
            $data['barber_id'],
            $data['service_id'],
            $data['appointment_date'],
            $data['appointment_time'],
            $service['price'],
            $data['notes'] ?? null
        ]);
        
        $appointment_id = $conn->lastInsertId();
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Appointment booked successfully',
            'data' => ['id' => $appointment_id]
        ]);
    } catch(PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}