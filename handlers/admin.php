<?php

function getAdminStats() {
    try {
        $conn = new PDO("mysql:host=fdb1032.awardspace.net;dbname=4761973_barbing", "4761973_barbing", "Hello123###");
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $stmt = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'customer'");
        $totalUsers = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        $stmt = $conn->query("SELECT COUNT(*) as count FROM barbers WHERE is_active = 1");
        $totalBarbers = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        $stmt = $conn->query("SELECT COUNT(*) as count FROM services WHERE is_active = 1");
        $totalServices = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        $stmt = $conn->query("SELECT COUNT(*) as count FROM appointments");
        $totalAppointments = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        $stmt = $conn->query("SELECT SUM(total_price) as total FROM appointments WHERE status = 'completed'");
        $revenue = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
        
        echo json_encode([
            'status' => 'success',
            'data' => [
                'totalUsers' => (int)$totalUsers,
                'totalBarbers' => (int)$totalBarbers,
                'totalServices' => (int)$totalServices,
                'totalAppointments' => (int)$totalAppointments,
                'totalRevenue' => (float)$revenue
            ]
        ]);
    } catch(PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}

function getAllUsers() {
    try {
        $conn = new PDO("mysql:host=fdb1032.awardspace.net;dbname=4761973_barbing", "4761973_barbing", "Hello123###");
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $stmt = $conn->query("SELECT id, name, email, phone, role, loyalty_points, avatar, created_at FROM users ORDER BY created_at DESC");
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Add full avatar URL for each user
        foreach ($users as &$user) {
            if ($user['avatar']) {
                $user['avatar_url'] = 'http://localhost/php-backend/uploads/avatars/' . $user['avatar'];
            } else {
                $user['avatar_url'] = null;
            }
        }
        
        echo json_encode(['status' => 'success', 'data' => $users]);
    } catch(PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}

function deleteUser($id) {
    try {
        $conn = new PDO("mysql:host=fdb1032.awardspace.net;dbname=4761973_barbing", "4761973_barbing", "Hello123###");
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // First, check if user has any appointments
        $checkStmt = $conn->prepare("SELECT COUNT(*) as count FROM appointments WHERE customer_id = ? OR barber_id = ?");
        $checkStmt->execute([$id, $id]);
        $result = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['count'] > 0) {
            // Delete appointments first
            $delAppointments = $conn->prepare("DELETE FROM appointments WHERE customer_id = ? OR barber_id = ?");
            $delAppointments->execute([$id, $id]);
        }
        
        // Then delete the user
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role != 'admin'");
        $stmt->execute([$id]);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode(['status' => 'success', 'message' => 'User deleted successfully']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'User not found or cannot delete admin']);
        }
    } catch(PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}
//FUNTIONS FOR NOTIFUICATIONS

// Add this function to send notification to user
function sendNotification($user_id, $title, $message, $type = 'info') {
    try {
        $conn = new PDO("mysql:host=fdb1032.awardspace.net;dbname=4761973_barbing", "4761973_barbing", "Hello123###");
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)");
        $stmt->execute([$user_id, $title, $message, $type]);
        
        return true;
    } catch(PDOException $e) {
        return false;
    }
}

// Update the updateAppointmentStatus function
function updateAppointmentStatus($id, $data) {
    try {
        $conn = new PDO("mysql:host=fdb1032.awardspace.net;dbname=4761973_barbing", "4761973_barbing", "Hello123###");
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Get appointment details before update
        $stmt = $conn->prepare("
            SELECT a.*, u.email, u.name as customer_name, b.name as barber_name, s.name as service_name
            FROM appointments a
            JOIN users u ON a.customer_id = u.id
            JOIN users b ON a.barber_id = b.id
            JOIN services s ON a.service_id = s.id
            WHERE a.id = ?
        ");
        $stmt->execute([$id]);
        $appointment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Update appointment status
        $updateStmt = $conn->prepare("UPDATE appointments SET status = ? WHERE id = ?");
        $updateStmt->execute([$data['status'], $id]);
        
        // Send notification based on status change
        $title = '';
        $message = '';
        $type = 'info';
        
        if ($data['status'] === 'confirmed') {
            $title = '✅ Appointment Confirmed!';
            $message = "Your appointment for {$appointment['service_name']} with {$appointment['barber_name']} on " . 
                       date('F j, Y', strtotime($appointment['appointment_date'])) . " at {$appointment['appointment_time']} has been CONFIRMED.";
            $type = 'success';
        } elseif ($data['status'] === 'cancelled') {
            $title = '❌ Appointment Cancelled';
            $message = "Your appointment for {$appointment['service_name']} on " . 
                       date('F j, Y', strtotime($appointment['appointment_date'])) . " has been CANCELLED.";
            $type = 'error';
        } elseif ($data['status'] === 'completed') {
            $title = '✨ Appointment Completed';
            $message = "Your appointment for {$appointment['service_name']} has been completed. Thank you for choosing us!";
            $type = 'success';
        }
        
        if ($title) {
            sendNotification($appointment['customer_id'], $title, $message, $type);
        }
        
        // Also send email notification (optional - requires mail server setup)
        // mail($appointment['email'], $title, $message);
        
        echo json_encode(['status' => 'success', 'message' => 'Appointment updated']);
    } catch(PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}

// Add function to send custom message from admin
function sendAdminMessage($data) {
    try {
        $conn = new PDO("mysql:host=fdb1032.awardspace.net;dbname=4761973_barbing", "4761973_barbing", "Hello123###");
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Send notification
        sendNotification($data['user_id'], $data['title'], $data['message'], 'admin');
        
        // Also update appointment admin_message if appointment_id provided
        if (isset($data['appointment_id'])) {
            $stmt = $conn->prepare("UPDATE appointments SET admin_message = ? WHERE id = ?");
            $stmt->execute([$data['message'], $data['appointment_id']]);
        }
        
        echo json_encode(['status' => 'success', 'message' => 'Message sent']);
    } catch(PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}

// Add function to get user notifications
function getUserNotifications($user_id) {
    try {
        $conn = new PDO("mysql:host=fdb1032.awardspace.net;dbname=4761973_barbing", "4761973_barbing", "Hello123###");
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $stmt = $conn->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC");
        $stmt->execute([$user_id]);
        $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['status' => 'success', 'data' => $notifications]);
    } catch(PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}

// Add function to mark notification as read
function markNotificationRead($id) {
    try {
        $conn = new PDO("mysql:host=fdb1032.awardspace.net;dbname=4761973_barbing", "4761973_barbing", "Hello123###");
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ?");
        $stmt->execute([$id]);
        
        echo json_encode(['status' => 'success', 'message' => 'Marked as read']);
    } catch(PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}

function getAllAppointments() {
    try {
        $conn = new PDO("mysql:host=fdb1032.awardspace.net;dbname=4761973_barbing", "4761973_barbing", "Hello123###");
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $stmt = $conn->query("
            SELECT a.*, c.name as customer_name, b.name as barber_name, s.name as service_name
            FROM appointments a
            JOIN users c ON a.customer_id = c.id
            JOIN users b ON a.barber_id = b.id
            JOIN services s ON a.service_id = s.id
            ORDER BY a.appointment_date DESC
        ");
        $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['status' => 'success', 'data' => $appointments]);
    } catch(PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}



function getAllServicesAdmin() {
    try {
        $conn = new PDO("mysql:host=fdb1032.awardspace.net;dbname=4761973_barbing", "4761973_barbing", "Hello123###");
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $stmt = $conn->query("SELECT * FROM services ORDER BY id DESC");
        $services = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['status' => 'success', 'data' => $services]);
    } catch(PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}

function createService($data) {
    try {
        $conn = new PDO("mysql:host=fdb1032.awardspace.net;dbname=4761973_barbing", "4761973_barbing", "Hello123###");
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $stmt = $conn->prepare("INSERT INTO services (name, description, price, duration_minutes, category, is_active) VALUES (?, ?, ?, ?, ?, 1)");
        $stmt->execute([$data['name'], $data['description'], $data['price'], $data['duration_minutes'], $data['category']]);
        
        echo json_encode(['status' => 'success', 'message' => 'Service created', 'id' => $conn->lastInsertId()]);
    } catch(PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}

function updateService($id, $data) {
    try {
        $conn = new PDO("mysql:host=fdb1032.awardspace.net;dbname=4761973_barbing", "4761973_barbing", "Hello123###");
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $stmt = $conn->prepare("UPDATE services SET name=?, description=?, price=?, duration_minutes=?, category=? WHERE id=?");
        $stmt->execute([$data['name'], $data['description'], $data['price'], $data['duration_minutes'], $data['category'], $id]);
        
        echo json_encode(['status' => 'success', 'message' => 'Service updated']);
    } catch(PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}

function deleteService($id) {
    try {
        $conn = new PDO("mysql:host=fdb1032.awardspace.net;dbname=4761973_barbing", "4761973_barbing", "Hello123###");
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $stmt = $conn->prepare("DELETE FROM services WHERE id = ?");
        $stmt->execute([$id]);
        
        echo json_encode(['status' => 'success', 'message' => 'Service deleted']);
    } catch(PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}

function getAllBarbersAdmin() {
    try {
        $conn = new PDO("mysql:host=fdb1032.awardspace.net;dbname=4761973_barbing", "4761973_barbing", "Hello123###");
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $stmt = $conn->query("
            SELECT b.*, u.name, u.email, u.phone 
            FROM barbers b 
            JOIN users u ON b.user_id = u.id 
            ORDER BY b.id DESC
        ");
        $barbers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['status' => 'success', 'data' => $barbers]);
    } catch(PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}

function createBarber($data) {
    try {
        $conn = new PDO("mysql:host=fdb1032.awardspace.net;dbname=4761973_barbing", "4761973_barbing", "Hello123###");
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // First create user
        $hashed_password = password_hash('password123', PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO users (name, email, phone, password, role) VALUES (?, ?, ?, ?, 'barber')");
        $stmt->execute([$data['name'], $data['email'], $data['phone'], $hashed_password]);
        $user_id = $conn->lastInsertId();
        
        // Then create barber
        $stmt = $conn->prepare("INSERT INTO barbers (user_id, bio, specialties, rating, commission_rate, is_active) VALUES (?, ?, ?, ?, ?, 1)");
        $stmt->execute([$user_id, $data['bio'], $data['specialties'], $data['rating'] ?? 4.5, $data['commission_rate'] ?? 50]);
        
        echo json_encode(['status' => 'success', 'message' => 'Barber created']);
    } catch(PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}

function updateBarber($id, $data) {
    try {
        $conn = new PDO("mysql:host=fdb1032.awardspace.net;dbname=4761973_barbing", "4761973_barbing", "Hello123###");
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $stmt = $conn->prepare("UPDATE barbers SET bio=?, specialties=?, rating=?, commission_rate=?, is_active=? WHERE id=?");
        $stmt->execute([$data['bio'], $data['specialties'], $data['rating'], $data['commission_rate'], $data['is_active'], $id]);
        
        echo json_encode(['status' => 'success', 'message' => 'Barber updated']);
    } catch(PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}

function deleteBarber($id) {
    try {
        $conn = new PDO("mysql:host=fdb1032.awardspace.net;dbname=4761973_barbing", "4761973_barbing", "Hello123###");
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Get user_id first
        $stmt = $conn->prepare("SELECT user_id FROM barbers WHERE id = ?");
        $stmt->execute([$id]);
        $barber = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($barber) {
            $stmt = $conn->prepare("DELETE FROM barbers WHERE id = ?");
            $stmt->execute([$id]);
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$barber['user_id']]);
        }
        
        echo json_encode(['status' => 'success', 'message' => 'Barber deleted']);
    } catch(PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}