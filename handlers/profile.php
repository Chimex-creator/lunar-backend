<?php

function uploadAvatar() {
    try {
        // Check if user is logged in via token
        $headers = getallheaders();
        $token = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : null;
        
        if (!$token) {
            echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
            return;
        }
        
        $userData = json_decode(base64_decode($token), true);
        $user_id = $userData['user_id'] ?? null;
        
        if (!$user_id) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid token']);
            return;
        }
        
        // Check if file was uploaded
        if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['status' => 'error', 'message' => 'No file uploaded']);
            return;
        }
        
        $file = $_FILES['avatar'];
        $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif'];
        
        if (!in_array($file['type'], $allowedTypes)) {
            echo json_encode(['status' => 'error', 'message' => 'Only JPG, PNG, GIF images allowed']);
            return;
        }
        
        if ($file['size'] > 2 * 1024 * 1024) {
            echo json_encode(['status' => 'error', 'message' => 'File size must be less than 2MB']);
            return;
        }
        
        // Create uploads directory if not exists
        $uploadDir = __DIR__ . '/../uploads/avatars/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        // Generate unique filename
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'avatar_' . $user_id . '_' . time() . '.' . $extension;
        $filepath = $uploadDir . $filename;
        
        // Move uploaded file
        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            // Save avatar path to database (optional - for persistence)
            $conn = new PDO("mysql:host=fdb1032.awardspace.net;dbname=4761973_barbing", "4761973_barbing", "Hello123###");
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            $stmt = $conn->prepare("UPDATE users SET avatar = ? WHERE id = ?");
            $stmt->execute([$filename, $user_id]);
            
            echo json_encode([
                'status' => 'success',
                'message' => 'Avatar uploaded successfully',
                'data' => ['avatar_url' => 'http://localhost/php-backend/uploads/avatars/' . $filename]
            ]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to upload file']);
        }
    } catch(PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}

function updateProfile($data) {
    try {
        $headers = getallheaders();
        $token = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : null;
        
        if (!$token) {
            echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
            return;
        }
        
        $userData = json_decode(base64_decode($token), true);
        $user_id = $userData['user_id'] ?? null;
        
        if (!$user_id) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid token']);
            return;
        }
        
        $conn = new PDO("mysql:host=fdb1032.awardspace.net;dbname=4761973_barbing", "4761973_barbing", "Hello123###");
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, phone = ?, bio = ?, location = ? WHERE id = ?");
        $stmt->execute([
            $data['name'],
            $data['email'],
            $data['phone'],
            $data['bio'] ?? '',
            $data['location'] ?? '',
            $user_id
        ]);
        
        echo json_encode(['status' => 'success', 'message' => 'Profile updated']);
    } catch(PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}

function getProfile() {
    try {
        $headers = getallheaders();
        $token = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : null;
        
        if (!$token) {
            echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
            return;
        }
        
        $userData = json_decode(base64_decode($token), true);
        $user_id = $userData['user_id'] ?? null;
        
        if (!$user_id) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid token']);
            return;
        }
        
        $conn = new PDO("mysql:host=fdb1032.awardspace.net;dbname=4761973_barbing", "4761973_barbing", "Hello123###");
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $stmt = $conn->prepare("SELECT id, name, email, phone, role, avatar, bio, location, created_at FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && $user['avatar']) {
            $user['avatar_url'] = 'http://localhost/php-backend/uploads/avatars/' . $user['avatar'];
        }
        
        echo json_encode(['status' => 'success', 'data' => $user]);
    } catch(PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}